<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

$kontenStruktur = [];
if ($db instanceof mysqli) {
    $qS = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci LIKE 'struktur_%'");
    if ($qS) {
        while ($row = $qS->fetch_assoc()) {
            $kontenStruktur[$row['kunci']] = $row['nilai'];
        }
    }
}
if (!function_exists('stVal')) {
    function stVal($key, $default = '') {
        global $kontenStruktur;
        return isset($kontenStruktur[$key]) && trim((string)$kontenStruktur[$key]) !== '' ? $kontenStruktur[$key] : $default;
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
    <title>Struktur Organisasi - Klinik Pratama Rumah Hanania</title>
    <meta name="description" content="Bagan dan susunan organisasi kepemimpinan serta tim penanggung jawab pelayanan Klinik Pratama Rumah Hanania.">
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

        /* SECTION CARD */
        .section-box {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--hanania-purple-border);
            margin-bottom: 35px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--hanania-purple-soft);
        }

        .section-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--hanania-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* POSTER BAGAN */
        .diagram-wrapper {
            position: relative;
            background: #FAF8FD;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--hanania-purple-border);
            text-align: center;
            padding: 16px;
            box-shadow: inset 0 2px 10px rgba(79, 50, 147, 0.04);
        }

        .diagram-img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .diagram-img:hover {
            transform: scale(1.01);
        }

        .diagram-action-bar {
            display: flex;
            justify-content: center;
            margin-top: 18px;
            gap: 12px;
        }

        .btn-diagram {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--hanania-primary);
            color: #FFFFFF;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-diagram:hover {
            background: var(--hanania-primary-dark);
            transform: translateY(-2px);
        }

        /* HIERARCHY TREE */
        .leadership-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .leader-card {
            background: linear-gradient(135deg, #FAF8FD 0%, #FFFFFF 100%);
            border: 2px solid var(--hanania-purple-border);
            border-radius: 16px;
            padding: 22px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .leader-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #6A22A7, #8A4BC8);
        }

        .leader-card.primary {
            border-color: #D9B3FF;
            box-shadow: 0 12px 28px rgba(79, 50, 147, 0.12);
        }

        .leader-card.primary::before {
            background: linear-gradient(90deg, #E58622, #F59E0B);
        }

        .leader-icon {
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
            border: 2px solid #FFFFFF;
            box-shadow: 0 4px 10px rgba(79, 50, 147, 0.1);
        }

        .leader-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--hanania-primary);
            margin-bottom: 6px;
        }

        .leader-name {
            font-size: 16px;
            font-weight: 800;
            color: var(--hanania-text);
            line-height: 1.4;
        }

        /* 7 PENANGGUNG JAWAB (PJ) */
        .pj-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 35px;
        }

        .pj-card {
            background: #FFFFFF;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.25s ease;
        }

        .pj-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(79, 50, 147, 0.08);
            border-color: #C8B3E4;
        }

        .pj-badge {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--hanania-purple-soft);
            color: var(--hanania-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .pj-info h5 {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--hanania-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .pj-info p {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--hanania-text);
            line-height: 1.35;
        }

        /* 3 UNIT PELAKSANA */
        .units-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .unit-box {
            background: #FAF8FD;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .unit-box-header {
            background: var(--hanania-primary);
            color: #FFFFFF;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .unit-box-header h4 {
            font-size: 15px;
            font-weight: 800;
            margin: 0;
        }

        .unit-list {
            list-style: none;
            padding: 16px 18px;
            display: grid;
            gap: 12px;
            flex: 1;
        }

        .unit-list-item {
            background: #FFFFFF;
            border: 1px solid var(--hanania-purple-border);
            border-radius: 10px;
            padding: 12px 14px;
        }

        .unit-role {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--hanania-primary);
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .unit-person {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--hanania-text);
        }

        /* LIGHTBOX MODAL UNTUK GAMBAR BAGAN */
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

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .leadership-grid, .units-grid {
                grid-template-columns: 1fr;
            }
            .pj-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .pj-grid {
                grid-template-columns: 1fr;
            }
            .section-box {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<!-- HERO -->
<section class="page-hero">
    <div class="hero-inner">
        <div class="hero-badge">👥 Tata Kelola &amp; Manajemen Mutu</div>
        <h1>Struktur Organisasi</h1>
        <p>Bagan hierarki kepemimpinan, penanggung jawab mutu, keselamatan pasien, dan unit pelayanan resmi Klinik Pratama Rumah Hanania.</p>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="content-container">

    <?php 
    $baganFoto = trim((string)stVal('struktur_organisasi_foto', ''));
    if ($baganFoto !== '' && file_exists(__DIR__ . '/../' . ltrim($baganFoto, '/'))): 
    ?>
        <!-- BAGAN VISUAL STRUKTUR (JIKA ADA GAMBAR) -->
        <section class="section-box" style="margin-bottom:28px;text-align:center;">
            <div class="section-header" style="justify-content:center;margin-bottom:18px;">
                <h2><span>📊</span> Bagan Visual Struktur Organisasi</h2>
            </div>
            <div style="max-width:880px;margin:0 auto;overflow:hidden;border-radius:16px;border:1px solid var(--hanania-purple-border);box-shadow:var(--card-shadow);background:#fff;padding:12px;">
                <img src="../<?= h(ltrim($baganFoto, '/')) ?>" alt="Bagan Struktur Organisasi" style="max-width:100%;height:auto;border-radius:10px;display:block;margin:0 auto;" onerror="this.parentElement.parentElement.style.display='none';">
            </div>
        </section>
    <?php endif; ?>

    <?php
    $timJson = stVal('struktur_tim_json', '');
    $timList = !empty($timJson) ? json_decode($timJson, true) : null;

    $pimpinanList = [];
    $pjList = [];
    $groupedUnits = [];

    if (is_array($timList) && !empty($timList)) {
        foreach ($timList as $item) {
            $kat = trim($item['kategori'] ?? 'Lainnya');
            $itemJab = trim($item['jabatan'] ?? '');
            $itemNama = trim($item['nama'] ?? '');
            $itemIcon = trim($item['icon'] ?? '👥');
            if ($itemJab === '' && $itemNama === '') continue;

            if (stripos($kat, 'Pimpinan') !== false || stripos($kat, 'Direksi') !== false) {
                $pimpinanList[] = ['jabatan' => $itemJab, 'nama' => $itemNama, 'icon' => $itemIcon ?: '🎓'];
            } elseif (stripos($kat, 'Penanggung Jawab') !== false || stripos($kat, 'PJ') !== false) {
                $pjList[] = ['jabatan' => $itemJab, 'nama' => $itemNama, 'icon' => $itemIcon ?: '🛡️'];
            } else {
                if (!isset($groupedUnits[$kat])) {
                    $iconHeader = '🏥';
                    if (stripos($kat, 'Pelayanan') !== false || stripos($kat, 'Medis') !== false) $iconHeader = '🩺';
                    elseif (stripos($kat, 'Penunjang') !== false) $iconHeader = '🔬';
                    elseif (stripos($kat, 'Umum') !== false || stripos($kat, 'Admin') !== false) $iconHeader = '🏢';
                    $groupedUnits[$kat] = ['icon' => $iconHeader, 'list' => []];
                }
                $groupedUnits[$kat]['list'][] = ['jabatan' => $itemJab, 'nama' => $itemNama, 'icon' => $itemIcon];
            }
        }
    } else {
        // Fallback default
        $pimpinanList = [
            ['jabatan' => 'Direktur Utama', 'nama' => stVal('struktur_dirut_nama', 'Dr. Fajar Delli Wihartiko, M.M., M.Kom.'), 'icon' => '🎓'],
            ['jabatan' => 'Direktur Operasional', 'nama' => stVal('struktur_dirop_nama', 'Gita Marissa, S.Tr.Keb., Bdn.'), 'icon' => '💼'],
            ['jabatan' => 'Kepala Klinik', 'nama' => stVal('struktur_kepala_nama', 'drg. Fatmasari Pangesti'), 'icon' => '🩺'],
        ];
        $pjList = [
            ['jabatan' => 'PJ Mutu / PMKP', 'nama' => stVal('struktur_pj_mutu', "Aida Sa'adah, S.Tr.Keb., Bdn."), 'icon' => '📊'],
            ['jabatan' => 'PJ PPI', 'nama' => stVal('struktur_pj_ppi', 'dr. Mona Hernika'), 'icon' => '🧼'],
            ['jabatan' => 'PJ Pelayanan Medis', 'nama' => stVal('struktur_pj_pelayanan', 'dr. Patricia Mellroviane'), 'icon' => '🩺'],
            ['jabatan' => 'PJ Manajemen', 'nama' => stVal('struktur_pj_manajemen', 'Gita Marissa, S.Tr.Keb., Bdn.'), 'icon' => '📑'],
            ['jabatan' => 'PJ Umum & Kepegawaian', 'nama' => stVal('struktur_pj_umum', 'Putri Nurul Fajriah, S.Tr.Keb., Bdn.'), 'icon' => '🏢'],
            ['jabatan' => 'PJ Manajemen Risiko', 'nama' => stVal('struktur_pj_risiko', 'Mika Aulia Chantika, Amd Keb'), 'icon' => '⚖️'],
            ['jabatan' => 'PJ Keselamatan Pasien (K3)', 'nama' => stVal('struktur_pj_keselamatan', 'Rifa Armawanti, Amd Keb'), 'icon' => '❤️'],
        ];
        $groupedUnits = [
            'Unit Pelayanan Medis' => [
                'icon' => '🩺',
                'list' => [
                    ['jabatan' => 'Poli Umum', 'nama' => stVal('struktur_unit_poli_umum', 'dr. Patricia Mellroviane')],
                    ['jabatan' => 'Poli Gigi & Mulut', 'nama' => stVal('struktur_unit_poli_gigi', 'drg. Fatmasari Pangesti')],
                    ['jabatan' => 'Poli Kebidanan & KIA', 'nama' => stVal('struktur_unit_poli_kebidanan', 'Pinandha Arafah, Amd.Keb')],
                    ['jabatan' => 'Ruang Tindakan & Gawat Darurat', 'nama' => stVal('struktur_unit_ruang_tindakan', 'Bilqis Az-Zahra, A. Md Keb')],
                ]
            ],
            'Unit Penunjang Medis' => [
                'icon' => '🔬',
                'list' => [
                    ['jabatan' => 'Pendaftaran & Informasi', 'nama' => stVal('struktur_unit_pendaftaran', 'Delia Puspita')],
                    ['jabatan' => 'Farmasi & Apotek', 'nama' => stVal('struktur_unit_farmasi', 'Apt. Dewi Pawitra Sari')],
                    ['jabatan' => 'Rekam Medis (RME)', 'nama' => stVal('struktur_unit_rekam_medis', 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb')],
                    ['jabatan' => 'Promotif Preventif', 'nama' => stVal('struktur_unit_promotif', 'Salma Nur Fatimah, Amd.Keb')],
                ]
            ],
            'Unit Administrasi & Umum' => [
                'icon' => '🏢',
                'list' => [
                    ['jabatan' => 'Umum & Kepegawaian', 'nama' => stVal('struktur_unit_kepegawaian', 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb')],
                    ['jabatan' => 'Kebersihan Lingkungan', 'nama' => stVal('struktur_unit_kebersihan', 'Suherman')],
                    ['jabatan' => 'Keamanan Klinik', 'nama' => stVal('struktur_unit_keamanan', 'Suherman')],
                ]
            ]
        ];
    }
    ?>

    <!-- 1. PUCUK PIMPINAN & DIREKSI -->
    <?php if (!empty($pimpinanList)): ?>
    <section class="section-box">
        <div class="section-header">
            <h2><span>🏛️</span> Pimpinan &amp; Pengelola Klinik</h2>
        </div>

        <div class="leadership-grid">
            <?php foreach ($pimpinanList as $pIdx => $leader): ?>
            <div class="leader-card <?= $pIdx === 0 ? 'primary' : '' ?>">
                <div class="leader-icon"><?= h($leader['icon'] ?: '🎓') ?></div>
                <div class="leader-title"><?= h($leader['jabatan']) ?></div>
                <div class="leader-name"><?= h($leader['nama']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 2. TIM PENANGGUNG JAWAB (PJ) MUTU -->
    <?php if (!empty($pjList)): ?>
    <section class="section-box">
        <div class="section-header">
            <h2><span>🛡️</span> Tim Penanggung Jawab (PJ) Program Mutu &amp; Manajemen</h2>
        </div>

        <div class="pj-grid">
            <?php foreach ($pjList as $pj): ?>
            <div class="pj-card">
                <div class="pj-badge"><?= h($pj['icon'] ?: '🛡️') ?></div>
                <div class="pj-info">
                    <h5><?= h($pj['jabatan']) ?></h5>
                    <p><?= h($pj['nama']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 3. UNIT PELAYANAN, PENUNJANG & ADMINISTRASI -->
    <?php if (!empty($groupedUnits)): ?>
    <section class="section-box">
        <div class="section-header">
            <h2><span>🏥</span> Unit Pelayanan Medis, Penunjang &amp; Administrasi</h2>
        </div>

        <div class="units-grid">
            <?php 
            $colors = ['#6A22A7', '#6841B0', '#553C7B', '#4A2C6D', '#7B3C9B'];
            $uIdx = 0;
            foreach ($groupedUnits as $unitName => $uData): 
                $bgHeader = $colors[$uIdx % count($colors)];
                $uIdx++;
            ?>
            <div class="unit-box">
                <div class="unit-box-header" style="background:<?= $bgHeader ?>;">
                    <span><?= h($uData['icon']) ?></span>
                    <h4><?= h($unitName) ?></h4>
                </div>
                <ul class="unit-list">
                    <?php foreach ($uData['list'] as $uRow): ?>
                    <li class="unit-list-item">
                        <span class="unit-role"><?= h($uRow['jabatan']) ?></span>
                        <div class="unit-person"><?= h($uRow['nama']) ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
