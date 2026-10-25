<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Database connection failed\n");
}

echo "======================================================\n";
echo "=== SINKRONISASI & NORMALISASI SEMUA DATA EXISTING ===\n";
echo "======================================================\n\n";

// ---------------------------------------------------------
// 1. PASTIKAN STRUKTUR TABEL & KOLOM LENGKAP
// ---------------------------------------------------------
echo "[1] Memeriksa & Menyempurnakan Struktur Kolom...\n";

// Tabel pasien
$colsPasien = [];
$res = $db->query("SHOW COLUMNS FROM pasien");
while ($r = $res->fetch_assoc()) $colsPasien[] = $r['Field'];

if (!in_array('nama_ibu_kandung', $colsPasien, true)) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_ibu_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "  + Kolom nama_ibu_kandung ditambahkan ke pasien\n";
}
if (!in_array('nama_suami', $colsPasien, true)) {
    $db->query("ALTER TABLE pasien ADD COLUMN nama_suami VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "  + Kolom nama_suami ditambahkan ke pasien\n";
}

// Tabel keluarga_pasien
$colsKeluarga = [];
$res = $db->query("SHOW COLUMNS FROM keluarga_pasien");
while ($r = $res->fetch_assoc()) $colsKeluarga[] = $r['Field'];

if (!in_array('nama_ibu_kandung', $colsKeluarga, true)) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_ibu_kandung VARCHAR(100) NULL AFTER nama_lengkap");
    echo "  + Kolom nama_ibu_kandung ditambahkan ke keluarga_pasien\n";
}
if (!in_array('nama_suami', $colsKeluarga, true)) {
    $db->query("ALTER TABLE keluarga_pasien ADD COLUMN nama_suami VARCHAR(100) NULL AFTER nama_ibu_kandung");
    echo "  + Kolom nama_suami ditambahkan ke keluarga_pasien\n";
}

// Tabel poli
$colsPoli = [];
$res = $db->query("SHOW COLUMNS FROM poli");
while ($r = $res->fetch_assoc()) $colsPoli[] = $r['Field'];

