<?php
$_GET['tanggal'] = date('Y-m-d');
$_GET['poli'] = '1';
$_GET['dokter'] = '1';
ob_start();
include __DIR__ . '/../pasien/get_jadwal.php';
$out = ob_get_clean();
echo "LENGTH: " . strlen($out) . "\n";
echo "OUTPUT:\n" . $out . "\n";
$decoded = json_decode($out, true);
echo "JSON ERROR: " . json_last_error_msg() . "\n";
