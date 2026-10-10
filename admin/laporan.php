<?php
require_once __DIR__ . '/_guard.php';

// Default rentang tanggal kunjungan: 30 hari terakhir hingga hari ini
$defaultAwal = date('Y-m-d', strtotime('-30 days'));
$defaultAkhir = date('Y-m-d');

// Filter berdasarkan Tanggal Kunjungan (bisa memilih rentang maupun satu hari tertentu)
if (!empty($_GET['tgl_awal']) && empty($_GET['tgl_akhir'])) {
    $tglAwal = trim($_GET['tgl_awal']);
    $tglAkhir = $tglAwal;
} elseif (empty($_GET['tgl_awal']) && !empty($_GET['tgl_akhir'])) {
    $tglAkhir = trim($_GET['tgl_akhir']);
    $tglAwal = $tglAkhir;
} else {
    $tglAwal = trim($_GET['tgl_awal'] ?? $defaultAwal);
    $tglAkhir = trim($_GET['tgl_akhir'] ?? $defaultAkhir);
}

$sumber = trim($_GET['sumber'] ?? ''); // Default semua sumber agar pendaftaran tidak terlewat
$status = trim($_GET['status'] ?? '');
$idPoli = (int)($_GET['id_poli'] ?? 0);

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAwal)) $tglAwal = $defaultAwal;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglAkhir)) $tglAkhir = $defaultAkhir;
if ($tglAwal > $tglAkhir) {
    $temp = $tglAwal;
    $tglAwal = $tglAkhir;
    $tglAkhir = $temp;
}

// Opsi dropdown poli
$listPoli = [];
$resP = $db->query("SELECT id_poli, nama_poli FROM poli ORDER BY nama_poli ASC");
if ($resP) {
    while ($row = $resP->fetch_assoc()) {
        $listPoli[] = $row;
    }
}

// BUILD QUERY: FILTER BERDASARKAN TANGGAL KUNJUNGAN
$whereSql = "WHERE p.tanggal_kunjungan BETWEEN ? AND ?";
$bindTypes = "ss";
$bindParams = [$tglAwal, $tglAkhir];

if ($sumber !== '' && in_array($sumber, ['online', 'offline'], true)) {
    $whereSql .= " AND p.sumber_pendaftaran = ?";
    $bindTypes .= "s";
    $bindParams[] = $sumber;
}

if ($status !== '' && in_array($status, ['menunggu', 'terdaftar', 'selesai', 'dibatalkan'], true)) {
    $whereSql .= " AND p.status_pendaftaran = ?";
    $bindTypes .= "s";
    $bindParams[] = $status;
}

if ($idPoli > 0) {
    $whereSql .= " AND p.id_poli = ?";
    $bindTypes .= "i";
    $bindParams[] = $idPoli;
}

// QUERY DATA UTAMA
$sqlReport = "
    SELECT p.*,
           pa.nama_lengkap AS nama_pasien, pa.nik, pa.tanggal_lahir, pa.jenis_kelamin,
           pa.no_telepon, pa.email, pa.alamat, pa.pekerjaan, pa.status_perkawinan,
           pa.pendidikan_terakhir, pa.agama, pa.ada_rujukan, pa.asal_rujukan AS asal_rujukan_pasien,
           po.nama_poli,
           COALESCE(d.nama_dokter, b.nama_bidan, 'Tenaga Medis') AS tenaga_medis,
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
    $whereSql
    ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
";

$stmtRep = $db->prepare($sqlReport);
$dataLaporan = [];

if ($stmtRep) {
    $stmtRep->bind_param($bindTypes, ...$bindParams);
    $stmtRep->execute();
    $resRep = $stmtRep->get_result();
    while ($r = $resRep->fetch_assoc()) {
        $dataLaporan[] = $r;
    }
    $stmtRep->close();
}

