<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$message = '';
$messageType = 'success';

// PROSES POST: TAMBAH ANTREAN OFFLINE & UPDATE STATUS PENDAFTARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_antrean_offline') {
        try {
            $idPasien = (int)($_POST['id_pasien'] ?? 0);
            $idPoli = (int)($_POST['id_poli'] ?? 0);
            $staffVal = trim($_POST['tenaga_medis'] ?? '');
            $tanggalKunjungan = trim($_POST['tanggal_kunjungan'] ?? date('Y-m-d'));
            $sesiKunjungan = trim($_POST['sesi_kunjungan'] ?? 'Pagi');
            $jenisPasien = in_array($_POST['jenis_pasien'] ?? '', ['BPJS', 'Umum'], true) ? $_POST['jenis_pasien'] : 'Umum';
            $nomorJkn = trim($_POST['nomor_antrean_jkn'] ?? '');
            $jenisPerawatan = trim($_POST['jenis_perawatan'] ?? 'Pemeriksaan Rawat Jalan');
            $keluhan = trim($_POST['keluhan'] ?? 'Pendaftaran antrean offline / walk-in');

            if ($idPoli <= 0) {
                throw new Exception('Poli pelayanan wajib dipilih.');
            }

            // Jika pilih tambah pasien baru secara langsung
            if ($idPasien <= 0) {
                $namaBaru = trim($_POST['nama_pasien_baru'] ?? '');
                $nikBaru = trim($_POST['nik_pasien_baru'] ?? '');
                $telpBaru = trim($_POST['telp_pasien_baru'] ?? '');
                $namaIbuBaru = trim($_POST['nama_ibu_kandung_baru'] ?? '');
                $namaSuamiBaru = trim($_POST['nama_suami_baru'] ?? '');
                if ($namaBaru === '') {
                    throw new Exception('Nama pasien baru wajib diisi.');
                }
                $stmtNewP = $db->prepare("INSERT INTO pasien (nik, nama_lengkap, no_telepon, nama_ibu_kandung, nama_suami) VALUES (?, ?, ?, ?, ?)");
                if ($stmtNewP) {
                    $stmtNewP->bind_param('sssss', $nikBaru, $namaBaru, $telpBaru, $namaIbuBaru, $namaSuamiBaru);
                    $stmtNewP->execute();
                    $idPasien = $stmtNewP->insert_id;
                    $stmtNewP->close();
                } else {
                    throw new Exception('Gagal mendaftarkan pasien baru: ' . $db->error);
                }
            }

            $idDokter = null;
            $idBidan = null;
            if (strpos($staffVal, 'dokter_') === 0) {
                $idDokter = (int)str_replace('dokter_', '', $staffVal);
            } elseif (strpos($staffVal, 'bidan_') === 0) {
                $idBidan = (int)str_replace('bidan_', '', $staffVal);
            }

            // Hitung nomor antrean offline berikutnya dengan pola:
            // 1-2: Offline, 3-5: Online, 6-7: Offline, 8-10: Online, dst.
            $calcAntrean = hitungNomorAntreanBerikutnya($db, $tanggalKunjungan, $idPoli, $idDokter, $idBidan, 'offline', $sesiKunjungan);
            $nomorAntreanNumber = $calcAntrean['nomor'];

            $jamKunjungan = date('H:i:s');

            $stmtReg = $db->prepare("
                INSERT INTO pendaftaran (
                    id_pasien, id_dokter, id_bidan, id_poli,
                    tanggal_kunjungan, jam_kunjungan,
                    jenis_pasien, jenis_perawatan, sesi_kunjungan, nomor_antrean_jkn,
                    keluhan, status_pendaftaran, sumber_pendaftaran
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'terdaftar', 'offline')
            ");
            if (!$stmtReg) {
                throw new Exception('Gagal menyiapkan pendaftaran: ' . $db->error);
            }
            $stmtReg->bind_param('iiiisssssss', $idPasien, $idDokter, $idBidan, $idPoli, $tanggalKunjungan, $jamKunjungan, $jenisPasien, $jenisPerawatan, $sesiKunjungan, $nomorJkn, $keluhan);
            if (!$stmtReg->execute()) {
                throw new Exception('Gagal menyimpan pendaftaran offline: ' . $stmtReg->error);
            }
            $newRegId = $stmtReg->insert_id;
            $stmtReg->close();

            // Tambah antrean
            $stmtAnt = $db->prepare("INSERT INTO antrean (id_pendaftaran, nomor_antrean, estimasi_waktu, status_antrean) VALUES (?, ?, ?, 'menunggu')");
            if ($stmtAnt) {
                $stmtAnt->bind_param('iis', $newRegId, $nomorAntreanNumber, $jamKunjungan);
                $stmtAnt->execute();
                $stmtAnt->close();
            }

            // Tambahkan poin reward ke akun pasien sesuai kategori kunjungan
            require_once __DIR__ . '/../includes/poin_helper.php';
            $calcPoin = hitungPoinKunjungan($db, $jenisPasien, $jenisPerawatan, $keluhan, $idPoli);
            $poinDidapat = (int)($calcPoin['poin'] ?? 0);
            $poinTipe = $calcPoin['tipe'] ?? 'Kunjungan';
            $poinKode = $calcPoin['kode'] ?? 'biasa';
            if ($poinDidapat > 0 && $idPasien > 0) {
                $db->query("UPDATE pasien SET reward_poin = COALESCE(reward_poin, 0) + $poinDidapat WHERE id_pasien = $idPasien");

                // Catat ke riwayat_poin
                $qSalOff = $db->query("SELECT reward_poin FROM pasien WHERE id_pasien = $idPasien LIMIT 1");
                $saldoAkhirOff = ($qSalOff && $rSalOff = $qSalOff->fetch_assoc()) ? (int)$rSalOff['reward_poin'] : $poinDidapat;
                $kategoriLog = 'kunjungan_' . $poinKode;
                $ketLog = "Pendaftaran Langsung (Offline): {$poinTipe} (+{$poinDidapat} Poin)";
                $adminLogName = $_SESSION['nama_lengkap'] ?? $_SESSION['admin_username'] ?? 'Petugas Pendaftaran';
                $stLogOff = $db->prepare("INSERT INTO riwayat_poin (id_pasien, jenis, jumlah_poin, saldo_akhir, kategori, keterangan, created_by, created_at) VALUES (?, 'tambah', ?, ?, ?, ?, ?, NOW())");
                if ($stLogOff) {
                    $stLogOff->bind_param('isisss', $idPasien, $poinDidapat, $saldoAkhirOff, $kategoriLog, $ketLog, $adminLogName);
                    $stLogOff->execute();
                    $stLogOff->close();
                }
            }

            $message = "Antrean offline berhasil ditambahkan! Nomor antrean: #" . str_pad((string)$nomorAntreanNumber, 3, '0', STR_PAD_LEFT) . " (Pasien mendapatkan +{$poinDidapat} Poin: {$poinTipe})";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Gagal menambah antrean: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        // PROSES UPDATE STATUS PENDAFTARAN BIASA
        $idPendaftaran = (int)($_POST['id_pendaftaran'] ?? 0);
        $status = trim($_POST['status_pendaftaran'] ?? '');
        $allowed = ['menunggu', 'terdaftar', 'selesai', 'dibatalkan'];

        if ($idPendaftaran > 0 && in_array($status, $allowed, true)) {
            $stmt = $db->prepare("UPDATE pendaftaran SET status_pendaftaran = ? WHERE id_pendaftaran = ?");
            if ($stmt) {
                $stmt->bind_param('si', $status, $idPendaftaran);
                $stmt->execute();
                $stmt->close();

                // Update juga status antrean di tabel antrean jika ada
                $antreanStatusMap = [
                    'menunggu' => 'menunggu',
                    'terdaftar' => 'check-in',
                    'selesai' => 'selesai',
                    'dibatalkan' => 'batal'
                ];
                $antreanStatus = $antreanStatusMap[$status] ?? 'menunggu';
                $stmtA = $db->prepare("UPDATE antrean SET status_antrean = ? WHERE id_pendaftaran = ?");
                if ($stmtA) {
                    $stmtA->bind_param('si', $antreanStatus, $idPendaftaran);
                    $stmtA->execute();
                    $stmtA->close();
                }

                $message = "Status pendaftaran #$idPendaftaran berhasil diperbarui menjadi " . ucfirst($status) . ".";
                $messageType = 'success';
            }
        }
    }
}

