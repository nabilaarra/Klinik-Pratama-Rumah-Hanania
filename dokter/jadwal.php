<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';
if (isset($conn) && $conn instanceof mysqli) $db = $conn; 
elseif (isset($koneksi) && $koneksi instanceof mysqli) $db = $koneksi; 
else die('Koneksi database tidak ditemukan.');
$db->set_charset('utf8mb4');
$allPoliMap = getAllPoliMap($db);

function eJ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function jamJ($v) { return substr((string)$v, 0, 5); }

// Tentukan hari saat ini dalam bahasa Indonesia
$hariIniEn = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('l');
$mapHari = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
$hariIni = $mapHari[$hariIniEn] ?? 'Senin';

$filterDokterId = (int)($_GET['dokter'] ?? 0);
$filterBidanId = (int)($_GET['bidan'] ?? 0);

$filteredStaffInfo = null;
if ($filterDokterId > 0) {
    $st = $db->prepare("SELECT id_dokter AS id, nama_dokter AS nama, spesialisasi AS peran, foto, 'Dokter' AS jenis, status FROM dokter WHERE id_dokter = ? AND status != 'nonaktif' LIMIT 1");
    if ($st) {
        $st->bind_param('i', $filterDokterId);
        $st->execute();
        $filteredStaffInfo = $st->get_result()->fetch_assoc();
        $st->close();
    }
} elseif ($filterBidanId > 0) {
    $st = $db->prepare("SELECT id_bidan AS id, nama_bidan AS nama, 'Bidan' AS peran, foto, 'Bidan' AS jenis, status FROM bidan WHERE id_bidan = ? AND status != 'nonaktif' LIMIT 1");
    if ($st) {
        $st->bind_param('i', $filterBidanId);
        $st->execute();
        $filteredStaffInfo = $st->get_result()->fetch_assoc();
        $st->close();
    }
}

// Urutkan hari secara dinamis sehingga HARI INI berada di paling atas
$baseHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$startIdx = array_search($hariIni, $baseHari);
if ($startIdx !== false) {
    $hariOrder = array_merge(array_slice($baseHari, $startIdx), array_slice($baseHari, 0, $startIdx));
} else {
    $hariOrder = $baseHari;
}

$data = [];

// Jadwal Dokter
if ($filterBidanId <= 0) {
    $docFilterSql = ($filterDokterId > 0) ? " AND jd.id_dokter = $filterDokterId " : "";
    $q = $db->query("
        SELECT jd.id_dokter AS id_tenaga, d.nama_dokter AS nama_tenaga, d.spesialisasi AS peran,
               jd.id_poli, p.nama_poli, jd.hari, jd.jam_mulai, jd.jam_selesai, jd.status, 'Dokter' AS jenis, d.status AS status_tenaga
        FROM jadwal_dokter jd
        INNER JOIN dokter d ON d.id_dokter = jd.id_dokter
        INNER JOIN poli p ON p.id_poli = jd.id_poli
        WHERE d.status != 'nonaktif' AND jd.status = 'tersedia' $docFilterSql
        ORDER BY FIELD(jd.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jd.jam_mulai ASC, d.nama_dokter ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) $data[] = $r;
    }
}

// Jadwal Bidan
if ($filterDokterId <= 0) {
    $bidFilterSql = ($filterBidanId > 0) ? " AND jb.id_bidan = $filterBidanId " : "";
    $q = $db->query("
        SELECT jb.id_bidan AS id_tenaga, b.nama_bidan AS nama_tenaga, 'Bidan' AS peran,
               jb.id_poli, p.nama_poli, jb.hari, jb.jam_mulai, jb.jam_selesai, jb.status, 'Bidan' AS jenis, b.status AS status_tenaga
        FROM jadwal_bidan jb
        INNER JOIN bidan b ON b.id_bidan = jb.id_bidan
        INNER JOIN poli p ON p.id_poli = jb.id_poli
        WHERE b.status != 'nonaktif' AND jb.status = 'tersedia' $bidFilterSql
        ORDER BY FIELD(jb.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), CASE WHEN b.nama_bidan LIKE '%Gita%' THEN 0 ELSE 1 END, jb.jam_mulai ASC, b.nama_bidan ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) $data[] = $r;
    }
}

