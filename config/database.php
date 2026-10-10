<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "klinik_pratama_rumah_hanania"; // SESUAIKAN dengan nama database Anda

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>