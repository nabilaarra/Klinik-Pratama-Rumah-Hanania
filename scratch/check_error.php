<?php
$urls = [
    'http://localhost/Klinik-Pratama-Rumah-Hanania/index.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/profil%20klinik.php',
    'http://localhost/Klinik-Pratama-Rumah-Hanania/tentang/struktur_organisasi.php',
];

foreach ($urls as $u) {
    echo "=== $u ===\n";
    $html = file_get_contents($u);
    preg_match_all('/(Fatal error|Parse error|Warning|Notice).*?<br/i', $html, $matches);
    if (!empty($matches[0])) {
        foreach ($matches[0] as $err) {
            echo strip_tags($err) . "\n";
        }
    } else {
        // Find if 'error' is in the text
        if (stripos($html, 'Fatal error') !== false) {
            $pos = stripos($html, 'Fatal error');
            echo substr($html, $pos, 300) . "\n";
        } else {
            echo "No PHP error string found.\n";
        }
    }
}
