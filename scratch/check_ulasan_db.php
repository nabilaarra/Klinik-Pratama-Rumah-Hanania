<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google'");
if ($row = $res->fetch_assoc()) {
    echo "Found daftar_ulasan_google:\n";
    $data = json_decode($row['nilai'], true);
    print_r($data);
} else {
    echo "Key daftar_ulasan_google not found in DB\n";
}
