<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== INSPEKSI 28 DATA PENDAFTARAN EXISTING ===\n\n";

$sql = "
    SELECT p.id_pendaftaran, p.id_pasien, pas.nama_lengkap, p.id_poli, pol.nama_poli, 
           p.id_dokter, doc.nama_dokter, p.id_bidan, bid.nama_bidan,
           p.tanggal_kunjungan, p.jam_kunjungan, p.status_pendaftaran, p.sumber_pendaftaran,
           a.id_antrean, a.nomor_antrean, a.status_antrean
    FROM pendaftaran p
    LEFT JOIN pasien pas ON pas.id_pasien = p.id_pasien
    LEFT JOIN poli pol ON pol.id_poli = p.id_poli
    LEFT JOIN dokter doc ON doc.id_dokter = p.id_dokter
    LEFT JOIN bidan bid ON bid.id_bidan = p.id_bidan
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    ORDER BY p.id_pendaftaran ASC
";

$res = $db->query($sql);
$noAntreanCount = 0;
while ($r = $res->fetch_assoc()) {
    $hasAntrean = !empty($r['id_antrean']);
    if (!$hasAntrean) $noAntreanCount++;
    $staff = !empty($r['nama_dokter']) ? "Dokter: {$r['nama_dokter']}" : (!empty($r['nama_bidan']) ? "Bidan: {$r['nama_bidan']}" : "Staff: -");
    echo sprintf(
        "ID #%02d | Pasien: %-20s | Poli: %-15s | %-25s | Tgl: %s | Status: %-10s | Sumber: %-7s | Antrean: %s\n",
        $r['id_pendaftaran'],
        substr($r['nama_lengkap'] ?? 'N/A', 0, 20),
        substr($r['nama_poli'] ?? 'N/A', 0, 15),
        substr($staff, 0, 25),
        $r['tanggal_kunjungan'],
        $r['status_pendaftaran'],
        $r['sumber_pendaftaran'] ?? 'NULL',
        $hasAntrean ? "No. {$r['nomor_antrean']} ({$r['status_antrean']})" : "TIDAK ADA"
    );
}

echo "\nTotal pendaftaran tanpa baris antrean: $noAntreanCount\n";
