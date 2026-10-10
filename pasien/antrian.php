<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak tersedia.');
}
$db->set_charset('utf8mb4');
$base = '/Klinik-Pratama-Rumah-Hanania';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tanggalIndonesia')) {
    function tanggalIndonesia($value) {
        $date = DateTime::createFromFormat('Y-m-d', (string)$value);
        if (!$date) return (string)$value;
        $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return (int)$date->format('d') . ' ' . $bulan[(int)$date->format('m') - 1] . ' ' . $date->format('Y');
    }
}

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);
$idPasien = (int)($_SESSION['id_pasien'] ?? 0);
$isLoggedIn = ($idUser > 0);

if ($idPasien <= 0 && $idUser > 0) {
    $stmt = $db->prepare('SELECT id_pasien FROM pasien WHERE id_user = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $idPasien = (int)($row['id_pasien'] ?? 0);
        $stmt->close();
    }
}
if ($idPasien <= 0 && $isLoggedIn) {
    $idPasien = (int)($_SESSION['hasil_pendaftaran']['id_pasien'] ?? 0);
}

$riwayat = [];

if ($isLoggedIn && $idPasien > 0) {
    $stmt = $db->prepare("
        SELECT p.id_pendaftaran, p.id_pasien, p.id_poli, p.id_dokter, p.id_bidan,
               p.tanggal_kunjungan, p.jam_kunjungan, p.jenis_pasien, p.keluhan,
               p.status_pendaftaran, p.sumber_pendaftaran,
               po.nama_poli, d.nama_dokter, b.nama_bidan,
               a.nomor_antrean, a.estimasi_waktu,
               r.nama_perujuk, r.file_rujukan
        FROM pendaftaran p
        LEFT JOIN poli po ON po.id_poli = p.id_poli
        LEFT JOIN dokter d ON d.id_dokter = p.id_dokter
        LEFT JOIN bidan b ON b.id_bidan = p.id_bidan
        LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
        LEFT JOIN rujukan_pendaftaran r ON r.id_pendaftaran = p.id_pendaftaran
        WHERE p.id_pasien = ?
        ORDER BY p.tanggal_kunjungan DESC, p.id_pendaftaran DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $idPasien);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $riwayat[] = $row;
        }
        $stmt->close();
    }
}

