<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

// Endpoint untuk mengambil daftar Quick Questions
if (isset($_GET['action']) && $_GET['action'] === 'get_quick') {
    $quickQuestions = [];
    if ($db instanceof mysqli) {
        $res = $db->query("SELECT id_faq, pertanyaan FROM faq WHERE status = 'aktif' ORDER BY urutan ASC, id_faq ASC LIMIT 8");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $quickQuestions[] = $row;
            }
        }
    }
    echo json_encode(['success' => true, 'questions' => $quickQuestions], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = trim((string)($_POST['message'] ?? $_POST['pesan'] ?? $_GET['message'] ?? $_GET['pesan'] ?? ''));

if ($message === '') {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            $message = trim((string)($jsonData['message'] ?? $jsonData['pesan'] ?? ''));
        }
    }
}

if ($message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Pertanyaan belum diisi.']);
    exit;
}

$cleanMsg = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
$cleanMsg = trim(preg_replace('/[?!.,]/', '', $cleanMsg));

$reply = null;
$waNumber = '6288510703839';
$waDisplayNumber = '088510703839';
$waName = 'Admin CS Klinik Pratama Rumah Hanania';
$waMessageTpl = 'Halo {admin}, saya ingin menanyakan perihal: {pertanyaan}';

if ($db instanceof mysqli) {
    $qWa = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci IN ('chatbot_fallback_wa_number', 'chatbot_fallback_wa_name', 'chatbot_fallback_wa_message')");
    if ($qWa) {
        while ($rowWa = $qWa->fetch_assoc()) {
            if ($rowWa['kunci'] === 'chatbot_fallback_wa_number' && !empty(trim((string)$rowWa['nilai']))) {
                $cleaned = preg_replace('/[^0-9]/', '', trim((string)$rowWa['nilai']));
                if (str_starts_with($cleaned, '0')) {
                    $waNumber = '62' . substr($cleaned, 1);
                } elseif (str_starts_with($cleaned, '8')) {
                    $waNumber = '628' . substr($cleaned, 1);
                } elseif (str_starts_with($cleaned, '62')) {
                    $waNumber = $cleaned;
                } else {
                    $waNumber = $cleaned;
                }
                $waDisplayNumber = trim((string)$rowWa['nilai']);
            }
            if ($rowWa['kunci'] === 'chatbot_fallback_wa_name' && !empty(trim((string)$rowWa['nilai']))) {
                $waName = trim((string)$rowWa['nilai']);
            }
            if ($rowWa['kunci'] === 'chatbot_fallback_wa_message' && !empty(trim((string)$rowWa['nilai']))) {
                $waMessageTpl = trim((string)$rowWa['nilai']);
            }
        }
    }
}

// Format template pesan WhatsApp fallback
$finalWaText = $waMessageTpl;
$finalWaText = str_replace('{admin}', $waName, $finalWaText);
if (strpos($finalWaText, '{pertanyaan}') !== false) {
    $finalWaText = str_replace('{pertanyaan}', $message, $finalWaText);
} else {
    $finalWaText .= "\n\nPertanyaan: " . $message;
}
$waLink = "https://wa.me/{$waNumber}?text=" . urlencode($finalWaText);

// =========================================================================
// 1. EXACT & HIGH-PRECISION CLINICAL INTENT MATCHING (DATA RESMI KLINIK)
// =========================================================================

// A. Pertanyaan Obat / Vitamin
if (!$reply && preg_match('/\b(obat|vitamin|suplemen|tebus obat|resep dokter)\b/i', $cleanMsg) && stripos($cleanMsg, 'berobat') === false) {
    $reply = "Seluruh tarif pemeriksaan kehamilan, USG 2D, USG 3D/4D, maupun skrining kehamilan di Klinik Pratama Rumah Hanania BELUM TERMASUK obat atau vitamin.\n\nApabila dokter atau bidan meresepkan suplemen vitamin, kalsium, atau obat medis sesuai kondisi Bunda, biaya obat akan disesuaikan dengan resep yang ditebus di unit Farmasi/Apotek klinik.";
}

