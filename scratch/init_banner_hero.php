<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

if (!$db instanceof mysqli) {
    die("Error connecting to database.\n");
}

$sql = "CREATE TABLE IF NOT EXISTS banner_hero (
    id_banner INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NULL,
    subjudul VARCHAR(255) NULL,
    foto VARCHAR(255) NOT NULL,
    urutan INT DEFAULT 0,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$db->query($sql);

$chk = $db->query("SELECT COUNT(*) AS c FROM banner_hero");
$row = $chk ? $chk->fetch_assoc() : null;

if (!$row || (int)$row['c'] === 0) {
    $defaults = [
        ['Gedung & Pelayanan Klinik', 'Pelayanan kesehatan keluarga terpercaya', 'assets/img/klinik3.jpg', 1, 'aktif'],
        ['Ruang Praktik & Tindakan Modern', 'Didukung fasilitas medis yang lengkap dan higienis', 'assets/img/klinik2.jpg', 2, 'aktif'],
        ['Pelayanan Ramah & Profesional', 'Dokter dan bidan berpengalaman siap melayani', 'assets/img/klinik1.jpg', 3, 'aktif']
    ];
    $stmt = $db->prepare("INSERT INTO banner_hero (judul, subjudul, foto, urutan, status) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $stmt->bind_param('sssis', $d[0], $d[1], $d[2], $d[3], $d[4]);
        $stmt->execute();
    }
    $stmt->close();
    echo "Table banner_hero created and seeded successfully.\n";
} else {
    echo "Table banner_hero already exists with {$row['c']} records.\n";
}
