<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';

ob_start();
include __DIR__ . '/../admin/dokter.php';
$html = ob_get_clean();

preg_match_all('/<button[^>]+class="[^"]*btn-(?:edit|foto)-staff[^"]*"[^>]*>/s', $html, $matches);
foreach ($matches[0] as $b) {
    if (strpos($b, 'Aida') !== false || strpos($b, 'Bilqis') !== false || strpos($b, 'Salma') !== false) {
        echo $b . "\n\n";
    }
}
