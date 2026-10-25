<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';
$tab = trim($_GET['tab'] ?? 'layanan');
if (!in_array($tab, ['layanan', 'fields', 'poli'], true)) {
    $tab = 'layanan';
}

// Ambil daftar poli untuk pilihan poli terkait
$allPoliList = [];
$poliMap = [];
$qPoliDb = $db->query("SELECT id_poli, nama_poli FROM poli ORDER BY id_poli ASC");
if ($qPoliDb) {
    while ($rp = $qPoliDb->fetch_assoc()) {
        $allPoliList[] = $rp;
        $poliMap[(string)$rp['id_poli']] = $rp['nama_poli'];
    }
}

// -------------------------------------------------------------
// TAB 1: LOGIKA LAYANAN / TINDAKAN PENDAFTARAN
// -------------------------------------------------------------

// Handle HAPUS TINDAKAN
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtDel = $db->prepare("DELETE FROM tindakan_pendaftaran WHERE id_tindakan = ?");
    if ($stmtDel) {
        $stmtDel->bind_param('i', $idHapus);
        if ($stmtDel->execute()) {
            $message = 'Layanan / tindakan pendaftaran berhasil dihapus.';
        } else {
            $message = 'Gagal menghapus tindakan: ' . $db->error;
            $messageType = 'danger';
        }
        $stmtDel->close();
    }
}

// Handle TOGGLE STATUS TINDAKAN
if (isset($_GET['toggle_status'])) {
    $idToggle = (int)$_GET['toggle_status'];
    $stmtT = $db->prepare("UPDATE tindakan_pendaftaran SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_tindakan = ?");
    if ($stmtT) {
        $stmtT->bind_param('i', $idToggle);
        $stmtT->execute();
        $stmtT->close();
        $message = 'Status tindakan pendaftaran berhasil diperbarui.';
    }
}

