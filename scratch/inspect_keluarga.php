<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("DESCRIBE keluarga_pasien");
echo "=== KELUARGA_PASIEN ===\n";
while ($r = $res->fetch_assoc()) {
    echo "{$r['Field']} - {$r['Type']} (Null: {$r['Null']}, Default: {$r['Default']})\n";
}