// Opsi filter
$filterHariIni = (isset($_GET['hari_ini']) && $_GET['hari_ini'] == '1') || (isset($_GET['filter_hari_ini']) && $_GET['filter_hari_ini'] == '1') || (isset($_GET['periode']) && $_GET['periode'] === 'hari_ini');
if ($filterHariIni) {
    $filterTanggal = date('Y-m-d');
} else {
    $filterTanggal = trim($_GET['tanggal'] ?? '');
}
$isFilterHariIni = ($filterTanggal === date('Y-m-d'));
$filterPoli = (int)($_GET['poli'] ?? 0);
$filterStatus = trim($_GET['status'] ?? 'semua');
$filterSumber = trim($_GET['sumber'] ?? 'semua');

// Ambil data poli untuk filter dropdown
$daftarPoli = [];
$resPoli = $db->query("SELECT id_poli, nama_poli FROM poli ORDER BY nama_poli ASC");
if ($resPoli) {
    while ($row = $resPoli->fetch_assoc()) {
        $daftarPoli[] = $row;
    }
}

// Ambil daftar pasien untuk antrean offline
$daftarPasienSelect = [];
$resPas = $db->query("SELECT id_pasien, nama_lengkap, nik, no_telepon FROM pasien ORDER BY nama_lengkap ASC LIMIT 250");
if ($resPas) {
    while ($r = $resPas->fetch_assoc()) {
        $daftarPasienSelect[] = $r;
    }
}

// Ambil daftar dokter & bidan untuk antrean offline
$daftarTenaga = [];
$qDoc = $db->query("SELECT id_dokter, nama_dokter, 'dokter' as jenis, spesialisasi FROM dokter WHERE status != 'nonaktif' ORDER BY nama_dokter ASC");
if ($qDoc) {
    while ($r = $qDoc->fetch_assoc()) $daftarTenaga[] = $r;
}
$qBid = $db->query("SELECT id_bidan, nama_bidan, 'bidan' as jenis, 'Bidan' as spesialisasi FROM bidan WHERE status != 'nonaktif' ORDER BY nama_bidan ASC");
if ($qBid) {
    while ($r = $qBid->fetch_assoc()) $daftarTenaga[] = $r;
}

