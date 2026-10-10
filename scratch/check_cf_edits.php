<?php
$f = "C:/Users/arien/.gemini/antigravity-ide/brain/cf91adbd-4179-47b3-80ec-2fe19510e5f4/.system_generated/logs/transcript_full.jsonl";
if (file_exists($f)) {
    $fp = fopen($f, 'r');
    while (($l = fgets($fp)) !== false) {
        $d = json_decode($l, true);
        $tc = $d['tool_calls'] ?? [];
        foreach ($tc as $c) {
            $args = json_decode($c['function']['arguments'] ?? '{}', true);
            if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'index.php') !== false) {
                echo "Desc: " . ($args['Description'] ?? '') . "\n";
                echo "Instruction: " . ($args['Instruction'] ?? '') . "\n";
                echo "Target: " . substr($args['TargetContent'] ?? '', 0, 150) . "...\n";
                echo "Repl: " . substr($args['ReplacementContent'] ?? '', 0, 150) . "...\n";
                echo "---------------------------------\n";
            }
        }
    }
    fclose($fp);
}
