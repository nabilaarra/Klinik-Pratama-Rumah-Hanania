<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// -------------------------------------------------------------------------
// 1. PROSES POST: TAMBAH & EDIT PASIEN
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'tambah_pasien') {
            $nik = trim($_POST['nik'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $tglLahir = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
            $jk = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P'], true) ? $_POST['jenis_kelamin'] : null;
            $telp = trim($_POST['no_telepon'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $pekerjaan = trim($_POST['pekerjaan'] ?? '');
            $statusKawin = trim($_POST['status_perkawinan'] ?? '');
            $pendidikan = trim($_POST['pendidikan_terakhir'] ?? '');
            $agama = trim($_POST['agama'] ?? '');
            $namaIbu = trim($_POST['nama_ibu_kandung'] ?? '');
            $namaSuami = trim($_POST['nama_suami'] ?? '');
            $namaAyah = trim($_POST['nama_ayah_kandung'] ?? '');
            $adaRujukan = in_array($_POST['ada_rujukan'] ?? '', ['Ya', 'Tidak'], true) ? $_POST['ada_rujukan'] : 'Tidak';
            $asalRujukan = ($adaRujukan === 'Ya') ? trim($_POST['asal_rujukan'] ?? '') : null;

            if ($nama === '') {
                throw new Exception('Nama lengkap pasien wajib diisi.');
            }

            // Validasi NIK unik jika diisi
            if ($nik !== '') {
                $chk = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param('s', $nik);
                    $chk->execute();
                    if ($chk->get_result()->fetch_assoc()) {
                        $chk->close();
                        throw new Exception("NIK '{$nik}' sudah terdaftar pada pasien lain.");
                    }
                    $chk->close();
                }
            } else {
                $nik = null;
            }

            $stmt = $db->prepare("
                INSERT INTO pasien (
                    nik, nama_lengkap, tanggal_lahir, jenis_kelamin, 
                    no_telepon, email, alamat, pekerjaan, 
                    status_perkawinan, pendidikan_terakhir, agama, 
                    nama_ibu_kandung, nama_suami, nama_ayah_kandung,
                    ada_rujukan, asal_rujukan
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception('Gagal menyiapkan query simpan data: ' . $db->error);
            }

            $stmt->bind_param(
                'ssssssssssssssss',
                $nik, $nama, $tglLahir, $jk,
                $telp, $email, $alamat, $pekerjaan,
                $statusKawin, $pendidikan, $agama,
                $namaIbu, $namaSuami, $namaAyah,
                $adaRujukan, $asalRujukan
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Gagal menyimpan data pasien: ' . $err);
            }
            $stmt->close();

            $message = "Pasien baru atas nama '{$nama}' berhasil ditambahkan!";
            $messageType = 'success';

        } elseif ($action === 'edit_pasien') {
            $idPasien = (int)($_POST['id_pasien'] ?? 0);
            $nik = trim($_POST['nik'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $tglLahir = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
            $jk = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P'], true) ? $_POST['jenis_kelamin'] : null;
            $telp = trim($_POST['no_telepon'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $pekerjaan = trim($_POST['pekerjaan'] ?? '');
            $statusKawin = trim($_POST['status_perkawinan'] ?? '');
            $pendidikan = trim($_POST['pendidikan_terakhir'] ?? '');
            $agama = trim($_POST['agama'] ?? '');
            $namaIbu = trim($_POST['nama_ibu_kandung'] ?? '');
            $namaSuami = trim($_POST['nama_suami'] ?? '');
            $namaAyah = trim($_POST['nama_ayah_kandung'] ?? '');
            $adaRujukan = in_array($_POST['ada_rujukan'] ?? '', ['Ya', 'Tidak'], true) ? $_POST['ada_rujukan'] : 'Tidak';
            $asalRujukan = ($adaRujukan === 'Ya') ? trim($_POST['asal_rujukan'] ?? '') : null;

            if ($idPasien <= 0) {
                throw new Exception('ID Pasien tidak valid.');
            }
            if ($nama === '') {
                throw new Exception('Nama lengkap pasien wajib diisi.');
            }

            // Validasi NIK jika diisi tidak boleh bentrok dengan pasien lain
            if ($nik !== '') {
                $chk = $db->prepare("SELECT id_pasien FROM pasien WHERE nik = ? AND id_pasien != ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param('si', $nik, $idPasien);
                    $chk->execute();
                    if ($chk->get_result()->fetch_assoc()) {
                        $chk->close();
                        throw new Exception("NIK '{$nik}' sudah digunakan oleh pasien lain.");
                    }
                    $chk->close();
                }
            } else {
                $nik = null;
            }

            $stmt = $db->prepare("
                UPDATE pasien SET 
                    nik = ?, 
                    nama_lengkap = ?, 
                    tanggal_lahir = ?, 
                    jenis_kelamin = ?, 
                    no_telepon = ?, 
                    email = ?, 
                    alamat = ?, 
                    pekerjaan = ?, 
                    status_perkawinan = ?, 
                    pendidikan_terakhir = ?, 
                    agama = ?, 
                    nama_ibu_kandung = ?, 
                    nama_suami = ?, 
                    nama_ayah_kandung = ?,
                    ada_rujukan = ?, 
                    asal_rujukan = ?
                WHERE id_pasien = ?
            ");

            if (!$stmt) {
                throw new Exception('Gagal menyiapkan query update: ' . $db->error);
            }

            $stmt->bind_param(
                'ssssssssssssssssi',
                $nik, $nama, $tglLahir, $jk,
                $telp, $email, $alamat, $pekerjaan,
                $statusKawin, $pendidikan, $agama,
                $namaIbu, $namaSuami, $namaAyah,
                $adaRujukan, $asalRujukan,
                $idPasien
            );

            if (!$stmt->execute()) {
                $err = $stmt->error;
                $stmt->close();
                throw new Exception('Gagal memperbarui data pasien: ' . $err);
            }
            $stmt->close();

            $message = "Data pasien '{$nama}' berhasil diperbarui!";
            $messageType = 'success';
        } elseif ($action === 'acc_penukaran') {
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            if ($idPenukaran <= 0) {
                throw new Exception('ID Penukaran tidak valid.');
            }
            $catatan = trim($_POST['catatan_admin'] ?? 'Disetujui oleh admin');

            $qCheck = $db->query("SELECT id_pasien, poin_digunakan, status FROM penukaran_poin WHERE id_penukaran = $idPenukaran LIMIT 1");
            if (!$qCheck || !$rowCheck = $qCheck->fetch_assoc()) {
                throw new Exception('Data pengajuan tidak ditemukan.');
            }
            if ($rowCheck['status'] === 'disetujui') {
                throw new Exception('Pengajuan ini sudah disetujui sebelumnya.');
            }

            $idPas = (int)$rowCheck['id_pasien'];
            $poinPakai = (int)$rowCheck['poin_digunakan'];
            if ($idPas > 0 && $poinPakai > 0) {
                $db->query("UPDATE pasien SET reward_poin = GREATEST(0, reward_poin - $poinPakai) WHERE id_pasien = $idPas");
            }

            $stmtA = $db->prepare("UPDATE penukaran_poin SET status = 'disetujui', catatan_admin = ? WHERE id_penukaran = ?");
            if ($stmtA) {
                $stmtA->bind_param('si', $catatan, $idPenukaran);
                $stmtA->execute();
                $stmtA->close();
                $message = "Permintaan penukaran reward berhasil di-ACC (Disetujui)! Saldo {$poinPakai} poin pasien telah resmi dipotong.";
                $messageType = 'success';
            }
        } elseif ($action === 'tolak_penukaran') {
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            if ($idPenukaran <= 0) {
                throw new Exception('ID Penukaran tidak valid.');
            }
            $catatan = trim($_POST['catatan_admin'] ?? 'Ditolak oleh admin');
            
            $stmtT = $db->prepare("UPDATE penukaran_poin SET status = 'ditolak', catatan_admin = ? WHERE id_penukaran = ?");
            if ($stmtT) {
                $stmtT->bind_param('si', $catatan, $idPenukaran);
                $stmtT->execute();
                $stmtT->close();
                $message = "Pengajuan penukaran reward telah ditolak. Poin pasien tidak berkurang.";
                $messageType = 'info';
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// -------------------------------------------------------------------------
// 2. PROSES GET: HAPUS PASIEN SECARA AMAN (CASCADING TRANSACTION)
// -------------------------------------------------------------------------
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    if ($idHapus > 0) {
        $db->begin_transaction();
        try {
            // Ambil info nama & id_user terlebih dahulu
            $stmtInfo = $db->prepare("SELECT nama_lengkap, id_user FROM pasien WHERE id_pasien = ?");
            if (!$stmtInfo) {
                throw new Exception('Gagal menyiapkan verifikasi data pasien.');
            }
            $stmtInfo->bind_param('i', $idHapus);
            $stmtInfo->execute();
            $resInfo = $stmtInfo->get_result();
            $dataPasienHapus = $resInfo->fetch_assoc();
            $stmtInfo->close();

            if (!$dataPasienHapus) {
                throw new Exception('Data pasien yang ingin dihapus tidak ditemukan.');
            }

            $namaHapus = $dataPasienHapus['nama_lengkap'];
            $idUserHapus = (int)($dataPasienHapus['id_user'] ?? 0);

            // Cari seluruh id_pendaftaran terkait pasien ini
            $idDaftarList = [];
            $stmtD = $db->prepare("SELECT id_pendaftaran FROM pendaftaran WHERE id_pasien = ?");
            if ($stmtD) {
                $stmtD->bind_param('i', $idHapus);
                $stmtD->execute();
                $resD = $stmtD->get_result();
                while ($rD = $resD->fetch_assoc()) {
                    $idDaftarList[] = (int)$rD['id_pendaftaran'];
                }
                $stmtD->close();
            }

            if (!empty($idDaftarList)) {
                $inClause = implode(',', $idDaftarList);
                // Hapus data anak: antrean & pengingat_pendaftaran
                $db->query("DELETE FROM antrean WHERE id_pendaftaran IN ($inClause)");
                $db->query("DELETE FROM pengingat_pendaftaran WHERE id_pendaftaran IN ($inClause)");
                // Hapus pendaftaran
                $db->query("DELETE FROM pendaftaran WHERE id_pasien = $idHapus");
            }

            // Hapus data pasien
            $stmtDelP = $db->prepare("DELETE FROM pasien WHERE id_pasien = ?");
            if (!$stmtDelP) {
                throw new Exception('Gagal menyiapkan query hapus pasien.');
            }
            $stmtDelP->bind_param('i', $idHapus);
            $stmtDelP->execute();
            $stmtDelP->close();

            // Hapus akun user jika ada dan memiliki role pasien
            if ($idUserHapus > 0) {
                $stmtDelU = $db->prepare("DELETE FROM users WHERE id_user = ? AND role = 'pasien'");
                if ($stmtDelU) {
                    $stmtDelU->bind_param('i', $idUserHapus);
                    $stmtDelU->execute();
                    $stmtDelU->close();
                }
            }

            $db->commit();
            $message = "Data pasien '{$namaHapus}' beserta seluruh riwayat kunjungannya berhasil dihapus.";
            $messageType = 'success';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'Gagal menghapus data pasien: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// -------------------------------------------------------------------------
// 3. AMBIL DATA PASIEN UNTUK DITAMPILKAN
// -------------------------------------------------------------------------
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT p.*,
           COUNT(pd.id_pendaftaran) AS jumlah_kunjungan,
           MAX(pd.tanggal_kunjungan) AS kunjungan_terakhir
    FROM pasien p
    LEFT JOIN pendaftaran pd ON pd.id_pasien = p.id_pasien
";

if ($search !== '') {
    $searchEsc = $db->real_escape_string($search);
    $sql .= " WHERE p.nama_lengkap LIKE '%$searchEsc%' OR p.nik LIKE '%$searchEsc%' OR p.no_telepon LIKE '%$searchEsc%' ";
}

$sql .= " GROUP BY p.id_pasien ORDER BY p.id_pasien DESC";
$result = $db->query($sql);

$daftarPasien = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $daftarPasien[] = $row;
    }
}

// Ambil data pengajuan penukaran poin reward
$qPenukaran = $db->query("
    SELECT pp.*, p.nama_lengkap, p.nik, p.no_telepon, p.reward_poin
    FROM penukaran_poin pp
    JOIN pasien p ON p.id_pasien = pp.id_pasien
    ORDER BY CASE WHEN pp.status = 'menunggu' THEN 0 ELSE 1 END, pp.created_at DESC
    LIMIT 50
");
$daftarPenukaran = [];
$totalMenungguAcc = 0;
if ($qPenukaran) {
    while ($rp = $qPenukaran->fetch_assoc()) {
        $daftarPenukaran[] = $rp;
        if ($rp['status'] === 'menunggu') {
            $totalMenungguAcc++;
        }
    }
}

adminPageStart('Data Pasien', 'pasien');
?>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(18, 12, 38, 0.55);
    z-index: 2100;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(3px);
}
.modal-dialog {
    background: #FFFFFF;
    border-radius: 16px;
    max-width: 640px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    animation: hananiaFadeDown 0.2s ease;
}
.modal-head {
    padding: 18px 24px;
    border-bottom: 1px solid var(--hanania-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #FAF8FD;
}
.modal-body {
    padding: 22px 24px;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (max-width: 600px) {
    .form-grid-2 {
        grid-template-columns: 1fr;
    }
}
.form-group-field {
    margin-bottom: 14px;
}
.form-group-field label {
    font-size: 12px;
    font-weight: 700;
    display: block;
    margin-bottom: 6px;
    color: var(--text);
}
.form-group-field .filter-input,
.form-group-field select,
.form-group-field textarea {
    width: 100%;
    font-size: 13px;
}
.table-scroll-hint {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--hanania-muted);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    background: #FAF8FD;
    border: 1px solid var(--hanania-border);
    padding: 6px 14px;
    border-radius: 8px;
    width: fit-content;
}
@media (min-width: 961px) {
    .sticky-col-right {
        position: sticky;
        right: 0;
        background: #FFFFFF;
        box-shadow: -6px 0 14px rgba(79, 50, 147, 0.08);
        z-index: 5;
    }
    th.sticky-col-right {
        background: #FAF8FD !important;
        z-index: 6;
    }
    tr:hover td.sticky-col-right {
        background: #FCFAFF !important;
    }
}
@media (max-width: 960px) {
    .sticky-col-right,
    th.sticky-col-right,
    td.sticky-col-right {
        position: static !important;
        box-shadow: none !important;
        width: auto !important;
    }
}
</style>

<main class="admin-page" style="padding: 24px 30px 60px;">
    <header class="admin-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:22px;font-weight:900;color:var(--hanania-primary);letter-spacing:-.3px;margin:0 0 4px;">
                <span>👥</span> Data Pasien Klinik
            </h1>
            <p style="font-size:13px;color:var(--hanania-muted);margin:0;">
                Kelola data identitas pasien, rekam profil, penambahan pasien baru, pengeditan data, dan penghapusan pasien secara terpadu.
            </p>
        </div>
        <div class="admin-header-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span class="badge badge-purple" style="font-size:13px;padding:8px 14px;">
                Total Pasien: <?= count($daftarPasien) ?> Orang
            </span>
            <button type="button" class="btn btn-sm" onclick="bukaModalTambahPasien()" style="display:inline-flex;align-items:center;gap:6px;">
                <span>➕</span> Tambah Pasien Baru
            </button>
        </div>
    </header>

    <!-- NOTIFIKASI FEEDBACK -->
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;padding:12px 18px;border-radius:10px;display:flex;align-items:center;gap:10px;">
            <span style="font-size:18px;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div style="font-size:13.5px;font-weight:600;"><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- SEARCH & FILTER BAR -->
    <div class="admin-filter-bar" style="margin-bottom:20px;">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;flex:1;">
            <input type="text" name="q" value="<?= adminEsc($search) ?>" 
                   placeholder="Cari nama pasien, NIK, atau nomor telepon..." 
                   class="filter-input" style="flex:1;min-width:240px;font-size:13px;">
            <button type="submit" class="btn btn-sm">🔍 Cari Pasien</button>
            <?php if ($search !== ''): ?>
                <a href="pasien.php" class="btn btn-sm btn-light">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ========================================================= -->
    <!-- KELOLA PENUKARAN POIN PASIEN (ACC ADMIN)                  -->
    <!-- ========================================================= -->
    <section class="admin-card" style="margin-bottom:28px;border:1.5px solid var(--hanania-border);border-radius:16px;overflow:hidden;padding:0;">
        <div style="padding:16px 22px;background:#FAF8FD;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="font-size:16px;font-weight:800;color:var(--hanania-primary);margin:0 0 3px;display:flex;align-items:center;gap:8px;">
                    <span>🎁</span> Pengajuan Penukaran Poin &amp; Klaim Reward
                </h3>
                <p style="font-size:12.5px;color:var(--hanania-muted);margin:0;">
                    Pasien yang menukarkan poin dengan voucher reward memerlukan persetujuan (ACC) dari Admin sebelum voucher dinyatakan aktif.
                </p>
            </div>
            <div>
                <?php if ($totalMenungguAcc > 0): ?>
                    <span class="badge" style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-size:12.5px;font-weight:800;padding:6px 14px;">
                        ⏳ <?= $totalMenungguAcc ?> Pengajuan Perlu di-ACC
                    </span>
                <?php else: ?>
                    <span class="badge badge-success" style="font-size:12.5px;padding:6px 14px;font-weight:800;">
                        ✅ Semua Pengajuan Telah Ditindaklanjuti
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-table-responsive" style="overflow-x:auto;width:100%;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#FCFAFF;">
                        <th style="padding:11px 16px;font-size:11px;">TANGGAL PENGAJUAN</th>
                        <th style="padding:11px 16px;font-size:11px;">PASIEN</th>
                        <th style="padding:11px 16px;font-size:11px;">REWARD / VOUCHER</th>
                        <th style="padding:11px 16px;font-size:11px;text-align:center;">POIN DITUKAR</th>
                        <th style="padding:11px 16px;font-size:11px;">KODE VOUCHER</th>
                        <th style="padding:11px 16px;font-size:11px;text-align:center;">STATUS</th>
                        <th style="padding:11px 16px;font-size:11px;text-align:center;width:190px;">AKSI PERSETUJUAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarPenukaran)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:26px;color:var(--hanania-muted);font-size:13px;">
                                Belum ada riwayat pengajuan penukaran poin dari pasien.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftarPenukaran as $pen): ?>
                            <tr style="border-bottom:1px solid #F1EBF7;">
                                <td style="padding:12px 16px;">
                                    <strong><?= date('d/m/Y, H:i', strtotime($pen['created_at'])) ?></strong>
                                </td>
                                <td style="padding:12px 16px;">
                                    <strong style="color:var(--hanania-text);"><?= adminEsc($pen['nama_lengkap']) ?></strong>
                                    <div style="font-size:11.5px;color:var(--hanania-muted);">NIK: <?= adminEsc($pen['nik'] ?: '-') ?> &bull; <?= adminEsc($pen['no_telepon'] ?: '-') ?></div>
                                </td>
                                <td style="padding:12px 16px;">
                                    <span style="font-weight:700;color:var(--hanania-primary);"><?= adminEsc($pen['nama_reward']) ?></span>
                                </td>
                                <td style="padding:12px 16px;text-align:center;">
                                    <strong style="color:#D97706;">⭐ <?= number_format($pen['poin_digunakan'], 0, ',', '.') ?></strong>
                                </td>
                                <td style="padding:12px 16px;">
                                    <span style="font-family:monospace;font-weight:800;background:#F3E8FF;color:#6C34A3;padding:3px 8px;border-radius:6px;font-size:12px;">
                                        <?= adminEsc($pen['kode_voucher']) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 16px;text-align:center;">
                                    <?php if ($pen['status'] === 'menunggu'): ?>
                                        <span class="badge" style="background:#FFF9E6;color:#B45309;border:1px solid #FDE68A;font-weight:800;font-size:11px;">
                                            ⏳ Menunggu ACC
                                        </span>
                                    <?php elseif ($pen['status'] === 'disetujui'): ?>
                                        <span class="badge badge-success" style="font-weight:800;font-size:11px;">
                                            ✅ Disetujui
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;font-weight:800;font-size:11px;">
                                            ❌ Ditolak
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px 16px;text-align:center;">
                                    <?php if ($pen['status'] === 'menunggu'): ?>
                                        <div style="display:flex;gap:6px;justify-content:center;">
                                            <form method="POST" action="pasien.php" onsubmit="return confirm('Setujui (ACC) penukaran voucher untuk <?= adminEsc(addslashes($pen['nama_lengkap'])) ?>?');">
                                                <input type="hidden" name="action" value="acc_penukaran">
                                                <input type="hidden" name="id_penukaran" value="<?= (int)$pen['id_penukaran'] ?>">
                                                <button type="submit" class="btn btn-sm" style="padding:5px 9px;font-size:11px;font-weight:800;background:#16a34a;color:#fff;" title="Setujui dan Aktifkan Voucher">
                                                    ✅ ACC
                                                </button>
                                            </form>
                                            <form method="POST" action="pasien.php" onsubmit="return confirm('Tolak penukaran ini dan kembalikan <?= $pen['poin_digunakan'] ?> poin ke saldo pasien <?= adminEsc(addslashes($pen['nama_lengkap'])) ?>?');">
                                                <input type="hidden" name="action" value="tolak_penukaran">
                                                <input type="hidden" name="id_penukaran" value="<?= (int)$pen['id_penukaran'] ?>">
                                                <button type="submit" class="btn btn-sm" style="padding:5px 9px;font-size:11px;font-weight:800;background:#DC2626;color:#fff;" title="Tolak & Kembalikan Poin">
                                                    ❌ Tolak
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:11.5px;color:var(--hanania-muted);"><?= adminEsc($pen['catatan_admin'] ?: 'Selesai diproses') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- PETUNJUK GESER TABEL -->
    <div class="table-scroll-hint">
        <span>👉 Geser / scroll tabel ke kanan untuk melihat data lengkap dan tombol aksi (Detail, Edit, Ubah Poin, Hapus).</span>
    </div>

    <!-- TABEL DATA PASIEN -->
    <section class="admin-card" style="padding:0;overflow:hidden;">
        <div class="admin-table-responsive" style="overflow-x:auto!important;-webkit-overflow-scrolling:touch;width:100%;">
            <table style="width:100%;min-width:1150px;table-layout:auto;">
                <thead>
                    <tr>
                        <th style="width:130px;">NIK</th>
                        <th>Nama Pasien</th>
                        <th style="width:100px;text-align:center;">Jenis Kelamin</th>
                        <th style="width:110px;">Tgl Lahir / Usia</th>
                        <th>Kontak</th>
                        <th>Pekerjaan / Agama</th>
                        <th style="width:100px;text-align:center;">Poin Reward</th>
                        <th style="width:85px;text-align:center;">Kunjungan</th>
                        <th class="sticky-col-right" style="width:230px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarPasien)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:var(--muted);">
                                <?= $search !== '' ? 'Tidak ditemukan data pasien yang sesuai kata kunci pencarian.' : 'Belum ada data pasien terdaftar.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftarPasien as $p): 
                            $usia = '-';
                            if (!empty($p['tanggal_lahir'])) {
                                $tgl = new DateTime($p['tanggal_lahir']);
                                $now = new DateTime();
                                $usia = $tgl->diff($now)->y . ' th';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="font-family:monospace;font-size:13px;color:var(--button);">
                                    <?= adminEsc($p['nik'] ?: '-') ?>
                                </strong>
                            </td>
                            <td>
                                <strong style="color:var(--text);font-size:14px;"><?= adminEsc($p['nama_lengkap']) ?></strong>
                                <?php if (!empty($p['ada_rujukan']) && $p['ada_rujukan'] === 'Ya'): ?>
                                    <span class="badge badge-warning" style="font-size:10px;margin-left:4px;">Ada Rujukan</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-purple">
                                    <?= ($p['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : (($p['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : '-') ?>
                                </span>
                            </td>
                            <td>
                                <span><?= !empty($p['tanggal_lahir']) ? date('d/m/Y', strtotime($p['tanggal_lahir'])) : '-' ?></span>
                                <span style="display:block;font-size:11px;color:var(--muted);"><?= $usia ?></span>
                            </td>
                            <td>
                                <div><?= !empty($p['no_telepon']) ? adminEsc($p['no_telepon']) : '-' ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?= adminEsc($p['email'] ?? '-') ?></div>
                            </td>
                            <td>
                                <div><?= adminEsc($p['pekerjaan'] ?? '-') ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?= adminEsc($p['agama'] ?? '-') ?></div>
                            </td>
                            <td style="text-align:center;">
                                <strong style="color:var(--hanania-primary);font-size:13.5px;">⭐ <?= number_format((int)($p['reward_poin'] ?? 0), 0, ',', '.') ?></strong>
                            </td>
                            <td style="text-align:center;">
                                <strong style="font-size:14px;color:var(--purple-dark);"><?= (int)$p['jumlah_kunjungan'] ?></strong> kali
                            </td>
                            <td class="sticky-col-right" style="text-align:center;">
                                <div style="display:flex;gap:4px;justify-content:center;align-items:center;flex-wrap:wrap;">
                                    <?php $pasienJson = htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" class="btn btn-sm btn-light btn-detail-pasien" 
                                            style="padding:4px 7px;font-size:11px;font-weight:700;"
                                            data-pasien='<?= $pasienJson ?>'
                                            title="Lihat Detail Pasien">
                                        👁️ Detail
                                    </button>
                                    <button type="button" class="btn btn-sm btn-edit-pasien" 
                                            style="padding:4px 7px;font-size:11px;font-weight:700;background:var(--hanania-primary);color:#fff;"
                                            data-pasien='<?= $pasienJson ?>' 
                                            title="Ubah Data Pasien">
                                        ✏️ Edit
                                    </button>
                                    <a href="pasien.php?hapus=<?= (int)$p['id_pasien'] ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus pasien <?= htmlspecialchars(addslashes($p['nama_lengkap']), ENT_QUOTES, 'UTF-8') ?>?\n\nPeringatan: Seluruh data antrean dan riwayat kunjungan pasien ini juga akan dihapus permanen.');" 
                                       class="btn btn-sm" 
                                       style="padding:4px 7px;font-size:11px;font-weight:700;background:#FDEAEA;color:#B72B2B;" 
                                       title="Hapus Pasien">
                                        🗑️
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ========================================================= -->
    <!-- MODAL DETAIL PASIEN                                       -->
    <!-- ========================================================= -->
    <div id="modalDetailPasien" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-head">
                <h3 style="font-size:16px;font-weight:800;color:var(--hanania-primary);margin:0;">
                    👤 Rekam Profil Pasien
                </h3>
                <button type="button" onclick="tutupDetailPasien()" style="border:0;background:transparent;font-size:18px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>

            <div class="modal-body">
                <div id="detailIsi" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <!-- Diisi via JS -->
                </div>
            </div>

            <div style="padding:14px 24px;border-top:1px solid var(--hanania-border);background:#FAF8FD;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="btnEditFromDetail" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;">✏️ Ubah Pasien Ini</button>
                <button type="button" class="btn btn-sm btn-light" onclick="tutupDetailPasien()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL TAMBAH PASIEN BARU                                  -->
    <!-- ========================================================= -->
    <div id="modalTambahPasien" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-head">
                <h3 style="font-size:16px;font-weight:800;color:var(--hanania-primary);margin:0;">
                    ➕ Tambah Pasien Baru
                </h3>
                <button type="button" onclick="tutupModalTambahPasien()" style="border:0;background:transparent;font-size:18px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>

            <form method="POST" action="pasien.php" class="modal-body">
                <input type="hidden" name="action" value="tambah_pasien">

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="nik" maxlength="16" placeholder="16 digit NIK sesuai KTP" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Lengkap Pasien *</label>
                        <input type="text" name="nama_lengkap" required placeholder="Nama lengkap" class="filter-input">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="filter-input">
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Nomor HP / WhatsApp</label>
                        <input type="text" name="no_telepon" placeholder="Contoh: 08123456789" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Alamat Email</label>
                        <input type="email" name="email" placeholder="email@contoh.com" class="filter-input">
                    </div>
                </div>

                <div class="form-group-field">
                    <label>Alamat Domisili Lengkap</label>
                    <textarea name="alamat" rows="2" placeholder="Nama jalan, RT/RW, kelurahan, kecamatan" class="filter-input"></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Pekerjaan</label>
                        <input type="text" name="pekerjaan" placeholder="Contoh: Pegawai Swasta, Mahasiswa" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Status Perkawinan</label>
                        <select name="status_perkawinan" class="filter-input">
                            <option value="">-- Pilih Status --</option>
                            <option value="Belum Kawin">Belum Kawin</option>
                            <option value="Kawin">Kawin</option>
                            <option value="Cerai Hidup">Cerai Hidup</option>
                            <option value="Cerai Mati">Cerai Mati</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Pendidikan Terakhir</label>
                        <select name="pendidikan_terakhir" class="filter-input">
                            <option value="">-- Pilih Pendidikan --</option>
                            <option value="SD">SD / Sederajat</option>
                            <option value="SMP">SMP / Sederajat</option>
                            <option value="SMA/SMK">SMA / SMK / Sederajat</option>
                            <option value="Diploma">Diploma (D1-D4)</option>
                            <option value="Sarjana (S1)">Sarjana (S1)</option>
                            <option value="Magister (S2)">Magister (S2)</option>
                            <option value="Doktor (S3)">Doktor (S3)</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group-field">
                        <label>Agama</label>
                        <select name="agama" class="filter-input">
                            <option value="">-- Pilih Agama --</option>
                            <option value="Islam">Islam</option>
                            <option value="Kristen Protestan">Kristen Protestan</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Buddha">Buddha</option>
                            <option value="Khonghucu">Khonghucu</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group-field">
                        <label>Nama Ibu Kandung</label>
                        <input type="text" name="nama_ibu_kandung" placeholder="Nama ibu kandung pasien" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Ayah Kandung</label>
                        <input type="text" name="nama_ayah_kandung" placeholder="Khusus pasien anak/belum nikah" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Suami / Pasangan</label>
                        <input type="text" name="nama_suami" placeholder="Khusus pasien menikah" class="filter-input">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Status Rujukan</label>
                        <select name="ada_rujukan" class="filter-input" onchange="toggleRujukanField(this.value, 'tambahAsalRujukanContainer')">
                            <option value="Tidak">Tidak Ada Rujukan</option>
                            <option value="Ya">Ada Rujukan</option>
                        </select>
                    </div>
                    <div class="form-group-field" id="tambahAsalRujukanContainer" style="display:none;">
                        <label>Asal Instansi Rujukan</label>
                        <input type="text" name="asal_rujukan" placeholder="Contoh: Puskesmas / RS / Klinik Luar" class="filter-input">
                    </div>
                </div>

                <div style="padding-top:10px;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="tutupModalTambahPasien()" class="btn btn-sm btn-light">Batal</button>
                    <button type="submit" class="btn btn-sm">💾 Simpan Data Pasien</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL EDIT PASIEN                                         -->
    <!-- ========================================================= -->
    <div id="modalEditPasien" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-head">
                <h3 style="font-size:16px;font-weight:800;color:var(--hanania-primary);margin:0;">
                    ✏️ Perbarui Data Pasien
                </h3>
                <button type="button" onclick="tutupModalEditPasien()" style="border:0;background:transparent;font-size:18px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>

            <form method="POST" action="pasien.php" class="modal-body">
                <input type="hidden" name="action" value="edit_pasien">
                <input type="hidden" name="id_pasien" id="editPasienId">

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="nik" id="editPasienNik" maxlength="16" placeholder="16 digit NIK" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Lengkap Pasien *</label>
                        <input type="text" name="nama_lengkap" id="editPasienNama" required placeholder="Nama lengkap" class="filter-input">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" id="editPasienTglLahir" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="editPasienJk" class="filter-input">
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Nomor HP / WhatsApp</label>
                        <input type="text" name="no_telepon" id="editPasienTelp" placeholder="Contoh: 08123456789" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Alamat Email</label>
                        <input type="email" name="email" id="editPasienEmail" placeholder="email@contoh.com" class="filter-input">
                    </div>
                </div>

                <div class="form-group-field">
                    <label>Alamat Domisili Lengkap</label>
                    <textarea name="alamat" id="editPasienAlamat" rows="2" placeholder="Nama jalan, RT/RW, kelurahan, kecamatan" class="filter-input"></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Pekerjaan</label>
                        <input type="text" name="pekerjaan" id="editPasienPekerjaan" placeholder="Pekerjaan" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Status Perkawinan</label>
                        <select name="status_perkawinan" id="editPasienStatusKawin" class="filter-input">
                            <option value="">-- Pilih Status --</option>
                            <option value="Belum Kawin">Belum Kawin</option>
                            <option value="Kawin">Kawin</option>
                            <option value="Cerai Hidup">Cerai Hidup</option>
                            <option value="Cerai Mati">Cerai Mati</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Pendidikan Terakhir</label>
                        <select name="pendidikan_terakhir" id="editPasienPendidikan" class="filter-input">
                            <option value="">-- Pilih Pendidikan --</option>
                            <option value="SD">SD / Sederajat</option>
                            <option value="SMP">SMP / Sederajat</option>
                            <option value="SMA/SMK">SMA / SMK / Sederajat</option>
                            <option value="Diploma">Diploma (D1-D4)</option>
                            <option value="Sarjana (S1)">Sarjana (S1)</option>
                            <option value="Magister (S2)">Magister (S2)</option>
                            <option value="Doktor (S3)">Doktor (S3)</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group-field">
                        <label>Agama</label>
                        <select name="agama" id="editPasienAgama" class="filter-input">
                            <option value="">-- Pilih Agama --</option>
                            <option value="Islam">Islam</option>
                            <option value="Kristen Protestan">Kristen Protestan</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Buddha">Buddha</option>
                            <option value="Khonghucu">Khonghucu</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group-field">
                        <label>Nama Ibu Kandung</label>
                        <input type="text" name="nama_ibu_kandung" id="editPasienNamaIbu" placeholder="Nama ibu kandung" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Ayah Kandung</label>
                        <input type="text" name="nama_ayah_kandung" id="editPasienNamaAyah" placeholder="Nama ayah kandung" class="filter-input">
                    </div>
                    <div class="form-group-field">
                        <label>Nama Suami / Pasangan</label>
                        <input type="text" name="nama_suami" id="editPasienNamaSuami" placeholder="Nama suami (jika ada)" class="filter-input">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group-field">
                        <label>Status Rujukan</label>
                        <select name="ada_rujukan" id="editPasienAdaRujukan" class="filter-input" onchange="toggleRujukanField(this.value, 'editAsalRujukanContainer')">
                            <option value="Tidak">Tidak Ada Rujukan</option>
                            <option value="Ya">Ada Rujukan</option>
                        </select>
                    </div>
                    <div class="form-group-field" id="editAsalRujukanContainer" style="display:none;">
                        <label>Asal Instansi Rujukan</label>
                        <input type="text" name="asal_rujukan" id="editPasienAsalRujukan" placeholder="Contoh: Puskesmas / RS Luar" class="filter-input">
                    </div>
                </div>

                <div style="padding-top:10px;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="tutupModalEditPasien()" class="btn btn-sm btn-light">Batal</button>
                    <button type="submit" class="btn btn-sm">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

</main>

<script>
let currentSelectedPasien = null;

function toggleRujukanField(status, containerId) {
    const el = document.getElementById(containerId);
    if (el) {
        el.style.display = (status === 'Ya') ? 'block' : 'none';
    }
}

// MODAL DETAIL
function lihatDetailPasien(p) {
    currentSelectedPasien = p;
    const modal = document.getElementById('modalDetailPasien');
    const container = document.getElementById('detailIsi');

    const fields = [
        { label: 'Nama Lengkap', val: p.nama_lengkap },
        { label: 'NIK', val: p.nik },
        { label: 'Tanggal Lahir', val: p.tanggal_lahir },
        { label: 'Jenis Kelamin', val: p.jenis_kelamin === 'L' ? 'Laki-laki' : (p.jenis_kelamin === 'P' ? 'Perempuan' : '-') },
        { label: 'Nomor Telepon', val: p.no_telepon },
        { label: 'Email', val: p.email },
        { label: 'Alamat Domisili', val: p.alamat, full: true },
        { label: 'Pekerjaan', val: p.pekerjaan },
        { label: 'Status Perkawinan', val: p.status_perkawinan },
        { label: 'Pendidikan Terakhir', val: p.pendidikan_terakhir },
        { label: 'Agama', val: p.agama },
        { label: 'Nama Ibu Kandung', val: p.nama_ibu_kandung },
        { 
            label: (p.status_perkawinan === 'Belum Menikah' || !p.status_perkawinan) ? 'Nama Ayah Kandung' : 'Nama Suami / Pasangan', 
            val: (p.status_perkawinan === 'Belum Menikah' || !p.status_perkawinan) ? (p.nama_ayah_kandung || p.nama_suami) : (p.nama_suami || p.nama_ayah_kandung)
        },
        { label: 'Status Rujukan', val: (p.ada_rujukan === 'Ya' ? 'Ada Rujukan' : 'Tidak Ada') + (p.asal_rujukan ? ' · ' + p.asal_rujukan : '') },
        { label: 'Total Pendaftaran Kunjungan', val: (p.jumlah_kunjungan || 0) + ' kali' },
        { label: 'Kunjungan Terakhir', val: p.kunjungan_terakhir || 'Belum pernah' }
    ];

    let html = '';
    fields.forEach(f => {
        const fullStyle = f.full ? 'grid-column: 1 / -1;' : '';
        html += `
            <div style="background:#FBF9FD;border:1px solid #E8DFF0;border-radius:10px;padding:10px 14px;${fullStyle}">
                <span style="font-size:10px;font-weight:800;color:#8E7E9E;text-transform:uppercase;display:block;margin-bottom:4px;">${f.label}</span>
                <strong style="font-size:13px;color:#352D3E;">${f.val || '-'}</strong>
            </div>
        `;
    });

    container.innerHTML = html;

    const btnEdit = document.getElementById('btnEditFromDetail');
    if (btnEdit) {
        btnEdit.onclick = function() {
            tutupDetailPasien();
            bukaModalEditPasien(p);
        };
    }

    modal.style.display = 'flex';
}

function tutupDetailPasien() {
    document.getElementById('modalDetailPasien').style.display = 'none';
}

// MODAL TAMBAH
function bukaModalTambahPasien() {
    document.getElementById('modalTambahPasien').style.display = 'flex';
}
function tutupModalTambahPasien() {
    document.getElementById('modalTambahPasien').style.display = 'none';
}

// MODAL EDIT
function bukaModalEditPasien(p) {
    document.getElementById('editPasienId').value = p.id_pasien || '';
    document.getElementById('editPasienNik').value = p.nik || '';
    document.getElementById('editPasienNama').value = p.nama_lengkap || '';
    document.getElementById('editPasienTglLahir').value = p.tanggal_lahir || '';
    document.getElementById('editPasienJk').value = p.jenis_kelamin || '';
    document.getElementById('editPasienTelp').value = p.no_telepon || '';
    document.getElementById('editPasienEmail').value = p.email || '';
    document.getElementById('editPasienAlamat').value = p.alamat || '';
    document.getElementById('editPasienPekerjaan').value = p.pekerjaan || '';
    document.getElementById('editPasienStatusKawin').value = p.status_perkawinan || '';
    document.getElementById('editPasienPendidikan').value = p.pendidikan_terakhir || '';
    document.getElementById('editPasienAgama').value = p.agama || '';
    document.getElementById('editPasienNamaIbu').value = p.nama_ibu_kandung || '';
    if (document.getElementById('editPasienNamaAyah')) document.getElementById('editPasienNamaAyah').value = p.nama_ayah_kandung || '';
    document.getElementById('editPasienNamaSuami').value = p.nama_suami || '';
    
    const rujukan = p.ada_rujukan || 'Tidak';
    document.getElementById('editPasienAdaRujukan').value = rujukan;
    document.getElementById('editPasienAsalRujukan').value = p.asal_rujukan || '';
    toggleRujukanField(rujukan, 'editAsalRujukanContainer');

    document.getElementById('modalEditPasien').style.display = 'flex';
}

function tutupModalEditPasien() {
    document.getElementById('modalEditPasien').style.display = 'none';
}

// Close modal when clicking on overlay outside dialog
window.addEventListener('click', function(e) {
    const overlays = ['modalDetailPasien', 'modalTambahPasien', 'modalEditPasien'];
    overlays.forEach(id => {
        const modal = document.getElementById(id);
        if (modal && e.target === modal) {
            modal.style.display = 'none';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-detail-pasien').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                const p = JSON.parse(this.getAttribute('data-pasien'));
                lihatDetailPasien(p);
            } catch (err) {
                console.error('Gagal parsing data pasien detail:', err);
            }
        });
    });

    document.querySelectorAll('.btn-edit-pasien').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                const p = JSON.parse(this.getAttribute('data-pasien'));
                bukaModalEditPasien(p);
            } catch (err) {
                console.error('Gagal parsing data pasien edit:', err);
            }
        });
    });
});
</script>
<?php adminPageEnd(); ?>
