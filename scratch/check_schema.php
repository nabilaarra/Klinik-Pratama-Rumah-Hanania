<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$tables = ['users', 'pasien', 'fasilitas_klinik', 'pengaturan_konten'];
foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $res = $db->query("DESCRIBE `$t`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo " - {$row['Field']} ({$row['Type']}) " . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . " Default: {$row['Default']}\n";
        }
    } else {
        echo "Error: " . $db->error . "\n";
    }
}
