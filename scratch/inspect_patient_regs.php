<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== DAFTAR PENDAFTARAN TIAP PASIEN ===\n";
$qP = $db->query("SELECT id_pasien, nama_lengkap, reward_poin FROM pasien");
while ($pas = $qP->fetch_assoc()) {
    $id = (int)$pas['id_pasien'];
    $qReg = $db->query("SELECT id_pendaftaran, jenis_pasien, jenis_perawatan, keluhan, id_poli, status_pendaftaran, tanggal_kunjungan 
                        FROM pendaftaran WHERE id_pasien = $id");
    echo "Pasien #{$id} ({$pas['nama_lengkap']}) - Saldo di DB saat ini: {$pas['reward_poin']} Poin\n";
    $countReg = 0;
    while ($r = $qReg->fetch_assoc()) {
        $countReg++;
        echo "  - Reg #{$r['id_pendaftaran']}: Tgl={$r['tanggal_kunjungan']}, Jenis={$r['jenis_pasien']}, Perawatan='{$r['jenis_perawatan']}', Keluhan='{$r['keluhan']}', Poli={$r['id_poli']}, Status={$r['status_pendaftaran']}\n";
    }
    if ($countReg === 0) {
        echo "  (Tidak ada pendaftaran di tabel pendaftaran)\n";
    }
    echo "--------------------------------------------------------\n";
}
