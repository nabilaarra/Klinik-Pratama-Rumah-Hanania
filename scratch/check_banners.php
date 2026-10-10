<?php
require __DIR__ . '/../config/database.php';
$r = $conn->query("SELECT id_banner, judul, subjudul, foto, status FROM banner_hero");
while ($row = $r->fetch_assoc()) {
    echo $row['id_banner'] . " | " . $row['foto'] . " | " . $row['status'] . "\n";
    $p = __DIR__ . '/../' . ltrim($row['foto'], '/');
    if (file_exists($p)) {
        $info = getimagesize($p);
        echo "   Size: " . $info[0] . "x" . $info[1] . "\n";
    }
}
$q1 = $conn->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'hero_%'");
while ($row = $q1->fetch_assoc()) {
    echo $row['kunci'] . " => " . $row['nilai'] . "\n";
}
