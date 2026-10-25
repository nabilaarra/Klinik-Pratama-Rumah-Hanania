<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query("SELECT COUNT(*) as total FROM riwayat_poin");
echo "Total riwayat_poin rows: " . $res->fetch_assoc()["total"] . "\n";
$sample = $db->query("SELECT r.*, p.nama_lengkap FROM riwayat_poin r JOIN pasien p ON r.id_pasien = p.id_pasien ORDER BY r.id_riwayat DESC LIMIT 6");
while ($s = $sample->fetch_assoc()) {
    echo "#" . $s["id_riwayat"] . " [" . $s["nama_lengkap"] . "] " . $s["jenis"] . " " . $s["jumlah_poin"] . " pts (Saldo: " . $s["saldo_akhir"] . ") -> " . $s["keterangan"] . "\n";
}
