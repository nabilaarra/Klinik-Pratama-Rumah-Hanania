<?php
require 'config/database.php';
$q = $conn->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE '%wa%' OR kunci LIKE '%kontak%'");
while ($r = $q->fetch_assoc()) {
    echo $r['kunci'] . ' => ' . $r['nilai'] . PHP_EOL;
}
