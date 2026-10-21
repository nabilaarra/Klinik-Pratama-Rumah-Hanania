<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query('SELECT b.id_bidan, b.nama_bidan, b.id_poli, jb.id_poli as j_poli, jb.hari FROM bidan b LEFT JOIN jadwal_bidan jb ON b.id_bidan = jb.id_bidan WHERE b.nama_bidan LIKE "%Gita%"');
while($r = $res->fetch_assoc()) {
    print_r($r);
}
