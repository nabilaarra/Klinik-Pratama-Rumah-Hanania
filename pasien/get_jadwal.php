<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
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
         WHERE d.status = 'aktif' AND jd.status = 'tersedia' AND LOWER(TRIM(jd.hari)) = LOWER(?)",
        'Dokter'
    ],
    [
        "SELECT jb.id_jadwal_bidan AS id_jadwal, jb.id_bidan AS id_tenaga, b.nama_bidan AS nama_tenaga,
                'Bidan' AS peran, jb.id_poli, p.nama_poli, jb.jam_mulai, jb.jam_selesai, jb.status, 'Bidan' AS jenis
         FROM jadwal_bidan jb
         INNER JOIN bidan b ON b.id_bidan = jb.id_bidan
         LEFT JOIN poli p ON p.id_poli = jb.id_poli
         WHERE b.status = 'aktif' AND jb.status = 'tersedia' AND LOWER(TRIM(jb.hari)) = LOWER(?)",
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
        // Pola: 2 offline lalu 1 online yang menggunakan 3 nomor
        // Online 1 -> 003 (range 003–005)
        // Online 2 -> 008 (range 008–010)
        // Online k -> (k * 5) - 2
        $onlineIndex = $onlineBefore + 1;
        $nomorAntreanNumber = ($onlineIndex * 5) - 2;
        $nomorAntreanFormatted = str_pad((string)$nomorAntreanNumber, 3, '0', STR_PAD_LEFT);
        $nomorAntreanRange = $nomorAntreanFormatted . '–' . str_pad((string)($nomorAntreanNumber + 2), 3, '0', STR_PAD_LEFT);

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

        // Cek ketersediaan kuota / batas operasional dokter
        $available = true;
        $keterangan = 'Tersedia';

        if ($isPast) {
            $available = false;
            $keterangan = 'Tanggal pemeriksaan sudah berlalu';
        } elseif ($serviceStartTs >= $doctorEndTs) {
            $available = false;
            $keterangan = 'Kuota antrean hari ini sudah penuh sesuai jam praktik dokter';
        } elseif ($isToday) {
            // Jika hari ini, pendaftaran ditutup 1 jam sebelum estimasi pelayanan atau jika sudah lewat
            $cutoffTs = $serviceStartTs - (60 * 60);
            if ($now->getTimestamp() >= $cutoffTs) {
                $available = false;
                $keterangan = 'Pendaftaran online ditutup (minimal 1 jam sebelum estimasi pelayanan)';
            }
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
