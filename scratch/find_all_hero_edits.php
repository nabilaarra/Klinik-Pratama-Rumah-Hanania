<?php
$dirs = glob('C:/Users/arien/.gemini/antigravity-ide/brain/*/.system_generated/logs/transcript_full.jsonl');
foreach ($dirs as $file) {
    $fp = fopen($file, 'r');
    if (!$fp) continue;
    while (($line = fgets($fp)) !== false) {
        if (strpos($line, 'hero') !== false && strpos($line, 'replace_file_content') !== false) {
            $d = json_decode($line, true);
            $tc = $d['tool_calls'] ?? [];
            foreach ($tc as $c) {
                if (($c['function']['name'] ?? '') === 'replace_file_content') {
                    $args = json_decode($c['function']['arguments'] ?? '{}', true);
                    if (strpos($args['TargetFile'] ?? '', 'index.php') !== false) {
                        echo "File: $file\n";
                        echo "Desc: " . ($args['Description'] ?? '') . "\n";
                        echo "Target: " . substr($args['TargetContent'] ?? '', 0, 100) . "...\n";
                        echo "Repl: " . substr($args['ReplacementContent'] ?? '', 0, 100) . "...\n";
                        echo "========================\n";
                    }
                }
            }
        }
    }
    fclose($fp);
}
