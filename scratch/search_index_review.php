<?php
$content = file_get_contents('index.php');
if (preg_match_all('/.{0,40}(?:google|review|ulasan).{0,40}/i', $content, $m)) {
    foreach (array_slice($m[0], 0, 15) as $match) {
        echo trim($match) . "\n---\n";
    }
} else {
    echo "No match\n";
}
