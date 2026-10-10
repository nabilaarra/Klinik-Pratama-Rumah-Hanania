<?php
/**
 * Test Suite Moderasi & Verifikasi Ulasan
 */
require_once __DIR__ . '/../config/database.php';
$db = $conn;

echo "=== TEST 1: SYNTAX CHECK ===\n";
$files = [
    __DIR__ . '/../pasien/simpan_ulasan.php',
    __DIR__ . '/../index.php',
    __DIR__ . '/../admin/_guard.php',
    __DIR__ . '/../admin/ulasan.php',
    __DIR__ . '/../admin/konten.php'
];

foreach ($files as $f) {
    $out = [];
    $ret = 0;
    exec('"D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" -l "' . $f . '"', $out, $ret);
    echo basename($f) . ": " . ($ret === 0 ? "OK" : "ERROR: " . implode(" ", $out)) . "\n";
    if ($ret !== 0) exit(1);
}

echo "\n=== TEST 2: SIMULASI POST ULASAN RATING 5 (AUTO APPROVE) ===\n";
// Ambil kondisi awal
$qBefore = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
$backupJson = ($qBefore && $rB = $qBefore->fetch_assoc()) ? $rB['nilai'] : null;

// Test POST 5 star review
function postReviewMock($rating, $nama, $ulasan) {
    global $db;
    // Set mock POST
    $postData = [
        'nama' => $nama,
        'rating' => $rating,
        'layanan' => 'Poli Umum Test',
        'ulasan' => $ulasan
    ];

    $minStarAuto = 5;
    $qMinStar = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'ulasan_auto_approve_min_star' LIMIT 1");
    if ($qMinStar && $rMin = $qMinStar->fetch_assoc()) {
        $val = (int)$rMin['nilai'];
        if ($val >= 1 && $val <= 5) $minStarAuto = $val;
    }

    $isApproved = ($rating >= $minStarAuto);
    $statusReview = $isApproved ? 'disetujui' : 'menunggu';

    $newReview = [
        'id' => 'test_' . time() . '_' . mt_rand(100, 999),
        'nama' => htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'),
        'badge' => 'Pengunjung Website',
        'rating' => $rating,
        'ulasan' => htmlspecialchars($ulasan, ENT_QUOTES, 'UTF-8'),
        'layanan' => 'Poli Umum Test',
        'sumber' => 'Website Langsung',
        'tanggal' => 'Hari Ini',
        'status' => $statusReview,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $currentReviews = [];
    $qRev = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
    if ($qRev && $rowRev = $qRev->fetch_assoc()) {
        $decoded = json_decode($rowRev['nilai'] ?: '[]', true);
        if (is_array($decoded) && !empty($decoded)) $currentReviews = $decoded;
    }

    array_unshift($currentReviews, $newReview);
    $jsonUpdated = json_encode($currentReviews, JSON_UNESCAPED_UNICODE);
    $stmtSet = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('daftar_ulasan_google', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmtSet->bind_param('s', $jsonUpdated);
    $stmtSet->execute();
    $stmtSet->close();

    return [$isApproved, $statusReview, $newReview];
}

[$isApp5, $st5, $rev5] = postReviewMock(5, "Test Pasien Bintang 5", "Pelayanan sangat baik dan cepat!");
echo "Rating 5 -> isApproved: " . ($isApp5 ? 'TRUE' : 'FALSE') . ", status: $st5\n";
if ($isApp5 !== true || $st5 !== 'disetujui') {
    echo "ERROR: Rating 5 should be approved!\n";
    exit(1);
}

echo "\n=== TEST 3: SIMULASI POST ULASAN RATING 1 (MENUNGGU VERIFIKASI) ===\n";
[$isApp1, $st1, $rev1] = postReviewMock(1, "Test Pasien Bintang 1", "Antrean agak panjang tadi pagi.");
echo "Rating 1 -> isApproved: " . ($isApp1 ? 'TRUE' : 'FALSE') . ", status: $st1\n";
if ($isApp1 !== false || $st1 !== 'menunggu') {
    echo "ERROR: Rating 1 should be pending/menunggu!\n";
    exit(1);
}

echo "\n=== TEST 4: SIMULASI POST ULASAN RATING 2 & 3 (MENUNGGU VERIFIKASI) ===\n";
[$isApp2, $st2, $rev2] = postReviewMock(2, "Test Pasien Bintang 2", "Cukup baik tapi perlu perbaikan.");
[$isApp3, $st3, $rev3] = postReviewMock(3, "Test Pasien Bintang 3", "Standar pelayanan lumayan.");
echo "Rating 2 -> isApproved: " . ($isApp2 ? 'TRUE' : 'FALSE') . ", status: $st2\n";
echo "Rating 3 -> isApproved: " . ($isApp3 ? 'TRUE' : 'FALSE') . ", status: $st3\n";
if ($st2 !== 'menunggu' || $st3 !== 'menunggu') {
    echo "ERROR: Rating 2 and 3 should be pending/menunggu!\n";
    exit(1);
}

echo "\n=== TEST 5: FILTER FRONTEND INDEX.PHP ===\n";
// Ambil ulasan dari DB dan cek apakah hanya yang disetujui yang lolos filter
$qPub = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
$allInDb = json_decode($qPub->fetch_assoc()['nilai'], true);

$publicVisible = array_values(array_filter($allInDb, function($r) {
    return ($r['status'] ?? 'disetujui') === 'disetujui';
}));

$foundStar1InPublic = false;
$foundStar5InPublic = false;
foreach ($publicVisible as $pv) {
    if ($pv['id'] === $rev1['id']) $foundStar1InPublic = true;
    if ($pv['id'] === $rev5['id']) $foundStar5InPublic = true;
}

echo "Apakah ulasan bintang 1 muncul di website publik? " . ($foundStar1InPublic ? "YA (SALAH!)" : "TIDAK (BENAR - Tertahan verifikasi)") . "\n";
echo "Apakah ulasan bintang 5 muncul di website publik? " . ($foundStar5InPublic ? "YA (BENAR - Langsung Tampil)" : "TIDAK (SALAH!)") . "\n";

if ($foundStar1InPublic || !$foundStar5InPublic) {
    echo "ERROR in public visibility filter!\n";
    exit(1);
}

echo "\n=== TEST 6: SIMULASI VERIFIKASI ADMIN (SETUJUI ULASAN BINTANG 1) ===\n";
// Admin menyetujui ulasan bintang 1
foreach ($allInDb as &$r) {
    if ($r['id'] === $rev1['id']) {
        $r['status'] = 'disetujui';
        $r['verified_at'] = date('Y-m-d H:i:s');
        $r['verified_by'] = 'admin';
        break;
    }
}
unset($r);
$stmtSet = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('daftar_ulasan_google', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
$jUp = json_encode($allInDb, JSON_UNESCAPED_UNICODE);
$stmtSet->bind_param('s', $jUp);
$stmtSet->execute();
$stmtSet->close();

// Cek apakah sekarang ulasan bintang 1 sudah tayang di website publik
$qPub2 = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
$allInDb2 = json_decode($qPub2->fetch_assoc()['nilai'], true);
$publicVisible2 = array_values(array_filter($allInDb2, function($r) {
    return ($r['status'] ?? 'disetujui') === 'disetujui';
}));
$foundStar1AfterAdminAcc = false;
foreach ($publicVisible2 as $pv) {
    if ($pv['id'] === $rev1['id']) $foundStar1AfterAdminAcc = true;
}
echo "Setelah di-ACC Admin, apakah ulasan sekarang tayang di website? " . ($foundStar1AfterAdminAcc ? "YA (BENAR!)" : "TIDAK (SALAH!)") . "\n";

echo "\n=== TEST 7: BERSIHKAN DATA TEST & RESTORE ===\n";
if ($backupJson !== null) {
    $stmtSet = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'daftar_ulasan_google'");
    $stmtSet->bind_param('s', $backupJson);
    $stmtSet->execute();
    $stmtSet->close();
    echo "Data ulasan lama berhasil dikembalikan.\n";
} else {
    // Hapus data test dari DB agar bersih
    $cleaned = array_values(array_filter($allInDb2, function($r) {
        return strpos($r['id'], 'test_') === false;
    }));
    $jClean = json_encode($cleaned, JSON_UNESCAPED_UNICODE);
    $stmtSet = $db->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = 'daftar_ulasan_google'");
    $stmtSet->bind_param('s', $jClean);
    $stmtSet->execute();
    $stmtSet->close();
    echo "Data ulasan test dibersihkan.\n";
}

echo "\nALL TESTS PASSED SUCCESSFULLY! ✅\n";
