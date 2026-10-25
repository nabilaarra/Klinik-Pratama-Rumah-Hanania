<?php
$lines = file('C:/Users/arien/.gemini/antigravity-ide/brain/9c0a1de2-4079-4a77-8623-f58e5da7869f/.system_generated/logs/transcript.jsonl');
foreach ($lines as $l) {
    $d = json_decode($l, true);
    if (isset($d['type']) && $d['type'] === 'USER_INPUT') {
        echo "USER: " . $d['content'] . "\n=========================\n";
    }
}
