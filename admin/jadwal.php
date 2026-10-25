<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// Ambil pesan flash session jika ada
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// Filter hari, jenis, dan poli
$filterHari = trim($_GET['hari'] ?? 'semua');
$filterJenis = trim($_GET['jenis'] ?? 'semua');
$filterPoli = (int)($_GET['poli'] ?? 0);

// Ambil daftar poli
$daftarPoli = [];
$resPoli = $db->query("SELECT id_poli, nama_poli FROM poli ORDER BY id_poli ASC");
if ($resPoli) {
    while ($row = $resPoli->fetch_assoc()) {
        $daftarPoli[] = $row;
    }
}

// Ambil daftar dokter aktif (urutan konsisten DOKTER terlebih dahulu)
$daftarDokter = [];
$qD = $db->query("SELECT id_dokter, nama_dokter, COALESCE(id_poli, 0) AS id_poli FROM dokter WHERE status='aktif' ORDER BY nama_dokter ASC");
if ($qD) while ($row = $qD->fetch_assoc()) $daftarDokter[] = $row;

// Ambil daftar bidan aktif (Bu Gita Marissa pertama di antara bidan)
$daftarBidan = [];
$qB = $db->query("SELECT id_bidan, nama_bidan, COALESCE(id_poli, 0) AS id_poli FROM bidan WHERE status='aktif' ORDER BY CASE WHEN nama_bidan LIKE '%Gita%' THEN 0 ELSE 1 END, nama_bidan ASC");
if ($qB) while ($row = $qB->fetch_assoc()) $daftarBidan[] = $row;

$daftarHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

// QUICK TOGGLE STATUS JADWAL TENAGA MEDIS (Buka / Tutup / Full / Cuti)
if (isset($_GET['toggle_status_id'])) {
    $idToggle = (int)$_GET['toggle_status_id'];
    $typeToggle = strtolower(trim($_GET['type'] ?? 'dokter'));
    $modeToggle = strtolower(trim($_GET['mode'] ?? 'tersedia'));
    $allowedModes = ['tersedia', 'ditutup', 'full_booking', 'cuti'];
    if (!in_array($modeToggle, $allowedModes, true)) $modeToggle = 'tersedia';

    $table = ($typeToggle === 'bidan') ? 'jadwal_bidan' : 'jadwal_dokter';
    $colId = ($typeToggle === 'bidan') ? 'id_jadwal_bidan' : 'id_jadwal_dokter';

    $stmtToggle = $db->prepare("UPDATE $table SET status = ? WHERE $colId = ?");
    if ($stmtToggle) {
        $stmtToggle->bind_param('si', $modeToggle, $idToggle);
        $stmtToggle->execute();
        $stmtToggle->close();

        $labels = [
            'tersedia' => '🟢 Pendaftaran Tersedia (Buka)',
            'ditutup' => '⛔ Pendaftaran Ditutup (Tutup)',
            'full_booking' => '🟡 Full Booking (Penuh)',
            'cuti' => '🏖️ Cuti / Libur Praktik'
        ];
        $_SESSION['flash_msg'] = "Status jadwal berhasil diubah menjadi: " . ($labels[$modeToggle] ?? $modeToggle);
        $_SESSION['flash_type'] = 'success';
    }

    $redirParams = $_GET;
    unset($redirParams['toggle_status_id'], $redirParams['type'], $redirParams['mode']);
    $url = 'jadwal.php' . (!empty($redirParams) ? '?' . http_build_query($redirParams) : '');
    header("Location: $url");
    exit;
}

