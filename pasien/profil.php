<?php
/**
 * Profil Pasien - Klinik Pratama Rumah Hanania
 * Menampilkan dan mengelola data diri pasien, status antrean, dan reward poin.
 * Terintegrasi dengan 4 tab komprehensif, upload foto profil, dan shared navbar/footer.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak tersedia. Silakan periksa file config/database.php.');
}
$db->set_charset('utf8mb4');

$base = '/Klinik-Pratama-Rumah-Hanania';

// Cek autentikasi pasien
$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);
$idPasienSession = (int)($_SESSION['id_pasien'] ?? $_SESSION['user']['id_pasien'] ?? 0);
$role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';

// Jika belum login atau bukan pasien, arahkan ke login
if ($idUser <= 0 && $idPasienSession <= 0) {
    header('Location: ../login.php?redirect=profil');
    exit;
}

// Ambil data pasien dari database
$pasien = null;
if ($idUser > 0) {
    $stmt = $db->prepare("SELECT * FROM pasien WHERE id_user = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        $pasien = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$pasien && $idPasienSession > 0) {
    $stmt = $db->prepare("SELECT * FROM pasien WHERE id_pasien = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $idPasienSession);
        $stmt->execute();
        $res = $stmt->get_result();
        $pasien = $res->fetch_assoc();
        $stmt->close();
    }
}

$idPasien = (int)($pasien['id_pasien'] ?? $idPasienSession);
if ($idPasien > 0 && !isset($_SESSION['id_pasien'])) {
    $_SESSION['id_pasien'] = $idPasien;
}

// Cek status klaim Google Review (+1 Poin)
$googleReviewClaimed = false;
if ($idPasien > 0) {
    $qRev = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'google_review_claimed_pasien_ids' LIMIT 1");
    if ($qRev && $rowR = $qRev->fetch_assoc()) {
        $claimedList = json_decode($rowR['nilai'] ?: '[]', true);
        if (is_array($claimedList) && in_array($idPasien, $claimedList, true)) {
            $googleReviewClaimed = true;
        }
    }
}

// Flash Message handling
$errors = [];
$successMessage = '';
$claimedVoucher = null;

if (isset($_SESSION['profil_flash'])) {
    $successMessage = $_SESSION['profil_flash']['message'] ?? '';
    if (isset($_SESSION['profil_flash']['voucher'])) {
        $claimedVoucher = $_SESSION['profil_flash']['voucher'];
    }
    unset($_SESSION['profil_flash']);
}

// Tab navigation state
$allowedTabs = ['view', 'edit'];
$activeTab = $_GET['tab'] ?? 'view';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'view';
}

// Handle POST: Update Profil & Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_profil') {
    $activeTab = 'edit';
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
    $noTelepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pekerjaan = trim($_POST['pekerjaan'] ?? '');
    $statusPerkawinan = trim($_POST['status_perkawinan'] ?? '');
    $pendidikanTerakhir = trim($_POST['pendidikan_terakhir'] ?? '');
    $agama = trim($_POST['agama'] ?? '');

    // Validasi
    if ($namaLengkap === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if ($nik === '') {
        $errors[] = 'NIK wajib diisi.';
    } elseif (!preg_match('/^[0-9]{16}$/', $nik)) {
        $errors[] = 'NIK harus terdiri dari 16 digit angka.';
    }
    if ($tanggalLahir === '') {
        $errors[] = 'Tanggal lahir wajib diisi.';
    }
    if (!in_array($jenisKelamin, ['L', 'P'], true)) {
        $errors[] = 'Pilih jenis kelamin yang valid (Laki-laki atau Perempuan).';
    }
    if ($noTelepon === '') {
        $errors[] = 'Nomor telepon/WhatsApp aktif wajib diisi.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format alamat email tidak valid.';
    }

    // Handle Upload Foto Profil
    $fotoPath = $pasien['foto'] ?? null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            $fileInfo = getimagesize($_FILES['foto']['tmp_name']);

            if (!$fileInfo || !in_array($fileInfo['mime'], $allowedMime, true)) {
                $errors[] = 'Format foto harus berupa JPG, PNG, atau WEBP.';
            } elseif ($_FILES['foto']['size'] > $maxFileSize) {
                $errors[] = 'Ukuran foto maksimal 2MB.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/profil/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $cleanExt = strtolower($ext);
                if (!in_array($cleanExt, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $cleanExt = 'jpg';
                }
                $newFileName = 'profil_' . ($idPasien > 0 ? $idPasien : $idUser) . '_' . time() . '.' . $cleanExt;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
                    $fotoPath = 'uploads/profil/' . $newFileName;
                } else {
                    $errors[] = 'Gagal menyimpan berkas foto ke server.';
                }
            }
        } else {
            $errors[] = 'Terjadi kesalahan saat mengunggah foto profil.';
        }
    }

    // Validasi NIK agar tidak bentrok dengan pasien lain
    if ($nik !== '') {
        if ($idPasien > 0) {
            $chkNikStmt = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? AND id_pasien != ? LIMIT 1");
            if ($chkNikStmt) {
                $chkNikStmt->bind_param('si', $nik, $idPasien);
                $chkNikStmt->execute();
                if ($chkNikStmt->get_result()->num_rows > 0) {
                    $errors[] = 'NIK sudah digunakan oleh pasien lain.';
                }
                $chkNikStmt->close();
            }
        } else {
            // Cek apakah NIK ini sudah ada di tabel pasien
            $chkNikStmt = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? LIMIT 1");
            if ($chkNikStmt) {
                $chkNikStmt->bind_param('s', $nik);
                $chkNikStmt->execute();
                $existingP = $chkNikStmt->get_result()->fetch_assoc();
                $chkNikStmt->close();
                if ($existingP) {
                    $idPasien = (int)$existingP['id_pasien'];
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($idPasien > 0) {
                $updateSql = "UPDATE pasien SET 
                                nama_lengkap = ?,
                                nik = ?,
                                tanggal_lahir = ?,
                                jenis_kelamin = ?,
                                no_telepon = ?,
                                email = ?,
                                alamat = ?,
                                pekerjaan = ?,
                                status_perkawinan = ?,
                                pendidikan_terakhir = ?,
                                agama = ?,
                                foto = ?" . ($idUser > 0 ? ", id_user = ?" : "") . "
                              WHERE id_pasien = ?";
                $upStmt = $db->prepare($updateSql);
                if ($upStmt) {
                    if ($idUser > 0) {
                        $upStmt->bind_param(
                            'ssssssssssssii',
                            $namaLengkap,
                            $nik,
                            $tanggalLahir,
                            $jenisKelamin,
                            $noTelepon,
                            $email,
                            $alamat,
                            $pekerjaan,
                            $statusPerkawinan,
                            $pendidikanTerakhir,
                            $agama,
                            $fotoPath,
                            $idUser,
                            $idPasien
                        );
                    } else {
                        $upStmt->bind_param(
                            'ssssssssssssi',
                            $namaLengkap,
                            $nik,
                            $tanggalLahir,
                            $jenisKelamin,
                            $noTelepon,
                            $email,
                            $alamat,
                            $pekerjaan,
                            $statusPerkawinan,
                            $pendidikanTerakhir,
                            $agama,
                            $fotoPath,
                            $idPasien
                        );
                    }
                    $exec = $upStmt->execute();
                    $upStmt->close();

                    if ($exec) {
                        $_SESSION['nama_lengkap'] = $namaLengkap;
                        $_SESSION['nik'] = $nik;
                        $_SESSION['email'] = $email;
                        if ($fotoPath) {
                            $_SESSION['foto'] = $fotoPath;
                        }
                        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                            $_SESSION['user']['nama_lengkap'] = $namaLengkap;
                            $_SESSION['user']['nik'] = $nik;
                            $_SESSION['user']['email'] = $email;
                            if ($fotoPath) {
                                $_SESSION['user']['foto'] = $fotoPath;
                            }
                        }

                        $_SESSION['profil_flash'] = [
                            'type' => 'success',
                            'message' => 'Data profil dan foto berhasil diperbarui!'
                        ];
                        header('Location: profil.php?tab=view');
                        exit;
                    } else {
                        $errors[] = 'Gagal menyimpan perubahan: ' . $db->error;
                    }
                } else {
                    $errors[] = 'Gagal mempersiapkan query update: ' . $db->error;
                }
            } else {
                // Insert baru jika belum ada record pasien
                $insertSql = "INSERT INTO pasien (id_user, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, no_telepon, email, alamat, pekerjaan, status_perkawinan, pendidikan_terakhir, agama, foto, reward_poin)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 50)";
                $inStmt = $db->prepare($insertSql);
                if ($inStmt) {
                    $inStmt->bind_param(
                        'issssssssssss',
                        $idUser,
                        $nik,
                        $namaLengkap,
                        $tanggalLahir,
                        $jenisKelamin,
                        $noTelepon,
                        $email,
                        $alamat,
                        $pekerjaan,
                        $statusPerkawinan,
                        $pendidikanTerakhir,
                        $agama,
                        $fotoPath
                    );
                    $exec = $inStmt->execute();
                    $newIdPasien = $inStmt->insert_id;
                    $inStmt->close();

                    if ($exec) {
                        $_SESSION['id_pasien'] = $newIdPasien;
                        $_SESSION['nama_lengkap'] = $namaLengkap;
                        $_SESSION['nik'] = $nik;
                        $_SESSION['email'] = $email;
                        if ($fotoPath) {
                            $_SESSION['foto'] = $fotoPath;
                        }
                        $_SESSION['profil_flash'] = [
                            'type' => 'success',
                            'message' => 'Data profil berhasil dibuat!'
                        ];
                        header('Location: profil.php?tab=view');
                        exit;
                    } else {
                        $errors[] = 'Gagal menyimpan profil baru: ' . $db->error;
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $errors[] = 'NIK sudah terdaftar dalam sistem untuk pasien lain.';
            } else {
                $errors[] = 'Terjadi kesalahan basis data: ' . $e->getMessage();
            }
        }
    }
}

// Ambil Riwayat & Status Antrean
$antreanList = [];
if ($idPasien > 0) {
    $stmtAntrean = $db->prepare("
        SELECT p.id_pendaftaran, p.id_pasien, p.id_poli, p.id_dokter, p.id_bidan,
               p.tanggal_kunjungan, p.jam_kunjungan, p.jenis_pasien, p.keluhan,
               p.status_pendaftaran, p.sumber_pendaftaran, p.created_at,
               po.nama_poli, d.nama_dokter, b.nama_bidan,
               a.nomor_antrean, a.estimasi_waktu, a.status_antrean
        FROM pendaftaran p
        LEFT JOIN poli po ON po.id_poli = p.id_poli
        LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
        LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
        LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
        WHERE p.id_pasien = ?
        ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
    ");
    if ($stmtAntrean) {
        $stmtAntrean->bind_param('i', $idPasien);
        $stmtAntrean->execute();
        $resAntrean = $stmtAntrean->get_result();
        while ($row = $resAntrean->fetch_assoc()) {
            $antreanList[] = $row;
        }
        $stmtAntrean->close();
    }
}

// Hitung data antrean aktif
$antreanAktif = [];
$antreanRiwayat = [];
foreach ($antreanList as $item) {
    $nomorRaw = (int)($item['nomor_antrean'] ?? 0);
    $item['nomor_antrean_display'] = $nomorRaw > 0 ? str_pad((string)$nomorRaw, 3, '0', STR_PAD_LEFT) : '003';

    // Estimasi waktu
    $jamStr = substr((string)$item['jam_kunjungan'], 0, 5);
    $tsStart = strtotime($item['tanggal_kunjungan'] . ' ' . ($jamStr ?: '08:00'));
    if ($tsStart !== false) {
        $item['estimasi_awal'] = date('H:i', $tsStart);
        $item['estimasi_akhir'] = date('H:i', $tsStart + (20 * 60));
        $item['waktu_datang'] = date('H:i', $tsStart - (15 * 60));
    } else {
        $item['estimasi_awal'] = '08:00';
        $item['estimasi_akhir'] = '08:20';
        $item['waktu_datang'] = '07:45';
    }

    $statusP = $item['status_pendaftaran'] ?? 'menunggu';
    if ($statusP === 'selesai' || $statusP === 'dibatalkan') {
        $antreanRiwayat[] = $item;
    } else {
        $antreanAktif[] = $item;
    }
}

// Sanitasi output
function e($val) {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

$rewardPoin = (int)($pasien['reward_poin'] ?? 50);
$namaTampil = $pasien['nama_lengkap'] ?? $_SESSION['nama_lengkap'] ?? 'Sahabat Hanania';

// Hitung Inisial & Foto Profil
$initials = '';
$parts = explode(' ', trim($namaTampil));
foreach (array_slice($parts, 0, 2) as $p) {
    $initials .= mb_strtoupper(mb_substr($p, 0, 1));
}
if ($initials === '') $initials = 'H';

$fotoSrc = '';
if (!empty($pasien['foto'])) {
    if (strpos($pasien['foto'], 'http') === 0) {
        $fotoSrc = $pasien['foto'];
    } elseif (file_exists(__DIR__ . '/../' . ltrim($pasien['foto'], '/'))) {
        $fotoSrc = $base . '/' . ltrim($pasien['foto'], '/');
    }
}

// Status Keanggotaan
$tier = 'Sahabat Hanania';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Sahabat Hanania - Klinik Pratama Rumah Hanania</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --hanania-primary: #6A22A7;
            --hanania-secondary: #8A4BC8;
            --hanania-accent: #E58735;
            --hanania-light: #F8F5FC;
            --hanania-soft: #F4EEFB;
            --hanania-border: #E5DBF0;
            --hanania-text: #2B1B48;
            --hanania-muted: #6B6278;
            --hanania-card: #FFFFFF;
            --hanania-radius: 16px;
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
            padding: 32px 20px 60px;
        }

        /* Hero Profil Card */
        .profile-hero {
            background: linear-gradient(135deg, #6A22A7 0%, #8A4BC8 100%);
            border-radius: var(--hanania-radius);
            padding: 30px;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            box-shadow: 0 12px 30px rgba(79, 50, 147, 0.18);
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }

        .profile-hero::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1;
        }

        .hero-avatar {
            width: 82px;
            height: 82px;
            background: #FFFFFF;
            color: var(--hanania-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 800;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
            flex-shrink: 0;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.85);
        }

        .hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .hero-info h1 {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .hero-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .badge-sahabat {
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(4px);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .2px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-tier {
            background: #FFE082;
            color: #5D4037;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .hero-right {
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 1;
        }

        .hero-points-card {
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            padding: 14px 22px;
            border-radius: 14px;
            text-align: right;
            min-width: 170px;
        }

        .hero-points-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            opacity: 0.9;
        }

        .hero-points-value {
            font-size: 24px;
            font-weight: 800;
            color: #FFD56B;
        }

        .hero-points-link {
            display: inline-block;
            margin-top: 4px;
            font-size: 12px;
            color: #FFFFFF;
            text-decoration: underline;
            font-weight: 600;
            cursor: pointer;
        }

        /* Navigation Tabs */
        .tabs-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--hanania-border);
            padding-bottom: 8px;
            overflow-x: auto;
        }

        .tab-btn {
            background: #FFFFFF;
            border: 1px solid var(--hanania-border);
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--hanania-muted);
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: var(--hanania-primary);
            border-color: #CBB9E3;
            background: var(--hanania-soft);
        }

        .tab-btn.active {
            color: #FFFFFF;
            background: var(--hanania-primary);
            border-color: var(--hanania-primary);
            box-shadow: 0 4px 12px rgba(79, 50, 147, 0.2);
        }

        /* Alerts */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
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

        .claimed-voucher-box {
            background: #FFFFFF;
            border: 2px dashed #1F7A4D;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 10px;
            color: #1F7A4D;
        }

        /* Content Cards */
        .profile-card {
            background: var(--hanania-card);
            border: 1px solid var(--hanania-border);
            border-radius: var(--hanania-radius);
            padding: 30px;
            box-shadow: 0 4px 18px rgba(42, 31, 61, 0.04);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--hanania-border);
        }

        .card-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--hanania-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-sm {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            background: var(--hanania-soft);
            color: var(--hanania-primary);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-action-sm:hover {
            background: var(--hanania-primary);
            color: #FFFFFF;
        }

        /* View Mode Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .info-item {
            background: #FAF8FD;
            border: 1px solid #F0EAF8;
            border-radius: 12px;
            padding: 16px 20px;
        }

        .info-item.full-width {
            grid-column: span 2;
        }

        .info-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--hanania-muted);
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--hanania-text);
            word-break: break-word;
        }

        /* Form Edit Mode */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--hanania-text);
        }

        .form-label .required {
            color: #B42318;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--hanania-border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            color: var(--hanania-text);
            background: #FFFFFF;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--hanania-primary);
            box-shadow: 0 0 0 3px rgba(79, 50, 147, 0.12);
        }

        textarea.form-control {
            min-height: 85px;
            resize: vertical;
        }

        /* Photo Upload preview */
        .photo-upload-container {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px;
            background: #FAF8FD;
            border: 1px dashed var(--hanania-primary);
            border-radius: 12px;
            grid-column: span 2;
        }

        .photo-preview-box {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 2px solid var(--hanania-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--hanania-primary);
            overflow: hidden;
            flex-shrink: 0;
        }

        .photo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .form-actions {
            grid-column: span 2;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 12px;
            padding-top: 20px;
            border-top: 1px solid var(--hanania-border);
        }

        .btn-primary {
            background: var(--hanania-primary);
            color: #FFFFFF;
            border: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 50, 147, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary:hover {
            background: #3D2575;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #EDE6F6;
            color: var(--hanania-primary);
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #E2D7F0;
        }

        /* TAB 3: Antrean Styling */
        .queue-card-active {
            background: linear-gradient(135deg, #FFFFFF 0%, #FAF8FD 100%);
            border: 2px solid var(--hanania-secondary);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(79, 50, 147, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .queue-num-large {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--hanania-primary);
            color: #FFFFFF;
            padding: 18px 26px;
            border-radius: 14px;
            text-align: center;
            min-width: 140px;
            box-shadow: 0 6px 16px rgba(79, 50, 147, 0.25);
        }

        .queue-num-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            opacity: .9;
        }

        .queue-num-val {
            font-size: 38px;
            font-weight: 800;
            line-height: 1.1;
        }

        .queue-details {
            flex: 1;
            min-width: 260px;
        }

        .queue-badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .queue-badge-status.menunggu { background: #FEF3C7; color: #92400E; }
        .queue-badge-status.diproses, .queue-badge-status.terdaftar { background: #DBEAFE; color: #1E40AF; }
        .queue-badge-status.selesai { background: #D1FAE5; color: #065F46; }
        .queue-badge-status.dibatalkan { background: #FEE2E2; color: #991B1B; }

        .queue-timing-box {
            display: flex;
            gap: 14px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .timing-pill {
            background: #F0EAF8;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--hanania-primary);
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--hanania-border);
        }

        .hanania-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }

        .hanania-table th {
            background: #F4EFFC;
            color: var(--hanania-primary);
            font-weight: 800;
            padding: 12px 16px;
            border-bottom: 1px solid var(--hanania-border);
        }

        .hanania-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #F0ECF5;
            color: var(--hanania-text);
        }

        .hanania-table tr:hover {
            background: #FAF8FD;
        }

        /* TAB 4: Reward Styling */
        .tier-progress-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-border);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
        }

        .progress-bar-bg {
            background: #EAE4F9;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
            margin: 10px 0 6px;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #8A4BC8, #6A22A7);
            height: 100%;
            border-radius: 6px;
            transition: width 0.4s ease;
        }

        .reward-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .reward-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-border);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
            position: relative;
        }

        .reward-card:hover {
            border-color: var(--hanania-secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 50, 147, 0.08);
        }

        .reward-badge-tag {
            position: absolute;
            top: 14px;
            right: 14px;
            background: var(--hanania-soft);
            color: var(--hanania-primary);
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .reward-icon {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .reward-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--hanania-primary);
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .reward-desc {
            font-size: 12px;
            color: var(--hanania-muted);
            line-height: 1.5;
            margin-bottom: 14px;
            flex-grow: 1;
        }

        .reward-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #F0EAF8;
        }

        .reward-points {
            font-size: 15px;
            font-weight: 800;
            color: #D97706;
        }

        .btn-claim {
            background: var(--hanania-primary);
            color: #FFFFFF;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: .15s;
        }

        .btn-claim:hover {
            background: #3D2575;
        }

        .btn-claim:disabled {
            background: #E5DBF0;
            color: #8C7D9E;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .profile-hero {
                flex-direction: column;
                align-items: flex-start;
                padding: 22px;
            }

            .hero-right {
                width: 100%;
                justify-content: space-between;
            }

            .hero-points-card {
                text-align: left;
                width: 100%;
            }

            .info-grid, .form-grid {
                grid-template-columns: 1fr;
            }

            .info-item.full-width, .form-group.full-width, .form-actions, .photo-upload-container {
                grid-column: span 1;
            }

            .profile-card {
                padding: 20px;
            }

            .queue-card-active {
                flex-direction: column;
                align-items: stretch;
            }

            .queue-num-large {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="main-content">

        <!-- Hero Profil Card -->
        <section class="profile-hero">
            <div class="hero-left">
                <div class="hero-avatar">
                    <?php if ($fotoSrc): ?>
                        <img src="<?= e($fotoSrc) ?>" alt="Foto <?= e($namaTampil) ?>">
                    <?php else: ?>
                        <?= e($initials) ?>
                    <?php endif; ?>
                </div>
                <div class="hero-info">
                    <h1><?= e($namaTampil) ?></h1>
                    <div class="hero-badges">
                        <span class="badge-sahabat">✨ Sahabat Hanania</span>
                        <span style="font-size: 12px; opacity: 0.9;">NIK: <strong><?= e($pasien['nik'] ?? $_SESSION['nik'] ?? '-') ?></strong></span>
                    </div>
                </div>
            </div>
            <div class="hero-right">
                <div class="hero-points-card">
                    <div class="hero-points-label">Poin Hanania</div>
                    <div class="hero-points-value">⭐ <?= number_format($rewardPoin, 0, ',', '.') ?> Pts</div>
                </div>
            </div>
        </section>

        <!-- KARTU GOOGLE REVIEW (+1 POIN REWARD) -->
        <div class="profile-card" style="margin-bottom: 24px; background: linear-gradient(135deg, #FFFDF8 0%, #FFF9EC 100%); border: 1.5px solid #FDE68A; padding: 18px 22px; border-radius: 16px; box-shadow: 0 4px 16px rgba(245, 158, 11, 0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 260px;">
                    <div style="width: 46px; height: 46px; border-radius: 12px; background: #FEF3C7; border: 1px solid #FCD34D; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">
                        ⭐
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <strong style="color: #2B1B48; font-size: 15px;">Ulasan Google Review</strong>
                            <span style="background: #F59E0B; color: #FFFFFF; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">
                                +1 Poin Reward
                            </span>
                        </div>
                        <p style="margin: 3px 0 0; font-size: 12.5px; color: #6D5B7B; line-height: 1.5;">
                            Beri rating bintang 5 di Google Review untuk mendukung pelayanan kami dan dapatkan otomatis <strong>+1 Poin Hanania</strong>!
                        </p>
                    </div>
                </div>
                <div style="flex-shrink: 0;" id="wrapReviewBtn">
                    <?php if ($googleReviewClaimed): ?>
                        <div style="display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 9px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 800;">
                            ✅ +1 Poin Review Diterima
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="klaimGoogleReview()" id="btnKlaimReview" class="btn" style="background: #F59E0B; color: #FFFFFF; font-weight: 800; font-size: 13px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);">
                            <span>⭐</span> Tulis Review &amp; Klaim +1 Poin
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifikasi Sukses / Error -->
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <span style="font-size:20px;">✅</span>
                <div style="flex:1;">
                    <div><?= e($successMessage) ?></div>
                    <?php if ($claimedVoucher): ?>
                        <div class="claimed-voucher-box">
                            <strong>KODE VOUCHER KLAIM:</strong>
                            <div style="font-size:22px;font-weight:800;letter-spacing:1px;margin:4px 0;color:var(--hanania-primary);"><?= e($claimedVoucher['kode']) ?></div>
                            <small>Tunjukkan kode ini kepada bagian kasir/administrasi Klinik Rumah Hanania sebelum <?= e($claimedVoucher['berlaku']) ?>.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <span style="font-size:20px;">⚠️</span>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div>• <?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==================== TAB 1: VIEW RINGKASAN DATA DIRI ==================== -->
        <?php if ($activeTab === 'view'): ?>
            <section class="profile-card">
                <div class="card-header">
                    <h2 class="card-title">👤 Informasi Lengkap Pasien</h2>
                    <a href="profil.php?tab=edit" class="btn-action-sm">
                        ✏️ Ubah Profil &amp; Foto
                    </a>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Nama Lengkap</span>
                        <div class="info-value"><?= e($pasien['nama_lengkap'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Nomor Induk Kependudukan (NIK)</span>
                        <div class="info-value"><?= e($pasien['nik'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Tanggal Lahir</span>
                        <div class="info-value">
                            <?php 
                                if (!empty($pasien['tanggal_lahir'])) {
                                    $tgl = strtotime($pasien['tanggal_lahir']);
                                    echo date('d F Y', $tgl);
                                } else {
                                    echo '-';
                                }
                            ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Jenis Kelamin</span>
                        <div class="info-value">
                            <?php 
                                $jk = $pasien['jenis_kelamin'] ?? '';
                                if ($jk === 'L') echo '<span class="timing-pill">Laki-laki</span>';
                                elseif ($jk === 'P') echo '<span class="timing-pill">Perempuan</span>';
                                else echo '-';
                            ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Nomor Telepon / WhatsApp</span>
                        <div class="info-value"><?= e($pasien['no_telepon'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Alamat Email</span>
                        <div class="info-value"><?= e($pasien['email'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Pekerjaan</span>
                        <div class="info-value"><?= e($pasien['pekerjaan'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Status Perkawinan</span>
                        <div class="info-value"><?= e($pasien['status_perkawinan'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Pendidikan Terakhir</span>
                        <div class="info-value"><?= e($pasien['pendidikan_terakhir'] ?? '-') ?></div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Agama</span>
                        <div class="info-value"><?= e($pasien['agama'] ?? '-') ?></div>
                    </div>

                    <div class="info-item full-width">
                        <span class="info-label">Alamat Domisili</span>
                        <div class="info-value"><?= nl2br(e($pasien['alamat'] ?? '-')) ?></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ==================== TAB 2: EDIT DATA DIRI & FOTO ==================== -->
        <?php if ($activeTab === 'edit'): ?>
            <section class="profile-card">
                <div class="card-header">
                    <h2 class="card-title">✏️ Perbarui Data Diri &amp; Foto</h2>
                    <a href="profil.php?tab=view" class="btn-action-sm">
                        ✕ Batal / Kembali
                    </a>
                </div>

                <form method="POST" action="profil.php" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="simpan_profil">

                    <div class="form-grid">
                        <!-- Foto Profil Upload -->
                        <div class="photo-upload-container">
                            <div class="photo-preview-box" id="avatarPreviewContainer">
                                <?php if ($fotoSrc): ?>
                                    <img src="<?= e($fotoSrc) ?>" id="avatarPreviewImg" alt="Foto Profil">
                                <?php else: ?>
                                    <span id="avatarPlaceholder"><?= e($initials) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;">
                                <label class="form-label" for="foto" style="margin-bottom:4px;display:block;">Unggah Foto Profil</label>
                                <input type="file" id="foto" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewPhoto(this)">
                                <small style="color:var(--hanania-muted);font-size:11px;display:block;margin-top:4px;">Format: JPG, PNG, WEBP. Maksimal 2MB.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nama_lengkap">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required
                                   value="<?= e($_POST['nama_lengkap'] ?? $pasien['nama_lengkap'] ?? '') ?>" placeholder="Sesuai KTP">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nik">Nomor Induk Kependudukan (NIK) <span class="required">*</span></label>
                            <input type="text" id="nik" name="nik" class="form-control" required maxlength="16"
                                   value="<?= e($_POST['nik'] ?? $pasien['nik'] ?? '') ?>" placeholder="16 digit angka KTP">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="tanggal_lahir">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control" required
                                   value="<?= e($_POST['tanggal_lahir'] ?? $pasien['tanggal_lahir'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="jenis_kelamin">Jenis Kelamin <span class="required">*</span></label>
                            <?php $currJk = $_POST['jenis_kelamin'] ?? $pasien['jenis_kelamin'] ?? ''; ?>
                            <select id="jenis_kelamin" name="jenis_kelamin" class="form-control" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="L" <?= $currJk === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $currJk === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="no_telepon">No. Telepon / WhatsApp <span class="required">*</span></label>
                            <input type="tel" id="no_telepon" name="no_telepon" class="form-control" required
                                   value="<?= e($_POST['no_telepon'] ?? $pasien['no_telepon'] ?? '') ?>" placeholder="Contoh: 08123456789">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Alamat Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= e($_POST['email'] ?? $pasien['email'] ?? '') ?>" placeholder="nama@email.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pekerjaan">Pekerjaan</label>
                            <input type="text" id="pekerjaan" name="pekerjaan" class="form-control"
                                   value="<?= e($_POST['pekerjaan'] ?? $pasien['pekerjaan'] ?? '') ?>" placeholder="Contoh: Karyawan Swasta, Wiraswasta, PNS">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="status_perkawinan">Status Perkawinan</label>
                            <?php $currKawin = $_POST['status_perkawinan'] ?? $pasien['status_perkawinan'] ?? ''; ?>
                            <select id="status_perkawinan" name="status_perkawinan" class="form-control">
                                <option value="">-- Pilih Status Perkawinan --</option>
                                <?php foreach (['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $currKawin === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pendidikan_terakhir">Pendidikan Terakhir</label>
                            <?php $currPend = $_POST['pendidikan_terakhir'] ?? $pasien['pendidikan_terakhir'] ?? ''; ?>
                            <select id="pendidikan_terakhir" name="pendidikan_terakhir" class="form-control">
                                <option value="">-- Pilih Pendidikan Terakhir --</option>
                                <?php foreach (['SD/Sederajat', 'SMP/Sederajat', 'SMA/SMK/Sederajat', 'Diploma (D1-D4)', 'Sarjana (S1)', 'Magister (S2)', 'Doktor (S3)', 'Tidak/Belum Sekolah'] as $pend): ?>
                                    <option value="<?= e($pend) ?>" <?= $currPend === $pend ? 'selected' : '' ?>><?= e($pend) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="agama">Agama</label>
                            <?php $currAgama = $_POST['agama'] ?? $pasien['agama'] ?? ''; ?>
                            <select id="agama" name="agama" class="form-control">
                                <option value="">-- Pilih Agama --</option>
                                <?php foreach (['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'] as $agm): ?>
                                    <option value="<?= e($agm) ?>" <?= $currAgama === $agm ? 'selected' : '' ?>><?= e($agm) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="alamat">Alamat Lengkap</label>
                            <textarea id="alamat" name="alamat" class="form-control" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota"><?= e($_POST['alamat'] ?? $pasien['alamat'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="profil.php?tab=view" class="btn-secondary">Batal</a>
                            <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </section>
        <?php endif; ?>

    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
    function previewPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const box = document.getElementById('avatarPreviewContainer');
                box.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function klaimGoogleReview() {
        window.open('https://maps.app.goo.gl/524k7ABe1fckKgpt6', '_blank');
        const btn = document.getElementById('btnKlaimReview');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Memproses Poin...';
        }
        fetch('klaim_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
                if (data.already_claimed) {
                    location.reload();
                } else if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>⭐</span> Tulis Review & Klaim +1 Poin';
                }
            }
        })
        .catch(err => {
            console.error('Error claiming review:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span>⭐</span> Tulis Review & Klaim +1 Poin';
            }
        });
    }
    </script>

</body>
</html>
