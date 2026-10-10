<?php
$f = file('C:/Users/arien/.gemini/antigravity-ide/brain/aa21b790-7149-4b2b-8218-a04130a90aa0/.system_generated/logs/transcript_full.jsonl');
foreach ($f as $idx => $line) {
    $d = json_decode($line, true);
    if (!$d) continue;
    if (($d['type'] ?? '') === 'PLANNER_RESPONSE') {
        $content = $d['content'] ?? '';
        if (strpos($content, 'Hero') !== false || strpos($content, 'hero') !== false) {
            echo "Step $idx Planner:\n" . substr($content, 0, 500) . "\n-----------------\n";
        }
    }
}
