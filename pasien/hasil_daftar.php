<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$dt) return $tanggal;
    return (int)$dt->format('d') . ' ' . $bulan[(int)$dt->format('m')] . ' ' . $dt->format('Y');
}

$idPendaftaran = (int)($_GET['id'] ?? $_SESSION['hasil_pendaftaran_id'] ?? 0);
$hasilSession = $_SESSION['hasil_pendaftaran'] ?? null;

if ($idPendaftaran <= 0 && is_array($hasilSession)) {
    $idPendaftaran = (int)($hasilSession['id_pendaftaran'] ?? 0);
}

if ($idPendaftaran <= 0) {
    $_SESSION['pesan_daftar'] = 'Belum ada hasil pendaftaran yang dapat ditampilkan.';
    header('Location: daftar.php');
    exit;
}

$stmt = $db->prepare("
    SELECT
        p.id_pendaftaran,
        p.id_pasien,
        p.id_poli,
        p.id_dokter,
        p.id_bidan,
        p.tanggal_kunjungan,
        p.jam_kunjungan,
        p.jenis_pasien,
        p.jenis_perawatan,
        p.keluhan,
        p.status_pendaftaran,
        p.sumber_pendaftaran,
        po.nama_poli,
        d.nama_dokter,
        b.nama_bidan,
        pas.nama_lengkap AS nama_pasien,
        pas.nik AS nik_pasien,
        a.nomor_antrean,
        a.estimasi_waktu
    FROM pendaftaran p
    LEFT JOIN poli po ON po.id_poli = p.id_poli
    LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
    LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
    LEFT JOIN pasien pas ON pas.id_pasien = p.id_pasien
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    WHERE p.id_pendaftaran = ?
    LIMIT 1
");

$data = null;
if ($stmt) {
    $stmt->bind_param('i', $idPendaftaran);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$data && is_array($hasilSession)) {
    $data = $hasilSession;
}

if (!$data) {
    $_SESSION['pesan_daftar'] = 'Data pendaftaran tidak ditemukan.';
    header('Location: daftar.php');
    exit;
}

$rujukanData = null;
$stmtReferral = $db->prepare('SELECT nama_perujuk, file_rujukan FROM rujukan_pendaftaran WHERE id_pendaftaran = ? ORDER BY id_rujukan DESC LIMIT 1');
if ($stmtReferral) {
    $stmtReferral->bind_param('i', $idPendaftaran);
    $stmtReferral->execute();
    $rujukanData = $stmtReferral->get_result()->fetch_assoc();
    $stmtReferral->close();
}

// 1. Nomor Antrean Tunggal (Hanya nomor utama, misal 003)
$nomorAntrean = (int)($data['nomor_antrean'] ?? $hasilSession['nomor_antrean'] ?? 0);
if ($nomorAntrean <= 0) {
    $nomorAntrean = 3; // fallback standar online
}
$nomorAntreanFormat = str_pad((string)$nomorAntrean, 3, '0', STR_PAD_LEFT);

// 2. Hitung jumlah antrean sebenarnya di depan untuk estimasi waktu tunggu
$idPoli = (int)($data['id_poli'] ?? 0);
$idDokter = (int)($data['id_dokter'] ?? 0);
$idBidan = (int)($data['id_bidan'] ?? 0);
$tanggalKunjungan = $data['tanggal_kunjungan'] ?? date('Y-m-d');
$staffCond = ($idDokter > 0) ? "id_dokter = ?" : "id_bidan = ?";
$staffId = ($idDokter > 0) ? $idDokter : $idBidan;

$orangDiDepan = 0;
$stmtAhead = $db->prepare("
    SELECT COUNT(*) AS total
    FROM pendaftaran
    WHERE tanggal_kunjungan = ? AND id_poli = ? AND $staffCond AND status_pendaftaran != 'dibatalkan' AND id_pendaftaran < ?
");
if ($stmtAhead) {
    $stmtAhead->bind_param('siii', $tanggalKunjungan, $idPoli, $staffId, $idPendaftaran);
    $stmtAhead->execute();
    $aheadRow = $stmtAhead->get_result()->fetch_assoc();
    $orangDiDepan = (int)($aheadRow['total'] ?? 0);
    $stmtAhead->close();
}

// 3. Estimasi Pelayanan
// Jam kunjungan di DB adalah jam mulai pelayanan yang sudah dihitung
$jamStr = substr((string)($data['jam_kunjungan'] ?? '08:00'), 0, 5);
$tsMulai = strtotime("$tanggalKunjungan $jamStr");
if ($tsMulai === false) {
    $tsMulai = strtotime("$tanggalKunjungan 08:00");
}
$tsSelesai = $tsMulai + (15 * 60);
$tsDatang = $tsMulai - (15 * 60);

$layaniAwalLabel = date('H:i', $tsMulai);
$layaniAkhirLabel = date('H:i', $tsSelesai);
$datangLabel = date('H:i', $tsDatang);

$nomorAntreanRange = $nomorAntreanFormat;

$estimasiMenunggu = '± 15 menit';

$namaPasien = $data['nama_pasien'] ?? $hasilSession['nama_pasien'] ?? 'Pasien';
$tenagaMedis = $data['nama_dokter'] ?: ($data['nama_bidan'] ?: ($hasilSession['nama_tenaga'] ?? 'Tenaga Kesehatan'));
$statusLabel = ucfirst(str_replace('_', ' ', $data['status_pendaftaran'] ?? 'menunggu'));

require_once __DIR__ . '/../includes/poin_helper.php';
$poinDidapat = (int)($hasilSession['poin_didapat'] ?? 0);
$poinTipe = $hasilSession['poin_tipe'] ?? '';
if ($poinDidapat <= 0 && $db instanceof mysqli) {
    $calcP = hitungPoinKunjungan($db, $data['jenis_pasien'] ?? 'Umum', $data['jenis_perawatan'] ?? '', $data['keluhan'] ?? '', (int)($data['id_poli'] ?? 0));
    $poinDidapat = (int)$calcP['poin'];
    $poinTipe = $calcP['tipe'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tiket Pendaftaran Online - Klinik Pratama Rumah Hanania</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:Arial,Helvetica,sans-serif;
    background:#F8F5FC;
    color:#352D3E;
    min-height:100vh;
    display:flex;
    flex-direction:column;
}
:root{
    --purple:#8E6BBE;
    --purple-dark:#7650A2;
    --button:#6A22A7;
    --soft:#EAE4F9;
    --border:#E4D9EF;
    --muted:#675F73;
}
a{text-decoration:none;color:inherit}

.result-hero{
    padding:45px 20px 85px;
    background:linear-gradient(135deg, #7650A2, #9E82C8, #EAE4F9);
    color:#fff;
    text-align:center;
}
.result-hero-inner{
    max-width:760px;
    margin:auto;
}
.result-check{
    width:56px;
    height:56px;
    border-radius:50%;
    background:#23834D;
    color:#fff;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    font-weight:900;
    margin-bottom:12px;
    box-shadow:0 8px 24px rgba(0,0,0,.15);
}
.result-hero h1{
    font-size:32px;
    font-weight:800;
    margin-bottom:8px;
}
.result-hero p{
    font-size:14px;
    line-height:1.7;
    opacity:.95;
}

.result-wrap{
    max-width:760px;
    width:calc(100% - 32px);
    margin:-50px auto 70px;
    position:relative;
    z-index:10;
    flex:1;
}
.result-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:22px;
    box-shadow:0 18px 45px rgba(79,50,147,.1);
    overflow:hidden;
}

.queue-main{
    padding:36px 24px 28px;
    text-align:center;
    background:linear-gradient(180deg, #FBF9FD 0%, #fff 100%);
    border-bottom:1px dashed #DCCFE8;
}
.queue-label{
    font-size:13px;
    font-weight:800;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:1px;
}
.queue-number{
    font-size:72px;
    font-weight:900;
    color:var(--button);
    line-height:1.1;
    margin:8px 0;
    letter-spacing:2px;
}
.queue-status{
    display:inline-block;
    padding:5px 14px;
    border-radius:999px;
    background:var(--soft);
    color:var(--button);
    font-size:12px;
    font-weight:800;
}

.arrival-note{
    margin:20px 24px 0;
    padding:16px 20px;
    background:#FAF7FD;
    border:1px solid #D6C4EC;
    border-radius:14px;
    color:var(--button);
    font-size:13px;
    line-height:1.7;
    text-align:center;
}
.arrival-note strong{
    display:block;
    font-size:15px;
    color:var(--purple-dark);
    margin-bottom:2px;
}

.estimate-strip{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    margin:20px 24px;
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
}
.estimate-item{
    padding:14px;
    text-align:center;
    background:#fff;
    border-right:1px solid var(--border);
}
.estimate-item:last-child{border-right:0}
.estimate-item.highlight{background:#FAF8FD}
.estimate-label{
    display:block;
    font-size:11px;
    font-weight:800;
    color:#7E718B;
    text-transform:uppercase;
    margin-bottom:4px;
}
.estimate-value{
    font-size:16px;
    font-weight:900;
    color:var(--purple-dark);
}

.detail-grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:14px;
    padding:20px 24px 28px;
}
.detail-item{
    background:#FBF9FD;
    border:1px solid #F0EAF4;
    border-radius:12px;
    padding:12px 16px;
}
.detail-label{
    display:block;
    font-size:11px;
    font-weight:800;
    color:#8E7E9E;
    text-transform:uppercase;
    margin-bottom:3px;
}
.detail-value{
    font-size:13px;
    font-weight:800;
    color:var(--text);
}

.result-actions{
    display:flex;
    gap:12px;
    padding:20px 24px 28px;
    border-top:1px solid #F0EAF4;
    flex-wrap:wrap;
}
.btn{
    flex:1;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:13px 20px;
    border-radius:12px;
    font-size:13px;
    font-weight:800;
    cursor:pointer;
    border:0;
    text-align:center;
    transition:all .2s;
}
.btn-primary{
    background:var(--button);
    color:#fff;
    box-shadow:0 4px 14px rgba(79,50,147,.25);
}
.btn-primary:hover{
    background:#3F237D;
    transform:translateY(-1px);
}
.btn-secondary{
    background:#EEE8F3;
    color:var(--purple-dark);
}
.btn-secondary:hover{
    background:#E2D7EA;
}

@media(max-width:720px){
    .estimate-strip{grid-template-columns:1fr;gap:0}
    .estimate-item{border-right:0;border-bottom:1px solid var(--border)}
    .detail-grid{grid-template-columns:1fr}
    .result-actions{flex-direction:column}
    .queue-number{font-size:56px}
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="result-hero">
    <div class="result-hero-inner">
        <div class="result-check">✓</div>
        <h1>Pendaftaran Berhasil!</h1>
        <p>
            Nomor antrean dan estimasi pelayanan Anda telah resmi diterbitkan.
            Silakan simpan tiket kunjungan ini dan hadir 15 menit lebih awal.
        </p>
    </div>
</section>

<main class="result-wrap">
    <div class="result-card">
        <section class="queue-main">
            <div class="queue-label">Nomor Antrean Online</div>
            <div class="queue-number"><?= e($nomorAntreanFormat) ?></div>
            <div class="queue-status">Status: <?= e($statusLabel) ?></div>
        </section>

        <div class="arrival-note">
            <strong>⏰ Rekomendasi Waktu Kedatangan: Pukul <?= e($datangLabel) ?> WIB</strong>
            Hadir 15 menit sebelum estimasi mulai pelayanan (<?= e($layaniAwalLabel) ?> WIB) untuk konfirmasi check-in di bagian pendaftaran klinik.
        </div>

        <?php if ($poinDidapat > 0): ?>
            <div style="background: linear-gradient(135deg, #F5EEFD 0%, #EDE2F9 100%); border: 1.5px solid #D8BFF5; border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:26px;">⭐</span>
                    <div>
                        <strong style="color:var(--primary, #6A22A7); font-size:14px; display:block;">
                            Selamat! Anda Mendapatkan +<?= $poinDidapat ?> Poin Reward Hanania
                        </strong>
                        <span style="font-size:12px; color:var(--text, #4A4A4A);">
                            Kategori: <strong><?= e($poinTipe) ?></strong> • Saldo poin akun Sahabat Hanania Anda otomatis bertambah!
                        </span>
                    </div>
                </div>
                <a href="reward.php" style="background:var(--button, #8E44AD); color:#fff; font-size:12px; font-weight:800; padding:8px 16px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    🎁 Cek Poin &amp; Reward &rarr;
                </a>
            </div>
        <?php endif; ?>

        <section class="estimate-strip">
            <div class="estimate-item highlight">
                <span class="estimate-label">Estimasi Waktu Tunggu</span>
                <div class="estimate-value"><?= e($estimasiMenunggu) ?></div>
            </div>
            <div class="estimate-item">
                <span class="estimate-label">Estimasi Pelayanan</span>
                <div class="estimate-value"><?= e($layaniAwalLabel) ?> – <?= e($layaniAkhirLabel) ?> WIB</div>
            </div>
            <div class="estimate-item">
                <span class="estimate-label">Waktu Hadir</span>
                <div class="estimate-value"><?= e($datangLabel) ?> WIB</div>
            </div>
        </section>

        <section class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Nama Lengkap Pasien</span>
                <div class="detail-value"><?= e($namaPasien) ?></div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Poli Pelayanan</span>
                <div class="detail-value"><?= e($data['nama_poli'] ?: 'Poli Terpilih') ?></div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tenaga Medis</span>
                <div class="detail-value"><?= e($tenagaMedis) ?></div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tanggal Kunjungan</span>
                <div class="detail-value"><?= e(formatTanggalIndonesia($data['tanggal_kunjungan'])) ?></div>
            </div>
            <div class="detail-item">
                <span class="detail-label">Surat Rujukan</span>
                <div class="detail-value">
                    <?php if ($rujukanData): ?>
                        Ada Rujukan (<?= e($rujukanData['nama_perujuk']) ?>) · 
                        <a href="../<?= e($rujukanData['file_rujukan']) ?>" target="_blank" rel="noopener" style="color:var(--button);text-decoration:underline;">Lihat File</a>
                    <?php else: ?>
                        Tidak Ada Rujukan
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-item" style="grid-column:1/-1;">
                <span class="detail-label">Keluhan / Catatan Medis</span>
                <div class="detail-value"><?= e($data['keluhan'] ?: 'Tidak ada keluhan khusus.') ?></div>
            </div>
        </section>

        <div class="result-actions">
            <a href="antrian.php" class="btn btn-primary">📋 Lihat Antrean &amp; Riwayat</a>
            <a href="daftar.php" class="btn btn-secondary">Daftar Kunjungan Baru</a>
            <a href="../index.php" class="btn btn-secondary">Kembali ke Beranda</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>