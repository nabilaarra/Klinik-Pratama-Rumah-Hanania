<?php
function testUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, $response];
}

echo "Testing Endpoints...\n";

// 1. login.php
list($code1, $res1) = testUrl("http://localhost/Klinik-Pratama-Rumah-Hanania/login.php");
echo "1. login.php: HTTP $code1\n";
echo "   - Contains toggle-password-btn: " . (strpos($res1, 'toggle-password-btn') !== false ? "YES" : "NO") . "\n";
echo "   - Contains lupa_password.php: " . (strpos($res1, 'lupa_password.php') !== false ? "YES" : "NO") . "\n";

// 2. lupa_password.php
list($code2, $res2) = testUrl("http://localhost/Klinik-Pratama-Rumah-Hanania/lupa_password.php");
echo "2. lupa_password.php: HTTP $code2\n";
echo "   - Contains Lupa Kata Sandi title: " . (strpos($res2, 'Lupa Kata Sandi') !== false ? "YES" : "NO") . "\n";
echo "   - Contains tanggal_lahir: " . (strpos($res2, 'name="tanggal_lahir"') !== false ? "YES" : "NO") . "\n";
echo "   - Contains password_baru: " . (strpos($res2, 'name="password_baru"') !== false ? "YES" : "NO") . "\n";

// 3. daftar_akun.php
list($code3, $res3) = testUrl("http://localhost/Klinik-Pratama-Rumah-Hanania/daftar_akun.php");
echo "3. daftar_akun.php: HTTP $code3\n";
echo "   - Contains pekerjaan: " . (strpos($res3, 'name="pekerjaan"') !== false ? "YES" : "NO") . "\n";
echo "   - Contains status_perkawinan: " . (strpos($res3, 'name="status_perkawinan"') !== false ? "YES" : "NO") . "\n";
echo "   - Contains pendidikan_terakhir: " . (strpos($res3, 'name="pendidikan_terakhir"') !== false ? "YES" : "NO") . "\n";
echo "   - Contains agama: " . (strpos($res3, 'name="agama"') !== false ? "YES" : "NO") . "\n";
echo "   - Contains toggle-password-btn: " . (strpos($res3, 'toggle-password-btn') !== false ? "YES" : "NO") . "\n";

// 4. index.php
list($code4, $res4) = testUrl("http://localhost/Klinik-Pratama-Rumah-Hanania/index.php");
echo "4. index.php: HTTP $code4\n";
echo "   - Contains hanania-mobile-bar: " . (strpos($res4, 'hanania-mobile-bar') !== false ? "YES" : "NO") . "\n";
echo "   - Contains hanania-mobile-pill: " . (strpos($res4, 'hanania-mobile-pill') !== false ? "YES" : "NO") . "\n";
echo "   - Contains hanania-mobile-actions: " . (strpos($res4, 'hanania-mobile-actions') !== false ? "YES" : "NO") . "\n";
echo "   - Contains hanania-notif-menu: " . (strpos($res4, 'hanania-notif-menu') !== false ? "YES" : "NO") . "\n";
echo "   - Contains HERO MOBILE BACKGROUND: " . (strpos($res4, 'HERO MOBILE BACKGROUND') !== false ? "YES" : "NO") . "\n";
