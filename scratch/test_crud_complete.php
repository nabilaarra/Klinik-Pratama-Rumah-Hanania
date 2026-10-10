<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

echo "=== START COMPLETE ADMIN CRUD VERIFICATION ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($condition, $name) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $name\n";
        $passCount++;
    } else {
        echo "  [FAIL] $name\n";
        $failCount++;
    }
}

// -------------------------------------------------------------
// 1. DOKTER & BIDAN CRUD TEST
// -------------------------------------------------------------
echo "1. Testing Dokter & Bidan CRUD:\n";

// 1.1 Insert Doctor
$namaTest = "dr. Budi Santoso, Sp.A";
$idPoli = 1;
$spesialisasi = "Spesialis Anak";
$telp = "081299988877";
$foto = "";
$status = "aktif";

$stmt = $db->prepare("INSERT INTO dokter (nama_dokter, id_poli, spesialisasi, no_telepon, foto, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sissss', $namaTest, $idPoli, $spesialisasi, $telp, $foto, $status);
$stmt->execute();
$idDokter = $stmt->insert_id;
$stmt->close();
assertTest($idDokter > 0, "Insert dokter baru (ID: $idDokter)");

// 1.2 Update Doctor
$namaUp = "dr. Budi Santoso, Sp.A (Konsultan)";
$stmt = $db->prepare("UPDATE dokter SET nama_dokter = ?, id_poli = ?, spesialisasi = ?, no_telepon = ?, status = ? WHERE id_dokter = ?");
$stmt->bind_param('sisssi', $namaUp, $idPoli, $spesialisasi, $telp, $status, $idDokter);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT nama_dokter FROM dokter WHERE id_dokter = $idDokter");
$r = $q->fetch_assoc();
assertTest($r['nama_dokter'] === $namaUp, "Update data dokter berhasil tersimpan");

// 1.3 Test Ganti Foto (simulate photo update)
$fakePhoto = "uploads/tenaga/test_foto_123.jpg";
$stmt = $db->prepare("UPDATE dokter SET foto = ? WHERE id_dokter = ?");
$stmt->bind_param('si', $fakePhoto, $idDokter);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT foto FROM dokter WHERE id_dokter = $idDokter");
$r = $q->fetch_assoc();
assertTest($r['foto'] === $fakePhoto, "Update foto dokter berhasil tersimpan");

// 1.4 Delete Doctor
$stmt = $db->prepare("DELETE FROM dokter WHERE id_dokter = ?");
$stmt->bind_param('i', $idDokter);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT id_dokter FROM dokter WHERE id_dokter = $idDokter");
assertTest($q->num_rows === 0, "Hapus data dokter berhasil");

// 1.5 Verify Aida, Bilqis, and Salma exist and can be queried
$q = $db->query("SELECT id_bidan, nama_bidan FROM bidan WHERE nama_bidan LIKE '%Aida%' OR nama_bidan LIKE '%Bilqis%' OR nama_bidan LIKE '%Salma%'");
$found = [];
while ($row = $q->fetch_assoc()) {
    $found[] = $row['nama_bidan'];
}
assertTest(count($found) >= 3, "Bidan Aida, Bilqis, dan Salma terbaca di database (" . count($found) . " data)");

// 1.6 Verify admin/dokter.php HTML output has modalFotoPeran & valid data-staff for Aida
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';

ob_start();
include __DIR__ . '/../admin/dokter.php';
$htmlDokter = ob_get_clean();

assertTest(strpos($htmlDokter, 'id="modalFotoPeran"') !== false, "Elemen modalFotoPeran ada di HTML modal");
assertTest(strpos($htmlDokter, "Aida Sa\\u0027adah") !== false || strpos($htmlDokter, "Sa&#039;adah") !== false, "Nama Aida Sa'adah ter-escape dengan aman pada atribut HTML");
assertTest(strpos($htmlDokter, "btn-edit-staff") !== false, "Class btn-edit-staff ada di tabel tenaga medis");
assertTest(strpos($htmlDokter, "btn-foto-staff") !== false, "Class btn-foto-staff ada di tabel tenaga medis");

// -------------------------------------------------------------
// 2. JADWAL CRUD TEST
// -------------------------------------------------------------
echo "\n2. Testing Jadwal Praktik CRUD:\n";

$idBidanAida = 4; // Aida Sa'adah
$idPoli = 3;
$hari = "Senin";
$jamMulai = "09:00:00";
$jamSelesai = "12:00:00";
$statusJ = "tersedia";

// Insert Jadwal
$stmt = $db->prepare("INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('iissss', $idBidanAida, $idPoli, $hari, $jamMulai, $jamSelesai, $statusJ);
$stmt->execute();
$idJadwal = $stmt->insert_id;
$stmt->close();
assertTest($idJadwal > 0, "Insert jadwal praktik bidan Aida (ID: $idJadwal)");

// Update Jadwal
$jamMulaiUp = "10:00:00";
$stmt = $db->prepare("UPDATE jadwal_bidan SET jam_mulai = ? WHERE id_jadwal_bidan = ?");
$stmt->bind_param('si', $jamMulaiUp, $idJadwal);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT jam_mulai FROM jadwal_bidan WHERE id_jadwal_bidan = $idJadwal");
$r = $q->fetch_assoc();
assertTest($r['jam_mulai'] === $jamMulaiUp, "Update jam praktik jadwal berhasil");

// Check admin/jadwal.php rendering
ob_start();
include __DIR__ . '/../admin/jadwal.php';
$htmlJadwal = ob_get_clean();
assertTest(strpos($htmlJadwal, "btn-edit-jadwal") !== false, "Class btn-edit-jadwal ada di tabel jadwal");
assertTest(strpos($htmlJadwal, "data-jadwal=") !== false, "Atribut data-jadwal aman ter-render");

// Delete Jadwal
$stmt = $db->prepare("DELETE FROM jadwal_bidan WHERE id_jadwal_bidan = ?");
$stmt->bind_param('i', $idJadwal);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT id_jadwal_bidan FROM jadwal_bidan WHERE id_jadwal_bidan = $idJadwal");
assertTest($q->num_rows === 0, "Hapus jadwal praktik berhasil");

// -------------------------------------------------------------
// 3. PASIEN CRUD TEST
// -------------------------------------------------------------
echo "\n3. Testing Pasien CRUD:\n";

$nikTest = "9999888877776666";
$namaPasien = "Ibu Siti Ma'munah";
$tglLahir = "1995-05-15";
$jk = "P";
$telp = "085544332211";

$stmt = $db->prepare("INSERT INTO pasien (nik, nama_lengkap, tanggal_lahir, jenis_kelamin, no_telepon) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('sssss', $nikTest, $namaPasien, $tglLahir, $jk, $telp);
$stmt->execute();
$idPasien = $stmt->insert_id;
$stmt->close();
assertTest($idPasien > 0, "Insert pasien dengan tanda petik (ID: $idPasien)");

// Check admin/pasien.php rendering
ob_start();
include __DIR__ . '/../admin/pasien.php';
$htmlPasien = ob_get_clean();
assertTest(strpos($htmlPasien, "btn-detail-pasien") !== false, "Class btn-detail-pasien ada di tabel pasien");
assertTest(strpos($htmlPasien, "btn-edit-pasien") !== false, "Class btn-edit-pasien ada di tabel pasien");
assertTest(strpos($htmlPasien, "data-pasien=") !== false, "Atribut data-pasien aman ter-render");

// Delete Pasien
$stmt = $db->prepare("DELETE FROM pasien WHERE id_pasien = ?");
$stmt->bind_param('i', $idPasien);
$stmt->execute();
$stmt->close();

$q = $db->query("SELECT id_pasien FROM pasien WHERE id_pasien = $idPasien");
assertTest($q->num_rows === 0, "Hapus pasien berhasil");

echo "\n=============================================\n";
echo "SUMMARY: $passCount TESTS PASSED, $failCount TESTS FAILED\n";
echo "=============================================\n";
