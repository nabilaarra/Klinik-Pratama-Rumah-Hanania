<?php
$brainDir = 'C:/Users/arien/.gemini/antigravity-ide/brain';
$dirs = glob($brainDir . '/*', GLOB_ONLYDIR);
foreach ($dirs as $d) {
    $tFile = $d . '/.system_generated/logs/transcript.jsonl';
    if (!file_exists($tFile)) continue;
    
    $lines = file($tFile);
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (isset($data['type']) && $data['type'] === 'USER_INPUT') {
            $c = $data['content'] ?? '';
            if (stripos($c, 'poin') !== false || stripos($c, 'review') !== false || stripos($c, 'google') !== false || stripos($c, 'kemarin') !== false) {
                echo "[" . basename($d) . "]\nUSER: " . substr($c, 0, 400) . "\n-------------------------\n";
            }
        }
    }
}
