<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$q = $db->query("SELECT * FROM pengaturan_konten WHERE kunci LIKE '%poin%'");
while($row = $q->fetch_assoc()) {
    echo "{$row['kunci']} = {$row['nilai']}\n";
}
