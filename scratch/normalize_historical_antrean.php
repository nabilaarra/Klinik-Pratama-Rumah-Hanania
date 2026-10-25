<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/antrean_helper.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Database connection failed\n");
}

// Group registrations by tanggal_kunjungan, id_poli, and staff
$sql = "
    SELECT p.id_pendaftaran, p.tanggal_kunjungan, p.id_poli, p.id_dokter, p.id_bidan,
           p.sumber_pendaftaran, a.id_antrean, a.nomor_antrean
    FROM pendaftaran p
    JOIN antrean a ON a.id_pendaftaran = p.id_pendaftaran
    WHERE p.status_pendaftaran != 'dibatalkan'
    ORDER BY p.tanggal_kunjungan ASC, p.id_pendaftaran ASC
";
$res = $db->query($sql);

$groups = [];
while ($row = $res->fetch_assoc()) {
    $staff = ($row['id_dokter'] > 0) ? 'doc_' . $row['id_dokter'] : 'bid_' . $row['id_bidan'];
    $key = $row['tanggal_kunjungan'] . '_' . $row['id_poli'] . '_' . $staff;
    $groups[$key][] = $row;
}

echo "=== MEMPERBAIKI NOMOR ANTREAN HISTORIS SESUAI POLA 1-2 OFFLINE & 3-5 ONLINE ===\n";
foreach ($groups as $groupKey => $rows) {
    $offlineIdx = 0;
    $onlineIdx = 0;
    
    foreach ($rows as $r) {
        $channel = ($r['sumber_pendaftaran'] === 'offline') ? 'offline' : 'online';
        if ($channel === 'offline') {
            $offlineIdx++;
            $correctNumber = getAntreanNomorByUrutan($offlineIdx, 'offline');
        } else {
            $onlineIdx++;
            $correctNumber = getAntreanNomorByUrutan($onlineIdx, 'online');
        }
        
        $currentNumber = (int)$r['nomor_antrean'];
        if ($currentNumber !== $correctNumber) {
            echo "Group {$groupKey} - Reg #{$r['id_pendaftaran']} ({$channel}): No Lama = {$currentNumber} -> No Baru = {$correctNumber}\n";
            $db->query("UPDATE antrean SET nomor_antrean = $correctNumber WHERE id_antrean = {$r['id_antrean']}");
        } else {
            echo "Group {$groupKey} - Reg #{$r['id_pendaftaran']} ({$channel}): No = {$currentNumber} (Sudah Sesuai)\n";
        }
    }
}

echo "Perbaikan nomor antrean historis selesai!\n";
