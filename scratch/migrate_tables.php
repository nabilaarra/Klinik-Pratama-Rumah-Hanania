<?php
require_once __DIR__ . '/../config/database.php';

// 1. Tabel penukaran_poin
$sql1 = "CREATE TABLE IF NOT EXISTS `penukaran_poin` (
    `id_penukaran` INT AUTO_INCREMENT PRIMARY KEY,
    `id_pasien` INT NOT NULL,
    `kode_reward` VARCHAR(50) NOT NULL,
    `nama_reward` VARCHAR(255) NOT NULL,
    `poin_digunakan` INT NOT NULL,
    `kode_voucher` VARCHAR(50) NOT NULL,
    `status` ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `catatan_admin` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_pasien` (`id_pasien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql1)) {
    echo "penukaran_poin table OK\n";
} else {
    echo "Error penukaran_poin: " . $conn->error . "\n";
}

// 2. Kolom nomor_antrean_jkn pada pendaftaran jika belum ada
$colCheck = $conn->query("SHOW COLUMNS FROM `pendaftaran` LIKE 'nomor_antrean_jkn'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE `pendaftaran` ADD COLUMN `nomor_antrean_jkn` VARCHAR(50) NULL AFTER `nomor_bpjs`");
    echo "nomor_antrean_jkn column added to pendaftaran\n";
} else {
    echo "nomor_antrean_jkn column already exists\n";
}

// 3. Kolom sesi_kunjungan pada pendaftaran jika belum ada
$colSesi = $conn->query("SHOW COLUMNS FROM `pendaftaran` LIKE 'sesi_kunjungan'");
if ($colSesi && $colSesi->num_rows === 0) {
    $conn->query("ALTER TABLE `pendaftaran` ADD COLUMN `sesi_kunjungan` VARCHAR(100) NULL AFTER `jenis_perawatan`");
    echo "sesi_kunjungan column added to pendaftaran\n";
} else {
    echo "sesi_kunjungan column already exists\n";
}

// 4. Default konten beranda di tabel pengaturan_konten
$defaults = [
    'hero_judul' => 'Solusi Pelayanan Medis Praktis & Terpercaya',
    'hero_subjudul' => 'Temukan informasi layanan, jadwal pelayanan, dokter dan bidan, serta lakukan pendaftaran pasien secara online melalui satu website.',
    'hero_badge_persalinan' => '🤰 Persalinan 24 Jam',
    'beranda_slogan' => 'Melayani dengan Hati, Menebar Kebaikan untuk Semua',
    'tentang_judul' => 'Klinik Pratama Rumah Hanania',
    'tentang_paragraf_1' => 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan yang memberikan pelayanan kesehatan berkualitas dengan hati untuk seluruh keluarga.',
    'tentang_paragraf_2' => 'Website ini membantu pasien memperoleh informasi klinik, melihat jadwal pelayanan dokter & bidan, serta melakukan pendaftaran pasien secara online.',
    'tentang_foto' => 'assets/img/klinik3.jpg',
    'kontak_telepon' => '0857 1417 8178',
    'kontak_alamat' => 'Bogor, Jawa Barat',
    'kontak_jam_buka' => 'Buka Setiap Hari (08.00 - 20.00 WIB) & Persalinan 24 Jam',
    'kontak_instagram' => '@rumahhanania_'
];

foreach ($defaults as $k => $v) {
    $stmt = $conn->prepare("INSERT INTO `pengaturan_konten` (`kunci`, `nilai`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `kunci` = `kunci`");
    if ($stmt) {
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
        $stmt->close();
    }
}
echo "Default konten beranda initialized\n";
