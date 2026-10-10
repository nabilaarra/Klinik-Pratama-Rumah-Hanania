<?php
$url = "https://www.google.com/maps/place/Rumah+Hanania+(Praktek+Bersama+Dokter+dan+Bidan)/@-6.5534339,106.7856485,17z/data=!3m1!4b1!4m6!3m5!1s0x2e69c5be26a4c097:0x546bbe409e809edb!8m2!3d-6.5534339!4d106.7882234!16s%2Fg%2F11j94pm98h";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7']);
$res = curl_exec($ch);

// Search for ChIJ
preg_match_all('/ChIJ[a-zA-Z0-9_\-]{20,}/', $res, $matches);
print_r($matches[0]);

// Search for rating pattern
if (preg_match('/\[\"([0-9\.]+)\",\s*([0-9]+)\s*,\s*\"ulasan\"/i', $res, $m2)) {
    echo "Rating match: " . $m2[1] . " with " . $m2[2] . " ulasan\n";
}
