<?php
require __DIR__ . '/../config/database.php';

$defaults = [
    'sejarah_foto_utama' => 'assets/img/klinik1.jpg',
    'sejarah_komitmen' => 'Awal perjalanan Klinik Pratama Rumah Hanania berangkat dari komitmen kuat untuk menyediakan pelayanan kebidanan, kesehatan umum, serta kesehatan gigi dan mulut yang bermutu, aman, serta terjangkau bagi seluruh lapisan masyarakat.',
    'sejarah_fokus' => 'Sejak awal berdiri, klinik berfokus pada peningkatan derajat kesehatan masyarakat melalui pelayanan promotif, preventif, kuratif, dan rehabilitatif secara terpadu, menghadirkan pelayanan yang ramah bagi ibu, anak, dan keluarga dengan pendekatan yang humanis dan profesional, serta mengutamakan kepuasan pasien melalui pelayanan yang cepat, tepat, dan berorientasi pada kebutuhan pasien.',
    'profil_klinik_foto_akreditasi' => 'assets/img/sertifikat_akreditasi_paripurna.jpg',
    'profil_klinik_foto_legalitas' => 'assets/img/pdf_pages/page_4.png'
];

foreach ($defaults as $k => $v) {
    $stmt = $conn->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = IF(nilai IS NULL OR nilai = '', VALUES(nilai), nilai)");
    if ($stmt) {
        $stmt->bind_param('ss', $k, $v);
        $stmt->execute();
        $stmt->close();
    }
}

echo "Seeded keys successfully!" . PHP_EOL;
