<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "--- JADWAL DOKTER COLUMNS ---\n";
$r = $db->query('SHOW COLUMNS FROM jadwal_dokter');
while($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "\n--- JADWAL BIDAN COLUMNS ---\n";
$r = $db->query('SHOW COLUMNS FROM jadwal_bidan');
while($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "\n--- BIDAN DATA ---\n";
$r = $db->query('SELECT id_bidan, nama_bidan, id_poli, status FROM bidan');
while($row = $r->fetch_assoc()) echo json_encode($row) . "\n";

