<?php
$f = "C:/Users/arien/.gemini/antigravity-ide/brain/cf91adbd-4179-47b3-80ec-2fe19510e5f4/.system_generated/logs/transcript_full.jsonl";
if (file_exists($f)) {
    $fp = fopen($f, 'r');
    while (($l = fgets($fp)) !== false) {
        if (strpos($l, 'replace_file_content') !== false) {
            $d = json_decode($l, true);
            foreach ($d['tool_calls'] ?? [] as $c) {
                $args = $c['args'] ?? [];
                if (strpos($args['TargetFile'] ?? '', 'index.php') !== false) {
                    if (strpos($args['Description'] ?? '', 'hero') !== false || strpos($args['Instruction'] ?? '', 'hero') !== false || strpos($args['TargetContent'] ?? '', 'hero') !== false) {
                        echo "Desc: " . ($args['Description'] ?? '') . "\n";
                        echo "Target:\n" . substr($args['TargetContent'] ?? '', 0, 300) . "\n---\n";
                        echo "Repl:\n" . substr($args['ReplacementContent'] ?? '', 0, 300) . "\n";
                        echo "=====================================\n";
                    }
                }
            }
        }
    }
    fclose($fp);
}
