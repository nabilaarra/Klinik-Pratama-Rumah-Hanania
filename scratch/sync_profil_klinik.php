<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

$profilKlinikData = [
    'profil_klinik_hero_judul' => 'Tentang Klinik Pratama Rumah Hanania',
    'profil_klinik_hero_subjudul' => 'Klinik Pratama Rawat Jalan & Persalinan 24 Jam di bawah naungan PT. Rumah Hanania Sejahtera di Kota Bogor.',
    'profil_klinik_motto' => 'Melayani dengan Hati, Menebar Kebaikan untuk Semua',
    'profil_klinik_teks' => "Klinik Pratama Rumah Hanania adalah fasilitas pelayanan kesehatan swasta yang berada di bawah naungan PT. Rumah Hanania Sejahtera. Klinik ini berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor, didirikan oleh Dr. Fajar Delli Wihartiko, M.M., M.Kom. bersama Gita Marissa, S.Tr.Keb., Bdn.\n\nKlinik Pratama Rumah Hanania menyelenggarakan pelayanan kesehatan terpadu meliputi pelayanan kesehatan umum, kesehatan gigi dan mulut, kebidanan & KIA, persalinan 24 jam, serta konsultasi spesialis obstetri & ginekologi (Sp.OG) yang didukung sarana medis steril dan sistem antrean digitalisasi online berbasis web dan Mobile JKN.",
    'profil_klinik_badan_usaha' => 'PT. Rumah Hanania Sejahtera',
    'profil_klinik_pendiri' => 'Dr. Fajar Delli Wihartiko, M.M., M.Kom. & Gita Marissa, S.Tr.Keb., Bdn.',
    'profil_klinik_jenis_faskes' => 'Klinik Pratama Rawat Jalan (Dengan Fasilitas Persalinan 24 Jam)',
    'profil_klinik_alamat' => 'Jl. Desa Sukadamai, Kp. Situpete RT 01 / RW 09, Kel. Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165',
    'profil_klinik_jam_operasional' => 'Poli: 08.00 – 20.00 WIB • Persalinan & Darurat: 24 Jam Penuh',
    'profil_klinik_wa_1' => '0828 1070 3839',
    'profil_klinik_wa_2' => '0857 1417 8178',
    'profil_klinik_ig' => '@rumahhanania_',
    'profil_klinik_nomor_akreditasi' => 'YM.02.01/D/674/2026',
    'profil_klinik_masa_akreditasi' => '2026 s.d 2031 (5 Tahun)',
    'profil_klinik_lembaga_akreditasi' => 'Lafkespri',
    'profil_klinik_regulator' => 'Kementerian Kesehatan RI',
    'profil_klinik_legalitas_nomor' => '28102100123850003',
    'profil_klinik_legalitas_kbli' => '86105',
    'profil_klinik_legalitas_status' => 'Perizinan Baru',
    'profil_klinik_legalitas_pelaku' => 'PERORANGAN GITA MARISSA',
    'profil_klinik_legalitas_faskes' => 'Klinik Pratama Rumah Hanania',
    'profil_klinik_legalitas_kemampuan' => 'Klinik Pratama',
    'profil_klinik_legalitas_penyelenggaraan' => 'Rawat Jalan',
    'profil_klinik_legalitas_kesesuaian' => 'Telah Memenuhi Syarat Usaha',
    'profil_klinik_foto' => 'assets/img/klinik3.jpg',
    'profil_foto_gedung' => 'assets/img/klinik3.jpg'
];

$stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
foreach ($profilKlinikData as $k => $v) {
    $stmt->bind_param('ss', $k, $v);
    $stmt->execute();
}
$stmt->close();
echo "profil_klinik_* keys successfully synced.\n";
