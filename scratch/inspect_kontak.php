<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT * FROM pengaturan_konten LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r(array_keys($r));
        break;
    }
    $all = $conn->query("SELECT * FROM pengaturan_konten");
    while ($r = $all->fetch_assoc()) {
        if (strpos($r['kunci'], 'kontak') !== false || strpos($r['kunci'], 'alamat') !== false || strpos($r['kunci'], 'wa') !== false || strpos($r['kunci'], 'footer') !== false) {
            echo $r['kunci'] . ' => ' . $r['nilai'] . "\n";
        }
    }
}
