<?php
$f = file('C:/Users/arien/.gemini/antigravity-ide/brain/aa21b790-7149-4b2b-8218-a04130a90aa0/.system_generated/logs/transcript_full.jsonl');
foreach ($f as $l) {
    $d = json_decode($l, true);
    if ($d && isset($d['type']) && $d['type'] === 'USER_INPUT') {
        echo "=== USER INPUT ===\n" . $d['content'] . "\n";
    }
}
