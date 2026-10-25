<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi;

echo "=== MIGRATION STEP 1: PERSALINAN SCHEDULE, AYAH KANDUNG & RIWAYAT POIN ===\n\n";

// 1. Tambah kolom nama_ayah_kandung di tabel pasien
$colsP = [];
$resP = $db->query("SHOW COLUMNS FROM pasien");
while ($r = $resP->fetch_assoc()) $colsP[] = $r['Field'];

if (!in_array('nama_ayah_kandung', $colsP, true)) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_ayah_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "  + Added nama_ayah_kandung to pasien\n";
} else {
    echo "  ✓ nama_ayah_kandung already exists in pasien\n";
}

// 2. Tambah kolom nama_ayah_kandung di tabel keluarga_pasien
$colsK = [];
$resK = $db->query("SHOW COLUMNS FROM keluarga_pasien");
while ($r = $resK->fetch_assoc()) $colsK[] = $r['Field'];

if (!in_array('nama_ayah_kandung', $colsK, true)) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_ayah_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "  + Added nama_ayah_kandung to keluarga_pasien\n";
} else {
    echo "  ✓ nama_ayah_kandung already exists in keluarga_pasien\n";
}

// 3. Rapikan jadwal Poli 5 (Persalinan 24 Jam) di jadwal_bidan
// Hapus jadwal duplicate Persalinan 24 Jam agar setiap hari ada 1 Bidan Jaga Utama yang bertugas
$db->query("DELETE FROM jadwal_bidan WHERE id_poli = 5");

$jadwalPersalinan = [
    'Senin'   => 2, // Bidan Gita Marissa
    'Selasa'  => 1, // Bidan Pinandha Arafah
    'Rabu'    => 7, // Bidan Rifa Armawanti
    'Kamis'   => 2, // Bidan Gita Marissa
    'Jumat'   => 1, // Bidan Pinandha Arafah
    'Sabtu'   => 7, // Bidan Rifa Armawanti
    'Minggu'  => 2  // Bidan Gita Marissa
];

foreach ($jadwalPersalinan as $hari => $idBidan) {
    $db->query("
        INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status)
        VALUES ($idBidan, 5, '$hari', '00:00:00', '23:59:00', 'tersedia')
    ");
}
echo "  ✓ Jadwal Bidan Jaga Persalinan 24 Jam diset 1 bidan jaga per hari (total 7 hari terstruktur).\n";

// 4. Buat tabel riwayat_poin jika belum ada
$db->query("
    CREATE TABLE IF NOT EXISTS riwayat_poin (
        id_riwayat INT AUTO_INCREMENT PRIMARY KEY,
        id_pasien INT NOT NULL,
        jenis ENUM('tambah','kurang') NOT NULL DEFAULT 'tambah',
        jumlah_poin INT NOT NULL,
        saldo_akhir INT NOT NULL,
        kategori VARCHAR(100) NOT NULL DEFAULT 'Penyesuaian Admin',
        keterangan TEXT NULL,
        created_by VARCHAR(50) DEFAULT 'Admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_pasien),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "  ✓ Tabel riwayat_poin terverifikasi/dibuat.\n";

// 5. Update data pasien existing untuk nama_ayah_kandung
$ayahData = [
    1 => 'Bpk H. Sulaiman',        // Tia Ningsih (Menikah, tapi tetap punya ayah)
    2 => 'Bpk Hendra Cendana',     // Haidar Cendana (L, Belum Menikah)
    3 => 'Bpk H. Arra Pisesa',     // Nabila Pisesa (P, Belum Menikah)
    4 => 'Bpk Irawan Lia',         // Keithra Lia
    5 => 'Bpk Bambang Cantika',    // Diandra Cantika
    6 => 'Bpk Christopher Bang',   // Bangchan (L, Belum Menikah)
    9 => 'Bpk Suryono',            // Hana Cantika (Anak / Belum Menikah)
    10 => 'Bpk H. Arra Pisesa',    // Nabila Pisesa
    11 => 'Bpk Joko Susilo',       // Linda Kartika
    12 => 'Bpk Dwi Jian'           // Narenda Jian (L, Belum Menikah)
];

foreach ($ayahData as $idPas => $ayah) {
    $ayahEsc = $db->real_escape_string($ayah);
    $db->query("UPDATE pasien SET nama_ayah_kandung = '$ayahEsc' WHERE id_pasien = $idPas");
}

// Pastikan saldo poin member sesuai:
// User #2 (Nabila Pisesa - Pasien #3): 150 poin
$db->query("UPDATE pasien SET reward_poin = 150 WHERE id_pasien = 3");
$db->query("UPDATE pasien SET reward_poin = 200 WHERE id_pasien = 1"); // Tia Ningsih
$db->query("UPDATE pasien SET reward_poin = 100 WHERE id_pasien IN (6, 9, 10, 11, 12)");

echo "  ✓ Data nama_ayah_kandung & reward_poin pasien existing telah diperbarui.\n";
echo "\n=== MIGRATION STEP 1 COMPLETED ===\n";
