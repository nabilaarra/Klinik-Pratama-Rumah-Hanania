<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== DAFTAR 10 PASIEN EXISTING ===\n\n";
$res = $db->query("SELECT id_pasien, id_user, nama_lengkap, nik, jenis_kelamin, no_telepon, nama_ibu_kandung, nama_suami FROM pasien ORDER BY id_pasien ASC");
while ($r = $res->fetch_assoc()) {
    echo sprintf(
        "ID: %02d | User: %s | NIK: %-16s | Nama: %-22s | JK: %s | Ibu: %-15s | Suami: %-15s\n",
        $r['id_pasien'],
        $r['id_user'] ?? 'NULL',
        $r['nik'] ?? '-',
        $r['nama_lengkap'],
        $r['jenis_kelamin'],
        $r['nama_ibu_kandung'] ?? '-',
        $r['nama_suami'] ?? '-'
    );
}
