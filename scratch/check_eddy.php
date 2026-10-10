<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT * FROM jadwal_dokter WHERE id_dokter = 4");
echo "Jadwal Dr. Eddy:\n";
while ($r = $res->fetch_assoc()) {
    echo $r['hari'] . " (" . $r['jam_mulai'] . " - " . $r['jam_selesai'] . ") - Poli " . $r['id_poli'] . "\n";
}
