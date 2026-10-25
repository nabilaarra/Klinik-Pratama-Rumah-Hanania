<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT * FROM tindakan_pendaftaran ORDER BY urutan ASC");
echo "=== TINDAKAN PENDAFTARAN ===\n";
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
