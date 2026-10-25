<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/poli_helper.php';

$sudah_login = isset($_SESSION['id_user']) && $_SESSION['role'] === 'pasien';

$nama_pasien = $_SESSION['nama_lengkap'] ?? 'Pasien';
$id_pasien = $_SESSION['id_pasien'] ?? null;

/*
|--------------------------------------------------------------------------
| DATA REWARD
|--------------------------------------------------------------------------
| Untuk sementara ambil dari session dulu.
| Nanti kalau tabel poin sudah dipakai, tinggal disambungkan ke database.
|--------------------------------------------------------------------------
*/
$poin_reward = $_SESSION['poin_reward'] ?? 0;

/* =====================================================
   SESSION PASIEN
===================================================== */

$isPasienLoggedIn =
    (
        isset($_SESSION['user']) &&
        (($_SESSION['user']['role'] ?? '') === 'pasien')
    )
    ||
    (
        isset($_SESSION['id_user']) &&
        (($_SESSION['role'] ?? '') === 'pasien')
    );

$namaPasien = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['nama']
    ?? $_SESSION['nama_lengkap']
    ?? 'Pasien';

$fotoPasien = $_SESSION['user']['foto']
    ?? $_SESSION['user']['foto_profil']
    ?? $_SESSION['foto']
    ?? '';

$rewardPoints = (int) (
    $_SESSION['user']['poin']
    ?? $_SESSION['user']['points']
    ?? $_SESSION['user']['reward_poin']
    ?? $_SESSION['poin_reward']
    ?? 0
);

$googleReviewClaimed = false;
if ($isPasienLoggedIn) {
    $uid = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? 0);
    $pId = (int)($_SESSION['id_pasien'] ?? $_SESSION['user']['id_pasien'] ?? 0);
    if ($uid > 0 && !$pId && isset($conn) && $conn instanceof mysqli) {
        $stP = $conn->prepare("SELECT id_pasien, reward_poin FROM pasien WHERE id_user = ? LIMIT 1");
        if ($stP) {
            $stP->bind_param("i", $uid);
            $stP->execute();
            $rowP = $stP->get_result()->fetch_assoc();
            if ($rowP) {
                $pId = (int)$rowP['id_pasien'];
                $_SESSION['id_pasien'] = $pId;
                $rewardPoints = (int)$rowP['reward_poin'];
            }
            $stP->close();
        }
    }
    if ($pId > 0 && isset($conn) && $conn instanceof mysqli) {
        $qConf = $conn->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'google_review_claimed_pasien_ids' LIMIT 1");
        if ($qConf && $rConf = $qConf->fetch_assoc()) {
            $claimedIds = json_decode($rConf['nilai'] ?? '[]', true);
            if (is_array($claimedIds) && in_array($pId, $claimedIds)) {
                $googleReviewClaimed = true;
            }
        }
    }
}

/* =====================================================
   LOGOUT
   Logout sengaja ditangani di index supaya setelah klik
   langsung kembali ke halaman utama sebagai guest.
===================================================== */

if (isset($_GET['logout']) && $_GET['logout'] === '1') {

    unset(
        $_SESSION['user'],
        $_SESSION['id_user'],
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['role'],
        $_SESSION['id_pasien'],
        $_SESSION['nama_lengkap'],
        $_SESSION['nik'],
        $_SESSION['email'],
        $_SESSION['foto']
    );

    // Jika tidak ada sesi admin yang sedang aktif, musnahkan sesi sepenuhnya
    if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_id'])) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    header('Location: index.php');
    exit;
}

/* =====================================================
   AMBIL POIN DARI DATABASE JIKA KONEKSI SUDAH TERSEDIA
===================================================== */

$idPasien = (int) (
    $_SESSION['user']['id_pasien']
    ?? $_SESSION['user']['id_user']
    ?? $_SESSION['id_pasien']
    ?? $_SESSION['id_user']
    ?? 0
);

if ($isPasienLoggedIn && $idPasien > 0 && isset($conn) && $conn) {

    $possibleTables = [
        ['pasien', 'id_pasien', 'poin'],
        ['pasien', 'id_pasien', 'points'],
        ['pasien', 'id_pasien', 'reward_poin'],
        ['pasien', 'id_pasien', 'poin_reward']
    ];

    foreach ($possibleTables as [$table, $idColumn, $pointColumn]) {

        $check = @mysqli_query(
            $conn,
            "SHOW COLUMNS FROM `$table` LIKE '$pointColumn'"
        );

        if ($check && mysqli_num_rows($check) > 0) {

            $result = @mysqli_query(
                $conn,
                "SELECT `$pointColumn` AS reward_points
                 FROM `$table`
                 WHERE `$idColumn` = $idPasien
                 LIMIT 1"
            );

            if ($result && ($row = mysqli_fetch_assoc($result))) {
                $rewardPoints = (int) ($row['reward_points'] ?? 0);
                break;
            }
        }
    }
}




/* =====================================================
   DATA DOKTER, BIDAN, POLI & JADWAL
   Data diambil langsung dari database.
   Tampilan/CSS halaman tidak diubah.
===================================================== */

$dataJadwal = [];
$dataStaff = [];
$staffSchedules = [];

if (isset($conn) && $conn) {
    $q = mysqli_query($conn, "SELECT jd.id_dokter AS id_tenaga,d.nama_dokter AS nama_tenaga,d.spesialisasi AS peran,jd.id_poli,p.nama_poli,jd.hari,jd.jam_mulai,jd.jam_selesai,jd.status,'Dokter' AS jenis, d.status AS status_tenaga FROM jadwal_dokter jd INNER JOIN dokter d ON d.id_dokter=jd.id_dokter LEFT JOIN poli p ON p.id_poli=jd.id_poli WHERE d.status != 'nonaktif' ORDER BY jd.hari,jd.jam_mulai,d.id_dokter");
    if ($q) while ($row=mysqli_fetch_assoc($q)) {
        $dataJadwal[]=$row;
        $staffSchedules['Dokter-'.(int)$row['id_tenaga']][]=$row;
    }

    $q = mysqli_query($conn, "SELECT jb.id_bidan AS id_tenaga,b.nama_bidan AS nama_tenaga,'Bidan' AS peran,jb.id_poli,p.nama_poli,jb.hari,jb.jam_mulai,jb.jam_selesai,jb.status,'Bidan' AS jenis, b.status AS status_tenaga FROM jadwal_bidan jb INNER JOIN bidan b ON b.id_bidan=jb.id_bidan LEFT JOIN poli p ON p.id_poli=jb.id_poli WHERE b.status != 'nonaktif' ORDER BY jb.hari,jb.jam_mulai,jb.id_bidan");
    if ($q) while ($row=mysqli_fetch_assoc($q)) {
        $dataJadwal[]=$row;
        $staffSchedules['Bidan-'.(int)$row['id_tenaga']][]=$row;
    }

    $q = mysqli_query($conn, "SELECT id_dokter AS id_tenaga,nama_dokter AS nama_tenaga,spesialisasi AS peran,foto,'Dokter' AS jenis, status AS status_tenaga FROM dokter WHERE status != 'nonaktif' ORDER BY id_dokter");
    if ($q) while ($row=mysqli_fetch_assoc($q)) $dataStaff[]=$row;

    $q = mysqli_query($conn, "SELECT id_bidan AS id_tenaga,nama_bidan AS nama_tenaga,'Bidan' AS peran,foto,'Bidan' AS jenis, status AS status_tenaga FROM bidan WHERE status != 'nonaktif' ORDER BY id_bidan");
    if ($q) while ($row=mysqli_fetch_assoc($q)) $dataStaff[]=$row;

    // Urutan konsisten: DOKTER terlebih dahulu, kemudian BIDAN (Bu Gita Marissa pertama di antara bidan)
    usort($dataStaff, function ($left, $right) {
        $typeOrder = ($left['jenis'] === 'Dokter' ? 0 : 1) <=> ($right['jenis'] === 'Dokter' ? 0 : 1);
        if ($typeOrder !== 0) return $typeOrder;
        if ($left['jenis'] === 'Bidan') {
            $isGitaA = (stripos((string)$left['nama_tenaga'], 'gita') !== false) ? 0 : 1;
            $isGitaB = (stripos((string)$right['nama_tenaga'], 'gita') !== false) ? 0 : 1;
            if ($isGitaA !== $isGitaB) return $isGitaA <=> $isGitaB;
        }
        return strcasecmp((string)$left['nama_tenaga'], (string)$right['nama_tenaga']);
    });
}

$siteKontenMap = [];
if (isset($conn) && $conn) {
    $qK = @mysqli_query($conn, "SELECT kunci, nilai FROM pengaturan_konten");
    if ($qK) {
        while ($kr = mysqli_fetch_assoc($qK)) {
            $siteKontenMap[$kr['kunci']] = $kr['nilai'];
        }
    }
}
function getSiteKonten($key, $default = '') {
    global $siteKontenMap;
    return isset($siteKontenMap[$key]) && trim((string)$siteKontenMap[$key]) !== '' ? $siteKontenMap[$key] : $default;
}

$heroBanners = [];
$heroFoto1 = getSiteKonten('hero_foto_1', 'assets/img/klinik3.jpg');
$heroFoto2 = getSiteKonten('hero_foto_2', 'assets/img/klinik2.jpg');
if (isset($conn) && $conn) {
    // 1. Ambil seluruh slide aktif dari tabel banner_hero
    $qBanner = @mysqli_query($conn, "SELECT foto, judul FROM banner_hero WHERE status = 'aktif' ORDER BY urutan ASC, id_banner ASC");
    if ($qBanner && mysqli_num_rows($qBanner) > 0) {
        while ($br = mysqli_fetch_assoc($qBanner)) {
            $heroBanners[] = $br;
        }
    }
}

function eIndex($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function jamIndex($value) {
    $time=strtotime((string)$value);
    return $time ? date('H.i',$time) : (string)$value;
}
function jadwalRingkasIndex($rows) {
    $hasil=[];
    foreach ($rows as $row) {
        $hasil[] = eIndex($row['hari']).'<br>'.eIndex(jamIndex($row['jam_mulai'])).' - '.eIndex(jamIndex($row['jam_selesai']));
    }
    return implode('<br><br>',$hasil);
}

/* =====================================================
   JADWAL HARI INI
   Tabel jadwal utama hanya menampilkan jadwal sesuai
   hari/tanggal sekarang. Jadwal pada kartu dokter &
   bidan tetap menampilkan seluruh jadwal mereka.
===================================================== */
$namaHariIndonesia = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu'
];

$namaBulanIndonesia = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

$hariIni = $namaHariIndonesia[(int)date('N')];
$tanggalHariIni = date('d') . ' ' . $namaBulanIndonesia[(int)date('n')] . ' ' . date('Y');

$dataJadwalHariIni = array_values(array_filter($dataJadwal, function ($jadwal) use ($hariIni) {
    return strcasecmp(trim((string)$jadwal['hari']), $hariIni) === 0;
}));
$allPoliMapIndex = (isset($conn) && $conn) ? getAllPoliMap($conn) : [];

// Ambil Ulasan Google Maps & Pasien Web
$rawUlasanGoogle = getSiteKonten('daftar_ulasan_google', '');
$daftarUlasanGoogle = [];
if (!empty($rawUlasanGoogle)) {
    $decodedUlasan = json_decode($rawUlasanGoogle, true);
    if (is_array($decodedUlasan) && !empty($decodedUlasan)) {
        $daftarUlasanGoogle = $decodedUlasan;
    }
}
if (empty($daftarUlasanGoogle)) {
    $daftarUlasanGoogle = [
        [
            'id' => 'gr_1',
            'nama' => 'Siti Nurhaliza',
            'badge' => 'Local Guide • Google Maps',
            'rating' => 5,
            'ulasan' => 'Pelayanan di Klinik Rumah Hanania sangat memuaskan dan ramah sekali. Ruang tunggu bersih, nyaman, dan ber-AC. Pelayanan bidan Bu Gita sangat teliti saat konsultasi kehamilan dan USG. Penjelasannya sangat menenangkan. Sangat recommended untuk warga Bogor dan sekitarnya!',
            'layanan' => 'Poli Kebidanan & USG',
            'sumber' => 'Google Maps',
            'tanggal' => '1 minggu lalu',
            'status' => 'disetujui'
        ],
        [
            'id' => 'gr_2',
            'nama' => 'Dedi Kurniawan',
            'badge' => 'Ulasan Terverifikasi • Google Maps',
            'rating' => 5,
            'ulasan' => 'Dokter umumnya sangat teliti memeriksa keluhan. dr. Mona komunikatif, obatnya manjur, dan biaya periksa sangat terjangkau. Pendaftaran online di website ini juga sangat mempermudah tidak perlu antre lama di klinik.',
            'layanan' => 'Poli Umum',
            'sumber' => 'Google Maps',
            'tanggal' => '2 minggu lalu',
            'status' => 'disetujui'
        ],
        [
            'id' => 'gr_3',
            'nama' => 'Rina Rahmawati',
            'badge' => 'Ulasan Terverifikasi • Google Maps',
            'rating' => 5,
            'ulasan' => 'Alhamdulillah periksa kehamilan dan ANC di sini selalu nyaman. Bidan Gita Marissa sangat sabar, ramah, dan telaten. Alat USG jelas dan fasilitas klinik lengkap. Sukses selalu Rumah Hanania!',
            'layanan' => 'Poli Kebidanan (ANC)',
            'sumber' => 'Google Maps',
            'tanggal' => '3 minggu lalu',
            'status' => 'disetujui'
        ],
        [
            'id' => 'gr_4',
            'nama' => 'Agus Setiawan',
            'badge' => 'Local Guide • Google Maps',
            'rating' => 5,
            'ulasan' => 'Klinik pratama terbaik di Tanah Sareal Bogor. Pelayanan cepat, staf pendaftaran ramah dan cekatan. Tempatnya adem dan bersih banget. Pelayanan BPJS maupun umum sama-sama dilayani dengan sangat baik.',
            'layanan' => 'Poli Umum & Farmasi',
            'sumber' => 'Google Maps',
            'tanggal' => '1 bulan lalu',
            'status' => 'disetujui'
        ],
        [
            'id' => 'gr_5',
            'nama' => 'Mega Puspita',
            'badge' => 'Ulasan Terverifikasi • Google Maps',
            'rating' => 5,
            'ulasan' => 'Klinik langganan keluarga. Dari pendaftaran lewat website sampai pemeriksaan di ruang dokter sangat rapi alurnya. Poin reward kunjungan dan voucher diskonnya juga beneran bisa dipakai. Terima kasih Rumah Hanania!',
            'layanan' => 'Poli Umum & Kebidanan',
            'sumber' => 'Google Maps',
            'tanggal' => '1 bulan lalu',
            'status' => 'disetujui'
        ]
    ];
}