// B. dr. Eddy Wibowo & Status BPJS
elseif (!$reply && (stripos($cleanMsg, 'eddy') !== false || stripos($cleanMsg, 'wibowo') !== false) && stripos($cleanMsg, 'bpjs') !== false) {
    $reply = "Untuk dr. Eddy Wibowo, Sp.OG (Dokter Spesialis Kebidanan dan Kandungan), pelayanan TIDAK DAPAT menggunakan BPJS Kesehatan (khusus pembayaran UMUM / Mandiri).\n\nRincian Biaya dr. Eddy Wibowo, Sp.OG:\n• Biaya Pendaftaran: Rp 10.000\n• USG 2D: Rp 170.000 (Foto USG, Keterangan Hasil, Konsultasi)\n• USG 3D/4D: Rp 195.000 (Foto USG, Keterangan Hasil, Konsultasi, Video Rekaman USG)\n*Biaya belum termasuk obat atau vitamin.\nJadwal praktek: Hari Jumat pukul 16.00 WIB.";
}

// C. dr. Patricia & Status BPJS
elseif (!$reply && (stripos($cleanMsg, 'patricia') !== false || stripos($cleanMsg, 'meilvoriane') !== false) && stripos($cleanMsg, 'bpjs') !== false) {
    $reply = "Ya, dr. Patricia Meilvoriane BISA melayani pasien BPJS Kesehatan di Klinik Pratama Rumah Hanania.\n\nRincian Biaya Pemeriksaan USG dengan dr. Patricia Meilvoriane:\n• Biaya Pendaftaran: Rp 10.000\n• USG 2D: Rp 130.000 (Foto USG, Keterangan Hasil, Konsultasi)\n• USG 3D/4D: Rp 185.000 (Foto USG, Keterangan Hasil, Konsultasi, Video Rekaman USG)\n*Biaya belum termasuk obat atau vitamin.";
}

// D. Bidan Gita Marissa (Biaya / BPJS)
elseif (!$reply && (stripos($cleanMsg, 'gita') !== false || stripos($cleanMsg, 'bidan') !== false) && (stripos($cleanMsg, 'tarif') !== false || stripos($cleanMsg, 'biaya') !== false || stripos($cleanMsg, 'harga') !== false || stripos($cleanMsg, 'bpjs') !== false || stripos($cleanMsg, 'periksa') !== false || stripos($cleanMsg, 'konsultasi') !== false)) {
    $reply = "Rincian Biaya Pemeriksaan Kehamilan dengan Bidan di Klinik Pratama Rumah Hanania:\n\n• Tenaga Medis: Bidan Gita Marissa, S.Tr.Keb., Bdn\n• Biaya Pendaftaran: Rp 10.000\n• Biaya Tindakan Pemeriksaan & Konsultasi: Rp 90.000\n• Total Biaya: Rp 100.000 (Pendaftaran + Tindakan)\n• Status BPJS: Melayani Pasien BPJS Kesehatan\n\nFasilitas: Pemeriksaan fisik palpasi hamil, doppler detak jantung janin, dan konsultasi kebidanan. Seluruh biaya belum termasuk obat atau vitamin.";
}

// E. Skrining Kehamilan Usia Kandungan (11-13 minggu)
elseif (!$reply && (preg_match('/\b(screening|skrining|nt|preeklamsia|preeklampsia)\b/i', $cleanMsg)) && (stripos($cleanMsg, 'usia') !== false || stripos($cleanMsg, 'minggu') !== false || stripos($cleanMsg, 'kapan') !== false || stripos($cleanMsg, 'syarat') !== false || stripos($cleanMsg, 'kandungan') !== false)) {
    $reply = "Layanan Skrining Kehamilan (Pemeriksaan NT dan Deteksi Risiko Pre-eklampsia) dikhususkan untuk usia kandungan 11 hingga 13 minggu (Trimester 1 akhir).\n\nPada rentang usia 11–13 minggu ini, ketebalan cairan di tengkuk janin (Nuchal Translucency / NT) dan risiko pre-eklampsia dapat diukur secara paling akurat untuk mendeteksi kelainan kromosom sejak awal.";
}

