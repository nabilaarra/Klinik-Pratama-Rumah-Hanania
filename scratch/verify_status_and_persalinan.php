<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Database connection failed\n");
}

echo "=== TEST 1: PERSALINAN 24 JAM URUTAN PALING ATAS ===\n";
$qPoli = $db->query("
    SELECT id_poli, nama_poli, is_24jam 
    FROM poli 
    WHERE status = 'aktif' 
    ORDER BY CASE WHEN is_24jam = 1 OR id_poli = 5 OR nama_poli LIKE '%Persalinan%' THEN 0 ELSE 1 END, id_poli ASC
");
$allPoli = [];
while ($row = $qPoli->fetch_assoc()) {
    $allPoli[] = $row;
}
echo "Poli urutan pertama: " . ($allPoli[0]['nama_poli'] ?? '') . " (ID: " . ($allPoli[0]['id_poli'] ?? '') . ")\n";
$passTop = (isset($allPoli[0]['id_poli']) && ((int)$allPoli[0]['id_poli'] === 5 || (int)$allPoli[0]['is_24jam'] === 1));
echo "-> Urutan paling atas: " . ($passTop ? "PASS" : "FAIL") . "\n\n";

echo "=== TEST 2: PERSALINAN 24 JAM TIDAK PERNAH DITUTUP ===\n";
$poli24 = ['id_poli' => 5, 'nama_poli' => 'Persalinan 24 Jam', 'is_24jam' => 1, 'status_operasional' => 'otomatis'];
$st24 = cekStatusJadwalPoli($poli24, '00:00:00', '23:59:00', true, 'Persalinan Normal');
echo "is_closed: " . ($st24['is_closed'] ? 'true' : 'false') . "\n";
echo "status_label: " . $st24['status_label'] . "\n";
echo "keterangan: " . $st24['keterangan'] . "\n";
$pass24 = ($st24['is_closed'] === false && $st24['is_24jam'] === true && $st24['status_label'] === 'Pendaftaran Tersedia');
echo "-> Persalinan 24 Jam selalu tersedia: " . ($pass24 ? "PASS" : "FAIL") . "\n\n";

echo "=== TEST 3: PENGATURAN STATUS ADMIN (TERSEDIA, DITUTUP, FULL BOOKING) ===\n";
$testCases = [
    [
        'status_operasional' => 'tersedia',
        'expected_closed' => false,
        'expected_label' => 'Pendaftaran Tersedia',
        'expected_ket' => 'Pendaftaran Tersedia'
    ],
    [
        'status_operasional' => 'ditutup',
        'expected_closed' => true,
        'expected_label' => 'Pendaftaran Ditutup',
        'expected_ket' => 'Pendaftaran Ditutup'
    ],
    [
        'status_operasional' => 'full_booking',
        'expected_closed' => true,
        'expected_label' => 'Full Booking',
        'expected_ket' => 'Full Booking'
    ],
    [
        'status_operasional' => 'otomatis',
        'cutoff_simulation' => true, // simulasi waktu cutoff lewat
        'expected_closed' => true,
        'expected_label' => 'Pendaftaran Ditutup',
        'expected_ket' => 'Pendaftaran Ditutup'
    ]
];

$allCasesPass = true;
foreach ($testCases as $idx => $tc) {
    $dummyPoli = [
        'id_poli' => 1,
        'nama_poli' => 'Poli Umum',
        'is_24jam' => 0,
        'status_operasional' => $tc['status_operasional'],
        'waktu_cutoff_menit' => 60
    ];
    
    // Jika simulasi cutoff lewat, gunakan jam selesai yang sudah lewat
    $jamM = '08:00:00';
    $jamS = !empty($tc['cutoff_simulation']) ? '08:30:00' : '23:00:00'; // 08:30 pasti sudah lewat cutoff jika dijalankan siang/sore
    
    $res = cekStatusJadwalPoli($dummyPoli, $jamM, $jamS, true);
    
    $matchClosed = ($res['is_closed'] === $tc['expected_closed']);
    $matchLabel = ($res['status_label'] === $tc['expected_label']);
    $matchKet = ($res['keterangan'] === $tc['expected_ket']);
    
    $ok = ($matchClosed && $matchLabel && $matchKet);
    if (!$ok) $allCasesPass = false;
    
    echo sprintf("Case %d (%s): Closed=%s (exp %s), Label='%s' (exp '%s'), Ket='%s' (exp '%s') -> %s\n",
        $idx + 1,
        $tc['status_operasional'] . (!empty($tc['cutoff_simulation']) ? ' [Cutoff Lewat]' : ''),
        $res['is_closed'] ? 'true' : 'false',
        $tc['expected_closed'] ? 'true' : 'false',
        $res['status_label'],
        $tc['expected_label'],
        $res['keterangan'],
        $tc['expected_ket'],
        $ok ? 'PASS' : 'FAIL'
    );
}

echo "\n============================================\n";
if ($passTop && $pass24 && $allCasesPass) {
    echo "SEMUA FITUR TERVERIFIKASI BERHASIL 100%!\n";
} else {
    echo "TERDAPAT TEST YANG GAGAL!\n";
}
