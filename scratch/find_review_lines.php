<?php
$lines = file('index.php');
foreach ($lines as $num => $line) {
    if (stripos($line, 'daftarUlasanGoogle') !== false || stripos($line, 'Google Review') !== false || stripos($line, 'ulasan-card') !== false || stripos($line, 'google-rating') !== false) {
        echo ($num + 1) . ": " . trim($line) . "\n";
    }
}