// PROSES POST TAMBAH, EDIT, HAPUS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'tambah') {
            $staffRaw = trim($_POST['staff'] ?? ''); // format: "dokter-1" atau "bidan-2"
            $parts = explode('-', $staffRaw);
            $type = $parts[0] ?? 'dokter';
            $staffId = (int)($parts[1] ?? 0);
            $idPoli = (int)($_POST['id_poli'] ?? 0);
            $jamMulai = trim($_POST['jam_mulai'] ?? '');
            $jamSelesai = trim($_POST['jam_selesai'] ?? '');
            $statusRaw = $_POST['status'] ?? 'tersedia';
            $status = in_array($statusRaw, ['tersedia', 'ditutup', 'full_booking', 'cuti', 'tidak_tersedia', 'nonaktif'], true) ? $statusRaw : 'tersedia';
            if ($status === 'nonaktif' || $status === 'tidak_tersedia') $status = 'ditutup';

            // Ambil multi-hari
            $hariPost = $_POST['hari'] ?? [];
            if (!is_array($hariPost)) {
                $hariPost = [$hariPost];
            }
            $hariDipilih = array_values(array_intersect($hariPost, $daftarHari));

            if ($staffId <= 0) {
                throw new Exception('Pilih tenaga kesehatan terlebih dahulu.');
            }
            if ($idPoli <= 0) {
                throw new Exception('Pilih poli pelayanan terlebih dahulu.');
            }
            if (empty($hariDipilih)) {
                throw new Exception('Pilih minimal satu hari praktik.');
            }
            if ($jamMulai === '' || $jamSelesai === '') {
                throw new Exception('Jam mulai dan jam selesai wajib diisi.');
            }
            if ($jamMulai >= $jamSelesai) {
                throw new Exception('Jam mulai harus lebih awal dari jam selesai.');
            }

            // Normalisasi format jam HH:MM:00
            if (strlen($jamMulai) === 5) $jamMulai .= ':00';
            if (strlen($jamSelesai) === 5) $jamSelesai .= ':00';

            $suksesCount = 0;
            if ($type === 'dokter') {
                $stmt = $db->prepare("INSERT INTO jadwal_dokter (id_dokter, id_poli, hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($hariDipilih as $h) {
                    $stmt->bind_param('iissss', $staffId, $idPoli, $h, $jamMulai, $jamSelesai, $status);
                    if ($stmt->execute()) $suksesCount++;
                }
                $stmt->close();
            } else {
                $stmt = $db->prepare("INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($hariDipilih as $h) {
                    $stmt->bind_param('iissss', $staffId, $idPoli, $h, $jamMulai, $jamSelesai, $status);
                    if ($stmt->execute()) $suksesCount++;
                }
                $stmt->close();
            }

            $message = "Berhasil menambahkan $suksesCount sesi jadwal praktik.";
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $type = trim($_POST['type'] ?? 'dokter');
            $staffRaw = trim($_POST['staff'] ?? '');
            $parts = explode('-', $staffRaw);
            $staffId = (int)($parts[1] ?? 0);
            $idPoli = (int)($_POST['id_poli'] ?? 0);
            $jamMulai = trim($_POST['jam_mulai'] ?? '');
            $jamSelesai = trim($_POST['jam_selesai'] ?? '');
            $statusRaw = $_POST['status'] ?? 'tersedia';
            $status = in_array($statusRaw, ['tersedia', 'ditutup', 'full_booking', 'cuti', 'tidak_tersedia', 'nonaktif'], true) ? $statusRaw : 'tersedia';
            if ($status === 'nonaktif' || $status === 'tidak_tersedia') $status = 'ditutup';

            // Ambil hari
            $hariPost = $_POST['hari'] ?? [];
            if (!is_array($hariPost)) {
                $hariPost = [$hariPost];
            }
            $hariDipilih = array_values(array_intersect($hariPost, $daftarHari));

            if ($id <= 0) {
                throw new Exception('ID jadwal tidak valid.');
            }
            if ($staffId <= 0) {
                throw new Exception('Tenaga kesehatan tidak valid.');
            }
            if ($idPoli <= 0) {
                throw new Exception('Pilih poli pelayanan terlebih dahulu.');
            }
            if (empty($hariDipilih)) {
                throw new Exception('Pilih minimal satu hari praktik.');
            }
            if ($jamMulai === '' || $jamSelesai === '') {
                throw new Exception('Jam mulai dan jam selesai wajib diisi.');
            }
            if ($jamMulai >= $jamSelesai) {
                throw new Exception('Jam mulai harus lebih awal dari jam selesai.');
            }

            if (strlen($jamMulai) === 5) $jamMulai .= ':00';
            if (strlen($jamSelesai) === 5) $jamSelesai .= ':00';

            // Update sesi pertama ke hari terpilih pertama
            $hariUtama = $hariDipilih[0];
            if ($type === 'dokter') {
                $stmt = $db->prepare("UPDATE jadwal_dokter SET id_dokter = ?, id_poli = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, status = ? WHERE id_jadwal_dokter = ?");
                $stmt->bind_param('iissssi', $staffId, $idPoli, $hariUtama, $jamMulai, $jamSelesai, $status, $id);
                $stmt->execute();
                $stmt->close();

                // Jika dicentang lebih dari 1 hari saat edit, buatkan sesi baru untuk hari sisanya
                if (count($hariDipilih) > 1) {
                    $stmtExtra = $db->prepare("INSERT INTO jadwal_dokter (id_dokter, id_poli, hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($k = 1; $k < count($hariDipilih); $k++) {
                        $stmtExtra->bind_param('iissss', $staffId, $idPoli, $hariDipilih[$k], $jamMulai, $jamSelesai, $status);
                        $stmtExtra->execute();
                    }
                    $stmtExtra->close();
                }
            } else {
                $stmt = $db->prepare("UPDATE jadwal_bidan SET id_bidan = ?, id_poli = ?, hari = ?, jam_mulai = ?, jam_selesai = ?, status = ? WHERE id_jadwal_bidan = ?");
                $stmt->bind_param('iissssi', $staffId, $idPoli, $hariUtama, $jamMulai, $jamSelesai, $status, $id);
                $stmt->execute();
                $stmt->close();

                if (count($hariDipilih) > 1) {
                    $stmtExtra = $db->prepare("INSERT INTO jadwal_bidan (id_bidan, id_poli, hari, jam_mulai, jam_selesai, status) VALUES (?, ?, ?, ?, ?, ?)");
                    for ($k = 1; $k < count($hariDipilih); $k++) {
                        $stmtExtra->bind_param('iissss', $staffId, $idPoli, $hariDipilih[$k], $jamMulai, $jamSelesai, $status);
                        $stmtExtra->execute();
                    }
                    $stmtExtra->close();
                }
            }

            $message = "Jadwal praktik berhasil diperbarui.";
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            $type = trim($_POST['type'] ?? 'dokter');

            if ($id <= 0) {
                throw new Exception('ID jadwal tidak valid.');
            }

            if ($type === 'dokter') {
                $stmt = $db->prepare("DELETE FROM jadwal_dokter WHERE id_jadwal_dokter = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $message = "Jadwal dokter berhasil dihapus.";
            } else {
                $stmt = $db->prepare("DELETE FROM jadwal_bidan WHERE id_jadwal_bidan = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $message = "Jadwal bidan berhasil dihapus.";
            }
        }

        if ($message !== '') {
            $_SESSION['flash_msg'] = $message;
            $_SESSION['flash_type'] = $messageType;
            $redir = 'jadwal.php?' . ($filterHari !== 'semua' ? 'hari=' . urlencode($filterHari) . '&' : '') . ($filterJenis !== 'semua' ? 'jenis=' . urlencode($filterJenis) : '');
            header('Location: ' . rtrim($redir, '&?'));
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_msg'] = $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        $redir = 'jadwal.php?' . ($filterHari !== 'semua' ? 'hari=' . urlencode($filterHari) . '&' : '') . ($filterJenis !== 'semua' ? 'jenis=' . urlencode($filterJenis) : '');
        header('Location: ' . rtrim($redir, '&?'));
        exit;
    }
}

