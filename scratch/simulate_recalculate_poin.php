<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
require_once __DIR__ . '/../includes/poin_helper.php';

$qP = $db->query("SELECT id_pasien, nama_lengkap, reward_poin FROM pasien ORDER BY id_pasien ASC");
echo "=== SIMULASI PERHITUNGAN ULANG POIN DARI KUNJUNGAN AKTUAL ===\n";

while ($pas = $qP->fetch_assoc()) {
    $id = (int)$pas['id_pasien'];
    $qReg = $db->query("SELECT * FROM pendaftaran WHERE id_pasien = $id AND status_pendaftaran != 'dibatalkan'");
    $totalPoin = 0;
    $detail = [];
    while ($r = $qReg->fetch_assoc()) {
        $calc = hitungPoinKunjungan($db, $r['jenis_pasien'], $r['jenis_perawatan'], $r['keluhan'], (int)$r['id_poli']);
        $totalPoin += $calc['poin'];
        $detail[] = "Reg #{$r['id_pendaftaran']} ({$calc['tipe']} = +{$calc['poin']})";
    }

    echo "Pasien #{$id} {$pas['nama_lengkap']}:\n";
    echo "  - Poin Database Saat Ini : {$pas['reward_poin']} Poin\n";
    echo "  - Total Poin Sesuai Kunjungan : {$totalPoin} Poin\n";
    echo "  - Detail Kunjungan: " . (empty($detail) ? "Tidak ada kunjungan" : implode(", ", $detail)) . "\n\n";
}
