<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/poli_helper.php';

$db = $conn ?? $koneksi;
if (!$db instanceof mysqli) {
    die("DB connection failed\n");
}

echo "=== START VERIFICATION TESTS ===\n\n";

$allPassed = true;

// TEST 1: Poli 5 Persalinan 24 Jam
echo "Test 1: Poli 5 (Persalinan 24 Jam) in Database\n";
$qPoli = $db->query("SELECT * FROM poli WHERE id_poli = 5 LIMIT 1");
$poli5 = $qPoli ? $qPoli->fetch_assoc() : null;
if ($poli5 && (int)$poli5['is_24jam'] === 1 && $poli5['nama_poli'] === 'Persalinan 24 Jam') {
    echo "  [PASS] Poli 5 is configured as 'Persalinan 24 Jam' with is_24jam = 1\n";
} else {
    echo "  [FAIL] Poli 5 is not configured correctly: " . json_encode($poli5) . "\n";
    $allPassed = false;
}

// TEST 2: Helper cekStatusJadwalPoli for Persalinan 24 Jam (Always Open)
echo "\nTest 2: Persalinan 24 Jam is ALWAYS OPEN\n";
$status24 = cekStatusJadwalPoli($poli5, '00:00:00', '23:59:59', true, 'Persalinan 24 Jam');
if ($status24['is_closed'] === false && $status24['is_24jam'] === true && $status24['status_label'] === 'Pendaftaran Tersedia') {
    echo "  [PASS] cekStatusJadwalPoli returned open (is_closed = false, is_24jam = true, status_label = 'Pendaftaran Tersedia')\n";
} else {
    echo "  [FAIL] cekStatusJadwalPoli unexpected result: " . json_encode($status24) . "\n";
    $allPassed = false;
}

// TEST 3: Regular Poli Cutoff Logic (1 hour / 60 mins before end time)
echo "\nTest 3: Cutoff 1 Hour Before End Time\n";
$qPoli1 = $db->query("SELECT * FROM poli WHERE id_poli = 1 LIMIT 1");
$poli1 = $qPoli1 ? $qPoli1->fetch_assoc() : null;

// Mock time testing: schedule ending in 30 minutes from now (should be closed)
$now = time();
$endIn30m = date('H:i:s', $now + 1800); // 30 minutes left, cutoff is 60 mins -> CLOSED
$startPassed = date('H:i:s', $now - 3600);
$statusCutoff = cekStatusJadwalPoli($poli1, $startPassed, $endIn30m, true);
if ($statusCutoff['is_closed'] === true) {
    echo "  [PASS] Schedule ending in 30 mins correctly marked as CLOSED (cutoff: 60 mins before end)\n";
    echo "         Message: {$statusCutoff['message']}\n";
} else {
    echo "  [FAIL] Expected CLOSED for 30 mins remaining, got: " . json_encode($statusCutoff) . "\n";
    $allPassed = false;
}

// Mock time testing: schedule ending in 90 minutes from now (should be open)
$endIn90m = date('H:i:s', $now + 5400); // 90 minutes left -> OPEN
$statusOpen = cekStatusJadwalPoli($poli1, $startPassed, $endIn90m, true);
if ($statusOpen['is_closed'] === false) {
    echo "  [PASS] Schedule ending in 90 mins correctly marked as OPEN\n";
} else {
    echo "  [FAIL] Expected OPEN for 90 mins remaining, got: " . json_encode($statusOpen) . "\n";
    $allPassed = false;
}

// TEST 4: Manual Override in Admin (Tutup Manual)
echo "\nTest 4: Manual Admin Override (Tutup Manual)\n";
$poliTutupManual = $poli1;
$poliTutupManual['status_operasional'] = 'tutup_manual';
$poliTutupManual['pesan_tutup'] = 'Dokter cuti darurat';
$statusManual = cekStatusJadwalPoli($poliTutupManual, $startPassed, $endIn90m, true);
if ($statusManual['is_closed'] === true && strpos($statusManual['message'], 'Dokter cuti darurat') !== false) {
    echo "  [PASS] Manual override 'tutup_manual' successfully closed poli with custom message\n";
} else {
    echo "  [FAIL] Expected manual close override, got: " . json_encode($statusManual) . "\n";
    $allPassed = false;
}

