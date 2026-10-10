<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// Default Katalog Reward Hanania jika belum diubah
$defaultKatalogReward = [
    [
        'id' => 'VCH-GIGI-25',
        'nama' => 'Voucher Diskon Tindakan Poli Gigi Rp 25.000',
        'kategori' => 'Poli Gigi & Mulut',
        'poin' => 100,
        'deskripsi' => 'Berlaku untuk pembersihan karang gigi (scaling), penambalan, atau pencabutan gigi ringan di Klinik Rumah Hanania.',
        'icon' => '🦷',
        'badge' => 'Paling Populer'
    ],
    [
        'id' => 'VCH-KONSUL-GRATIS',
        'nama' => 'Gratis Konsultasi Lanjutan Dokter Umum',
        'kategori' => 'Poli Umum',
        'poin' => 150,
        'deskripsi' => 'Bebas biaya jasa pemeriksaan dan konsultasi dokter umum untuk satu kali kunjungan lanjutan kontrol kesehatan.',
        'icon' => '🩺',
        'badge' => 'Spesial'
    ],
    [
        'id' => 'VCH-VIT-HANANIA',
        'nama' => 'Paket Multivitamin Sehat Hanania (30 Tablet)',
        'kategori' => 'Farmasi & Apotek',
        'poin' => 200,
        'deskripsi' => 'Paket multivitamin lengkap untuk menjaga imunitas dan kebugaran tubuh, dapat diambil langsung di farmasi klinik.',
        'icon' => '💊',
        'badge' => 'Kesehatan'
    ],
    [
        'id' => 'VCH-USG-20',
        'nama' => 'Diskon 20% Pemeriksaan USG Kebidanan',
        'kategori' => 'Poli KIA & Kebidanan',
        'poin' => 250,
        'deskripsi' => 'Potongan 20% biaya layanan USG 2D/3D untuk ibu hamil saat jadwal pemeriksaan dengan dokter spesialis / bidan.',
        'icon' => '👶',
        'badge' => 'Bunda & Anak'
    ],
    [
        'id' => 'VCH-TUMBLER',
        'nama' => 'Merchandise Tumbler & Kit Hand Sanitizer Hanania',
        'kategori' => 'Souvenir Sehat',
        'poin' => 80,
        'deskripsi' => 'Tumbler ramah lingkungan edisi Rumah Hanania dilengkapi dengan botol hand sanitizer saku yang praktis.',
        'icon' => '🎁',
        'badge' => 'Hadiah Langsung'
    ]
];

// Helper get & set pengaturan_konten
function getPoinKontenAdmin($db, $kunci, $default = '') {
    if (!$db instanceof mysqli) return $default;
    $stmt = $db->prepare("SELECT nilai FROM pengaturan_konten WHERE kunci = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $kunci);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (isset($row['nilai']) && trim((string)$row['nilai']) !== '') {
                $stmt->close();
                return $row['nilai'];
            }
        }
        $stmt->close();
    }
    return $default;
}

