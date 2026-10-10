<?php
// Function to convert CID to Place ID or vice versa
// hex1 = 0x2e69c5be26a4c097, hex2 = 0x546bbe409e809edb
// In protobuf:
// field 1 (int64) = 0x2e69c5be26a4c097
// field 2 (int64) = 0x546bbe409e809edb
$hex1 = "2e69c5be26a4c097";
$hex2 = "546bbe409e809edb";

// Varint encoding of 64-bit int
function encodeVarint($val) {
    $out = '';
    while (bccomp($val, '127') > 0) {
        $byte = bcmod($val, '128');
        $out .= chr($byte | 0x80);
        $val = bcdiv(bcsub($val, $byte), '128');
    }
    $out .= chr((int)$val);
    return $out;
}

// Convert hex to decimal string
function hexToDec($hex) {
    $dec = '0';
    $len = strlen($hex);
    for ($i = 0; $i < $len; $i++) {
        $dec = bcmul($dec, '16');
        $dec = bcadd($dec, (string)hexdec($hex[$i]));
    }
    return $dec;
}

$dec1 = hexToDec($hex1);
$dec2 = hexToDec($hex2);

// Tag 1 (field 1, wire type 0 = (1 << 3) | 0 = 0x08)
// Tag 2 (field 2, wire type 0 = (2 << 3) | 0 = 0x10)
$proto = chr(0x08) . encodeVarint($dec1) . chr(0x10) . encodeVarint($dec2);
$placeId = 'ChIJ' . rtrim(strtr(base64_encode($proto), '+/', '-_'), '=');
echo "Place ID: " . $placeId . "\n";
