<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query("SELECT * FROM poli");
print_r($res->fetch_all(MYSQLI_ASSOC));
