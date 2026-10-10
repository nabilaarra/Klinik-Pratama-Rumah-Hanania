<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "=== BANNER HERO SIZES ===\n";
$res = $db->query("SELECT id_banner, foto, judul FROM banner_hero WHERE status = 'aktif'");
while ($r = $res->fetch_assoc()) {
    $f = __DIR__ . '/../' . ltrim($r['foto'], '/');
    if (file_exists($f)) {
        $info = getimagesize($f);
        $w = $info[0];
        $h = $info[1];
        $ratio = round($w / $h, 2);
        echo "ID {$r['id_banner']} ({$r['foto']}): {$w}x{$h} (ratio: {$ratio})\n";
    } else {
        echo "ID {$r['id_banner']} ({$r['foto']}): File NOT found\n";
    }
}
