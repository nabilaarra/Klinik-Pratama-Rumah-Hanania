<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== JADWAL POLI 5 (PERSALINAN 24 JAM) ===\n";
$rB = $db->query("SELECT j.*, b.nama_bidan FROM jadwal_bidan j JOIN bidan b ON b.id_bidan = j.id_bidan WHERE j.id_poli = 5");
echo "Bidan: " . $rB->num_rows . " jadwal\n";
while ($row = $rB->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

$rD = $db->query("SELECT j.*, d.nama_dokter FROM jadwal_dokter j JOIN dokter d ON d.id_dokter = j.id_dokter WHERE j.id_poli = 5");
echo "Dokter: " . $rD->num_rows . " jadwal\n";
while ($row = $rD->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== SEMUA BIDAN ===\n";
$allB = $db->query("SELECT id_bidan, nama_bidan, id_poli, status FROM bidan");
while ($row = $allB->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