// Query seluruh jadwal (DOKTER terlebih dahulu, kemudian BIDAN dengan Bu Gita di urutan pertama)
$jadwalList = [];
$qJ = $db->query("
    SELECT jd.id_jadwal_dokter AS id, jd.id_dokter AS staff_id, jd.id_poli, jd.hari, jd.jam_mulai, jd.jam_selesai, jd.status,
           d.nama_dokter AS nama_tenaga, d.foto, p.nama_poli, 'Dokter' AS jenis
    FROM jadwal_dokter jd
    JOIN dokter d ON d.id_dokter = jd.id_dokter
    JOIN poli p ON p.id_poli = jd.id_poli
    UNION ALL
    SELECT jb.id_jadwal_bidan AS id, jb.id_bidan AS staff_id, jb.id_poli, jb.hari, jb.jam_mulai, jb.jam_selesai, jb.status,
           b.nama_bidan AS nama_tenaga, b.foto, p.nama_poli, 'Bidan' AS jenis
    FROM jadwal_bidan jb
    JOIN bidan b ON b.id_bidan = jb.id_bidan
    JOIN poli p ON p.id_poli = jb.id_poli
    ORDER BY FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), FIELD(jenis, 'Dokter', 'Bidan'), CASE WHEN jenis = 'Bidan' AND nama_tenaga LIKE '%Gita%' THEN 0 ELSE 1 END, jam_mulai ASC, nama_tenaga ASC
");
if ($qJ) {
    while ($row = $qJ->fetch_assoc()) {
        $jadwalList[] = $row;
    }
}

// Filter hari, jenis & poli untuk tampilan tabel
$jadwalTableFiltered = $jadwalList;
if ($filterHari !== 'semua' && in_array($filterHari, $daftarHari, true)) {
    $jadwalTableFiltered = array_filter($jadwalTableFiltered, fn($x) => $x['hari'] === $filterHari);
}
if ($filterJenis === 'dokter') {
    $jadwalTableFiltered = array_filter($jadwalTableFiltered, fn($x) => $x['jenis'] === 'Dokter');
} elseif ($filterJenis === 'bidan') {
    $jadwalTableFiltered = array_filter($jadwalTableFiltered, fn($x) => $x['jenis'] === 'Bidan');
}
if ($filterPoli > 0) {
    $jadwalTableFiltered = array_filter($jadwalTableFiltered, fn($x) => (int)$x['id_poli'] === $filterPoli);
}

adminPageStart('Kelola Jadwal Praktik', 'jadwal');
?>

