<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== PENUKARAN POIN ===\n";
$q = $db->query("SELECT * FROM penukaran_poin");
echo "Total rows: " . $q->num_rows . "\n";
while ($r = $q->fetch_assoc()) {
    echo "ID: {$r['id_penukaran']}, Pasien: {$r['id_pasien']}, Item: {$r['nama_reward']}, Poin: {$r['poin_digunakan']}, Status: {$r['status']}\n";
}

echo "\n=== RIWAYAT POIN ===\n";
$q2 = $db->query("SELECT * FROM riwayat_poin");
echo "Total rows: " . $q2->num_rows . "\n";
while ($r = $q2->fetch_assoc()) {
    echo "ID: {$r['id_riwayat']}, Pasien: {$r['id_pasien']}, Jenis: {$r['jenis']}, Poin: {$r['jumlah_poin']}, Saldo: {$r['saldo_akhir']}, Kat: {$r['kategori']}, Ket: {$r['keterangan']}\n";
}
