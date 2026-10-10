<?php
$c = new mysqli('localhost', 'root', '', 'klinik_pratama_rumah_hanania');
$res = $c->query('SELECT * FROM poli');
echo "POLI:\n";
while ($r = $res->fetch_assoc()) {
    echo $r['id_poli'] . ' - ' . $r['nama_poli'] . "\n";
}

$res2 = $c->query('SELECT id_dokter, nama_dokter, status FROM dokter');
echo "\nDOKTER:\n";
while ($r = $res2->fetch_assoc()) {
    echo $r['id_dokter'] . ' - ' . $r['nama_dokter'] . ' [' . $r['status'] . "]\n";
}

$res3 = $c->query('SELECT id_bidan, nama_bidan, status FROM bidan');
echo "\nBIDAN:\n";
while ($r = $res3->fetch_assoc()) {
    echo $r['id_bidan'] . ' - ' . $r['nama_bidan'] . ' [' . $r['status'] . "]\n";
}
