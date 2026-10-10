<?php
require_once __DIR__ . '/../config/database.php';

// Minimal valid MP3 header and silent/soft frame so browser audio element loads without error
$targetFile = __DIR__ . '/../uploads/audio/suasana_klinik_hanania.mp3';

if (!file_exists($targetFile)) {
    // Write a valid small MP3 byte sequence (MPEG-1 Audio Layer III, 128 kbps, 44.1 kHz, stereo)
    $mp3Frame = hex2bin('fffb906400000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000');
    // Repeat to create a few seconds
    $content = str_repeat($mp3Frame, 200);
    file_put_contents($targetFile, $content);
    echo "Sample audio file created at: $targetFile\n";
} else {
    echo "Audio file already exists.\n";
}

// Inisialisasi default pengaturan konten
$defaults = [
    // Audio
    'audio_beranda_aktif' => '1',
    'audio_beranda_file' => 'uploads/audio/suasana_klinik_hanania.mp3',
    'audio_beranda_judul' => 'Instrumental Suasana Tenang - Klinik Pratama Rumah Hanania',
    'audio_beranda_autoplay' => '1',

    // Navbar
    'navbar_brand_nama' => 'Klinik Pratama Rumah Hanania',
    'navbar_brand_slogan' => 'Melayani dengan Hati',
    'navbar_menu_beranda' => 'Beranda',
    'navbar_menu_layanan' => 'Layanan',
    'navbar_menu_jadwal' => 'Jadwal',
    'navbar_menu_dokter' => 'Dokter & Bidan',
    'navbar_menu_tentang' => 'Tentang',
    'navbar_menu_kontak' => 'Kontak & Alamat',
    'navbar_btn_login' => 'Login',

    // Submenu Dropdown Tentang
    'navbar_sub_profil' => 'Profil Klinik',
    'navbar_sub_sejarah' => 'Sejarah Klinik',
    'navbar_sub_visi_misi' => 'Visi & Misi',
    'navbar_sub_fasilitas' => 'Fasilitas Klinik',
    'navbar_sub_struktur' => 'Struktur Organisasi',

    // Section Layanan di Beranda
    'layanan_section_label' => 'LAYANAN',
    'layanan_section_judul' => 'Informasi Layanan Klinik',
    'layanan_section_subjudul' => 'Temukan informasi mengenai layanan kesehatan yang tersedia di Klinik Pratama Rumah Hanania.',

    // 5 Layanan Utama
    'layanan_item_1_nama' => 'Pelayanan Umum',
    'layanan_item_1_deskripsi' => 'Informasi mengenai pelayanan kesehatan umum yang tersedia di klinik.',
    'layanan_item_1_icon' => '🏥',

    'layanan_item_2_nama' => 'Pelayanan Gigi',
    'layanan_item_2_deskripsi' => 'Informasi mengenai pelayanan dan pemeriksaan kesehatan gigi.',
    'layanan_item_2_icon' => '🦷',

    'layanan_item_3_nama' => 'Pelayanan Kebidanan',
    'layanan_item_3_deskripsi' => 'Informasi mengenai pelayanan kebidanan dan ANC yang tersedia di klinik.',
    'layanan_item_3_icon' => '🤱🏻',

    'layanan_item_4_nama' => 'Pasien BPJS',
    'layanan_item_4_deskripsi' => 'Klinik melayani pasien BPJS sesuai ketentuan dan prosedur pelayanan yang berlaku.',
    'layanan_item_4_icon' => '💳',

    'layanan_item_5_nama' => 'Persalinan 24 Jam',
    'layanan_item_5_deskripsi' => 'Layanan persalinan tersedia 24 jam. Hubungi klinik terlebih dahulu untuk informasi dan penanganan.',
    'layanan_item_5_icon' => '🤰',

    'layanan_item_6_nama' => 'USG Kehamilan 2D / 4D',
    'layanan_item_6_deskripsi' => 'Pemeriksaan USG kandungan spesialis dengan hasil cetak foto dan video rekaman.',
    'layanan_item_6_icon' => '👶',

    // Footer
    'footer_deskripsi' => 'Pelayanan kesehatan rawat jalan tingkat pertama yang mudah dijangkau, berkualitas, dan berorientasi pada kebutuhan keluarga dan masyarakat.',
    'footer_legalitas' => 'Sertifikat Standar Perizinan Berusaha Berbasis Risiko terdaftar resmi.',
    'footer_wa1' => '088510703839',
    'footer_wa2' => '085714178178',
    'footer_maps_link' => 'https://maps.google.com',
    'footer_copyright' => '© ' . date('Y') . ' Klinik Pratama Rumah Hanania. Hak Cipta Dilindungi.'
];

$stmt = $conn->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = COALESCE(NULLIF(nilai, ''), VALUES(nilai))");
foreach ($defaults as $k => $v) {
    $stmt->bind_param('ss', $k, $v);
    $stmt->execute();
}
$stmt->close();
echo "Default pengaturan_konten updated successfully!\n";
