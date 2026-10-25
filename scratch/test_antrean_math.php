<?php
require_once __DIR__ . '/../includes/antrean_helper.php';

echo "=== TEST ANTREAN HELPER MATH ===\n\n";

$expected = [
    1 => 'offline',
    2 => 'offline',
    3 => 'online',
    4 => 'online',
    5 => 'online',
    6 => 'offline',
    7 => 'offline',
    8 => 'online',
    9 => 'online',
    10 => 'online',
    11 => 'offline',
    12 => 'offline',
    13 => 'online',
    14 => 'online',
    15 => 'online',
    16 => 'offline',
    17 => 'offline',
    18 => 'online',
    19 => 'online',
    20 => 'online',
];

$pass = true;
foreach ($expected as $num => $expChannel) {
    $actChannel = getNomorAntreanChannel($num);
    if ($actChannel !== $expChannel) {
        echo "[FAIL] Number $num expected $expChannel, got $actChannel\n";
        $pass = false;
    }
}

if ($pass) {
    echo "[PASS] All 20 numbers correctly classified!\n";
}

// Test sequence generators:
echo "\nOnline sequence 1..10:\n";
$onlineNums = [];
for ($i = 1; $i <= 10; $i++) {
    $onlineNums[] = getAntreanNomorByUrutan($i, 'online');
}
echo implode(', ', $onlineNums) . "\n";
$expectedOnline = [3, 4, 5, 8, 9, 10, 13, 14, 15, 18];
if ($onlineNums === $expectedOnline) {
    echo "[PASS] Online sequence matches expected!\n";
} else {
    echo "[FAIL] Online sequence mismatch!\n";
    $pass = false;
}

echo "\nOffline sequence 1..8:\n";
$offlineNums = [];
for ($i = 1; $i <= 8; $i++) {
    $offlineNums[] = getAntreanNomorByUrutan($i, 'offline');
}
echo implode(', ', $offlineNums) . "\n";
$expectedOffline = [1, 2, 6, 7, 11, 12, 16, 17];
if ($offlineNums === $expectedOffline) {
    echo "[PASS] Offline sequence matches expected!\n";
} else {
    echo "[FAIL] Offline sequence mismatch!\n";
    $pass = false;
}

if ($pass) {
    echo "\n>>> ALL MATH TESTS PASSED! <<<\n";
}
