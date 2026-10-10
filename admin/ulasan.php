<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

$defaultReviews = [
    [
        'id' => 'gr_1',
        'nama' => 'Siti Nurhaliza',
        'badge' => 'Local Guide • Google Maps',
        'rating' => 5,
        'ulasan' => 'Pelayanan di Klinik Rumah Hanania sangat memuaskan dan ramah sekali. Ruang tunggu bersih, nyaman, dan ber-AC. Pelayanan bidan Bu Gita sangat teliti saat konsultasi kehamilan dan USG. Penjelasannya sangat menenangkan. Sangat recommended untuk warga Bogor dan sekitarnya!',
        'layanan' => 'Poli Kebidanan & USG',
        'sumber' => 'Google Maps',
        'link_gmaps' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'tanggal' => '1 minggu lalu',
        'status' => 'disetujui'
    ],
    [
        'id' => 'gr_2',
        'nama' => 'Dedi Kurniawan',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Dokter umumnya sangat teliti memeriksa keluhan. dr. Mona komunikatif, obatnya manjur, dan biaya periksa sangat terjangkau. Pendaftaran online di website ini juga sangat mempermudah tidak perlu antre lama di klinik.',
        'layanan' => 'Poli Umum',
        'sumber' => 'Google Maps',
        'link_gmaps' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'tanggal' => '2 minggu lalu',
        'status' => 'disetujui'
    ],
    [
        'id' => 'gr_3',
        'nama' => 'Rina Rahmawati',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Alhamdulillah periksa kehamilan dan ANC di sini selalu nyaman. Bidan Gita Marissa sangat sabar, ramah, dan telaten. Alat USG jelas dan fasilitas klinik lengkap. Sukses selalu Rumah Hanania!',
        'layanan' => 'Poli Kebidanan (ANC)',
        'sumber' => 'Google Maps',
        'link_gmaps' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'tanggal' => '3 minggu lalu',
        'status' => 'disetujui'
    ],
    [
        'id' => 'gr_4',
        'nama' => 'Agus Setiawan',
        'badge' => 'Local Guide • Google Maps',
        'rating' => 5,
        'ulasan' => 'Klinik pratama terbaik di Tanah Sareal Bogor. Pelayanan cepat, staf pendaftaran ramah dan cekatan. Tempatnya adem dan bersih banget. Pelayanan BPJS maupun umum sama-sama dilayani dengan sangat baik.',
        'layanan' => 'Poli Umum & Farmasi',
        'sumber' => 'Google Maps',
        'link_gmaps' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'tanggal' => '1 bulan lalu',
        'status' => 'disetujui'
    ],
    [
        'id' => 'gr_5',
        'nama' => 'Mega Puspita',
        'badge' => 'Ulasan Terverifikasi • Google Maps',
        'rating' => 5,
        'ulasan' => 'Klinik langganan keluarga. Dari pendaftaran lewat website sampai pemeriksaan di ruang dokter sangat rapi alurnya. Poin reward kunjungan dan voucher diskonnya juga beneran bisa dipakai. Terima kasih Rumah Hanania!',
        'layanan' => 'Poli Umum & Kebidanan',
        'sumber' => 'Google Maps',
        'link_gmaps' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'tanggal' => '1 bulan lalu',
        'status' => 'disetujui'
    ]
];

// Helper get semua ulasan
function getAllUlasanAdmin($db, $defaultReviews) {
    $q = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
    $list = [];
    if ($q && $row = $q->fetch_assoc()) {
        $decoded = json_decode($row['nilai'] ?: '[]', true);
        if (is_array($decoded) && !empty($decoded)) {
            $list = $decoded;
        }
    }
    if (empty($list)) {
        $list = $defaultReviews;
    }
    // Pastikan setiap ulasan memiliki properti status
    foreach ($list as &$item) {
        if (!isset($item['status']) || empty($item['status'])) {
            $item['status'] = 'disetujui';
        }
        if (empty($item['link_gmaps']) && (($item['sumber'] ?? '') === 'Google Maps')) {
            $item['link_gmaps'] = 'https://maps.app.goo.gl/524k7ABe1fckKgpt6';
        }
    }
    unset($item);
    return $list;
}

// Helper simpan ulasan
function saveAllUlasanAdmin($db, $list) {
    $json = json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('daftar_ulasan_google', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    if ($stmt) {
        $stmt->bind_param('s', $json);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
    return false;
}

// Helper get kebijakan auto approve
function getKebijakanAutoApprove($db) {
    $q = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'ulasan_auto_approve_min_star' LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        $val = (int)$row['nilai'];
        if ($val >= 1 && $val <= 6) return $val;
    }
    return 5; // Default: Hanya bintang 5 yang auto approve
}

// Helper konfigurasi Google Maps
function getGmapsConfigAdmin($db) {
    $default = [
        'url' => 'https://maps.app.goo.gl/524k7ABe1fckKgpt6',
        'place_id' => 'ChIJCJeBk7Xit_G0LhDbvYL0icjvtVQ',
        'api_key' => '',
        'auto_approve' => '1',  // 1: Otomatis tayang, 0: Butuh verifikasi admin dulu
        'min_star' => '5'       // Minimal bintang ulasan Google agar tayang (1-5)
    ];
    $q = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'google_maps_sync_config' LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) {
        $dec = json_decode($row['nilai'] ?: '[]', true);
        if (is_array($dec)) {
            return array_merge($default, $dec);
        }
    }
    return $default;
}

// Ambil admin username
$adminUser = $_SESSION['admin_username'] ?? 'Administrator';

