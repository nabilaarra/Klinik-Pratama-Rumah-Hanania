<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query("SELECT id_pasien, nama_lengkap, reward_poin FROM pasien");
while ($r = $res->fetch_assoc()) {
    echo "#{$r['id_pasien']} {$r['nama_lengkap']} => {$r['reward_poin']} Poin\n";
}
