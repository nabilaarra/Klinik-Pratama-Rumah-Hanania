<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== DAFTAR ANTREAN DI DATABASE ===\n";
$res = $db->query("
    SELECT a.id_antrean, a.nomor_antrean, a.estimasi_waktu, a.status_antrean,
           p.id_pendaftaran, p.id_poli, p.id_dokter, p.id_bidan, p.tanggal_kunjungan,
           p.sumber_pendaftaran, p.status_pendaftaran
    FROM antrean a
    JOIN pendaftaran p ON p.id_pendaftaran = a.id_pendaftaran
    ORDER BY p.tanggal_kunjungan DESC, a.nomor_antrean ASC
    LIMIT 30
");
while ($r = $res->fetch_assoc()) {
    echo "ID Reg: {$r['id_pendaftaran']}, Tgl: {$r['tanggal_kunjungan']}, Poli: {$r['id_poli']}, Sumber: {$r['sumber_pendaftaran']}, No Antrean: {$r['nomor_antrean']}, Status: {$r['status_pendaftaran']}\n";
}
