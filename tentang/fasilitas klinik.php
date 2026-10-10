<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$fasilitasList = [];
if ($db instanceof mysqli) {
    $db->set_charset('utf8mb4');
    $q = @$db->query("SELECT id_fasilitas, nama_fasilitas, deskripsi, foto, foto_galeri, urutan FROM fasilitas_klinik ORDER BY urutan ASC, id_fasilitas ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $galeri = [];
            if (!empty($r['foto_galeri'])) {
                $dec = json_decode($r['foto_galeri'], true);
                if (is_array($dec)) {
                    $galeri = array_values(array_filter($dec));
                }
            }
            if (empty($galeri) && !empty($r['foto'])) {
                $galeri = [$r['foto']];
            }
            if (empty($galeri)) {
                $galeri = ['assets/img/klinik1.jpg'];
            }
            $r['galeri_array'] = $galeri;
            $fasilitasList[] = $r;
        }
    }
}

$kontenFasilitas = [];
if ($db instanceof mysqli) {
    $qKF = @$db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'fasilitas_%'");
    if ($qKF) {
        while ($kr = $qKF->fetch_assoc()) {
            $kontenFasilitas[$kr['kunci']] = $kr['nilai'];
        }
    }
}
if (!function_exists('fasVal')) {
    function fasVal($key, $default = '') {
        global $kontenFasilitas;
        return isset($kontenFasilitas[$key]) && trim((string)$kontenFasilitas[$key]) !== '' ? $kontenFasilitas[$key] : $default;
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
    <title>Fasilitas Klinik - Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Jelajahi fasilitas pelayanan medis lengkap, higienis, dan ramah pasien di Klinik Pratama Rumah Hanania.">
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
            --hanania-text: #2B1B48;
            --hanania-muted: #6E667B;
            --hanania-bg: #F8F6FC;
            --card-shadow: 0 10px 30px rgba(106, 34, 167, 0.08);
            --card-shadow-hover: 0 20px 45px rgba(106, 34, 167, 0.16);
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
            min-height: 100vh;
        }

        /* HERO HEADER */
        .facility-hero {
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            padding: 55px 20px 65px;
            position: relative;
            overflow: hidden;
            text-align: center;
            border-bottom: 1px solid #E5D5FA;
        }

        .facility-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 140%;
            height: 200%;
            background: radial-gradient(circle, rgba(106, 34, 167, 0.04) 0%, transparent 60%);
            pointer-events: none;
        }

        .facility-hero-inner {
            max-width: 900px;
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
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            border: 1.5px solid rgba(106, 34, 167, 0.22);
            text-transform: uppercase;
        }

        .facility-hero h1 {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 900;
            color: #2B1B48;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .facility-hero p {
            font-size: clamp(14px, 2vw, 17px);
            color: #5D4D73;
            max-width: 680px;
            margin: 0 auto;
            font-weight: 400;
            line-height: 1.7;
        }

        /* MAIN CONTENT */
        .facility-container {
            width: min(1180px, calc(100% - 36px));
            margin: -30px auto 80px;
            position: relative;
            z-index: 3;
        }

        /* STATS / INFO STRIP */
        .info-strip {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 20px 30px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--hanania-purple-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 35px;
        }

        .strip-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .strip-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--hanania-purple-soft);
            color: var(--hanania-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .strip-text h4 {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin: 0;
        }

        .strip-text span {
            font-size: 12.5px;
            color: var(--hanania-muted);
        }

        /* GRID FASILITAS */
        .facility-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 28px;
        }

        /* KARTU FASILITAS */
        .facility-card {
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--hanania-purple-border);
            box-shadow: var(--card-shadow);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            position: relative;
        }

        .facility-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
            border-color: #C8B3E4;
        }

        /* SLIDER CONTAINER */
        .slider-viewport {
            width: 100%;
            height: 230px;
            position: relative;
            background: #1C132B;
            overflow: hidden;
        }

        .slider-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.45s ease-in-out, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            transform: scale(1);
        }

        .slider-img.active {
            opacity: 1;
            transform: scale(1.03);
        }

        /* OVERLAYS & BADGES */
        .slider-badge-count {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(20, 10, 35, 0.65);
            backdrop-filter: blur(8px);
            color: #FFFFFF;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 5;
        }

        .slider-hover-tip {
            position: absolute;
            bottom: 12px;
            left: 14px;
            background: rgba(79, 50, 147, 0.85);
            backdrop-filter: blur(6px);
            color: #FFFFFF;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 700;
            opacity: 0;
            transform: translateY(6px);
            transition: all 0.25s ease;
            pointer-events: none;
            z-index: 5;
        }

        .facility-card:hover .slider-hover-tip {
            opacity: 1;
            transform: translateY(0);
        }

        /* SLIDER DOTS */
        .slider-dots {
            position: absolute;
            bottom: 12px;
            right: 14px;
            display: flex;
            gap: 5px;
            z-index: 5;
            background: rgba(0, 0, 0, 0.35);
            padding: 4px 8px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
        }

        .slider-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.45);
            transition: all 0.3s ease;
        }

        .slider-dot.active {
            background: #FFFFFF;
            width: 16px;
            border-radius: 6px;
        }

        /* CARD BODY */
        .facility-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .facility-body h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin-bottom: 8px;
            line-height: 1.35;
        }

        .facility-body p {
            font-size: 13.5px;
            color: var(--hanania-muted);
            line-height: 1.65;
            margin-bottom: 16px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .facility-footer {
            padding-top: 14px;
            border-top: 1px solid var(--hanania-purple-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-lihat-detail {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 800;
            color: var(--hanania-primary);
            background: var(--hanania-purple-soft);
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid var(--hanania-purple-border);
            transition: all 0.2s ease;
        }

        .facility-card:hover .btn-lihat-detail {
            background: var(--hanania-primary);
            color: #FFFFFF;
            border-color: var(--hanania-primary);
        }

        .btn-lihat-detail span {
            transition: transform 0.2s ease;
        }

        .facility-card:hover .btn-lihat-detail span {
            transform: translateX(3px);
        }

        /* MODAL GALERI INTERAKTIF */
        .gallery-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(18, 8, 30, 0.82);
            backdrop-filter: blur(12px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.25s ease;
        }

        .gallery-modal-overlay.active {
            display: flex;
        }

        .gallery-modal-box {
            background: #FFFFFF;
            width: 100%;
            max-width: 960px;
            max-height: 92vh;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            display: flex;
            flex-direction: column;
            animation: zoomIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-top-bar {
            padding: 16px 24px;
            background: #FAF8FD;
            border-bottom: 1px solid var(--hanania-purple-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title-group h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--hanania-primary);
            margin: 0;
        }

        .modal-title-group span {
            font-size: 12px;
            color: var(--hanania-muted);
            font-weight: 600;
        }

        .modal-close-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid var(--hanania-purple-border);
            background: #FFFFFF;
            color: var(--hanania-muted);
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-close-btn:hover {
            background: #FDEAEA;
            color: #B72B2B;
            border-color: #F8D7DA;
            transform: rotate(90deg);
        }

        .modal-main-content {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            overflow-y: auto;
            flex: 1;
        }

        /* MODAL VIEWER SISI KIRI */
        .modal-viewer-side {
            background: #0E0717;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .viewer-stage {
            width: 100%;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            background: #140A22;
        }

        .viewer-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 12px;
            transition: opacity 0.25s ease;
        }

        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #FFFFFF;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .nav-btn:hover {
            background: var(--hanania-primary);
            border-color: var(--hanania-primary);
            transform: translateY(-50%) scale(1.08);
        }

        .nav-btn.prev { left: 12px; }
        .nav-btn.next { right: 12px; }

        .viewer-counter-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(6px);
            color: #FFFFFF;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        /* THUMBNAIL STRIP */
        .modal-thumbs-strip {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            width: 100%;
            overflow-x: auto;
            padding: 4px 2px;
            justify-content: center;
        }

        .thumb-item {
            width: 60px;
            height: 46px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid transparent;
            opacity: 0.55;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
            background: #251838;
        }

        .thumb-item.active {
            border-color: var(--hanania-accent);
            opacity: 1;
            transform: scale(1.05);
        }

        .thumb-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* MODAL DETAIL SISI KANAN */
        .modal-detail-side {
            padding: 30px;
            display: flex;
            flex-direction: column;
            background: #FFFFFF;
        }

        .facility-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }

        .facility-tag {
            background: var(--hanania-purple-soft);
            color: var(--hanania-primary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .modal-desc-heading {
            font-size: 13px;
            font-weight: 800;
            color: var(--hanania-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .modal-desc-text {
            font-size: 14px;
            color: var(--hanania-text);
            line-height: 1.75;
            margin-bottom: 24px;
            flex: 1;
            white-space: pre-line;
        }

        .modal-feature-list {
            list-style: none;
            margin-bottom: 24px;
            display: grid;
            gap: 10px;
        }

        .modal-feature-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--hanania-muted);
            font-weight: 600;
        }

        .modal-feature-list li span {
            color: #2E7D32;
            font-size: 14px;
        }

        .modal-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--hanania-primary);
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(79, 50, 147, 0.25);
        }

        .modal-action-btn:hover {
            background: var(--hanania-primary-dark);
            transform: translateY(-2px);
        }

        /* ANIMATIONS */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes zoomIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* RESPONSIVE */
        @media (max-width: 860px) {
            .slider-hover-tip {
                display: none !important;
            }
            .slider-viewport {
                touch-action: pan-y;
            }
            .modal-main-content {
                grid-template-columns: 1fr;
            }
            .viewer-stage {
                height: 280px;
            }
            .info-strip {
                flex-direction: column;
                align-items: flex-start;
            }
            .facility-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- HERO BANNER -->
<section class="facility-hero">
    <div class="facility-hero-inner">
        <div class="hero-badge"><?= h(fasVal('fasilitas_hero_badge', '✨ Standar Mutu & Kenyamanan Medis')) ?></div>
        <h1><?= h(fasVal('fasilitas_hero_judul', 'Fasilitas Pelayanan Klinik')) ?></h1>
        <p><?= nl2br(h(fasVal('fasilitas_hero_subjudul', 'Klinik Pratama Rumah Hanania menyediakan sarana dan prasarana medis yang lengkap, bersih, modern, dan higienis demi kenyamanan serta keselamatan pasien.'))) ?></p>
    </div>
</section>

<!-- MAIN CONTENT WRAPPER -->
<main class="facility-container">

    <!-- INFO STRIP -->
    <div class="info-strip">
        <div class="strip-item">
            <div class="strip-icon">🏥</div>
            <div class="strip-text">
                <h4>Fasilitas Lengkap &amp; Terpadu</h4>
                <span>Poli Umum, Gigi, KIA, USG, Farmasi &amp; Tindakan</span>
            </div>
        </div>
        <div class="strip-item">
            <div class="strip-icon">🛡️</div>
            <div class="strip-text">
                <h4>Steril &amp; Higienis</h4>
                <span>Protokol keselamatan pasien &amp; standar pencegahan infeksi (PPI)</span>
            </div>
        </div>
    </div>

    <!-- GRID FASILITAS -->
    <div class="facility-grid" id="facilityGrid">
        <?php if (!empty($fasilitasList)): ?>
            <?php foreach ($fasilitasList as $idx => $f): 
                $photos = $f['galeri_array'];
                $totalFoto = count($photos);
                $encodedData = htmlspecialchars(json_encode($f, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
                <article class="facility-card" 
                         data-facility='<?= $encodedData ?>'
                         data-total="<?= $totalFoto ?>"
                         onclick="openFacilityModal(this)">
                    
                    <!-- SLIDER VIEWPORT -->
                    <div class="slider-viewport" data-index="0">
                        <?php foreach ($photos as $pIdx => $photoPath): 
                            $cleanPath = ltrim($photoPath, '/');
                        ?>
                            <img src="../<?= h($cleanPath) ?>" 
                                 alt="<?= h($f['nama_fasilitas']) ?> - Foto <?= $pIdx + 1 ?>" 
                                 class="slider-img <?= $pIdx === 0 ? 'active' : '' ?>" 
                                 data-slide="<?= $pIdx ?>"
                                 onerror="this.src='../assets/img/klinik1.jpg';">
                        <?php endforeach; ?>

                        <!-- BADGE TOTAL FOTO -->
                        <div class="slider-badge-count">
                            <span>📷</span> <?= $totalFoto ?> Foto
                        </div>

                        <!-- HOVER TIP DESKTOP -->
                        <?php if ($totalFoto > 1): ?>
                            <div class="slider-hover-tip">
                                ✨ Arahkan kursor untuk slide
                            </div>
                        <?php endif; ?>

                        <!-- SLIDER DOTS -->
                        <?php if ($totalFoto > 1): ?>
                            <div class="slider-dots">
                                <?php for ($d = 0; $d < $totalFoto; $d++): ?>
                                    <span class="slider-dot <?= $d === 0 ? 'active' : '' ?>" data-dot="<?= $d ?>"></span>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- CARD BODY -->
                    <div class="facility-body">
                        <h3><?= h($f['nama_fasilitas']) ?></h3>
                        <p><?= nl2br(h($f['deskripsi'])) ?></p>

                        <div class="facility-footer">
                            <span style="font-size:12px;color:var(--hanania-muted);font-weight:600;">
                                Klinik Rumah Hanania
                            </span>
                            <div class="btn-lihat-detail">
                                Lihat Galeri <span>&rarr;</span>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column:1/-1;text-align:center;padding:60px;background:#fff;border-radius:20px;box-shadow:var(--card-shadow);">
                <span style="font-size:48px;">🏥</span>
                <h3 style="margin-top:14px;color:var(--hanania-primary);">Belum Ada Fasilitas yang Ditampilkan</h3>
                <p style="color:var(--hanania-muted);font-size:14px;">Fasilitas klinik dapat dikelola melalui panel admin konten.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- MODAL GALERI DETAIL FASILITAS -->
<div class="gallery-modal-overlay" id="galleryModalOverlay" onclick="closeFacilityModalOnBackdrop(event)">
    <div class="gallery-modal-box" id="galleryModalBox">
        
        <!-- MODAL TOP BAR -->
        <div class="modal-top-bar">
            <div class="modal-title-group">
                <h3 id="modalFacilityTitle">Ruang Pemeriksaan Umum</h3>
                <span>🏥 Fasilitas Pelayanan Klinik Pratama Rumah Hanania</span>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeFacilityModal()" title="Tutup Galeri">
                ✕
            </button>
        </div>

        <!-- MODAL MAIN CONTENT -->
        <div class="modal-main-content">
            
            <!-- SISI KIRI: VIEWER BESAR -->
            <div class="modal-viewer-side">
                <div class="viewer-stage" id="viewerStage">
                    <img src="../assets/img/klinik1.jpg" alt="" class="viewer-img" id="modalMainImg">
                    
                    <!-- COUNTER -->
                    <div class="viewer-counter-badge" id="modalCounterBadge">
                        Foto 1 dari 3
                    </div>

                    <!-- TOMBOL PREV & NEXT -->
                    <button type="button" class="nav-btn prev" onclick="prevModalPhoto(event)" title="Foto Sebelumnya">
                        &#10094;
                    </button>
                    <button type="button" class="nav-btn next" onclick="nextModalPhoto(event)" title="Foto Berikutnya">
                        &#10095;
                    </button>
                </div>

                <!-- THUMBNAIL STRIP -->
                <div class="modal-thumbs-strip" id="modalThumbsStrip">
                    <!-- Thumbs injected via JS -->
                </div>
            </div>

            <!-- SISI KANAN: DESKRIPSI & INFO -->
            <div class="modal-detail-side">
                <div class="facility-tags">
                    <span class="facility-tag">🏥 Pelayanan Medis</span>
                    <span class="facility-tag">✨ Higienis &amp; Steril</span>
                    <span class="facility-tag">❄️ Nyaman &amp; Ber-AC</span>
                </div>

                <div class="modal-desc-heading">Keterangan Fasilitas</div>
                <div class="modal-desc-text" id="modalFacilityDesc">
                    Deskripsi lengkap mengenai sarana dan fungsi fasilitas ini bagi pasien klinik.
                </div>

                <ul class="modal-feature-list">
                    <li><span>✔</span> Didukung tenaga kesehatan dokter &amp; bidan berpengalaman</li>
                    <li><span>✔</span> Peralatan diagnosis medis terawat dan terkalibrasi berkala</li>
                    <li><span>✔</span> Menjaga kenyamanan serta privasi pasien selama tindakan</li>
                </ul>

                <a href="../pasien/daftar.php" class="modal-action-btn">
                    <span>🗓️</span> Daftar Konsultasi Sekarang
                </a>
            </div>

        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ============================================================
// 1. DESKTOP HOVER AUTO-SLIDE CARD SYSTEM
// ============================================================
document.querySelectorAll('.facility-card').forEach(card => {
    const total = parseInt(card.dataset.total || '1', 10);
    if (total <= 1) return;

    const viewport = card.querySelector('.slider-viewport');
    const images = card.querySelectorAll('.slider-img');
    const dots = card.querySelectorAll('.slider-dot');
    let currentIndex = 0;
    let hoverInterval = null;

    function showSlide(index) {
        currentIndex = (index + total) % total;
        images.forEach((img, i) => {
            img.classList.toggle('active', i === currentIndex);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentIndex);
        });
        viewport.dataset.index = currentIndex;
    }

    // Hover mouse enter: start automatic cycling every 1300ms
    card.addEventListener('mouseenter', () => {
        clearInterval(hoverInterval);
        hoverInterval = setInterval(() => {
            showSlide(currentIndex + 1);
        }, 1300);
    });

    // Hover mouse leave: return to first photo smoothly
    card.addEventListener('mouseleave', () => {
        clearInterval(hoverInterval);
        showSlide(0);
    });

    // Interactive dots clicking inside card
    dots.forEach((dot, dIdx) => {
        dot.addEventListener('click', (e) => {
            e.stopPropagation();
            clearInterval(hoverInterval);
            showSlide(dIdx);
        });
    });

    // Touch Swipe langsung pada gambar kartu di Mobile
    let cardTouchStartX = 0;
    let cardTouchStartY = 0;
    let cardSwiped = false;

    viewport.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            cardTouchStartX = e.touches[0].clientX;
            cardTouchStartY = e.touches[0].clientY;
            cardSwiped = false;
        }
    }, { passive: true });

    viewport.addEventListener('touchmove', (e) => {
        if (e.touches.length === 1) {
            const dx = e.touches[0].clientX - cardTouchStartX;
            const dy = e.touches[0].clientY - cardTouchStartY;
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 20) {
                cardSwiped = true;
            }
        }
    }, { passive: true });

    viewport.addEventListener('touchend', (e) => {
        if (cardSwiped) {
            const touchEndX = e.changedTouches[0].clientX;
            const diff = touchEndX - cardTouchStartX;
            if (diff < -30) {
                // Geser ke kiri -> foto berikutnya
                showSlide(currentIndex + 1);
            } else if (diff > 30) {
                // Geser ke kanan -> foto sebelumnya
                showSlide(currentIndex - 1);
            }
            // Tandai kartu baru saja digeser agar tidak memicu pembukaan modal galeri
            card.dataset.justSwiped = '1';
            setTimeout(() => {
                delete card.dataset.justSwiped;
            }, 350);
        }
    }, { passive: true });
});

