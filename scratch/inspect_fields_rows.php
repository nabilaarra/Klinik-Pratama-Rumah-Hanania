<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;
$res = $db->query('SELECT * FROM pengaturan_form_pendaftaran ORDER BY id_field ASC');
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id_field']} | nama_field: {$r['nama_field']} | label: {$r['label_field']} | wajib: {$r['wajib']} | status: {$r['status']} | tahap: {$r['tahap']}\n";
}
