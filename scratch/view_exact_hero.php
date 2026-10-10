<?php
$f = "C:/Users/arien/.gemini/antigravity-ide/brain/cf91adbd-4179-47b3-80ec-2fe19510e5f4/.system_generated/logs/transcript_full.jsonl";
$fp = fopen($f, 'r');
while (($l = fgets($fp)) !== false) {
    if (strpos($l, 'Update desktop hero-media-wrapper') !== false) {
        $d = json_decode($l, true);
        foreach ($d['tool_calls'] ?? [] as $c) {
            echo "TARGET CONTENT:\n" . ($c['args']['TargetContent'] ?? '') . "\n";
            echo "REPLACEMENT CONTENT:\n" . ($c['args']['ReplacementContent'] ?? '') . "\n";
        }
    }
}
fclose($fp);
