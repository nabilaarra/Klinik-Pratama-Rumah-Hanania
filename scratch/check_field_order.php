<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$q = $db->query("SELECT id_field, nama_field, label_field, urutan, status, wajib FROM pengaturan_form_pendaftaran ORDER BY urutan ASC");
while($row = $q->fetch_assoc()) {
    echo "{$row['urutan']} | {$row['nama_field']} | {$row['label_field']} | {$row['status']}\n";
}
