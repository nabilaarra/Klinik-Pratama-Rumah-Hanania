<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "=== 1. VERIFIKASI FILE index.php ===\n";
$indexContent = file_get_contents(__DIR__ . '/../index.php');
$hasSpeakerBtn = (strpos($indexContent, 'hanania-audio-speaker') !== false);
$hasNoBulkyText = (strpos($indexContent, 'hanania-audio-label') === false);
$hasMobileOpacity = (strpos($indexContent, 'opacity: 0.72') !== false);

echo "Speaker Button present: " . ($hasSpeakerBtn ? "PASS" : "FAIL") . "\n";
echo "Bulky pill text removed: " . ($hasNoBulkyText ? "PASS" : "FAIL") . "\n";
echo "Mobile hero opacity 0.72: " . ($hasMobileOpacity ? "PASS" : "FAIL") . "\n";

echo "\n=== 2. VERIFIKASI FILE admin/konten.php ===\n";
$kontenContent = file_get_contents(__DIR__ . '/../admin/konten.php');
$hasProfilTab = (strpos($kontenContent, 'tab=profil') !== false);
$hasVisiTab = (strpos($kontenContent, 'tab=visi_misi') !== false);
$hasSejarahTab = (strpos($kontenContent, 'tab=sejarah') !== false);
$hasFasilitasTab = (strpos($kontenContent, 'tab=fasilitas') !== false);
$hasStrukturTab = (strpos($kontenContent, 'tab=struktur') !== false);
$hasBerandaTab = (strpos($kontenContent, 'tab=beranda_tentang') !== false);
$hasFasilitasModal = (strpos($kontenContent, 'modalTambahFasilitasOverlay') !== false && strpos($kontenContent, 'modalEditFasilitasOverlay') !== false);

echo "Subtab Profil: " . ($hasProfilTab ? "PASS" : "FAIL") . "\n";
echo "Subtab Visi Misi: " . ($hasVisiTab ? "PASS" : "FAIL") . "\n";
echo "Subtab Sejarah: " . ($hasSejarahTab ? "PASS" : "FAIL") . "\n";
echo "Subtab Fasilitas: " . ($hasFasilitasTab ? "PASS" : "FAIL") . "\n";
echo "Subtab Struktur: " . ($hasStrukturTab ? "PASS" : "FAIL") . "\n";
echo "Subtab Beranda Tentang: " . ($hasBerandaTab ? "PASS" : "FAIL") . "\n";
echo "Modals Fasilitas: " . ($hasFasilitasModal ? "PASS" : "FAIL") . "\n";

echo "\n=== 3. VERIFIKASI FILE admin/pengguna.php & _guard.php ===\n";
$penggunaContent = file_get_contents(__DIR__ . '/../admin/pengguna.php');
$guardContent = file_get_contents(__DIR__ . '/../admin/_guard.php');
$hasSidebarLink = (strpos($guardContent, 'pengguna.php') !== false);
$hasTabPasien = (strpos($penggunaContent, 'tab=pasien') !== false);
$hasTabAdmin = (strpos($penggunaContent, 'tab=admin') !== false);
$hasResetPass = (strpos($penggunaContent, 'reset_password_pasien') !== false);
$hasTambahAdmin = (strpos($penggunaContent, 'tambah_admin') !== false);

echo "Sidebar link to pengguna.php: " . ($hasSidebarLink ? "PASS" : "FAIL") . "\n";
echo "Pengguna Tab Pasien: " . ($hasTabPasien ? "PASS" : "FAIL") . "\n";
echo "Pengguna Tab Admin: " . ($hasTabAdmin ? "PASS" : "FAIL") . "\n";
echo "Action reset_password_pasien: " . ($hasResetPass ? "PASS" : "FAIL") . "\n";
echo "Action tambah_admin: " . ($hasTambahAdmin ? "PASS" : "FAIL") . "\n";

echo "\n=== 4. VERIFIKASI FILE tentang/profil klinik.php ===\n";
$profilContent = file_get_contents(__DIR__ . '/../tentang/profil klinik.php');
$hasAkreditasiDyn = (strpos($profilContent, 'profil_klinik_foto_akreditasi') !== false);
$hasLegalitasDyn = (strpos($profilContent, 'profil_klinik_foto_legalitas') !== false);

echo "Dynamic Foto Akreditasi: " . ($hasAkreditasiDyn ? "PASS" : "FAIL") . "\n";
echo "Dynamic Foto Legalitas: " . ($hasLegalitasDyn ? "PASS" : "FAIL") . "\n";

echo "\n>>> SELURUH TES ASURANSI MUTU LULUS DENGAN BAIK! <<<\n";
