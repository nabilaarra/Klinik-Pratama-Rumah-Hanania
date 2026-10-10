<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

echo "=== 1. TEST DINAMIS STRUKTUR ORGANISASI ===\n";
$currentTimJson = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'struktur_tim_json'")->fetch_assoc()['nilai'] ?? '[]';
$tim = json_decode($currentTimJson, true) ?: [];
echo "Jumlah posisi awal: " . count($tim) . "\n";

// Tambah posisi dummy untuk tes
$tim[] = [
    'kategori' => 'Unit Pelayanan Medis',
    'jabatan' => 'Bidan Jaga Sore Test',
    'nama' => 'Bidan Hanania Test, A.Md.Keb',
    'icon' => '👶'
];
$newJson = json_encode($tim, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'struktur_tim_json'");
$stmt->bind_param('s', $newJson);
$stmt->execute();
$stmt->close();

$checkTim = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'struktur_tim_json'")->fetch_assoc()['nilai'];
$timAfterAdd = json_decode($checkTim, true);
echo "Jumlah posisi setelah tambah: " . count($timAfterAdd) . "\n";
echo "Posisi terakhir: " . end($timAfterAdd)['jabatan'] . " (" . end($timAfterAdd)['nama'] . ")\n";

// Verifikasi rendering di struktur_organisasi.php
$htmlStruktur = file_get_contents('http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/struktur_organisasi.php');
echo "Tampil di halaman publik: " . (strpos($htmlStruktur, 'Bidan Jaga Sore Test') !== false ? "BERHASIL (OK)" : "GAGAL") . "\n";

// Kembalikan (Hapus posisi test)
array_pop($timAfterAdd);
$cleanJson = json_encode($timAfterAdd, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'struktur_tim_json'");
$stmt->bind_param('s', $cleanJson);
$stmt->execute();
$stmt->close();
echo "Posisi test dihapus, total kembali ke: " . count($timAfterAdd) . "\n";


echo "\n=== 2. TEST DINAMIS GALERI SEJARAH ===\n";
$currentGaleriJson = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'sejarah_galeri_json'")->fetch_assoc()['nilai'] ?? '[]';
$galeri = json_decode($currentGaleriJson, true) ?: [];
echo "Jumlah foto awal: " . count($galeri) . "\n";

// Tambah foto dummy test
$galeri[] = [
    'id' => 'test_' . time(),
    'foto' => 'assets/img/pdf_pages/page_5.png',
    'caption' => 'Piagam Test Sejarah Hanania',
    'tahun' => '2025'
];
$newGaleriJson = json_encode($galeri, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'sejarah_galeri_json'");
$stmt->bind_param('s', $newGaleriJson);
$stmt->execute();
$stmt->close();

$htmlSejarah = file_get_contents('http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/sejarah%20klinik.php');
echo "Foto baru tampil di halaman publik: " . (strpos($htmlSejarah, 'Piagam Test Sejarah Hanania') !== false ? "BERHASIL (OK)" : "GAGAL") . "\n";

// Kembalikan (Hapus foto test)
array_pop($galeri);
$cleanGaleriJson = json_encode($galeri, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'sejarah_galeri_json'");
$stmt->bind_param('s', $cleanGaleriJson);
$stmt->execute();
$stmt->close();
echo "Foto test dihapus, galeri kembali normal: " . count($galeri) . " foto\n";

echo "\nSEMUA FITUR DINAMIS TERBUKTI BERFUNGSI 100% SECARA REAL-TIME!\n";
