<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

$keys = [
    // Akreditasi & Legalitas (Image 1)
    'akreditasi_hero_judul', 'akreditasi_hero_subjudul',
    'akreditasi_sertifikat_status', 'akreditasi_nomor_sk', 'akreditasi_masa_berlaku',
    'akreditasi_lembaga', 'akreditasi_regulator', 'legalitas_nomor_izin', 'legalitas_nama_pelaku',
    'legalitas_kbli', 'legalitas_kemampuan_pelayanan',

    // Profil (Image 2)
    'profil_hero_judul', 'profil_hero_subjudul', 'profil_semboyan',
    'profil_badan_usaha', 'profil_pendiri', 'profil_jenis_faskes', 'profil_alamat',
    'profil_jam_operasional', 'profil_kontak_wa_1', 'profil_kontak_wa_2', 'profil_kontak_ig',
    'profil_foto_gedung',

    // Sejarah (Image 3)
    'sejarah_hero_judul', 'sejarah_hero_subjudul', 'sejarah_kisah_awal', 'sejarah_quote',
    'sejarah_timeline_1_tahun', 'sejarah_timeline_1_judul',
    'sejarah_timeline_2_tahun', 'sejarah_timeline_2_judul',
    'sejarah_timeline_3_tahun', 'sejarah_timeline_3_judul',
    'sejarah_timeline_4_tahun', 'sejarah_timeline_4_judul',

    // Visi Misi (Image 4)
    'visi_misi_visi_teks', 'visi_misi_motto',
    'visi_misi_misi_1', 'visi_misi_misi_2', 'visi_misi_misi_3', 'visi_misi_misi_4',
    'visi_misi_nilai_1_judul', 'visi_misi_nilai_1_teks',
    'visi_misi_nilai_2_judul', 'visi_misi_nilai_2_teks',
    'visi_misi_nilai_3_judul', 'visi_misi_nilai_3_teks',
    'visi_misi_nilai_4_judul', 'visi_misi_nilai_4_teks'
];

$res = [];
$q = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci IN ('" . implode("','", $keys) . "')");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $res[$r['kunci']] = $r['nilai'];
    }
}

foreach ($keys as $k) {
    echo "$k => " . ($res[$k] ?? '[NOT SET]') . "\n";
}