// Bangun query pendaftaran
$where = [];
if ($filterTanggal !== '') {
    $where[] = "p.tanggal_kunjungan = '" . $db->real_escape_string($filterTanggal) . "'";
}
if ($filterPoli > 0) {
    $where[] = "p.id_poli = " . $filterPoli;
}
if ($filterStatus !== 'semua' && in_array($filterStatus, ['menunggu', 'terdaftar', 'selesai', 'dibatalkan'], true)) {
    $where[] = "p.status_pendaftaran = '" . $db->real_escape_string($filterStatus) . "'";
}
if ($filterSumber !== 'semua' && in_array($filterSumber, ['online', 'offline'], true)) {
    $where[] = "p.sumber_pendaftaran = '" . $db->real_escape_string($filterSumber) . "'";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT p.*,
           pa.nama_lengkap, pa.nik, pa.nama_ibu_kandung, pa.nama_suami, pa.nama_ayah_kandung, pa.tanggal_lahir, pa.jenis_kelamin,
           pa.no_telepon, pa.email, pa.alamat, pa.pekerjaan, pa.status_perkawinan,
           pa.pendidikan_terakhir, pa.agama, pa.ada_rujukan, pa.asal_rujukan AS asal_rujukan_pasien,
           po.nama_poli,
           COALESCE(d.nama_dokter, b.nama_bidan, '-') AS tenaga_medis,
           d.spesialisasi,
           a.nomor_antrean, a.estimasi_waktu, a.status_antrean,
           r.nama_perujuk, r.file_rujukan
    FROM pendaftaran p
    LEFT JOIN pasien pa ON pa.id_pasien = p.id_pasien
    LEFT JOIN poli po ON po.id_poli = p.id_poli
    LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
    LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    LEFT JOIN rujukan_pendaftaran r ON r.id_pendaftaran = p.id_pendaftaran
    $whereClause
    ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
";

$result = $db->query($sql);
$listPendaftaran = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $listPendaftaran[] = $row;
    }
}

