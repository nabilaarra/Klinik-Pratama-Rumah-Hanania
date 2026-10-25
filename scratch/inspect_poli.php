<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT * FROM poli");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id_poli']} | Nama: {$r['nama_poli']} | Status: {$r['status']} | Deskripsi: {$r['deskripsi']}\n";
}
