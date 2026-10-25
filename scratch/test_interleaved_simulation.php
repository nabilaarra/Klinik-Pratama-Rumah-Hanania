<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Database connection failed\n");
}

$testDate = '2099-12-31';
$idPoli = 1;
$idDokter = 1;
$idBidan = 0;

// Clean up any test records
$db->query("DELETE a, p FROM pendaftaran p LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran WHERE p.tanggal_kunjungan = '$testDate'");

echo "=== MEMULAI TEST SIMULASI POLA ANTREAN ===\n";
echo "Urutan pendaftaran yang akan diuji:\n";
echo "1. Offline\n2. Online\n3. Offline\n4. Online\n5. Online\n6. Offline\n7. Online\n8. Offline\n9. Online\n10. Online\n11. Offline\n\n";

$steps = [
    ['channel' => 'offline', 'expected' => 1],
    ['channel' => 'online',  'expected' => 3],
    ['channel' => 'offline', 'expected' => 2],
    ['channel' => 'online',  'expected' => 4],
    ['channel' => 'online',  'expected' => 5],
    ['channel' => 'offline', 'expected' => 6],
    ['channel' => 'online',  'expected' => 8],
    ['channel' => 'offline', 'expected' => 7],
    ['channel' => 'online',  'expected' => 9],
    ['channel' => 'online',  'expected' => 10],
    ['channel' => 'offline', 'expected' => 11],
    ['channel' => 'offline', 'expected' => 12],
    ['channel' => 'online',  'expected' => 13],
];

$allPassed = true;

foreach ($steps as $idx => $step) {
    $channel = $step['channel'];
    $expected = $step['expected'];
    
    // Hitung nomor berikutnya
    $calc = hitungNomorAntreanBerikutnya($db, $testDate, $idPoli, $idDokter, $idBidan, $channel, '08:00');
    $assignedNumber = $calc['nomor'];
    
    $status = ($assignedNumber === $expected) ? "PASS" : "FAIL (Expected $expected, got $assignedNumber)";
    if ($assignedNumber !== $expected) {
        $allPassed = false;
    }
    
    echo sprintf("[%s] Step %02d (%s): Nomor = %d (%s) -> %s\n",
        ($assignedNumber === $expected ? "OK" : "ERR"),
        $idx + 1,
        strtoupper($channel),
        $assignedNumber,
        $calc['format'],
        $status
    );
    
    // Simpan dummy registration & antrean ke database untuk step berikutnya
    $stmtP = $db->prepare("
        INSERT INTO pendaftaran (id_pasien, id_poli, id_dokter, tanggal_kunjungan, jam_kunjungan, jenis_pasien, jenis_perawatan, status_pendaftaran, sumber_pendaftaran)
        VALUES (1, ?, ?, ?, '08:00:00', 'Umum', 'Pemeriksaan Rawat Jalan', 'terdaftar', ?)
    ");
    $stmtP->bind_param('iiss', $idPoli, $idDokter, $testDate, $channel);
    $stmtP->execute();
    $newId = $stmtP->insert_id;
    $stmtP->close();
    
    $stmtA = $db->prepare("INSERT INTO antrean (id_pendaftaran, nomor_antrean, estimasi_waktu, status_antrean) VALUES (?, ?, '08:00:00', 'menunggu')");
    $stmtA->bind_param('ii', $newId, $assignedNumber);
    $stmtA->execute();
    $stmtA->close();
}

// Clean up test records
$db->query("DELETE a, p FROM pendaftaran p LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran WHERE p.tanggal_kunjungan = '$testDate'");

echo "\n============================================\n";
if ($allPassed) {
    echo "SEMUA TEST BERHASIL 100%! Pola nomor urut antrean offline (1-2, 6-7, 11-12...) dan online (3-5, 8-10, 13-15...) berjalan sempurna dan tidak pernah bentrok.\n";
} else {
    echo "ADA TEST YANG GAGAL!\n";
}
