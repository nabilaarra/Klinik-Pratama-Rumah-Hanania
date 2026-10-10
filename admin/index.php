<?php
require_once __DIR__ . '/_guard.php';

// Aksi cepat update status pendaftaran dari dashboard
$quickMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $idPendaftaran = (int)($_POST['id_pendaftaran'] ?? 0);
    $newStatus = trim($_POST['status_pendaftaran'] ?? '');
    $allowed = ['menunggu', 'terdaftar', 'selesai', 'dibatalkan'];

    if ($idPendaftaran > 0 && in_array($newStatus, $allowed, true)) {
        $stmt = $db->prepare("UPDATE pendaftaran SET status_pendaftaran = ? WHERE id_pendaftaran = ?");
        if ($stmt) {
            $stmt->bind_param('si', $newStatus, $idPendaftaran);
            $stmt->execute();
            $stmt->close();
            $quickMessage = "Status pendaftaran #$idPendaftaran berhasil diperbarui menjadi $newStatus.";
        }
    }
}

// 1. STATISTIK REAL DARI DATABASE
$totalPasien = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM pasien");
if ($res && $r = $res->fetch_assoc()) $totalPasien = (int)$r['c'];

$totalDokter = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM dokter WHERE status='aktif'");
if ($res && $r = $res->fetch_assoc()) $totalDokter = (int)$r['c'];

$totalBidan = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM bidan WHERE status='aktif'");
if ($res && $r = $res->fetch_assoc()) $totalBidan = (int)$r['c'];

$today = date('Y-m-d');
$pendaftaranHariIni = 0;
$res = $db->prepare("SELECT COUNT(*) AS c FROM pendaftaran WHERE tanggal_kunjungan = ? OR DATE(created_at) = ?");
if ($res) {
    $res->bind_param('ss', $today, $today);
    $res->execute();
    $r = $res->get_result()->fetch_assoc();
    $pendaftaranHariIni = (int)($r['c'] ?? 0);
    $res->close();
}

$antreanBerjalan = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM pendaftaran WHERE status_pendaftaran IN ('menunggu', 'terdaftar')");
if ($res && $r = $res->fetch_assoc()) $antreanBerjalan = (int)$r['c'];

$pelayananSelesai = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM pendaftaran WHERE status_pendaftaran = 'selesai'");
if ($res && $r = $res->fetch_assoc()) $pelayananSelesai = (int)$r['c'];

$pendaftaranOnline = 0;
$res = $db->query("SELECT COUNT(*) AS c FROM pendaftaran WHERE sumber_pendaftaran = 'online'");
if ($res && $r = $res->fetch_assoc()) $pendaftaranOnline = (int)$r['c'];

// 2. RINGKASAN TREN PENDAFTARAN MINGGUAN (7 HARI TERAKHIR)
$grafikMingguan = [];
$maxGrafik = 1;
for ($i = 6; $i >= 0; $i--) {
    $tglLoop = date('Y-m-d', strtotime("-$i days"));
    $hariLoop = date('D', strtotime("-$i days"));
    $hariIndoMap = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];
    $labelHari = $hariIndoMap[$hariLoop] ?? $hariLoop;
    $labelTgl = date('d/m', strtotime("-$i days"));

    $stmtG = $db->prepare("SELECT COUNT(*) AS c FROM pendaftaran WHERE tanggal_kunjungan = ?");
    $totalLoop = 0;
    if ($stmtG) {
        $stmtG->bind_param('s', $tglLoop);
        $stmtG->execute();
        $gRes = $stmtG->get_result()->fetch_assoc();
        $totalLoop = (int)($gRes['c'] ?? 0);
        $stmtG->close();
    }
    if ($totalLoop > $maxGrafik) $maxGrafik = $totalLoop;

    $grafikMingguan[] = [
        'tanggal' => $tglLoop,
        'label' => $labelHari . ' ' . $labelTgl,
        'total' => $totalLoop
    ];
}

// 3. JADWAL PRAKTIK HARI INI
$hariMap = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hariIni = $hariMap[date('l')] ?? 'Senin';

