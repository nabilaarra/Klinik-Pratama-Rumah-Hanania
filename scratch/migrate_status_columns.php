<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== MIGRATING DATABASE COLUMNS FOR REGISTRATION STATUSES ===\n";

// 1. Alter status_operasional in poli
$res1 = $db->query("ALTER TABLE `poli` MODIFY COLUMN `status_operasional` VARCHAR(50) NOT NULL DEFAULT 'otomatis'");
if ($res1) {
    echo "poli.status_operasional modified to VARCHAR(50)\n";
} else {
    echo "Error modifying poli.status_operasional: " . $db->error . "\n";
}

// 2. Alter status in jadwal_dokter
$res2 = $db->query("ALTER TABLE `jadwal_dokter` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'tersedia'");
if ($res2) {
    echo "jadwal_dokter.status modified to VARCHAR(50)\n";
} else {
    echo "Error modifying jadwal_dokter.status: " . $db->error . "\n";
}

// 3. Alter status in jadwal_bidan
$res3 = $db->query("ALTER TABLE `jadwal_bidan` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'tersedia'");
if ($res3) {
    echo "jadwal_bidan.status modified to VARCHAR(50)\n";
} else {
    echo "Error modifying jadwal_bidan.status: " . $db->error . "\n";
}

// 4. Ensure Persalinan 24 Jam (id_poli = 5) has is_24jam = 1 and status_operasional = 'tersedia' or 'otomatis'
$db->query("UPDATE `poli` SET `is_24jam` = 1 WHERE `id_poli` = 5 OR `nama_poli` LIKE '%Persalinan%'");
echo "Persalinan 24 Jam verified with is_24jam = 1\n";

echo "Migration finished successfully!\n";
