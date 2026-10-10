<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (stripos($scriptName, '/Klinik-Pratama-Rumah-Hanania') !== false) {
    $base = '/Klinik-Pratama-Rumah-Hanania';
} else {
    $base = '';
}

if ((!isset($conn) || !($conn instanceof mysqli)) && file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
}

$isAdmin = (
    !empty($_SESSION['admin_logged_in'])
    || isset($_SESSION['admin_id'])
    || (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin')
    || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
    || isset($_SESSION['admin_username'])
);
$adminName = $_SESSION['admin_username'] ?? 'Admin';

$isPatient = (
    (isset($_SESSION['id_user']) && (($_SESSION['role'] ?? '') === 'pasien'))
    || (isset($_SESSION['user']['id_user']) && (($_SESSION['user']['role'] ?? '') === 'pasien'))
);

$name = $_SESSION['user']['nama_lengkap'] ?? $_SESSION['user']['nama'] ?? $_SESSION['nama_lengkap'] ?? 'Pasien';
$photo = $_SESSION['user']['foto'] ?? $_SESSION['user']['foto_profil'] ?? $_SESSION['foto'] ?? '';
$points = (int)($_SESSION['user']['poin'] ?? $_SESSION['user']['points'] ?? $_SESSION['user']['reward_poin'] ?? $_SESSION['poin_reward'] ?? 0);
$userId = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);

$notifList = [];
if ($isPatient && $userId > 0 && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare('SELECT nama_lengkap, id_pasien, foto, reward_poin FROM pasien WHERE id_user = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        if ($patient) {
            $name = $patient['nama_lengkap'] ?? $name;
            $_SESSION['nama_lengkap'] = $name;
            $_SESSION['id_pasien'] = (int)($patient['id_pasien'] ?? 0);
            if (!empty($patient['foto'])) {
                $rawPhoto = $patient['foto'];
                if (strpos($rawPhoto, 'http') === 0) {
                    $photo = $rawPhoto;
                } else {
                    $photo = $base . '/' . ltrim($rawPhoto, '/');
                }
            }
            if (isset($patient['reward_poin'])) {
                $points = (int)$patient['reward_poin'];
            }
        }
        $stmt->close();
    }

    // Fetch active notifications/reminders for patient
    $qNotif = $conn->prepare("
        SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.jam_kunjungan, p.status_pendaftaran,
               a.nomor_antrean, a.estimasi_waktu,
               COALESCE(d.nama_dokter, b.nama_bidan, 'Tenaga Medis') AS nama_nakes
        FROM pendaftaran p
        INNER JOIN pasien pas ON p.id_pasien = pas.id_pasien
        LEFT JOIN antrean a ON p.id_pendaftaran = a.id_pendaftaran
        LEFT JOIN dokter d ON p.id_dokter = d.id_dokter
        LEFT JOIN bidan b ON p.id_bidan = b.id_bidan
        WHERE pas.id_user = ? AND p.status_pendaftaran IN ('menunggu', 'terdaftar')
        ORDER BY p.tanggal_kunjungan ASC, p.id_pendaftaran DESC
        LIMIT 5
    ");
    if ($qNotif) {
        $qNotif->bind_param('i', $userId);
        $qNotif->execute();
        $resNotif = $qNotif->get_result();
        while ($row = $resNotif->fetch_assoc()) {
            $timeStr = !empty($row['estimasi_waktu']) ? $row['estimasi_waktu'] : ($row['jam_kunjungan'] ?? '08:00:00');
            $tsPelayanan = strtotime($row['tanggal_kunjungan'] . ' ' . $timeStr);
            if ($tsPelayanan === false) {
                $tsPelayanan = strtotime($row['tanggal_kunjungan'] . ' 08:00:00');
            }
            $row['waktu_hadir'] = date('H:i', $tsPelayanan - (15 * 60));
            $row['waktu_pelayanan'] = date('H:i', $tsPelayanan);
            $notifList[] = $row;
        }
        $qNotif->close();
    }
}

