<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query('DESCRIBE riwayat_poin');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['Field'] . ' (' . $r['Type'] . ")\n";
    }
} else {
    echo "Table riwayat_poin does not exist: " . $db->error . "\n";
}
