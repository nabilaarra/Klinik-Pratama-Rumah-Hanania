<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== POLI COLUMNS ===\n";
$r = $db->query("SHOW COLUMNS FROM poli");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Default: " . var_export($row['Default'], true) . "\n";
}

echo "\n=== POLI DATA ===\n";
$r = $db->query("SELECT id_poli, nama_poli, status_operasional, is_24jam, status FROM poli");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== JADWAL_DOKTER COLUMNS ===\n";
$r = $db->query("SHOW COLUMNS FROM jadwal_dokter");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Default: " . var_export($row['Default'], true) . "\n";
}

echo "\n=== JADWAL_BIDAN COLUMNS ===\n";
$r = $db->query("SHOW COLUMNS FROM jadwal_bidan");
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - Default: " . var_export($row['Default'], true) . "\n";
}
