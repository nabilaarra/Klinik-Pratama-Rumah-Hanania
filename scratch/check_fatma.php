<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('SELECT * FROM jadwal_dokter WHERE id_dokter = 3');
echo "Jumlah jadwal drg Fatmasari: " . $r->num_rows . "\n";
while($row = $r->fetch_assoc()) {
    print_r($row);
}
$allJ = $conn->query('SELECT * FROM jadwal_dokter');
echo "\nTotal semua jadwal dokter di DB: " . $allJ->num_rows . "\n";
while($row = $allJ->fetch_assoc()) {
    echo "- Dokter ID: {$row['id_dokter']} | Poli ID: {$row['id_poli']} | Hari: {$row['hari']} | Jam: {$row['jam_mulai']}-{$row['jam_selesai']} | Status: {$row['status']}\n";
}
