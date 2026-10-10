<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';
$activeSection = $_GET['section'] ?? 'beranda';
if ($activeSection === 'tenaga') {
    header('Location: dokter.php');
    exit;
}
if ($activeSection === 'profil') {
    header('Location: konten.php?section=tentang&tab=profil');
    exit;
}
if ($activeSection === 'fasilitas') {
    header('Location: konten.php?section=tentang&tab=fasilitas');
    exit;
}
if ($activeSection === 'struktur') {
    header('Location: konten.php?section=tentang&tab=struktur');
    exit;
}
if ($activeSection === 'layanan_beranda') {
    header('Location: konten.php?section=beranda#layanan_beranda');
    exit;
}
if ($activeSection === 'poin') {
    header('Location: poin.php');
    exit;
}
if ($activeSection === 'ulasan' || $activeSection === 'review') {
    header('Location: ulasan.php');
    exit;
}
$subTabTentang = $_GET['tab'] ?? 'profil';

// Helper function ambil konten
if (!function_exists('getKonten')) {
    function getKonten($kunci, $default = '') {
        global $db;
        $stmt = $db->prepare("SELECT nilai FROM pengaturan_konten WHERE kunci = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $kunci);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                return $row['nilai'];
            }
            $stmt->close();
        }
        return $default;
    }
}

// Helper function simpan konten
if (!function_exists('setKonten')) {
    function setKonten($kunci, $nilai) {
        global $db;
        $stmt = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        if ($stmt) {
            $stmt->bind_param('ss', $kunci, $nilai);
            $stmt->execute();
            $stmt->close();
            return true;
        }
        return false;
    }
}

// Helper upload file
if (!function_exists('uploadFileKonten')) {
    function uploadFileKonten($fileInput, $subfolder = 'konten') {
        if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$fileInput];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            throw new Exception('Format file harus JPG, PNG, atau WEBP.');
        }
        if ($file['size'] > 6 * 1024 * 1024) {
            throw new Exception('Ukuran file maksimal 6 MB.');
        }
        $uploadDir = __DIR__ . '/../uploads/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $newFileName = $subfolder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $targetPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Gagal memindahkan file ke folder uploads.');
        }
        return 'uploads/' . $subfolder . '/' . $newFileName;
    }
}