// F. Skrining Kehamilan Biaya & Paket
elseif (!$reply && (preg_match('/\b(screening|skrining|nt|preeklamsia|preeklampsia)\b/i', $cleanMsg))) {
    $reply = "Layanan Skrining Kehamilan di Klinik Pratama Rumah Hanania (Khusus Usia Kandungan 11–13 Minggu):\n\nMeliputi pemeriksaan Nuchal Translucency (NT) dan deteksi risiko pre-eklampsia sejak dini.\n\nRincian Biaya:\n• Biaya Pendaftaran: Rp 10.000\n• Biaya Tindakan: Rp 250.000\n• PROMO SPESIAL: GRATIS USG 4D!\n\nCatatan: Seluruh biaya di atas belum termasuk obat atau vitamin.";
}

// G. Fasilitas yang Didapat saat USG
elseif (!$reply && (stripos($cleanMsg, 'fasilitas') !== false || stripos($cleanMsg, 'dapat apa') !== false || stripos($cleanMsg, 'apa yang didapat') !== false || stripos($cleanMsg, 'apa saja yang didapat') !== false || stripos($cleanMsg, 'video usg') !== false) && stripos($cleanMsg, 'usg') !== false) {
    $reply = "Fasilitas yang Anda dapatkan saat melakukan pemeriksaan USG di Klinik Pratama Rumah Hanania:\n\n1. Pada USG 2D:\n• Cetak Foto USG janin\n• Lembar Keterangan Hasil Pemeriksaan Dokter\n• Konsultasi Medis Lengkap dengan Dokter\n\n2. Pada USG 3D / 4D:\n• Cetak Foto USG janin\n• Video Hasil Rekaman USG janin (dapat disimpan di smartphone)\n• Lembar Keterangan Hasil Pemeriksaan Dokter\n• Konsultasi Medis Lengkap dengan Dokter\n\nCatatan: Seluruh pemeriksaan belum termasuk obat atau vitamin.";
}

// H. Biaya USG 3D / 4D
elseif (!$reply && (preg_match('/\b(4d|3d|empat dimensi|tiga dimensi)\b/i', $cleanMsg)) && (stripos($cleanMsg, 'biaya') !== false || stripos($cleanMsg, 'harga') !== false || stripos($cleanMsg, 'tarif') !== false || stripos($cleanMsg, 'berapa') !== false || stripos($cleanMsg, 'usg') !== false)) {
    $reply = "Rincian Biaya USG 3D / 4D di Klinik Pratama Rumah Hanania:\n\n1. dr. Eddy Wibowo, Sp.OG (Dokter Spesialis Kebidanan & Kandungan):\n• Tindakan USG 3D/4D: Rp 195.000\n• Biaya Pendaftaran: Rp 10.000\n• Pembayaran: UMUM (Tidak bisa BPJS)\n\n2. dr. Patricia Meilvoriane (Dokter Umum):\n• Tindakan USG 3D/4D: Rp 185.000\n• Biaya Pendaftaran: Rp 10.000\n• Pembayaran: Melayani Pasien BPJS\n\nFasilitas: Cetak Foto USG, Lembar Keterangan Hasil, Konsultasi Dokter, dan Video Rekaman USG. Belum termasuk obat atau vitamin.";
}

// I. Biaya USG 2D
elseif (!$reply && (preg_match('/\b(2d|dua dimensi)\b/i', $cleanMsg)) && (stripos($cleanMsg, 'biaya') !== false || stripos($cleanMsg, 'harga') !== false || stripos($cleanMsg, 'tarif') !== false || stripos($cleanMsg, 'berapa') !== false || stripos($cleanMsg, 'usg') !== false)) {
    $reply = "Rincian Biaya USG 2D di Klinik Pratama Rumah Hanania:\n\n1. dr. Eddy Wibowo, Sp.OG (Dokter Spesialis Kebidanan & Kandungan):\n• Tindakan USG 2D: Rp 170.000\n• Biaya Pendaftaran: Rp 10.000\n• Pembayaran: UMUM (Tidak bisa BPJS)\n\n2. dr. Patricia Meilvoriane (Dokter Umum):\n• Tindakan USG 2D: Rp 130.000\n• Biaya Pendaftaran: Rp 10.000\n• Pembayaran: Melayani Pasien BPJS\n\nFasilitas: Cetak Foto USG, Lembar Keterangan Hasil, dan Konsultasi Dokter. Belum termasuk obat atau vitamin.";
}

