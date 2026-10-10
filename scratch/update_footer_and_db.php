<?php
require_once __DIR__ . '/../config/database.php';

$updates = [
    'kontak_alamat' => 'Jl. Desa Sukadamai No.RT 01, RT.01/RW.06, Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165',
    'footer_maps_link' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
    'footer_wa1' => '088510703839',
    'footer_wa2' => '085714178178',
];

foreach ($updates as $k => $v) {
    $stmt = $conn->prepare("UPDATE pengaturan_konten SET nilai = ? WHERE kunci = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $v, $k);
        $stmt->execute();
        echo "Updated $k: " . ($stmt->affected_rows >= 0 ? "OK" : "Error") . "\n";
        $stmt->close();
    }
}

// Check or create keluarga_pasien table
$sqlKeluarga = "CREATE TABLE IF NOT EXISTS keluarga_pasien (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sqlKeluarga)) {
    echo "Table keluarga_pasien checked/created successfully.\n";
} else {
    echo "Error table keluarga_pasien: " . $conn->error . "\n";
}

// Seed Linda Kartika for all existing patient users if not exists
$users = $conn->query("SELECT id_user FROM users WHERE role = 'pasien'");
if ($users) {
    while ($u = $users->fetch_assoc()) {
        $uId = (int)$u['id_user'];
        $check = $conn->query("SELECT id_keluarga FROM keluarga_pasien WHERE id_user = $uId LIMIT 1");
        if ($check && $check->num_rows === 0) {
            $stmtK = $conn->prepare("INSERT INTO keluarga_pasien (id_user, nama_lengkap, nik, hubungan, tanggal_lahir, jenis_kelamin, no_telepon, alamat) VALUES (?, 'Linda Kartika', '320145627763910', 'Istri / Anggota Keluarga', '1996-08-14', 'P', '085714178178', 'Bogor, Jawa Barat')");
            if ($stmtK) {
                $stmtK->bind_param('i', $uId);
                $stmtK->execute();
                $stmtK->close();
                echo "Seeded Linda Kartika for user $uId.\n";
            }
        }
    }
}