<main class="admin-page">
    <header class="admin-header">
        <div>
            <h1><span>📅</span> Pengelolaan Jadwal Praktik</h1>
            <p>Kelola jadwal praktik harian dokter dan bidan, jam pelayanan, poli, dan status ketersediaan dengan mudah.</p>
        </div>
        <div class="admin-header-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <a href="layanan_pendaftaran.php?tab=poli" class="btn btn-sm btn-light" style="display:inline-flex;align-items:center;gap:6px;font-weight:700;">
                <span>🏥</span> Status Operasional Poli
            </a>
            <button type="button" class="btn" onclick="toggleFormJadwal()">
                <span>➕</span> Tambah Jadwal Baru
            </button>
        </div>
    </header>

    <!-- BANNER PEMBERITAHUAN ATURAN CUTOFF & 24 JAM -->
    <div style="background:#FAF8FD;border:1.5px solid #E8DEF5;border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:20px;">⏱️</span>
            <div style="font-size:12.5px;color:#5A4B70;line-height:1.5;">
                <strong>Aturan Pendaftaran Online:</strong> Pendaftaran poli reguler otomatis ditutup <strong>1 jam sebelum jadwal praktik berakhir</strong>. Persalinan 24 Jam siaga 24/7 tanpa tutup. 
                Untuk menutup poli darurat secara manual, kelola di menu <strong>Status Operasional Poli</strong>.
            </div>
        </div>
        <a href="layanan_pendaftaran.php?tab=poli" class="btn btn-sm" style="padding:6px 14px;font-size:12px;font-weight:800;background:var(--hanania-primary);color:#fff;white-space:nowrap;">
            Kelola Buka/Tutup Poli &rarr;
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>">
            <span><?= $messageType === 'danger' ? '⚠️' : '✅' ?></span> <?= adminEsc($message) ?>
        </div>
    <?php endif; ?>

    <!-- FORM TAMBAH / EDIT JADWAL -->
    <section class="admin-card" id="cardFormJadwal" style="display:none;margin-bottom:24px;">
        <div class="admin-card-head" style="margin-bottom:16px;">
            <div class="admin-card-title">
                <span id="formJadwalTitle"><span>➕</span> Tambah Jadwal Praktik Baru</span>
            </div>
            <button type="button" class="btn btn-sm btn-light" onclick="tutupFormJadwal()">✕ Tutup</button>
        </div>

        <form method="POST" id="formJadwal">
            <input type="hidden" name="action" id="jadwalAction" value="tambah">
            <input type="hidden" name="id" id="jadwalId" value="0">
            <input type="hidden" name="type" id="jadwalType" value="dokter">

            <div class="form-grid">
                <!-- 1. PILIH POLI PELAYANAN TERLEBIH DAHULU -->
                <div class="form-field">
                    <label>Poli Pelayanan *</label>
                    <select name="id_poli" id="fieldJadwalPoli" required onchange="onPoliChange(this.value)">
                        <option value="">-- Pilih Poli Pelayanan --</option>
                        <?php foreach ($daftarPoli as $p): ?>
                            <option value="<?= (int)$p['id_poli'] ?>"><?= adminEsc($p['nama_poli']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. PILIH TENAGA KESEHATAN (OTOMATIS DISARING SESUAI POLI) -->
                <div class="form-field" id="wrapStaffSelect">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <label style="margin:0;">Tenaga Medis (Dokter / Bidan) *</label>
                        <span id="staffPoliBadge" style="display:none;font-size:11px;font-weight:800;color:#15803D;background:#DCFCE7;padding:2px 8px;border-radius:6px;border:1px solid #86EFAC;"></span>
                    </div>
                    <select name="staff" id="fieldStaff" required style="margin-top:6px;">
                        <option value="">-- Pilih Poli Terlebih Dahulu --</option>
                    </select>
                    <small id="staffPoliHint" style="color:var(--hanania-muted);font-size:11.5px;display:block;margin-top:4px;">
                        Pilih poli pelayanan terlebih dahulu untuk memfilter dokter atau bidan yang bertugas.
                    </small>
                </div>

                <!-- PILIH HARI PRAKTIK -->
                <div class="form-field full" id="wrapHariMulti" style="background:#FAF8FD;border:1px dashed var(--hanania-primary);border-radius:12px;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                        <label style="font-weight:700;color:var(--hanania-primary);margin:0;font-size:13px;" id="lblHariPraktik">
                            🗓️ Pilih Hari Praktik * (Bisa pilih satu atau beberapa hari sekaligus)
                        </label>
                        <div style="display:flex;gap:6px;">
                            <button type="button" class="btn btn-sm btn-light" style="font-size:11px;padding:3px 8px;" onclick="pilihSemuaHari(true)">Pilih Semua</button>
                            <button type="button" class="btn btn-sm btn-light" style="font-size:11px;padding:3px 8px;" onclick="pilihHariKerja()">Senin-Jumat</button>
                            <button type="button" class="btn btn-sm btn-light" style="font-size:11px;padding:3px 8px;" onclick="pilihSemuaHari(false)">Reset</button>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <?php foreach ($daftarHari as $h): ?>
                            <label style="display:inline-flex;align-items:center;gap:6px;background:#FFFFFF;border:1px solid #E6D9F2;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:700;font-size:13px;user-select:none;">
                                <input type="checkbox" name="hari[]" value="<?= $h ?>" class="check-hari">
                                <span><?= $h ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small style="color:var(--hanania-muted);margin-top:8px;display:block;" id="hintHariPraktik">
                        💡 Jika mencentang beberapa hari (misal Senin & Rabu), sistem akan membuat sesi jadwal sekaligus untuk hari-hari tersebut.
                    </small>
                </div>

                <!-- JAM MULAI -->
                <div class="form-field">
                    <label>Jam Mulai Pelayanan *</label>
                    <input type="time" name="jam_mulai" id="fieldJamMulai" required value="08:00">
                </div>

                <!-- JAM SELESAI -->
                <div class="form-field">
                    <label>Jam Selesai Pelayanan *</label>
                    <input type="time" name="jam_selesai" id="fieldJamSelesai" required value="16:00">
                </div>

                <!-- STATUS KETERSEDIAAN -->
                <div class="form-field">
                    <label>Status Praktik &amp; Pendaftaran *</label>
                    <select name="status" id="fieldJadwalStatus" required>
                        <option value="tersedia">🟢 Pendaftaran Tersedia (Aktif Praktik)</option>
                        <option value="ditutup">⛔ Pendaftaran Ditutup</option>
                        <option value="full_booking">🟡 Full Booking (Kuota Penuh)</option>
                        <option value="cuti">🏖️ Cuti / Libur Praktik</option>
                    </select>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;display:flex;gap:10px;">
                <button type="submit" class="btn" id="btnSubmitJadwal">Simpan Jadwal</button>
                <button type="button" class="btn btn-light" onclick="tutupFormJadwal()">Batal</button>
            </div>
        </form>
    </section>

    <!-- TABEL DAFTAR JADWAL PRAKTIK -->
    <section class="admin-card" style="padding:0;overflow:hidden;" id="sectionTabelJadwal">
        <div class="admin-card-head" style="padding:16px 20px;border-bottom:1px solid var(--hanania-border);margin-bottom:0;">
            <div>
                <div class="admin-card-title">
                    <span>📋 Daftar Jadwal Praktik Dokter &amp; Bidan</span>
                </div>
                <div class="admin-card-subtitle">
                    Total: <?= count($jadwalTableFiltered) ?> sesi praktik terdaftar
                </div>
            </div>

            <!-- FILTER BAR HARI, JENIS & POLI -->
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <!-- FILTER POLI -->
                <div class="admin-filter-group">
                    <span style="font-size:12px;font-weight:700;color:var(--muted);">Poli:</span>
                    <select onchange="location.href=this.value" style="padding:6px 10px;border-radius:8px;font-size:12px;border:1px solid var(--hanania-border);background:#fff;font-family:inherit;font-weight:700;color:var(--hanania-text);cursor:pointer;">
                        <option value="jadwal.php?<?= http_build_query(array_merge($_GET, ['poli' => 0])) ?>">Semua Poli</option>
                        <?php foreach ($daftarPoli as $p): ?>
                            <option value="jadwal.php?<?= http_build_query(array_merge($_GET, ['poli' => $p['id_poli']])) ?>" <?= $filterPoli===(int)$p['id_poli'] ? 'selected' : '' ?>>
                                <?= adminEsc($p['nama_poli']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- FILTER JENIS -->
                <div class="admin-filter-group">
                    <span style="font-size:12px;font-weight:700;color:var(--muted);">Jenis:</span>
                    <a href="jadwal.php<?= $filterHari !== 'semua' ? '?hari='.urlencode($filterHari) : '' ?>" class="btn btn-sm <?= ($filterJenis === 'semua' && $filterPoli !== 5) ? '' : 'btn-light' ?>">Semua</a>
                    <a href="jadwal.php?jenis=dokter<?= $filterHari !== 'semua' ? '&hari='.urlencode($filterHari) : '' ?>" class="btn btn-sm <?= $filterJenis === 'dokter' ? '' : 'btn-light' ?>">Dokter</a>
                    <a href="jadwal.php?jenis=bidan<?= $filterHari !== 'semua' ? '&hari='.urlencode($filterHari) : '' ?>" class="btn btn-sm <?= ($filterJenis === 'bidan' && $filterPoli !== 5) ? '' : 'btn-light' ?>">Bidan</a>
                    <a href="jadwal.php?poli=5<?= $filterHari !== 'semua' ? '&hari='.urlencode($filterHari) : '' ?>" class="btn btn-sm <?= $filterPoli === 5 ? '' : 'btn-light' ?>" style="font-weight:700;">🌙 Persalinan 24 Jam</a>
                </div>

                <!-- FILTER HARI -->
                <div class="admin-filter-group">
                    <span style="font-size:12px;font-weight:700;color:var(--muted);">Hari:</span>
                    <a href="jadwal.php<?= $filterPoli > 0 ? '?poli='.$filterPoli : ($filterJenis !== 'semua' ? '?jenis='.urlencode($filterJenis) : '') ?>" class="btn btn-sm <?= $filterHari === 'semua' ? '' : 'btn-light' ?>">Semua</a>
                    <?php foreach ($daftarHari as $h): ?>
                        <a href="jadwal.php?hari=<?= urlencode($h) ?><?= $filterPoli > 0 ? '&poli='.$filterPoli : ($filterJenis !== 'semua' ? '&jenis='.urlencode($filterJenis) : '') ?>" class="btn btn-sm <?= $filterHari === $h ? '' : 'btn-light' ?>"><?= $h ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($filterPoli === 5): ?>
            <!-- BANNER KHUSUS JADWAL JAGA PERSALINAN 24 JAM -->
            <div style="background:#FAF5FF;border-bottom:1.5px solid #E9D5FF;padding:14px 20px;display:flex;align-items:center;gap:12px;">
                <span style="font-size:24px;">🌙</span>
                <div style="font-size:13px;color:#581C87;line-height:1.5;">
                    <strong>Jadwal Bidan Jaga Persalinan 24 Jam:</strong> Setiap hari telah diatur 1 Bidan Jaga Utama yang siaga non-stop 24 jam. Admin dapat mengubah bidan yang bertugas jaga pada hari tertentu melalui tombol <strong>✏️ Edit</strong>.
                </div>
            </div>
        <?php endif; ?>

        <div class="admin-table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:110px;">Hari</th>
                        <th>Tenaga Medis</th>
                        <th style="width:110px;">Jenis</th>
                        <th>Poli Pelayanan</th>
                        <th>Jam Praktik</th>
                        <th style="width:120px;">Status</th>
                        <th style="text-align:right;width:170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jadwalTableFiltered)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:36px;color:var(--muted);">
                                Belum ada data jadwal praktik yang sesuai dengan filter yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jadwalTableFiltered as $j): 
                            $status = $j['status'] ?? 'tersedia';
                            $badge = 'badge-success';
                            $labelStatus = 'Pendaftaran Tersedia';
                            if ($status === 'ditutup' || $status === 'tidak_tersedia' || $status === 'nonaktif') {
                                $badge = 'badge-danger';
                                $labelStatus = 'Pendaftaran Ditutup';
                            } elseif ($status === 'full_booking') {
                                $badge = 'badge-warning';
                                $labelStatus = 'Full Booking';
                            } elseif ($status === 'cuti') {
                                $badge = 'badge-gray';
                                $labelStatus = 'Cuti / Libur';
                            }
                            $jadwalJson = htmlspecialchars(json_encode($j, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--hanania-primary);font-size:13.5px;"><?= adminEsc($j['hari']) ?></strong>
                            </td>
                            <td>
                                <strong style="color:var(--hanania-text);font-size:13.5px;"><?= adminEsc($j['nama_tenaga']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $j['jenis'] === 'Dokter' ? 'badge-purple' : 'badge-pink' ?>" style="font-size:11px;">
                                    <?= adminEsc($j['jenis']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:3px;">
                                    <span><?= adminEsc($j['nama_poli']) ?></span>
                                    <?php if ((int)$j['id_poli'] === 5): ?>
                                        <span class="badge" style="background:#FAF5FF;color:#6B21A8;border:1px solid #D8B4FE;font-size:10.5px;font-weight:700;width:fit-content;">🌙 Bidan Jaga 24 Jam</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight:700;color:var(--hanania-primary);">
                                    <?= adminEsc(substr($j['jam_mulai'], 0, 5)) ?> – <?= adminEsc(substr($j['jam_selesai'], 0, 5)) ?> WIB
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    <span class="badge <?= $badge ?>"><?= adminEsc($labelStatus) ?></span>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <?php
                                        $currStatus = $j['status'] ?? 'tersedia';
                                        $jType = strtolower($j['jenis']);
                                        $jId = (int)$j['id'];
                                        $baseLink = 'jadwal.php?' . http_build_query(array_merge($_GET, ['toggle_status_id' => $jId, 'type' => $jType]));
                                        ?>
                                        <?php if ($currStatus !== 'tersedia'): ?>
                                            <a href="<?= $baseLink ?>&mode=tersedia" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10.5px;color:#16A34A;background:#DCFCE7;border:1px solid #86EFAC;text-decoration:none;" title="Ubah jadi Pendaftaran Tersedia (Buka)">🟢 Buka</a>
                                        <?php endif; ?>
                                        <?php if ($currStatus !== 'ditutup'): ?>
                                            <a href="<?= $baseLink ?>&mode=ditutup" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10.5px;color:#DC2626;background:#FEE2E2;border:1px solid #FCA5A5;text-decoration:none;" title="Ubah jadi Pendaftaran Ditutup">⛔ Tutup</a>
                                        <?php endif; ?>
                                        <?php if ($currStatus !== 'full_booking'): ?>
                                            <a href="<?= $baseLink ?>&mode=full_booking" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10.5px;color:#D97706;background:#FEF3C7;border:1px solid #FCD34D;text-decoration:none;" title="Ubah jadi Penuh (Full Booking)">🟡 Full</a>
                                        <?php endif; ?>
                                        <?php if ($currStatus !== 'cuti'): ?>
                                            <a href="<?= $baseLink ?>&mode=cuti" class="btn btn-sm btn-light" style="padding:2px 6px;font-size:10.5px;color:#4B5563;background:#F3F4F6;border:1px solid #E5E7EB;text-decoration:none;" title="Ubah jadi Cuti / Libur">🏖️ Cuti</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <button type="button" class="btn btn-sm btn-light btn-edit-jadwal" 
                                            data-jadwal='<?= $jadwalJson ?>' 
                                            title="Edit Jadwal Praktik">
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus sesi jadwal praktik ini?');" style="display:inline;">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                                        <input type="hidden" name="type" value="<?= strtolower($j['jenis']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus Jadwal">
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
// Master data tenaga medis dari database
const allStaffList = [
    <?php foreach ($daftarDokter as $d): ?>
    {
        key: 'dokter-<?= (int)$d['id_dokter'] ?>',
        nama: <?= json_encode($d['nama_dokter']) ?>,
        jenis: 'Dokter',
        id_poli: <?= (int)$d['id_poli'] ?>
    },
    <?php endforeach; ?>
    <?php foreach ($daftarBidan as $b): ?>
    {
        key: 'bidan-<?= (int)$b['id_bidan'] ?>',
        nama: <?= json_encode($b['nama_bidan']) ?>,
        jenis: 'Bidan',
        id_poli: <?= (int)$b['id_poli'] ?>
    },
    <?php endforeach; ?>
];

function onPoliChange(poliId, preselectedKey = '') {
    const staffSelect = document.getElementById('fieldStaff');
    const badge = document.getElementById('staffPoliBadge');
    const hint = document.getElementById('staffPoliHint');
    const typeHidden = document.getElementById('jadwalType');
    if (!staffSelect) return;

    const numPoli = Number(poliId || 0);
    staffSelect.innerHTML = '';

    if (!numPoli) {
        // Jika belum memilih poli: tampilkan seluruh dokter & bidan terkelompok
        const optDefault = document.createElement('option');
        optDefault.value = '';
        optDefault.textContent = '-- Pilih Poli Terlebih Dahulu --';
        staffSelect.appendChild(optDefault);

        const groupDokter = document.createElement('optgroup');
        groupDokter.label = 'Dokter';
        const groupBidan = document.createElement('optgroup');
        groupBidan.label = 'Bidan';

        allStaffList.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.key;
            opt.textContent = s.nama;
            opt.dataset.poli = s.id_poli;
            if (preselectedKey && s.key === preselectedKey) opt.selected = true;
            if (s.jenis === 'Dokter') groupDokter.appendChild(opt);
            else groupBidan.appendChild(opt);
        });

        if (groupDokter.children.length) staffSelect.appendChild(groupDokter);
        if (groupBidan.children.length) staffSelect.appendChild(groupBidan);

        if (badge) badge.style.display = 'none';
        if (hint) {
            hint.textContent = 'Pilih poli pelayanan terlebih dahulu untuk memfilter dokter atau bidan yang bertugas.';
            hint.style.color = 'var(--hanania-muted)';
        }
        return;
    }

    // Filter dokter & bidan yang terdaftar pada poli yang dipilih
    const filtered = allStaffList.filter(s => Number(s.id_poli) === numPoli);

    if (filtered.length === 0) {
        const optEmpty = document.createElement('option');
        optEmpty.value = '';
        optEmpty.textContent = '-- Tidak ada tenaga medis di poli ini --';
        staffSelect.appendChild(optEmpty);
        if (badge) badge.style.display = 'none';
        if (hint) {
            hint.textContent = '⚠️ Belum ada dokter atau bidan yang terdaftar aktif pada poli ini.';
            hint.style.color = '#B91C1C';
        }
        return;
    }

    // JIKA HANYA ADA 1 TENAGA MEDIS (Misal Poli Gigi -> drg. Fatmasari Pangesti)
    // Otomatis langsung terpilih tanpa perlu admin memilih manual!
    if (filtered.length === 1) {
        const single = filtered[0];
        const opt = document.createElement('option');
        opt.value = single.key;
        opt.textContent = single.nama + ' (' + single.jenis + ')';
        opt.dataset.poli = single.id_poli;
        opt.selected = true;
        staffSelect.appendChild(opt);
        staffSelect.value = single.key;

        if (typeHidden) typeHidden.value = single.jenis.toLowerCase();

        if (badge) {
            badge.style.display = 'inline-block';
            badge.textContent = '✓ Otomatis Terpilih';
        }
        if (hint) {
            hint.innerHTML = '✨ <strong>Otomatis:</strong> Karena tenaga medis pada poli ini hanya ada 1 orang, sistem langsung memilih <strong>' + single.nama + '</strong>.';
            hint.style.color = '#15803D';
        }
        return;
    }

    // JIKA ADA LEBIH DARI 1 TENAGA MEDIS (Misal Poli Umum, Poli Kebidanan)
    const optPrompt = document.createElement('option');
    optPrompt.value = '';
    optPrompt.textContent = '-- Pilih Tenaga Medis (' + filtered.length + ' Tersedia) --';
    staffSelect.appendChild(optPrompt);

    filtered.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.key;
        opt.textContent = s.nama + ' (' + s.jenis + ')';
        opt.dataset.poli = s.id_poli;
        if (preselectedKey && s.key === preselectedKey) {
            opt.selected = true;
        }
        staffSelect.appendChild(opt);
    });

    if (preselectedKey) {
        staffSelect.value = preselectedKey;
        const selectedObj = filtered.find(s => s.key === preselectedKey);
        if (selectedObj && typeHidden) {
            typeHidden.value = selectedObj.jenis.toLowerCase();
        }
    }

    if (badge) badge.style.display = 'none';
    if (hint) {
        hint.textContent = 'Silakan pilih tenaga medis yang bertugas (' + filtered.length + ' dokter/bidan tersedia di poli ini).';
        hint.style.color = 'var(--hanania-muted)';
    }
}

