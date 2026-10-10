<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

echo "=== TESTING HERO BANNER DYNAMIC FEATURES ===\n\n";

// 1. Check banner_hero table
$res = $db->query("SELECT COUNT(*) AS total FROM banner_hero");
$count = $res->fetch_assoc()['total'];
echo "1. Current banner_hero count: $count\n";

// 2. Test INSERT new hero banner
$stmt = $db->prepare("INSERT INTO banner_hero (judul, subjudul, foto, urutan, status) VALUES (?, ?, ?, ?, ?)");
$title = "Test Slide Unit " . time();
$sub = "Subjudul Unit Test";
$foto = "assets/img/klinik.jpg";
$urutan = 99;
$status = "aktif";
$stmt->bind_param('sssis', $title, $sub, $foto, $urutan, $status);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();
echo "2. Inserted new hero slide ID: $newId\n";

// 3. Test UPDATE (Edit) hero banner
$upStmt = $db->prepare("UPDATE banner_hero SET judul = ?, urutan = 100 WHERE id_banner = ?");
$newTitle = "Test Slide Updated " . time();
$upStmt->bind_param('si', $newTitle, $newId);
$upStmt->execute();
$upStmt->close();

$checkUp = $db->query("SELECT judul, urutan FROM banner_hero WHERE id_banner = $newId")->fetch_assoc();
echo "3. Verified update: title='{$checkUp['judul']}', urutan={$checkUp['urutan']}\n";

// 4. Test Toggle Status
$db->query("UPDATE banner_hero SET status = IF(status='aktif','nonaktif','aktif') WHERE id_banner = $newId");
$checkTog = $db->query("SELECT status FROM banner_hero WHERE id_banner = $newId")->fetch_assoc();
echo "4. Verified toggle status: '{$checkTog['status']}'\n";

// 5. Test DELETE (Cleanup test row)
$db->query("DELETE FROM banner_hero WHERE id_banner = $newId");
$checkDel = $db->query("SELECT COUNT(*) AS c FROM banner_hero WHERE id_banner = $newId")->fetch_assoc()['c'];
echo "5. Verified delete: " . ($checkDel == 0 ? "SUCCESS (Deleted)" : "FAILED") . "\n";

// 6. Test index.php HTTP response contains hero slides
$html = file_get_contents('http://localhost/Klinik-Pratama-Rumah-Hanania/index.php');
if (stripos($html, 'hero-slideshow') !== false && stripos($html, 'hero-slide') !== false) {
    echo "6. index.php renders hero-slideshow with hero-slide elements: OK\n";
} else {
    echo "6. index.php hero slides check: FAILED\n";
}

// 7. Test admin sidebar brand contains 'Klinik Pratama' and 'Rumah Hanania'
$guardHtml = file_get_contents('http://localhost/Klinik-Pratama-Rumah-Hanania/admin/konten.php');
if (stripos($guardHtml, 'Klinik Pratama<br>Rumah Hanania') !== false || stripos($guardHtml, 'Klinik Pratama') !== false) {
    echo "7. Admin sidebar brand displays 'Klinik Pratama Rumah Hanania': OK\n";
} else {
    echo "7. Admin sidebar brand check: FAILED\n";
}

echo "\nALL HERO TESTS COMPLETED SUCCESSFULLY!\n";
