<?php
session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
| File ini hanya membutuhkan config/database.php.
| Tidak memakai includes/header.php, includes/footer.php, atau functions.php.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config/database.php';

$error = '';

/* Helper escape */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Redirect helper */
function go($url) {
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'NIK / Email dan password wajib diisi.';
    } else {

        /*
         * Login:
         * 1. username = NIK
         * 2. email pada tabel pasien
         *
         * users.username tetap dipakai sebagai identitas login NIK.
         */
        $sql = "
            SELECT
                u.id_user,
                u.username,
                u.password,
                u.role,
                p.id_pasien,
                p.nik,
                p.nama_lengkap,
                p.email,
                p.reward_poin
            FROM users u
            LEFT JOIN pasien p ON p.id_user = u.id_user
                WHERE u.username = ?
                    OR p.email = ?
                    OR p.nik = ?
                ORDER BY CASE
                     WHEN u.username = ? THEN 0
                     WHEN p.email = ? THEN 1
                     WHEN p.nik = ? THEN 2
                     ELSE 3
                END
            LIMIT 1
        ";

        $user = null;

        if (isset($conn) && $conn instanceof mysqli) {

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssss', $login, $login, $login, $login, $login, $login);
                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);
                $user = $result ? mysqli_fetch_assoc($result) : null;

                mysqli_stmt_close($stmt);
            }

        } elseif (isset($pdo) && $pdo instanceof PDO) {

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$login, $login, $login, $login, $login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
            $error = 'Koneksi database tidak ditemukan. Cek config/database.php.';
        }

        if ($error === '' && $user) {

            $passwordValid = password_verify($password, $user['password']);

            /*
             * Kalau password lama di database masih tersimpan plain text,
             * sementara waktu tetap bisa login. Setelah sistem stabil,
             * sebaiknya semua password disimpan dengan password_hash().
             */
            if (!$passwordValid && hash_equals((string)$user['password'], $password)) {
                $passwordValid = true;
            }

            if ($passwordValid) {

                // Simpan sesi admin jika sebelumnya sedang aktif agar tidak hilang saat uji coba pasien
                $existingAdmin = [
                    'admin_logged_in' => $_SESSION['admin_logged_in'] ?? null,
                    'admin_id' => $_SESSION['admin_id'] ?? null,
                    'admin_username' => $_SESSION['admin_username'] ?? null,
                    'admin_role' => $_SESSION['admin_role'] ?? null,
                ];

                session_regenerate_id(true);

                // Kembalikan sesi admin jika ada
                foreach ($existingAdmin as $k => $v) {
                    if ($v !== null) {
                        $_SESSION[$k] = $v;
                    }
                }

                $userRewardPoin = (int)($user['reward_poin'] ?? 0);
                $_SESSION['id_user'] = (int)$user['id_user'];
                $_SESSION['user_id'] = (int)$user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'pasien'; // Portal pasien murni bertindak sebagai pasien aktif
                $_SESSION['id_pasien'] = (int)($user['id_pasien'] ?? 0);
                $_SESSION['nama_lengkap'] = !empty($user['nama_lengkap']) ? $user['nama_lengkap'] : $user['username'];
                $_SESSION['nik'] = $user['nik'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['reward_poin'] = $userRewardPoin;
                $_SESSION['user'] = [
                    'id_user' => (int)$user['id_user'],
                    'id_pasien' => (int)($user['id_pasien'] ?? 0),
                    'username' => $user['username'],
                    'role' => 'pasien',
                    'nama_lengkap' => $_SESSION['nama_lengkap'],
                    'nik' => $user['nik'] ?? '',
                    'email' => $user['email'] ?? '',
                    'reward_poin' => $userRewardPoin,
                    'poin' => $userRewardPoin
                ];

                $redirectParam = trim((string)($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
                if ($redirectParam === 'review') {
                    go('index.php?open_review=1#google-review');
                } elseif ($redirectParam === 'reward') {
                    go('pasien/reward.php');
                } else {
                    go('index.php');
                }

            } else {
                $error = 'NIK / Email atau password tidak sesuai.';
            }

        } elseif ($error === '') {
            $error = 'NIK / Email atau password tidak sesuai.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Klinik Pratama Rumah Hanania</title>

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

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .login-header {
            background: linear-gradient(135deg, #7b4bb3, #9a68ca);
            color: white;
            padding: 34px 35px;
            border-radius: 22px 22px 0 0;
        }

        .login-header .small-title {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            opacity: .9;
        }

        .login-header h1 {
            margin: 0;
            font-size: 34px;
            line-height: 1.15;
        }

        .login-header p {
            margin: 10px 0 0;
            line-height: 1.6;
            opacity: .92;
        }

        .login-card {
            background: #fff;
            padding: 32px 35px 30px;
            border-radius: 0 0 22px 22px;
            box-shadow: 0 12px 35px rgba(92, 59, 126, .12);
            border: 1px solid #eadff2;
            border-top: 0;
        }

        .form-group {
            margin-bottom: 20px;
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

        .forgot-password-link {
            display: inline-block;
            font-size: 13px;
            color: #7b4bb3;
            text-decoration: none;
            font-weight: 600;
            transition: .2s;
        }

        .forgot-password-link:hover {
            color: #5c2f8f;
            text-decoration: underline;
        }

        .form-meta {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .btn-login {
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

        .btn-login:hover {
            background: #69409b;
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff0f0;
            border: 1px solid #f0caca;
            color: #a33a3a;
            font-size: 14px;
            line-height: 1.5;
        }

        .register-text {
            margin: 20px 0 0;
            text-align: center;
            color: #777;
            font-size: 14px;
        }

        .register-text a {
            color: #7b4bb3;
            font-weight: 700;
            text-decoration: none;
        }

        .register-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="login-page">

    <div class="login-wrapper">

        <div class="login-header">
            <p class="small-title">KLINIK PRATAMA RUMAH HANANIA</p>
            <h1>Login</h1>
            <p>Masuk ke sistem untuk mengakses layanan klinik.</p>
        </div>

        <div class="login-card">

            <?php 
            $curRedirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
            if ($curRedirect === 'review'): 
            ?>
                <div style="background:#FAF5FF;border:1.5px solid #D8B4FE;color:#581C87;padding:12px 16px;border-radius:12px;margin-bottom:18px;font-size:13px;line-height:1.5;">
                    ⭐ <strong>Khusus Sahabat Hanania:</strong> Silakan login atau <a href="daftar_akun.php?redirect=review" style="color:#6A22A7;font-weight:800;text-decoration:underline;">daftar akun baru</a> terlebih dahulu untuk memberikan ulasan dan mendapatkan <strong>+1 Poin Hanania</strong>!
                </div>
            <?php elseif ($curRedirect === 'reward'): ?>
                <div style="background:#FAF5FF;border:1.5px solid #D8B4FE;color:#581C87;padding:12px 16px;border-radius:12px;margin-bottom:18px;font-size:13px;line-height:1.5;">
                    🎁 <strong>Sahabat Hanania Point &amp; Reward:</strong> Silakan login untuk melihat saldo poin aktif dan menukarkan reward kesehatan Anda.
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
                <?php if ($curRedirect !== ''): ?>
                    <input type="hidden" name="redirect" value="<?= h($curRedirect) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="login">NIK / Email</label>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        placeholder="Masukkan NIK atau email"
                        autocomplete="username"
                        value="<?= h($_POST['login'] ?? '') ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="toggle-password-btn"
                            onclick="togglePasswordVisibility('password', this)"
                            aria-label="Lihat password"
                            title="Tampilkan password"
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
                    <div class="form-meta">
                        <a href="lupa_password.php" class="forgot-password-link">Lupa Kata Sandi?</a>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    Masuk
                </button>

            </form>

            <p class="register-text">
                Belum memiliki akun?
                <a href="daftar_akun.php<?= $curRedirect !== '' ? '?redirect=' . urlencode($curRedirect) : '' ?>">Daftar Disini</a>
            </p>

            <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #E5DBF0;text-align:center;font-size:12.5px;color:#736787;">
                <span>🛡️ Petugas / Administrator?</span>
                <a href="admin/login.php" style="color:#6A22A7;font-weight:800;text-decoration:none;margin-left:4px;">Login Portal Admin &rarr;</a>
            </div>

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
        btn.setAttribute('aria-label', 'Sembunyikan password');
        btn.title = 'Sembunyikan password';
    } else {
        input.type = 'password';
        if (eyeOpen) eyeOpen.style.display = 'block';
        if (eyeClosed) eyeClosed.style.display = 'none';
        btn.setAttribute('aria-label', 'Lihat password');
        btn.title = 'Tampilkan password';
    }
}
</script>

</body>
</html>
