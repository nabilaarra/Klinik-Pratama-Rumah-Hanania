<?php
require_once __DIR__ . '/../config/database.php';

echo "=== TEST DATABASE & LOGIKA ===\n";

// 1. Cek tabel penukaran_poin
$res1 = $conn->query("SHOW TABLES LIKE 'penukaran_poin'");
echo "Tabel penukaran_poin: " . ($res1 && $res1->num_rows > 0 ? "ADA" : "TIDAK ADA") . "\n";

// 2. Cek kolom pendaftaran
$res2 = $conn->query("SHOW COLUMNS FROM pendaftaran LIKE 'nomor_antrean_jkn'");
echo "Kolom nomor_antrean_jkn: " . ($res2 && $res2->num_rows > 0 ? "ADA" : "TIDAK ADA") . "\n";

$res3 = $conn->query("SHOW COLUMNS FROM pendaftaran LIKE 'sesi_kunjungan'");
echo "Kolom sesi_kunjungan: " . ($res3 && $res3->num_rows > 0 ? "ADA" : "TIDAK ADA") . "\n";

// 3. Cek pengaturan konten beranda
$res4 = $conn->query("SELECT COUNT(*) as cnt FROM pengaturan_konten WHERE kunci IN ('hero_judul', 'hero_badge_persalinan', 'beranda_slogan')");
$r4 = $res4->fetch_assoc();
echo "Jumlah key konten beranda: " . $r4['cnt'] . "\n";

// 4. Test simulasi panggilan get_jadwal.php
$_GET['tanggal'] = date('Y-m-d');
$_GET['poli'] = '1';
$_GET['dokter'] = '1';
ob_start();
include __DIR__ . '/../pasien/get_jadwal.php';
$output = ob_get_clean();
$json = json_decode($output, true);
echo "get_jadwal.php JSON response: " . ($json && isset($json['success']) ? "VALID JSON (success=" . ($json['success'] ? 'true' : 'false') . ")" : "INVALID") . "\n";
if (isset($json['jadwal_tersisa_minggu'])) {
    echo "jadwal_tersisa_minggu key ada: " . count($json['jadwal_tersisa_minggu']) . " slots\n";
}

echo "=== SELESAI ===\n";
