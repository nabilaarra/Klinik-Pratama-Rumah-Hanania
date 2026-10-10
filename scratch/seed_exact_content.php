<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

$data = [
    // === 1. AKREDITASI & LEGALITAS (Image 1) ===
    'akreditasi_hero_judul' => 'Sertifikat Akreditasi Fasilitas Kesehatan',
    'akreditasi_hero_subjudul' => 'Klinik Pratama Rumah Hanania telah menjalani evaluasi mutu menyeluruh dan secara resmi memperoleh pengakuan kelulusan predikat PARIPURNA dari Kementerian Kesehatan Republik Indonesia dan Lembaga Akreditasi Fasilitas Kesehatan Primer (Lafkespri).',
    'akreditasi_sertifikat_status' => 'PARIPURNA',
    'akreditasi_sertifikat_bintang' => '5',
    'akreditasi_nomor_sk' => 'YM.02.01/D/674/2026',
    'akreditasi_masa_berlaku' => '2026 s.d 2031 (5 Tahun)',
    'akreditasi_lembaga' => 'Lafkespri',
    'akreditasi_regulator' => 'Kementerian Kesehatan RI',
    'akreditasi_penjelasan' => 'Akreditasi PARIPURNA merupakan pengakuan mutu dan keselamatan pasien tertinggi dari pemerintah bagi fasilitas kesehatan yang telah memenuhi 100% kepatuhan standar pelayanan, pencegahan infeksi, dan tata kelola medis modern.',
    'akreditasi_ttd_1' => 'Dr. Dra. Chazali H. Situmorang, Apt, M.Sc, CIRB (Ketua Lafkespri)',
    'akreditasi_ttd_2' => 'dr. Maria Endang Sumiwi, MPH (Direktur Jenderal Kesehatan Primer dan Komunitas Kemenkes RI)',

    'legalitas_hero_judul' => 'Legalitas & Sertifikat Standar Klinik',
    'legalitas_hero_subjudul' => 'Klinik Pratama Rumah Hanania telah mengantongi izin resmi operasional dari Pemerintah Republik Indonesia melalui sistem Perizinan Berusaha Berbasis Risiko (KBLI Subsektor Kesehatan).',
    'legalitas_nomor_izin' => '28102100123850003',
    'legalitas_kbli' => '86105',
    'legalitas_nama_pelaku' => 'PERORANGAN GITA MARISSA',
    'legalitas_kemampuan_pelayanan' => 'Klinik Pratama',
    'legalitas_penyelenggaraan' => 'Rawat Jalan',
    'legalitas_status_permohonan' => 'Perizinan Baru',
    'legalitas_kesesuaian_standar' => 'Telah Memenuhi Syarat Usaha',
    'legalitas_catatan' => 'Berdasarkan hasil penilaian kesesuaian, klinik ini telah memenuhi Persyaratan Standar Usaha Klinik sesuai Peraturan Menteri Kesehatan tentang Standar Kegiatan Usaha subsektor kesehatan.',
    'legalitas_layanan_list' => json_encode(['Pelayanan Gawat Darurat', 'Pelayanan Umum', 'Pelayanan Gigi', 'Persalinan 24 Jam'], JSON_UNESCAPED_UNICODE),

    // === 2. PROFIL KLINIK (Image 2) ===
    'profil_hero_judul' => 'Tentang Klinik Pratama Rumah Hanania',
    'profil_hero_subjudul' => 'Klinik Pratama Rawat Jalan & Persalinan 24 Jam di bawah naungan PT. Rumah Hanania Sejahtera di Kota Bogor.',
    'profil_paragraf_1' => 'Klinik Pratama Rumah Hanania adalah fasilitas pelayanan kesehatan swasta yang berada di bawah naungan PT. Rumah Hanania Sejahtera. Klinik ini berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor, didirikan oleh Dr. Fajar Delli Wihartiko, M.M., M.Kom. bersama Gita Marissa, S.Tr.Keb., Bdn.',
    'profil_semboyan' => 'Melayani dengan Hati, Menebar Kebaikan untuk Semua',
    'profil_paragraf_2' => 'Klinik Pratama Rumah Hanania menyelenggarakan pelayanan kesehatan terpadu meliputi pelayanan kesehatan umum, kesehatan gigi dan mulut, kebidanan & KIA, persalinan 24 jam, serta konsultasi spesialis obstetri & ginekologi (Sp.OG) yang didukung sarana medis steril dan sistem antrean digitalisasi online berbasis web dan Mobile JKN.',
    'profil_badan_usaha' => 'PT. Rumah Hanania Sejahtera',
    'profil_pendiri' => 'Dr. Fajar Delli Wihartiko, M.M., M.Kom. & Gita Marissa, S.Tr.Keb., Bdn.',
    'profil_jenis_faskes' => 'Klinik Pratama Rawat Jalan (Dengan Fasilitas Persalinan 24 Jam)',
    'profil_alamat' => 'Jl. Desa Sukadamai, Kp. Situpete RT 01 / RW 09, Kel. Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165',
    'profil_maps_url' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt9',
    'profil_jam_operasional' => 'Poli: 08.00 – 20.00 WIB • Persalinan & Darurat: 24 Jam Penuh',
    'profil_kontak_wa_1' => '0828 1070 3839',
    'profil_kontak_wa_2' => '0857 1417 8178',
    'profil_kontak_ig' => '@rumahhanania_',

    // === 3. SEJARAH KLINIK (Image 3) ===
    'sejarah_hero_judul' => 'Sejarah Klinik Pratama Rumah Hanania',
    'sejarah_hero_subjudul' => 'Perjalanan dedikasi pelayanan kesehatan bermutu dan berorientasi keluarga di Kota Bogor sejak tahun 2014.',
    'sejarah_kisah_awal' => "Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan swasta yang didirikan oleh PT. Rumah Hanania Sejahtera dan berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor.\n\nKlinik ini didirikan oleh Dr. Fajar Delli Wihartiko, M.M., M.Kom. bersama Gita Marissa, S.Tr.Keb., Bdn.",
    'sejarah_quote' => 'Klinik ini didirikan dengan harapan dan doa tulus agar seluruh insan yang terlibat—baik pasien, keluarga, tenaga medis, maupun seluruh tim pendukung—senantiasa berada dalam lindungan dan kasih sayang Allah SWT.',
    'sejarah_timeline_1_tahun' => '2014',
    'sejarah_timeline_1_judul' => 'Bidan Praktik Mandiri Gita Marissa',
    'sejarah_timeline_1_teks' => 'Dimulainya pelayanan kebidanan mandiri oleh Bidan Gita Marissa dengan pendekatan personal dan kekeluargaan untuk melayani pemeriksaan ibu hamil dan pertolongan persalinan masyarakat sekitar Sukadamai, Tanah Sareal.',
    'sejarah_timeline_2_tahun' => '2018',
    'sejarah_timeline_2_judul' => 'Praktik Bersama Dokter Umum – Bidan',
    'sejarah_timeline_2_teks' => 'Pengembangan layanan kesehatan melalui kolaborasi praktik bersama antara dokter umum dan bidan guna memperluas cakupan diagnosis medis, pengobatan penyakit umum keluarga, dan tindakan preventif.',
    'sejarah_timeline_3_tahun' => '2023',
    'sejarah_timeline_3_judul' => 'Praktik Bersama Dokter Umum, Dokter Gigi dan Bidan',
    'sejarah_timeline_3_teks' => 'Layanan semakin terpadu dengan penambahan unit Pelayanan Kesehatan Gigi & Mulut, fasilitas USG kehamilan, serta kelengkapan sarana medis untuk memenuhi kebutuhan kesehatan komprehensif keluarga.',
    'sejarah_timeline_4_tahun' => '2026',
    'sejarah_timeline_4_judul' => 'Klinik Pratama Rumah Hanania',
    'sejarah_timeline_4_teks' => 'Resmi bertransformasi menjadi fasilitas kesehatan berizin klinik pratama di bawah naungan PT. Rumah Hanania Sejahtera, dilengkapi perizinan berusaha berbasis risiko pemerintah, sistem digitalisasi antrean online web & Mobile JKN, serta layanan persalinan 24 jam.',
    'sejarah_galeri_json' => json_encode([
        ['id' => 1, 'foto' => 'assets/img/pdf_pages/page_3.png', 'caption' => 'Dokumen Profil Sejarah Klinik Hanania', 'tahun' => '2014'],
        ['id' => 2, 'foto' => 'assets/img/pdf_pages/page_4.png', 'caption' => 'Dokumen Legalitas & Sertifikat Standar', 'tahun' => '2026']
    ], JSON_UNESCAPED_UNICODE),

    // === 4. VISI, MISI & TATA NILAI (Image 4) ===
    'visi_misi_hero_judul' => 'Visi & Misi Klinik Pratama Rumah Hanania',
    'visi_misi_hero_subjudul' => 'Arah haluan, dedikasi pelayanan, dan tata nilai luhur yang senantiasa menjiwai setiap langkah pelayanan kami.',
    'visi_misi_visi' => 'Menjadi Klinik Pratama yang unggul, terpercaya, dan dalam memberikan pelayanan kesehatan umum, gigi, kebidanan yang bermutu, aman, serta berorientasi pada kebutuhan masyarakat.',
    'visi_misi_visi_teks' => 'Menjadi Klinik Pratama yang unggul, terpercaya, dan dalam memberikan pelayanan kesehatan umum, gigi, kebidanan yang bermutu, aman, serta berorientasi pada kebutuhan masyarakat.',
    'visi_misi_misi_subjudul' => 'Empat pilar komitmen nyata Klinik Pratama Rumah Hanania kepada seluruh masyarakat.',
    'visi_misi_misi_1' => 'Memberikan pelayanan kesehatan umum, kesehatan gigi dan kebidanan yang profesional, dan sesuai standar pelayanan medis dengan harga yang terjangkau.',
    'visi_misi_misi_2' => 'Menyelenggarakan pelayanan kesehatan umum, kesehatan gigi dan mulut, serta kebidanan yang bermutu melalui tindakan promotif, preventif, dan kuratif.',
    'visi_misi_misi_3' => 'Memberikan pelayanan yang aman dan ramah, serta mengutamakan keselamatan, kenyamanan, dan kepuasan pasien dalam setiap pelayanan.',
    'visi_misi_misi_4' => 'Mendukung program pemerintah di bidang kesehatan ibu dan anak serta kesehatan masyarakat.',
    'visi_misi_motto' => 'Melayani dengan Senang Hati, Menebar Keberkahan untuk Semua',
    'visi_misi_nilai_1_judul' => 'PROFESIONAL',
    'visi_misi_nilai_1_teks' => 'Pelayanan kompeten sesuai standar etika dan mutu medis tertinggi.',
    'visi_misi_nilai_2_judul' => 'AMAN',
    'visi_misi_nilai_2_teks' => 'Prioritas keselamatan pasien dengan sarana dan prosedur yang steril.',
    'visi_misi_nilai_3_judul' => 'RAMAH',
    'visi_misi_nilai_3_teks' => 'Pelayanan sepenuh hati dengan senyum, keramahan, dan empati.',
    'visi_misi_nilai_4_judul' => 'TERPERCAYA',
    'visi_misi_nilai_4_teks' => 'Integritas dan transparansi diagnosis untuk kesehatan keluarga Anda.',

    // === 5. STRUKTUR ORGANISASI TIM DINAMIS ===
    'struktur_tim_json' => json_encode([
        ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Direktur Utama', 'nama' => 'Dr. Fajar Delli Wihartiko, M.M., M.Kom.', 'icon' => '🎓'],
        ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Direktur Operasional', 'nama' => 'Gita Marissa, S.Tr.Keb., Bdn.', 'icon' => '💼'],
        ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Kepala Klinik', 'nama' => 'drg. Fatmasari Pangesti', 'icon' => '🩺'],

        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Mutu / PMKP', 'nama' => "Aida Sa'adah, S.Tr.Keb., Bdn.", 'icon' => '📊'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ PPI', 'nama' => 'dr. Mona Hernika', 'icon' => '🧼'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Pelayanan Medis', 'nama' => 'dr. Patricia Mellroviane', 'icon' => '🩺'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Manajemen', 'nama' => 'Gita Marissa, S.Tr.Keb., Bdn.', 'icon' => '📑'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Umum & Kepegawaian', 'nama' => 'Putri Nurul Fajriah, S.Tr.Keb., Bdn.', 'icon' => '🏢'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Manajemen Risiko', 'nama' => 'Mika Aulia Chantika, Amd Keb', 'icon' => '⚖️'],
        ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Keselamatan Pasien (K3)', 'nama' => 'Rifa Armawanti, Amd Keb', 'icon' => '❤️'],

        ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Umum', 'nama' => 'dr. Patricia Mellroviane', 'icon' => '🩺'],
        ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Gigi & Mulut', 'nama' => 'drg. Fatmasari Pangesti', 'icon' => '🦷'],
        ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Kebidanan & KIA', 'nama' => 'Pinandha Arafah, Amd.Keb', 'icon' => '👶'],
        ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Ruang Tindakan & Gawat Darurat', 'nama' => 'Bilqis Az-Zahra, A. Md Keb', 'icon' => '🚑'],

        ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Pendaftaran & Informasi', 'nama' => 'Delia Puspita', 'icon' => '📋'],
        ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Farmasi & Apotek', 'nama' => 'Apt. Dewi Pawitra Sari', 'icon' => '💊'],
        ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Rekam Medis (RME)', 'nama' => 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb', 'icon' => '📂'],
        ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Promotif Preventif', 'nama' => 'Salma Nur Fatimah, Amd.Keb', 'icon' => '📢'],

        ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Umum & Kepegawaian', 'nama' => 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb', 'icon' => '👥'],
        ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Kebersihan Lingkungan', 'nama' => 'Suherman', 'icon' => '🧹'],
        ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Keamanan Klinik', 'nama' => 'Suherman', 'icon' => '🛡️']
    ], JSON_UNESCAPED_UNICODE)
];

// Pastikan kolom nilai cukup panjang (MEDIUMTEXT atau TEXT)
$db->query("ALTER TABLE pengaturan_konten MODIFY nilai MEDIUMTEXT NULL");

$stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
$count = 0;
foreach ($data as $k => $v) {
    $stmt->bind_param('ss', $k, $v);
    if ($stmt->execute()) {
        $count++;
    }
}
$stmt->close();

echo "Berhasil memperbarui / menyinkronkan {$count} baris konten sesuai 4 gambar input pengguna.\n";
