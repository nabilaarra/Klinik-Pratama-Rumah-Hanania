<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "--- PASIEN LIST ---\n";
$r = $db->query("SELECT id_pasien, nama_lengkap, reward_poin FROM pasien");
while($row = $r->fetch_assoc()) echo "#{$row['id_pasien']} - {$row['nama_lengkap']} - {$row['reward_poin']} Poin\n";

echo "\n--- PENGATURAN FORM COLUMNS ---\n";
$r = $db->query("SHOW COLUMNS FROM pengaturan_form_pendaftaran");
while($row = $r->fetch_assoc()) echo $row['Field'] . " | " . $row['Type'] . "\n";

echo "\n--- PENGATURAN FORM AYAH ---\n";
$r = $db->query("SELECT * FROM pengaturan_form_pendaftaran WHERE nama_field LIKE '%ayah%'");
while($row = $r->fetch_assoc()) print_r($row);
