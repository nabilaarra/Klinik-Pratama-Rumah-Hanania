<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// Handle HAPUS
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtDel = $db->prepare("DELETE FROM paket_layanan WHERE id_paket = ?");
    if ($stmtDel) {
        $stmtDel->bind_param('i', $idHapus);
        if ($stmtDel->execute()) {
            $message = 'Paket layanan berhasil dihapus.';
        } else {
            $message = 'Gagal menghapus layanan: ' . $db->error;
            $messageType = 'danger';
        }
        $stmtDel->close();
    }
}

// Handle TOGGLE STATUS
if (isset($_GET['toggle_status'])) {
    $idToggle = (int)$_GET['toggle_status'];
    $stmtT = $db->prepare("UPDATE paket_layanan SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_paket = ?");
    if ($stmtT) {
        $stmtT->bind_param('i', $idToggle);
        $stmtT->execute();
        $stmtT->close();
        $message = 'Status layanan berhasil diubah.';
    }
}

// Handle SIMPAN (Tambah / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idPaket = (int)($_POST['id_paket'] ?? 0);
    $kategori = trim($_POST['kategori'] ?? 'Pemeriksaan Kehamilan & USG');
    $namaLayanan = trim($_POST['nama_layanan'] ?? '');
    $tenagaMedis = trim($_POST['tenaga_medis'] ?? '');
    $spesialisasi = trim($_POST['spesialisasi'] ?? '');
    $biayaDaftar = (int)($_POST['biaya_daftar'] ?? 10000);
    $biayaTindakan = (int)($_POST['biaya_tindakan'] ?? 0);
    $statusBpjs = in_array($_POST['status_bpjs'] ?? '', ['Bisa BPJS', 'Tidak Bisa BPJS'], true) ? $_POST['status_bpjs'] : 'Tidak Bisa BPJS';
    $fasilitasTermasuk = trim($_POST['fasilitas_termasuk'] ?? '');
    $syaratKetentuan = trim($_POST['syarat_ketentuan'] ?? '');
    $catatan = trim($_POST['catatan'] ?? 'Biaya belum termasuk obat/vitamin');
    $urutan = (int)($_POST['urutan'] ?? 1);
    $status = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

    if ($namaLayanan === '' || $tenagaMedis === '') {
        $message = 'Nama layanan dan tenaga medis wajib diisi.';
        $messageType = 'danger';
    } else {
        if ($action === 'edit' && $idPaket > 0) {
            $stmtUp = $db->prepare("
                UPDATE paket_layanan 
                SET kategori = ?, nama_layanan = ?, tenaga_medis = ?, spesialisasi = ?, 
                    biaya_daftar = ?, biaya_tindakan = ?, status_bpjs = ?, 
                    fasilitas_termasuk = ?, syarat_ketentuan = ?, catatan = ?, 
                    urutan = ?, status = ?
                WHERE id_paket = ?
            ");
            if ($stmtUp) {
                $stmtUp->bind_param(
                    'ssssiissssisi',
                    $kategori, $namaLayanan, $tenagaMedis, $spesialisasi,
                    $biayaDaftar, $biayaTindakan, $statusBpjs,
                    $fasilitasTermasuk, $syaratKetentuan, $catatan,
                    $urutan, $status, $idPaket
                );
                if ($stmtUp->execute()) {
                    $message = "Paket layanan \"$namaLayanan\" berhasil diperbarui!";
                } else {
                    $message = 'Gagal memperbarui paket: ' . $db->error;
                    $messageType = 'danger';
                }
                $stmtUp->close();
            }
        } elseif ($action === 'tambah') {
            $stmtIn = $db->prepare("
                INSERT INTO paket_layanan 
                (kategori, nama_layanan, tenaga_medis, spesialisasi, biaya_daftar, biaya_tindakan, status_bpjs, fasilitas_termasuk, syarat_ketentuan, catatan, urutan, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmtIn) {
                $stmtIn->bind_param(
                    'ssssiissssis',
                    $kategori, $namaLayanan, $tenagaMedis, $spesialisasi,
                    $biayaDaftar, $biayaTindakan, $statusBpjs,
                    $fasilitasTermasuk, $syaratKetentuan, $catatan,
                    $urutan, $status
                );
                if ($stmtIn->execute()) {
                    $message = "Paket layanan baru \"$namaLayanan\" berhasil ditambahkan!";
                } else {
                    $message = 'Gagal menambahkan paket: ' . $db->error;
                    $messageType = 'danger';
                }
                $stmtIn->close();
            }
        }
    }
}

// Query Semua Layanan
$listPaket = [];
$res = $db->query("SELECT * FROM paket_layanan ORDER BY urutan ASC, id_paket ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $listPaket[] = $row;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$targetEditPaket = null;
if ($editId > 0) {
    foreach ($listPaket as $p) {
        if ((int)$p['id_paket'] === $editId) {
            $targetEditPaket = $p;
            break;
        }
    }
}

adminPageStart('Tarif & Layanan Klinik', 'layanan');
?>
<main class="admin-page">
    <header class="admin-header">
        <div>
            <h1><span>💳</span> Tarif &amp; Paket Layanan Klinik</h1>
            <p>Kelola rincian biaya pendaftaran, biaya tindakan USG, status BPJS, dan fasilitas pemeriksaan kehamilan Klinik Hanania.</p>
        </div>
        <div class="admin-header-actions" style="display:flex;gap:8px;align-items:center;">
            <a href="layanan_pendaftaran.php" class="btn btn-sm btn-light" style="background:var(--hanania-purple-soft);color:var(--hanania-primary);display:inline-flex;align-items:center;gap:6px;">
                <span>📋</span> Pilihan Layanan Form Pasien
            </a>
            <button type="button" class="btn btn-sm" onclick="toggleFormPaket()">
                <span>➕</span> Tambah Paket Layanan
            </button>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>">
            <span><?= $messageType === 'danger' ? '⚠️' : '✅' ?></span> <?= adminEsc($message) ?>
        </div>
    <?php endif; ?>

    <!-- FORM TAMBAH / EDIT PAKET LAYANAN -->
    <section class="admin-card" id="cardFormPaket" style="display:none;margin-bottom:24px;">
        <div class="admin-card-title" style="margin-bottom:18px;">
            <span id="formPaketTitle"><span>➕</span> Tambah Paket Layanan Baru</span>
            <button type="button" class="btn btn-sm btn-light" onclick="tutupFormPaket()">✕ Tutup</button>
        </div>

        <form method="POST" id="formPaketLayanan">
            <input type="hidden" name="action" id="fieldPaketAction" value="tambah">
            <input type="hidden" name="id_paket" id="fieldPaketId" value="0">

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
                <!-- KATEGORI -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Kategori Layanan *</label>
                    <select name="kategori" id="fieldKategori" class="filter-input" style="width:100%;" required>
                        <option value="Pemeriksaan Kehamilan & USG">Pemeriksaan Kehamilan &amp; USG</option>
                        <option value="Screening Kehamilan">Screening Kehamilan</option>
                        <option value="Pelayanan Umum">Pelayanan Umum</option>
                        <option value="Pelayanan Gigi">Pelayanan Gigi</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <!-- NAMA LAYANAN -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nama Layanan / Pemeriksaan *</label>
                    <input type="text" name="nama_layanan" id="fieldNamaLayanan" required class="filter-input" style="width:100%;" placeholder="Contoh: USG 2D Kebidanan & Kandungan">
                </div>

                <!-- TENAGA MEDIS -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Tenaga Medis Pelaksana *</label>
                    <input type="text" name="tenaga_medis" id="fieldTenagaMedis" required class="filter-input" style="width:100%;" placeholder="Contoh: Dr. Eddy Wibowo, Sp.OG">
                </div>

                <!-- SPESIALISASI -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Spesialisasi / Keterangan Peran</label>
                    <input type="text" name="spesialisasi" id="fieldSpesialisasi" class="filter-input" style="width:100%;" placeholder="Contoh: Dokter Spesialis Kebidanan dan Kandungan">
                </div>

                <!-- BIAYA PENDAFTARAN -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Biaya Pendaftaran (Rp) *</label>
                    <input type="number" name="biaya_daftar" id="fieldBiayaDaftar" value="10000" min="0" step="1000" class="filter-input" style="width:100%;" required>
                </div>

                <!-- BIAYA TINDAKAN / PEMERIKSAAN -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Biaya Tindakan / USG (Rp) *</label>
                    <input type="number" name="biaya_tindakan" id="fieldBiayaTindakan" value="170000" min="0" step="1000" class="filter-input" style="width:100%;" required>
                </div>

                <!-- STATUS BPJS -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Penerimaan BPJS *</label>
                    <select name="status_bpjs" id="fieldStatusBpjs" class="filter-input" style="width:100%;font-weight:700;" required>
                        <option value="Bisa BPJS">🟢 Bisa BPJS</option>
                        <option value="Tidak Bisa BPJS">🔴 Tidak Bisa BPJS (Umum/Mandiri)</option>
                    </select>
                </div>

                <!-- SYARAT & KETENTUAN (CONTOH USIA KEHAMILAN) -->
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Syarat &amp; Ketentuan Khusus (Opsional)</label>
                    <input type="text" name="syarat_ketentuan" id="fieldSyaratKetentuan" class="filter-input" style="width:100%;" placeholder="Contoh: Khusus usia kehamilan 11–13 minggu">
                </div>
            </div>

            <!-- FASILITAS YANG TERMASUK -->
            <div style="margin-top:16px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Fasilitas / Hasil yang Termasuk (Pisahkan dengan baris baru / enter)</label>
                <textarea name="fasilitas_termasuk" id="fieldFasilitasTermasuk" rows="3" class="filter-input" style="width:100%;resize:vertical;" placeholder="Foto USG&#10;Keterangan hasil USG&#10;Konsultasi dokter&#10;Video hasil USG"></textarea>
            </div>

            <!-- CATATAN & URUTAN -->
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-top:16px;">
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Catatan Biaya Tambahan</label>
                    <input type="text" name="catatan" id="fieldCatatan" value="Biaya belum termasuk obat/vitamin" class="filter-input" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Urutan</label>
                    <input type="number" name="urutan" id="fieldUrutan" value="1" min="0" class="filter-input" style="width:100%;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Tayang</label>
                    <select name="status" id="fieldStatus" class="filter-input" style="width:100%;">
                        <option value="aktif">🟢 Aktif</option>
                        <option value="nonaktif">⚪ Nonaktif</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn btn-sm btn-light" onclick="tutupFormPaket()">Batal</button>
                <button type="submit" class="btn btn-sm" id="btnSubmitPaket">Simpan Paket Layanan</button>
            </div>
        </form>
    </section>

    <!-- TABEL DAFTAR TARIF & PAKET LAYANAN -->
    <section class="admin-card" style="padding:0;overflow:hidden;">
        <div class="admin-card-head" style="padding:18px 24px;border-bottom:1px solid var(--hanania-border);margin:0;">
            <div class="admin-card-title">
                <span>📋 Daftar Tarif &amp; Paket Layanan (<?= count($listPaket) ?> Layanan)</span>
            </div>
        </div>

        <div class="admin-table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">No</th>
                        <th>Layanan &amp; Tenaga Medis</th>
                        <th>Biaya Pendaftaran</th>
                        <th>Biaya Tindakan / USG</th>
                        <th>Status BPJS</th>
                        <th>Fasilitas Termasuk</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:right;width:130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listPaket)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:34px;color:var(--hanania-muted);">
                                Belum ada data tarif layanan. Klik "Tambah Paket Layanan" di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listPaket as $idx => $p): 
                            $isBpjs = $p['status_bpjs'] === 'Bisa BPJS';
                            $isAktif = $p['status'] === 'aktif';
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--hanania-muted);">
                                    <?= $idx + 1 ?>
                                </td>
                                <td>
                                    <strong style="color:var(--hanania-primary);font-size:14px;display:block;">
                                        <?= adminEsc($p['nama_layanan']) ?>
                                    </strong>
                                    <span style="font-size:12.5px;color:var(--hanania-text);font-weight:600;">
                                        🩺 <?= adminEsc($p['tenaga_medis']) ?>
                                    </span>
                                    <?php if (!empty($p['spesialisasi'])): ?>
                                        <div style="font-size:11.5px;color:var(--hanania-muted);"><?= adminEsc($p['spesialisasi']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($p['syarat_ketentuan'])): ?>
                                        <div style="font-size:11px;color:#D97706;font-weight:700;margin-top:2px;">
                                            ⚠️ <?= adminEsc($p['syarat_ketentuan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size:13px;color:var(--hanania-text);">
                                        Rp <?= number_format($p['biaya_daftar'], 0, ',', '.') ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="font-size:14px;color:var(--hanania-primary);font-weight:800;">
                                        Rp <?= number_format($p['biaya_tindakan'], 0, ',', '.') ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?= $isBpjs ? 'badge-success' : 'badge-warning' ?>" style="font-size:11px;">
                                        <?= $isBpjs ? '✅ Bisa BPJS' : '❌ Tidak Dapat BPJS' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:12px;color:var(--hanania-text);line-height:1.4;">
                                        <?= nl2br(adminEsc($p['fasilitas_termasuk'])) ?>
                                    </div>
                                    <?php if (!empty($p['catatan'])): ?>
                                        <small style="font-size:10.5px;color:var(--hanania-muted);display:block;margin-top:3px;">
                                            * <?= adminEsc($p['catatan']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="layanan.php?toggle_status=<?= (int)$p['id_paket'] ?>" 
                                       title="Ubah status aktif" style="text-decoration:none;">
                                        <span class="badge <?= $isAktif ? 'badge-success' : 'badge-gray' ?>" style="cursor:pointer;">
                                            <?= $isAktif ? '🟢 Aktif' : '⚪ Nonaktif' ?>
                                        </span>
                                    </a>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:6px;">
                                        <button type="button" 
                                                onclick='bukaEditPaket(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                class="btn btn-sm btn-light" 
                                                style="padding:5px 9px;font-size:11px;font-weight:700;">
                                            ✏️ Edit
                                        </button>
                                        <a href="layanan.php?hapus=<?= (int)$p['id_paket'] ?>" 
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus paket <?= addslashes($p['nama_layanan']) ?>?');"
                                           class="btn btn-sm btn-danger" 
                                           style="padding:5px 9px;font-size:11px;">
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
</main>

<script>
function toggleFormPaket() {
    const card = document.getElementById('cardFormPaket');
    card.style.display = card.style.display === 'none' ? 'block' : 'none';
    if (card.style.display === 'block') {
        document.getElementById('formPaketTitle').innerHTML = '<span>➕</span> Tambah Paket Layanan Baru';
        document.getElementById('fieldPaketAction').value = 'tambah';
        document.getElementById('fieldPaketId').value = '0';
        document.getElementById('btnSubmitPaket').textContent = 'Simpan Paket Layanan';
        document.getElementById('formPaketLayanan').reset();
        card.scrollIntoView({ behavior: 'smooth' });
    }
}

function bukaEditPaket(p) {
    const card = document.getElementById('cardFormPaket');
    card.style.display = 'block';
    document.getElementById('formPaketTitle').innerHTML = '<span>✏️</span> Edit Paket Layanan: ' + p.nama_layanan;
    document.getElementById('fieldPaketAction').value = 'edit';
    document.getElementById('fieldPaketId').value = p.id_paket;

    document.getElementById('fieldKategori').value = p.kategori || 'Pemeriksaan Kehamilan & USG';
    document.getElementById('fieldNamaLayanan').value = p.nama_layanan || '';
    document.getElementById('fieldTenagaMedis').value = p.tenaga_medis || '';
    document.getElementById('fieldSpesialisasi').value = p.spesialisasi || '';
    document.getElementById('fieldBiayaDaftar').value = p.biaya_daftar || 10000;
    document.getElementById('fieldBiayaTindakan').value = p.biaya_tindakan || 0;
    document.getElementById('fieldStatusBpjs').value = p.status_bpjs || 'Tidak Bisa BPJS';
    document.getElementById('fieldSyaratKetentuan').value = p.syarat_ketentuan || '';
    document.getElementById('fieldFasilitasTermasuk').value = p.fasilitas_termasuk || '';
    document.getElementById('fieldCatatan').value = p.catatan || 'Biaya belum termasuk obat/vitamin';
    document.getElementById('fieldUrutan').value = p.urutan || 1;
    document.getElementById('fieldStatus').value = p.status || 'aktif';

    document.getElementById('btnSubmitPaket').textContent = 'Perbarui Paket Layanan';
    card.scrollIntoView({ behavior: 'smooth' });
}

function tutupFormPaket() {
    document.getElementById('cardFormPaket').style.display = 'none';
}

<?php if ($targetEditPaket): ?>
document.addEventListener('DOMContentLoaded', function() {
    bukaEditPaket(<?= json_encode($targetEditPaket, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);
});
<?php endif; ?>
</script>

<?php adminPageEnd(); ?>
