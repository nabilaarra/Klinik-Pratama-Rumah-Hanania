<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$db = $conn ?? $koneksi;

echo "=== TESTING RUNTIME QUERIES DENGAN DATA REAL DATABASE ===\n\n";

$allPassed = true;

// 1. Test get_jadwal for Poli 5 (Persalinan 24 Jam)
echo "1. Simulasi Pemanggilan Jadwal Persalinan 24 Jam:\n";
$phpExe = 'D:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe';
$cmd5 = "\"$phpExe\" -r \"\$_GET=['poli'=>5, 'tanggal'=>date('Y-m-d')]; include 'pasien/get_jadwal.php';\"";
$out5 = shell_exec($cmd5);
$dataJadwal5 = json_decode($out5, true);

if ($dataJadwal5 && isset($dataJadwal5['tenaga']) && count($dataJadwal5['tenaga']) > 0) {
    $first = $dataJadwal5['tenaga'][0];
    echo "  [PASS] Berhasil memuat " . count($dataJadwal5['tenaga']) . " jadwal bidan siaga.\n";
    echo "         Status: '{$first['status_label']}', Tersedia: " . ($first['tersedia'] ? 'YES' : 'NO') . ", 24 Jam: " . ($first['is_24jam'] ? 'YES' : 'NO') . "\n";
    if ($first['tersedia'] !== true || $first['is_24jam'] !== true) {
        $allPassed = false;
        echo "  [FAIL] Persalinan 24 jam harus selalu tersedia dan bertanda 24 jam!\n";
    }
} else {
    echo "  [FAIL] Gagal memuat jadwal Poli 5: $out5\n";
    $allPassed = false;
}

// 2. Test get_jadwal for Poli 1 (Poli Umum)
echo "\n2. Simulasi Pemanggilan Jadwal Poli 1 (Poli Umum):\n";
$nextMonday = date('Y-m-d', strtotime('next monday'));
$cmd1 = "\"$phpExe\" -r \"\$_GET=['poli'=>1, 'tanggal'=>'$nextMonday']; include 'pasien/get_jadwal.php';\"";
$out1 = shell_exec($cmd1);
$dataPoli1 = json_decode($out1, true);

if ($dataPoli1 && isset($dataPoli1['tenaga'])) {
    echo "  [PASS] Berhasil memuat jadwal Poli Umum untuk Senin depan (Total: " . count($dataPoli1['tenaga']) . " jadwal).\n";
} else {
    echo "  [FAIL] Gagal memuat jadwal Poli 1: $out1\n";
    $allPassed = false;
}

// 3. Test Hasil Daftar untuk Registrasi Existing #2 (Tia), #28 (Tia), #30 (Diandra), #31 (Narenda)
echo "\n3. Simulasi Data Hasil Pendaftaran Existing:\n";
$testIds = [2, 28, 30, 31];
foreach ($testIds as $tId) {
    $q = $db->query("
        SELECT p.id_pendaftaran, p.tanggal_kunjungan, po.nama_poli, pas.nama_lengkap, pas.nama_ibu_kandung, pas.nama_suami, a.nomor_antrean, a.status_antrean
        FROM pendaftaran p
        LEFT JOIN poli po ON po.id_poli = p.id_poli
        LEFT JOIN pasien pas ON pas.id_pasien = p.id_pasien
        LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
        WHERE p.id_pendaftaran = $tId
        LIMIT 1
    ");
    $row = $q ? $q->fetch_assoc() : null;
    if ($row && !empty($row['nomor_antrean'])) {
        echo "  [PASS] Reg #$tId: Pasien={$row['nama_lengkap']}, Ibu={$row['nama_ibu_kandung']}, Suami=" . ($row['nama_suami'] ?? '-') . ", No.Antrean={$row['nomor_antrean']} ({$row['status_antrean']})\n";
    } else {
        echo "  [FAIL] Reg #$tId data tidak lengkap: " . json_encode($row) . "\n";
        $allPassed = false;
    }
}

// 4. Test Query Admin Pendaftaran pada seluruh 28 data
echo "\n4. Simulasi Query Admin Pendaftaran (28 Data):\n";
$adminQ = $db->query("
    SELECT p.*,
           pa.nama_lengkap, pa.nik, pa.nama_ibu_kandung, pa.nama_suami, pa.tanggal_lahir, pa.jenis_kelamin,
           pa.no_telepon, pa.email, pa.alamat, pa.pekerjaan, pa.status_perkawinan,
           pa.pendidikan_terakhir, pa.agama, pa.ada_rujukan, pa.asal_rujukan AS asal_rujukan_pasien,
           po.nama_poli,
           COALESCE(d.nama_dokter, b.nama_bidan, '-') AS tenaga_medis,
           d.spesialisasi,
           a.nomor_antrean, a.estimasi_waktu, a.status_antrean,
           r.nama_perujuk, r.file_rujukan
    FROM pendaftaran p
    LEFT JOIN pasien pa ON pa.id_pasien = p.id_pasien
    LEFT JOIN poli po ON po.id_poli = p.id_poli
    LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
    LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    LEFT JOIN rujukan_pendaftaran r ON r.id_pendaftaran = p.id_pendaftaran
    ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
");
if ($adminQ && $adminQ->num_rows === 28) {
    echo "  [PASS] Seluruh 28 data pendaftaran berhasil dimuat lengkap dengan relasi antrean, poli, staff, dan ibu/suami.\n";
} else {
    echo "  [FAIL] Admin query mengembalikan " . ($adminQ ? $adminQ->num_rows : 0) . " baris (diharapkan 28)!\n";
    $allPassed = false;
}

// 5. Test Query Admin Data Pasien pada seluruh 10 data
echo "\n5. Simulasi Query Admin Data Pasien (10 Pasien):\n";
$pasienQ = $db->query("
    SELECT p.*, 
           COUNT(pd.id_pendaftaran) AS jumlah_kunjungan,
           MAX(pd.tanggal_kunjungan) AS kunjungan_terakhir
    FROM pasien p
    LEFT JOIN pendaftaran pd ON pd.id_pasien = p.id_pasien
    GROUP BY p.id_pasien
    ORDER BY p.id_pasien DESC
");
if ($pasienQ && $pasienQ->num_rows === 10) {
    echo "  [PASS] Seluruh 10 pasien berhasil dimuat lengkap dengan riwayat kunjungan dan data baru.\n";
} else {
    echo "  [FAIL] Pasien query mengembalikan " . ($pasienQ ? $pasienQ->num_rows : 0) . " baris (diharapkan 10)!\n";
    $allPassed = false;
}

echo "\n======================================================\n";
if ($allPassed) {
    echo ">>> SELURUH TES RUNTIME PADA DATA EXISTING SUKSES 100%! <<<\n";
} else {
    echo ">>> TERDAPAT KEGAGALAN PADA TES RUNTIME! <<<\n";
}
echo "======================================================\n";
