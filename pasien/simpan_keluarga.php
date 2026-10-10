<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$db = (isset($conn) && $conn instanceof mysqli) ? $conn : ((isset($koneksi) && $koneksi instanceof mysqli) ? $koneksi : null);
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia.']);
    exit;
}
$db->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak valid.']);
    exit;
}

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);
if ($idUser <= 0) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu untuk menambah data keluarga.']);
    exit;
}

$nama = trim($_POST['nama_lengkap'] ?? '');
$nik = trim($_POST['nik'] ?? '');
$hubungan = trim($_POST['hubungan'] ?? 'Keluarga');
$tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
$jenisKelamin = trim($_POST['jenis_kelamin'] ?? 'P');
$noTelepon = trim($_POST['no_telepon'] ?? '');
$email = trim($_POST['email'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$pekerjaan = trim($_POST['pekerjaan'] ?? '');
$statusPerkawinan = trim($_POST['status_perkawinan'] ?? '');
$pendidikanTerakhir = trim($_POST['pendidikan_terakhir'] ?? '');
$agama = trim($_POST['agama'] ?? '');

if ($nama === '' || $nik === '') {
    echo json_encode(['success' => false, 'message' => 'Nama lengkap dan NIK wajib diisi.']);
    exit;
}

if (!preg_match('/^\d{16}$/', $nik)) {
    echo json_encode(['success' => false, 'message' => 'NIK harus terdiri dari 16 digit angka valid.']);
    exit;
}

// Pastikan tabel keluarga_pasien ada
$db->query("CREATE TABLE IF NOT EXISTS keluarga_pasien (
    id_keluarga INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    nik VARCHAR(20) NOT NULL,
    hubungan VARCHAR(50) DEFAULT 'Keluarga',
    tanggal_lahir DATE NULL,
    jenis_kelamin ENUM('L', 'P') NULL,
    no_telepon VARCHAR(25) NULL,
    email VARCHAR(100) NULL,
    pekerjaan VARCHAR(100) NULL,
    status_perkawinan VARCHAR(50) NULL,
    pendidikan_terakhir VARCHAR(50) NULL,
    agama VARCHAR(50) NULL,
    alamat TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (id_user)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = trim($_POST['action'] ?? 'tambah');
$idKeluarga = (int)($_POST['id_keluarga'] ?? 0);

if ($action === 'update' && $idKeluarga > 0) {
    $stmt = $db->prepare("
        UPDATE keluarga_pasien 
        SET nama_lengkap = ?, nik = ?, tanggal_lahir = ?, jenis_kelamin = ?, 
            no_telepon = ?, email = ?, pekerjaan = ?, status_perkawinan = ?, 
            pendidikan_terakhir = ?, agama = ?, alamat = ?
        WHERE id_keluarga = ? AND id_user = ?
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query update: ' . $db->error]);
        exit;
    }
    $stmt->bind_param(
        'sssssssssssii',
        $nama,
        $nik,
        $tanggalLahir,
        $jenisKelamin,
        $noTelepon,
        $email,
        $pekerjaan,
        $statusPerkawinan,
        $pendidikanTerakhir,
        $agama,
        $alamat,
        $idKeluarga,
        $idUser
    );
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Perubahan data anggota keluarga berhasil disimpan!',
            'data' => [
                'id_keluarga' => $idKeluarga,
                'nama_lengkap' => $nama,
                'nik' => $nik,
                'hubungan' => $hubungan,
                'tanggal_lahir' => $tanggalLahir,
                'jenis_kelamin' => $jenisKelamin,
                'no_telepon' => $noTelepon,
                'email' => $email,
                'pekerjaan' => $pekerjaan,
                'status_perkawinan' => $statusPerkawinan,
                'pendidikan_terakhir' => $pendidikanTerakhir,
                'agama' => $agama,
                'alamat' => $alamat
            ]
        ]);
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data keluarga: ' . $err]);
        exit;
    }
}

$stmt = $db->prepare("
    INSERT INTO keluarga_pasien 
    (id_user, nama_lengkap, nik, hubungan, tanggal_lahir, jenis_kelamin, no_telepon, email, pekerjaan, status_perkawinan, pendidikan_terakhir, agama, alamat)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query: ' . $db->error]);
    exit;
}

$stmt->bind_param(
    'issssssssssss',
    $idUser,
    $nama,
    $nik,
    $hubungan,
    $tanggalLahir,
    $jenisKelamin,
    $noTelepon,
    $email,
    $pekerjaan,
    $statusPerkawinan,
    $pendidikanTerakhir,
    $agama,
    $alamat
);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Anggota keluarga berhasil disimpan.',
        'data' => [
            'id_keluarga' => $newId,
            'nama_lengkap' => $nama,
            'nik' => $nik,
            'hubungan' => $hubungan,
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => $jenisKelamin,
            'no_telepon' => $noTelepon,
            'email' => $email,
            'pekerjaan' => $pekerjaan,
            'status_perkawinan' => $statusPerkawinan,
            'pendidikan_terakhir' => $pendidikanTerakhir,
            'agama' => $agama,
            'alamat' => $alamat
        ]
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan anggota keluarga: ' . $err]);
}
