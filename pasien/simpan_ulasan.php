<?php
/**
 * Endpoint Simpan Ulasan Google / Website Pasien
 * Menyimpan ulasan baru ke pengaturan_konten dan memberikan +1 Poin Reward bagi pasien terdaftar yang login.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}
$db->set_charset('utf8mb4');

$defaultReviews = [
    [
        'id' => 'gr_1',
        'nama' => 'Siti Nurhaliza',
        'badge' => 'Local Guide • Google Maps',
        'rating' => 5,
        'ulasan' => 'Pelayanan di Klinik Rumah Hanania sangat memuaskan dan ramah sekali. Ruang tunggu bersih, nyaman, dan ber-AC. Pelayanan bidan Bu Gita sangat teliti saat konsultasi kehamilan dan USG. Penjelasannya sangat menenangkan. Sangat recommended untuk warga Bogor dan sekitarnya!',
        'layanan' => 'Poli Kebidanan & USG',
        'sumber' => 'Google Maps',
        'tanggal' => '1 minggu lalu'
    ],
    [
        'id' => 'gr_2',
        'nama' => 'Dedi Kurniawan',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Dokter umumnya sangat teliti memeriksa keluhan. dr. Mona komunikatif, obatnya manjur, dan biaya periksa sangat terjangkau. Pendaftaran online di website ini juga sangat mempermudah tidak perlu antre lama di klinik.',
        'layanan' => 'Poli Umum',
        'sumber' => 'Google Maps',
        'tanggal' => '2 minggu lalu'
    ],
    [
        'id' => 'gr_3',
        'nama' => 'Rina Rahmawati',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Alhamdulillah periksa kehamilan dan ANC di sini selalu nyaman. Bidan Gita Marissa sangat sabar, ramah, dan telaten. Alat USG jelas dan fasilitas klinik lengkap. Sukses selalu Rumah Hanania!',
        'layanan' => 'Poli Kebidanan (ANC)',
        'sumber' => 'Google Maps',
        'tanggal' => '3 minggu lalu'
    ],
    [
        'id' => 'gr_4',
        'nama' => 'Agus Setiawan',
        'badge' => 'Local Guide • Google Maps',
        'rating' => 5,
        'ulasan' => 'Klinik pratama terbaik di Tanah Sareal Bogor. Pelayanan cepat, staf pendaftaran ramah dan cekatan. Tempatnya adem dan bersih banget. Pelayanan BPJS maupun umum sama-sama dilayani dengan sangat baik.',
        'layanan' => 'Poli Umum & Farmasi',
        'sumber' => 'Google Maps',
        'tanggal' => '1 bulan lalu'
    ],
    [
        'id' => 'gr_5',
        'nama' => 'Mega Puspita',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Klinik langganan keluarga. Dari pendaftaran lewat website sampai pemeriksaan di ruang dokter sangat rapi alurnya. Poin reward kunjungan dan voucher diskonnya juga beneran bisa dipakai. Terima kasih Rumah Hanania!',
        'layanan' => 'Poli Umum & Kebidanan',
        'sumber' => 'Google Maps',
        'tanggal' => '1 bulan lalu'
    ]
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // GET: Kembalikan daftar review yang disetujui
    $q = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
    $list = [];
    if ($q && $row = $q->fetch_assoc()) {
        $decoded = json_decode($row['nilai'] ?: '[]', true);
        if (is_array($decoded) && !empty($decoded)) {
            $list = $decoded;
        }
    }
    if (empty($list)) {
        $list = $defaultReviews;
    }
    // Hanya tampilkan review yang berstatus disetujui (atau ulasan default) di website publik
    $approvedList = array_values(array_filter($list, function ($r) {
        return ($r['status'] ?? 'disetujui') === 'disetujui';
    }));
    echo json_encode(['success' => true, 'reviews' => $approvedList]);
    exit;
}

// POST: Simpan review baru
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);
if (!is_array($postData)) {
    $postData = $_POST;
}

$rating = max(1, min(5, (int)($postData['rating'] ?? 5)));
$ulasan = trim((string)($postData['ulasan'] ?? ''));
$layanan = trim((string)($postData['layanan'] ?? 'Pelayanan Klinik'));

if ($ulasan === '') {
    echo json_encode(['success' => false, 'message' => 'Mohon tuliskan isi ulasan Anda terlebih dahulu.']);
    exit;
}

// Cek kebijakan auto-approve dari pengaturan_konten (default: bintang 5 saja)
$minStarAuto = 5;
$qMinStar = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'ulasan_auto_approve_min_star' LIMIT 1");
if ($qMinStar && $rMin = $qMinStar->fetch_assoc()) {
    $val = (int)$rMin['nilai'];
    if ($val >= 1 && $val <= 5) {
        $minStarAuto = $val;
    }
}

// Kebijakan: Rating 5 langsung disetujui (auto-approved).
// Rating di bawah 5 (1, 2, 3, 4) berstatus 'menunggu' untuk diverifikasi admin terlebih dahulu.
$isApproved = ($rating >= $minStarAuto);
$statusReview = $isApproved ? 'disetujui' : 'menunggu';

// Cek apakah ada sesi login pasien
$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? 0);
$idPasienSession = (int)($_SESSION['id_pasien'] ?? $_SESSION['user']['id_pasien'] ?? 0);

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

$namaInput = trim((string)($postData['nama'] ?? ''));
if ($namaInput !== '') {
    $namaFinal = $namaInput;
} elseif ($pasien && !empty($pasien['nama_lengkap'])) {
    $namaFinal = $pasien['nama_lengkap'];
} else {
    $namaFinal = 'Pasien Hanania';
}

// Buat item review baru
$newReview = [
    'id' => 'web_' . time() . '_' . mt_rand(100, 999),
    'nama' => htmlspecialchars($namaFinal, ENT_QUOTES, 'UTF-8'),
    'badge' => $pasien ? 'Pasien Terdaftar • Website' : 'Pengunjung Website',
    'rating' => $rating,
    'ulasan' => htmlspecialchars($ulasan, ENT_QUOTES, 'UTF-8'),
    'layanan' => htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8'),
    'sumber' => 'Website Langsung',
    'tanggal' => 'Hari Ini',
    'status' => $statusReview,
    'created_at' => date('Y-m-d H:i:s')
];

// Ambil data ulasan yang ada di database
$currentReviews = [];
$qRev = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
if ($qRev && $rowRev = $qRev->fetch_assoc()) {
    $decoded = json_decode($rowRev['nilai'] ?: '[]', true);
    if (is_array($decoded) && !empty($decoded)) {
        $currentReviews = $decoded;
    }
}
if (empty($currentReviews)) {
    $currentReviews = array_map(function($r) {
        if (!isset($r['status'])) $r['status'] = 'disetujui';
        return $r;
    }, $defaultReviews);
}

// Sisipkan ulasan baru di urutan paling atas
array_unshift($currentReviews, $newReview);

// Simpan ke pengaturan_konten
$jsonUpdated = json_encode($currentReviews, JSON_UNESCAPED_UNICODE);
$stmtSet = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('daftar_ulasan_google', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
if ($stmtSet) {
    $stmtSet->bind_param('s', $jsonUpdated);
    $stmtSet->execute();
    $stmtSet->close();
}

// Logika +1 Poin Reward jika pasien sudah login
$poinDidapat = 0;
$newPoin = $pasien ? (int)$pasien['reward_poin'] : 0;
$pesanPoin = '';

if ($pasien) {
    $idPas = (int)$pasien['id_pasien'];
    $claimedKey = 'google_review_claimed_pasien_ids';
    $claimedJson = '[]';
    $qC = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = '$claimedKey' LIMIT 1");
    if ($qC && $rowC = $qC->fetch_assoc()) {
        $claimedJson = $rowC['nilai'] ?: '[]';
    }
    $claimedList = json_decode($claimedJson, true);
    if (!is_array($claimedList)) $claimedList = [];

    if (!in_array($idPas, $claimedList, true)) {
        // Berikan +1 poin
        $claimedList[] = $idPas;
        $newClaimedJson = json_encode(array_values(array_unique($claimedList)));
        $stmtC = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        if ($stmtC) {
            $stmtC->bind_param('ss', $claimedKey, $newClaimedJson);
            $stmtC->execute();
            $stmtC->close();
        }

        $db->query("UPDATE pasien SET reward_poin = reward_poin + 1 WHERE id_pasien = $idPas");
        $poinDidapat = 1;
        $newPoin = (int)$pasien['reward_poin'] + 1;
        if (isset($_SESSION['user']['reward_poin'])) {
            $_SESSION['user']['reward_poin'] = $newPoin;
        }
        $pesanPoin = " Selamat! Anda mendapatkan +1 Poin Reward Hanania.";
    } else {
        $pesanPoin = " (Ulasan telah tercatat. Anda sebelumnya sudah mengklaim poin reward review).";
    }
}

// Pesan respon berdasarkan status persetujuan
if ($isApproved) {
    $pesanRespon = 'Terima kasih banyak atas ulasan Anda! Ulasan bintang 5 Anda langsung tampil di website.' . $pesanPoin;
} else {
    $pesanRespon = 'Terima kasih atas penilaian dan masukan Anda! Penilaian bintang ' . $rating . ' Anda telah kami terima dan akan diverifikasi oleh Admin terlebih dahulu sebelum ditampilkan di website.' . $pesanPoin;
}

echo json_encode([
    'success' => true,
    'is_approved' => $isApproved,
    'status' => $statusReview,
    'message' => $pesanRespon,
    'poin_didapat' => $poinDidapat,
    'new_poin' => $newPoin,
    'is_logged_in' => (bool)$pasien,
    'review' => $newReview
]);
exit;
