<?php
require_once __DIR__ . '/../config/database.php';
echo "=== POLI ===\n";
$res = $conn->query("SELECT * FROM poli");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "=== PENGATURAN FORM PENDAFTARAN ===\n";
$res = $conn->query("SELECT * FROM pengaturan_form_pendaftaran ORDER BY urutan ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo json_encode($r) . "\n";
    }
} else {
    echo "No table or error: " . $conn->error . "\n";
}

echo "=== JADWAL POLI / DOKTER / BIDAN ===\n";
$res = $conn->query("SELECT j.*, d.nama_dokter, p.nama_poli FROM jadwal_dokter j JOIN dokter d ON j.id_dokter=d.id_dokter JOIN poli p ON j.id_poli=p.id_poli");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
$res = $conn->query("SELECT j.*, b.nama_bidan, p.nama_poli FROM jadwal_bidan j JOIN bidan b ON j.id_bidan=b.id_bidan JOIN poli p ON j.id_poli=p.id_poli");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