// Ambil data navbar dinamis
if (!function_exists('getSiteKontenNavbar')) {
    function getSiteKontenNavbar($key, $default = '') {
        global $conn;
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            if (isset($conn) && $conn instanceof mysqli) {
                $q = @mysqli_query($conn, "SELECT kunci, nilai FROM pengaturan_konten");
                if ($q) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $cache[$r['kunci']] = $r['nilai'];
                    }
                }
            }
        }
        return isset($cache[$key]) && trim((string)$cache[$key]) !== '' ? $cache[$key] : $default;
    }
}
$navBrandNama = getSiteKontenNavbar('navbar_brand_nama', 'Klinik Pratama Rumah Hanania');
$navBrandSlogan = getSiteKontenNavbar('navbar_brand_slogan', 'Melayani dengan Hati');
$navMenuBeranda = getSiteKontenNavbar('navbar_menu_beranda', 'Beranda');
$navMenuLayanan = getSiteKontenNavbar('navbar_menu_layanan', 'Layanan');
$navMenuJadwal = getSiteKontenNavbar('navbar_menu_jadwal', 'Jadwal Praktik');
$navMenuDokter = getSiteKontenNavbar('navbar_menu_dokter', 'Dokter & Bidan');
$navMenuTentang = getSiteKontenNavbar('navbar_menu_tentang', 'Tentang');
$navMenuKontak = getSiteKontenNavbar('navbar_menu_kontak', 'Kontak & Alamat');
$navBtnLogin = getSiteKontenNavbar('navbar_btn_login', 'Login');

$navSubProfil = getSiteKontenNavbar('navbar_sub_profil', 'Profil Klinik');
$navSubSejarah = getSiteKontenNavbar('navbar_sub_sejarah', 'Sejarah Klinik');
$navSubVisiMisi = getSiteKontenNavbar('navbar_sub_visi_misi', 'Visi & Misi');
$navSubFasilitas = getSiteKontenNavbar('navbar_sub_fasilitas', 'Fasilitas Klinik');
$navSubStruktur = getSiteKontenNavbar('navbar_sub_struktur', 'Struktur Organisasi');
?>
<style>
:root{
    --hanania-purple:#8A4BC8;
    --hanania-purple-dark:#4A1278;
    --hanania-button:#6A22A7;
    --hanania-soft:#F3EDFA;
    --hanania-border:#E4D8F1;
}
.hanania-header{position:sticky;top:0;z-index:1000;background:rgba(247,243,252,.96);border-bottom:1px solid var(--hanania-border);backdrop-filter:blur(12px);box-shadow:0 4px 20px rgba(106,34,167,.04)}
.hanania-navbar{max-width:1220px;margin:auto;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px;position:relative}

