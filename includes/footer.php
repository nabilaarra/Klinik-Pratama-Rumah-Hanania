<?php
// Ambil data konten dinamis jika fungsi getSiteKonten belum ada
if (!function_exists('getSiteKontenFooter')) {
    function getSiteKontenFooter($key, $default = '') {
        global $conn;
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            if (isset($conn) && $conn instanceof mysqli) {
                $q = @mysqli_query($conn, "SELECT kunci, nilai FROM pengaturan_konten");
                if ($q) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $cache[$r['kunci']] = $r['nilai'];
                    }
                }
            }
        }
        return isset($cache[$key]) && trim((string)$cache[$key]) !== '' ? $cache[$key] : $default;
    }
}

$footerBrandNama = getSiteKontenFooter('navbar_brand_nama', 'Klinik Pratama Rumah Hanania');
$footerDeskripsi = getSiteKontenFooter('footer_deskripsi', 'Pelayanan kesehatan rawat jalan tingkat pertama yang mudah dijangkau, berkualitas, dan berorientasi pada kebutuhan keluarga dan masyarakat.');
$footerLegalitas = getSiteKontenFooter('footer_legalitas', 'Sertifikat Standar Perizinan Berusaha Berbasis Risiko terdaftar resmi.');
$footerWa1 = getSiteKontenFooter('footer_wa1', '088510703839');
$footerWa2 = getSiteKontenFooter('footer_wa2', '085714178178');
$footerAlamat = getSiteKontenFooter('kontak_alamat', 'Jl. Desa Sukadamai No.RT 01, RT.01/RW.06, Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165');
$footerMaps = getSiteKontenFooter('footer_maps_link', 'https://maps.app.goo.gl/524k7ABe1fckKgpt6');
$footerCopyright = getSiteKontenFooter('footer_copyright', '© ' . date('Y') . ' Klinik Pratama Rumah Hanania. Hak Cipta Dilindungi.');

// Format nomor WA untuk link wa.me
$waClean1 = preg_replace('/[^0-9]/', '', $footerWa1);
if (strpos($waClean1, '0') === 0) $waClean1 = '62' . substr($waClean1, 1);
$waClean2 = preg_replace('/[^0-9]/', '', $footerWa2);
if (strpos($waClean2, '0') === 0) $waClean2 = '62' . substr($waClean2, 1);
?>
<style>
.hanania-footer {
    padding: 55px 24px 35px;
    background: linear-gradient(180deg, #F6F1FC 0%, #EAE1F7 100%);
    color: #2B1B48;
    scroll-margin-top: 80px;
    border-top: 1.5px solid #D8CAEC;
    position: relative;
    z-index: 10;
}
.hanania-footer-container {
    max-width: 1200px;
    margin: auto;
    display: grid;
    grid-template-columns: 1.2fr 1fr 1.2fr;
    gap: 38px;
}
.hanania-footer-column {
    display: flex;
    flex-direction: column;
}
.hanania-footer-brand {
    font-size: 17px;
    font-weight: 800;
    color: #2B1B48;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    letter-spacing: -0.2px;
}
.hanania-footer p {
    margin: 4px 0 12px;
    color: #55416C;
    font-size: 13px;
    line-height: 1.7;
}
.hanania-footer h4 {
    margin: 0 0 14px;
    font-size: 13.5px;
    font-weight: 800;
    color: #4A1775;
    letter-spacing: 0.6px;
    text-transform: uppercase;
}
.hanania-footer-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.hanania-footer-list li {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
}
.hanania-footer-list span {
    color: #705D86;
    font-size: 11.5px;
    font-weight: 700;
}
.hanania-footer-list a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #FFFFFF;
    border: 1px solid #D8CBEB;
    color: #5A1E8F;
    font-size: 12.5px;
    font-weight: 800;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 9999px;
    width: fit-content;
    box-shadow: 0 2px 8px rgba(106, 34, 167, 0.05);
    transition: all 0.2s;
}
.hanania-footer-list a:hover {
    background: #6A22A7;
    color: #FFFFFF;
    border-color: #6A22A7;
    transform: translateY(-1px);
}
.hanania-maps {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 9px 18px;
    border-radius: 9999px;
    background: #6A22A7;
    color: #FFFFFF;
    font-size: 12.5px;
    font-weight: 800;
    text-decoration: none;
    width: fit-content;
    box-shadow: 0 4px 14px rgba(106, 34, 167, 0.2);
    transition: all 0.2s;
}
.hanania-maps:hover {
    background: #4A1278;
    color: #FFFFFF;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(106, 34, 167, 0.3);
}
.hanania-footer-bottom {
    max-width: 1200px;
    margin: 32px auto 0;
    padding-top: 22px;
    border-top: 1px solid #D8CAEC;
    text-align: center;
    font-size: 12px;
    color: #726088;
    font-weight: 600;
}
@media(max-width: 850px) {
    .hanania-footer-container {
        grid-template-columns: 1fr;
        gap: 26px;
    }
}
</style>
<footer class="hanania-footer" id="kontak">
    <div class="hanania-footer-container">
        <div class="hanania-footer-column">
            <div class="hanania-footer-brand">
                <span>🩺</span> <?= htmlspecialchars($footerBrandNama) ?>
            </div>
            <p>
                <?= nl2br(htmlspecialchars($footerDeskripsi)) ?>
            </p>
            <p style="font-size:11px;color:#78668E;margin-top:auto;font-weight:600;">
                <?= htmlspecialchars($footerLegalitas) ?>
            </p>
        </div>

        <div class="hanania-footer-column">
            <h4>Contact Person</h4>
            <ul class="hanania-footer-list">
                <li>
                    <span>WhatsApp Layanan &amp; Administrasi 1</span>
                    <a href="https://wa.me/<?= $waClean1 ?>" target="_blank" rel="noopener noreferrer">
                        <span>📞</span> <?= htmlspecialchars($footerWa1) ?>
                    </a>
                </li>
                <li>
                    <span>WhatsApp Informasi &amp; Pendaftaran 2</span>
                    <a href="https://wa.me/<?= $waClean2 ?>" target="_blank" rel="noopener noreferrer">
                        <span>📞</span> <?= htmlspecialchars($footerWa2) ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="hanania-footer-column">
            <h4>Lokasi Klinik</h4>
            <p style="margin-bottom:6px;">
                <strong><?= htmlspecialchars($footerAlamat) ?></strong>
            </p>
            <a href="<?= htmlspecialchars($footerMaps) ?>" target="_blank" rel="noopener noreferrer" class="hanania-maps">
                <span>📍</span> Buka Rute di Google Maps
            </a>
        </div>
    </div>

    <div class="hanania-footer-bottom">
        <?php 
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $footerBase = (stripos($scriptName, '/Klinik-Pratama-Rumah-Hanania') !== false) ? '/Klinik-Pratama-Rumah-Hanania' : '';
        ?>
        <?= htmlspecialchars($footerCopyright) ?> &bull; <span style="font-weight:600;opacity:0.88;">Credit by @nabila.pisesa@gmail.com</span> &bull; <a href="<?= $footerBase ?>/admin/index.php" style="color:inherit;text-decoration:none;opacity:0.6;font-size:11px;">Portal Petugas &amp; Admin</a>
    </div>
</footer>
<?php include_once __DIR__ . '/chatbot.php'; ?>
<?php include_once __DIR__ . '/audio_player.php'; ?>