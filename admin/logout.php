<?php
/**
 * Logout Administrator
 * Menghapus seluruh session admin dan me-redirect ke halaman login utama (/login.php)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_role'],
    $_SESSION['admin']
);

if (empty($_SESSION['id_user'])) {
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
}

header('Location: login.php');
exit;