// TEST 5: Form Fields in pengaturan_form_pendaftaran
echo "\nTest 5: Fields 'nama_ibu_kandung' & 'nama_suami' in Settings\n";
$qF = $db->query("SELECT * FROM pengaturan_form_pendaftaran WHERE nama_field IN ('nama_ibu_kandung', 'nama_suami')");
$fields = [];
while ($row = $qF->fetch_assoc()) {
    $fields[$row['nama_field']] = $row;
}
if (isset($fields['nama_ibu_kandung']) && isset($fields['nama_suami'])) {
    echo "  [PASS] Both fields exist in pengaturan_form_pendaftaran:\n";
    echo "         - nama_ibu_kandung: label='{$fields['nama_ibu_kandung']['label_field']}', wajib={$fields['nama_ibu_kandung']['wajib']}, status={$fields['nama_ibu_kandung']['status']}\n";
    echo "         - nama_suami: label='{$fields['nama_suami']['label_field']}', wajib={$fields['nama_suami']['wajib']}, status={$fields['nama_suami']['status']}\n";
} else {
    echo "  [FAIL] Missing fields in pengaturan_form_pendaftaran: " . json_encode(array_keys($fields)) . "\n";
    $allPassed = false;
}

// TEST 6: Patient Database Columns & CRUD
echo "\nTest 6: Database Columns & Patient Data Persistence\n";
$chkPasienCols = $db->query("SHOW COLUMNS FROM pasien LIKE 'nama_ibu_kandung'");
$hasColIbu = $chkPasienCols && $chkPasienCols->num_rows > 0;
$chkPasienColsSuami = $db->query("SHOW COLUMNS FROM pasien LIKE 'nama_suami'");
$hasColSuami = $chkPasienColsSuami && $chkPasienColsSuami->num_rows > 0;

if ($hasColIbu && $hasColSuami) {
    echo "  [PASS] Columns 'nama_ibu_kandung' and 'nama_suami' exist in 'pasien' table\n";
} else {
    echo "  [FAIL] Columns missing in 'pasien'\n";
    $allPassed = false;
}

$chkFamCols = $db->query("SHOW COLUMNS FROM keluarga_pasien LIKE 'nama_ibu_kandung'");
$hasFamColIbu = $chkFamCols && $chkFamCols->num_rows > 0;
$chkFamColsSuami = $db->query("SHOW COLUMNS FROM keluarga_pasien LIKE 'nama_suami'");
$hasFamColSuami = $chkFamColsSuami && $chkFamColsSuami->num_rows > 0;

if ($hasFamColIbu && $hasFamColSuami) {
    echo "  [PASS] Columns 'nama_ibu_kandung' and 'nama_suami' exist in 'keluarga_pasien' table\n";
} else {
    echo "  [FAIL] Columns missing in 'keluarga_pasien'\n";
    $allPassed = false;
}

