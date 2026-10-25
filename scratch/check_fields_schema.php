<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query('DESCRIBE pengaturan_form_pendaftaran');
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' (' . $r['Type'] . ")\n";
}
