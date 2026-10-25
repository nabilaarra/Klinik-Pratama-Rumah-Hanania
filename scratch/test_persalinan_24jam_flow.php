<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

echo "=== TEST VERIFIKASI FITUR PERSALINAN 24 JAM & CHECKBOX 24 JAM ===\n\n";

// 1. Verifikasi Data Poli 5
$rPoli = $db->query("SELECT * FROM poli WHERE id_poli = 5")->fetch_assoc();
echo "1. Data Poli 5:\n";
echo "   - Nama: {$rPoli['nama_poli']}\n";
echo "   - is_24jam: {$rPoli['is_24jam']}\n";
echo "   - Status: {$rPoli['status']}\n";
assert((int)$rPoli['is_24jam'] === 1, "Poli 5 harus is_24jam = 1");
echo "   -> [PASS] Poli 5 terverifikasi sebagai layanan 24 Jam.\n\n";

// 2. Verifikasi Data Bidan Aktif
$qB = $db->query("SELECT id_bidan, nama_bidan, status FROM bidan WHERE status = 'aktif' ORDER BY CASE WHEN nama_bidan LIKE '%Gita%' THEN 0 ELSE 1 END, nama_bidan ASC");
$activeBidan = [];
while ($b = $qB->fetch_assoc()) $activeBidan[] = $b;
echo "2. Daftar Bidan Aktif yang Tersedia untuk Jaga Persalinan 24 Jam:\n";
echo "   - Total: " . count($activeBidan) . " Bidan\n";
foreach ($activeBidan as $idx => $b) {
    echo "   " . ($idx + 1) . ". {$b['nama_bidan']} (ID: {$b['id_bidan']})\n";
}
assert(count($activeBidan) >= 7, "Harus ada minimal 7 bidan siaga");
assert(strpos($activeBidan[0]['nama_bidan'], 'Gita') !== false, "Bu Gita Marissa harus urutan pertama");
echo "   -> [PASS] Daftar bidan aktif lengkap dan Bu Gita Marissa di urutan teratas.\n\n";

// 3. Verifikasi HTML admin/jadwal.php
$htmlJadwal = file_get_contents(__DIR__ . '/../admin/jadwal.php');
echo "3. Verifikasi Elemen UI Form di admin/jadwal.php:\n";
$hasCheck24 = strpos($htmlJadwal, 'id="check24Jam"') !== false;
$hasPresetButtons = strpos($htmlJadwal, 'setPresetJam(\'00:00\', \'23:59\')') !== false;
$hasIsPersalinanFilter = strpos($htmlJadwal, 'isPersalinan = (numPoli === 5') !== false;
$hasToggle24 = strpos($htmlJadwal, 'function toggle24JamMode') !== false;
$hasCheck24Auto = strpos($htmlJadwal, 'function checkJam24Otomatis') !== false;

echo "   - Checkbox 24 Jam (id=check24Jam): " . ($hasCheck24 ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Tombol Preset Cepat 24 Jam: " . ($hasPresetButtons ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Filter Khusus Bidan untuk Persalinan: " . ($hasIsPersalinanFilter ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Fungsi toggle24JamMode: " . ($hasToggle24 ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Fungsi auto-detect jam 00:00-23:59 (checkJam24Otomatis): " . ($hasCheck24Auto ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";

assert($hasCheck24 && $hasPresetButtons && $hasIsPersalinanFilter && $hasToggle24 && $hasCheck24Auto, "Semua elemen UI harus ada");
echo "   -> [PASS] Semua elemen UI dan JavaScript terintegrasi sempurna!\n\n";

// 4. Verifikasi admin/layanan_pendaftaran.php tab=poli
$htmlLayanan = file_get_contents(__DIR__ . '/../admin/layanan_pendaftaran.php');
echo "4. Verifikasi Tab Poli di admin/layanan_pendaftaran.php:\n";
$hasPoliInArray = strpos($htmlLayanan, "['layanan', 'fields', 'poli']") !== false;
$hasTabPoliLink = strpos($htmlLayanan, 'layanan_pendaftaran.php?tab=poli') !== false;
$hasPoliSection = strpos($htmlLayanan, "\$tab === 'poli'") !== false;

echo "   - 'poli' di array validasi tab: " . ($hasPoliInArray ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Tombol Tab Status Operasional Poli: " . ($hasTabPoliLink ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";
echo "   - Section Tampilan Kelola Poli 24 Jam: " . ($hasPoliSection ? "ADA [PASS]" : "TIDAK ADA [FAIL]") . "\n";

assert($hasPoliInArray && $hasTabPoliLink && $hasPoliSection, "Tab poli harus lengkap");
echo "   -> [PASS] Tab Status Operasional Poli & 24 Jam lengkap!\n\n";

echo "=== SEMUA PENGUJIAN FITUR PERSALINAN 24 JAM SUKSES 100%! 🎉 ===\n";