// HANYA ulasan berstatus 'disetujui' yang ditampilkan di website publik.
// Ulasan dengan status 'menunggu' (bintang di bawah 5) atau 'ditolak' disembunyikan sampai diverifikasi admin.
$daftarUlasanGoogle = array_values(array_filter($daftarUlasanGoogle, function ($rev) {
    return ($rev['status'] ?? 'disetujui') === 'disetujui';
}));
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Klinik Pratama Rumah Hanania
    </title>

    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        :root {

            --purple: #6A22A7;
            --purple-dark: #4A1278;
            --purple-soft: #F4EEFB;
            --purple-light: #EDE6F8;
            --purple-hero: #F6F1FC;

            --text: #2B1B48;
            --muted: #6B5B82;

            --border: #E8DDF2;

            --white: #FFFFFF;

        }


        html {
            scroll-behavior: smooth;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: var(--text);

            background: #FFFFFF;

            overflow-x: hidden;

        }


        a {
            text-decoration: none;
        }


        button,
        input {
            font-family: inherit;
        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        header {

            position: sticky;

            top: 0;

            z-index: 1000;

            background:
                rgba(255, 255, 255, 0.96);

            border-bottom:
                1px solid var(--border);

            backdrop-filter:
                blur(10px);

        }


        .navbar {

            max-width: 1200px;

            margin: auto;

            padding: 15px 30px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .brand-logo {

            width: 52px;

            height: 52px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-shrink: 0;

            overflow: hidden;

            border-radius: 14px;

            background:
                var(--purple-soft);

        }


        .brand-logo img {

            width: 100%;

            height: 100%;

            object-fit: contain;

            display: block;

        }


        .brand-name {

            color:
                var(--purple-dark);

            font-size: 14px;

            line-height: 1.35;

            font-weight: bold;

        }


        nav {

            display: flex;

            align-items: center;

            gap: 24px;

        }


        nav a {

            color: #555;

            font-size: 14px;

            transition: 0.2s;

        }


        nav a:hover {

            color:
                var(--purple);

        }

        /* =====================================================
        TENTANG DROPDOWN
        ===================================================== */

        .about-dropdown {
            position: relative;
        }

        .about-toggle {
            cursor: pointer;
            color: #555;
            font-size: 14px;
            font-weight: 400;
            white-space: nowrap;
        }

        .about-arrow {
            margin-left: 3px;
            font-size: 11px;
            color: #777;
        }

        .about-menu {
            display: none;
            position: absolute;
            top: calc(100% + 9px);
            left: 0;

            width: 190px;
            padding: 6px;

            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 13px;

            box-shadow: 0 12px 28px rgba(70,45,90,.13);

            z-index: 1200;
        }

        .about-menu.active {
            display: block;
        }

        .about-menu a {
            display: block;
            padding: 9px 11px;
            border-radius: 8px;

            color: #555555;
            font-size: 12px;
            text-decoration: none;
        }

        .about-menu a:hover {
            background: var(--purple-soft);
            color: var(--purple-dark);
        }


        .login-button {

            padding:
                10px 18px;

            background:
                var(--purple);

            color: white !important;

            border-radius: 9px;

            font-weight: bold;

        }


        .login-button:hover {

            background:
                var(--purple-dark);

        }


        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            position: relative;
            padding: 68px 30px 65px;
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            overflow: hidden;
            min-height: 560px;
            display: flex;
            align-items: center;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 480px;
            height: 480px;
            right: -100px;
            bottom: -150px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(106, 34, 167, 0.05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 1;
        }

        .hero-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1200px;
            margin: auto;
            display: flex;
            align-items: center;
        }

        .hero-content {
            max-width: 590px;
        }

        .hero-label {
            display: inline-block;
            margin-bottom: 20px;
            color: #6A22A7;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.6px;
            text-transform: uppercase;
        }

        .hero h1 {
            max-width: 760px;
            margin-bottom: 20px;
            color: #2B1B48;
            font-family: 'Playfair Display', Georgia, "Times New Roman", serif;
            font-size: clamp(38px, 5.2vw, 62px);
            line-height: 1.08;
            letter-spacing: -1.2px;
            font-weight: 900;
        }

        .hero h1 span {
            display: block;
            color: #2B1B48;
        }

        .hero p {
            max-width: 570px;
            margin-bottom: 26px;
            color: #5D4D73;
            line-height: 1.75;
            font-size: 15.5px;
        }

        .hero-info-badges {
            margin-bottom: 22px;
        }

        .highlight-persalinan {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(106, 34, 167, 0.08);
            color: #6A22A7;
            border: 1.5px solid rgba(106, 34, 167, 0.22);
            border-radius: 9999px;
            padding: 7px 18px;
            font-size: 13px;
            font-weight: 800;
        }

        .hero-reward-promo {
            background: rgba(255, 255, 255, 0.95);
            border: 1.5px solid #DFD0F0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(106, 34, 167, 0.06);
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 26px;
            color: #2B1B48;
        }

        .hero-reward-copy {
            flex: 1 1 auto;
            min-width: 0;
            text-align: left;
        }

        .hero-reward-title {
            color: #2B1B48;
            font-weight: 900;
            font-size: 16.5px;
            letter-spacing: -0.3px;
            line-height: 1.25;
            margin-bottom: 4px;
            text-align: left;
        }

        .hero-reward-text {
            color: #5D4D73;
            font-size: 12.5px;
            margin: 0;
            line-height: 1.5;
            text-align: left;
        }

        .hero-reward-action-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            gap: 6px;
            flex-shrink: 0;
            text-align: right;
        }

        .hero-reward-action {
            background: #6A22A7;
            color: #FFFFFF !important;
            font-weight: 800;
            padding: 9px 20px;
            border-radius: 9999px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(106, 34, 167, 0.25);
        }

        .hero-reward-action:hover {
            background: #4A1278;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(106, 34, 167, 0.35);
        }

        .hero-reward-login-link {
            font-size: 11.5px;
            color: #6A22A7;
            font-weight: 700;
            text-decoration: underline;
            white-space: nowrap;
        }

        .hero-reward-points {
            color: #6A22A7;
            font-size: 22px;
            font-weight: 900;
            white-space: nowrap;
            line-height: 1;
        }

        .hero-reward-points span {
            font-size: 12px;
            font-weight: 700;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button-primary {
            display: inline-block;
            padding: 13px 30px;
            border: none;
            border-radius: 9999px;
            background: #6A22A7 !important;
            color: #FFFFFF !important;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 6px 18px rgba(106, 34, 167, 0.28);
            transition: 0.2s;
        }

        .button-primary:hover {
            background: #4A1278 !important;
            color: #FFFFFF !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(106, 34, 167, 0.35);
        }

        .button-outline {
            display: inline-block;
            padding: 12px 28px;
            border: 1.5px solid #D2C1EC;
            border-radius: 9999px;
            background: #FFFFFF !important;
            color: #6A22A7 !important;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(106, 34, 167, 0.06);
            transition: 0.2s;
        }

        .button-outline:hover {
            background: #F4EDFA !important;
            border-color: #6A22A7;
            transform: translateY(-2px);
        }

        /* =====================================================
           HERO IMAGE BANNER (ORIGINAL SLIDESHOW)
        ===================================================== */

        .hero-media-wrapper {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 50%;
            max-width: 680px;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 24px 32px 24px 0;
        }

        .hero-slideshow {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .hero-slide-item {
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
            pointer-events: none;
        }

        .hero-slide-item.active {
            opacity: 1;
            pointer-events: auto;
        }

        .hero-slide-backdrop {
            display: none;
        }

        .hero-slide {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            object-position: right center;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
            filter: none !important;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(43, 27, 72, 0.16);
        }

        .hero-slide.active,
        .hero-slide-item.active .hero-slide {
            opacity: 1;
        }


        /* =====================================================
           QUICK MENU
        ===================================================== */

        .quick-menu-wrapper {

            position: relative;

            z-index: 20;

            max-width: 1200px;

            margin:
                22px auto 0;

            padding:
                0 30px;

        }


        .quick-menu {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            overflow: hidden;

            border:
                1px solid #F0EAF4;

            border-radius: 28px;

            background: white;

            box-shadow:
                0 18px 45px
                rgba(50,35,65,0.12);

        }


        .quick-item {

            min-height: 125px;

            display: flex;

            align-items: center;

            gap: 17px;

            padding:
                24px 25px;

            border-right:
                1px solid var(--border);

            background: white;

            color: var(--text);

            transition: 0.2s;

        }


        .quick-item:last-child {

            border-right: none;

        }


        .quick-item:hover {

            background:
                #FCF9FE;

        }


        .quick-icon {

            flex-shrink: 0;

            width: 52px;

            height: 52px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 15px;

            background:
                var(--purple-soft);

            color:
                var(--purple);

            font-size: 22px;

        }


        .quick-content strong {

            display: block;

            margin-bottom: 7px;

            color: #252525;

            font-size: 15px;

        }


        .quick-content span {

            display: block;

            color:
                var(--muted);

            font-size: 12px;

            line-height: 1.5;

        }


        /* =====================================================
           GENERAL SECTION
        ===================================================== */

        section {

            padding:
                38px 30px;

        }


        .container {

            max-width: 1200px;

            margin: auto;

        }


        .section-heading {

            margin-bottom: 28px;

        }


        .section-label {

            display: block;

            margin-bottom: 8px;

            color:
                var(--purple);

            font-size: 12px;

            font-weight: bold;

            letter-spacing: 0.5px;

        }


        .section-heading h2 {

            margin-bottom: 10px;

            color:
                var(--purple-dark);

            font-size: 34px;

        }


        .section-heading p {

            max-width: 650px;

            color:
                var(--muted);

            line-height: 1.7;

            font-size: 14px;

        }


        /* =====================================================
           POIN / KEUNGGULAN
        ===================================================== */

        .benefits-section {

            background:
                var(--purple-light);

        }


        .benefits-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

        }


        .benefit-card {

            padding: 24px;

            border:
                1px solid var(--border);

            border-radius: 18px;

            background: white;

            transition: 0.25s;

        }


        .benefit-card:hover {

            transform:
                translateY(-4px);

            box-shadow:
                0 12px 30px
                rgba(138,92,183,0.10);

        }


        .benefit-number {

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 16px;

            border-radius: 12px;

            background:
                var(--purple-soft);

            color:
                var(--purple);

            font-weight: 700;

            font-size: 13px;

        }


        .benefit-card h3 {

            margin-bottom: 8px;

            color:
                var(--purple-dark);

            font-size: 16px;

        }


        .benefit-card p {

            color:
                var(--muted);

            font-size: 13px;

            line-height: 1.7;

        }


        /* =====================================================
           LAYANAN
        ===================================================== */

        .services-section {

            background: white;

        }


        .service-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

        }


        .service-card {

            padding: 25px;

            border:
                1px solid var(--border);

            border-radius: 17px;

            background: white;

            transition: 0.25s;

        }


        .service-card:hover {

            transform:
                translateY(-4px);

            box-shadow:
                0 12px 30px
                rgba(138,92,183,0.10);

        }


        .service-icon {

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 17px;

            border-radius: 13px;

            background:
                var(--purple-soft);

            color:
                var(--purple);

            font-size: 21px;

        }


        .service-card h3 {

            margin-bottom: 9px;

            color:
                var(--purple-dark);

            font-size: 17px;

        }


        .service-card p {

            color:
                var(--muted);

            font-size: 13px;

            line-height: 1.7;

        }


        /* =====================================================
           JADWAL
        ===================================================== */

        .schedule-section {

            background:
                var(--purple-light);

        }


        .schedule-today {
            font-size: 28px;
            font-weight: 600;
            color: #56369b;
            margin-top: 12px;
            margin-bottom: 12px;
            line-height: 1.0;
        }

        .schedule-box {

            overflow-x: auto;

            border:
                1px solid var(--border);

            border-radius: 20px;

            background: white;

            box-shadow:
                0 8px 25px
                rgba(80,50,100,0.05);

        }


        .schedule-table {

            width: 100%;

            min-width: 650px;

            border-collapse:
                collapse;

        }


        .schedule-table th {

            padding:
                16px 18px;

            background:
                var(--purple);

            color: white;

            text-align: left;

            font-size: 13px;

        }


        .schedule-table td {

            padding:
                17px 18px;

            border-bottom:
                1px solid var(--border);

            color: #555;

            font-size: 13px;

        }


        .schedule-table tr:last-child td {

            border-bottom: none;

        }


        .schedule-name {

            display: block;

            color: #333;

            font-weight: bold;

        }


        .schedule-role {

            display: block;

            margin-top: 4px;

            color:
                var(--purple);

            font-size: 11px;

        }


        .schedule-status {

            display: inline-block;

            padding:
                5px 10px;

            border-radius: 20px;

            background:
                #EDF8F1;

            color:
                #39845A;

            font-size: 11px;

            font-weight: bold;

        }


        /* =====================================================
           DOKTER & BIDAN
        ===================================================== */

        .staff-section {

            background: white;

        }


        .staff-list {
            display: flex;
            gap: 16px;
            width: 100%;
            overflow-x: auto;
            padding: 4px 2px 12px;
            scroll-snap-type: x mandatory;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .staff-list::-webkit-scrollbar {
            height: 7px;
        }

        .staff-list::-webkit-scrollbar-thumb {
            background: var(--purple-soft);
            border-radius: 20px;
        }


        .staff-card {

            flex: 0 0 calc((100% - 64px) / 5);
            width: calc((100% - 64px) / 5);
            scroll-snap-align: start;

            overflow: hidden;

            padding:
                8px 8px 11px;

            border:
                1px solid var(--border);

            border-radius: 13px;

            background: white;

            box-shadow:
                0 4px 14px
                rgba(70,40,90,0.06);

            transition: 0.2s;

        }


        .staff-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 10px 25px
                rgba(90,50,120,0.12);

        }


        .staff-photo {

            width: 100%;

            height: 132px;

            overflow: hidden;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background:
                #F4F0F6;

            color:
                var(--purple);

            font-size: 11px;

        }


        .staff-photo img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;

        }


        .staff-info {

            padding:
                8px 5px 3px;

            text-align: center;

        }


        .staff-role {

            display: block;

            margin-bottom: 4px;

            color:
                var(--purple);

            font-size: 10px;

            font-weight: 600;

        }


        .staff-info h3 {

            margin-bottom: 7px;

            color: #292929;

            font-size: 13px;

            line-height: 1.3;

        }


        .staff-schedule {

            padding:
                7px 6px;

            border-radius: 6px;

            background:
                #F7F1FA;

            color:
                #666;

            text-align: left;

            font-size: 9.5px;

            line-height: 1.5;

        }


        .staff-schedule strong {

            color:
                var(--purple-dark);

            font-size: 9.5px;

        }


        .staff-action {

            margin-top: 8px;

        }


        .staff-button {

            display: block;

            width: 100%;

            padding:
                8px 5px;

            border-radius: 7px;

            background:
                var(--purple-dark);

            color: white;

            text-align: center;

            font-size: 9.5px;

            font-weight: 600;

            transition: 0.2s;

        }


        .staff-button:hover {

            background:
                #68458E;

        }


        /* =====================================================
           TENTANG KAMI
        ===================================================== */

        .about-section {

            background:
                var(--purple-light);

        }


        .about-container {

            display: grid;

            grid-template-columns:
                0.9fr 1.1fr;

            gap: 12px;

            align-items: center;

        }


        .about-image {

            position: relative;

            height: 370px;

            overflow: hidden;

            border:
                1px solid var(--border);

            border-radius: 22px;

            background: white;

            box-shadow:
                0 12px 35px
                rgba(70,40,90,0.10);

        }


        .about-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            object-position: center;

            display: block;

        }


        .about-image::after {

            content: "";

            position: absolute;

            inset: 0;

            background:
                linear-gradient(
                    135deg,
                    rgba(122,82,163,0.28),
                    rgba(255,255,255,0)
                );

            pointer-events: none;

        }


        .about-text h2 {

            margin-bottom: 15px;

            color:
                var(--purple-dark);

            font-size: 31px;

        }


        .about-text p {

            margin-bottom: 14px;

            color:
                var(--muted);

            line-height: 1.8;

            font-size: 14px;

        }


        /* =====================================================
           FOOTER
        ===================================================== */

        footer, .hanania-footer {
            padding: 55px 24px 35px;
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%) !important;
            color: #2B1B48 !important;
            border-top: 1.5px solid #D8CAEC !important;
        }

        .footer-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }

        .footer-container p {
            margin-top: 5px;
            color: #55416C !important;
            font-size: 12.5px;
            line-height: 1.7;
        }

        .footer-column h4 {
            margin-bottom: 8px;
            font-size: 14px;
            color: #4A1775 !important;
            font-weight: 800;
        }

        .footer-contact {
            color: #55416C !important;
            font-size: 12.5px;
            line-height: 1.8;
        }

        .maps-button {
            display: inline-block;
            margin-top: 10px;
            padding: 9px 18px;
            border-radius: 9999px;
            background: #6A22A7 !important;
            color: #FFFFFF !important;
            font-size: 12.5px;
            font-weight: bold;
            box-shadow: 0 4px 14px rgba(106, 34, 167, 0.25);
            transition: all .2s;
        }

        .maps-button:hover {
            background: #4A1278 !important;
            transform: translateY(-2px);
        }


        /* =====================================================
           HANABOT BUTTON
        ===================================================== */

        .chatbot-button {

            position: fixed;

            right: 28px;

            bottom: 28px;

            width: 225px;

            height: 62px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            border: none;

            border-radius: 32px;

            background:
                var(--purple);

            color: white;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            box-shadow:
                0 10px 30px
                rgba(0,0,0,0.18);

            z-index: 2000;

            transition: 0.25s;

        }


        .chatbot-button:hover {

            background:
                var(--purple-dark);

            transform:
                translateY(-2px);

        }


        .chatbot-icon {

            font-size: 21px;

        }


        /* =====================================================
           HANABOT WINDOW
        ===================================================== */

        .chatbot-window {

            display: none;

            position: fixed;

            right: 28px;

            bottom: 105px;

            width: 470px;

            height: 650px;

            max-height:
                calc(100vh - 125px);

            min-height: 450px;

            overflow: hidden;

            border:
                1px solid var(--border);

            border-radius: 22px;

            background: white;

            box-shadow:
                0 20px 55px
                rgba(0,0,0,0.20);

            z-index: 2001;

        }


        .chatbot-window.active {

            display: flex;

            flex-direction: column;

        }


        .chat-header {

            flex-shrink: 0;

            min-height: 75px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                15px 19px;

            background:
                var(--purple);

            color: white;

        }


        .chat-title {

            display: flex;

            align-items: center;

            gap: 11px;

        }


        .chat-avatar {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: white;

            color:
                var(--purple);

        }


        .chat-title strong {

            display: block;

            font-size: 16px;

        }


        .chat-title small {

            display: block;

            margin-top: 3px;

            font-size: 11px;

            opacity: 0.9;

        }


        .chat-close {

            width: 34px;

            height: 34px;

            border: none;

            border-radius: 50%;

            background:
                rgba(255,255,255,0.18);

            color: white;

            font-size: 19px;

            cursor: pointer;

        }


        .chat-close:hover {

            background:
                rgba(255,255,255,0.3);

        }


        .chat-body {

            flex: 1;

            min-height: 0;

            overflow-y: auto;

            padding: 20px;

            background:
                #FCFAFD;

            font-size: 14px;

        }


        .bot-message {

            width: fit-content;

            max-width: 84%;

            margin-bottom: 12px;

            padding:
                12px 15px;

            border:
                1px solid var(--border);

            border-radius:
                14px 14px 14px 4px;

            background: white;

            color:
                #555;

            line-height: 1.6;

        }


        .user-message {

            margin-left: auto;

            border-radius:
                14px 14px 4px 14px;

            background:
                #F0E8F6;

        }


        .chat-input {

            flex-shrink: 0;

            min-height: 62px;

            display: flex;

            border-top:
                1px solid var(--border);

            background: white;

        }


        .chat-input input {

            flex: 1;

            min-width: 0;

            padding:
                14px 16px;

            border: none;

            outline: none;

            color: #333;

            font-size: 14px;

        }


        .chat-input button {

            width: 62px;

            flex-shrink: 0;

            border: none;

            background:
                var(--purple);

            color: white;

            font-size: 17px;

            cursor: pointer;

        }


        .chat-input button:hover {

            background:
                var(--purple-dark);

        }


        /* =====================================================
           TABLET
        ===================================================== */

        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 1000px) {

            .hero {
                position: relative;
                overflow: hidden;
                background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%) !important;
                padding: 44px 20px 38px;
                min-height: auto;
            }

            .hero-container {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 24px !important;
                position: relative;
                z-index: 2;
                width: 100%;
            }

            .hero-content {
                flex: 1 1 54% !important;
                max-width: 56% !important;
                min-width: 0 !important;
            }

            .hero-media-wrapper {
                display: block !important;
                position: relative !important;
                inset: auto !important;
                flex: 0 0 42% !important;
                width: 42% !important;
                max-width: 42% !important;
                height: 320px !important;
                border-radius: 18px !important;
                overflow: hidden !important;
                box-shadow: 0 12px 30px rgba(43, 27, 72, 0.14) !important;
                background: #EDE6F8 !important;
                margin: 0 !important;
                padding: 0 !important;
                z-index: 2 !important;
            }

            .hero-slideshow {
                position: relative !important;
                width: 100% !important;
                height: 100% !important;
            }

            .hero-slide-item {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                opacity: 0;
                transition: opacity 0.8s ease-in-out;
            }

            .hero-slide-item.active {
                opacity: 1 !important;
            }

            .hero-slide-backdrop {
                display: none !important;
            }

            .hero-media-wrapper::after {
                display: none !important;
            }

            .hero-slide {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                object-position: center !important;
                border-radius: 18px !important;
                opacity: 1 !important;
                box-shadow: none !important;
            }

            .quick-menu {
                grid-template-columns: repeat(3, 1fr);
            }

            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .staff-card {
                flex-basis: 250px;
                width: 250px;
            }

            .about-container {
                grid-template-columns: 1fr;
                gap: 32px;
            }

        }


        /* =====================================================
           MOBILE (SEBELAHAN HERO & TAMPILAN RAPI)
        ===================================================== */

        @media (max-width: 768px) {

            html, body {
                overflow-x: hidden !important;
                max-width: 100vw !important;
                width: 100% !important;
            }

            /* HERO MOBILE: HEADER TEXT & FOTO SEBELAHAN, DI BAWAHNYA BUTTON 3 BARIS */
            .hero {
                padding: 16px 14px 26px !important;
                min-height: 0 !important;
                background: linear-gradient(180deg, #F8F4FD 0%, #EDE6F8 100%) !important;
                position: relative;
                overflow: hidden;
                width: 100% !important;
                max-width: 100vw !important;
                box-sizing: border-box !important;
            }

            .hero-container {
                display: grid !important;
                grid-template-columns: 0.95fr 1.05fr !important;
                gap: 10px !important;
                align-items: center !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .hero-content {
                display: contents !important;
            }

            .hero-intro {
                grid-column: 1 / 2 !important;
                grid-row: 1 !important;
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 3px !important;
                text-align: left !important;
            }

            .hero-label {
                font-size: 9.5px !important;
                font-weight: 800 !important;
                letter-spacing: 0.5px !important;
                margin-bottom: 2px !important;
                line-height: 1.2 !important;
                color: #6A22A7 !important;
                text-align: left !important;
            }

            .hero h1 {
                font-size: clamp(15px, 4.3vw, 19px) !important;
                line-height: 1.22 !important;
                letter-spacing: -0.3px !important;
                margin-bottom: 3px !important;
                color: #24143D !important;
                text-align: left !important;
            }

            .hero h1 span {
                color: #24143D !important;
            }

            .hero p {
                font-size: 11px !important;
                line-height: 1.4 !important;
                margin-bottom: 0 !important;
                color: #55446C !important;
                text-align: left !important;
                display: -webkit-box !important;
                -webkit-line-clamp: 2 !important;
                -webkit-box-orient: vertical !important;
                overflow: hidden !important;
            }

            /* FOTO HERO MOBILE: SEBELAHAN SAMA HEADLINE, LANGSUNG FOTONYA (LEBIH BESAR, TANPA LATAR BELAKANG/BORDER) */
            .hero-media-wrapper {
                grid-column: 2 / 3 !important;
                grid-row: 1 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                position: relative !important;
                inset: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                aspect-ratio: 4 / 3 !important;
                height: auto !important;
                min-height: 140px !important;
                max-height: 205px !important;
                border-radius: 14px !important;
                overflow: visible !important;
                box-shadow: none !important;
                background: transparent !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                z-index: 2 !important;
                box-sizing: border-box !important;
            }

            .hero-slideshow {
                position: relative !important;
                width: 100% !important;
                height: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                background: transparent !important;
                border: none !important;
            }

            .hero-slide-item {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                opacity: 0;
                transition: opacity 0.8s ease-in-out;
            }

            .hero-slide-item.active {
                opacity: 1 !important;
            }

            /* HAPUS TOTAL LATAR BELAKANG BLUR / AMBIENT */
            .hero-slide-backdrop {
                display: none !important;
            }

            .hero-media-wrapper::after {
                display: none !important;
            }

            .hero-slide {
                position: absolute !important;
                inset: 0 !important;
                width: 100% !important;
                height: 100% !important;
                object-fit: contain !important;
                object-position: center !important;
                border-radius: 14px !important;
                padding: 0 !important;
                background: transparent !important;
                box-sizing: border-box !important;
                opacity: 1 !important;
                transform: none !important;
                box-shadow: none !important;
                filter: drop-shadow(0 6px 16px rgba(106, 34, 167, 0.16)) !important;
                z-index: 2 !important;
            }

            /* TOMBOL & INFORMASI DI BAWAH FOTO: 3 BARIS */
            .hero-actions-wrap {
                grid-column: 1 / -1 !important;
                grid-row: 2 !important;
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
                margin-top: 6px !important;
            }

            .hero-info-badges {
                margin-bottom: 2px !important;
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 8px !important;
            }

            .hero-info-badges span {
                font-size: 11px !important;
                padding: 6px 12px !important;
            }

            .hero-info-badges .highlight-persalinan {
                padding: 11px 24px !important;
                font-size: 18px !important;
                font-weight: 900 !important;
                border-radius: 999px !important;
                border: 2.5px solid #6A22A7 !important;
                background: #F5EDFA !important;
                color: #521387 !important;
                box-shadow: 0 4px 16px rgba(106, 34, 167, 0.18) !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                letter-spacing: 0.3px !important;
            }

            .hero-reward-promo {
                padding: 14px 16px !important;
                border-radius: 16px !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 12px !important;
                background: rgba(255, 255, 255, 0.98) !important;
                border: 1.5px solid #DDD0F0 !important;
                box-shadow: 0 4px 18px rgba(106, 34, 167, 0.08) !important;
                margin: 4px 0 8px !important;
            }

            .hero-reward-copy {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                text-align: left !important;
            }

            .hero-reward-title {
                font-size: 14.5px !important;
                font-weight: 900 !important;
                color: #2B1B48 !important;
                line-height: 1.3 !important;
                margin-bottom: 3px !important;
                text-align: left !important;
            }

            .hero-reward-text {
                font-size: 11.5px !important;
                color: #5D4D73 !important;
                line-height: 1.4 !important;
                margin: 0 !important;
                text-align: left !important;
            }

            .hero-reward-action-wrap {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-end !important;
                gap: 4px !important;
                flex-shrink: 0 !important;
                text-align: right !important;
            }

            .hero-reward-action {
                font-size: 12px !important;
                padding: 8px 16px !important;
                text-align: center !important;
                border-radius: 999px !important;
                font-weight: 800 !important;
                background: #6A22A7 !important;
                color: #FFFFFF !important;
                box-shadow: 0 4px 12px rgba(106, 34, 167, 0.25) !important;
                width: auto !important;
                white-space: nowrap !important;
            }

            .hero-reward-login-link {
                font-size: 11px !important;
                color: #6A22A7 !important;
                font-weight: 700 !important;
                text-decoration: underline !important;
                text-align: right !important;
                white-space: nowrap !important;
            }

            .hero-reward-points {
                font-size: 20px !important;
                font-weight: 900 !important;
                color: #6A22A7 !important;
                text-align: right !important;
            }

            .hero-reward-login-link {
                font-size: 12px !important;
                color: #6A22A7 !important;
                font-weight: 700 !important;
                text-decoration: underline !important;
                text-align: center !important;
            }

            .hero-reward-points {
                font-size: 22px !important;
                font-weight: 900 !important;
                color: #6A22A7 !important;
                text-align: center !important;
            }

            .hero-buttons {
                display: flex !important;
                flex-direction: row !important;
                gap: 10px !important;
                width: 100% !important;
            }

            .button-primary,
            .button-outline {
                flex: 1 1 50% !important;
                width: auto !important;
                padding: 11px 12px !important;
                font-size: 12.5px !important;
                font-weight: 800 !important;
                border-radius: 10px !important;
                text-align: center !important;
                box-sizing: border-box !important;
            }

            /* REVIEW SLIDER MOBILE */
            .review-card-item {
                flex: 0 0 84vw !important;
                width: 84vw !important;
                min-width: 84vw !important;
            }
            .slider-nav-btn {
                display: none !important;
            }

            /* REVIEW HEADER CARD & TOMBOL MOBILE (FIT & TIDAK GESER) */
            .google-review-header-card {
                padding: 20px 16px !important;
                border-radius: 18px !important;
                gap: 16px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .google-review-action-col {
                width: 100% !important;
                align-items: stretch !important;
                text-align: left !important;
            }

            .google-review-btn-wrap {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .btn-review-maps {
                width: 100% !important;
                font-size: 13px !important;
                padding: 10px 14px !important;
                box-sizing: border-box !important;
                white-space: nowrap !important;
            }

            .btn-review-tulis {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 12px !important;
                padding: 10px 12px !important;
                white-space: normal !important;
                box-sizing: border-box !important;
            }

            .btn-review-text-wrap {
                white-space: normal !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 2px !important;
                width: 100% !important;
            }

            .review-sub-info {
                font-size: 10.5px !important;
                line-height: 1.3 !important;
                white-space: normal !important;
                text-align: center !important;
                display: block !important;
            }

            /* JADWAL PELAYANAN TABLE MOBILE */
            .schedule-box {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                border-radius: 14px !important;
            }
            .schedule-table {
                min-width: 480px !important;
            }

            /* QUICK MENU */
            .quick-menu-wrapper {
                padding: 0 14px;
                margin-top: -24px;
                position: relative;
                z-index: 10;
            }

            .quick-menu {
                display: grid;
                grid-template-columns: 1fr 1fr;
                border-radius: 18px;
                border: 1px solid var(--border);
                box-shadow: 0 10px 26px rgba(50,35,65,0.09);
                background: #fff;
                overflow: hidden;
            }

            .quick-item {
                min-height: auto;
                padding: 13px 12px;
                gap: 10px;
                align-items: center;
                border-right: 1px solid var(--border);
                border-bottom: 1px solid var(--border);
            }

            .quick-item:nth-child(2n) {
                border-right: none;
            }

            .quick-item:last-child:nth-child(odd) {
                grid-column: span 2;
                border-right: none;
                border-bottom: none;
            }

            .quick-icon {
                width: 38px;
                height: 38px;
                min-width: 38px;
                border-radius: 10px;
                font-size: 17px;
            }

            .quick-content strong {
                font-size: 12px;
                line-height: 1.3;
                color: #2B1B48;
            }

            .quick-content span {
                font-size: 10.5px;
                line-height: 1.3;
                color: #776A88;
            }

            /* GENERAL SECTION */
            section {
                padding: 38px 16px;
            }

            .section-heading {
                margin-bottom: 22px;
                text-align: center;
            }

            .section-label {
                font-size: 10.5px;
                letter-spacing: 1px;
            }

            .section-heading h2 {
                font-size: 23px;
                line-height: 1.25;
                margin-bottom: 8px;
            }

            .section-heading p {
                font-size: 12.5px;
                line-height: 1.55;
                margin: auto;
            }

            /* POIN & KEUNGGULAN */
            .benefits-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .benefit-card {
                padding: 18px 16px;
                border-radius: 14px;
            }

            /* LAYANAN */
            .service-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .service-card {
                padding: 18px 16px;
                border-radius: 14px;
            }

            /* JADWAL */
            .schedule-box {
                border-radius: 16px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                box-shadow: 0 4px 18px rgba(80,50,100,0.06);
            }

            .schedule-table {
                min-width: 520px;
            }

            .schedule-table th {
                padding: 12px 14px;
                font-size: 12px;
            }

            .schedule-table td {
                padding: 12px 14px;
                font-size: 12px;
            }

            /* STAFF DOKTER & BIDAN */
            .staff-carousel-btn {
                display: none !important;
            }

            .staff-list {
                gap: 14px !important;
                padding: 4px 2px 14px !important;
            }

            .staff-card {
                flex: 0 0 230px !important;
                width: 230px !important;
                min-width: 230px !important;
                border-radius: 16px !important;
                padding: 0 !important;
            }

            .staff-photo-wrap {
                height: 200px !important;
            }

            .staff-info {
                padding: 12px 12px 14px !important;
                text-align: left !important;
            }

            .staff-role {
                font-size: 11px !important;
                font-weight: 700 !important;
            }

            .staff-info h3 {
                font-size: 14.5px !important;
                line-height: 1.3 !important;
                font-weight: 800 !important;
                margin: 4px 0 6px !important;
            }

            .staff-schedule {
                font-size: 11.5px !important;
            }

            .staff-button {
                font-size: 12px !important;
                padding: 10px 14px !important;
                border-radius: 10px !important;
            }

            /* ABOUT */
            .about-container {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .about-image {
                width: 100% !important;
                height: 210px !important;
                max-height: 240px !important;
                border-radius: 16px !important;
                display: block !important;
                overflow: hidden !important;
            }

            .about-image img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                display: block !important;
            }

            .about-text h2 {
                font-size: 23px !important;
            }

            .about-text p {
                font-size: 13px !important;
                line-height: 1.65 !important;
            }

            /* FOOTER */
            footer, .hanania-footer {
                padding: 38px 16px 28px !important;
            }

            .footer-container {
                flex-direction: column;
                gap: 24px;
            }

            /* HANABOT */
            .chatbot-button {
                right: 16px;
                bottom: 16px;
                width: 54px;
                height: 54px;
                padding: 0;
                border-radius: 50%;
                font-size: 22px;
            }

            .chatbot-text {
                display: none;
            }

            .chatbot-icon {
                font-size: 23px;
            }

            .chatbot-window {
                top: 8px;
                right: 8px;
                bottom: 8px;
                left: 8px;
                width: auto;
                height: auto;
                min-height: 0;
                max-height: none;
                border-radius: 18px;
            }

            .chat-header {
                min-height: 64px;
                padding: 12px 14px;
            }

            .chat-body {
                padding: 16px;
            }

            .chat-input {
                min-height: 56px;
                padding-bottom: env(safe-area-inset-bottom);
            }

        }

        /* =====================================================
        FOOTER CONTACT LIST
        ===================================================== */

        .footer-contact-list {
            list-style: none;
            padding: 0;
            margin: 8px 0 0;
        }

        .footer-contact-list li {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 12px;
        }

        .footer-contact-list li:last-child {
            margin-bottom: 0;
        }

        .footer-contact-list span {
            color: rgba(255,255,255,0.75);
            font-size: 11px;
        }

        .footer-contact-list a {
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: 0.2s;
        }

        .footer-contact-list a:hover {
            color: #E8DFF0;
            text-decoration: underline;
        }

    

        /* =====================================================
           PASIEN PROFILE + REWARD DROPDOWN
        ===================================================== */

        .profile-dropdown {
            position: relative;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
            background: #FFFFFF;
            color: var(--purple-dark);
            padding: 5px 9px 5px 6px;
            border-radius: 12px;
            cursor: pointer;
            font-family: inherit;
        }

        .profile-button:hover {
            background: var(--purple-soft);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .profile-avatar-default {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--purple-soft);
            font-size: 15px;
        }

        .profile-name {
            max-width: 105px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
        }

        .profile-points {
            font-size: 11px;
            font-weight: 700;
            color: var(--purple);
            white-space: nowrap;
        }

        .profile-arrow {
            font-size: 14px;
            color: #777777;
        }

        .profile-menu {
            display: none;
            position: absolute;
            top: calc(100% + 9px);
            right: 0;
            width: 175px;
            padding: 6px;
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: 0 12px 28px rgba(70,45,90,.13);
            z-index: 1200;
        }

        .profile-menu.active {
            display: block;
        }

        .profile-menu a {
            display: block;
            padding: 9px 11px;
            border-radius: 8px;
            color: #555555;
            font-size: 12px;
        }

        .profile-menu a:hover {
            background: var(--purple-soft);
            color: var(--purple-dark);
        }

        /* =====================================================
           REWARD PROMO DI HERO
        ===================================================== */

        .hero-reward-promo {
            max-width: 720px;
            margin: 22px 0 24px;
            padding: 15px 17px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 16px;
            background: rgba(255,255,255,.11);
            backdrop-filter: blur(8px);
        }

        .hero-reward-copy {
            min-width: 0;
        }

        .hero-reward-title {
            margin-bottom: 4px;
            color: #2B1B48;
            font-size: 14px;
            font-weight: 800;
        }

        .hero-reward-text {
            color: #4A3C60;
            font-size: 12.5px;
            line-height: 1.55;
        }

        .hero-reward-action {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 15px;
            border-radius: 999px;
            background: #6A22A7;
            color: #FFFFFF !important;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s;
        }

        .hero-reward-action:hover {
            background: #4A1278;
            color: #FFFFFF !important;
        }

        /* Highlight revisi: poin reward & persalinan dibuat lebih menonjol tanpa mengubah layout utama. */
.hero-info-badges .highlight-persalinan{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:16px 32px;
    font-size:24px;
    font-weight:900;
    color:#2B124C;
    background:linear-gradient(135deg, #cea2fd 0%, #ecdcfd 50%, #cea2fd 100%);
    border:3px solid #b37af8;
    border-radius:999px;
    box-shadow:0 12px 35px rgba(206,162,253,.65), 0 0 25px rgba(179,122,248,.4);
    animation:persalinanPulse 2.2s ease-in-out infinite;
    transform-origin:center;
    letter-spacing:.5px;
    text-shadow:0 1px 2px rgba(255,255,255,.8);
}
@keyframes persalinanPulse{
    0%,100%{transform:scale(1);box-shadow:0 12px 35px rgba(206,162,253,.65);}
    50%{transform:scale(1.06);box-shadow:0 18px 45px rgba(173,110,245,.85);}
}
.badge-cuti-merah{
    background:#DC2626 !important;
    color:#FFFFFF !important;
    font-size:9.5px !important;
    font-weight:800 !important;
    padding:4px 10px !important;
    border-radius:20px !important;
    letter-spacing:.4px !important;
    box-shadow:0 2px 8px rgba(220,38,38,0.4) !important;
    display:inline-flex !important;
    align-items:center !important;
    gap:4px !important;
}
.btn-lihat-galeri{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:13px 24px;
    border-radius:12px;
    background:linear-gradient(135deg, #cea2fd 0%, #ad6ef5 100%);
    color:#2B124C;
    font-weight:800;
    font-size:14px;
    text-decoration:none;
    box-shadow:0 6px 22px rgba(206,162,253,0.5);
    transition:all .25s ease;
}
.btn-lihat-galeri:hover{
    background:linear-gradient(135deg, #b985f9 0%, #9c55eb 100%);
    color:#fff;
    transform:translateY(-2px);
    box-shadow:0 10px 28px rgba(173,110,245,0.6);
}
.hero-reward-points {
    flex-shrink: 0;
    color: #6A22A7;
    font-size: 20px;
    font-weight: 900;
    white-space: nowrap;
}

.hero-reward-points span {
    font-size: 11px;
    font-weight: 700;
    color: #4A3C60;
}

        /* Small reward highlight on the home page */
        .patient-reward-card {
            width: min(1120px, calc(100% - 40px));
            margin: 30px auto 0;
            padding: 13px 17px;
            border-radius: 14px;
            background: linear-gradient(135deg, #F5EFF9, #FFFFFF);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .reward-card-title {
            color: var(--purple-dark);
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .reward-card-desc {
            color: var(--muted);
            font-size: 11px;
        }

        .reward-card-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .reward-points-number {
            color: var(--purple);
            font-size: 16px;
            font-weight: 800;
            white-space: nowrap;
        }

        .reward-card-button {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 8px;
            background: var(--purple);
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
        }

        .reward-card-button:hover {
            background: var(--purple-dark);
        }


        .hero-info-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .hero-info-badges span {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border: 1.5px solid #DDD0F0;
            border-radius: 999px;
            background: #FFFFFF;
            color: #2B1B48;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(106, 34, 167, 0.08);
        }

        .patient-reward-card {
            width: min(1120px, calc(100% - 60px));
            margin: 30px auto 0;
            padding: 18px 22px;
            border-radius: 18px;
            background: linear-gradient(135deg, #F5EFF9, #FFFFFF);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 12px 32px rgba(70,45,90,.08);
        }

        .reward-card-left { flex: 1; }
        .reward-card-title { color: var(--purple-dark); font-size: 16px; font-weight: 800; margin-bottom: 4px; }
        .reward-card-desc { color: var(--muted); font-size: 12px; line-height: 1.6; }
        .reward-card-right { display:flex; align-items:center; gap:14px; flex-shrink:0; }
        .reward-points-number { color:var(--purple); font-size:22px; font-weight:900; white-space:nowrap; }
        .reward-points-number span { font-size:12px; font-weight:700; }
        .reward-card-button { display:inline-flex; align-items:center; padding:10px 14px; border-radius:10px; background:var(--purple); color:#fff; font-size:12px; font-weight:800; }

        .reminder-section { padding-top: 22px; padding-bottom: 12px; }
        .reminder-card { display:flex; align-items:center; gap:16px; padding:18px 20px; border:1px solid var(--border); border-radius:18px; background:#fff; box-shadow:0 10px 28px rgba(70,45,90,.06); }
        .reminder-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:var(--purple-soft); font-size:22px; flex-shrink:0; }
        .reminder-content { flex:1; }
        .reminder-content .section-label { margin-bottom:4px; }
        .reminder-content h3 { color:var(--purple-dark); margin:0 0 5px; font-size:17px; }
        .reminder-content p { color:var(--muted); font-size:12px; line-height:1.6; margin:0; }
        .reminder-button { padding:10px 14px; border-radius:10px; background:var(--purple); color:#fff; font-size:12px; font-weight:800; white-space:nowrap; }
        .service-highlight { background: linear-gradient(135deg,#fff,#faf7fc); }

        @media (max-width: 768px) {

            .profile-name {
                max-width: 75px;
            }

            .profile-points {
                display: none;
            }

            .profile-menu {
                right: auto;
                left: 50%;
                transform: translateX(-50%);
            }

            .hero-reward-promo {
                align-items: center !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                text-align: left !important;
            }

            .hero-reward-copy {
                text-align: left !important;
            }

            .hero-reward-title,
            .hero-reward-text {
                text-align: left !important;
            }

            .hero-reward-action-wrap {
                align-items: flex-end !important;
                text-align: right !important;
            }

            .patient-reward-card {
                width: calc(100% - 28px);
                padding: 15px;
                flex-direction: column;
                align-items: flex-start;
            }

            .reward-card-right {
                width: 100%;
                justify-content: space-between;
            }

            .reminder-card {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .reminder-button {
                margin-left: 64px;
            }
        }


/* ===== PALETTE FINAL (GITANIA SOFT LILAC & VIBRANT PURPLE) ===== */
.button-primary,
.login-button,
.staff-button,
.reward-card-button,
.reminder-button {
    background: #6A22A7 !important;
    color: #FFFFFF !important;
    font-weight: 800 !important;
}
.button-primary:hover,
.login-button:hover,
.staff-button:hover,
.reward-card-button:hover,
.reminder-button:hover {
    background: #4A1278 !important;
}
.notification-button {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #FFFFFF;
    color: #6A22A7 !important;
    font-size: 17px;
    transition: .2s ease;
}
.notification-button:hover { background: #F4EEFB; }

/* =====================================================
   AMBIENT AUDIO SPEAKER BUTTON (ICON-ONLY FLOATING)
===================================================== */
.hanania-audio-speaker {
    position: fixed;
    bottom: 26px;
    left: 26px;
    z-index: 1500;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    border: 1.5px solid #DDD0F0;
    box-shadow: 0 8px 24px rgba(90, 25, 135, 0.18);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    color: #6A22A7;
    font-size: 20px;
    padding: 0;
    outline: none;
}
.hanania-audio-speaker:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 30px rgba(90, 25, 135, 0.28);
    border-color: #6A22A7;
}
.hanania-audio-speaker.is-playing {
    background: linear-gradient(135deg, #6A22A7 0%, #8A4BC8 100%);
    color: #FFFFFF;
    border-color: #8A4BC8;
    box-shadow: 0 8px 25px rgba(106, 34, 167, 0.38);
}
.hanania-audio-speaker.is-playing::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 2px solid rgba(138, 75, 200, 0.45);
    animation: soundwave-pulse 2s cubic-bezier(0, 0, 0.2, 1) infinite;
    pointer-events: none;
}
@keyframes soundwave-pulse {
    0% {
        transform: scale(0.9);
        opacity: 0.85;
    }
    100% {
        transform: scale(1.45);
        opacity: 0;
    }
}
@media (max-width: 600px) {
    .hanania-audio-speaker {
        bottom: 22px;
        left: 18px;
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
}
.patient-reward-card {
    width: min(1120px, calc(100% - 40px));
    margin: 16px auto 0 !important;
    padding: 14px 18px !important;
    background: linear-gradient(135deg, #EAE4F9, #FFFFFF) !important;
    border: 1px solid #DDD2F1 !important;
}
.reminder-section {
    padding-top: 12px !important;
    padding-bottom: 16px !important;
}
.reminder-card {
    background: #FFFFFF !important;
    border-color: #DDD2F1 !important;
}
@media (max-width: 768px) {
    .patient-reward-card { width: calc(100% - 28px); }
    .reminder-section { display: none !important; }
}


        /* =====================================================
           MODAL PILIH JADWAL PENDAFTARAN
           (Ditambahkan: alur pilih dokter/bidan & jadwal sebelum
           masuk ke form pasien/daftar.php)
        ===================================================== */

        .jm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(41, 25, 60, 0.55);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .jm-overlay.active {
            display: flex;
        }

        .jm-box {
            background: #fff;
            border-radius: 20px;
            width: min(560px, 100%);
            max-height: 88vh;
            overflow-y: auto;
            box-shadow: 0 24px 60px rgba(40, 20, 60, 0.25);
        }

        .jm-head {
            position: sticky;
            top: 0;
            background: #fff;
            padding: 20px 22px 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            z-index: 1;
        }

        .jm-head h3 {
            color: var(--purple-dark);
            font-size: 18px;
            margin-bottom: 4px;
        }

        .jm-head p {
            color: var(--muted);
            font-size: 12.5px;
            line-height: 1.5;
        }

        .jm-close {
            border: none;
            background: var(--purple-soft);
            color: var(--purple-dark);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .jm-body {
            padding: 18px 22px 22px;
        }

        .jm-date-field {
            margin-bottom: 16px;
        }

        .jm-date-field label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: #51475A;
            margin-bottom: 6px;
        }

        .jm-date-field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #DCCFE8;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text);
        }

        .jm-group-title {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--purple-dark);
            letter-spacing: .04em;
            text-transform: uppercase;
            margin: 14px 0 8px;
        }

        .jm-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 13px;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            background: #fff;
        }

        .jm-item:hover {
            border-color: var(--purple);
        }

        .jm-item.jm-selected {
            border-color: var(--purple-dark);
            background: var(--purple-soft);
        }

        .jm-item.jm-item-disabled {
            opacity: 0.65;
            background: #F8F6FA !important;
            border-color: #E2D9EC !important;
            cursor: not-allowed !important;
        }
        .jm-item.jm-item-disabled:hover {
            border-color: #E2D9EC !important;
        }

        .jm-item-name {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--text);
        }

        .jm-item-sub {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 2px;
        }

        .jm-sesi-list { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; max-width:58%; }
        .jm-sesi-button { min-height:36px; padding:8px 10px; border:1px solid #DCCFE8; border-radius:8px; background:#fff; color:var(--purple-dark); font:inherit; font-size:11px; font-weight:700; cursor:pointer; }
        .jm-sesi-button:hover, .jm-sesi-button.jm-sesi-selected { border-color:var(--purple-dark); background:var(--purple-dark); color:#fff; }
        .jm-sesi-button.jm-sesi-disabled { background:#F0ECF4; color:#9A92A3; cursor:not-allowed; }

        .jm-item-time {
            font-size: 12px;
            font-weight: 700;
            color: var(--purple-dark);
            white-space: nowrap;
        }

        .jm-empty {
            padding: 16px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            background: var(--purple-soft);
            border-radius: 12px;
        }

        .jm-loading {
            padding: 16px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .jm-footer {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .jm-btn-continue {
            border: none;
            background: var(--purple-dark);
            color: #fff;
            font-weight: 700;
            font-size: 13.5px;
            padding: 11px 20px;
            border-radius: 10px;
            cursor: pointer;
        }

        .jm-btn-continue:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        @media (max-width: 480px) {

            .jm-box {
                max-height: 92vh;
                border-radius: 16px;
            }

            .jm-head {
                padding: 16px 16px 12px;
            }

            .jm-body {
                padding: 14px 16px 18px;
            }

            .jm-item { align-items:flex-start; flex-direction:column; }
            .jm-sesi-list { justify-content:flex-start; max-width:100%; width:100%; }
            .jm-sesi-button { flex:1 1 110px; }

        }
        /* =====================================================
           GOOGLE REVIEW & REWARD SECTION
        ===================================================== */
        .google-review-section {
            padding: 50px 24px;
            background: linear-gradient(135deg, #FAF6FD 0%, #F1E7FC 100%);
            border-top: 1px solid #EADBFA;
            border-bottom: 1px solid #EADBFA;
        }

        .google-review-card {
            max-width: 1000px;
            margin: auto;
            background: #FFFFFF;
            border: 1.5px solid #E2D2F2;
            border-radius: 24px;
            padding: 32px 36px;
            box-shadow: 0 16px 42px rgba(106, 34, 167, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }

        .gr-badge-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #F3EDFA;
            border: 1px solid #DDD0F0;
            border-radius: 999px;
            color: #6A22A7;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .gr-title {
            color: #2B1B48;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 10px;
        }

        .gr-stars {
            color: #F59E0B;
            font-size: 18px;
            letter-spacing: 2px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 10px;
        }

        .gr-desc {
            color: #5D4D73;
            font-size: 14px;
            line-height: 1.65;
            max-width: 580px;
        }

        .gr-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .gr-btn-claim {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 26px;
            background: linear-gradient(135deg, #6A22A7 0%, #8A4BC8 100%);
            color: #FFFFFF !important;
            font-size: 14px;
            font-weight: 800;
            border-radius: 999px;
            text-decoration: none;
            box-shadow: 0 8px 22px rgba(106, 34, 167, 0.28);
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            white-space: nowrap;
        }

        .gr-btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(106, 34, 167, 0.38);
            background: linear-gradient(135deg, #581690 0%, #763BB5 100%);
        }

        .gr-btn-claimed {
            background: #EAF7EE !important;
            color: #1F7A43 !important;
            border: 1.5px solid #B8E4C4 !important;
            box-shadow: none !important;
            cursor: default !important;
        }

        .gr-note {
            font-size: 11.5px;
            color: #796694;
            text-align: center;
        }

        @media (max-width: 768px) {
            .google-review-section {
                padding: 38px 16px;
            }
            .google-review-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 20px;
                gap: 20px;
            }
            .gr-title {
                font-size: 20px;
            }
            .gr-desc {
                font-size: 13px;
            }
            .gr-actions {
                width: 100%;
                align-items: stretch;
            }
            .gr-btn-claim {
                width: 100%;
                text-align: center;
            }
        }

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<?php include __DIR__ . '/includes/navbar.php'; ?>


<!-- =====================================================
     REWARD & PENGINGAT PASIEN
===================================================== -->
<?php if ($isPasienLoggedIn): ?>
<section class="patient-reward-card">
    <div class="reward-card-left">
        <div class="reward-card-title">⭐ Poin Reward Kamu</div>
        <div class="reward-card-desc">Kumpulkan poin dari kunjungan dan pendaftaranmu di Klinik Pratama Rumah Hanania.</div>
    </div>
    <div class="reward-card-right">
        <div class="reward-points-number"><?= number_format($rewardPoints, 0, ',', '.') ?> <span>Poin</span></div>
        <a href="pasien/reward.php" class="reward-card-button">Lihat Reward</a>
    </div>
</section>
<?php endif; ?>
<?php if ($isPasienLoggedIn): ?>
<section class="reminder-section">
    <div class="container">
        <div class="reminder-card">
            <div class="reminder-icon">🔔</div>
            <div class="reminder-content">
                <span class="section-label">PENGINGAT KUNJUNGAN</span>
                <h3>Sahabat Hanania, jangan lupa jadwal kontrolmu</h3>
                <p>Pastikan kamu melihat nomor antrean dan jadwal kunjungan agar tidak melewatkan pemeriksaan.</p>
            </div>
                            <a href="pasien/antrian.php" class="reminder-button">Lihat Antrean</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =====================================================
     HERO
===================================================== -->

<section
    class="hero"
    id="beranda"
>

    <div class="hero-container">


        <div class="hero-content">
            <div class="hero-intro">
                <span class="hero-label">
                    <?= eIndex(getSiteKonten('beranda_slogan', 'KLINIK PRATAMA RUMAH HANANIA')) ?>
                </span>

                <h1>
                    <?= eIndex(getSiteKonten('hero_judul', 'Solusi Pelayanan Medis Praktis & Terpercaya')) ?>
                </h1>

                <p>
                    <?= nl2br(eIndex(getSiteKonten('hero_subjudul', 'Temukan informasi layanan, jadwal pelayanan, dokter dan bidan, serta lakukan pendaftaran pasien secara online melalui satu website.'))) ?>
                </p>
            </div>

            <div class="hero-actions-wrap">
                <div class="hero-info-badges">
                    <span class="highlight-persalinan"><?= eIndex(getSiteKonten('hero_badge_persalinan', '🤰 Persalinan 24 Jam')) ?></span>
                </div>

                <?php if ($isPasienLoggedIn): ?>
                    <div class="hero-reward-promo">
                        <div class="hero-reward-copy">
                            <div class="hero-reward-title">⭐ Sahabat Hanania • Poin Reward Kamu</div>
                            <div class="hero-reward-text">
                                Terus kumpulkan poin reward dari pendaftaran online, kunjungan kontrol, dan ulasan klinik untuk ditukarkan voucher!
                            </div>
                        </div>
                        <div class="hero-reward-action-wrap">
                            <div class="hero-reward-points">
                                <?= number_format($rewardPoints, 0, ',', '.') ?> <span>Poin</span>
                            </div>
                            <a href="pasien/reward.php" class="hero-reward-action">Katalog Reward &rarr;</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hero-reward-promo">
                        <div class="hero-reward-copy">
                            <div class="hero-reward-title">✨ Gabung Menjadi Sahabat Hanania</div>
                            <div class="hero-reward-text">
                                Daftarkan akun Anda untuk mempermudah antrean online, voucher diskon klinik, dan kumpulkan poin reward setiap kunjungan!
                            </div>
                        </div>
                        <div class="hero-reward-action-wrap">
                            <a href="daftar_akun.php" class="hero-reward-action">Daftar Akun</a>
                            <a href="login.php" class="hero-reward-login-link">Sudah punya akun? Masuk</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="hero-buttons">
                    <a
                        href="pasien/daftar.php"
                        class="button-primary"
                    >
                        Daftar Online
                    </a>

                    <a
                        href="dokter/jadwal.php"
                        class="button-outline"
                    >
                        Lihat Jadwal
                    </a>
                </div>
            </div>
        </div>

        <!-- FOTO HERO BANNER SHARP & CRISP (ORIGINAL SLIDESHOW) -->
        <div class="hero-media-wrapper">
            <div class="hero-slideshow">
                <?php if (!empty($heroBanners)): ?>
                    <?php foreach ($heroBanners as $idx => $hb): 
                        $srcImg = !empty($hb['foto']) ? ltrim($hb['foto'], '/') : 'assets/img/klinik.jpg';
                    ?>
                        <div class="hero-slide-item <?= $idx === 0 ? 'active' : '' ?>">
                            <div class="hero-slide-backdrop" style="background-image: url('<?= eIndex($srcImg) ?>');"></div>
                            <img src="<?= eIndex($srcImg) ?>" alt="<?= eIndex($hb['judul'] ?: 'Klinik Pratama Rumah Hanania') ?>" class="hero-slide <?= $idx === 0 ? 'active' : '' ?>" onload="checkHeroLandscape(this)">
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="hero-slide-item active">
                        <div class="hero-slide-backdrop" style="background-image: url('<?= eIndex($heroFoto1) ?>');"></div>
                        <img src="<?= eIndex($heroFoto1) ?>" alt="Klinik Pratama Rumah Hanania" class="hero-slide active" onload="checkHeroLandscape(this)">
                    </div>
                    <div class="hero-slide-item">
                        <div class="hero-slide-backdrop" style="background-image: url('<?= eIndex($heroFoto2) ?>');"></div>
                        <img src="<?= eIndex($heroFoto2) ?>" alt="Klinik Pratama Rumah Hanania" class="hero-slide" onload="checkHeroLandscape(this)">
                    </div>
                    <div class="hero-slide-item">
                        <div class="hero-slide-backdrop" style="background-image: url('assets/img/klinik1.jpg');"></div>
                        <img src="assets/img/klinik1.jpg" alt="Klinik Pratama Rumah Hanania" class="hero-slide" onload="checkHeroLandscape(this)">
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</section>


<!-- =====================================================
     QUICK MENU (DINAMIS DARI ADMIN)
===================================================== -->

<div class="quick-menu-wrapper">

    <div class="quick-menu">

        <!-- ITEM 1: PENDAFTARAN -->
        <a href="pasien/daftar.php" class="quick-item">
            <div class="quick-icon">📅</div>
            <div class="quick-content">
                <strong><?= eIndex(getSiteKonten('quick_item_1_nama', 'Pendaftaran Online')) ?></strong>
                <span><?= eIndex(getSiteKonten('quick_item_1_deskripsi', 'Akses pendaftaran pasien dengan lebih praktis.')) ?></span>
            </div>
        </a>

        <!-- ITEM 2: NOMOR ANTREAN -->
        <a href="pasien/antrian.php" class="quick-item">
            <div class="quick-icon">🎫</div>
            <div class="quick-content">
                <strong><?= eIndex(getSiteKonten('quick_item_2_nama', 'Nomor Antrean')) ?></strong>
                <span><?= eIndex(getSiteKonten('quick_item_2_deskripsi', 'Lihat informasi pendaftaran dan nomor antrean.')) ?></span>
            </div>
        </a>

        <!-- ITEM 3: JADWAL -->
        <a href="dokter/jadwal.php" class="quick-item">
            <div class="quick-icon">🗓</div>
            <div class="quick-content">
                <strong><?= eIndex(getSiteKonten('quick_item_3_nama', 'Jadwal Pelayanan')) ?></strong>
                <span><?= eIndex(getSiteKonten('quick_item_3_deskripsi', 'Lihat jadwal dokter dan bidan yang tersedia.')) ?></span>
            </div>
        </a>

        <!-- ITEM 4: INFORMASI LAYANAN -->
        <a href="#layanan" class="quick-item">
            <div class="quick-icon">✚</div>
            <div class="quick-content">
                <strong><?= eIndex(getSiteKonten('quick_item_4_nama', 'Informasi Layanan')) ?></strong>
                <span><?= eIndex(getSiteKonten('quick_item_4_deskripsi', 'Lihat layanan umum, gigi, kebidanan, BPJS, dan persalinan 24 jam.')) ?></span>
            </div>
        </a>

        <!-- ITEM 5: POIN REWARD -->
        <a href="<?= $isPasienLoggedIn ? 'pasien/reward.php' : 'login.php?redirect=reward' ?>" class="quick-item">
            <div class="quick-icon">⭐</div>
            <div class="quick-content">
                <strong><?= eIndex(getSiteKonten('quick_item_5_nama', 'Poin Reward')) ?></strong>
                <span><?= eIndex(getSiteKonten('quick_item_5_deskripsi', 'Lihat dan pantau poin reward pendaftaran Anda.')) ?></span>
            </div>
        </a>

    </div>

</div>


<!-- =====================================================
     LAYANAN (DINAMIS DARI ADMIN)
===================================================== -->

<section class="services-section" id="layanan">

    <div class="container">

        <div class="section-heading">
            <span class="section-label">
                <?= eIndex(getSiteKonten('layanan_section_label', 'LAYANAN')) ?>
            </span>
            <h2>
                <?= eIndex(getSiteKonten('layanan_section_judul', 'Informasi Layanan Klinik')) ?>
            </h2>
            <p>
                <?= nl2br(eIndex(getSiteKonten('layanan_section_subjudul', 'Temukan informasi mengenai layanan kesehatan yang tersedia di Klinik Pratama Rumah Hanania.'))) ?>
            </p>
        </div>

        <div class="service-grid">

            <!-- Card 1 -->
            <div class="service-card">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_1_icon', '🏥')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_1_nama', 'Pelayanan Umum')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_1_deskripsi', 'Informasi mengenai pelayanan kesehatan umum yang tersedia di klinik.'))) ?></p>
            </div>

            <!-- Card 2 -->
            <div class="service-card">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_2_icon', '🦷')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_2_nama', 'Pelayanan Gigi')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_2_deskripsi', 'Informasi mengenai pelayanan dan pemeriksaan kesehatan gigi.'))) ?></p>
            </div>

            <!-- Card 3 -->
            <div class="service-card">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_3_icon', '🤱🏻')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_3_nama', 'Pelayanan Kebidanan')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_3_deskripsi', 'Informasi mengenai pelayanan kebidanan yang tersedia di klinik.'))) ?></p>
            </div>

            <!-- Card 4 -->
            <div class="service-card service-highlight">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_4_icon', '💳')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_4_nama', 'Pasien BPJS')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_4_deskripsi', 'Klinik melayani pasien BPJS sesuai ketentuan dan prosedur pelayanan yang berlaku.'))) ?></p>
            </div>

            <!-- Card 5 -->
            <div class="service-card service-highlight">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_5_icon', '🤰')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_5_nama', 'Persalinan 24 Jam')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_5_deskripsi', 'Layanan persalinan tersedia 24 jam. Hubungi klinik terlebih dahulu untuk informasi dan penanganan.'))) ?></p>
            </div>

            <!-- Card 6 -->
            <div class="service-card service-highlight">
                <div class="service-icon"><?= eIndex(getSiteKonten('layanan_item_6_icon', '👶')) ?></div>
                <h3><?= eIndex(getSiteKonten('layanan_item_6_nama', 'USG Kehamilan 2D / 4D')) ?></h3>
                <p><?= nl2br(eIndex(getSiteKonten('layanan_item_6_deskripsi', 'Pemeriksaan USG spesialis kandungan dengan hasil cetak foto dan video rekaman janin.'))) ?></p>
            </div>

        </div>

        <div style="text-align:center;margin-top:28px;">
            <a href="layanan/index.php#tarif-kehamilan" class="hero-button primary" style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:800;text-decoration:none;box-shadow:0 6px 18px rgba(79,50,147,.2);">
                <span>💳</span> Lihat Rincian Biaya USG &amp; Layanan Lengkap &rarr;
            </a>
        </div>

    </div>

</section>


<!-- =====================================================
     JADWAL PELAYANAN
===================================================== -->

<section
    class="schedule-section"
    id="jadwal"
>

    <div class="container">


        <div class="section-heading">

            <span class="section-label">
                JADWAL PELAYANAN
            </span>

                <div class="schedule-today">
                Jadwal hari ini: <strong><?= eIndex($hariIni) ?></strong>, <?= eIndex($tanggalHariIni) ?>
                </div>

            <p>

                Lihat jadwal pelayanan dokter
                dan bidan sebelum melakukan
                pendaftaran.

            </p>

            

        </div>


        <div class="schedule-box">

            <table class="schedule-table">

                <thead>

                    <tr>

                        <th>
                            Tenaga Kesehatan
                        </th>

                        <th>
                            Hari
                        </th>

                        <th>
                            Jam Pelayanan
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>
<?php if (!empty($dataJadwalHariIni)): ?>
<?php foreach ($dataJadwalHariIni as $jadwal): 
    $poliItem = $allPoliMapIndex[(int)($jadwal['id_poli'] ?? 0)] ?? null;
    $statusJadwal = cekStatusJadwalPoli($poliItem, $jadwal['jam_mulai'], $jadwal['jam_selesai'], true, $jadwal['peran'] ?? '');
    $isClosedToday = $statusJadwal['is_closed'];
?>
<tr>
<td><span class="schedule-name"><?= eIndex($jadwal['nama_tenaga']) ?></span><span class="schedule-role"><?= eIndex($jadwal['peran']) ?></span></td>
<td><?= eIndex($jadwal['hari']) ?></td>
<td>
    <?php if (!empty($statusJadwal['is_24jam'])): ?>
        <strong style="color:#6D28D9;">Pelayanan 24 Jam Non-Stop</strong>
    <?php else: ?>
        <?= eIndex(jamIndex($jadwal['jam_mulai'])) ?> - <?= eIndex(jamIndex($jadwal['jam_selesai'])) ?>
    <?php endif; ?>
</td>
<td>
    <?php if (!empty($statusJadwal['is_24jam'])): ?>
        <span class="schedule-status" style="background:#EDE9FE;color:#6D28D9;border:1px solid #DDD6FE;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;" title="Siaga 24 Jam non-stop">✨ Tersedia 24 Jam</span>
    <?php elseif ($isClosedToday): ?>
        <span class="schedule-status" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;" title="<?= eIndex($statusJadwal['message']) ?>">⛔ Ditutup (1 Jam Sblm Selesai)</span>
    <?php else: ?>
        <span class="schedule-status" style="background:#EAF7EF;color:#10B981;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800;">Tersedia</span>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">Tidak ada jadwal pelayanan untuk hari ini.</td></tr>
<?php endif; ?>
</tbody>

            </table>

        </div>

    </div>

</section>


<!-- =====================================================
     DOKTER & BIDAN
===================================================== -->

<section
    class="staff-section"
    id="tenaga-kesehatan"
>

    <div class="container">


        <div class="section-heading">

            <span class="section-label">
                TENAGA KESEHATAN
            </span>

            <h2>
                Dokter & Bidan Kami
            </h2>

            <p>

                Pilih dokter atau bidan dan
                jadwal yang tersedia untuk Anda.

            </p>

        </div>


        <div class="staff-carousel-wrapper" style="position:relative;margin-top:10px;">
            <button type="button" class="staff-carousel-btn prev" onclick="scrollStaffList(-1)" aria-label="Geser ke kiri" style="position:absolute;left:-16px;top:45%;transform:translateY(-50%);z-index:20;width:38px;height:38px;border-radius:50%;background:#FFFFFF;border:1px solid #E8DFF0;box-shadow:0 4px 14px rgba(106,34,167,.18);color:#6A22A7;font-size:18px;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;">❮</button>

            <div class="staff-list" id="staffListContainer" style="display:flex;gap:18px;width:100%;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding:8px 4px 18px;scrollbar-width:thin;-webkit-overflow-scrolling:touch;">
<?php foreach ($dataStaff as $staff): ?>
<?php 
$key = $staff['jenis'].'-'.(int)$staff['id_tenaga']; 
$jadwalStaff = $staffSchedules[$key] ?? []; 
$foto = trim((string)($staff['foto'] ?? '')); 
$staffPhotoResolved = '';
if ($foto !== '') {
    $cleanF = ltrim($foto, '/');
    if (strpos($cleanF, 'uploads/') === 0 || strpos($cleanF, 'assets/') === 0) {
        $staffPhotoResolved = $cleanF;
    } elseif (file_exists(__DIR__ . '/uploads/tenaga/' . $cleanF)) {
        $staffPhotoResolved = 'uploads/tenaga/' . $cleanF;
    } elseif (file_exists(__DIR__ . '/assets/img/' . $cleanF)) {
        $staffPhotoResolved = 'assets/img/' . $cleanF;
    } else {
        $staffPhotoResolved = 'uploads/' . $cleanF;
    }
}
$hasRealPhoto = false;
if ($staffPhotoResolved !== '' && file_exists(__DIR__ . '/' . $staffPhotoResolved) && strpos($staffPhotoResolved, 'default_') === false) {
    $hasRealPhoto = true;
}
$staffDays = [];
$firstPoli = 0;
if (!empty($jadwalStaff)) {
    foreach ($jadwalStaff as $js) {
        $staffDays[] = $js['hari'];
        if (!$firstPoli && !empty($js['id_poli'])) $firstPoli = (int)$js['id_poli'];
    }
}
$staffDaysStr = implode(',', array_unique($staffDays));
?>
<div class="staff-card" style="flex:0 0 265px;width:265px;min-width:265px;scroll-snap-align:start;border:1px solid var(--border);border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(70,40,90,0.07);transition:all 0.25s ease;display:flex;flex-direction:column;overflow:hidden;text-align:left;">
    <!-- FOTO DOKTER KOTAK MEMANJANG -->
    <div class="staff-photo-wrap" style="position:relative;width:100%;height:240px;background:linear-gradient(135deg, #F3EDFC 0%, #E8DCFA 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;">
        <?php if ($hasRealPhoto): ?>
            <img src="<?= eIndex($staffPhotoResolved) ?>" alt="<?= eIndex($staff['nama_tenaga']) ?>" style="width:100%;height:100%;object-fit:cover;object-position:top center;display:block;transition:transform 0.3s ease;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                <span style="font-size:72px;line-height:1;user-select:none;filter:drop-shadow(0 4px 10px rgba(106,34,167,0.15));"><?= $staff['jenis'] === 'Dokter' ? '🩺' : '👶' ?></span>
            </div>
        <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                <span style="font-size:72px;line-height:1;user-select:none;filter:drop-shadow(0 4px 10px rgba(106,34,167,0.15));"><?= $staff['jenis'] === 'Dokter' ? '🩺' : '👶' ?></span>
            </div>
        <?php endif; ?>
        <div style="position:absolute;top:10px;left:10px;display:flex;gap:6px;flex-wrap:wrap;z-index:2;">
            <span style="background:rgba(79,50,147,0.88);backdrop-filter:blur(4px);color:#fff;font-size:9.5px;font-weight:800;padding:4px 10px;border-radius:20px;letter-spacing:.4px;text-transform:uppercase;">
                <?= eIndex(strtoupper($staff['peran'])) ?>
            </span>
            <?php if (($staff['status_tenaga'] ?? '') === 'cuti'): ?>
                <span class="badge-cuti-merah">🔴 Cuti / Tidak Hadir</span>
            <?php endif; ?>
        </div>
        <div style="position:absolute;bottom:8px;right:8px;z-index:2;">
            <span style="background:#fff;color:var(--purple);font-size:12px;padding:3px 8px;border-radius:12px;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,0.15);display:inline-flex;align-items:center;gap:4px;">
                <?= $staff['jenis'] === 'Dokter' ? '👨‍⚕️ Dokter' : '👩‍⚕️ Bidan' ?>
            </span>
        </div>
    </div>

    <div class="staff-info" style="flex:1;display:flex;flex-direction:column;justify-content:space-between;padding:16px 14px;">
        <div>
            <h3 style="font-size:15px;font-weight:800;color:var(--purple-dark);line-height:1.35;margin-bottom:8px;min-height:40px;display:flex;align-items:center;">
                <?= eIndex($staff['nama_tenaga']) ?>
            </h3>
            <div class="staff-schedule" style="font-size:11.5px;color:var(--text);line-height:1.5;margin-bottom:14px;background:#FAF8FD;padding:9px 11px;border-radius:10px;border:1px solid #F0ECF5;text-align:left;">
                <strong style="color:var(--purple-dark);font-size:11px;display:flex;align-items:center;gap:4px;margin-bottom:3px;">
                    <span>📅</span> Jadwal Praktik
                </strong>
                <div>
                    <?php if ($jadwalStaff): ?><?= jadwalRingkasIndex($jadwalStaff) ?><?php else: ?><span style="color:#9B8EA9;">Jadwal belum tersedia</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="staff-action" style="margin-top:auto;">
            <?php if (($staff['status_tenaga'] ?? '') === 'cuti'): ?>
                <button type="button" class="staff-button" style="width:100%;padding:10px 12px;font-size:12.5px;font-weight:800;border-radius:10px;text-align:center;display:block;background:#FDE8E8;color:#DC2626;border:1px solid #FCA5A5;cursor:not-allowed;" disabled>
                    🔴 Sedang Cuti
                </button>
            <?php else: ?>
                <a href="pasien/daftar.php?<?= $staff['jenis']==='Dokter' ? 'dokter' : 'bidan' ?>=<?= (int)$staff['id_tenaga'] ?>" 
                   class="staff-button" 
                   onclick="bukaJadwalModal(event, this)" 
                   data-jenis="<?= eIndex($staff['jenis']) ?>" 
                   data-id="<?= (int)$staff['id_tenaga'] ?>" 
                   data-poli="<?= $firstPoli ?>" 
                   data-nama="<?= eIndex($staff['nama_tenaga']) ?>" 
                   data-hari="<?= eIndex($staffDaysStr) ?>"
                   style="width:100%;padding:10px 12px;font-size:12.5px;font-weight:800;border-radius:10px;text-align:center;display:block;">Daftar Sekarang</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
            </div>

            <button type="button" class="staff-carousel-btn next" onclick="scrollStaffList(1)" aria-label="Geser ke kanan" style="position:absolute;right:-16px;top:45%;transform:translateY(-50%);z-index:20;width:38px;height:38px;border-radius:50%;background:#FFFFFF;border:1px solid #E8DFF0;box-shadow:0 4px 14px rgba(106,34,167,.18);color:#6A22A7;font-size:18px;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;">❯</button>
        </div>
    </div>
</section>


<!-- =====================================================
     GOOGLE REVIEWS & ULASAN PASIEN
===================================================== -->
<section class="google-review-section" id="google-review" style="padding: 70px 0; background: linear-gradient(180deg, #FBF8FE 0%, #F5EEFD 100%);">
    <div class="container">
        <!-- HEADER ULASAN GOOGLE -->
        <div class="google-review-header-card" style="background:#fff;border-radius:24px;padding:32px 36px;border:1.5px solid #EADDFC;box-shadow:0 12px 36px rgba(106,34,167,0.07);margin-bottom:32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:24px;">
            <div style="max-width:600px;">
                <div style="display:inline-flex;align-items:center;gap:8px;background:#FEF3C7;color:#92400E;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:800;margin-bottom:12px;border:1px solid #FDE68A;flex-wrap:wrap;">
                    <span>🌟 Google Maps Verified Reviews</span>
                    <span>•</span>
                    <span>⭐ 5.0 Bintang</span>
                    <span>•</span>
                    <span style="background:#FDE68A;color:#92400E;padding:2px 9px;border-radius:999px;font-weight:900;">🎁 +1 Poin Tiap Ulasan</span>
                </div>
                <h2 style="font-size:28px;font-weight:900;color:var(--purple-dark);line-height:1.25;margin-bottom:8px;">
                    Ulasan Pasien &amp; Google Maps
                </h2>
                <p style="color:var(--text);font-size:14px;line-height:1.6;margin-bottom:0;">
                    Simak pengalaman nyata pasien di Klinik Pratama Rumah Hanania. Tulis ulasan Anda langsung dari website atau Google Maps!
                </p>
            </div>

            <div class="google-review-action-col" style="display:flex;flex-direction:column;align-items:flex-end;gap:12px;text-align:right;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="font-size:38px;font-weight:900;color:#2B1B48;line-height:1;">5.0</div>
                    <div style="text-align:left;">
                        <div style="color:#F59E0B;font-size:18px;letter-spacing:1px;">⭐⭐⭐⭐⭐</div>
                        <div style="font-size:11.5px;color:var(--muted);font-weight:700;">Google Maps &amp; Web Review</div>
                    </div>
                </div>
                <div class="google-review-btn-wrap" style="display:flex;flex-direction:column;gap:10px;width:100%;max-width:540px;align-items:stretch;">
                    <a href="https://maps.app.goo.gl/524k7ABe1fckKgpt6" target="_blank" rel="noopener noreferrer" class="btn btn-review-maps" style="background:#fff;color:#6A22A7;border:1.5px solid #D6BFF3;font-weight:800;font-size:13.5px;padding:10px 18px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 3px 10px rgba(106,34,167,0.06);transition:all .2s;text-decoration:none;box-sizing:border-box;width:100%;white-space:nowrap;">
                        <span>📍</span> Lihat Google Maps ↗
                    </a>
                    <button type="button" class="btn btn-review-tulis" onclick="handleBeriReviewKlik()" style="background:#6A22A7;color:#fff;font-weight:800;font-size:13px;padding:10px 18px;border-radius:12px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 4px 14px rgba(106,34,167,0.25);transition:all .2s;text-align:center;box-sizing:border-box;line-height:1.4;width:100%;">
                        <span>⭐</span>
                        <span class="btn-review-text-wrap" style="white-space:nowrap;">
                            Beri Ulasan
                            <?php if ($isPasienLoggedIn): ?>
                                <span class="review-sub-info" style="font-weight:600;font-size:12px;color:#F3E8FF;">(+1 Poin Sahabat Hanania)</span>
                            <?php else: ?>
                                <span class="review-sub-info" style="font-weight:600;font-size:11.5px;color:#F3E8FF;">(Dapatkan +1 Poin jika Menjadi Sahabat Hanania atau Login)</span>
                            <?php endif; ?>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- DAFTAR KARTU REVIEW (SLIDER / CAROUSEL) -->
        <div class="review-slider-wrapper" style="position:relative;margin-top:20px;">
            <button type="button" class="slider-nav-btn prev" onclick="slideReview(-1)" aria-label="Sebelumnya" style="position:absolute;left:-16px;top:50%;transform:translateY(-50%);z-index:10;width:40px;height:40px;border-radius:50%;background:#FFFFFF;border:1px solid #E6DBF2;box-shadow:0 6px 16px rgba(106,34,167,0.18);color:#6A22A7;font-size:18px;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;">❮</button>

            <div id="gridUlasanContainer" style="display:flex;gap:20px;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding:12px 6px 20px;scrollbar-width:thin;-webkit-overflow-scrolling:touch;">
                <?php foreach ($daftarUlasanGoogle as $rev): 
                    $rRating = max(1, min(5, (int)($rev['rating'] ?? 5)));
                    $starsStr = str_repeat('⭐', $rRating);
                    $initial = strtoupper(substr(trim($rev['nama'] ?? 'P'), 0, 2));
                    $isWeb = (($rev['sumber'] ?? '') === 'Website Langsung' || ($rev['sumber'] ?? '') === 'Website Resmi');
                ?>
                <div class="review-card-item" style="flex:0 0 350px;width:350px;min-width:350px;scroll-snap-align:start;background:#fff;border-radius:20px;padding:22px;border:1.5px solid #ECE3F6;box-shadow:0 6px 20px rgba(106,34,167,0.06);display:flex;flex-direction:column;justify-content:space-between;transition:transform .2s, box-shadow .2s;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg, #8E44AD 0%, #6A22A7 100%);color:#fff;font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(106,34,167,0.25);flex-shrink:0;">
                                    <?= eIndex($initial) ?>
                                </div>
                                <div>
                                    <strong style="color:#2B1B48;font-size:14.5px;display:block;"><?= eIndex($rev['nama']) ?></strong>
                                    <span style="font-size:11px;color:var(--muted);"><?= eIndex($rev['badge'] ?? ($isWeb ? 'Ulasan Pasien Web' : 'Ulasan Google Maps')) ?></span>
                                </div>
                            </div>
                            <span style="font-size:11px;color:var(--muted);white-space:nowrap;"><?= eIndex($rev['tanggal'] ?? 'Terverifikasi') ?></span>
                        </div>

                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                            <span style="font-size:13px;letter-spacing:1px;"><?= $starsStr ?></span>
                            <span style="background:#F4EDFB;color:#6A22A7;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:6px;">
                                <?= eIndex($rev['layanan'] ?? 'Pelayanan Klinik') ?>
                            </span>
                        </div>

                        <p style="color:#4B3F5B;font-size:13px;line-height:1.65;font-style:italic;margin-bottom:0;">
                            &ldquo;<?= nl2br(eIndex($rev['ulasan'])) ?>&rdquo;
                        </p>
                    </div>

                    <div style="margin-top:14px;padding-top:10px;border-top:1px solid #F4EEFB;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#8F82A2;">
                        <?php if ($isWeb): ?>
                            <span>🌐 Dikirim dari Web</span>
                        <?php else: ?>
                            <a href="https://maps.app.goo.gl/524k7ABe1fckKgpt6" target="_blank" rel="noopener noreferrer" style="color:#6A22A7;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                <span>📍 Google Maps</span> <span style="font-size:10px;">↗</span>
                            </a>
                        <?php endif; ?>
                        <span style="font-weight:700;color:#15803D;">✓ Terverifikasi</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="slider-nav-btn next" onclick="slideReview(1)" aria-label="Selanjutnya" style="position:absolute;right:-16px;top:50%;transform:translateY(-50%);z-index:10;width:40px;height:40px;border-radius:50%;background:#FFFFFF;border:1px solid #E6DBF2;box-shadow:0 6px 16px rgba(106,34,167,0.18);color:#6A22A7;font-size:18px;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;">❯</button>
        </div>
    </div>
</section>

<!-- MODAL PILIHAN LOGIN / DAFTAR SAHABAT HANANIA / GOOGLE MAPS UNTUK REVIEW -->
<div id="modalReviewLoginPrompt" onclick="if(event.target===this)tutupModalReviewLoginPrompt()" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.68);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:24px;max-width:480px;width:100%;padding:30px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.28);position:relative;text-align:center;box-sizing:border-box;">
        <button type="button" onclick="tutupModalReviewLoginPrompt()" aria-label="Tutup" style="position:absolute;top:18px;right:18px;background:#F3EDFA;border:none;width:32px;height:32px;border-radius:50%;color:#6A22A7;font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;">✕</button>

        <div style="width:62px;height:62px;border-radius:50%;background:#FEF3C7;color:#D97706;font-size:28px;display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px;box-shadow:0 6px 16px rgba(217,119,6,0.18);">
            ⭐
        </div>

        <h3 style="color:#2B1B48;font-size:21px;font-weight:900;margin-bottom:16px;line-height:1.3;">
            Beri Ulasan &amp; Klaim +1 Poin
        </h3>

        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
            <a href="daftar_akun.php?redirect=review" class="btn" style="background:#6A22A7;color:#fff;font-weight:800;font-size:14px;padding:12px;border-radius:12px;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(106,34,167,0.25);">
                <span>✨</span> Daftar Menjadi Sahabat Hanania (+1 Poin)
            </a>
            <a href="login.php?redirect=review" class="btn" style="background:#FAF5FF;color:#6A22A7;border:1.5px solid #D2B8EE;font-weight:800;font-size:14px;padding:11px;border-radius:12px;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span>🔑</span> Masuk ke Akun (+1 Poin)
            </a>
            <a href="https://maps.app.goo.gl/524k7ABe1fckKgpt6" target="_blank" rel="noopener noreferrer" class="btn" style="background:#fff;color:#5D4D73;border:1.5px solid #E5D5FA;font-weight:700;font-size:13px;padding:11px;border-radius:12px;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px;">
                <span>📍</span> Buka Google Maps (Tanpa Poin)
            </a>
        </div>
    </div>
</div>

<!-- MODAL TULIS ULASAN GOOGLE / WEB -->
<div id="modalUlasanWeb" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.6);backdrop-filter:blur(5px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:22px;max-width:520px;width:100%;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.25);position:relative;max-height:90vh;overflow-y:auto;">
        <button type="button" onclick="tutupModalTulisUlasan()" style="position:absolute;top:18px;right:18px;background:#F3EDFA;border:none;width:32px;height:32px;border-radius:50%;color:#6A22A7;font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>

        <div style="text-align:center;margin-bottom:20px;">
            <div style="font-size:36px;margin-bottom:6px;">⭐</div>
            <h3 style="color:#2B1B48;font-size:20px;font-weight:900;">Tulis Ulasan &amp; Beri Review</h3>
            <p style="color:var(--muted);font-size:13px;margin-top:4px;">
                Pengalaman nyata Anda membantu kami terus menjaga mutu pelayanan kesehatan terbaik.
            </p>
            <?php if ($isPasienLoggedIn): ?>
                <div style="background:#FEF3C7;color:#92400E;padding:8px 14px;border-radius:12px;font-size:12.5px;font-weight:800;margin-top:10px;display:inline-flex;align-items:center;gap:8px;border:1px solid #FDE68A;text-align:left;">
                    <?php if ($googleReviewClaimed): ?>
                        <span>ℹ️ Akun Anda sudah mengklaim +1 Poin Review Google. Ulasan baru Anda tetap dapat dikirim dan tampil di website.</span>
                    <?php else: ?>
                        <span>🎁 Member Sahabat Hanania: Berikan ulasan Anda &amp; dapatkan otomatis <strong>+1 Poin Reward</strong>!</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <form id="formUlasanWeb" onsubmit="kirimUlasanWeb(event)">
            <!-- BINTANG RATING INTERAKTIF -->
            <div style="text-align:center;margin-bottom:18px;">
                <label style="display:block;font-size:12px;font-weight:800;color:#2B1B48;margin-bottom:6px;">Beri Rating Bintang *</label>
                <div id="starRatingWrap" style="font-size:28px;cursor:pointer;display:inline-flex;gap:4px;user-select:none;">
                    <span data-star="1" onclick="pilihStar(1)">⭐</span>
                    <span data-star="2" onclick="pilihStar(2)">⭐</span>
                    <span data-star="3" onclick="pilihStar(3)">⭐</span>
                    <span data-star="4" onclick="pilihStar(4)">⭐</span>
                    <span data-star="5" onclick="pilihStar(5)">⭐</span>
                </div>
                <input type="hidden" name="rating" id="inputRatingValue" value="5">
                <div id="ratingTextLabel" style="font-size:12px;color:#D97706;font-weight:800;margin-top:2px;">5 Bintang (Sangat Memuaskan)</div>
                <div id="ratingNoticeInfo" style="display:none;font-size:11.5px;color:#92400E;background:#FEF3C7;padding:6px 12px;border-radius:8px;margin-top:8px;font-weight:600;border:1px solid #FDE68A;text-align:left;line-height:1.4;"></div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12.5px;font-weight:700;color:#2B1B48;margin-bottom:5px;">Nama Anda *</label>
                <input type="text" name="nama" id="inputUlasanNama" value="<?= eIndex($namaPasien ?: '') ?>" placeholder="Masukkan nama Anda..." required style="width:100%;padding:10px 14px;border:1.5px solid #E5D5FA;border-radius:10px;font-size:13px;outline:none;font-family:inherit;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12.5px;font-weight:700;color:#2B1B48;margin-bottom:5px;">Layanan yang Diterima *</label>
                <select name="layanan" id="selectUlasanLayanan" style="width:100%;padding:10px 14px;border:1.5px solid #E5D5FA;border-radius:10px;font-size:13px;outline:none;background:#fff;font-family:inherit;">
                    <option value="Poli Umum">Poli Umum</option>
                    <option value="Poli Kebidanan &amp; Kandungan">Poli Kebidanan &amp; Kandungan</option>
                    <option value="USG Kehamilan 2D / 4D">USG Kehamilan 2D / 4D</option>
                    <option value="Imunisasi &amp; Anak">Imunisasi &amp; Anak</option>
                    <option value="Laboratorium &amp; Cek Darah">Laboratorium &amp; Cek Darah</option>
                    <option value="Farmasi / Obat">Farmasi &amp; Apotek</option>
                    <option value="Pelayanan Umum Klinik" selected>Pelayanan Umum Klinik</option>
                </select>
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:block;font-size:12.5px;font-weight:700;color:#2B1B48;margin-bottom:5px;">Isi Ulasan &amp; Pengalaman Anda *</label>
                <textarea name="ulasan" id="inputUlasanTeks" rows="4" placeholder="Ceritakan bagaimana pelayanan dokter, bidan, kebersihan klinik, atau kecepatan pendaftaran..." required style="width:100%;padding:10px 14px;border:1.5px solid #E5D5FA;border-radius:10px;font-size:13px;outline:none;font-family:inherit;line-height:1.5;"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="tutupModalTulisUlasan()" class="btn" style="background:#F3EDFA;color:#6A22A7;font-weight:800;padding:10px 18px;border-radius:10px;border:none;cursor:pointer;">Batal</button>
                <button type="submit" id="btnSubmitUlasanWeb" class="btn" style="background:#6A22A7;color:#fff;font-weight:800;padding:10px 22px;border-radius:10px;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(106,34,167,0.3);">
                    Kirim Ulasan Sekarang ✨
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================
     TENTANG KAMI
===================================================== -->

<section
    class="about-section"
    id="tentang"
>

    <div class="container">


        <div class="about-container">

            <div class="about-image">
                <img
                    src="<?= eIndex(getSiteKonten('tentang_foto', 'assets/img/klinik3.jpg')) ?>"
                    alt="Foto Klinik Pratama Rumah Hanania"
                    onerror="this.src='assets/img/klinik3.jpg';"
                >
            </div>

            <div class="about-text">
                <span class="section-label">
                    TENTANG KAMI
                </span>

                <h2>
                    <?= eIndex(getSiteKonten('tentang_judul', 'Klinik Pratama Rumah Hanania')) ?>
                </h2>

                <p>
                    <?= nl2br(eIndex(getSiteKonten('tentang_paragraf_1', 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan yang memberikan pelayanan kesehatan kepada masyarakat.'))) ?>
                </p>

                <p>
                    <?= nl2br(eIndex(getSiteKonten('tentang_paragraf_2', 'Website ini membantu pasien memperoleh informasi klinik, melihat jadwal pelayanan, serta melakukan pendaftaran pasien secara online.'))) ?>
                </p>

                <div style="margin-top:24px;">
                    <a href="tentang/fasilitas klinik.php" class="btn-lihat-galeri">
                        🖼️ Lihat Galeri Kami &rarr;
                    </a>
                </div>
            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     MUSIK LATAR WEBSITE (SUASANA KLINIK)
     Dimuat secara global melalui includes/footer.php (hanania-audio-speaker)
===================================================== -->


<!-- =====================================================
     FOOTER
===================================================== -->

<?php include __DIR__ . '/includes/footer.php'; ?>


<!-- =====================================================
     MODAL PILIH JADWAL PENDAFTARAN
===================================================== -->

<div class="jm-overlay" id="jmOverlay" onclick="jmHandleOverlayClick(event)">

    <div class="jm-box">

        <div class="jm-head">

            <div>
                <h3>Pilih Jadwal Pendaftaran</h3>
                <p>Pilih tanggal kunjungan, lalu pilih dokter atau bidan sesuai jadwal yang tersedia.</p>
            </div>

            <button type="button" class="jm-close" onclick="jmClose()" aria-label="Tutup">✕</button>

        </div>

        <div class="jm-body">

            <div class="jm-date-field">
                <label for="jmTanggal">Tanggal Kunjungan</label>
                <input type="date" id="jmTanggal" onchange="jmLoadJadwal()">
            </div>

            <div id="jmContent">
                <div class="jm-loading">Memuat jadwal...</div>
            </div>

        </div>

    </div>

</div>

<!-- Chatbot global floating HanaBot disertakan otomatis via includes/footer.php -->


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>





    /* =====================================================
       KLAIM POIN GOOGLE REVIEW (HOME)
    ===================================================== */
    function klaimGoogleReviewHome() {
        const btn = document.getElementById('btnKlaimReviewHome');
        if (!btn) return;

        window.open('https://maps.app.goo.gl/524k7ABe1fckKgpt6', '_blank');

        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = '<span>Memproses Poin Reward...</span>';

        fetch('pasien/klaim_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                btn.className = 'gr-btn-claim gr-btn-claimed';
                btn.innerHTML = '<span>✅ +1 Poin Berhasil Diklaim!</span>';
                
                const ptEls = document.querySelectorAll('.hanania-profile-points, .reward-points-number, .hero-reward-points');
                ptEls.forEach(function(el) {
                    el.innerHTML = '⭐ ' + data.new_poin + ' Poin';
                });

                alert('🎉 Terima kasih atas ulasan Google Review Anda! +1 Poin Reward telah berhasil ditambahkan ke akun Anda.');
            } else if (data.already_claimed) {
                btn.className = 'gr-btn-claim gr-btn-claimed';
                btn.innerHTML = '<span>✅ Poin Sudah Pernah Diklaim</span>';
                alert(data.message);
            } else {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Beri Ulasan di Google (+1 Poin)</span>';
                alert(data.message || 'Terjadi kesalahan.');
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span>Beri Ulasan di Google (+1 Poin)</span>';
            console.error(err);
        });
    }

    /* =====================================================
       TENTANG DROPDOWN
    ===================================================== */

        function toggleAboutDropdown() {
            const menu = document.getElementById('aboutDropdown');

            if (!menu) return;

            menu.classList.toggle('active');
        }

        document.addEventListener('click', function(event) {

            const dropdown = document.querySelector('.about-dropdown');
            const menu = document.getElementById('aboutDropdown');

            if (
                dropdown &&
                menu &&
                !dropdown.contains(event.target)
            ) {
                menu.classList.remove('active');
            }

        });




        /* =====================================================
           PROFILE DROPDOWN
        ===================================================== */

        function toggleProfileDropdown() {

            const menu = document.getElementById('profileDropdown');
            const button = document.querySelector('.profile-button');

            if (!menu) return;

            menu.classList.toggle('active');

            if (button) {
                button.setAttribute(
                    'aria-expanded',
                    menu.classList.contains('active') ? 'true' : 'false'
                );
            }
        }

        document.addEventListener('click', function(event) {

            const dropdown = document.querySelector('.profile-dropdown');
            const menu = document.getElementById('profileDropdown');
            const button = document.querySelector('.profile-button');

            if (
                dropdown &&
                menu &&
                !dropdown.contains(event.target)
            ) {
                menu.classList.remove('active');

                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            }
        });



    /* =====================================================
       MODAL PILIH JADWAL PENDAFTARAN
       Alur: klik "Daftar ke Klinik Sekarang" pada kartu
       dokter/bidan -> buka modal -> pilih tanggal & jadwal
       -> lanjut ke pasien/daftar.php dengan data terbawa.
    ===================================================== */

    let jmSelectedJenis = null;
    let jmSelectedId = null;
    let jmSelectedPoli = 0;
    let jmSelectedSesi = null;
    let jmPreselectJenis = null;
    let jmPreselectId = null;

    function bukaJadwalModal(event, el) {
        if (event) event.preventDefault();

        jmPreselectJenis = el.dataset.jenis === 'Dokter' ? 'dokter' : 'bidan';
        jmPreselectId = Number(el.dataset.id);
        jmSelectedJenis = null;
        jmSelectedId = null;
        jmSelectedPoli = Number(el.dataset.poli || 0);
        jmSelectedSesi = null;

        const tanggalInput = document.getElementById('jmTanggal');
        const today = new Date();
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        const todayStr = `${y}-${m}-${d}`;
        tanggalInput.min = todayStr;

        // Cek hari praktik dokter/bidan yang diklik
        const rawHari = (el.dataset.hari || '').toLowerCase();
        const hariArr = rawHari.split(',').map(s => s.trim()).filter(Boolean);
        const hariMap = { 'minggu': 0, 'senin': 1, 'selasa': 2, 'rabu': 3, 'kamis': 4, 'jumat': 5, 'sabtu': 6 };

        let targetDate = new Date();
        if (hariArr.length > 0) {
            let found = false;
            for (let i = 0; i < 7; i++) {
                const check = new Date();
                check.setDate(check.getDate() + i);
                const dayNum = check.getDay();
                for (const h of hariArr) {
                    if (hariMap[h] === dayNum) {
                        targetDate = check;
                        found = true;
                        break;
                    }
                }
                if (found) break;
            }
        }

        const ty = targetDate.getFullYear();
        const tm = String(targetDate.getMonth() + 1).padStart(2, '0');
        const td = String(targetDate.getDate()).padStart(2, '0');
        tanggalInput.value = `${ty}-${tm}-${td}`;

        document.getElementById('jmOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';

        jmLoadJadwal();
        return false;
    }

    function jmClose() {
        document.getElementById('jmOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }

    function jmHandleOverlayClick(event) {
        if (event.target.id === 'jmOverlay') jmClose();
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') jmClose();
    });

    async function jmLoadJadwal() {

        const tanggal = document.getElementById('jmTanggal').value;
        const content = document.getElementById('jmContent');

        if (!tanggal) {
            content.innerHTML = '<div class="jm-empty">Pilih tanggal terlebih dahulu.</div>';
            return;
        }

        content.innerHTML = '<div class="jm-loading">Memuat jadwal...</div>';
        jmSelectedJenis = null;
        jmSelectedId = null;
        jmSelectedPoli = 0;
        jmSelectedSesi = null;

        try {
            const response = await fetch('pasien/get_jadwal.php?tanggal=' + encodeURIComponent(tanggal));
            const data = await response.json();
            jmRenderJadwal(data);
        } catch (error) {
            content.innerHTML = '<div class="jm-empty">Jadwal tidak dapat dimuat. Silakan coba lagi.</div>';
        }
    }

    function jmFormatJam(jam) {
        return String(jam || '').substring(0, 5);
    }

    function jmEscape(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function jmItemHtml(item, isPre) {
        const jenis = item.jenis === 'Dokter' ? 'dokter' : 'bidan';
        const isTersedia = (item.tersedia !== false);
        const disabledClass = !isTersedia ? ' jm-item-disabled' : '';
        const selectedClass = (isPre && isTersedia) ? ' jm-selected' : '';
        const sesi = (item.sesi && item.sesi.length > 0) ? item.sesi[0] : {
            jam_mulai: item.jam_mulai || '08:00',
            jam_selesai: item.jam_selesai || '12:00',
            label: (item.jam_mulai ? item.jam_mulai.substring(0, 5) : '08:00') + ' – ' + (item.jam_selesai ? item.jam_selesai.substring(0, 5) : '12:00') + ' WIB',
            tersedia: item.tersedia !== false
        };

        const statusBadge = isTersedia
            ? '<span style="margin-top:6px;font-size:11px;font-weight:700;color:#15803d;background:#DCFCE7;padding:3px 9px;border-radius:6px;display:inline-block;">🕒 ' + jmEscape(sesi.label) + ' • Sesi Tersedia</span>'
            : '<span style="margin-top:6px;font-size:11px;font-weight:800;color:#B91C1C;background:#FEE2E2;padding:3px 9px;border-radius:6px;display:inline-block;">⛔ Pendaftaran Ditutup / Kuota Habis (Jadwal: ' + jmEscape(sesi.label) + ')</span>';

        return '<div class="jm-item' + selectedClass + disabledClass + '" data-jenis="' + jenis + '" data-id="' + item.id_tenaga + '" data-poli="' + (item.id_poli || 0) + '" data-mulai="' + jmEscape(sesi.jam_mulai) + '" data-selesai="' + jmEscape(sesi.jam_selesai) + '" data-tersedia="' + (isTersedia ? '1' : '0') + '" onclick="jmPilihStaffCard(this)" style="' + (!isTersedia ? 'cursor:not-allowed;opacity:0.75;background:#FAF8F8;border:1.5px dashed #E5D0D0;' : 'cursor:pointer;') + '">'
            + '<div style="flex:1;">'
            + '<div class="jm-item-name" style="font-size:15px;font-weight:800;color:' + (isTersedia ? 'var(--purple-dark)' : '#7F6B8A') + ';">' + jmEscape(item.nama_tenaga) + '</div>'
            + '<div class="jm-item-sub" style="font-size:12px;color:var(--muted);margin-top:2px;">' + jmEscape(item.peran || item.jenis) + ' &bull; ' + jmEscape(item.nama_poli || 'Poli Pelayanan') + '</div>'
            + statusBadge
            + '</div>'
            + (!isTersedia ? '<span style="font-size:11px;font-weight:800;color:#991B1B;background:#FEE2E2;padding:4px 8px;border-radius:6px;align-self:center;">⛔ Tutup</span>' : '')
            + '</div>';
    }

    function jmRenderJadwal(data) {
        const content = document.getElementById('jmContent');
        let tenagaList = (data.tenaga || []);

        if (jmPreselectJenis && jmPreselectId) {
            const filtered = tenagaList.filter(function (item) {
                const jenis = item.jenis === 'Dokter' ? 'dokter' : 'bidan';
                return jenis === jmPreselectJenis && Number(item.id_tenaga) === jmPreselectId;
            });
            if (filtered.length > 0) {
                tenagaList = filtered;
            } else {
                content.innerHTML = '<div class="jm-empty">Tenaga kesehatan yang Anda pilih tidak memiliki jadwal praktik pada tanggal ini. Silakan pilih hari atau tanggal lain yang sesuai dengan jadwal praktiknya.</div>';
                return;
            }
        }

        if (!tenagaList.length) {
            content.innerHTML = '<div class="jm-empty">Tidak ada jadwal dokter maupun bidan pada tanggal ini. Silakan pilih tanggal lain yang sesuai jadwal praktik.</div>';
            return;
        }

        let hasActive = false;
        tenagaList.forEach(function (t) {
            if (t.tersedia !== false) hasActive = true;
        });

        let html = '';
        if (!hasActive) {
            html += '<div style="background:#FEE2E2;border:1.5px solid #FCA5A5;color:#991B1B;padding:12px 14px;border-radius:12px;font-size:12.5px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px;">⚠️ <strong>Pendaftaran Ditutup / Kuota Habis:</strong> Sesi pendaftaran untuk hari ini telah ditutup. Anda tetap dapat melihat jadwal nakes di bawah, atau pilih tanggal kunjungan berikutnya.</div>';
        }

        html += '<div class="jm-group-title">TENAGA KESEHATAN DAN SESI PELAYANAN</div>';
        tenagaList.forEach(function (item, idx) {
            const isPre = (jmPreselectJenis && jmPreselectId)
                ? (jmPreselectJenis === (item.jenis === 'Dokter' ? 'dokter' : 'bidan') && jmPreselectId === Number(item.id_tenaga))
                : (idx === 0);
            html += jmItemHtml(item, isPre);
        });

        html += '<div class="jm-footer"><button type="button" class="jm-btn-continue" id="jmContinueBtn" onclick="jmLanjutkan()">Lanjutkan Pendaftaran →</button></div>';

        content.innerHTML = html;

        const preselected = content.querySelector('.jm-item.jm-selected:not(.jm-item-disabled)') || content.querySelector('.jm-item:not(.jm-item-disabled)');
        const btn = document.getElementById('jmContinueBtn');
        if (preselected) {
            jmPilihStaffCard(preselected);
        } else {
            jmSelectedJenis = null;
            jmSelectedId = null;
            if (btn) {
                btn.disabled = true;
                btn.textContent = '⛔ Pendaftaran Ditutup / Kuota Habis';
            }
        }
    }

    function jmPilihStaffCard(el) {
        if (!el) return;
        if (el.dataset.tersedia === '0') {
            alert('Pendaftaran online untuk jadwal ini telah ditutup / kuota penuh. Anda tetap dapat melihat jadwal yang tertera, atau silakan pilih tanggal/jadwal lain yang masih tersedia.');
            return;
        }
        document.querySelectorAll('.jm-item').forEach(function (item) { item.classList.remove('jm-selected'); });
        el.classList.add('jm-selected');
        jmSelectedJenis = el.dataset.jenis;
        jmSelectedId = Number(el.dataset.id);
        jmSelectedPoli = Number(el.dataset.poli);
        jmSelectedSesi = { mulai: el.dataset.mulai, selesai: el.dataset.selesai };
        const btn = document.getElementById('jmContinueBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Lanjutkan Pendaftaran →';
        }
    }

    function jmPilihSesi(event, el) {
        if (event) event.stopPropagation();
        const card = el.closest('.jm-item');
        if (card) jmPilihStaffCard(card);
    }

    function jmLanjutkan() {
        if (!jmSelectedJenis || !jmSelectedId) return;
        const tanggal = document.getElementById('jmTanggal').value;
        const params = new URLSearchParams();
        params.set(jmSelectedJenis, jmSelectedId);
        if (jmSelectedPoli) params.set('poli', jmSelectedPoli);
        params.set('tanggal', tanggal);
        if (jmSelectedSesi) {
            params.set('sesi_mulai', jmSelectedSesi.mulai);
            params.set('sesi_selesai', jmSelectedSesi.selesai);
        }
        window.location.href = 'pasien/daftar.php?' + params.toString();
    }

    function scrollStaffList(direction) {
        const container = document.getElementById('staffListContainer');
        if (container) {
            container.scrollBy({ left: 300 * direction, behavior: 'smooth' });
        }
    }

    function slideReview(direction) {
        const container = document.getElementById('gridUlasanContainer');
        if (container) {
            const amount = container.clientWidth * 0.75;
            container.scrollBy({ left: amount * direction, behavior: 'smooth' });
        }
    }

    function handleBeriReviewKlik() {
        const isLoggedIn = <?= $isPasienLoggedIn ? 'true' : 'false' ?>;
        if (!isLoggedIn) {
            bukaModalReviewLoginPrompt();
            return;
        }
        bukaModalTulisUlasan();
    }

    function bukaModalReviewLoginPrompt() {
        const modal = document.getElementById('modalReviewLoginPrompt');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function tutupModalReviewLoginPrompt() {
        const modal = document.getElementById('modalReviewLoginPrompt');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open_review') === '1' || urlParams.get('redirect') === 'review') {
            const isLoggedIn = <?= $isPasienLoggedIn ? 'true' : 'false' ?>;
            if (isLoggedIn) {
                setTimeout(function() {
                    bukaModalTulisUlasan();
                    const revSection = document.getElementById('google-review');
                    if (revSection) revSection.scrollIntoView({ behavior: 'smooth' });
                }, 300);
            }
        }
    });

    function bukaModalTulisUlasan() {
        const modal = document.getElementById('modalUlasanWeb');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function tutupModalTulisUlasan() {
        const modal = document.getElementById('modalUlasanWeb');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    function pilihStar(count) {
        const hidden = document.getElementById('inputRatingValue');
        const label = document.getElementById('ratingTextLabel');
        const notice = document.getElementById('ratingNoticeInfo');
        if (hidden) hidden.value = count;
        const texts = {
            1: '1 Bintang (Kurang)',
            2: '2 Bintang (Cukup)',
            3: '3 Bintang (Baik)',
            4: '4 Bintang (Sangat Baik)',
            5: '5 Bintang (Sangat Memuaskan)'
        };
        if (label) label.textContent = texts[count] || `${count} Bintang`;
        for (let i = 1; i <= 5; i++) {
            const el = document.querySelector(`#starRatingWrap span[data-star="${i}"]`);
            if (el) el.style.opacity = (i <= count) ? '1' : '0.25';
        }
        if (notice) {
            if (count < 5) {
                notice.style.display = 'block';
                notice.innerHTML = 'ℹ️ <em>Catatan:</em> Penilaian di bawah bintang 5 akan diverifikasi oleh Admin terlebih dahulu untuk evaluasi dan peningkatan mutu pelayanan klinik.';
            } else {
                notice.style.display = 'none';
            }
        }
    }

    async function kirimUlasanWeb(event) {
        event.preventDefault();
        const btn = document.getElementById('btnSubmitUlasanWeb');
        const nama = document.getElementById('inputUlasanNama').value.trim();
        const rating = document.getElementById('inputRatingValue').value;
        const layanan = document.getElementById('selectUlasanLayanan').value;
        const ulasan = document.getElementById('inputUlasanTeks').value.trim();

        if (!ulasan) {
            alert('Mohon tuliskan isi ulasan Anda.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Mengirim ulasan...';

        try {
            const res = await fetch('pasien/simpan_ulasan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nama, rating, layanan, ulasan })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message || 'Terima kasih atas ulasan Anda!');
                tutupModalTulisUlasan();
                document.getElementById('inputUlasanTeks').value = '';

                // Prepend kartu ulasan baru ke grid secara realtime HANYA jika otomatis disetujui (bintang 5)
                const rev = data.review;
                const container = document.getElementById('gridUlasanContainer');
                if (data.is_approved && container && rev) {
                    const initial = (rev.nama || 'P').substring(0, 2).toUpperCase();
                    const stars = '⭐'.repeat(Number(rev.rating) || 5);
                    const card = document.createElement('div');
                    card.className = 'review-card-item';
                    card.style.cssText = 'flex:0 0 350px;width:350px;min-width:350px;scroll-snap-align:start;background:#fff;border-radius:20px;padding:22px;border:2px solid #6A22A7;box-shadow:0 10px 28px rgba(106,34,167,0.18);display:flex;flex-direction:column;justify-content:space-between;transition:transform .2s;';
                    card.innerHTML = `
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg, #10B981 0%, #059669 100%);color:#fff;font-weight:800;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        ${initial}
                                    </div>
                                    <div>
                                        <strong style="color:#2B1B48;font-size:14.5px;display:block;">${rev.nama}</strong>
                                        <span style="font-size:11px;color:#10B981;font-weight:700;">Baru saja • Pasien Terverifikasi</span>
                                    </div>
                                </div>
                                <span style="font-size:11px;color:var(--muted);white-space:nowrap;">Hari ini</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <span style="font-size:13px;letter-spacing:1px;">${stars}</span>
                                <span style="background:#F4EDFB;color:#6A22A7;font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:6px;">
                                    ${rev.layanan}
                                </span>
                            </div>
                            <p style="color:#4B3F5B;font-size:13px;line-height:1.65;font-style:italic;margin-bottom:0;">
                                &ldquo;${rev.ulasan}&rdquo;
                            </p>
                        </div>
                        <div style="margin-top:14px;padding-top:10px;border-top:1px solid #F4EEFB;display:flex;justify-content:space-between;align-items:center;font-size:11px;color:#8F82A2;">
                            <span>🌐 Dikirim dari Web</span>
                            <span style="font-weight:700;color:#15803D;">✓ Terverifikasi</span>
                        </div>
                    `;
                    container.prepend(card);
                    container.scrollTo({ left: 0, behavior: 'smooth' });
                }

                if (data.poin_didapat && data.new_poin) {
                    const ptEls = document.querySelectorAll('.hanania-profile-points, .reward-points-number, .hero-reward-points');
                    ptEls.forEach(el => {
                        el.innerHTML = `${data.new_poin} <span>Poin</span>`;
                    });
                }
            } else {
                alert(data.message || 'Gagal mengirim ulasan.');
            }
        } catch(err) {
            console.error(err);
            alert('Terjadi kesalahan saat mengirim ulasan. Silakan coba lagi.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kirim Ulasan Sekarang ✨';
        }
    }

    // Deteksi Rasio Foto Banner (Landscape agar tidak terpotong di Mobile)
    function checkHeroLandscape(img) {
        if (!img) return;
        function apply() {
            if (img.naturalWidth && img.naturalHeight) {
                const isLand = (img.naturalWidth / img.naturalHeight) >= 1.15;
                img.classList.toggle('is-landscape', isLand);
            }
        }
        if (img.complete) {
            apply();
        } else {
            img.addEventListener('load', apply);
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.hero-slide').forEach(checkHeroLandscape);
    });

    // Rotasi Otomatis Hero Slideshow
    (function() {
        const slideItems = document.querySelectorAll('.hero-slideshow .hero-slide-item');
        if (slideItems.length > 1) {
            let current = 0;
            setInterval(function() {
                slideItems[current].classList.remove('active');
                current = (current + 1) % slideItems.length;
                slideItems[current].classList.add('active');
            }, 5000);
        } else {
            const legacySlides = document.querySelectorAll('.hero-slideshow .hero-slide');
            if (legacySlides.length > 1) {
                let current = 0;
                setInterval(function() {
                    legacySlides[current].classList.remove('active');
                    current = (current + 1) % legacySlides.length;
                    legacySlides[current].classList.add('active');
                }, 5000);
            }
        }
    })();

</script>


</body>

</html>