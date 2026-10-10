<?php
require_once __DIR__ . '/../config/database.php';

$tables = ['dokter', 'bidan', 'jadwal_dokter', 'jadwal_bidan'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    $res = $conn->query("DESCRIBE `$t`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo "  " . str_pad($r['Field'], 24) . " " . str_pad($r['Type'], 20) . "\n";
        }
        $count = $conn->query("SELECT COUNT(*) as c FROM `$t`")->fetch_assoc()['c'];
        echo "  Total rows: $count\n";
    }
}
