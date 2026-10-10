<?php
session_start();
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';
$_SESSION['role'] = 'admin';

function testPost($postData, $filesData = []) {
    $_POST = $postData;
    $_FILES = $filesData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    include __DIR__ . '/../admin/dokter.php';
    $html = ob_get_clean();
    
    // Check message in HTML
    if (preg_match('/<div class="alert ([^"]+)"><span>[^<]+<\/span>\s*(.*?)<\/div>/s', $html, $m)) {
        return ['type' => $m[1], 'message' => trim($m[2])];
    }
    return ['type' => 'none', 'message' => 'No alert found'];
}

echo "1. Testing POST Tambah Dokter:\n";
$res = testPost([
    'action' => 'tambah',
    'type' => 'dokter',
    'nama' => 'dr. Simulasi Test, Sp.A',
    'id_poli' => '1',
    'spesialisasi' => 'Spesialis Anak',
    'no_telepon' => '081122334455',
    'status' => 'aktif',
    'foto_cropped' => ''
]);
print_r($res);

// Get inserted ID
require __DIR__ . '/../config/database.php';
$q = $conn->query("SELECT id_dokter FROM dokter WHERE nama_dokter = 'dr. Simulasi Test, Sp.A' ORDER BY id_dokter DESC LIMIT 1");
$row = $q->fetch_assoc();
$insertedId = $row['id_dokter'] ?? 0;
echo "Inserted ID: $insertedId\n";

if ($insertedId > 0) {
    echo "\n2. Testing POST Edit Dokter:\n";
    $res = testPost([
        'action' => 'edit',
        'id' => $insertedId,
        'type' => 'dokter',
        'nama' => 'dr. Simulasi Test, Sp.A (Updated)',
        'id_poli' => '1',
        'spesialisasi' => 'Spesialis Anak & Remaja',
        'no_telepon' => '081122334455',
        'status' => 'aktif',
        'foto_cropped' => ''
    ]);
    print_r($res);

    echo "\n3. Testing POST Hapus Dokter:\n";
    $res = testPost([
        'action' => 'hapus',
        'id' => $insertedId,
        'type' => 'dokter'
    ]);
    print_r($res);
}
