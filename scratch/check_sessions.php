<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== CHECK MULTIPLE SESSIONS ON SAME DAY ===\n";
$q1 = $db->query("
    SELECT id_dokter, hari, COUNT(*) as cnt, GROUP_CONCAT(CONCAT(jam_mulai, '-', jam_selesai)) as jams
    FROM jadwal_dokter
    GROUP BY id_dokter, hari
    HAVING cnt > 1
");
print_r($q1->fetch_all(MYSQLI_ASSOC));

$q2 = $db->query("
    SELECT id_bidan, hari, COUNT(*) as cnt, GROUP_CONCAT(CONCAT(jam_mulai, '-', jam_selesai)) as jams
    FROM jadwal_bidan
    GROUP BY id_bidan, hari
    HAVING cnt > 1
");
print_r($q2->fetch_all(MYSQLI_ASSOC));
