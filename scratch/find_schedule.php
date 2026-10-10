<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query("SELECT jd.hari, d.id_dokter, d.nama_dokter, jd.id_poli FROM jadwal_dokter jd JOIN dokter d ON d.id_dokter=jd.id_dokter LIMIT 1")->fetch_assoc();
echo json_encode($r);
