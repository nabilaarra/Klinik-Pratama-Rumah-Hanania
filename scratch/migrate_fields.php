<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

// 1. Tambah kolom is_custom jika belum ada
$chk = $db->query("SHOW COLUMNS FROM pengaturan_form_pendaftaran LIKE 'is_custom'");
if ($chk && $chk->num_rows === 0) {
    $db->query("ALTER TABLE pengaturan_form_pendaftaran ADD COLUMN is_custom TINYINT(1) DEFAULT 0 AFTER urutan");
    echo "Added is_custom column to pengaturan_form_pendaftaran\n";
} else {
    echo "is_custom already exists\n";
}

// 2. Cek apakah nama_ayah_kandung sudah ada di pengaturan_form_pendaftaran
$chkA = $db->query("SELECT id_field FROM pengaturan_form_pendaftaran WHERE nama_field = 'nama_ayah_kandung' LIMIT 1");
if ($chkA && $chkA->num_rows === 0) {
    $db->query("INSERT INTO pengaturan_form_pendaftaran (nama_field, label_field, tahap, status, wajib, urutan, is_custom, keterangan) VALUES ('nama_ayah_kandung', 'Nama Ayah Kandung (Pasien Anak / Belum Menikah)', 1, 'aktif', 0, 16, 0, 'Sesuai Kartu Keluarga / Akta Kelahiran (Otomatis tampil jika pasien anak atau belum menikah)')");
    echo "Inserted nama_ayah_kandung into pengaturan_form_pendaftaran\n";
} else {
    echo "nama_ayah_kandung already exists in pengaturan_form_pendaftaran\n";
}

// 3. Pastikan kolom nama_ayah_kandung ada di pasien dan keluarga_pasien
$chkColP = $db->query("SHOW COLUMNS FROM pasien LIKE 'nama_ayah_kandung'");
if ($chkColP && $chkColP->num_rows === 0) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_ayah_kandung VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "Added nama_ayah_kandung to pasien\n";
} else {
    echo "nama_ayah_kandung already in pasien\n";
}

$chkColK = $db->query("SHOW COLUMNS FROM keluarga_pasien LIKE 'nama_ayah_kandung'");
if ($chkColK && $chkColK->num_rows === 0) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_ayah_kandung VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "Added nama_ayah_kandung to keluarga_pasien\n";
} else {
    echo "nama_ayah_kandung already in keluarga_pasien\n";
}

echo "Migration finished successfully.\n";
