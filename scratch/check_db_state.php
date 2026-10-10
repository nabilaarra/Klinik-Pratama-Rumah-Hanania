<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query("SELECT * FROM pengaturan_konten WHERE kunci LIKE 'navbar%'");
while($row = $r->fetch_assoc()) {
    echo $row['kunci'] . ' => ' . $row['nilai'] . PHP_EOL;
}
