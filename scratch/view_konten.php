<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT kunci, LEFT(nilai, 100) AS val FROM pengaturan_konten");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['kunci'] . " => " . $r['val'] . "\n";
    }
}
