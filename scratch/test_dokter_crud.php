<?php
require_once __DIR__ . '/../config/database.php';

echo "=== DOKTER ROWS ===\n";
$res = $conn->query("SELECT * FROM dokter");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== BIDAN ROWS ===\n";
$res = $conn->query("SELECT * FROM bidan");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== POLI ROWS ===\n";
$res = $conn->query("SELECT * FROM poli");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