adminPageStart('Data Pendaftaran', 'pendaftaran');
?>
<main class="admin-page">
    <header class="admin-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h1><span>📝</span> Pengelolaan Pendaftaran Pasien</h1>
            <p>Kelola nomor antrean, verifikasi data kunjungan, status rujukan, dan pemantauan pelayanan.</p>
        </div>
        <div class="admin-header-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <a href="layanan_pendaftaran.php" class="btn btn-sm btn-light" style="background:var(--hanania-purple-soft);color:var(--hanania-primary);display:inline-flex;align-items:center;gap:6px;">
                <span>📋</span> Kelola Pilihan Layanan Form
            </a>
            <span class="badge badge-purple" style="font-size:13px;padding:8px 14px;">
                Total: <?= count($listPendaftaran) ?> Pendaftaran
            </span>
            <button type="button" class="btn btn-sm" onclick="bukaModalAntreanOffline()" style="background:var(--hanania-primary);color:#fff;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(116,52,169,.25);">
                <span>➕</span> Tambah Antrean Offline
            </button>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;padding:12px 18px;border-radius:10px;display:flex;align-items:center;gap:10px;">
            <span style="font-size:18px;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div style="font-size:13.5px;font-weight:600;"><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="admin-filter-bar">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
            <div style="display:flex;flex-direction:column;gap:3px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <label style="font-size:11px;font-weight:700;color:var(--muted);">Tanggal Kunjungan</label>
                    <a href="pendaftaran.php?tanggal=<?= date('Y-m-d') ?><?= $filterPoli > 0 ? '&poli='.$filterPoli : '' ?><?= $filterStatus !== 'semua' ? '&status='.urlencode($filterStatus) : '' ?><?= $filterSumber !== 'semua' ? '&sumber='.urlencode($filterSumber) : '' ?>" style="font-size:10.5px;font-weight:800;color:var(--hanania-primary);text-decoration:none;">
                        ⚡ Set Hari Ini
                    </a>
                </div>
                <input type="date" name="tanggal" value="<?= adminEsc($filterTanggal) ?>" class="filter-input">
            </div>

            <div style="display:flex;flex-direction:column;gap:3px;">
                <label style="font-size:11px;font-weight:700;color:var(--muted);">Poli Pelayanan</label>
                <select name="poli" class="filter-input">
                    <option value="0">Semua Poli</option>
                    <?php foreach ($daftarPoli as $p): ?>
                        <option value="<?= (int)$p['id_poli'] ?>" <?= $filterPoli === (int)$p['id_poli'] ? 'selected' : '' ?>>
                            <?= adminEsc($p['nama_poli']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:3px;">
                <label style="font-size:11px;font-weight:700;color:var(--muted);">Status Pendaftaran</label>
                <select name="status" class="filter-input">
                    <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="menunggu" <?= $filterStatus === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="terdaftar" <?= $filterStatus === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                    <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $filterStatus === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:3px;">
                <label style="font-size:11px;font-weight:700;color:var(--muted);">Sumber</label>
                <select name="sumber" class="filter-input">
                    <option value="semua" <?= $filterSumber === 'semua' ? 'selected' : '' ?>>Semua Sumber</option>
                    <option value="online" <?= $filterSumber === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="offline" <?= $filterSumber === 'offline' ? 'selected' : '' ?>>Offline (Walk-in)</option>
                </select>
            </div>

            <div style="margin-top:auto;display:flex;gap:6px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-sm">Filter Data</button>
                <a href="pendaftaran.php?tanggal=<?= date('Y-m-d') ?><?= $filterPoli > 0 ? '&poli='.$filterPoli : '' ?><?= $filterStatus !== 'semua' ? '&status='.urlencode($filterStatus) : '' ?><?= $filterSumber !== 'semua' ? '&sumber='.urlencode($filterSumber) : '' ?>" class="btn btn-sm <?= ($isFilterHariIni) ? 'btn-primary' : 'btn-light' ?>" style="font-weight:800;" title="Lihat pendaftaran khusus hari ini">
                    📅 Hari Ini (<?= date('d/m') ?>)
                </a>
                <?php if ($filterTanggal !== '' || $filterPoli > 0 || $filterStatus !== 'semua' || $filterSumber !== 'semua'): ?>
                    <a href="pendaftaran.php" class="btn btn-sm btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- TABEL DATA PENDAFTARAN -->
    <section class="admin-card" style="padding:0;overflow:hidden;">
        <div class="admin-table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:105px;">No. Antrean</th>
                        <th>Jadwal Kunjungan</th>
                        <th>Identitas Pasien</th>
                        <th>Poli &amp; Tenaga Medis</th>
                        <th>Keluhan &amp; Rujukan</th>
                        <th>Status</th>
                        <th style="text-align:center;width:115px;">Detail</th>
                        <th style="text-align:right;">Ubah Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listPendaftaran)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:35px;color:var(--muted);">
                                Belum ada pendaftaran yang sesuai kriteria filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listPendaftaran as $p): 
                            $status = $p['status_pendaftaran'] ?? 'menunggu';
                            $badge = 'badge-warning';
                            if ($status === 'selesai') $badge = 'badge-success';
                            elseif ($status === 'dibatalkan') $badge = 'badge-danger';
                            elseif ($status === 'terdaftar') $badge = 'badge-purple';

                            $nomorAntreanDisplay = !empty($p['nomor_antrean']) 
                                ? str_pad((string)$p['nomor_antrean'], 3, '0', STR_PAD_LEFT)
                                : (!empty($p['id_pendaftaran']) ? str_pad((string)$p['id_pendaftaran'], 3, '0', STR_PAD_LEFT) : '-');
                            
                            $isOffline = (($p['sumber_pendaftaran'] ?? '') === 'offline');
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--button);font-size:16px;font-weight:900;">
                                    <?= adminEsc($nomorAntreanDisplay) ?>
                                </strong>
                                <span style="display:block;font-size:10.5px;font-weight:800;color:<?= $isOffline ? '#D97706' : '#6B7280' ?>;">
                                    <?= $isOffline ? '🏢 OFFLINE' : '🌐 ONLINE' ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($p['tanggal_kunjungan'])) ?></strong>
                                <span style="display:block;font-size:11px;color:var(--purple-dark);font-weight:700;">
                                    <?= !empty($p['jam_kunjungan']) ? adminEsc(substr($p['jam_kunjungan'], 0, 5)) . ' WIB' : '-' ?>
                                </span>
                                <?php if (!empty($p['sesi_kunjungan'])): ?>
                                    <span style="display:inline-block;font-size:10.5px;background:#F3E8FF;color:#6C34A3;padding:2px 6px;border-radius:4px;margin-top:2px;">
                                        Sesi <?= adminEsc($p['sesi_kunjungan']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--text);font-size:13px;"><?= adminEsc($p['nama_lengkap'] ?? 'Pasien') ?></strong>
                                <span style="display:block;font-size:11px;color:var(--muted);">
                                    NIK: <?= adminEsc($p['nik'] ?: '-') ?>
                                </span>
                                <?php if (!empty($p['no_telepon'])): ?>
                                    <span style="display:block;font-size:11px;color:var(--muted);">
                                        📞 <?= adminEsc($p['no_telepon']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($p['nomor_antrean_jkn'])): ?>
                                    <span style="display:block;font-size:11px;color:#15803D;font-weight:700;">
                                        JKN: <?= adminEsc($p['nomor_antrean_jkn']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= adminEsc($p['nama_poli'] ?? '-') ?></strong>
                                <span style="display:block;font-size:11px;color:var(--muted);">
                                    <?= adminEsc($p['tenaga_medis']) ?>
                                </span>
                                <?php if (!empty($p['jenis_perawatan'])): ?>
                                    <div style="font-size:11px;color:var(--hanania-primary);font-weight:600;margin-top:2px;">
                                        <?= adminEsc($p['jenis_perawatan']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:220px;">
                                <div style="font-size:12px;color:var(--text);line-height:1.4;">
                                    <?= !empty($p['keluhan']) ? adminEsc($p['keluhan']) : '<span style="color:var(--muted);">-</span>' ?>
                                </div>
                                <?php if (!empty($p['file_rujukan'])): ?>
                                    <div style="margin-top:4px;">
                                        <a href="../<?= adminEsc($p['file_rujukan']) ?>" target="_blank" rel="noopener noreferrer" 
                                           style="display:inline-flex;align-items:center;gap:3px;font-size:11px;color:var(--button);font-weight:700;">
                                            📄 File Rujukan (<?= adminEsc($p['nama_perujuk'] ?: 'Perujuk') ?>)
                                        </a>
                                    </div>
                                <?php elseif (!empty($p['asal_rujukan'])): ?>
                                    <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                                        Rujukan: <?= adminEsc($p['asal_rujukan']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $badge ?>"><?= adminEsc(ucfirst($status)) ?></span>
                            </td>
                            <td style="text-align:center;">
                                <button type="button" class="btn btn-sm btn-light btn-detail-pendaftaran" 
                                        data-pendaftaran="<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>"
                                        style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;font-size:11.5px;font-weight:700;"
                                        title="Lihat Detail Lengkap Pasien &amp; Pendaftaran">
                                    👁️ Lihat Detail
                                </button>
                            </td>
                            <td style="text-align:right;">
                                <form method="POST" style="display:inline-flex;gap:4px;align-items:center;">
                                    <input type="hidden" name="id_pendaftaran" value="<?= (int)$p['id_pendaftaran'] ?>">
                                    <select name="status_pendaftaran" class="filter-input" style="padding:5px 8px;font-size:11px;">
                                        <option value="menunggu" <?= $status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                        <option value="terdaftar" <?= $status === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                                        <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Batal</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-light" title="Simpan Status">
                                        💾
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- MODAL TAMBAH ANTREAN OFFLINE (WALK-IN)                    -->
    <!-- ========================================================= -->
    <div id="modalAntreanOffline" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
        <div style="background:#fff;border-radius:20px;max-width:620px;width:100%;max-height:90vh;overflow-y:auto;padding:26px;box-shadow:0 25px 60px rgba(0,0,0,0.25);position:relative;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid var(--hanania-border);padding-bottom:12px;">
                <h3 style="font-size:17px;font-weight:800;color:var(--hanania-primary);margin:0;display:flex;align-items:center;gap:8px;">
                    <span>➕</span> Tambah Antrean Offline / Walk-in
                </h3>
                <button type="button" onclick="tutupModalAntreanOffline()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>

            <form method="POST" action="pendaftaran.php">
                <input type="hidden" name="action" value="tambah_antrean_offline">

                <!-- PILIH PASIEN ATAU BARU -->
                <div style="margin-bottom:16px;background:#FAF8FD;padding:12px 14px;border-radius:12px;border:1px solid #EAE0F5;">
                    <label style="font-size:12px;font-weight:800;display:block;margin-bottom:6px;color:#4A4353;">Pilih Pasien Terdaftar:</label>
                    <select name="id_pasien" id="selectPasienOffline" class="filter-input" style="width:100%;" onchange="toggleBoxPasienBaru(this.value)">
                        <option value="0">+ Pasien Baru (Input Walk-in)</option>
                        <?php foreach ($daftarPasienSelect as $ps): ?>
                            <option value="<?= (int)$ps['id_pasien'] ?>">
                                <?= adminEsc($ps['nama_lengkap']) ?> (NIK: <?= adminEsc($ps['nik'] ?: '-') ?> | Telp: <?= adminEsc($ps['no_telepon'] ?: '-') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- BOX PASIEN BARU -->
                <div id="boxPasienBaru" style="margin-bottom:16px;border:1.5px dashed var(--hanania-border);border-radius:12px;padding:14px;background:#fff;">
                    <div style="font-size:12px;font-weight:800;color:var(--hanania-primary);margin-bottom:10px;">Data Pasien Baru:</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                        <div>
                            <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">Nama Lengkap Pasien *</label>
                            <input type="text" name="nama_pasien_baru" id="nama_pasien_baru" placeholder="Nama lengkap pasien" class="filter-input" style="width:100%;">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">NIK Pasien (opsional)</label>
                            <input type="text" name="nik_pasien_baru" maxlength="16" placeholder="16 digit NIK" class="filter-input" style="width:100%;">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="telp_pasien_baru" placeholder="08xxxxxxxxxx" class="filter-input" style="width:100%;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                        <div>
                            <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">Nama Ibu Kandung (opsional)</label>
                            <input type="text" name="nama_ibu_kandung_baru" placeholder="Nama ibu kandung" class="filter-input" style="width:100%;">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">Nama Suami / Pasangan (opsional)</label>
                            <input type="text" name="nama_suami_baru" placeholder="Nama suami (jika ada)" class="filter-input" style="width:100%;">
                        </div>
                    </div>
                </div>

                <!-- POLI & DOKTER -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Poli Pelayanan *</label>
                        <select name="id_poli" required class="filter-input" style="width:100%;">
                            <option value="">-- Pilih Poli --</option>
                            <?php foreach ($daftarPoli as $po): ?>
                                <option value="<?= (int)$po['id_poli'] ?>"><?= adminEsc($po['nama_poli']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Tenaga Medis (Dokter / Bidan)</label>
                        <select name="tenaga_medis" class="filter-input" style="width:100%;">
                            <option value="">-- Bebas / Sesuai Antrean Poli --</option>
                            <?php foreach ($daftarTenaga as $t): ?>
                                <option value="<?= $t['jenis'] . '_' . ($t['id_dokter'] ?? $t['id_bidan']) ?>">
                                    <?= ucfirst($t['jenis']) ?>: <?= adminEsc($t['nama_dokter'] ?? $t['nama_bidan']) ?> (<?= adminEsc($t['spesialisasi']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Tanggal Kunjungan *</label>
                        <input type="date" name="tanggal_kunjungan" value="<?= date('Y-m-d') ?>" required class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Pilihan Sesi *</label>
                        <select name="sesi_kunjungan" class="filter-input" style="width:100%;">
                            <option value="Pagi">🌅 Sesi Pagi (08:00 - 12:00 WIB)</option>
                            <option value="Siang">☀️ Sesi Siang (13:00 - 16:00 WIB)</option>
                            <option value="Sore/Malam">🌙 Sesi Sore / Malam (16:00 - 20:30 WIB)</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Kategori Penjamin</label>
                        <select name="jenis_pasien" class="filter-input" style="width:100%;" onchange="toggleBpjsBox(this.value)">
                            <option value="Umum">💵 Pasien Umum / Mandiri</option>
                            <option value="BPJS">💳 Pasien BPJS Kesehatan</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Jenis Layanan / Tindakan</label>
                        <select name="jenis_perawatan" class="filter-input" style="width:100%;">
                            <option value="Pemeriksaan Rawat Jalan">Pemeriksaan Rawat Jalan</option>
                            <option value="USG Kehamilan">USG Kehamilan 2D / 4D</option>
                            <option value="Pelayanan Kebidanan">Pelayanan Kebidanan &amp; KIA</option>
                            <option value="Tindakan Gigi">Pemeriksaan Gigi &amp; Mulut</option>
                            <option value="Imunisasi">Imunisasi / Vaksinasi</option>
                        </select>
                    </div>
                </div>

                <div id="boxBpjsOfflineInput" style="display:none;margin-bottom:14px;">
                    <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;color:#166534;">Nomor Antrean Mobile JKN (jika ada)</label>
                    <input type="text" name="nomor_antrean_jkn" placeholder="Contoh: A-012" class="filter-input" style="width:100%;border-color:#86EFAC;">
                </div>

                <div style="margin-bottom:18px;">
                    <label style="font-size:11px;font-weight:800;display:block;margin-bottom:4px;">Keluhan Utama Pasien</label>
                    <textarea name="keluhan" rows="2" placeholder="Catatan keluhan / keperluan berobat walk-in" class="filter-input" style="width:100%;"></textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="tutupModalAntreanOffline()" class="btn btn-sm btn-light">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;">
                        ➕ Terbitkan Antrean Offline
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ========================================================= -->
    <!-- MODAL DETAIL LENGKAP PENDAFTARAN PASIEN                    -->
    <!-- ========================================================= -->
    <div id="modalDetailPendaftaran" class="no-print" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.65);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(4px);">
        <div style="background:#fff;border-radius:20px;max-width:780px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.25);overflow:hidden;position:relative;">
            
            <!-- MODAL HEADER -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #EFEBF4;background:#FAF8FD;">
                <div>
                    <h3 style="font-size:17px;font-weight:800;color:var(--hanania-primary);margin:0;display:flex;align-items:center;gap:8px;">
                        <span>📋</span> Detail Lengkap Pendaftaran Pasien
                    </h3>
                    <div style="font-size:12px;color:var(--hanania-muted);margin-top:2px;" id="detailSubHeader">
                        Memuat data pendaftaran...
                    </div>
                </div>
                <button type="button" onclick="tutupModalDetailPendaftaran()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--hanania-muted);line-height:1;padding:4px;" title="Tutup">✕</button>
            </div>

            <!-- MODAL BODY (SCROLLABLE) -->
            <div style="padding:20px 24px;overflow-y:auto;display:flex;flex-direction:column;gap:18px;">
                
                <!-- SECTION 1: PELAYANAN & KUNJUNGAN -->
                <div style="background:#FAF8FD;border:1px solid #EADBFA;border-radius:14px;padding:16px;">
                    <h4 style="font-size:13px;font-weight:800;color:var(--hanania-primary);margin:0 0 12px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:0.3px;">
                        <span>🩺</span> Informasi Kunjungan &amp; Antrean
                    </h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;font-size:12.5px;">
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Nomor Antrean</span>
                            <strong id="detNomorAntrean" style="font-size:18px;color:var(--hanania-primary);font-weight:900;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Status Pendaftaran</span>
                            <span id="detStatusBadge" class="badge badge-purple" style="font-size:11.5px;padding:3px 10px;margin-top:2px;display:inline-block;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Poli Pelayanan</span>
                            <strong id="detNamaPoli" style="color:#2B1B48;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Dokter / Bidan Terpilih</span>
                            <strong id="detTenagaMedis" style="color:#2B1B48;">-</strong>
                            <span id="detSpesialisasi" style="font-size:11px;color:var(--hanania-muted);display:block;"></span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Tanggal Kunjungan</span>
                            <strong id="detTanggalKunjungan" style="color:#2B1B48;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Jam &amp; Sesi Kunjungan</span>
                            <strong id="detJamSesi" style="color:#2B1B48;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Jenis Pasien &amp; Jaminan</span>
                            <strong id="detJenisPasien" style="color:#2B1B48;">-</strong>
                            <span id="detNomorBpjs" style="font-size:11px;color:#15803D;display:block;"></span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Layanan / Tindakan Medis</span>
                            <strong id="detJenisPerawatan" style="color:#2B1B48;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Sumber Registrasi</span>
                            <span id="detSumber" style="font-weight:700;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:var(--hanania-muted);display:block;font-weight:700;">Waktu Mendaftar Masuk</span>
                            <span id="detWaktuDaftar" style="color:#4B4453;">-</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: DATA DIRI PASIEN -->
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;padding:16px;">
                    <h4 style="font-size:13px;font-weight:800;color:#334155;margin:0 0 12px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:0.3px;">
                        <span>👤</span> Data Identitas Pasien
                    </h4>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;font-size:12.5px;">
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Nama Lengkap</span>
                            <strong id="detNamaPasien" style="color:#0F172A;font-size:13.5px;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">NIK (Identitas Pasien)</span>
                            <strong id="detNik" style="color:#0F172A;font-family:monospace;font-size:13px;">-</strong>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Tanggal Lahir / Usia</span>
                            <span id="detTanggalLahir" style="color:#0F172A;font-weight:600;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Jenis Kelamin</span>
                            <span id="detJenisKelamin" style="color:#0F172A;font-weight:600;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">No. Telepon / WhatsApp</span>
                            <span id="detNoTelepon" style="color:#0F172A;font-weight:600;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Email</span>
                            <span id="detEmail" style="color:#0F172A;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Status Perkawinan</span>
                            <span id="detStatusPerkawinan" style="color:#0F172A;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Agama</span>
                            <span id="detAgama" style="color:#0F172A;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Nama Ibu Kandung</span>
                            <span id="detNamaIbu" style="color:#0F172A;font-weight:600;">-</span>
                        </div>
                        <div>
                            <span id="lblDetNamaSuami" style="font-size:11px;color:#64748B;display:block;font-weight:700;">Nama Suami / Ayah Kandung</span>
                            <span id="detNamaSuami" style="color:#0F172A;font-weight:600;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Pendidikan Terakhir</span>
                            <span id="detPendidikan" style="color:#0F172A;">-</span>
                        </div>
                        <div>
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Pekerjaan</span>
                            <span id="detPekerjaan" style="color:#0F172A;">-</span>
                        </div>
                        <div style="grid-column:1 / -1;">
                            <span style="font-size:11px;color:#64748B;display:block;font-weight:700;">Alamat Lengkap</span>
                            <span id="detAlamat" style="color:#0F172A;line-height:1.5;">-</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: KELUHAN & RUJUKAN -->
                <div style="background:#FFFBF5;border:1px solid #FED7AA;border-radius:14px;padding:16px;">
                    <h4 style="font-size:13px;font-weight:800;color:#9A3412;margin:0 0 12px;display:flex;align-items:center;gap:6px;text-transform:uppercase;letter-spacing:0.3px;">
                        <span>📝</span> Keluhan Medis &amp; Informasi Rujukan
                    </h4>
                    <div style="display:flex;flex-direction:column;gap:10px;font-size:12.5px;">
                        <div>
                            <span style="font-size:11px;color:#78350F;display:block;font-weight:700;margin-bottom:2px;">Keluhan Pasien:</span>
                            <div id="detKeluhan" style="background:#fff;border:1px solid #FFEDD5;padding:10px 14px;border-radius:10px;color:#431407;line-height:1.5;">
                                -
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-top:4px;">
                            <div>
                                <span style="font-size:11px;color:#78350F;display:block;font-weight:700;">Status Surat Rujukan:</span>
                                <span id="detAdaRujukan" style="font-weight:700;">-</span>
                            </div>
                            <div>
                                <span style="font-size:11px;color:#78350F;display:block;font-weight:700;">Nama Dokter / Instansi Perujuk:</span>
                                <span id="detNamaPerujuk" style="color:#431407;">-</span>
                            </div>
                            <div style="grid-column:1 / -1;">
                                <span style="font-size:11px;color:#78350F;display:block;font-weight:700;">Berkas File Surat Rujukan:</span>
                                <div id="detFileRujukanContainer" style="margin-top:4px;">
                                    <span style="color:var(--hanania-muted);font-style:italic;">Tidak ada berkas rujukan diunggah.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- MODAL FOOTER -->
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;padding:14px 24px;border-top:1px solid #EFEBF4;background:#FAF8FD;">
                <button type="button" class="btn btn-light" onclick="tutupModalDetailPendaftaran()">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</main>

<script>
function bukaModalAntreanOffline() {
    document.getElementById('modalAntreanOffline').style.display = 'flex';
}
function tutupModalAntreanOffline() {
    document.getElementById('modalAntreanOffline').style.display = 'none';
}
function toggleBoxPasienBaru(val) {
    const box = document.getElementById('boxPasienBaru');
    if (box) {
        box.style.display = (val === '0') ? 'block' : 'none';
    }
}
function toggleBpjsBox(val) {
    const box = document.getElementById('boxBpjsOfflineInput');
    if (box) {
        box.style.display = (val === 'BPJS') ? 'block' : 'none';
    }
}

function bukaModalDetailPendaftaran(d) {
    const modal = document.getElementById('modalDetailPendaftaran');
    if (!modal || !d) return;

    // Sub Header
    document.getElementById('detailSubHeader').textContent = 
        'Registrasi ID #' + (d.id_pendaftaran || '-') + 
        ' · Terdaftar: ' + (d.created_at ? formatWaktuIndo(d.created_at) : '-');

    // Pelayanan & Antrean
    const noAntrean = d.nomor_antrean ? '#' + String(d.nomor_antrean).padStart(3, '0') : ('#' + String(d.id_pendaftaran || '-').padStart(3, '0'));
    document.getElementById('detNomorAntrean').textContent = noAntrean;

    const st = (d.status_pendaftaran || 'menunggu').toLowerCase();
    const stBadge = document.getElementById('detStatusBadge');
    stBadge.textContent = st.charAt(0).toUpperCase() + st.slice(1);
    stBadge.className = 'badge ' + (st === 'selesai' ? 'badge-success' : (st === 'dibatalkan' ? 'badge-danger' : (st === 'terdaftar' ? 'badge-purple' : 'badge-warning')));

    document.getElementById('detNamaPoli').textContent = d.nama_poli || '-';
    document.getElementById('detTenagaMedis').textContent = d.tenaga_medis || '-';
    document.getElementById('detSpesialisasi').textContent = d.spesialisasi ? '(' + d.spesialisasi + ')' : '';
    document.getElementById('detTanggalKunjungan').textContent = d.tanggal_kunjungan ? formatTanggalIndo(d.tanggal_kunjungan) : '-';
    
    let jamSesiText = (d.jam_kunjungan ? d.jam_kunjungan.substring(0, 5) + ' WIB' : '-');
    if (d.sesi_kunjungan) {
        jamSesiText += ' (Sesi ' + d.sesi_kunjungan + ')';
    }
    document.getElementById('detJamSesi').textContent = jamSesiText;

    document.getElementById('detJenisPasien').textContent = d.jenis_pasien || 'Umum';
    let bpjsText = '';
    if (d.nomor_bpjs) bpjsText += 'No. Kartu: ' + d.nomor_bpjs;
    if (d.nomor_antrean_jkn) bpjsText += (bpjsText ? ' · ' : '') + 'Antrean JKN: ' + d.nomor_antrean_jkn;
    document.getElementById('detNomorBpjs').textContent = bpjsText;

    document.getElementById('detJenisPerawatan').textContent = d.jenis_perawatan || 'Pemeriksaan Medis Umum';
    document.getElementById('detSumber').textContent = (d.sumber_pendaftaran || 'online').toUpperCase();
    document.getElementById('detWaktuDaftar').textContent = d.created_at ? formatWaktuIndo(d.created_at) : '-';

    // Data Identitas Pasien
    document.getElementById('detNamaPasien').textContent = d.nama_lengkap || d.nama_pasien || 'Pasien';
    document.getElementById('detNik').textContent = d.nik || '-';
    
    let tglLahirText = d.tanggal_lahir ? formatTanggalIndo(d.tanggal_lahir) : '-';
    if (d.tanggal_lahir) {
        const age = hitungUsia(d.tanggal_lahir);
        if (age !== null) tglLahirText += ' (' + age + ' tahun)';
    }
    document.getElementById('detTanggalLahir').textContent = tglLahirText;

    const jk = (d.jenis_kelamin || '').toUpperCase();
    document.getElementById('detJenisKelamin').textContent = jk === 'L' ? 'Laki-laki (L)' : (jk === 'P' ? 'Perempuan (P)' : (d.jenis_kelamin || '-'));
    document.getElementById('detNoTelepon').textContent = d.no_telepon || '-';
    document.getElementById('detEmail').textContent = d.email || '-';
    document.getElementById('detStatusPerkawinan').textContent = d.status_perkawinan || '-';
    document.getElementById('detAgama').textContent = d.agama || '-';
    document.getElementById('detPendidikan').textContent = d.pendidikan_terakhir || '-';
    document.getElementById('detPekerjaan').textContent = d.pekerjaan || '-';
    document.getElementById('detNamaIbu').textContent = d.nama_ibu_kandung || '-';
    const isBelumNikahP = (!d.status_perkawinan || d.status_perkawinan === 'Belum Menikah');
    if (document.getElementById('lblDetNamaSuami')) document.getElementById('lblDetNamaSuami').textContent = isBelumNikahP ? 'Nama Ayah Kandung' : 'Nama Suami / Pasangan';
    document.getElementById('detNamaSuami').textContent = isBelumNikahP ? (d.nama_ayah_kandung || d.nama_suami || '-') : (d.nama_suami || d.nama_ayah_kandung || '-');
    document.getElementById('detAlamat').textContent = d.alamat || '-';

    // Keluhan & Rujukan
    document.getElementById('detKeluhan').textContent = d.keluhan || 'Tidak ada keluhan tertulis.';
    
    const adaRuj = (d.ada_rujukan || (d.nama_perujuk || d.file_rujukan ? 'Ya' : 'Tidak'));
    document.getElementById('detAdaRujukan').textContent = adaRuj;
    
    const perujuk = d.nama_perujuk || d.asal_rujukan || d.asal_rujukan_pasien || '-';
    document.getElementById('detNamaPerujuk').textContent = perujuk;

    const containerFile = document.getElementById('detFileRujukanContainer');
    if (d.file_rujukan) {
        const fileUrl = '../' + d.file_rujukan.replace(/^\.\.\//, '').replace(/^\//, '');
        containerFile.innerHTML = '<a href="' + fileUrl + '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--hanania-primary);border-color:#D8CAEC;"><span>📄</span> Buka / Unduh Berkas Rujukan</a>';
    } else {
        containerFile.innerHTML = '<span style="color:var(--hanania-muted);font-style:italic;font-size:12px;">Tidak ada berkas rujukan diunggah.</span>';
    }

    modal.style.display = 'flex';
}

function tutupModalDetailPendaftaran() {
    const modal = document.getElementById('modalDetailPendaftaran');
    if (modal) modal.style.display = 'none';
}

function formatTanggalIndo(dateStr) {
    if (!dateStr) return '-';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return parseInt(parts[2], 10) + ' ' + (bulan[parseInt(parts[1], 10)] || parts[1]) + ' ' + parts[0];
        }
    } catch(e) {}
    return dateStr;
}

function formatWaktuIndo(datetimeStr) {
    if (!datetimeStr) return '-';
    try {
        const p = datetimeStr.split(' ');
        const tgl = formatTanggalIndo(p[0]);
        const jam = p[1] ? p[1].substring(0, 5) + ' WIB' : '';
        return tgl + ' ' + jam;
    } catch(e) {}
    return datetimeStr;
}

function hitungUsia(birthDateStr) {
    if (!birthDateStr) return null;
    const b = new Date(birthDateStr);
    if (isNaN(b.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - b.getFullYear();
    const m = today.getMonth() - b.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < b.getDate())) {
        age--;
    }
    return age;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-detail-pendaftaran').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.getAttribute('data-pendaftaran'));
                bukaModalDetailPendaftaran(data);
            } catch(e) {
                console.error('Gagal parsing data pendaftaran:', e);
            }
        });
    });
});

window.addEventListener('click', function(e) {
    const modalOffline = document.getElementById('modalAntreanOffline');
    if (modalOffline && e.target === modalOffline) {
        modalOffline.style.display = 'none';
    }
    const modalDetail = document.getElementById('modalDetailPendaftaran');
    if (modalDetail && e.target === modalDetail) {
        modalDetail.style.display = 'none';
    }
});
</script>
<?php adminPageEnd(); ?>