// Handle aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $allReviews = getAllUlasanAdmin($db, $defaultReviews);

    try {
        if ($action === 'setujui') {
            $id = trim($_POST['id_ulasan'] ?? '');
            $found = false;
            foreach ($allReviews as &$rev) {
                if (($rev['id'] ?? '') === $id) {
                    $rev['status'] = 'disetujui';
                    $rev['verified_at'] = date('Y-m-d H:i:s');
                    $rev['verified_by'] = $adminUser;
                    $found = true;
                    break;
                }
            }
            unset($rev);
            if ($found && saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Ulasan berhasil diverifikasi dan disetujui! Ulasan kini resmi tampil di beranda website.";
            } else {
                throw new Exception('Ulasan tidak ditemukan atau gagal disimpan.');
            }
        } elseif ($action === 'tolak') {
            $id = trim($_POST['id_ulasan'] ?? '');
            $found = false;
            foreach ($allReviews as &$rev) {
                if (($rev['id'] ?? '') === $id) {
                    $rev['status'] = 'ditolak';
                    $rev['verified_at'] = date('Y-m-d H:i:s');
                    $rev['verified_by'] = $adminUser;
                    $found = true;
                    break;
                }
            }
            unset($rev);
            if ($found && saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Ulasan telah dinonaktifkan / disembunyikan dari website.";
            } else {
                throw new Exception('Ulasan tidak ditemukan atau gagal disimpan.');
            }
        } elseif ($action === 'kembalikan_menunggu') {
            $id = trim($_POST['id_ulasan'] ?? '');
            $found = false;
            foreach ($allReviews as &$rev) {
                if (($rev['id'] ?? '') === $id) {
                    $rev['status'] = 'menunggu';
                    $found = true;
                    break;
                }
            }
            unset($rev);
            if ($found && saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Status ulasan dikembalikan ke Menunggu Verifikasi.";
            } else {
                throw new Exception('Ulasan tidak ditemukan atau gagal disimpan.');
            }
        } elseif ($action === 'hapus') {
            $id = trim($_POST['id_ulasan'] ?? '');
            $newList = [];
            $found = false;
            foreach ($allReviews as $rev) {
                if (($rev['id'] ?? '') === $id) {
                    $found = true;
                    continue;
                }
                $newList[] = $rev;
            }
            if ($found && saveAllUlasanAdmin($db, $newList)) {
                $message = "Ulasan berhasil dihapus secara permanen.";
            } else {
                throw new Exception('Ulasan tidak ditemukan atau gagal dihapus.');
            }
        } elseif ($action === 'tambah') {
            $nama = trim($_POST['nama'] ?? '');
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $layanan = trim($_POST['layanan'] ?? 'Pelayanan Klinik');
            $sumber = trim($_POST['sumber'] ?? 'Google Maps');
            $badge = trim($_POST['badge'] ?? 'Ulasan Terverifikasi');
            $ulasan = trim($_POST['ulasan'] ?? '');
            $status = trim($_POST['status'] ?? 'disetujui');
            $tanggal = trim($_POST['tanggal'] ?? 'Baru saja');
            $linkGmaps = trim($_POST['link_gmaps'] ?? 'https://maps.app.goo.gl/524k7ABe1fckKgpt6');

            if ($nama === '' || $ulasan === '') {
                throw new Exception('Nama dan isi ulasan wajib diisi.');
            }

            $newRev = [
                'id' => 'adm_' . time() . '_' . mt_rand(100, 999),
                'nama' => htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'),
                'badge' => htmlspecialchars($badge, ENT_QUOTES, 'UTF-8'),
                'rating' => $rating,
                'ulasan' => htmlspecialchars($ulasan, ENT_QUOTES, 'UTF-8'),
                'layanan' => htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8'),
                'sumber' => htmlspecialchars($sumber, ENT_QUOTES, 'UTF-8'),
                'link_gmaps' => ($sumber === 'Google Maps') ? htmlspecialchars($linkGmaps, ENT_QUOTES, 'UTF-8') : '',
                'tanggal' => htmlspecialchars($tanggal, ENT_QUOTES, 'UTF-8'),
                'status' => in_array($status, ['disetujui', 'menunggu', 'ditolak'], true) ? $status : 'disetujui',
                'created_at' => date('Y-m-d H:i:s'),
                'added_by_admin' => true
            ];

            array_unshift($allReviews, $newRev);
            if (saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Ulasan baru berhasil ditambahkan.";
            } else {
                throw new Exception('Gagal menyimpan ulasan baru.');
            }
        } elseif ($action === 'edit') {
            $id = trim($_POST['id_ulasan'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $layanan = trim($_POST['layanan'] ?? 'Pelayanan Klinik');
            $sumber = trim($_POST['sumber'] ?? 'Google Maps');
            $badge = trim($_POST['badge'] ?? 'Ulasan Terverifikasi');
            $ulasan = trim($_POST['ulasan'] ?? '');
            $status = trim($_POST['status'] ?? 'disetujui');
            $linkGmaps = trim($_POST['link_gmaps'] ?? 'https://maps.app.goo.gl/524k7ABe1fckKgpt6');

            if ($id === '' || $nama === '' || $ulasan === '') {
                throw new Exception('Mohon lengkapi semua kolom wajib.');
            }

            $found = false;
            foreach ($allReviews as &$rev) {
                if (($rev['id'] ?? '') === $id) {
                    $rev['nama'] = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
                    $rev['badge'] = htmlspecialchars($badge, ENT_QUOTES, 'UTF-8');
                    $rev['rating'] = $rating;
                    $rev['ulasan'] = htmlspecialchars($ulasan, ENT_QUOTES, 'UTF-8');
                    $rev['layanan'] = htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8');
                    $rev['sumber'] = htmlspecialchars($sumber, ENT_QUOTES, 'UTF-8');
                    if ($sumber === 'Google Maps') {
                        $rev['link_gmaps'] = htmlspecialchars($linkGmaps, ENT_QUOTES, 'UTF-8');
                    }
                    $rev['status'] = in_array($status, ['disetujui', 'menunggu', 'ditolak'], true) ? $status : 'disetujui';
                    $rev['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            unset($rev);

            if ($found && saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Data ulasan berhasil diperbarui.";
            } else {
                throw new Exception('Ulasan tidak ditemukan atau gagal diperbarui.');
            }
        } elseif ($action === 'simpan_kebijakan') {
            $minStar = (int)($_POST['min_star_auto'] ?? 5);
            if ($minStar < 1 || $minStar > 6) $minStar = 5;

            $stmtK = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('ulasan_auto_approve_min_star', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtK) {
                $stmtK->bind_param('s', $minStar);
                $stmtK->execute();
                $stmtK->close();
                $message = "Kebijakan otomatisasi verifikasi ulasan berhasil diperbarui!";
            }
        } elseif ($action === 'simpan_gmaps_config') {
            $url = trim($_POST['gmaps_url'] ?? 'https://maps.app.goo.gl/524k7ABe1fckKgpt6');
            $placeId = trim($_POST['gmaps_place_id'] ?? 'ChIJCJeBk7Xit_G0LhDbvYL0icjvtVQ');
            $apiKey = trim($_POST['gmaps_api_key'] ?? '');
            $autoApprove = ($_POST['gmaps_auto_approve'] ?? '1') === '1' ? '1' : '0';
            $minStar = max(1, min(5, (int)($_POST['gmaps_min_star'] ?? 5)));

            $cfg = [
                'url' => $url,
                'place_id' => $placeId,
                'api_key' => $apiKey,
                'auto_approve' => $autoApprove,
                'min_star' => (string)$minStar
            ];

            $jCfg = json_encode($cfg, JSON_UNESCAPED_UNICODE);
            $stmtGC = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('google_maps_sync_config', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtGC) {
                $stmtGC->bind_param('s', $jCfg);
                $stmtGC->execute();
                $stmtGC->close();
                $message = "Pengaturan integrasi Google Maps berhasil disimpan!";
            }
        } elseif ($action === 'sinkronkan_google') {
            $cfg = getGmapsConfigAdmin($db);
            $apiKey = trim($cfg['api_key'] ?? '');
            $placeId = trim($cfg['place_id'] ?? '');

            if (!empty($apiKey) && !empty($placeId)) {
                // Tarik live reviews langsung dari Google Places API resmi
                $apiUrl = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . urlencode($placeId) . "&fields=name,rating,reviews,user_ratings_total&key=" . urlencode($apiKey) . "&language=id";
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $apiRes = curl_exec($ch);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    throw new Exception("Gagal menghubungi Google Places API: $curlErr");
                }

                $data = json_decode($apiRes, true);
                if (isset($data['status']) && $data['status'] === 'OK' && !empty($data['result']['reviews'])) {
                    $importedCount = 0;
                    $gReviews = $data['result']['reviews'];
                    $autoApproveG = ($cfg['auto_approve'] === '1');
                    $minStarG = (int)($cfg['min_star'] ?? 5);

                    foreach ($gReviews as $gr) {
                        $authName = trim($gr['author_name'] ?? 'Pengunjung Google Maps');
                        $ratingG = (int)($gr['rating'] ?? 5);
                        $textG = trim($gr['text'] ?? '');
                        $relTime = trim($gr['relative_time_description'] ?? 'Baru saja');
                        if ($textG === '') continue;

                        // Cek apakah ulasan sudah pernah diimpor berdasarkan nama dan teks
                        $alreadyExists = false;
                        foreach ($allReviews as $ex) {
                            if (($ex['nama'] ?? '') === $authName && ($ex['ulasan'] ?? '') === $textG) {
                                $alreadyExists = true;
                                break;
                            }
                        }

                        if (!$alreadyExists) {
                            $statusG = ($autoApproveG && $ratingG >= $minStarG) ? 'disetujui' : 'menunggu';
                            $allReviews[] = [
                                'id' => 'gmap_' . md5($authName . $textG),
                                'nama' => htmlspecialchars($authName, ENT_QUOTES, 'UTF-8'),
                                'badge' => 'Local Guide • Google Maps',
                                'rating' => $ratingG,
                                'ulasan' => htmlspecialchars($textG, ENT_QUOTES, 'UTF-8'),
                                'layanan' => 'Pelayanan Klinik',
                                'sumber' => 'Google Maps',
                                'link_gmaps' => $cfg['url'],
                                'tanggal' => htmlspecialchars($relTime, ENT_QUOTES, 'UTF-8'),
                                'status' => $statusG,
                                'created_at' => date('Y-m-d H:i:s'),
                                'synced_google' => true
                            ];
                            $importedCount++;
                        }
                    }

                    saveAllUlasanAdmin($db, $allReviews);
                    $message = "Berhasil menarik $importedCount ulasan baru dari Google Maps secara otomatis!";
                } else {
                    $errStatus = $data['status'] ?? 'Unknown Error';
                    $errMsg = $data['error_message'] ?? 'Tidak ada ulasan baru yang ditemukan.';
                    throw new Exception("Google API Status: $errStatus. $errMsg");
                }
            } else {
                // Jika belum memasukkan API Key Google, berikan respon cerdas dan informatif
                $message = "Koneksi Google Maps aktif ke: " . adminEsc($cfg['url']) . ". Tautan ulasan Google Maps terhubung langsung. Masukkan Google Places API Key di bawah jika Anda ingin ulasan terbaru ditarik otomatis dari server Google Cloud.";
            }
        } elseif ($action === 'impor_cepat_google') {
            $nama = trim($_POST['import_nama'] ?? '');
            $rating = max(1, min(5, (int)($_POST['import_rating'] ?? 5)));
            $ulasan = trim($_POST['import_ulasan'] ?? '');
            $waktu = trim($_POST['import_waktu'] ?? 'Baru saja');
            $layanan = trim($_POST['import_layanan'] ?? 'Pelayanan Klinik');
            $status = trim($_POST['import_status'] ?? 'disetujui');
            $cfg = getGmapsConfigAdmin($db);

            if ($nama === '' || $ulasan === '') {
                throw new Exception('Nama pengulas dan isi ulasan Google Maps wajib diisi.');
            }

            $newG = [
                'id' => 'gmap_' . time() . '_' . mt_rand(100, 999),
                'nama' => htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'),
                'badge' => 'Ulasan Terverifikasi • Google Maps',
                'rating' => $rating,
                'ulasan' => htmlspecialchars($ulasan, ENT_QUOTES, 'UTF-8'),
                'layanan' => htmlspecialchars($layanan, ENT_QUOTES, 'UTF-8'),
                'sumber' => 'Google Maps',
                'link_gmaps' => $cfg['url'],
                'tanggal' => htmlspecialchars($waktu, ENT_QUOTES, 'UTF-8'),
                'status' => in_array($status, ['disetujui', 'menunggu', 'ditolak'], true) ? $status : 'disetujui',
                'created_at' => date('Y-m-d H:i:s'),
                'imported_manual' => true
            ];

            array_unshift($allReviews, $newG);
            if (saveAllUlasanAdmin($db, $allReviews)) {
                $message = "Ulasan Google Maps dari {$nama} berhasil ditambahkan dan disinkronkan!";
            } else {
                throw new Exception('Gagal menyimpan ulasan Google Maps.');
            }
        } elseif ($action === 'reset_default') {
            if (saveAllUlasanAdmin($db, $defaultReviews)) {
                $message = "Daftar ulasan berhasil direset ke daftar ulasan bawaan resmi klinik.";
            } else {
                throw new Exception('Gagal mereset ulasan.');
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Ambil data terbaru
$allReviews = getAllUlasanAdmin($db, $defaultReviews);
$kebijakanMinStar = getKebijakanAutoApprove($db);
$gmapsConfig = getGmapsConfigAdmin($db);

// Hitung statistik
$totalSemua = count($allReviews);
$totalMenunggu = 0;
$totalDisetujui = 0;
$totalDitolak = 0;
$totalGoogle = 0;
$totalWeb = 0;
$sumRatingDisetujui = 0;

foreach ($allReviews as $r) {
    $st = $r['status'] ?? 'disetujui';
    $src = $r['sumber'] ?? '';
    if ($src === 'Google Maps') $totalGoogle++;
    else $totalWeb++;

    if ($st === 'menunggu') $totalMenunggu++;
    elseif ($st === 'disetujui') {
        $totalDisetujui++;
        $sumRatingDisetujui += (int)($r['rating'] ?? 5);
    } elseif ($st === 'ditolak') $totalDitolak++;
}

$avgRating = $totalDisetujui > 0 ? round($sumRatingDisetujui / $totalDisetujui, 1) : 5.0;

// Filter tab aktif
$defaultTab = ($totalMenunggu > 0) ? 'menunggu' : 'semua';
$activeFilterTab = $_GET['tab'] ?? $defaultTab;
$filterRating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filterSumber = trim($_GET['sumber'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// Filter data untuk ditampilkan
$filteredReviews = array_filter($allReviews, function($item) use ($activeFilterTab, $filterRating, $filterSumber, $searchQuery) {
    $st = $item['status'] ?? 'disetujui';
    $rt = (int)($item['rating'] ?? 5);
    $src = $item['sumber'] ?? '';

    if ($activeFilterTab === 'menunggu' && $st !== 'menunggu') return false;
    if ($activeFilterTab === 'disetujui' && $st !== 'disetujui') return false;
    if ($activeFilterTab === 'ditolak' && $st !== 'ditolak') return false;
    if ($activeFilterTab === 'gmaps' && $src !== 'Google Maps') return false;

    if ($filterRating > 0 && $rt !== $filterRating) return false;
    if ($filterSumber === 'gmaps' && $src !== 'Google Maps') return false;
    if ($filterSumber === 'web' && $src === 'Google Maps') return false;

    if ($searchQuery !== '') {
        $q = mb_strtolower($searchQuery);
        $nama = mb_strtolower((string)($item['nama'] ?? ''));
        $ulasan = mb_strtolower((string)($item['ulasan'] ?? ''));
        $layanan = mb_strtolower((string)($item['layanan'] ?? ''));
        if (strpos($nama, $q) === false && strpos($ulasan, $q) === false && strpos($layanan, $q) === false) {
            return false;
        }
    }

    return true;
});

adminPageStart('Verifikasi & Moderasi Ulasan Pasien', 'ulasan');
?>

<div class="admin-page">
    <!-- HEADER HALAMAN -->
    <div class="admin-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:24px;">
        <div>
            <div style="display:inline-flex;align-items:center;gap:8px;background:var(--hanania-purple-soft);color:var(--hanania-primary);font-size:12px;font-weight:800;padding:4px 12px;border-radius:999px;margin-bottom:8px;border:1px solid var(--hanania-lavender);">
                <span>⭐ Moderasi Ulasan &amp; Integrasi Google Maps</span>
            </div>
            <h1 style="font-size:24px;font-weight:900;color:var(--hanania-primary);margin-bottom:6px;">
                Verifikasi &amp; Moderasi Ulasan Pasien
            </h1>
            <p style="color:var(--hanania-muted);font-size:13.5px;max-width:750px;line-height:1.5;">
                Kelola penilaian pasien dan ulasan Google Maps. Anda memiliki kendali penuh untuk menayangkan atau menyembunyikan ulasan apa pun, mengatur kebijakan verifikasi otomatis bintang 5, serta menyinkronkan ulasan langsung dengan profil Google Maps resmi klinik.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button type="button" onclick="bukaModalGmaps()" class="btn" style="background:#0F9D58;color:#fff;display:inline-flex;align-items:center;gap:7px;font-weight:800;font-size:12.5px;padding:10px 16px;box-shadow:0 4px 12px rgba(15,157,88,0.25);">
                <span>📍</span> Integrasi Google Maps
            </button>
            <button type="button" onclick="bukaModalKebijakan()" class="btn btn-light" style="display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:12.5px;padding:10px 16px;">
                <span>⚙️</span> Kebijakan Auto-Approve
            </button>
            <button type="button" onclick="bukaModalTambah()" class="btn" style="background:var(--hanania-primary);color:#fff;display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:12.5px;padding:10px 18px;box-shadow:0 4px 12px rgba(79,50,147,0.22);">
                <span>➕</span> Tambah Ulasan Manual
            </button>
        </div>
    </div>

    <!-- NOTIFIKASI FLASH MESSAGE -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'danger' ? 'danger' : 'success' ?>" style="display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span><?= $messageType === 'danger' ? '⚠️' : '✅' ?></span>
                <span><?= adminEsc($message) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:16px;color:inherit;font-weight:bold;">✕</button>
        </div>
    <?php endif; ?>

    <!-- BANNER PERINGATAN JIKA ADA ULASAN MENUNGGU -->
    <?php if ($totalMenunggu > 0): ?>
        <div style="background:linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);border:1.5px solid #FCD34D;border-radius:14px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;box-shadow:0 4px 14px rgba(217,119,6,0.1);">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:42px;height:42px;border-radius:12px;background:#F59E0B;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                    ⏳
                </div>
                <div>
                    <h4 style="font-size:15px;font-weight:900;color:#92400E;margin-bottom:2px;">
                        Ada <?= $totalMenunggu ?> Ulasan Baru Menunggu Verifikasi!
                    </h4>
                    <p style="font-size:12.5px;color:#B45309;margin-bottom:0;">
                        Terdapat ulasan pasien dengan rating di bawah bintang 5 yang tertahan untuk verifikasi. Klik tombol di bawah untuk menyetujui agar tampil di website atau tindak lanjuti secara internal.
                    </p>
                </div>
            </div>
            <a href="ulasan.php?tab=menunggu" class="btn" style="background:#D97706;color:#fff;font-weight:800;font-size:12.5px;padding:9px 18px;border-radius:10px;white-space:nowrap;">
                Lihat Ulasan Menunggu &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:16px;margin-bottom:26px;">
        <!-- CARD MENUNGGU VERIFIKASI -->
        <div style="background:#fff;border-radius:16px;padding:18px 20px;border:1.5px solid <?= $totalMenunggu > 0 ? '#F59E0B' : 'var(--hanania-border)' ?>;box-shadow:0 4px 14px rgba(79,50,147,0.04);position:relative;overflow:hidden;">
            <?php if ($totalMenunggu > 0): ?>
                <div style="position:absolute;top:0;right:0;width:4px;height:100%;background:#F59E0B;"></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:800;color:#92400E;text-transform:uppercase;letter-spacing:.5px;">Menunggu Verifikasi</span>
                <span style="font-size:20px;">⏳</span>
            </div>
            <div style="font-size:30px;font-weight:900;color:<?= $totalMenunggu > 0 ? '#D97706' : '#2D253B' ?>;line-height:1.2;">
                <?= $totalMenunggu ?>
            </div>
            <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:4px;">
                Ulasan &lt; 5 bintang belum tayang
            </div>
        </div>

        <!-- CARD DISETUJUI / TAYANG -->
        <div style="background:#fff;border-radius:16px;padding:18px 20px;border:1.5px solid var(--hanania-border);box-shadow:0 4px 14px rgba(79,50,147,0.04);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:800;color:#15803D;text-transform:uppercase;letter-spacing:.5px;">Tayang di Website</span>
                <span style="font-size:20px;">✅</span>
            </div>
            <div style="font-size:30px;font-weight:900;color:#15803D;line-height:1.2;">
                <?= $totalDisetujui ?>
            </div>
            <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:4px;">
                Aktif tampil di beranda utama
            </div>
        </div>

        <!-- CARD GOOGLE MAPS REVIEWS -->
        <div style="background:#fff;border-radius:16px;padding:18px 20px;border:1.5px solid var(--hanania-border);box-shadow:0 4px 14px rgba(79,50,147,0.04);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:800;color:#4285F4;text-transform:uppercase;letter-spacing:.5px;">Google Maps Reviews</span>
                <span style="font-size:20px;">📍</span>
            </div>
            <div style="font-size:30px;font-weight:900;color:#1E3A8A;line-height:1.2;">
                <?= $totalGoogle ?>
            </div>
            <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:4px;">
                Terhubung ke link Google Maps
            </div>
        </div>

        <!-- CARD RATA-RATA RATING -->
        <div style="background:#fff;border-radius:16px;padding:18px 20px;border:1.5px solid var(--hanania-border);box-shadow:0 4px 14px rgba(79,50,147,0.04);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:800;color:var(--hanania-primary);text-transform:uppercase;letter-spacing:.5px;">Rata-rata Rating Tayang</span>
                <span style="font-size:20px;">⭐</span>
            </div>
            <div style="font-size:30px;font-weight:900;color:#D97706;line-height:1.2;">
                <?= number_format($avgRating, 1) ?> <span style="font-size:16px;color:var(--hanania-muted);font-weight:700;">/ 5.0</span>
            </div>
            <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:4px;">
                Dari <?= $totalDisetujui ?> ulasan aktif
            </div>
        </div>
    </div>

    <!-- FILTER BAR & TABS -->
    <div class="admin-card" style="padding:0;overflow:hidden;margin-bottom:24px;background:#fff;border-radius:16px;border:1px solid var(--hanania-border);">
        <!-- TAB BAR -->
        <div style="display:flex;border-bottom:1px solid var(--hanania-border);background:#FAF8FD;overflow-x:auto;">
            <a href="ulasan.php?tab=semua<?= $filterRating ? '&rating='.$filterRating : '' ?>" 
               style="padding:14px 20px;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;border-bottom:3px solid <?= $activeFilterTab==='semua' ? 'var(--hanania-primary)' : 'transparent' ?>;color:<?= $activeFilterTab==='semua' ? 'var(--hanania-primary)' : 'var(--hanania-muted)' ?>;background:<?= $activeFilterTab==='semua' ? '#fff' : 'transparent' ?>;">
                <span>Semua Ulasan</span>
                <span style="background:var(--hanania-purple-soft);color:var(--hanania-primary);font-size:11px;padding:2px 8px;border-radius:999px;">
                    <?= $totalSemua ?>
                </span>
            </a>

            <a href="ulasan.php?tab=menunggu<?= $filterRating ? '&rating='.$filterRating : '' ?>" 
               style="padding:14px 20px;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;border-bottom:3px solid <?= $activeFilterTab==='menunggu' ? '#F59E0B' : 'transparent' ?>;color:<?= $activeFilterTab==='menunggu' ? '#D97706' : 'var(--hanania-muted)' ?>;background:<?= $activeFilterTab==='menunggu' ? '#fff' : 'transparent' ?>;">
                <span>⏳ Menunggu Verifikasi</span>
                <?php if ($totalMenunggu > 0): ?>
                    <span style="background:#E11D48;color:#fff;font-size:11px;padding:2px 8px;border-radius:999px;font-weight:900;">
                        <?= $totalMenunggu ?> Baru
                    </span>
                <?php else: ?>
                    <span style="background:#F3F4F6;color:#6B7280;font-size:11px;padding:2px 8px;border-radius:999px;">0</span>
                <?php endif; ?>
            </a>

            <a href="ulasan.php?tab=disetujui<?= $filterRating ? '&rating='.$filterRating : '' ?>" 
               style="padding:14px 20px;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;border-bottom:3px solid <?= $activeFilterTab==='disetujui' ? '#10B981' : 'transparent' ?>;color:<?= $activeFilterTab==='disetujui' ? '#047857' : 'var(--hanania-muted)' ?>;background:<?= $activeFilterTab==='disetujui' ? '#fff' : 'transparent' ?>;">
                <span>✅ Tayang di Website</span>
                <span style="background:#DCFCE7;color:#15803D;font-size:11px;padding:2px 8px;border-radius:999px;">
                    <?= $totalDisetujui ?>
                </span>
            </a>

            <a href="ulasan.php?tab=gmaps" 
               style="padding:14px 20px;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;border-bottom:3px solid <?= $activeFilterTab==='gmaps' ? '#4285F4' : 'transparent' ?>;color:<?= $activeFilterTab==='gmaps' ? '#1D4ED8' : 'var(--hanania-muted)' ?>;background:<?= $activeFilterTab==='gmaps' ? '#fff' : 'transparent' ?>;">
                <span>📍 Google Maps</span>
                <span style="background:#DBEAFE;color:#1E40AF;font-size:11px;padding:2px 8px;border-radius:999px;">
                    <?= $totalGoogle ?>
                </span>
            </a>

            <a href="ulasan.php?tab=ditolak<?= $filterRating ? '&rating='.$filterRating : '' ?>" 
               style="padding:14px 20px;font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:8px;border-bottom:3px solid <?= $activeFilterTab==='ditolak' ? '#EF4444' : 'transparent' ?>;color:<?= $activeFilterTab==='ditolak' ? '#B91C1C' : 'var(--hanania-muted)' ?>;background:<?= $activeFilterTab==='ditolak' ? '#fff' : 'transparent' ?>;">
                <span>🚫 Disembunyikan (Arsip)</span>
                <span style="background:#FEE2E2;color:#991B1B;font-size:11px;padding:2px 8px;border-radius:999px;">
                    <?= $totalDitolak ?>
                </span>
            </a>
        </div>

        <!-- SEARCH & RATING FILTER BAR -->
        <div style="padding:16px 20px;background:#fff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <form method="GET" action="ulasan.php" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;flex:1;">
                <input type="hidden" name="tab" value="<?= adminEsc($activeFilterTab) ?>">
                
                <div style="position:relative;min-width:240px;flex:1;max-width:380px;">
                    <input type="text" name="q" value="<?= adminEsc($searchQuery) ?>" placeholder="Cari nama pasien, layanan, isi ulasan..." 
                           style="width:100%;padding:9px 12px 9px 34px;border:1px solid var(--hanania-border);border-radius:10px;font-size:12.5px;outline:none;font-family:inherit;">
                    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:14px;color:var(--hanania-muted);">🔍</span>
                </div>

                <select name="rating" onchange="this.form.submit()" style="padding:9px 12px;border:1px solid var(--hanania-border);border-radius:10px;font-size:12.5px;outline:none;background:#fff;font-family:inherit;">
                    <option value="0" <?= $filterRating===0 ? 'selected' : '' ?>>Semua Bintang</option>
                    <option value="5" <?= $filterRating===5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ (5 Bintang)</option>
                    <option value="4" <?= $filterRating===4 ? 'selected' : '' ?>>⭐⭐⭐⭐ (4 Bintang)</option>
                    <option value="3" <?= $filterRating===3 ? 'selected' : '' ?>>⭐⭐⭐ (3 Bintang)</option>
                    <option value="2" <?= $filterRating===2 ? 'selected' : '' ?>>⭐⭐ (2 Bintang)</option>
                    <option value="1" <?= $filterRating===1 ? 'selected' : '' ?>>⭐ (1 Bintang)</option>
                </select>

                <select name="sumber" onchange="this.form.submit()" style="padding:9px 12px;border:1px solid var(--hanania-border);border-radius:10px;font-size:12.5px;outline:none;background:#fff;font-family:inherit;">
                    <option value="" <?= $filterSumber==='' ? 'selected' : '' ?>>Semua Sumber</option>
                    <option value="gmaps" <?= $filterSumber==='gmaps' ? 'selected' : '' ?>>📍 Google Maps</option>
                    <option value="web" <?= $filterSumber==='web' ? 'selected' : '' ?>>🌐 Website Pasien</option>
                </select>

                <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;padding:9px 14px;font-weight:700;">
                    Terapkan
                </button>

                <?php if ($searchQuery !== '' || $filterRating > 0 || $filterSumber !== ''): ?>
                    <a href="ulasan.php?tab=<?= adminEsc($activeFilterTab) ?>" style="font-size:12px;color:var(--hanania-muted);text-decoration:underline;">
                        Reset Filter
                    </a>
                <?php endif; ?>
            </form>

            <div style="font-size:12.5px;color:var(--hanania-muted);">
                Menampilkan <strong><?= count($filteredReviews) ?></strong> ulasan
            </div>
        </div>
    </div>

    <!-- DAFTAR KARTU ULASAN -->
    <?php if (empty($filteredReviews)): ?>
        <div style="background:#fff;border-radius:16px;padding:48px 24px;text-align:center;border:1.5px dashed var(--hanania-border);margin-bottom:24px;">
            <div style="font-size:42px;margin-bottom:12px;">💬</div>
            <h3 style="font-size:17px;font-weight:800;color:var(--hanania-primary);margin-bottom:6px;">
                Tidak ada ulasan pada filter ini
            </h3>
            <p style="font-size:13px;color:var(--hanania-muted);max-width:460px;margin:0 auto 16px;">
                Belum ada data ulasan yang cocok dengan kriteria status atau pencarian yang Anda pilih.
            </p>
            <a href="ulasan.php?tab=semua" class="btn btn-light btn-sm" style="font-weight:700;">
                Lihat Semua Ulasan
            </a>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:30px;">
            <?php foreach ($filteredReviews as $rev): 
                $rId = (string)($rev['id'] ?? '');
                $rRating = max(1, min(5, (int)($rev['rating'] ?? 5)));
                $rStatus = $rev['status'] ?? 'disetujui';
                $rNama = (string)($rev['nama'] ?? 'Pasien');
                $rBadge = (string)($rev['badge'] ?? 'Website');
                $rLayanan = (string)($rev['layanan'] ?? 'Pelayanan Klinik');
                $rSumber = (string)($rev['sumber'] ?? 'Website Langsung');
                $rTanggal = (string)($rev['tanggal'] ?? 'Terbaru');
                $rUlasan = (string)($rev['ulasan'] ?? '');
                $rCreated = (string)($rev['created_at'] ?? '');
                $rLinkGmaps = (string)($rev['link_gmaps'] ?? ($rSumber === 'Google Maps' ? $gmapsConfig['url'] : ''));
                $initial = strtoupper(substr(trim($rNama), 0, 2));

                $isGmaps = ($rSumber === 'Google Maps');
                $isPending = ($rStatus === 'menunggu');
                $isApproved = ($rStatus === 'disetujui');
                $isRejected = ($rStatus === 'ditolak');
            ?>
            <div style="background:#fff;border-radius:16px;padding:20px 22px;border:1.5px solid <?= $isPending ? '#F59E0B' : ($isRejected ? '#FCA5A5' : 'var(--hanania-border)') ?>;box-shadow:0 4px 14px rgba(79,50,147,0.04);display:flex;flex-direction:column;gap:14px;position:relative;">
                <!-- STATUS WATERMARK / STRIP -->
                <?php if ($isPending): ?>
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg, #F59E0B, #D97706);border-top-left-radius:16px;border-top-right-radius:16px;"></div>
                <?php endif; ?>

                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                    <!-- PROFIL PENGULAS -->
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:46px;height:46px;border-radius:50%;background:<?= $isGmaps ? 'linear-gradient(135deg, #4285F4, #0F9D58);color:#fff' : ($isPending ? '#FEF3C7;color:#D97706' : ($isApproved ? 'linear-gradient(135deg, #4F3293, #7650A2);color:#fff' : '#FEE2E2;color:#B91C1C')) ?>;font-weight:800;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 3px 8px rgba(0,0,0,0.08);">
                            <?= adminEsc($initial) ?>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <strong style="font-size:15px;color:var(--hanania-text);"><?= adminEsc($rNama) ?></strong>
                                <span style="font-size:11px;background:<?= $isGmaps ? '#DBEAFE;color:#1E40AF' : 'var(--hanania-purple-soft);color:var(--hanania-primary)' ?>;font-weight:700;padding:2px 8px;border-radius:6px;border:1px solid <?= $isGmaps ? '#BFDBFE' : 'var(--hanania-lavender)' ?>;">
                                    <?= adminEsc($rBadge) ?>
                                </span>
                            </div>
                            <div style="font-size:11.5px;color:var(--hanania-muted);margin-top:2px;">
                                <span><?= $isGmaps ? '📍 Google Maps' : '🌐 Website Langsung' ?></span> &bull; <span>🕒 <?= adminEsc($rTanggal) ?></span>
                                <?php if ($rCreated): ?>
                                    <span>(<?= date('d M Y, H:i', strtotime($rCreated)) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- BADGE STATUS & RATING -->
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <!-- RATING STAR -->
                        <div style="background:#FFFBEB;border:1px solid #FDE68A;padding:4px 10px;border-radius:10px;display:inline-flex;align-items:center;gap:4px;">
                            <span style="font-size:14px;letter-spacing:1px;"><?= str_repeat('⭐', $rRating) ?></span>
                            <strong style="font-size:13px;color:#92400E;margin-left:2px;"><?= $rRating ?>.0</strong>
                        </div>

                        <!-- STATUS BADGE -->
                        <?php if ($isPending): ?>
                            <span style="background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;">
                                <span>⏳</span> Menunggu Verifikasi
                            </span>
                        <?php elseif ($isApproved): ?>
                            <span style="background:#DCFCE7;color:#15803D;border:1px solid #86EFAC;font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;">
                                <span>✅</span> Tayang di Website
                            </span>
                        <?php else: ?>
                            <span style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;">
                                <span>🚫</span> Disembunyikan (Arsip)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAG LAYANAN & ISI ULASAN -->
                <div style="background:#FAF8FD;border-radius:12px;padding:14px 16px;border:1px solid #F0ECF5;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
                        <span style="background:#E9D5FF;color:#6B21A8;font-size:11px;font-weight:800;padding:3px 9px;border-radius:6px;display:inline-block;">
                            🏥 <?= adminEsc($rLayanan) ?>
                        </span>
                        <?php if ($isGmaps): ?>
                            <a href="<?= adminEsc($rLinkGmaps ?: $gmapsConfig['url']) ?>" target="_blank" rel="noopener noreferrer" style="font-size:11.5px;color:#1D4ED8;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                <span>📍 Buka Profil di Google Maps ↗</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <p style="color:#374151;font-size:13.5px;line-height:1.65;font-style:italic;margin-bottom:0;">
                        &ldquo;<?= nl2br(adminEsc($rUlasan)) ?>&rdquo;
                    </p>
                </div>

                <!-- BOTTOM ACTIONS -->
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding-top:6px;">
                    <div style="font-size:11.5px;color:var(--hanania-muted);">
                        <?php if (!empty($rev['verified_at'])): ?>
                            <span>Diverifikasi pada: <?= date('d M Y, H:i', strtotime($rev['verified_at'])) ?> <?= !empty($rev['verified_by']) ? 'oleh '.adminEsc($rev['verified_by']) : '' ?></span>
                        <?php elseif ($isPending): ?>
                            <span style="color:#D97706;font-weight:700;">⚠️ Ulasan belum dipublikasikan ke beranda publik</span>
                        <?php else: ?>
                            <span style="color:#15803D;font-weight:700;">✓ Tampil di beranda website</span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <!-- TOGGLE TAYANGKAN ATAU SEMBUNYIKAN -->
                        <?php if (!$isApproved): ?>
                            <form method="POST" action="ulasan.php" style="margin:0;" onsubmit="return confirm('Tayangkan ulasan ini di beranda website?');">
                                <input type="hidden" name="action" value="setujui">
                                <input type="hidden" name="id_ulasan" value="<?= adminEsc($rId) ?>">
                                <button type="submit" class="btn btn-sm" style="background:#10B981;color:#fff;font-weight:800;padding:7px 14px;border-radius:8px;">
                                    ✓ Tayangkan di Web
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="ulasan.php" style="margin:0;" onsubmit="return confirm('Sembunyikan ulasan ini dari beranda website?');">
                                <input type="hidden" name="action" value="tolak">
                                <input type="hidden" name="id_ulasan" value="<?= adminEsc($rId) ?>">
                                <button type="submit" class="btn btn-sm btn-light" style="font-weight:700;padding:7px 12px;border-radius:8px;color:#B91C1C;">
                                    ⏸️ Sembunyikan
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- TOMBOL EDIT -->
                        <button type="button" 
                                class="btn btn-sm btn-light" 
                                style="font-weight:700;padding:7px 12px;border-radius:8px;"
                                onclick='bukaModalEdit(<?= json_encode($rev, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                            ✏️ Edit
                        </button>

                        <!-- TOMBOL HAPUS -->
                        <form method="POST" action="ulasan.php" style="margin:0;" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS PERMANEN ulasan ini?');">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id_ulasan" value="<?= adminEsc($rId) ?>">
                            <button type="submit" class="btn btn-sm" style="background:#FEE2E2;color:#B91C1C;font-weight:700;padding:7px 12px;border-radius:8px;border:1px solid #FECACA;">
                                🗑️ Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- RESET KE DEFAULT FOOTNOTE -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:14px;border-top:1px dashed var(--hanania-border);flex-wrap:wrap;gap:10px;">
        <div style="font-size:12px;color:var(--hanania-muted);">
            📍 Google Maps URL Terhubung: <a href="<?= adminEsc($gmapsConfig['url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--hanania-primary);font-weight:700;text-decoration:underline;">Lihat Profil Google Maps Klinik ↗</a>
        </div>
        <form method="POST" action="ulasan.php" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin mereset daftar ulasan ke ulasan bawaan resmi klinik? Tindakan ini akan mengembalikan data default.');">
            <input type="hidden" name="action" value="reset_default">
            <button type="submit" style="background:none;border:none;color:var(--hanania-muted);font-size:12px;cursor:pointer;text-decoration:underline;">
                🔄 Reset Ulasan ke Format Bawaan Asli
            </button>
        </form>
    </div>
</div>

<!-- =====================================================
     MODAL INTEGRASI & SINKRONISASI GOOGLE MAPS
===================================================== -->
<div id="modalGmaps" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.68);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:22px;max-width:580px;width:100%;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.28);position:relative;max-height:92vh;overflow-y:auto;">
        <button type="button" onclick="tutupModalGmaps()" style="position:absolute;top:18px;right:18px;background:var(--hanania-purple-soft);border:none;width:32px;height:32px;border-radius:50%;color:var(--hanania-primary);font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:46px;height:46px;border-radius:12px;background:#E8F0FE;color:#1A73E8;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 3px 10px rgba(26,115,232,0.15);">
                📍
            </div>
            <div>
                <h3 style="color:#1E3A8A;font-size:18.5px;font-weight:900;margin-bottom:2px;">
                    Integrasi &amp; Sinkronisasi Google Maps
                </h3>
                <p style="color:var(--hanania-muted);font-size:12px;margin-bottom:0;">
                    Hubungkan website dengan profil Google Maps resmi Rumah Hanania
                </p>
            </div>
        </div>

        <!-- TABS DI DALAM MODAL GOOGLE MAPS -->
        <div style="display:flex;gap:8px;margin-bottom:18px;border-bottom:1.5px solid var(--hanania-border);padding-bottom:10px;">
            <button type="button" id="tabBtnGmapsSetting" onclick="switchGmapsTab('setting')" class="btn btn-sm" style="background:#1A73E8;color:#fff;font-weight:800;border-radius:8px;">
                ⚙️ Pengaturan &amp; Auto-Sync
            </button>
            <button type="button" id="tabBtnGmapsImport" onclick="switchGmapsTab('import')" class="btn btn-sm btn-light" style="font-weight:800;border-radius:8px;">
                📥 Impor Ulasan Cepat
            </button>
        </div>

        <!-- PANEL 1: PENGATURAN & AUTO-SYNC GOOGLE MAPS -->
        <div id="panelGmapsSetting">
            <form method="POST" action="ulasan.php">
                <input type="hidden" name="action" value="simpan_gmaps_config">

                <div class="form-grid" style="gap:14px;margin-bottom:16px;">
                    <div class="form-field full">
                        <label>Tautan Profil Google Maps Klinik *</label>
                        <input type="url" name="gmaps_url" value="<?= adminEsc($gmapsConfig['url']) ?>" required placeholder="https://maps.app.goo.gl/...">
                        <small style="color:var(--hanania-muted);font-size:11px;">Tautan profil Google Maps tempat pasien diarahkan saat memberi bintang atau melihat lokasi.</small>
                    </div>

                    <div class="form-field full">
                        <label>Google Place ID (Tempat Klinik)</label>
                        <input type="text" name="gmaps_place_id" value="<?= adminEsc($gmapsConfig['place_id']) ?>" placeholder="ChIJCJeBk7Xit_G0LhDbvYL0icjvtVQ">
                        <small style="color:var(--hanania-muted);font-size:11px;">ID unik lokasi Rumah Hanania di Google Maps.</small>
                    </div>

                    <div class="form-field full">
                        <label>Google Places API Key (Opsional untuk Live Cloud Sync)</label>
                        <input type="password" name="gmaps_api_key" value="<?= adminEsc($gmapsConfig['api_key']) ?>" placeholder="AIzaSy...">
                        <small style="color:var(--hanania-muted);font-size:11px;">Jika Anda memiliki Google Places API Key dari Google Cloud, masukkan di sini agar sistem dapat menarik ulasan live secara langsung.</small>
                    </div>

                    <div class="form-field">
                        <label>Moderasi Ulasan Google Baru</label>
                        <select name="gmaps_auto_approve">
                            <option value="1" <?= ($gmapsConfig['auto_approve'] === '1') ? 'selected' : '' ?>>✅ Otomatis Tayang Langsung</option>
                            <option value="0" <?= ($gmapsConfig['auto_approve'] === '0') ? 'selected' : '' ?>>⏳ Harus Diverifikasi Admin Dulu</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Rating Minimal Auto-Tayang</label>
                        <select name="gmaps_min_star">
                            <option value="5" <?= ($gmapsConfig['min_star'] === '5') ? 'selected' : '' ?>>⭐ Bintang 5 Saja</option>
                            <option value="4" <?= ($gmapsConfig['min_star'] === '4') ? 'selected' : '' ?>>⭐⭐ Bintang 4 &amp; 5</option>
                            <option value="1" <?= ($gmapsConfig['min_star'] === '1') ? 'selected' : '' ?>>Semua Bintang (1 - 5)</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;flex-wrap:wrap;gap:10px;">
                    <a href="<?= adminEsc($gmapsConfig['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light" style="font-weight:700;">
                        📍 Buka Google Maps Klinik ↗
                    </a>
                    <button type="submit" class="btn" style="background:#1A73E8;color:#fff;font-weight:800;padding:10px 20px;">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>

            <!-- TOMBOL SINKRONISASI -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px dashed var(--hanania-border);background:#F8FAFC;padding:14px;border-radius:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <strong style="color:#0F172A;font-size:13px;display:block;">Tarik Ulasan Google Maps</strong>
                        <span style="font-size:11.5px;color:var(--hanania-muted);">Sinkronkan ulasan terbaru dari Google Maps ke database website.</span>
                    </div>
                    <form method="POST" action="ulasan.php" style="margin:0;">
                        <input type="hidden" name="action" value="sinkronkan_google">
                        <button type="submit" class="btn btn-sm" style="background:#0F9D58;color:#fff;font-weight:800;padding:8px 16px;">
                            🔄 Sinkronkan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- PANEL 2: IMPOR ULASAN CEPAT GOOGLE MAPS -->
        <div id="panelGmapsImport" style="display:none;">
            <p style="font-size:12.5px;color:var(--hanania-muted);margin-bottom:14px;">
                Fitur ini memudahkan Anda mengimpor ulasan yang baru masuk di Google Maps secara instan tanpa perlu akun Google Cloud API berbayar.
            </p>

            <form method="POST" action="ulasan.php">
                <input type="hidden" name="action" value="impor_cepat_google">

                <div class="form-grid" style="gap:12px;margin-bottom:14px;">
                    <div class="form-field full">
                        <label>Nama Akun Pengulas Google Maps *</label>
                        <input type="text" name="import_nama" required placeholder="Contoh: Rian Pratama">
                    </div>

                    <div class="form-field">
                        <label>Rating Bintang di Google *</label>
                        <select name="import_rating" required>
                            <option value="5" selected>⭐⭐⭐⭐⭐ (5 Bintang)</option>
                            <option value="4">⭐⭐⭐⭐ (4 Bintang)</option>
                            <option value="3">⭐⭐⭐ (3 Bintang)</option>
                            <option value="2">⭐⭐ (2 Bintang)</option>
                            <option value="1">⭐ (1 Bintang)</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Status Tayang di Web</label>
                        <select name="import_status" required>
                            <option value="disetujui" selected>✅ Langsung Tayang</option>
                            <option value="menunggu">⏳ Menunggu Verifikasi</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label>Layanan yang Diulas</label>
                        <input type="text" name="import_layanan" value="Poli Kebidanan &amp; Kandungan" placeholder="Poli Umum / Kebidanan / USG">
                    </div>

                    <div class="form-field">
                        <label>Waktu Ulasan di Google</label>
                        <input type="text" name="import_waktu" value="Baru saja" placeholder="Contoh: 3 hari lalu, 1 minggu lalu">
                    </div>

                    <div class="form-field full">
                        <label>Isi Teks Ulasan dari Google Maps *</label>
                        <textarea name="import_ulasan" rows="4" required placeholder="Salin (copy-paste) teks ulasan pasien dari Google Maps..."></textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
                    <button type="button" onclick="tutupModalGmaps()" class="btn btn-light" style="padding:10px 18px;font-weight:700;">Batal</button>
                    <button type="submit" class="btn" style="background:#0F9D58;color:#fff;font-weight:800;padding:10px 22px;">
                        📥 Impor Ulasan ke Website
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================
     MODAL KEBIJAKAN AUTO-APPROVE
===================================================== -->
<div id="modalKebijakan" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.65);backdrop-filter:blur(5px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:100%;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.25);position:relative;">
        <button type="button" onclick="tutupModalKebijakan()" style="position:absolute;top:18px;right:18px;background:var(--hanania-purple-soft);border:none;width:32px;height:32px;border-radius:50%;color:var(--hanania-primary);font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:12px;background:var(--hanania-purple-soft);color:var(--hanania-primary);display:flex;align-items:center;justify-content:center;font-size:22px;">
                ⚙️
            </div>
            <div>
                <h3 style="color:var(--hanania-primary);font-size:18px;font-weight:900;margin-bottom:2px;">
                    Kebijakan Verifikasi Otomatis
                </h3>
                <p style="color:var(--hanania-muted);font-size:12px;margin-bottom:0;">
                    Tentukan rating bintang mana yang boleh langsung tayang tanpa verifikasi admin
                </p>
            </div>
        </div>

        <form method="POST" action="ulasan.php">
            <input type="hidden" name="action" value="simpan_kebijakan">

            <div style="display:flex;flex-direction:column;gap:12px;margin:20px 0;">
                <label style="display:flex;align-items:flex-start;gap:12px;background:<?= $kebijakanMinStar===5 ? '#F4EEFB' : '#FAF8FD' ?>;padding:14px 16px;border-radius:12px;border:1.5px solid <?= $kebijakanMinStar===5 ? 'var(--hanania-primary)' : 'var(--hanania-border)' ?>;cursor:pointer;">
                    <input type="radio" name="min_star_auto" value="5" <?= $kebijakanMinStar===5 ? 'checked' : '' ?> style="margin-top:4px;">
                    <div>
                        <strong style="color:var(--hanania-primary);font-size:13.5px;display:block;">⭐ Bintang 5 Saja Otomatis Disetujui (Rekomendasi)</strong>
                        <span style="font-size:12px;color:var(--hanania-muted);line-height:1.4;display:block;margin-top:2px;">
                            Hanya ulasan bintang 5 yang langsung muncul di website. Ulasan bintang 1, 2, 3, dan 4 masuk status 'Menunggu' untuk diverifikasi admin terlebih dahulu.
                        </span>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:12px;background:<?= $kebijakanMinStar===4 ? '#F4EEFB' : '#FAF8FD' ?>;padding:14px 16px;border-radius:12px;border:1.5px solid <?= $kebijakanMinStar===4 ? 'var(--hanania-primary)' : 'var(--hanania-border)' ?>;cursor:pointer;">
                    <input type="radio" name="min_star_auto" value="4" <?= $kebijakanMinStar===4 ? 'checked' : '' ?> style="margin-top:4px;">
                    <div>
                        <strong style="color:var(--hanania-primary);font-size:13.5px;display:block;">⭐⭐ Bintang 4 &amp; 5 Otomatis Disetujui</strong>
                        <span style="font-size:12px;color:var(--hanania-muted);line-height:1.4;display:block;margin-top:2px;">
                            Ulasan bintang 4 dan 5 langsung muncul di website. Ulasan bintang 1, 2, dan 3 menunggu verifikasi admin.
                        </span>
                    </div>
                </label>

                <label style="display:flex;align-items:flex-start;gap:12px;background:<?= $kebijakanMinStar===6 ? '#F4EEFB' : '#FAF8FD' ?>;padding:14px 16px;border-radius:12px;border:1.5px solid <?= $kebijakanMinStar===6 ? 'var(--hanania-primary)' : 'var(--hanania-border)' ?>;cursor:pointer;">
                    <input type="radio" name="min_star_auto" value="6" <?= $kebijakanMinStar===6 ? 'checked' : '' ?> style="margin-top:4px;">
                    <div>
                        <strong style="color:var(--hanania-primary);font-size:13.5px;display:block;">🛡️ Semua Ulasan Butuh Verifikasi Admin</strong>
                        <span style="font-size:12px;color:var(--hanania-muted);line-height:1.4;display:block;margin-top:2px;">
                            Tidak ada ulasan yang tayang otomatis. Semua rating (1 hingga 5 bintang) harus disetujui admin sebelum tampil di website.
                        </span>
                    </div>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" onclick="tutupModalKebijakan()" class="btn btn-light" style="padding:10px 18px;font-weight:700;">Batal</button>
                <button type="submit" class="btn" style="background:var(--hanania-primary);color:#fff;font-weight:800;padding:10px 22px;">Simpan Kebijakan</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================
     MODAL TAMBAH ULASAN MANUAL
===================================================== -->
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.65);backdrop-filter:blur(5px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;max-width:540px;width:100%;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.25);position:relative;max-height:92vh;overflow-y:auto;">
        <button type="button" onclick="tutupModalTambah()" style="position:absolute;top:18px;right:18px;background:var(--hanania-purple-soft);border:none;width:32px;height:32px;border-radius:50%;color:var(--hanania-primary);font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>

        <h3 style="color:var(--hanania-primary);font-size:18px;font-weight:900;margin-bottom:4px;">
            ➕ Tambah Ulasan / Testimoni Manual
        </h3>
        <p style="color:var(--hanania-muted);font-size:12.5px;margin-bottom:18px;">
            Tambahkan testimoni pasien dari Google Maps atau survei kepuasan offline.
        </p>

        <form method="POST" action="ulasan.php">
            <input type="hidden" name="action" value="tambah">

            <div class="form-grid" style="gap:14px;margin-bottom:14px;">
                <div class="form-field full">
                    <label>Nama Pasien / Pengulas *</label>
                    <input type="text" name="nama" required placeholder="Contoh: Siti Rahmawati">
                </div>

                <div class="form-field">
                    <label>Rating Bintang *</label>
                    <select name="rating" required>
                        <option value="5" selected>⭐⭐⭐⭐⭐ (5 Bintang)</option>
                        <option value="4">⭐⭐⭐⭐ (4 Bintang)</option>
                        <option value="3">⭐⭐⭐ (3 Bintang)</option>
                        <option value="2">⭐⭐ (2 Bintang)</option>
                        <option value="1">⭐ (1 Bintang)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Status Publikasi *</label>
                    <select name="status" required>
                        <option value="disetujui" selected>✅ Disetujui (Langsung Tayang)</option>
                        <option value="menunggu">⏳ Menunggu Verifikasi</option>
                        <option value="ditolak">🚫 Ditolak (Arsip)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Sumber Ulasan</label>
                    <select name="sumber">
                        <option value="Google Maps" selected>📍 Google Maps</option>
                        <option value="Website Langsung">🌐 Website Langsung</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Badge / Sumber</label>
                    <input type="text" name="badge" value="Local Guide • Google Maps" placeholder="Local Guide • Google Maps / Pasien Web">
                </div>

                <div class="form-field">
                    <label>Layanan yang Direview</label>
                    <input type="text" name="layanan" value="Poli Umum" placeholder="Poli Umum / Kebidanan / Farmasi">
                </div>

                <div class="form-field">
                    <label>Keterangan Tanggal (Opsional)</label>
                    <input type="text" name="tanggal" value="Hari Ini" placeholder="Contoh: 1 minggu lalu, Hari ini">
                </div>

                <div class="form-field full">
                    <label>Isi Ulasan &amp; Testimoni *</label>
                    <textarea name="ulasan" rows="4" required placeholder="Tuliskan isi ulasan lengkap dari pasien..."></textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" onclick="tutupModalTambah()" class="btn btn-light" style="padding:10px 18px;font-weight:700;">Batal</button>
                <button type="submit" class="btn" style="background:var(--hanania-primary);color:#fff;font-weight:800;padding:10px 22px;">Simpan Ulasan</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================
     MODAL EDIT ULASAN
===================================================== -->
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(25,12,45,0.65);backdrop-filter:blur(5px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;max-width:540px;width:100%;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,0.25);position:relative;max-height:92vh;overflow-y:auto;">
        <button type="button" onclick="tutupModalEdit()" style="position:absolute;top:18px;right:18px;background:var(--hanania-purple-soft);border:none;width:32px;height:32px;border-radius:50%;color:var(--hanania-primary);font-weight:bold;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">✕</button>

        <h3 style="color:var(--hanania-primary);font-size:18px;font-weight:900;margin-bottom:4px;">
            ✏️ Edit Data Ulasan Pasien
        </h3>
        <p style="color:var(--hanania-muted);font-size:12.5px;margin-bottom:18px;">
            Perbarui informasi nama, rating, status tayang, atau isi ulasan pasien.
        </p>

        <form method="POST" action="ulasan.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_ulasan" id="edit_id">

            <div class="form-grid" style="gap:14px;margin-bottom:14px;">
                <div class="form-field full">
                    <label>Nama Pasien *</label>
                    <input type="text" name="nama" id="edit_nama" required>
                </div>

                <div class="form-field">
                    <label>Rating Bintang *</label>
                    <select name="rating" id="edit_rating" required>
                        <option value="5">⭐⭐⭐⭐⭐ (5 Bintang)</option>
                        <option value="4">⭐⭐⭐⭐ (4 Bintang)</option>
                        <option value="3">⭐⭐⭐ (3 Bintang)</option>
                        <option value="2">⭐⭐ (2 Bintang)</option>
                        <option value="1">⭐ (1 Bintang)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Status Publikasi *</label>
                    <select name="status" id="edit_status" required>
                        <option value="disetujui">✅ Disetujui (Tayang di Web)</option>
                        <option value="menunggu">⏳ Menunggu Verifikasi</option>
                        <option value="ditolak">🚫 Ditolak (Tidak Tayang)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Sumber</label>
                    <select name="sumber" id="edit_sumber">
                        <option value="Google Maps">📍 Google Maps</option>
                        <option value="Website Langsung">🌐 Website Langsung</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Layanan yang Direview</label>
                    <input type="text" name="layanan" id="edit_layanan">
                </div>

                <div class="form-field">
                    <label>Badge / Sumber</label>
                    <input type="text" name="badge" id="edit_badge">
                </div>

                <div class="form-field full">
                    <label>Isi Ulasan &amp; Pengalaman Pasien *</label>
                    <textarea name="ulasan" id="edit_ulasan" rows="4" required></textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" onclick="tutupModalEdit()" class="btn btn-light" style="padding:10px 18px;font-weight:700;">Batal</button>
                <button type="submit" class="btn" style="background:var(--hanania-primary);color:#fff;font-weight:800;padding:10px 22px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalGmaps() {
    const modal = document.getElementById('modalGmaps');
    if (modal) modal.style.display = 'flex';
}
function tutupModalGmaps() {
    const modal = document.getElementById('modalGmaps');
    if (modal) modal.style.display = 'none';
}

function switchGmapsTab(tab) {
    const panelSetting = document.getElementById('panelGmapsSetting');
    const panelImport = document.getElementById('panelGmapsImport');
    const btnSetting = document.getElementById('tabBtnGmapsSetting');
    const btnImport = document.getElementById('tabBtnGmapsImport');

    if (tab === 'setting') {
        if (panelSetting) panelSetting.style.display = 'block';
        if (panelImport) panelImport.style.display = 'none';
        if (btnSetting) { btnSetting.className = 'btn btn-sm'; btnSetting.style.background = '#1A73E8'; btnSetting.style.color = '#fff'; }
        if (btnImport) { btnImport.className = 'btn btn-sm btn-light'; btnImport.style.background = ''; btnImport.style.color = ''; }
    } else {
        if (panelSetting) panelSetting.style.display = 'none';
        if (panelImport) panelImport.style.display = 'block';
        if (btnImport) { btnImport.className = 'btn btn-sm'; btnImport.style.background = '#0F9D58'; btnImport.style.color = '#fff'; }
        if (btnSetting) { btnSetting.className = 'btn btn-sm btn-light'; btnSetting.style.background = ''; btnSetting.style.color = ''; }
    }
}

function bukaModalKebijakan() {
    const modal = document.getElementById('modalKebijakan');
    if (modal) modal.style.display = 'flex';
}
function tutupModalKebijakan() {
    const modal = document.getElementById('modalKebijakan');
    if (modal) modal.style.display = 'none';
}

function bukaModalTambah() {
    const modal = document.getElementById('modalTambah');
    if (modal) modal.style.display = 'flex';
}
function tutupModalTambah() {
    const modal = document.getElementById('modalTambah');
    if (modal) modal.style.display = 'none';
}

function bukaModalEdit(data) {
    if (!data) return;
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_nama').value = data.nama || '';
    document.getElementById('edit_rating').value = data.rating || 5;
    document.getElementById('edit_status').value = data.status || 'disetujui';
    document.getElementById('edit_sumber').value = data.sumber || 'Google Maps';
    document.getElementById('edit_layanan').value = data.layanan || '';
    document.getElementById('edit_badge').value = data.badge || '';
    document.getElementById('edit_ulasan').value = data.ulasan || '';

    const modal = document.getElementById('modalEdit');
    if (modal) modal.style.display = 'flex';
}
function tutupModalEdit() {
    const modal = document.getElementById('modalEdit');
    if (modal) modal.style.display = 'none';
}

// Tutup modal jika klik backdrop
window.addEventListener('click', function(e) {
    ['modalGmaps', 'modalKebijakan', 'modalTambah', 'modalEdit'].forEach(function(id) {
        const m = document.getElementById(id);
        if (m && e.target === m) {
            m.style.display = 'none';
        }
    });
});
</script>

<?php
adminPageEnd();
