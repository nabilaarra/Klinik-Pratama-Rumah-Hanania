<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$kontenProfil = [];
if ($db instanceof mysqli) {
    $qKP = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'profil_klinik_%'");
    if ($qKP) {
        while ($row = $qKP->fetch_assoc()) {
            $kontenProfil[$row['kunci']] = $row['nilai'];
        }
    }
}
if (!function_exists('pkVal')) {
    function pkVal($key, $default = '') {
        global $kontenProfil;
        return isset($kontenProfil[$key]) && trim((string)$kontenProfil[$key]) !== '' ? $kontenProfil[$key] : $default;
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
    <title>Profil &amp; Sertifikat Akreditasi Paripurna - Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Profil resmi Klinik Pratama Rumah Hanania Bogor, Terakreditasi Paripurna oleh Kemenkes RI &amp; Lafkespri, legalitas izin operasional berusaha berbasis risiko.">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #F8F5FC;
            color: #352D3E;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }
        .hero {
            padding: 50px 24px 60px;
            background: linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
            color: #2B1B48;
            text-align: center;
            border-bottom: 1px solid #E5D5FA;
        }
        .hero-inner {
            max-width: 820px;
            margin: auto;
        }
        .hero h1 {
            font-size: clamp(28px, 4vw, 38px);
            font-weight: 900;
            color: #2B1B48;
            margin: 0 0 10px;
            letter-spacing: -0.5px;
        }
        .hero p {
            font-size: 15px;
            color: #5D4D73;
            margin: 0;
            line-height: 1.7;
        }

        .container {
            width: min(1000px, calc(100% - 32px));
            margin: -30px auto 70px;
            position: relative;
            z-index: 2;
        }

        .card {
            background: #FFFFFF;
            border: 1px solid #EAE2F3;
            border-radius: 20px;
            padding: 34px;
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

        .motto-banner {
            background: linear-gradient(135deg, #FAF7FD 0%, #F3EDFA 100%);
            border: 1.5px dashed #7650A2;
            border-radius: 14px;
            padding: 16px 20px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            color: #6A22A7;
        }
        .motto-banner span {
            color: #E58E26;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }
        .data-item {
            padding: 16px;
            background: #FAF8FD;
            border: 1px solid #EAE0F5;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .data-item:hover {
            border-color: #7650A2;
            background: #FFFFFF;
        }
        .data-item strong {
            display: block;
            color: #6A22A7;
            font-size: 13.5px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .data-item span {
            color: #554D60;
            font-size: 13px;
            line-height: 1.6;
        }

        /* SERTIFIKAT AKREDITASI PARIPURNA */
        .akreditasi-box {
            background: linear-gradient(145deg, #FFFCF5 0%, #FBF7ED 100%);
            border: 2px solid #D4AF37;
            border-radius: 18px;
            padding: 28px;
            margin-top: 10px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.12);
        }
        .akreditasi-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, #B8860B, #D4AF37);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .4px;
            box-shadow: 0 3px 10px rgba(184, 134, 11, 0.25);
            margin-bottom: 14px;
        }

        /* SERTIFIKAT STANDAR & LEGALITAS */
        .legalitas-box {
            background: #FAF9FD;
            border: 2px solid #7650A2;
            border-radius: 16px;
            padding: 24px;
            margin-top: 20px;
        }
        .legalitas-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #10B981;
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cert-display {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 24px;
            align-items: center;
            margin-top: 18px;
        }
        .cert-img-wrap {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #D8CAEB;
            box-shadow: 0 6px 20px rgba(70, 45, 90, 0.10);
            background: #fff;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .cert-img-wrap:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(70, 45, 90, 0.18);
            border-color: #7650A2;
        }
        .cert-img-wrap img {
            width: 100%;
            height: auto;
            display: block;
        }
        .cert-zoom-hint {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(40, 20, 60, 0.78);
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            pointer-events: none;
        }

        .cert-info-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
        }
        .cert-info-row {
            display: flex;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 1px dashed #E4DAF0;
        }
        .cert-info-row span:first-child {
            color: #7650A2;
            font-weight: 700;
        }
        .cert-info-row span:last-child {
            color: #352D3E;
            font-weight: 800;
            text-align: right;
        }

        .service-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .service-tag {
            background: #FFFFFF;
            border: 1px solid #7650A2;
            color: #6A22A7;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        /* LIGHTBOX MODAL */
        .lightbox-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 10, 35, 0.85);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(5px);
        }
        .lightbox-content {
            position: relative;
            max-width: 920px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .lightbox-content img {
            max-width: 100%;
            max-height: 88vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            object-fit: contain;
        }
        .lightbox-close {
            position: absolute;
            top: -42px;
            right: 0;
            background: #FFFFFF;
            color: #333;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media(max-width: 768px) {
            .container { padding: 16px 14px; }
            .data-grid { grid-template-columns: 1fr; }
            .cert-display { grid-template-columns: 1fr !important; }
            .card { padding: 20px 16px; margin-bottom: 20px; }
            .akreditasi-box { padding: 18px 14px; }
            .legalitas-box { padding: 18px 14px; }
            .profil-hero-img-wrap { max-height: 240px !important; }
            .profil-hero-img-wrap img { max-height: 240px !important; width: 100% !important; height: auto !important; object-fit: cover !important; display: block !important; }
            .cert-img-wrap { max-width: 100% !important; margin-bottom: 14px; }
            .cert-img-wrap img { width: 100% !important; height: auto !important; display: block !important; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="hero">
    <div class="hero-inner">
        <h1><?= h(pkVal('profil_klinik_hero_judul', 'Profil & Sertifikat Akreditasi Klinik')) ?></h1>
        <p><?= nl2br(h(pkVal('profil_klinik_hero_subjudul', 'Identitas resmi faskes, bukti predikat kelulusan akreditasi Paripurna, serta legalitas standar operasional Klinik Pratama Rumah Hanania.'))) ?></p>
    </div>
</section>

<main class="container">

    <!-- TENTANG KLINIK -->
    <article class="card">
        <div class="section-header">
            <span style="font-size:28px;">🏥</span>
            <h2>Tentang Klinik Pratama Rumah Hanania</h2>
        </div>

        <?php 
        $fotoProfilKlinik = pkVal('profil_klinik_foto', 'assets/img/klinik3.jpg');
        $realProfilFoto = __DIR__ . '/../' . ltrim($fotoProfilKlinik, '/');
        if (!file_exists($realProfilFoto)) {
            $fotoProfilKlinik = 'assets/img/klinik3.jpg';
        }
        if (!empty($fotoProfilKlinik)): 
        ?>
            <div class="profil-hero-img-wrap" style="margin-bottom:20px;border-radius:14px;overflow:hidden;border:1px solid #EAE2F3;box-shadow:0 6px 20px rgba(70,45,90,0.06);max-height:360px;width:100%;">
                <img src="../<?= h(ltrim($fotoProfilKlinik, '/')) ?>" alt="Gedung Klinik Pratama Rumah Hanania" style="width:100%;height:auto;max-height:360px;object-fit:cover;display:block;" onerror="this.onerror=null;this.src='../assets/img/klinik3.jpg';">
            </div>
        <?php endif; ?>

        <?php 
        $narasiProfil = pkVal('profil_klinik_teks_tentang', '');
        if ($narasiProfil): 
        ?>
            <div style="color:#4A4353;font-size:15px;line-height:1.8;margin-bottom:16px;">
                <?= nl2br(h($narasiProfil)) ?>
            </div>
        <?php else: ?>
            <p style="color:#4A4353;font-size:15px;line-height:1.8;">
                <strong>Klinik Pratama Rumah Hanania</strong> adalah fasilitas pelayanan kesehatan swasta yang berada di bawah naungan <strong>PT. Rumah Hanania Sejahtera</strong>. Klinik ini berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor, didirikan oleh <strong>Dr. Fajar Delli Wihartiko, M.M., M.Kom.</strong> bersama <strong>Gita Marissa, S.Tr.Keb., Bdn.</strong>
            </p>
        <?php endif; ?>

        <div class="motto-banner">
            ✨ Semboyan Resmi: <em>"<?= h(pkVal('profil_klinik_motto', 'Melayani dengan Hati, Menebar Kebaikan untuk Semua')) ?>"</em>
        </div>

        <?php if (!$narasiProfil): ?>
        <p style="color:#4A4353;font-size:15px;line-height:1.8;">
            Klinik Pratama Rumah Hanania menyelenggarakan pelayanan kesehatan terpadu meliputi pelayanan kesehatan umum, kesehatan gigi dan mulut, kebidanan &amp; KIA, persalinan 24 jam, serta konsultasi spesialis obstetri &amp; ginekologi (Sp.OG) yang didukung sarana medis steril dan sistem antrean digitalisasi online berbasis web dan Mobile JKN.
        </p>
        <?php endif; ?>

        <div class="data-grid">
            <div class="data-item">
                <strong>🏛️ Badan Usaha / Pengelola</strong>
                <span><?= h(pkVal('profil_klinik_badan_usaha', 'PT. Rumah Hanania Sejahtera')) ?></span>
            </div>
            <div class="data-item">
                <strong>👨‍⚕️👩‍⚕️ Pendiri Faskes</strong>
                <span><?= h(pkVal('profil_klinik_pendiri', 'Dr. Fajar Delli Wihartiko, M.M., M.Kom. & Gita Marissa, S.Tr.Keb., Bdn.')) ?></span>
            </div>
            <div class="data-item">
                <strong>🏷️ Jenis Fasilitas Kesehatan</strong>
                <span><?= h(pkVal('profil_klinik_jenis_faskes', 'Klinik Pratama Rawat Jalan (Dengan Fasilitas Persalinan 24 Jam)')) ?></span>
            </div>
            <div class="data-item">
                <strong>📍 Lokasi Operasional</strong>
                <span style="display:block;margin-bottom:8px;font-weight:600;">
                    <?= h(pkVal('profil_klinik_alamat', 'Jl. Desa Sukadamai, Kp. Situpete RT 03 / RW 08, Kel. Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165')) ?>
                </span>
                <a href="https://maps.app.goo.gl/524k7ABe1fckKgpt6" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;background:#6A22A7;color:#fff;padding:7px 14px;border-radius:9px;font-size:12px;font-weight:800;text-decoration:none;box-shadow:0 3px 10px rgba(79,50,147,0.2);transition:all .2s;">
                    📍 Buka Rute di Google Maps &rarr;
                </a>
            </div>
            <div class="data-item">
                <strong>⏰ Jam Operasional</strong>
                <span><?= h(pkVal('profil_klinik_jam_operasional', 'Poli: 08.00 – 20.00 WIB • Persalinan & Darurat: 24 Jam Penuh')) ?></span>
            </div>
            <div class="data-item">
                <strong>📞 Kontak &amp; WhatsApp Resmi</strong>
                <?php 
                $wa1 = pkVal('profil_klinik_wa_1', '0828 1070 3839');
                $wa1Clean = preg_replace('/[^0-9]/', '', $wa1);
                if (str_starts_with($wa1Clean, '0')) $wa1Clean = '62' . substr($wa1Clean, 1);
                $wa2 = pkVal('profil_klinik_wa_2', '0857 1417 8178');
                $wa2Clean = preg_replace('/[^0-9]/', '', $wa2);
                if (str_starts_with($wa2Clean, '0')) $wa2Clean = '62' . substr($wa2Clean, 1);
                $ig = pkVal('profil_klinik_ig', '@rumahhanania_');
                $igHandle = ltrim($ig, '@');
                ?>
                <div style="display:flex;flex-direction:column;gap:7px;margin-top:6px;">
                    <?php if ($wa1): ?>
                    <a href="https://wa.me/<?= h($wa1Clean) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:7px;color:#1E7E34;font-size:13px;font-weight:700;text-decoration:none;">
                        <span style="background:#E8F8EE;padding:3px 8px;border-radius:6px;border:1px solid #C3E6CB;">💬 WA 1: <?= h($wa1) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($wa2): ?>
                    <a href="https://wa.me/<?= h($wa2Clean) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:7px;color:#1E7E34;font-size:13px;font-weight:700;text-decoration:none;">
                        <span style="background:#E8F8EE;padding:3px 8px;border-radius:6px;border:1px solid #C3E6CB;">💬 WA 2: <?= h($wa2) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($ig): ?>
                    <a href="https://instagram.com/<?= h($igHandle) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:7px;color:#C13584;font-size:13px;font-weight:700;text-decoration:none;">
                        <span style="background:#FDEBF3;padding:3px 8px;border-radius:6px;border:1px solid #F5C6CB;">📷 IG: <?= h($ig) ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </article>

    <!-- SERTIFIKAT AKREDITASI PARIPURNA (KEMENKES RI & LAFKESPRI) -->
    <article class="card" id="akreditasi">
        <div class="section-header">
            <span style="font-size:28px;">🏆</span>
            <h2>Sertifikat Akreditasi Fasilitas Kesehatan</h2>
        </div>

        <p style="color:#4A4353;font-size:14.5px;line-height:1.7;">
            Klinik Pratama Rumah Hanania telah menjalani evaluasi mutu menyeluruh dan secara resmi memperoleh pengakuan kelulusan predikat <strong>PARIPURNA</strong> dari Kementerian Kesehatan Republik Indonesia dan Lembaga Akreditasi Fasilitas Kesehatan Primer (Lafkespri).
        </p>

        <div class="akreditasi-box">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                <span class="akreditasi-badge">
                    ⭐ TERAKREDITASI PARIPURNA (PREDIKAT TERTINGGI)
                </span>
                <span style="font-size:12px;font-weight:800;color:#8B6B15;background:rgba(212,175,55,0.2);padding:4px 10px;border-radius:12px;">
                    Masa Berlaku: <?= h(pkVal('profil_klinik_masa_akreditasi', '2026 s.d 2031 (5 Tahun)')) ?>
                </span>
            </div>

            <h3 style="margin:6px 0 6px;font-size:18px;color:#3F2D0B;font-weight:900;">
                Sertifikat Akreditasi Kemenkes RI &amp; Lafkespri
            </h3>
            <p style="font-size:13px;color:#755A1A;margin:0 0 16px;">
                Nomor Sertifikat Resmi: <strong><?= h(pkVal('profil_klinik_nomor_akreditasi', 'YM.02.01/D/674/2026')) ?></strong>
            </p>

            <?php 
            $fotoAkreditasi = pkVal('profil_klinik_foto_akreditasi', 'assets/img/sertifikat_akreditasi_paripurna.jpg');
            if (empty($fotoAkreditasi) || !file_exists(__DIR__ . '/../' . ltrim($fotoAkreditasi, '/'))) {
                $fotoAkreditasi = 'assets/img/sertifikat_akreditasi_paripurna.jpg';
            }
            ?>
            <div class="cert-display" style="<?= empty($fotoAkreditasi) ? 'grid-template-columns:1fr;' : '' ?>">
                <!-- GAMBAR SERTIFIKAT AKREDITASI RESMI -->
                <?php if (!empty($fotoAkreditasi) && file_exists(__DIR__ . '/../' . ltrim($fotoAkreditasi, '/'))): ?>
                <div class="cert-img-wrap" onclick="bukaLightbox('../<?= h(ltrim($fotoAkreditasi, '/')) ?>', 'Sertifikat Akreditasi Paripurna Klinik Pratama Rumah Hanania')" title="Klik untuk memperbesar gambar sertifikat">
                    <img src="../<?= h(ltrim($fotoAkreditasi, '/')) ?>" alt="Sertifikat Akreditasi Paripurna Klinik Pratama Rumah Hanania" onerror="this.onerror=null;this.src='../assets/img/sertifikat_akreditasi_paripurna.jpg'">
                    <div class="cert-zoom-hint">🔍 Klik untuk Memperbesar</div>
                </div>
                <?php endif; ?>

                <div>
                    <div class="cert-info-list">
                        <div class="cert-info-row">
                            <span>Fasilitas Pelayanan</span>
                            <span><?= h(pkVal('profil_klinik_badan_usaha', 'Klinik Pratama Rumah Hanania')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Status Kelulusan</span>
                            <span style="color:#B8860B;font-size:14px;">PARIPURNA ⭐⭐⭐⭐⭐</span>
                        </div>
                        <div class="cert-info-row">
                            <span>Nomor Registrasi</span>
                            <span><?= h(pkVal('profil_klinik_nomor_akreditasi', 'YM.02.01/D/674/2026')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Masa Berlaku</span>
                            <span><?= h(pkVal('profil_klinik_masa_akreditasi', '2026 s.d 2031 (5 Tahun)')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Lembaga Akreditasi</span>
                            <span><?= h(pkVal('profil_klinik_lembaga_akreditasi', 'Lafkespri')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Regulator</span>
                            <span><?= h(pkVal('profil_klinik_regulator', 'Kementerian Kesehatan RI')) ?></span>
                        </div>
                    </div>

                    <div style="margin-top:14px;font-size:12.5px;color:#554D60;line-height:1.6;background:#fff;padding:12px;border-radius:10px;border:1px solid #EAE0F5;">
                        🌟 <em>Akreditasi <strong>PARIPURNA</strong> merupakan pengakuan mutu dan keselamatan pasien tertinggi dari pemerintah bagi fasilitas kesehatan yang telah memenuhi 100% kepatuhan standar pelayanan, pencegahan infeksi, dan tata kelola medis modern.</em>
                    </div>

                    <div style="margin-top:14px;font-size:11.5px;color:#6B5E7D;line-height:1.5;">
                        ✍️ <em>Ditandatangani secara elektronik oleh:</em><br>
                        • <strong>Dr. Drs. Chazali H Situmorang, Apt, M.Sc, CIRB</strong> (Ketua Lafkespri)<br>
                        • <strong>dr. Maria Endang Sumiwi, MPH</strong> (Direktur Jenderal Kesehatan Primer dan Komunitas Kemenkes RI)
                    </div>
                </div>
            </div>
        </div>
    </article>

    <!-- LEGALITAS & SERTIFIKAT STANDAR PERIZINAN BERBASIS RISIKO -->
    <article class="card">
        <div class="section-header">
            <span style="font-size:28px;">📜</span>
            <h2>Legalitas &amp; Sertifikat Standar Klinik</h2>
        </div>

        <p style="color:#4A4353;font-size:14.5px;line-height:1.7;">
            Klinik Pratama Rumah Hanania telah mengantongi izin resmi operasional dari Pemerintah Republik Indonesia melalui sistem Perizinan Berusaha Berbasis Risiko (KBLI Subsektor Kesehatan).
        </p>

        <div class="legalitas-box">
            <span class="legalitas-badge">✅ Izin Resmi Terdaftar &amp; Terverifikasi</span>
            <h3 style="margin:0 0 8px;font-size:17px;color:#6A22A7;font-weight:900;">
                Pemerintah Republik Indonesia – Perizinan Berusaha Berbasis Risiko
            </h3>
            <p style="font-size:13px;color:#675F73;margin:0 0 14px;">
                Lampiran Sertifikat Standar Nomor: <strong><?= h(pkVal('profil_klinik_legalitas_nomor', '28102100123850003')) ?></strong> (KBLI: <?= h(pkVal('profil_klinik_legalitas_kbli', '86105')) ?>)
            </p>

            <?php 
            $fotoLegalitas = pkVal('profil_klinik_foto_legalitas', 'assets/img/pdf_pages/page_4.png');
            if (empty($fotoLegalitas) || !file_exists(__DIR__ . '/../' . ltrim($fotoLegalitas, '/'))) {
                $fotoLegalitas = 'assets/img/pdf_pages/page_4.png';
            }
            ?>
            <div class="cert-display" style="<?= empty($fotoLegalitas) ? 'grid-template-columns:1fr;' : '' ?>">
                <?php if (!empty($fotoLegalitas) && file_exists(__DIR__ . '/../' . ltrim($fotoLegalitas, '/'))): ?>
                <div class="cert-img-wrap" onclick="bukaLightbox('../<?= h(ltrim($fotoLegalitas, '/')) ?>', 'Sertifikat Standar Legalitas Klinik Pratama Rumah Hanania')" title="Klik untuk memperbesar dokumen sertifikat">
                    <img src="../<?= h(ltrim($fotoLegalitas, '/')) ?>" alt="Sertifikat Standar Legalitas Klinik Pratama Rumah Hanania" onerror="this.onerror=null;this.src='../assets/img/pdf_pages/page_4.png'">
                    <div class="cert-zoom-hint">🔍 Klik untuk Memperbesar</div>
                </div>
                <?php endif; ?>

                <div>
                    <div class="cert-info-list">
                        <div class="cert-info-row">
                            <span>Status Permohonan</span>
                            <span><?= h(pkVal('profil_klinik_legalitas_status', 'Perizinan Baru')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Nama Pelaku Usaha</span>
                            <span><?= h(pkVal('profil_klinik_legalitas_pelaku', 'PERORANGAN GITA MARISSA')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Nama Fasilitas</span>
                            <span><?= h(pkVal('profil_klinik_legalitas_faskes', 'Klinik Pratama Rumah Hanania')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Kemampuan Pelayanan</span>
                            <span><?= h(pkVal('profil_klinik_legalitas_kemampuan', 'Klinik Pratama')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Penyelenggaraan</span>
                            <span><?= h(pkVal('profil_klinik_legalitas_penyelenggaraan', 'Rawat Jalan')) ?></span>
                        </div>
                        <div class="cert-info-row">
                            <span>Kesesuaian Standar</span>
                            <span style="color:#10B981;"><?= h(pkVal('profil_klinik_legalitas_kesesuaian', 'Telah Memenuhi Syarat Usaha')) ?></span>
                        </div>
                    </div>

                    <div style="margin-top:14px;font-size:12.5px;color:#554D60;line-height:1.6;background:#fff;padding:12px;border-radius:10px;border:1px solid #EAE0F5;">
                        ⚖️ <em>Berdasarkan hasil penilaian kesesuaian, Klinik ini <strong>Telah Memenuhi Persyaratan Standar Usaha Klinik</strong> sesuai Peraturan Menteri Kesehatan tentang Standar Kegiatan Usaha subsektor kesehatan.</em>
                    </div>

                    <div style="margin-top:14px;">
                        <strong style="font-size:12.5px;color:#6A22A7;display:block;margin-bottom:6px;">Rincian Pelayanan Resmi Izin Berusaha:</strong>
                        <div class="service-tags">
                            <span class="service-tag">1. Pelayanan Gawat Darurat</span>
                            <span class="service-tag">2. Pelayanan Umum</span>
                            <span class="service-tag">3. Pelayanan Gigi</span>
                            <span class="service-tag">4. Persalinan 24 Jam</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </article>

</main>

<!-- LIGHTBOX MODAL DOKUMEN -->
<div id="lightboxModal" class="lightbox-modal" onclick="tutupLightbox(event)">
    <div class="lightbox-content">
        <button type="button" class="lightbox-close" onclick="tutupLightboxDirect()">✕</button>
        <img id="lightboxImg" src="" alt="Pratinjau Dokumen">
    </div>
</div>

<script>
function bukaLightbox(src, alt) {
    const modal = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImg');
    if (modal && img) {
        img.src = src;
        img.alt = alt || 'Dokumen Resmi';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function tutupLightboxDirect() {
    const modal = document.getElementById('lightboxModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function tutupLightbox(e) {
    if (e.target.id === 'lightboxModal') {
        tutupLightboxDirect();
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupLightboxDirect();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
