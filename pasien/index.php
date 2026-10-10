<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION['id_user']) || !empty($_SESSION['user']['id_user']);
if ($isLoggedIn) {
    header('Location: profil.php');
} else {
    header('Location: daftar.php');
}
exit;
