<?php
$lines = file('index.php');
foreach ($lines as $i => $l) {
    if (strpos($l, '.hero') !== false || strpos($l, 'hero-') !== false || strpos($l, 'highlight-persalinan') !== false) {
        if ($i < 3800) { // inside <style>
            echo ($i + 1) . ': ' . trim($l) . "\n";
        }
    }
}
