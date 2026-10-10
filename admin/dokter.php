<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// Helper upload foto tenaga medis
if (!function_exists('uploadFotoTenaga')) {
function uploadFotoTenaga($fileKey = 'foto') {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fileKey];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        throw new Exception('Format berkas foto harus JPG, PNG, atau WEBP.');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new Exception('Ukuran berkas foto maksimal 4 MB.');
    }
    $uploadDir = __DIR__ . '/../uploads/tenaga/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $newFileName = 'tenaga_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $targetPath = $uploadDir . $newFileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Gagal menyimpan foto ke folder uploads/tenaga.');
    }
    return 'uploads/tenaga/' . $newFileName;
}
}

// Helper simpan foto hasil atur posisi / crop profil (base64)
if (!function_exists('saveCroppedFotoTenaga')) {
function saveCroppedFotoTenaga($base64Data) {
    $base64Data = trim((string)$base64Data);
    if ($base64Data === '') return null;
    if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $base64Data, $matches)) {
        $raw = base64_decode($matches[2]);
        if (!$raw) return null;
        if (strlen($raw) > 6 * 1024 * 1024) {
            throw new Exception('Ukuran berkas foto hasil penyesuaian posisi terlalu besar.');
        }
        $uploadDir = __DIR__ . '/../uploads/tenaga/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $newFileName = 'tenaga_profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $targetPath = $uploadDir . $newFileName;
        if (file_put_contents($targetPath, $raw) === false) {
            throw new Exception('Gagal menyimpan foto hasil pengaturan posisi ke server.');
        }
        return 'uploads/tenaga/' . $newFileName;
    }
    return null;
}
}

// Helper resolusi URL foto tenaga medis
if (!function_exists('resolveDokterFoto')) {
function resolveDokterFoto($path, $prefix = '../') {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    $clean = ltrim($path, '/');
    if (strpos($clean, 'uploads/') === 0 || strpos($clean, 'assets/') === 0) {
        return $prefix . $clean;
    }
    if (file_exists(__DIR__ . '/../uploads/tenaga/' . $clean)) {
        return $prefix . 'uploads/tenaga/' . $clean;
    }
    if (file_exists(__DIR__ . '/../assets/img/' . $clean)) {
        return $prefix . 'assets/img/' . $clean;
    }
    return $prefix . 'uploads/' . $clean;
}
}

// Ambil pesan flash session jika ada
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Ambil data poli untuk opsi dropdown
$daftarPoli = [];
$resPoli = $db->query("SELECT id_poli, nama_poli FROM poli ORDER BY nama_poli ASC");
if ($resPoli) {
    while ($row = $resPoli->fetch_assoc()) {
        $daftarPoli[] = $row;
    }
}

// Filter jenis dari URL atau POST
$filterJenis = trim($_GET['jenis'] ?? $_POST['filter_jenis'] ?? 'semua');