// J. Dokter Spesialis Kandungan / Sp.OG / dr. Eddy Wibowo
elseif (!$reply && (stripos($cleanMsg, 'spog') !== false || stripos($cleanMsg, 'sp.og') !== false || stripos($cleanMsg, 'kandungan') !== false || stripos($cleanMsg, 'obgyn') !== false || stripos($cleanMsg, 'eddy') !== false || stripos($cleanMsg, 'wibowo') !== false)) {
    $reply = "Dokter Spesialis Kebidanan dan Kandungan (Sp.OG) yang tersedia di Klinik Pratama Rumah Hanania adalah Dr. Eddy Wibowo, Sp.OG.\n\nKetentuan Pelayanan:\n• Melayani USG 2D, USG 3D/4D, dan Konsultasi Kebidanan & Kandungan.\n• Pembayaran: UMUM (Tidak dapat menggunakan BPJS).\n• Biaya: Pendaftaran Rp 10.000, USG 2D Rp 170.000, USG 3D/4D Rp 195.000.\n• Biaya belum termasuk obat atau vitamin.\n• Jadwal Praktek: Hari Jumat pukul 16.00 WIB.";
}

// K. Status BPJS Pemeriksaan Kehamilan / Umum
elseif (!$reply && stripos($cleanMsg, 'bpjs') !== false && (stripos($cleanMsg, 'kehamilan') !== false || stripos($cleanMsg, 'usg') !== false || stripos($cleanMsg, 'dokter') !== false || stripos($cleanMsg, 'bisa bpjs') !== false)) {
    $reply = "Ketentuan Pelayanan BPJS untuk Kehamilan & USG di Klinik Pratama Rumah Hanania:\n\n1. dr. Patricia Meilvoriane (Dokter Umum): BISA melayani BPJS.\n2. Bidan Gita Marissa, S.Tr.Keb., Bdn: BISA melayani BPJS.\n3. dr. Eddy Wibowo, Sp.OG (Spesialis Kandungan): TIDAK BISA menggunakan BPJS (khusus pembayaran UMUM / Mandiri).\n\nUntuk pasien BPJS, mohon membawa kartu BPJS Kesehatan dan identitas diri saat berkunjung.";
}

// L. Jadwal Dokter & Bidan
elseif (!$reply && (stripos($cleanMsg, 'jadwal') !== false || stripos($cleanMsg, 'praktek') !== false || stripos($cleanMsg, 'praktik') !== false)) {
    $reply = "Jadwal Pelayanan Dokter & Bidan di Klinik Pratama Rumah Hanania:\n\n• dr. Patricia Mellroviane (Dokter Umum): Senin – Sabtu (08.00–12.00 & 16.00–20.00 WIB)\n• dr. Mona Hernika (Dokter Umum): Senin – Sabtu (13.00–16.00 WIB)\n• drg. Fatmasari Pangesti (Dokter Gigi): Senin, Rabu, Jumat (09.00–12.00 WIB)\n• Dr. Eddy Wibowo, Sp.OG (Spesialis Kandungan): Hari Jumat pukul 16.00 WIB\n• Pelayanan KIA & Persalinan (Bidan): Buka Setiap Hari (Persalinan 24 Jam)\n\nAnda dapat melihat jadwal terupdate per hari ini di menu 'Jadwal' pada website.";
}