$jadwalHariIni = [];
$qJadwal = $db->prepare("
    SELECT jd.id_jadwal_dokter AS id, d.nama_dokter AS nama, 'Dokter' AS jenis, d.foto, p.nama_poli, jd.jam_mulai, jd.jam_selesai, jd.status
    FROM jadwal_dokter jd
    JOIN dokter d ON d.id_dokter = jd.id_dokter
    JOIN poli p ON p.id_poli = jd.id_poli
    WHERE jd.hari = ? AND jd.status = 'tersedia' AND d.status = 'aktif'
    UNION ALL
    SELECT jb.id_jadwal_bidan AS id, b.nama_bidan AS nama, 'Bidan' AS jenis, b.foto, p.nama_poli, jb.jam_mulai, jb.jam_selesai, jb.status
    FROM jadwal_bidan jb
    JOIN bidan b ON b.id_bidan = jb.id_bidan
    JOIN poli p ON p.id_poli = jb.id_poli
    WHERE jb.hari = ? AND jb.status = 'tersedia' AND b.status = 'aktif'
    ORDER BY FIELD(jenis, 'Dokter', 'Bidan'), CASE WHEN jenis = 'Bidan' AND nama LIKE '%Gita%' THEN 0 ELSE 1 END, jam_mulai ASC, nama ASC
");
if ($qJadwal) {
    $qJadwal->bind_param('ss', $hariIni, $hariIni);
    $qJadwal->execute();
    $res = $qJadwal->get_result();
    while ($row = $res->fetch_assoc()) {
        $jadwalHariIni[] = $row;
    }
    $qJadwal->close();
}

// 4. TABEL AKTIVITAS TERBARU DENGAN FILTER
$filterTanggal = trim($_GET['tanggal'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterSumber = trim($_GET['sumber'] ?? '');

$whereSql = "WHERE 1=1";
$params = [];
$types = "";

if ($filterTanggal !== '') {
    $whereSql .= " AND p.tanggal_kunjungan = ?";
    $params[] = $filterTanggal;
    $types .= "s";
}
if ($filterStatus !== '') {
    $whereSql .= " AND p.status_pendaftaran = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
if ($filterSumber !== '') {
    $whereSql .= " AND p.sumber_pendaftaran = ?";
    $params[] = $filterSumber;
    $types .= "s";
}

$sqlRecent = "
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.jam_kunjungan, p.jenis_pasien, p.status_pendaftaran, p.sumber_pendaftaran, p.keluhan, p.created_at,
           pa.nama_lengkap, pa.nik, po.nama_poli,
           COALESCE(d.nama_dokter, b.nama_bidan, '-') AS tenaga_medis,
           a.nomor_antrean
    FROM pendaftaran p
    LEFT JOIN pasien pa ON pa.id_pasien = p.id_pasien
    LEFT JOIN poli po ON po.id_poli = p.id_poli
    LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
    LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    $whereSql
    ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
    LIMIT 10
";

$pendaftaranTerbaru = [];
if (!empty($params)) {
    $stmtR = $db->prepare($sqlRecent);
    if ($stmtR) {
        $stmtR->bind_param($types, ...$params);
        $stmtR->execute();
        $resR = $stmtR->get_result();
        while ($row = $resR->fetch_assoc()) {
            $pendaftaranTerbaru[] = $row;
        }
        $stmtR->close();
    }
} else {
    $resR = $db->query($sqlRecent);
    if ($resR) {
        while ($row = $resR->fetch_assoc()) {
            $pendaftaranTerbaru[] = $row;
        }
    }
}

adminPageStart('Dashboard', 'dashboard');
?>
<main class="admin-page">
    <header class="admin-header">
        <div>
            <h1><span>📊</span> Ringkasan Operasional Klinik</h1>
            <p>Pantau metrik pelayanan harian, antrean pasien berjalan, dan jadwal praktik tenaga medis.</p>
        </div>
        <div class="admin-header-actions">
            <span class="badge badge-purple" style="font-size:12.5px;padding:8px 14px;">
                🗓️ <?= $hariIni ?>, <?= date('d M Y') ?>
            </span>
            <a href="laporan.php" class="btn btn-light">
                <span>📈</span> Buka Laporan Lengkap
            </a>
            <a href="pendaftaran.php" class="btn">
                <span>📝</span> Kelola Pendaftaran
            </a>
        </div>
    </header>

    <?php if ($quickMessage): ?>
        <div class="alert alert-success">
            <span>✅</span> <?= adminEsc($quickMessage) ?>
        </div>
    <?php endif; ?>

    <!-- STATISTIK KARTU UTAMA -->
    <section style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">
        <div class="admin-card" style="margin-bottom:0;padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#F2ECFB;color:var(--hanania-primary);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                👥
            </div>
            <div>
                <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:.3px;">Total Pasien</span>
                <div style="font-size:24px;font-weight:900;color:var(--hanania-primary);line-height:1.2;margin-top:2px;">
                    <?= number_format($totalPasien, 0, ',', '.') ?>
                </div>
                <span style="font-size:11px;color:#1F7A4D;font-weight:700;">Terdaftar di sistem</span>
            </div>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#EBF3FA;color:#2A6DB2;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                📅
            </div>
            <div>
                <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:.3px;">Pendaftaran Hari Ini</span>
                <div style="font-size:24px;font-weight:900;color:#2A6DB2;line-height:1.2;margin-top:2px;">
                    <?= number_format($pendaftaranHariIni, 0, ',', '.') ?>
                </div>
                <span style="font-size:11px;color:var(--hanania-muted);font-weight:600;">Kunjungan <?= date('d/m') ?></span>
            </div>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#FFF6E5;color:#996000;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                ⏳
            </div>
            <div>
                <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:.3px;">Antrean Berjalan</span>
                <div style="font-size:24px;font-weight:900;color:#996000;line-height:1.2;margin-top:2px;">
                    <?= number_format($antreanBerjalan, 0, ',', '.') ?>
                </div>
                <span style="font-size:11px;color:#996000;font-weight:700;">Menunggu &amp; terdaftar</span>
            </div>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#E9F9EE;color:#1B7A3E;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                🩺
            </div>
            <div>
                <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:.3px;">Dokter &amp; Bidan</span>
                <div style="font-size:24px;font-weight:900;color:#1B7A3E;line-height:1.2;margin-top:2px;">
                    <?= $totalDokter + $totalBidan ?>
                </div>
                <span style="font-size:11px;color:var(--hanania-muted);font-weight:600;"><?= $totalDokter ?> Dokter · <?= $totalBidan ?> Bidan</span>
            </div>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:20px;display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#F4EEFB;color:var(--hanania-primary);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                📲
            </div>
            <div>
                <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;letter-spacing:.3px;">Pendaftaran Online</span>
                <div style="font-size:24px;font-weight:900;color:var(--hanania-primary);line-height:1.2;margin-top:2px;">
                    <?= number_format($pendaftaranOnline, 0, ',', '.') ?>
                </div>
                <span style="font-size:11px;color:var(--hanania-muted);font-weight:600;">Selesai: <?= $pelayananSelesai ?></span>
            </div>
        </div>
    </section>

    <!-- SECTION 2: TREN MINGGUAN & JADWAL HARI INI -->
    <div class="dashboard-split-grid" style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;margin-bottom:24px;">
        <!-- GRAFIK MINGGUAN -->
        <section class="admin-card" style="margin-bottom:0;">
            <div class="admin-card-head">
                <div>
                    <div class="admin-card-title">
                        <span>📈</span> Tren Kunjungan Pasien 7 Hari Terakhir
                    </div>
                    <div class="admin-card-subtitle">Volume kunjungan pasien dari basis data per tanggal pemeriksaan</div>
                </div>
                <span class="badge badge-purple">Real-time</span>
            </div>

            <!-- CSS BAR CHART -->
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;padding-bottom:4px;">
                <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:8px;min-width:320px;height:180px;padding:20px 4px 0;border-bottom:1px solid #F0EAF8;">
                    <?php foreach ($grafikMingguan as $gm): 
                        $percent = $maxGrafik > 0 ? max(10, round(($gm['total'] / $maxGrafik) * 100)) : 10;
                        $isTodayLoop = ($gm['tanggal'] === $today);
                    ?>
                        <div style="flex:1;min-width:34px;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;gap:6px;">
                            <span style="font-size:11px;font-weight:800;color:<?= $isTodayLoop ? 'var(--hanania-primary)' : 'var(--hanania-muted)' ?>;">
                                <?= $gm['total'] ?>
                            </span>
                            <div style="width:100%;max-width:32px;height:<?= $percent ?>%;background:<?= $isTodayLoop ? 'linear-gradient(180deg, #4F3293, #7650A2)' : 'linear-gradient(180deg, #D4C5E8, #EAE4F9)' ?>;border-radius:6px 6px 0 0;transition:height .3s ease;" title="<?= $gm['label'] ?>: <?= $gm['total'] ?> Pasien"></div>
                            <span style="font-size:10px;font-weight:700;color:<?= $isTodayLoop ? 'var(--hanania-primary)' : 'var(--hanania-muted)' ?>;white-space:nowrap;margin-top:4px;">
                                <?= $gm['label'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:12px;font-size:11.5px;color:var(--hanania-muted);">
                <span>ℹ️ Data otomatis diperbarui berdasarkan tanggal kunjungan pasien.</span>
                <a href="laporan.php" style="color:var(--hanania-primary);font-weight:700;text-decoration:underline;">Lihat Laporan Periode Lain &rarr;</a>
            </div>
        </section>

        <!-- HIGHLIGHT PRAKTIK HARI INI -->
        <section class="admin-card" style="margin-bottom:0;">
            <div class="admin-card-head">
                <div>
                    <div class="admin-card-title">
                        <span>🩺</span> Jadwal Praktik Hari Ini
                    </div>
                    <div class="admin-card-subtitle"><?= $hariIni ?>, <?= date('d F Y') ?></div>
                </div>
                <a href="jadwal.php" class="btn btn-sm btn-light">Kelola Jadwal</a>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;max-height:220px;overflow-y:auto;padding-right:4px;">
                <?php if (empty($jadwalHariIni)): ?>
                    <div style="text-align:center;padding:30px 10px;color:var(--hanania-muted);font-size:13px;">
                        <span>☕</span> Tidak ada jadwal praktik dokter/bidan pada hari ini.
                    </div>
                <?php else: ?>
                    <?php foreach ($jadwalHariIni as $jh): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;background:#FAF8FD;border:1px solid #F0EBF7;border-radius:10px;">
                            <div style="display:flex;align-items:center;gap:10px;overflow:hidden;">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--hanania-purple-soft);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
                                    <?= $jh['jenis'] === 'Dokter' ? '🩺' : '👶' ?>
                                </div>
                                <div style="overflow:hidden;">
                                    <div style="font-size:13px;font-weight:800;color:var(--hanania-primary);white-space:nowrap;text-overflow:ellipsis;overflow:hidden;">
                                        <?= adminEsc($jh['nama']) ?>
                                    </div>
                                    <div style="font-size:11px;color:var(--hanania-muted);">
                                        <?= adminEsc($jh['nama_poli']) ?> · <?= adminEsc($jh['jenis']) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <span class="badge badge-purple" style="font-size:10.5px;">
                                    🕒 <?= substr($jh['jam_mulai'], 0, 5) ?> - <?= substr($jh['jam_selesai'], 0, 5) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- SECTION 3: TABEL AKTIVITAS PENDAFTARAN TERBARU + FILTER -->
    <section class="admin-card">
        <div class="admin-card-head">
            <div>
                <div class="admin-card-title">
                    <span>📋</span> Aktivitas Pendaftaran Pasien Terbaru
                </div>
                <div class="admin-card-subtitle">
                    Daftar reservasi pemeriksaan dan status antrean pasien secara langsung
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="pendaftaran.php" class="btn btn-light btn-sm">Semua Pendaftaran &rarr;</a>
                <a href="laporan.php" class="btn btn-sm">Cetak Laporan</a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="admin-filter-bar">
            <div class="admin-filter-group">
                <label style="font-size:11.5px;font-weight:700;color:var(--hanania-muted);">Filter:</label>
                <input type="date" name="tanggal" value="<?= adminEsc($filterTanggal) ?>" class="filter-input" title="Filter Tanggal Kunjungan">
                
                <select name="status" class="filter-input">
                    <option value="">-- Semua Status --</option>
                    <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="terdaftar" <?= $filterStatus === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                    <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $filterStatus === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>

                <select name="sumber" class="filter-input">
                    <option value="">-- Semua Sumber --</option>
                    <option value="online" <?= $filterSumber === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="offline" <?= $filterSumber === 'offline' ? 'selected' : '' ?>>Offline</option>
                </select>

                <button type="submit" class="btn btn-sm">Terapkan Filter</button>
                <?php if ($filterTanggal !== '' || $filterStatus !== '' || $filterSumber !== ''): ?>
                    <a href="index.php" class="btn btn-sm btn-light">Reset</a>
                <?php endif; ?>
            </div>
            <span style="font-size:12px;color:var(--hanania-muted);font-weight:600;">
                Menampilkan <?= count($pendaftaranTerbaru) ?> data terbaru
            </span>
        </form>

        <?php if (empty($pendaftaranTerbaru)): ?>
            <div style="padding:40px;text-align:center;color:var(--hanania-muted);">
                <div style="font-size:32px;margin-bottom:8px;">🔍</div>
                <strong>Tidak ada data pendaftaran yang sesuai dengan filter.</strong>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No. Antrean</th>
                            <th>Data Pasien</th>
                            <th>Poli &amp; Tenaga Medis</th>
                            <th>Jadwal Kunjungan</th>
                            <th>Jenis Pasien</th>
                            <th>Status Pelayanan</th>
                            <th>Aksi Cepat Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendaftaranTerbaru as $row): 
                            $status = $row['status_pendaftaran'] ?? 'menunggu';
                            $badgeClass = 'badge-warning';
                            if ($status === 'selesai') $badgeClass = 'badge-success';
                            elseif ($status === 'dibatalkan') $badgeClass = 'badge-danger';
                            elseif ($status === 'terdaftar') $badgeClass = 'badge-purple';

                            $nomorDisplay = !empty($row['nomor_antrean']) 
                                ? str_pad((string)$row['nomor_antrean'], 3, '0', STR_PAD_LEFT)
                                : '-';
                            $sumber = ($row['sumber_pendaftaran'] ?? 'online') === 'online' ? 'Online' : 'Offline';
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <strong style="color:var(--hanania-primary);font-size:16px;font-weight:900;">
                                        #<?= adminEsc($nomorDisplay) ?>
                                    </strong>
                                    <span class="badge <?= $sumber === 'Online' ? 'badge-purple' : 'badge-gray' ?>" style="font-size:10px;">
                                        <?= $sumber ?>
                                    </span>
                                </div>
                                <span style="font-size:10.5px;color:var(--hanania-muted);display:block;margin-top:2px;">
                                    ID Reg: #<?= (int)$row['id_pendaftaran'] ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color:var(--hanania-text);font-size:13.5px;display:block;">
                                    <?= adminEsc($row['nama_lengkap'] ?? 'Pasien') ?>
                                </strong>
                                <span style="font-size:11px;color:var(--hanania-muted);">NIK: <?= adminEsc($row['nik'] ?? '-') ?></span>
                            </td>
                            <td>
                                <strong style="color:var(--hanania-text);"><?= adminEsc($row['nama_poli'] ?? '-') ?></strong>
                                <span style="display:block;font-size:11.5px;color:var(--hanania-muted);"><?= adminEsc($row['tenaga_medis']) ?></span>
                            </td>
                            <td>
                                <strong>📅 <?= date('d/m/Y', strtotime($row['tanggal_kunjungan'])) ?></strong>
                                <span style="display:block;font-size:11.5px;color:var(--hanania-muted);">
                                    🕒 <?= !empty($row['jam_kunjungan']) ? adminEsc(substr($row['jam_kunjungan'], 0, 5)) . ' WIB' : '-' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-gray">
                                    <?= adminEsc($row['jenis_pasien'] ?? 'Umum') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= adminEsc(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display:flex;gap:6px;align-items:center;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id_pendaftaran" value="<?= (int)$row['id_pendaftaran'] ?>">
                                    <select name="status_pendaftaran" class="filter-input" style="padding:4px 8px;font-size:11px;">
                                        <option value="menunggu" <?= $status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                        <option value="terdaftar" <?= $status === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                                        <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Batal</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-light" title="Simpan Perubahan Status">💾</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
adminPageEnd();
