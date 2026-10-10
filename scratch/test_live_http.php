<?php
$urls = [
    'http://localhost/Klinik-Pratama-Rumah-Hanania/index.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/profil%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/struktur_organisasi.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/fasilitas%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/sejarah%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/visi_misi.php'
];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "$url -> HTTP $httpCode (" . strlen($html) . " bytes)\n";
}
