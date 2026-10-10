<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

$activeTab = $_GET['tab'] ?? 'pasien';
$searchQuery = trim($_GET['q'] ?? '');

// Current logged in admin identifier
$currentAdminUser = $_SESSION['admin_username'] ?? 'admin';
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);

// ----------------------------------------------------
// POST ACTIONS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ================================================
        // 1. AKUN SAHABAT HANANIA (PASIEN)
        // ================================================
        if ($action === 'tambah_akun_pasien') {
            $activeTab = 'pasien';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $nik = trim($_POST['nik'] ?? '');
            $telepon = trim($_POST['no_telepon'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $tglLahir = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
            $jk = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P'], true) ? $_POST['jenis_kelamin'] : null;

            if ($username === '' || $password === '' || $nama === '') {
                throw new Exception('Username/Email, Password, dan Nama Lengkap wajib diisi.');
            }
            if (strlen($password) < 6) {
                throw new Exception('Password minimal 6 karakter.');
            }

            // Cek apakah username sudah ada di users
            $chkU = $db->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
            if ($chkU) {
                $chkU->bind_param('s', $username);
                $chkU->execute();
                if ($chkU->get_result()->fetch_assoc()) {
                    $chkU->close();
                    throw new Exception("Username / Login ID '{$username}' sudah digunakan.");
                }
                $chkU->close();
            }

            // Simpan akun ke users
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'pasien')");
            if (!$stmtUser) {
                throw new Exception('Gagal menyimpan user: ' . $db->error);
            }
            $stmtUser->bind_param('ss', $username, $hash);
            $stmtUser->execute();
            $newUserId = $stmtUser->insert_id;
            $stmtUser->close();

            // Cek apakah NIK sudah ada di pasien
            $existingPasienId = 0;
            if ($nik !== '') {
                $chkP = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? LIMIT 1");
                if ($chkP) {
                    $chkP->bind_param('s', $nik);
                    $chkP->execute();
                    $rP = $chkP->get_result()->fetch_assoc();
                    if ($rP) {
                        $existingPasienId = (int)$rP['id_pasien'];
                    }
                    $chkP->close();
                }
            }

            if ($existingPasienId > 0) {
                // Tautkan ke data pasien yang sudah ada
                $stmtLink = $db->prepare("UPDATE pasien SET id_user = ?, email = IFNULL(NULLIF(?, ''), email), no_telepon = IFNULL(NULLIF(?, ''), no_telepon) WHERE id_pasien = ?");
                if ($stmtLink) {
                    $stmtLink->bind_param('issi', $newUserId, $email, $telepon, $existingPasienId);
                    $stmtLink->execute();
                    $stmtLink->close();
                }
                $message = "Akun Sahabat Hanania untuk '{$nama}' berhasil dibuat dan ditautkan ke data rekam medis pasien yang ada!";
            } else {
                // Buat data pasien baru
                $stmtP = $db->prepare("INSERT INTO pasien (id_user, nik, nama_lengkap, no_telepon, email, tanggal_lahir, jenis_kelamin, alamat, reward_poin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 50)");
                if ($stmtP) {
                    $stmtP->bind_param('isssssss', $newUserId, $nik, $nama, $telepon, $email, $tglLahir, $jk, $alamat);
                    $stmtP->execute();
                    $stmtP->close();
                }
                $message = "Akun Sahabat Hanania baru untuk '{$nama}' berhasil dibuat dengan bonus 50 Poin!";
            }

        } elseif ($action === 'reset_password_pasien') {
            $activeTab = 'pasien';
            $idUser = (int)($_POST['id_user'] ?? 0);
            $passwordBaru = $_POST['password_baru'] ?? '';

            if ($idUser <= 0 || $passwordBaru === '') {
                throw new Exception('ID Pengguna dan Password Baru wajib diisi.');
            }
            if (strlen($passwordBaru) < 6) {
                throw new Exception('Password baru minimal 6 karakter.');
            }

            $hash = password_hash($passwordBaru, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id_user = ? AND role = 'pasien'");
            if ($stmt) {
                $stmt->bind_param('si', $hash, $idUser);
                $stmt->execute();
                $stmt->close();
                $message = 'Password Akun Sahabat Hanania berhasil direset/diperbarui!';
            } else {
                throw new Exception('Gagal mereset password: ' . $db->error);
            }

        } elseif ($action === 'edit_akun_pasien') {
            $activeTab = 'pasien';
            $idUser = (int)($_POST['id_user'] ?? 0);
            $idPasien = (int)($_POST['id_pasien'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $nik = trim($_POST['nik'] ?? '');
            $telepon = trim($_POST['no_telepon'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $poin = (int)($_POST['reward_poin'] ?? 0);

            if ($idUser <= 0 || $username === '' || $nama === '') {
                throw new Exception('Data akun tidak lengkap.');
            }

            // Cek apakah username dipakai user lain
            $chk = $db->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('si', $username, $idUser);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()) {
                    $chk->close();
                    throw new Exception("Username '{$username}' sudah dipakai oleh akun lain.");
                }
                $chk->close();
            }

            // Update user
            $stmtU = $db->prepare("UPDATE users SET username = ? WHERE id_user = ?");
            if ($stmtU) {
                $stmtU->bind_param('si', $username, $idUser);
                $stmtU->execute();
                $stmtU->close();
            }

            // Update data pasien jika terkait
            if ($idPasien > 0) {
                $stmtP = $db->prepare("UPDATE pasien SET nama_lengkap = ?, nik = ?, no_telepon = ?, email = ?, reward_poin = ? WHERE id_pasien = ?");
                if ($stmtP) {
                    $stmtP->bind_param('ssssii', $nama, $nik, $telepon, $email, $poin, $idPasien);
                    $stmtP->execute();
                    $stmtP->close();
                }
            }

            $message = "Data Akun Sahabat Hanania '{$nama}' berhasil diperbarui!";

        } elseif ($action === 'hapus_akun_pasien') {
            $activeTab = 'pasien';
            $idUser = (int)($_POST['id_user'] ?? 0);
            if ($idUser <= 0) {
                throw new Exception('ID Akun tidak valid.');
            }

            // Lepaskan relasi di tabel pasien agar rekam medis tetap terjaga
            $db->query("UPDATE pasien SET id_user = NULL WHERE id_user = $idUser");
            // Hapus user
            $db->query("DELETE FROM users WHERE id_user = $idUser AND role = 'pasien'");

            $message = 'Akun Sahabat Hanania berhasil dihapus. Catatan rekam medis pasien tetap tersimpan di arsip klinik.';

        // ================================================
        // 2. AKUN ADMINISTRATOR
        // ================================================
        } elseif ($action === 'tambah_admin') {
            $activeTab = 'admin';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $konfirmasi = $_POST['konfirmasi_password'] ?? '';

            if ($username === '' || $password === '') {
                throw new Exception('Username dan Password admin wajib diisi.');
            }
            if (strlen($username) < 3) {
                throw new Exception('Username minimal 3 karakter.');
            }
            if (strlen($password) < 6) {
                throw new Exception('Password admin minimal 6 karakter.');
            }
            if ($password !== $konfirmasi) {
                throw new Exception('Konfirmasi password tidak cocok.');
            }

            $chkA = $db->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1");
            if ($chkA) {
                $chkA->bind_param('s', $username);
                $chkA->execute();
                if ($chkA->get_result()->fetch_assoc()) {
                    $chkA->close();
                    throw new Exception("Username admin '{$username}' sudah terdaftar.");
                }
                $chkA->close();
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            if ($stmt) {
                $stmt->bind_param('ss', $username, $hash);
                $stmt->execute();
                $stmt->close();
                $message = "Akun Administrator baru '{$username}' berhasil ditambahkan!";
            } else {
                throw new Exception('Gagal menambahkan admin: ' . $db->error);
            }

        } elseif ($action === 'ganti_password_admin') {
            $activeTab = 'admin';
            $idUser = (int)($_POST['id_user'] ?? 0);
            $passwordBaru = $_POST['password_baru'] ?? '';
            $konfirmasi = $_POST['konfirmasi_password'] ?? '';

            if ($idUser <= 0 || $passwordBaru === '') {
                throw new Exception('ID Admin dan Password Baru wajib diisi.');
            }
            if (strlen($passwordBaru) < 6) {
                throw new Exception('Password baru minimal 6 karakter.');
            }
            if ($passwordBaru !== $konfirmasi) {
                throw new Exception('Konfirmasi password tidak sesuai.');
            }

            $hash = password_hash($passwordBaru, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id_user = ? AND role = 'admin'");
            if ($stmt) {
                $stmt->bind_param('si', $hash, $idUser);
                $stmt->execute();
                $stmt->close();
                $message = 'Password administrator berhasil diperbarui!';
            } else {
                throw new Exception('Gagal mengubah password admin: ' . $db->error);
            }

        } elseif ($action === 'edit_admin') {
            $activeTab = 'admin';
            $idUser = (int)($_POST['id_user'] ?? 0);
            $usernameBaru = trim($_POST['username'] ?? '');

            if ($idUser <= 0 || $usernameBaru === '') {
                throw new Exception('Username tidak boleh kosong.');
            }

            $chk = $db->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('si', $usernameBaru, $idUser);
                $chk->execute();
                if ($chk->get_result()->fetch_assoc()) {
                    $chk->close();
                    throw new Exception("Username '{$usernameBaru}' sudah dipakai admin lain.");
                }
                $chk->close();
            }

            $stmt = $db->prepare("UPDATE users SET username = ? WHERE id_user = ? AND role = 'admin'");
            if ($stmt) {
                $stmt->bind_param('si', $usernameBaru, $idUser);
                $stmt->execute();
                $stmt->close();

                // Bila mengedit akun yang sedang login, update sesi
                if ($idUser === $currentAdminId || $currentAdminUser === $_POST['username_lama']) {
                    $_SESSION['admin_username'] = $usernameBaru;
                    $currentAdminUser = $usernameBaru;
                }
                $message = "Username administrator berhasil diperbarui menjadi '{$usernameBaru}'!";
            }

        } elseif ($action === 'hapus_admin') {
            $activeTab = 'admin';
            $idUser = (int)($_POST['id_user'] ?? 0);
            $usernameTarget = trim($_POST['username'] ?? '');

            if ($idUser <= 0) {
                throw new Exception('ID Admin tidak valid.');
            }

            // Cegah hapus diri sendiri
            if ($usernameTarget === $currentAdminUser || $idUser === $currentAdminId) {
                throw new Exception('Anda tidak dapat menghapus akun administrator Anda sendiri yang sedang aktif.');
            }

            // Cek jumlah total admin tersisa
            $qCount = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
            $rowCount = $qCount ? $qCount->fetch_assoc() : null;
            if ($rowCount && (int)$rowCount['total'] <= 1) {
                throw new Exception('Tidak dapat menghapus admin terakhir. Sistem harus memiliki minimal satu administrator.');
            }

            $stmtDel = $db->prepare("DELETE FROM users WHERE id_user = ? AND role = 'admin'");
            if ($stmtDel) {
                $stmtDel->bind_param('i', $idUser);
                $stmtDel->execute();
                $stmtDel->close();
                $message = "Akun Administrator '{$usernameTarget}' berhasil dihapus.";
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// ----------------------------------------------------
// QUERY DATA
// ----------------------------------------------------
// Hitung Statistik
$statPasienUser = 0;
$statTotalPasien = 0;
$statTotalPoin = 0;
$statAdminCount = 0;

$qStat1 = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'pasien'");
if ($qStat1) $statPasienUser = (int)$qStat1->fetch_assoc()['total'];

$qStat2 = $db->query("SELECT COUNT(*) as total, SUM(reward_poin) as poin FROM pasien");
if ($qStat2) {
    $r2 = $qStat2->fetch_assoc();
    $statTotalPasien = (int)$r2['total'];
    $statTotalPoin = (int)($r2['poin'] ?? 0);
}

$qStat3 = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
if ($qStat3) $statAdminCount = (int)$qStat3->fetch_assoc()['total'];

// Query Pasien Accounts
$sqlPasien = "
    SELECT 
        u.id_user,
        u.username,
        u.created_at AS user_created_at,
        p.id_pasien,
        p.nik,
        p.nama_lengkap,
        p.no_telepon,
        p.email,
        p.reward_poin,
        p.tanggal_lahir,
        p.jenis_kelamin,
        p.alamat
    FROM users u
    LEFT JOIN pasien p ON p.id_user = u.id_user
    WHERE u.role = 'pasien'
";

if ($searchQuery !== '') {
    $escQ = '%' . $db->real_escape_string($searchQuery) . '%';
    $sqlPasien .= " AND (u.username LIKE '$escQ' OR p.nama_lengkap LIKE '$escQ' OR p.nik LIKE '$escQ' OR p.no_telepon LIKE '$escQ' OR p.email LIKE '$escQ') ";
}
$sqlPasien .= " ORDER BY u.id_user DESC";
$resPasien = $db->query($sqlPasien);
$listPasien = [];
if ($resPasien) {
    while ($row = $resPasien->fetch_assoc()) {
        $listPasien[] = $row;
    }
}

// Query Admin Accounts
$resAdmin = $db->query("SELECT id_user, username, role, created_at FROM users WHERE role = 'admin' ORDER BY id_user ASC");
$listAdmin = [];
if ($resAdmin) {
    while ($row = $resAdmin->fetch_assoc()) {
        $listAdmin[] = $row;
    }
}

adminPageStart('Kelola Akun Pengguna', 'pengguna');
?>

<style>
.pengguna-header-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid var(--hanania-border);
    padding: 24px 28px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(79, 50, 147, 0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.pengguna-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.pengguna-stat-card {
    background: #fff;
    padding: 20px 22px;
    border-radius: 16px;
    border: 1px solid var(--hanania-border);
    box-shadow: 0 4px 18px rgba(79, 50, 147, 0.03);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform .2s ease, box-shadow .2s ease;
}
.pengguna-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(79, 50, 147, 0.07);
}
.pengguna-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7C3AED, #9333EA);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 15px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
}
.user-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #FAF8FD;
    color: var(--hanania-primary);
    border: 1px solid #EAE0F5;
    padding: 3px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    font-family: Consolas, monospace;
}

/* TAB NAVIGATION STYLES */
.user-tabs-bar {
    display: flex;
    gap: 12px;
    border-bottom: 2px solid #EAE0F5;
    padding-bottom: 16px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.content-nav-btn {
    padding: 11px 22px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748B;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.content-nav-btn:hover {
    color: var(--hanania-primary);
    background: #F3E8FF;
    border-color: #D8B4FE;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 34, 166, 0.08);
}
.content-nav-btn.active {
    background: linear-gradient(135deg, #6C22A6 0%, #4F3293 100%) !important;
    color: #FFFFFF !important;
    border-color: #6C22A6 !important;
    font-weight: 800;
    box-shadow: 0 6px 18px rgba(108, 34, 166, 0.32) !important;
    transform: translateY(-2px);
}
.content-nav-btn .tab-badge {
    padding: 3px 10px;
    border-radius: 14px;
    font-size: 11.5px;
    font-weight: 800;
    display: inline-block;
    transition: all 0.2s;
}
.content-nav-btn.active .tab-badge {
    background: rgba(255, 255, 255, 0.28) !important;
    color: #FFFFFF !important;
}
.content-nav-btn:not(.active) .tab-badge {
    background: #E2E8F0 !important;
    color: #475569 !important;
}
</style>

<div class="admin-page">
    <!-- HEADER -->
    <div class="pengguna-header-card">
        <div style="max-width:680px;">
            <div style="display:inline-flex;align-items:center;gap:8px;padding:4px 12px;background:var(--hanania-purple-soft);border-radius:20px;font-size:12px;font-weight:800;color:var(--hanania-primary);margin-bottom:8px;">
                <span>🔐</span> Kredensial &amp; Hak Akses
            </div>
            <h1 style="font-size:22px;font-weight:900;color:var(--hanania-primary);margin:0 0 6px;letter-spacing:-0.3px;">
                Kelola Akun Pengguna
            </h1>
            <p style="font-size:13px;color:var(--hanania-muted);margin:0;line-height:1.5;">
                Manajemen akun pasien (Sahabat Hanania) untuk pemesanan antrean online &amp; poin reward, serta kredensial akun Administrator sistem.
            </p>
        </div>
        <div>
            <?php if ($activeTab === 'pasien'): ?>
                <button type="button" class="btn" onclick="bukaModalTambahPasien()" style="font-weight:800;padding:12px 22px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(108,34,166,0.25);">
                    <span style="font-size:16px;">➕</span> Buat Akun Sahabat Hanania
                </button>
            <?php else: ?>
                <button type="button" class="btn" onclick="bukaModalTambahAdmin()" style="font-weight:800;padding:12px 22px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(108,34,166,0.25);">
                    <span style="font-size:16px;">➕</span> Tambah Administrator Baru
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;padding:12px 18px;border-radius:12px;font-size:13.5px;display:flex;align-items:center;gap:10px;">
            <span><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- STATS WIDGET -->
    <div class="pengguna-stat-grid">
        <div class="pengguna-stat-card" style="border-top:3px solid #7C3AED;">
            <div style="width:50px;height:50px;border-radius:14px;background:var(--hanania-purple-soft);display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--hanania-primary);">
                👥
            </div>
            <div>
                <div style="font-size:11.5px;font-weight:700;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:0.5px;">Akun Sahabat Hanania</div>
                <div style="font-size:24px;font-weight:900;color:var(--hanania-primary);line-height:1.2;"><?= number_format($statPasienUser) ?></div>
                <div style="font-size:11.5px;color:var(--hanania-muted);">Dari total <?= number_format($statTotalPasien) ?> pasien klinik</div>
            </div>
        </div>

        <div class="pengguna-stat-card" style="border-top:3px solid #D97706;">
            <div style="width:50px;height:50px;border-radius:14px;background:#FFF6E5;display:flex;align-items:center;justify-content:center;font-size:24px;color:#D97706;">
                ⭐
            </div>
            <div>
                <div style="font-size:11.5px;font-weight:700;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:0.5px;">Total Poin Reward</div>
                <div style="font-size:24px;font-weight:900;color:#D97706;line-height:1.2;"><?= number_format($statTotalPoin) ?> <span style="font-size:13px;font-weight:700;color:var(--hanania-muted);">Pts</span></div>
                <div style="font-size:11.5px;color:var(--hanania-muted);">Poin reward loyalitas aktif</div>
            </div>
        </div>

        <div class="pengguna-stat-card" style="border-top:3px solid #10B981;">
            <div style="width:50px;height:50px;border-radius:14px;background:#EAF6ED;display:flex;align-items:center;justify-content:center;font-size:24px;color:#1B7A3E;">
                🛡️
            </div>
            <div>
                <div style="font-size:11.5px;font-weight:700;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:0.5px;">Akun Administrator</div>
                <div style="font-size:24px;font-weight:900;color:#1B7A3E;line-height:1.2;"><?= number_format($statAdminCount) ?> <span style="font-size:13px;font-weight:700;color:var(--hanania-muted);">User</span></div>
                <div style="font-size:11.5px;color:var(--hanania-muted);">Pengelola sistem klinik</div>
            </div>
        </div>
    </div>

    <!-- MAIN CARD WITH TABS -->
    <section class="admin-card">
        <!-- TAB SWITCHER -->
        <div class="user-tabs-bar">
            <a href="pengguna.php?tab=pasien" class="content-nav-btn <?= $activeTab === 'pasien' ? 'active' : '' ?>">
                <span style="font-size:16px;">👥</span> Akun Sahabat Hanania 
                <span class="tab-badge">
                    <?= number_format($statPasienUser) ?>
                </span>
            </a>
            <a href="pengguna.php?tab=admin" class="content-nav-btn <?= $activeTab === 'admin' ? 'active' : '' ?>">
                <span style="font-size:16px;">🛡️</span> Akun Administrator 
                <span class="tab-badge">
                    <?= number_format($statAdminCount) ?>
                </span>
            </a>
        </div>

        <!-- ==================================================== -->
        <!-- TAB 1: AKUN SAHABAT HANANIA                          -->
        <!-- ==================================================== -->
        <?php if ($activeTab === 'pasien'): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:14px;">
                <div>
                    <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                        Daftar Akun Member Sahabat Hanania
                    </h4>
                    <p style="font-size:12.5px;color:var(--hanania-muted);margin:0;">
                        Pasien yang telah mendaftar akun dapat login untuk melihat riwayat antrean pendaftaran dan menukarkan poin reward.
                    </p>
                </div>

                <!-- SEARCH FORM -->
                <form method="GET" action="pengguna.php" style="display:flex;align-items:center;gap:8px;">
                    <input type="hidden" name="tab" value="pasien">
                    <input type="text" name="q" value="<?= adminEsc($searchQuery) ?>" placeholder="Cari nama, NIK, username..." class="filter-input" style="font-size:12.5px;min-width:240px;">
                    <button type="submit" class="btn btn-sm" style="font-size:12px;padding:8px 16px;font-weight:800;">Cari</button>
                    <?php if ($searchQuery !== ''): ?>
                        <a href="pengguna.php?tab=pasien" class="btn btn-sm btn-outline" style="font-size:12px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;text-align:center;">No</th>
                            <th>Pasien &amp; Akun Login</th>
                            <th>NIK &amp; Kontak</th>
                            <th style="width:120px;text-align:center;">Poin Reward</th>
                            <th style="width:140px;">Tgl Registrasi</th>
                            <th style="width:190px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listPasien)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px 20px;color:var(--hanania-muted);">
                                    <div style="font-size:32px;margin-bottom:8px;">👥</div>
                                    <div style="font-weight:700;font-size:14px;color:var(--hanania-text);margin-bottom:4px;">
                                        <?= $searchQuery !== '' ? 'Data Tidak Ditemukan' : 'Belum Ada Akun Sahabat Hanania' ?>
                                    </div>
                                    <div style="font-size:12.5px;">
                                        <?= $searchQuery !== '' ? 'Tidak ditemukan akun dengan kata kunci "' . adminEsc($searchQuery) . '"' : 'Klik tombol "+ Buat Akun Sahabat Hanania" di atas untuk menambahkan akun baru.' ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($listPasien as $p): ?>
                                <?php
                                $namaTampil = !empty($p['nama_lengkap']) ? $p['nama_lengkap'] : $p['username'];
                                $initial = strtoupper(mb_substr($namaTampil, 0, 1));
                                ?>
                                <tr>
                                    <td style="text-align:center;font-weight:700;color:var(--hanania-muted);"><?= $no++ ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:12px;">
                                            <div class="pengguna-avatar">
                                                <?= adminEsc($initial) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:800;color:var(--hanania-text);font-size:13.5px;margin-bottom:2px;">
                                                    <?= adminEsc($namaTampil) ?>
                                                    <?php if (!empty($p['jenis_kelamin'])): ?>
                                                        <span style="font-size:10.5px;color:var(--hanania-muted);font-weight:600;margin-left:4px;">
                                                            (<?= $p['jenis_kelamin'] === 'L' ? 'L' : 'P' ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display:flex;align-items:center;gap:6px;">
                                                    <span class="user-pill" title="Login ID / Username">
                                                        👤 <?= adminEsc($p['username']) ?>
                                                    </span>
                                                    <span style="font-size:10.5px;color:var(--hanania-muted);">#<?= (int)$p['id_user'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:12px;font-family:monospace;font-weight:700;color:var(--hanania-text);margin-bottom:2px;">
                                            🪪 <?= !empty($p['nik']) ? adminEsc($p['nik']) : '<span style="color:var(--hanania-muted);font-style:italic;">NIK belum diisi</span>' ?>
                                        </div>
                                        <div style="font-size:11.5px;color:var(--hanania-muted);">
                                            📞 <?= !empty($p['no_telepon']) ? adminEsc($p['no_telepon']) : (!empty($p['email']) ? adminEsc($p['email']) : '-') ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="display:inline-block;padding:5px 12px;border-radius:14px;background:#FFF6E5;color:#D97706;font-size:12.5px;font-weight:800;border:1px solid #FDE68A;">
                                            ⭐ <?= number_format((int)($p['reward_poin'] ?? 0)) ?> Poin
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--hanania-muted);line-height:1.4;">
                                        <?= date('d M Y', strtotime($p['user_created_at'])) ?><br>
                                        <small style="color:var(--hanania-muted);"><?= date('H:i', strtotime($p['user_created_at'])) ?> WIB</small>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:inline-flex;gap:6px;">
                                            <!-- GANTI PASSWORD -->
                                            <button type="button" class="btn btn-sm btn-outline" onclick="bukaModalResetPassword(<?= (int)$p['id_user'] ?>, '<?= adminEsc(addslashes($p['username'])) ?>', '<?= adminEsc(addslashes($p['nama_lengkap'] ?? $p['username'])) ?>')" title="Ganti/Reset Password" style="padding:6px 10px;font-size:11.5px;font-weight:700;">
                                                🔑 Sandi
                                            </button>
                                            <!-- EDIT DATA -->
                                            <button type="button" class="btn btn-sm btn-outline" onclick='bukaModalEditPasien(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Data Akun" style="padding:6px 10px;font-size:11.5px;font-weight:700;">
                                                ✏️ Edit
                                            </button>
                                            <!-- HAPUS AKUN -->
                                            <form method="POST" action="pengguna.php?tab=pasien" onsubmit="return confirm('Hapus akun login untuk pasien <?= adminEsc(addslashes($p['nama_lengkap'] ?? $p['username'])) ?>?\n\nCatatan: Data rekam medis pasien tetap tersimpan di sistem.')">
                                                <input type="hidden" name="action" value="hapus_akun_pasien">
                                                <input type="hidden" name="id_user" value="<?= (int)$p['id_user'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Hapus Akun" style="padding:6px 10px;font-size:11.5px;">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ==================================================== -->
        <!-- TAB 2: AKUN ADMINISTRATOR                            -->
        <!-- ==================================================== -->
        <?php if ($activeTab === 'admin'): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:14px;">
                <div>
                    <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                        Daftar Administrator Sistem Klinik
                    </h4>
                    <p style="font-size:12.5px;color:var(--hanania-muted);margin:0;">
                        Pengguna dengan hak akses penuh ke panel kontrol operasional Klinik Pratama Rumah Hanania.
                    </p>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:60px;text-align:center;">ID</th>
                            <th>Username Administrator</th>
                            <th>Tingkat Hak Akses</th>
                            <th>Tanggal Pembuatan</th>
                            <th style="width:130px;text-align:center;">Status</th>
                            <th style="width:200px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listAdmin as $a): ?>
                            <?php $isSelf = ($a['username'] === $currentAdminUser || (int)$a['id_user'] === $currentAdminId); ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--hanania-muted);">#<?= (int)$a['id_user'] ?></td>
                                <td>
                                    <div style="font-weight:800;color:var(--hanania-primary);font-size:14px;display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:16px;">🛡️</span> <?= adminEsc($a['username']) ?>
                                        <?php if ($isSelf): ?>
                                            <span style="font-size:10.5px;background:#EAF6ED;color:#1B7A3E;border:1px solid #BBF7D0;padding:2px 8px;border-radius:10px;font-weight:800;">
                                                Akun Anda
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:12px;font-weight:700;color:var(--hanania-primary);background:var(--hanania-purple-soft);padding:4px 10px;border-radius:8px;border:1px solid #EAE0F5;">
                                        Super Administrator
                                    </span>
                                </td>
                                <td style="font-size:12.5px;color:var(--hanania-muted);">
                                    <?= date('d M Y, H:i', strtotime($a['created_at'])) ?> WIB
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-size:11.5px;font-weight:800;color:#15803d;background:#DCFCE7;padding:3px 10px;border-radius:12px;border:1px solid #BBF7D0;">
                                        ● Aktif
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <!-- GANTI PASSWORD ADMIN -->
                                        <button type="button" class="btn btn-sm btn-outline" onclick="bukaModalGantiPasswordAdmin(<?= (int)$a['id_user'] ?>, '<?= adminEsc(addslashes($a['username'])) ?>')" title="Ganti Password" style="padding:6px 10px;font-size:11.5px;font-weight:700;">
                                            🔑 Sandi
                                        </button>
                                        <!-- EDIT USERNAME ADMIN -->
                                        <button type="button" class="btn btn-sm btn-outline" onclick="bukaModalEditAdmin(<?= (int)$a['id_user'] ?>, '<?= adminEsc(addslashes($a['username'])) ?>')" title="Edit Username" style="padding:6px 10px;font-size:11.5px;font-weight:700;">
                                            ✏️ Edit
                                        </button>
                                        <!-- HAPUS ADMIN -->
                                        <?php if (!$isSelf && count($listAdmin) > 1): ?>
                                            <form method="POST" action="pengguna.php?tab=admin" onsubmit="return confirm('Hapus akun administrator <?= adminEsc(addslashes($a['username'])) ?>?')">
                                                <input type="hidden" name="action" value="hapus_admin">
                                                <input type="hidden" name="id_user" value="<?= (int)$a['id_user'] ?>">
                                                <input type="hidden" name="username" value="<?= adminEsc($a['username']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Hapus Admin" style="padding:6px 10px;font-size:11.5px;">
                                                    🗑️
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-light" disabled title="<?= $isSelf ? 'Tidak dapat menghapus akun Anda sendiri' : 'Tidak dapat menghapus admin terakhir' ?>" style="padding:6px 10px;font-size:11.5px;opacity:0.4;cursor:not-allowed;">
                                                🗑️
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- ==================================================== -->
<!-- MODAL-MODAL INTERAKTIF                               -->
<!-- ==================================================== -->

<!-- 1. MODAL TAMBAH AKUN SAHABAT HANANIA -->
<div id="modalTambahPasienOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:620px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 22px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin:0;">➕ Buat Akun Sahabat Hanania Baru</h4>
            <button type="button" onclick="tutupModalTambahPasien()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=pasien" style="padding:22px;">
            <input type="hidden" name="action" value="tambah_akun_pasien">
            
            <div style="background:#FAF8FD;padding:12px;border-radius:10px;border:1px solid #EAE0F5;margin-bottom:16px;font-size:12px;color:var(--hanania-text);">
                💡 Akun ini otomatis mendapatkan <strong>50 Poin Reward Selamat Datang</strong> dan dapat digunakan untuk login ke portal pasien.
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Username / Login ID <span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="username" required placeholder="Contoh: keitra@gmail.com / NIK" class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Kata Sandi (Password) <span style="color:#e53e3e;">*</span></label>
                    <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" class="filter-input" style="width:100%;font-size:13px;">
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Nama Lengkap Pasien <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="nama_lengkap" required placeholder="Nama lengkap sesuai KTP" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">NIK (16 Digit KTP)</label>
                    <input type="text" name="nik" maxlength="16" placeholder="3201..." class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">No. Telepon / WhatsApp</label>
                    <input type="tel" name="no_telepon" placeholder="08..." class="filter-input" style="width:100%;font-size:13px;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="filter-input" style="width:100%;font-size:13px;">
                        <option value="">-- Pilih --</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Alamat Domisili</label>
                <textarea name="alamat" rows="2" class="filter-input" style="width:100%;font-size:13px;" placeholder="Alamat lengkap tempat tinggal..."></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalTambahPasien()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Akun Pasien</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL RESET PASSWORD PASIEN -->
<div id="modalResetPasswordOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin:0;">🔑 Ganti / Reset Password Pasien</h4>
            <button type="button" onclick="tutupModalResetPassword()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=pasien" style="padding:20px;">
            <input type="hidden" name="action" value="reset_password_pasien">
            <input type="hidden" name="id_user" id="resetUserId">

            <div style="margin-bottom:14px;">
                <div style="font-size:12px;color:var(--hanania-muted);margin-bottom:2px;">Akun:</div>
                <div id="resetUserNama" style="font-weight:800;color:var(--hanania-primary);font-size:14px;">-</div>
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Password Baru <span style="color:#e53e3e;">*</span></label>
                <div style="display:flex;gap:6px;">
                    <input type="text" name="password_baru" id="resetPassInput" required minlength="6" class="filter-input" style="width:100%;font-size:13px;" placeholder="Masukkan password baru...">
                    <button type="button" class="btn btn-sm btn-outline" onclick="generateRandomPass('resetPassInput')" title="Buat password acak otomatis" style="flex-shrink:0;">🎲 Acak</button>
                </div>
                <small style="font-size:11px;color:var(--hanania-muted);margin-top:4px;display:block;">Minimal 6 karakter. Beritahukan password baru ini kepada pasien.</small>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalResetPassword()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Password</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. MODAL EDIT AKUN PASIEN -->
<div id="modalEditPasienOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:540px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 22px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin:0;">✏️ Edit Data Akun Sahabat Hanania</h4>
            <button type="button" onclick="tutupModalEditPasien()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=pasien" style="padding:22px;">
            <input type="hidden" name="action" value="edit_akun_pasien">
            <input type="hidden" name="id_user" id="editPasienUserId">
            <input type="hidden" name="id_pasien" id="editPasienPasienId">

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Login ID / Username <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="username" id="editPasienUsername" required class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Nama Lengkap Pasien <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="nama_lengkap" id="editPasienNama" required class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">NIK Pasien</label>
                    <input type="text" name="nik" id="editPasienNik" maxlength="16" class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">No. Telepon / WhatsApp</label>
                    <input type="tel" name="no_telepon" id="editPasienTelp" class="filter-input" style="width:100%;font-size:13px;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Email Pasien</label>
                    <input type="email" name="email" id="editPasienEmail" class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Poin Reward</label>
                    <input type="number" name="reward_poin" id="editPasienPoin" min="0" class="filter-input" style="width:100%;font-size:13px;">
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalEditPasien()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. MODAL TAMBAH ADMIN -->
<div id="modalTambahAdminOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin:0;">➕ Tambah Administrator Baru</h4>
            <button type="button" onclick="tutupModalTambahAdmin()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=admin" style="padding:20px;">
            <input type="hidden" name="action" value="tambah_admin">

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Username Admin <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="username" required minlength="3" placeholder="Contoh: admin_klinik" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Password <span style="color:#e53e3e;">*</span></label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Konfirmasi Password <span style="color:#e53e3e;">*</span></label>
                <input type="password" name="konfirmasi_password" required minlength="6" placeholder="Ulangi password di atas" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalTambahAdmin()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Admin</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL GANTI PASSWORD ADMIN -->
<div id="modalPassAdminOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin:0;">🔑 Ganti Password Admin</h4>
            <button type="button" onclick="tutupModalGantiPasswordAdmin()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=admin" style="padding:20px;">
            <input type="hidden" name="action" value="ganti_password_admin">
            <input type="hidden" name="id_user" id="passAdminId">

            <div style="margin-bottom:14px;">
                <div style="font-size:12px;color:var(--hanania-muted);margin-bottom:2px;">Administrator:</div>
                <div id="passAdminUser" style="font-weight:800;color:var(--hanania-primary);font-size:14px;">-</div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Password Baru <span style="color:#e53e3e;">*</span></label>
                <input type="password" name="password_baru" required minlength="6" placeholder="Minimal 6 karakter" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Konfirmasi Password Baru <span style="color:#e53e3e;">*</span></label>
                <input type="password" name="konfirmasi_password" required minlength="6" placeholder="Ulangi password baru" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalGantiPasswordAdmin()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Password</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. MODAL EDIT USERNAME ADMIN -->
<div id="modalEditAdminOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
    <div style="background:#fff;border-radius:16px;max-width:440px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
            <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin:0;">✏️ Edit Username Administrator</h4>
            <button type="button" onclick="tutupModalEditAdmin()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
        </div>
        <form method="POST" action="pengguna.php?tab=admin" style="padding:20px;">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="id_user" id="editAdminId">
            <input type="hidden" name="username_lama" id="editAdminOldUser">

            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Username Baru <span style="color:#e53e3e;">*</span></label>
                <input type="text" name="username" id="editAdminUsername" required minlength="3" class="filter-input" style="width:100%;font-size:13px;">
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                <button type="button" onclick="tutupModalEditAdmin()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Username</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalTambahPasien() {
    document.getElementById('modalTambahPasienOverlay').style.display = 'flex';
}
function tutupModalTambahPasien() {
    document.getElementById('modalTambahPasienOverlay').style.display = 'none';
}

function bukaModalResetPassword(idUser, username, nama) {
    document.getElementById('resetUserId').value = idUser;
    document.getElementById('resetUserNama').textContent = (nama && nama !== username) ? (nama + ' (' + username + ')') : username;
    document.getElementById('resetPassInput').value = '';
    document.getElementById('modalResetPasswordOverlay').style.display = 'flex';
}
function tutupModalResetPassword() {
    document.getElementById('modalResetPasswordOverlay').style.display = 'none';
}

function bukaModalEditPasien(p) {
    document.getElementById('editPasienUserId').value = p.id_user;
    document.getElementById('editPasienPasienId').value = p.id_pasien || 0;
    document.getElementById('editPasienUsername').value = p.username || '';
    document.getElementById('editPasienNama').value = p.nama_lengkap || '';
    document.getElementById('editPasienNik').value = p.nik || '';
    document.getElementById('editPasienTelp').value = p.no_telepon || '';
    document.getElementById('editPasienEmail').value = p.email || '';
    document.getElementById('editPasienPoin').value = p.reward_poin || 0;
    document.getElementById('modalEditPasienOverlay').style.display = 'flex';
}
function tutupModalEditPasien() {
    document.getElementById('modalEditPasienOverlay').style.display = 'none';
}

function bukaModalTambahAdmin() {
    document.getElementById('modalTambahAdminOverlay').style.display = 'flex';
}
function tutupModalTambahAdmin() {
    document.getElementById('modalTambahAdminOverlay').style.display = 'none';
}

function bukaModalGantiPasswordAdmin(idUser, username) {
    document.getElementById('passAdminId').value = idUser;
    document.getElementById('passAdminUser').textContent = username;
    document.getElementById('modalPassAdminOverlay').style.display = 'flex';
}
function tutupModalGantiPasswordAdmin() {
    document.getElementById('modalPassAdminOverlay').style.display = 'none';
}

function bukaModalEditAdmin(idUser, username) {
    document.getElementById('editAdminId').value = idUser;
    document.getElementById('editAdminOldUser').value = username;
    document.getElementById('editAdminUsername').value = username;
    document.getElementById('modalEditAdminOverlay').style.display = 'flex';
}
function tutupModalEditAdmin() {
    document.getElementById('modalEditAdminOverlay').style.display = 'none';
}

function generateRandomPass(inputId) {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
    let res = '';
    for (let i = 0; i < 8; i++) {
        res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const inp = document.getElementById(inputId);
    if (inp) {
        inp.value = res;
        inp.type = 'text';
    }
}
</script>

<?php
adminPageEnd();
?>
