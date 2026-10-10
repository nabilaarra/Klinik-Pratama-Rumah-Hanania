<?php
$pages = [
    'http://localhost/Klinik-Pratama-Rumah-Hanania/index.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/login.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/profil%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/fasilitas%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/struktur_organisasi.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/chatbot/chatbot.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/pasien/daftar.php',
];

foreach ($pages as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $hasFatal = (stripos($response, 'Fatal error') !== false || stripos($response, 'Parse error') !== false);
    echo "$url => HTTP $httpCode" . ($hasFatal ? " [CONTAINS PHP ERROR]" : " [OK]") . "\n";
}
