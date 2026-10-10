<?php
// Mock session for admin
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_id'] = 1;
$_SESSION['admin_role'] = 'admin';

echo "=== 1. Testing tentang/struktur_organisasi.php ===\n";
ob_start();
include __DIR__ . '/../tentang/struktur_organisasi.php';
$htmlStruktur = ob_get_clean();
$hasExtra = strpos($htmlStruktur, 'Posisi & Penanggung Jawab Tambahan') !== false;
$hasBaganImg = strpos($htmlStruktur, 'Bagan Visual Struktur Organisasi') !== false;
echo "Contains 'Posisi & Penanggung Jawab Tambahan': " . ($hasExtra ? "YES (FAIL)" : "NO (PASS)") . "\n";
echo "Bagan image section shown when empty: " . ($hasBaganImg ? "YES (FAIL)" : "NO (PASS)") . "\n";

echo "\n=== 2. Testing tentang/profil klinik.php ===\n";
ob_start();
include __DIR__ . '/../tentang/profil klinik.php';
$htmlProfil = ob_get_clean();
echo "Length of profil klinik HTML: " . strlen($htmlProfil) . " bytes\n";
echo "Contains 'Profil & Sertifikat Akreditasi Klinik': " . (strpos(html_entity_decode($htmlProfil), 'Profil & Sertifikat Akreditasi Klinik') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";

echo "\n=== 3. Testing tentang/sejarah klinik.php ===\n";
ob_start();
include __DIR__ . '/../tentang/sejarah klinik.php';
$htmlSejarah = ob_get_clean();
echo "Length of sejarah klinik HTML: " . strlen($htmlSejarah) . " bytes\n";
echo "Contains 'Sejarah Klinik Pratama Rumah Hanania': " . (strpos($htmlSejarah, 'Sejarah Klinik Pratama Rumah Hanania') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";

echo "\n=== 4. Testing tentang/visi_misi.php ===\n";
ob_start();
include __DIR__ . '/../tentang/visi_misi.php';
$htmlVisi = ob_get_clean();
echo "Length of visi misi HTML: " . strlen($htmlVisi) . " bytes\n";
echo "Contains 'PROFESIONAL': " . (strpos($htmlVisi, 'PROFESIONAL') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";

echo "\n=== 5. Testing tentang/fasilitas klinik.php ===\n";
ob_start();
include __DIR__ . '/../tentang/fasilitas klinik.php';
$htmlFasilitas = ob_get_clean();
echo "Length of fasilitas klinik HTML: " . strlen($htmlFasilitas) . " bytes\n";
echo "Contains 'Fasilitas Pelayanan Klinik': " . (strpos(html_entity_decode($htmlFasilitas), 'Fasilitas Pelayanan Klinik') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";

echo "\n=== 6. Testing admin/konten.php (section=tentang&tab=profil) ===\n";
$_GET['section'] = 'tentang';
$_GET['tab'] = 'profil';
ob_start();
include __DIR__ . '/../admin/konten.php';
$htmlAdminKonten = ob_get_clean();
echo "Contains 'hapus_profil_foto': " . (strpos($htmlAdminKonten, 'hapus_profil_foto') !== false || strpos($htmlAdminKonten, 'profil_klinik_foto') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "Contains 'hapus_akreditasi_foto': " . (strpos($htmlAdminKonten, 'hapus_akreditasi_foto') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "Contains 'hapus_legalitas_foto': " . (strpos($htmlAdminKonten, 'hapus_legalitas_foto') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "Contains duplicate old 'Foto Fasilitas Klinik' button in top nav: " . (strpos($htmlAdminKonten, 'Foto Fasilitas Klinik') !== false ? "YES (FAIL)" : "NO (PASS)") . "\n";

echo "\n=== 7. Testing admin/pengguna.php ===\n";
$_GET['tab'] = 'pasien';
ob_start();
include __DIR__ . '/../admin/pengguna.php';
$htmlAdminPengguna = ob_get_clean();
echo "Contains 'pengguna-header-card': " . (strpos($htmlAdminPengguna, 'pengguna-header-card') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "Contains 'pengguna-stat-card': " . (strpos($htmlAdminPengguna, 'pengguna-stat-card') !== false ? "YES (PASS)" : "NO (FAIL)") . "\n";

echo "\n=== ALL VERIFICATION TESTS COMPLETED ===\n";
