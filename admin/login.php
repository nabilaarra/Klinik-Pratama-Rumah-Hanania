<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Pastikan akun admin default tersedia jika database baru
$exists = $db->query("SELECT id_user FROM users WHERE username='admin' LIMIT 1");
if ($exists && $exists->num_rows === 0) {
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    if ($stmt) {
        $stmt->bind_param('ss', $username, $password);
        $stmt->execute();
        $stmt->close();
    }
}

if (!empty($_SESSION['admin_logged_in']) || isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare("SELECT id_user, username, password, role FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $user = null;
    }

    $passwordValid = false;
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } elseif (hash_equals((string)$user['password'], $password)) {
            $passwordValid = true;
            // Upgrade plain-text password to bcrypt hash
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUp = $db->prepare("UPDATE users SET password = ? WHERE id_user = ?");
            if ($stmtUp) {
                $stmtUp->bind_param('si', $newHash, $user['id_user']);
                $stmtUp->execute();
                $stmtUp->close();
            }
        }
    }

    if ($passwordValid) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int)$user['id_user'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role'] = 'admin';

        header('Location: index.php');
        exit;
    }
    $error = 'Username atau password admin tidak sesuai.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Klinik Pratama Rumah Hanania</title>
    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            background:#F8F5FC;
            font-family:Arial,Helvetica,sans-serif;
            color:#352D3E;
            min-height:100vh;
            display:flex;
            flex-direction:column;
        }
        .login-wrapper{
            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px 16px;
        }
        .login-box{
            width:min(440px, 100%);
            background:#fff;
            border:1px solid #E4D9EF;
            border-radius:20px;
            overflow:hidden;
            box-shadow:0 18px 45px rgba(79,50,147,.1);
        }
        .login-head{
            padding:32px 28px 24px;
            background:linear-gradient(135deg, #7650A2, #9E82C8);
            color:#fff;
            text-align:center;
        }
        .login-head-icon{
            font-size:36px;
            margin-bottom:8px;
        }
        .login-head h1{
            margin:0 0 6px;
            font-size:24px;
            font-weight:800;
        }
        .login-head p{
            margin:0;
            font-size:13px;
            opacity:.9;
        }
        .login-body{
            padding:28px;
        }
        .login-field{
            margin-bottom:18px;
        }
        .login-field label{
            display:block;
            margin-bottom:7px;
            font-size:13px;
            font-weight:700;
            color:#4B4453;
        }
        .login-field input{
            width:100%;
            padding:12px 14px;
            border:1px solid #DCCFE8;
            border-radius:10px;
            font:inherit;
            font-size:14px;
            transition:.15s;
        }
        .login-field input:focus{
            outline:none;
            border-color:#7650A2;
            box-shadow:0 0 0 3px rgba(118,80,162,.15);
        }
        .btn-submit{
            width:100%;
            padding:13px;
            border:0;
            border-radius:10px;
            background:#4F3293;
            color:#fff;
            font-size:14px;
            font-weight:800;
            cursor:pointer;
            transition:all .2s;
        }
        .btn-submit:hover{
            background:#3D2575;
            transform:translateY(-1px);
        }
        .alert-error{
            padding:11px 14px;
            border-radius:9px;
            background:#FFEBEB;
            color:#B72B2B;
            font-size:12px;
            font-weight:600;
            margin-bottom:16px;
            border:1px solid #F5C6C6;
        }
        .hint-box{
            margin-top:20px;
            padding:12px;
            background:#FBF9FD;
            border:1px solid #E8DFF0;
            border-radius:10px;
            color:#675F73;
            font-size:11px;
            line-height:1.6;
            text-align:center;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="login-wrapper">
        <main class="login-box">
            <header class="login-head">
                <div class="login-head-logo" style="margin-bottom:12px;display:flex;justify-content:center;">
                    <img src="../assets/img/logo.jpg" alt="Logo Klinik Pratama Rumah Hanania" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.92);box-shadow:0 6px 18px rgba(0,0,0,0.18);background:#fff;" onerror="this.onerror=null;this.parentElement.innerHTML='🩺';">
                </div>
                <h1>Login Panel Admin</h1>
                <p>Sistem Pengelolaan Terintegrasi Klinik Hanania</p>
            </header>

            <section class="login-body">
                <?php if (isset($_GET['notice']) && $_GET['notice'] === 'admin_redirect'): ?>
                    <div style="background:#EEF2FF;border:1.5px solid #C7D2FE;color:#3730A3;padding:12px 14px;border-radius:10px;font-size:12.5px;font-weight:700;margin-bottom:16px;line-height:1.5;">
                        ℹ️ Akun Administrator silakan login melalui portal khusus staf berikut ini.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-error">
                        ⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="login-field">
                        <label>Username Admin</label>
                        <input type="text" name="username" required autocomplete="username" placeholder="Masukkan username admin" autofocus>
                    </div>

                    <div class="login-field">
                        <label>Password</label>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
                    </div>

                    <button type="submit" class="btn-submit">Masuk ke Dashboard</button>
                </form>

                <div class="hint-box">
                    Akses aman khusus pengelola Klinik Pratama Rumah Hanania.
                </div>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
