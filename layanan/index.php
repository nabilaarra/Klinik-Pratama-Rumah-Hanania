<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

// Ambil paket layanan aktif dari database
$paketLayanan = [];
if ($db instanceof mysqli) {
    $db->set_charset('utf8mb4');
    $q = $db->query("SELECT * FROM paket_layanan WHERE status = 'aktif' ORDER BY urutan ASC, id_paket ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $paketLayanan[] = $r;
        }
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan &amp; Biaya Pemeriksaan - Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Informasi layanan lengkap, biaya pemeriksaan kehamilan, USG 2D, USG 3D/4D, tarif bidan, screening kehamilan, dan informasi BPJS Klinik Hanania.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --hanania-primary: #4A1278;
            --hanania-primary-dark: #320B54;
            --hanania-primary-accent: #6A22A7;
            --hanania-primary-light: #F4EEFB;
            --hanania-lilac: #CEA2FD;
            --hanania-purple-soft: #F6EDFF;
            --hanania-purple-border: #E5D5FA;
            --hanania-accent: #E58622;
            --hanania-text: #201435;
            --hanania-muted: #4A3E5C;
            --hanania-bg: #FAF7FD;
            --card-shadow: 0 10px 30px rgba(74, 18, 120, 0.08);
            --card-shadow-hover: 0 18px 40px rgba(74, 18, 120, 0.16);
            --font-main: 'Outfit', 'Plus Jakarta Sans', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background: var(--hanania-bg);
            color: var(--hanania-text);
            line-height: 1.6;
        }

        /* HERO HEADER */
        .page-hero {
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            padding: 55px 20px 65px;
            position: relative;
            text-align: center;
            overflow: hidden;
            border-bottom: 1px solid #E5D5FA;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 140%;
            height: 200%;
            background: radial-gradient(circle, rgba(106, 34, 167, 0.04) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero-inner {
            max-width: 860px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(106, 34, 167, 0.08);
            color: #6A22A7;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            border: 1.5px solid rgba(106, 34, 167, 0.22);
            text-transform: uppercase;
        }

        .page-hero h1 {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 900;
            color: #2B1B48;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .page-hero p {
            font-size: clamp(14px, 2vw, 17px);
            color: #5D4D73;
            max-width: 680px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* CONTAINER */
        .page-container {
            width: min(1160px, calc(100% - 36px));
            margin: -30px auto 80px;
            position: relative;
            z-index: 3;
        }

        /* SECTION BOX */
        .section-box {
            background: #FFFFFF;
            border-radius: 22px;
            padding: 34px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--hanania-purple-border);
            margin-bottom: 35px;
        }

        .section-title-wrap {
            text-align: center;
            margin-bottom: 30px;
        }

        .section-title-wrap h2 {
            font-size: 26px;
            font-weight: 900;
            color: var(--hanania-primary);
            margin-bottom: 8px;
        }

        .section-title-wrap p {
            color: var(--hanania-muted);
            font-size: 14.5px;
            max-width: 700px;
            margin: 0 auto;
        }

        /* OVERVIEW GRID (7 LAYANAN UMUM) */
        .service-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 10px;
        }

        .overview-card {
            background: #FAF8FD;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 16px;
            padding: 22px;
            transition: all 0.25s ease;
        }

        .overview-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(79, 50, 147, 0.08);
            border-color: #C8B3E4;
            background: #FFFFFF;
        }

        .overview-card .icon {
            font-size: 32px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .overview-card h3 {
            font-size: 16.5px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 6px;
        }

        .overview-card p {
            font-size: 13px;
            color: var(--hanania-muted);
            line-height: 1.6;
        }

        /* TABS FILTER PAKET */
        .filter-pills {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-pill {
            padding: 8px 18px;
            border-radius: 50px;
            border: 1px solid var(--hanania-purple-border);
            background: #FFFFFF;
            color: var(--hanania-muted);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-pill:hover {
            background: var(--hanania-purple-soft);
            color: var(--hanania-primary);
        }

        .filter-pill.active {
            background: var(--hanania-primary);
            color: #FFFFFF;
            border-color: var(--hanania-primary);
            box-shadow: 0 4px 12px rgba(79, 50, 147, 0.25);
        }

        /* GRID PAKET PEMERIKSAAN & TARIF */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .pricing-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .pricing-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
            border-color: #C8B3E4;
        }

        .pricing-card-header {
            padding: 22px 24px 18px;
            background: #FAF8FD;
            border-bottom: 1px solid var(--hanania-purple-border);
        }

        .badge-bpjs {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11.5px;
            font-weight: 800;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-bpjs.yes {
            background: #E8F8EE;
            color: #1B7A3E;
            border: 1px solid #BEE8CA;
        }

        .badge-bpjs.no {
            background: #FFF4E5;
            color: #B45309;
            border: 1px solid #FDE68A;
        }

        .pricing-card-title {
            font-size: 17.5px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 6px;
            line-height: 1.35;
        }

        .pricing-doctor-name {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--hanania-text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pricing-doctor-spec {
            font-size: 12px;
            color: var(--hanania-muted);
            margin-top: 2px;
        }

        /* BIAYA BLOCK */
        .pricing-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .price-box {
            background: #FAF8FD;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--hanania-muted);
            margin-bottom: 6px;
        }

        .price-row.highlight {
            font-size: 15px;
            font-weight: 800;
            color: var(--hanania-primary);
            padding-top: 8px;
            margin-top: 8px;
            border-top: 1px dashed var(--hanania-purple-border);
        }

        .price-amount {
            font-weight: 800;
            color: var(--hanania-text);
        }

        .price-amount.main {
            font-size: 20px;
            color: var(--hanania-primary);
        }

        /* INCLUSIONS */
        .inclusions-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--hanania-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .inclusions-list {
            list-style: none;
            margin-bottom: 18px;
            display: grid;
            gap: 8px;
            flex: 1;
        }

        .inclusions-list li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            color: var(--hanania-text);
            line-height: 1.45;
        }

        .inclusions-list li span.check {
            color: #1B7A3E;
            font-weight: 900;
            font-size: 14px;
            line-height: 1;
        }

        /* SYARAT & CATATAN */
        .syarat-badge {
            background: #FEF3C7;
            color: #92400E;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #FDE68A;
        }

        .note-small {
            font-size: 11.5px;
            color: var(--hanania-muted);
            font-style: italic;
            margin-bottom: 18px;
        }

        .btn-daftar-layanan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 16px;
            border-radius: 12px;
            background: var(--hanania-primary);
            color: #FFFFFF;
            font-size: 13.5px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 50, 147, 0.2);
            text-align: center;
            margin-top: auto;
        }

        .btn-daftar-layanan:hover {
            background: var(--hanania-primary-dark);
            transform: translateY(-2px);
        }

        /* NOTICE BOX */
        .notice-banner {
            background: linear-gradient(135deg, #FAF8FD 0%, #FFFFFF 100%);
            border: 1px solid var(--hanania-purple-border);
            border-left: 5px solid var(--hanania-primary);
            border-radius: 16px;
            padding: 20px 24px;
            margin-top: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .notice-icon {
            font-size: 28px;
            line-height: 1;
            flex-shrink: 0;
        }

        .notice-text h4 {
            font-size: 15px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 4px;
        }

        .notice-text p {
            font-size: 13px;
            color: var(--hanania-muted);
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .section-box {
                padding: 22px;
            }
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<?php
$layananHeroBadge = '🩺 Pelayanan Medis Lengkap & Terjangkau';
$layananHeroJudul = 'Layanan & Biaya Klinik';
$layananHeroSubjudul = 'Informasi resmi mengenai cakupan pelayanan kesehatan, paket pemeriksaan kehamilan, USG, tarif konsultasi, dan prosedur kepesertaan BPJS di Klinik Pratama Rumah Hanania.';
$layananCakupanJudul = 'Cakupan Layanan Klinik';
$layananCakupanSubjudul = 'Pilihan layanan kesehatan menyeluruh untuk Anda dan seluruh anggota keluarga.';

$kontenLayanan = [];
if ($db instanceof mysqli) {
    $qL = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'layanan_%'");
    if ($qL) {
        while ($lr = $qL->fetch_assoc()) {
            $kontenLayanan[$lr['kunci']] = $lr['nilai'];
            if ($lr['kunci'] === 'layanan_hero_badge' && trim($lr['nilai']) !== '') $layananHeroBadge = $lr['nilai'];
            if ($lr['kunci'] === 'layanan_hero_judul' && trim($lr['nilai']) !== '') $layananHeroJudul = $lr['nilai'];
            if ($lr['kunci'] === 'layanan_hero_subjudul' && trim($lr['nilai']) !== '') $layananHeroSubjudul = $lr['nilai'];
            if ($lr['kunci'] === 'layanan_cakupan_judul' && trim($lr['nilai']) !== '') $layananCakupanJudul = $lr['nilai'];
            if ($lr['kunci'] === 'layanan_cakupan_subjudul' && trim($lr['nilai']) !== '') $layananCakupanSubjudul = $lr['nilai'];
        }
    }
}
function layVal($key, $default = '') {
    global $kontenLayanan;
    return isset($kontenLayanan[$key]) && trim((string)$kontenLayanan[$key]) !== '' ? $kontenLayanan[$key] : $default;
}
?>
<!-- HERO -->
<section class="page-hero">
    <div class="hero-inner">
        <div class="hero-badge"><?= h($layananHeroBadge) ?></div>
        <h1><?= h($layananHeroJudul) ?></h1>
        <p><?= nl2br(h($layananHeroSubjudul)) ?></p>
    </div>
</section>

<!-- MAIN CONTAINER -->
<main class="page-container">

    <!-- 1. OVERVIEW LAYANAN UTAMA -->
    <section class="section-box">
        <div class="section-title-wrap">
            <h2><span>🏥</span> <?= h($layananCakupanJudul) ?></h2>
            <p><?= h($layananCakupanSubjudul) ?></p>
        </div>

        <div class="service-overview-grid">
            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_1_icon', '🩺')) ?></span>
                <h3><?= h(layVal('layanan_card_1_judul', 'Pelayanan Umum')) ?></h3>
                <p><?= h(layVal('layanan_card_1_desc', 'Pemeriksaan kesehatan umum, diagnosis medis, penanganan keluhan dasar, dan konsultasi kesehatan berkala.')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_2_icon', '🦷')) ?></span>
                <h3><?= h(layVal('layanan_card_2_judul', 'Pelayanan Gigi & Mulut')) ?></h3>
                <p><?= h(layVal('layanan_card_2_desc', 'Pemeriksaan kesehatan gigi, penambalan, pencabutan, pembersihan karang gigi (scaling), dan perawatan gusi.')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_3_icon', '🤱')) ?></span>
                <h3><?= h(layVal('layanan_card_3_judul', 'Pelayanan Kebidanan (KIA)')) ?></h3>
                <p><?= h(layVal('layanan_card_3_desc', 'Pemeriksaan kehamilan rutin, pemantauan detak jantung janin, konseling masa nifas, dan program Keluarga Berencana (KB).')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_4_icon', '🔬')) ?></span>
                <h3><?= h(layVal('layanan_card_4_judul', 'Spesialis Kebidanan (Sp.OG)')) ?></h3>
                <p><?= h(layVal('layanan_card_4_desc', 'Konsultasi spesialis obstetri & ginekologi, pemeriksaan USG kehamilan mendalam, serta evaluasi perkembangan janin.')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_5_icon', '👶')) ?></span>
                <h3><?= h(layVal('layanan_card_5_judul', 'Persalinan 24 Jam')) ?></h3>
                <p><?= h(layVal('layanan_card_5_desc', 'Pertolongan persalinan normal didampingi bidan bersertifikat dalam ruangan bersalin yang steril dan ramah ibu melahirkan.')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_6_icon', '💳')) ?></span>
                <h3><?= h(layVal('layanan_card_6_judul', 'Pasien BPJS Kesehatan')) ?></h3>
                <p><?= h(layVal('layanan_card_6_desc', 'Melayani peserta BPJS Kesehatan dengan kartu/identitas aktif sesuai ketentuan faskes tingkat pertama (FKTP).')) ?></p>
            </div>

            <div class="overview-card">
                <span class="icon"><?= h(layVal('layanan_card_7_icon', '📲')) ?></span>
                <h3><?= h(layVal('layanan_card_7_judul', 'Pendaftaran Online')) ?></h3>
                <p><?= h(layVal('layanan_card_7_desc', 'Kemudahan memilih dokter/bidan, melihat jadwal praktik, serta mengambil nomor antrean secara online sebelum datang ke klinik.')) ?></p>
            </div>
        </div>
    </section>

    <!-- 2. DETAIL TARIF & PAKET PEMERIKSAAN KEHAMILAN / USG -->
    <section class="section-box" id="tarif-kehamilan">
        <div class="section-title-wrap">
            <h2><span>📋</span> Tarif &amp; Paket Pemeriksaan Kehamilan &amp; USG</h2>
            <p>Rincian biaya pendaftaran, biaya tindakan USG, fasilitas hasil pemeriksaan, serta ketentuan BPJS untuk setiap tenaga medis.</p>
        </div>

        <!-- FILTER PILLS -->
        <div class="filter-pills">
            <button type="button" class="filter-pill active" onclick="filterPaket('semua', this)">Semua Layanan (<?= count($paketLayanan) ?>)</button>
            <button type="button" class="filter-pill" onclick="filterPaket('Spesialis', this)">Dokter Spesialis Kebidanan &amp; Kandungan (Sp.OG)</button>
            <button type="button" class="filter-pill" onclick="filterPaket('Patricia', this)">dr. Patricia Mellroviane (BPJS)</button>
            <button type="button" class="filter-pill" onclick="filterPaket('Gita', this)">Bidan Gita Marissa (BPJS)</button>
            <button type="button" class="filter-pill" onclick="filterPaket('Screening', this)">Screening Kehamilan</button>
        </div>

        <!-- GRID KARTU TARIF -->
        <div class="pricing-grid" id="pricingGrid">
            <?php if (!empty($paketLayanan)): ?>
                <?php foreach ($paketLayanan as $p): 
                    $isBpjs = $p['status_bpjs'] === 'Bisa BPJS';
                    $inclusions = !empty($p['fasilitas_termasuk']) ? explode("\n", trim($p['fasilitas_termasuk'])) : [];
                ?>
                    <div class="pricing-card" 
                         data-staff="<?= h($p['tenaga_medis']) ?>" 
                         data-kategori="<?= h($p['kategori']) ?>"
                         data-spesialisasi="<?= h($p['spesialisasi'] ?? '') ?>"
                         data-layanan="<?= h($p['nama_layanan']) ?>">
                        
                        <!-- CARD HEADER -->
                        <div class="pricing-card-header">
                            <span class="badge-bpjs <?= $isBpjs ? 'yes' : 'no' ?>">
                                <?= $isBpjs ? '✅ Bisa BPJS' : '🔴 Pembayaran Umum (Non-BPJS)' ?>
                            </span>
                            <h3 class="pricing-card-title"><?= h($p['nama_layanan']) ?></h3>
                            <div class="pricing-doctor-name">
                                <span>🩺</span> <?= h($p['tenaga_medis']) ?>
                            </div>
                            <?php if (!empty($p['spesialisasi'])): ?>
                                <div class="pricing-doctor-spec"><?= h($p['spesialisasi']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- CARD BODY -->
                        <div class="pricing-body">
                            
                            <!-- BIAYA BOX -->
                            <div class="price-box">
                                <div class="price-row">
                                    <span>Biaya Pendaftaran</span>
                                    <span class="price-amount">Rp <?= number_format($p['biaya_daftar'], 0, ',', '.') ?></span>
                                </div>
                                <div class="price-row">
                                    <span>Biaya Pemeriksaan / USG</span>
                                    <span class="price-amount">Rp <?= number_format($p['biaya_tindakan'], 0, ',', '.') ?></span>
                                </div>
                                <div class="price-row highlight">
                                    <span>Total Biaya</span>
                                    <span class="price-amount main">
                                        Rp <?= number_format($p['biaya_daftar'] + $p['biaya_tindakan'], 0, ',', '.') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- SYARAT KHUSUS -->
                            <?php if (!empty($p['syarat_ketentuan'])): ?>
                                <div class="syarat-badge">
                                    <span>⚠️</span> <?= h($p['syarat_ketentuan']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- FASILITAS YANG TERMASUK -->
                            <?php if (!empty($inclusions)): ?>
                                <div class="inclusions-title">Fasilitas yang Termasuk:</div>
                                <ul class="inclusions-list">
                                    <?php foreach ($inclusions as $inc): 
                                        $t = trim($inc);
                                        if ($t === '') continue;
                                    ?>
                                        <li>
                                            <span class="check">✔</span>
                                            <span><?= h($t) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <!-- CATATAN -->
                            <div class="note-small">
                                * <?= h($p['catatan'] ?: 'Biaya belum termasuk obat/vitamin') ?>
                            </div>

                            <!-- CTA DAFTAR -->
                            <?php
                            $staffLink = '../pasien/daftar.php';
                            $tm = strtolower((string)$p['tenaga_medis']);
                            $nm = strtolower((string)$p['nama_layanan']);
                            $isUsg = (strpos($nm, 'usg') !== false);
                            if ($isUsg || strpos($tm, 'eddy') !== false) {
                                $staffLink = '../pasien/daftar.php?layanan=usg&dokter=4&poli=4';
                            } elseif (strpos($tm, 'patricia') !== false) {
                                $staffLink = '../pasien/daftar.php?layanan=umum&dokter=1&poli=1';
                            } elseif (strpos($tm, 'gita') !== false) {
                                $staffLink = '../pasien/daftar.php?layanan=kebidanan&bidan=2&poli=3';
                            }
                            ?>
                            <a href="<?= $staffLink ?>" class="btn-daftar-layanan" style="background:linear-gradient(135deg, #cea2fd 0%, #ad6ef5 100%);color:#2E1065;">
                                <span>🗓️</span> Daftar Konsultasi Sekarang
                            </a>

                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1;text-align:center;padding:40px;background:#fff;border-radius:16px;color:var(--hanania-muted);">
                    Data tarif layanan belum tersedia di database.
                </div>
            <?php endif; ?>
        </div>

        <!-- NOTICE BANNER PENTING -->
        <div class="notice-banner">
            <div class="notice-icon">💡</div>
            <div class="notice-text">
                <h4>Catatan Penting Pelayanan &amp; Obat</h4>
                <p>Seluruh rincian biaya tindakan, pemeriksaan USG, dan pendaftaran yang tercantum di atas <strong>belum termasuk biaya obat-obatan atau suplemen vitamin</strong> yang diresepkan oleh dokter/bidan sesuai indikasi medis pasien.</p>
                <p style="margin-top:6px;">Untuk pasien BPJS, silakan menunjukkan kartu BPJS/KIS aktif serta identitas KTP/KK pada meja pendaftaran saat kedatangan.</p>
            </div>
        </div>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function filterPaket(keyword, btn) {
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('#pricingGrid .pricing-card');
    cards.forEach(card => {
        if (keyword === 'semua') {
            card.style.display = 'flex';
        } else {
            const staff = (card.getAttribute('data-staff') || '').toLowerCase();
            const kat = (card.getAttribute('data-kategori') || '').toLowerCase();
            const spec = (card.getAttribute('data-spesialisasi') || '').toLowerCase();
            const lay = (card.getAttribute('data-layanan') || '').toLowerCase();
            const kw = keyword.toLowerCase();

            let match = false;
            if (kw === 'spesialis' || kw.includes('sp.og') || kw.includes('kandungan') || kw.includes('kebidanan') || kw === 'eddy') {
                if (staff.includes('spesialis') || staff.includes('sp.og') || staff.includes('kandungan') || spec.includes('spesialis') || spec.includes('kandungan') || lay.includes('kandungan') || staff.includes('eddy')) {
                    match = true;
                }
            } else {
                match = staff.includes(kw) || kat.includes(kw) || spec.includes(kw) || lay.includes(kw);
            }
            card.style.display = match ? 'flex' : 'none';
        }
    });
}
</script>

</body>
</html>