// EXPORT CSV / EXCEL
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Laporan_Kunjungan_Hanania_" . $tglAwal . "_sd_" . $tglAkhir . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    // UTF-8 BOM untuk Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, [
        'No', 'ID Pendaftaran', 'Tanggal Kunjungan', 'Jam Pelayanan', 'Sesi',
        'Nomor Antrean', 'Nama Pasien', 'NIK', 'No Telepon',
        'Poli', 'Tenaga Medis',
        'Sumber', 'Jenis Pasien', 'Status', 'Keluhan'
    ]);

    $no = 1;
    foreach ($dataLaporan as $row) {
        $nomorDisplay = !empty($row['nomor_antrean']) ? str_pad((string)$row['nomor_antrean'], 3, '0', STR_PAD_LEFT) : '-';
        fputcsv($out, [
            $no++,
            $row['id_pendaftaran'],
            $row['tanggal_kunjungan'],
            substr((string)$row['jam_kunjungan'], 0, 5),
            $row['sesi_kunjungan'] ?? '-',
            $nomorDisplay,
            $row['nama_pasien'],
            $row['nik'] ? "'" . $row['nik'] : '-',
            $row['no_telepon'],
            $row['nama_poli'],
            $row['tenaga_medis'],
            ucfirst($row['sumber_pendaftaran']),
            $row['jenis_pasien'],
            ucfirst($row['status_pendaftaran']),
            $row['keluhan']
        ]);
    }
    fclose($out);
    exit;
}

// RINGKASAN STATISTIK LAPORAN
$totalData = count($dataLaporan);
$countOnline = 0;
$countOffline = 0;
$countMenunggu = 0;
$countSelesai = 0;
$countBpjs = 0;
$countUmum = 0;

foreach ($dataLaporan as $row) {
    if (($row['sumber_pendaftaran'] ?? '') === 'online') $countOnline++;
    else $countOffline++;

    $st = $row['status_pendaftaran'] ?? 'menunggu';
    if ($st === 'menunggu') $countMenunggu++;
    elseif ($st === 'selesai') $countSelesai++;

    if (($row['jenis_pasien'] ?? '') === 'BPJS') $countBpjs++;
    else $countUmum++;
}

adminPageStart('Laporan Pendaftaran', 'laporan');
?>
<style>
@media print {
    .admin-sidebar, .admin-topbar, .admin-filter-bar, .report-actions, .btn, .no-print {
        display: none !important;
    }
    .admin-main {
        margin-left: 0 !important;
        width: 100% !important;
        background: #fff !important;
    }
    .admin-page {
        padding: 0 !important;
    }
    .admin-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #333;
    }
    .print-signatures {
        display: flex !important;
        justify-content: space-between;
        margin-top: 40px;
        page-break-inside: avoid;
    }
    table {
        min-width: 100% !important;
        font-size: 11px !important;
    }
    th, td {
        padding: 8px 10px !important;
        border: 1px solid #ccc !important;
    }
}
.print-header, .print-signatures {
    display: none;
}
</style>