function toggleFormJadwal() {
    const card = document.getElementById('cardFormJadwal');
    const form = document.getElementById('formJadwal');
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') {
        document.getElementById('formJadwalTitle').innerHTML = '<span>➕</span> Tambah Jadwal Praktik Baru';
        document.getElementById('jadwalAction').value = 'tambah';
        document.getElementById('jadwalId').value = '0';
        document.getElementById('lblHariPraktik').innerHTML = '🗓️ Pilih Hari Praktik * (Bisa pilih satu atau beberapa hari sekaligus)';
        document.getElementById('hintHariPraktik').innerHTML = '💡 Contoh: jika dicentang Senin, Rabu, dan Jumat, sistem akan otomatis membuat 3 sesi jadwal sekaligus dengan jam yang sama.';
        document.getElementById('btnSubmitJadwal').textContent = 'Simpan Jadwal';
        form.reset();
        pilihSemuaHari(false);
        onPoliChange('');
        card.scrollIntoView({ behavior: 'smooth' });
    }
}

function bukaEditJadwal(j) {
    const card = document.getElementById('cardFormJadwal');
    card.style.display = 'block';
    document.getElementById('formJadwalTitle').innerHTML = '<span>✏️</span> Edit Jadwal Praktik: ' + (j.nama_tenaga || '');
    document.getElementById('jadwalAction').value = 'edit';
    document.getElementById('jadwalId').value = j.id;
    document.getElementById('jadwalType').value = j.jenis.toLowerCase();

    // Set pilihan poli dan filter staff sesuai poli tersebut
    const staffKey = j.jenis.toLowerCase() + '-' + j.staff_id;
    const poliSelect = document.getElementById('fieldJadwalPoli');
    if (poliSelect) poliSelect.value = j.id_poli;

    onPoliChange(j.id_poli, staffKey);

    // Set pilihan hari (uncheck semua, lalu centang hari aktif)
    pilihSemuaHari(false);
    if (j.hari) {
        const targetCb = document.querySelector('.check-hari[value="' + j.hari + '"]');
        if (targetCb) {
            targetCb.checked = true;
        }
    }
    document.getElementById('lblHariPraktik').innerHTML = '🗓️ Hari Praktik * (Hari aktif: <strong>' + (j.hari || '') + '</strong> — Centang untuk ganti/tambah hari)';
    document.getElementById('hintHariPraktik').innerHTML = '💡 Anda dapat mengganti hari praktik dengan mencentang hari lain.';

    document.getElementById('fieldJamMulai').value = j.jam_mulai.substring(0, 5);
    document.getElementById('fieldJamSelesai').value = j.jam_selesai.substring(0, 5);
    
    // Status
    let st = j.status || 'tersedia';
    if (st === 'nonaktif' || st === 'tidak_tersedia') st = 'ditutup';
    document.getElementById('fieldJadwalStatus').value = st;

    document.getElementById('btnSubmitJadwal').textContent = 'Perbarui Jadwal';
    card.scrollIntoView({ behavior: 'smooth' });
}

