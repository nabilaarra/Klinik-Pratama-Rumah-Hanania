<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Database connection failed\n");
}

echo "=== MEMERIKSA STRUKTUR DAN DATA DATABASE SAAT INI ===\n\n";

// 1. Cek tabel-tabel utama
$tables = [];
$res = $db->query("SHOW TABLES");
while ($r = $res->fetch_array()) {
    $tables[] = $r[0];
}
echo "Total tabel ditemukan: " . count($tables) . "\n";

// 2. Cek kolom di pasien
echo "\n--- Kolom Tabel Pasien ---\n";
$pasienCols = [];
$res = $db->query("SHOW COLUMNS FROM pasien");
while ($r = $res->fetch_assoc()) {
    $pasienCols[] = $r['Field'];
}
echo "Kolom pasien: " . implode(', ', $pasienCols) . "\n";
echo "Has nama_ibu_kandung? " . (in_array('nama_ibu_kandung', $pasienCols) ? 'YES' : 'NO') . "\n";
echo "Has nama_suami? " . (in_array('nama_suami', $pasienCols) ? 'YES' : 'NO') . "\n";

// 3. Cek data pasien existing
$res = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN nama_ibu_kandung IS NOT NULL AND nama_ibu_kandung != '' THEN 1 ELSE 0 END) as with_ibu, SUM(CASE WHEN nama_suami IS NOT NULL AND nama_suami != '' THEN 1 ELSE 0 END) as with_suami FROM pasien");
$row = $res->fetch_assoc();
echo "Total Pasien: {$row['total']} | Dengan nama ibu: {$row['with_ibu']} | Dengan nama suami: {$row['with_suami']}\n";

// 4. Cek kolom di keluarga_pasien jika tabel ada
echo "\n--- Kolom Tabel Keluarga Pasien ---\n";
if (in_array('keluarga_pasien', $tables)) {
    $famCols = [];
    $res = $db->query("SHOW COLUMNS FROM keluarga_pasien");
    while ($r = $res->fetch_assoc()) {
        $famCols[] = $r['Field'];
    }
    echo "Kolom keluarga_pasien: " . implode(', ', $famCols) . "\n";
    $res = $db->query("SELECT COUNT(*) as total FROM keluarga_pasien");
    $r = $res->fetch_assoc();
    echo "Total Data Keluarga Pasien: {$r['total']}\n";
} else {
    echo "Tabel keluarga_pasien BELUM ADA!\n";
}

// 5. Cek poli
echo "\n--- Kolom dan Data Tabel Poli ---\n";
$poliCols = [];
$res = $db->query("SHOW COLUMNS FROM poli");
while ($r = $res->fetch_assoc()) {
    $poliCols[] = $r['Field'];
}
echo "Kolom poli: " . implode(', ', $poliCols) . "\n";
$res = $db->query("SELECT id_poli, nama_poli, status, status_operasional, is_24jam, waktu_cutoff_menit, pesan_tutup FROM poli");
while ($r = $res->fetch_assoc()) {
    echo "Poli #{$r['id_poli']}: {$r['nama_poli']} | Status: {$r['status']} | Operasional: {$r['status_operasional']} | 24 Jam: {$r['is_24jam']} | Cutoff: {$r['waktu_cutoff_menit']}m | Pesan: {$r['pesan_tutup']}\n";
}

// 6. Cek pengaturan form pendaftaran
echo "\n--- Pengaturan Form Pendaftaran ---\n";
if (in_array('pengaturan_form_pendaftaran', $tables)) {
    $res = $db->query("SELECT id_field, nama_field, label_field, status, wajib, urutan FROM pengaturan_form_pendaftaran ORDER BY urutan ASC");
    while ($r = $res->fetch_assoc()) {
        echo "Field #{$r['id_field']}: {$r['nama_field']} ('{$r['label_field']}') - Status: {$r['status']} - Wajib: {$r['wajib']} - Urutan: {$r['urutan']}\n";
    }
} else {
    echo "Tabel pengaturan_form_pendaftaran BELUM ADA!\n";
}

// 7. Cek jadwal_dokter dan jadwal_bidan
echo "\n--- Jadwal Dokter & Bidan ---\n";
$resDocCols = $db->query("SHOW COLUMNS FROM jadwal_dokter");
$docCols = [];
while ($r = $resDocCols->fetch_assoc()) $docCols[] = $r['Field'];
echo "Kolom jadwal_dokter: " . implode(', ', $docCols) . "\n";

$resBidanCols = $db->query("SHOW COLUMNS FROM jadwal_bidan");
$bidanCols = [];
while ($r = $resBidanCols->fetch_assoc()) $bidanCols[] = $r['Field'];
echo "Kolom jadwal_bidan: " . implode(', ', $bidanCols) . "\n";

// Cek jadwal persalinan 24 jam
$resJadwal24 = $db->query("
    SELECT jb.id_jadwal_bidan, jb.hari, jb.jam_mulai, jb.jam_selesai, jb.status, b.nama_bidan, p.nama_poli 
    FROM jadwal_bidan jb
    LEFT JOIN bidan b ON b.id_bidan = jb.id_bidan
    LEFT JOIN poli p ON p.id_poli = jb.id_poli
    WHERE jb.id_poli = 5 OR p.nama_poli LIKE '%Persalinan%'
");
echo "Total Jadwal Persalinan 24 Jam: " . ($resJadwal24 ? $resJadwal24->num_rows : 0) . "\n";
while ($r = $resJadwal24->fetch_assoc()) {
    echo "  - {$r['hari']}: {$r['nama_bidan']} ({$r['jam_mulai']} - {$r['jam_selesai']}) Status: {$r['status']}\n";
}

// 8. Cek Data Pendaftaran dan Antrean Existing
echo "\n--- Data Pendaftaran & Antrean Existing ---\n";
$resP = $db->query("SELECT COUNT(*) as total FROM pendaftaran");
$totalP = $resP->fetch_assoc()['total'];
$resA = $db->query("SELECT COUNT(*) as total FROM antrean");
$totalA = $resA->fetch_assoc()['total'];
echo "Total Pendaftaran: {$totalP} | Total Antrean: {$totalA}\n";

$colsPend = [];
$resColsP = $db->query("SHOW COLUMNS FROM pendaftaran");
while ($r = $resColsP->fetch_assoc()) $colsPend[] = $r['Field'];
echo "Kolom pendaftaran: " . implode(', ', $colsPend) . "\n";

$colsAnt = [];
$resColsA = $db->query("SHOW COLUMNS FROM antrean");
while ($r = $resColsA->fetch_assoc()) $colsAnt[] = $r['Field'];
echo "Kolom antrean: " . implode(', ', $colsAnt) . "\n";

// Cek sampel pendaftaran antrean
$resSample = $db->query("
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.id_poli, p.sumber_pendaftaran, a.nomor_antrean, a.status_antrean
    FROM pendaftaran p
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
    LIMIT 10
");
while ($r = $resSample->fetch_assoc()) {
    echo "Reg #{$r['id_pendaftaran']} ({$r['no_pendaftaran']}) | Tgl: {$r['tanggal_kunjungan']} | Poli: {$r['id_poli']} | Sumber: {$r['sumber_pendaftaran']} | No Antrean: {$r['nomor_antrean']} | Status: {$r['status_antrean']}\n";
}
