<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

// Pastikan kolom-kolom pendukung tersedia di database
@$db->query("CREATE TABLE IF NOT EXISTS antrean (
    id_antrean INT AUTO_INCREMENT PRIMARY KEY,
    id_pendaftaran INT NOT NULL,
    nomor_antrean INT NOT NULL,
    estimasi_waktu TIME NULL,
    status_antrean ENUM('menunggu','check-in','dipanggil','dilayani','selesai','batal') DEFAULT 'menunggu',
    INDEX (id_pendaftaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@$db->query("CREATE TABLE IF NOT EXISTS rujukan_pendaftaran (
    id_rujukan INT AUTO_INCREMENT PRIMARY KEY,
    id_pendaftaran INT NOT NULL,
    nama_perujuk VARCHAR(150) NOT NULL,
    file_rujukan VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (id_pendaftaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Pastikan kolom sumber_pendaftaran tersedia
$chkSrc = @$db->query("SHOW COLUMNS FROM pendaftaran LIKE 'sumber_pendaftaran'");
if (!$chkSrc || $chkSrc->num_rows === 0) {
    @$db->query("ALTER TABLE pendaftaran ADD COLUMN sumber_pendaftaran VARCHAR(20) NOT NULL DEFAULT 'offline'");
}

// Pastikan kolom reward_poin ada di tabel pasien
$chkPoin = @$db->query("SHOW COLUMNS FROM pasien LIKE 'reward_poin'");
if (!$chkPoin || $chkPoin->num_rows === 0) {
    @$db->query("ALTER TABLE pasien ADD COLUMN reward_poin INT NOT NULL DEFAULT 50");
}

function kembaliDenganPesan(string $pesan): void {
    $_SESSION['pesan_daftar'] = $pesan;
    header('Location: daftar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar.php');
    exit;
}

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);

// DATA DIRI PASIEN
$idPasien = isset($_POST['id_pasien']) && $_POST['id_pasien'] !== '' ? (int)$_POST['id_pasien'] : 0;
$nik = trim($_POST['nik'] ?? '');
$nama = trim($_POST['nama_lengkap'] ?? '');
$tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
$jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
$telepon = trim($_POST['no_telepon'] ?? '');
$email = trim($_POST['email'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$pekerjaan = trim($_POST['pekerjaan'] ?? '');
$statusPerkawinan = trim($_POST['status_perkawinan'] ?? '');
$pendidikanTerakhir = trim($_POST['pendidikan_terakhir'] ?? '');
$agama = trim($_POST['agama'] ?? '');
$adaRujukan = in_array(trim($_POST['ada_rujukan'] ?? 'Tidak'), ['Ya', 'Tidak'], true) ? trim($_POST['ada_rujukan']) : 'Tidak';
$asalRujukan = trim($_POST['asal_rujukan'] ?? '');
$namaPerujuk = trim($_POST['nama_perujuk'] ?? '');
$tipePasien = ($_POST['tipe_pasien'] ?? 'sendiri') === 'keluarga' ? 'keluarga' : 'sendiri';
$jenisPerawatan = trim($_POST['jenis_perawatan'] ?? 'Pemeriksaan Rutin');
$sesiKunjungan = trim($_POST['sesi_kunjungan'] ?? 'Sesi Pagi (08:00 - 12:00 WIB)');
$nomorAntreanJkn = trim($_POST['nomor_antrean_jkn'] ?? '');

/* =====================================================
   AKSI AJAX: SIMPAN DATA DIRI SAJA
===================================================== */
if (($_POST['action'] ?? '') === 'save_data_only') {
    header('Content-Type: application/json; charset=utf-8');

    if ($nik === '' || $nama === '' || $tanggalLahir === '' || $jenisKelamin === '' || $telepon === '' || $alamat === '') {
        echo json_encode(['success' => false, 'message' => 'Lengkapi data identitas utama terlebih dahulu.']);
        exit;
    }
    if (!preg_match('/^\d{16}$/', $nik)) {
        echo json_encode(['success' => false, 'message' => 'NIK harus terdiri dari 16 digit angka.']);
        exit;
    }

    try {
        $db->begin_transaction();
        if ($tipePasien === 'sendiri' && $idUser > 0 && $idPasien <= 0) {
            $stmtU = $db->prepare('SELECT id_pasien FROM pasien WHERE id_user = ? LIMIT 1');
            if ($stmtU) {
                $stmtU->bind_param('i', $idUser);
                $stmtU->execute();
                $f = $stmtU->get_result()->fetch_assoc();
                $idPasien = (int)($f['id_pasien'] ?? 0);
                $stmtU->close();
            }
        }

        if ($idPasien > 0) {
            $stmt = $db->prepare("
                UPDATE pasien 
                SET nik = ?, nama_lengkap = ?, tanggal_lahir = ?, jenis_kelamin = ?, no_telepon = ?, email = ?, alamat = ?,
                    pekerjaan = ?, status_perkawinan = ?, pendidikan_terakhir = ?, agama = ?, ada_rujukan = ?, asal_rujukan = ?
                WHERE id_pasien = ?
            ");
            if ($stmt) {
                $stmt->bind_param('sssssssssssssi', $nik, $nama, $tanggalLahir, $jenisKelamin, $telepon, $email, $alamat,
                    $pekerjaan, $statusPerkawinan, $pendidikanTerakhir, $agama, $adaRujukan, $asalRujukan, $idPasien);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $db->prepare("
                INSERT INTO pasien (id_user, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, no_telepon, email, alamat,
                                    pekerjaan, status_perkawinan, pendidikan_terakhir, agama, ada_rujukan, asal_rujukan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                $bindUser = ($tipePasien === 'sendiri' && $idUser > 0) ? $idUser : null;
                $stmt->bind_param('isssssssssssss', $bindUser, $nik, $nama, $tanggalLahir, $jenisKelamin, $telepon, $email, $alamat,
                    $pekerjaan, $statusPerkawinan, $pendidikanTerakhir, $agama, $adaRujukan, $asalRujukan);
                $stmt->execute();
                $idPasien = $stmt->insert_id;
                $stmt->close();
            }
        }
        $db->commit();
        if ($tipePasien === 'sendiri') {
            $_SESSION['id_pasien'] = $idPasien;
            $_SESSION['nama_lengkap'] = $nama;
        }
        echo json_encode(['success' => true, 'message' => 'Data diri berhasil disimpan.', 'id_pasien' => $idPasien]);
    } catch (Throwable $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
    }
    exit;
}

/* =====================================================
   PROSES LENGKAP PENDAFTARAN PASIEN ONLINE
===================================================== */
$idPoli = (int)($_POST['id_poli'] ?? 0);
$idDokter = (int)($_POST['id_dokter'] ?? 0);
$idBidan = (int)($_POST['id_bidan'] ?? 0);
$tanggalKunjungan = trim($_POST['tanggal_kunjungan'] ?? '');
$keluhan = trim($_POST['keluhan'] ?? '');
$jenisPasien = in_array($_POST['jenis_pasien'] ?? '', ['BPJS', 'Umum'], true) ? $_POST['jenis_pasien'] : 'Umum';

// Ambil konfigurasi field dari database
$formConfig = [];
$resCfg = $db->query("SELECT nama_field, label_field, wajib, status FROM pengaturan_form_pendaftaran");
if ($resCfg) {
    while ($r = $resCfg->fetch_assoc()) {
        $formConfig[$r['nama_field']] = $r;
    }
}
$isWajib = function($fieldName, $default = true) use ($formConfig) {
    if (!isset($formConfig[$fieldName])) return $default;
    return ($formConfig[$fieldName]['status'] === 'aktif' && (int)$formConfig[$fieldName]['wajib'] === 1);
};

// Validasi Form Dinamis
if ($isWajib('nik', true) && ($nik === '' || !preg_match('/^\d{16}$/', $nik))) {
    kembaliDenganPesan('NIK wajib diisi dengan 16 digit angka valid.');
}
if ($isWajib('nama_lengkap', true) && $nama === '') {
    kembaliDenganPesan('Nama lengkap pasien wajib diisi.');
}
if ($isWajib('tanggal_lahir', true) && $tanggalLahir === '') {
    kembaliDenganPesan('Tanggal lahir pasien wajib diisi.');
}
if ($isWajib('jenis_kelamin', true) && $jenisKelamin === '') {
    kembaliDenganPesan('Jenis kelamin pasien wajib dipilih.');
}
if ($isWajib('no_telepon', true) && $telepon === '') {
    kembaliDenganPesan('Nomor WhatsApp / telepon pasien wajib diisi.');
}
if ($isWajib('alamat', true) && $alamat === '') {
    kembaliDenganPesan('Alamat domisili pasien wajib diisi.');
}
if ($isWajib('email', false) && $email === '') {
    kembaliDenganPesan('Alamat email pasien wajib diisi.');
}
if ($isWajib('keluhan', false) && $keluhan === '') {
    kembaliDenganPesan('Keluhan atau alasan kunjungan wajib diisi.');
}

if ($idPoli <= 0) {
    kembaliDenganPesan('Silakan pilih poli pelayanan.');
}
if ($idDokter <= 0 && $idBidan <= 0) {
    kembaliDenganPesan('Silakan pilih salah satu dokter atau bidan yang diinginkan.');
}
if ($idDokter > 0 && $idBidan > 0) {
    kembaliDenganPesan('Pilih salah satu saja antara dokter atau bidan.');
}
if ($tanggalKunjungan === '') {
    kembaliDenganPesan('Silakan tentukan hari dan tanggal kunjungan.');
}

$dateObj = DateTime::createFromFormat('Y-m-d', $tanggalKunjungan, new DateTimeZone('Asia/Jakarta'));
if (!$dateObj || $dateObj->format('Y-m-d') !== $tanggalKunjungan) {
    kembaliDenganPesan('Format tanggal kunjungan tidak valid.');
}

$hariIndonesiaMap = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
$hariKunjungan = $hariIndonesiaMap[$dateObj->format('l')] ?? '';

// Validasi File Rujukan jika Ya
if ($adaRujukan === 'Ya') {
    if ($namaPerujuk === '') {
        kembaliDenganPesan('Nama perujuk wajib diisi jika memiliki rujukan.');
    }
    $fileRujukan = $_FILES['file_rujukan'] ?? null;
    if (!$fileRujukan || $fileRujukan['error'] !== UPLOAD_ERR_OK) {
        kembaliDenganPesan('File rujukan wajib diunggah (PDF, JPG, PNG).');
    }
    if ((int)$fileRujukan['size'] > 5 * 1024 * 1024) {
        kembaliDenganPesan('Ukuran file rujukan maksimal 5 MB.');
    }
    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileRujukan['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        kembaliDenganPesan('Format file rujukan harus PDF, JPG, atau PNG.');
    }
}

// 1. Ambil Jadwal Tenaga Kesehatan pada Hari Kunjungan Tersebut sesuai Sesi (Pagi vs Sore)
$jamMulai = '';
$jamSelesai = '';
$namaTenaga = '';
$namaPoli = '';

$isSoreSession = (stripos($sesiKunjungan, 'sore') !== false || stripos($sesiKunjungan, 'malam') !== false);
$sesiTimeSql = $isSoreSession ? "AND j.jam_mulai >= '13:00:00'" : "AND j.jam_mulai < '13:00:00'";

if ($idDokter > 0) {
    // Cari jadwal dokter yang spesifik dengan sesi
    $stmtJadwal = $db->prepare("
        SELECT d.nama_dokter, p.nama_poli, j.jam_mulai, j.jam_selesai
        FROM jadwal_dokter j
        JOIN dokter d ON d.id_dokter = j.id_dokter
        JOIN poli p ON p.id_poli = j.id_poli
        WHERE j.id_dokter = ? AND j.id_poli = ? AND LOWER(TRIM(j.hari)) = LOWER(?) $sesiTimeSql AND j.status = 'tersedia' AND d.status = 'aktif'
        ORDER BY j.jam_mulai ASC LIMIT 1
    ");
    $rowJ = null;
    if ($stmtJadwal) {
        $stmtJadwal->bind_param('iis', $idDokter, $idPoli, $hariKunjungan);
        $stmtJadwal->execute();
        $rowJ = $stmtJadwal->get_result()->fetch_assoc();
        $stmtJadwal->close();
    }
    // Fallback jika tidak ada sesi spesifik (misal jadwal tunggal)
    if (!$rowJ) {
        $stmtFallback = $db->prepare("
            SELECT d.nama_dokter, p.nama_poli, j.jam_mulai, j.jam_selesai
            FROM jadwal_dokter j
            JOIN dokter d ON d.id_dokter = j.id_dokter
            JOIN poli p ON p.id_poli = j.id_poli
            WHERE j.id_dokter = ? AND j.id_poli = ? AND LOWER(TRIM(j.hari)) = LOWER(?) AND j.status = 'tersedia' AND d.status = 'aktif'
            ORDER BY j.jam_mulai ASC LIMIT 1
        ");
        if ($stmtFallback) {
            $stmtFallback->bind_param('iis', $idDokter, $idPoli, $hariKunjungan);
            $stmtFallback->execute();
            $rowJ = $stmtFallback->get_result()->fetch_assoc();
            $stmtFallback->close();
        }
    }
    if (!$rowJ) {
        kembaliDenganPesan("Dokter yang dipilih tidak memiliki jadwal praktik pada hari $hariKunjungan.");
    }
    $namaTenaga = $rowJ['nama_dokter'];
    $namaPoli = $rowJ['nama_poli'];
    $jamMulai = substr((string)$rowJ['jam_mulai'], 0, 5);
    $jamSelesai = substr((string)$rowJ['jam_selesai'], 0, 5);
} else {
    // Cari jadwal bidan yang spesifik dengan sesi
    $stmtJadwal = $db->prepare("
        SELECT b.nama_bidan, p.nama_poli, j.jam_mulai, j.jam_selesai
        FROM jadwal_bidan j
        JOIN bidan b ON b.id_bidan = j.id_bidan
        JOIN poli p ON p.id_poli = j.id_poli
        WHERE j.id_bidan = ? AND j.id_poli = ? AND LOWER(TRIM(j.hari)) = LOWER(?) $sesiTimeSql AND j.status = 'tersedia' AND b.status = 'aktif'
        ORDER BY j.jam_mulai ASC LIMIT 1
    ");
    $rowJ = null;
    if ($stmtJadwal) {
        $stmtJadwal->bind_param('iis', $idBidan, $idPoli, $hariKunjungan);
        $stmtJadwal->execute();
        $rowJ = $stmtJadwal->get_result()->fetch_assoc();
        $stmtJadwal->close();
    }
    // Fallback jika tidak ada sesi spesifik (misal jadwal tunggal)
    if (!$rowJ) {
        $stmtFallback = $db->prepare("
            SELECT b.nama_bidan, p.nama_poli, j.jam_mulai, j.jam_selesai
            FROM jadwal_bidan j
            JOIN bidan b ON b.id_bidan = j.id_bidan
            JOIN poli p ON p.id_poli = j.id_poli
            WHERE j.id_bidan = ? AND j.id_poli = ? AND LOWER(TRIM(j.hari)) = LOWER(?) AND j.status = 'tersedia' AND b.status = 'aktif'
            ORDER BY j.jam_mulai ASC LIMIT 1
        ");
        if ($stmtFallback) {
            $stmtFallback->bind_param('iis', $idBidan, $idPoli, $hariKunjungan);
            $stmtFallback->execute();
            $rowJ = $stmtFallback->get_result()->fetch_assoc();
            $stmtFallback->close();
        }
    }
    if (!$rowJ) {
        kembaliDenganPesan("Bidan yang dipilih tidak memiliki jadwal praktik pada hari $hariKunjungan.");
    }
    $namaTenaga = $rowJ['nama_bidan'];
    $namaPoli = $rowJ['nama_poli'];
    $jamMulai = substr((string)$rowJ['jam_mulai'], 0, 5);
    $jamSelesai = substr((string)$rowJ['jam_selesai'], 0, 5);
}

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$todayDate = $now->format('Y-m-d');
if ($tanggalKunjungan < $todayDate) {
    kembaliDenganPesan('Tanggal kunjungan sudah berlalu.');
}

$db->begin_transaction();

try {
    // 2. Simpan atau Dapatkan Data Pasien (Diferensiasi Sendiri vs Anggota Keluarga)
    if ($tipePasien === 'sendiri' && $idUser > 0) {
        // Ambil pasien yang tertaut ke akun user login
        $stmtP = $db->prepare('SELECT id_pasien FROM pasien WHERE id_user = ? LIMIT 1');
        if ($stmtP) {
            $stmtP->bind_param('i', $idUser);
            $stmtP->execute();
            $fP = $stmtP->get_result()->fetch_assoc();
            if ($fP) {
                $idPasien = (int)$fP['id_pasien'];
            }
            $stmtP->close();
        }
    } elseif ($tipePasien === 'keluarga') {
        // Anggota keluarga adalah pasien terpisah, cari berdasarkan NIK yang tidak tertaut ke akun user lain
        $stmtP = $db->prepare('SELECT id_pasien, id_user FROM pasien WHERE nik = ? LIMIT 1');
        if ($stmtP) {
            $stmtP->bind_param('s', $nik);
            $stmtP->execute();
            $fP = $stmtP->get_result()->fetch_assoc();
            if ($fP && empty($fP['id_user'])) {
                $idPasien = (int)$fP['id_pasien'];
            } else {
                $idPasien = 0; // Buat record pasien baru untuk anggota keluarga agar tidak menimpa akun user
            }
            $stmtP->close();
        }
    }

    if ($idPasien <= 0 && $tipePasien === 'sendiri') {
        $stmtP = $db->prepare('SELECT id_pasien FROM pasien WHERE nik = ? LIMIT 1');
        if ($stmtP) {
            $stmtP->bind_param('s', $nik);
            $stmtP->execute();
            $fP = $stmtP->get_result()->fetch_assoc();
            $idPasien = (int)($fP['id_pasien'] ?? 0);
            $stmtP->close();
        }
    }

    if ($idPasien > 0) {
        $stmtUp = $db->prepare("
            UPDATE pasien 
            SET nik = ?, nama_lengkap = ?, tanggal_lahir = ?, jenis_kelamin = ?, no_telepon = ?, email = ?, alamat = ?,
                pekerjaan = ?, status_perkawinan = ?, pendidikan_terakhir = ?, agama = ?, ada_rujukan = ?, asal_rujukan = ?
            WHERE id_pasien = ?
        ");
        if ($stmtUp) {
            $stmtUp->bind_param('sssssssssssssi', $nik, $nama, $tanggalLahir, $jenisKelamin, $telepon, $email, $alamat,
                $pekerjaan, $statusPerkawinan, $pendidikanTerakhir, $agama, $adaRujukan, $asalRujukan, $idPasien);
            $stmtUp->execute();
            $stmtUp->close();
        }
    } else {
        $stmtIn = $db->prepare("
            INSERT INTO pasien (id_user, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, no_telepon, email, alamat,
                                pekerjaan, status_perkawinan, pendidikan_terakhir, agama, ada_rujukan, asal_rujukan, reward_poin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 50)
        ");
        $bindUser = ($tipePasien === 'sendiri' && $idUser > 0) ? $idUser : null;
        if ($stmtIn) {
            $stmtIn->bind_param('isssssssssssss', $bindUser, $nik, $nama, $tanggalLahir, $jenisKelamin, $telepon, $email, $alamat,
                $pekerjaan, $statusPerkawinan, $pendidikanTerakhir, $agama, $adaRujukan, $asalRujukan);
            $stmtIn->execute();
            $idPasien = $stmtIn->insert_id;
            $stmtIn->close();
        }
    }

    if ($idPasien <= 0) {
        throw new Exception('Data pasien gagal diproses.');
    }

    // Jika mendaftar untuk diri sendiri, update session akun
    if ($tipePasien === 'sendiri' && $idUser > 0) {
        $_SESSION['nama_lengkap'] = $nama;
        $_SESSION['nik'] = $nik;
    }

    // 3. Hitung Jumlah Pendaftaran Sebelumnya & Nomor Antrean Online
    $staffCond = ($idDokter > 0) ? "id_dokter = ?" : "id_bidan = ?";
    $targetStaffId = ($idDokter > 0) ? $idDokter : $idBidan;

    $sessionFilterSql = $isSoreSession
        ? "AND jam_kunjungan >= '13:00:00'"
        : "AND jam_kunjungan < '13:00:00'";

    $stmtQueueCount = $db->prepare("
        SELECT COUNT(*) AS total_pasien,
               SUM(CASE WHEN sumber_pendaftaran = 'online' THEN 1 ELSE 0 END) AS online_count,
               SUM(CASE WHEN sumber_pendaftaran = 'offline' THEN 1 ELSE 0 END) AS offline_count
        FROM pendaftaran
        WHERE tanggal_kunjungan = ? AND id_poli = ? AND $staffCond AND status_pendaftaran != 'dibatalkan' $sessionFilterSql
    ");
    if (!$stmtQueueCount) {
        throw new Exception('Gagal menghitung antrean tenaga kesehatan.');
    }
    $stmtQueueCount->bind_param('sii', $tanggalKunjungan, $idPoli, $targetStaffId);
    $stmtQueueCount->execute();
    $cntRes = $stmtQueueCount->get_result()->fetch_assoc();
    $stmtQueueCount->close();

    $countBefore = (int)($cntRes['total_pasien'] ?? 0);
    $onlineBefore = (int)($cntRes['online_count'] ?? 0);
    $offlineBefore = (int)($cntRes['offline_count'] ?? 0);

    // Nomor antrean online: Pola 2 offline lalu 1 online yang menggunakan 3 nomor
    // Online 1 -> 003 (range internal 003–005)
    // Online 2 -> 008 (range internal 008–010)
    // Online k -> (k * 5) - 2
    $onlineIndex = $onlineBefore + 1;
    $nomorAntreanNumber = ($onlineIndex * 5) - 2;
    $nomorAntreanFormatted = str_pad((string)$nomorAntreanNumber, 3, '0', STR_PAD_LEFT);
    $nomorAntreanRange = $nomorAntreanFormatted . '–' . str_pad((string)($nomorAntreanNumber + 2), 3, '0', STR_PAD_LEFT);

    // 4. Hitung Estimasi Waktu Pelayanan Otomatis:
    // 1 Pasien = 1 pendaftaran = 15–20 menit pelayanan
    $doctorStartTs = strtotime("$tanggalKunjungan $jamMulai");
    $doctorEndTs = strtotime("$tanggalKunjungan $jamSelesai");

    $serviceStartTs = $doctorStartTs + ($countBefore * 20 * 60);
    $serviceEndTs = $serviceStartTs + (20 * 60);

    if ($serviceStartTs >= $doctorEndTs) {
        throw new Exception('Mohon maaf, kuota antrean pelayanan tenaga kesehatan pada tanggal ini sudah penuh.');
    }

    $jamKunjungan = date('H:i:s', $serviceStartTs);
    $estimasiMulai = date('H:i', $serviceStartTs);
    $estimasiSelesai = date('H:i', $serviceEndTs);
    $estimasiLabel = "$estimasiMulai – $estimasiSelesai WIB";

    $waktuDatangTs = $serviceStartTs - (15 * 60);
    $waktuDatang = date('H:i', $waktuDatangTs) . ' WIB';

    // Estimasi waktu tunggu ringkas: ± 15 menit
    $waktuTungguLabel = '± 15 menit';

    // 5. Simpan Record ke Tabel pendaftaran
    $valDokter = ($idDokter > 0) ? $idDokter : null;
    $valBidan  = ($idBidan > 0)  ? $idBidan  : null;

    $stmtReg = $db->prepare("
        INSERT INTO pendaftaran (
            id_pasien, id_dokter, id_bidan, id_poli,
            tanggal_kunjungan, jam_kunjungan,
            jenis_pasien, jenis_perawatan, sesi_kunjungan, nomor_antrean_jkn,
            keluhan, status_pendaftaran, sumber_pendaftaran
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu', 'online')
    ");
    if (!$stmtReg) {
        throw new Exception('Query pendaftaran tidak dapat disiapkan: ' . $db->error);
    }
    $stmtReg->bind_param('iiiisssssss', $idPasien, $valDokter, $valBidan, $idPoli, $tanggalKunjungan, $jamKunjungan, $jenisPasien, $jenisPerawatan, $sesiKunjungan, $nomorAntreanJkn, $keluhan);
    if (!$stmtReg->execute()) {
        throw new Exception('Gagal menyimpan pendaftaran: ' . $stmtReg->error);
    }
    $idPendaftaran = $stmtReg->insert_id;
    $stmtReg->close();

    // 6. Simpan Record ke Tabel antrean
    $stmtAntrean = $db->prepare("
        INSERT INTO antrean (id_pendaftaran, nomor_antrean, estimasi_waktu, status_antrean)
        VALUES (?, ?, ?, 'menunggu')
    ");
    if ($stmtAntrean) {
        $estimasiWaktuDb = date('H:i:s', $serviceStartTs);
        $stmtAntrean->bind_param('iis', $idPendaftaran, $nomorAntreanNumber, $estimasiWaktuDb);
        $stmtAntrean->execute();
        $stmtAntrean->close();
    }

    // 7. Simpan File Rujukan jika Ada
    if ($adaRujukan === 'Ya') {
        $uploadDir = __DIR__ . '/../uploads/rujukan';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $extMap = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $ext = $extMap[$mime] ?? 'pdf';
        $fileName = 'rujukan_' . $idPendaftaran . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (move_uploaded_file($fileRujukan['tmp_name'], $targetPath)) {
            $relPath = 'uploads/rujukan/' . $fileName;
            $stmtR = $db->prepare("INSERT INTO rujukan_pendaftaran (id_pendaftaran, nama_perujuk, file_rujukan) VALUES (?, ?, ?)");
            if ($stmtR) {
                $stmtR->bind_param('iss', $idPendaftaran, $namaPerujuk, $relPath);
                $stmtR->execute();
                $stmtR->close();
            }
        }
    }

    // 8. Tambahkan Poin Reward Pasien (+50 Poin)
    $db->query("UPDATE pasien SET reward_poin = COALESCE(reward_poin, 0) + 50 WHERE id_pasien = $idPasien");
    $curPoin = (int)($_SESSION['reward_poin'] ?? $_SESSION['user']['reward_poin'] ?? 0) + 50;
    $_SESSION['reward_poin'] = $curPoin;
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['reward_poin'] = $curPoin;
        $_SESSION['user']['poin'] = $curPoin;
    }

    $db->commit();

    // 9. Simpan ke Session untuk Bukti Pendaftaran
    $_SESSION['pendaftaran_berhasil'] = true;
    $_SESSION['hasil_pendaftaran_id'] = $idPendaftaran;
    $_SESSION['hasil_pendaftaran'] = [
        'id_pendaftaran'       => $idPendaftaran,
        'id_pasien'            => $idPasien,
        'nama_pasien'          => $nama,
        'nik'                  => $nik,
        'id_poli'              => $idPoli,
        'nama_poli'            => $namaPoli,
        'id_dokter'            => $idDokter,
        'id_bidan'             => $idBidan,
        'nama_tenaga'          => $namaTenaga,
        'tanggal_kunjungan'    => $tanggalKunjungan,
        'hari_kunjungan'       => $hariKunjungan,
        'jam_kunjungan'        => $jamKunjungan,
        'estimasi_pelayanan'   => $estimasiLabel,
        'estimasi_mulai'       => $estimasiMulai,
        'estimasi_selesai'     => $estimasiSelesai,
        'waktu_datang'         => $waktuDatang,
        'nomor_antrean'        => $nomorAntreanNumber,
        'nomor_antrean_format' => $nomorAntreanFormatted,
        'nomor_antrean_range'  => $nomorAntreanRange,
        'waktu_tunggu_label'   => $waktuTungguLabel,
        'antrean_di_depan'     => $countBefore,
        'jenis_pasien'         => $jenisPasien,
        'jenis_perawatan'      => $jenisPerawatan,
        'sesi_kunjungan'       => $sesiKunjungan,
        'nomor_antrean_jkn'    => $nomorAntreanJkn,
        'keluhan'              => $keluhan,
        'ada_rujukan'          => $adaRujukan,
        'nama_perujuk'         => $namaPerujuk,
        'sumber_pendaftaran'   => 'online'
    ];

    header('Location: hasil_daftar.php');
    exit;

} catch (Throwable $e) {
    $db->rollback();
    kembaliDenganPesan($e->getMessage());
}