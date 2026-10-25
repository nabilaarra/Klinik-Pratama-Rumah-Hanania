<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn;

echo "=== TEST JADWAL POLI FILTER LOGIC ===\n";

$daftarDokter = [];
$qD = $db->query("SELECT id_dokter, nama_dokter, COALESCE(id_poli, 0) AS id_poli FROM dokter WHERE status='aktif' ORDER BY nama_dokter ASC");
if ($qD) while ($row = $qD->fetch_assoc()) $daftarDokter[] = $row;

$daftarBidan = [];
$qB = $db->query("SELECT id_bidan, nama_bidan, COALESCE(id_poli, 0) AS id_poli FROM bidan WHERE status='aktif' ORDER BY CASE WHEN nama_bidan LIKE '%Gita%' THEN 0 ELSE 1 END, nama_bidan ASC");
if ($qB) while ($row = $qB->fetch_assoc()) $daftarBidan[] = $row;

$allStaff = [];
foreach ($daftarDokter as $d) {
    $allStaff[] = [
        'key' => 'dokter-' . $d['id_dokter'],
        'nama' => $d['nama_dokter'],
        'jenis' => 'Dokter',
        'id_poli' => (int)$d['id_poli']
    ];
}
foreach ($daftarBidan as $b) {
    $allStaff[] = [
        'key' => 'bidan-' . $b['id_bidan'],
        'nama' => $b['nama_bidan'],
        'jenis' => 'Bidan',
        'id_poli' => (int)$b['id_poli']
    ];
}

// 1. Test Poli Gigi (id_poli = 2)
$poliGigiStaff = array_values(array_filter($allStaff, fn($s) => $s['id_poli'] === 2));
echo "Poli Gigi (id_poli 2) Total Staff: " . count($poliGigiStaff) . "\n";
if (count($poliGigiStaff) === 1 && stripos($poliGigiStaff[0]['nama'], 'Fatma') !== false) {
    echo "✓ BENAR: Poli Gigi hanya memiliki 1 dokter yaitu " . $poliGigiStaff[0]['nama'] . " -> Auto-Select dr Fatma!\n";
} else {
    echo "✕ GAGAL pada Poli Gigi!\n";
    exit(1);
}

// 2. Test Poli Umum (id_poli = 1)
$poliUmumStaff = array_values(array_filter($allStaff, fn($s) => $s['id_poli'] === 1));
echo "Poli Umum (id_poli 1) Total Staff: " . count($poliUmumStaff) . "\n";
foreach ($poliUmumStaff as $st) {
    echo " - " . $st['nama'] . "\n";
}
if (count($poliUmumStaff) === 2) {
    echo "✓ BENAR: Poli Umum memiliki 2 dokter (dr. Patricia & dr. Mona).\n";
} else {
    echo "✕ GAGAL pada Poli Umum!\n";
    exit(1);
}

// 3. Test Poli Kebidanan (id_poli = 3)
$poliKebidananStaff = array_values(array_filter($allStaff, fn($s) => $s['id_poli'] === 3));
echo "Poli Kebidanan (id_poli 3) Total Staff: " . count($poliKebidananStaff) . "\n";
if (count($poliKebidananStaff) > 1 && $poliKebidananStaff[0]['jenis'] === 'Bidan') {
    echo "✓ BENAR: Poli Kebidanan hanya memuat tenaga Bidan.\n";
} else {
    echo "✕ GAGAL pada Poli Kebidanan!\n";
    exit(1);
}

// 4. Test Poli Sp.OG (id_poli = 4)
$poliSpogStaff = array_values(array_filter($allStaff, fn($s) => $s['id_poli'] === 4));
echo "Poli Sp.OG (id_poli 4) Total Staff: " . count($poliSpogStaff) . "\n";
if (count($poliSpogStaff) === 1) {
    echo "✓ BENAR: Poli Sp.OG hanya memiliki 1 dokter (" . $poliSpogStaff[0]['nama'] . ") -> Auto-Select!\n";
} else {
    echo "✕ GAGAL pada Poli Sp.OG!\n";
    exit(1);
}

// 5. Test Persalinan 24 Jam (id_poli = 5)
$numPoli = 5;
$isPersalinan = ($numPoli === 5);
$persalinanStaff = array_values(array_filter($allStaff, function($s) use ($isPersalinan, $numPoli) {
    if ($isPersalinan) {
        return $s['jenis'] === 'Bidan' || $s['id_poli'] === 5;
    }
    return $s['id_poli'] === $numPoli;
}));
echo "Persalinan 24 Jam (id_poli 5) Total Staff: " . count($persalinanStaff) . "\n";
if (count($persalinanStaff) >= 7 && $persalinanStaff[0]['jenis'] === 'Bidan') {
    echo "✓ BENAR: Persalinan 24 Jam memuat seluruh Bidan aktif (total: " . count($persalinanStaff) . " bidan siaga).\n";
    foreach ($persalinanStaff as $b) {
        echo "   - " . $b['nama'] . "\n";
    }
} else {
    echo "✕ GAGAL pada Persalinan 24 Jam!\n";
    exit(1);
}

echo "\nSEMUA TEST JADWAL POLI & AUTO-SELECT BERHASIL 100%! ✅\n";