// M. Cara Mendaftar Antrean Online & BPJS
elseif (!$reply && (stripos($cleanMsg, 'cara daftar') !== false || stripos($cleanMsg, 'bagaimana daftar') !== false || stripos($cleanMsg, 'mendaftar') !== false || stripos($cleanMsg, 'alur daftar') !== false || stripos($cleanMsg, 'booking') !== false || stripos($cleanMsg, 'ambil antrean') !== false || stripos($cleanMsg, 'ambil nomor') !== false)) {
    $reply = "Cara Mendaftar Antrean di Klinik Pratama Rumah Hanania:\n\n1. Pasien Umum / Mandiri:\n• Klik tombol 'Daftar Sekarang' di website ini.\n• Isi data identitas pasien (bisa untuk diri sendiri atau keluarga).\n• Pilih Poli dan dokter/bidan yang diinginkan.\n• Pilih hari/tanggal kunjungan, sistem otomatis menerbitkan nomor antrean online.\n\n2. Pasien BPJS Kesehatan:\n• Sesuai regulasi BPJS, antrean faskes online dilakukan melalui aplikasi resmi Mobile JKN.\n• Pilih faskes 'Klinik Pratama Rumah Hanania' di menu Pendaftaran Pelayanan.";
}

// N. Jam Operasional & Layanan 24 Jam
elseif (!$reply && (stripos($cleanMsg, 'jam operasional') !== false || stripos($cleanMsg, 'jam buka') !== false || stripos($cleanMsg, 'buka jam') !== false || stripos($cleanMsg, 'buka sampai') !== false || stripos($cleanMsg, '24 jam') !== false)) {
    $reply = "Jam Operasional Pelayanan Klinik Pratama Rumah Hanania:\n\n• Poli Rawat Jalan (Umum, Gigi, KIA): Buka setiap hari pukul 08.00 – 20.00 WIB\n• Pelayanan Persalinan & Bidan Siaga: Melayani 24 Jam Penuh\n• Pelayanan Kegawatdaruratan Dasar: Siaga 24 Jam\n\nUntuk konfirmasi persalinan darurat, hubungi WhatsApp: 0857 1417 8178.";
}

// O. Lokasi, Alamat & Kontak WhatsApp
elseif (!$reply && (stripos($cleanMsg, 'lokasi') !== false || stripos($cleanMsg, 'alamat') !== false || stripos($cleanMsg, 'dimana') !== false || stripos($cleanMsg, 'maps') !== false || stripos($cleanMsg, 'kontak') !== false || stripos($cleanMsg, 'nomor telepon') !== false || stripos($cleanMsg, 'no telepon') !== false || stripos($cleanMsg, 'wa') !== false)) {
    $reply = "Lokasi & Kontak Resmi Klinik Pratama Rumah Hanania:\n\n📍 Alamat: Kp. Situpete RT 03 / RW 08, Kelurahan Sukadamai, Kecamatan Tanah Sareal, Kota Bogor, Jawa Barat 16165.\n📞 Telepon / WhatsApp: 0857 1417 8178 / 0885 1070 3839\n📷 Instagram: @rumahhanania_\n🌐 Rute Google Maps dapat dibuka langsung melalui tautan di bagian footer website.";
}

// P. Aplikasi Mobile JKN
elseif (!$reply && (stripos($cleanMsg, 'mobile jkn') !== false || stripos($cleanMsg, 'jkn') !== false)) {
    $reply = "Aplikasi Mobile JKN digunakan bagi peserta BPJS Kesehatan untuk mengambil antrean faskes tingkat pertama secara terpadu.\n\nUnduh aplikasi Mobile JKN melalui Google Play Store atau App Store, lalu pilih Faskes 'Klinik Pratama Rumah Hanania' untuk mengambil antrean online BPJS Anda.";
}

