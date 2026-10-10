<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

try {
    $stmt = $db->prepare('INSERT INTO dokter (nama_dokter, id_poli, spesialisasi, no_telepon, foto, status) VALUES (?, ?, ?, ?, ?, ?)');
    $nama = 'Test Null Poli';
    $valPoli = null;
    $sp = 'Umum';
    $telp = '';
    $foto = '';
    $st = 'aktif';
    $stmt->bind_param('sissss', $nama, $valPoli, $sp, $telp, $foto, $st);
    $stmt->execute();
    $id = $stmt->insert_id;
    echo "Inserted with null poli: ID $id\n";
    
    // Check what was stored in database
    $res = $db->query("SELECT id_poli FROM dokter WHERE id_dokter = $id");
    $r = $res->fetch_assoc();
    var_dump($r);
    
    $db->query("DELETE FROM dokter WHERE id_dokter = $id");
    echo "Deleted test row\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
