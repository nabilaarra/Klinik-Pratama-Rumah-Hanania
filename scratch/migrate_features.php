<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== MIGRATION: POLI & PASIEN FIELDS ===\n";

// 1. Tambah kolom di tabel poli jika belum ada
$colsPoli = [];
$res = $db->query("SHOW COLUMNS FROM poli");
while ($r = $res->fetch_assoc()) {
    $colsPoli[] = $r['Field'];
}

if (!in_array('status_operasional', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN status_operasional ENUM('otomatis','buka_manual','tutup_manual') NOT NULL DEFAULT 'otomatis'");
    echo "Added status_operasional to poli\n";
}
if (!in_array('is_24jam', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN is_24jam TINYINT(1) NOT NULL DEFAULT 0");
    echo "Added is_24jam to poli\n";
}
if (!in_array('waktu_cutoff_menit', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN waktu_cutoff_menit INT NOT NULL DEFAULT 60");
    echo "Added waktu_cutoff_menit to poli\n";
}
if (!in_array('pesan_tutup', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN pesan_tutup VARCHAR(255) NULL DEFAULT 'Pendaftaran ditutup minimal 1 jam sebelum jadwal selesai'");
    echo "Added pesan_tutup to poli\n";
}

// 2. Tambah kolom di tabel pasien jika belum ada
$colsPasien = [];
$res = $db->query("SHOW COLUMNS FROM pasien");
while ($r = $res->fetch_assoc()) {
    $colsPasien[] = $r['Field'];
}

if (!in_array('nama_ibu_kandung', $colsPasien, true)) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_ibu_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "Added nama_ibu_kandung to pasien\n";
}
if (!in_array('nama_suami', $colsPasien, true)) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_suami VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "Added nama_suami to pasien\n";
}

// 3. Tambah kolom di tabel keluarga_pasien jika belum ada
$colsKeluarga = [];
$res = $db->query("SHOW COLUMNS FROM keluarga_pasien");
while ($r = $res->fetch_assoc()) {
    $colsKeluarga[] = $r['Field'];
}

if (!in_array('nama_ibu_kandung', $colsKeluarga, true)) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_ibu_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "Added nama_ibu_kandung to keluarga_pasien\n";
}
if (!in_array('nama_suami', $colsKeluarga, true)) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_suami VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "Added nama_suami to keluarga_pasien\n";
}

// 4. Pastikan field nama_ibu_kandung dan nama_suami ada di pengaturan_form_pendaftaran
$chkField = $db->query("SELECT id_field FROM pengaturan_form_pendaftaran WHERE nama_field = 'nama_ibu_kandung'");
if (!$chkField || $chkField->num_rows === 0) {
    $db->query("INSERT INTO pengaturan_form_pendaftaran (nama_field, label_field, tahap, status, wajib, urutan, keterangan) 
                VALUES ('nama_ibu_kandung', 'Nama Ibu Kandung', 1, 'aktif', 0, 11, 'Nama lengkap ibu kandung pasien (sesuai Kartu Keluarga / Akta)')");
    echo "Inserted nama_ibu_kandung into pengaturan_form_pendaftaran\n";
}

$chkField2 = $db->query("SELECT id_field FROM pengaturan_form_pendaftaran WHERE nama_field = 'nama_suami'");
if (!$chkField2 || $chkField2->num_rows === 0) {
    $db->query("INSERT INTO pengaturan_form_pendaftaran (nama_field, label_field, tahap, status, wajib, urutan, keterangan) 
                VALUES ('nama_suami', 'Nama Suami / Pasangan', 1, 'aktif', 0, 12, 'Nama suami atau pasangan pasien (khusus bersalin / pasien menikah)')");
    echo "Inserted nama_suami into pengaturan_form_pendaftaran\n";
}

// 5. Periksa / Tambahkan Poli 'Persalinan 24 Jam' di tabel poli
$chkPoli = $db->query("SELECT id_poli FROM poli WHERE nama_poli LIKE '%Persalinan%' LIMIT 1");
$idPersalinanPoli = 0;
if ($chkPoli && $chkPoli->num_rows > 0) {
    $rowP = $chkPoli->fetch_assoc();
    $idPersalinanPoli = (int)$rowP['id_poli'];
    $db->query("UPDATE poli SET is_24jam = 1, status = 'aktif', deskripsi = 'Pelayanan persalinan normal dan kebidanan darurat 24 jam penuh didampingi bidan bersertifikat.' WHERE id_poli = $idPersalinanPoli");
    echo "Updated existing Persalinan poli (id_poli: $idPersalinanPoli) with is_24jam=1\n";
} else {
    $db->query("INSERT INTO poli (nama_poli, deskripsi, status, status_operasional, is_24jam, waktu_cutoff_menit, pesan_tutup) 
                VALUES ('Persalinan 24 Jam', 'Pelayanan persalinan normal dan kebidanan darurat 24 jam penuh didampingi tim bidan siaga.', 'aktif', 'otomatis', 1, 0, 'Persalinan siaga 24 jam')");
    $idPersalinanPoli = $db->insert_id;
    echo "Inserted new Persalinan 24 Jam poli with id_poli: $idPersalinanPoli\n";
}

// 6. Pastikan jadwal_bidan memiliki jadwal siaga 24 jam untuk Persalinan 24 Jam
// Ambil bidan Gita Marissa (id_bidan 2) atau Bidan Pinandha (1) atau Rifa (7)
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$bidan24Jam = [1, 2, 7]; // Bidan Pinandha, Gita, Rifa
foreach ($bidan24Jam as $idB) {
    $chkStaff = $db->query("SELECT id_bidan FROM bidan WHERE id_bidan = $idB LIMIT 1");
    if (!$chkStaff || $chkStaff->num_rows === 0) continue;
    foreach ($hariList as $h) {
        $chkJ = $db->query("SELECT id_jadwal_bidan FROM jadwal_bidan WHERE id_bidan = $idB AND id_poli = $idPersalinanPoli AND hari = '$h' LIMIT 1");
        if (!$chkJ || $chkJ->num_rows === 0) {
            $db->query("INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status) 
                        VALUES ($idB, $idPersalinanPoli, '$h', '00:00:00', '23:59:00', 'tersedia')");
            echo "Added 24h schedule for bidan $idB on $h (poli $idPersalinanPoli)\n";
        }
    }
}

// 7. Perbarui tindakan_pendaftaran 'Persalinan 24 Jam (Bersalin)' agar terkait juga ke idPersalinanPoli
$resT = $db->query("SELECT id_tindakan, id_poli_terkait FROM tindakan_pendaftaran WHERE nama_tindakan LIKE '%Persalinan%' LIMIT 1");
if ($resT && $rowT = $resT->fetch_assoc()) {
    $currentTerkait = explode(',', (string)$rowT['id_poli_terkait']);
    if (!in_array((string)$idPersalinanPoli, $currentTerkait, true)) {
        $currentTerkait[] = (string)$idPersalinanPoli;
        $newTerkait = implode(',', array_unique(array_filter($currentTerkait)));
        $db->query("UPDATE tindakan_pendaftaran SET id_poli_terkait = '$newTerkait' WHERE id_tindakan = " . (int)$rowT['id_tindakan']);
        echo "Updated tindakan_pendaftaran with id_poli_terkait: $newTerkait\n";
    }
}

echo "MIGRATION COMPLETE! ✅\n";
