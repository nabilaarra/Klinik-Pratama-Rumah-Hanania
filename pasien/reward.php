<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak tersedia.');
}
$db->set_charset('utf8mb4');
$base = '/Klinik-Pratama-Rumah-Hanania';

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? 0);
$idPasienSession = (int)($_SESSION['id_pasien'] ?? $_SESSION['user']['id_pasien'] ?? 0);
$isLogin = ($idUser > 0 || $idPasienSession > 0);

$pasien = null;
$rewardPoin = 0;
$namaPasien = 'Sobat Hanania';
$totalKunjungan = 0;
$riwayatKunjungan = [];
$riwayatPenukaran = [];

if ($isLogin && $db instanceof mysqli) {
    // Ambil data pasien
    if ($idUser > 0) {
        $stmt = $db->prepare("SELECT * FROM pasien WHERE id_user = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $pasien = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
    if (!$pasien && $idPasienSession > 0) {
        $stmt = $db->prepare("SELECT * FROM pasien WHERE id_pasien = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $idPasienSession);
            $stmt->execute();
            $pasien = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }

    if ($pasien) {
        $rewardPoin = (int)($pasien['reward_poin'] ?? 50);
        $namaPasien = $pasien['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? 'Pasien';
        $idPasien = (int)$pasien['id_pasien'];

        // Hitung total riwayat pendaftaran
        $qKunjungan = $db->query("SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.status_pendaftaran, pl.nama_poli,
                                         COALESCE(d.nama_dokter, b.nama_bidan, 'Tenaga Medis') as nama_tenaga_medis
                                  FROM pendaftaran p
                                  JOIN poli pl ON p.id_poli = pl.id_poli
                                  LEFT JOIN dokter d ON p.id_dokter = d.id_dokter
                                  LEFT JOIN bidan b ON p.id_bidan = b.id_bidan
                                  WHERE p.id_pasien = $idPasien
                                  ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
                                  LIMIT 5");
        if ($qKunjungan) {
            $riwayatKunjungan = $qKunjungan->fetch_all(MYSQLI_ASSOC);
            $totalKunjungan = count($riwayatKunjungan);
        }

        // Ambil riwayat penukaran poin
        $qPenukaran = $db->query("SELECT * FROM penukaran_poin WHERE id_pasien = $idPasien ORDER BY created_at DESC");
        if ($qPenukaran) {
            $riwayatPenukaran = $qPenukaran->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Helper ambil konten dinamis dari pengaturan_konten
function getPoinKonten($db, $kunci, $default = '') {
    if (!$db instanceof mysqli) return $default;
    $stmt = $db->prepare("SELECT nilai FROM pengaturan_konten WHERE kunci = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $kunci);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (isset($row['nilai']) && trim((string)$row['nilai']) !== '') {
                $stmt->close();
                return $row['nilai'];
            }
        }
        $stmt->close();
    }
    return $default;
}

$poinHeroJudul = getPoinKonten($db, 'poin_hero_judul', 'Hanania Point & Reward');
$poinHeroDeskripsi = getPoinKonten($db, 'poin_hero_deskripsi', 'Dapatkan apresiasi terbaik untuk setiap kunjungan dan konsultasi kesehatan Anda.');
$poinPanduanJudul = getPoinKonten($db, 'poin_panduan_judul', 'Panduan & Cara Penukaran Poin');
$poinPanduanTeks = getPoinKonten($db, 'poin_panduan_teks', "1. Setiap kunjungan pelayanan kesehatan di klinik akan memberikan poin reward ke akun Anda.\n2. Pilih reward atau voucher yang sesuai dengan jumlah poin yang Anda miliki.\n3. Ajukan penukaran dan tunggu persetujuan (ACC) dari Admin Klinik.\n4. Tunjukkan kode voucher resmi yang telah disetujui kepada petugas pendaftaran/kasir saat kunjungan berikutnya.");

// Default Katalog Reward Hanania jika belum diubah oleh admin
$defaultKatalogReward = [
    [
        'id' => 'VCH-GIGI-25',
        'nama' => 'Voucher Diskon Tindakan Poli Gigi Rp 25.000',
        'kategori' => 'Poli Gigi & Mulut',
        'poin' => 100,
        'deskripsi' => 'Berlaku untuk pembersihan karang gigi (scaling), penambalan, atau pencabutan gigi ringan di Klinik Rumah Hanania.',
        'icon' => '🦷',
        'badge' => 'Paling Populer'
    ],
    [
        'id' => 'VCH-KONSUL-GRATIS',
        'nama' => 'Gratis Konsultasi Lanjutan Dokter Umum',
        'kategori' => 'Poli Umum',
        'poin' => 150,
        'deskripsi' => 'Bebas biaya jasa pemeriksaan dan konsultasi dokter umum untuk satu kali kunjungan lanjutan kontrol kesehatan.',
        'icon' => '🩺',
        'badge' => 'Spesial'
    ],
    [
        'id' => 'VCH-VIT-HANANIA',
        'nama' => 'Paket Multivitamin Sehat Hanania (30 Tablet)',
        'kategori' => 'Farmasi & Apotek',
        'poin' => 200,
        'deskripsi' => 'Paket multivitamin lengkap untuk menjaga imunitas dan kebugaran tubuh, dapat diambil langsung di farmasi klinik.',
        'icon' => '💊',
        'badge' => 'Kesehatan'
    ],
    [
        'id' => 'VCH-USG-20',
        'nama' => 'Diskon 20% Pemeriksaan USG Kebidanan',
        'kategori' => 'Poli KIA & Kebidanan',
        'poin' => 250,
        'deskripsi' => 'Potongan 20% biaya layanan USG 2D/3D untuk ibu hamil saat jadwal pemeriksaan dengan dokter spesialis / bidan.',
        'icon' => '👶',
        'badge' => 'Bunda & Anak'
    ],
    [
        'id' => 'VCH-TUMBLER',
        'nama' => 'Merchandise Tumbler & Kit Hand Sanitizer Hanania',
        'kategori' => 'Souvenir Sehat',
        'poin' => 80,
        'deskripsi' => 'Tumbler ramah lingkungan edisi Rumah Hanania dilengkapi dengan botol hand sanitizer saku yang praktis.',
        'icon' => '🎁',
        'badge' => 'Hadiah Langsung'
    ]
];

$katalogJson = getPoinKonten($db, 'poin_katalog_json', '');
$katalogReward = [];
if (!empty($katalogJson)) {
    $decoded = json_decode($katalogJson, true);
    if (is_array($decoded) && !empty($decoded)) {
        $katalogReward = $decoded;
    }
}
if (empty($katalogReward)) {
    $katalogReward = $defaultKatalogReward;
}

// Logika Penukaran Reward (POST) - Membutuhkan ACC Admin
$flashMessage = '';
$flashType = '';
$voucherKlaim = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tukar_poin') {
    if (!$isLogin || !$pasien) {
        $flashType = 'danger';
        $flashMessage = 'Silakan masuk ke akun Anda terlebih dahulu untuk menukarkan poin reward.';
    } else {
        $rewardId = trim($_POST['reward_id'] ?? '');
        $selectedReward = null;
        foreach ($katalogReward as $r) {
            if ($r['id'] === $rewardId) {
                $selectedReward = $r;
                break;
            }
        }

        if (!$selectedReward) {
            $flashType = 'danger';
            $flashMessage = 'Pilihan reward tidak ditemukan.';
        } elseif ($rewardPoin < $selectedReward['poin']) {
            $flashType = 'danger';
            $flashMessage = 'Poin Anda belum mencukupi untuk menukar reward ini (dibutuhkan ' . $selectedReward['poin'] . ' poin).';
        } else {
            $idPasien = (int)$pasien['id_pasien'];
            $kodeKlaim = 'HNA-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            try {
                // Cek apakah ada pengajuan lain yang masih menunggu dan total poinnya melebihi saldo
                $qPending = $db->query("SELECT COALESCE(SUM(poin_digunakan), 0) AS total_pending FROM penukaran_poin WHERE id_pasien = $idPasien AND status = 'menunggu'");
                $totalPending = ($qPending && $rPend = $qPending->fetch_assoc()) ? (int)$rPend['total_pending'] : 0;
                if (($rewardPoin - $totalPending) < $selectedReward['poin']) {
                    throw new Exception('Anda memiliki pengajuan reward lain yang masih menunggu persetujuan admin. Sisa saldo poin aktif Anda tidak mencukupi untuk pengajuan ini.');
                }

                // Masukkan ke tabel penukaran_poin dengan status 'menunggu' (Poin BELUM dipotong, baru dipotong saat di-ACC Admin)
                $stmtIns = $db->prepare("
                    INSERT INTO penukaran_poin (id_pasien, kode_reward, nama_reward, poin_digunakan, kode_voucher, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'menunggu', NOW())
                ");
                if (!$stmtIns) {
                    throw new Exception('Gagal menyimpan pengajuan penukaran poin.');
                }
                $poinPakai = $selectedReward['poin'];
                $stmtIns->bind_param('issis', $idPasien, $selectedReward['id'], $selectedReward['nama'], $poinPakai, $kodeKlaim);
                if (!$stmtIns->execute()) {
                    throw new Exception('Gagal mengeksekusi penukaran poin: ' . $stmtIns->error);
                }
                $stmtIns->close();

                $voucherKlaim = [
                    'kode' => $kodeKlaim,
                    'item' => $selectedReward['nama'],
                    'poin' => $selectedReward['poin'],
                    'status' => 'menunggu'
                ];

                $flashType = 'success';
                $flashMessage = 'Permintaan penukaran reward "' . $selectedReward['nama'] . '" berhasil diajukan! Poin reward Anda belum berkurang dan baru akan dipotong setelah disetujui (ACC) oleh Admin Klinik.';

                // Refresh riwayat penukaran
                $qPenukaran = $db->query("SELECT * FROM penukaran_poin WHERE id_pasien = $idPasien ORDER BY created_at DESC");
                if ($qPenukaran) {
                    $riwayatPenukaran = $qPenukaran->fetch_all(MYSQLI_ASSOC);
                }
            } catch (Exception $e) {
                $flashType = 'danger';
                $flashMessage = 'Terjadi kendala: ' . $e->getMessage();
            }
        }
    }
}

// Hitung Tier Keanggotaan
$tier = 'Reguler';
$tierIcon = '🥉';
$tierColor = '#8A6D3B';
$nextTier = 'Silver';
$targetNext = 100;

if ($rewardPoin >= 300) {
    $tier = 'Gold Member';
    $tierIcon = '🥇';
    $tierColor = '#D4AF37';
    $nextTier = 'Maksimal (Diamond)';
    $targetNext = 300;
    $progressPercent = 100;
} elseif ($rewardPoin >= 100) {
    $tier = 'Silver Member';
    $tierIcon = '🥈';
    $tierColor = '#8C9BAE';
    $nextTier = 'Gold';
    $targetNext = 300;
    $progressPercent = min(100, round(($rewardPoin / 300) * 100));
} else {
    $progressPercent = min(100, round(($rewardPoin / 100) * 100));
}

function eR($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poin &amp; Reward Pasien - Klinik Pratama Rumah Hanania</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --hanania-primary: #cea2fd;
            --hanania-purple-dark: #6c34a3;
            --hanania-button: #8e44ad;
            --hanania-button-hover: #7d389c;
            --hanania-secondary: #a86df7;
            --hanania-accent: #E58735;
            --hanania-gold: #F5A623;
            --hanania-light: #F9F7FC;
            --hanania-border: #E6D3FB;
            --hanania-text: #2A1F3D;
            --hanania-muted: #6B6278;
            --hanania-card: #FFFFFF;
            --hanania-radius: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--hanania-light);
            color: var(--hanania-text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 20px 64px;
        }

        /* Hero Point Card */
        .reward-hero {
            background: linear-gradient(135deg, #7434a9 0%, #9e5ff3 50%, #cea2fd 100%);
            border-radius: var(--hanania-radius);
            padding: 36px;
            color: #FFFFFF;
            margin-bottom: 36px;
            box-shadow: 0 16px 40px rgba(116, 52, 169, 0.18);
            position: relative;
            overflow: hidden;
        }

        .reward-hero::after {
            content: "🎁";
            position: absolute;
            bottom: -20px;
            right: 20px;
            font-size: 140px;
            opacity: 0.12;
            pointer-events: none;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
        }

        .hero-title-area h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .hero-title-area p {
            font-size: 14px;
            opacity: 0.92;
        }

        .tier-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(8px);
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.35);
        }

        .hero-balance {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 24px;
        }

        .balance-number {
            font-size: 54px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -1px;
        }

        .balance-unit {
            font-size: 18px;
            font-weight: 600;
            opacity: 0.88;
        }

        .hero-progress-box {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .progress-label-row {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .progress-bar-bg {
            height: 10px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 99px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #FFD966 0%, #FFA834 100%);
            border-radius: 99px;
            transition: width 0.4s ease;
        }

        /* Flash Message */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #EBF9F1;
            color: #1F7A4D;
            border: 1px solid #B8E8CE;
        }

        .alert-danger {
            background: #FDF2F2;
            color: #B42318;
            border: 1px solid #F8C9C9;
        }

        /* Section Layout */
        .section-header {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--hanania-purple-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Catalog Grid */
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .reward-card {
            background: #FFFFFF;
            border: 1.5px solid var(--hanania-border);
            border-radius: var(--hanania-radius);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
        }

        .reward-card:hover {
            transform: translateY(-3px);
            border-color: var(--hanania-primary);
            box-shadow: 0 12px 30px rgba(116, 52, 169, 0.12);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .reward-icon {
            font-size: 36px;
        }

        .badge-tag {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 99px;
            background: #F8F0FF;
            color: var(--hanania-button);
            border: 1px solid #E4D0FC;
        }

        .reward-category {
            font-size: 12px;
            font-weight: 700;
            color: var(--hanania-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .reward-name {
            font-size: 16px;
            font-weight: 800;
            color: var(--hanania-text);
            margin-bottom: 8px;
            line-height: 1.35;
        }

        .reward-desc {
            font-size: 13px;
            color: var(--hanania-muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid #F0EAF8;
        }

        .cost-label {
            font-size: 11px;
            color: var(--hanania-muted);
            font-weight: 600;
        }

        .cost-val {
            font-size: 17px;
            font-weight: 800;
            color: var(--hanania-purple-dark);
        }

        .btn-tukar {
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            border: none;
            background: var(--hanania-button);
            color: #FFFFFF;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(142, 68, 173, 0.25);
        }

        .btn-tukar:hover:not(:disabled) {
            background: var(--hanania-button-hover);
            transform: translateY(-1px);
        }

        .btn-tukar:disabled {
            background: #E8E2F0;
            color: #A098AD;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* History Table */
        .history-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-border);
            border-radius: var(--hanania-radius);
            padding: 24px;
            margin-bottom: 36px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--hanania-muted);
            background: #FAF8FD;
            border-bottom: 1px solid var(--hanania-border);
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #F0EAF8;
            color: var(--hanania-text);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 800;
        }

        .status-menunggu { background: #FFF9E6; color: #B45309; border: 1px solid #FDE68A; }
        .status-disetujui { background: #EBF9F1; color: #15803D; border: 1px solid #BBF7D0; }
        .status-ditolak { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

        @media (max-width: 768px) {
            .reward-hero {
                padding: 24px;
            }

            .hero-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .catalog-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <!-- Notifikasi -->
        <?php if ($flashMessage !== ''): ?>
            <div class="alert alert-<?= eR($flashType) ?>">
                <span><?= $flashType === 'success' ? '🎉' : '⚠️' ?></span>
                <div><?= $flashMessage ?></div>
            </div>
        <?php endif; ?>

        <!-- Hero Saldo Poin / Ajakan Bergabung Sahabat Hanania -->
        <section class="reward-hero">
            <?php if ($isLogin): ?>
                <div class="hero-top">
                    <div class="hero-title-area">
                        <span style="display:inline-block;background:rgba(255,255,255,0.22);color:#FFFFFF;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:800;margin-bottom:8px;letter-spacing:0.5px;border:1px solid rgba(255,255,255,0.3);">
                            ⭐ MEMBER RESMI SAHABAT HANANIA
                        </span>
                        <h1 style="font-size:24px;font-weight:900;color:#FFFFFF;"><?= eR($poinHeroJudul) ?></h1>
                        <p style="font-size:13.5px;opacity:0.92;margin-top:4px;">Halo, <strong><?= eR($namaPasien) ?></strong>! <?= eR($poinHeroDeskripsi) ?></p>
                    </div>
                    <div class="tier-pill">
                        <span><?= $tierIcon ?></span>
                        <span><?= eR($tier) ?></span>
                    </div>
                </div>

                <div class="hero-balance">
                    <div class="balance-number"><?= number_format($rewardPoin, 0, ',', '.') ?></div>
                    <div class="balance-unit">Poin Aktif Tersedia</div>
                </div>

                <div class="hero-progress-box">
                    <div class="progress-label-row">
                        <span>Status Keanggotaan: <?= eR($tier) ?></span>
                        <?php if ($rewardPoin >= 300): ?>
                            <span>Tingkat Tertinggi Terpenuhi! 🏆</span>
                        <?php else: ?>
                            <span>Target <?= eR($nextTier) ?>: <?= number_format($targetNext - $rewardPoin, 0, ',', '.') ?> poin lagi</span>
                        <?php endif; ?>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?= $progressPercent ?>%;"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="hero-top" style="flex-direction:column;align-items:flex-start;gap:12px;">
                    <span style="display:inline-block;background:rgba(255,255,255,0.25);color:#FFFFFF;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:800;letter-spacing:0.5px;border:1px solid rgba(255,255,255,0.35);">
                        🎁 PROGRAM LOYALITAS PASIEN
                    </span>
                    <h1 style="font-size:28px;font-weight:900;color:#FFFFFF;line-height:1.2;margin:0;">
                        Gabung Menjadi Sahabat Hanania
                    </h1>
                    <p style="font-size:14px;opacity:0.94;line-height:1.6;max-width:680px;margin:0;">
                        Daftar sebagai <strong>Sahabat Hanania</strong> untuk mempermudah pendaftaran online, mendapatkan poin reward di setiap kunjungan/ulasan, dan menukarkan voucher potongan biaya pelayanan kesehatan di bawah ini!
                    </p>
                </div>

                <div style="margin:20px 0 16px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:14px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
                    <div>
                        <div style="font-size:12px;opacity:0.9;font-weight:600;">Status Akun Anda Saat Ini:</div>
                        <div style="font-size:15px;font-weight:800;color:#FFFFFF;margin-top:2px;">
                            🔒 Belum Masuk Akun • Silakan Login untuk Akses Poin Anda
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="../login.php?redirect=reward" style="background:#FFFFFF;color:#6A22A7;font-weight:800;font-size:13px;padding:10px 18px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                            🔑 Masuk Akun
                        </a>
                        <a href="../daftar_akun.php?redirect=reward" style="background:rgba(255,255,255,0.22);color:#FFFFFF;border:1.5px solid rgba(255,255,255,0.5);font-weight:800;font-size:13px;padding:10px 18px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            ✨ Daftar Baru
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- INFORMASI & PANDUAN POIN -->
        <?php if (!empty($poinPanduanTeks)): ?>
            <div class="history-card" style="margin-bottom:32px;background:#FAF8FD;border:1.5px solid var(--hanania-border);padding:22px 24px;">
                <div style="font-size:16px;font-weight:800;color:var(--hanania-purple-dark);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                    <span>💡</span> <?= eR($poinPanduanJudul) ?>
                </div>
                <div style="font-size:13px;color:var(--hanania-text);line-height:1.75;white-space:pre-line;">
                    <?= eR($poinPanduanTeks) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- RIWAYAT PENGAJUAN PENUKARAN POIN (STATUS ACC ADMIN) -->
        <?php if ($isLogin): ?>
            <div class="section-header">
                <h2 class="section-title">
                    <span>📑</span> Riwayat Penukaran &amp; Status ACC Admin
                </h2>
            </div>

            <div class="history-card" style="margin-bottom:36px;">
                <?php if (empty($riwayatPenukaran)): ?>
                    <div style="text-align:center;padding:30px;color:var(--hanania-muted);font-size:13px;">
                        Belum ada riwayat penukaran poin. Silakan pilih reward pada katalog di bawah untuk menukarkan poin Anda.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal Pengajuan</th>
                                    <th>Nama Reward / Voucher</th>
                                    <th>Poin Digunakan</th>
                                    <th>Kode Voucher</th>
                                    <th>Status ACC Admin</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayatPenukaran as $rp): ?>
                                    <tr>
                                        <td><strong><?= date('d M Y, H:i', strtotime($rp['created_at'])) ?> WIB</strong></td>
                                        <td>
                                            <strong style="color:var(--hanania-purple-dark);"><?= eR($rp['nama_reward']) ?></strong>
                                        </td>
                                        <td>⭐ <?= number_format($rp['poin_digunakan'], 0, ',', '.') ?> Pts</td>
                                        <td>
                                            <?php if ($rp['status'] === 'disetujui'): ?>
                                                <span style="font-family:monospace;font-weight:800;background:#F3E8FF;color:#6C34A3;padding:4px 8px;border-radius:6px;font-size:13px;">
                                                    <?= eR($rp['kode_voucher']) ?>
                                                </span>
                                            <?php elseif ($rp['status'] === 'menunggu'): ?>
                                                <span style="font-size:12px;color:var(--hanania-muted);font-style:italic;">Menunggu persetujuan</span>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:#DC2626;text-decoration:line-through;"><?= eR($rp['kode_voucher']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rp['status'] === 'disetujui'): ?>
                                                <span class="status-badge status-disetujui">✅ Disetujui (Voucher Aktif)</span>
                                            <?php elseif ($rp['status'] === 'menunggu'): ?>
                                                <span class="status-badge status-menunggu">⏳ Menunggu ACC Admin</span>
                                            <?php else: ?>
                                                <span class="status-badge status-ditolak">❌ Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px;color:var(--hanania-muted);">
                                            <?= eR($rp['catatan_admin'] ?: '-') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Katalog Reward -->
        <div class="section-header">
            <h2 class="section-title">
                <span>🎁</span> Katalog Penukaran Reward &amp; Voucher
            </h2>
        </div>

        <div class="catalog-grid">
            <?php foreach ($katalogReward as $item): ?>
                <?php $cukup = ($isLogin && $rewardPoin >= $item['poin']); ?>
                <div class="reward-card">
                    <div>
                        <div class="card-top">
                            <div class="reward-icon"><?= $item['icon'] ?></div>
                            <span class="badge-tag"><?= eR($item['badge']) ?></span>
                        </div>
                        <div class="reward-category"><?= eR($item['kategori']) ?></div>
                        <h3 class="reward-name"><?= eR($item['nama']) ?></h3>
                        <p class="reward-desc"><?= eR($item['deskripsi']) ?></p>
                    </div>

                    <div class="card-footer">
                        <div>
                            <div class="cost-label">Dibutuhkan</div>
                            <div class="cost-val">⭐ <?= number_format($item['poin'], 0, ',', '.') ?> Pts</div>
                        </div>

                        <?php if (!$isLogin): ?>
                            <a href="../login.php?redirect=reward" class="btn-tukar" style="text-decoration:none; display:inline-block;">
                                Login
                            </a>
                        <?php else: ?>
                            <form method="POST" action="reward.php" onsubmit="return confirm('Ajukan penukaran <?= $item['poin'] ?> poin untuk <?= addslashes($item['nama']) ?>?\n(Penukaran akan diproses dan di-ACC oleh Admin Klinik)');">
                                <input type="hidden" name="action" value="tukar_poin">
                                <input type="hidden" name="reward_id" value="<?= eR($item['id']) ?>">
                                <button type="submit" class="btn-tukar" <?= !$cukup ? 'disabled' : '' ?>>
                                    <?= $cukup ? 'Tukarkan' : 'Poin Kurang' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
