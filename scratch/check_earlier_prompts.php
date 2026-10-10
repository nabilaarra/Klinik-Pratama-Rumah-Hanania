<?php
$convs = [
    'cf91adbd-4179-47b3-80ec-2fe19510e5f4',
    'c8351fb3-e3f8-4962-a47c-d134706cee31',
    '3b92bae8-bae0-4b58-b23e-78fdc65dc910'
];

foreach ($convs as $cid) {
    $f = "C:/Users/arien/.gemini/antigravity-ide/brain/$cid/.system_generated/logs/transcript_full.jsonl";
    if (!file_exists($f)) continue;
    $fp = fopen($f, 'r');
    while (($line = fgets($fp)) !== false) {
        $d = json_decode($line, true);
        if (($d['type'] ?? '') === 'USER_INPUT') {
            echo "=== CID: $cid USER INPUT ===\n" . substr($d['content'] ?? '', 0, 400) . "\n\n";
        }
    }
    fclose($fp);
}
