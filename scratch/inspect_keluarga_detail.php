<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== DATA TABEL KELUARGA_PASIEN ===\n\n";
$res = $db->query("SELECT id_keluarga, id_user, nama_lengkap, hubungan, nik, nama_ibu_kandung, nama_suami FROM keluarga_pasien");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