$grouped = [];
foreach ($data as $r) {
    $grouped[$r['hari']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $filteredStaffInfo ? 'Jadwal Praktik ' . eJ($filteredStaffInfo['nama']) : 'Jadwal Dokter & Bidan' ?> - Klinik Pratama Rumah Hanania</title>
    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #FAF7FD;
            color: #352D3E;
        }
        .schedule-page {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 35px 0 70px;
        }
        .schedule-hero {
            padding: 48px 25px 56px;
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            text-align: center;
            border-bottom: 1px solid #E5D5FA;
        }
        .schedule-hero-inner {
            max-width: 800px;
            margin: auto;
        }
        .schedule-hero h1 {
            margin: 0 0 10px;
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 900;
            color: #2B1B48;
            letter-spacing: -0.5px;
        }
        .schedule-hero p {
            margin: 0;
            line-height: 1.7;
            font-size: 15px;
            color: #5D4D73;
        }

        .day-section {
            margin-bottom: 30px;
        }
        .day-section.is-today {
            background: #FAF5FF;
            border: 2px solid #cea2fd;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 12px 35px rgba(206, 162, 253, 0.25);
        }
        .day-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .day-title h2 {
            margin: 0;
            color: #5E2F9D;
            font-size: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .today-badge {
            padding: 6px 14px;
            border-radius: 999px;
            background: #10B981;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .schedule-table-wrap {
            overflow: auto;
            background: #fff;
            border: 1px solid #E5D5FA;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(70, 45, 90, 0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 880px;
        }
        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #F6EDFF;
            font-size: 13.5px;
            vertical-align: middle;
            white-space: nowrap;
        }
        th {
            background: #FAF5FF;
            color: #5E2F9D;
            font-size: 12.5px;
            font-weight: 800;
            letter-spacing: .2px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        tr:last-child td {
            border-bottom: 0;
        }
        tr:hover td {
            background: #FDFBFF;
        }
        .staff {
            font-weight: 800;
            color: #352D3E;
            white-space: nowrap;
        }
        .role {
            display: block;
            color: #7650A2;
            font-size: 11.5px;
            margin-top: 3px;
            font-weight: normal;
            white-space: nowrap;
        }
        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #EAF7EF;
            color: #10B981;
            font-size: 11.5px;
            font-weight: 800;
            white-space: nowrap;
        }
        .status-cuti {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FCA5A5;
            font-size: 11.5px;
            font-weight: 800;
            white-space: nowrap;
        }
        .btn-daftar-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
            background: linear-gradient(135deg, #cea2fd 0%, #ad6ef5 100%);
            color: #2E1065;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(206, 162, 253, 0.35);
        }
        .btn-daftar-row:hover {
            background: linear-gradient(135deg, #bf8afb 0%, #9955e8 100%);
            color: #fff;
            transform: translateY(-1px);
        }
        .empty {
            padding: 22px;
            background: #fff;
            border: 1px dashed #DCCFE8;
            border-radius: 14px;
            color: #8E7F9B;
            font-size: 13px;
            text-align: center;
        }
        .schedule-mobile-list {
            display: none;
        }
        @media(max-width: 720px) {
            .schedule-hero { padding: 32px 16px 36px; }
            .schedule-hero h1 { font-size: 24px; }
            .schedule-hero p { font-size: 13.5px; }
            .schedule-page { padding-top: 18px; padding-left: 14px; padding-right: 14px; }
            .day-section { margin-bottom: 18px; }
            .day-section.is-today { padding: 14px; border-radius: 14px; }
            .day-title { align-items: center; flex-direction: row; justify-content: space-between; margin-bottom: 10px; gap: 8px; }
            .day-title h2 { font-size: 18px; }
            .today-badge { padding: 4px 10px; font-size: 11px; }
            .schedule-table-wrap { display: none !important; }
            .schedule-mobile-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .schedule-card-mobile {
                background: #fff;
                border: 1px solid #E5D5FA;
                border-radius: 12px;
                padding: 12px 14px;
                box-shadow: 0 2px 8px rgba(70, 45, 90, 0.04);
            }
            .schedule-card-mobile.is-cuti {
                background: #FFFBFB;
                border-color: #FECACA;
            }
            .scm-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 8px;
            }
            .scm-name {
                font-weight: 800;
                color: #352D3E;
                font-size: 13.5px;
                line-height: 1.3;
            }
            .scm-role {
                color: #7650A2;
                font-size: 11.5px;
                margin-top: 2px;
            }
            .scm-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 8px;
                border-top: 1px dashed #F0E6FA;
                gap: 8px;
            }
            .scm-time {
                font-size: 12px;
                color: #4F3293;
                display: flex;
                align-items: center;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="schedule-hero">
    <div class="schedule-hero-inner">
        <h1><?= $filteredStaffInfo ? 'Jadwal Praktik: ' . eJ($filteredStaffInfo['nama']) : 'Jadwal Dokter &amp; Bidan' ?></h1>
        <p><?= $filteredStaffInfo ? 'Informasi hari dan jam praktik ' . eJ($filteredStaffInfo['nama']) . ' di Klinik Pratama Rumah Hanania.' : 'Jadwal pelayanan klinik diurutkan mulai dari hari ini agar Anda dapat langsung melihat ketersediaan tenaga medis yang berpraktik.' ?></p>
    </div>
</section>

<main class="schedule-page">
    <?php if ($filteredStaffInfo): ?>
        <div style="background:#fff;border:2px solid #cea2fd;border-radius:18px;padding:18px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;box-shadow:0 8px 25px rgba(206,162,253,0.25);">
            <div style="display:flex;align-items:center;gap:16px;">
                <div style="width:54px;height:54px;border-radius:14px;background:#F6EDFF;display:flex;align-items:center;justify-content:center;font-size:26px;border:1px solid #E5D5FA;">
                    <?= $filteredStaffInfo['jenis'] === 'Dokter' ? '👨‍⚕️' : '👩‍⚕️' ?>
                </div>
                <div>
                    <span style="font-size:11px;font-weight:800;color:#5E2F9D;text-transform:uppercase;letter-spacing:.3px;">Jadwal Khusus <?= $filteredStaffInfo['jenis'] ?></span>
                    <h2 style="margin:2px 0 0;font-size:20px;font-weight:900;color:#32283D;"><?= eJ($filteredStaffInfo['nama']) ?></h2>
                    <div style="font-size:12.5px;color:#6B6178;"><?= eJ($filteredStaffInfo['peran']) ?></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="jadwal.php" style="padding:10px 18px;border-radius:10px;background:#F6EDFF;color:#5E2F9D;text-decoration:none;font-size:13px;font-weight:800;border:1px solid #E5D5FA;">← Lihat Semua Jadwal</a>
                <?php if ($filteredStaffInfo['status'] !== 'cuti'): ?>
                    <a href="../pasien/daftar.php?<?= strtolower($filteredStaffInfo['jenis']) ?>=<?= (int)$filteredStaffInfo['id'] ?>" style="padding:10px 18px;border-radius:10px;background:linear-gradient(135deg, #cea2fd 0%, #ad6ef5 100%);color:#2E1065;text-decoration:none;font-size:13px;font-weight:800;box-shadow:0 4px 12px rgba(206,162,253,.45);">Daftar ke <?= $filteredStaffInfo['jenis'] ?></a>
                <?php else: ?>
                    <span style="padding:10px 18px;border-radius:10px;background:#FEE2E2;color:#DC2626;font-size:13px;font-weight:800;border:1px solid #FCA5A5;">🔴 Sedang Cuti</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($hariOrder as $index => $hari): 
        $isToday = ($hari === $hariIni);
        $listHari = $grouped[$hari] ?? [];
        if ($filteredStaffInfo && empty($listHari)) {
            continue; // jika sedang filter spesifik dokter dan di hari ini tidak ada jadwal, lewati
        }
    ?>
        <section class="day-section <?= $isToday ? 'is-today' : '' ?>">
            <div class="day-title">
                <h2>
                    <span>📅</span> <?= eJ($hari) ?>
                </h2>
                <?php if ($isToday): ?>
                    <span class="today-badge">
                        <span>●</span> Hari Ini
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($listHari)): ?>
                <!-- Desktop Table View -->
                <div class="schedule-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tenaga Medis</th>
                                <th>Jenis</th>
                                <th>Poli</th>
                                <th>Jam Praktik</th>
                                <th>Status</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                                <?php foreach ($listHari as $row): 
                                $isStaffCuti = (($row['status_tenaga'] ?? '') === 'cuti');
                                $poliItem = $allPoliMap[(int)($row['id_poli'] ?? 0)] ?? null;
                                $statusJadwal = cekStatusJadwalPoli($poliItem, $row['jam_mulai'], $row['jam_selesai'], $isToday, $row['peran'] ?? '');
                                $isClosed = $statusJadwal['is_closed'] || (($row['status'] ?? 'tersedia') !== 'tersedia');
                            ?>
                                <tr>
                                    <td>
                                        <span class="staff"><?= eJ($row['nama_tenaga']) ?></span>
                                        <span class="role"><?= eJ($row['peran']) ?></span>
                                    </td>
                                    <td><?= eJ($row['jenis']) ?></td>
                                    <td><?= eJ($row['nama_poli']) ?></td>
                                    <td>
                                        <?php if (!empty($statusJadwal['is_24jam'])): ?>
                                            <strong style="color:#6D28D9;">Siaga 24 Jam Non-Stop</strong>
                                        <?php else: ?>
                                            <strong><?= jamJ($row['jam_mulai']) ?> – <?= jamJ($row['jam_selesai']) ?> WIB</strong>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isStaffCuti): ?>
                                             <span class="status-cuti">🔴 Sedang Cuti</span>
                                        <?php elseif (!empty($statusJadwal['is_24jam'])): ?>
                                             <span class="status" style="background:#EDE9FE;color:#6D28D9;border:1px solid #DDD6FE;font-weight:800;">✨ 24 Jam</span>
                                        <?php elseif ($isClosed): ?>
                                             <span class="status-cuti" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;" title="<?= eJ($statusJadwal['message']) ?>">⛔ Ditutup (1 Jam Sblm Selesai)</span>
                                        <?php else: ?>
                                             <span class="status">Tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if ($isStaffCuti): ?>
                                            <button type="button" class="btn-daftar-row" style="background:#FEE2E2;color:#DC2626;cursor:not-allowed;border:1px solid #FCA5A5;box-shadow:none;" disabled>Sedang Cuti</button>
                                        <?php elseif ($isClosed): ?>
                                            <button type="button" class="btn-daftar-row" style="background:#F3F4F6;color:#9CA3AF;cursor:not-allowed;border:1px solid #E5E7EB;box-shadow:none;" disabled title="<?= eJ($statusJadwal['message']) ?>">⛔ Ditutup</button>
                                        <?php else: ?>
                                            <a class="btn-daftar-row" href="../pasien/daftar.php?<?= strtolower($row['jenis']) ?>=<?= (int)$row['id_tenaga'] ?><?= !empty($statusJadwal['is_24jam']) ? '&layanan=persalinan' : '' ?>">
                                                <?= !empty($statusJadwal['is_24jam']) ? 'Daftar Persalinan &rarr;' : 'Daftar &rarr;' ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="schedule-mobile-list">
                    <?php foreach ($listHari as $row): 
                        $isStaffCuti = (($row['status_tenaga'] ?? '') === 'cuti');
                        $poliItem = $allPoliMap[(int)($row['id_poli'] ?? 0)] ?? null;
                        $statusJadwal = cekStatusJadwalPoli($poliItem, $row['jam_mulai'], $row['jam_selesai'], $isToday, $row['peran'] ?? '');
                        $isClosed = $statusJadwal['is_closed'] || (($row['status'] ?? 'tersedia') !== 'tersedia');
                    ?>
                        <div class="schedule-card-mobile <?= ($isStaffCuti || $isClosed) ? 'is-cuti' : '' ?>">
                            <div class="scm-header">
                                <div>
                                    <div class="scm-name"><?= eJ($row['nama_tenaga']) ?></div>
                                    <div class="scm-role"><?= eJ($row['peran']) ?> • <?= eJ($row['nama_poli']) ?></div>
                                </div>
                                <div>
                                    <?php if ($isStaffCuti): ?>
                                        <span class="status-cuti" style="font-size:10px;padding:3px 8px;">🔴 Cuti</span>
                                    <?php elseif (!empty($statusJadwal['is_24jam'])): ?>
                                        <span class="status" style="background:#EDE9FE;color:#6D28D9;border:1px solid #DDD6FE;font-size:10px;padding:3px 8px;font-weight:800;">✨ 24 Jam</span>
                                    <?php elseif ($isClosed): ?>
                                        <span class="status-cuti" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;font-size:10px;padding:3px 8px;">⛔ Ditutup</span>
                                    <?php else: ?>
                                        <span class="status" style="font-size:10px;padding:3px 8px;">Tersedia</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="scm-footer">
                                <div class="scm-time">
                                    <span style="font-size:12px;">⏰</span> 
                                    <?php if (!empty($statusJadwal['is_24jam'])): ?>
                                        <strong style="color:#6D28D9;">Siaga 24 Jam Non-Stop</strong>
                                    <?php else: ?>
                                        <strong><?= jamJ($row['jam_mulai']) ?> – <?= jamJ($row['jam_selesai']) ?> WIB</strong>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($isStaffCuti): ?>
                                        <button type="button" class="btn-daftar-row" style="background:#FEE2E2;color:#DC2626;cursor:not-allowed;border:1px solid #FCA5A5;box-shadow:none;padding:5px 10px;font-size:11px;" disabled>Cuti</button>
                                    <?php elseif ($isClosed): ?>
                                        <button type="button" class="btn-daftar-row" style="background:#F3F4F6;color:#9CA3AF;cursor:not-allowed;border:1px solid #E5E7EB;box-shadow:none;padding:5px 10px;font-size:11px;" disabled>⛔ Ditutup</button>
                                    <?php else: ?>
                                        <a class="btn-daftar-row" href="../pasien/daftar.php?<?= strtolower($row['jenis']) ?>=<?= (int)$row['id_tenaga'] ?><?= !empty($statusJadwal['is_24jam']) ? '&layanan=persalinan' : '' ?>" style="padding:6px 12px;font-size:11.5px;">
                                            <?= !empty($statusJadwal['is_24jam']) ? 'Daftar Persalinan &rarr;' : 'Daftar &rarr;' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    Tidak ada jadwal praktik pelayanan pada hari <?= eJ($hari) ?>.
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