function bukaEditJadwalDariBtn(btn) {
    try {
        const j = JSON.parse(btn.getAttribute('data-jadwal'));
        bukaEditJadwal(j);
    } catch (e) {
        console.error('Gagal parsing jadwal:', e);
    }
}

function tutupFormJadwal() {
    document.getElementById('cardFormJadwal').style.display = 'none';
}

function pilihSemuaHari(checked) {
    document.querySelectorAll('.check-hari').forEach(cb => cb.checked = checked);
}

function pilihHariKerja() {
    const hariKerja = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    document.querySelectorAll('.check-hari').forEach(cb => {
        cb.checked = hariKerja.includes(cb.value);
    });
}

document.getElementById('fieldStaff').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const poliId = selected ? selected.dataset.poli : null;
    const typeHidden = document.getElementById('jadwalType');

    if (this.value) {
        const parts = this.value.split('-');
        if (typeHidden && parts[0]) typeHidden.value = parts[0];
    }

    if (poliId && Number(poliId) > 0) {
        const poliSelect = document.getElementById('fieldJadwalPoli');
        if (poliSelect && poliSelect.value !== poliId) {
            poliSelect.value = poliId;
        }
    }
});

document.getElementById('formJadwal').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.check-hari:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Silakan pilih minimal satu hari praktik.');
        return false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-jadwal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            bukaEditJadwalDariBtn(this);
        });
    });
    // Inisialisasi dropdown staff
    onPoliChange('');
});
</script>
<?php adminPageEnd(); ?>
