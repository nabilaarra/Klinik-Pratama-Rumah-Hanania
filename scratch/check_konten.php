<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT kunci, LEFT(nilai, 60) as snippet FROM pengaturan_konten ORDER BY kunci");
while ($r = $res->fetch_assoc()) {
    echo str_pad($r['kunci'], 30) . " => " . $r['snippet'] . "\n";
}
