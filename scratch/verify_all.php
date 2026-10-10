<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/Klinik-Pratama-Rumah-Hanania/test.php';

// Test 1: admin/pengguna.php with tab=pasien
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';

$_GET['tab'] = 'pasien';
ob_start();
include __DIR__ . '/../admin/pengguna.php';
$htmlPenggunaPasien = ob_get_clean();

$hasTabActive = strpos($htmlPenggunaPasien, 'content-nav-btn active') !== false;
$hasTabsBar = strpos($htmlPenggunaPasien, 'user-tabs-bar') !== false;
$hasBadge = strpos($htmlPenggunaPasien, 'tab-badge') !== false;
echo "Pengguna Pasien Tab Active: " . ($hasTabActive ? "PASS" : "FAIL") . PHP_EOL;
echo "Pengguna user-tabs-bar: " . ($hasTabsBar ? "PASS" : "FAIL") . PHP_EOL;
echo "Pengguna tab-badge: " . ($hasBadge ? "PASS" : "FAIL") . PHP_EOL;

// Test 2: admin/konten.php Tab Sejarah
$_GET = ['section' => 'tentang', 'tab' => 'sejarah'];
ob_start();
include __DIR__ . '/../admin/konten.php';
$htmlKontenSejarah = ob_get_clean();

$hasKomitmen = strpos($htmlKontenSejarah, 'name="sejarah_komitmen"') !== false;
$hasFokus = strpos($htmlKontenSejarah, 'name="sejarah_fokus"') !== false;
$hasFotoUtama = strpos($htmlKontenSejarah, 'name="sejarah_foto_utama"') !== false;
echo "Admin Konten sejarah_komitmen: " . ($hasKomitmen ? "PASS" : "FAIL") . PHP_EOL;
echo "Admin Konten sejarah_fokus: " . ($hasFokus ? "PASS" : "FAIL") . PHP_EOL;
echo "Admin Konten sejarah_foto_utama: " . ($hasFotoUtama ? "PASS" : "FAIL") . PHP_EOL;

// Test 3: tentang/sejarah klinik.php
ob_start();
include __DIR__ . '/../tentang/sejarah klinik.php';
$htmlSejarah = ob_get_clean();

$hasSjImg = strpos($htmlSejarah, 'sejarah-hero-img-wrap') !== false;
$hasSjKomitmen = strpos($htmlSejarah, 'Awal perjalanan Klinik Pratama Rumah Hanania berangkat dari komitmen kuat') !== false;
$hasSjFokus = strpos($htmlSejarah, 'promotif, preventif, kuratif, dan rehabilitatif') !== false;
echo "Sejarah Klinik Hero Img Wrap: " . ($hasSjImg ? "PASS" : "FAIL") . PHP_EOL;
echo "Sejarah Klinik Komitmen Paragraf: " . ($hasSjKomitmen ? "PASS" : "FAIL") . PHP_EOL;
echo "Sejarah Klinik Fokus Paragraf: " . ($hasSjFokus ? "PASS" : "FAIL") . PHP_EOL;

// Test 4: tentang/profil klinik.php
ob_start();
include __DIR__ . '/../tentang/profil klinik.php';
$htmlProfil = ob_get_clean();

$hasGedungImg = strpos($htmlProfil, 'profil-hero-img-wrap') !== false;
$hasAkreditasiImg = strpos($htmlProfil, 'sertifikat_akreditasi_paripurna.jpg') !== false;
$hasLegalitasImg = strpos($htmlProfil, 'page_4.png') !== false;
echo "Profil Klinik Gedung Img Wrap: " . ($hasGedungImg ? "PASS" : "FAIL") . PHP_EOL;
echo "Profil Klinik Akreditasi Img: " . ($hasAkreditasiImg ? "PASS" : "FAIL") . PHP_EOL;
echo "Profil Klinik Legalitas Img: " . ($hasLegalitasImg ? "PASS" : "FAIL") . PHP_EOL;

// Test 5: index.php
ob_start();
include __DIR__ . '/../index.php';
$htmlIndex = ob_get_clean();

$hasAboutMobileCss = strpos($htmlIndex, '.about-image') !== false && strpos($htmlIndex, 'height: 230px !important') !== false;
echo "Index About Image Mobile CSS: " . ($hasAboutMobileCss ? "PASS" : "FAIL") . PHP_EOL;

echo "=== ALL CHECKS COMPLETED ===" . PHP_EOL;
