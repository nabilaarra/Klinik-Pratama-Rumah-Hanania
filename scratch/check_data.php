<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "--- USERS ---\n";
$u = $db->query("SELECT id_user, username, role, created_at FROM users");
while ($r = $u->fetch_assoc()) {
    echo "ID: {$r['id_user']} | User: {$r['username']} | Role: {$r['role']} | Created: {$r['created_at']}\n";
}

echo "\n--- PASIEN COUNT ---\n";
$resP = $db->query("SELECT count(*) as total, count(id_user) as with_user FROM pasien");
$rowP = $resP->fetch_assoc();
echo "Total Pasien: {$rowP['total']}, Pasien punya Akun: {$rowP['with_user']}\n";

echo "\n--- FASILITAS ---\n";
$f = $db->query("SELECT id_fasilitas, nama_fasilitas, urutan, foto FROM fasilitas_klinik ORDER BY urutan ASC");
while ($r = $f->fetch_assoc()) {
    echo "ID: {$r['id_fasilitas']} | Urutan: {$r['urutan']} | Nama: {$r['nama_fasilitas']} | Foto: {$r['foto']}\n";
}
