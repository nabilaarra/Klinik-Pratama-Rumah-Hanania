<?php
session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login          = trim($_POST['login'] ?? '');
    $tanggal_lahir  = trim($_POST['tanggal_lahir'] ?? '');
    $password_baru  = $_POST['password_baru'] ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';

    if ($login === '' || $tanggal_lahir === '' || $password_baru === '' || $konfirmasi === '') {
        $error = 'Semua data verifikasi dan kata sandi baru wajib diisi.';
    } elseif (strlen($password_baru) < 6) {
        $error = 'Kata sandi baru minimal 6 karakter.';
    } elseif ($password_baru !== $konfirmasi) {
        $error = 'Konfirmasi kata sandi baru tidak sama.';
    } else {
        /*
         * Cari akun pasien berdasarkan NIK atau Email serta Tanggal Lahir
         */
        $sql = "
            SELECT u.id_user, u.username, p.id_pasien, p.nik, p.nama_lengkap, p.email, p.tanggal_lahir
            FROM users u
            INNER JOIN pasien p ON p.id_user = u.id_user
            WHERE (u.username = ? OR p.email = ? OR p.nik = ?)
              AND p.tanggal_lahir = ?
            LIMIT 1
        ";

        $user = null;

        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssss', $login, $login, $login, $tanggal_lahir);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
            }
        } elseif (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$login, $login, $login, $tanggal_lahir]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Koneksi database tidak tersedia.';
        }

        if ($error === '') {
            if ($user && !empty($user['id_user'])) {
                $passwordHash = password_hash($password_baru, PASSWORD_DEFAULT);
                $idUser = (int)$user['id_user'];

                $updateSql = "UPDATE users SET password = ? WHERE id_user = ?";
                $updated = false;

                if (isset($conn) && $conn instanceof mysqli) {
                    $stmtUp = mysqli_prepare($conn, $updateSql);
                    if ($stmtUp) {
                        mysqli_stmt_bind_param($stmtUp, 'si', $passwordHash, $idUser);
                        $updated = mysqli_stmt_execute($stmtUp);
                        mysqli_stmt_close($stmtUp);
                    }
                } elseif (isset($pdo) && $pdo instanceof PDO) {
                    $stmtUp = $pdo->prepare($updateSql);
                    $updated = $stmtUp->execute([$passwordHash, $idUser]);
                }

                if ($updated) {
                    $success = 'Kata sandi akun atas nama ' . $user['nama_lengkap'] . ' berhasil diubah! Silakan login dengan kata sandi baru Anda.';
                } else {
                    $error = 'Gagal memperbarui kata sandi. Silakan coba beberapa saat lagi.';
                }
            } else {
                $error = 'Kombinasi NIK/Email dan Tanggal Lahir tidak cocok dengan data pasien kami.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi - Klinik Pratama Rumah Hanania</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #faf7fc;
            color: #333;
        }

        .reset-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 20px;
        }

        .reset-wrapper {
            width: 100%;
            max-width: 490px;
        }

        .reset-header {
            background: linear-gradient(135deg, #7b4bb3, #9a68ca);
            color: white;
            padding: 32px 35px;
            border-radius: 22px 22px 0 0;
        }

        .reset-header .small-title {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            opacity: .9;
        }

        .reset-header h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.2;
        }

        .reset-header p {
            margin: 10px 0 0;
            line-height: 1.5;
            font-size: 14px;
            opacity: .92;
        }

        .reset-card {
            background: #fff;
            padding: 32px 35px 30px;
            border-radius: 0 0 22px 22px;
            box-shadow: 0 12px 35px rgba(92, 59, 126, .12);
            border: 1px solid #eadff2;
            border-top: 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #5e4774;
            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;
            height: 48px;
            padding: 0 15px;
            border: 1px solid #ddd1e7;
            border-radius: 11px;
            outline: none;
            font-size: 15px;
            font-family: inherit;
            transition: .2s;
        }

        input:focus {
            border-color: #8a59bd;
            box-shadow: 0 0 0 3px rgba(138, 89, 189, .10);
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            padding-right: 48px;
        }

        .toggle-password-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8c729c;
            border-radius: 8px;
            transition: color .2s, background-color .2s;
        }

        .toggle-password-btn:hover {
            color: #7b4bb3;
            background-color: #f3ecf9;
        }

        .toggle-password-btn svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }

        .btn-reset {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 11px;
            background: #7b4bb3;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: background .2s;
        }

        .btn-reset:hover {
            background: #69409b;
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-error {
            background: #fff0f0;
            border: 1px solid #f0caca;
            color: #a33a3a;
        }

        .alert-success {
            background: #eefbee;
            border: 1px solid #c9e8ca;
            color: #27672b;
        }

        .back-to-login {
            margin: 22px 0 0;
            text-align: center;
            color: #777;
            font-size: 14px;
        }

        .back-to-login a {
            color: #7b4bb3;
            font-weight: 700;
            text-decoration: none;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }

        .note-text {
            font-size: 12px;
            color: #7e6f8a;
            margin-top: 4px;
        }
    </style>
</head>

<body>

<div class="reset-page">

    <div class="reset-wrapper">

        <div class="reset-header">
            <p class="small-title">KLINIK PRATAMA RUMAH HANANIA</p>
            <h1>Lupa Kata Sandi</h1>
            <p>Verifikasi identitas pasien Anda untuk memperbarui kata sandi dengan aman.</p>
        </div>

        <div class="reset-card">

            <?php if ($error !== ''): ?>
                <div class="alert alert-error">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success">
                    <?= h($success) ?>
                    <div style="margin-top: 15px;">
                        <a href="login.php" class="btn-reset" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none; text-align:center;">
                            Masuk ke Akun Sekarang &rarr;
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <form method="post" autocomplete="off">

                    <div class="form-group">
                        <label for="login">NIK atau Email Terdaftar</label>
                        <input
                            type="text"
                            id="login"
                            name="login"
                            placeholder="Masukkan NIK atau email Anda"
                            value="<?= h($_POST['login'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="tanggal_lahir">Tanggal Lahir (Verifikasi)</label>
                        <input
                            type="date"
                            id="tanggal_lahir"
                            name="tanggal_lahir"
                            value="<?= h($_POST['tanggal_lahir'] ?? '') ?>"
                            required
                        >
                        <div class="note-text">Sesuai dengan tanggal lahir yang terdaftar saat pendaftaran.</div>
                    </div>

                    <div class="form-group">
                        <label for="password_baru">Kata Sandi Baru</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password_baru"
                                name="password_baru"
                                placeholder="Minimal 6 karakter"
                                required
                            >
                            <button
                                type="button"
                                class="toggle-password-btn"
                                onclick="togglePasswordVisibility('password_baru', this)"
                                aria-label="Lihat kata sandi baru"
                                title="Tampilkan kata sandi"
                            >
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                    <line x1="2" x2="22" y1="2" y2="22"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="konfirmasi_password">Konfirmasi Kata Sandi Baru</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="konfirmasi_password"
                                name="konfirmasi_password"
                                placeholder="Ulangi kata sandi baru"
                                required
                            >
                            <button
                                type="button"
                                class="toggle-password-btn"
                                onclick="togglePasswordVisibility('konfirmasi_password', this)"
                                aria-label="Lihat konfirmasi kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-closed" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                    <line x1="2" x2="22" y1="2" y2="22"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-reset">
                        Simpan Kata Sandi Baru
                    </button>

                </form>

            <?php endif; ?>

            <p class="back-to-login">
                Ingat kata sandi Anda?
                <a href="login.php">Kembali ke Login</a>
            </p>

        </div>

    </div>

</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');

    if (input.type === 'password') {
        input.type = 'text';
        if (eyeOpen) eyeOpen.style.display = 'none';
        if (eyeClosed) eyeClosed.style.display = 'block';
        btn.setAttribute('aria-label', 'Sembunyikan kata sandi');
        btn.title = 'Sembunyikan kata sandi';
    } else {
        input.type = 'password';
        if (eyeOpen) eyeOpen.style.display = 'block';
        if (eyeClosed) eyeClosed.style.display = 'none';
        btn.setAttribute('aria-label', 'Lihat kata sandi');
        btn.title = 'Tampilkan kata sandi';
    }
}
</script>

</body>
</html>
