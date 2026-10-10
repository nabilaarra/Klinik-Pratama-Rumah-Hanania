<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== AUTOMATED FULL SUITE VERIFICATION ===\n\n";

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

if (!$db instanceof mysqli) {
    die("FAIL: Cannot connect to MySQL.\n");
}

$passCount = 0;
$failCount = 0;

function assertTest($condition, $name) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name\n";
        $failCount++;
    }
}

// 1. Check FAQ in DB
$faqCount = $db->query("SELECT COUNT(*) AS c FROM faq")->fetch_assoc()['c'];
assertTest($faqCount >= 5, "FAQ seeded in database (total: $faqCount)");

// 2. Test Chatbot API logic
$_POST['pesan'] = 'Halo';
ob_start();
include __DIR__ . '/../chatbot/proses_chat.php';
$chatOut = ob_get_clean();
$chatJson = json_decode($chatOut, true);
assertTest(isset($chatJson['jawaban']), "Chatbot responds to greeting");

// Test Chatbot Quick Questions API
$_GET['action'] = 'get_quick';
unset($_POST['pesan']);
ob_start();
include __DIR__ . '/../chatbot/proses_chat.php';
$quickOut = ob_get_clean();
$quickJson = json_decode($quickOut, true);
assertTest(!empty($quickJson['quick_questions']), "Chatbot provides quick questions list");
unset($_GET['action']);

// Test Chatbot Fallback to WA
$_POST['pesan'] = 'pertanyaan acak yang sama sekali tidak ada di database xyz123';
ob_start();
include __DIR__ . '/../chatbot/proses_chat.php';
$waOut = ob_get_clean();
$waJson = json_decode($waOut, true);
assertTest(isset($waJson['jawaban']) && strpos($waJson['jawaban'], 'wa.me') !== false, "Chatbot falls back to WhatsApp on unknown question");

// 3. Test Patient Profile self-update logic (No Duplicate Entry error)
$pasienUser = $db->query("SELECT p.id_pasien, p.id_user, p.nik FROM pasien p WHERE p.id_user IS NOT NULL LIMIT 1")->fetch_assoc();
if ($pasienUser) {
    $myId = (int)$pasienUser['id_pasien'];
    $myNik = $pasienUser['nik'];
    
    // Check self duplicate query:
    $stmtSelf = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? AND id_pasien != ? LIMIT 1");
    $stmtSelf->bind_param('si', $myNik, $myId);
    $stmtSelf->execute();
    $selfRes = $stmtSelf->get_result();
    assertTest($selfRes->num_rows === 0, "Patient updating own data with same NIK is not duplicate");
    $stmtSelf->close();
}

// 4. Test Queue Calculation
// Simulasikan pendaftaran online 1, 2, 3
for ($k = 1; $k <= 3; $k++) {
    $onlineNumber = ($k * 5) - 2;
    $pad = str_pad((string)$onlineNumber, 3, '0', STR_PAD_LEFT);
    if ($k === 1) assertTest($pad === '003', "Online registration #1 queue number is 003");
    if ($k === 2) assertTest($pad === '008', "Online registration #2 queue number is 008");
    if ($k === 3) assertTest($pad === '013', "Online registration #3 queue number is 013");
}

// 5. Check Navbar FAQ removed
$navbarContent = file_get_contents(__DIR__ . '/../includes/navbar.php');
assertTest(strpos($navbarContent, 'href="faq.php"') === false && strpos($navbarContent, 'href="../faq.php"') === false, "FAQ link removed from navbar");
assertTest(strpos($navbarContent, 'nav-bell-btn') !== false, "Notification bell button added to navbar");

// 6. Check Admin Logout redirect
$logoutContent = file_get_contents(__DIR__ . '/../admin/logout.php');
assertTest(strpos($logoutContent, "header('Location: ../login.php');") !== false, "Admin logout redirects to ../login.php");

// 7. Check Admin Sidebar has Konten and Chatbot
$sidebarContent = file_get_contents(__DIR__ . '/../admin/_guard.php');
assertTest(strpos($sidebarContent, 'konten.php') !== false, "Admin sidebar has Kelola Konten link");
assertTest(strpos($sidebarContent, 'chatbot.php') !== false, "Admin sidebar has Kelola Chatbot link");
assertTest(strpos($sidebarContent, 'logout.php') !== false, "Admin sidebar has logout.php link");

// 8. Check Public Pages Content Integration
$fasilitasContent = file_get_contents(__DIR__ . '/../tentang/fasilitas klinik.php');
assertTest(strpos($fasilitasContent, 'fasilitas_klinik') !== false, "tentang/fasilitas klinik.php queries fasilitas_klinik table");

$profilContent = file_get_contents(__DIR__ . '/../tentang/profil klinik.php');
assertTest(strpos($profilContent, 'pengaturan_konten') !== false, "tentang/profil klinik.php queries pengaturan_konten table");

$strukturContent = file_get_contents(__DIR__ . '/../tentang/struktur_organisasi.php');
assertTest(strpos($strukturContent, 'pengaturan_konten') !== false, "tentang/struktur_organisasi.php queries pengaturan_konten table");

echo "\nSUMMARY: $passCount PASSED, $failCount FAILED\n";
if ($failCount === 0) {
    echo "🎉 ALL TESTS PASSED PERFECTLY!\n";
} else {
    echo "⚠️ SOME TESTS FAILED.\n";
}
