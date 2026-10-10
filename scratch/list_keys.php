<?php
require_once __DIR__ . '/../config/database.php';
$q = $conn->query("SELECT kunci, LEFT(nilai, 40) as n FROM pengaturan_konten ORDER BY kunci");
while ($r = $q->fetch_assoc()) {
    echo $r['kunci'] . " => " . $r['n'] . "\n";
}
