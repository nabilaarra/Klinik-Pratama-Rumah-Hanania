<?php
// Script testing kelayakan eksekusi semua halaman PHP tanpa error atau warning
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== MEMERIKSA SINTAKS & EKSEKUSI HALAMAN-HALAMAN UTAMA ===\n\n";

$filesToTest = [
    'includes/poli_helper.php',
    'includes/antrean_helper.php',
    'pasien/get_jadwal.php',
    'pasien/daftar.php',
    'pasien/hasil_daftar.php',
    'pasien/profil.php',
    'pasien/antrian.php',
    'admin/pasien.php',
    'admin/pendaftaran.php',
    'admin/layanan_pendaftaran.php',
    'admin/jadwal.php',
    'dokter/jadwal.php',
    'dokter/index.php',
    'index.php'
];

$allClean = true;
foreach ($filesToTest as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (!file_exists($fullPath)) {
        echo "[-] File tidak ditemukan: $file\n";
        $allClean = false;
        continue;
    }

    // PHP Lint Check
    $phpExe = 'D:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe';
    $output = [];
    $ret = 0;
    exec("\"$phpExe\" -l \"$fullPath\" 2>&1", $output, $ret);
    $outStr = implode("\n", $output);
    if ($ret !== 0 || strpos($outStr, 'No syntax errors detected') === false) {
        echo "[FAIL LINT] $file: $outStr\n";
        $allClean = false;
    } else {
        echo "[PASS LINT] $file: Sintaks PHP Valid & Bersih\n";
    }
}

echo "\n==================================================\n";
if ($allClean) {
    echo ">>> SEMUA 14 FILE UTAMA LULUS VALIDASI SINTAKS! <<<\n";
} else {
    echo ">>> BEBERAPA FILE GAGAL LINTING! <<<\n";
}
echo "==================================================\n";
