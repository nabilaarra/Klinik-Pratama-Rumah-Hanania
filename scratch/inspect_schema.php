<?php
require_once __DIR__ . '/../config/database.php';

$tables = ['pasien', 'penukaran_poin', 'pendaftaran', 'antrean', 'pengaturan_konten'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    $res = $conn->query("DESCRIBE `$t`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo "  " . str_pad($r['Field'], 24) . " " . str_pad($r['Type'], 20) . " Null:" . $r['Null'] . " Key:" . $r['Key'] . " Default:" . ($r['Default'] ?? 'NULL') . "\n";
        }
    } else {
        echo "  Table does not exist or error: " . $conn->error . "\n";
    }
    echo "\n";
}
