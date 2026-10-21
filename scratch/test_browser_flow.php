<?php
require_once __DIR__ . '/../config/database.php';

// Ambil semua data dokter & bidan persis seperti di daftar.php
$qSchedule = $conn->query("
    SELECT id_dokter AS id_tenaga, id_poli, hari, jam_mulai, jam_selesai, 'Dokter' AS jenis
    FROM jadwal_dokter WHERE status = 'tersedia'
    UNION ALL
    SELECT id_bidan AS id_tenaga, id_poli, hari, jam_mulai, jam_selesai, 'Bidan' AS jenis
    FROM jadwal_bidan WHERE status = 'tersedia'
    ORDER BY jenis, id_tenaga, FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jam_mulai
");
$scheduleMap = [];
while ($row = $qSchedule->fetch_assoc()) {
    $scheduleMap[$row['jenis'] . '-' . (int)$row['id_tenaga']][] = $row;
}

$dataTenaga = [];
$qDokter = $conn->query("SELECT id_dokter AS id, nama_dokter AS nama, status, COALESCE(id_poli,0) AS id_poli, spesialisasi, foto FROM dokter WHERE status != 'nonaktif'");
while ($row = $qDokter->fetch_assoc()) {
    $key = 'Dokter-' . (int)$row['id'];
    if (!empty($scheduleMap[$key])) {
        $row['jenis'] = 'Dokter';
        $row['jadwal_db'] = $scheduleMap[$key];
        $dataTenaga[] = $row;
    }
}
$qBidan = $conn->query("SELECT id_bidan AS id, nama_bidan AS nama, status, COALESCE(id_poli,0) AS id_poli, 'Bidan' AS spesialisasi, foto FROM bidan WHERE status != 'nonaktif'");
while ($row = $qBidan->fetch_assoc()) {
    $key = 'Bidan-' . (int)$row['id'];
    if (!empty($scheduleMap[$key])) {
        $row['jenis'] = 'Bidan';
        $row['jadwal_db'] = $scheduleMap[$key];
        $dataTenaga[] = $row;
    }
}

usort($dataTenaga, function ($left, $right) {
    $typeOrder = ($left['jenis'] === 'Dokter' ? 0 : 1) <=> ($right['jenis'] === 'Dokter' ? 0 : 1);
    if ($typeOrder !== 0) return $typeOrder;
    return $left['nama'] <=> $right['nama'];
});

echo "=== DAFTAR SEMUA TENAGA KESEHATAN DI DAFTAR.PHP ===\n";
foreach ($dataTenaga as $t) {
    $poliList = array_unique(array_column($t['jadwal_db'], 'id_poli'));
    echo "- [{$t['jenis']}] {$t['nama']} | Poli Asal: {$t['id_poli']} | Jadwal Polis: " . implode(',', $poliList) . "\n";
}

echo "\n=== SIMULASI onFilterChange() UNTUK SETIAP POLI ===\n";
$polisToTest = [
    1 => 'Poli Umum',
    2 => 'Poli Gigi',
    3 => 'Poli Kebidanan',
    4 => 'Poli Spesialis Kebidanan dan Kandungan'
];

foreach ($polisToTest as $idPoli => $namaPoli) {
    echo "\n>>> Test Poli: $idPoli ($namaPoli)\n";
    $visibleCards = [];
    foreach ($dataTenaga as $card) {
        $matchesPoli = ((int)$card['id_poli'] === $idPoli) || in_array($idPoli, array_map('intval', array_column($card['jadwal_db'], 'id_poli')));
        if ($matchesPoli) {
            $visibleCards[] = $card;
        }
    }
    echo "Jumlah tenaga kesehatan yang tampil: " . count($visibleCards) . "\n";
    foreach ($visibleCards as $vc) {
        echo "   * [{$vc['jenis']}] {$vc['nama']}\n";
    }
    if (empty($visibleCards)) {
        echo "   ⛔ TIDAK ADA TENAGA KESEHATAN!\n";
    }
}
