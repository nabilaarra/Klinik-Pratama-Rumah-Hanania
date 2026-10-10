<?php
$files = [
    'index.php',
    'admin/konten.php',
    'admin/pengguna.php',
    'admin/_guard.php',
    'tentang/profil klinik.php',
    'tentang/struktur_organisasi.php',
    'tentang/fasilitas klinik.php',
    'tentang/sejarah klinik.php',
    'tentang/visi_misi.php'
];

$allPassed = true;
foreach ($files as $f) {
    $out = [];
    $ret = 0;
    exec("\"D:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe\" -l \"$f\"", $out, $ret);
    $line = implode(" ", $out);
    echo "$f: $line\n";
    if ($ret !== 0) {
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n>>> ALL 9 FILES PASSED SYNTAX CHECK! <<<\n";
} else {
    echo "\n>>> ERRORS DETECTED! <<<\n";
}
