<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/antrean_helper.php';
$db = $conn ?? $koneksi;

$res = $db->query("
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.id_poli, p.id_dokter, p.id_bidan, p.sumber_pendaftaran, p.status_pendaftaran, p.jam_kunjungan
    FROM pendaftaran p 
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran 
    WHERE a.id_antrean IS NULL 
    ORDER BY p.tanggal_kunjungan ASC, p.id_pendaftaran ASC
");

echo "=== PENDAFTARAN TANPA ANTREAN ===\n";
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
