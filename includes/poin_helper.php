<?php
/**
 * Helper Perhitungan & Konfigurasi Poin Reward Klinik Pratama Rumah Hanania
 * 
 * Aturan Default:
 * - Kunjungan BPJS = 1 poin
 * - Kunjungan biasa tanpa USG = 2 poin
 * - Kunjungan USG = 3 poin
 * - Kunjungan bersalin = 10 poin
 * 
 * Nilai dapat diubah sewaktu-waktu oleh Admin melalui halaman admin/poin.php.
 */

if (!function_exists('getPoinSetting')) {
    function getPoinSetting($db, $kunci, $default = 0) {
        if (!$db instanceof mysqli) {
            return $default;
        }
        $stmt = $db->prepare("SELECT nilai FROM pengaturan_konten WHERE kunci = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $kunci);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (isset($row['nilai']) && trim((string)$row['nilai']) !== '') {
                    $stmt->close();
                    return $row['nilai'];
                }
            }
            $stmt->close();
        }
        return $default;
    }
}

if (!function_exists('setPoinSetting')) {
    function setPoinSetting($db, $kunci, $nilai) {
        if (!$db instanceof mysqli) {
            return false;
        }
        $stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        if ($stmt) {
            $stmt->bind_param('ss', $kunci, $nilai);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }
        return false;
    }
}

if (!function_exists('hitungPoinKunjungan')) {
    /**
     * Menghitung pendapatan poin reward untuk kunjungan pasien berdasarkan kategori.
     * 
     * @param mysqli $db
     * @param string $jenisPasien 'BPJS' atau 'Umum'
     * @param string $jenisPerawatan e.g. 'USG 2D', 'Persalinan 24 Jam', 'Pemeriksaan Medis Umum', dll.
     * @param string $keluhan
     * @param int $idPoli
     * @return array ['poin' => int, 'tipe' => string, 'kode' => string]
     */
    function hitungPoinKunjungan($db, $jenisPasien, $jenisPerawatan, $keluhan = '', $idPoli = 0) {
        $poinBpjs     = (int)getPoinSetting($db, 'poin_kunjungan_bpjs', 1);
        $poinBiasa    = (int)getPoinSetting($db, 'poin_kunjungan_biasa', 2);
        $poinUsg      = (int)getPoinSetting($db, 'poin_kunjungan_usg', 3);
        $poinBersalin = (int)getPoinSetting($db, 'poin_kunjungan_bersalin', 10);

        $jp  = strtolower(trim((string)$jenisPerawatan));
        $kel = strtolower(trim((string)$keluhan));
        $jPas = strtoupper(trim((string)$jenisPasien));

        // 1. Kunjungan Bersalin / Persalinan (10 Poin)
        $isBersalin = (
            strpos($jp, 'bersalin') !== false ||
            strpos($jp, 'persalinan') !== false ||
            strpos($jp, 'melahirkan') !== false ||
            strpos($kel, 'bersalin') !== false ||
            strpos($kel, 'persalinan') !== false ||
            strpos($kel, 'melahirkan') !== false
        );

        if ($isBersalin) {
            return [
                'poin' => $poinBersalin,
                'tipe' => 'Kunjungan Bersalin',
                'kode' => 'bersalin'
            ];
        }

        // 2. Kunjungan USG (3 Poin)
        $isUsg = (
            strpos($jp, 'usg') !== false ||
            strpos($kel, 'usg') !== false ||
            (int)$idPoli === 4 // Poli Spesialis Kebidanan & Kandungan (Dr. Sp.OG)
        );

        if ($isUsg) {
            return [
                'poin' => $poinUsg,
                'tipe' => 'Kunjungan USG',
                'kode' => 'usg'
            ];
        }

        // 3. Kunjungan BPJS (1 Poin)
        if ($jPas === 'BPJS') {
            return [
                'poin' => $poinBpjs,
                'tipe' => 'Kunjungan BPJS',
                'kode' => 'bpjs'
            ];
        }

        // 4. Kunjungan Biasa Tanpa USG (2 Poin)
        return [
            'poin' => $poinBiasa,
            'tipe' => 'Kunjungan Biasa (Tanpa USG)',
            'kode' => 'biasa'
        ];
    }
}

if (!function_exists('getCustomPoinRules')) {
    /**
     * Mengambil daftar aturan/kategori poin tambahan yang dapat dikonfigurasi & ditambah di admin.
     * 
     * @param mysqli $db
     * @return array
     */
    function getCustomPoinRules($db) {
        $raw = getPoinSetting($db, 'poin_aturan_kustom_json', '');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }
        // Aturan bawaan jika admin belum menambahkan poin lainnya
        return [
            [
                'id' => 'rule_google_review',
                'nama' => 'Ulasan Google Review Bintang 5',
                'poin' => 1,
                'kategori' => 'review_google',
                'keterangan' => 'Apresiasi pemberian rating bintang 5 di Google Maps Hanania'
            ],
            [
                'id' => 'rule_imunisasi',
                'nama' => 'Layanan Imunisasi & Vaksinasi Anak',
                'poin' => 2,
                'kategori' => 'kunjungan_imunisasi',
                'keterangan' => 'Kunjungan pemberian imunisasi dan vaksinasi dasar'
            ],
            [
                'id' => 'rule_lab',
                'nama' => 'Pemeriksaan Laboratorium & Cek Darah',
                'poin' => 2,
                'kategori' => 'laboratorium',
                'keterangan' => 'Pemeriksaan laboratorium penunjang medis'
            ]
        ];
    }
}

if (!function_exists('saveCustomPoinRules')) {
    /**
     * Menyimpan daftar aturan/kategori poin tambahan ke database.
     * 
     * @param mysqli $db
     * @param array $rules
     * @return bool
     */
    function saveCustomPoinRules($db, array $rules) {
        return setPoinSetting($db, 'poin_aturan_kustom_json', json_encode(array_values($rules), JSON_UNESCAPED_UNICODE));
    }
}