foreach ($riwayat as &$item) {
    $source = ($item['sumber_pendaftaran'] ?? '') === 'online' ? 'online' : 'offline';
    $item['sumber_label'] = $source === 'online' ? 'Online' : 'Offline';

    // Nomor Antrean Tunggal
    $number = (int)($item['nomor_antrean'] ?? 0);
    if ($number <= 0) {
        $number = ($source === 'online') ? 3 : 1;
    }
    $item['nomor_antrean_display'] = str_pad((string)$number, 3, '0', STR_PAD_LEFT);

    // Hitung antrean di depan
    $aheadStaff = (int)($item['id_dokter'] ?? 0);
    $aheadBidan = (int)($item['id_bidan'] ?? 0);
    $staffCond = ($aheadStaff > 0) ? "id_dokter = ?" : "id_bidan = ?";
    $targetStaff = ($aheadStaff > 0) ? $aheadStaff : $aheadBidan;
    $countBefore = 0;

    $stmtAhead = $db->prepare("
        SELECT COUNT(*) AS total
        FROM pendaftaran
        WHERE tanggal_kunjungan = ? AND id_poli = ? AND $staffCond AND status_pendaftaran != 'dibatalkan' AND id_pendaftaran < ?
    ");
    if ($stmtAhead) {
        $poli = (int)$item['id_poli'];
        $idReg = (int)$item['id_pendaftaran'];
        $stmtAhead->bind_param('siii', $item['tanggal_kunjungan'], $poli, $targetStaff, $idReg);
        $stmtAhead->execute();
        $cntR = $stmtAhead->get_result()->fetch_assoc();
        $countBefore = (int)($cntR['total'] ?? 0);
        $stmtAhead->close();
    }
    $item['orang_di_depan'] = $countBefore;

    // Estimasi waktu pelayanan
    $jamKunjunganStr = substr((string)$item['jam_kunjungan'], 0, 5);
    $tsStart = strtotime($item['tanggal_kunjungan'] . ' ' . $jamKunjunganStr);
    if ($tsStart !== false) {
        $item['estimasi_awal'] = date('H:i', $tsStart);
        $item['estimasi_akhir'] = date('H:i', $tsStart + (20 * 60));
        $item['waktu_datang'] = date('H:i', $tsStart - (15 * 60));
    } else {
        $item['estimasi_awal'] = '-';
        $item['estimasi_akhir'] = '-';
        $item['waktu_datang'] = '-';
    }

    $item['waktu_tunggu_label'] = '± 15 menit';
}
unset($item);

$namaPasien = $_SESSION['nama_lengkap'] ?? $_SESSION['user']['nama_lengkap'] ?? 'Pasien';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Antrean &amp; Riwayat Pendaftaran - Klinik Pratama Rumah Hanania</title>
<style>
*{box-sizing:border-box}
body{
    margin:0;
    background:#F8F5FC;
    color:#352D3E;
    font-family:Arial,Helvetica,sans-serif;
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

.history-page{
    max-width:1080px;
    width:calc(100% - 32px);
    margin:auto;
    padding:40px 0 70px;
    flex:1;
}
.history-head{
    margin-bottom:28px;
}
.history-head h1{
    margin:0 0 8px;
    color:var(--purple-dark);
    font-size:28px;
    font-weight:800;
}
.history-head p{
    margin:0;
    color:var(--muted);
    font-size:14px;
    line-height:1.6;
}

.visit-list{
    display:grid;
    gap:20px;
}
.visit-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(79,50,147,.06);
}
.visit-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    padding:18px 24px;
    border-bottom:1px solid #F2ECF7;
    background:#FBF9FD;
}
.visit-date{
    color:var(--purple-dark);
    font-weight:800;
    font-size:15px;
}
.visit-status{
    padding:6px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:800;
    text-transform:capitalize;
}
.status-menunggu{background:#FFF6E5;color:#996000}
.status-terdaftar{background:var(--soft);color:var(--button)}
.status-selesai{background:#E9F9EE;color:#1B7A3E}
.status-dibatalkan{background:#FFEBEB;color:#B72B2B}

.queue-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    padding:20px 24px;
    background:#FAF7FD;
    border-bottom:1px solid #F0EAF4;
    flex-wrap:wrap;
}
.queue-number-box{
    display:flex;
    align-items:baseline;
    gap:8px;
}
.queue-number-label{
    font-size:11px;
    font-weight:800;
    color:var(--muted);
    text-transform:uppercase;
}
.queue-number{
    color:var(--button);
    font-size:42px;
    font-weight:900;
    line-height:1;
}
.queue-meta{
    color:var(--muted);
    font-size:13px;
    line-height:1.7;
    text-align:right;
}
.queue-meta strong{
    color:var(--purple-dark);
}

.detail-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:1px;
    background:#F2ECF7;
}
.detail{
    background:#fff;
    padding:14px 20px;
}
.detail-label{
    display:block;
    margin-bottom:4px;
    color:#8E7E9E;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
}
.detail-value{
    font-size:13px;
    font-weight:700;
    color:var(--text);
}

.empty-history{
    padding:40px 20px;
    background:#fff;
    border:1px dashed var(--border);
    border-radius:18px;
    color:var(--muted);
    text-align:center;
    font-size:14px;
}

@media(max-width:768px){
    .history-page{padding-top:25px}
    .history-head h1{font-size:24px}
    .detail-grid{grid-template-columns:1fr}
    .queue-row{flex-direction:column;align-items:flex-start}
    .queue-meta{text-align:left}
}

.antrian-hero {
    background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
    color: #2B1B48;
    padding: 48px 24px 54px;
    text-align: center;
    border-bottom: 1px solid #E5D5FA;
}
.antrian-hero-inner {
    max-width: 820px;
    margin: 0 auto;
}
.antrian-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(106, 34, 167, 0.08);
    color: #6A22A7;
    padding: 6px 18px;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 14px;
    border: 1.5px solid rgba(106, 34, 167, 0.22);
    text-transform: uppercase;
}
.antrian-hero h1 {
    margin: 0 0 10px;
    color: #2B1B48;
    font-size: clamp(26px, 3.8vw, 36px);
    font-weight: 900;
    letter-spacing: -0.5px;
}
.antrian-hero p {
    margin: 0 auto;
    color: #5D4D73;
    font-size: 15px;
    line-height: 1.7;
    max-width: 640px;
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="antrian-hero">
    <div class="antrian-hero-inner">
        <div class="antrian-hero-badge">
            <span>📋</span> Monitoring Antrean Pasien
        </div>
        <h1>Antrean &amp; Riwayat Pendaftaran</h1>
        <p>Pantau nomor antrean aktif, perkiraan jam pelayanan dokter, serta riwayat pemeriksaan kesehatan Anda di Klinik Pratama Rumah Hanania.</p>
    </div>
</section>

<main class="history-page">
    <?php if (!$isLoggedIn): ?>
        <div class="empty-history" style="background:#fff; border:1.5px dashed #D1BCE8; border-radius:24px; padding:48px 24px; text-align:center; max-width:680px; margin:20px auto 40px; box-shadow:0 12px 35px rgba(106,34,167,0.06);">
            <div style="width:72px; height:72px; background:#F4EDFB; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:36px; margin:0 auto 16px;">
                🔒
            </div>
            <h2 style="font-size:22px; font-weight:900; color:#2B1B48; margin:0 0 10px;">
                Riwayat Antrean Membutuhkan Akun Pasien
            </h2>
            <p style="font-size:14.5px; color:#5D4D73; line-height:1.7; max-width:520px; margin:0 auto 24px;">
                Untuk dapat melihat status nomor antrean, estimasi waktu pelayanan, serta riwayat pemeriksaan kesehatan Anda di Klinik Pratama Rumah Hanania, Anda harus memiliki akun terdaftar dan masuk (login) terlebih dahulu.
            </p>
            <div style="display:flex; justify-content:center; align-items:center; gap:12px; flex-wrap:wrap;">
                <a href="<?= $base ?>/login.php?redirect=antrian" style="display:inline-flex; align-items:center; gap:8px; padding:12px 26px; border-radius:999px; background:#6A22A7; color:#fff; font-weight:800; font-size:14px; text-decoration:none; box-shadow:0 6px 18px rgba(106,34,167,0.28); transition:0.2s;">
                    🔑 Masuk (Login) Akun
                </a>
                <a href="<?= $base ?>/daftar_akun.php" style="display:inline-flex; align-items:center; gap:8px; padding:11px 24px; border-radius:999px; background:#FFFFFF; color:#6A22A7; border:1.5px solid #D2C1EC; font-weight:800; font-size:14px; text-decoration:none; transition:0.2s;">
                    📝 Buat Akun Pasien Baru
                </a>
            </div>
        </div>
    <?php elseif (empty($riwayat)): ?>
        <div class="empty-history">
            <div style="font-size:36px;margin-bottom:10px;">📋</div>
            <strong>Belum ada riwayat pendaftaran kunjungan.</strong>
            <p style="margin-top:6px;font-size:13px;">Silakan daftarkan jadwal pemeriksaan Anda melalui menu pendaftaran online.</p>
            <a href="daftar.php" style="display:inline-block;margin-top:16px;padding:10px 20px;border-radius:10px;background:var(--button);color:#fff;font-weight:700;font-size:13px;">
                Daftar Kunjungan Sekarang &rarr;
            </a>
        </div>
    <?php else: ?>
        <div class="visit-list">
            <?php foreach ($riwayat as $item): 
                $tenaga = $item['nama_dokter'] ?: ($item['nama_bidan'] ?: 'Tenaga Kesehatan');
                $st = $item['status_pendaftaran'] ?? 'menunggu';
                $stClass = 'status-' . $st;
            ?>
            <article class="visit-card">
                <div class="visit-top">
                    <div class="visit-date">
                        📅 <?= e(tanggalIndonesia($item['tanggal_kunjungan'])) ?>
                    </div>
                    <span class="visit-status <?= $stClass ?>">
                        <?= e(ucfirst($st)) ?>
                    </span>
                </div>

                <div class="queue-row">
                    <div>
                        <div class="queue-number-label">Nomor Antrean Online</div>
                        <!-- NOMOR ANTREAN TUNGGAL (MISAL 003) -->
                        <div class="queue-number"><?= e($item['nomor_antrean_display']) ?></div>
                    </div>
                    <div class="queue-meta">
                        <div>Estimasi Pelayanan: <strong><?= e($item['estimasi_awal']) ?> – <?= e($item['estimasi_akhir']) ?> WIB</strong></div>
                        <div>Waktu Kehadiran: <strong><?= e($item['waktu_datang']) ?> WIB</strong> (15 menit sebelum pelayanan)</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px;">Waktu tunggu: <?= e($item['waktu_tunggu_label']) ?></div>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail">
                        <span class="detail-label">Tenaga Medis</span>
                        <div class="detail-value"><?= e($tenaga) ?></div>
                    </div>

                    <div class="detail">
                        <span class="detail-label">Poli Pelayanan</span>
                        <div class="detail-value"><?= e($item['nama_poli'] ?: 'Poli Terpilih') ?></div>
                    </div>

                    <div class="detail">
                        <span class="detail-label">Sumber &amp; ID Pendaftaran</span>
                        <div class="detail-value"><?= e($item['sumber_label']) ?> · #<?= e($item['id_pendaftaran']) ?></div>
                    </div>

                    <div class="detail">
                        <span class="detail-label">Surat Rujukan</span>
                        <div class="detail-value">
                            <?php if (!empty($item['file_rujukan'])): ?>
                                Ada (<?= e($item['nama_perujuk'] ?: 'Perujuk') ?>) · 
                                <a href="../<?= e($item['file_rujukan']) ?>" target="_blank" rel="noopener" style="color:var(--button);text-decoration:underline;">Lihat File</a>
                            <?php else: ?>
                                Tidak Ada Rujukan
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="detail" style="grid-column: 2 / -1;">
                        <span class="detail-label">Keluhan / Catatan Pasien</span>
                        <div class="detail-value"><?= e($item['keluhan'] ?: '-') ?></div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