// Helper upload multiple files untuk galeri fasilitas
if (!function_exists('uploadMultipleFiles')) {
    function uploadMultipleFiles($fileInput, $subfolder = 'fasilitas') {
        $uploaded = [];
        if (!isset($_FILES[$fileInput])) {
            return $uploaded;
        }
        $f = $_FILES[$fileInput];
        if (!is_array($f['name'])) {
            $single = uploadFileKonten($fileInput, $subfolder);
            return $single ? [$single] : [];
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $uploadDir = __DIR__ . '/../uploads/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $total = count($f['name']);
        for ($i = 0; $i < $total; $i++) {
            if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($f['size'][$i] > 8 * 1024 * 1024) continue;
            $tmpName = $f['tmp_name'][$i];
            if (!is_uploaded_file($tmpName)) continue;
            $mime = $finfo->file($tmpName);
            if (!in_array($mime, $allowedMime, true)) continue;

            $ext = pathinfo($f['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
            $newFileName = $subfolder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            $targetPath = $uploadDir . $newFileName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $uploaded[] = 'uploads/' . $subfolder . '/' . $newFileName;
            }
        }
        return $uploaded;
    }
}

// Helper upload audio file (.mp3, .wav, .ogg, .m4a)
if (!function_exists('uploadAudioKonten')) {
    function uploadAudioKonten($fileInput, $subfolder = 'audio') {
        if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $file = $_FILES[$fileInput];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
        if (!in_array($ext, $allowedExt, true)) {
            throw new Exception('Format file audio harus MP3, WAV, OGG, atau M4A.');
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new Exception('Ukuran file musik maksimal 20 MB.');
        }
        $uploadDir = __DIR__ . '/../uploads/' . $subfolder . '/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $newFileName = 'musik_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $targetPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Gagal menyimpan file audio ke folder uploads/audio.');
        }
        return 'uploads/' . $subfolder . '/' . $newFileName;
    }
}

// ----------------------------------------------------
// POST ACTIONS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'reorder_hero') {
            header('Content-Type: application/json; charset=utf-8');
            $orderRaw = $_POST['order'] ?? '';
            $ids = json_decode($orderRaw, true);
            if (is_array($ids)) {
                $pos = 1;
                $stmtU = $db->prepare("UPDATE banner_hero SET urutan = ? WHERE id_banner = ?");
                if ($stmtU) {
                    foreach ($ids as $idB) {
                        $idB = (int)$idB;
                        if ($idB > 0) {
                            $stmtU->bind_param('ii', $pos, $idB);
                            $stmtU->execute();
                            $pos++;
                        }
                    }
                    $stmtU->close();
                }
                echo json_encode(['success' => true]);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Data urutan tidak valid']);
            exit;
        } elseif ($action === 'tambah_hero') {
            $activeSection = 'hero';
            $judul = trim($_POST['judul'] ?? '');
            $subjudul = trim($_POST['subjudul'] ?? '');
            
            // Hitung urutan otomatis paling akhir
            $resMax = $db->query("SELECT MAX(urutan) as max_u FROM banner_hero");
            $maxU = ($resMax && $rowM = $resMax->fetch_assoc()) ? (int)$rowM['max_u'] : 0;
            $urutan = $maxU + 1;
            
            $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
            
            $file = uploadFileKonten('foto_hero', 'banner');
            if (!$file) {
                throw new Exception('Pilih berkas foto banner untuk diunggah.');
            }
            $stmt = $db->prepare("INSERT INTO banner_hero (judul, subjudul, foto, urutan, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('sssis', $judul, $subjudul, $file, $urutan, $status);
                $stmt->execute();
                $stmt->close();
                $message = 'Slide banner hero baru berhasil ditambahkan!';
            }
        } elseif ($action === 'edit_hero') {
            $activeSection = 'hero';
            $idBanner = (int)($_POST['id_banner'] ?? 0);
            $judul = trim($_POST['judul'] ?? '');
            $subjudul = trim($_POST['subjudul'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';
            
            if ($idBanner <= 0) {
                throw new Exception('ID slide banner tidak valid.');
            }
            $newFile = uploadFileKonten('foto_hero', 'banner');
            if ($newFile) {
                $stmt = $db->prepare("UPDATE banner_hero SET judul = ?, subjudul = ?, foto = ?, status = ? WHERE id_banner = ?");
                if ($stmt) {
                    $stmt->bind_param('ssssi', $judul, $subjudul, $newFile, $status, $idBanner);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $db->prepare("UPDATE banner_hero SET judul = ?, subjudul = ?, status = ? WHERE id_banner = ?");
                if ($stmt) {
                    $stmt->bind_param('sssi', $judul, $subjudul, $status, $idBanner);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $message = 'Slide banner hero berhasil diperbarui!';
        } elseif ($action === 'simpan_hero') {
            $activeSection = 'hero';
            $file1 = uploadFileKonten('hero_1', 'banner');
            $file2 = uploadFileKonten('hero_2', 'banner');
            if ($file1) setKonten('hero_foto_1', $file1);
            if ($file2) setKonten('hero_foto_2', $file2);
            $message = 'Foto Banner/Hero beranda berhasil diperbarui!';
        } elseif ($action === 'tambah_fasilitas') {
            $activeSection = 'fasilitas';
            $nama = trim($_POST['nama_fasilitas'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $urutan = (int)($_POST['urutan'] ?? 0);
            if ($nama === '') {
                throw new Exception('Nama fasilitas wajib diisi.');
            }
            $uploadedPhotos = uploadMultipleFiles('foto_fasilitas', 'fasilitas');
            $fotoCover = !empty($uploadedPhotos) ? $uploadedPhotos[0] : 'assets/img/klinik.jpg';
            $fotoGaleriJson = json_encode(array_values($uploadedPhotos), JSON_UNESCAPED_SLASHES);

            $stmt = $db->prepare("INSERT INTO fasilitas_klinik (nama_fasilitas, deskripsi, foto, foto_galeri, urutan) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('ssssi', $nama, $deskripsi, $fotoCover, $fotoGaleriJson, $urutan);
                $stmt->execute();
                $stmt->close();
                $message = 'Fasilitas klinik berhasil ditambahkan beserta foto galeri!';
            }
        } elseif ($action === 'edit_fasilitas') {
            $activeSection = 'fasilitas';
            $idF = (int)($_POST['id_fasilitas'] ?? 0);
            $nama = trim($_POST['nama_fasilitas'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $urutan = (int)($_POST['urutan'] ?? 0);
            if ($idF <= 0 || $nama === '') {
                throw new Exception('Data fasilitas tidak valid.');
            }

            // Ambil galeri eksisting
            $currentGaleri = [];
            $qOld = $db->query("SELECT foto, foto_galeri FROM fasilitas_klinik WHERE id_fasilitas = $idF LIMIT 1");
            if ($qOld && $old = $qOld->fetch_assoc()) {
                if (!empty($old['foto_galeri'])) {
                    $dec = json_decode($old['foto_galeri'], true);
                    if (is_array($dec)) $currentGaleri = $dec;
                }
                if (empty($currentGaleri) && !empty($old['foto'])) {
                    $currentGaleri = [$old['foto']];
                }
            }

            // Hapus foto yang dipilih untuk dihapus
            $hapusFoto = $_POST['hapus_foto'] ?? [];
            if (is_array($hapusFoto) && !empty($hapusFoto)) {
                $currentGaleri = array_values(array_filter($currentGaleri, fn($item) => !in_array($item, $hapusFoto, true)));
            }

            // Tambahkan foto tambahan baru jika ada yang diupload
            $newPhotos = uploadMultipleFiles('foto_tambahan', 'fasilitas');
            foreach ($newPhotos as $np) {
                $currentGaleri[] = $np;
            }

            // Tentukan cover foto
            $cover = !empty($currentGaleri) ? $currentGaleri[0] : 'assets/img/klinik.jpg';
            $galeriJson = json_encode(array_values($currentGaleri), JSON_UNESCAPED_SLASHES);

            $stmt = $db->prepare("UPDATE fasilitas_klinik SET nama_fasilitas = ?, deskripsi = ?, foto = ?, foto_galeri = ?, urutan = ? WHERE id_fasilitas = ?");
            if ($stmt) {
                $stmt->bind_param('ssssii', $nama, $deskripsi, $cover, $galeriJson, $urutan, $idF);
                $stmt->execute();
                $stmt->close();
                $message = 'Data fasilitas klinik dan galeri foto berhasil diperbarui!';
            }
        } elseif ($action === 'simpan_profil_klinik') {
            $activeSection = 'profil';
            $teksProfil = trim($_POST['profil_klinik_teks'] ?? '');
            if ($teksProfil !== '') {
                setKonten('profil_klinik_teks', $teksProfil);
            }
            $fotoProfil = uploadFileKonten('profil_klinik_foto', 'profil_klinik');
            if ($fotoProfil) {
                setKonten('profil_klinik_foto', $fotoProfil);
            }
            $message = 'Konten dan foto profil klinik berhasil diperbarui!';
        } elseif ($action === 'simpan_struktur') {
            $activeSection = 'struktur';
            $fotoStruktur = uploadFileKonten('struktur_organisasi_foto', 'struktur');
            if ($fotoStruktur) {
                setKonten('struktur_organisasi_foto', $fotoStruktur);
            }
            $fieldsStruktur = [
                'struktur_hero_badge', 'struktur_hero_judul', 'struktur_hero_subjudul',
                'struktur_dirut_nama', 'struktur_dirop_nama', 'struktur_kepala_nama',
                'struktur_pj_mutu', 'struktur_pj_ppi', 'struktur_pj_pelayanan', 'struktur_pj_manajemen',
                'struktur_pj_umum', 'struktur_pj_risiko', 'struktur_pj_keselamatan',
                'struktur_unit_poli_umum', 'struktur_unit_poli_gigi', 'struktur_unit_poli_kebidanan', 'struktur_unit_ruang_tindakan',
                'struktur_unit_pendaftaran', 'struktur_unit_farmasi', 'struktur_unit_rekam_medis', 'struktur_unit_promotif',
                'struktur_unit_kepegawaian', 'struktur_unit_kebersihan', 'struktur_unit_keamanan'
            ];
            foreach ($fieldsStruktur as $fs) {
                if (isset($_POST[$fs])) {
                    setKonten($fs, trim($_POST[$fs]));
                }
            }
            // Simpan baris jabatan dinamis tambahan jika ada (JSON)
            $jabatanArr = $_POST['jabatan'] ?? [];
            $namaArr = $_POST['nama_pejabat'] ?? [];
            $tugasArr = $_POST['tugas'] ?? [];
            $strukturList = [];
            for ($i = 0; $i < count($jabatanArr); $i++) {
                $jb = trim($jabatanArr[$i] ?? '');
                $nm = trim($namaArr[$i] ?? '');
                $tg = trim($tugasArr[$i] ?? '');
                if ($jb !== '') {
                    $strukturList[] = ['jabatan' => $jb, 'nama' => $nm, 'tugas' => $tg];
                }
            }
            setKonten('struktur_organisasi_teks', json_encode($strukturList, JSON_UNESCAPED_UNICODE));
            $message = 'Struktur organisasi klinik berhasil diperbarui!';
        } elseif ($action === 'simpan_beranda') {
            $activeSection = 'beranda';
            $fields = [
                'hero_judul', 'hero_subjudul', 'hero_badge_persalinan',
                'beranda_slogan', 'hero_promo_judul', 'hero_promo_subjudul',
                'hero_btn_daftar', 'hero_btn_jadwal',
                'layanan_section_label', 'layanan_section_judul', 'layanan_section_subjudul',
                'layanan_item_1_nama', 'layanan_item_1_deskripsi', 'layanan_item_1_icon',
                'layanan_item_2_nama', 'layanan_item_2_deskripsi', 'layanan_item_2_icon',
                'layanan_item_3_nama', 'layanan_item_3_deskripsi', 'layanan_item_3_icon',
                'layanan_item_4_nama', 'layanan_item_4_deskripsi', 'layanan_item_4_icon',
                'layanan_item_5_nama', 'layanan_item_5_deskripsi', 'layanan_item_5_icon',
                'layanan_item_6_nama', 'layanan_item_6_deskripsi', 'layanan_item_6_icon',
                'quick_item_1_nama', 'quick_item_1_deskripsi',
                'quick_item_2_nama', 'quick_item_2_deskripsi',
                'quick_item_3_nama', 'quick_item_3_deskripsi',
                'quick_item_4_nama', 'quick_item_4_deskripsi',
                'quick_item_5_nama', 'quick_item_5_deskripsi',
                'tentang_judul', 'tentang_paragraf_1', 'tentang_paragraf_2',
                'tentang_poin_1', 'tentang_poin_2', 'tentang_poin_3', 'tentang_poin_4'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $fotoTentang = uploadFileKonten('tentang_foto', 'konten');
            if ($fotoTentang) {
                setKonten('tentang_foto', $fotoTentang);
            }
            $message = 'Konten Beranda (Hero, Layanan Beranda & Tentang Kami) berhasil diperbarui!';
        } elseif ($action === 'simpan_halaman_layanan') {
            $activeSection = 'layanan';
            $fields = [
                'layanan_hero_badge', 'layanan_hero_judul', 'layanan_hero_subjudul',
                'layanan_cakupan_judul', 'layanan_cakupan_subjudul',
                'layanan_bpjs_judul', 'layanan_bpjs_deskripsi', 'layanan_bpjs_syarat'
            ];
            for ($i = 1; $i <= 7; $i++) {
                $fields[] = "layanan_card_{$i}_icon";
                $fields[] = "layanan_card_{$i}_judul";
                $fields[] = "layanan_card_{$i}_desc";
            }
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $message = 'Konten Halaman Layanan & BPJS berhasil diperbarui!';
        } elseif ($action === 'simpan_tentang_profil') {
            $activeSection = 'tentang';
            $subTabTentang = 'profil';
            $fields = [
                'profil_klinik_hero_judul', 'profil_klinik_hero_subjudul',
                'profil_klinik_motto', 'profil_klinik_teks_tentang',
                'profil_klinik_badan_usaha', 'profil_klinik_pendiri',
                'profil_klinik_jenis_faskes', 'profil_klinik_alamat',
                'profil_klinik_jam_operasional', 'profil_klinik_nomor_akreditasi',
                'profil_klinik_masa_akreditasi', 'profil_klinik_lembaga_akreditasi',
                'profil_klinik_regulator', 'profil_klinik_wa_1', 'profil_klinik_wa_2', 'profil_klinik_ig',
                'profil_klinik_legalitas_nomor', 'profil_klinik_legalitas_kbli', 'profil_klinik_legalitas_status',
                'profil_klinik_legalitas_pelaku', 'profil_klinik_legalitas_faskes', 'profil_klinik_legalitas_kemampuan',
                'profil_klinik_legalitas_penyelenggaraan', 'profil_klinik_legalitas_kesesuaian'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            if (isset($_POST['profil_klinik_teks_tentang'])) {
                setKonten('profil_klinik_teks', trim($_POST['profil_klinik_teks_tentang']));
            }
            
            // HAPUS ATAU UPLOAD FOTO PROFIL GEDUNG
            if (!empty($_POST['hapus_profil_foto'])) {
                setKonten('profil_klinik_foto', '');
            } else {
                $fotoProfil = uploadFileKonten('profil_klinik_foto', 'profil_klinik');
                if ($fotoProfil) {
                    setKonten('profil_klinik_foto', $fotoProfil);
                }
            }

            // HAPUS ATAU UPLOAD FOTO AKREDITASI
            if (!empty($_POST['hapus_akreditasi_foto'])) {
                setKonten('profil_klinik_foto_akreditasi', '');
            } else {
                $fotoAkred = uploadFileKonten('profil_klinik_foto_akreditasi', 'profil_klinik');
                if ($fotoAkred) {
                    setKonten('profil_klinik_foto_akreditasi', $fotoAkred);
                }
            }

            // HAPUS ATAU UPLOAD FOTO LEGALITAS
            if (!empty($_POST['hapus_legalitas_foto'])) {
                setKonten('profil_klinik_foto_legalitas', '');
            } else {
                $fotoLeg = uploadFileKonten('profil_klinik_foto_legalitas', 'profil_klinik');
                if ($fotoLeg) {
                    setKonten('profil_klinik_foto_legalitas', $fotoLeg);
                }
            }

            $message = 'Profil Resmi & Sertifikat Akreditasi pada Halaman Tentang berhasil diperbarui!';
        } elseif ($action === 'simpan_tentang_visi') {
            $activeSection = 'tentang';
            $subTabTentang = 'visi_misi';
            $fields = [
                'visi_misi_hero_judul', 'visi_misi_hero_subjudul', 'visi_misi_visi', 'visi_misi_motto',
                'visi_misi_misi_1_judul', 'visi_misi_misi_1_teks',
                'visi_misi_misi_2_judul', 'visi_misi_misi_2_teks',
                'visi_misi_misi_3_judul', 'visi_misi_misi_3_teks',
                'visi_misi_misi_4_judul', 'visi_misi_misi_4_teks',
                'visi_misi_nilai_1_judul', 'visi_misi_nilai_1_teks',
                'visi_misi_nilai_2_judul', 'visi_misi_nilai_2_teks',
                'visi_misi_nilai_3_judul', 'visi_misi_nilai_3_teks',
                'visi_misi_nilai_4_judul', 'visi_misi_nilai_4_teks'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $message = 'Visi, Misi & 4 Nilai Utama Klinik pada Halaman Tentang berhasil diperbarui!';
        } elseif ($action === 'simpan_tentang_sejarah') {
            $activeSection = 'tentang';
            $subTabTentang = 'sejarah';
            $fields = [
                'sejarah_hero_judul', 'sejarah_hero_subjudul',
                'sejarah_kisah_awal', 'sejarah_quote',
                'sejarah_komitmen', 'sejarah_fokus',
                'sejarah_caption_arsip_1', 'sejarah_caption_arsip_2',
                'sejarah_timeline_1_tahun', 'sejarah_timeline_1_judul', 'sejarah_timeline_1_teks',
                'sejarah_timeline_2_tahun', 'sejarah_timeline_2_judul', 'sejarah_timeline_2_teks',
                'sejarah_timeline_3_tahun', 'sejarah_timeline_3_judul', 'sejarah_timeline_3_teks',
                'sejarah_timeline_4_tahun', 'sejarah_timeline_4_judul', 'sejarah_timeline_4_teks'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }

            // KELOLA FOTO UTAMA KISAH SEJARAH (UPLOAD & HAPUS)
            if (!empty($_POST['hapus_sejarah_foto_utama'])) {
                setKonten('sejarah_foto_utama', '');
            } else {
                $fUtama = uploadFileKonten('sejarah_foto_utama', 'sejarah');
                if ($fUtama) {
                    setKonten('sejarah_foto_utama', $fUtama);
                }
            }

            // KELOLA GALERI FOTO SEJARAH DINAMIS (TAMBAH, EDIT, HAPUS)
            $galeriJson = getKonten('sejarah_galeri_json', '[]');
            $galeri = json_decode($galeriJson, true) ?: [];

            $hapusList = $_POST['hapus_galeri_foto'] ?? [];
            if (!is_array($hapusList)) $hapusList = [];
            $captionList = $_POST['galeri_caption'] ?? [];
            $tahunList = $_POST['galeri_tahun'] ?? [];

            $newGaleri = [];
            foreach ($galeri as $idx => $item) {
                $fid = (string)($item['id'] ?? $idx);
                if (in_array($fid, $hapusList, true)) {
                    continue; // Hapus foto ini
                }
                if (isset($captionList[$fid])) {
                    $item['caption'] = trim($captionList[$fid]);
                }
                if (isset($tahunList[$fid])) {
                    $item['tahun'] = trim($tahunList[$fid]);
                }
                $newGaleri[] = $item;
            }

            // Upload foto baru jika ada
            if (isset($_FILES['sejarah_foto_baru']) && !empty($_FILES['sejarah_foto_baru']['name'])) {
                $uploaded = uploadMultipleFiles('sejarah_foto_baru', 'sejarah');
                $captionsBaru = $_POST['caption_foto_baru'] ?? '';
                $tahunBaru = $_POST['tahun_foto_baru'] ?? '';
                foreach ($uploaded as $upPath) {
                    $newGaleri[] = [
                        'id' => time() . '_' . mt_rand(100, 999),
                        'foto' => $upPath,
                        'caption' => trim($captionsBaru) !== '' ? trim($captionsBaru) : 'Dokumentasi Sejarah Klinik',
                        'tahun' => trim($tahunBaru) !== '' ? trim($tahunBaru) : date('Y')
                    ];
                }
            }

            // Fallback arsip 1 & 2 upload langsung jika ada
            if (!empty($_POST['hapus_arsip_1'])) {
                setKonten('sejarah_foto_arsip_1', '');
            } else {
                $fArsip1 = uploadFileKonten('sejarah_foto_arsip_1', 'sejarah');
                if ($fArsip1) setKonten('sejarah_foto_arsip_1', $fArsip1);
            }
            if (!empty($_POST['hapus_arsip_2'])) {
                setKonten('sejarah_foto_arsip_2', '');
            } else {
                $fArsip2 = uploadFileKonten('sejarah_foto_arsip_2', 'sejarah');
                if ($fArsip2) setKonten('sejarah_foto_arsip_2', $fArsip2);
            }

            setKonten('sejarah_galeri_json', json_encode($newGaleri, JSON_UNESCAPED_UNICODE));
            if (!empty($newGaleri[0]['foto'])) {
                setKonten('sejarah_foto_arsip_1', $newGaleri[0]['foto']);
                setKonten('sejarah_caption_arsip_1', $newGaleri[0]['caption']);
            }
            if (!empty($newGaleri[1]['foto'])) {
                setKonten('sejarah_foto_arsip_2', $newGaleri[1]['foto']);
                setKonten('sejarah_caption_arsip_2', $newGaleri[1]['caption']);
            }

            $message = 'Sejarah, Linimasa & Galeri Arsip Pendirian Klinik berhasil diperbarui!';
        } elseif ($action === 'simpan_tentang_struktur') {
            $activeSection = 'tentang';
            $subTabTentang = 'struktur';

            // KELOLA POSISI STRUKTUR ORGANISASI SECARA DINAMIS (TAMBAH, EDIT, HAPUS)
            $timList = [];
            if (!empty($_POST['posisi_jabatan']) && is_array($_POST['posisi_jabatan'])) {
                foreach ($_POST['posisi_jabatan'] as $i => $jabatan) {
                    $j = trim($jabatan);
                    $n = trim($_POST['posisi_nama'][$i] ?? '');
                    $k = trim($_POST['posisi_kategori'][$i] ?? 'Lainnya');
                    $ic = trim($_POST['posisi_icon'][$i] ?? '👥');
                    if ($j !== '' || $n !== '') {
                        $timList[] = [
                            'kategori' => $k,
                            'jabatan' => $j,
                            'nama' => $n,
                            'icon' => $ic
                        ];
                    }
                }
            }
            if (!empty($timList)) {
                setKonten('struktur_tim_json', json_encode($timList, JSON_UNESCAPED_UNICODE));
            }

            // Sync field legacy untuk backward compatibility
            $fields = [
                'struktur_dirut_nama', 'struktur_dirop_nama', 'struktur_kepala_nama',
                'struktur_pj_mutu', 'struktur_pj_ppi', 'struktur_pj_pelayanan',
                'struktur_pj_manajemen', 'struktur_pj_umum', 'struktur_pj_risiko', 'struktur_pj_keselamatan',
                'struktur_unit_poli_umum', 'struktur_unit_poli_gigi', 'struktur_unit_poli_kebidanan', 'struktur_unit_ruang_tindakan',
                'struktur_unit_pendaftaran', 'struktur_unit_farmasi', 'struktur_unit_rekam_medis', 'struktur_unit_promotif',
                'struktur_unit_kepegawaian', 'struktur_unit_kebersihan', 'struktur_unit_keamanan'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }

            // HAPUS ATAU UPLOAD GAMBAR BAGAN STRUKTUR
            if (!empty($_POST['hapus_bagan_foto'])) {
                setKonten('struktur_organisasi_foto', '');
            } else {
                $fotoBagan = uploadFileKonten('struktur_organisasi_foto', 'struktur');
                if ($fotoBagan) {
                    setKonten('struktur_organisasi_foto', $fotoBagan);
                }
            }

            $message = 'Bagan & Susunan Struktur Organisasi Klinik berhasil diperbarui!';
        } elseif ($action === 'simpan_tentang_fasilitas_hero') {
            $activeSection = 'tentang';
            $subTabTentang = 'fasilitas';
            $fields = ['fasilitas_hero_badge', 'fasilitas_hero_judul', 'fasilitas_hero_subjudul'];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $message = 'Header & Keterangan Halaman Fasilitas berhasil diperbarui!';
        } elseif ($action === 'tambah_fasilitas') {
            $activeSection = 'tentang';
            $subTabTentang = 'fasilitas';
            $nama = trim($_POST['nama_fasilitas'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $urutan = (int)($_POST['urutan'] ?? 0);

            if ($nama === '') {
                throw new Exception('Nama fasilitas tidak boleh kosong.');
            }

            $fotoUtama = uploadFileKonten('foto_fasilitas', 'fasilitas') ?: 'assets/img/klinik1.jpg';
            $fotoGaleriArray = uploadMultipleFiles('foto_galeri', 'fasilitas');
            $fotoGaleriJson = !empty($fotoGaleriArray) ? json_encode($fotoGaleriArray) : null;

            $stmtF = $db->prepare("INSERT INTO fasilitas_klinik (nama_fasilitas, deskripsi, foto, foto_galeri, urutan) VALUES (?, ?, ?, ?, ?)");
            if ($stmtF) {
                $stmtF->bind_param('ssssi', $nama, $deskripsi, $fotoUtama, $fotoGaleriJson, $urutan);
                $stmtF->execute();
                $stmtF->close();
                $message = "Fasilitas '{$nama}' berhasil ditambahkan ke daftar fasilitas!";
            } else {
                throw new Exception('Gagal menyimpan fasilitas: ' . $db->error);
            }
        } elseif ($action === 'edit_fasilitas') {
            $activeSection = 'tentang';
            $subTabTentang = 'fasilitas';
            $idFasilitas = (int)($_POST['id_fasilitas'] ?? 0);
            $nama = trim($_POST['nama_fasilitas'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');
            $urutan = (int)($_POST['urutan'] ?? 0);

            if ($idFasilitas <= 0 || $nama === '') {
                throw new Exception('Data fasilitas tidak valid.');
            }

            $fotoUtama = uploadFileKonten('foto_fasilitas', 'fasilitas');
            $fotoGaleriBaru = uploadMultipleFiles('foto_galeri', 'fasilitas');
            $hapusFoto = $_POST['hapus_foto'] ?? [];

            $qOld = $db->query("SELECT foto, foto_galeri FROM fasilitas_klinik WHERE id_fasilitas = $idFasilitas LIMIT 1");
            $rOld = $qOld ? $qOld->fetch_assoc() : null;
            $fotoFinal = $fotoUtama ?: ($rOld['foto'] ?? 'assets/img/klinik1.jpg');

            $galeriLama = json_decode((string)($rOld['foto_galeri'] ?? '[]'), true) ?: [];
            if (!empty($hapusFoto) && is_array($hapusFoto)) {
                $galeriLama = array_values(array_diff($galeriLama, $hapusFoto));
            }
            if (!empty($fotoGaleriBaru)) {
                $galeriLama = array_values(array_unique(array_merge($galeriLama, $fotoGaleriBaru)));
            }
            $galeriFinalJson = !empty($galeriLama) ? json_encode($galeriLama) : null;

            $stmtE = $db->prepare("UPDATE fasilitas_klinik SET nama_fasilitas = ?, deskripsi = ?, foto = ?, foto_galeri = ?, urutan = ? WHERE id_fasilitas = ?");
            if ($stmtE) {
                $stmtE->bind_param('ssssii', $nama, $deskripsi, $fotoFinal, $galeriFinalJson, $urutan, $idFasilitas);
                $stmtE->execute();
                $stmtE->close();
                $message = "Fasilitas '{$nama}' berhasil diperbarui!";
            } else {
                throw new Exception('Gagal memperbarui fasilitas: ' . $db->error);
            }
        } elseif ($action === 'hapus_fasilitas') {
            $activeSection = 'tentang';
            $subTabTentang = 'fasilitas';
            $idFasilitas = (int)($_POST['id_fasilitas'] ?? 0);
            if ($idFasilitas > 0) {
                $db->query("DELETE FROM fasilitas_klinik WHERE id_fasilitas = $idFasilitas LIMIT 1");
                $message = 'Fasilitas berhasil dihapus dari sistem!';
            }
        } elseif ($action === 'simpan_tentang_beranda') {
            $activeSection = 'tentang';
            $subTabTentang = 'beranda_tentang';
            $fields = [
                'tentang_judul', 'tentang_paragraf_1', 'tentang_paragraf_2',
                'tentang_poin_1', 'tentang_poin_2', 'tentang_poin_3', 'tentang_poin_4'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $fotoTentang = uploadFileKonten('tentang_foto', 'konten');
            if ($fotoTentang) {
                setKonten('tentang_foto', $fotoTentang);
            }
            $message = 'Section Tentang Kami pada Beranda berhasil diperbarui!';
        } elseif ($action === 'acc_voucher') {
            $activeSection = 'poin';
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            $catatan = trim($_POST['catatan_admin'] ?? 'Disetujui oleh Admin');
            if ($idPenukaran <= 0) {
                throw new Exception('ID penukaran voucher tidak valid.');
            }
            $qCek = $db->query("SELECT id_pasien, poin_digunakan, status FROM penukaran_poin WHERE id_penukaran = $idPenukaran LIMIT 1");
            if (!$qCek || !$rowCek = $qCek->fetch_assoc()) {
                throw new Exception('Data penukaran tidak ditemukan.');
            }
            if ($rowCek['status'] === 'disetujui') {
                throw new Exception('Pengajuan voucher ini sudah disetujui sebelumnya.');
            }
            $idPasien = (int)$rowCek['id_pasien'];
            $poinPakai = (int)$rowCek['poin_digunakan'];

            // Potong saldo poin pasien saat ACC disetujui
            if ($idPasien > 0 && $poinPakai > 0) {
                $db->query("UPDATE pasien SET reward_poin = GREATEST(0, reward_poin - $poinPakai) WHERE id_pasien = $idPasien");
            }

            $stmtAcc = $db->prepare("UPDATE penukaran_poin SET status = 'disetujui', catatan_admin = ?, updated_at = NOW() WHERE id_penukaran = ?");
            if ($stmtAcc) {
                $stmtAcc->bind_param('si', $catatan, $idPenukaran);
                $stmtAcc->execute();
                $stmtAcc->close();
                $message = "Voucher reward berhasil disetujui (ACC)! Saldo {$poinPakai} poin pasien telah resmi dipotong.";
            }
        } elseif ($action === 'tolak_voucher') {
            $activeSection = 'poin';
            $idPenukaran = (int)($_POST['id_penukaran'] ?? 0);
            $catatan = trim($_POST['catatan_admin'] ?? 'Ditolak oleh Admin');
            if ($idPenukaran <= 0) {
                throw new Exception('ID penukaran voucher tidak valid.');
            }
            $qCek = $db->query("SELECT id_pasien, poin_digunakan, status FROM penukaran_poin WHERE id_penukaran = $idPenukaran LIMIT 1");
            if (!$qCek || !$rowCek = $qCek->fetch_assoc()) {
                throw new Exception('Data penukaran tidak ditemukan.');
            }
            if ($rowCek['status'] === 'ditolak') {
                throw new Exception('Penukaran voucher ini sudah ditolak sebelumnya.');
            }
            
            $stmtTolak = $db->prepare("UPDATE penukaran_poin SET status = 'ditolak', catatan_admin = ?, updated_at = NOW() WHERE id_penukaran = ?");
            if ($stmtTolak) {
                $stmtTolak->bind_param('si', $catatan, $idPenukaran);
                $stmtTolak->execute();
                $stmtTolak->close();
            }
            // Karena poin belum dipotong saat pengajuan, tidak perlu refund poin
            $message = "Klaim voucher telah ditolak. Poin pasien tidak berkurang.";
        } elseif ($action === 'ubah_poin_pasien') {
            $activeSection = 'poin';
            $idPasien = (int)($_POST['id_pasien'] ?? 0);
            $poinBaru = max(0, (int)($_POST['reward_poin'] ?? 0));
            if ($idPasien <= 0) {
                throw new Exception('Data pasien tidak valid.');
            }
            $stmtPoin = $db->prepare("UPDATE pasien SET reward_poin = ? WHERE id_pasien = ?");
            if ($stmtPoin) {
                $stmtPoin->bind_param('ii', $poinBaru, $idPasien);
                $stmtPoin->execute();
                $stmtPoin->close();
                $message = "Saldo reward poin pasien ID #{$idPasien} berhasil diperbarui menjadi {$poinBaru} poin!";
            }
        } elseif ($action === 'simpan_navbar') {
            $activeSection = 'beranda';
            $fields = [
                'navbar_brand_nama', 'navbar_brand_slogan',
                'navbar_menu_beranda', 'navbar_menu_layanan', 'navbar_menu_jadwal',
                'navbar_menu_dokter', 'navbar_menu_tentang', 'navbar_menu_kontak',
                'navbar_btn_login',
                'navbar_sub_profil', 'navbar_sub_sejarah', 'navbar_sub_visi_misi',
                'navbar_sub_fasilitas', 'navbar_sub_struktur'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $message = 'Pengaturan Navbar berhasil diperbarui!';
        } elseif ($action === 'simpan_musik') {
            $activeSection = 'musik';
            $statusAktif = isset($_POST['audio_beranda_aktif']) && $_POST['audio_beranda_aktif'] === '1' ? '1' : '0';
            setKonten('audio_beranda_aktif', $statusAktif);
            
            $judul = trim($_POST['audio_beranda_judul'] ?? '');
            if ($judul !== '') {
                setKonten('audio_beranda_judul', $judul);
            }
            
            $audioFile = uploadAudioKonten('audio_file', 'audio');
            if ($audioFile) {
                setKonten('audio_beranda_file', $audioFile);
            }
            $message = 'Pengaturan musik/lagu latar website berhasil disimpan!';
        } elseif ($action === 'simpan_kontak_footer') {
            $activeSection = 'kontak_footer';
            $fields = [
                'kontak_telepon', 'kontak_instagram', 'kontak_jam_buka', 'kontak_alamat',
                'footer_deskripsi', 'footer_legalitas', 'footer_wa1', 'footer_wa2',
                'footer_maps_link', 'footer_copyright'
            ];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    setKonten($f, trim($_POST[$f]));
                }
            }
            $message = 'Informasi Kontak dan Footer berhasil disimpan!';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle Hapus Fasilitas
if (isset($_GET['hapus_fasilitas'])) {
    $activeSection = 'fasilitas';
    $idHapus = (int)$_GET['hapus_fasilitas'];
    $stmtDel = $db->prepare("DELETE FROM fasilitas_klinik WHERE id_fasilitas = ?");
    if ($stmtDel) {
        $stmtDel->bind_param('i', $idHapus);
        $stmtDel->execute();
        $stmtDel->close();
        $message = 'Fasilitas berhasil dihapus.';
    }
}

// Handle Hapus Slide Hero
if (isset($_GET['hapus_hero'])) {
    $activeSection = 'hero';
    $idHapus = (int)$_GET['hapus_hero'];
    $stmtDel = $db->prepare("DELETE FROM banner_hero WHERE id_banner = ?");
    if ($stmtDel) {
        $stmtDel->bind_param('i', $idHapus);
        $stmtDel->execute();
        $stmtDel->close();
        $message = 'Slide banner hero berhasil dihapus.';
    }
}

// Handle Toggle Status Slide Hero
if (isset($_GET['toggle_hero'])) {
    $activeSection = 'hero';
    $idToggle = (int)$_GET['toggle_hero'];
    $stmtTog = $db->prepare("UPDATE banner_hero SET status = IF(status='aktif','nonaktif','aktif') WHERE id_banner = ?");
    if ($stmtTog) {
        $stmtTog->bind_param('i', $idToggle);
        $stmtTog->execute();
        $stmtTog->close();
        $message = 'Status slide banner hero berhasil diubah.';
    }
}

// Data Konten Aktif
$heroBanners = [];
$qH = $db->query("SELECT * FROM banner_hero ORDER BY urutan ASC, id_banner ASC");
if ($qH) while ($r = $qH->fetch_assoc()) $heroBanners[] = $r;

$heroFoto1 = getKonten('hero_foto_1', 'assets/img/klinik.jpg');
$heroFoto2 = getKonten('hero_foto_2', 'assets/img/klinik2.jpg');
$profilFoto = getKonten('profil_klinik_foto', 'assets/img/klinik3.jpg');
$profilTeks = getKonten('profil_klinik_teks', '');
$strukturFoto = getKonten('struktur_organisasi_foto', '');
$strukturJson = getKonten('struktur_organisasi_teks', '[]');
$strukturItems = json_decode($strukturJson, true) ?: [];

// Data Dokter & Bidan
$dokterList = [];
$qD = $db->query("SELECT id_dokter, nama_dokter, spesialisasi, foto FROM dokter WHERE status='aktif' ORDER BY nama_dokter ASC");
if ($qD) while ($r = $qD->fetch_assoc()) $dokterList[] = $r;

$bidanList = [];
$qB = $db->query("SELECT id_bidan, nama_bidan, foto FROM bidan WHERE status='aktif' ORDER BY nama_bidan ASC");
if ($qB) while ($r = $qB->fetch_assoc()) $bidanList[] = $r;

// Data Fasilitas
$fasilitasList = [];
$qF = $db->query("SELECT * FROM fasilitas_klinik ORDER BY urutan ASC, id_fasilitas ASC");
if ($qF) while ($r = $qF->fetch_assoc()) $fasilitasList[] = $r;

// Data Penukaran Poin & Voucher
$filterStatusVoucher = $_GET['status_voucher'] ?? '';
$sqlPoin = "SELECT pp.*, p.nama_lengkap, p.nik, p.no_telepon, p.reward_poin as saldo_saat_ini 
            FROM penukaran_poin pp 
            LEFT JOIN pasien p ON pp.id_pasien = p.id_pasien";
if (in_array($filterStatusVoucher, ['menunggu', 'disetujui', 'ditolak'], true)) {
    $sqlPoin .= " WHERE pp.status = '" . $db->real_escape_string($filterStatusVoucher) . "'";
}
$sqlPoin .= " ORDER BY pp.id_penukaran DESC LIMIT 100";
$listVoucher = [];
$qPoin = $db->query($sqlPoin);
if ($qPoin) while ($r = $qPoin->fetch_assoc()) $listVoucher[] = $r;

// Data Pasien untuk Ubah Poin
$searchPasienPoin = trim($_GET['q_pasien'] ?? '');
$sqlPasienPoin = "SELECT id_pasien, nama_lengkap, nik, no_telepon, reward_poin FROM pasien";
if ($searchPasienPoin !== '') {
    $sc = $db->real_escape_string($searchPasienPoin);
    $sqlPasienPoin .= " WHERE nama_lengkap LIKE '%$sc%' OR nik LIKE '%$sc%' OR no_telepon LIKE '%$sc%'";
}
$sqlPasienPoin .= " ORDER BY reward_poin DESC, id_pasien DESC LIMIT 50";
$listPasienPoin = [];
$qPP = $db->query($sqlPasienPoin);
if ($qPP) while ($r = $qPP->fetch_assoc()) $listPasienPoin[] = $r;

// Data Paket Layanan
$paketLayananAll = [];
$qPL = $db->query("SELECT * FROM paket_layanan ORDER BY urutan ASC, id_paket ASC");
if ($qPL) while ($r = $qPL->fetch_assoc()) $paketLayananAll[] = $r;

adminPageStart('Kelola Konten Website', 'konten');
?>

<style>
.tab-content-panel { display: none; }
.tab-content-panel.active { display: block; animation: hananiaFadeDown 0.2s ease; }
.content-nav-btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--hanania-muted);
    background: #FFFFFF;
    border: 1px solid var(--hanania-border);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}
.content-nav-btn:hover {
    color: var(--hanania-primary);
    background: var(--hanania-purple-soft);
}
.content-nav-btn.active {
    background: var(--hanania-primary);
    color: #FFFFFF;
    border-color: var(--hanania-primary);
    box-shadow: 0 4px 12px rgba(79, 50, 147, 0.2);
}
.preview-thumb-box {
    width: 100%;
    height: 160px;
    border-radius: 12px;
    background: #FAF8FD;
    border: 1px solid var(--hanania-border);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}
.preview-thumb-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.hero-grid-layout {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 1100px) {
    .hero-grid-layout {
        grid-template-columns: 1fr;
    }
}
.table-action-group {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
}
.hero-sortable-row {
    transition: background 0.15s;
}
.hero-sortable-row.hero-dragging {
    opacity: 0.4;
    background: #EFE8F7 !important;
}
.hero-sortable-row.hero-drag-over {
    border-top: 3px solid var(--hanania-primary) !important;
}
.hero-drag-handle {
    cursor: grab;
    padding: 5px 9px;
    border-radius: 7px;
    background: #F3EDFA;
    border: 1px solid #DCCEEB;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--hanania-primary);
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.hero-drag-handle:active {
    cursor: grabbing;
}
.btn-hero-arrow {
    border: 1px solid #D8CAEB;
    background: #fff;
    border-radius: 6px;
    padding: 4px 7px;
    cursor: pointer;
    font-size: 10px;
    font-weight: bold;
    color: var(--hanania-primary);
    line-height: 1;
    transition: all 0.15s;
}
.btn-hero-arrow:hover {
    background: var(--hanania-primary);
    color: #fff;
    border-color: var(--hanania-primary);
}
</style>

<div class="admin-page" style="padding: 24px 30px 60px;">

    <!-- HEADER TITLE -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:22px;font-weight:900;color:var(--hanania-primary);letter-spacing:-.3px;">
                🖼️ Pengelolaan Konten Website Publik
            </h2>
            <p style="font-size:13px;color:var(--hanania-muted);margin-top:3px;">
                Perbarui foto banner hero, galeri fasilitas, profil klinik, dan struktur organisasi secara terpadu.
            </p>
        </div>
        <a href="../index.php" target="_blank" rel="noopener" class="btn btn-sm btn-light">
            🌐 Buka Halaman Publik &rarr;
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;">
            <span><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- TAB NAVIGASI KONTEN -->
    <div style="display:flex;gap:8px;margin-bottom:24px;overflow-x:auto;padding-bottom:6px;flex-wrap:wrap;">
        <a href="konten.php?section=beranda" class="content-nav-btn <?= $activeSection === 'beranda' ? 'active' : '' ?>">
            🏠 Konten Beranda
        </a>
        <a href="konten.php?section=layanan" class="content-nav-btn <?= $activeSection === 'layanan' ? 'active' : '' ?>">
            🏷️ Halaman Layanan
        </a>
        <a href="konten.php?section=tentang" class="content-nav-btn <?= $activeSection === 'tentang' ? 'active' : '' ?>">
            🏢 Halaman Tentang
        </a>
        <a href="konten.php?section=musik" class="content-nav-btn <?= $activeSection === 'musik' ? 'active' : '' ?>">
            🎵 Musik &amp; Lagu Latar
        </a>
        <a href="konten.php?section=kontak_footer" class="content-nav-btn <?= $activeSection === 'kontak_footer' ? 'active' : '' ?>">
            📞 Kontak &amp; Footer
        </a>
        <a href="konten.php?section=hero" class="content-nav-btn <?= $activeSection === 'hero' ? 'active' : '' ?>">
            🌄 Foto Banner / Hero
        </a>
    </div>

    <!-- ========================================================= -->
    <!-- 0. SECTION: BERANDA / INDEX                              -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'beranda'): ?>
        <section class="admin-card">
            <div class="admin-card-head">
                <div class="admin-card-title">
                    <span>🏠 Kelola Teks &amp; Konten Utama Beranda</span>
                </div>
                <div class="admin-card-subtitle">
                    Atur headline hero, informasi layanan beranda, quick menu, dan section tentang kami yang tampil di halaman utama (index.php).
                </div>
            </div>

            <form method="POST" action="konten.php?section=beranda" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <input type="hidden" name="action" value="simpan_beranda">

                <!-- 1. BAGIAN HERO HEADLINE -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:8px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>✨</span> 1. Hero &amp; Headline Utama Beranda
                    </h3>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Utama Hero (Headline)</label>
                    <input type="text" name="hero_judul" value="<?= adminEsc(getKonten('hero_judul', 'Solusi Pelayanan Medis Praktis & Terpercaya')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Badge Persalinan 24 Jam</label>
                    <input type="text" name="hero_badge_persalinan" value="<?= adminEsc(getKonten('hero_badge_persalinan', '🤰 Persalinan 24 Jam')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Slogan / Tagline Beranda</label>
                    <input type="text" name="beranda_slogan" value="<?= adminEsc(getKonten('beranda_slogan', 'Melayani dengan Hati, Menebar Kebaikan untuk Semua')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul / Deskripsi Hero</label>
                    <textarea name="hero_subjudul" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('hero_subjudul', 'Temukan informasi layanan, jadwal pelayanan, dokter dan bidan, serta lakukan pendaftaran pasien secara online melalui satu website.')) ?></textarea>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Promo Sahabat Hanania</label>
                    <input type="text" name="hero_promo_judul" value="<?= adminEsc(getKonten('hero_promo_judul', '✨ Jadi Sahabat Hanania, dapatkan Reward!')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Deskripsi Promo Sahabat Hanania</label>
                    <input type="text" name="hero_promo_subjudul" value="<?= adminEsc(getKonten('hero_promo_subjudul', 'Daftar sebagai member pasien untuk mendapatkan akses layanan online sekaligus mengumpulkan poin reward.')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Label Tombol CTA Utama (Kiri)</label>
                    <input type="text" name="hero_btn_daftar" value="<?= adminEsc(getKonten('hero_btn_daftar', 'Daftar Online')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Label Tombol CTA Kedua (Kanan)</label>
                    <input type="text" name="hero_btn_jadwal" value="<?= adminEsc(getKonten('hero_btn_jadwal', 'Lihat Jadwal')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <!-- 2. BAGIAN LAYANAN DI BERANDA -->
                <div id="layanan_beranda" style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:14px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>🏷️</span> 2. Section Layanan &amp; Quick Menu di Beranda
                    </h3>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Label Kecil Section</label>
                    <input type="text" name="layanan_section_label" value="<?= adminEsc(getKonten('layanan_section_label', 'LAYANAN')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Utama Section Layanan Beranda</label>
                    <input type="text" name="layanan_section_judul" value="<?= adminEsc(getKonten('layanan_section_judul', 'Informasi Layanan Klinik')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul / Deskripsi Section Layanan</label>
                    <textarea name="layanan_section_subjudul" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('layanan_section_subjudul', 'Temukan informasi mengenai layanan kesehatan yang tersedia di Klinik Pratama Rumah Hanania.')) ?></textarea>
                </div>

                <!-- 6 KARTU LAYANAN BERANDA -->
                <div style="grid-column:1/-1;margin-top:6px;">
                    <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:10px;">
                        🏥 6 Kartu Layanan di Halaman Depan Beranda
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
                        <?php 
                        $layananItems = [
                            1 => ['def_nama' => 'Pelayanan Umum', 'def_icon' => '🏥', 'def_desc' => 'Informasi mengenai pelayanan kesehatan umum yang tersedia di klinik.'],
                            2 => ['def_nama' => 'Pelayanan Gigi', 'def_icon' => '🦷', 'def_desc' => 'Informasi mengenai pelayanan dan pemeriksaan kesehatan gigi.'],
                            3 => ['def_nama' => 'Pelayanan Kebidanan', 'def_icon' => '🤱🏻', 'def_desc' => 'Informasi mengenai pelayanan kebidanan dan ANC yang tersedia di klinik.'],
                            4 => ['def_nama' => 'Pasien BPJS', 'def_icon' => '💳', 'def_desc' => 'Klinik melayani pasien BPJS sesuai ketentuan dan prosedur pelayanan yang berlaku.'],
                            5 => ['def_nama' => 'Persalinan 24 Jam', 'def_icon' => '🤰', 'def_desc' => 'Layanan persalinan tersedia 24 jam. Hubungi klinik terlebih dahulu untuk informasi dan penanganan.'],
                            6 => ['def_nama' => 'USG Kehamilan 2D / 4D', 'def_icon' => '👶', 'def_desc' => 'Pemeriksaan USG kandungan spesialis dengan hasil cetak foto dan video rekaman.']
                        ];
                        foreach ($layananItems as $i => $item):
                        ?>
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                    <span style="font-size:12px;font-weight:800;color:var(--hanania-primary);">Kartu #<?= $i ?></span>
                                    <input type="text" name="layanan_item_<?= $i ?>_icon" value="<?= adminEsc(getKonten("layanan_item_{$i}_icon", $item['def_icon'])) ?>" class="filter-input" style="width:48px;font-size:16px;text-align:center;padding:4px 6px;" title="Icon / Emoji">
                                </div>
                                <div style="margin-bottom:8px;">
                                    <input type="text" name="layanan_item_<?= $i ?>_nama" value="<?= adminEsc(getKonten("layanan_item_{$i}_nama", $item['def_nama'])) ?>" class="filter-input" style="width:100%;font-size:13px;font-weight:700;" placeholder="Nama Layanan">
                                </div>
                                <div>
                                    <textarea name="layanan_item_<?= $i ?>_deskripsi" rows="2" class="filter-input" style="width:100%;font-size:12px;" placeholder="Deskripsi Singkat"><?= adminEsc(getKonten("layanan_item_{$i}_deskripsi", $item['def_desc'])) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 5 MENU CEPAT BERANDA -->
                <div style="grid-column:1/-1;margin-top:10px;">
                    <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:10px;">
                        ⚡ 5 Menu Cepat (Quick Menu Bar di Bawah Hero Beranda)
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:12px;">
                        <?php 
                        $quickItems = [
                            1 => ['def_nama' => 'Pendaftaran Online', 'def_desc' => 'Akses pendaftaran pasien dengan lebih praktis.'],
                            2 => ['def_nama' => 'Nomor Antrean', 'def_desc' => 'Lihat informasi pendaftaran dan nomor antrean.'],
                            3 => ['def_nama' => 'Jadwal Pelayanan', 'def_desc' => 'Lihat jadwal dokter dan bidan yang tersedia.'],
                            4 => ['def_nama' => 'Informasi Layanan', 'def_desc' => 'Lihat layanan umum, gigi, kebidanan, BPJS, dan persalinan.'],
                            5 => ['def_nama' => 'Poin Reward', 'def_desc' => 'Lihat dan pantau poin reward pendaftaran Anda.']
                        ];
                        foreach ($quickItems as $qId => $qVal):
                        ?>
                            <div style="background:#FFF;padding:12px;border-radius:10px;border:1px solid #EAE0F5;">
                                <label style="font-size:11px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Item #<?= $qId ?></label>
                                <input type="text" name="quick_item_<?= $qId ?>_nama" value="<?= adminEsc(getKonten("quick_item_{$qId}_nama", $qVal['def_nama'])) ?>" class="filter-input" style="width:100%;font-size:12px;font-weight:700;margin-bottom:6px;">
                                <input type="text" name="quick_item_<?= $qId ?>_deskripsi" value="<?= adminEsc(getKonten("quick_item_{$qId}_deskripsi", $qVal['def_desc'])) ?>" class="filter-input" style="width:100%;font-size:11.5px;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3. BAGIAN TENTANG KAMI DI BERANDA -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:14px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>🏢</span> 3. Bagian Tentang Kami di Beranda
                    </h3>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Section Tentang Kami</label>
                    <input type="text" name="tentang_judul" value="<?= adminEsc(getKonten('tentang_judul', 'Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Paragraf 1 Tentang Kami</label>
                    <textarea name="tentang_paragraf_1" rows="3" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('tentang_paragraf_1', 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan yang memberikan pelayanan kesehatan kepada masyarakat.')) ?></textarea>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Paragraf 2 Tentang Kami</label>
                    <textarea name="tentang_paragraf_2" rows="3" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('tentang_paragraf_2', 'Website ini membantu pasien memperoleh informasi klinik, melihat jadwal pelayanan, serta melakukan pendaftaran pasien secara online.')) ?></textarea>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Poin Keunggulan 1</label>
                    <input type="text" name="tentang_poin_1" value="<?= adminEsc(getKonten('tentang_poin_1', 'Pelayanan Ramah, Santun & Nyaman')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Poin Keunggulan 2</label>
                    <input type="text" name="tentang_poin_2" value="<?= adminEsc(getKonten('tentang_poin_2', 'Tenaga Medis Profesional & Berpengalaman')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Poin Keunggulan 3</label>
                    <input type="text" name="tentang_poin_3" value="<?= adminEsc(getKonten('tentang_poin_3', 'Fasilitas Ruang Periksa Steril & Lengkap')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Poin Keunggulan 4</label>
                    <input type="text" name="tentang_poin_4" value="<?= adminEsc(getKonten('tentang_poin_4', 'Pendaftaran Digital & Sistem Antrean Terpadu')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Klinik pada Section Tentang Kami</label>
                    <?php $fotoTtg = getKonten('tentang_foto', 'assets/img/klinik3.jpg'); ?>
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
                        <img src="../<?= adminEsc(ltrim($fotoTtg, '/')) ?>" alt="Foto Tentang Kami" style="width:120px;height:75px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);" onerror="this.src='../assets/img/klinik3.jpg'">
                        <input type="file" name="tentang_foto" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;">
                    </div>
                    <small style="font-size:11px;color:var(--hanania-muted);">Unggah foto baru jika ingin mengganti foto klinik di section Tentang Kami.</small>
                </div>

                <div style="grid-column:1/-1;margin-top:14px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn" style="padding:12px 32px;font-size:14px;font-weight:800;">
                        💾 Simpan Semua Konten Beranda
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 1. SECTION: HALAMAN LAYANAN                              -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'layanan'): ?>
        <section class="admin-card">
            <div class="admin-card-head">
                <div class="admin-card-title">
                    <span>🏷️ Kelola Halaman Layanan &amp; Biaya (layanan/index.php)</span>
                </div>
                <div class="admin-card-subtitle">
                    Atur teks banner hero, judul cakupan layanan, edukasi prosedur BPJS, serta tinjau paket &amp; tarif pemeriksaan.
                </div>
            </div>

            <form method="POST" action="konten.php?section=layanan" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <input type="hidden" name="action" value="simpan_halaman_layanan">

                <!-- 1. HERO BANNER HALAMAN LAYANAN -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>✨</span> 1. Banner Hero Halaman Layanan
                    </h3>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Badge Hero Atas</label>
                    <input type="text" name="layanan_hero_badge" value="<?= adminEsc(getKonten('layanan_hero_badge', '🩺 Pelayanan Medis Lengkap & Terjangkau')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Utama Halaman Layanan</label>
                    <input type="text" name="layanan_hero_judul" value="<?= adminEsc(getKonten('layanan_hero_judul', 'Layanan & Biaya Klinik')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Cakupan Layanan</label>
                    <input type="text" name="layanan_cakupan_judul" value="<?= adminEsc(getKonten('layanan_cakupan_judul', 'Cakupan Layanan Klinik')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul Hero Halaman Layanan</label>
                    <textarea name="layanan_hero_subjudul" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('layanan_hero_subjudul', 'Informasi resmi mengenai cakupan pelayanan kesehatan, paket pemeriksaan kehamilan, USG, tarif konsultasi, dan prosedur kepesertaan BPJS di Klinik Pratama Rumah Hanania.')) ?></textarea>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul Cakupan Layanan</label>
                    <input type="text" name="layanan_cakupan_subjudul" value="<?= adminEsc(getKonten('layanan_cakupan_subjudul', 'Pilihan layanan kesehatan menyeluruh untuk Anda dan seluruh anggota keluarga.')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <!-- 1.B 7 KARTU CAKUPAN LAYANAN -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:14px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>🏥</span> 2. 7 Kartu Cakupan &amp; Ikhtisar Layanan di Halaman Layanan
                    </h3>
                </div>

                <div style="grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:14px;">
                    <?php
                    $layananCardsDefault = [
                        1 => ['icon' => '🩺', 'judul' => 'Pelayanan Umum', 'desc' => 'Pemeriksaan kesehatan umum, diagnosis medis, penanganan keluhan dasar, dan konsultasi kesehatan berkala.'],
                        2 => ['icon' => '🦷', 'judul' => 'Pelayanan Gigi & Mulut', 'desc' => 'Pemeriksaan kesehatan gigi, penambalan, pencabutan, pembersihan karang gigi (scaling), dan perawatan gusi.'],
                        3 => ['icon' => '🤱', 'judul' => 'Pelayanan Kebidanan (KIA)', 'desc' => 'Pemeriksaan kehamilan rutin, pemantauan detak jantung janin, konseling masa nifas, dan program Keluarga Berencana (KB).'],
                        4 => ['icon' => '🔬', 'judul' => 'Spesialis Kebidanan (Sp.OG)', 'desc' => 'Konsultasi spesialis obstetri & ginekologi, pemeriksaan USG kehamilan mendalam, serta evaluasi perkembangan janin.'],
                        5 => ['icon' => '👶', 'judul' => 'Persalinan 24 Jam', 'desc' => 'Pertolongan persalinan normal didampingi bidan bersertifikat dalam ruangan bersalin yang steril dan ramah ibu melahirkan.'],
                        6 => ['icon' => '💳', 'judul' => 'Pasien BPJS Kesehatan', 'desc' => 'Melayani peserta BPJS Kesehatan dengan kartu/identitas aktif sesuai ketentuan faskes tingkat pertama (FKTP).'],
                        7 => ['icon' => '📲', 'judul' => 'Pendaftaran Online', 'desc' => 'Kemudahan memilih dokter/bidan, melihat jadwal praktik, serta mengambil nomor antrean secara online sebelum datang ke klinik.']
                    ];
                    foreach ($layananCardsDefault as $cNum => $cDef):
                    ?>
                        <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                <span style="font-size:12px;font-weight:800;color:var(--hanania-primary);">Kartu #<?= $cNum ?></span>
                                <input type="text" name="layanan_card_<?= $cNum ?>_icon" value="<?= adminEsc(getKonten("layanan_card_{$cNum}_icon", $cDef['icon'])) ?>" class="filter-input" style="width:48px;font-size:16px;text-align:center;padding:4px 6px;" title="Icon / Emoji">
                            </div>
                            <div style="margin-bottom:8px;">
                                <input type="text" name="layanan_card_<?= $cNum ?>_judul" value="<?= adminEsc(getKonten("layanan_card_{$cNum}_judul", $cDef['judul'])) ?>" class="filter-input" style="width:100%;font-size:13px;font-weight:700;" placeholder="Judul Layanan">
                            </div>
                            <div>
                                <textarea name="layanan_card_<?= $cNum ?>_desc" rows="2" class="filter-input" style="width:100%;font-size:12px;" placeholder="Deskripsi Singkat"><?= adminEsc(getKonten("layanan_card_{$cNum}_desc", $cDef['desc'])) ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 3. INFORMASI PROSEDUR BPJS KESEHATAN -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:14px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>💳</span> 3. Informasi &amp; Prosedur BPJS Kesehatan di Halaman Layanan
                    </h3>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Box BPJS</label>
                    <input type="text" name="layanan_bpjs_judul" value="<?= adminEsc(getKonten('layanan_bpjs_judul', 'Pelayanan Peserta BPJS Kesehatan')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Deskripsi Prosedur BPJS</label>
                    <textarea name="layanan_bpjs_deskripsi" rows="3" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('layanan_bpjs_deskripsi', 'Klinik Pratama Rumah Hanania merupakan Fasilitas Kesehatan Tingkat Pertama (FKTP) resmi yang bekerja sama dengan BPJS Kesehatan. Pendaftaran dapat dilakukan langsung melalui aplikasi Mobile JKN kemudian memasukkan nomor antrean JKN pada portal pendaftaran kami.')) ?></textarea>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Catatan Syarat / Ketentuan BPJS</label>
                    <input type="text" name="layanan_bpjs_syarat" value="<?= adminEsc(getKonten('layanan_bpjs_syarat', 'Pastikan status kepesertaan BPJS aktif dan faskes tingkat pertama tercatat di Klinik Pratama Rumah Hanania.')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                    <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                        💾 Simpan Teks Halaman Layanan
                    </button>
                </div>
            </form>
        </section>

        <!-- PREVIEW PAKET LAYANAN & TARIF -->
        <section class="admin-card" style="margin-top:24px;">
            <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div class="admin-card-title">
                        <span>📋 Daftar Paket Pemeriksaan &amp; Tarif Aktif</span>
                    </div>
                    <div class="admin-card-subtitle">
                        Total <?= count($paketLayananAll) ?> paket/tindakan medis terdaftar yang tampil di kartu tarif halaman layanan.
                    </div>
                </div>
                <a href="tarif.php" class="btn btn-sm" style="display:inline-flex;align-items:center;gap:6px;font-weight:800;">
                    ⚙️ Buka Menu Kelola Tarif &amp; Layanan &rarr;
                </a>
            </div>

            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Kategori</th>
                            <th>Nama Paket / Tindakan</th>
                            <th>Tenaga Medis</th>
                            <th>Biaya Tindakan</th>
                            <th>Status BPJS</th>
                            <th>Status</th>
                            <th style="text-align:right;width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paketLayananAll)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:24px;color:var(--hanania-muted);">
                                    Belum ada paket layanan terdaftar. <a href="layanan.php">Tambah paket baru</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paketLayananAll as $idxP => $p): ?>
                                <tr>
                                    <td><?= $idxP + 1 ?></td>
                                    <td>
                                        <span class="badge" style="background:#F0E7FA;color:var(--hanania-primary);font-size:11.5px;font-weight:700;">
                                            <?= adminEsc($p['kategori']) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:700;">
                                        <a href="layanan.php?edit=<?= (int)$p['id_paket'] ?>" title="Klik untuk mengedit paket ini" style="color:var(--hanania-primary);text-decoration:underline;">
                                            <?= adminEsc($p['nama_layanan']) ?>
                                        </a>
                                    </td>
                                    <td style="font-size:12.5px;"><?= adminEsc($p['tenaga_medis']) ?></td>
                                    <td style="font-weight:800;color:#1B7A3E;">
                                        Rp <?= number_format($p['biaya_tindakan'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <?php if ($p['status_bpjs'] === 'Didukung'): ?>
                                            <span style="color:#1B7A3E;font-size:12px;font-weight:700;">✅ Didukung BPJS</span>
                                        <?php else: ?>
                                            <span style="color:#A15403;font-size:12px;font-weight:700;">Umum / Mandiri</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $p['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="layanan.php?edit=<?= (int)$p['id_paket'] ?>" class="btn btn-sm btn-light" style="padding:5px 10px;font-size:11px;font-weight:700;">
                                            ✏️ Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 2. SECTION: HALAMAN TENTANG (PROFIL, VISI MISI, SEJARAH)  -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'tentang'): ?>
        <section class="admin-card">
            <div class="admin-card-head">
                <div class="admin-card-title">
                    <span>🏢 Kelola Seluruh Halaman Menu "Tentang" &amp; Profil Faskes</span>
                </div>
                <div class="admin-card-subtitle">
                    Pilih subhalaman yang ingin Anda kelola: Profil Resmi &amp; Sertifikat, Visi &amp; Misi, Sejarah, Fasilitas &amp; Galeri, Struktur Organisasi &amp; Bagan, atau Section Tentang di Beranda.
                </div>
            </div>

            <!-- SUB-TAB SWITCHER TENTANG -->
            <div style="display:flex;gap:10px;margin-bottom:24px;border-bottom:2px solid var(--hanania-border);padding-bottom:12px;flex-wrap:wrap;">
                <a href="konten.php?section=tentang&tab=profil" class="content-nav-btn <?= $subTabTentang === 'profil' ? 'active' : '' ?>">
                    🏥 1. Profil Resmi &amp; Akreditasi
                </a>
                <a href="konten.php?section=tentang&tab=visi_misi" class="content-nav-btn <?= $subTabTentang === 'visi_misi' ? 'active' : '' ?>">
                    🎯 2. Visi, Misi &amp; Motto
                </a>
                <a href="konten.php?section=tentang&tab=sejarah" class="content-nav-btn <?= $subTabTentang === 'sejarah' ? 'active' : '' ?>">
                    📜 3. Sejarah Pendirian Klinik
                </a>
                <a href="konten.php?section=tentang&tab=fasilitas" class="content-nav-btn <?= $subTabTentang === 'fasilitas' ? 'active' : '' ?>">
                    🏢 4. Fasilitas &amp; Galeri Klinik
                </a>
                <a href="konten.php?section=tentang&tab=struktur" class="content-nav-btn <?= $subTabTentang === 'struktur' ? 'active' : '' ?>">
                    👥 5. Struktur Organisasi &amp; Bagan
                </a>
                <a href="konten.php?section=tentang&tab=beranda_tentang" class="content-nav-btn <?= $subTabTentang === 'beranda_tentang' ? 'active' : '' ?>">
                    🌟 6. Section Tentang di Beranda
                </a>
            </div>

            <!-- TAB 1: PROFIL KLINIK -->
            <?php if ($subTabTentang === 'profil'): ?>
                <form method="POST" action="konten.php?section=tentang&tab=profil" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <input type="hidden" name="action" value="simpan_tentang_profil">

                    <div style="grid-column:1/-1;">
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            Editor Halaman: Profil &amp; Sertifikat Akreditasi Paripurna (tentang/profil klinik.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Halaman ini memuat profil resmi faskes, bukti predikat kelulusan akreditasi Paripurna, serta legalitas standar operasional.
                        </p>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Hero Profil</label>
                        <input type="text" name="profil_klinik_hero_judul" value="<?= adminEsc(getKonten('profil_klinik_hero_judul', 'Profil & Sertifikat Akreditasi Klinik')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Semboyan / Motto Resmi</label>
                        <input type="text" name="profil_klinik_motto" value="<?= adminEsc(getKonten('profil_klinik_motto', 'Melayani dengan Hati, Menebar Kebaikan untuk Semua')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul Hero Profil</label>
                        <textarea name="profil_klinik_hero_subjudul" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('profil_klinik_hero_subjudul', 'Identitas resmi faskes, bukti predikat kelulusan akreditasi Paripurna, serta legalitas standar operasional Klinik Pratama Rumah Hanania.')) ?></textarea>
                    </div>

                    <!-- FOTO PROFIL GEDUNG / KLINIK -->
                    <div style="grid-column:1/-1;background:#FAF8FD;padding:16px;border-radius:12px;border:1px solid #EAE0F5;">
                        <label style="font-size:12.5px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:8px;">
                            🏢 Foto Profil Gedung / Tampak Depan Klinik
                        </label>
                        <?php $fProfil = getKonten('profil_klinik_foto', ''); ?>
                        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                            <?php if ($fProfil && file_exists(__DIR__ . '/../' . ltrim($fProfil, '/'))): ?>
                                <div>
                                    <img src="../<?= adminEsc(ltrim($fProfil, '/')) ?>" alt="Foto Profil Klinik" style="width:140px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                        <input type="checkbox" name="hapus_profil_foto" value="1">
                                        🗑️ Hapus Foto Profil Gedung Ini
                                    </label>
                                </div>
                            <?php else: ?>
                                <div style="font-size:12px;color:var(--hanania-muted);background:#fff;padding:8px 14px;border-radius:8px;border:1px dashed var(--hanania-border);">
                                    Belum ada foto profil kustom (menggunakan tampilan visual klinik bawaan).
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:240px;">
                                <input type="file" name="profil_klinik_foto" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;width:100%;margin-bottom:4px;">
                                <small style="font-size:11px;color:var(--hanania-muted);">Unggah foto gedung baru (JPG, PNG, WEBP, maks 6MB).</small>
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Teks Narasi Lengkap Profil Klinik</label>
                        <textarea name="profil_klinik_teks_tentang" rows="5" class="filter-input" style="width:100%;font-size:13px;line-height:1.6;"><?= adminEsc(getKonten('profil_klinik_teks_tentang', getKonten('profil_klinik_teks', 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan swasta yang berada di bawah naungan PT. Rumah Hanania Sejahtera. Klinik ini berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor, didirikan oleh Dr. Fajar Delli Wihartiko, M.M., M.Kom. bersama Gita Marissa, S.Tr.Keb., Bdn.'))) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:4px;">
                        <h5 style="font-size:13.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:10px;">📋 Informasi Legalitas, Pendiri &amp; Operasional Faskes</h5>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Badan Usaha / Pengelola</label>
                        <input type="text" name="profil_klinik_badan_usaha" value="<?= adminEsc(getKonten('profil_klinik_badan_usaha', 'PT. Rumah Hanania Sejahtera')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Pendiri Faskes</label>
                        <input type="text" name="profil_klinik_pendiri" value="<?= adminEsc(getKonten('profil_klinik_pendiri', 'Dr. Fajar Delli Wihartiko, M.M., M.Kom. & Gita Marissa, S.Tr.Keb., Bdn.')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Jenis Fasilitas Kesehatan</label>
                        <input type="text" name="profil_klinik_jenis_faskes" value="<?= adminEsc(getKonten('profil_klinik_jenis_faskes', 'Klinik Pratama Rawat Jalan (Dengan Fasilitas Persalinan 24 Jam)')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Jam Operasional</label>
                        <input type="text" name="profil_klinik_jam_operasional" value="<?= adminEsc(getKonten('profil_klinik_jam_operasional', 'Poli: 08.00 – 20.00 WIB • Persalinan & Darurat: 24 Jam Penuh')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Lokasi Operasional / Alamat Lengkap</label>
                        <input type="text" name="profil_klinik_alamat" value="<?= adminEsc(getKonten('profil_klinik_alamat', 'Jl. Desa Sukadamai, Kp. Situpete RT 03 / RW 08, Kel. Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <!-- KONTAK RESMI -->
                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">WhatsApp Layanan 1</label>
                        <input type="text" name="profil_klinik_wa_1" value="<?= adminEsc(getKonten('profil_klinik_wa_1', '0885 1070 3839')) ?>" class="filter-input" style="width:100%;font-size:13px;" placeholder="0885 1070 3839">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">WhatsApp Layanan 2</label>
                        <input type="text" name="profil_klinik_wa_2" value="<?= adminEsc(getKonten('profil_klinik_wa_2', '0857 1417 8178')) ?>" class="filter-input" style="width:100%;font-size:13px;" placeholder="0857 1417 8178">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Akun Instagram Resmi</label>
                        <input type="text" name="profil_klinik_ig" value="<?= adminEsc(getKonten('profil_klinik_ig', '@rumahhanania_')) ?>" class="filter-input" style="width:100%;font-size:13px;" placeholder="@rumahhanania_">
                    </div>

                    <!-- AKREDITASI -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:4px;">
                        <h5 style="font-size:13.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:10px;">🏆 Sertifikat Akreditasi Paripurna</h5>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nomor Sertifikat Akreditasi</label>
                        <input type="text" name="profil_klinik_nomor_akreditasi" value="<?= adminEsc(getKonten('profil_klinik_nomor_akreditasi', 'YM.02.01/B/674/2026')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Masa Berlaku Akreditasi</label>
                        <input type="text" name="profil_klinik_masa_akreditasi" value="<?= adminEsc(getKonten('profil_klinik_masa_akreditasi', '2026 s.d 2031 (5 Tahun)')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Lembaga Akreditasi</label>
                        <input type="text" name="profil_klinik_lembaga_akreditasi" value="<?= adminEsc(getKonten('profil_klinik_lembaga_akreditasi', 'Lafkespri')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Regulator / Pengesah</label>
                        <input type="text" name="profil_klinik_regulator" value="<?= adminEsc(getKonten('profil_klinik_regulator', 'Kementerian Kesehatan RI')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Sertifikat Akreditasi Paripurna</label>
                        <?php $fAkred = getKonten('profil_klinik_foto_akreditasi', 'assets/img/sertifikat_akreditasi_paripurna.jpg'); ?>
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <?php if ($fAkred && file_exists(__DIR__ . '/../' . ltrim($fAkred, '/'))): ?>
                                <div>
                                    <img src="../<?= adminEsc(ltrim($fAkred, '/')) ?>" alt="Sertifikat Akreditasi" style="width:130px;height:85px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                        <input type="checkbox" name="hapus_akreditasi_foto" value="1">
                                        🗑️ Hapus Foto Sertifikat Akreditasi
                                    </label>
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:240px;">
                                <input type="file" name="profil_klinik_foto_akreditasi" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;width:100%;margin-bottom:4px;">
                                <small style="font-size:11px;color:var(--hanania-muted);">Unggah file gambar sertifikat baru jika ada pembaruan.</small>
                            </div>
                        </div>
                    </div>

                    <!-- LEGALITAS & IZIN USAHA -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:4px;">
                        <h5 style="font-size:13.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:10px;">📄 Sertifikat Standar &amp; Legalitas Operasional</h5>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nomor Sertifikat Standar</label>
                        <input type="text" name="profil_klinik_legalitas_nomor" value="<?= adminEsc(getKonten('profil_klinik_legalitas_nomor', '28102100123850003')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Kode KBLI</label>
                        <input type="text" name="profil_klinik_legalitas_kbli" value="<?= adminEsc(getKonten('profil_klinik_legalitas_kbli', '86105 (Aktivitas Klinik Swasta)')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Perizinan</label>
                        <input type="text" name="profil_klinik_legalitas_status" value="<?= adminEsc(getKonten('profil_klinik_legalitas_status', 'Perizinan Baru (Memenuhi Standar)')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nama Pelaku Usaha / Pemegang Izin</label>
                        <input type="text" name="profil_klinik_legalitas_pelaku" value="<?= adminEsc(getKonten('profil_klinik_legalitas_pelaku', 'PERORANGAN GITA MARISSA')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nama Fasilitas Kesehatan pada Izin</label>
                        <input type="text" name="profil_klinik_legalitas_faskes" value="<?= adminEsc(getKonten('profil_klinik_legalitas_faskes', 'Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Kemampuan Pelayanan</label>
                        <input type="text" name="profil_klinik_legalitas_kemampuan" value="<?= adminEsc(getKonten('profil_klinik_legalitas_kemampuan', 'Klinik Pratama')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Penyelenggaraan</label>
                        <input type="text" name="profil_klinik_legalitas_penyelenggaraan" value="<?= adminEsc(getKonten('profil_klinik_legalitas_penyelenggaraan', 'Rawat Jalan')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Kesesuaian Standar</label>
                        <input type="text" name="profil_klinik_legalitas_kesesuaian" value="<?= adminEsc(getKonten('profil_klinik_legalitas_kesesuaian', 'Telah Memenuhi Syarat Usaha')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Dokumen Izin / Sertifikat Standar Legalitas</label>
                        <?php $fLeg = getKonten('profil_klinik_foto_legalitas', 'assets/img/pdf_pages/page_4.png'); ?>
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <?php if ($fLeg && file_exists(__DIR__ . '/../' . ltrim($fLeg, '/'))): ?>
                                <div>
                                    <img src="../<?= adminEsc(ltrim($fLeg, '/')) ?>" alt="Dokumen Legalitas" style="width:130px;height:85px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                        <input type="checkbox" name="hapus_legalitas_foto" value="1">
                                        🗑️ Hapus Foto Dokumen Legalitas
                                    </label>
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:240px;">
                                <input type="file" name="profil_klinik_foto_legalitas" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;width:100%;margin-bottom:4px;">
                                <small style="font-size:11px;color:var(--hanania-muted);">Unggah dokumen legalitas izin berusaha baru jika ada perubahan.</small>
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                            💾 Simpan Profil Klinik
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- TAB 2: VISI & MISI -->
            <?php if ($subTabTentang === 'visi_misi'): ?>
                <form method="POST" action="konten.php?section=tentang&tab=visi_misi" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <input type="hidden" name="action" value="simpan_tentang_visi">

                    <div style="grid-column:1/-1;">
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            Editor Halaman: Visi, Misi &amp; Motto Pelayanan (tentang/visi_misi.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Kelola pernyataan visi utama, 4 pilar misi pelayanan kesehatan, serta semboyan resmi klinik.
                        </p>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Hero Halaman Visi Misi</label>
                        <input type="text" name="visi_misi_hero_judul" value="<?= adminEsc(getKonten('visi_misi_hero_judul', 'Visi, Misi & Motto')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Motto Resmi Klinik</label>
                        <input type="text" name="visi_misi_motto" value="<?= adminEsc(getKonten('visi_misi_motto', 'Melayani dengan Senang Hati, Menebar Keberkahan untuk Semua')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul Hero Halaman</label>
                        <textarea name="visi_misi_hero_subjudul" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('visi_misi_hero_subjudul', 'Komitmen resmi Klinik Pratama Rumah Hanania dalam memberikan pelayanan kesehatan terbaik, bermutu tinggi, dan berorientasi pada kemaslahatan masyarakat.')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Pernyataan Visi Klinik</label>
                        <textarea name="visi_misi_visi" rows="3" class="filter-input" style="width:100%;font-size:13.5px;font-weight:700;color:var(--hanania-primary);"><?= adminEsc(getKonten('visi_misi_visi', 'Menjadi Klinik Pratama yang unggul, terpercaya, dan dalam memberikan pelayanan kesehatan umum, gigi, kebidanan yang bermutu, aman, serta berorientasi pada kebutuhan masyarakat.')) ?></textarea>
                    </div>

                    <!-- 4 MISI ITEMS -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:6px;">
                        <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:12px;">
                            🚀 4 Butir Misi Pelayanan
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <!-- MISI 1 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Misi #01: Judul</label>
                                <input type="text" name="visi_misi_misi_1_judul" value="<?= adminEsc(getKonten('visi_misi_misi_1_judul', 'Standar Pelayanan Medis Terjangkau')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Misi</label>
                                <textarea name="visi_misi_misi_1_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_misi_1_teks', 'Memberikan pelayanan kesehatan umum, Kesehatan gigi dan kebidanan yang profesional, dan sesuai standar pelayanan medis dengan harga yang terjangkau.')) ?></textarea>
                            </div>

                            <!-- MISI 2 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Misi #02: Judul</label>
                                <input type="text" name="visi_misi_misi_2_judul" value="<?= adminEsc(getKonten('visi_misi_misi_2_judul', 'Promotif, Preventif & Kuratif')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Misi</label>
                                <textarea name="visi_misi_misi_2_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_misi_2_teks', 'Menyelenggarakan pelayanan Kesehatan umum, kesehatan gigi dan mulut, serta kebidanan yang bermutu melalui tindakan promotif, preventif, dan kuratif.')) ?></textarea>
                            </div>

                            <!-- MISI 3 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Misi #03: Judul</label>
                                <input type="text" name="visi_misi_misi_3_judul" value="<?= adminEsc(getKonten('visi_misi_misi_3_judul', 'Aman, Ramah & Mengutamakan Pasien')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Misi</label>
                                <textarea name="visi_misi_misi_3_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_misi_3_teks', 'Memberikan pelayanan yang aman dan ramah, serta mengutamakan keselamatan, kenyamanan, dan kepuasan pasien dalam setiap pelayanan.')) ?></textarea>
                            </div>

                            <!-- MISI 4 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Misi #04: Judul</label>
                                <input type="text" name="visi_misi_misi_4_judul" value="<?= adminEsc(getKonten('visi_misi_misi_4_judul', 'Dukungan Program Kesehatan Pemerintah')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Misi</label>
                                <textarea name="visi_misi_misi_4_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_misi_4_teks', 'Mendukung program pemerintah di bidang kesehatan ibu dan anak serta kesehatan Masyarakat.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 4 TATA NILAI UTAMA (P-A-R-T) -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:6px;">
                        <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:12px;">
                            💎 4 Tata Nilai Utama Klinik (P - A - R - T)
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <!-- NILAI 1: PROFESIONAL -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Nilai #1: Judul / Kata Kunci</label>
                                <input type="text" name="visi_misi_nilai_1_judul" value="<?= adminEsc(getKonten('visi_misi_nilai_1_judul', 'PROFESIONAL')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Makna &amp; Penerapan Nilai</label>
                                <textarea name="visi_misi_nilai_1_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_nilai_1_teks', 'Bekerja dengan keahlian, integritas, dan mematuhi etika profesi kedokteran dan kebidanan secara disiplin.')) ?></textarea>
                            </div>

                            <!-- NILAI 2: AMAN -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Nilai #2: Judul / Kata Kunci</label>
                                <input type="text" name="visi_misi_nilai_2_judul" value="<?= adminEsc(getKonten('visi_misi_nilai_2_judul', 'AMAN')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Makna &amp; Penerapan Nilai</label>
                                <textarea name="visi_misi_nilai_2_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_nilai_2_teks', 'Mengedepankan keselamatan pasien (patient safety), higienitas lingkungan, dan prosedur klinis yang terstandar.')) ?></textarea>
                            </div>

                            <!-- NILAI 3: RAMAH -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Nilai #3: Judul / Kata Kunci</label>
                                <input type="text" name="visi_misi_nilai_3_judul" value="<?= adminEsc(getKonten('visi_misi_nilai_3_judul', 'RAMAH')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Makna &amp; Penerapan Nilai</label>
                                <textarea name="visi_misi_nilai_3_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_nilai_3_teks', 'Memberikan pelayanan dengan senyum, salam, sapa, sopan, dan empati sepenuh hati kepada setiap pasien.')) ?></textarea>
                            </div>

                            <!-- NILAI 4: TERPERCAYA -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <label style="font-size:12px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:4px;">Nilai #4: Judul / Kata Kunci</label>
                                <input type="text" name="visi_misi_nilai_4_judul" value="<?= adminEsc(getKonten('visi_misi_nilai_4_judul', 'TERPERCAYA')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;margin-bottom:8px;">
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Makna &amp; Penerapan Nilai</label>
                                <textarea name="visi_misi_nilai_4_teks" rows="2" class="filter-input" style="width:100%;font-size:12px;"><?= adminEsc(getKonten('visi_misi_nilai_4_teks', 'Menjaga kerahasiaan medis, transparansi tindakan serta obat, dan menjaga amanah kepercayaan pasien.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                            💾 Simpan Visi &amp; Misi Klinik
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- TAB 3: SEJARAH KLINIK -->
            <?php if ($subTabTentang === 'sejarah'): ?>
                <form method="POST" action="konten.php?section=tentang&tab=sejarah" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <input type="hidden" name="action" value="simpan_tentang_sejarah">

                    <div style="grid-column:1/-1;">
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            Editor Halaman: Sejarah &amp; Perjalanan Klinik (tentang/sejarah klinik.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Kelola narasi kisah pendirian, rekam jejak linimasa perjalanan klinik dari tahun ke tahun, dan foto arsip/piagam sejarah.
                        </p>
                    </div>

                    <!-- FOTO UTAMA KISAH SEJARAH -->
                    <div style="grid-column:1/-1;background:#FAF8FD;padding:16px;border-radius:14px;border:1px solid #EAE0F5;">
                        <label style="font-size:12.5px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:6px;">
                            📸 Foto Utama Kisah Sejarah (Awal Berdiri &amp; Pendiri Klinik)
                        </label>
                        <?php $fUtamaSj = getKonten('sejarah_foto_utama', 'assets/img/klinik1.jpg'); ?>
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <?php if ($fUtamaSj && file_exists(__DIR__ . '/../' . ltrim($fUtamaSj, '/'))): ?>
                                <div>
                                    <img src="../<?= adminEsc(ltrim($fUtamaSj, '/')) ?>" alt="Foto Utama Sejarah" style="width:140px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                        <input type="checkbox" name="hapus_sejarah_foto_utama" value="1">
                                        🗑️ Hapus Foto Ini
                                    </label>
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:240px;">
                                <input type="file" name="sejarah_foto_utama" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;width:100%;margin-bottom:4px;">
                                <small style="font-size:11px;color:var(--hanania-muted);">Foto ini ditampilkan di kartu artikel "Awal Berdiri &amp; Pendiri Klinik" pada halaman sejarah.</small>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Hero Sejarah</label>
                        <input type="text" name="sejarah_hero_judul" value="<?= adminEsc(getKonten('sejarah_hero_judul', 'Sejarah Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul Hero Sejarah</label>
                        <input type="text" name="sejarah_hero_subjudul" value="<?= adminEsc(getKonten('sejarah_hero_subjudul', 'Perjalanan dedikasi pelayanan kesehatan bermutu dan berorientasi keluarga di Kota Bogor sejak tahun 2014.')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Narasi Kisah Awal Pendirian Klinik (Paragraf Pembuka)</label>
                        <textarea name="sejarah_kisah_awal" rows="4" class="filter-input" style="width:100%;font-size:13px;line-height:1.6;"><?= adminEsc(getKonten('sejarah_kisah_awal', 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan swasta yang didirikan oleh PT. Rumah Hanania Sejahtera dan berlokasi di Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor. Klinik ini didirikan oleh Dr. Fajar Delli Wihartiko, M.M., M.Kom. bersama Gita Marissa, S.Tr.Keb., Bdn.')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Kutipan Makna Nama &amp; Doa Pendirian</label>
                        <textarea name="sejarah_quote" rows="3" class="filter-input" style="width:100%;font-size:13px;line-height:1.6;font-style:italic;color:var(--hanania-primary);"><?= adminEsc(getKonten('sejarah_quote', 'Klinik ini didirikan dengan harapan dan doa tulus agar seluruh insan yang terlibat—baik pasien, keluarga, tenaga medis, maupun seluruh tim pendukung—senantiasa berada dalam lindungan dan kasih sayang Allah SWT.')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Komitmen Pelayanan Medis Awal (Paragraf Lanjutan 1)</label>
                        <textarea name="sejarah_komitmen" rows="3" class="filter-input" style="width:100%;font-size:13px;line-height:1.6;"><?= adminEsc(getKonten('sejarah_komitmen', 'Awal perjalanan Klinik Pratama Rumah Hanania berangkat dari komitmen kuat untuk menyediakan pelayanan kebidanan, kesehatan umum, serta kesehatan gigi dan mulut yang bermutu, aman, serta terjangkau bagi seluruh lapisan masyarakat.')) ?></textarea>
                        <small style="font-size:11px;color:var(--hanania-muted);display:block;margin-top:4px;">Uraian komitmen awal klinik melayani kebidanan, umum, serta gigi dan mulut bagi seluruh lapisan masyarakat.</small>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Fokus Layanan Terpadu &amp; Kepuasan Pasien (Paragraf Lanjutan 2)</label>
                        <textarea name="sejarah_fokus" rows="3" class="filter-input" style="width:100%;font-size:13px;line-height:1.6;"><?= adminEsc(getKonten('sejarah_fokus', 'Sejak awal berdiri, klinik berfokus pada peningkatan derajat kesehatan masyarakat melalui pelayanan promotif, preventif, kuratif, dan rehabilitatif secara terpadu, menghadirkan pelayanan yang ramah bagi ibu, anak, dan keluarga dengan pendekatan yang humanis dan profesional, serta mengutamakan kepuasan pasien melalui pelayanan yang cepat, tepat, dan berorientasi pada kebutuhan pasien.')) ?></textarea>
                        <small style="font-size:11px;color:var(--hanania-muted);display:block;margin-top:4px;">Uraian fokus pelayanan promotif, preventif, kuratif, dan rehabilitatif dengan pendekatan humanis &amp; ramah keluarga.</small>
                    </div>

                    <!-- 4 LINIMASA PERJALANAN (TIMELINE) -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:6px;">
                        <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:12px;">
                            📅 4 Tonggak Linimasa Perjalanan Klinik (Milestones)
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <!-- TIMELINE 1 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;margin-bottom:8px;">
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Tahun</label>
                                        <input type="text" name="sejarah_timeline_1_tahun" value="<?= adminEsc(getKonten('sejarah_timeline_1_tahun', '2014')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:800;text-align:center;">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Judul Fase #1</label>
                                        <input type="text" name="sejarah_timeline_1_judul" value="<?= adminEsc(getKonten('sejarah_timeline_1_judul', 'Bidan Praktik Mandiri Gita Marissa')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;">
                                    </div>
                                </div>
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Peristiwa</label>
                                <textarea name="sejarah_timeline_1_teks" rows="3" class="filter-input" style="width:100%;font-size:12px;line-height:1.5;"><?= adminEsc(getKonten('sejarah_timeline_1_teks', 'Dimulainya pelayanan kebidanan mandiri oleh Bidan Gita Marissa dengan pendekatan personal dan kekeluargaan untuk melayani pemeriksaan ibu hamil dan pertolongan persalinan masyarakat sekitar Sukadamai, Tanah Sareal.')) ?></textarea>
                            </div>

                            <!-- TIMELINE 2 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;margin-bottom:8px;">
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Tahun</label>
                                        <input type="text" name="sejarah_timeline_2_tahun" value="<?= adminEsc(getKonten('sejarah_timeline_2_tahun', '2018')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:800;text-align:center;">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Judul Fase #2</label>
                                        <input type="text" name="sejarah_timeline_2_judul" value="<?= adminEsc(getKonten('sejarah_timeline_2_judul', 'Praktik Bersama Dokter Umum - Bidan')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;">
                                    </div>
                                </div>
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Peristiwa</label>
                                <textarea name="sejarah_timeline_2_teks" rows="3" class="filter-input" style="width:100%;font-size:12px;line-height:1.5;"><?= adminEsc(getKonten('sejarah_timeline_2_teks', 'Pengembangan layanan kesehatan melalui kolaborasi praktik bersama antara dokter umum dan bidan guna memperluas cakupan diagnosis medis, pengobatan penyakit umum keluarga, dan tindakan preventif.')) ?></textarea>
                            </div>

                            <!-- TIMELINE 3 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;margin-bottom:8px;">
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Tahun</label>
                                        <input type="text" name="sejarah_timeline_3_tahun" value="<?= adminEsc(getKonten('sejarah_timeline_3_tahun', '2023')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:800;text-align:center;">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Judul Fase #3</label>
                                        <input type="text" name="sejarah_timeline_3_judul" value="<?= adminEsc(getKonten('sejarah_timeline_3_judul', 'Praktik Bersama Dokter Umum, Dokter Gigi dan Bidan')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;">
                                    </div>
                                </div>
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Peristiwa</label>
                                <textarea name="sejarah_timeline_3_teks" rows="3" class="filter-input" style="width:100%;font-size:12px;line-height:1.5;"><?= adminEsc(getKonten('sejarah_timeline_3_teks', 'Layanan semakin terpadu dengan penambahan unit Pelayanan Kesehatan Gigi & Mulut, fasilitas USG kehamilan, serta kelengkapan sarana medis untuk memenuhi kebutuhan kesehatan komprehensif keluarga.')) ?></textarea>
                            </div>

                            <!-- TIMELINE 4 -->
                            <div style="background:#FAF8FD;padding:14px;border-radius:12px;border:1px solid #EAE0F5;">
                                <div style="display:grid;grid-template-columns:90px 1fr;gap:10px;margin-bottom:8px;">
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Tahun</label>
                                        <input type="text" name="sejarah_timeline_4_tahun" value="<?= adminEsc(getKonten('sejarah_timeline_4_tahun', '2026')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:800;text-align:center;color:#10B981;">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Judul Fase #4</label>
                                        <input type="text" name="sejarah_timeline_4_judul" value="<?= adminEsc(getKonten('sejarah_timeline_4_judul', 'Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:12.5px;font-weight:700;">
                                    </div>
                                </div>
                                <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Uraian Peristiwa</label>
                                <textarea name="sejarah_timeline_4_teks" rows="3" class="filter-input" style="width:100%;font-size:12px;line-height:1.5;"><?= adminEsc(getKonten('sejarah_timeline_4_teks', 'Resmi bertransformasi menjadi fasilitas kesehatan berizin klinik pratama di bawah naungan PT. Rumah Hanania Sejahtera, dilengkapi perizinan berusaha berbasis risiko pemerintah, sistem digitalisasi antrean online web & Mobile JKN, serta layanan persalinan 24 jam.')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- DOKUMENTASI FOTO SEJARAH KLINIK (TAMBAH, EDIT, HAPUS) -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;margin-top:6px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                            <div>
                                <label style="font-size:13.5px;font-weight:800;color:var(--hanania-primary);display:block;">
                                    📷 Galeri &amp; Foto Dokumentasi Arsip Sejarah Klinik
                                </label>
                                <small style="color:var(--hanania-muted);font-size:11.5px;">Kelola foto dokumentasi perjalanan, piagam, dan arsip pendirian klinik. Anda dapat menambah foto baru, mengubah keterangan/tahun, atau menghapus foto.</small>
                            </div>
                        </div>

                        <?php 
                        $galeriJson = getKonten('sejarah_galeri_json', '[]');
                        $galeriSejarah = json_decode($galeriJson, true) ?: [];
                        if (empty($galeriSejarah)) {
                            $fa1 = getKonten('sejarah_foto_arsip_1', 'assets/img/pdf_pages/page_3.png');
                            $ca1 = getKonten('sejarah_caption_arsip_1', 'Dokumen Profil Sejarah Klinik Hanania');
                            $fa2 = getKonten('sejarah_foto_arsip_2', 'assets/img/pdf_pages/page_4.png');
                            $ca2 = getKonten('sejarah_caption_arsip_2', 'Dokumen Legalitas & Sertifikat Standar');
                            if ($fa1) $galeriSejarah[] = ['id' => '1', 'foto' => $fa1, 'caption' => $ca1, 'tahun' => '2014'];
                            if ($fa2) $galeriSejarah[] = ['id' => '2', 'foto' => $fa2, 'caption' => $ca2, 'tahun' => '2026'];
                        }
                        ?>

                        <?php if (!empty($galeriSejarah)): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;margin-bottom:16px;">
                            <?php foreach ($galeriSejarah as $idx => $gItem): 
                                $gId = htmlspecialchars((string)($gItem['id'] ?? $idx), ENT_QUOTES, 'UTF-8');
                                $gFoto = $gItem['foto'] ?? '';
                                $gCap = $gItem['caption'] ?? '';
                                $gTahun = $gItem['tahun'] ?? '';
                            ?>
                                <div style="background:#FAF8FD;padding:12px;border-radius:12px;border:1px solid #EAE0F5;display:flex;gap:12px;">
                                    <div style="width:95px;flex-shrink:0;">
                                        <img src="../<?= adminEsc(ltrim($gFoto, '/')) ?>" alt="Foto" style="width:100%;height:75px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;" onerror="this.src='../assets/img/klinik1.jpg';">
                                        <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                            <input type="checkbox" name="hapus_galeri_foto[]" value="<?= $gId ?>">
                                            🗑️ Hapus
                                        </label>
                                    </div>
                                    <div style="flex:1;">
                                        <label style="font-size:10.5px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:2px;">Keterangan Foto (Caption)</label>
                                        <input type="text" name="galeri_caption[<?= $gId ?>]" value="<?= adminEsc($gCap) ?>" class="filter-input" style="width:100%;font-size:11.5px;margin-bottom:6px;" placeholder="Keterangan foto...">
                                        <label style="font-size:10.5px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:2px;">Tahun / Periode</label>
                                        <input type="text" name="galeri_tahun[<?= $gId ?>]" value="<?= adminEsc($gTahun) ?>" class="filter-input" style="width:100%;font-size:11.5px;" placeholder="Contoh: 2014">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <div style="background:#FAF8FD;border:1px dashed var(--hanania-border);padding:14px;border-radius:10px;margin-bottom:14px;color:var(--hanania-muted);font-size:12px;">
                                Belum ada foto dokumentasi sejarah. Unggah foto baru menggunakan form di bawah.
                            </div>
                        <?php endif; ?>

                        <!-- UNGGAH FOTO BARU -->
                        <div style="background:#FFFFFF;border:1.5px dashed #D2C1EC;border-radius:12px;padding:16px;">
                            <label style="font-size:12.5px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:8px;">
                                ➕ Unggah Foto Dokumentasi / Arsip Sejarah Baru
                            </label>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;align-items:end;">
                                <div>
                                    <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Pilih File Gambar (Bisa pilih sekaligus)</label>
                                    <input type="file" name="sejarah_foto_baru[]" multiple accept="image/*" class="filter-input" style="width:100%;font-size:11.5px;background:#FAF8FD;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Keterangan Foto</label>
                                    <input type="text" name="caption_foto_baru" placeholder="Contoh: Foto Gedung Pertama / Piagam" class="filter-input" style="width:100%;font-size:11.5px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:700;color:var(--hanania-muted);display:block;margin-bottom:4px;">Tahun / Periode</label>
                                    <input type="text" name="tahun_foto_baru" placeholder="Contoh: <?= date('Y') ?>" class="filter-input" style="width:100%;font-size:11.5px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                            💾 Simpan Sejarah Klinik
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- TAB 4: FASILITAS & GALERI KLINIK -->
            <?php if ($subTabTentang === 'fasilitas'): ?>
                <?php
                $qFas = $db->query("SELECT id_fasilitas, nama_fasilitas, deskripsi, foto, foto_galeri, urutan FROM fasilitas_klinik ORDER BY urutan ASC, id_fasilitas ASC");
                $fasilitasRows = [];
                if ($qFas) {
                    while ($rf = $qFas->fetch_assoc()) {
                        $galeriArr = [];
                        if (!empty($rf['foto_galeri'])) {
                            $dec = json_decode($rf['foto_galeri'], true);
                            if (is_array($dec)) $galeriArr = $dec;
                        }
                        $rf['galeri_count'] = count($galeriArr);
                        $rf['galeri_json'] = json_encode($galeriArr);
                        $fasilitasRows[] = $rf;
                    }
                }
                ?>
                <!-- PENGATURAN HEADER HALAMAN FASILITAS -->
                <form method="POST" action="konten.php?section=tentang&tab=fasilitas" style="background:#FAF8FD;border:1px solid #EAE0F5;border-radius:14px;padding:16px 20px;margin-bottom:24px;">
                    <input type="hidden" name="action" value="simpan_tentang_fasilitas_hero">
                    <h5 style="font-size:13.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:12px;">
                        🏷️ Pengaturan Header &amp; Judul Halaman Fasilitas (Publik)
                    </h5>
                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Badge / Label Atas</label>
                            <input type="text" name="fasilitas_hero_badge" value="<?= adminEsc(getKonten('fasilitas_hero_badge', 'Fasilitas & Sarana Medis')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Utama Halaman Fasilitas</label>
                            <input type="text" name="fasilitas_hero_judul" value="<?= adminEsc(getKonten('fasilitas_hero_judul', 'Fasilitas Modern & Ruang Layanan Nyaman')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                        </div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul / Deskripsi Header</label>
                        <input type="text" name="fasilitas_hero_subjudul" value="<?= adminEsc(getKonten('fasilitas_hero_subjudul', 'Klinik Pratama Rumah Hanania dilengkapi dengan sarana medis modern, ruang tindakan higienis, serta fasilitas penunjang yang ramah keluarga.')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-sm" style="font-weight:800;padding:8px 18px;">
                            💾 Simpan Header Fasilitas
                        </button>
                    </div>
                </form>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            🏢 Kelola Fasilitas Medis &amp; Galeri Foto (tentang/fasilitas klinik.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Kelola ruang tindakan, poli, kamar rawat, dan galeri dokumentasi fasilitas klinik.
                        </p>
                    </div>
                    <button type="button" onclick="bukaModalTambahFasilitas()" class="btn btn-sm" style="display:inline-flex;align-items:center;gap:6px;font-weight:800;">
                        <span>➕</span> Tambah Fasilitas Baru
                    </button>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width:50px;text-align:center;">Urutan</th>
                                <th style="width:90px;text-align:center;">Foto</th>
                                <th>Nama Fasilitas</th>
                                <th>Deskripsi Ringkas</th>
                                <th style="width:110px;text-align:center;">Foto Galeri</th>
                                <th style="width:150px;text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fasilitasRows)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:30px;color:var(--hanania-muted);">
                                        Belum ada fasilitas klinik yang ditambahkan. Klik tombol "Tambah Fasilitas Baru" di atas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fasilitasRows as $f): ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:800;color:var(--hanania-primary);">
                                            <?= (int)$f['urutan'] ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php $fImg = !empty($f['foto']) ? ltrim($f['foto'], '/') : 'assets/img/klinik1.jpg'; ?>
                                            <img src="../<?= adminEsc($fImg) ?>" alt="<?= adminEsc($f['nama_fasilitas']) ?>" style="width:65px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);" onerror="this.src='../assets/img/klinik1.jpg'">
                                        </td>
                                        <td style="font-weight:800;color:var(--hanania-text);font-size:13.5px;">
                                            <?= adminEsc($f['nama_fasilitas']) ?>
                                        </td>
                                        <td style="font-size:12.5px;color:var(--hanania-muted);max-width:320px;line-height:1.5;">
                                            <?= adminEsc(mb_strimwidth($f['deskripsi'] ?? '-', 0, 95, '...')) ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span style="display:inline-block;padding:3px 9px;border-radius:12px;background:var(--hanania-purple-soft);color:var(--hanania-primary);font-size:11.5px;font-weight:800;">
                                                📷 <?= (int)$f['galeri_count'] ?> foto
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="display:inline-flex;gap:6px;">
                                                <button type="button" class="btn btn-sm btn-outline" onclick='bukaModalEditFasilitas(<?= json_encode($f, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= $f['galeri_json'] ?>)' style="padding:5px 10px;font-size:12px;">
                                                    ✏️ Edit
                                                </button>
                                                <form method="POST" action="konten.php?section=tentang&tab=fasilitas" onsubmit="return confirm('Hapus fasilitas <?= adminEsc(addslashes($f['nama_fasilitas'])) ?>?')">
                                                    <input type="hidden" name="action" value="hapus_fasilitas">
                                                    <input type="hidden" name="id_fasilitas" value="<?= (int)$f['id_fasilitas'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" style="padding:5px 10px;font-size:12px;">
                                                        🗑️
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- TAB 5: STRUKTUR ORGANISASI & BAGAN -->
            <?php if ($subTabTentang === 'struktur'): ?>
                <form method="POST" action="konten.php?section=tentang&tab=struktur" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <input type="hidden" name="action" value="simpan_tentang_struktur">

                    <div style="grid-column:1/-1;">
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            👥 Editor Halaman: Struktur Organisasi &amp; Tim Tata Kelola (tentang/struktur_organisasi.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Kelola bagan visual organisasi, pucuk pimpinan direksi, tim penanggung jawab (PJ) mutu, serta unit pelayanan dan penunjang medis.
                        </p>
                    </div>

                    <!-- BAGAN FOTO STRUKTUR -->
                    <div style="grid-column:1/-1;background:#FAF8FD;padding:18px;border-radius:14px;border:1px solid #EAE0F5;">
                        <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:8px;">
                            📊 Bagan Visual Struktur Organisasi (Gambar Bagan / Diagram)
                        </label>
                        <?php $baganImg = getKonten('struktur_organisasi_foto', ''); ?>
                        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                            <?php if ($baganImg && file_exists(__DIR__ . '/../' . ltrim($baganImg, '/'))): ?>
                                <div>
                                    <img src="../<?= adminEsc(ltrim($baganImg, '/')) ?>" alt="Bagan Struktur Organisasi" style="max-width:220px;max-height:120px;object-fit:contain;background:#fff;padding:6px;border-radius:8px;border:1px solid var(--hanania-border);display:block;margin-bottom:6px;">
                                    <label style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:#b91c1c;font-weight:700;cursor:pointer;">
                                        <input type="checkbox" name="hapus_bagan_foto" value="1">
                                        🗑️ Hapus Gambar Bagan Ini
                                    </label>
                                </div>
                            <?php else: ?>
                                <div style="font-size:12px;color:var(--hanania-muted);background:#fff;padding:8px 14px;border-radius:8px;border:1px dashed var(--hanania-border);">
                                    Belum ada gambar bagan kustom (halaman publik menampilkan pohon organisasi teks).
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:240px;">
                                <input type="file" name="struktur_organisasi_foto" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;width:100%;margin-bottom:6px;">
                                <small style="font-size:11px;color:var(--hanania-muted);">Unggah gambar bagan/diagram struktur organisasi (JPG, PNG, WEBP). Bila diisi, gambar bagan akan tampil di bagian atas halaman Struktur Organisasi.</small>
                            </div>
                        </div>
                    </div>

                    <!-- SUSUNAN TIM STRUKTUR ORGANISASI (BISA DITAMBAH, DIUBAH, DIKURANGIN) -->
                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
                            <div>
                                <h5 style="font-size:14px;font-weight:800;color:var(--hanania-primary);margin:0 0 4px;">
                                    👥 Susunan Tim &amp; Posisi Struktur Organisasi
                                </h5>
                                <p style="font-size:12px;color:var(--hanania-muted);margin:0;">
                                    Daftar nama, posisi, dan unit pelayanan yang ditampilkan pada halaman Struktur Organisasi. Anda dapat menambah posisi baru, mengubah jabatan/nama, atau menghapus posisi.
                                </p>
                            </div>
                            <button type="button" onclick="tambahBarisPosisi()" class="btn btn-sm" style="display:inline-flex;align-items:center;gap:6px;font-weight:800;">
                                <span>➕</span> Tambah Posisi Baru
                            </button>
                        </div>

                        <?php 
                        $timJson = getKonten('struktur_tim_json', '[]');
                        $timList = json_decode($timJson, true) ?: [];
                        if (empty($timList)) {
                            $timList = [
                                ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Direktur Utama', 'nama' => 'Dr. Fajar Delli Wihartiko, M.M., M.Kom.', 'icon' => '🎓'],
                                ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Direktur Operasional', 'nama' => 'Gita Marissa, S.Tr.Keb., Bdn.', 'icon' => '💼'],
                                ['kategori' => 'Pimpinan & Direksi', 'jabatan' => 'Kepala Klinik', 'nama' => 'drg. Fatmasari Pangesti', 'icon' => '🩺'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Mutu / PMKP', 'nama' => "Aida Sa'adah, S.Tr.Keb., Bdn.", 'icon' => '📊'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ PPI', 'nama' => 'dr. Mona Hernika', 'icon' => '🧼'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Pelayanan Medis', 'nama' => 'dr. Patricia Mellroviane', 'icon' => '🩺'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Manajemen', 'nama' => 'Gita Marissa, S.Tr.Keb., Bdn.', 'icon' => '📑'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Umum & Kepegawaian', 'nama' => 'Putri Nurul Fajriah, S.Tr.Keb., Bdn.', 'icon' => '🏢'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Manajemen Risiko', 'nama' => 'Mika Aulia Chantika, Amd Keb', 'icon' => '⚖️'],
                                ['kategori' => 'Tim Penanggung Jawab (PJ) Mutu & Manajemen', 'jabatan' => 'PJ Keselamatan Pasien (K3)', 'nama' => 'Rifa Armawanti, Amd Keb', 'icon' => '❤️'],
                                ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Umum', 'nama' => 'dr. Patricia Mellroviane', 'icon' => '🩺'],
                                ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Gigi & Mulut', 'nama' => 'drg. Fatmasari Pangesti', 'icon' => '🦷'],
                                ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Poli Kebidanan & KIA', 'nama' => 'Pinandha Arafah, Amd.Keb', 'icon' => '👶'],
                                ['kategori' => 'Unit Pelayanan Medis', 'jabatan' => 'Ruang Tindakan & Gawat Darurat', 'nama' => 'Bilqis Az-Zahra, A. Md Keb', 'icon' => '🚑'],
                                ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Pendaftaran & Informasi', 'nama' => 'Delia Puspita', 'icon' => '📋'],
                                ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Farmasi & Apotek', 'nama' => 'Apt. Dewi Pawitra Sari', 'icon' => '💊'],
                                ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Rekam Medis (RME)', 'nama' => 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb', 'icon' => '📂'],
                                ['kategori' => 'Unit Penunjang Medis', 'jabatan' => 'Promotif Preventif', 'nama' => 'Salma Nur Fatimah, Amd.Keb', 'icon' => '📢'],
                                ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Umum & Kepegawaian', 'nama' => 'Ni Luh Putu Indrayantidarmaully, A.Md.Keb', 'icon' => '👥'],
                                ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Kebersihan Lingkungan', 'nama' => 'Suherman', 'icon' => '🧹'],
                                ['kategori' => 'Unit Administrasi & Umum', 'jabatan' => 'Keamanan Klinik', 'nama' => 'Suherman', 'icon' => '🛡️']
                            ];
                        }
                        $kategoriList = [
                            'Pimpinan & Direksi' => '🏛️ Pimpinan & Direksi',
                            'Tim Penanggung Jawab (PJ) Mutu & Manajemen' => '🛡️ Tim PJ Mutu & Manajemen',
                            'Unit Pelayanan Medis' => '🩺 Unit Pelayanan Medis',
                            'Unit Penunjang Medis' => '🔬 Unit Penunjang Medis',
                            'Unit Administrasi & Umum' => '🏢 Unit Administrasi & Umum',
                            'Lainnya' => '✨ Kategori Lainnya'
                        ];
                        ?>

                        <div class="admin-table-wrap" style="max-height:480px;overflow-y:auto;border:1px solid #EAE0F5;border-radius:12px;">
                            <table class="admin-table">
                                <thead style="position:sticky;top:0;background:#F8F4FC;z-index:2;">
                                    <tr>
                                        <th style="width:230px;">Kelompok / Kategori</th>
                                        <th style="width:240px;">Jabatan / Posisi</th>
                                        <th>Nama Pejabat / Tenaga</th>
                                        <th style="width:75px;text-align:center;">Ikon</th>
                                        <th style="width:70px;text-align:center;">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyStrukturPosisi">
                                    <?php foreach ($timList as $item): 
                                        $kat = $item['kategori'] ?? 'Lainnya';
                                        $jab = $item['jabatan'] ?? '';
                                        $nam = $item['nama'] ?? '';
                                        $ico = $item['icon'] ?? '👥';
                                    ?>
                                        <tr>
                                            <td>
                                                <select name="posisi_kategori[]" class="filter-input" style="width:100%;font-size:12px;font-weight:700;">
                                                    <?php foreach ($kategoriList as $kVal => $kLabel): ?>
                                                        <option value="<?= adminEsc($kVal) ?>" <?= $kat === $kVal ? 'selected' : '' ?>>
                                                            <?= adminEsc($kLabel) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <?php if (!array_key_exists($kat, $kategoriList)): ?>
                                                        <option value="<?= adminEsc($kat) ?>" selected><?= adminEsc($kat) ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="posisi_jabatan[]" value="<?= adminEsc($jab) ?>" class="filter-input" style="width:100%;font-size:12px;font-weight:700;" placeholder="Jabatan..." required>
                                            </td>
                                            <td>
                                                <input type="text" name="posisi_nama[]" value="<?= adminEsc($nam) ?>" class="filter-input" style="width:100%;font-size:12px;" placeholder="Nama & Gelar..." required>
                                            </td>
                                            <td style="text-align:center;">
                                                <input type="text" name="posisi_icon[]" value="<?= adminEsc($ico) ?>" class="filter-input" style="width:55px;text-align:center;font-size:14px;">
                                            </td>
                                            <td style="text-align:center;">
                                                <button type="button" onclick="hapusBarisPosisi(this)" class="btn btn-sm btn-danger" style="padding:6px 10px;font-size:12px;" title="Hapus Posisi Ini">🗑️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top:12px;">
                            <button type="button" onclick="tambahBarisPosisi()" class="btn btn-sm" style="background:#FAF8FD;border:1.5px dashed var(--hanania-primary);color:var(--hanania-primary);font-weight:800;padding:8px 16px;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                                <span>➕</span> Tambah Posisi / Tenaga Baru
                            </button>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                            💾 Simpan Struktur Organisasi
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- TAB 6: SECTION TENTANG DI BERANDA -->
            <?php if ($subTabTentang === 'beranda_tentang'): ?>
                <form method="POST" action="konten.php?section=tentang&tab=beranda_tentang" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <input type="hidden" name="action" value="simpan_tentang_beranda">

                    <div style="grid-column:1/-1;">
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--hanania-primary);margin-bottom:4px;">
                            🌟 Editor Section Tentang Kami pada Halaman Utama (Beranda / index.php)
                        </h4>
                        <p style="font-size:12.5px;color:var(--hanania-muted);">
                            Kelola judul headline, narasi paragraf, 4 poin keunggulan, serta foto klinik yang tampil di section Tentang Kami di Beranda.
                        </p>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul Section Tentang Kami di Beranda</label>
                        <input type="text" name="tentang_judul" value="<?= adminEsc(getKonten('tentang_judul', 'Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:13px;" required>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Paragraf 1 Tentang Kami</label>
                        <textarea name="tentang_paragraf_1" rows="3" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('tentang_paragraf_1', 'Klinik Pratama Rumah Hanania merupakan fasilitas pelayanan kesehatan yang memberikan pelayanan kesehatan kepada masyarakat.')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Paragraf 2 Tentang Kami</label>
                        <textarea name="tentang_paragraf_2" rows="3" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('tentang_paragraf_2', 'Website ini membantu pasien memperoleh informasi klinik, melihat jadwal pelayanan, serta melakukan pendaftaran pasien secara online.')) ?></textarea>
                    </div>

                    <div style="grid-column:1/-1;border-top:1px solid var(--hanania-border);padding-top:14px;">
                        <label style="font-size:13px;font-weight:800;color:var(--hanania-primary);display:block;margin-bottom:10px;">
                            ✨ 4 Poin Keunggulan Utama di Beranda
                        </label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Poin #1</label>
                                <input type="text" name="tentang_poin_1" value="<?= adminEsc(getKonten('tentang_poin_1', 'Pelayanan Ramah, Santun & Nyaman')) ?>" class="filter-input" style="width:100%;font-size:12.5px;">
                            </div>
                            <div>
                                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Poin #2</label>
                                <input type="text" name="tentang_poin_2" value="<?= adminEsc(getKonten('tentang_poin_2', 'Tenaga Medis Profesional & Berpengalaman')) ?>" class="filter-input" style="width:100%;font-size:12.5px;">
                            </div>
                            <div>
                                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Poin #3</label>
                                <input type="text" name="tentang_poin_3" value="<?= adminEsc(getKonten('tentang_poin_3', 'Fasilitas Ruang Periksa Steril & Lengkap')) ?>" class="filter-input" style="width:100%;font-size:12.5px;">
                            </div>
                            <div>
                                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Poin #4</label>
                                <input type="text" name="tentang_poin_4" value="<?= adminEsc(getKonten('tentang_poin_4', 'Pendaftaran Digital & Sistem Antrean Terpadu')) ?>" class="filter-input" style="width:100%;font-size:12.5px;">
                            </div>
                        </div>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Klinik pada Section Tentang Kami</label>
                        <?php $fotoTtg = getKonten('tentang_foto', 'assets/img/klinik3.jpg'); ?>
                        <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px;">
                            <img src="../<?= adminEsc(ltrim($fotoTtg, '/')) ?>" alt="Foto Tentang Kami" style="width:120px;height:75px;object-fit:cover;border-radius:8px;border:1px solid var(--hanania-border);" onerror="this.src='../assets/img/klinik3.jpg'">
                            <input type="file" name="tentang_foto" accept="image/*" class="filter-input" style="font-size:12px;background:#fff;">
                        </div>
                    </div>

                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end;margin-top:10px;">
                        <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                            💾 Simpan Section Tentang Beranda
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>



    <!-- ========================================================= -->
    <!-- 3. SECTION: MUSIK / LAGU LATAR WEBSITE                    -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'musik'): ?>
        <section class="admin-card">
            <div class="admin-card-head">
                <div class="admin-card-title">
                    <span>🎵 Kelola Musik / Lagu Latar Website (Suasana Klinik)</span>
                </div>
                <div class="admin-card-subtitle">
                    Atur lagu instrumental / musik suasana tenang yang otomatis berputar saat pengunjung awal mengakses website dan berlanjut ke seluruh halaman.
                </div>
            </div>

            <!-- KOTAK INFORMASI LOKASI FILE UNTUK PENGGUNA -->
            <div style="background:#F3EDFB;border:1.5px solid #D5C5EC;border-radius:14px;padding:18px 22px;margin-bottom:24px;">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    <span style="font-size:24px;">💡</span>
                    <div>
                        <strong style="font-size:14px;color:#4A1D75;display:block;margin-bottom:4px;">
                            Petunjuk Lokasi &amp; Cara Memasukkan Lagu:
                        </strong>
                        <p style="font-size:13px;color:#55416C;line-height:1.6;margin:0 0 8px;">
                            Anda dapat mengunggah file lagu langsung melalui form di bawah ini, atau menyalin file lagu berformat <strong>.mp3 / .ogg / .wav</strong> ke folder project berikut di komputer Anda:
                        </p>
                        <code style="background:#FFFFFF;border:1px solid #D1C0E8;padding:6px 14px;border-radius:8px;font-size:12.5px;color:#6C22A6;font-weight:700;display:inline-block;font-family:Consolas,monospace;">
                            d:\laragon\www\Klinik-Pratama-Rumah-Hanania\uploads\audio\
                        </code>
                        <p style="font-size:12px;color:#6B5880;margin-top:8px;margin-bottom:0;">
                            <em>Catatan Browser &amp; Pemutaran:</em> Musik akan langsung berusaha diputar saat pengunjung awal membuka website. Bila kebijakan browser (Chrome/Edge) menahan audio bersuara sebelum ada sentuhan pertama, sistem akan langsung mengaktifkan suara segera setelah pengunjung menyentuh, menggeser (scroll), atau mengeklik bagian mana pun pada website.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="konten.php?section=musik" enctype="multipart/form-data" style="max-width:760px;">
                <input type="hidden" name="action" value="simpan_musik">

                <!-- STATUS ON / OFF -->
                <div style="margin-bottom:20px;">
                    <label style="font-size:13px;font-weight:800;color:var(--text);display:block;margin-bottom:8px;">Status Pemutar Musik Latar Website *</label>
                    <?php $audioAktif = getKonten('audio_beranda_aktif', '1'); ?>
                    <div style="display:flex;gap:20px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:#FAF8FD;padding:10px 18px;border-radius:10px;border:1px solid #EAE0F5;">
                            <input type="radio" name="audio_beranda_aktif" value="1" <?= $audioAktif === '1' ? 'checked' : '' ?>>
                            <strong style="color:#15803d;font-size:13px;">▶️ Aktifkan Musik Latar</strong>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;background:#FAF8FD;padding:10px 18px;border-radius:10px;border:1px solid #EAE0F5;">
                            <input type="radio" name="audio_beranda_aktif" value="0" <?= $audioAktif === '0' ? 'checked' : '' ?>>
                            <strong style="color:#b91c1c;font-size:13px;">⏸️ Nonaktifkan Musik</strong>
                        </label>
                    </div>
                </div>

                <!-- JUDUL LAGU -->
                <div style="margin-bottom:20px;">
                    <label style="font-size:12px;font-weight:800;display:block;margin-bottom:6px;">Judul Lagu / Nama Suasana (Tampil di Floating Player)</label>
                    <input type="text" name="audio_beranda_judul" value="<?= adminEsc(getKonten('audio_beranda_judul', 'Instrumental Suasana Tenang - Klinik Pratama Rumah Hanania')) ?>" class="filter-input" style="width:100%;font-size:13.5px;font-weight:700;" placeholder="Contoh: Suasana Tenang - Klinik Pratama Rumah Hanania">
                </div>

                <!-- FILE SAAT INI -->
                <?php $currentAudio = getKonten('audio_beranda_file', 'uploads/audio/suasana_klinik_hanania.mp3'); ?>
                <div style="margin-bottom:20px;background:#FAF8FD;padding:16px 20px;border-radius:12px;border:1px solid #EAE0F5;">
                    <div style="font-size:12px;font-weight:800;color:var(--hanania-primary);margin-bottom:8px;">File Lagu yang Sedang Digunakan Saat Ini:</div>
                    <div style="font-size:13px;color:#352D3E;margin-bottom:10px;font-family:monospace;background:#FFF;padding:8px 12px;border-radius:8px;border:1px solid #E5D8F0;">
                        📁 <?= adminEsc($currentAudio) ?>
                    </div>
                    <?php if (file_exists(__DIR__ . '/../' . ltrim($currentAudio, '/'))): ?>
                        <div style="margin-top:8px;">
                            <span style="font-size:11.5px;color:var(--hanania-muted);display:block;margin-bottom:6px;">Pratinjau Dengar Lagu:</span>
                            <audio controls style="width:100%;max-width:440px;height:38px;">
                                <source src="../<?= adminEsc(ltrim($currentAudio, '/')) ?>" type="audio/mpeg">
                                Browser Anda tidak mendukung pemutar audio.
                            </audio>
                        </div>
                    <?php else: ?>
                        <small style="color:#b91c1c;font-size:11.5px;">File audio belum ada di folder server. Silakan unggah berkas MP3 di bawah.</small>
                    <?php endif; ?>
                </div>

                <!-- UPLOAD FILE AUDIO BARU -->
                <div style="margin-bottom:24px;">
                    <label style="font-size:12px;font-weight:800;display:block;margin-bottom:6px;">Unggah Berkas Lagu Baru (.mp3 / .wav / .ogg - Maks 20 MB)</label>
                    <input type="file" name="audio_file" accept=".mp3,.wav,.ogg,.m4a" class="filter-input" style="width:100%;background:#FFF;font-size:13px;">
                    <small style="font-size:11.5px;color:var(--hanania-muted);margin-top:4px;display:block;">
                        Biarkan kosong jika tidak ingin mengganti file lagu yang sedang aktif.
                    </small>
                </div>

                <button type="submit" class="btn" style="padding:12px 30px;font-size:14px;font-weight:800;">
                    💾 Simpan Pengaturan Musik
                </button>
            </form>
        </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 4. SECTION: KONTAK, ALAMAT & FOOTER                      -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'kontak_footer'): ?>
        <section class="admin-card">
            <div class="admin-card-head">
                <div class="admin-card-title">
                    <span>📞 Kelola Kontak, Alamat &amp; Footer Website</span>
                </div>
                <div class="admin-card-subtitle">
                    Atur nomor WhatsApp, jam operasional, alamat lengkap, link Google Maps, serta teks legalitas dan hak cipta di footer (Gambar 3).
                </div>
            </div>

            <form method="POST" action="konten.php?section=kontak_footer" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <input type="hidden" name="action" value="simpan_kontak_footer">

                <!-- KONTAK & WHATSAPP -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>📱</span> Nomor WhatsApp &amp; Kontak (Gambar 3)
                    </h3>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">WhatsApp Layanan &amp; Administrasi 1</label>
                    <input type="text" name="footer_wa1" value="<?= adminEsc(getKonten('footer_wa1', '088510703839')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">WhatsApp Informasi &amp; Pendaftaran 2</label>
                    <input type="text" name="footer_wa2" value="<?= adminEsc(getKonten('footer_wa2', '085714178178')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nomor Telepon Utama / WhatsApp Klinik</label>
                    <input type="text" name="kontak_telepon" value="<?= adminEsc(getKonten('kontak_telepon', '0857 1417 8178')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Akun Instagram</label>
                    <input type="text" name="kontak_instagram" value="<?= adminEsc(getKonten('kontak_instagram', '@rumahhanania_')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Jam Pelayanan / Operasional</label>
                    <input type="text" name="kontak_jam_buka" value="<?= adminEsc(getKonten('kontak_jam_buka', 'Buka Setiap Hari (08.00 - 20.00 WIB) & Persalinan 24 Jam')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Tautan / Link Buka Rute Google Maps</label>
                    <input type="text" name="footer_maps_link" value="<?= adminEsc(getKonten('footer_maps_link', 'https://maps.app.goo.gl/')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Alamat Lengkap Klinik (Lokasi Klinik)</label>
                    <textarea name="kontak_alamat" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('kontak_alamat', 'Jl. Desa Sukadamai No.RT 01, RT.01/RW.06, Sukadamai, Kec. Tanah Sareal, Kota Bogor, Jawa Barat 16165')) ?></textarea>
                </div>

                <!-- DESKRIPSI & LEGALITAS FOOTER -->
                <div style="grid-column:1/-1;border-bottom:2px solid var(--hanania-border);padding-bottom:8px;margin-top:10px;">
                    <h3 style="font-size:15px;color:var(--hanania-primary);font-weight:800;display:flex;align-items:center;gap:6px;">
                        <span>📜</span> Teks Deskripsi &amp; Legalitas Footer (Gambar 3)
                    </h3>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Teks Deskripsi Singkat Footer</label>
                    <textarea name="footer_deskripsi" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('footer_deskripsi', 'Pelayanan kesehatan rawat jalan tingkat pertama yang mudah dijangkau, berkualitas, dan berorientasi pada kebutuhan keluarga dan masyarakat.')) ?></textarea>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Teks Legalitas / Sertifikasi Standar</label>
                    <textarea name="footer_legalitas" rows="2" class="filter-input" style="width:100%;font-size:13px;"><?= adminEsc(getKonten('footer_legalitas', 'Sertifikat Standar Perizinan Berusaha Berbasis Risiko terdaftar resmi.')) ?></textarea>
                </div>

                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Teks Hak Cipta (Copyright Footer)</label>
                    <input type="text" name="footer_copyright" value="<?= adminEsc(getKonten('footer_copyright', '© ' . date('Y') . ' Klinik Pratama Rumah Hanania. Hak Cipta Dilindungi.')) ?>" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="grid-column:1/-1;margin-top:10px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn" style="padding:12px 28px;font-size:14px;font-weight:800;">
                        💾 Simpan Kontak &amp; Footer
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- 1. SECTION: HERO / BANNER                                -->
    <!-- ========================================================= -->
    <?php if ($activeSection === 'hero'): ?>
        <div class="hero-grid-layout">
            
            <!-- FORM TAMBAH SLIDE HERO -->
            <section class="admin-card">
                <div class="admin-card-head">
                    <div class="admin-card-title">
                        <span>➕ Tambah Slide Hero Baru</span>
                    </div>
                    <div class="admin-card-subtitle">
                        Unggah foto banner baru untuk ditampilkan di slideshow beranda.
                    </div>
                </div>

                <form method="POST" action="konten.php?section=hero" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="tambah_hero">

                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Banner * (JPG/PNG/WEBP)</label>
                        <input type="file" name="foto_hero" accept="image/*" required class="filter-input" style="width:100%;font-size:12px;background:#fff;">
                        <small style="font-size:11px;color:var(--hanania-muted);margin-top:4px;display:block;">Rasio landscape disarankan (misal 1200x700 px, maks 4MB).</small>
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul / Keterangan Banner</label>
                        <input type="text" name="judul" placeholder="Contoh: Gedung & Pelayanan Klinik" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul / Slogan Singkat (Opsional)</label>
                        <input type="text" name="subjudul" placeholder="Contoh: Pelayanan ramah & terpercaya" class="filter-input" style="width:100%;font-size:13px;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Tayang</label>
                        <select name="status" class="filter-input" style="width:100%;font-size:13px;">
                            <option value="aktif">🟢 Aktif (Tampil di Beranda)</option>
                            <option value="nonaktif">⚪ Nonaktif (Disembunyikan)</option>
                        </select>
                        <small style="font-size:11.5px;color:var(--hanania-muted);margin-top:6px;display:block;line-height:1.4;">
                            💡 Slide baru otomatis ditempatkan di posisi terakhir. Anda bebas menggeser urutannya ke atas atau ke bawah pada tabel di samping.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-sm" style="width:100%;justify-content:center;">➕ Tambah Slide Banner</button>
                </form>
            </section>

            <!-- DAFTAR SLIDE HERO YANG ADA -->
            <section class="admin-card" style="padding:0;overflow:hidden;">
                <div class="admin-card-head" style="padding:18px 24px;border-bottom:1px solid var(--hanania-border);margin:0;">
                    <div>
                        <div class="admin-card-title">
                            <span>🌄 Daftar Slide Banner Hero (<?= count($heroBanners) ?> Slide)</span>
                        </div>
                        <div class="admin-card-subtitle" style="margin-top:2px;">
                            Geser baris ke atas/bawah atau gunakan tombol panah (▲/▼) untuk mengatur urutan tayang bebas tanpa repot mengetik angka.
                        </div>
                    </div>
                </div>

                <div class="admin-table-responsive">
                    <table style="width:100%;table-layout:auto;">
                        <thead>
                            <tr>
                                <th style="width:90px;">Pratinjau</th>
                                <th>Judul &amp; Keterangan</th>
                                <th style="width:130px;text-align:center;">Atur Posisi</th>
                                <th style="width:105px;text-align:center;">Status</th>
                                <th style="width:150px;text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="heroSortableBody">
                            <?php if (empty($heroBanners)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:36px;color:var(--hanania-muted);">
                                        Belum ada slide banner hero. Silakan tambah slide baru melalui formulir di sebelah kiri.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($heroBanners as $hb): 
                                    $fotoSrc = !empty($hb['foto']) ? '../' . ltrim($hb['foto'], '/') : '../assets/img/klinik.jpg';
                                    $isAktif = ($hb['status'] ?? 'aktif') === 'aktif';
                                ?>
                                    <tr draggable="true" data-id="<?= (int)$hb['id_banner'] ?>" class="hero-sortable-row">
                                        <td>
                                            <div style="width:85px;height:52px;border-radius:8px;overflow:hidden;background:#FAF8FD;border:1px solid var(--hanania-border);">
                                                <img src="<?= adminEsc($fotoSrc) ?>" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='../assets/img/klinik.jpg';">
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:var(--hanania-primary);font-size:13.5px;display:block;">
                                                 <?= adminEsc($hb['judul'] ?: 'Banner Slide #' . $hb['id_banner']) ?>
                                            </strong>
                                            <?php if (!empty($hb['subjudul'])): ?>
                                                <span style="font-size:12px;color:var(--hanania-muted);display:block;margin-top:2px;">
                                                    <?= adminEsc($hb['subjudul']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span style="font-size:11px;color:#9E8EB1;">
                                                Path: <?= adminEsc($hb['foto']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">
                                                <span class="hero-drag-handle" title="Tahan dan geser baris ini ke atas atau ke bawah untuk mengatur urutan tayang">
                                                    ☰ Geser
                                                </span>
                                                <button type="button" class="btn-hero-arrow" onclick="geserRowHero(this, -1)" title="Geser Naik (Ke Atas)">▲</button>
                                                <button type="button" class="btn-hero-arrow" onclick="geserRowHero(this, 1)" title="Geser Turun (Ke Bawah)">▼</button>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="konten.php?section=hero&toggle_hero=<?= (int)$hb['id_banner'] ?>" 
                                               title="Klik untuk ubah: <?= $isAktif ? 'Nonaktifkan' : 'Aktifkan' ?>" 
                                               style="text-decoration:none;display:inline-block;">
                                                <span class="badge badge-<?= $isAktif ? 'success' : 'gray' ?>" style="cursor:pointer;">
                                                    <?= $isAktif ? '🟢 Aktif' : '⚪ Nonaktif' ?>
                                                </span>
                                            </a>
                                        </td>
                                        <td style="text-align:right;">
                                            <div class="table-action-group">
                                                <button type="button" 
                                                        onclick="bukaModalEditHero(<?= (int)$hb['id_banner'] ?>, '<?= adminEsc(addslashes($hb['judul'] ?? '')) ?>', '<?= adminEsc(addslashes($hb['subjudul'] ?? '')) ?>', '<?= adminEsc($hb['status']) ?>', '<?= adminEsc($fotoSrc) ?>')"
                                                        class="btn btn-sm btn-light" 
                                                        style="padding:6px 10px;font-size:11.5px;font-weight:700;" 
                                                        title="Ubah Foto & Data Slide">
                                                    ✏️ Ubah
                                                </button>
                                                <a href="konten.php?section=hero&hapus_hero=<?= (int)$hb['id_banner'] ?>" 
                                                   onclick="return confirm('Yakin ingin menghapus slide banner hero ini?');"
                                                   class="btn btn-sm" 
                                                   style="padding:6px 9px;font-size:11.5px;font-weight:700;background:#FDEAEA;color:#B72B2B;" 
                                                   title="Hapus Slide">
                                                    🗑️ Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    <?php endif; ?>



    <!-- ========================================================= -->
    <!-- MODAL EDIT HERO                                          -->
    <!-- ========================================================= -->
    <div id="modalEditHeroOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:18px;max-width:540px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);overflow:hidden;animation:hananiaFadeDown 0.2s ease;">
            <div style="padding:18px 24px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
                <h3 style="font-size:16px;font-weight:800;color:var(--hanania-primary);margin:0;">
                    ✏️ Ubah Slide Banner Hero
                </h3>
                <button type="button" onclick="tutupModalEditHero()" style="border:0;background:transparent;font-size:18px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>
            <form method="POST" action="konten.php?section=hero" enctype="multipart/form-data" style="padding:22px 24px;">
                <input type="hidden" name="action" value="edit_hero">
                <input type="hidden" name="id_banner" id="editHeroId">

                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Pratinjau Foto Banner</label>
                    <div style="width:100%;height:160px;border-radius:10px;overflow:hidden;background:#FAF8FD;border:1px solid var(--hanania-border);margin-bottom:8px;display:flex;align-items:center;justify-content:center;">
                        <img id="editHeroPreviewImg" src="" alt="Pratinjau" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Ganti Foto Baru (Pilih berkas baru jika ingin mengganti gambar)</label>
                    <input type="file" name="foto_hero" id="editHeroInputFile" accept="image/*" class="filter-input" style="width:100%;font-size:12px;background:#fff;" onchange="previewEditHeroFoto(this)">
                    <small style="font-size:11px;color:var(--hanania-muted);margin-top:4px;display:block;">Biarkan kosong jika tidak ingin mengubah foto yang sudah ada.</small>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Judul / Keterangan Banner</label>
                    <input type="text" name="judul" id="editHeroJudul" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Subjudul / Slogan Singkat (Opsional)</label>
                    <input type="text" name="subjudul" id="editHeroSubjudul" class="filter-input" style="width:100%;font-size:13px;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Status Tayang</label>
                    <select name="status" id="editHeroStatus" class="filter-input" style="width:100%;font-size:13px;">
                        <option value="aktif">🟢 Aktif (Ditampilkan)</option>
                        <option value="nonaktif">⚪ Nonaktif (Disembunyikan)</option>
                    </select>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="tutupModalEditHero()" class="btn btn-sm btn-light">Batal</button>
                    <button type="submit" class="btn btn-sm">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>


    <script>
    function previewEditHeroFoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('editHeroPreviewImg');
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function bukaModalEditHero(id, judul, subjudul, status, fotoSrc) {
        document.getElementById('editHeroId').value = id;
        document.getElementById('editHeroJudul').value = judul;
        document.getElementById('editHeroSubjudul').value = subjudul;
        document.getElementById('editHeroStatus').value = status;
        document.getElementById('editHeroPreviewImg').src = fotoSrc;
        const fileInput = document.getElementById('editHeroInputFile');
        if (fileInput) fileInput.value = '';
        const overlay = document.getElementById('modalEditHeroOverlay');
        if (overlay) overlay.style.display = 'flex';
    }
    function tutupModalEditHero() {
        const overlay = document.getElementById('modalEditHeroOverlay');
        if (overlay) overlay.style.display = 'none';
    }

    // =====================================================
    // ATUR POSISI SLIDE HERO: DRAG & DROP & ARROW BUTTONS
    // =====================================================
    let heroDragSrc = null;

    function initHeroDragAndDrop() {
        const tbody = document.getElementById('heroSortableBody');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('.hero-sortable-row');
        rows.forEach(row => {
            row.addEventListener('dragstart', function(e) {
                heroDragSrc = this;
                e.dataTransfer.effectAllowed = 'move';
                this.classList.add('hero-dragging');
            });

            row.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                const target = this;
                if (target && target !== heroDragSrc) {
                    const rect = target.getBoundingClientRect();
                    const isAfter = (e.clientY - rect.top) > (rect.height / 2);
                    tbody.insertBefore(heroDragSrc, isAfter ? target.nextSibling : target);
                }
            });

            row.addEventListener('dragend', function() {
                this.classList.remove('hero-dragging');
                document.querySelectorAll('.hero-sortable-row').forEach(r => r.classList.remove('hero-drag-over'));
                simpanUrutanBannerHero();
            });
        });
    }

    function geserRowHero(btn, dir) {
        const row = btn.closest('tr');
        if (!row) return;
        const tbody = row.parentElement;
        if (!tbody) return;

        if (dir === -1) {
            const prev = row.previousElementSibling;
            if (prev) {
                tbody.insertBefore(row, prev);
                simpanUrutanBannerHero();
            }
        } else if (dir === 1) {
            const next = row.nextElementSibling;
            if (next) {
                tbody.insertBefore(next, row);
                simpanUrutanBannerHero();
            }
        }
    }

    function simpanUrutanBannerHero() {
        const tbody = document.getElementById('heroSortableBody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('.hero-sortable-row');
        const ids = Array.from(rows).map(r => r.getAttribute('data-id'));
        if (ids.length === 0) return;

        const formData = new FormData();
        formData.append('action', 'reorder_hero');
        formData.append('order', JSON.stringify(ids));

        fetch('konten.php?section=hero', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                tampilkanToastHero('✅ Urutan slide banner berhasil diperbarui!');
            }
        })
        .catch(err => {
            console.error('Gagal menyimpan urutan:', err);
        });
    }

    function tampilkanToastHero(pesan) {
        let toast = document.getElementById('heroReorderToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'heroReorderToast';
            toast.style.cssText = 'position:fixed;bottom:25px;right:25px;background:#10B981;color:#fff;padding:12px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.18);font-size:13.5px;font-weight:800;z-index:99999;transition:all .3s ease;display:none;align-items:center;gap:8px;';
            document.body.appendChild(toast);
        }
        toast.innerHTML = pesan;
        toast.style.display = 'flex';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        clearTimeout(window._heroToastTimer);
        window._heroToastTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => { toast.style.display = 'none'; }, 300);
        }, 2200);
    }

    document.addEventListener('DOMContentLoaded', function() {
        initHeroDragAndDrop();
    });

    function bukaModalEditFasilitas(f, galeri) {
        document.getElementById('editFasilitasId').value = f.id_fasilitas;
        document.getElementById('editFasilitasNama').value = f.nama_fasilitas || '';
        document.getElementById('editFasilitasDeskripsi').value = f.deskripsi || '';
        document.getElementById('editFasilitasUrutan').value = f.urutan || 1;
        const fileInput = document.getElementById('editFasilitasInputFile');
        if (fileInput) fileInput.value = '';

        const galeriWrap = document.getElementById('editFasilitasGaleriList');
        galeriWrap.innerHTML = '';
        const fotoCount = document.getElementById('editFasilitasFotoCount');
        
        let photos = [];
        if (Array.isArray(galeri)) {
            photos = galeri;
        } else if (typeof galeri === 'string' && galeri.trim() !== '') {
            try { photos = JSON.parse(galeri); } catch(e) { photos = [galeri]; }
        }
        if (photos.length === 0 && f.foto) {
            photos = [f.foto];
        }

        fotoCount.textContent = photos.length;

        if (photos.length === 0) {
            galeriWrap.innerHTML = '<div style="grid-column:1/-1;text-align:center;font-size:12px;color:var(--hanania-muted);padding:14px;">Belum ada foto galeri untuk fasilitas ini.</div>';
        } else {
            photos.forEach((imgPath, idx) => {
                const cleanPath = imgPath.replace(/^\/+/, '');
                const card = document.createElement('div');
                card.style.cssText = 'position:relative;background:#fff;border-radius:8px;border:1px solid var(--hanania-border);padding:6px;text-align:center;overflow:hidden;transition:all .2s;';
                card.innerHTML = `
                    <div style="width:100%;height:65px;border-radius:6px;overflow:hidden;background:#f3f3f3;margin-bottom:6px;">
                        <img src="../${cleanPath}" alt="Foto ${idx+1}" style="width:100%;height:100%;object-fit:cover;" onerror="this.src='../assets/img/klinik.jpg';">
                    </div>
                    <label style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#B72B2B;cursor:pointer;user-select:none;">
                        <input type="checkbox" name="hapus_foto[]" value="${imgPath}" onchange="toggleHapusFoto(this, this.closest('div'))" style="accent-color:#B72B2B;">
                        <span>Hapus</span>
                    </label>
                    ${idx === 0 ? '<span style="position:absolute;top:4px;left:4px;background:var(--hanania-primary);color:#fff;font-size:9px;font-weight:800;padding:1px 5px;border-radius:3px;">Cover</span>' : ''}
                `;
                galeriWrap.appendChild(card);
            });
        }

        const overlay = document.getElementById('modalEditFasilitasOverlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function toggleHapusFoto(checkbox, cardDiv) {
        if (checkbox.checked) {
            cardDiv.style.opacity = '0.45';
            cardDiv.style.borderColor = '#B72B2B';
            cardDiv.style.background = '#FDEAEA';
        } else {
            cardDiv.style.opacity = '1';
            cardDiv.style.borderColor = 'var(--hanania-border)';
            cardDiv.style.background = '#fff';
        }
    }

    function bukaModalTambahFasilitas() {
        const overlay = document.getElementById('modalTambahFasilitasOverlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function tutupModalTambahFasilitas() {
        const overlay = document.getElementById('modalTambahFasilitasOverlay');
        if (overlay) overlay.style.display = 'none';
    }

    function tutupModalEditFasilitas() {
        const overlay = document.getElementById('modalEditFasilitasOverlay');
        if (overlay) overlay.style.display = 'none';
    }
    </script>

    <!-- MODAL TAMBAH FASILITAS -->
    <div id="modalTambahFasilitasOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
        <div style="background:#fff;border-radius:16px;max-width:600px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
            <div style="padding:16px 22px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
                <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin:0;">➕ Tambah Fasilitas Baru</h4>
                <button type="button" onclick="tutupModalTambahFasilitas()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>
            <form method="POST" action="konten.php?section=tentang&tab=fasilitas" enctype="multipart/form-data" style="padding:22px;">
                <input type="hidden" name="action" value="tambah_fasilitas">
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nama Fasilitas <span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="nama_fasilitas" required class="filter-input" style="width:100%;font-size:13px;" placeholder="Contoh: Poli Gigi &amp; Mulut">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nomor Urutan Tampil</label>
                        <input type="number" name="urutan" value="1" min="1" class="filter-input" style="width:100%;font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto Utama / Sampul</label>
                        <input type="file" name="foto_fasilitas" accept="image/*" class="filter-input" style="width:100%;font-size:12px;background:#fff;">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Deskripsi Fasilitas</label>
                    <textarea name="deskripsi" rows="3" class="filter-input" style="width:100%;font-size:13px;" placeholder="Tuliskan keterangan lengkap fasilitas..."></textarea>
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Foto-Foto Tambahan Galeri (Bisa pilih beberapa sekaligus)</label>
                    <input type="file" name="foto_galeri[]" multiple accept="image/*" class="filter-input" style="width:100%;font-size:12px;background:#fff;">
                    <small style="font-size:11px;color:var(--hanania-muted);">Foto ini akan tampil di galeri popup fasilitas pada website publik.</small>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                    <button type="button" onclick="tutupModalTambahFasilitas()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Fasilitas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT FASILITAS -->
    <div id="modalEditFasilitasOverlay" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(18,12,38,0.58);z-index:9999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);">
        <div style="background:#fff;border-radius:16px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
            <div style="padding:16px 22px;border-bottom:1px solid var(--hanania-border);display:flex;justify-content:space-between;align-items:center;background:#FAF8FD;">
                <h4 style="font-size:15px;font-weight:800;color:var(--hanania-primary);margin:0;">✏️ Edit Data Fasilitas</h4>
                <button type="button" onclick="tutupModalEditFasilitas()" style="border:0;background:transparent;font-size:20px;cursor:pointer;color:var(--hanania-muted);">✕</button>
            </div>
            <form method="POST" action="konten.php?section=tentang&tab=fasilitas" enctype="multipart/form-data" style="padding:22px;">
                <input type="hidden" name="action" value="edit_fasilitas">
                <input type="hidden" name="id_fasilitas" id="editFasilitasId">
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nama Fasilitas <span style="color:#e53e3e;">*</span></label>
                    <input type="text" name="nama_fasilitas" id="editFasilitasNama" required class="filter-input" style="width:100%;font-size:13px;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Nomor Urutan</label>
                        <input type="number" name="urutan" id="editFasilitasUrutan" min="1" class="filter-input" style="width:100%;font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Ganti Foto Utama (Opsional)</label>
                        <input type="file" name="foto_fasilitas" id="editFasilitasInputFile" accept="image/*" class="filter-input" style="width:100%;font-size:12px;background:#fff;">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Deskripsi Fasilitas</label>
                    <textarea name="deskripsi" id="editFasilitasDeskripsi" rows="3" class="filter-input" style="width:100%;font-size:13px;"></textarea>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">
                        Galeri Foto Tersimpan (<span id="editFasilitasFotoCount">0</span> foto):
                    </label>
                    <div id="editFasilitasGaleriList" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(110px, 1fr));gap:10px;background:#FAF8FD;padding:12px;border-radius:10px;border:1px solid #EAE0F5;max-height:190px;overflow-y:auto;">
                        <!-- JS populates cards with delete checkbox -->
                    </div>
                    <small style="font-size:11px;color:var(--hanania-muted);">Centang "Hapus" pada foto yang ingin dihapus dari galeri.</small>
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:6px;">Tambah Foto Galeri Baru</label>
                    <input type="file" name="foto_galeri[]" multiple accept="image/*" class="filter-input" style="width:100%;font-size:12px;background:#fff;">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #f0f0f0;padding-top:14px;">
                    <button type="button" onclick="tutupModalEditFasilitas()" class="btn btn-sm" style="background:#eee;color:#444;">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:var(--hanania-primary);color:#fff;font-weight:800;">💾 Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
<script>
function tambahBarisPosisi() {
    const tbody = document.getElementById('tbodyStrukturPosisi');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="posisi_kategori[]" class="filter-input" style="width:100%;font-size:12px;font-weight:700;">
                <option value="Pimpinan & Direksi">🏛️ Pimpinan &amp; Direksi</option>
                <option value="Tim Penanggung Jawab (PJ) Mutu & Manajemen">🛡️ Tim PJ Mutu &amp; Manajemen</option>
                <option value="Unit Pelayanan Medis" selected>🩺 Unit Pelayanan Medis</option>
                <option value="Unit Penunjang Medis">🔬 Unit Penunjang Medis</option>
                <option value="Unit Administrasi & Umum">🏢 Unit Administrasi &amp; Umum</option>
                <option value="Lainnya">✨ Kategori Lainnya</option>
            </select>
        </td>
        <td>
            <input type="text" name="posisi_jabatan[]" placeholder="Contoh: Bidan Pelaksana" class="filter-input" style="width:100%;font-size:12px;font-weight:700;" required>
        </td>
        <td>
            <input type="text" name="posisi_nama[]" placeholder="Nama Lengkap & Gelar..." class="filter-input" style="width:100%;font-size:12px;" required>
        </td>
        <td style="text-align:center;">
            <input type="text" name="posisi_icon[]" value="🩺" class="filter-input" style="width:55px;text-align:center;font-size:14px;">
        </td>
        <td style="text-align:center;">
            <button type="button" onclick="hapusBarisPosisi(this)" class="btn btn-sm btn-danger" style="padding:6px 10px;font-size:12px;" title="Hapus Posisi Ini">🗑️</button>
        </td>
    `;
    tbody.appendChild(tr);
    tr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hapusBarisPosisi(btn) {
    const tr = btn.closest('tr');
    if (tr) {
        tr.style.opacity = '0';
        tr.style.transform = 'scale(0.95)';
        tr.style.transition = 'all 0.2s ease';
        setTimeout(() => tr.remove(), 200);
    }
}
</script>
</div>

<?php
adminPageEnd();
?>
