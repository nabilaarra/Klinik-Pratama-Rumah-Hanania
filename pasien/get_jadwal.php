<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';
require_once __DIR__ . '/../includes/antrean_helper.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    echo json_encode(['success' => false, 'error' => 'Koneksi database tidak ditemukan.']);
    exit;
}
$db->set_charset('utf8mb4');

$tanggal = trim($_GET['tanggal'] ?? '');
$targetDoctor = (int)($_GET['dokter'] ?? 0);
$targetBidan = (int)($_GET['bidan'] ?? 0);
$targetPoli = (int)($_GET['poli'] ?? 0);
$targetSesi = trim($_GET['sesi'] ?? '');

$date = DateTime::createFromFormat('Y-m-d', $tanggal, new DateTimeZone('Asia/Jakarta'));
if (!$date || $date->format('Y-m-d') !== $tanggal) {
    echo json_encode(['success' => false, 'hari' => '', 'tenaga' => [], 'error' => 'Format tanggal tidak valid.']);
    exit;
}

$hariMap = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hari = $hariMap[$date->format('l')] ?? '';

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$isToday = ($tanggal === $now->format('Y-m-d'));
$isPast = ($tanggal < $now->format('Y-m-d'));

// Ambil riwayat pendaftaran aktif pasien yang login pada tanggal ini
$sessionPatientId = (int)($_SESSION['id_pasien'] ?? 0);
$sessionUserId = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);
if ($sessionPatientId <= 0 && $sessionUserId > 0) {
    $pStmt = $db->prepare('SELECT id_pasien FROM pasien WHERE id_user = ? LIMIT 1');
    if ($pStmt) {
        $pStmt->bind_param('i', $sessionUserId);
        $pStmt->execute();
        $pRow = $pStmt->get_result()->fetch_assoc();
        $sessionPatientId = (int)($pRow['id_pasien'] ?? 0);
        $pStmt->close();
    }
}
$patientRegistrationCount = 0;
if ($sessionPatientId > 0) {
    $regStmt = $db->prepare("SELECT COUNT(*) AS total FROM pendaftaran WHERE id_pasien = ? AND tanggal_kunjungan = ? AND status_pendaftaran NOT IN ('dibatalkan', 'selesai')");
    if ($regStmt) {
        $regStmt->bind_param('is', $sessionPatientId, $tanggal);
        $regStmt->execute();
        $regRow = $regStmt->get_result()->fetch_assoc();
        $patientRegistrationCount = (int)($regRow['total'] ?? 0);
        $regStmt->close();
    }
}

// Cek ketersediaan kolom sumber_pendaftaran
$hasSourceCol = false;
$chkCol = @$db->query("SHOW COLUMNS FROM pendaftaran LIKE 'sumber_pendaftaran'");
if ($chkCol && $chkCol->num_rows > 0) {
    $hasSourceCol = true;
}

$queries = [
    [
        "SELECT jd.id_jadwal_dokter AS id_jadwal, jd.id_dokter AS id_tenaga, d.nama_dokter AS nama_tenaga,
                d.spesialisasi AS peran, jd.id_poli, p.nama_poli, jd.jam_mulai, jd.jam_selesai, jd.status, 'Dokter' AS jenis
         FROM jadwal_dokter jd
         INNER JOIN dokter d ON d.id_dokter = jd.id_dokter
         LEFT JOIN poli p ON p.id_poli = jd.id_poli
         WHERE d.status = 'aktif' AND LOWER(TRIM(jd.hari)) = LOWER(?)",
        'Dokter'
    ],
    [
        "SELECT jb.id_jadwal_bidan AS id_jadwal, jb.id_bidan AS id_tenaga, b.nama_bidan AS nama_tenaga,
                'Bidan' AS peran, jb.id_poli, p.nama_poli, jb.jam_mulai, jb.jam_selesai, jb.status, 'Bidan' AS jenis
         FROM jadwal_bidan jb
         INNER JOIN bidan b ON b.id_bidan = jb.id_bidan
         LEFT JOIN poli p ON p.id_poli = jb.id_poli
         WHERE b.status = 'aktif' AND LOWER(TRIM(jb.hari)) = LOWER(?)",
        'Bidan'
    ]
];

