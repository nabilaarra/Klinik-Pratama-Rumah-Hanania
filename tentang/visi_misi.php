<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$kontenVisi = [];
if ($db instanceof mysqli) {
    $qKV = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'visi_misi_%'");
    if ($qKV) {
        while ($r = $qKV->fetch_assoc()) {
            $kontenVisi[$r['kunci']] = $r['nilai'];
        }
    }
}
if (!function_exists('vmVal')) {
    function vmVal($key, $default = '') {
        global $kontenVisi;
        return isset($kontenVisi[$key]) && trim((string)$kontenVisi[$key]) !== '' ? $kontenVisi[$key] : $default;
    }
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visi, Misi &amp; Motto - Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Visi, Misi, dan Motto Klinik Pratama Rumah Hanania: Profesional, Aman, Ramah, dan Terpercaya.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --hanania-primary: #6A22A7;
            --hanania-primary-dark: #4A1278;
            --hanania-primary-light: #8A4BC8;
            --hanania-purple-soft: #F4EEFB;
            --hanania-purple-border: #E4D9EF;
            --hanania-accent: #E58622;
            --hanania-gold: #D97706;
            --hanania-text: #2B1B48;
            --hanania-muted: #6E667B;
            --hanania-bg: #F8F6FC;
            --card-shadow: 0 10px 30px rgba(106, 34, 167, 0.08);
            --card-shadow-hover: 0 20px 45px rgba(106, 34, 167, 0.15);
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
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* CONTAINER */
        .content-container {
            width: min(1140px, calc(100% - 36px));
            margin: -30px auto 80px;
            position: relative;
            z-index: 3;
        }

        /* VISI CARD */
        .card-visi {
            background: linear-gradient(135deg, #FAF8FD 0%, #FFFFFF 100%);
            border: 2px solid var(--hanania-purple-border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            margin-bottom: 35px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .card-visi::before {
            content: '“';
            position: absolute;
            top: -20px;
            left: 30px;
            font-size: 140px;
            color: rgba(79, 50, 147, 0.06);
            font-family: serif;
            line-height: 1;
            pointer-events: none;
        }

        .visi-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--hanania-primary);
            color: #FFFFFF;
            padding: 6px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .visi-quote {
            font-size: clamp(18px, 2.4vw, 24px);
            font-weight: 800;
            color: var(--hanania-primary);
            line-height: 1.6;
            max-width: 920px;
            margin: 0 auto;
        }

        /* MISI GRID */
        .section-heading {
            text-align: center;
            margin-bottom: 28px;
        }

        .section-heading h2 {
            font-size: 26px;
            font-weight: 900;
            color: var(--hanania-primary);
            margin-bottom: 6px;
        }

        .section-heading p {
            color: var(--hanania-muted);
            font-size: 14.5px;
        }

        .misi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
            margin-bottom: 40px;
        }

        .misi-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            display: flex;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .misi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
            border-color: #C8B3E4;
        }

        .misi-num {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--hanania-primary) 0%, var(--hanania-primary-light) 100%);
            color: #FFFFFF;
            font-size: 18px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(79, 50, 147, 0.25);
        }

        .misi-content h4 {
            font-size: 16px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 8px;
        }

        .misi-content p {
            font-size: 14px;
            color: var(--hanania-text);
            line-height: 1.7;
        }

        /* MOTTO SECTION */
        .card-motto {
            background: linear-gradient(135deg, #6A22A7 0%, #4A1278 100%);
            color: #FFFFFF;
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 16px 40px rgba(79, 50, 147, 0.22);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .card-motto::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .motto-label {
            display: inline-block;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(8px);
            padding: 5px 18px;
            border-radius: 50px;
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .motto-text {
            font-size: clamp(20px, 3vw, 30px);
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.4;
            color: #FFFFFF;
        }

        .motto-text span {
            color: #FFD56B;
        }

        .nilai-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 18px;
            padding: 24px 18px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .nilai-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--hanania-primary);
        }

        .nilai-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--hanania-purple-soft);
            color: var(--hanania-primary);
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            transition: all 0.3s ease;
        }

        .nilai-card:hover .nilai-icon {
            background: var(--hanania-primary);
            color: #FFFFFF;
            transform: rotate(10deg);
        }

        .nilai-title {
            font-size: 15px;
            font-weight: 900;
            color: var(--hanania-primary);
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .nilai-desc {
            font-size: 12px;
            color: var(--hanania-muted);
            line-height: 1.5;
        }

        /* POSTER INFOGRAFIS RESMI */
        .poster-box {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--hanania-purple-border);
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .poster-box h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 16px;
        }

        .poster-img-wrap {
            max-width: 720px;
            margin: 0 auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(0,0,0,0.1);
            border: 1px solid var(--hanania-purple-border);
            cursor: pointer;
        }

        .poster-img-wrap img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.3s ease;
        }

        .poster-img-wrap:hover img {
            transform: scale(1.02);
        }

        /* LIGHTBOX */
        .lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(14, 7, 22, 0.92);
            backdrop-filter: blur(10px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .lightbox-modal.active {
            display: flex;
        }

        .lightbox-content {
            max-width: 95vw;
            max-height: 92vh;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            position: relative;
        }

        .lightbox-content img {
            display: block;
            max-width: 100%;
            max-height: 92vh;
            object-fit: contain;
        }

        .lightbox-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(255,255,255,0.4);
            color: #fff;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            backdrop-filter: blur(6px);
            transition: all 0.2s;
        }

        .lightbox-close:hover {
            background: #E53E3E;
            transform: scale(1.1);
        }

        @media (max-width: 860px) {
            .misi-grid {
                grid-template-columns: 1fr;
            }
            .nilai-grid {
                grid-template-columns: 1fr 1fr;
            }
            .card-visi {
                padding: 28px 20px;
            }
        }

        @media (max-width: 500px) {
            .nilai-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- HERO -->
<section class="page-hero">
    <div class="hero-inner">
        <div class="hero-badge">🌟 Prinsip &amp; Landasan Layanan</div>
        <h1><?= h(vmVal('visi_misi_hero_judul', 'Visi, Misi & Motto')) ?></h1>
        <p><?= nl2br(h(vmVal('visi_misi_hero_subjudul', 'Komitmen resmi Klinik Pratama Rumah Hanania dalam memberikan pelayanan kesehatan terbaik, bermutu tinggi, dan berorientasi pada kemaslahatan masyarakat.'))) ?></p>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="content-container">

    <!-- 1. VISI -->
    <section class="card-visi">
        <div class="visi-badge">🎯 VISI KLINIK</div>
        <blockquote class="visi-quote">
            "<?= h(vmVal('visi_misi_visi', 'Menjadi Klinik Pratama yang unggul, terpercaya, dan dalam memberikan pelayanan kesehatan umum, gigi, kebidanan yang bermutu, aman, serta berorientasi pada kebutuhan masyarakat.')) ?>"
        </blockquote>
    </section>

    <!-- 2. MISI (4 BUTIR) -->
    <div class="section-heading">
        <h2><span>🚀</span> MISI PELAYANAN</h2>
        <p>Empat pilar komitmen nyata Klinik Pratama Rumah Hanania kepada seluruh masyarakat.</p>
    </div>

    <section class="misi-grid">
        <!-- MISI 1 -->
        <div class="misi-card">
            <div class="misi-num">01</div>
            <div class="misi-content">
                <h4><?= h(vmVal('visi_misi_misi_1_judul', 'Standar Pelayanan Medis Terjangkau')) ?></h4>
                <p><?= nl2br(h(vmVal('visi_misi_misi_1_teks', 'Memberikan pelayanan kesehatan umum, Kesehatan gigi dan kebidanan yang profesional, dan sesuai standar pelayanan medis dengan harga yang terjangkau.'))) ?></p>
            </div>
        </div>

        <!-- MISI 2 -->
        <div class="misi-card">
            <div class="misi-num">02</div>
            <div class="misi-content">
                <h4><?= h(vmVal('visi_misi_misi_2_judul', 'Promotif, Preventif & Kuratif')) ?></h4>
                <p><?= nl2br(h(vmVal('visi_misi_misi_2_teks', 'Menyelenggarakan pelayanan Kesehatan umum, kesehatan gigi dan mulut, serta kebidanan yang bermutu melalui tindakan promotif, preventif, dan kuratif.'))) ?></p>
            </div>
        </div>

        <!-- MISI 3 -->
        <div class="misi-card">
            <div class="misi-num">03</div>
            <div class="misi-content">
                <h4><?= h(vmVal('visi_misi_misi_3_judul', 'Aman, Ramah & Mengutamakan Pasien')) ?></h4>
                <p><?= nl2br(h(vmVal('visi_misi_misi_3_teks', 'Memberikan pelayanan yang aman dan ramah, serta mengutamakan keselamatan, kenyamanan, dan kepuasan pasien dalam setiap pelayanan.'))) ?></p>
            </div>
        </div>

        <!-- MISI 4 -->
        <div class="misi-card">
            <div class="misi-num">04</div>
            <div class="misi-content">
                <h4><?= h(vmVal('visi_misi_misi_4_judul', 'Dukungan Program Kesehatan Pemerintah')) ?></h4>
                <p><?= nl2br(h(vmVal('visi_misi_misi_4_teks', 'Mendukung program pemerintah di bidang kesehatan ibu dan anak serta kesehatan Masyarakat.'))) ?></p>
            </div>
        </div>
    </section>

    <!-- 3. MOTTO -->
    <section class="card-motto">
        <div class="motto-label">✨ MOTTO RESMI</div>
        <div class="motto-text">
            "<?= h(vmVal('visi_misi_motto', 'Melayani dengan Senang Hati, Menebar Keberkahan untuk Semua')) ?>"
        </div>
    </section>

    

    <section class="nilai-grid">
        <div class="nilai-card">
            <div class="nilai-icon">🩺</div>
            <div class="nilai-title"><?= h(vmVal('visi_misi_nilai_1_judul', 'PROFESIONAL')) ?></div>
            <div class="nilai-desc"><?= h(vmVal('visi_misi_nilai_1_teks', 'Pelayanan kompeten sesuai standar etika dan mutu medis tertinggi.')) ?></div>
        </div>

        <div class="nilai-card">
            <div class="nilai-icon">🛡️</div>
            <div class="nilai-title"><?= h(vmVal('visi_misi_nilai_2_judul', 'AMAN')) ?></div>
            <div class="nilai-desc"><?= h(vmVal('visi_misi_nilai_2_teks', 'Prioritas keselamatan pasien dengan sarana dan prosedur yang steril.')) ?></div>
        </div>

        <div class="nilai-card">
            <div class="nilai-icon">😊</div>
            <div class="nilai-title"><?= h(vmVal('visi_misi_nilai_3_judul', 'RAMAH')) ?></div>
            <div class="nilai-desc"><?= h(vmVal('visi_misi_nilai_3_teks', 'Pelayanan sepenuh hati dengan senyum, keramahan, dan empati.')) ?></div>
        </div>

        <div class="nilai-card">
            <div class="nilai-icon">🤝</div>
            <div class="nilai-title"><?= h(vmVal('visi_misi_nilai_4_judul', 'TERPERCAYA')) ?></div>
            <div class="nilai-desc"><?= h(vmVal('visi_misi_nilai_4_teks', 'Integritas dan transparansi diagnosa untuk kesehatan keluarga Anda.')) ?></div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