// PROSES TAMBAH, EDIT, GANTI FOTO & HAPUS TENAGA MEDIS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'tambah') {
            $type = $_POST['type'] ?? 'dokter';
            $nama = trim($_POST['nama'] ?? '');
            $idPoli = (int)($_POST['id_poli'] ?? 0);
            $spesialisasi = trim($_POST['spesialisasi'] ?? '');
            $noTelepon = trim($_POST['no_telepon'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['aktif', 'cuti', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

            if ($nama === '') {
                throw new Exception('Nama tenaga medis wajib diisi.');
            }

            // Utamakan foto hasil pengaturan posisi / crop jika ada
            $fotoPath = saveCroppedFotoTenaga($_POST['foto_cropped'] ?? '') ?: uploadFotoTenaga('foto');
            $valPoli = $idPoli > 0 ? $idPoli : null;
            $valFoto = $fotoPath ?: '';

            if ($type === 'bidan') {
                $stmt = $db->prepare("INSERT INTO bidan (nama_bidan, id_poli, no_telepon, foto, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sisss', $nama, $valPoli, $noTelepon, $valFoto, $status);
                    if (!$stmt->execute()) {
                        throw new Exception('Gagal menyimpan data bidan: ' . $stmt->error);
                    }
                    $stmt->close();
                    $message = "Data bidan \"$nama\" berhasil ditambahkan!";
                } else {
                    throw new Exception('Gagal menyiapkan query database: ' . $db->error);
                }
            } else {
                $stmt = $db->prepare("INSERT INTO dokter (nama_dokter, id_poli, spesialisasi, no_telepon, foto, status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sissss', $nama, $valPoli, $spesialisasi, $noTelepon, $valFoto, $status);
                    if (!$stmt->execute()) {
                        throw new Exception('Gagal menyimpan data dokter: ' . $stmt->error);
                    }
                    $stmt->close();
                    $message = "Data dokter \"$nama\" berhasil ditambahkan!";
                } else {
                    throw new Exception('Gagal menyiapkan query database: ' . $db->error);
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $type = $_POST['type'] ?? 'dokter';
            $nama = trim($_POST['nama'] ?? '');
            $idPoli = (int)($_POST['id_poli'] ?? 0);
            $spesialisasi = trim($_POST['spesialisasi'] ?? '');
            $noTelepon = trim($_POST['no_telepon'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['aktif', 'cuti', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
            $hapusFoto = !empty($_POST['hapus_foto']);
            // Utamakan foto hasil pengaturan posisi / crop jika ada
            $fotoBaru = saveCroppedFotoTenaga($_POST['foto_cropped'] ?? '') ?: uploadFotoTenaga('foto');

            if ($id <= 0 || $nama === '') {
                throw new Exception('Data tenaga medis tidak lengkap.');
            }

            $valPoli = $idPoli > 0 ? $idPoli : null;

            if ($type === 'bidan') {
                if ($fotoBaru) {
                    $stmt = $db->prepare("UPDATE bidan SET nama_bidan = ?, id_poli = ?, no_telepon = ?, foto = ?, status = ? WHERE id_bidan = ?");
                    $stmt->bind_param('sisssi', $nama, $valPoli, $noTelepon, $fotoBaru, $status, $id);
                } elseif ($hapusFoto) {
                    $stmt = $db->prepare("UPDATE bidan SET nama_bidan = ?, id_poli = ?, no_telepon = ?, foto = '', status = ? WHERE id_bidan = ?");
                    $stmt->bind_param('sissi', $nama, $valPoli, $noTelepon, $status, $id);
                } else {
                    $stmt = $db->prepare("UPDATE bidan SET nama_bidan = ?, id_poli = ?, no_telepon = ?, status = ? WHERE id_bidan = ?");
                    $stmt->bind_param('sissi', $nama, $valPoli, $noTelepon, $status, $id);
                }
                if ($stmt) {
                    if (!$stmt->execute()) {
                        throw new Exception('Gagal memperbarui data bidan: ' . $stmt->error);
                    }
                    $stmt->close();
                    $message = "Data bidan \"$nama\" berhasil diperbarui!";
                } else {
                    throw new Exception('Gagal menyiapkan query update bidan: ' . $db->error);
                }
            } else {
                if ($fotoBaru) {
                    $stmt = $db->prepare("UPDATE dokter SET nama_dokter = ?, id_poli = ?, spesialisasi = ?, no_telepon = ?, foto = ?, status = ? WHERE id_dokter = ?");
                    $stmt->bind_param('sissssi', $nama, $valPoli, $spesialisasi, $noTelepon, $fotoBaru, $status, $id);
                } elseif ($hapusFoto) {
                    $stmt = $db->prepare("UPDATE dokter SET nama_dokter = ?, id_poli = ?, spesialisasi = ?, no_telepon = ?, foto = '', status = ? WHERE id_dokter = ?");
                    $stmt->bind_param('sisssi', $nama, $valPoli, $spesialisasi, $noTelepon, $status, $id);
                } else {
                    $stmt = $db->prepare("UPDATE dokter SET nama_dokter = ?, id_poli = ?, spesialisasi = ?, no_telepon = ?, status = ? WHERE id_dokter = ?");
                    $stmt->bind_param('sisssi', $nama, $valPoli, $spesialisasi, $noTelepon, $status, $id);
                }
                if ($stmt) {
                    if (!$stmt->execute()) {
                        throw new Exception('Gagal memperbarui data dokter: ' . $stmt->error);
                    }
                    $stmt->close();
                    $message = "Data dokter \"$nama\" berhasil diperbarui!";
                } else {
                    throw new Exception('Gagal menyiapkan query update dokter: ' . $db->error);
                }
            }
        } elseif ($action === 'ganti_foto') {
            $id = (int)($_POST['id'] ?? 0);
            $type = $_POST['type'] ?? 'dokter';
            if ($id <= 0) {
                throw new Exception('Tenaga medis tidak valid.');
            }
            // Utamakan foto hasil pengaturan posisi / crop jika ada
            $fotoBaru = saveCroppedFotoTenaga($_POST['foto_cropped_modal'] ?? '') ?: uploadFotoTenaga('foto_cepat');
            if (!$fotoBaru) {
                throw new Exception('Pilih berkas foto baru atau sesuaikan posisi foto terlebih dahulu.');
            }
            if ($type === 'bidan') {
                $stmt = $db->prepare("UPDATE bidan SET foto = ? WHERE id_bidan = ?");
            } else {
                $stmt = $db->prepare("UPDATE dokter SET foto = ? WHERE id_dokter = ?");
            }
            if ($stmt) {
                $stmt->bind_param('si', $fotoBaru, $id);
                if (!$stmt->execute()) {
                    throw new Exception('Gagal menyimpan foto: ' . $stmt->error);
                }
                $stmt->close();
                $message = "Foto profil tenaga medis berhasil diperbarui dan disesuaikan posisinya!";
            } else {
                throw new Exception('Gagal menyiapkan query simpan foto: ' . $db->error);
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            $type = $_POST['type'] ?? 'dokter';

            if ($id > 0) {
                $cekSql = $type === 'bidan' 
                    ? "SELECT COUNT(*) AS c FROM pendaftaran WHERE id_bidan = ?"
                    : "SELECT COUNT(*) AS c FROM pendaftaran WHERE id_dokter = ?";
                $stmtCek = $db->prepare($cekSql);
                $stmtCek->bind_param('i', $id);
                $stmtCek->execute();
                $count = (int)($stmtCek->get_result()->fetch_assoc()['c'] ?? 0);
                $stmtCek->close();

                if ($count > 0) {
                    $ubahStatus = $type === 'bidan'
                        ? "UPDATE bidan SET status = 'nonaktif' WHERE id_bidan = ?"
                        : "UPDATE dokter SET status = 'nonaktif' WHERE id_dokter = ?";
                    $stmtUbah = $db->prepare($ubahStatus);
                    $stmtUbah->bind_param('i', $id);
                    $stmtUbah->execute();
                    $stmtUbah->close();
                    $message = "Tenaga medis memiliki riwayat pelayanan ($count pendaftaran). Status otomatis diubah menjadi Nonaktif untuk menjaga riwayat data.";
                    $messageType = 'warning';
                } else {
                    $delJadwal = $type === 'bidan' ? "DELETE FROM jadwal_bidan WHERE id_bidan = ?" : "DELETE FROM jadwal_dokter WHERE id_dokter = ?";
                    $stmtJ = $db->prepare($delJadwal);
                    if ($stmtJ) {
                        $stmtJ->bind_param('i', $id);
                        $stmtJ->execute();
                        $stmtJ->close();
                    }

                    $delStaff = $type === 'bidan' ? "DELETE FROM bidan WHERE id_bidan = ?" : "DELETE FROM dokter WHERE id_dokter = ?";
                    $stmtD = $db->prepare($delStaff);
                    if ($stmtD) {
                        $stmtD->bind_param('i', $id);
                        if (!$stmtD->execute()) {
                            throw new Exception('Gagal menghapus data: ' . $stmtD->error);
                        }
                        $stmtD->close();
                        $message = "Data tenaga medis berhasil dihapus.";
                    } else {
                        throw new Exception('Gagal menyiapkan query hapus: ' . $db->error);
                    }
                }
            }
        }

        if ($message !== '') {
            $_SESSION['flash_msg'] = $message;
            $_SESSION['flash_type'] = $messageType;
            $redir = 'dokter.php' . ($filterJenis !== 'semua' ? '?jenis=' . urlencode($filterJenis) : '');
            header('Location: ' . $redir);
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_msg'] = $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        $redir = 'dokter.php' . ($filterJenis !== 'semua' ? '?jenis=' . urlencode($filterJenis) : '');
        header('Location: ' . $redir);
        exit;
    }
}

// Ambil seluruh data dokter & bidan
$listStaff = [];
$qDokter = $db->query("
    SELECT d.id_dokter AS id, d.nama_dokter AS nama, d.id_poli, COALESCE(p.nama_poli, 'Umum') AS nama_poli,
           d.spesialisasi, d.no_telepon, d.foto, d.status, 'Dokter' AS jenis
    FROM dokter d
    LEFT JOIN poli p ON p.id_poli = d.id_poli
    ORDER BY d.nama_dokter ASC
");
if ($qDokter) {
    while ($row = $qDokter->fetch_assoc()) {
        $listStaff[] = $row;
    }
}

$qBidan = $db->query("
    SELECT b.id_bidan AS id, b.nama_bidan AS nama, b.id_poli, COALESCE(p.nama_poli, 'Kebidanan') AS nama_poli,
           'Bidan' AS spesialisasi, b.no_telepon, b.foto, b.status, 'Bidan' AS jenis
    FROM bidan b
    LEFT JOIN poli p ON p.id_poli = b.id_poli
    ORDER BY CASE WHEN b.nama_bidan LIKE '%Gita%' THEN 0 ELSE 1 END, b.nama_bidan ASC
");
if ($qBidan) {
    while ($row = $qBidan->fetch_assoc()) {
        $listStaff[] = $row;
    }
}

// Terapkan filter jika ada
if ($filterJenis === 'dokter') {
    $listStaff = array_filter($listStaff, fn($x) => $x['jenis'] === 'Dokter');
} elseif ($filterJenis === 'bidan') {
    $listStaff = array_filter($listStaff, fn($x) => $x['jenis'] === 'Bidan');
}

adminPageStart('Dokter dan Bidan', 'dokter');
?>
<main class="admin-page">
    <header class="admin-header">
        <div>
            <h1><span>🩺</span> Pengelolaan Tenaga Kesehatan</h1>
            <p>Kelola data dokter, bidan, foto profil, poli pelayanan, kontak, dan status ketersediaan pelayanan.</p>
        </div>
        <div class="admin-header-actions">
            <button type="button" class="btn" onclick="toggleFormTambah()">
                <span>➕</span> Tambah Tenaga Medis
            </button>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : ($messageType === 'warning' ? 'alert-warning' : 'alert-success') ?>">
            <span><?= $messageType === 'danger' ? '⚠️' : '✅' ?></span> <?= adminEsc($message) ?>
        </div>
    <?php endif; ?>

    <!-- FORM TAMBAH / EDIT TENAGA KESEHATAN -->
    <section class="admin-card" id="cardFormTenaga" style="display:none;margin-bottom:24px;">
        <div class="admin-card-title">
            <span id="formTitle"><span>➕</span> Tambah Tenaga Kesehatan Baru</span>
            <button type="button" class="btn btn-sm btn-light" onclick="tutupForm()">✕ Tutup</button>
        </div>

        <form method="POST" id="formTenaga" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="fieldId" value="0">

            <div class="form-grid">
                <div class="form-field">
                    <label>Jenis Tenaga Medis</label>
                    <select name="type" id="fieldType" onchange="toggleSpesialisasiField()">
                        <option value="dokter">Dokter</option>
                        <option value="bidan">Bidan</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Nama Lengkap &amp; Gelar *</label>
                    <input type="text" name="nama" id="fieldNama" required placeholder="Contoh: dr. Ahmad, Sp.A">
                </div>

                <div class="form-field">
                    <label>Poli Pelayanan</label>
                    <select name="id_poli" id="fieldPoli">
                        <option value="0">-- Pilih Poli --</option>
                        <?php foreach ($daftarPoli as $p): ?>
                            <option value="<?= (int)$p['id_poli'] ?>"><?= adminEsc($p['nama_poli']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field" id="wrapSpesialisasi">
                    <label>Spesialisasi / Bidang Keahlian</label>
                    <input type="text" name="spesialisasi" id="fieldSpesialisasi" placeholder="Contoh: Dokter Umum / Spesialis Kebidanan & Kandungan">
                </div>

                <div class="form-field">
                    <label>Nomor Telepon / WhatsApp</label>
                    <input type="text" name="no_telepon" id="fieldTelepon" placeholder="Contoh: 081234567890">
                </div>

                <div class="form-field">
                    <label>Status Ketersediaan</label>
                    <select name="status" id="fieldStatus">
                        <option value="aktif">Aktif</option>
                        <option value="cuti">Cuti</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <!-- UPLOAD FOTO PROFIL -->
                <div class="form-field full" style="background:#FAF8FD;border:1px dashed var(--hanania-primary);border-radius:12px;padding:18px;margin-top:6px;">
                    <label style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px;color:var(--hanania-primary);">
                        <span>📷</span> Foto Profil Tenaga Medis (JPG, PNG, WEBP maks 4MB)
                    </label>
                    <input type="hidden" name="foto_cropped" id="fieldFotoCropped" value="">

                    <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:12px;">
                        <div id="previewFotoContainer" style="width:72px;height:72px;border-radius:50%;border:2px solid var(--hanania-border);background:#fff;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                            <img id="imgPreviewFoto" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;display:none;">
                            <span id="iconPreviewFoto" style="font-size:32px;">🩺</span>
                        </div>
                        <div style="flex:1;min-width:240px;">
                            <input type="file" name="foto" id="fieldFoto" accept="image/jpeg,image/png,image/webp" class="filter-input" style="width:100%;font-size:12px;background:#fff;" onchange="handleFormFotoChange(this)">
                            <small style="font-size:11px;color:var(--hanania-muted);margin-top:4px;display:block;">
                                Foto ini akan tampil di kartu profil beranda, halaman Dokter &amp; Bidan, serta sistem pendaftaran pasien.
                            </small>
                            <div id="wrapHapusFoto" style="display:none;margin-top:8px;">
                                <label style="font-size:12px;color:var(--danger-text);cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                                    <input type="checkbox" name="hapus_foto" id="checkHapusFoto" value="1">
                                    🗑️ Hapus foto saat ini (gunakan avatar bawaan)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- PENGATUR POSISI FOTO DI FORM (GAYA MOBILE KOTAK CROP) -->
                    <div id="panelCropForm" style="display:none;background:#111827;border-radius:14px;padding:16px;margin-top:12px;color:#fff;">
                        <div style="font-size:13px;font-weight:800;color:#fff;display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <span>📐 Sesuaikan Posisi Foto Profil</span>
                            <span style="font-size:11px;color:#94a3b8;font-weight:normal;">Geser foto / scroll untuk zoom</span>
                        </div>
                        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;justify-content:center;">
                            <div id="cropContainerForm" style="width:260px;height:280px;border-radius:10px;overflow:hidden;background:#030712;border:1px solid #334155;cursor:grab;touch-action:none;position:relative;">
                                <canvas id="canvasCropForm" width="260" height="280" style="width:100%;height:100%;display:block;"></canvas>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <button type="button" class="btn btn-sm btn-light" onclick="formCropper.adjustZoom(0.1)" style="font-size:11px;">➕ Perbesar (Zoom In)</button>
                                <button type="button" class="btn btn-sm btn-light" onclick="formCropper.adjustZoom(-0.1)" style="font-size:11px;">➖ Perkecil (Zoom Out)</button>
                                <button type="button" class="btn btn-sm btn-light" onclick="formCropper.reset()" style="font-size:11px;">🔄 Reset Posisi</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-field full" style="display:flex;gap:10px;margin-top:10px;">
                    <button type="submit" class="btn" id="btnSubmitForm">Simpan Data</button>
                    <button type="button" class="btn btn-light" onclick="tutupForm()">Batal</button>
                </div>
            </div>
        </form>
    </section>

    <!-- MODAL ATUR & GANTI FOTO CEPAT (TAMPILAN CROPPER MOBILE) -->
    <div id="modalGantiFoto" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:2000;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#111827;border-radius:20px;max-width:440px;width:100%;padding:20px;box-shadow:0 25px 60px rgba(0,0,0,0.6);position:relative;color:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.12);padding-bottom:10px;">
                <div>
                    <h3 style="font-size:15px;font-weight:800;color:#fff;margin:0;display:flex;align-items:center;gap:6px;">
                        <span>📷</span> Atur Posisi Foto Dokter / Bidan
                    </h3>
                    <div id="modalFotoNama" style="font-size:13px;font-weight:700;color:#f8fafc;margin-top:2px;">Nama Tenaga Medis</div>
                    <div id="modalFotoPeran" style="font-size:11.5px;color:#cbd5e1;margin-top:1px;">Spesialisasi &amp; Poli</div>
                </div>
                <button type="button" onclick="tutupModalGantiFoto()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;line-height:1;">✕</button>
            </div>

            <form method="POST" id="formModalGantiFoto" enctype="multipart/form-data">
                <input type="hidden" name="action" value="ganti_foto">
                <input type="hidden" name="id" id="modalFotoId" value="0">
                <input type="hidden" name="type" id="modalFotoType" value="dokter">
                <input type="hidden" name="foto_cropped_modal" id="modalFotoCropped" value="">

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;color:#cbd5e1;">
                        Pilih Berkas Foto Baru (Opsional):
                    </label>
                    <input type="file" name="foto_cepat" id="modalFileInput" accept="image/jpeg,image/png,image/webp" class="filter-input" style="width:100%;font-size:12px;background:#1e293b;color:#fff;border-color:#334155;" onchange="handleModalFotoChange(this)">
                </div>

                <!-- KANVAS CROP KOTAK MOBILE DENGAN SUDUT SIKU & GRID 3x3 -->
                <div style="position:relative;background:#030712;border-radius:14px;overflow:hidden;border:1px solid #334155;margin-bottom:14px;">
                    <div id="cropContainerModal" style="width:100%;height:320px;cursor:grab;touch-action:none;display:flex;align-items:center;justify-content:center;position:relative;">
                        <canvas id="canvasCropModal" width="380" height="320" style="width:100%;height:100%;display:block;"></canvas>
                        <span id="modalCropPlaceholder" style="font-size:48px;position:absolute;display:none;color:#64748b;">🩺</span>
                    </div>
                    <div style="position:absolute;top:8px;left:0;right:0;text-align:center;font-size:10.5px;color:rgba(255,255,255,0.7);pointer-events:none;background:rgba(0,0,0,0.3);padding:2px 0;">
                        💡 Geser foto langsung / scroll mouse untuk zoom
                    </div>
                </div>

                <!-- ACTION BAR GAYA MOBILE (BATAL - RESET - SELESAI) -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding-top:4px;">
                    <button type="button" onclick="tutupModalGantiFoto()" style="background:none;border:none;color:#94a3b8;font-size:14px;font-weight:700;cursor:pointer;padding:8px 14px;">
                        Batal
                    </button>
                    <button type="button" onclick="modalCropper.reset()" style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;width:38px;height:38px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;" title="Reset Posisi">
                        🔄
                    </button>
                    <button type="submit" style="background:#10b981;color:#fff;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(16,185,129,0.35);">
                        ✓ Selesai
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="admin-filter-bar">
        <div class="admin-filter-group">
            <span style="font-size:12px;font-weight:700;color:var(--muted);">Filter Jenis:</span>
            <a href="dokter.php" class="btn btn-sm <?= $filterJenis === 'semua' ? '' : 'btn-light' ?>">Semua</a>
            <a href="dokter.php?jenis=dokter" class="btn btn-sm <?= $filterJenis === 'dokter' ? '' : 'btn-light' ?>">Dokter</a>
            <a href="dokter.php?jenis=bidan" class="btn btn-sm <?= $filterJenis === 'bidan' ? '' : 'btn-light' ?>">Bidan</a>
        </div>
        <div>
            <span style="font-size:12px;color:var(--muted);font-weight:600;">
                Total Tenaga: <strong><?= count($listStaff) ?></strong> orang
            </span>
        </div>
    </div>

    <!-- TABEL DATA TENAGA KESEHATAN -->
    <section class="admin-card" style="padding:0;overflow:hidden;">
        <div class="admin-table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:75px;">Foto</th>
                        <th>Nama &amp; Gelar</th>
                        <th>Jenis</th>
                        <th>Poli</th>
                        <th>Spesialisasi / Peran</th>
                        <th>No. Telepon</th>
                        <th>Status</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listStaff)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:30px;color:var(--muted);">
                                Belum ada data tenaga medis sesuai filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listStaff as $s): 
                            $status = $s['status'] ?? 'aktif';
                            $badge = 'badge-success';
                            if ($status === 'cuti') $badge = 'badge-warning';
                            elseif ($status === 'nonaktif') $badge = 'badge-gray';

                            $photoUrl = resolveDokterFoto($s['foto'] ?? '', '../');
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($photoUrl)): ?>
                                    <img src="<?= adminEsc($photoUrl) ?>" alt="Foto" style="width:42px;height:42px;border-radius:50%;object-fit:cover;display:block;border:1px solid var(--hanania-border);" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div style="width:42px;height:42px;border-radius:50%;background:var(--hanania-purple-soft);display:none;align-items:center;justify-content:center;font-size:18px;">
                                        <?= $s['jenis'] === 'Dokter' ? '👨‍⚕️' : '👩‍⚕️' ?>
                                    </div>
                                <?php else: ?>
                                    <div style="width:42px;height:42px;border-radius:50%;background:var(--hanania-purple-soft);display:flex;align-items:center;justify-content:center;font-size:18px;">
                                        <?= $s['jenis'] === 'Dokter' ? '👨‍⚕️' : '👩‍⚕️' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--hanania-primary);font-size:14px;"><?= adminEsc($s['nama']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $s['jenis'] === 'Dokter' ? 'badge-purple' : 'badge-pink' ?>"><?= adminEsc($s['jenis']) ?></span>
                            </td>
                            <td>
                                <strong><?= adminEsc($s['nama_poli']) ?></strong>
                            </td>
                            <td>
                                <span style="color:var(--muted);"><?= adminEsc($s['spesialisasi'] ?: '-') ?></span>
                            </td>
                            <td>
                                <?= !empty($s['no_telepon']) ? adminEsc($s['no_telepon']) : '-' ?>
                            </td>
                            <td>
                                <span class="badge <?= $badge ?>"><?= adminEsc(ucfirst($status)) ?></span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <?php 
                                        $staffJson = htmlspecialchars(json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                        $escPhoto = htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" class="btn btn-sm btn-light btn-edit-staff" 
                                            data-staff='<?= $staffJson ?>'
                                            data-photo="<?= $escPhoto ?>"
                                            title="Edit Tenaga Medis">
                                        ✏️ Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-foto-staff" 
                                            style="background:var(--hanania-purple-soft);color:var(--hanania-primary);font-weight:700;padding:6px 10px;"
                                            data-staff='<?= $staffJson ?>'
                                            data-photo="<?= $escPhoto ?>"
                                            title="Ganti Foto">
                                        📷 Foto
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data tenaga medis <?= htmlspecialchars(addslashes($s['nama']), ENT_QUOTES, 'UTF-8') ?>?');" style="display:inline;">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                        <input type="hidden" name="type" value="<?= strtolower($s['jenis']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
// PENGATUR POSISI & CROP FOTO PROFIL MOBILE-STYLE (BINGKAI KOTAK + CORNER BRACKETS + GRID 3x3 + DRAG & PINCH/WHEEL ZOOM)
function createProfileCropper(cfg) {
    const state = {
        img: null,
        zoom: 1.0,
        offsetX: 0,
        offsetY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,
        hasInteracted: false,
        hasNewFile: false,
        initialPinchDistance: null
    };

    const container = document.getElementById(cfg.containerId);
    const canvas = document.getElementById(cfg.canvasId);

    // Hitung dimensi kotak crop persegi panjang vertikal (portrait)
    function getCropBox() {
        const cw = canvas ? canvas.width : 300;
        const ch = canvas ? canvas.height : 300;
        // Rasio portrait ~ 1 : 1.18 (ideal untuk kartu dokter beranda & jadwal)
        const boxH = Math.round(ch * 0.82);
        const boxW = Math.round(boxH * 0.82);
        const boxX = Math.round((cw - boxW) / 2);
        const boxY = Math.round((ch - boxH) / 2);
        return { boxX, boxY, boxW, boxH, cw, ch };
    }

    function redraw() {
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const { boxX, boxY, boxW, boxH, cw, ch } = getCropBox();
        ctx.clearRect(0, 0, cw, ch);

        if (state.img) {
            const iw = state.img.naturalWidth || state.img.width;
            const ih = state.img.naturalHeight || state.img.height;

            if (iw && ih) {
                // Skala minimum agar gambar menutupi kotak crop
                const baseScale = Math.max(boxW / iw, boxH / ih);
                const scale = baseScale * state.zoom;
                const drawW = iw * scale;
                const drawH = ih * scale;
                const drawX = boxX + (boxW - drawW) / 2 + state.offsetX;
                const drawY = boxY + (boxH - drawH) / 2 + state.offsetY;

                ctx.save();
                ctx.drawImage(state.img, drawX, drawY, drawW, drawH);
                ctx.restore();
            }
        }

        // 1. Overlay gelap di luar kotak crop
        ctx.fillStyle = 'rgba(0, 0, 0, 0.68)';
        ctx.fillRect(0, 0, cw, boxY); // atas
        ctx.fillRect(0, boxY + boxH, cw, ch - (boxY + boxH)); // bawah
        ctx.fillRect(0, boxY, boxX, boxH); // kiri
        ctx.fillRect(boxX + boxW, boxY, cw - (boxX + boxW), boxH); // kanan

        // 2. Border halus kotak crop
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
        ctx.lineWidth = 1;
        ctx.strokeRect(boxX, boxY, boxW, boxH);

        // 3. Garis kisi 3x3 (Rule of Thirds)
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
        ctx.lineWidth = 0.8;
        ctx.beginPath();
        // Garis vertikal
        ctx.moveTo(boxX + boxW / 3, boxY);
        ctx.lineTo(boxX + boxW / 3, boxY + boxH);
        ctx.moveTo(boxX + (boxW * 2) / 3, boxY);
        ctx.lineTo(boxX + (boxW * 2) / 3, boxY + boxH);
        // Garis horizontal
        ctx.moveTo(boxX, boxY + boxH / 3);
        ctx.lineTo(boxX + boxW, boxY + boxH / 3);
        ctx.moveTo(boxX, boxY + (boxH * 2) / 3);
        ctx.lineTo(boxX + boxW, boxY + (boxH * 2) / 3);
        ctx.stroke();

        // 4. Sudut siku putih khas alat crop mobile (Corner brackets ┌ ┐ └ ┘)
        const cLen = 22; // Panjang lengan siku
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Sudut kiri atas ┌
        ctx.beginPath();
        ctx.moveTo(boxX, boxY + cLen);
        ctx.lineTo(boxX, boxY);
        ctx.lineTo(boxX + cLen, boxY);
        ctx.stroke();

        // Sudut kanan atas ┐
        ctx.beginPath();
        ctx.moveTo(boxX + boxW - cLen, boxY);
        ctx.lineTo(boxX + boxW, boxY);
        ctx.lineTo(boxX + boxW, boxY + cLen);
        ctx.stroke();

        // Sudut kiri bawah └
        ctx.beginPath();
        ctx.moveTo(boxX, boxY + boxH - cLen);
        ctx.lineTo(boxX, boxY + boxH);
        ctx.lineTo(boxX + cLen, boxY + boxH);
        ctx.stroke();

        // Sudut kanan bawah ┘
        ctx.beginPath();
        ctx.moveTo(boxX + boxW - cLen, boxY + boxH);
        ctx.lineTo(boxX + boxW, boxY + boxH);
        ctx.lineTo(boxX + boxW, boxY + boxH - cLen);
        ctx.stroke();
    }

    if (container) {
        // Drag dengan Mouse
        container.addEventListener('mousedown', function(e) {
            if (!state.img) return;
            state.isDragging = true;
            state.hasInteracted = true;
            state.startX = e.clientX - state.offsetX;
            state.startY = e.clientY - state.offsetY;
            container.style.cursor = 'grabbing';
            e.preventDefault();
        });

        window.addEventListener('mousemove', function(e) {
            if (!state.isDragging) return;
            state.offsetX = e.clientX - state.startX;
            state.offsetY = e.clientY - state.startY;
            redraw();
        });

        window.addEventListener('mouseup', function() {
            if (state.isDragging) {
                state.isDragging = false;
                container.style.cursor = 'grab';
            }
        });

        // Zoom dengan Scroll Mouse
        container.addEventListener('wheel', function(e) {
            if (!state.img) return;
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.08 : -0.08;
            state.zoom = Math.max(1.0, Math.min(3.5, state.zoom + delta));
            state.hasInteracted = true;
            redraw();
        }, { passive: false });

        // Touch support (Drag & Pinch Zoom pada Layar Sentuh)
        container.addEventListener('touchstart', function(e) {
            if (!state.img) return;
            if (e.touches.length === 1) {
                state.isDragging = true;
                state.hasInteracted = true;
                state.startX = e.touches[0].clientX - state.offsetX;
                state.startY = e.touches[0].clientY - state.offsetY;
            } else if (e.touches.length === 2) {
                state.isDragging = false;
                state.initialPinchDistance = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
            }
        }, { passive: true });

        window.addEventListener('touchmove', function(e) {
            if (!state.img) return;
            if (state.isDragging && e.touches.length === 1) {
                state.offsetX = e.touches[0].clientX - state.startX;
                state.offsetY = e.touches[0].clientY - state.startY;
                redraw();
            } else if (e.touches.length === 2 && state.initialPinchDistance) {
                const dist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                const factor = dist / state.initialPinchDistance;
                state.zoom = Math.max(1.0, Math.min(3.5, state.zoom * factor));
                state.initialPinchDistance = dist;
                state.hasInteracted = true;
                redraw();
            }
        }, { passive: true });

        window.addEventListener('touchend', function() {
            state.isDragging = false;
            state.initialPinchDistance = null;
        });
    }

    return {
        loadImage: function(src, isNewFile = false, cb) {
            if (!src) {
                state.img = null;
                state.hasNewFile = false;
                redraw();
                return;
            }
            const img = new Image();
            // Hanya gunakan anonymous jika src berasal dari host eksternal
            if (/^https?:\/\//i.test(src) && !src.includes(window.location.host)) {
                img.crossOrigin = 'anonymous';
            }
            img.onload = function() {
                state.img = img;
                state.zoom = 1.0;
                state.offsetX = 0;
                state.offsetY = 0;
                state.hasInteracted = false;
                state.hasNewFile = isNewFile;
                redraw();
                if (cb) cb();
            };
            img.onerror = function() {
                console.warn('Gagal memuat gambar:', src);
                state.img = null;
                redraw();
            };
            img.src = src;
        },
        reset: function() {
            state.zoom = 1.0;
            state.offsetX = 0;
            state.offsetY = 0;
            redraw();
        },
        adjustZoom: function(delta) {
            state.zoom = Math.max(1.0, Math.min(3.5, state.zoom + delta));
            state.hasInteracted = true;
            redraw();
        },
        getExportBase64: function(targetW = 450) {
            try {
                if (!state.img || !canvas) return null;
                const { boxX, boxY, boxW, boxH } = getCropBox();
                const targetH = Math.round(targetW * (boxH / boxW));

                const off = document.createElement('canvas');
                off.width = targetW;
                off.height = targetH;
                const ctx = off.getContext('2d');

                const iw = state.img.naturalWidth || state.img.width;
                const ih = state.img.naturalHeight || state.img.height;
                if (!iw || !ih) return null;

                const baseScale = Math.max(boxW / iw, boxH / ih);
                const scale = baseScale * state.zoom;
                const drawW = iw * scale;
                const drawH = ih * scale;
                const drawX = boxX + (boxW - drawW) / 2 + state.offsetX;
                const drawY = boxY + (boxH - drawH) / 2 + state.offsetY;

                // Skalakan ke target output
                const factor = targetW / boxW;
                const outDrawX = (drawX - boxX) * factor;
                const outDrawY = (drawY - boxY) * factor;
                const outDrawW = drawW * factor;
                const outDrawH = drawH * factor;

                ctx.drawImage(state.img, outDrawX, outDrawY, outDrawW, outDrawH);
                return off.toDataURL('image/jpeg', 0.92);
            } catch (err) {
                console.warn('Gagal mengekspor kanvas foto:', err);
                return null;
            }
        },
        hasImage: function() {
            return !!state.img;
        },
        shouldExport: function() {
            return !!state.img && (state.hasInteracted || state.hasNewFile);
        },
        redraw: redraw
    };
}

// Inisialisasi Instance Cropper untuk Form dan Modal
const formCropper = createProfileCropper({
    containerId: 'cropContainerForm',
    canvasId: 'canvasCropForm'
});

const modalCropper = createProfileCropper({
    containerId: 'cropContainerModal',
    canvasId: 'canvasCropModal'
});

// FORM TAMBAH / EDIT
function toggleFormTambah() {
    const card = document.getElementById('cardFormTenaga');
    const form = document.getElementById('formTenaga');
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') {
        document.getElementById('formTitle').innerHTML = '<span>➕</span> Tambah Tenaga Kesehatan Baru';
        document.getElementById('formAction').value = 'tambah';
        document.getElementById('fieldId').value = '0';
        document.getElementById('btnSubmitForm').textContent = 'Simpan Data';
        document.getElementById('fieldFoto').value = '';
        document.getElementById('fieldFotoCropped').value = '';
        document.getElementById('wrapHapusFoto').style.display = 'none';
        document.getElementById('checkHapusFoto').checked = false;
        
        // Aktifkan kembali jenis tenaga medis
        const typeSelect = document.getElementById('fieldType');
        typeSelect.disabled = false;
        const hiddenType = document.getElementById('fieldHiddenType');
        if (hiddenType) hiddenType.remove();

        // Reset preview & cropper
        document.getElementById('imgPreviewFoto').style.display = 'none';
        document.getElementById('iconPreviewFoto').style.display = 'inline';
        document.getElementById('panelCropForm').style.display = 'none';
        formCropper.loadImage(null);
        
        form.reset();
        toggleSpesialisasiField();
        card.scrollIntoView({ behavior: 'smooth' });
    }
}

function bukaEdit(data, photoUrl) {
    const card = document.getElementById('cardFormTenaga');
    card.style.display = 'block';
    document.getElementById('formTitle').innerHTML = '<span>✏️</span> Edit Tenaga Kesehatan: ' + (data.nama || '');
    document.getElementById('formAction').value = 'edit';
    document.getElementById('fieldId').value = data.id;
    
    // Kunci jenis agar tidak bertabrakan dengan ID tabel lain
    const typeSelect = document.getElementById('fieldType');
    typeSelect.value = (data.jenis || 'dokter').toLowerCase();
    typeSelect.disabled = true;

    let hiddenType = document.getElementById('fieldHiddenType');
    if (!hiddenType) {
        hiddenType = document.createElement('input');
        hiddenType.type = 'hidden';
        hiddenType.name = 'type';
        hiddenType.id = 'fieldHiddenType';
        document.getElementById('formTenaga').appendChild(hiddenType);
    }
    hiddenType.value = (data.jenis || 'dokter').toLowerCase();

    document.getElementById('fieldNama').value = data.nama || '';
    document.getElementById('fieldPoli').value = data.id_poli || 0;
    document.getElementById('fieldSpesialisasi').value = data.spesialisasi || '';
    document.getElementById('fieldTelepon').value = data.no_telepon || '';
    document.getElementById('fieldStatus').value = data.status || 'aktif';
    document.getElementById('btnSubmitForm').textContent = 'Perbarui Data';
    
    // Atur Pratinjau Foto
    document.getElementById('fieldFoto').value = '';
    document.getElementById('fieldFotoCropped').value = '';
    const imgEl = document.getElementById('imgPreviewFoto');
    const iconEl = document.getElementById('iconPreviewFoto');
    const wrapHapus = document.getElementById('wrapHapusFoto');
    document.getElementById('checkHapusFoto').checked = false;

    if (photoUrl && photoUrl.trim() !== '') {
        imgEl.src = photoUrl;
        imgEl.style.display = 'block';
        iconEl.style.display = 'none';
        wrapHapus.style.display = 'block';
        document.getElementById('panelCropForm').style.display = 'block';
        formCropper.loadImage(photoUrl, false);
    } else {
        imgEl.style.display = 'none';
        iconEl.style.display = 'inline';
        iconEl.textContent = (data.jenis || 'Dokter').toLowerCase() === 'dokter' ? '👨‍⚕️' : '👩‍⚕️';
        wrapHapus.style.display = 'none';
        document.getElementById('panelCropForm').style.display = 'none';
        formCropper.loadImage(null);
    }

    toggleSpesialisasiField();
    card.scrollIntoView({ behavior: 'smooth' });
}

function tutupForm() {
    document.getElementById('cardFormTenaga').style.display = 'none';
    const typeSelect = document.getElementById('fieldType');
    if (typeSelect) typeSelect.disabled = false;
    const hiddenType = document.getElementById('fieldHiddenType');
    if (hiddenType) hiddenType.remove();
}

function toggleSpesialisasiField() {
    const typeSelect = document.getElementById('fieldType');
    const type = typeSelect ? typeSelect.value : 'dokter';
    const wrap = document.getElementById('wrapSpesialisasi');
    if (type === 'bidan') {
        wrap.style.display = 'none';
    } else {
        wrap.style.display = 'flex';
    }
}

function handleFormFotoChange(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imgEl = document.getElementById('imgPreviewFoto');
            const iconEl = document.getElementById('iconPreviewFoto');
            imgEl.src = e.target.result;
            imgEl.style.display = 'block';
            iconEl.style.display = 'none';

            // Tampilkan panel atur posisi dan muat ke kanvas cropper
            document.getElementById('panelCropForm').style.display = 'block';
            formCropper.loadImage(e.target.result, true);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Hook submit form tenaga untuk menyematkan cropped base64
document.getElementById('formTenaga').addEventListener('submit', function() {
    try {
        if (formCropper.shouldExport()) {
            const b64 = formCropper.getExportBase64(500);
            if (b64) {
                document.getElementById('fieldFotoCropped').value = b64;
            }
        }
    } catch (err) {
        console.warn('Gagal menyiapkan crop foto form:', err);
    }
});

// QUICK PHOTO MODAL
function bukaModalGantiFoto(data, photoUrl) {
    const modal = document.getElementById('modalGantiFoto');
    document.getElementById('modalFotoId').value = data.id;
    document.getElementById('modalFotoType').value = (data.jenis || 'dokter').toLowerCase();
    document.getElementById('modalFotoNama').textContent = data.nama;
    
    const elPeran = document.getElementById('modalFotoPeran');
    if (elPeran) {
        elPeran.textContent = (data.spesialisasi || data.jenis || 'Tenaga Medis') + ' • ' + (data.nama_poli || 'Poli Pelayanan');
    }
    
    document.getElementById('modalFileInput').value = '';
    document.getElementById('modalFotoCropped').value = '';

    const placeholder = document.getElementById('modalCropPlaceholder');
    if (photoUrl && photoUrl.trim() !== '') {
        if (placeholder) placeholder.style.display = 'none';
        modalCropper.loadImage(photoUrl, false);
    } else {
        if (placeholder) {
            placeholder.style.display = 'block';
            placeholder.textContent = (data.jenis || 'Dokter').toLowerCase() === 'dokter' ? '👨‍⚕️' : '👩‍⚕️';
        }
        modalCropper.loadImage(null);
    }

    modal.style.display = 'flex';
}

function tutupModalGantiFoto() {
    document.getElementById('modalGantiFoto').style.display = 'none';
}

function handleModalFotoChange(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const placeholder = document.getElementById('modalCropPlaceholder');
            if (placeholder) placeholder.style.display = 'none';
            modalCropper.loadImage(e.target.result, true);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Hook submit modal foto untuk menyematkan cropped base64
document.getElementById('formModalGantiFoto').addEventListener('submit', function(e) {
    try {
        if (modalCropper.hasImage()) {
            const b64 = modalCropper.getExportBase64(500);
            if (b64) {
                document.getElementById('modalFotoCropped').value = b64;
            }
        }
    } catch (err) {
        console.warn('Gagal menyiapkan crop foto modal:', err);
    }
});

// Event listener delegasi untuk tombol Edit dan Foto
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-staff').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.getAttribute('data-staff'));
                const photoUrl = this.getAttribute('data-photo') || '';
                bukaEdit(data, photoUrl);
            } catch (err) {
                console.error('Gagal parsing data staff edit:', err);
            }
        });
    });

    document.querySelectorAll('.btn-foto-staff').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.getAttribute('data-staff'));
                const photoUrl = this.getAttribute('data-photo') || '';
                bukaModalGantiFoto(data, photoUrl);
            } catch (err) {
                console.error('Gagal parsing data staff foto:', err);
            }
        });
    });
});
</script>
<?php adminPageEnd(); ?>
