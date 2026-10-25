<?php
/**
 * Helper Nomor Antrean Klinik Pratama Rumah Hanania
 * 
 * Aturan Urutan Nomor Antrean:
 * Mengikuti siklus pola setiap 5 nomor:
 * - 1 sampai 2 : Pasien OFFLINE (Walk-in langsung di klinik)
 * - 3 sampai 5 : Pasien ONLINE  (Pendaftaran lewat website)
 * - 6 sampai 7 : Pasien OFFLINE
 * - 8 sampai 10: Pasien ONLINE
 * - 11 sampai 12: Pasien OFFLINE
 * - 13 sampai 15: Pasien ONLINE
 * ... dan seterusnya secara konsisten.
 */

if (!function_exists('isNomorAntreanOffline')) {
    /**
     * Memeriksa apakah suatu nomor antrean dialokasikan untuk pasien OFFLINE
     * Pola: nomor % 5 bernilai 1 atau 2 (contoh: 1, 2, 6, 7, 11, 12, 16, 17, dst)
     * 
     * @param int $nomor
     * @return bool
     */
    function isNomorAntreanOffline($nomor) {
        $nomor = (int)$nomor;
        if ($nomor <= 0) return false;
        $rem = $nomor % 5;
        return ($rem === 1 || $rem === 2);
    }
}

if (!function_exists('isNomorAntreanOnline')) {
    /**
     * Memeriksa apakah suatu nomor antrean dialokasikan untuk pasien ONLINE
     * Pola: nomor % 5 bernilai 3, 4, atau 0 (contoh: 3, 4, 5, 8, 9, 10, 13, 14, 15, dst)
     * 
     * @param int $nomor
     * @return bool
     */
    function isNomorAntreanOnline($nomor) {
        $nomor = (int)$nomor;
        if ($nomor <= 0) return false;
        $rem = $nomor % 5;
        return ($rem === 3 || $rem === 4 || $rem === 0);
    }
}

if (!function_exists('getNomorAntreanChannel')) {
    /**
     * Mengembalikan channel ('offline' atau 'online') berdasarkan nomor antrean
     * 
     * @param int $nomor
     * @return string
     */
    function getNomorAntreanChannel($nomor) {
        return isNomorAntreanOffline($nomor) ? 'offline' : 'online';
    }
}

if (!function_exists('getAntreanNomorByUrutan')) {
    /**
     * Menghitung nilai nomor antrean ke-N untuk channel tertentu (1-indexed)
     * Contoh:
     * - Online ke-1  -> 3
     * - Online ke-2  -> 4
     * - Online ke-3  -> 5
     * - Online ke-4  -> 8
     * - Online ke-5  -> 9
     * - Online ke-6  -> 10
     * - Offline ke-1 -> 1
     * - Offline ke-2 -> 2
     * - Offline ke-3 -> 6
     * - Offline ke-4 -> 7
     * 
     * @param int $urutan (1, 2, 3, dst)
     * @param string $channel 'online' atau 'offline'
     * @return int
     */
    function getAntreanNomorByUrutan($urutan, $channel = 'online') {
        $urutan = max(1, (int)$urutan);
        if ($channel === 'offline') {
            $block = (int)floor(($urutan - 1) / 2);
            $offset = ($urutan - 1) % 2;
            return ($block * 5) + 1 + $offset;
        } else {
            $block = (int)floor(($urutan - 1) / 3);
            $offset = ($urutan - 1) % 3;
            return ($block * 5) + 3 + $offset;
        }
    }
}

if (!function_exists('hitungNomorAntreanBerikutnya')) {
    /**
     * Menghitung nomor antrean berikutnya yang belum terpakai pada hari, poli, dan staf tersebut.
     * Mengikuti pola 1-2 offline, 3-5 online, 6-7 offline, 8-10 online, dst.
     * 
     * @param mysqli $db
     * @param string $tanggalKunjungan Format 'YYYY-MM-DD'
     * @param int $idPoli
     * @param int|null $idDokter
     * @param int|null $idBidan
     * @param string $channel 'online' atau 'offline'
     * @param string $jamMulaiOrSesi Format 'HH:MM' atau string sesi
     * @return array ['nomor' => int, 'format' => string, 'channel' => string]
     */
    function hitungNomorAntreanBerikutnya($db, $tanggalKunjungan, $idPoli, $idDokter = null, $idBidan = null, $channel = 'online', $jamMulaiOrSesi = '') {
        $channel = strtolower(trim((string)$channel)) === 'offline' ? 'offline' : 'online';
        $idPoli = (int)$idPoli;
        $idDokter = (int)$idDokter;
        $idBidan = (int)$idBidan;
        
        // Filter sesi berdasarkan jam mulai atau nama sesi jika tersedia
        $sessionFilterSql = "";
        if ($jamMulaiOrSesi !== '') {
            $clean = strtolower(trim((string)$jamMulaiOrSesi));
            if (strpos($clean, 'sore') !== false || strpos($clean, 'malam') !== false || strpos($clean, 'siang') !== false) {
                $sessionFilterSql = "AND p.jam_kunjungan >= '13:00:00'";
            } elseif (strpos($clean, 'pagi') !== false) {
                $sessionFilterSql = "AND p.jam_kunjungan < '13:00:00'";
            } elseif (is_numeric(substr($clean, 0, 2))) {
                $startH = (int)substr($clean, 0, 2);
                $sessionFilterSql = ($startH >= 13) ? "AND p.jam_kunjungan >= '13:00:00'" : "AND p.jam_kunjungan < '13:00:00'";
            }
        }
        
        // Filter dokter / bidan
        $staffSql = "";
        $types = "si";
        $params = [$tanggalKunjungan, $idPoli];
        
        if ($idDokter > 0) {
            $staffSql = "AND p.id_dokter = ?";
            $types .= "i";
            $params[] = $idDokter;
        } elseif ($idBidan > 0) {
            $staffSql = "AND p.id_bidan = ?";
            $types .= "i";
            $params[] = $idBidan;
        }
        
        $sql = "
            SELECT a.nomor_antrean
            FROM antrean a
            JOIN pendaftaran p ON p.id_pendaftaran = a.id_pendaftaran
            WHERE p.tanggal_kunjungan = ?
              AND p.id_poli = ?
              AND p.status_pendaftaran != 'dibatalkan'
              $staffSql
              $sessionFilterSql
        ";
        
        $usedNumbers = [];
        if ($stmt = $db->prepare($sql)) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $no = (int)($row['nomor_antrean'] ?? 0);
                if ($no > 0) {
                    $usedNumbers[$no] = true;
                }
            }
            $stmt->close();
        }
        
        // Cari nomor terkecil yang sesuai channel dan belum digunakan
        $candidate = 1;
        $nomorTerpilih = ($channel === 'offline') ? 1 : 3;
        
        while ($candidate <= 2000) {
            $matchChannel = ($channel === 'offline') 
                ? isNomorAntreanOffline($candidate) 
                : isNomorAntreanOnline($candidate);
                
            if ($matchChannel && !isset($usedNumbers[$candidate])) {
                $nomorTerpilih = $candidate;
                break;
            }
            $candidate++;
        }
        
        return [
            'nomor'   => $nomorTerpilih,
            'format'  => str_pad((string)$nomorTerpilih, 3, '0', STR_PAD_LEFT),
            'channel' => $channel
        ];
    }
}
