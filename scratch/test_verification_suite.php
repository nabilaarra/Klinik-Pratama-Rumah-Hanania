<?php
require_once __DIR__ . '/../config/database.php';

$results = [];

// 1. Footer verification
ob_start();
require __DIR__ . '/../includes/footer.php';
$footerHtml = ob_get_clean();

$results['footer_address'] = (strpos($footerHtml, 'Sukadamai') !== false);
$results['footer_wa1'] = (strpos($footerHtml, '088510703839') !== false);
$results['footer_wa2'] = (strpos($footerHtml, '085714178178') !== false);
$results['footer_maps'] = (strpos($footerHtml, '524k7ABe1fckKgpt6') !== false);

// 2. Antrian css media query check
$antrianContent = file_get_contents(__DIR__ . '/../pasien/antrian.php');
$results['antrian_media_query_closed'] = (strpos($antrianContent, ".queue-meta{text-align:left}\n}\n\n.antrian-hero {") !== false || strpos($antrianContent, ".queue-meta{text-align:left}\r\n}\r\n\r\n.antrian-hero {") !== false);

// 3. Admin guard scroll persistence
$guardContent = file_get_contents(__DIR__ . '/../admin/_guard.php');
$results['admin_scroll_persistence'] = (strpos($guardContent, 'admin_sidebar_scroll') !== false);

// 4. Admin tarif and konten link
$tarifExists = file_exists(__DIR__ . '/../admin/tarif.php');
$kontenContent = file_get_contents(__DIR__ . '/../admin/konten.php');
$results['tarif_file_exists'] = $tarifExists;
$results['konten_edit_links'] = (strpos($kontenContent, 'layanan.php?edit=') !== false);

// 5. Login admin separation
$loginContent = file_get_contents(__DIR__ . '/../login.php');
$results['login_admin_redirect'] = (strpos($loginContent, 'admin/login.php?notice=admin_redirect') !== false);
$results['login_admin_link'] = (strpos($loginContent, 'admin/login.php') !== false);

// 6. Daftar session and family logic
$daftarContent = file_get_contents(__DIR__ . '/../pasien/daftar.php');
$results['daftar_group_sesi'] = (strpos($daftarContent, 'id="group_sesi"') !== false);
$results['daftar_update_sesi_fn'] = (strpos($daftarContent, 'function updateSesiVisibility()') !== false);
$results['daftar_box_keluarga'] = (strpos($daftarContent, 'id="boxDataKeluarga"') !== false);
$results['daftar_modal_keluarga'] = (strpos($daftarContent, 'id="modalTambahKeluarga"') !== false);
$results['daftar_modal_pekerjaan'] = (strpos($daftarContent, 'id="kel_pekerjaan"') !== false);
$results['daftar_modal_status_perkawinan'] = (strpos($daftarContent, 'id="kel_status_perkawinan"') !== false);
$results['daftar_modal_pendidikan'] = (strpos($daftarContent, 'id="kel_pendidikan_terakhir"') !== false);
$results['daftar_modal_agama'] = (strpos($daftarContent, 'id="kel_agama"') !== false);
$results['daftar_button_simpan_perubahan'] = (strpos($daftarContent, 'Simpan Perubahan Data') !== false);
$results['daftar_pilih_keluarga_fn'] = (strpos($daftarContent, 'function pilihAnggotaKeluarga(') !== false);

// 7. Simpan keluarga endpoint
$results['simpan_keluarga_exists'] = file_exists(__DIR__ . '/../pasien/simpan_keluarga.php');
$simpanKeluargaContent = file_get_contents(__DIR__ . '/../pasien/simpan_keluarga.php');
$results['simpan_keluarga_update_support'] = (strpos($simpanKeluargaContent, '$action === \'update\'') !== false);

echo "=== VERIFICATION SUITE RESULTS ===\n";
$allPassed = true;
foreach ($results as $k => $pass) {
    echo ($pass ? "✅ PASS" : "❌ FAIL") . " - $k\n";
    if (!$pass) $allPassed = false;
}

if ($allPassed) {
    echo "\n🎉 ALL VERIFICATION CHECKS PASSED PERFECTLY!\n";
} else {
    echo "\n⚠️ SOME CHECKS FAILED!\n";
}