// ============================================================
// 2. MODAL GALERI FOTO INTERAKTIF
// ============================================================
let currentModalPhotos = [];
let currentModalIdx = 0;

function openFacilityModal(cardElement) {
    if (cardElement && cardElement.dataset && cardElement.dataset.justSwiped === '1') {
        return; // Jangan buka modal jika pengguna sedang menggeser foto di mobile
    }
    try {
        const raw = cardElement.getAttribute('data-facility');
        const data = JSON.parse(raw);
        
        currentModalPhotos = data.galeri_array || [];
        if (currentModalPhotos.length === 0 && data.foto) {
            currentModalPhotos = [data.foto];
        }
        if (currentModalPhotos.length === 0) {
            currentModalPhotos = ['assets/img/klinik1.jpg'];
        }
        currentModalIdx = 0;

        document.getElementById('modalFacilityTitle').textContent = data.nama_fasilitas || 'Fasilitas Klinik';
        document.getElementById('modalFacilityDesc').textContent = data.deskripsi || 'Fasilitas prima yang disediakan oleh Klinik Pratama Rumah Hanania.';

        renderModalThumbs();
        updateModalPhoto(0);

        const overlay = document.getElementById('galleryModalOverlay');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    } catch(err) {
        console.error('Gagal membuka modal fasilitas:', err);
    }
}