/* Desktop Brand */
.hanania-brand{display:flex;align-items:center;gap:12px;flex-shrink:0;text-decoration:none}
.hanania-brand-logo{width:46px;height:46px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border-radius:14px;background:#fff;border:1px solid var(--hanania-border);box-shadow:0 2px 8px rgba(106,34,167,.08)}
.hanania-brand-logo img{width:100%;height:100%;object-fit:cover;display:block}
.hanania-brand-text{display:flex;flex-direction:column}
.hanania-brand-name{color:#2B1B48;font-size:15.5px;line-height:1.25;font-weight:800;letter-spacing:-.2px}

/* Desktop Nav Links */
.hanania-nav{display:flex;align-items:center;gap:22px}
.hanania-nav a{color:#4B3E5B;font-size:13.5px;font-weight:700;transition:all .2s;text-decoration:none;white-space:nowrap}
.hanania-nav a:hover{color:var(--hanania-button)}
.hanania-about,.hanania-profile,.hanania-notif-container{position:relative}
.hanania-about-toggle{cursor:pointer;color:#4B3E5B;font-size:13.5px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center;gap:4px;transition:.2s}
.hanania-about-toggle:hover{color:var(--hanania-button)}
.hanania-desktop-actions{display:flex;align-items:center;gap:14px}
.hanania-mobile-bar{display:none}
.hanania-mobile-only-login{display:none}
.hanania-mobile-drawer{display:none}
@media(max-width:960px){
    .hanania-mobile-drawer{display:flex;flex-direction:column;gap:6px;margin-top:10px;padding-top:12px;border-top:1px solid #EDE4F7}
    .hanania-mobile-drawer a{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;font-size:13.5px;font-weight:700;text-decoration:none}
}

/* Popover Menus (About, Profile, Notif) */
.hanania-about-menu,.hanania-profile-menu,.hanania-notif-menu{display:none;position:absolute;top:calc(100% + 10px);padding:8px;background:#fff;border:1px solid var(--hanania-border);border-radius:16px;box-shadow:0 16px 36px rgba(106,34,167,.12);z-index:1200}
.hanania-about-menu{left:0;width:205px}
.hanania-profile-menu{right:0;width:215px}
.hanania-notif-menu{right:0;width:330px;padding:12px}
.hanania-about-menu.active,.hanania-profile-menu.active,.hanania-notif-menu.active{display:block;animation:hananiaFadeDown .2s ease}
@keyframes hananiaFadeDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.hanania-about-menu a,.hanania-profile-menu a{display:flex;align-items:center;padding:9px 12px;border-radius:10px;color:#4B3E5B;font-size:13px;font-weight:600;transition:.15s;text-decoration:none}
.hanania-about-menu a:hover,.hanania-profile-menu a:hover{background:var(--hanania-soft);color:var(--hanania-button)}

.hanania-admin-link{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#F3EDFA;color:var(--hanania-button)!important;border:1.5px solid #D8CAEC;border-radius:9999px;font-size:12.5px;font-weight:800;transition:all .2s;text-decoration:none;box-shadow:0 2px 8px rgba(106,34,167,.08)}
.hanania-admin-link:hover{background:var(--hanania-button);color:#fff!important;border-color:var(--hanania-button);transform:translateY(-1px);box-shadow:0 4px 14px rgba(106,34,167,.25)}

/* Login Button - Pill rounded */
.hanania-login{padding:8px 22px;background:var(--hanania-button);color:#fff!important;border-radius:9999px;font-size:13.5px;font-weight:700;box-shadow:0 4px 14px rgba(106,34,167,.25);transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.hanania-login:hover{background:var(--hanania-purple-dark);transform:translateY(-1px);box-shadow:0 6px 18px rgba(106,34,167,.35)}

/* Notif Button */
.hanania-notif-btn{position:relative;width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--hanania-border);border-radius:10px;background:#fff;color:var(--hanania-button);font-size:16px;cursor:pointer;transition:.2s}
.hanania-notif-btn:hover{background:var(--hanania-soft)}
.hanania-notif-badge{position:absolute;top:-4px;right:-4px;background:#E53E3E;color:#fff;font-size:10px;font-weight:800;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 2px 5px rgba(229,62,62,.4)}

/* Profile Button */
.hanania-profile-button{display:flex;align-items:center;gap:8px;border:1px solid var(--hanania-border);background:#fff;color:var(--hanania-button);padding:4px 10px 4px 5px;border-radius:12px;cursor:pointer;font-family:inherit;transition:all .2s}
.hanania-profile-button:hover{border-color:#C8B5DF;box-shadow:0 4px 12px rgba(79,50,147,.08)}
.hanania-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;display:block}
.hanania-avatar-default{display:inline-flex;align-items:center;justify-content:center;background:var(--hanania-soft);font-size:15px}
.hanania-avatar-admin{display:inline-flex;align-items:center;justify-content:center;background:#F0E7FA;font-size:14px}
.hanania-profile-name{max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;font-weight:700}
.hanania-profile-points{font-size:11px;font-weight:800;color:var(--hanania-button);background:var(--hanania-soft);padding:3px 7px;border-radius:8px;white-space:nowrap}
.hanania-admin-badge{font-size:10px;font-weight:800;color:#fff;background:var(--hanania-button);padding:2px 6px;border-radius:6px;letter-spacing:.3px;text-transform:uppercase}
.hanania-profile-arrow{font-size:12px;color:#8E7E9E}

/* Popover Notifikasi */
.hanania-notif-header{display:flex;align-items:center;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid var(--hanania-border);margin-bottom:8px}
.hanania-notif-header h5{margin:0;font-size:13px;font-weight:700;color:var(--hanania-button)}
.hanania-notif-header span{font-size:11px;color:#8E7E9E}
.hanania-notif-body{max-height:270px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
.hanania-notif-item{padding:10px;border-radius:10px;background:#FAFAFC;border:1px solid #F0ECF5;font-size:12px;line-height:1.4;transition:.15s}
.hanania-notif-item:hover{background:var(--hanania-soft)}
.hanania-notif-item-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
.hanania-notif-queue{font-weight:800;color:var(--hanania-button);background:#fff;padding:2px 6px;border-radius:6px;border:1px solid var(--hanania-border)}
.hanania-notif-status{font-size:10px;font-weight:700;padding:2px 6px;border-radius:6px;text-transform:capitalize}
.hanania-notif-status.menunggu{background:#FEF3C7;color:#92400E}
.hanania-notif-status.diproses{background:#DBEAFE;color:#1E40AF}
.hanania-notif-status.diterima{background:#D1FAE5;color:#065F46}
.hanania-notif-footer{margin-top:10px;padding-top:8px;border-top:1px solid var(--hanania-border);text-align:center}
.hanania-notif-footer a{font-size:12px;font-weight:700;color:var(--hanania-button);text-decoration:none}
.hanania-notif-footer a:hover{text-decoration:underline}

/* =====================================================
   MOBILE NAVBAR (Google Drive Search-Bar Style)
===================================================== */
@media(max-width:960px){
    .hanania-navbar{padding:10px 14px}
    .hanania-brand-desktop{display:none}
    .hanania-desktop-actions{display:none}

    /* Google Drive App Bar on Mobile */
    .hanania-mobile-bar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        width:100%;
    }

    /* Left Menu Button (Hamburger) */
    .hanania-mobile-toggle{
        width:40px;
        height:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:#FAF7FD;
        border:1px solid var(--hanania-border);
        border-radius:50%;
        color:var(--hanania-button);
        cursor:pointer;
        flex-shrink:0;
        transition:all .2s;
        padding:0;
    }
    .hanania-mobile-toggle:hover,.hanania-mobile-toggle:active{
        background:var(--hanania-soft);
        transform:scale(0.96);
    }

    /* Center Pill (Google Drive Search Style) */
    .hanania-mobile-pill{
        flex:1;
        min-width:0;
        margin:0 4px;
        padding:7px 14px;
        display:flex;
        align-items:center;
        gap:8px;
        background:#F6F0FB;
        border:1px solid #E4D7F0;
        border-radius:999px;
        text-decoration:none;
        box-shadow:inset 0 1px 3px rgba(79,50,147,.05);
        transition:all .2s;
    }
    .hanania-mobile-pill:active{
        background:#ECE1F7;
    }
    .hanania-pill-badge{
        width:24px;
        height:24px;
        border-radius:50%;
        overflow:hidden;
        background:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
        font-size:14px;
        box-shadow:0 1px 4px rgba(79,50,147,.1);
    }
    .hanania-pill-badge img{width:100%;height:100%;object-fit:cover;display:block}
    .hanania-pill-title{
        color:var(--hanania-button);
        font-size:12.5px;
        font-weight:700;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        letter-spacing:-.1px;
    }

    /* Right Action Group: Notif Bell + Profile Avatar */
    .hanania-mobile-actions{
        display:flex;
        align-items:center;
        gap:8px;
        flex-shrink:0;
    }
    .hanania-mobile-actions .hanania-notif-btn{
        width:38px;
        height:38px;
        border-radius:50%;
        background:#FAF7FD;
    }

    /* Google Drive Avatar Button with Accent Glow Ring */
    .hanania-mobile-avatar-btn{
        background:none;
        border:none;
        padding:0;
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        border-radius:50%;
        text-decoration:none;
        flex-shrink:0;
    }
    .hanania-mobile-avatar-btn .hanania-avatar{
        width:36px;
        height:36px;
        border-radius:50%;
        box-shadow:0 0 0 2px #fff, 0 0 0 4px #7650A2;
        transition:transform .2s;
    }
    .hanania-mobile-avatar-btn:hover .hanania-avatar,
    .hanania-mobile-avatar-btn:active .hanania-avatar{
        transform:scale(1.05);
    }
    .hanania-guest-avatar .hanania-avatar{
        box-shadow:0 0 0 2px #fff, 0 0 0 3.5px #8E6BBE;
    }

    /* Navigation Menu Dropdown List */
    .hanania-nav{
        display:none;
        position:absolute;
        left:12px;
        right:12px;
        top:calc(100% + 8px);
        padding:16px;
        flex-direction:column;
        align-items:stretch;
        gap:4px;
        background:#fff;
        border:1px solid var(--hanania-border);
        border-radius:18px;
        box-shadow:0 18px 40px rgba(79,50,147,.18);
        z-index:1200;
        max-height: calc(100vh - 85px);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .hanania-nav::-webkit-scrollbar{
        width: 5px;
    }
    .hanania-nav::-webkit-scrollbar-thumb{
        background: #D8CAEC;
        border-radius: 4px;
    }
    .hanania-nav.active{
        display:flex;
        animation:hananiaFadeDown .2s ease;
    }
    .hanania-nav>a,.hanania-about-toggle{
        display:flex;
        align-items:center;
        padding:11px 14px;
        font-size:14px;
        font-weight:700;
        color:#3E354A;
        border-radius:10px;
    }
    .hanania-nav>a:hover,.hanania-about-toggle:hover{
        background:var(--hanania-soft);
        color:var(--hanania-button);
    }
    .hanania-about-menu{
        position:static;
        width:auto;
        margin:4px 0 8px 12px;
        box-shadow:none;
        border-left:2px solid var(--hanania-soft);
    }
    .hanania-mobile-only-login{
        display:block;
        margin-top:8px;
        padding-top:10px;
        border-top:1px solid var(--hanania-border);
    }

    /* Popovers positioning on mobile */
    .hanania-notif-menu{
        position:absolute;
        left:12px;
        right:12px;
        top:calc(100% + 8px);
        width:auto;
        max-width:360px;
        margin-left:auto;
        box-shadow:0 18px 40px rgba(79,50,147,.2);
    }
    .hanania-profile-menu{
        position:absolute;
        right:12px;
        top:calc(100% + 8px);
        width:220px;
        box-shadow:0 18px 40px rgba(79,50,147,.2);
    }
}
</style>

<header class="hanania-header">
    <div class="hanania-navbar">
        
        <!-- DESKTOP BRAND (Layar Lebar) -->
        <a class="hanania-brand hanania-brand-desktop" href="<?= $base ?>/index.php#beranda">
            <span class="hanania-brand-logo">
                <img src="<?= $base ?>/assets/img/logo.jpg" alt="Logo <?= htmlspecialchars($navBrandNama) ?>" onerror="this.onerror=null;this.parentElement.innerHTML='🩺';">
            </span>
            <span class="hanania-brand-text">
                <span class="hanania-brand-name"><?= htmlspecialchars($navBrandNama) ?></span>
            </span>
        </a>

        <!-- MOBILE BAR (Tampilan Google Drive Style) -->
        <div class="hanania-mobile-bar">
            <!-- Left: Hamburger Menu Button -->
            <button type="button" class="hanania-mobile-toggle" onclick="toggleHananiaMobileNav()" aria-label="Buka menu navigasi" aria-expanded="false" title="Menu Navigasi">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <!-- Center: Pill Bar bertuliskan Klinik Pratama Rumah Hanania -->
            <a class="hanania-mobile-pill" href="<?= $base ?>/index.php#beranda" title="Klinik Pratama Rumah Hanania">
                <span class="hanania-pill-badge">
                    <img src="<?= $base ?>/assets/img/logo.jpg" alt="Logo" onerror="this.onerror=null;this.parentElement.innerHTML='🩺';">
                </span>
                <span class="hanania-pill-title">Klinik Pratama Rumah Hanania</span>
            </a>

            <!-- Right: Notifikasi Lonceng & Avatar Profil / Login Pasien -->
            <div class="hanania-mobile-actions">
                <?php if ($isPatient): ?>
                    <button type="button" class="hanania-notif-btn" onclick="toggleHananiaNotif()" aria-label="Notifikasi Kunjungan" title="Notifikasi Jadwal &amp; Antrean">
                        <span style="font-size:18px;line-height:1;">🔔</span>
                        <?php if (!empty($notifList)): ?>
                            <span class="hanania-notif-badge"><?= count($notifList) ?></span>
                        <?php endif; ?>
                    </button>

                    <button type="button" class="hanania-mobile-avatar-btn" onclick="toggleHananiaProfile()" aria-label="Menu Profil Pasien" title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($photo): ?>
                            <img class="hanania-avatar" src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="Foto Profil">
                        <?php else: ?>
                            <span class="hanania-avatar hanania-avatar-default">👤</span>
                        <?php endif; ?>
                    </button>
                <?php else: ?>
                    <a href="<?= $base ?>/login.php" class="hanania-mobile-avatar-btn hanania-guest-avatar" aria-label="Masuk ke Akun Pasien" title="Masuk / Login Pasien">
                        <span class="hanania-avatar hanania-avatar-default">👤</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- POPOVER NOTIFIKASI (Desktop & Mobile) -->
        <div id="hananiaNotifMenu" class="hanania-notif-menu">
            <div class="hanania-notif-header">
                <h5>Pengingat Kunjungan</h5>
                <span><?= count($notifList) ?> aktif</span>
            </div>
            <div class="hanania-notif-body">
                <?php if (!empty($notifList)): ?>
                    <?php foreach ($notifList as $nt): ?>
                        <div class="hanania-notif-item">
                            <div class="hanania-notif-item-top">
                                <span class="hanania-notif-queue">Antrean <?= htmlspecialchars(!empty($nt['nomor_antrean']) ? str_pad($nt['nomor_antrean'], 3, '0', STR_PAD_LEFT) : '003', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="hanania-notif-status <?= htmlspecialchars($nt['status_pendaftaran'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($nt['status_pendaftaran']), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div style="font-weight:700;color:#2D253B;font-size:13px;"><?= htmlspecialchars($nt['nama_nakes'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="color:#6A5C7E;font-size:11.5px;margin-top:4px;line-height:1.5;">
                                📅 <?= date('d M Y', strtotime($nt['tanggal_kunjungan'])) ?> &bull; Sesi: <strong><?= $nt['waktu_pelayanan'] ?> WIB</strong>
                                <br>
                                ⏰ Hadir 15 menit sebelumnya: <strong style="color:var(--hanania-button);"><?= $nt['waktu_hadir'] ?> WIB</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:16px 8px;color:#8E7E9E;font-size:12px;">
                        <span style="font-size:24px;display:block;margin-bottom:6px;">🔔</span>
                        Belum ada jadwal kunjungan aktif saat ini.
                    </div>
                <?php endif; ?>
            </div>
            <div class="hanania-notif-footer">
                <a href="<?= $base ?>/pasien/antrian.php">Buka Status Antrean &rarr;</a>
            </div>
        </div>

        <!-- POPOVER PROFIL PASIEN (Desktop & Mobile) -->
        <?php if ($isPatient): ?>
            <div id="hananiaProfileMenu" class="hanania-profile-menu">
                <div style="padding:8px 12px;border-bottom:1px solid var(--hanania-border);margin-bottom:6px;">
                    <div style="font-size:13px;font-weight:800;color:var(--hanania-button);"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:11px;color:#8E7E9E;">⭐ <?= number_format($points, 0, ',', '.') ?> Poin Reward</div>
                </div>
                <a href="<?= $base ?>/pasien/profil.php">👤&nbsp; Profil Pasien</a>
                <a href="<?= $base ?>/pasien/antrian.php">📋&nbsp; Status Antrean</a>
                <a href="<?= $base ?>/pasien/reward.php">⭐&nbsp; Poin &amp; Reward</a>
                <div style="border-top:1px dashed var(--hanania-border);margin:6px 0;padding-top:4px;"></div>
                <a href="<?= $base ?>/index.php?logout=1" style="color:#B44A4A!important">↪&nbsp; Keluar (Logout)</a>
            </div>
        <?php endif; ?>

        <!-- NAV LINKS LIST -->
        <nav class="hanania-nav" id="hananiaNav">
            <a href="<?= $base ?>/index.php#beranda"><?= htmlspecialchars($navMenuBeranda) ?></a>
            <a href="<?= $base ?>/layanan/index.php"><?= htmlspecialchars($navMenuLayanan) ?></a>
            <a href="<?= $base ?>/dokter/jadwal.php"><?= htmlspecialchars($navMenuJadwal) ?></a>
            <a href="<?= $base ?>/dokter/index.php"><?= htmlspecialchars($navMenuDokter) ?></a>

            <div class="hanania-about">
                <span class="hanania-about-toggle" onclick="toggleHananiaAbout()"><?= htmlspecialchars($navMenuTentang) ?> <span>▾</span></span>
                <div id="hananiaAboutMenu" class="hanania-about-menu">
                    <a href="<?= $base ?>/tentang/profil%20klinik.php"><?= htmlspecialchars($navSubProfil) ?></a>
                    <a href="<?= $base ?>/tentang/sejarah%20klinik.php"><?= htmlspecialchars($navSubSejarah) ?></a>
                    <a href="<?= $base ?>/tentang/visi_misi.php"><?= htmlspecialchars($navSubVisiMisi) ?></a>
                    <a href="<?= $base ?>/tentang/fasilitas%20klinik.php"><?= htmlspecialchars($navSubFasilitas) ?></a>
                    <a href="<?= $base ?>/tentang/struktur_organisasi.php"><?= htmlspecialchars($navSubStruktur) ?></a>
                </div>
            </div>

            <a href="#kontak" onclick="const el=document.getElementById('kontak');if(el){event.preventDefault();el.scrollIntoView({behavior:'smooth'});}else{window.location.href='<?= $base ?>/index.php#kontak';}"><?= htmlspecialchars($navMenuKontak) ?></a>

            <!-- DESKTOP ACTIONS ONLY -->
            <div class="hanania-desktop-actions">
                <?php if ($isPatient): ?>
                    <div class="hanania-notif-container">
                        <button type="button" class="hanania-notif-btn" onclick="toggleHananiaNotif()" aria-label="Notifikasi Kunjungan" title="Notifikasi Jadwal &amp; Antrean">
                            <span style="font-size:18px;">🔔</span>
                            <?php if (!empty($notifList)): ?>
                                <span class="hanania-notif-badge"><?= count($notifList) ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <div class="hanania-profile">
                        <button type="button" class="hanania-profile-button" onclick="toggleHananiaProfile()" aria-expanded="false">
                            <?php if ($photo): ?>
                                <img class="hanania-avatar" src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="Foto Profil">
                            <?php else: ?>
                                <span class="hanania-avatar hanania-avatar-default">👤</span>
                            <?php endif; ?>
                            <span class="hanania-profile-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="hanania-profile-points">⭐ <?= number_format($points, 0, ',', '.') ?></span>
                            <span class="hanania-profile-arrow">▾</span>
                        </button>
                    </div>
                <?php else: ?>
                    <a href="<?= $base ?>/login.php" class="hanania-login"><?= htmlspecialchars($navBtnLogin) ?></a>
                <?php endif; ?>
            </div>

            <!-- Mobile Drawer Links (Hidden on Desktop) -->
            <div class="hanania-mobile-drawer">
                <?php if ($isPatient): ?>
                    <a href="<?= $base ?>/pasien/profil.php" style="background:#F6F0FB;color:#6A22A7;">
                        👤 Profil Pasien
                    </a>
                    <a href="<?= $base ?>/pasien/reward.php" style="background:#F6F0FB;color:#6A22A7;">
                        ⭐ Poin &amp; Reward
                    </a>
                    <a href="<?= $base ?>/index.php?logout=1" style="color:#B44A4A!important;background:#FFF5F5;justify-content:center;margin-top:6px;">
                        ↪ Keluar Akun Pasien
                    </a>
                <?php else: ?>
                    <a href="<?= $base ?>/login.php" class="hanania-login" style="display:flex;justify-content:center;text-align:center;margin-top:8px;">
                        🔑 Masuk ke Akun Pasien
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<script>
function toggleHananiaMobileNav(){
    const n = document.getElementById('hananiaNav');
    const b = document.querySelector('.hanania-mobile-toggle');
    const notifM = document.getElementById('hananiaNotifMenu');
    const profM = document.getElementById('hananiaProfileMenu');
    if (notifM) notifM.classList.remove('active');
    if (profM) profM.classList.remove('active');
    if (!n) return;
    n.classList.toggle('active');
    if (b) b.setAttribute('aria-expanded', n.classList.contains('active') ? 'true' : 'false');
}

function toggleHananiaAbout(){
    const m = document.getElementById('hananiaAboutMenu');
    if (m) m.classList.toggle('active');
}

function toggleHananiaProfile(){
    const m = document.getElementById('hananiaProfileMenu');
    const n = document.getElementById('hananiaNav');
    const notifM = document.getElementById('hananiaNotifMenu');
    if (n) n.classList.remove('active');
    if (notifM) notifM.classList.remove('active');
    if (!m) return;
    m.classList.toggle('active');
}

function toggleHananiaNotif(){
    const m = document.getElementById('hananiaNotifMenu');
    const n = document.getElementById('hananiaNav');
    const profM = document.getElementById('hananiaProfileMenu');
    if (n) n.classList.remove('active');
    if (profM) profM.classList.remove('active');
    if (!m) return;
    m.classList.toggle('active');
}

document.addEventListener('click', function(e){
    const a = document.querySelector('.hanania-about');
    const am = document.getElementById('hananiaAboutMenu');
    if(a && am && !a.contains(e.target)) am.classList.remove('active');

    const nav = document.getElementById('hananiaNav');
    const toggleBtn = document.querySelector('.hanania-mobile-toggle');
    if (nav && toggleBtn && !nav.contains(e.target) && !toggleBtn.contains(e.target)) {
        nav.classList.remove('active');
    }

    const profBtn = document.querySelector('.hanania-profile-button');
    const profMobileBtn = document.querySelector('.hanania-mobile-avatar-btn');
    const profMenu = document.getElementById('hananiaProfileMenu');
    if (profMenu && (!profBtn || !profBtn.contains(e.target)) && (!profMobileBtn || !profMobileBtn.contains(e.target)) && !profMenu.contains(e.target)) {
        profMenu.classList.remove('active');
    }

    const notifBtn = document.querySelectorAll('.hanania-notif-btn');
    const notifM = document.getElementById('hananiaNotifMenu');
    let clickedNotifBtn = false;
    notifBtn.forEach(function(btn){ if(btn.contains(e.target)) clickedNotifBtn = true; });
    if (notifM && !clickedNotifBtn && !notifM.contains(e.target)) {
        notifM.classList.remove('active');
    }
});

<?php if ($isPatient && !empty($notifList)): ?>
window.addEventListener('DOMContentLoaded', function(){
    if (!sessionStorage.getItem('hanania_notif_seen')) {
        setTimeout(function(){
            const m = document.getElementById('hananiaNotifMenu');
            if (m) {
                m.classList.add('active');
                setTimeout(function(){
                    m.classList.remove('active');
                }, 6000);
            }
            sessionStorage.setItem('hanania_notif_seen', '1');
        }, 900);
    }
});
<?php endif; ?>
</script>