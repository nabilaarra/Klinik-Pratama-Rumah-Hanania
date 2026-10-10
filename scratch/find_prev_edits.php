<?php
$f = file('C:/Users/arien/.gemini/antigravity-ide/brain/aa21b790-7149-4b2b-8218-a04130a90aa0/.system_generated/logs/transcript_full.jsonl');
foreach ($f as $idx => $l) {
    $d = json_decode($l, true);
    if (!$d) continue;
    $tc = $d['tool_calls'] ?? [];
    foreach ($tc as $c) {
        $name = $c['function']['name'] ?? '';
        $args = json_decode($c['function']['arguments'] ?? '{}', true);
        if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'index.php') !== false) {
            echo "Step $idx ($name): TargetFile: {$args['TargetFile']}\n";
            echo "Instruction: " . ($args['Instruction'] ?? '') . "\n";
            echo "Description: " . ($args['Description'] ?? '') . "\n";
            if (isset($args['StartLine'])) {
                echo "Lines: {$args['StartLine']} - {$args['EndLine']}\n";
            }
            echo "------------------------------------\n";
        }
    }
}