$tenagaList = [];

foreach ($queries as [$sql, $jenis]) {
    $stmt = $db->prepare($sql);
    if (!$stmt) continue;
    $stmt->bind_param('s', $hari);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $idTenaga = (int)$row['id_tenaga'];
        $idPoli = (int)$row['id_poli'];
        $jamMulai = substr((string)$row['jam_mulai'], 0, 5);
        $jamSelesai = substr((string)$row['jam_selesai'], 0, 5);

        // Filter jika user spesifik memilih dokter atau bidan tertentu
        if ($targetDoctor > 0 && $jenis === 'Dokter' && $idTenaga !== $targetDoctor) continue;
        if ($targetBidan > 0 && $jenis === 'Bidan' && $idTenaga !== $targetBidan) continue;
        if ($targetPoli > 0 && $idPoli !== $targetPoli) continue;

        if ($targetSesi !== '') {
            $startH = (int)substr((string)$row['jam_mulai'], 0, 2);
            if ($targetSesi === 'Pagi' && $startH >= 13) continue;
            if (($targetSesi === 'Sore' || $targetSesi === 'Sore/Malam') && $startH < 13) continue;
        }

        // 1. Hitung jumlah pendaftaran yang SUDAH MASUK sebelum pendaftaran ini
        // pada tanggal + dokter/bidan + poli tersebut yang tidak dibatalkan (terisolasi per sesi pagi vs sore)
        $startH = (int)substr((string)$row['jam_mulai'], 0, 2);
        $sessionFilterSql = ($startH >= 13) ? "AND jam_kunjungan >= '13:00:00'" : "AND jam_kunjungan < '13:00:00'";
        $staffCondition = ($jenis === 'Dokter') ? "id_dokter = ?" : "id_bidan = ?";
        
        $countQuery = $hasSourceCol
            ? "SELECT COUNT(*) AS total_pasien,
                      SUM(CASE WHEN sumber_pendaftaran = 'online' THEN 1 ELSE 0 END) AS online_count,
                      SUM(CASE WHEN sumber_pendaftaran = 'offline' THEN 1 ELSE 0 END) AS offline_count
               FROM pendaftaran
               WHERE tanggal_kunjungan = ? AND id_poli = ? AND $staffCondition AND status_pendaftaran != 'dibatalkan' $sessionFilterSql"
            : "SELECT COUNT(*) AS total_pasien, COUNT(*) AS online_count, 0 AS offline_count
               FROM pendaftaran
               WHERE tanggal_kunjungan = ? AND id_poli = ? AND $staffCondition AND status_pendaftaran != 'dibatalkan' $sessionFilterSql";

        $stmtCount = $db->prepare($countQuery);
        $countBefore = 0;
        $onlineBefore = 0;
        $offlineBefore = 0;

        if ($stmtCount) {
            $stmtCount->bind_param('sii', $tanggal, $idPoli, $idTenaga);
            $stmtCount->execute();
            $cntRow = $stmtCount->get_result()->fetch_assoc();
            $countBefore = (int)($cntRow['total_pasien'] ?? 0);
            $onlineBefore = (int)($cntRow['online_count'] ?? 0);
            $offlineBefore = (int)($cntRow['offline_count'] ?? 0);
            $stmtCount->close();
        }

        // 2. Hitung Nomor Antrean Online berdasarkan aturan urutan:
        // Pola: 1-2 offline, 3-5 online, 6-7 offline, 8-10 online, dst.
        $calcAntrean = hitungNomorAntreanBerikutnya(
            $db,
            $tanggal,
            $idPoli,
            ($jenis === 'Dokter' ? $idTenaga : null),
            ($jenis === 'Bidan' ? $idTenaga : null),
            'online',
            $jamMulai
        );
        $nomorAntreanNumber = $calcAntrean['nomor'];
        $nomorAntreanFormatted = $calcAntrean['format'];
        $nomorAntreanRange = $nomorAntreanFormatted;

        // 3. Hitung Estimasi Pelayanan Otomatis:
        // 1 Pasien = 1 pendaftaran = 15-20 menit pelayanan
        $doctorStartTs = strtotime("$tanggal $jamMulai");
        $doctorEndTs = strtotime("$tanggal $jamSelesai");

        // Estimasi mulai = jam mulai dokter + (jumlah pasien sebelumnya * 20 menit)
        $serviceStartTs = $doctorStartTs + ($countBefore * 20 * 60);
        $serviceEndTs = $serviceStartTs + (20 * 60);

        $estimasiMulai = date('H:i', $serviceStartTs);
        $estimasiSelesai = date('H:i', $serviceEndTs);
        $estimasiLabel = "$estimasiMulai – $estimasiSelesai WIB";

        // Waktu Datang = 15 menit sebelum estimasi pelayanan dimulai
        $waktuDatangTs = $serviceStartTs - (15 * 60);
        $waktuDatang = date('H:i', $waktuDatangTs) . ' WIB';

        // Estimasi waktu tunggu ringkas sesuai instruksi: ± 15 menit
        $waktuTungguMenit = 15;
        $waktuTungguLabel = '± 15 menit';

        // Cek ketersediaan kuota & status operasional poli (tutup 1 jam sebelum selesai, persalinan 24 jam nonstop)
        $allPoliMap = getAllPoliMap($db);
        $poliData = $allPoliMap[$idPoli] ?? ['id_poli' => $idPoli, 'nama_poli' => $namaPoli];
        $targetLayanan = trim($_GET['layanan'] ?? '');
        $statusCheck = cekStatusJadwalPoli($poliData, $jamMulai, $jamSelesai, $isToday, $targetLayanan);

        $available = true;
        $keterangan = 'Pendaftaran Tersedia';
        $statusLabel = 'Pendaftaran Tersedia';

        $scheduleStatus = strtolower(trim((string)($row['status'] ?? 'tersedia')));

        if ($isPast) {
            $available = false;
            $keterangan = 'Pendaftaran Ditutup (Tanggal kunjungan sudah berlalu)';
            $statusLabel = 'Pendaftaran Ditutup';
        } elseif ($scheduleStatus === 'ditutup' || $scheduleStatus === 'tidak_tersedia' || $scheduleStatus === 'nonaktif') {
            $available = false;
            $keterangan = 'Pendaftaran Ditutup (Jadwal Tenaga Medis Sedang Ditutup)';
            $statusLabel = 'Pendaftaran Ditutup';
        } elseif ($scheduleStatus === 'cuti') {
            $available = false;
            $keterangan = 'Pendaftaran Ditutup (Tenaga Medis Sedang Cuti / Libur Praktik)';
            $statusLabel = 'Cuti / Libur';
        } elseif ($scheduleStatus === 'full_booking' || ($statusCheck['status_operasional'] ?? '') === 'full_booking') {
            $available = false;
            $keterangan = 'Full Booking (Kuota Praktik Telah Penuh)';
            $statusLabel = 'Full Booking';
        } elseif ($statusCheck['is_24jam']) {
            $available = true;
            $keterangan = 'Pendaftaran Tersedia (Layanan Persalinan 24 Jam Selalu Siaga Nonstop)';
            $statusLabel = 'Pendaftaran Tersedia';
        } elseif ($statusCheck['is_closed']) {
            $available = false;
            $keterangan = $statusCheck['keterangan'] ?: 'Pendaftaran Ditutup';
            $statusLabel = $statusCheck['status_label'] ?: 'Pendaftaran Ditutup';
        } elseif ($serviceStartTs >= $doctorEndTs) {
            $available = false;
            $keterangan = 'Full Booking';
            $statusLabel = 'Full Booking';
        } else {
            $available = true;
            $keterangan = $statusCheck['keterangan'] ?: 'Pendaftaran Tersedia';
            $statusLabel = 'Pendaftaran Tersedia';
        }

        $jadwalTersisaMinggu = [];
        if ($isToday && !$available) {
            $tableJ = ($jenis === 'Dokter') ? 'jadwal_dokter' : 'jadwal_bidan';
            $colId = ($jenis === 'Dokter') ? 'id_dokter' : 'id_bidan';
            $dayNumMap = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];

            $sSql = "SELECT hari, jam_mulai, jam_selesai FROM $tableJ WHERE $colId = ? AND id_poli = ? AND status = 'tersedia' ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jam_mulai ASC";
            $stRem = $db->prepare($sSql);
            if ($stRem) {
                $stRem->bind_param('ii', $idTenaga, $idPoli);
                $stRem->execute();
                $resRem = $stRem->get_result();
                while ($rRow = $resRem->fetch_assoc()) {
                    $hName = $rRow['hari'];
                    $targetW = $dayNumMap[$hName] ?? -1;
                    if ($targetW >= 0) {
                        for ($d = 1; $d <= 7; $d++) {
                            $futureTs = strtotime("+$d days", $now->getTimestamp());
                            if ((int)date('w', $futureTs) === $targetW) {
                                $futureDate = date('Y-m-d', $futureTs);
                                $jadwalTersisaMinggu[] = [
                                    'hari' => $hName,
                                    'tanggal' => $futureDate,
                                    'tanggal_label' => $hName . ', ' . date('d-m-Y', $futureTs),
                                    'jam_mulai' => substr($rRow['jam_mulai'], 0, 5),
                                    'jam_selesai' => substr($rRow['jam_selesai'], 0, 5),
                                    'jam_label' => substr($rRow['jam_mulai'], 0, 5) . ' – ' . substr($rRow['jam_selesai'], 0, 5) . ' WIB'
                                ];
                                break;
                            }
                        }
                    }
                }
                $stRem->close();
            }
        }

        $sesiList = [
            [
                'label' => "$jamMulai – $jamSelesai WIB",
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'estimasi_pelayanan' => $estimasiLabel,
                'waktu_datang' => $waktuDatang,
                'nomor_antrean' => $nomorAntreanFormatted,
                'nomor_range' => $nomorAntreanRange,
                'tersedia' => $available
            ]
        ];

        $tenagaList[] = [
            'id_jadwal' => (int)$row['id_jadwal'],
            'id_tenaga' => $idTenaga,
            'nama_tenaga' => $row['nama_tenaga'],
            'peran' => $row['peran'] ?? $jenis,
            'id_poli' => $idPoli,
            'nama_poli' => $row['nama_poli'] ?? '',
            'jenis' => $jenis,
            'hari' => $hari,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'jam_operasional_label' => "$hari, $jamMulai – $jamSelesai WIB",
            'nomor_antrean' => $nomorAntreanFormatted,
            'nomor_antrean_range' => $nomorAntreanRange,
            'nomor_antrean_angka' => $nomorAntreanNumber,
            'estimasi_mulai' => $estimasiMulai,
            'estimasi_selesai' => $estimasiSelesai,
            'estimasi_pelayanan' => $estimasiLabel,
            'waktu_datang' => $waktuDatang,
            'waktu_tunggu_menit' => $waktuTungguMenit,
            'waktu_tunggu_label' => $waktuTungguLabel,
            'antrean_di_depan' => $countBefore,
            'tersedia' => $available,
            'status_label' => $statusLabel,
            'is_24jam' => (bool)$statusCheck['is_24jam'],
            'keterangan_ketersediaan' => $keterangan,
            'sesi' => $sesiList,
            'jadwal_tersisa_minggu' => $jadwalTersisaMinggu
        ];
    }
    $stmt->close();
}

// Urutkan tenaga: Dokter terlebih dahulu, lalu Bidan, dan alfabet nama
usort($tenagaList, function ($a, $b) {
    $typeOrder = ($a['jenis'] === 'Dokter' ? 0 : 1) <=> ($b['jenis'] === 'Dokter' ? 0 : 1);
    if ($typeOrder !== 0) return $typeOrder;
    return $a['nama_tenaga'] <=> $b['nama_tenaga'];
});

echo json_encode([
    'success' => true,
    'tanggal' => $tanggal,
    'hari' => $hari,
    'tenaga' => $tenagaList,
    'pendaftaran_pasien' => $patientRegistrationCount
], JSON_UNESCAPED_UNICODE);
