<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
$db->query("UPDATE pasien SET reward_poin = 150 WHERE id_pasien = 10");
echo "Updated id 10 to 150\n";
