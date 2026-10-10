<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT id_user, username, role FROM users WHERE role = 'admin' OR username = 'admin'");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
// Also delete any test rows in dokter
$conn->query("DELETE FROM dokter WHERE nama_dokter LIKE '%Simulasi%' OR nama_dokter LIKE '%Test%'");
