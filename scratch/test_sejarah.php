<?php
$files = [
    'assets/img/klinik3.jpg',
    'assets/img/klinik2.jpg',
    'assets/img/pdf_pages/page_3.png',
    'assets/img/pdf_pages/page_4.png',
    'uploads/profil/poster_visi_misi.jpg'
];
foreach ($files as $f) {
    echo $f . ' => ' . (file_exists(__DIR__ . '/../' . $f) ? 'EXISTS (' . filesize(__DIR__ . '/../' . $f) . ' bytes)' : 'NOT FOUND') . PHP_EOL;
}