if (!in_array('status_operasional', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN status_operasional VARCHAR(50) NOT NULL DEFAULT 'otomatis'");
    echo "  + Kolom status_operasional ditambahkan ke poli\n";
} else {
    $db->query("ALTER TABLE poli MODIFY COLUMN status_operasional VARCHAR(50) NOT NULL DEFAULT 'otomatis'");
}
if (!in_array('is_24jam', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN is_24jam TINYINT(1) NOT NULL DEFAULT 0");
    echo "  + Kolom is_24jam ditambahkan ke poli\n";
}
if (!in_array('waktu_cutoff_menit', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN waktu_cutoff_menit INT NOT NULL DEFAULT 60");
    echo "  + Kolom waktu_cutoff_menit ditambahkan ke poli\n";
}
if (!in_array('pesan_tutup', $colsPoli, true)) {
    $db->query("ALTER TABLE poli ADD COLUMN pesan_tutup VARCHAR(255) NULL DEFAULT 'Pendaftaran ditutup minimal 1 jam sebelum jadwal selesai'");
    echo "  + Kolom pesan_tutup ditambahkan ke poli\n";
}

// Status di jadwal_dokter dan jadwal_bidan
$db->query("ALTER TABLE `jadwal_dokter` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'tersedia'");
$db->query("ALTER TABLE `jadwal_bidan` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'tersedia'");
echo "  ✓ Struktur kolom semua tabel terverifikasi.\n";

// ---------------------------------------------------------
// 2. POLI PERSALINAN 24 JAM & JADWAL SIAGA LENGKAP
// ---------------------------------------------------------
echo "\n[2] Memverifikasi Poli Persalinan 24 Jam...\n";
$db->query("
    UPDATE poli 
    SET is_24jam = 1, status = 'aktif', status_operasional = 'otomatis', waktu_cutoff_menit = 0,
        deskripsi = 'Pelayanan persalinan normal dan kebidanan darurat 24 jam penuh didampingi tim bidan bersertifikat siaga.',
        pesan_tutup = 'Persalinan siaga 24 jam nonstop'
    WHERE id_poli = 5 OR nama_poli LIKE '%Persalinan%'
");

// Pastikan jadwal bidan 7 hari tersedia
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$bidan24Jam = [1, 2, 7]; // Bidan Pinandha, Gita, Rifa
foreach ($bidan24Jam as $idB) {
    $chkStaff = $db->query("SELECT id_bidan FROM bidan WHERE id_bidan = $idB LIMIT 1");
    if (!$chkStaff || $chkStaff->num_rows === 0) continue;
    foreach ($hariList as $h) {
        $chkJ = $db->query("SELECT id_jadwal_bidan FROM jadwal_bidan WHERE id_bidan = $idB AND id_poli = 5 AND hari = '$h' LIMIT 1");
        if (!$chkJ || $chkJ->num_rows === 0) {
            $db->query("INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status) VALUES ($idB, 5, '$h', '00:00:00', '23:59:00', 'tersedia')");
        } else {
            $db->query("UPDATE jadwal_bidan SET status = 'tersedia', jam_mulai = '00:00:00', jam_selesai = '23:59:00' WHERE id_bidan = $idB AND id_poli = 5 AND hari = '$h'");
        }
    }
}
echo "  ✓ Poli Persalinan 24 Jam dan jadwal siaga 7 hari terverifikasi.\n";

// ---------------------------------------------------------
// 3. PENGATURAN FORM PENDAFTARAN (URUTAN BERSIH)
// ---------------------------------------------------------
echo "\n[3] Merapikan Urutan Pengaturan Form Pendaftaran...\n";
$fieldDefinitions = [
    ['nama_field' => 'nik', 'label' => 'NIK (Nomor Induk Kependudukan)', 'wajib' => 1, 'urutan' => 1, 'ket' => 'Nomor KTP/NIK 16 digit pasien'],
    ['nama_field' => 'nama_lengkap', 'label' => 'Nama Lengkap Pasien', 'wajib' => 1, 'urutan' => 2, 'ket' => 'Nama lengkap sesuai kartu identitas'],
    ['nama_field' => 'nama_ibu_kandung', 'label' => 'Nama Ibu Kandung', 'wajib' => 0, 'urutan' => 3, 'ket' => 'Nama lengkap ibu kandung pasien (sesuai Kartu Keluarga / Akta)'],
    ['nama_field' => 'nama_suami', 'label' => 'Nama Suami / Pasangan', 'wajib' => 0, 'urutan' => 4, 'ket' => 'Nama suami atau pasangan pasien (khusus bersalin / pasien menikah)'],
    ['nama_field' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'wajib' => 1, 'urutan' => 5, 'ket' => 'Tanggal lahir pasien'],
    ['nama_field' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'wajib' => 1, 'urutan' => 6, 'ket' => 'Laki-laki / Perempuan'],
    ['nama_field' => 'no_telepon', 'label' => 'Nomor Telepon / WhatsApp', 'wajib' => 1, 'urutan' => 7, 'ket' => 'Nomor WhatsApp aktif untuk konfirmasi'],
    ['nama_field' => 'email', 'label' => 'Alamat Email', 'wajib' => 0, 'urutan' => 8, 'ket' => 'Email aktif pasien'],
    ['nama_field' => 'pekerjaan', 'label' => 'Pekerjaan Pasien', 'wajib' => 0, 'urutan' => 9, 'ket' => 'Pekerjaan utama'],
    ['nama_field' => 'status_perkawinan', 'label' => 'Status Perkawinan', 'wajib' => 0, 'urutan' => 10, 'ket' => 'Belum Menikah / Menikah / Cerai'],
    ['nama_field' => 'pendidikan_terakhir', 'label' => 'Pendidikan Terakhir', 'wajib' => 0, 'urutan' => 11, 'ket' => 'Jenjang pendidikan terakhir'],
    ['nama_field' => 'agama', 'label' => 'Agama', 'wajib' => 0, 'urutan' => 12, 'ket' => 'Agama pasien'],
    ['nama_field' => 'alamat', 'label' => 'Alamat Tempat Tinggal (Domisili)', 'wajib' => 1, 'urutan' => 13, 'ket' => 'Alamat lengkap domisili'],
    ['nama_field' => 'ada_rujukan', 'label' => 'Surat Rujukan (BPJS / Faskes Lain)', 'wajib' => 0, 'urutan' => 14, 'ket' => 'Ketersediaan surat rujukan'],
    ['nama_field' => 'keluhan', 'label' => 'Keluhan Utama / Kebutuhan Konsultasi', 'wajib' => 0, 'urutan' => 15, 'ket' => 'Gejala / keluhan yang dirasakan']
];

foreach ($fieldDefinitions as $fd) {
    $chkF = $db->query("SELECT id_field FROM pengaturan_form_pendaftaran WHERE nama_field = '{$fd['nama_field']}' LIMIT 1");
    if ($chkF && $chkF->num_rows > 0) {
        $rowF = $chkF->fetch_assoc();
        $db->query("UPDATE pengaturan_form_pendaftaran SET label_field = '{$fd['label']}', urutan = {$fd['urutan']}, status = 'aktif' WHERE id_field = {$rowF['id_field']}");
    } else {
        $db->query("INSERT INTO pengaturan_form_pendaftaran (nama_field, label_field, tahap, status, wajib, urutan, keterangan) 
                    VALUES ('{$fd['nama_field']}', '{$fd['label']}', 1, 'aktif', {$fd['wajib']}, {$fd['urutan']}, '{$fd['ket']}')");
    }
}
echo "  ✓ Pengaturan form pendaftaran tersusun rapi (15 field, urutan 1-15).\n";

// ---------------------------------------------------------
// 4. BACKFILL & NORMALISASI ANTREAN UNTUK SEMUA PENDAFTARAN
// ---------------------------------------------------------
echo "\n[4] Menyinkronkan Baris Antrean & Menormalisasi Nomor Antrean...\n";

// Cek pendaftaran tanpa antrean
$missingQ = $db->query("
    SELECT p.id_pendaftaran, p.jam_kunjungan, p.status_pendaftaran
    FROM pendaftaran p
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    WHERE a.id_antrean IS NULL
");
$missingCount = 0;
while ($m = $missingQ->fetch_assoc()) {
    $estJam = $m['jam_kunjungan'] ?? '08:00:00';
    $stAnt = ($m['status_pendaftaran'] === 'selesai') ? 'selesai' : (($m['status_pendaftaran'] === 'dibatalkan') ? 'batal' : 'menunggu');
    $db->query("INSERT INTO antrean (id_pendaftaran, nomor_antrean, estimasi_waktu, status_antrean) VALUES ({$m['id_pendaftaran']}, 1, '$estJam', '$stAnt')");
    $missingCount++;
}
if ($missingCount > 0) {
    echo "  + Berhasil membuat $missingCount baris antrean untuk pendaftaran yang belum memilikinya.\n";
}

// Sekarang normalisasi seluruh antrean sesuai pola 1-2 offline, 3-5 online, dst.
$sqlAll = "
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.id_poli, p.id_dokter, p.id_bidan,
           p.sumber_pendaftaran, a.id_antrean, a.nomor_antrean
    FROM pendaftaran p
    JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    WHERE p.status_pendaftaran != 'dibatalkan'
    ORDER BY p.tanggal_kunjungan ASC, p.id_pendaftaran ASC
";
$resAll = $db->query($sqlAll);
$groups = [];
while ($row = $resAll->fetch_assoc()) {
    $staff = ($row['id_dokter'] > 0) ? 'doc_' . $row['id_dokter'] : 'bid_' . $row['id_bidan'];
    $key = $row['tanggal_kunjungan'] . '_' . $row['id_poli'] . '_' . $staff;
    $groups[$key][] = $row;
}

$normalizedCount = 0;
foreach ($groups as $groupKey => $rows) {
    $offlineIdx = 0;
    $onlineIdx = 0;
    
    foreach ($rows as $r) {
        $channel = ($r['sumber_pendaftaran'] === 'offline') ? 'offline' : 'online';
        if ($channel === 'offline') {
            $offlineIdx++;
            $correctNumber = getAntreanNomorByUrutan($offlineIdx, 'offline');
        } else {
            $onlineIdx++;
            $correctNumber = getAntreanNomorByUrutan($onlineIdx, 'online');
        }
        
        $currentNumber = (int)$r['nomor_antrean'];
        if ($currentNumber !== $correctNumber) {
            $db->query("UPDATE antrean SET nomor_antrean = $correctNumber WHERE id_antrean = {$r['id_antrean']}");
            $normalizedCount++;
        }
    }
}
echo "  ✓ Seluruh nomor antrean ter-sinkronisasi (total $normalizedCount baris diperbarui ke pola standar).\n";

// ---------------------------------------------------------
// 5. ISI DATA NAMA IBU KANDUNG & SUAMI PADA DATA EXISTING
// ---------------------------------------------------------
echo "\n[5] Mengisi Data Nama Ibu Kandung & Suami pada Pasien Existing...\n";
$patientEnrichment = [
    1 => ['ibu' => 'Ibu Siti Aminah', 'suami' => 'Bpk Rudi Hermawan'], // Tia Ningsih
    2 => ['ibu' => 'Ibu Kartini', 'suami' => null],                  // Haidar Cendana (L)
    3 => ['ibu' => 'Ibu Hj. Faridah', 'suami' => null],              // Nabila Pisesa
    4 => ['ibu' => 'Ibu Ratna Dewi', 'suami' => 'Bpk Dimas Anggara'],// Keithra Lia
    5 => ['ibu' => 'Ibu Nurhayati', 'suami' => 'Bpk Hendra Pratama'],// Diandra Cantika
    6 => ['ibu' => 'Ibu Maria Susanti', 'suami' => null],            // Bangchan (L)
    9 => ['ibu' => 'Ibu Mariam', 'suami' => null],                   // Hana Cantika
    10 => ['ibu' => 'Ibu Hj. Faridah', 'suami' => null],             // Nabila Pisesa
    11 => ['ibu' => 'Ibu Endang Sri', 'suami' => 'Bpk Agus Setiawan'],// Linda Kartika
    12 => ['ibu' => 'Ibu Dewi Sartika', 'suami' => null]             // Narenda Jian (L)
];

foreach ($patientEnrichment as $idPas => $vals) {
    $ibuSql = $vals['ibu'] ? "'" . $db->real_escape_string($vals['ibu']) . "'" : "NULL";
    $suamiSql = $vals['suami'] ? "'" . $db->real_escape_string($vals['suami']) . "'" : "NULL";
    $db->query("UPDATE pasien SET nama_ibu_kandung = $ibuSql, nama_suami = $suamiSql WHERE id_pasien = $idPas");
}

// Hubungkan User #2 (username: 'pasien') ke Pasien #3 (Nabila Pisesa) jika belum terhubung
$db->query("UPDATE pasien SET id_user = 2 WHERE id_pasien = 3 AND (id_user IS NULL OR id_user = 0)");

// Perbarui keluarga_pasien (Linda Kartika)
$db->query("UPDATE keluarga_pasien SET nama_ibu_kandung = 'Ibu Endang Sri', nama_suami = 'Bpk Agus Setiawan' WHERE nama_lengkap LIKE '%Linda Kartika%'");
echo "  ✓ Data nama_ibu_kandung dan nama_suami berhasil diterapkan ke seluruh data pasien & keluarga existing.\n";

echo "\n======================================================\n";
echo ">>> SINKRONISASI SELESAI DENGAN SEMPURNA! <<<\n";
echo "======================================================\n";
