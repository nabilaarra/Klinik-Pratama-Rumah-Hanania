<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';

$files = [
    'admin/index.php',
    'admin/dokter.php',
    'admin/jadwal.php',
    'admin/pasien.php',
    'admin/pendaftaran.php',
    'admin/laporan.php',
    'admin/chatbot.php',
    'admin/konten.php',
    'admin/logout.php'
];

foreach ($files as $f) {
    ob_start();
    try {
        include __DIR__ . '/../' . $f;
        $out = ob_get_clean();
        $hasErr = (stripos($out, 'Fatal error') !== false || stripos($out, 'Parse error') !== false);
        echo "$f => " . ($hasErr ? "[FATAL ERROR]" : "[OK - Length: " . strlen($out) . "]") . "\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "$f => EXCEPTION: " . $e->getMessage() . "\n";
    }
}
