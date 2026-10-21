<?php
require_once __DIR__ . '/../config/database.php';
$d = $conn->query('SELECT id_dokter, nama_dokter, id_poli, spesialisasi, status FROM dokter');
while($r = $d->fetch_assoc()) {
    echo "Dokter: [{$r['id_dokter']}] {$r['nama_dokter']} | Poli ID: {$r['id_poli']} | Spesialisasi: {$r['spesialisasi']}\n";
    $qj = $conn->query("SELECT * FROM jadwal_dokter WHERE id_dokter = {$r['id_dokter']}");
    while($j = $qj->fetch_assoc()) {
        echo "   -> Jadwal: id_poli={$j['id_poli']}, hari={$j['hari']}, jam={$j['jam_mulai']}-{$j['jam_selesai']}\n";
    }
}
$b = $conn->query('SELECT id_bidan, nama_bidan, id_poli, status FROM bidan');
while($r = $b->fetch_assoc()) {
    echo "Bidan: [{$r['id_bidan']}] {$r['nama_bidan']} | Poli ID: {$r['id_poli']}\n";
    $qj = $conn->query("SELECT * FROM jadwal_bidan WHERE id_bidan = {$r['id_bidan']}");
    while($j = $qj->fetch_assoc()) {
        echo "   -> Jadwal: id_poli={$j['id_poli']}, hari={$j['hari']}, jam={$j['jam_mulai']}-{$j['jam_selesai']}\n";
    }
}
