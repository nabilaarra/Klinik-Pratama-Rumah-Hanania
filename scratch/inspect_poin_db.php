<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE '%poin%'");
while ($r = $res->fetch_assoc()) {
    echo "KEY: " . $r["kunci"] . "\nVALUE:\n" . $r["nilai"] . "\n-------------------------\n";
}
