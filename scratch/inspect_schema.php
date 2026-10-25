<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "--- JADWAL DOKTER COLUMNS ---\n";
$r = $db->query('SHOW COLUMNS FROM jadwal_dokter');
while($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "\n--- JADWAL BIDAN COLUMNS ---\n";
$r = $db->query('SHOW COLUMNS FROM jadwal_bidan');
while($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "\n--- JADWAL POLI 5 ROWS ---\n";
$r = $db->query('SELECT * FROM jadwal_bidan WHERE id_poli = 5');
while($row = $r->fetch_assoc()) print_r($row);
