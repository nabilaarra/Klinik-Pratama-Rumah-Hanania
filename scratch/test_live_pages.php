<?php
$pages = [
    'index.php' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/index.php',
    'profil' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/profil%20klinik.php',
    'visi_misi' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/visi_misi.php',
    'sejarah' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/sejarah%20klinik.php',
    'struktur' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/struktur_organisasi.php',
    'fasilitas' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/fasilitas%20klinik.php',
    'admin_konten' => 'http://localhost/Klinik-Pratama-Rumah-Hanania/admin/konten.php'
];

foreach ($pages as $name => $url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    $html = @file_get_contents($url, false, $ctx);
    $status = 'UNKNOWN';
    if (isset($http_response_header[0])) {
        $status = $http_response_header[0];
    }
    $len = strlen($html ?: '');
    echo "$name: $status (bytes: $len)\n";

    // Content checks
    if ($name === 'profil') {
        echo "  - Check WA 1 (0828 1070 3839): " . (strpos($html, '0828 1070 3839') !== false ? "OK" : "NOT FOUND") . "\n";
        echo "  - Check Akreditasi (YM.02.01/D/674/2026): " . (strpos($html, 'YM.02.01/D/674/2026') !== false ? "OK" : "NOT FOUND") . "\n";
    } elseif ($name === 'struktur') {
        echo "  - Check Direktur Utama: " . (strpos($html, 'Direktur Utama') !== false ? "OK" : "NOT FOUND") . "\n";
        echo "  - Check Dr. Fajar: " . (strpos($html, 'Dr. Fajar Delli Wihartiko') !== false ? "OK" : "NOT FOUND") . "\n";
        echo "  - Check PJ Mutu: " . (strpos($html, 'Aida Sa&#039;adah') !== false || strpos($html, "Aida Sa'adah") !== false ? "OK" : "NOT FOUND") . "\n";
    } elseif ($name === 'sejarah') {
        echo "  - Check 2014: " . (strpos($html, 'Bidan Praktik Mandiri Gita Marissa') !== false ? "OK" : "NOT FOUND") . "\n";
        echo "  - Check Lightbox Modal: " . (strpos($html, 'sejarahLightbox') !== false ? "OK" : "NOT FOUND") . "\n";
    } elseif ($name === 'index.php') {
        echo "  - Check hero-slide-item: " . (strpos($html, 'hero-slide-item') !== false ? "OK" : "NOT FOUND") . "\n";
        echo "  - Check checkHeroLandscape: " . (strpos($html, 'checkHeroLandscape') !== false ? "OK" : "NOT FOUND") . "\n";
    }
}
