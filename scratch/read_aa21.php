<?php
$lines = file('C:/Users/arien/.gemini/antigravity-ide/brain/aa21b790-7149-4b2b-8218-a04130a90aa0/.system_generated/logs/transcript.jsonl');
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (isset($data['type']) && $data['type'] === 'USER_INPUT') {
        echo "USER INPUT IN aa21b790:\n" . $data['content'] . "\n====================\n";
    }
}