// Handle SIMPAN TINDAKAN (Tambah / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_target'] ?? '') === 'tindakan')) {
    $action = $_POST['action'] ?? '';
    $idTindakan = (int)($_POST['id_tindakan'] ?? 0);
    $namaTindakan = trim($_POST['nama_tindakan'] ?? '');
    $icon = trim($_POST['icon'] ?? '🩺');
    if ($icon === '') $icon = '🩺';

    // Tangani pilihan poli terkait (bisa multi checkbox atau 'all')
    $rawPoliTerkait = $_POST['poli_terkait'] ?? [];
    if (!is_array($rawPoliTerkait)) {
        $rawPoliTerkait = [$rawPoliTerkait];
    }
    
    // Jika dicentang 'all' atau tidak ada yang dipilih, default ke 'all'
    if (in_array('all', $rawPoliTerkait, true) || empty($rawPoliTerkait)) {
        $idPoliTerkait = 'all';
        $kategoriPoli = 'Semua Poli';
    } else {
        $cleanPolis = array_unique(array_filter(array_map('intval', $rawPoliTerkait)));
        sort($cleanPolis);
        if (empty($cleanPolis)) {
            $idPoliTerkait = 'all';
            $kategoriPoli = 'Semua Poli';
        } else {
            $idPoliTerkait = implode(',', $cleanPolis);
            $poliNames = [];
            foreach ($cleanPolis as $pid) {
                if (isset($poliMap[(string)$pid])) {
                    $poliNames[] = $poliMap[(string)$pid];
                }
            }
            $kategoriPoli = !empty($poliNames) ? implode(', ', $poliNames) : 'Poli Terpilih';
        }
    }

    $keterangan = trim($_POST['keterangan'] ?? '');
    $urutan = (int)($_POST['urutan'] ?? 1);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

    if ($namaTindakan === '') {
        $message = 'Nama tindakan / layanan wajib diisi.';
        $messageType = 'danger';
    } else {
        if ($action === 'edit' && $idTindakan > 0) {
            $stmtUp = $db->prepare("
                UPDATE tindakan_pendaftaran 
                SET nama_tindakan = ?, icon = ?, kategori_poli = ?, id_poli_terkait = ?, keterangan = ?, urutan = ?, status = ?
                WHERE id_tindakan = ?
            ");
            if ($stmtUp) {
                $stmtUp->bind_param('sssssisi', $namaTindakan, $icon, $kategoriPoli, $idPoliTerkait, $keterangan, $urutan, $status, $idTindakan);
                if ($stmtUp->execute()) {
                    $message = 'Perubahan layanan / tindakan pendaftaran berhasil disimpan.';
                } else {
                    $message = 'Gagal menyimpan perubahan: ' . $db->error;
                    $messageType = 'danger';
                }
                $stmtUp->close();
            }
        } else {
            $stmtIn = $db->prepare("
                INSERT INTO tindakan_pendaftaran (nama_tindakan, icon, kategori_poli, id_poli_terkait, keterangan, urutan, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmtIn) {
                $stmtIn->bind_param('sssssis', $namaTindakan, $icon, $kategoriPoli, $idPoliTerkait, $keterangan, $urutan, $status);
                if ($stmtIn->execute()) {
                    $message = 'Layanan / tindakan pendaftaran baru berhasil ditambahkan!';
                } else {
                    $message = 'Gagal menambahkan tindakan: ' . $db->error;
                    $messageType = 'danger';
                }
                $stmtIn->close();
            }
        }
    }
}

// -------------------------------------------------------------
// TAB 2: LOGIKA PENGATURAN DATA YANG DIMINTA DARI PASIEN
// -------------------------------------------------------------

// Toggle Aktif/Nonaktif Field
if (isset($_GET['toggle_field_status'])) {
    $idField = (int)$_GET['toggle_field_status'];
    $stmtF = $db->prepare("UPDATE pengaturan_form_pendaftaran SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_field = ?");
    if ($stmtF) {
        $stmtF->bind_param('i', $idField);
        $stmtF->execute();
        $stmtF->close();
        $message = 'Visibilitas data yang diminta pada formulir berhasil diperbarui.';
        $tab = 'fields';
    }
}

// Toggle Wajib/Opsional Field
if (isset($_GET['toggle_field_wajib'])) {
    $idField = (int)$_GET['toggle_field_wajib'];
    $stmtW = $db->prepare("UPDATE pengaturan_form_pendaftaran SET wajib = IF(wajib = 1, 0, 1) WHERE id_field = ?");
    if ($stmtW) {
        $stmtW->bind_param('i', $idField);
        $stmtW->execute();
        $stmtW->close();
        $message = 'Ketentuan sifat data (Wajib / Opsional) berhasil diperbarui.';
        $tab = 'fields';
    }
}

// Handle Hapus Field Kustom
if (isset($_GET['hapus_field'])) {
    $idHapusField = (int)$_GET['hapus_field'];
    $tab = 'fields';
    $stmtCek = $db->prepare("SELECT id_field, nama_field, label_field, is_custom FROM pengaturan_form_pendaftaran WHERE id_field = ? LIMIT 1");
    if ($stmtCek) {
        $stmtCek->bind_param('i', $idHapusField);
        $stmtCek->execute();
        $fRow = $stmtCek->get_result()->fetch_assoc();
        $stmtCek->close();

        if ($fRow && (int)($fRow['is_custom'] ?? 0) === 1) {
            $stmtDelF = $db->prepare("DELETE FROM pengaturan_form_pendaftaran WHERE id_field = ?");
            if ($stmtDelF) {
                $stmtDelF->bind_param('i', $idHapusField);
                $stmtDelF->execute();
                $stmtDelF->close();
                $message = "Kolom data formulir '{$fRow['label_field']}' berhasil dihapus.";
            }
        } else {
            $message = 'Field bawaan sistem tidak dapat dihapus, namun visibilitasnya dapat Anda nonaktifkan.';
            $messageType = 'danger';
        }
    }
}

// Handle Tambah Field Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_target'] ?? '') === 'tambah_field')) {
    $labelField = trim($_POST['label_field'] ?? '');
    $namaFieldInput = trim($_POST['nama_field'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tahap = in_array((int)($_POST['tahap'] ?? 1), [1, 2], true) ? (int)$_POST['tahap'] : 1;
    $wajib = (int)($_POST['wajib'] ?? 0);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
    $tab = 'fields';

    if ($labelField === '') {
        $message = 'Label data formulir wajib diisi.';
        $messageType = 'danger';
    } else {
        // Buat slug nama_field jika tidak diisi
        if ($namaFieldInput === '') {
            $slug = strtolower($labelField);
            $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
            $slug = trim($slug, '_');
        } else {
            $slug = strtolower($namaFieldInput);
            $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug);
            $slug = trim($slug, '_');
        }

        if (strlen($slug) < 2) {
            $slug = 'custom_' . time();
        }

        // Cek apakah nama_field sudah ada
        $stmtCekExist = $db->prepare("SELECT id_field FROM pengaturan_form_pendaftaran WHERE nama_field = ? LIMIT 1");
        $stmtCekExist->bind_param('s', $slug);
        $stmtCekExist->execute();
        $isExist = $stmtCekExist->get_result()->fetch_assoc();
        $stmtCekExist->close();

        if ($isExist) {
            $message = "Kode data '$slug' sudah digunakan. Silakan gunakan label atau kode lain.";
            $messageType = 'danger';
        } else {
            // Ambil urutan terakhir
            $qMax = $db->query("SELECT MAX(urutan) AS max_u FROM pengaturan_form_pendaftaran WHERE tahap = $tahap");
            $maxU = $qMax ? (int)($qMax->fetch_assoc()['max_u'] ?? 10) : 10;
            $newUrutan = $maxU + 1;

            $stmtInF = $db->prepare("
                INSERT INTO pengaturan_form_pendaftaran (nama_field, label_field, tahap, status, wajib, urutan, is_custom, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)
            ");
            if ($stmtInF) {
                $stmtInF->bind_param('ssisiis', $slug, $labelField, $tahap, $status, $wajib, $newUrutan, $keterangan);
                if ($stmtInF->execute()) {
                    $stmtInF->close();

                    // Tambahkan kolom di tabel pasien dan keluarga_pasien jika belum ada
                    $cleanCol = preg_replace('/[^a-z0-9_]/', '', $slug);
                    $chkP = $db->query("SHOW COLUMNS FROM pasien LIKE '$cleanCol'");
                    if ($chkP && $chkP->num_rows === 0) {
                        $db->query("ALTER TABLE pasien ADD COLUMN `$cleanCol` VARCHAR(255) NULL");
                    }
                    $chkK = $db->query("SHOW COLUMNS FROM keluarga_pasien LIKE '$cleanCol'");
                    if ($chkK && $chkK->num_rows === 0) {
                        $db->query("ALTER TABLE keluarga_pasien ADD COLUMN `$cleanCol` VARCHAR(255) NULL");
                    }

                    $message = "Data baru '$labelField' berhasil ditambahkan ke formulir pendaftaran pasien!";
                } else {
                    $message = 'Gagal menambahkan field: ' . $db->error;
                    $messageType = 'danger';
                    $stmtInF->close();
                }
            }
        }
    }
}

// Handle Simpan Edit Field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_target'] ?? '') === 'field')) {
    $idField = (int)($_POST['id_field'] ?? 0);
    $labelField = trim($_POST['label_field'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $wajib = (int)($_POST['wajib'] ?? 0);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
    $tab = 'fields';

    if ($labelField === '' || $idField <= 0) {
        $message = 'Label data wajib diisi.';
        $messageType = 'danger';
    } else {
        $stmtUpF = $db->prepare("
            UPDATE pengaturan_form_pendaftaran
            SET label_field = ?, keterangan = ?, wajib = ?, status = ?
            WHERE id_field = ?
        ");
        if ($stmtUpF) {
            $stmtUpF->bind_param('ssisi', $labelField, $keterangan, $wajib, $status, $idField);
            if ($stmtUpF->execute()) {
                $message = 'Pengaturan data formulir pasien berhasil disimpan.';
            } else {
                $message = 'Gagal menyimpan: ' . $db->error;
                $messageType = 'danger';
            }
            $stmtUpF->close();
        }
    }
}

// -------------------------------------------------------------
// TAB 3: LOGIKA OPERASIONAL & STATUS POLI (24 JAM & TUTUP MANUAL)
// -------------------------------------------------------------

// Toggle Mode Operasional Cepat (tersedia / ditutup / full_booking / otomatis)
if (isset($_GET['toggle_poli_mode'])) {
    $idPoliToggle = (int)$_GET['toggle_poli_mode'];
    $allowedModes = ['tersedia', 'ditutup', 'full_booking', 'otomatis', 'buka_manual', 'tutup_manual'];
    $modeBaru = in_array($_GET['mode'] ?? '', $allowedModes, true) ? $_GET['mode'] : 'otomatis';
    $pesanTutup = trim($_GET['pesan'] ?? '');

    $stmtPMode = $db->prepare("UPDATE poli SET status_operasional = ?, pesan_tutup = IF(? != '', ?, pesan_tutup) WHERE id_poli = ?");
    if ($stmtPMode) {
        $stmtPMode->bind_param('sssi', $modeBaru, $pesanTutup, $pesanTutup, $idPoliToggle);
        $stmtPMode->execute();
        $stmtPMode->close();
        
        $modeLabels = [
            'tersedia' => 'Pendaftaran Tersedia',
            'buka_manual' => 'Pendaftaran Tersedia',
            'ditutup' => 'Pendaftaran Ditutup',
            'tutup_manual' => 'Pendaftaran Ditutup',
            'full_booking' => 'Full Booking',
            'otomatis' => 'Otomatis'
        ];
        $labelBaru = $modeLabels[$modeBaru] ?? $modeBaru;
        $message = "Status pendaftaran poli berhasil diubah menjadi: " . $labelBaru;
        $tab = 'poli';
    }
}

// Toggle Status 24 Jam
if (isset($_GET['toggle_poli_24jam'])) {
    $idPoli24 = (int)$_GET['toggle_poli_24jam'];
    $stmtP24 = $db->prepare("UPDATE poli SET is_24jam = IF(is_24jam = 1, 0, 1) WHERE id_poli = ?");
    if ($stmtP24) {
        $stmtP24->bind_param('i', $idPoli24);
        $stmtP24->execute();
        $stmtP24->close();
        $message = 'Status pelayanan 24 Jam poli berhasil diperbarui.';
        $tab = 'poli';
    }
}

// Toggle Status Aktif/Nonaktif Poli
if (isset($_GET['toggle_poli_aktif'])) {
    $idPoliAktif = (int)$_GET['toggle_poli_aktif'];
    $stmtPA = $db->prepare("UPDATE poli SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_poli = ?");
    if ($stmtPA) {
        $stmtPA->bind_param('i', $idPoliAktif);
        $stmtPA->execute();
        $stmtPA->close();
        $message = 'Status keaktifan poli berhasil diperbarui.';
        $tab = 'poli';
    }
}

// Simpan Form Pengaturan Poli (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['form_target'] ?? '') === 'poli')) {
    $idPoli = (int)($_POST['id_poli'] ?? 0);
    $namaPoli = trim($_POST['nama_poli'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $allowedModes = ['tersedia', 'ditutup', 'full_booking', 'otomatis', 'buka_manual', 'tutup_manual'];
    $statusOperasional = in_array($_POST['status_operasional'] ?? '', $allowedModes, true) ? $_POST['status_operasional'] : 'otomatis';
    $is24Jam = isset($_POST['is_24jam']) ? 1 : 0;
    $waktuCutoff = max(0, (int)($_POST['waktu_cutoff_menit'] ?? 60));
    $pesanTutup = trim($_POST['pesan_tutup'] ?? '');
    $statusPoli = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
    $tab = 'poli';

    if ($idPoli <= 0 || $namaPoli === '') {
        $message = 'Data poli tidak valid atau nama poli kosong.';
        $messageType = 'danger';
    } else {
        $stmtUpP = $db->prepare("
            UPDATE poli 
            SET nama_poli = ?, deskripsi = ?, status_operasional = ?, is_24jam = ?, waktu_cutoff_menit = ?, pesan_tutup = ?, status = ?
            WHERE id_poli = ?
        ");
        if ($stmtUpP) {
            $stmtUpP->bind_param('sssiissi', $namaPoli, $deskripsi, $statusOperasional, $is24Jam, $waktuCutoff, $pesanTutup, $statusPoli, $idPoli);
            if ($stmtUpP->execute()) {
                $message = "Pengaturan operasional untuk '{$namaPoli}' berhasil diperbarui.";
            } else {
                $message = 'Gagal menyimpan pengaturan poli: ' . $db->error;
                $messageType = 'danger';
            }
            $stmtUpP->close();
        }
    }
}

// Data edit tindakan jika ada
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $resE = $db->query("SELECT * FROM tindakan_pendaftaran WHERE id_tindakan = $idEdit LIMIT 1");
    if ($resE && $resE->num_rows > 0) {
        $editData = $resE->fetch_assoc();
        $tab = 'layanan';
    }
}

// Data edit field jika ada
$editFieldData = null;
if (isset($_GET['edit_field'])) {
    $idEditF = (int)$_GET['edit_field'];
    $resEF = $db->query("SELECT * FROM pengaturan_form_pendaftaran WHERE id_field = $idEditF LIMIT 1");
    if ($resEF && $resEF->num_rows > 0) {
        $editFieldData = $resEF->fetch_assoc();
        $tab = 'fields';
    }
}

// Data edit poli jika ada
$editPoliData = null;
if (isset($_GET['edit_poli'])) {
    $idEditP = (int)$_GET['edit_poli'];
    $resEP = $db->query("SELECT * FROM poli WHERE id_poli = $idEditP LIMIT 1");
    if ($resEP && $resEP->num_rows > 0) {
        $editPoliData = $resEP->fetch_assoc();
        $tab = 'poli';
    }
}

// Ambil daftar tindakan
$dataTindakan = [];
$resAll = $db->query("SELECT * FROM tindakan_pendaftaran ORDER BY urutan ASC, id_tindakan ASC");
if ($resAll) {
    while ($r = $resAll->fetch_assoc()) {
        $dataTindakan[] = $r;
    }
}

// Ambil daftar pengaturan field formulir
$dataFields = [];
$resFields = $db->query("SELECT * FROM pengaturan_form_pendaftaran ORDER BY tahap ASC, urutan ASC, id_field ASC");
if ($resFields) {
    while ($r = $resFields->fetch_assoc()) {
        $dataFields[] = $r;
    }
}

// Ambil daftar poli lengkap
$dataPoliLengkap = [];
$resPolis = $db->query("SELECT * FROM poli ORDER BY id_poli ASC");
if ($resPolis) {
    while ($rp = $resPolis->fetch_assoc()) {
        $dataPoliLengkap[] = $rp;
    }
}

adminPageStart('Pengaturan Pendaftaran Pasien', 'layanan_pendaftaran');
?>
<main class="admin-page">
    <header class="admin-header">
        <div>
            <h1><span>📋</span> Kelola Formulir &amp; Layanan Pendaftaran Pasien</h1>
            <p>Atur pilihan layanan tindakan medis serta kustomisasi data yang diminta dari pasien pada saat pendaftaran online tanpa perlu koding.</p>
        </div>
        <div class="admin-header-actions">
            <a href="pendaftaran.php" class="btn btn-sm btn-light">
                <span>←</span> Data Pendaftaran
            </a>
            <a href="layanan.php" class="btn btn-sm btn-light">
                <span>💳</span> Kelola Tarif &amp; Layanan
            </a>
            <a href="../pasien/daftar.php" target="_blank" rel="noopener" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);">
                <span>👁️</span> Lihat Form Pasien &rarr;
            </a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>">
            <span><?= $messageType === 'danger' ? '⚠️' : '✅' ?></span>
            <span><?= adminEsc($message) ?></span>
        </div>
    <?php endif; ?>

    <!-- TAB SWITCHER SEGMEN HARMONIS -->
    <div style="display:flex;gap:8px;margin-bottom:24px;background:#F3EEFA;padding:6px;border-radius:14px;max-width:max-content;border:1px solid var(--hanania-border);">
        <a href="layanan_pendaftaran.php?tab=layanan" style="padding:10px 20px;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;<?= $tab === 'layanan' ? 'background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);' : 'color:#5A4B70;background:transparent;' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>
            <span>Pilihan Layanan / Tindakan</span>
            <span style="font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:999px;<?= $tab === 'layanan' ? 'background:rgba(255,255,255,.22);color:#fff;' : 'background:#E8DFF2;color:var(--hanania-primary);' ?>"><?= count($dataTindakan) ?></span>
        </a>
        <a href="layanan_pendaftaran.php?tab=fields" style="padding:10px 20px;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;<?= $tab === 'fields' ? 'background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);' : 'color:#5A4B70;background:transparent;' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
            <span>Data yang Diminta dari Pasien</span>
            <span style="font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:999px;<?= $tab === 'fields' ? 'background:rgba(255,255,255,.22);color:#fff;' : 'background:#E8DFF2;color:var(--hanania-primary);' ?>"><?= count($dataFields) ?></span>
        </a>
        <a href="layanan_pendaftaran.php?tab=poli" style="padding:10px 20px;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;<?= $tab === 'poli' ? 'background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);' : 'color:#5A4B70;background:transparent;' ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/></svg>
            <span>Status Operasional Poli &amp; 24 Jam</span>
            <span style="font-size:11.5px;font-weight:800;padding:2px 8px;border-radius:999px;<?= $tab === 'poli' ? 'background:rgba(255,255,255,.22);color:#fff;' : 'background:#E8DFF2;color:var(--hanania-primary);' ?>"><?= count($dataPoliLengkap) ?></span>
        </a>
    </div>

    <?php if ($tab === 'layanan'): ?>
        <!-- ==========================================
             TAB 1: PILIHAN LAYANAN / TINDAKAN
             ========================================== -->
        <!-- FORM TAMBAH / EDIT TINDAKAN -->
        <section class="admin-card" style="margin-bottom:24px;" id="formBox">
            <div class="admin-card-head">
                <div>
                    <h3 class="admin-card-title">
                        <span><?= $editData ? '✏️' : '➕' ?></span> <?= $editData ? 'Edit Tindakan / Layanan Pendaftaran' : 'Tambah Tindakan / Layanan Baru' ?>
                    </h3>
                    <p class="admin-card-subtitle">
                        <?= $editData ? 'Perbarui informasi layanan yang telah dipilih.' : 'Layanan baru akan langsung dapat dipilih pasien saat mendaftar online.' ?>
                    </p>
                </div>
                <?php if ($editData): ?>
                    <a href="layanan_pendaftaran.php?tab=layanan" class="btn btn-sm btn-light">+ Tambah Baru</a>
                <?php endif; ?>
            </div>

            <form action="layanan_pendaftaran.php?tab=layanan" method="POST">
                <input type="hidden" name="form_target" value="tindakan">
                <input type="hidden" name="action" value="<?= $editData ? 'edit' : 'tambah' ?>">
                <?php if ($editData): ?>
                    <input type="hidden" name="id_tindakan" value="<?= (int)$editData['id_tindakan'] ?>">
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:1fr 110px;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Nama Layanan / Tindakan *</label>
                        <input type="text" name="nama_tindakan" value="<?= adminEsc($editData['nama_tindakan'] ?? '') ?>" required class="filter-input" style="width:100%;" placeholder="Contoh: Pelayanan Kebidanan &amp; ANC, USG 2D, Cek Darah Lengkap">
                    </div>

                    <div>
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;text-align:center;">Emoji / Ikon</label>
                        <input type="text" name="icon" value="<?= adminEsc($editData['icon'] ?? '🩺') ?>" class="filter-input" style="width:100%;text-align:center;font-size:16px;" placeholder="🩺">
                    </div>
                </div>

                <div style="margin-bottom:18px;">
                    <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:8px;color:#2D253B;">
                        Pilihan Poli Terkait (Muncul di Poli Mana Saja?) *
                    </label>
                    <?php 
                    $curPoliTerkait = $editData['id_poli_terkait'] ?? 'all';
                    $curPoliArray = ($curPoliTerkait === 'all' || $curPoliTerkait === '') ? ['all'] : explode(',', $curPoliTerkait);
                    $isAllSelected = in_array('all', $curPoliArray, true);
                    ?>
                    <div style="background:#FAF8FD;border:1.5px solid var(--hanania-border);border-radius:12px;padding:14px 18px;display:flex;flex-wrap:wrap;gap:12px 22px;align-items:center;">
                        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;color:var(--hanania-primary);font-size:13px;">
                            <input type="checkbox" name="poli_terkait[]" value="all" id="check_all_poli" onchange="toggleAllPoli(this)" <?= $isAllSelected ? 'checked' : '' ?>>
                            <span>🌐 Semua Poli Pelayanan</span>
                        </label>
                        <span style="color:#CBD5E1;">|</span>
                        <?php foreach ($allPoliList as $po): 
                            $pid = (string)$po['id_poli'];
                            $pChecked = $isAllSelected || in_array($pid, $curPoliArray, true);
                            $iconPoli = '🩺';
                            if (stripos($po['nama_poli'], 'Gigi') !== false) $iconPoli = '🦷';
                            elseif (stripos($po['nama_poli'], 'Spesialis') !== false) $iconPoli = '🤰';
                            elseif (stripos($po['nama_poli'], 'Kebidanan') !== false) $iconPoli = '🤱';
                        ?>
                            <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;font-size:13px;color:#334155;">
                                <input type="checkbox" name="poli_terkait[]" value="<?= (int)$po['id_poli'] ?>" class="poli-checkbox" onchange="onPoliCheckboxChange()" <?= $pChecked ? 'checked' : '' ?>>
                                <span><?= $iconPoli ?> <?= adminEsc($po['nama_poli']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span style="font-size:11.5px;color:var(--hanania-muted);margin-top:6px;display:block;">
                        💡 <strong>Atur kemunculan tindakan:</strong> Centang poli yang Anda inginkan agar tindakan ini hanya dapat dipilih oleh pasien saat memilih poli tersebut di formulir pendaftaran.
                    </span>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1.2fr 130px;gap:16px;margin-bottom:20px;">
                    <div>
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Keterangan Tambahan / Penjelasan Singkat</label>
                        <input type="text" name="keterangan" value="<?= adminEsc($editData['keterangan'] ?? '') ?>" class="filter-input" style="width:100%;" placeholder="Contoh: Tindakan khusus kebidanan &amp; pemeriksaan ANC rutin">
                    </div>

                    <div>
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Status *</label>
                        <select name="status" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                            <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (Tampil)</option>
                            <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Sembunyi)</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;text-align:center;">Urutan Tampil</label>
                        <input type="number" name="urutan" value="<?= (int)($editData['urutan'] ?? (count($dataTindakan) + 1)) ?>" min="1" class="filter-input" style="width:100%;text-align:center;font-weight:700;">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #F4EEFB;">
                    <?php if ($editData): ?>
                        <a href="layanan_pendaftaran.php?tab=layanan" class="btn btn-sm btn-light">Batal</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);padding:9px 20px;">
                        <span>💾</span> <?= $editData ? 'Simpan Perubahan' : 'Tambahkan ke Form Pasien' ?>
                    </button>
                </div>
            </form>
        </section>

        <!-- TABEL DAFTAR LAYANAN PENDAFTARAN -->
        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3 class="admin-card-title">
                        <span>📋</span> Daftar Pilihan Layanan Pasien (<?= count($dataTindakan) ?>)
                    </h3>
                    <p class="admin-card-subtitle">Layanan yang berstatus "Aktif" akan langsung muncul pada pilihan tindakan di formulir pendaftaran online pasien.</p>
                </div>
            </div>

            <div class="admin-table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;text-align:center;">Urut</th>
                            <th>Ikon &amp; Nama Tindakan / Layanan</th>
                            <th>Poli Terkait</th>
                            <th>Keterangan</th>
                            <th style="width:110px;text-align:center;">Status</th>
                            <th style="width:150px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dataTindakan)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:36px;color:var(--hanania-muted);">Belum ada tindakan pendaftaran. Tambahkan melalui formulir di atas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dataTindakan as $t): ?>
                                <tr>
                                    <td style="text-align:center;font-weight:800;color:var(--hanania-muted);"><?= (int)$t['urutan'] ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <span style="font-size:18px;width:34px;height:34px;background:var(--hanania-purple-soft);border:1px solid var(--hanania-border);border-radius:10px;display:flex;align-items:center;justify-content:center;"><?= adminEsc($t['icon'] ?: '🩺') ?></span>
                                            <strong style="color:var(--hanania-primary);font-size:13.5px;"><?= adminEsc($t['nama_tindakan']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $pt = $t['id_poli_terkait'] ?? 'all';
                                        if ($pt === 'all' || $pt === '') {
                                            echo '<span class="badge badge-purple" style="font-weight:700;">🌐 Semua Poli</span>';
                                        } else {
                                            $pIds = explode(',', $pt);
                                            echo '<div style="display:flex;flex-wrap:wrap;gap:5px;">';
                                            foreach ($pIds as $pIdItem) {
                                                $pIdItem = trim($pIdItem);
                                                if ($pIdItem === '1') {
                                                    echo '<span class="badge" style="background:#EBF5FF;color:#0052CC;font-weight:700;font-size:11.5px;">🩺 Poli Umum</span>';
                                                } elseif ($pIdItem === '2') {
                                                    echo '<span class="badge" style="background:#EBFDF2;color:#0E6245;font-weight:700;font-size:11.5px;">🦷 Poli Gigi</span>';
                                                } elseif ($pIdItem === '3') {
                                                    echo '<span class="badge" style="background:#FDF2F8;color:#9D174D;font-weight:700;font-size:11.5px;">🤱 Kebidanan</span>';
                                                } elseif ($pIdItem === '4') {
                                                    echo '<span class="badge" style="background:#F5EEFD;color:#6A22A7;font-weight:700;font-size:11.5px;">🤰 Spesialis Kandungan</span>';
                                                } elseif (isset($poliMap[$pIdItem])) {
                                                    echo '<span class="badge badge-gray" style="font-size:11.5px;">' . adminEsc($poliMap[$pIdItem]) . '</span>';
                                                }
                                            }
                                            echo '</div>';
                                        }
                                        ?>
                                    </td>
                                    <td style="font-size:12.5px;color:var(--hanania-muted);"><?= adminEsc($t['keterangan'] ?: '-') ?></td>
                                    <td style="text-align:center;">
                                        <?php if ($t['status'] === 'aktif'): ?>
                                            <a href="layanan_pendaftaran.php?tab=layanan&toggle_status=<?= (int)$t['id_tindakan'] ?>" class="badge badge-success" title="Klik untuk nonaktifkan" style="cursor:pointer;text-decoration:none;">✓ Aktif</a>
                                        <?php else: ?>
                                            <a href="layanan_pendaftaran.php?tab=layanan&toggle_status=<?= (int)$t['id_tindakan'] ?>" class="badge badge-gray" title="Klik untuk aktifkan" style="cursor:pointer;text-decoration:none;">✕ Nonaktif</a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:inline-flex;gap:6px;">
                                            <a href="layanan_pendaftaran.php?tab=layanan&edit=<?= (int)$t['id_tindakan'] ?>#formBox" class="btn btn-sm btn-light" style="padding:4px 10px;font-size:12px;">✏️ Edit</a>
                                            <a href="layanan_pendaftaran.php?tab=layanan&hapus=<?= (int)$t['id_tindakan'] ?>" onclick="return confirm('Hapus tindakan pendaftaran ini?')" class="btn btn-sm btn-danger" style="padding:4px 9px;font-size:12px;">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'fields'): ?>
        <!-- ==========================================
             TAB 2: DATA YANG DIMINTA DARI PASIEN
             ========================================== -->
        <?php if ($editFieldData): ?>
            <!-- FORM EDIT DATA FIELD -->
            <section class="admin-card" style="margin-bottom:24px;" id="editFieldBox">
                <div class="admin-card-head">
                    <div>
                        <h3 class="admin-card-title">
                            <span>✏️</span> Edit Pengaturan Kolom: <?= adminEsc($editFieldData['label_field']) ?>
                        </h3>
                        <p class="admin-card-subtitle">
                            Ubah label, ketentuan sifat wajib, dan petunjuk teks bantuan yang dilihat pasien di formulir.
                        </p>
                    </div>
                    <a href="layanan_pendaftaran.php?tab=fields" class="btn btn-sm btn-light">Batal</a>
                </div>

                <form action="layanan_pendaftaran.php?tab=fields" method="POST">
                    <input type="hidden" name="form_target" value="field">
                    <input type="hidden" name="id_field" value="<?= (int)$editFieldData['id_field'] ?>">

                    <div style="display:grid;grid-template-columns:1.2fr 2fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Kode Field (Sistem)</label>
                            <input type="text" value="<?= adminEsc($editFieldData['nama_field']) ?>" disabled class="filter-input" style="width:100%;background:#F6F2FA;color:#776B8A;">
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Label Formulir Pasien *</label>
                            <input type="text" name="label_field" value="<?= adminEsc($editFieldData['label_field']) ?>" required class="filter-input" style="width:100%;">
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Sifat Kewajiban</label>
                            <select name="wajib" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="1" <?= (int)$editFieldData['wajib'] === 1 ? 'selected' : '' ?>>Wajib Diisi (*)</option>
                                <option value="0" <?= (int)$editFieldData['wajib'] === 0 ? 'selected' : '' ?>>Opsional</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Status Tampil</label>
                            <select name="status" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="aktif" <?= $editFieldData['status'] === 'aktif' ? 'selected' : '' ?>>Aktif (Tampil)</option>
                                <option value="nonaktif" <?= $editFieldData['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Sembunyi)</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Petunjuk / Teks Bantuan Pasien</label>
                        <input type="text" name="keterangan" value="<?= adminEsc($editFieldData['keterangan'] ?? '') ?>" placeholder="Contoh: Masukkan 16 digit sesuai KTP atau KK" class="filter-input" style="width:100%;">
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #F4EEFB;">
                        <a href="layanan_pendaftaran.php?tab=fields" class="btn btn-sm btn-light">Batal</a>
                        <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);padding:9px 20px;">
                            <span>💾</span> Simpan Pengaturan Kolom
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- FORM TAMBAH DATA BARU YANG DIMINTA -->
        <section class="admin-card" style="margin-bottom:24px;" id="boxTambahField">
            <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 class="admin-card-title">
                        <span>➕</span> Tambah Data Baru yang Diminta dari Pasien
                    </h3>
                    <p class="admin-card-subtitle">
                        Tambahkan kolom data baru yang ingin diminta dari pasien saat pendaftaran (misal: Nomor BPJS, Golongan Darah, Alergi Obat, dll). Kolom akan otomatis dibuat pada basis data.
                    </p>
                </div>
                <button type="button" class="btn btn-sm" onclick="document.getElementById('formTambahFieldBody').style.display = (document.getElementById('formTambahFieldBody').style.display === 'none' ? 'block' : 'none')" style="background:var(--hanania-primary);color:#fff;">
                    <span>✨</span> Form Tambah Data Baru
                </button>
            </div>

            <div id="formTambahFieldBody" style="display:none;padding-top:16px;border-top:1px solid #F4EEFB;">
                <form action="layanan_pendaftaran.php?tab=fields" method="POST">
                    <input type="hidden" name="form_target" value="tambah_field">

                    <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Label Formulir Pasien *</label>
                            <input type="text" name="label_field" placeholder="Contoh: Nomor Kartu BPJS / Asuransi" required class="filter-input" style="width:100%;">
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Kode Kolom (Opsional)</label>
                            <input type="text" name="nama_field" placeholder="Otomatis dari label jika kosong" class="filter-input" style="width:100%;">
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Tahap Formulir</label>
                            <select name="tahap" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="1">Langkah 1: Data Diri</option>
                                <option value="2">Langkah 2: Layanan &amp; Keluhan</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Sifat Kewajiban</label>
                            <select name="wajib" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="0">Opsional (Boleh Kosong)</option>
                                <option value="1">Wajib Diisi (*)</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Petunjuk / Teks Bantuan Pasien</label>
                        <input type="text" name="keterangan" placeholder="Contoh: Masukkan 13 digit nomor BPJS Kesehatan aktif Anda" class="filter-input" style="width:100%;">
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid #F4EEFB;">
                        <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('formTambahFieldBody').style.display='none'">Tutup</button>
                        <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);padding:9px 20px;">
                            <span>➕</span> Tambahkan Data ke Formulir
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- INFO BANNER LOGIKA DATA DIRI -->
        <div style="background:#FAF8FD;border:1.5px solid #E8DEF5;border-radius:14px;padding:14px 18px;margin-bottom:20px;display:flex;gap:14px;align-items:center;">
            <span style="font-size:24px;">💡</span>
            <div style="font-size:12.5px;color:#5A4B70;line-height:1.5;">
                <strong>Logika Dinamis Data Pasien:</strong> Pasien yang <strong>belum menikah</strong> atau <strong>pasien anak (&lt; 17 tahun)</strong> pada formulir pendaftaran secara cerdas menampilkan <strong>Nama Ayah Kandung</strong>. Sedangkan pasien yang sudah menikah menampilkan <strong>Nama Suami / Pasangan</strong>. <strong>Nama Ibu Kandung</strong> tetap ditampilkan standar untuk semua pasien.
            </div>
        </div>

        <!-- TABEL DATA FORMULIR PASIEN -->
        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3 class="admin-card-title">
                        <span>📋</span> Kelola Data yang Diminta Saat Pendaftaran Pasien (<?= count($dataFields) ?>)
                    </h3>
                    <p class="admin-card-subtitle">Atur apakah suatu data pasien wajib diisi, opsional, atau disembunyikan dari formulir pendaftaran online pasien.</p>
                </div>
            </div>

            <div class="admin-table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;text-align:center;">Urut</th>
                            <th>Nama Data / Field Identitas</th>
                            <th>Tahap Form</th>
                            <th>Tipe Data</th>
                            <th>Petunjuk Pasien</th>
                            <th style="width:130px;text-align:center;">Sifat Field</th>
                            <th style="width:120px;text-align:center;">Visibilitas</th>
                            <th style="width:120px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataFields as $f): 
                            $isCustom = (int)($f['is_custom'] ?? 0) === 1;
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:800;color:var(--hanania-muted);"><?= (int)$f['urutan'] ?></td>
                                <td>
                                    <div style="display:flex;flex-direction:column;">
                                        <strong style="color:var(--hanania-primary);font-size:13.5px;">
                                            <?= adminEsc($f['label_field']) ?>
                                        </strong>
                                        <code style="font-size:11px;color:#8E7E9E;margin-top:2px;"><?= adminEsc($f['nama_field']) ?></code>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-purple">
                                        <?= (int)$f['tahap'] === 1 ? 'Langkah 1: Data Diri' : 'Langkah 2: Layanan & Keluhan' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isCustom): ?>
                                        <span class="badge" style="background:#FDF2F8;color:#9D174D;border:1px solid #FBCFE8;font-size:11px;font-weight:700;">✨ Kustom Admin</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#F3F4F6;color:#4B5563;border:1px solid #E5E7EB;font-size:11px;font-weight:600;">🛡️ Bawaan Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;color:var(--hanania-muted);"><?= adminEsc($f['keterangan'] ?: '-') ?></td>
                                <td style="text-align:center;">
                                    <?php if ((int)$f['wajib'] === 1): ?>
                                        <a href="layanan_pendaftaran.php?tab=fields&toggle_field_wajib=<?= (int)$f['id_field'] ?>" class="badge badge-danger" title="Klik untuk ubah jadi Opsional" style="cursor:pointer;text-decoration:none;">
                                            ★ Wajib Diisi
                                        </a>
                                    <?php else: ?>
                                        <a href="layanan_pendaftaran.php?tab=fields&toggle_field_wajib=<?= (int)$f['id_field'] ?>" class="badge badge-gray" title="Klik untuk ubah jadi Wajib" style="cursor:pointer;text-decoration:none;">
                                            ○ Opsional
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($f['status'] === 'aktif'): ?>
                                        <a href="layanan_pendaftaran.php?tab=fields&toggle_field_status=<?= (int)$f['id_field'] ?>" class="badge badge-success" title="Klik untuk sembunyikan" style="cursor:pointer;text-decoration:none;">
                                            ✓ Tampil
                                        </a>
                                    <?php else: ?>
                                        <a href="layanan_pendaftaran.php?tab=fields&toggle_field_status=<?= (int)$f['id_field'] ?>" class="badge badge-gray" title="Klik untuk tampilkan" style="cursor:pointer;text-decoration:none;">
                                            ✕ Sembunyi
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex;gap:5px;">
                                        <a href="layanan_pendaftaran.php?tab=fields&edit_field=<?= (int)$f['id_field'] ?>#editFieldBox" class="btn btn-sm btn-light" style="padding:4px 9px;font-size:12px;" title="Edit Pengaturan Data">
                                            ✏️
                                        </a>
                                        <?php if ($isCustom): ?>
                                            <a href="layanan_pendaftaran.php?tab=fields&hapus_field=<?= (int)$f['id_field'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data kolom ini dari formulir?')" class="btn btn-sm btn-danger" style="padding:4px 8px;font-size:12px;" title="Hapus Field Kustom">
                                                🗑️
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'poli'): ?>
        <!-- ==========================================
             TAB 3: STATUS OPERASIONAL POLI & 24 JAM
             ========================================== -->
        <?php if ($editPoliData): ?>
            <!-- FORM EDIT POLI -->
            <section class="admin-card" style="margin-bottom:24px;" id="editPoliBox">
                <div class="admin-card-head">
                    <div>
                        <h3 class="admin-card-title">
                            <span>✏️</span> Edit Pengaturan Operasional: <?= adminEsc($editPoliData['nama_poli']) ?>
                        </h3>
                        <p class="admin-card-subtitle">
                            Atur nama poli, deskripsi, mode operasional otomatis/manual, status 24 jam nonstop, serta waktu cutoff pendaftaran.
                        </p>
                    </div>
                    <a href="layanan_pendaftaran.php?tab=poli" class="btn btn-sm btn-light">Batal</a>
                </div>

                <form action="layanan_pendaftaran.php?tab=poli" method="POST">
                    <input type="hidden" name="form_target" value="poli">
                    <input type="hidden" name="id_poli" value="<?= (int)$editPoliData['id_poli'] ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Nama Poli Pelayanan *</label>
                            <input type="text" name="nama_poli" value="<?= adminEsc($editPoliData['nama_poli']) ?>" required class="filter-input" style="width:100%;">
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Status Operasional Pendaftaran</label>
                            <select name="status_operasional" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="otomatis" <?= $editPoliData['status_operasional'] === 'otomatis' ? 'selected' : '' ?>>⚙️ Otomatis (Tutup otomatis 1 jam sebelum jadwal berakhir)</option>
                                <option value="tersedia" <?= in_array($editPoliData['status_operasional'], ['tersedia', 'buka_manual'], true) ? 'selected' : '' ?>>🟢 Pendaftaran Tersedia (Buka Manual)</option>
                                <option value="ditutup" <?= in_array($editPoliData['status_operasional'], ['ditutup', 'tutup_manual'], true) ? 'selected' : '' ?>>⛔ Pendaftaran Ditutup (Tutup Manual)</option>
                                <option value="full_booking" <?= $editPoliData['status_operasional'] === 'full_booking' ? 'selected' : '' ?>>🟡 Full Booking (Kuota Penuh)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Waktu Cutoff Pendaftaran (Menit)</label>
                            <input type="number" name="waktu_cutoff_menit" value="<?= (int)($editPoliData['waktu_cutoff_menit'] ?? 60) ?>" min="0" max="180" class="filter-input" style="width:100%;" placeholder="60">
                            <small style="color:var(--hanania-muted);font-size:11px;display:block;margin-top:4px;">Pendaftaran ditutup N menit sebelum sesi praktik berakhir (default 60 menit).</small>
                        </div>

                        <div>
                            <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Status Keaktifan Poli</label>
                            <select name="status" class="filter-input" style="width:100%;background:#fff;font-weight:600;">
                                <option value="aktif" <?= ($editPoliData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>✓ Aktif (Tampil di Sistem Pendaftaran)</option>
                                <option value="nonaktif" <?= ($editPoliData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>✕ Nonaktif (Disembunyikan)</option>
                            </select>
                        </div>
                    </div>

                    <div style="background:#FAF5FF;border:1.5px dashed #D8B4FE;border-radius:12px;padding:12px 16px;margin-bottom:16px;">
                        <label style="display:inline-flex;align-items:center;gap:10px;font-weight:700;color:#6B21A8;cursor:pointer;user-select:none;margin:0;">
                            <input type="checkbox" name="is_24jam" value="1" <?= (int)($editPoliData['is_24jam'] ?? 0) === 1 ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#7E22CE;cursor:pointer;">
                            <span>🌙 Layanan Siaga 24 Jam Nonstop (Tidak Pernah Ditutup oleh Cutoff Otomatis)</span>
                        </label>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Deskripsi Poli</label>
                        <textarea name="deskripsi" rows="2" class="filter-input" style="width:100%;resize:vertical;"><?= adminEsc($editPoliData['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px;color:#2D253B;">Pesan Kustom Saat Tutup (Opsional)</label>
                        <input type="text" name="pesan_tutup" value="<?= adminEsc($editPoliData['pesan_tutup'] ?? '') ?>" placeholder="Contoh: Pendaftaran dibuka kembali besok pagi pukul 08:00 WIB" class="filter-input" style="width:100%;">
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid #F4EEFB;">
                        <a href="layanan_pendaftaran.php?tab=poli" class="btn btn-sm btn-light">Batal</a>
                        <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;box-shadow:0 4px 14px rgba(79,50,147,.22);padding:9px 20px;">
                            <span>💾</span> Simpan Perubahan Poli
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- BANNER ATURAN 24 JAM -->
        <div style="background:#FAF5FF;border:1.5px solid #E9D5FF;border-radius:14px;padding:14px 18px;margin-bottom:20px;display:flex;gap:14px;align-items:center;">
            <span style="font-size:26px;">🌙</span>
            <div style="font-size:12.5px;color:#581C87;line-height:1.5;">
                <strong>Pelayanan Persalinan 24 Jam &amp; Penutupan Cutoff:</strong> Poli reguler secara otomatis ditutup <strong>60 menit</strong> sebelum sesi berakhir. Untuk layanan <strong>Persalinan 24 Jam</strong>, sistem dirancang selalu siaga 24/7 dan tidak ditutup oleh cutoff waktu.
            </div>
        </div>

        <!-- TABEL DATA POLI -->
        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3 class="admin-card-title">
                        <span>🏥</span> Daftar Poli Pelayanan &amp; Status Operasional (<?= count($dataPoliLengkap) ?>)
                    </h3>
                    <p class="admin-card-subtitle">Kelola jam cutoff, status 24 jam nonstop, serta override buka/tutup manual untuk setiap poli pelayanan.</p>
                </div>
            </div>

            <div class="admin-table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;text-align:center;">ID</th>
                            <th>Nama Poli Pelayanan</th>
                            <th style="width:140px;text-align:center;">Layanan 24 Jam</th>
                            <th>Mode Operasional</th>
                            <th style="width:100px;text-align:center;">Cutoff</th>
                            <th style="width:110px;text-align:center;">Keaktifan</th>
                            <th style="width:140px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataPoliLengkap as $p): 
                            $is24 = (int)($p['is_24jam'] ?? 0) === 1;
                            $stOp = $p['status_operasional'] ?? 'otomatis';
                            $badgeOp = 'badge-purple';
                            $lblOp = '⚙️ Otomatis';
                            if ($stOp === 'tersedia' || $stOp === 'buka_manual') {
                                $badgeOp = 'badge-success';
                                $lblOp = '🟢 Pendaftaran Tersedia';
                            } elseif ($stOp === 'ditutup' || $stOp === 'tutup_manual') {
                                $badgeOp = 'badge-danger';
                                $lblOp = '⛔ Pendaftaran Ditutup';
                            } elseif ($stOp === 'full_booking') {
                                $badgeOp = 'badge-warning';
                                $lblOp = '🟡 Full Booking';
                            }
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:800;color:var(--hanania-muted);"><?= (int)$p['id_poli'] ?></td>
                                <td>
                                    <strong style="color:var(--hanania-primary);font-size:13.5px;"><?= adminEsc($p['nama_poli']) ?></strong>
                                    <?php if (!empty($p['deskripsi'])): ?>
                                        <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:3px;"><?= adminEsc($p['deskripsi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is24): ?>
                                        <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_24jam=<?= (int)$p['id_poli'] ?>" class="badge" style="background:#FAF5FF;color:#6B21A8;border:1px solid #D8B4FE;font-size:11px;font-weight:700;text-decoration:none;" title="Klik untuk nonaktifkan mode 24 jam">
                                            🌙 Siaga 24 Jam
                                        </a>
                                    <?php else: ?>
                                        <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_24jam=<?= (int)$p['id_poli'] ?>" class="badge badge-gray" style="font-size:11px;text-decoration:none;" title="Klik untuk aktifkan mode 24 jam">
                                            ⏰ Terjadwal
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:5px;">
                                        <span class="badge <?= $badgeOp ?>" style="font-size:11.5px;width:fit-content;"><?= adminEsc($lblOp) ?></span>
                                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                            <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_mode=<?= (int)$p['id_poli'] ?>&mode=otomatis" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10px;text-decoration:none;" title="Mode Otomatis Sesuai Jadwal">Otomatis</a>
                                            <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_mode=<?= (int)$p['id_poli'] ?>&mode=buka_manual" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10px;color:#16A34A;background:#DCFCE7;border:1px solid #86EFAC;text-decoration:none;" title="Buka Manual">🟢 Buka</a>
                                            <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_mode=<?= (int)$p['id_poli'] ?>&mode=tutup_manual" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10px;color:#DC2626;background:#FEE2E2;border:1px solid #FCA5A5;text-decoration:none;" title="Tutup Manual">⛔ Tutup</a>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--hanania-primary);">
                                    <?= (int)($p['waktu_cutoff_menit'] ?? 60) ?>m
                                </td>
                                <td style="text-align:center;">
                                    <?php if (($p['status'] ?? 'aktif') === 'aktif'): ?>
                                        <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_aktif=<?= (int)$p['id_poli'] ?>" class="badge badge-success" title="Klik untuk nonaktifkan" style="text-decoration:none;">✓ Aktif</a>
                                    <?php else: ?>
                                        <a href="layanan_pendaftaran.php?tab=poli&toggle_poli_aktif=<?= (int)$p['id_poli'] ?>" class="badge badge-gray" title="Klik untuk aktifkan" style="text-decoration:none;">✕ Nonaktif</a>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="layanan_pendaftaran.php?tab=poli&edit_poli=<?= (int)$p['id_poli'] ?>#editPoliBox" class="btn btn-sm btn-light" style="padding:4px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px;">
                                        ✏️ Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

</main>

<script>
function toggleAllPoli(master) {
    const boxes = document.querySelectorAll('.poli-checkbox');
    boxes.forEach(b => {
        b.checked = master.checked;
    });
}

function onPoliCheckboxChange() {
    const master = document.getElementById('check_all_poli');
    const boxes = document.querySelectorAll('.poli-checkbox');
    const total = boxes.length;
    const checked = Array.from(boxes).filter(b => b.checked).length;
    if (master) {
        master.checked = (checked === total);
    }
}

</script>

<?php adminPageEnd(); ?>
