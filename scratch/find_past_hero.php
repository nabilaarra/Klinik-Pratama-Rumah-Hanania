<?php
$f = "C:/Users/arien/.gemini/antigravity-ide/brain/c8351fb3-e3f8-4962-a47c-d134706cee31/.system_generated/logs/transcript_full.jsonl";
if (file_exists($f)) {
    $fp = fopen($f, 'r');
    while (($l = fgets($fp)) !== false) {
        if (strpos($l, 'hero-media-wrapper') !== false || strpos($l, '<section class="hero"') !== false) {
            echo "Found in c8351fb3:\n" . substr($l, 0, 500) . "\n---\n";
            break;
        }
    }
    fclose($fp);
}

$f2 = "C:/Users/arien/.gemini/antigravity-ide/brain/3b92bae8-bae0-4b58-b23e-78fdc65dc910/.system_generated/logs/transcript_full.jsonl";
if (file_exists($f2)) {
    $fp = fopen($f2, 'r');
    while (($l = fgets($fp)) !== false) {
        if (strpos($l, 'hero-media-wrapper') !== false || strpos($l, 'hero-slideshow') !== false) {
            // let's see tool calls or content
            $d = json_decode($l, true);
            $tc = $d['tool_calls'] ?? [];
            foreach ($tc as $c) {
                $args = json_decode($c['function']['arguments'] ?? '{}', true);
                if (isset($args['ReplacementContent']) && strpos($args['ReplacementContent'], 'hero') !== false) {
                    echo "Found edit in 3b92bae8:\n" . substr($args['ReplacementContent'], 0, 400) . "\n===\n";
                }
            }
        }
    }
    fclose($fp);
}