function renderModalThumbs() {
    const strip = document.getElementById('modalThumbsStrip');
    strip.innerHTML = '';
    currentModalPhotos.forEach((src, idx) => {
        const clean = src.replace(/^\/+/, '');
        const div = document.createElement('div');
        div.className = 'thumb-item ' + (idx === 0 ? 'active' : '');
        div.onclick = (e) => {
            e.stopPropagation();
            updateModalPhoto(idx);
        };
        div.innerHTML = `<img src="../${clean}" alt="Thumb ${idx+1}" onerror="this.src='../assets/img/klinik1.jpg';">`;
        strip.appendChild(div);
    });
}

function updateModalPhoto(idx) {
    currentModalIdx = (idx + currentModalPhotos.length) % currentModalPhotos.length;
    const imgEl = document.getElementById('modalMainImg');
    const counter = document.getElementById('modalCounterBadge');
    
    imgEl.style.opacity = '0.3';
    setTimeout(() => {
        const cleanPath = currentModalPhotos[currentModalIdx].replace(/^\/+/, '');
        imgEl.src = '../' + cleanPath;
        imgEl.style.opacity = '1';
    }, 120);

    counter.textContent = `Foto ${currentModalIdx + 1} dari ${currentModalPhotos.length}`;

    // Update active thumb
    const thumbs = document.querySelectorAll('#modalThumbsStrip .thumb-item');
    thumbs.forEach((t, i) => {
        t.classList.toggle('active', i === currentModalIdx);
        if (i === currentModalIdx) {
            t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    });
}

function prevModalPhoto(e) {
    if (e) e.stopPropagation();
    updateModalPhoto(currentModalIdx - 1);
}

function nextModalPhoto(e) {
    if (e) e.stopPropagation();
    updateModalPhoto(currentModalIdx + 1);
}

function closeFacilityModal() {
    const overlay = document.getElementById('galleryModalOverlay');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

function closeFacilityModalOnBackdrop(e) {
    if (e.target.id === 'galleryModalOverlay') {
        closeFacilityModal();
    }
}

// Keyboard navigation
window.addEventListener('keydown', (e) => {
    const overlay = document.getElementById('galleryModalOverlay');
    if (!overlay.classList.contains('active')) return;

    if (e.key === 'Escape') {
        closeFacilityModal();
    } else if (e.key === 'ArrowRight') {
        nextModalPhoto();
    } else if (e.key === 'ArrowLeft') {
        prevModalPhoto();
    }
});

// Touch Swipe on mobile
let touchStartX = 0;
let touchEndX = 0;
const stage = document.getElementById('viewerStage');
stage.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
}, { passive: true });

stage.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    if (touchEndX < touchStartX - 40) {
        nextModalPhoto(); // swipe left
    } else if (touchEndX > touchStartX + 40) {
        prevModalPhoto(); // swipe right
    }
}, { passive: true });
</script>

</body>
</html>
