<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== INSPEKSI TABEL USERS & LINKING PASIEN ===\n\n";
$resCols = $db->query("SHOW COLUMNS FROM users");
$cols = [];
while ($r = $resCols->fetch_assoc()) $cols[] = $r['Field'];
echo "Kolom users: " . implode(', ', $cols) . "\n\n";

$resU = $db->query("SELECT * FROM users ORDER BY id_user ASC");
while ($u = $resU->fetch_assoc()) {
    $resP = $db->query("SELECT id_pasien, nama_lengkap, nik, nama_ibu_kandung, nama_suami FROM pasien WHERE id_user = {$u['id_user']} LIMIT 1");
    $p = ($resP && $resP->num_rows > 0) ? $resP->fetch_assoc() : null;
    $pasienInfo = $p ? "Pasien ID #{$p['id_pasien']} (NIK: {$p['nik']}, Nama: {$p['nama_lengkap']}, Ibu: " . ($p['nama_ibu_kandung'] ?? '-') . ", Suami: " . ($p['nama_suami'] ?? '-') . ")" : "TIDAK ADA RECORD PASIEN";
    echo sprintf(
        "User #%02d | Role: %-8s | Username: %-15s | %s\n",
        $u['id_user'],
        $u['role'] ?? '-',
        $u['username'] ?? '-',
        $pasienInfo
    );
}
