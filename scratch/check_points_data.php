<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== CEK TABEL DAN DATA POIN ===\n\n";

$res = $db->query("SELECT id_pasien, id_user, nama_lengkap, reward_poin, created_at FROM pasien ORDER BY id_pasien ASC");
while ($r = $res->fetch_assoc()) {
    echo "Pasien #{$r['id_pasien']}: {$r['nama_lengkap']} | Poin: {$r['reward_poin']}\n";
}

echo "\n--- TABEL RIWAYAT / PENUKARAN POIN ---\n";
$chkTable = $db->query("SHOW TABLES LIKE 'penukaran_poin'");
if ($chkTable && $chkTable->num_rows > 0) {
    $resP = $db->query("SELECT * FROM penukaran_poin ORDER BY id_penukaran DESC LIMIT 10");
    while ($r = $resP->fetch_assoc()) {
        echo json_encode($r) . "\n";
    }
} else {
    echo "Tabel penukaran_poin belum ada.\n";
}

$chkHist = $db->query("SHOW TABLES LIKE 'riwayat_poin'");
if ($chkHist && $chkHist->num_rows > 0) {
    echo "Tabel riwayat_poin ADA.\n";
} else {
    echo "Tabel riwayat_poin BELUM ADA.\n";
}
