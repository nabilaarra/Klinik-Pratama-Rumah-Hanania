<?php
/**
 * Endpoint Klaim Poin Google Review
 * Memberikan 1 Poin Reward ke akun pasien yang sudah login setelah memberikan ulasan Google.
 * Mencegah klaim ganda per pasien tanpa perlu mengubah struktur tabel database.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}
$db->set_charset('utf8mb4');

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? 0);
$idPasienSession = (int)($_SESSION['id_pasien'] ?? $_SESSION['user']['id_pasien'] ?? 0);

if ($idUser <= 0 && $idPasienSession <= 0) {
    echo json_encode([
        'success' => false,
        'need_login' => true,
        'message' => 'Silakan login terlebih dahulu untuk mendapatkan +1 Poin Reward.'
    ]);
    exit;
}

// Cari data pasien
$pasien = null;
if ($idPasienSession > 0) {
    $stmt = $db->prepare("SELECT id_pasien, reward_poin, nama_lengkap FROM pasien WHERE id_pasien = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $idPasienSession);
        $stmt->execute();
        $pasien = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if (!$pasien && $idUser > 0) {
    $stmt = $db->prepare("SELECT id_pasien, reward_poin, nama_lengkap FROM pasien WHERE id_user = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $pasien = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$pasien) {
    echo json_encode(['success' => false, 'message' => 'Data pasien tidak ditemukan.']);
    exit;
}

$idPasien = (int)$pasien['id_pasien'];

// Ambil daftar id pasien yang sudah mengklaim review google dari pengaturan_konten
$claimedKey = 'google_review_claimed_pasien_ids';
$claimedJson = '[]';

$qC = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = '$claimedKey' LIMIT 1");
if ($qC && $rowC = $qC->fetch_assoc()) {
    $claimedJson = $rowC['nilai'] ?: '[]';
}

$claimedList = json_decode($claimedJson, true);
if (!is_array($claimedList)) {
    $claimedList = [];
}

// Cek apakah pasien ini sudah pernah klaim
if (in_array($idPasien, $claimedList, true)) {
    echo json_encode([
        'success' => false,
        'already_claimed' => true,
        'current_poin' => (int)$pasien['reward_poin'],
        'message' => 'Anda sudah pernah mengklaim +1 Poin untuk Google Review sebelumnya.'
    ]);
    exit;
}

// Tambahkan id_pasien ke daftar klaim dan simpan ke pengaturan_konten
$claimedList[] = $idPasien;
$newClaimedJson = json_encode(array_values(array_unique($claimedList)));

$stmtSet = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
if ($stmtSet) {
    $stmtSet->bind_param('ss', $claimedKey, $newClaimedJson);
    $stmtSet->execute();
    $stmtSet->close();
}

// Tambah 1 Poin Reward ke pasien
$stmtUpdate = $db->prepare("UPDATE pasien SET reward_poin = reward_poin + 1 WHERE id_pasien = ?");
if ($stmtUpdate) {
    $stmtUpdate->bind_param('i', $idPasien);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}

$newPoin = (int)$pasien['reward_poin'] + 1;

// Update session jika ada
if (isset($_SESSION['user']['reward_poin'])) {
    $_SESSION['user']['reward_poin'] = $newPoin;
}

echo json_encode([
    'success' => true,
    'already_claimed' => false,
    'new_poin' => $newPoin,
    'message' => 'Terima kasih atas ulasan Google Review Anda! +1 Poin Reward berhasil ditambahkan ke akun Anda.'
]);
exit;
