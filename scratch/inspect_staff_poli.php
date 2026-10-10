<?php
require_once __DIR__ . '/../config/database.php';
$p = $conn->query("SELECT id_poli, nama_poli FROM poli ORDER BY id_poli");
echo "=== POLI ===\n";
while ($r = $p->fetch_assoc()) {
    echo $r['id_poli'] . ": " . $r['nama_poli'] . "\n";
}

$d = $conn->query("SELECT id_dokter, nama_dokter, spesialisasi, id_poli FROM dokter ORDER BY id_dokter");
echo "\n=== DOKTER ===\n";
while ($r = $d->fetch_assoc()) {
    echo $r['id_dokter'] . ": " . $r['nama_dokter'] . " | " . $r['spesialisasi'] . " | id_poli: " . ($r['id_poli'] ?? 'NULL') . "\n";
}

$b = $conn->query("SELECT id_bidan, nama_bidan, id_poli FROM bidan ORDER BY id_bidan");
echo "\n=== BIDAN ===\n";
while ($r = $b->fetch_assoc()) {
    echo $r['id_bidan'] . ": " . $r['nama_bidan'] . " | id_poli: " . ($r['id_poli'] ?? 'NULL') . "\n";
}
