<?php
require_once __DIR__ . '/../config/database.php';
echo "=== DOKTER ===\n";
$r = $conn->query("SELECT id_dokter, nama_dokter, status FROM dokter");
while ($row = $r->fetch_assoc()) {
    echo $row['id_dokter'] . " | " . $row['nama_dokter'] . " | " . $row['status'] . "\n";
}
echo "\n=== BIDAN ===\n";
$r = $conn->query("SELECT id_bidan, nama_bidan, status FROM bidan");
while ($row = $r->fetch_assoc()) {
    echo $row['id_bidan'] . " | " . $row['nama_bidan'] . " | " . $row['status'] . "\n";
}
