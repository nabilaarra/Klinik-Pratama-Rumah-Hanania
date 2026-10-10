<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$tables = ['dokter', 'bidan', 'jadwal_dokter', 'jadwal_bidan', 'poli', 'pendaftaran', 'pengaturan_form_pendaftaran', 'banner_hero', 'pengaturan_konten'];
foreach ($tables as $t) {
    echo "=== TABLE $t ===\n";
    $res = $db->query("DESCRIBE $t");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo "  {$r['Field']} - {$r['Type']} (Null: {$r['Null']}, Default: {$r['Default']})\n";
        }
    } else {
        echo "  Table $t does not exist or error: " . $db->error . "\n";
    }
}
