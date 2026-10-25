<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$db->query("ALTER TABLE riwayat_poin MODIFY COLUMN jenis ENUM('tambah','kurang','set') NOT NULL DEFAULT 'tambah'");
echo "riwayat_poin jenis enum updated.\n";