<main class="admin-page">
    <!-- PRINT HEADER (HANYA MUNCUL SAAT DICETAK) -->
    <div class="print-header">
        <h2 style="font-size:20px;font-weight:900;margin-bottom:4px;color:#000;">KLINIK PRATAMA RUMAH HANANIA</h2>
        <p style="font-size:12px;margin-bottom:2px;">Jl. Desa Sukadamai No.RT 01, RT.01/RW.06, Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165</p>
        <p style="font-size:12px;margin-bottom:6px;">WhatsApp / Call Center: 088510703839 · 085714178178</p>
        <h3 style="font-size:15px;text-transform:uppercase;margin-top:10px;text-decoration:underline;">
            LAPORAN KUNJUNGAN PASIEN
        </h3>
        <p style="font-size:11.5px;margin-top:4px;">
            Periode Tanggal Kunjungan: <strong><?= date('d/m/Y', strtotime($tglAwal)) ?></strong><?= ($tglAwal !== $tglAkhir ? ' s/d <strong>' . date('d/m/Y', strtotime($tglAkhir)) . '</strong>' : '') ?>
            · Sumber: <strong><?= $sumber ? strtoupper($sumber) : 'SEMUA' ?></strong>
        </p>
    </div>

    <header class="admin-header no-print">
        <div>
            <h1><span>📈</span> Laporan Kunjungan Pasien</h1>
            <p>
                Filter dan analisa data reservasi pasien <strong>berdasarkan tanggal kunjungan pasien</strong>.
            </p>
        </div>
        <div class="admin-header-actions report-actions">
            <button type="button" class="btn btn-light" onclick="window.print()">
                <span>🖨️</span> Cetak / Simpan PDF
            </button>
            <?php 
                $exportQuery = $_GET; 
                $exportQuery['export'] = 'csv'; 
            ?>
            <a href="?<?= http_build_query($exportQuery) ?>" class="btn">
                <span>📥</span> Ekspor Excel (CSV)
            </a>
        </div>
    </header>

    <!-- FORM FILTER TANGGAL KUNJUNGAN -->
    <section class="admin-card no-print" style="padding:20px;">
        <form method="GET" class="form-grid" style="align-items:flex-end;">
            <div class="form-field">
                <label>Tanggal Kunjungan (Mulai) *</label>
                <input type="date" name="tgl_awal" value="<?= adminEsc($tglAwal) ?>" required>
            </div>

            <div class="form-field">
                <label>Tanggal Kunjungan (Sampai) *</label>
                <input type="date" name="tgl_akhir" value="<?= adminEsc($tglAkhir) ?>" required>
            </div>

            <div class="form-field">
                <label>Sumber Registrasi</label>
                <select name="sumber">
                    <option value="">-- Semua Sumber --</option>
                    <option value="online" <?= $sumber === 'online' ? 'selected' : '' ?>>Pendaftaran Online (Web)</option>
                    <option value="offline" <?= $sumber === 'offline' ? 'selected' : '' ?>>Pendaftaran Langsung (Offline)</option>
                </select>
            </div>

            <div class="form-field">
                <label>Status Pelayanan</label>
                <select name="status">
                    <option value="">-- Semua Status --</option>
                    <option value="menunggu" <?= $status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="terdaftar" <?= $status === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                    <option value="selesai" <?= $status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>

            <div class="form-field">
                <label>Poli Pelayanan</label>
                <select name="id_poli">
                    <option value="0">-- Semua Poli --</option>
                    <?php foreach ($listPoli as $p): ?>
                        <option value="<?= (int)$p['id_poli'] ?>" <?= $idPoli === (int)$p['id_poli'] ? 'selected' : '' ?>>
                            <?= adminEsc($p['nama_poli']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field" style="display:flex;flex-direction:row;gap:6px;flex-wrap:wrap;">
                <button type="submit" class="btn" style="flex:1;">
                    <span>🔍</span> Tampilkan
                </button>
                <a href="laporan.php" class="btn btn-light" title="Reset Filter">↻</a>
            </div>
        </form>
    </section>

    <!-- METRIK RINGKASAN HASIL FILTER -->
    <section style="display:grid;grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:14px;margin-bottom:24px;">
        <div class="admin-card" style="margin-bottom:0;padding:16px;">
            <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;">Total Record</span>
            <div style="font-size:24px;font-weight:900;color:var(--hanania-primary);margin-top:2px;">
                <?= number_format($totalData, 0, ',', '.') ?>
            </div>
            <span style="font-size:11px;color:var(--hanania-muted);">Pendaftaran terpilih</span>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:16px;border-left:4px solid #D97706;">
            <span style="font-size:11px;font-weight:800;color:#92400E;text-transform:uppercase;">Status Menunggu</span>
            <div style="font-size:24px;font-weight:900;color:#D97706;margin-top:2px;">
                <?= number_format($countMenunggu, 0, ',', '.') ?>
            </div>
            <span style="font-size:11px;color:#B45309;font-weight:700;">⏳ Pasien baru mendaftar</span>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:16px;">
            <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;">Reservasi Online</span>
            <div style="font-size:24px;font-weight:900;color:#7650A2;margin-top:2px;">
                <?= number_format($countOnline, 0, ',', '.') ?>
            </div>
            <span style="font-size:11px;color:var(--hanania-muted);"><?= $totalData > 0 ? round(($countOnline / $totalData) * 100) : 0 ?>% dari total</span>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:16px;">
            <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;">Reservasi Offline</span>
            <div style="font-size:24px;font-weight:900;color:#675F73;margin-top:2px;">
                <?= number_format($countOffline, 0, ',', '.') ?>
            </div>
            <span style="font-size:11px;color:var(--hanania-muted);">Pasien langsung</span>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:16px;">
            <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;">Pelayanan Selesai</span>
            <div style="font-size:24px;font-weight:900;color:#1B7A3E;margin-top:2px;">
                <?= number_format($countSelesai, 0, ',', '.') ?>
            </div>
            <span style="font-size:11px;color:#1B7A3E;font-weight:700;">Telah dilayani</span>
        </div>

        <div class="admin-card" style="margin-bottom:0;padding:16px;">
            <span style="font-size:11px;font-weight:800;color:var(--hanania-muted);text-transform:uppercase;">BPJS vs UMUM</span>
            <div style="font-size:20px;font-weight:900;color:var(--hanania-primary);margin-top:4px;">
                <?= $countBpjs ?> <span style="font-size:12px;color:var(--hanania-muted);font-weight:600;">BPJS</span> / <?= $countUmum ?> <span style="font-size:12px;color:var(--hanania-muted);font-weight:600;">Umum</span>
            </div>
            <span style="font-size:11px;color:var(--hanania-muted);">Komposisi jaminan</span>
        </div>
    </section>

    <!-- TABEL HASIL LAPORAN -->
    <section class="admin-card">
        <div class="admin-card-head">
            <div>
                <div class="admin-card-title">
                    <span>📄</span> Data Rinci Kunjungan Pasien Periode <?= date('d M Y', strtotime($tglAwal)) ?><?= ($tglAwal !== $tglAkhir ? ' s/d ' . date('d M Y', strtotime($tglAkhir)) : '') ?>
                </div>
                <div class="admin-card-subtitle">
                    Disusun berdasarkan tanggal kunjungan pasien ke klinik
                </div>
            </div>
            <span class="badge badge-purple"><?= $totalData ?> Data Ditemukan</span>
        </div>

        <?php if (empty($dataLaporan)): ?>
            <div style="text-align:center;padding:45px 15px;color:var(--hanania-muted);">
                <div style="font-size:36px;margin-bottom:10px;">📋</div>
                <strong>Tidak ada data kunjungan pada tanggal kunjungan yang dipilih.</strong>
                <p style="font-size:12.5px;margin-top:4px;">Coba sesuaikan tanggal kunjungan mulai dan akhir pada form di atas.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;">No</th>
                            <th>No. Antrean</th>
                            <th>Nama Pasien &amp; Kontak</th>
                            <th>Poli &amp; Tenaga Medis</th>
                            <th>Tanggal Kunjungan</th>
                            <th>Sesi &amp; Jam</th>
                            <th>Sumber</th>
                            <th>Jenis Pasien</th>
                            <th>Status</th>
                            <th class="no-print" style="text-align:center;width:115px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($dataLaporan as $row): 
                            $status = $row['status_pendaftaran'] ?? 'menunggu';
                            $badgeClass = 'badge-warning';
                            if ($status === 'selesai') $badgeClass = 'badge-success';
                            elseif ($status === 'dibatalkan') $badgeClass = 'badge-danger';
                            elseif ($status === 'terdaftar') $badgeClass = 'badge-purple';

                            $nomorDisplay = !empty($row['nomor_antrean']) 
                                ? '#' . str_pad((string)$row['nomor_antrean'], 3, '0', STR_PAD_LEFT)
                                : '⏳ Menunggu';
                            $sumber = ($row['sumber_pendaftaran'] ?? 'online') === 'online' ? 'Online' : 'Offline';
                            $sesiDisplay = !empty($row['sesi_kunjungan']) ? 'Sesi ' . $row['sesi_kunjungan'] : '-';
                        ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--hanania-muted);"><?= $no++ ?></td>
                            <td>
                                <strong style="color:var(--hanania-primary);font-size:13.5px;font-weight:900;">
                                    <?= adminEsc($nomorDisplay) ?>
                                </strong>
                                <span style="display:block;font-size:10px;color:var(--hanania-muted);">Reg #<?= $row['id_pendaftaran'] ?></span>
                            </td>
                            <td>
                                <strong style="color:var(--hanania-text);display:block;"><?= adminEsc($row['nama_pasien'] ?? 'Pasien') ?></strong>
                                <span style="font-size:11px;color:var(--hanania-muted);">NIK: <?= adminEsc($row['nik'] ?? '-') ?></span>
                                <?php if (!empty($row['no_telepon'])): ?>
                                    <span style="display:block;font-size:10.5px;color:var(--hanania-muted);">Telp: <?= adminEsc($row['no_telepon']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= adminEsc($row['nama_poli'] ?? '-') ?></strong>
                                <span style="display:block;font-size:11.5px;color:var(--hanania-muted);"><?= adminEsc($row['tenaga_medis']) ?></span>
                            </td>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($row['tanggal_kunjungan'])) ?></strong>
                            </td>
                            <td>
                                <span style="display:inline-block;font-size:11px;background:#F3E8FF;color:#6C34A3;padding:2px 7px;border-radius:4px;font-weight:700;">
                                    <?= adminEsc($sesiDisplay) ?>
                                </span>
                                <span style="display:block;font-size:11px;color:var(--hanania-muted);margin-top:2px;">
                                    <?= !empty($row['jam_kunjungan']) ? adminEsc(substr($row['jam_kunjungan'], 0, 5)) . ' WIB' : '-' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $sumber === 'Online' ? 'badge-purple' : 'badge-gray' ?>">
                                    <?= $sumber ?>
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
                            <td class="no-print" style="text-align:center;">
                                <button type="button" class="btn btn-sm btn-light btn-detail-pendaftaran" 
                                        data-pendaftaran="<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>"
                                        style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;font-size:11.5px;font-weight:700;"
                                        title="Lihat Detail Pasien &amp; Pendaftaran">
                                    👁️ Lihat Detail
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

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

    <script>
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
        document.getElementById('detNamaPasien').textContent = d.nama_pasien || d.nama_lengkap || 'Pasien';
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
    </script>

    <!-- FOOTER CETAK & TANDA TANGAN (HANYA SAAT PRINT) -->
    <div class="print-signatures">
        <div style="text-align:left;width:250px;">
            <p style="font-size:12px;margin-bottom:60px;">Mengetahui,<br><strong>Penanggung Jawab Medis</strong></p>
            <p style="font-size:12px;border-top:1px solid #333;padding-top:4px;"><strong>( dr. Patricia Mellroviane )</strong><br>SIP: 446.1/045/SIP-D/DPMPTSP</p>
        </div>
        <div style="text-align:right;width:250px;">
            <p style="font-size:12px;margin-bottom:60px;">Bogor, <?= date('d F Y') ?><br><strong>Petugas Rekam Medis &amp; Adm</strong></p>
            <p style="font-size:12px;border-top:1px solid #333;padding-top:4px;"><strong>( <?= adminEsc($_SESSION['admin_username'] ?? 'Petugas Klinik') ?> )</strong><br>Klinik Pratama Rumah Hanania</p>
        </div>
    </div>
</main>
<?php
adminPageEnd();
