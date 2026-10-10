<?php
require __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE '%footer%' OR kunci LIKE '%kontak%' OR kunci LIKE '%alamat%' OR kunci LIKE '%wa%'");
while ($r = $res->fetch_assoc()) {
    echo $r['kunci'] . ' => ' . $r['nilai'] . PHP_EOL;
}