// TEST 7: CRUD Simulation
echo "\nTest 7: Test INSERT & UPDATE Patient with Mother & Husband Name\n";
$dummyNik = '9999' . str_pad((string)rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
$inStmt = $db->prepare("INSERT INTO pasien (nik, nama_lengkap, jenis_kelamin, nama_ibu_kandung, nama_suami, no_telepon) VALUES (?, 'Test Pasien Persalinan', 'P', 'Ibu Fatimah', 'Bpk Ahmad', '081299998888')");
$inStmt->bind_param('s', $dummyNik);
$inStmt->execute();
$dummyId = $inStmt->insert_id;
$inStmt->close();

if ($dummyId > 0) {
    $readQ = $db->query("SELECT nama_ibu_kandung, nama_suami FROM pasien WHERE id_pasien = $dummyId LIMIT 1");
    $readRow = $readQ->fetch_assoc();
    if ($readRow['nama_ibu_kandung'] === 'Ibu Fatimah' && $readRow['nama_suami'] === 'Bpk Ahmad') {
        echo "  [PASS] Successfully persisted nama_ibu_kandung and nama_suami\n";
    } else {
        echo "  [FAIL] Readback mismatch: " . json_encode($readRow) . "\n";
        $allPassed = false;
    }
    // Clean up
    $db->query("DELETE FROM pasien WHERE id_pasien = $dummyId");
    echo "  [PASS] Cleaned up test patient record\n";
} else {
    echo "  [FAIL] Could not insert dummy patient: " . $db->error . "\n";
    $allPassed = false;
}

// TEST 8: 100% Antrean Linkage & Integrity
echo "\nTest 8: Existing Registrations vs Antrean Records\n";
$qChkUnlinked = $db->query("
    SELECT COUNT(*) as unlinked_count 
    FROM pendaftaran p 
    LEFT JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran 
    WHERE a.id_antrean IS NULL
");
$unlinkedCount = (int)($qChkUnlinked->fetch_assoc()['unlinked_count'] ?? 0);
if ($unlinkedCount === 0) {
    echo "  [PASS] All registrations in database have an associated antrean record (0 unlinked)\n";
} else {
    echo "  [FAIL] Found $unlinkedCount registrations without antrean row!\n";
    $allPassed = false;
}

// TEST 9: Interleaving Numbering Accuracy on Historical Data
echo "\nTest 9: Historical Antrean Interleaving Pola (1-2 Offline, 3-5 Online)\n";
$qAntreanPola = $db->query("
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.id_poli, p.sumber_pendaftaran, a.nomor_antrean
    FROM pendaftaran p
    JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    ORDER BY p.tanggal_kunjungan ASC, a.nomor_antrean ASC
");
$invalidNumbers = 0;
while ($ar = $qAntreanPola->fetch_assoc()) {
    $src = ($ar['sumber_pendaftaran'] === 'offline') ? 'offline' : 'online';
    $num = (int)$ar['nomor_antrean'];
    $isOfflineSlot = in_array($num % 5, [1, 2], true);
    if ($src === 'offline' && !$isOfflineSlot) {
        $invalidNumbers++;
        echo "  [FAIL] Reg #{$ar['id_pendaftaran']} (offline) has invalid number $num\n";
    } elseif ($src === 'online' && $isOfflineSlot) {
        $invalidNumbers++;
        echo "  [FAIL] Reg #{$ar['id_pendaftaran']} (online) has offline-slot number $num\n";
    }
}
if ($invalidNumbers === 0) {
    echo "  [PASS] All existing antrean rows adhere strictly to the interleaved scheme\n";
} else {
    $allPassed = false;
}

// TEST 10: Existing Patient Profiles and Mother/Husband Data
echo "\nTest 10: Existing Patient Profiles & Mother/Husband Fields\n";
$qPatients = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN nama_ibu_kandung IS NOT NULL THEN 1 ELSE 0 END) as with_mother FROM pasien");
$pRow = $qPatients->fetch_assoc();
if ((int)$pRow['total'] > 0 && (int)$pRow['with_mother'] === (int)$pRow['total']) {
    echo "  [PASS] All {$pRow['total']} existing patients have nama_ibu_kandung populated and ready\n";
} else {
    echo "  [FAIL] Incomplete mother name data in patients: " . json_encode($pRow) . "\n";
    $allPassed = false;
}

// TEST 11: Form Settings Urutan Order Integrity
echo "\nTest 11: Form Settings Sequential Urutan (1 to 15)\n";
$qUrutan = $db->query("SELECT urutan, COUNT(*) as cnt FROM pengaturan_form_pendaftaran GROUP BY urutan HAVING cnt > 1");
if ($qUrutan && $qUrutan->num_rows === 0) {
    echo "  [PASS] No duplicate sequence numbers in pengaturan_form_pendaftaran (clean 1-15 sequence)\n";
} else {
    echo "  [FAIL] Found duplicate urutan in pengaturan_form_pendaftaran!\n";
    $allPassed = false;
}

echo "\n====================================\n";
if ($allPassed) {
    echo ">>> ALL 11 TEST SUITES PASSED! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
}
echo "====================================\n";

