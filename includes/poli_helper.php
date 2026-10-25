<?php
/**
 * Helper Pengaturan Operasional & Status Buka/Tutup Poli
 * Klinik Pratama Rumah Hanania
 * 
 * Mengatur:
 * 1. Logika penutupan pendaftaran 1 jam sebelum jam poli selesai (atau custom cutoff).
 * 2. Layanan Persalinan 24 Jam yang tidak pernah ditutup (selalu tersedia).
 * 3. Override manual status buka/tutup oleh Admin.
 */

if (!function_exists('getAllPoliMap')) {
    /**
     * Mengambil data semua poli indexed by id_poli
     * @param mysqli $db
     * @return array
     */
    function getAllPoliMap($db) {
        static $cachedPoli = null;
        if ($cachedPoli !== null) {
            return $cachedPoli;
        }
        $cachedPoli = [];
        if (!$db instanceof mysqli) {
            return $cachedPoli;
        }
        $res = $db->query("SELECT * FROM poli ORDER BY id_poli ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cachedPoli[(int)$row['id_poli']] = $row;
            }
        }
        return $cachedPoli;
    }
}

if (!function_exists('isPoliPersalinan24Jam')) {
    /**
     * Memeriksa apakah suatu poli atau layanan adalah Persalinan 24 Jam
     * @param array|int|string $poliDataOrId
     * @param string $namaTindakan
     * @return bool
     */
    function isPoliPersalinan24Jam($poliDataOrId, $namaTindakan = '') {
        $tindakanLower = strtolower(trim((string)$namaTindakan));
        if (strpos($tindakanLower, 'persalinan') !== false || strpos($tindakanLower, 'bersalin') !== false) {
            return true;
        }
        if (is_numeric($poliDataOrId) && (int)$poliDataOrId === 5) {
            return true;
        }
        if (is_array($poliDataOrId)) {
            if (!empty($poliDataOrId['is_24jam'])) return true;
            if (isset($poliDataOrId['id_poli']) && (int)$poliDataOrId['id_poli'] === 5) return true;
            $namaPoli = strtolower(trim((string)($poliDataOrId['nama_poli'] ?? '')));
            if (strpos($namaPoli, 'persalinan') !== false || strpos($namaPoli, '24 jam') !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('cekStatusJadwalPoli')) {
    /**
     * Memeriksa status ketersediaan jadwal poli.
     * 
     * Pilihan status:
     * 1. Pendaftaran Tersedia ('tersedia' / 'buka_manual')
     * 2. Pendaftaran Ditutup ('ditutup' / 'tutup_manual' / nonaktif)
     * 3. Full Booking ('full_booking')
     * 4. Otomatis ('otomatis'): Tersedia, dan otomatis "Pendaftaran Ditutup" 1 jam sebelum jadwal selesai.
     * 5. Persalinan 24 Jam: Selalu tersedia (tidak pernah ditutup).
     * 
     * @param array $poliData
     * @param string $jamMulai Format 'HH:MM' atau 'HH:MM:SS'
     * @param string $jamSelesai Format 'HH:MM' atau 'HH:MM:SS'
     * @param bool $isToday
     * @param string $namaTindakan
     * @return array
     */
    function cekStatusJadwalPoli($poliData, $jamMulai, $jamSelesai, $isToday = true, $namaTindakan = '') {
        $nowTs = time();
        $todayDate = date('Y-m-d');
        
        $namaPoli = strtolower(trim((string)($poliData['nama_poli'] ?? '')));
        $is24Jam = isPoliPersalinan24Jam($poliData, $namaTindakan);
        
        // 1. Layanan Persalinan 24 Jam: Selalu Tersedia Tanpa Ditutup (Aturan Mutlak)
        if ($is24Jam) {
            $msg = 'Pendaftaran Tersedia (Layanan Persalinan 24 Jam Selalu Siaga Nonstop)';
            return [
                'is_closed' => false,
                'status_label' => 'Pendaftaran Tersedia',
                'badge_class' => 'status-24jam',
                'keterangan' => $msg,
                'message' => 'Layanan Persalinan 24 Jam siaga penuh setiap hari tanpa tutup.',
                'is_24jam' => true,
                'status_operasional' => 'tersedia'
            ];
        }

        // 2. Status Poli nonaktif di database
        if (isset($poliData['status']) && $poliData['status'] === 'nonaktif') {
            return [
                'is_closed' => true,
                'status_label' => 'Pendaftaran Ditutup',
                'badge_class' => 'status-tutup',
                'keterangan' => 'Pendaftaran Ditutup',
                'message' => 'Poli saat ini sedang dinonaktifkan.',
                'is_24jam' => false,
                'status_operasional' => 'ditutup'
            ];
        }
        
        $statusOperasional = $poliData['status_operasional'] ?? 'otomatis';
        
        // 3. Status Manual: Full Booking
        if ($statusOperasional === 'full_booking') {
            return [
                'is_closed' => true,
                'status_label' => 'Full Booking',
                'badge_class' => 'status-full',
                'keterangan' => 'Full Booking',
                'message' => 'Kuota pendaftaran antrean telah penuh (Full Booking).',
                'is_24jam' => false,
                'status_operasional' => 'full_booking'
            ];
        }

        // 4. Status Manual: Pendaftaran Ditutup
        if ($statusOperasional === 'ditutup' || $statusOperasional === 'tutup_manual') {
            return [
                'is_closed' => true,
                'status_label' => 'Pendaftaran Ditutup',
                'badge_class' => 'status-tutup',
                'keterangan' => 'Pendaftaran Ditutup',
                'message' => !empty($poliData['pesan_tutup']) ? $poliData['pesan_tutup'] : 'Pendaftaran Ditutup',
                'is_24jam' => false,
                'status_operasional' => 'ditutup'
            ];
        }
        
        // 5. Status Manual: Pendaftaran Tersedia (Buka Terus)
        if ($statusOperasional === 'tersedia' || $statusOperasional === 'buka_manual') {
            return [
                'is_closed' => false,
                'status_label' => 'Pendaftaran Tersedia',
                'badge_class' => 'status-tersedia',
                'keterangan' => 'Pendaftaran Tersedia',
                'message' => 'Pendaftaran dibuka secara khusus oleh admin klinik.',
                'is_24jam' => false,
                'status_operasional' => 'tersedia'
            ];
        }
        
        // 6. Mode Otomatis
        if (!$isToday) {
            return [
                'is_closed' => false,
                'status_label' => 'Pendaftaran Tersedia',
                'badge_class' => 'status-tersedia',
                'keterangan' => 'Pendaftaran Tersedia',
                'message' => 'Jadwal pelayanan tersedia untuk reservasi.',
                'is_24jam' => false,
                'status_operasional' => 'otomatis'
            ];
        }
        
        // Jika Hari Ini: Ditutup 1 jam sebelum jadwal poli selesai
        $cutoffMenit = isset($poliData['waktu_cutoff_menit']) && (int)$poliData['waktu_cutoff_menit'] > 0
            ? (int)$poliData['waktu_cutoff_menit']
            : 60; // default 1 jam (60 menit)
            
        $endTs = strtotime($todayDate . ' ' . $jamSelesai);
        $cutoffTs = $endTs - ($cutoffMenit * 60);
        
        if ($nowTs >= $cutoffTs) {
            $waktuCutoffStr = date('H:i', $cutoffTs);
            $waktuSelesaiStr = substr($jamSelesai, 0, 5);
            $waktuTeks = ($cutoffMenit >= 60) ? round($cutoffMenit / 60) . ' jam' : $cutoffMenit . ' menit';
            
            return [
                'is_closed' => true,
                'status_label' => 'Pendaftaran Ditutup',
                'badge_class' => 'status-tutup',
                'keterangan' => 'Pendaftaran Ditutup',
                'message' => "Pendaftaran Ditutup (Batas pendaftaran {$waktuTeks} sebelum jadwal berakhir pukul {$waktuSelesaiStr} WIB).",
                'is_24jam' => false,
                'status_operasional' => 'otomatis',
                'cutoff_time' => $waktuCutoffStr
            ];
        }
        
        return [
            'is_closed' => false,
            'status_label' => 'Pendaftaran Tersedia',
            'badge_class' => 'status-tersedia',
            'keterangan' => 'Pendaftaran Tersedia',
            'message' => 'Pendaftaran aktif dibuka hingga 1 jam sebelum jam praktik selesai.',
            'is_24jam' => false,
            'status_operasional' => 'otomatis'
        ];
    }
}
