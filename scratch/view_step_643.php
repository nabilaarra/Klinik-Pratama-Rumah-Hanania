<?php
$f = file('C:/Users/arien/.gemini/antigravity-ide/brain/aa21b790-7149-4b2b-8218-a04130a90aa0/.system_generated/logs/transcript_full.jsonl');
$d = json_decode($f[643] ?? '', true);
echo $d['content'] ?? 'Not found';
