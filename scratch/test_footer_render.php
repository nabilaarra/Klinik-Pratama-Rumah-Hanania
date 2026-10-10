<?php
require_once __DIR__ . '/../config/database.php';
ob_start();
require __DIR__ . '/../includes/footer.php';
$out = ob_get_clean();

echo (strpos($out, 'Sukadamai') !== false ? "Address OK\n" : "Address FAIL\n");
echo (strpos($out, '088510703839') !== false ? "WA1 OK\n" : "WA1 FAIL\n");
echo (strpos($out, '085714178178') !== false ? "WA2 OK\n" : "WA2 FAIL\n");
echo (strpos($out, '524k7ABe1fckKgpt6') !== false ? "Maps OK\n" : "Maps FAIL\n");