// =========================================================================
// 2. QUERY DATABASE FAQ MATCHING (UNTUK PERTANYAAN LAINNYA)
// =========================================================================
if (!$reply && $db instanceof mysqli) {
    // Exact Match
    $stmtExact = $db->prepare("SELECT jawaban FROM faq WHERE LOWER(TRIM(pertanyaan)) = LOWER(?) AND status = 'aktif' LIMIT 1");
    if ($stmtExact) {
        $stmtExact->bind_param('s', $message);
        $stmtExact->execute();
        $res = $stmtExact->get_result();
        if ($row = $res->fetch_assoc()) {
            $reply = $row['jawaban'];
        }
        $stmtExact->close();
    }

    // Keyword / scoring match
    if (!$reply) {
        $stmtFaq = $db->query("SELECT id_faq, pertanyaan, jawaban, kata_kunci FROM faq WHERE status = 'aktif' ORDER BY urutan ASC, id_faq ASC");
        if ($stmtFaq) {
            $bestMatch = null;
            $maxScore = 0;

            while ($row = $stmtFaq->fetch_assoc()) {
                $score = 0;
                $pertanyaanDb = function_exists('mb_strtolower') ? mb_strtolower($row['pertanyaan'], 'UTF-8') : strtolower($row['pertanyaan']);
                $kataKunciDb = function_exists('mb_strtolower') ? mb_strtolower($row['kata_kunci'] ?? '', 'UTF-8') : strtolower($row['kata_kunci'] ?? '');

                // Cek kesamaan substring
                if (stripos($cleanMsg, $pertanyaanDb) !== false || stripos($pertanyaanDb, $cleanMsg) !== false) {
                    $score += 20;
                }

                // Cek kata-kunci
                if (!empty($kataKunciDb)) {
                    $keys = array_map('trim', explode(',', $kataKunciDb));
                    foreach ($keys as $k) {
                        if ($k !== '') {
                            if ($cleanMsg === $k) {
                                $score += 20;
                            } elseif (strlen($k) <= 3) {
                                if (preg_match('/\b' . preg_quote($k, '/') . '\b/i', $cleanMsg)) {
                                    $score += 10;
                                }
                            } else {
                                if (stripos($cleanMsg, $k) !== false) {
                                    $score += 8;
                                }
                            }
                        }
                    }
                }

                // Cek kecocokan perkata (abaikan stop words umum bahasa Indonesia)
                $stopWords = ['apakah', 'klinik', 'pratama', 'rumah', 'hanania', 'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'ini', 'itu', 'ada', 'bisa', 'saya', 'kami', 'anda', 'dengan', 'pada', 'atau', 'apa', 'saja'];
                $words = explode(' ', $cleanMsg);
                foreach ($words as $w) {
                    if (strlen($w) >= 3 && !in_array($w, $stopWords, true)) {
                        if (stripos($pertanyaanDb, $w) !== false || stripos($kataKunciDb, $w) !== false) {
                            $score += 3;
                        }
                    }
                }

                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestMatch = $row['jawaban'];
                }
            }

            if ($maxScore >= 6) {
                $reply = $bestMatch;
            }
        }
    }
}

// 3. Salam pembuka umum
if (!$reply && preg_match('/^(halo|hai|hi|hey|halo hanabot|selamat pagi|selamat siang|selamat sore|selamat malam|assalamualaikum|pagi|siang|sore|malam)$/i', $cleanMsg)) {
    $reply = 'Halo! Saya HanaBot, asisten virtual resmi Klinik Pratama Rumah Hanania. Ada yang bisa saya bantu terkait informasi layanan kehamilan, USG 2D/3D/4D, jadwal dokter, tarif, BPJS, atau alur pendaftaran?';
}

// 4. Fallback jika pertanyaan di luar kamus resmi (Strict: Never hallucinate!)
if (!$reply) {
    $reply = "Mohon maaf, pertanyaan Anda belum ada di data resmi HanaBot atau membutuhkan informasi medis/administrasi personal.\n\nSilakan langsung hubungi Admin Klinik kami melalui WhatsApp untuk dibantu dengan cepat.";
    echo json_encode([
        'success' => true,
        'message' => $reply,
        'is_fallback' => true,
        'whatsapp_url' => $waLink,
        'whatsapp_number' => $waDisplayNumber,
        'whatsapp_name' => $waName
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $reply,
    'is_fallback' => false
], JSON_UNESCAPED_UNICODE);
