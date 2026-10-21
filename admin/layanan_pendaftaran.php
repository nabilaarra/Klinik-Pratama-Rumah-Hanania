<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';
$tab = trim($_GET['tab'] ?? 'layanan');
if (!in_array($tab, ['layanan', 'fields'], true)) {
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

    <?php else: ?>
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
                            <th>Petunjuk Pasien</th>
                            <th style="width:130px;text-align:center;">Sifat Field</th>
                            <th style="width:120px;text-align:center;">Visibilitas</th>
                            <th style="width:100px;text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataFields as $f): ?>
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
                                    <a href="layanan_pendaftaran.php?tab=fields&edit_field=<?= (int)$f['id_field'] ?>#editFieldBox" class="btn btn-sm btn-light" style="padding:4px 10px;font-size:12px;">
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
