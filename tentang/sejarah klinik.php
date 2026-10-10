<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$kontenSejarah = [];
if ($db instanceof mysqli) {
    $qKS = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'sejarah_%'");
    if ($qKS) {
        while ($r = $qKS->fetch_assoc()) {
            $kontenSejarah[$r['kunci']] = $r['nilai'];
        }
    }
}
if (!function_exists('sjVal')) {
    function sjVal($key, $default = '') {
        global $kontenSejarah;
        return isset($kontenSejarah[$key]) && trim((string)$kontenSejarah[$key]) !== '' ? $kontenSejarah[$key] : $default;
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
    <title>Sejarah Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Sejarah berdirinya Klinik Pratama Rumah Hanania di Kota Bogor oleh PT. Rumah Hanania Sejahtera, perjalanan dari tahun 2014 hingga 2026.">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #F8F5FC;
            color: #352D3E;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }
        .page-hero {
            padding: 50px 24px 60px;
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            text-align: center;
            border-bottom: 1px solid #E5D5FA;
        }
        .page-hero-inner {
            max-width: 820px;
            margin: auto;
        }
        .page-hero h1 {
            font-size: clamp(28px, 4vw, 38px);
            font-weight: 900;
            color: #2B1B48;
            margin: 0 0 10px;
            letter-spacing: -0.5px;
        }
        .page-hero p {
            font-size: 15px;
            color: #5D4D73;
            margin: 0;
            line-height: 1.7;
        }

        .container {
            width: min(980px, calc(100% - 32px));
            margin: -30px auto 70px;
            position: relative;
            z-index: 2;
        }

        .card {
            background: #FFFFFF;
            border: 1px solid #EAE2F3;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 12px 35px rgba(70, 45, 90, 0.07);
            margin-bottom: 28px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .section-header h2 {
            font-size: 24px;
            font-weight: 900;
            color: #6A22A7;
            margin: 0;
        }

        .story-text {
            color: #4A4353;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 18px;
        }

        .quote-box {
            background: #FAF7FE;
            border-left: 4px solid #7650A2;
            border-radius: 12px;
            padding: 18px 22px;
            margin: 22px 0;
            font-style: italic;
            color: #6A22A7;
            font-size: 14.5px;
            line-height: 1.7;
        }

        /* TIMELINE GAYA MODERN */
        .timeline {
            position: relative;
            margin: 30px 0 10px;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 10px;
            bottom: 10px;
            left: 8px;
            width: 3px;
            background: linear-gradient(to bottom, #7650A2, #E4DCF2);
            border-radius: 3px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 28px;
        }
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        .timeline-dot {
            position: absolute;
            left: -30px;
            top: 4px;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: #6A22A7;
            border: 3px solid #FFFFFF;
            box-shadow: 0 0 0 2px #7650A2;
        }
        .timeline-content {
            background: #FAF8FD;
            border: 1px solid #EFE8F6;
            border-radius: 14px;
            padding: 18px 22px;
            transition: all 0.2s;
        }
        .timeline-content:hover {
            border-color: #7650A2;
            background: #FFFFFF;
            box-shadow: 0 6px 20px rgba(79, 50, 147, 0.08);
            transform: translateX(4px);
        }
        .timeline-year {
            display: inline-block;
            background: #6A22A7;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 8px;
            letter-spacing: .5px;
        }
        .timeline-title {
            font-size: 16px;
            font-weight: 800;
            color: #352D3E;
            margin-bottom: 6px;
        }
        .timeline-desc {
            font-size: 13.5px;
            color: #675F73;
            line-height: 1.65;
            margin: 0;
        }

        .archive-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .archive-photo-card {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #EAE2F3;
            background: #FAF8FD;
            box-shadow: 0 4px 14px rgba(0,0,0,0.04);
        }
        .archive-photo-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        .archive-photo-card p {
            padding: 10px 14px;
            margin: 0;
            font-size: 12px;
            color: #554D60;
            font-weight: 700;
            text-align: center;
        }

        @media(max-width: 768px) {
            .container { padding: 16px 14px; }
            .card { padding: 20px 16px; margin-bottom: 20px; }
            .sejarah-hero-img-wrap { max-height: 240px !important; }
            .sejarah-hero-img-wrap img { max-height: 240px !important; width: 100% !important; height: auto !important; object-fit: cover !important; display: block !important; }
            .archive-photo-grid { grid-template-columns: 1fr; gap: 14px; }
            .archive-photo-card img { height: 180px; width: 100%; object-fit: cover; display: block; }
        }
        @media(max-width: 600px) {
            .card { padding: 18px 14px; }
            .timeline { padding-left: 20px; }
            .timeline-dot { left: -20px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="page-hero">
    <div class="page-hero-inner">
        <h1><?= h(sjVal('sejarah_hero_judul', 'Sejarah Klinik Pratama Rumah Hanania')) ?></h1>
        <p><?= nl2br(h(sjVal('sejarah_hero_subjudul', 'Perjalanan dedikasi pelayanan kesehatan bermutu dan berorientasi keluarga di Kota Bogor sejak tahun 2014.'))) ?></p>
    </div>
</section>

<main class="container">
    
    <!-- ARTIKEL UTAMA -->
    <article class="card">
        <div class="section-header">
            <span style="font-size:28px;">🏛️</span>
            <h2>Awal Berdiri &amp; Pendiri Klinik</h2>
        </div>
        
        <?php 
        $fotoUtamaSejarah = sjVal('sejarah_foto_utama', 'assets/img/klinik1.jpg');
        if (!empty($fotoUtamaSejarah) && file_exists(__DIR__ . '/../' . ltrim($fotoUtamaSejarah, '/'))): 
        ?>
            <div class="sejarah-hero-img-wrap" style="margin-bottom:20px;border-radius:14px;overflow:hidden;border:1px solid #EAE2F3;box-shadow:0 6px 20px rgba(70,45,90,0.06);max-height:360px;width:100%;">
                <img src="../<?= h(ltrim($fotoUtamaSejarah, '/')) ?>" alt="Sejarah Klinik Pratama Rumah Hanania" style="width:100%;height:auto;max-height:360px;object-fit:cover;display:block;" onerror="this.onerror=null;this.src='../assets/img/klinik1.jpg';">
            </div>
        <?php endif; ?>

        <?php 
        $narasiSejarah = sjVal('sejarah_kisah_awal', '');
        if ($narasiSejarah): 
        ?>
            <div class="story-text">
                <?= nl2br(h($narasiSejarah)) ?>
            </div>
        <?php else: ?>
            <p class="story-text">
                <strong>Klinik Pratama Rumah Hanania</strong> merupakan fasilitas pelayanan kesehatan swasta yang didirikan oleh <strong>PT. Rumah Hanania Sejahtera</strong> dan berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor.
            </p>

            <p class="story-text">
                Klinik ini didirikan oleh <strong>Dr. Fajar Delli Wihartiko, M.M., M.Kom.</strong> bersama <strong>Gita Marissa, S.Tr.Keb., Bdn.</strong>
            </p>
        <?php endif; ?>

        <div class="quote-box">
            "<?= h(sjVal('sejarah_quote', 'Klinik ini didirikan dengan harapan dan doa tulus agar seluruh insan yang terlibat—baik pasien, keluarga, tenaga medis, maupun seluruh tim pendukung—senantiasa berada dalam lindungan dan kasih sayang Allah SWT.')) ?>"
        </div>

        <p class="story-text">
            <?= nl2br(h(sjVal('sejarah_komitmen', 'Awal perjalanan Klinik Pratama Rumah Hanania berangkat dari komitmen kuat untuk menyediakan pelayanan kebidanan, kesehatan umum, serta kesehatan gigi dan mulut yang bermutu, aman, serta terjangkau bagi seluruh lapisan masyarakat.'))) ?>
        </p>

        <p class="story-text">
            <?= nl2br(h(sjVal('sejarah_fokus', 'Sejak awal berdiri, klinik berfokus pada peningkatan derajat kesehatan masyarakat melalui pelayanan promotif, preventif, kuratif, dan rehabilitatif secara terpadu, menghadirkan pelayanan yang ramah bagi ibu, anak, dan keluarga dengan pendekatan yang humanis dan profesional, serta mengutamakan kepuasan pasien melalui pelayanan yang cepat, tepat, dan berorientasi pada kebutuhan pasien.'))) ?>
        </p>
    </article>

    <!-- LINIMASA PERJALANAN KAMI -->
    <section class="card">
        <div class="section-header">
            <span style="font-size:28px;">⏳</span>
            <h2>Perjalanan Kami (Timeline)</h2>
        </div>
        <p style="font-size:14px;color:#675F73;margin-top:0;">Tahapan transformasi dan perkembangan layanan dari masa ke masa:</p>

        <div class="timeline">
            <!-- 2014 -->
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-year"><?= h(sjVal('sejarah_timeline_1_tahun', '2014')) ?></span>
                    <div class="timeline-title"><?= h(sjVal('sejarah_timeline_1_judul', 'Bidan Praktik Mandiri Gita Marissa')) ?></div>
                    <p class="timeline-desc">
                        <?= nl2br(h(sjVal('sejarah_timeline_1_teks', 'Dimulainya pelayanan kebidanan mandiri oleh Bidan Gita Marissa dengan pendekatan personal dan kekeluargaan untuk melayani pemeriksaan ibu hamil dan pertolongan persalinan masyarakat sekitar Sukadamai, Tanah Sareal.'))) ?>
                    </p>
                </div>
            </div>

            <!-- 2018 -->
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-year"><?= h(sjVal('sejarah_timeline_2_tahun', '2018')) ?></span>
                    <div class="timeline-title"><?= h(sjVal('sejarah_timeline_2_judul', 'Praktik Bersama Dokter Umum – Bidan')) ?></div>
                    <p class="timeline-desc">
                        <?= nl2br(h(sjVal('sejarah_timeline_2_teks', 'Pengembangan layanan kesehatan melalui kolaborasi praktik bersama antara dokter umum dan bidan guna memperluas cakupan diagnosis medis, pengobatan penyakit umum keluarga, dan tindakan preventif.'))) ?>
                    </p>
                </div>
            </div>

            <!-- 2023 -->
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                    <span class="timeline-year"><?= h(sjVal('sejarah_timeline_3_tahun', '2023')) ?></span>
                    <div class="timeline-title"><?= h(sjVal('sejarah_timeline_3_judul', 'Praktik Bersama Dokter Umum, Dokter Gigi dan Bidan')) ?></div>
                    <p class="timeline-desc">
                        <?= nl2br(h(sjVal('sejarah_timeline_3_teks', 'Layanan semakin terpadu dengan penambahan unit Pelayanan Kesehatan Gigi & Mulut, fasilitas USG kehamilan, serta kelengkapan sarana medis untuk memenuhi kebutuhan kesehatan komprehensif keluarga.'))) ?>
                    </p>
                </div>
            </div>

            <!-- 2026 -->
            <div class="timeline-item">
                <div class="timeline-dot" style="background:#10B981;box-shadow:0 0 0 2px #10B981;"></div>
                <div class="timeline-content" style="border-left:3px solid #10B981;background:#F6FBF8;">
                    <span class="timeline-year" style="background:#10B981;"><?= h(sjVal('sejarah_timeline_4_tahun', '2026')) ?></span>
                    <div class="timeline-title"><?= h(sjVal('sejarah_timeline_4_judul', 'Klinik Pratama Rumah Hanania')) ?></div>
                    <p class="timeline-desc">
                        <?= nl2br(h(sjVal('sejarah_timeline_4_teks', 'Resmi bertransformasi menjadi fasilitas kesehatan berizin klinik pratama di bawah naungan PT. Rumah Hanania Sejahtera, dilengkapi perizinan berusaha berbasis risiko pemerintah, sistem digitalisasi antrean online web & Mobile JKN, serta layanan persalinan 24 jam.'))) ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FOTO DOKUMENTASI DARI DOKUMEN RESMI (DINAMIS) -->
    <?php 
    $galeriJson = sjVal('sejarah_galeri_json', '[]');
    $galeriList = json_decode($galeriJson, true) ?: [];
    if (empty($galeriList)) {
        $fotoArsip1 = sjVal('sejarah_foto_arsip_1', 'assets/img/pdf_pages/page_3.png');
        $captionArsip1 = sjVal('sejarah_caption_arsip_1', 'Dokumen Profil Sejarah Klinik Hanania');
        $fotoArsip2 = sjVal('sejarah_foto_arsip_2', 'assets/img/pdf_pages/page_4.png');
        $captionArsip2 = sjVal('sejarah_caption_arsip_2', 'Dokumen Legalitas & Sertifikat Standar');
        if (!empty($fotoArsip1)) $galeriList[] = ['foto' => $fotoArsip1, 'caption' => $captionArsip1, 'tahun' => '2014'];
        if (!empty($fotoArsip2)) $galeriList[] = ['foto' => $fotoArsip2, 'caption' => $captionArsip2, 'tahun' => '2026'];
    }

    $activeGaleri = [];
    foreach ($galeriList as $g) {
        $f = ltrim($g['foto'] ?? '', '/');
        if ($f !== '' && file_exists(__DIR__ . '/../' . $f)) {
            $activeGaleri[] = $g;
        }
    }
    ?>
    <?php if (!empty($activeGaleri)): ?>
    <section class="card">
        <div class="section-header">
            <span style="font-size:28px;">📷</span>
            <h2>Dokumentasi &amp; Arsip Resmi Klinik</h2>
        </div>
        <p style="font-size:14px;color:#675F73;margin-top:0;">Dokumentasi arsip sejarah, piagam, dan legalitas resmi pendirian klinik:</p>

        <div class="archive-photo-grid">
            <?php foreach ($activeGaleri as $gItem): 
                $gSrc = '../' . h(ltrim($gItem['foto'], '/'));
                $gCap = h($gItem['caption'] ?? 'Dokumentasi Sejarah');
                $gThn = h($gItem['tahun'] ?? '');
            ?>
            <div class="archive-photo-card" style="cursor:pointer;" onclick="bukaLightbox('<?= $gSrc ?>', '<?= addslashes($gCap) ?>')">
                <div style="position:relative;overflow:hidden;height:200px;">
                    <img src="<?= $gSrc ?>" alt="<?= $gCap ?>" style="width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <?php if ($gThn !== ''): ?>
                    <span style="position:absolute;top:10px;left:10px;background:rgba(106,34,167,0.88);color:#fff;font-size:11px;font-weight:800;padding:3px 9px;border-radius:6px;backdrop-filter:blur(4px);">
                        <?= $gThn ?>
                    </span>
                    <?php endif; ?>
                    <div style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;font-size:11px;padding:3px 8px;border-radius:6px;display:flex;align-items:center;gap:4px;">
                        🔍 Perbesar
                    </div>
                </div>
                <p><?= $gCap ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- LIGHTBOX MODAL PREVIEW -->
<div id="sejarahLightbox" style="display:none;position:fixed;inset:0;background:rgba(15,10,25,0.85);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(5px);" onclick="tutupLightbox()">
    <div style="max-width:920px;width:100%;text-align:center;position:relative;" onclick="event.stopPropagation()">
        <button type="button" onclick="tutupLightbox()" style="position:absolute;top:-42px;right:0;background:transparent;border:0;color:#fff;font-size:32px;cursor:pointer;line-height:1;">✕</button>
        <img id="lightboxImg" src="" alt="Dokumentasi Sejarah" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,0.5);display:block;margin:0 auto 12px;background:#fff;">
        <div id="lightboxCaption" style="color:#FAF8FD;font-size:14px;font-weight:600;"></div>
    </div>
</div>

<script>
function bukaLightbox(src, cap) {
    const box = document.getElementById('sejarahLightbox');
    const img = document.getElementById('lightboxImg');
    const caption = document.getElementById('lightboxCaption');
    if (!box || !img) return;
    img.src = src;
    caption.textContent = cap || '';
    box.style.display = 'flex';
}
function tutupLightbox() {
    const box = document.getElementById('sejarahLightbox');
    if (box) box.style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') tutupLightbox();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