function setPoinKontenAdmin($db, $kunci, $nilai) {
    if (!$db instanceof mysqli) return false;
    $stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    if ($stmt) {
        $stmt->bind_param('ss', $kunci, $nilai);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
    return false;
}

// Ambil data katalog saat ini
function getKatalogPoinAdmin($db, $defaultKatalogReward) {
    $raw = getPoinKontenAdmin($db, 'poin_katalog_json', '');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
    }
    return $defaultKatalogReward;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'acc_voucher') {
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            $catatan = trim($_POST['catatan_admin'] ?? 'Disetujui oleh Admin');
            if ($idPenukaran <= 0) {
                throw new Exception('ID penukaran voucher tidak valid.');
            }
            $qCek = $db->query("SELECT id_pasien, poin_digunakan, status FROM penukaran_poin WHERE id_penukaran = $idPenukaran LIMIT 1");
            if (!$qCek || !$rowCek = $qCek->fetch_assoc()) {
                throw new Exception('Data penukaran tidak ditemukan.');
            }
            if ($rowCek['status'] === 'disetujui') {
                throw new Exception('Pengajuan voucher ini sudah disetujui sebelumnya.');
            }
            $idPasien = (int)$rowCek['id_pasien'];
            $poinPakai = (int)$rowCek['poin_digunakan'];

            // Potong saldo poin pasien saat ACC disetujui
            if ($idPasien > 0 && $poinPakai > 0) {
                $db->query("UPDATE pasien SET reward_poin = GREATEST(0, reward_poin - $poinPakai) WHERE id_pasien = $idPasien");
            }

            $stmtAcc = $db->prepare("UPDATE penukaran_poin SET status = 'disetujui', catatan_admin = ?, updated_at = NOW() WHERE id_penukaran = ?");
            if ($stmtAcc) {
                $stmtAcc->bind_param('si', $catatan, $idPenukaran);
                $stmtAcc->execute();
                $stmtAcc->close();
                $message = "Pengajuan voucher reward berhasil disetujui (ACC)! Saldo {$poinPakai} poin pasien telah resmi dipotong.";
            }
        } elseif ($action === 'tolak_voucher') {
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            $catatan = trim($_POST['catatan_admin'] ?? 'Ditolak oleh Admin');
            if ($idPenukaran <= 0) {
                throw new Exception('ID penukaran voucher tidak valid.');
            }
            $qCek = $db->query("SELECT id_pasien, poin_digunakan, status FROM penukaran_poin WHERE id_penukaran = $idPenukaran LIMIT 1");
            if (!$qCek || !$rowCek = $qCek->fetch_assoc()) {
                throw new Exception('Data penukaran tidak ditemukan.');
            }
            if ($rowCek['status'] === 'ditolak') {
                throw new Exception('Pengajuan voucher ini sudah ditolak sebelumnya.');
            }
            
            $stmtTolak = $db->prepare("UPDATE penukaran_poin SET status = 'ditolak', catatan_admin = ?, updated_at = NOW() WHERE id_penukaran = ?");
            if ($stmtTolak) {
                $stmtTolak->bind_param('si', $catatan, $idPenukaran);
                $stmtTolak->execute();
                $stmtTolak->close();
            }
            // Karena poin belum dipotong saat pengajuan, tidak perlu refund poin
            $message = "Pengajuan voucher telah ditolak. Poin pasien tidak berkurang.";
        } elseif ($action === 'simpan_konten_poin') {
            // Simpan konten informasi halaman poin
            $heroJudul = trim($_POST['poin_hero_judul'] ?? '');
            $heroDesc = trim($_POST['poin_hero_deskripsi'] ?? '');
            $panduanJudul = trim($_POST['poin_panduan_judul'] ?? '');
            $panduanTeks = trim($_POST['poin_panduan_teks'] ?? '');

            setPoinKontenAdmin($db, 'poin_hero_judul', $heroJudul);
            setPoinKontenAdmin($db, 'poin_hero_deskripsi', $heroDesc);
            setPoinKontenAdmin($db, 'poin_panduan_judul', $panduanJudul);
            setPoinKontenAdmin($db, 'poin_panduan_teks', $panduanTeks);

            $message = 'Konten informasi halaman poin berhasil diperbarui!';
        } elseif ($action === 'simpan_reward') {
            // Tambah atau Edit Reward Voucher Katalog
            $isEdit = !empty($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
            $rewardId = trim($_POST['reward_id'] ?? '');
            $nama = trim($_POST['reward_nama'] ?? '');
            $kategori = trim($_POST['reward_kategori'] ?? 'Poli Umum');
            $poin = max(1, (int)($_POST['reward_poin'] ?? 100));
            $deskripsi = trim($_POST['reward_deskripsi'] ?? '');
            $icon = trim($_POST['reward_icon'] ?? '🎁');
            $badge = trim($_POST['reward_badge'] ?? '');

            if ($nama === '') {
                throw new Exception('Nama reward/voucher wajib diisi.');
            }
            if ($rewardId === '') {
                $rewardId = 'VCH-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
            }

            $currentKatalog = getKatalogPoinAdmin($db, $defaultKatalogReward);
            $found = false;

            foreach ($currentKatalog as &$item) {
                if ($item['id'] === $rewardId) {
                    $item['nama'] = $nama;
                    $item['kategori'] = $kategori;
                    $item['poin'] = $poin;
                    $item['deskripsi'] = $deskripsi;
                    $item['icon'] = $icon ?: '🎁';
                    $item['badge'] = $badge;
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                // Tambah baru
                $currentKatalog[] = [
                    'id' => $rewardId,
                    'nama' => $nama,
                    'kategori' => $kategori,
                    'poin' => $poin,
                    'deskripsi' => $deskripsi,
                    'icon' => $icon ?: '🎁',
                    'badge' => $badge
                ];
            }

            setPoinKontenAdmin($db, 'poin_katalog_json', json_encode(array_values($currentKatalog), JSON_UNESCAPED_UNICODE));
            $message = 'Katalog voucher reward berhasil disimpan!';
        } elseif ($action === 'hapus_reward') {
            $rewardId = trim($_POST['reward_id'] ?? '');
            if ($rewardId === '') {
                throw new Exception('ID reward tidak valid.');
            }
            $currentKatalog = getKatalogPoinAdmin($db, $defaultKatalogReward);
            $filtered = array_filter($currentKatalog, fn($x) => $x['id'] !== $rewardId);
            setPoinKontenAdmin($db, 'poin_katalog_json', json_encode(array_values($filtered), JSON_UNESCAPED_UNICODE));
            $message = 'Item reward berhasil dihapus dari katalog!';
        } elseif ($action === 'reset_katalog') {
            setPoinKontenAdmin($db, 'poin_katalog_json', json_encode($defaultKatalogReward, JSON_UNESCAPED_UNICODE));
            $message = 'Katalog reward berhasil direset ke pengaturan bawaan default!';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Data Konten Saat Ini
$kontenHeroJudul = getPoinKontenAdmin($db, 'poin_hero_judul', 'Hanania Point & Reward');
$kontenHeroDesc = getPoinKontenAdmin($db, 'poin_hero_deskripsi', 'Dapatkan apresiasi terbaik untuk setiap kunjungan dan konsultasi kesehatan Anda.');
$kontenPanduanJudul = getPoinKontenAdmin($db, 'poin_panduan_judul', 'Panduan & Cara Penukaran Poin');
$kontenPanduanTeks = getPoinKontenAdmin($db, 'poin_panduan_teks', "1. Setiap kunjungan pelayanan kesehatan di klinik akan memberikan poin reward ke akun Anda.\n2. Pilih reward atau voucher yang sesuai dengan jumlah poin yang Anda miliki.\n3. Ajukan penukaran dan tunggu persetujuan (ACC) dari Admin Klinik.\n4. Tunjukkan kode voucher resmi yang telah disetujui kepada petugas pendaftaran/kasir saat kunjungan berikutnya.");

// Data Katalog Saat Ini
$activeKatalog = getKatalogPoinAdmin($db, $defaultKatalogReward);

// Filter voucher ACC
$filterStatusVoucher = $_GET['status_voucher'] ?? '';
$sqlPoin = "SELECT pp.*, p.nama_lengkap, p.nik, p.no_telepon, p.reward_poin as saldo_saat_ini 
            FROM penukaran_poin pp 
            LEFT JOIN pasien p ON pp.id_pasien = p.id_pasien";
if (in_array($filterStatusVoucher, ['menunggu', 'disetujui', 'ditolak'], true)) {
    $sqlPoin .= " WHERE pp.status = '" . $db->real_escape_string($filterStatusVoucher) . "'";
}
$sqlPoin .= " ORDER BY pp.id_penukaran DESC LIMIT 100";
$listVoucher = [];
$qPoin = $db->query($sqlPoin);
if ($qPoin) while ($r = $qPoin->fetch_assoc()) $listVoucher[] = $r;

adminPageStart('Kelola Poin, Konten & Voucher', 'poin');
?>

<div class="admin-page" style="padding: 24px 30px 60px;">

    <!-- HEADER TITLE -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:22px;font-weight:900;color:var(--hanania-primary);letter-spacing:-.3px;">
                ⭐ Kelola Konten Poin, Reward &amp; Voucher
            </h2>
            <p style="font-size:13px;color:var(--hanania-muted);margin-top:3px;">
                Kelola isi halaman poin, susun katalog voucher hadiah, dan setujui klaim voucher pasien. Saldo poin pasien terhitung otomatis oleh sistem.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="../pasien/reward.php" target="_blank" rel="noopener" class="btn btn-sm btn-light">
                🎁 Lihat Halaman Poin Pasien &rarr;
            </a>
        </div>
    </div>

    <!-- QUICK SECTION SHORTCUTS -->
    <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
        <a href="#sec-voucher" class="btn btn-sm btn-primary">⭐ Pengajuan Penukaran Voucher (Paling Atas)</a>
        <a href="#sec-katalog" class="btn btn-sm btn-light">🎁 Katalog Voucher Hadiah</a>
        <a href="#sec-konten" class="btn btn-sm btn-light">📝 Kelola Konten &amp; Teks Poin</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;">
            <span><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- CARD 1 (PALING ATAS): ACC / TOLAK PENGAJUAN VOUCHER -->
    <section class="admin-card" id="sec-voucher" style="margin-bottom:26px;">
        <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <div class="admin-card-title">
                    <span>⭐ Persetujuan &amp; ACC Penukaran Voucher Reward Pasien</span>
                </div>
                <div class="admin-card-subtitle">
                    Tinjau dan setujui (ACC) atau tolak klaim voucher reward yang diajukan pasien. Poin pasien baru akan resmi dipotong saat disetujui (ACC).
                </div>
            </div>

            <!-- FILTER STATUS VOUCHER -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="poin.php#sec-voucher" class="btn btn-sm <?= empty($filterStatusVoucher) ? 'btn-primary' : 'btn-light' ?>" style="font-size:11.5px;">
                    Semua
                </a>
                <a href="poin.php?status_voucher=menunggu#sec-voucher" class="btn btn-sm <?= $filterStatusVoucher === 'menunggu' ? 'btn-primary' : 'btn-light' ?>" style="font-size:11.5px;">
                    ⏳ Menunggu ACC
                </a>
                <a href="poin.php?status_voucher=disetujui#sec-voucher" class="btn btn-sm <?= $filterStatusVoucher === 'disetujui' ? 'btn-primary' : 'btn-light' ?>" style="font-size:11.5px;">
                    ✅ Disetujui
                </a>
                <a href="poin.php?status_voucher=ditolak#sec-voucher" class="btn btn-sm <?= $filterStatusVoucher === 'ditolak' ? 'btn-primary' : 'btn-light' ?>" style="font-size:11.5px;">
                    ❌ Ditolak
                </a>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Waktu Klaim</th>
                        <th>Pasien</th>
                        <th>Reward &amp; Poin</th>
                        <th>Kode Voucher</th>
                        <th>Catatan Admin</th>
                        <th>Status</th>
                        <th style="text-align:center;width:190px;">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listVoucher)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:30px;color:var(--hanania-muted);">
                                Tidak ada data penukaran voucher reward<?= !empty($filterStatusVoucher) ? " dengan status '{$filterStatusVoucher}'" : '' ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listVoucher as $v): ?>
                            <tr>
                                <td><strong>#<?= $v['id_penukaran'] ?></strong></td>
                                <td style="font-size:12px;color:var(--hanania-muted);white-space:nowrap;">
                                    <?= date('d M Y, H:i', strtotime($v['created_at'])) ?>
                                </td>
                                <td>
                                    <div style="font-weight:800;color:var(--hanania-primary);font-size:13.5px;">
                                        <?= adminEsc($v['nama_lengkap'] ?: 'Pasien #' . $v['id_pasien']) ?>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--hanania-muted);">
                                        NIK: <?= adminEsc($v['nik'] ?: '-') ?> | Telp: <?= adminEsc($v['no_telepon'] ?: '-') ?>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--hanania-primary-dark);font-weight:700;">
                                        Saldo Sekarang: <?= number_format((int)($v['saldo_saat_ini'] ?? 0)) ?> Poin
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:800;color:#201435;font-size:13px;">
                                        <?= adminEsc($v['nama_reward']) ?>
                                    </div>
                                    <div style="font-size:11.5px;color:#A15403;font-weight:700;">
                                        🎟️ <?= number_format($v['poin_digunakan']) ?> Poin Digunakan
                                    </div>
                                </td>
                                <td>
                                    <code style="background:#F4EEFB;padding:4px 8px;border-radius:6px;font-size:12px;font-weight:800;color:var(--hanania-primary);letter-spacing:0.5px;border:1px solid #E5D5FA;">
                                        <?= adminEsc($v['kode_voucher']) ?>
                                    </code>
                                </td>
                                <td style="font-size:12px;color:var(--hanania-muted);max-width:180px;">
                                    <?= adminEsc($v['catatan_admin'] ?: '-') ?>
                                </td>
                                <td>
                                    <?php if ($v['status'] === 'menunggu'): ?>
                                        <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:11px;font-weight:800;padding:5px 9px;">
                                            ⏳ Menunggu ACC
                                        </span>
                                    <?php elseif ($v['status'] === 'disetujui'): ?>
                                        <span class="badge" style="background:#D1FAE5;color:#065F46;font-size:11px;font-weight:800;padding:5px 9px;">
                                            ✅ Disetujui
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#FEE2E2;color:#991B1B;font-size:11px;font-weight:800;padding:5px 9px;">
                                            ❌ Ditolak
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($v['status'] === 'menunggu'): ?>
                                        <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
                                            <!-- FORM ACC -->
                                            <form method="POST" action="poin.php" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui (ACC) voucher <?= addslashes($v['kode_voucher']) ?> untuk pasien <?= addslashes($v['nama_lengkap']) ?>? Poin pasien akan resmi dipotong.');">
                                                <input type="hidden" name="action" value="acc_voucher">
                                                <input type="hidden" name="id_penukaran" value="<?= $v['id_penukaran'] ?>">
                                                <input type="hidden" name="catatan_admin" value="Disetujui oleh Admin pada <?= date('d/m/Y H:i') ?>">
                                                <button type="submit" class="btn btn-sm" style="padding:5px 10px;font-size:11.5px;font-weight:800;background:#1B7A3E;color:#fff;">
                                                    ✅ ACC
                                                </button>
                                            </form>

                                            <!-- FORM TOLAK -->
                                            <form method="POST" action="poin.php" onsubmit="var alasan = prompt('Alasan penolakan voucher (poin pasien tetap aman/tidak berkurang):', 'Kriteria klaim tidak terpenuhi'); if (!alasan) return false; this.catatan_admin.value = alasan; return true;">
                                                <input type="hidden" name="action" value="tolak_voucher">
                                                <input type="hidden" name="id_penukaran" value="<?= $v['id_penukaran'] ?>">
                                                <input type="hidden" name="catatan_admin" value="Ditolak oleh admin">
                                                <button type="submit" class="btn btn-sm" style="padding:5px 10px;font-size:11.5px;font-weight:800;background:#B72B2B;color:#fff;">
                                                    ❌ Tolak Pengajuan
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size:11.5px;color:var(--hanania-muted);font-style:italic;">
                                            Selesai diproses
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- CARD: PENGATURAN KONTEN & INFORMASI HALAMAN POIN -->
    <section class="admin-card" id="sec-konten" style="margin-bottom:26px;">
        <div class="admin-card-head">
            <div>
                <div class="admin-card-title">
                    <span>📝 Pengaturan Konten &amp; Informasi Halaman Poin</span>
                </div>
                <div class="admin-card-subtitle">
                    Admin dapat mengubah judul, subjudul banner, serta informasi panduan &amp; syarat penukaran poin yang tampil di halaman pasien.
                </div>
            </div>
        </div>

        <form method="POST" action="poin.php" class="form-grid">
            <input type="hidden" name="action" value="simpan_konten_poin">

            <div class="form-field full">
                <label>Judul Header / Banner Poin *</label>
                <input type="text" name="poin_hero_judul" value="<?= adminEsc($kontenHeroJudul) ?>" class="filter-input" style="font-weight:700;" required>
            </div>

            <div class="form-field full">
                <label>Subjudul / Pesan Pembuka Banner *</label>
                <textarea name="poin_hero_deskripsi" rows="2" class="filter-input" style="font-size:13px;line-height:1.5;" required><?= adminEsc($kontenHeroDesc) ?></textarea>
            </div>

            <div class="form-field full">
                <label>Judul Bagian Informasi &amp; Panduan Poin</label>
                <input type="text" name="poin_panduan_judul" value="<?= adminEsc($kontenPanduanJudul) ?>" class="filter-input" style="font-weight:700;">
            </div>

            <div class="form-field full">
                <label>Isi Teks Panduan &amp; Syarat Penukaran Poin</label>
                <textarea name="poin_panduan_teks" rows="4" class="filter-input" style="font-size:13px;line-height:1.6;font-family:inherit;"><?= adminEsc($kontenPanduanTeks) ?></textarea>
                <span style="font-size:11px;color:var(--hanania-muted);">Tips: Gunakan nomor baris (1., 2., 3.) untuk poin panduan agar rapi dan mudah dibaca oleh pasien.</span>
            </div>

            <div class="form-field full" style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn">
                    💾 Simpan Perubahan Konten Poin
                </button>
            </div>
        </form>
    </section>

    <!-- CARD: KELOLA KATALOG VOUCHER REWARD -->
    <section class="admin-card" id="sec-katalog" style="margin-bottom:26px;">
        <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <div class="admin-card-title">
                    <span>🎁 Kelola Katalog Voucher Hadiah &amp; Reward Poin</span>
                </div>
                <div class="admin-card-subtitle">
                    Tambah, edit, atau hapus pilihan voucher yang dapat ditukarkan pasien dengan saldo poin mereka.
                </div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button type="button" class="btn btn-sm" onclick="toggleFormReward()">
                    <span>➕</span> Tambah Reward Baru
                </button>
                <form method="POST" action="poin.php" onsubmit="return confirm('Kembalikan katalog reward ke 5 pilihan bawaan klinik? Pilihan kustom yang sudah dibuat akan digantikan.');" style="display:inline;">
                    <input type="hidden" name="action" value="reset_katalog">
                    <button type="submit" class="btn btn-sm btn-light" title="Reset Katalog ke Default">
                        ↻ Reset Bawaan
                    </button>
                </form>
            </div>
        </div>

        <!-- FORM TAMBAH / EDIT REWARD (TOGGLEABLE) -->
        <div id="boxFormReward" style="display:none;background:#FAF8FD;border:1.5px solid var(--hanania-border);border-radius:14px;padding:18px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <strong id="titleFormReward" style="color:var(--hanania-primary);font-size:15px;">➕ Tambah Pilihan Reward Baru</strong>
                <button type="button" class="btn btn-sm btn-light" onclick="toggleFormReward()">✕ Tutup</button>
            </div>

            <form method="POST" action="poin.php" id="formRewardAct" class="form-grid">
                <input type="hidden" name="action" value="simpan_reward">
                <input type="hidden" name="edit_mode" id="fieldRewardEditMode" value="0">
                <input type="hidden" name="reward_id" id="fieldRewardId" value="">

                <div class="form-field">
                    <label>Nama Voucher / Reward *</label>
                    <input type="text" name="reward_nama" id="fieldRewardNama" placeholder="Contoh: Voucher Diskon Pemeriksaan Gigi Rp 25.000" class="filter-input" required>
                </div>

                <div class="form-field">
                    <label>Kategori Pelayanan *</label>
                    <input type="text" name="reward_kategori" id="fieldRewardKategori" placeholder="Contoh: Poli Gigi / Farmasi / Kebidanan" class="filter-input" required>
                </div>

                <div class="form-field">
                    <label>Poin yang Dibutuhkan *</label>
                    <input type="number" name="reward_poin" id="fieldRewardPoin" min="1" max="99999" placeholder="Contoh: 100" class="filter-input" required>
                </div>

                <div class="form-field">
                    <label>Icon Emoji</label>
                    <input type="text" name="reward_icon" id="fieldRewardIcon" placeholder="Contoh: 🦷 / 🩺 / 💊 / 🎁" class="filter-input" value="🎁">
                </div>

                <div class="form-field full">
                    <label>Badge Tag (Opsional)</label>
                    <input type="text" name="reward_badge" id="fieldRewardBadge" placeholder="Contoh: Paling Populer / Spesial / Diskon 20%" class="filter-input">
                </div>

                <div class="form-field full">
                    <label>Deskripsi Voucher / Keterangan Penukaran *</label>
                    <textarea name="reward_deskripsi" id="fieldRewardDesc" rows="3" placeholder="Jelaskan syarat atau cakupan pelayanan voucher ini..." class="filter-input" required></textarea>
                </div>

                <div class="form-field full" style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="submit" class="btn" id="btnSubmitReward">Simpan Voucher</button>
                    <button type="button" class="btn btn-light" onclick="toggleFormReward()">Batal</button>
                </div>
            </form>
        </div>

        <!-- TABEL KATALOG REWARD SAAT INI -->
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:50px;">Icon</th>
                        <th>Nama Voucher &amp; Deskripsi</th>
                        <th>Kategori</th>
                        <th>Poin Dibutuhkan</th>
                        <th>Badge</th>
                        <th style="width:130px;text-align:center;">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeKatalog)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:26px;color:var(--hanania-muted);">
                                Belum ada reward dalam katalog. Silakan klik "Tambah Reward Baru".
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeKatalog as $kat): ?>
                            <tr>
                                <td style="text-align:center;font-size:26px;"><?= $kat['icon'] ?: '🎁' ?></td>
                                <td>
                                    <div style="font-weight:800;color:var(--hanania-primary);font-size:13.5px;">
                                        <?= adminEsc($kat['nama']) ?>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:2px;">
                                        <?= adminEsc($kat['deskripsi']) ?>
                                    </div>
                                    <code style="font-size:10px;color:#8E7E9E;">ID: <?= adminEsc($kat['id']) ?></code>
                                </td>
                                <td>
                                    <span class="badge" style="background:#F2ECFA;color:var(--hanania-primary);font-size:11px;">
                                        <?= adminEsc($kat['kategori']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:#A15403;font-size:13px;">
                                        ⭐ <?= number_format($kat['poin']) ?> Poin
                                    </strong>
                                </td>
                                <td>
                                    <?php if (!empty($kat['badge'])): ?>
                                        <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:11px;font-weight:800;">
                                            <?= adminEsc($kat['badge']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#A8A0B2;font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex;gap:4px;">
                                        <?php $dataJson = htmlspecialchars(json_encode($kat, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                                        <button type="button" class="btn btn-sm btn-light" onclick='editRewardItem(<?= $dataJson ?>)' title="Edit Reward">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" action="poin.php" onsubmit="return confirm('Hapus voucher <?= addslashes($kat['nama']) ?> dari katalog?');" style="display:inline;">
                                            <input type="hidden" name="action" value="hapus_reward">
                                            <input type="hidden" name="reward_id" value="<?= adminEsc($kat['id']) ?>">
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

</div>

<script>
function toggleFormReward() {
    const box = document.getElementById('boxFormReward');
    const isHidden = box.style.display === 'none';
    box.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        document.getElementById('titleFormReward').textContent = '➕ Tambah Pilihan Reward Baru';
        document.getElementById('fieldRewardEditMode').value = '0';
        document.getElementById('fieldRewardId').value = '';
        document.getElementById('fieldRewardNama').value = '';
        document.getElementById('fieldRewardKategori').value = 'Poli Umum';
        document.getElementById('fieldRewardPoin').value = '100';
        document.getElementById('fieldRewardIcon').value = '🎁';
        document.getElementById('fieldRewardBadge').value = '';
        document.getElementById('fieldRewardDesc').value = '';
        document.getElementById('btnSubmitReward').textContent = 'Simpan Voucher';
        box.scrollIntoView({ behavior: 'smooth' });
    }
}

function editRewardItem(data) {
    const box = document.getElementById('boxFormReward');
    box.style.display = 'block';
    document.getElementById('titleFormReward').textContent = '✏️ Edit Reward: ' + (data.nama || '');
    document.getElementById('fieldRewardEditMode').value = '1';
    document.getElementById('fieldRewardId').value = data.id || '';
    document.getElementById('fieldRewardNama').value = data.nama || '';
    document.getElementById('fieldRewardKategori').value = data.kategori || '';
    document.getElementById('fieldRewardPoin').value = data.poin || 100;
    document.getElementById('fieldRewardIcon').value = data.icon || '🎁';
    document.getElementById('fieldRewardBadge').value = data.badge || '';
    document.getElementById('fieldRewardDesc').value = data.deskripsi || '';
    document.getElementById('btnSubmitReward').textContent = 'Perbarui Voucher';
    box.scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php adminPageEnd(); ?>
