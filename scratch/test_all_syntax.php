<?php
$files = [
    'admin/poin.php',
    'admin/konten.php',
    'admin/_guard.php',
    'layanan/index.php',
    'tentang/visi_misi.php',
    'tentang/struktur_organisasi.php',
    'tentang/sejarah klinik.php',
    'tentang/profil klinik.php',
    'tentang/fasilitas klinik.php',
    'pasien/daftar.php',
    'pasien/antrian.php',
    'dokter/jadwal.php',
    'dokter/index.php'
];

$allOk = true;
foreach ($files as $f) {
    $full = __DIR__ . '/../' . $f;
    if (!file_exists($full)) {
        echo "MISSING: $f\n";
        $allOk = false;
        continue;
    }
    $res = exec('d:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe -l "' . $full . '"', $out, $code);
    if ($code !== 0) {
        echo "ERROR in $f: $res\n";
        $allOk = false;
    } else {
        echo "OK: $f\n";
    }
}

if ($allOk) {
    echo "\n>>> ALL 13 FILES PASSED SYNTAX CHECK CLEANLY! <<<\n";
}
