<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isLogin = (
    (isset($_SESSION['id_user']) && (($_SESSION['role'] ?? '') === 'pasien'))
    || (isset($_SESSION['user']['id_user']) && (($_SESSION['user']['role'] ?? '') === 'pasien'))
);

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user']['id_user'] ?? $_SESSION['user_id'] ?? 0);

$namaPasien = $_SESSION['nama_lengkap'] ?? $_SESSION['user']['nama_lengkap'] ?? $_SESSION['user']['nama'] ?? '';
$nikUser = $_SESSION['nik'] ?? '';
$namaIbuKandungUser = $_SESSION['nama_ibu_kandung'] ?? '';
$namaSuamiUser = $_SESSION['nama_suami'] ?? '';
$namaAyahKandungUser = $_SESSION['nama_ayah_kandung'] ?? '';
$emailUser = $_SESSION['email'] ?? '';
$teleponUser = $_SESSION['no_telepon'] ?? '';
$alamatUser = $_SESSION['alamat'] ?? '';
$tanggalLahirUser = $_SESSION['tanggal_lahir'] ?? '';
$jenisKelaminUser = $_SESSION['jenis_kelamin'] ?? '';
$pekerjaanUser = $_SESSION['pekerjaan'] ?? '';
$statusPerkawinanUser = $_SESSION['status_perkawinan'] ?? '';
$pendidikanUser = $_SESSION['pendidikan_terakhir'] ?? '';
$agamaUser = $_SESSION['agama'] ?? '';
$adaRujukanUser = $_SESSION['ada_rujukan'] ?? 'Tidak';
$asalRujukanUser = $_SESSION['asal_rujukan'] ?? '';

if ($idUser > 0) {
    $stmt = $db->prepare("
        SELECT nik, nama_lengkap, nama_ibu_kandung, nama_suami, nama_ayah_kandung, tanggal_lahir, jenis_kelamin, no_telepon, email, alamat,
               pekerjaan, status_perkawinan, pendidikan_terakhir, agama, ada_rujukan, asal_rujukan
        FROM pasien
        WHERE id_user = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = ($res ? $res->fetch_assoc() : null)) {
            $nikUser = $row['nik'] ?? $nikUser;
            $namaPasien = $row['nama_lengkap'] ?? $namaPasien;
            $namaIbuKandungUser = $row['nama_ibu_kandung'] ?? $namaIbuKandungUser;
            $namaSuamiUser = $row['nama_suami'] ?? $namaSuamiUser;
            $namaAyahKandungUser = $row['nama_ayah_kandung'] ?? $namaAyahKandungUser;
            $tanggalLahirUser = $row['tanggal_lahir'] ?? $tanggalLahirUser;
            $jenisKelaminUser = $row['jenis_kelamin'] ?? $jenisKelaminUser;
            $teleponUser = $row['no_telepon'] ?? $teleponUser;
            $emailUser = $row['email'] ?? $emailUser;
            $alamatUser = $row['alamat'] ?? $alamatUser;
            $pekerjaanUser = $row['pekerjaan'] ?? $pekerjaanUser;
            $statusPerkawinanUser = $row['status_perkawinan'] ?? $statusPerkawinanUser;
            $pendidikanUser = $row['pendidikan_terakhir'] ?? $pendidikanUser;
            $agamaUser = $row['agama'] ?? $agamaUser;
            $adaRujukanUser = $row['ada_rujukan'] ?? $adaRujukanUser;
            $asalRujukanUser = $row['asal_rujukan'] ?? $asalRujukanUser;
        }
        $stmt->close();
    }
}

$daftarKeluarga = [];
if ($isLogin && $idUser > 0) {
    $qK = $db->prepare("SELECT * FROM keluarga_pasien WHERE id_user = ? ORDER BY id_keluarga DESC");
    if ($qK) {
        $qK->bind_param('i', $idUser);
        $qK->execute();
        $resK = $qK->get_result();
        while ($rK = $resK->fetch_assoc()) {
            $daftarKeluarga[] = $rK;
        }
        $qK->close();
    }
}

$dokterTerpilih = (int)($_GET['dokter'] ?? 0);
$bidanTerpilih = (int)($_GET['bidan'] ?? 0);
$poliTerpilih = (int)($_GET['poli'] ?? 0);
$tanggalTerpilih = $_GET['tanggal'] ?? date('Y-m-d');
$layananTerpilih = strtolower(trim($_GET['layanan'] ?? ''));

// Jika dari halaman layanan USG, otomatis pilih USG, Poli Spesialis Kebidanan & Dokter Spesialis (Dr. Eddy Sp.OG)
if ($layananTerpilih === 'usg') {
    if ($poliTerpilih <= 0) {
        $poliTerpilih = 4; // Poli Spesialis Kebidanan dan Kandungan
    }
    if ($dokterTerpilih <= 0 && $bidanTerpilih <= 0) {
        $dokterTerpilih = 4; // Dr. Eddy Wibowo, Sp.OG
    }
}

// Jika dari halaman layanan Persalinan 24 Jam
if ($layananTerpilih === 'persalinan' || $layananTerpilih === 'bersalin') {
    if ($poliTerpilih <= 0) {
        $poliTerpilih = 5; // Persalinan 24 Jam
    }
}

// Ambil Poli jika dokter/bidan dipilih dari kartu dokter
if ($poliTerpilih <= 0 && $dokterTerpilih > 0) {
    $stmtP = $db->prepare("SELECT id_poli FROM jadwal_dokter WHERE id_dokter = ? AND status = 'tersedia' LIMIT 1");
    if ($stmtP) {
        $stmtP->bind_param('i', $dokterTerpilih);
        $stmtP->execute();
        $rowP = $stmtP->get_result()->fetch_assoc();
        $poliTerpilih = (int)($rowP['id_poli'] ?? 0);
        $stmtP->close();
    }
}
if ($poliTerpilih <= 0 && $bidanTerpilih > 0) {
    $stmtP = $db->prepare("SELECT id_poli FROM jadwal_bidan WHERE id_bidan = ? AND status = 'tersedia' LIMIT 1");
    if ($stmtP) {
        $stmtP->bind_param('i', $bidanTerpilih);
        $stmtP->execute();
        $rowP = $stmtP->get_result()->fetch_assoc();
        $poliTerpilih = (int)($rowP['id_poli'] ?? 0);
        $stmtP->close();
    }
}

$dataPoli = [];
$qPoli = $db->query("
    SELECT id_poli, nama_poli, is_24jam, status_operasional 
    FROM poli 
    WHERE status = 'aktif' 
    ORDER BY CASE WHEN is_24jam = 1 OR id_poli = 5 OR nama_poli LIKE '%Persalinan%' THEN 0 ELSE 1 END, id_poli ASC
");
if ($qPoli) {
    while ($row = $qPoli->fetch_assoc()) {
        $dataPoli[] = $row;
    }
}

$dataTindakanList = [];
$qTindakan = $db->query("SELECT * FROM tindakan_pendaftaran WHERE status = 'aktif' ORDER BY urutan ASC, id_tindakan ASC");
if ($qTindakan && $qTindakan->num_rows > 0) {
    while ($row = $qTindakan->fetch_assoc()) {
        $dataTindakanList[] = $row;
    }
}

$formFieldConfig = [];
$qFields = $db->query("SELECT * FROM pengaturan_form_pendaftaran");
if ($qFields && $qFields->num_rows > 0) {
    while ($rf = $qFields->fetch_assoc()) {
        $formFieldConfig[$rf['nama_field']] = $rf;
    }
}
function isFieldActive($fieldName, $default = true) {
    global $formFieldConfig;
    if (!isset($formFieldConfig[$fieldName])) return $default;
    return ($formFieldConfig[$fieldName]['status'] === 'aktif');
}
function isFieldRequired($fieldName, $default = false) {
    global $formFieldConfig;
    if (!isset($formFieldConfig[$fieldName])) return $default;
    return ((int)$formFieldConfig[$fieldName]['wajib'] === 1);
}
function getFieldLabel($fieldName, $default = '') {
    global $formFieldConfig;
    return !empty($formFieldConfig[$fieldName]['label_field']) ? $formFieldConfig[$fieldName]['label_field'] : $default;
}

$scheduleMap = [];
$qSchedule = $db->query("
    SELECT id_dokter AS id_tenaga, id_poli, hari, jam_mulai, jam_selesai, 'Dokter' AS jenis
    FROM jadwal_dokter WHERE status = 'tersedia'
    UNION ALL
    SELECT id_bidan AS id_tenaga, id_poli, hari, jam_mulai, jam_selesai, 'Bidan' AS jenis
    FROM jadwal_bidan WHERE status = 'tersedia'
    ORDER BY jenis, id_tenaga, FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jam_mulai
");
if ($qSchedule) {
    while ($row = $qSchedule->fetch_assoc()) {
        $scheduleMap[$row['jenis'] . '-' . (int)$row['id_tenaga']][] = $row;
    }
}

$dataTenaga = [];
// Kecualikan tenaga medis nonaktif, namun tampilkan status 'cuti' dengan penanda khusus
$qDokter = $db->query("SELECT id_dokter AS id, nama_dokter AS nama, status, COALESCE(id_poli,0) AS id_poli, spesialisasi, foto FROM dokter WHERE status != 'nonaktif'");
if ($qDokter) {
    while ($row = $qDokter->fetch_assoc()) {
        $key = 'Dokter-' . (int)$row['id'];
        if (!empty($scheduleMap[$key])) {
            $row['jenis'] = 'Dokter';
            $row['jadwal_db'] = $scheduleMap[$key];
            $dataTenaga[] = $row;
        }
    }
}

$qBidan = $db->query("SELECT id_bidan AS id, nama_bidan AS nama, status, COALESCE(id_poli,0) AS id_poli, 'Bidan' AS spesialisasi, foto FROM bidan WHERE status != 'nonaktif'");
if ($qBidan) {
    while ($row = $qBidan->fetch_assoc()) {
        $key = 'Bidan-' . (int)$row['id'];
        if (!empty($scheduleMap[$key])) {
            $row['jenis'] = 'Bidan';
            $row['jadwal_db'] = $scheduleMap[$key];
            $dataTenaga[] = $row;
        }
    }
}

// Urutkan tenaga medis: Dokter terlebih dahulu, lalu Bidan, dan alfabet nama
usort($dataTenaga, function ($left, $right) {
    $typeOrder = ($left['jenis'] === 'Dokter' ? 0 : 1) <=> ($right['jenis'] === 'Dokter' ? 0 : 1);
    if ($typeOrder !== 0) return $typeOrder;
    return $left['nama'] <=> $right['nama'];
});

// Temukan tenaga medis terpilih jika ditentukan di parameter URL (?dokter=... atau ?bidan=...)
$selectedStaffObj = null;
if ($dokterTerpilih > 0 || $bidanTerpilih > 0) {
    foreach ($dataTenaga as $t) {
        if ($dokterTerpilih > 0 && $t['jenis'] === 'Dokter' && (int)$t['id'] === $dokterTerpilih) {
            $selectedStaffObj = $t;
            break;
        }
        if ($bidanTerpilih > 0 && $t['jenis'] === 'Bidan' && (int)$t['id'] === $bidanTerpilih) {
            $selectedStaffObj = $t;
            break;
        }
    }
}

$jadwalTenagaPilihan = $selectedStaffObj ? ($selectedStaffObj['jadwal_db'] ?? []) : [];
$hariPraktikTersedia = [];
foreach ($jadwalTenagaPilihan as $j) {
    $hariPraktikTersedia[] = $j['hari'];
}
$hariPraktikTersedia = array_values(array_unique($hariPraktikTersedia));

if (!empty($hariPraktikTersedia) && !isset($_GET['tanggal'])) {
    $hariMapIndo = ['Minggu' => 0, 'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6];
    $targetDays = [];
    foreach ($hariPraktikTersedia as $h) {
        if (isset($hariMapIndo[$h])) $targetDays[] = $hariMapIndo[$h];
    }
    if (!empty($targetDays)) {
        for ($i = 0; $i <= 7; $i++) {
            $checkTs = strtotime("+$i days");
            $w = (int)date('w', $checkTs);
            if (in_array($w, $targetDays, true)) {
                $tanggalTerpilih = date('Y-m-d', $checkTs);
                break;
            }
        }
    }
}

$pesan = $_SESSION['pesan_daftar'] ?? '';
unset($_SESSION['pesan_daftar']);

$hariIndonesiaLabel = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$hariIniLabel = ($hariIndonesiaLabel[date('l')] ?? date('l')) . ', ' . date('d-m-Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pendaftaran Pasien Online - Klinik Pratama Rumah Hanania</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family:'Plus Jakarta Sans',Arial,sans-serif;
    background:#F9F7FC;
    color:#352D3E;
    overflow-x:hidden;
    min-height:100vh;
    display:flex;
    flex-direction:column;
}
:root{
    --purple:#cea2fd;
    --purple-dark:#6c34a3;
    --purple-light:#eedafc;
    --button:#8e44ad;
    --button-hover:#7d389c;
    --soft:#f8f2fd;
    --soft-2:#fcfaff;
    --border:#e6d3fb;
    --muted:#675F73;
    --white:#fff;
    --danger:#dc2626;
    --success:#16a34a;
}
a{text-decoration:none;color:inherit}
button,input,select,textarea{font-family:inherit}

.page-header{
    padding:45px 20px 75px;
    background:linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);
    color:#2B1B48;
    text-align:center;
    position:relative;
    overflow:hidden;
    border-bottom:1px solid #E5D5FA;
}
.page-header::before{
    content:"";
    position:absolute;
    top:-50px;right:-50px;
    width:220px;height:220px;
    background:rgba(106,34,167,0.04);
    border-radius:50%;
}
.header-inner{
    max-width:820px;
    margin:auto;
    position:relative;
    z-index:2;
}
.header-inner h1{
    font-size:32px;
    font-weight:900;
    color:#2B1B48;
    margin-bottom:10px;
    letter-spacing:-0.5px;
}
.header-inner p{
    font-size:14.5px;
    line-height:1.7;
    color:#5D4D73;
}

/* PROGRESS BAR */
.registration-progress{
    width:min(650px, calc(100% - 32px));
    margin:25px auto 0;
    display:flex;
    justify-content:space-between;
    position:relative;
    z-index:2;
}
.registration-progress::before{
    content:"";
    position:absolute;
    top:20px;
    left:10%;
    right:10%;
    height:3px;
    background:#D8C5F2;
    z-index:1;
}
.progress-step{
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
    position:relative;
    z-index:2;
    font-size:12px;
    font-weight:700;
    color:#736488;
}
.progress-number{
    width:42px;
    height:42px;
    border-radius:50%;
    background:#fff;
    color:#6A22A7;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:15px;
    font-weight:800;
    border:2px solid #D8C5F2;
    box-shadow:0 4px 14px rgba(106,34,167,.08);
    transition:all .3s;
}
.progress-step.active{
    color:#6A22A7;
}
.progress-step.active .progress-number{
    background:#6A22A7;
    color:#fff;
    border-color:#6A22A7;
    outline:4px solid rgba(106,34,167,.18);
    box-shadow:0 4px 20px rgba(106,34,167,.3);
}
.progress-step.done .progress-number{
    background:#10b981;
    border-color:#10b981;
    color:#fff;
}
.progress-step.done{
    color:#10b981;
}

/* CONTAINER */
.container{
    max-width:940px;
    width:calc(100% - 32px);
    margin:24px auto 70px;
    position:relative;
    z-index:10;
    flex:1;
    scroll-margin-top:100px;
}
.registration-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:22px;
    box-shadow:0 20px 50px rgba(116,52,169,.08);
    overflow:hidden;
    scroll-margin-top:100px;
}

.step-panel{
    display:none;
    padding:34px;
    scroll-margin-top:100px;
}
.step-panel.active{
    display:block;
    animation:stepFade .25s ease;
}
@keyframes stepFade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

.section-card{
    margin-bottom:28px;
    scroll-margin-top:100px;
}
.section-heading{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    margin-bottom:18px;
    border-bottom:1px solid #F3EDF9;
    padding-bottom:12px;
}
.section-heading h2{
    font-size:20px;
    font-weight:800;
    color:var(--purple-dark);
}
.section-desc{
    font-size:13px;
    color:var(--muted);
    margin-top:4px;
    line-height:1.6;
}
.section-badge{
    padding:6px 14px;
    border-radius:10px;
    background:var(--soft);
    color:var(--purple-dark);
    font-size:11.5px;
    font-weight:800;
    border:1px solid var(--border);
    white-space:nowrap;
    flex-shrink:0;
    min-width:max-content;
    display:inline-flex;
    align-items:center;
    line-height:1.2;
}

/* FORM GRID */
.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.form-group{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.form-group.full{
    grid-column:1/-1;
}

/* Penataan Responsif Tanggal, Sesi & Keluhan */
@media (min-width: 769px) {
    #group_poli { grid-column: 1; grid-row: 1; }
    #group_tindakan { grid-column: 2; grid-row: 1; }
    #group_poli.poli-full { grid-column: 1 / -1; }
    /* Desktop: Tanggal di sebelah kiri, Sesi di sebelah kanan */
    #group_tanggal { grid-column: 1; grid-row: 2; }
    #group_sesi { grid-column: 2; grid-row: 2; }
    #group_keluhan, .form-group.full { grid-column: 1 / -1; grid-row: 3; }
}
@media (max-width: 768px) {
    /* Mobile: Urutan logis vertikal langkah demi langkah */
    #group_poli { order: 1; }
    #group_tindakan { order: 2; }
    #group_tanggal { order: 3; }
    #group_sesi { order: 4; }
    #group_keluhan, .form-group.full { order: 5; }
}
.form-group label{
    font-size:12.5px;
    font-weight:700;
    color:#4B4453;
}
.form-group input, .form-group select, .form-group textarea{
    width:100%;
    box-sizing:border-box;
    padding:11px 14px;
    border:1.5px solid var(--border);
    border-radius:12px;
    font:inherit;
    font-size:13px;
    background:#fff;
    color:inherit;
    transition:border .15s, box-shadow .15s;
}
input[type="date"]{
    width:100% !important;
    max-width:100% !important;
    box-sizing:border-box !important;
    min-height:46px;
    line-height:normal;
    background-color:#fff;
    cursor:pointer;
    display:flex;
    align-items:center;
}
input[type="date"]::-webkit-date-and-time-value{
    text-align:left;
    min-height:1.4em;
    width:100%;
}
input[type="date"]::-webkit-calendar-picker-indicator{
    cursor:pointer;
    opacity:0.65;
    padding:4px;
    margin-left:auto;
    transition:opacity .15s;
    font-size:15px;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover{
    opacity:1;
}
.form-group textarea{
    resize:vertical;
    min-height:85px;
    width:100%;
    box-sizing:border-box;
    line-height:1.5;
    word-break:break-word;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus{
    outline:none;
    border-color:var(--purple);
    box-shadow:0 0 0 3px rgba(206,162,253,.35);
}

/* PATIENT CHOICE */
.patient-choice{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-bottom:20px;
}
.choice-card{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px 16px;
    border:1.5px solid var(--border);
    border-radius:14px;
    cursor:pointer;
    background:#fff;
    transition:all .2s;
}
.choice-card:hover{
    border-color:var(--purple);
    background:var(--soft-2);
}
.choice-card.active-choice{
    border-color:var(--button);
    background:var(--soft);
}
.choice-card input[type="radio"]{
    accent-color:var(--button);
    width:18px;
    height:18px;
}
.choice-title{
    display:block;
    font-size:13.5px;
    font-weight:800;
    color:var(--purple-dark);
}
.choice-desc{
    display:block;
    font-size:11.5px;
    color:var(--muted);
    margin-top:2px;
    line-height:1.4;
}

/* MEDICAL CARDS (DOKTER & BIDAN) */
.medical-list{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(270px, 1fr));
    gap:16px;
    margin-top:14px;
}
.medical-card{
    border:2px solid var(--border);
    border-radius:16px;
    padding:18px;
    background:#fff;
    cursor:pointer;
    transition:all .25s ease;
    position:relative;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}
.medical-card:hover{
    border-color:var(--purple);
    transform:translateY(-2px);
    box-shadow:0 10px 24px rgba(116,52,169,.1);
}
.medical-card.selected{
    border-color:var(--button) !important;
    background:linear-gradient(135deg, #FAF4FF, #F2E3FD) !important;
    box-shadow:0 0 0 3px rgba(206,162,253,.65), 0 10px 28px rgba(142,68,173,.18) !important;
    transform:translateY(-2px);
}
.medical-card.selected .selected-indicator{
    display:inline-flex !important;
}
.selected-indicator{
    display:none;
    align-items:center;
    gap:4px;
    font-size:11px;
    font-weight:800;
    color:#fff;
    background:var(--button);
    padding:4px 10px;
    border-radius:8px;
    position:absolute;
    top:12px;
    right:12px;
    box-shadow:0 2px 8px rgba(142,68,173,.3);
}

.medical-card.is-cuti{
    background:#FFF5F5 !important;
    border-color:#FCA5A5 !important;
    cursor:not-allowed !important;
    opacity:0.8;
}
.badge-cuti{
    position:absolute;
    top:12px;
    right:12px;
    background:#FEE2E2;
    color:#DC2626;
    font-size:10.5px;
    font-weight:800;
    padding:4px 9px;
    border-radius:8px;
    border:1px solid #FECACA;
    display:inline-flex;
    align-items:center;
    gap:4px;
}

.medical-top{
    display:flex;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px;
}
.medical-avatar{
    width:50px;
    height:50px;
    border-radius:50%;
    background:var(--soft);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    overflow:hidden;
    flex-shrink:0;
    border:2px solid var(--border);
}
.medical-avatar img{
    width:100%;
    height:100%;
    object-fit:cover;
}
.medical-name{
    font-size:14.5px;
    font-weight:800;
    color:var(--purple-dark);
    line-height:1.3;
}
.medical-role{
    font-size:11.5px;
    color:var(--muted);
    margin-top:2px;
    font-weight:600;
}

/* JADWAL VERTIKAL DOKTER */
.sched-box{
    background:#FCFAFF;
    border:1px solid #EFE8F6;
    border-radius:12px;
    padding:10px 12px;
    margin-top:10px;
}
.sched-box-title{
    font-size:11px;
    font-weight:800;
    color:#7E718B;
    text-transform:uppercase;
    margin-bottom:6px;
    display:flex;
    align-items:center;
    gap:5px;
}
.sched-vert-list{
    display:flex;
    flex-direction:column;
    gap:5px;
}
.sched-vert-item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    background:#fff;
    padding:5px 9px;
    border-radius:8px;
    border:1px solid #F1E8F8;
    font-size:11.5px;
}
.sched-day-pill{
    font-weight:800;
    color:var(--purple-dark);
    background:var(--soft);
    padding:2px 7px;
    border-radius:6px;
    font-size:11px;
}
.sched-time-text{
    color:#4B4453;
    font-weight:600;
    font-size:11px;
}

/* JADWAL REKOMENDASI JIKA HARI INI PENUH / TUTUP */
.recommend-banner{
    background:linear-gradient(135deg, #FFF8E6, #FEF2D6);
    border:2px dashed #E6B443;
    border-radius:16px;
    padding:18px 20px;
    margin-top:18px;
    display:none;
}
.recommend-banner strong{
    color:#A06400;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:6px;
    margin-bottom:6px;
}
.recommend-banner p{
    font-size:12.5px;
    color:#6C4E03;
    line-height:1.5;
    margin-bottom:12px;
}
.recommend-slots{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}
.recommend-chip{
    background:#fff;
    border:1.5px solid #D69E2E;
    color:#875304;
    padding:7px 12px;
    border-radius:10px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
    transition:all .2s;
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.recommend-chip:hover{
    background:#D69E2E;
    color:#fff;
    transform:translateY(-1px);
}

/* DYNAMIC QUEUE ESTIMATION PREVIEW CARD */
.queue-preview-card{
    margin-top:22px;
    background:linear-gradient(135deg, #FAF7FD, #F4EEFB);
    border:2px solid var(--border);
    border-radius:18px;
    padding:22px;
    box-shadow:0 10px 25px rgba(116,52,169,.06);
}
.queue-preview-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:16px;
    border-bottom:1px solid #E6DAF2;
    padding-bottom:12px;
}
.queue-preview-head strong{
    color:var(--button);
    font-size:16px;
    display:flex;
    align-items:center;
    gap:8px;
}
.queue-preview-grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:14px;
}
.queue-metric{
    background:#fff;
    border:1px solid var(--border);
    border-radius:12px;
    padding:12px 16px;
}
.queue-metric-label{
    display:block;
    font-size:11px;
    font-weight:800;
    color:#7E718B;
    text-transform:uppercase;
    margin-bottom:4px;
}
.queue-metric-val{
    font-size:17px;
    font-weight:900;
    color:var(--purple-dark);
}
.queue-metric-sub{
    display:block;
    font-size:11.5px;
    color:var(--muted);
    margin-top:3px;
}
.queue-preview-note{
    margin-top:14px;
    font-size:12px;
    color:var(--muted);
    line-height:1.6;
    background:rgba(255,255,255,.8);
    padding:10px 14px;
    border-radius:10px;
}

/* CONFIRM GRID */
.confirm-grid{
    display:grid;
    grid-template-columns:repeat(2, 1fr);
    gap:14px;
}
.confirm-item{
    background:#FCFAFF;
    border:1px solid var(--border);
    border-radius:12px;
    padding:14px 16px;
}
.confirm-label{
    display:block;
    font-size:11px;
    font-weight:800;
    color:#8E7E9E;
    text-transform:uppercase;
    margin-bottom:4px;
}
.confirm-value{
    font-size:14px;
    font-weight:800;
    color:var(--text);
}

/* STEP ACTIONS */
.step-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-top:28px;
    padding-top:20px;
    border-top:1px solid #F0EAF4;
}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:12px 24px;
    border-radius:12px;
    font-size:13.5px;
    font-weight:800;
    cursor:pointer;
    border:0;
    transition:all .2s;
    text-decoration:none;
}
.btn-primary{
    background:var(--button);
    color:#fff;
    box-shadow:0 4px 15px rgba(142,68,173,.3);
}
.btn-primary:hover{
    background:var(--button-hover);
    transform:translateY(-1px);
}
.btn-secondary{
    background:#EEE8F3;
    color:var(--purple-dark);
}
.btn-secondary:hover{
    background:#E2D7EA;
}
.btn-save-data{
    background:var(--soft);
    color:var(--button);
    border:1px solid #D8C9E9;
    padding:9px 16px;
    border-radius:10px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}

.alert{
    padding:14px 18px;
    border-radius:12px;
    background:#FFEBEB;
    color:#B72B2B;
    font-size:13px;
    font-weight:700;
    margin-bottom:20px;
    border:1px solid #F5C6C6;
}

/* BPJS SECTION HIGHLIGHT */
.bpjs-box{
    background:linear-gradient(135deg, #F0FDF4, #EAF7EF);
    border:2px solid #86EFAC;
    border-radius:16px;
    padding:18px 20px;
    margin-top:14px;
    display:none;
}

@media(max-width:768px){
    .container{margin:16px auto 100px;padding-bottom:24px;}
    .patient-choice, .form-grid, .confirm-grid, .queue-preview-grid{grid-template-columns:1fr}
    .form-group.full, .confirm-item[style*="grid-column:1/-1"]{grid-column:auto}
    .step-panel{padding:20px 16px}
    .step-actions{flex-direction:column-reverse}
    .step-actions .btn{width:100%}
    .page-header{padding:35px 16px 65px}
    .page-header h1{font-size:26px}
}
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<section class="page-header">
    <div class="header-inner">
        <h1>Pendaftaran Pasien Online</h1>
        <p>
            Daftar kunjungan dengan memilih dokter atau bidan serta hari yang tersedia.
            Sistem antrean akan menentukan nomor antrean dan estimasi jam pelayanan secara otomatis.
        </p>
    </div>

    <div class="registration-progress" id="registrationProgress">
        <div class="progress-step active" data-progress="1">
            <span class="progress-number">1</span>
            <span>Data Diri</span>
        </div>
        <div class="progress-step" data-progress="2">
            <span class="progress-number">2</span>
            <span>Jadwal &amp; Antrean</span>
        </div>
        <div class="progress-step" data-progress="3">
            <span class="progress-number">3</span>
            <span>Konfirmasi</span>
        </div>
    </div>
</section>

<main class="container">
    <?php if ($pesan): ?>
        <div class="alert">⚠️ <?= e($pesan) ?></div>
    <?php endif; ?>

    <div class="registration-card">
        <form action="proses_daftar.php" method="POST" enctype="multipart/form-data" id="formPendaftaran">

            <!-- ==========================================
                 LANGKAH 1: DATA PASIEN
            =========================================== -->
            <div class="step-panel active" data-step="1">
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Data Identitas Pasien</h2>
                            <p class="section-desc">Pendaftaran dapat dilakukan untuk diri sendiri maupun anggota keluarga.</p>
                        </div>
                        <span class="section-badge">Langkah 1 dari 3</span>
                    </div>

                    <div class="patient-choice">
                        <label for="radio_tipe_sendiri" class="choice-card active-choice" id="choiceSendiri">
                            <input type="radio" id="radio_tipe_sendiri" name="tipe_pasien" value="sendiri" checked onchange="ubahTipePasien()">
                            <span>
                                <span class="choice-title">Pasien Sendiri</span>
                                <span class="choice-desc"><?= $isLogin ? 'Menggunakan data akun profil saya' : 'Isi data pasien yang akan diperiksa' ?></span>
                            </span>
                        </label>

                        <label for="radio_tipe_keluarga" class="choice-card" id="choiceKeluarga">
                            <input type="radio" id="radio_tipe_keluarga" name="tipe_pasien" value="keluarga" onchange="ubahTipePasien()">
                            <span>
                                <span class="choice-title">Anggota Keluarga</span>
                                <span class="choice-desc">Daftarkan anak, orang tua, atau pasangan</span>
                            </span>
                        </label>
                    </div>

                    <?php if ($isLogin): ?>
                    <!-- KOTAK DATA KELUARGA (SESUAI GAMBAR 5) -->
                    <div class="family-section-box" id="boxDataKeluarga" style="display:none; margin-top:20px; background:#FAF7FD; border:1.5px solid #E4D7F0; border-radius:18px; padding:20px 22px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                            <div>
                                <h3 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#2B1B48;">Data Keluarga</h3>
                                <p style="margin:0; font-size:12px; color:#6B5A82;">Data keluarga yang sudah disimpan bisa dipilih kembali tanpa mengetik ulang.</p>
                            </div>
                            <button type="button" onclick="bukaModalTambahKeluarga()" style="background:#6A22A7; color:#fff; font-weight:800; border-radius:10px; padding:8px 16px; font-size:13px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(106,34,167,0.2); border:none; cursor:pointer;">
                                <span style="font-size:15px;line-height:1;">+</span> Tambah Keluarga
                            </button>
                        </div>

                        <div class="family-cards-list" id="familyCardsList" style="display:flex; flex-direction:column; gap:10px;">
                            <?php if (empty($daftarKeluarga)): ?>
                                <div id="emptyFamilyNotice" style="padding:16px; background:#fff; border:1px dashed #D2C1EC; border-radius:12px; text-align:center; color:#78668E; font-size:12.5px;">
                                    Belum ada anggota keluarga tersimpan. Klik tombol <strong>+ Tambah Keluarga</strong> di atas untuk menambahkan.
                                </div>
                            <?php else: ?>
                                <?php foreach ($daftarKeluarga as $idxK => $k): ?>
                                    <div class="family-member-card" id="cardFamily_<?= (int)$k['id_keluarga'] ?>" style="background:#FFFFFF; border:1.5px solid #E8DEF5; border-radius:14px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; transition:all 0.2s;">
                                        <div>
                                            <div style="font-weight:800; font-size:14px; color:#2B1B48;"><?= e($k['nama_lengkap']) ?></div>
                                            <div style="font-size:12px; color:#78668E; margin-top:2px;">
                                                NIK: <?= e($k['nik']) ?> <?php if (!empty($k['hubungan'])): ?>&bull; <span style="color:#6A22A7;font-weight:700;"><?= e($k['hubungan']) ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-pilih-keluarga" onclick='pilihAnggotaKeluarga(<?= json_encode($k, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, this)' style="padding:6px 18px; font-size:12px; font-weight:800; border-radius:999px; background:#F4EDFB; color:#6A22A7; border:1px solid #D8CAEC; cursor:pointer; transition:0.2s;">
                                            Pilih
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SECTION HEADING DATA DIRI (GAMBAR 5) -->
                    <div style="margin-top:24px; margin-bottom:12px;">
                        <h3 style="margin:0 0 4px; font-size:16px; font-weight:800; color:#2B1B48;">Data Diri</h3>
                        <p style="margin:0; font-size:12.5px; color:#6B5A82;" id="descDataDiri">Data akun otomatis digunakan ketika memilih "Pasien Sendiri".</p>
                    </div>

                    <input type="hidden" name="id_pasien" id="id_pasien" value="">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nik">NIK (Nomor Induk Kependudukan) *</label>
                            <input type="text" name="nik" id="nik" maxlength="16" value="<?= e($nikUser) ?>" placeholder="16 digit angka KTP / KK" required>
                        </div>

                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap Pasien *</label>
                            <input type="text" name="nama_lengkap" id="nama_lengkap" value="<?= e($namaPasien) ?>" placeholder="Sesuai kartu identitas" required>
                        </div>

                        <div class="form-group">
                            <label for="tanggal_lahir">Tanggal Lahir *</label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" value="<?= e($tanggalLahirUser) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="jenis_kelamin">Jenis Kelamin *</label>
                            <select name="jenis_kelamin" id="jenis_kelamin" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="L" <?= $jenisKelaminUser === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $jenisKelaminUser === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>

                        <?php if (isFieldActive('status_perkawinan')): ?>
                        <div class="form-group">
                            <label for="status_perkawinan"><?= e(getFieldLabel('status_perkawinan', 'Status Perkawinan')) ?> <?= isFieldRequired('status_perkawinan') ? '*' : '(Opsional)' ?></label>
                            <select name="status_perkawinan" id="status_perkawinan" <?= isFieldRequired('status_perkawinan') ? 'required' : '' ?>>
                                <option value="">-- Pilih Status --</option>
                                <?php foreach(['Belum Menikah','Menikah','Cerai Hidup','Cerai Mati'] as $v): ?>
                                    <option value="<?= e($v) ?>" <?= $statusPerkawinanUser === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('nama_ibu_kandung')): ?>
                        <div class="form-group">
                            <label for="nama_ibu_kandung"><?= e(getFieldLabel('nama_ibu_kandung', 'Nama Ibu Kandung')) ?> <?= isFieldRequired('nama_ibu_kandung') ? '*' : '(Opsional)' ?></label>
                            <input type="text" name="nama_ibu_kandung" id="nama_ibu_kandung" value="<?= e($namaIbuKandungUser) ?>" placeholder="Sesuai Kartu Keluarga / Akta" <?= isFieldRequired('nama_ibu_kandung') ? 'required' : '' ?>>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('nama_suami') || isFieldActive('nama_ayah_kandung')): ?>
                        <div class="form-group" id="wrap_ayah_suami">
                            <label for="nama_suami" id="label_ayah_suami"><?= e(getFieldLabel('nama_suami', 'Nama Suami / Pasangan')) ?> <?= (isFieldRequired('nama_suami') || isFieldRequired('nama_ayah_kandung')) ? '*' : '(Opsional)' ?></label>
                            <input type="text" name="nama_suami" id="nama_suami" value="<?= e($namaSuamiUser ?: $namaAyahKandungUser) ?>" placeholder="Khusus bersalin / pasien menikah" <?= (isFieldRequired('nama_suami') || isFieldRequired('nama_ayah_kandung')) ? 'required' : '' ?>>
                            <input type="hidden" name="nama_ayah_kandung" id="nama_ayah_kandung" value="<?= e($namaAyahKandungUser) ?>">
                            <small id="hint_ayah_suami" style="color:var(--hanania-muted);font-size:11px;display:block;margin-top:3px;"></small>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('no_telepon')): ?>
                        <div class="form-group">
                            <label for="no_telepon"><?= e(getFieldLabel('no_telepon', 'Nomor Telepon / WhatsApp')) ?> <?= isFieldRequired('no_telepon', true) ? '*' : '(Opsional)' ?></label>
                            <input type="text" name="no_telepon" id="no_telepon" value="<?= e($teleponUser) ?>" placeholder="08xxxxxxxxxx" <?= isFieldRequired('no_telepon', true) ? 'required' : '' ?>>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('email')): ?>
                        <div class="form-group">
                            <label for="email"><?= e(getFieldLabel('email', 'Alamat Email')) ?> <?= isFieldRequired('email') ? '*' : '(Opsional)' ?></label>
                            <input type="email" name="email" id="email" value="<?= e($emailUser) ?>" placeholder="nama@email.com" <?= isFieldRequired('email') ? 'required' : '' ?>>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('pekerjaan')): ?>
                        <div class="form-group">
                            <label for="pekerjaan"><?= e(getFieldLabel('pekerjaan', 'Pekerjaan')) ?> <?= isFieldRequired('pekerjaan') ? '*' : '(Opsional)' ?></label>
                            <input type="text" name="pekerjaan" id="pekerjaan" value="<?= e($pekerjaanUser) ?>" placeholder="Contoh: Karyawan, Pelajar, Ibu Rumah Tangga" <?= isFieldRequired('pekerjaan') ? 'required' : '' ?>>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('pendidikan_terakhir')): ?>
                        <div class="form-group">
                            <label for="pendidikan_terakhir"><?= e(getFieldLabel('pendidikan_terakhir', 'Pendidikan Terakhir')) ?> <?= isFieldRequired('pendidikan_terakhir') ? '*' : '(Opsional)' ?></label>
                            <select name="pendidikan_terakhir" id="pendidikan_terakhir" <?= isFieldRequired('pendidikan_terakhir') ? 'required' : '' ?>>
                                <option value="">-- Pilih Pendidikan --</option>
                                <?php foreach(['Tidak Sekolah','SD','SMP','SMA/SMK','D1/D2/D3','S1','S2','S3'] as $v): ?>
                                    <option value="<?= e($v) ?>" <?= $pendidikanUser === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('agama')): ?>
                        <div class="form-group">
                            <label for="agama"><?= e(getFieldLabel('agama', 'Agama')) ?> <?= isFieldRequired('agama') ? '*' : '(Opsional)' ?></label>
                            <select name="agama" id="agama" <?= isFieldRequired('agama') ? 'required' : '' ?>>
                                <option value="">-- Pilih Agama --</option>
                                <?php foreach(['Islam','Kristen Protestan','Katolik','Hindu','Buddha','Konghucu'] as $v): ?>
                                    <option value="<?= e($v) ?>" <?= $agamaUser === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (isFieldActive('alamat')): ?>
                        <div class="form-group full">
                            <label for="alamat"><?= e(getFieldLabel('alamat', 'Alamat Tempat Tinggal')) ?> <?= isFieldRequired('alamat', true) ? '*' : '(Opsional)' ?></label>
                            <textarea name="alamat" id="alamat" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota" <?= isFieldRequired('alamat', true) ? 'required' : '' ?>><?= e($alamatUser) ?></textarea>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Tampilkan field kustom baru yang ditambahkan admin jika ada
                        foreach ($formFieldConfig as $fcKey => $fc):
                            if (!empty($fc['is_custom']) && $fc['status'] === 'aktif' && (int)$fc['tahap'] === 1):
                                $valCust = $_SESSION[$fcKey] ?? '';
                        ?>
                        <div class="form-group">
                            <label for="custom_<?= e($fcKey) ?>"><?= e($fc['label_field']) ?> <?= ((int)$fc['wajib'] === 1) ? '*' : '(Opsional)' ?></label>
                            <input type="text" name="<?= e($fcKey) ?>" id="custom_<?= e($fcKey) ?>" value="<?= e($valCust) ?>" placeholder="<?= e($fc['keterangan'] ?: 'Masukkan ' . $fc['label_field']) ?>" <?= ((int)$fc['wajib'] === 1) ? 'required' : '' ?>>
                        </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>

                    <?php if ($isLogin): ?>
                        <div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
                            <button type="button" class="btn-save-data" id="saveDataButton" onclick="simpanDataDiri()">
                                💾 Simpan Perubahan Data
                            </button>
                            <span id="saveProfileStatus" style="font-size:12px;font-weight:700;"></span>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- KATEGORI PASIEN / PENJAMIN BIAYA (UMUM VS BPJS) -->
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Kategori Pasien / Penjamin Biaya *</h2>
                            <p class="section-desc">Pilih jalur pembayaran atau penjaminan kesehatan yang Anda gunakan.</p>
                        </div>
                    </div>

                    <div class="patient-choice">
                        <label for="radio_penjamin_umum" class="choice-card active-choice" id="cardPenjaminUmum">
                            <input type="radio" id="radio_penjamin_umum" name="jenis_pasien_radio" value="Umum" checked onchange="pilihJenisPenjamin('Umum')">
                            <span>
                                <span class="choice-title">💵 Pasien Umum / Mandiri</span>
                                <span class="choice-desc">Pembayaran mandiri (Tunai / Debit / QRIS). Pendaftaran online langsung diproses di website klinik.</span>
                            </span>
                        </label>

                        <label for="radio_penjamin_bpjs" class="choice-card" id="cardPenjaminBpjs">
                            <input type="radio" id="radio_penjamin_bpjs" name="jenis_pasien_radio" value="BPJS" onchange="pilihJenisPenjamin('BPJS')">
                            <span>
                                <span class="choice-title">💳 Pasien BPJS Kesehatan</span>
                                <span class="choice-desc">Pendaftaran faskes resmi via aplikasi Mobile JKN &amp; masukkan nomor antrean Mobile JKN Anda di sini.</span>
                            </span>
                        </label>
                    </div>

                    <input type="hidden" name="jenis_pasien" id="field_jenis_pasien" value="Umum">

                    <!-- STATUS BPJS KESEHATAN (PANDUAN MUNCUL DI MODAL, BAGIAN LUAR TIDAK MEMUAT TUTORIAL PANJANG) -->
                    <div id="bpjsCompactStatus" style="display:none;margin-top:14px;padding:12px 18px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:14px;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-size:22px;">📱</span>
                            <div>
                                <div style="font-size:11.5px;font-weight:800;color:#166534;text-transform:uppercase;letter-spacing:0.5px;">Peserta BPJS Kesehatan (Mobile JKN)</div>
                                <div style="font-size:13.5px;color:#14532D;margin-top:2px;">
                                    Nomor Antrean: <strong id="displayNoJknText" style="color:#15803D;font-size:15px;">(Belum Diisi)</strong>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="bukaModalMobileJkn()" style="background:#fff;border:1.5px solid #16a34a;color:#15803d;font-size:12.5px;font-weight:800;padding:8px 16px;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                            📖 Buka Panduan &amp; Isi Nomor
                        </button>
                    </div>
                    <input type="hidden" name="nomor_antrean_jkn" id="nomor_antrean_jkn" value="">
                </section>

                <!-- RUJUKAN -->
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Status Rujukan Fasilitas Kesehatan</h2>
                            <p class="section-desc">Apakah pasien membawa surat rujukan dari faskes lain sebelumnya?</p>
                        </div>
                    </div>

                    <div class="patient-choice">
                        <label for="radio_rujukan_tidak" class="choice-card active-choice" id="choiceRujukanTidak">
                            <input type="radio" id="radio_rujukan_tidak" name="rujukan_choice" value="Tidak" <?= $adaRujukanUser !== 'Ya' ? 'checked' : '' ?> onchange="setRujukanChoice('Tidak')">
                            <span>
                                <span class="choice-title">Tidak Ada Rujukan</span>
                                <span class="choice-desc">Kunjungan langsung tanpa surat rujukan</span>
                            </span>
                        </label>

                        <label for="radio_rujukan_ya" class="choice-card" id="choiceRujukanYa">
                            <input type="radio" id="radio_rujukan_ya" name="rujukan_choice" value="Ya" <?= $adaRujukanUser === 'Ya' ? 'checked' : '' ?> onchange="setRujukanChoice('Ya')">
                            <span>
                                <span class="choice-title">Ada Surat Rujukan</span>
                                <span class="choice-desc">Membawa surat pengantar/rujukan medis</span>
                            </span>
                        </label>
                    </div>

                    <input type="hidden" name="ada_rujukan" id="ada_rujukan" value="<?= e($adaRujukanUser === 'Ya' ? 'Ya' : 'Tidak') ?>">

                    <div id="asalRujukanGroup" style="display:<?= $adaRujukanUser === 'Ya' ? 'block' : 'none' ?>;background:#FAF8FD;border:1px solid var(--border);border-radius:14px;padding:18px;margin-top:14px;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nama_perujuk">Nama Dokter / Fasilitas Perujuk *</label>
                                <input type="text" name="nama_perujuk" id="nama_perujuk" placeholder="Contoh: Puskesmas Sukadamai / dr. Hasan">
                            </div>
                            <div class="form-group">
                                <label for="file_rujukan">Unggah Berkas Surat Rujukan *</label>
                                <input type="file" name="file_rujukan" id="file_rujukan" accept=".pdf,.jpg,.jpeg,.png">
                                <small style="color:var(--muted);font-size:11px;">Format file: PDF, JPG, atau PNG (Maks 5 MB).</small>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="step-actions">
                    <a href="../index.php" class="btn btn-secondary">Batal</a>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Lanjut ke Pilih Jadwal →</button>
                </div>
            </div>

            <!-- ==========================================
                 LANGKAH 2: LAYANAN & JADWAL
            =========================================== -->
            <div class="step-panel" data-step="2">
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Pilihan Layanan, Sesi &amp; Hari Kunjungan</h2>
                            <p class="section-desc">
                                Tentukan layanan medis, sesi kedatangan, poli, serta hari kunjungan.
                                Sistem akan memperkirakan estimasi jam pelayanan secara otomatis.
                            </p>
                        </div>
                        <span class="section-badge">Langkah 2 dari 3</span>
                    </div>

                    <!-- BANNER PERSALINAN 24 JAM SELALU TERSEDIA -->
                    <div style="background: linear-gradient(135deg, #FAF5FF 0%, #F3E8FF 100%); border: 1.5px solid #D8B4FE; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 26px;">🤱</span>
                            <div>
                                <strong style="color: #6B21A8; font-size: 13.5px; display: block;">Layanan Persalinan 24 Jam Selalu Tersedia</strong>
                                <span style="color: #581C87; font-size: 12px;">Siaga penuh 24/7 tanpa penutupan pendaftaran untuk persalinan &amp; tindakan darurat kebidanan.</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm" onclick="pilihPersalinan24Jam()" style="background: #7E22CE; color: #fff; font-size: 12px; font-weight: 800; padding: 7px 15px; border-radius: 8px; border: none; cursor: pointer;">
                            Pilih Persalinan 24 Jam →
                        </button>
                    </div>

                    <div class="form-grid" style="margin-bottom:18px;">
                        <!-- 1. POLI PELAYANAN (PERSALINAN 24 JAM DITAMPILKAN PALING ATAS) -->
                        <div class="form-group" id="group_poli">
                            <label for="id_poli">Poli Pelayanan *</label>
                            <select name="id_poli" id="id_poli" required onchange="onFilterChange()">
                                <option value="">-- Pilih Poli --</option>
                                <?php foreach ($dataPoli as $poli): 
                                    $is24 = ((int)($poli['is_24jam'] ?? 0) === 1 || (int)$poli['id_poli'] === 5 || stripos($poli['nama_poli'], 'persalinan') !== false);
                                ?>
                                    <option value="<?= (int)$poli['id_poli'] ?>" <?= $poliTerpilih === (int)$poli['id_poli'] ? 'selected' : '' ?>>
                                        <?= e($poli['nama_poli']) ?><?= $is24 ? ' (✨ Siaga 24 Jam Nonstop)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 2. PILIHAN LAYANAN / TINDAKAN (TERSEDIA UNTUK SEMUA POLI, DIKELOLA OLEH ADMIN) -->
                        <div class="form-group" id="group_tindakan" style="display:block;">
                            <label for="jenis_perawatan">Pilihan Layanan / Tindakan *</label>
                            <select name="jenis_perawatan" id="jenis_perawatan" required onchange="onLayananChange()">
                                <?php 
                                // Filter tindakan pendaftaran sesuai poli awal yang terpilih
                                $filteredTindakanInit = [];
                                if (!empty($dataTindakanList)) {
                                    foreach ($dataTindakanList as $tdk) {
                                        $terkait = trim($tdk['id_poli_terkait'] ?? '');
                                        $namaLower = strtolower($tdk['nama_tindakan']);
                                        
                                        if ($poliTerpilih > 0) {
                                            // Kebidanan (3) & Spesialis (4): mutlak tidak boleh ada pemeriksaan gigi
                                            if (in_array($poliTerpilih, [3, 4]) && strpos($namaLower, 'gigi') !== false) {
                                                continue;
                                            }
                                            // Poli Gigi (2): hanya tindakan gigi
                                            if ($poliTerpilih === 2 && (strpos($namaLower, 'usg') !== false || strpos($namaLower, 'kebidanan') !== false || strpos($namaLower, 'anc') !== false || strpos($namaLower, 'sp.og') !== false || strpos($namaLower, 'bersalin') !== false || strpos($namaLower, 'imunisasi') !== false)) {
                                                continue;
                                            }
                                            // Poli Umum (1): umum, cek darah, imunisasi (tidak boleh gigi, usg, kebidanan)
                                            if ($poliTerpilih === 1 && (strpos($namaLower, 'gigi') !== false || strpos($namaLower, 'sp.og') !== false || strpos($namaLower, 'bersalin') !== false || strpos($namaLower, 'usg') !== false)) {
                                                continue;
                                            }
                                            // Cek kecocokan poli terkait
                                            if ($terkait !== '' && $terkait !== 'all') {
                                                $pArr = array_map('trim', explode(',', $terkait));
                                                if (!in_array((string)$poliTerpilih, $pArr, true)) {
                                                    continue;
                                                }
                                            }
                                        }
                                        $filteredTindakanInit[] = $tdk;
                                    }
                                }
                                ?>
                                <?php if (!empty($filteredTindakanInit)): ?>
                                    <?php foreach ($filteredTindakanInit as $idxT => $tdk): 
                                        $val = $tdk['nama_tindakan'];
                                        $icon = $tdk['icon'] ?: '🩺';
                                        $label = $icon . ' ' . $val;
                                        $isSelected = false;
                                        if ($layananTerpilih === 'usg' && (stripos($val, 'USG 2D') !== false || stripos($val, 'USG Kehamilan') !== false)) {
                                            $isSelected = true;
                                        } elseif ($layananTerpilih === 'usg_4d' && stripos($val, '4D') !== false) {
                                            $isSelected = true;
                                        } elseif (!$layananTerpilih && $idxT === 0) {
                                            $isSelected = true;
                                        }
                                    ?>
                                        <option value="<?= e($val) ?>" <?= $isSelected ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                <?php elseif ($poliTerpilih <= 0): ?>
                                    <option value="">-- Pilih Poli Terlebih Dahulu --</option>
                                <?php else: ?>
                                    <option value="Pemeriksaan Medis Umum">🩺 Pemeriksaan Medis Umum</option>
                                <?php endif; ?>
                            </select>
                            <small id="tindakan_hint" style="color:#6A22A7;font-size:11.5px;margin-top:4px;display:block;">
                                <?php 
                                if ($poliTerpilih === 1) {
                                    echo '💡 Tersedia Pemeriksaan Medis Umum, Cek Darah / Lab, dan Imunisasi';
                                } elseif ($poliTerpilih === 2) {
                                    echo '💡 Layanan pemeriksaan dan perawatan kesehatan gigi &amp; mulut';
                                } elseif (in_array($poliTerpilih, [3, 4])) {
                                    echo '💡 Layanan kebidanan, pemeriksaan kehamilan ANC, USG &amp; Sp.OG';
                                } else {
                                    echo '💡 Sesuaikan dengan kebutuhan pemeriksaan Anda';
                                }
                                ?>
                            </small>
                        </div>

                        <!-- 3. HARI / TANGGAL KUNJUNGAN (TERLEBIH DAHULU) -->
                        <div class="form-group" id="group_tanggal">
                            <label for="tanggal_kunjungan">Hari / Tanggal Kunjungan *</label>
                            <input type="date" name="tanggal_kunjungan" id="tanggal_kunjungan"
                                   value="<?= e($tanggalTerpilih) ?>" min="<?= date('Y-m-d') ?>" required onchange="onFilterChange()">
                            <?php if (!empty($hariPraktikTersedia)): ?>
                                <small style="color:var(--button);font-size:11.5px;font-weight:700;margin-top:4px;">
                                    🗓️ Hari praktik: <?= e(implode(', ', $hariPraktikTersedia)) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- 4. PILIHAN SESI KUNJUNGAN (DIBAWAH TANGGAL PADA MOBILE, KIRI PADA DESKTOP) -->
                        <div class="form-group" id="group_sesi" style="display:none;">
                            <label for="sesi_kunjungan">Pilihan Sesi Kunjungan *</label>
                            <select name="sesi_kunjungan" id="sesi_kunjungan" required onchange="hitungAntreanOtomatis()">
                                <option value="Pagi">🌅 Sesi Pagi (09:00 - 12:00 WIB)</option>
                                <option value="Sore/Malam">🌙 Sesi Sore (16:00 - 20:00 WIB)</option>
                            </select>
                            <small class="sesi-helper" style="color:#6A22A7;font-size:11.5px;margin-top:4px;display:block;">
                                💡 Pilihan sesi menyesuaikan tanggal kunjungan yang dipilih
                            </small>
                        </div>

                        <?php if (isFieldActive('keluhan')): ?>
                        <div class="form-group full" id="group_keluhan">
                            <label for="keluhan"><?= e(getFieldLabel('keluhan', 'Keluhan Utama / Kebutuhan Konsultasi')) ?> <?= isFieldRequired('keluhan') ? '*' : '(Opsional)' ?></label>
                            <textarea name="keluhan" id="keluhan" placeholder="Contoh: Batuk pilek sejak 2 hari, demam, kontrol rutin, dll." <?= isFieldRequired('keluhan') ? 'required' : '' ?>></textarea>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- BANNER SARAN JADWAL MINGGU INI JIKA HARI INI PENUH / KURANG 1 JAM -->
                    <div class="recommend-banner" id="recommendBanner">
                        <strong>
                            <span>⚠️</span> Jadwal Praktik Hari Ini Telah Ditutup / Kurang dari 1 Jam
                        </strong>
                        <p id="recommendBannerText">
                            Pendaftaran online untuk jadwal dokter pada hari ini sudah tidak tersedia. Silakan pilih salah satu jadwal praktik lain yang masih tersedia di minggu ini:
                        </p>
                        <div class="recommend-slots" id="recommendSlotsContainer">
                            <!-- Slot chips dynamically inserted via JS -->
                        </div>
                    </div>
                </section>

                <!-- PILIH DOKTER ATAU BIDAN -->
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Pilih Tenaga Kesehatan</h2>
                            <p class="section-desc">
                                Silakan pilih dokter atau bidan yang diinginkan. Anda tetap dapat memilih dokter lain dengan mengklik kartunya di bawah.
                            </p>
                        </div>
                    </div>

                    <input type="hidden" name="id_dokter" id="id_dokter" value="<?= $dokterTerpilih ?>">
                    <input type="hidden" name="id_bidan" id="id_bidan" value="<?= $bidanTerpilih ?>">

                    <div id="medicalMessage" style="display:none;padding:18px;background:#FAF8FD;border:1px dashed var(--border);border-radius:12px;color:var(--muted);text-align:center;font-size:13px;">
                        Pilih poli dan tanggal pemeriksaan terlebih dahulu.
                    </div>

                    <div class="medical-list" id="medicalList">
                        <?php foreach ($dataTenaga as $medis): 
                            $isDokter = ($medis['jenis'] === 'Dokter');
                            $isCuti = (($medis['status'] ?? '') === 'cuti');
                            $selected = (
                                !$isCuti &&
                                (
                                    ($dokterTerpilih > 0 && $isDokter && (int)$medis['id'] === $dokterTerpilih) ||
                                    ($bidanTerpilih > 0 && !$isDokter && (int)$medis['id'] === $bidanTerpilih)
                                )
                            );
                            $scheduleJson = e(json_encode($medis['jadwal_db'], JSON_UNESCAPED_UNICODE));

                            // Foto avatar
                            $mFoto = trim((string)($medis['foto'] ?? ''));
                            $mFotoUrl = '';
                            if ($mFoto !== '') {
                                $cleanMF = ltrim($mFoto, '/');
                                if (strpos($cleanMF, 'uploads/') === 0 || strpos($cleanMF, 'assets/') === 0) {
                                    $mFotoUrl = '../' . $cleanMF;
                                } elseif (file_exists(__DIR__ . '/../uploads/tenaga/' . $cleanMF)) {
                                    $mFotoUrl = '../uploads/tenaga/' . $cleanMF;
                                } elseif (file_exists(__DIR__ . '/../assets/img/' . $cleanMF)) {
                                    $mFotoUrl = '../assets/img/' . $cleanMF;
                                } else {
                                    $mFotoUrl = '../uploads/' . $cleanMF;
                                }
                            }
                        ?>
                        <div class="medical-card <?= $selected ? 'selected' : '' ?> <?= $isCuti ? 'is-cuti' : '' ?>"
                             data-id="<?= (int)$medis['id'] ?>"
                             data-poli="<?= (int)($medis['id_poli'] ?? 0) ?>"
                             data-jenis="<?= e($medis['jenis']) ?>"
                             data-nama="<?= e($medis['nama']) ?>"
                             data-status="<?= e($medis['status']) ?>"
                             data-schedule="<?= $scheduleJson ?>"
                             onclick="pilihTenagaMedis(this)">
                            
                            <?php if ($isCuti): ?>
                                <span class="badge-cuti">🔴 Cuti / Tidak Hadir</span>
                            <?php else: ?>
                                <span class="selected-indicator">✓ Terpilih</span>
                            <?php endif; ?>

                            <div class="medical-top">
                                <div class="medical-avatar">
                                    <?php if (!empty($mFotoUrl)): ?>
                                        <img src="<?= e($mFotoUrl) ?>" alt="<?= e($medis['nama']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                                        <span style="display:none;"><?= $isDokter ? '🩺' : '🤱' ?></span>
                                    <?php else: ?>
                                        <span><?= $isDokter ? '🩺' : '🤱' ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1;">
                                    <div class="medical-name"><?= e($medis['nama']) ?></div>
                                    <div class="medical-role"><?= e($medis['spesialisasi'] ?: $medis['jenis']) ?></div>
                                </div>
                            </div>

                            <!-- JADWAL DOKTER MENGURUT KE BAWAH (VERTIKAL) -->
                            <div class="sched-box">
                                <div class="sched-box-title">
                                    <span>🗓️</span> Jadwal Praktik Mingguan
                                </div>
                                <div class="sched-vert-list">
                                    <?php foreach ($medis['jadwal_db'] as $j): ?>
                                        <div class="sched-vert-item">
                                            <span class="sched-day-pill"><?= e($j['hari']) ?></span>
                                            <span class="sched-time-text"><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?> WIB</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </section>

                <div class="step-actions">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">← Kembali</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Lanjut ke Konfirmasi →</button>
                </div>
            </div>

            <!-- ==========================================
                 LANGKAH 3: KONFIRMASI PENDAFTARAN
            =========================================== -->
            <div class="step-panel" data-step="3">
                <section class="section-card">
                    <div class="section-heading">
                        <div>
                            <h2>Konfirmasi Data Kunjungan</h2>
                            <p class="section-desc">Mohon periksa kembali detail data dan jadwal sebelum nomor antrean resmi diterbitkan.</p>
                        </div>
                        <span class="section-badge">Langkah 3 dari 3</span>
                    </div>

                    <div class="confirm-grid">
                        <div class="confirm-item">
                            <span class="confirm-label">Nama Lengkap Pasien</span>
                            <div class="confirm-value" id="confirmNama">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">NIK Pasien</span>
                            <div class="confirm-value" id="confirmNik">-</div>
                        </div>

                        <div class="confirm-item" id="boxConfirmNamaIbu">
                            <span class="confirm-label">Nama Ibu Kandung</span>
                            <div class="confirm-value" id="confirmNamaIbu">-</div>
                        </div>

                        <div class="confirm-item" id="boxConfirmNamaSuami">
                            <span class="confirm-label" id="confirmLabelSuami">Nama Suami / Ayah Kandung</span>
                            <div class="confirm-value" id="confirmNamaSuami">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Kategori / Penjamin</span>
                            <div class="confirm-value" id="confirmJenisPasien" style="font-weight:800;color:var(--button);">Pasien Umum / Mandiri</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Nomor Antrean Mobile JKN</span>
                            <div class="confirm-value" id="confirmNomorJkn" style="font-weight:800;color:#16a34a;">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Pilihan Layanan / Tindakan</span>
                            <div class="confirm-value" id="confirmLayanan">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Sesi Kunjungan</span>
                            <div class="confirm-value" id="confirmSesi">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Poli Pelayanan</span>
                            <div class="confirm-value" id="confirmPoli">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Tenaga Medis</span>
                            <div class="confirm-value" id="confirmMedis">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Hari &amp; Tanggal Pemeriksaan</span>
                            <div class="confirm-value" id="confirmTanggal">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Nomor Antrean Online Klinik</span>
                            <div class="confirm-value" id="confirmNomorAntrean" style="color:var(--button);font-size:18px;">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Estimasi Waktu Pelayanan</span>
                            <div class="confirm-value" id="confirmEstimasi">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Waktu Kedatangan Disarankan</span>
                            <div class="confirm-value" id="confirmWaktuDatang">-</div>
                        </div>

                        <div class="confirm-item">
                            <span class="confirm-label">Status Surat Rujukan</span>
                            <div class="confirm-value" id="confirmRujukan">-</div>
                        </div>

                        <div class="confirm-item" style="grid-column:1/-1;">
                            <span class="confirm-label">Keluhan / Catatan Medis</span>
                            <div class="confirm-value" id="confirmKeluhan">-</div>
                        </div>
                    </div>

                    <div style="margin-top:20px;padding:14px;border-radius:12px;background:#FAF8FD;border:1px solid var(--border);font-size:12.5px;color:var(--muted);line-height:1.6;">
                        📌 Dengan menekan tombol di bawah, pendaftaran akan disimpan ke sistem klinik dan bukti pendaftaran digital akan langsung dicetak di layar Anda.
                    </div>
                </section>

                <div class="step-actions">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">← Ubah Data</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitDaftar">
                        ✅ Daftarkan &amp; Dapatkan Nomor Antrean
                    </button>
                </div>
            </div>

        </form>
    </div>
</main>

<!-- MODAL EDUKASI & TUTORIAL MOBILE JKN UNTUK PASIEN BPJS -->
<div id="modalMobileJkn" style="display:none;position:fixed;inset:0;background:rgba(15,8,28,0.72);z-index:9999;overflow-y:auto;padding:35px 16px;">
    <div style="background:#fff;border-radius:24px;max-width:560px;width:100%;margin:20px auto;padding:28px;box-shadow:0 25px 60px rgba(0,0,0,0.3);position:relative;">
        <button type="button" onclick="tutupModalMobileJkn()" aria-label="Tutup" style="position:absolute;top:16px;right:16px;width:34px;height:34px;border-radius:50%;background:#F4EDFB;border:none;color:#6A22A7;font-size:16px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
        <div style="text-align:center;margin-bottom:16px;">
            <div style="width:64px;height:64px;background:#EAF7EF;color:#10b981;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:32px;margin-bottom:10px;">
                📱
            </div>
            <h3 style="font-size:20px;font-weight:900;color:var(--purple-dark);margin:0 0 6px;">
                Tutorial Pendaftaran BPJS (Mobile JKN)
            </h3>
            <p style="font-size:13px;color:var(--muted);margin:0;line-height:1.5;">
                Ikuti langkah mudah berikut untuk mengambil nomor antrean resmi dari aplikasi Mobile JKN:
            </p>
        </div>

        <div style="background:#FAF8FD;border:1px solid #EAE0F5;border-radius:16px;padding:18px;margin-bottom:20px;font-size:13px;color:#4A4353;line-height:1.65;">
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">1</div>
                    <div><strong>Buka Aplikasi Mobile JKN</strong> di HP Anda lalu login dengan NIK / No. Kartu BPJS.</div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">2</div>
                    <div>Pilih menu <strong>Pendaftaran Pelayanan (Antrean)</strong> pada halaman utama.</div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">3</div>
                    <div>Pilih Faskes Tingkat Pertama (FKTP): <strong>Klinik Pratama Rumah Hanania</strong>.</div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">4</div>
                    <div>Pilih Poli yang dituju, dokter/bidan, dan tanggal rencana kunjungan.</div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">5</div>
                    <div>Aplikasi akan menerbitkan nomor antrean Anda (contoh: <strong>A-008</strong> atau <strong>B-015</strong>). Salin nomor tersebut ke formulir di bawah ini.</div>
                </div>
            </div>
        </div>

        <!-- INPUT NOMOR ANTREAN DI MODAL -->
        <div style="background:#EAF7EF;border:1.5px solid #86EFAC;border-radius:14px;padding:16px;margin-bottom:18px;">
            <label style="display:block;font-size:12.5px;font-weight:800;color:#166534;margin-bottom:6px;">
                Isi Nomor Antrean Mobile JKN yang Anda Dapatkan: *
            </label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="modalInputJkn" placeholder="Contoh: A-012" style="flex:1;padding:10px 14px;border:1.5px solid #86EFAC;border-radius:10px;font-weight:800;font-size:14px;">
                <button type="button" onclick="simpanNomorJknDariModal()" style="background:#10b981;color:#fff;border:none;padding:10px 18px;border-radius:10px;font-weight:800;cursor:pointer;">
                    Gunakan Nomor Ini
                </button>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="https://play.google.com/store/apps/details?id=app.bpjs.mobile" target="_blank" rel="noopener noreferrer" style="background:#10b981;color:#fff;text-align:center;padding:12px 18px;border-radius:12px;font-size:13.5px;font-weight:800;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span>📲</span> Download / Buka Aplikasi Mobile JKN
            </a>
            <button type="button" onclick="tutupModalMobileJkn()" style="background:none;border:none;color:var(--muted);font-size:12.5px;cursor:pointer;padding:8px;text-decoration:underline;">
                Tutup Jendela Panduan
            </button>
        </div>
    </div>
</div>

<!-- MODAL FORM TAMBAH ANGGOTA KELUARGA (SESUAI GAMBAR 5) -->
<div id="modalTambahKeluarga" style="display:none;position:fixed;inset:0;background:rgba(15,8,28,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px 16px;backdrop-filter:blur(4px);box-sizing:border-box;">
    <div style="background:#ffffff;border-radius:20px;max-width:560px;width:100%;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.35);border:1px solid #E8DEF5;overflow:hidden;position:relative;">
        
        <!-- HEADER MODAL (STICKY AT TOP) -->
        <div style="padding:18px 24px;border-bottom:1px solid #F0E6FA;background:#FAF8FD;display:flex;justify-content:space-between;align-items:flex-start;flex-shrink:0;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(106,34,167,0.08);color:#6A22A7;font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px;margin-bottom:6px;">
                    <span>👨‍👩‍👧</span> DATA KELUARGA BARU
                </div>
                <h3 style="font-size:18px;font-weight:900;color:var(--purple-dark);margin:0 0 4px;">
                    Tambah Anggota Keluarga
                </h3>
                <p style="font-size:12px;color:var(--muted);margin:0;">
                    Data yang disimpan dapat langsung dipilih untuk pendaftaran saat ini maupun berikutnya.
                </p>
            </div>
            <button type="button" onclick="tutupModalTambahKeluarga()" aria-label="Tutup" style="width:32px;height:32px;border-radius:50%;background:#F4EDFB;border:none;color:#6A22A7;font-size:15px;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-left:10px;">✕</button>
        </div>

        <!-- BODY MODAL (SCROLLABLE CONTAINER) -->
        <form id="formTambahKeluarga" onsubmit="simpanAnggotaKeluarga(event)" style="display:flex;flex-direction:column;flex:1;overflow:hidden;margin:0;">
            <div id="modalTambahKeluargaBody" style="padding:20px 24px;overflow-y:auto;flex:1;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Nama Lengkap Pasien *</label>
                        <input type="text" id="kel_nama" required placeholder="Sesuai KTP / Akta Kelahiran" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">NIK (16 Digit) *</label>
                        <input type="text" id="kel_nik" maxlength="16" required placeholder="16 digit KTP/KK" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Hubungan Keluarga *</label>
                        <select id="kel_hubungan" required onchange="updateKelAyahSuamiField()" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;background:#fff;">
                            <option value="Istri / Pasangan">Istri / Pasangan</option>
                            <option value="Suami / Pasangan">Suami / Pasangan</option>
                            <option value="Anak">Anak</option>
                            <option value="Orang Tua">Orang Tua</option>
                            <option value="Saudara Kandung">Saudara Kandung</option>
                            <option value="Keluarga Lainnya">Keluarga Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Tanggal Lahir *</label>
                        <input type="date" id="kel_tanggal_lahir" required onchange="updateKelAyahSuamiField()" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Jenis Kelamin *</label>
                        <select id="kel_jenis_kelamin" required style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;background:#fff;">
                            <option value="P">Perempuan</option>
                            <option value="L">Laki-laki</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Status Perkawinan</label>
                        <select id="kel_status_perkawinan" onchange="updateKelAyahSuamiField()" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;background:#fff;">
                            <option value="">-- Pilih Status --</option>
                            <option value="Belum Menikah">Belum Menikah</option>
                            <option value="Menikah">Menikah</option>
                            <option value="Cerai Hidup">Cerai Hidup</option>
                            <option value="Cerai Mati">Cerai Mati</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Nama Ibu Kandung</label>
                        <input type="text" id="kel_nama_ibu_kandung" placeholder="Sesuai KK / Akta" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label id="kel_label_suami_ayah" style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Nama Ayah Kandung</label>
                        <input type="text" id="kel_nama_suami" placeholder="Contoh: Nama ayah kandung sesuai Kartu Keluarga (KK)" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                        <input type="hidden" id="kel_nama_ayah_kandung">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">No. WhatsApp / HP</label>
                        <input type="text" id="kel_no_telepon" placeholder="08xxxxxxxxxx" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Email (Opsional)</label>
                        <input type="email" id="kel_email" placeholder="nama@email.com" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Pekerjaan</label>
                        <input type="text" id="kel_pekerjaan" placeholder="Contoh: Karyawan, Pelajar" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Pendidikan Terakhir</label>
                        <select id="kel_pendidikan_terakhir" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;background:#fff;">
                            <option value="">-- Pilih Pendidikan --</option>
                            <option value="Tidak Sekolah">Tidak Sekolah</option>
                            <option value="SD">SD</option>
                            <option value="SMP">SMP</option>
                            <option value="SMA/SMK">SMA/SMK</option>
                            <option value="D1/D2/D3">D1/D2/D3</option>
                            <option value="S1">S1</option>
                            <option value="S2">S2</option>
                            <option value="S3">S3</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Agama</label>
                        <select id="kel_agama" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;background:#fff;">
                            <option value="">-- Pilih Agama --</option>
                            <option value="Islam">Islam</option>
                            <option value="Kristen Protestan">Kristen Protestan</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Buddha">Buddha</option>
                            <option value="Konghucu">Konghucu</option>
                        </select>
                    </div>

                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:800;color:#352D3E;display:block;margin-bottom:4px;">Alamat Tempat Tinggal</label>
                        <textarea id="kel_alamat" rows="2" placeholder="Alamat domisili anggota keluarga" style="width:100%;padding:10px 12px;border:1px solid #D2C1EC;border-radius:10px;font-size:13px;box-sizing:border-box;"></textarea>
                    </div>
                </div>
            </div>

            <!-- FOOTER MODAL (STICKY AT BOTTOM) -->
            <div style="padding:14px 24px;border-top:1px solid #F0E6FA;background:#FAF8FD;display:flex;justify-content:flex-end;gap:10px;flex-shrink:0;">
                <button type="button" onclick="tutupModalTambahKeluarga()" style="padding:9px 18px;border-radius:10px;background:#fff;color:#6A22A7;border:1.5px solid #D8CAEC;font-weight:800;font-size:13px;cursor:pointer;">
                    Batal
                </button>
                <button type="submit" id="btnSimpanKeluarga" style="padding:9px 22px;border-radius:10px;background:#6A22A7;color:#fff;border:none;font-weight:800;font-size:13px;cursor:pointer;box-shadow:0 4px 14px rgba(106,34,167,0.25);">
                    Simpan Anggota Keluarga
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
let currentStep = 1;
let currentPreviewData = null;
let currentSelectedFamilyId = 0;
let currentSelectedFamilyData = null;

function showStep(step) {
    currentStep = step;
    document.querySelectorAll('.step-panel').forEach(panel => {
        panel.classList.toggle('active', Number(panel.dataset.step) === step);
    });

    document.querySelectorAll('.progress-step').forEach(item => {
        const n = Number(item.dataset.progress);
        item.classList.toggle('active', n === step);
        item.classList.toggle('done', n < step);
    });

    const formCard = document.querySelector('.container') || document.querySelector('.registration-card');
    if (formCard) {
        const yOffset = -90; // offset navbar
        const y = formCard.getBoundingClientRect().top + window.pageYOffset + yOffset;
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    if (step === 2) {
        updateTindakanVisibility();
        updateSesiVisibility();
    }
    if (step === 3) updateConfirmation();
}

function validateCurrentStep() {
    const panel = document.querySelector('.step-panel.active');
    if (!panel) return true;

    if (currentStep === 1) {
        // Validasi seluruh input bertanda required yang tampil di layar
        const requiredInputs = panel.querySelectorAll('input[required], select[required], textarea[required]');
        for (const el of requiredInputs) {
            if (el.offsetParent !== null && !String(el.value || '').trim()) {
                const grp = el.closest('.form-group');
                const labelTxt = grp?.querySelector('label')?.textContent.replace('*','').trim() || 'kolom ini';
                alert('Mohon lengkapi ' + labelTxt + ' yang bertanda wajib (*).');
                el.focus();
                return false;
            }
        }

        const nikEl = document.getElementById('nik');
        if (nikEl && nikEl.value.trim() && !/^\d{16}$/.test(nikEl.value.trim())) {
            alert('NIK harus terdiri dari 16 digit angka valid.');
            nikEl.focus();
            return false;
        }

        const jenisPasien = document.getElementById('field_jenis_pasien').value;
        if (jenisPasien === 'BPJS') {
            const noJkn = document.getElementById('nomor_antrean_jkn').value.trim();
            if (!noJkn) {
                alert('Untuk pendaftaran BPJS, silakan isi Nomor Antrean dari aplikasi Mobile JKN.');
                bukaModalMobileJkn();
                return false;
            }
        }

        const adaRujukan = document.getElementById('ada_rujukan').value === 'Ya';
        if (adaRujukan) {
            const namaPerujuk = document.getElementById('nama_perujuk').value.trim();
            const fileRujukan = document.getElementById('file_rujukan');
            if (!namaPerujuk) {
                alert('Silakan isi nama dokter atau fasilitas perujuk.');
                document.getElementById('nama_perujuk').focus();
                return false;
            }
            if (!fileRujukan.files || !fileRujukan.files[0]) {
                alert('Silakan unggah berkas surat rujukan Anda.');
                return false;
            }
        }
    }

    if (currentStep === 2) {
        const idPoli = document.getElementById('id_poli').value;
        const tgl = document.getElementById('tanggal_kunjungan').value;
        const idDokter = document.getElementById('id_dokter').value;
        const idBidan = document.getElementById('id_bidan').value;
        const selSesi = document.getElementById('sesi_kunjungan');
        const groupSesi = document.getElementById('group_sesi');
        const isSesiVisible = groupSesi && groupSesi.style.display !== 'none';

        if (!idPoli) {
            alert('Silakan pilih poli pelayanan.');
            return false;
        }
        if (!tgl) {
            alert('Silakan pilih tanggal kunjungan.');
            return false;
        }
        if (isSesiVisible && selSesi && selSesi.options[selSesi.selectedIndex] && selSesi.options[selSesi.selectedIndex].disabled) {
            alert('Sesi kunjungan yang Anda pilih sudah selesai / lewat untuk hari ini. Silakan pilih sesi lain atau tanggal kunjungan berikutnya.');
            return false;
        }
        if (!idDokter && !idBidan) {
            alert('Silakan pilih tenaga kesehatan (dokter atau bidan) yang tersedia.');
            return false;
        }
        if (currentPreviewData && currentPreviewData.tersedia === false) {
            alert('Pendaftaran online untuk jadwal ini telah ditutup / kuota penuh. Anda tetap dapat melihat informasi jadwal pada kartu di atas, atau silakan pilih tanggal/jadwal lain yang masih tersedia.');
            return false;
        }
    }

    return true;
}

function nextStep() {
    if (!validateCurrentStep()) return;
    if (currentStep < 3) showStep(currentStep + 1);
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function ubahTipePasien() {
    const choice = document.querySelector('input[name="tipe_pasien"]:checked');
    const saveBtn = document.getElementById('saveDataButton');
    const saveStatus = document.getElementById('saveProfileStatus');
    const choiceSendiri = document.getElementById('choiceSendiri');
    const choiceKeluarga = document.getElementById('choiceKeluarga');
    const boxKeluarga = document.getElementById('boxDataKeluarga');
    const descDataDiri = document.getElementById('descDataDiri');

    if (choice && choice.value === 'sendiri') {
        currentSelectedFamilyId = 0;
        currentSelectedFamilyData = null;
        choiceSendiri.classList.add('active-choice');
        choiceKeluarga.classList.remove('active-choice');
        if (boxKeluarga) boxKeluarga.style.display = 'none';
        if (descDataDiri) descDataDiri.textContent = 'Data akun otomatis digunakan ketika memilih "Pasien Sendiri".';
        isiDataSendiri();
        if (saveBtn) {
            saveBtn.style.display = 'inline-flex';
            saveBtn.textContent = '💾 Simpan Perubahan Data';
        }
        if (saveStatus) saveStatus.textContent = '';
    } else {
        choiceKeluarga.classList.add('active-choice');
        choiceSendiri.classList.remove('active-choice');
        if (boxKeluarga) boxKeluarga.style.display = 'block';
        if (descDataDiri) descDataDiri.textContent = 'Pilih dari Data Keluarga di atas atau masukkan identitas anggota keluarga.';
        // Tombol simpan perubahan data tetap tampil sesuai permintaan user
        if (saveBtn) {
            saveBtn.style.display = 'inline-flex';
            saveBtn.textContent = '💾 Simpan Perubahan Data';
        }
        if (saveStatus) saveStatus.textContent = '';

        // Otomatis pilih anggota keluarga pertama jika ada
        const firstBtn = document.querySelector('.family-cards-list .btn-pilih-keluarga');
        if (firstBtn && !document.querySelector('.family-member-card.active-family')) {
            firstBtn.click();
        } else if (!firstBtn) {
            ['nik','nama_lengkap','tanggal_lahir','jenis_kelamin','no_telepon','email','alamat','pekerjaan','status_perkawinan','pendidikan_terakhir','agama','id_pasien'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
    }
}

function pilihAnggotaKeluarga(k, btn) {
    currentSelectedFamilyId = (k && k.id_keluarga) ? parseInt(k.id_keluarga, 10) : 0;
    currentSelectedFamilyData = k || null;

    document.querySelectorAll('.family-member-card').forEach(card => {
        card.classList.remove('active-family');
        card.style.borderColor = '#E8DEF5';
        card.style.background = '#FFFFFF';
        const b = card.querySelector('.btn-pilih-keluarga');
        if (b) {
            b.textContent = 'Pilih';
            b.style.background = '#F4EDFB';
            b.style.color = '#6A22A7';
        }
    });

    const card = btn.closest('.family-member-card');
    if (card) {
        card.classList.add('active-family');
        card.style.borderColor = '#6A22A7';
        card.style.background = '#F8F2FC';
        btn.textContent = '✓ Terpilih';
        btn.style.background = '#6A22A7';
        btn.style.color = '#FFFFFF';
    }

    if (document.getElementById('nik')) document.getElementById('nik').value = k.nik || '';
    if (document.getElementById('nama_lengkap')) document.getElementById('nama_lengkap').value = k.nama_lengkap || '';
    if (document.getElementById('nama_ibu_kandung')) document.getElementById('nama_ibu_kandung').value = k.nama_ibu_kandung || '';
    if (document.getElementById('nama_suami')) document.getElementById('nama_suami').value = k.nama_suami || k.nama_ayah_kandung || '';
    if (document.getElementById('nama_ayah_kandung')) document.getElementById('nama_ayah_kandung').value = k.nama_ayah_kandung || '';
    if (document.getElementById('tanggal_lahir')) document.getElementById('tanggal_lahir').value = k.tanggal_lahir || '';
    if (document.getElementById('jenis_kelamin')) document.getElementById('jenis_kelamin').value = k.jenis_kelamin || 'P';
    if (document.getElementById('no_telepon')) document.getElementById('no_telepon').value = k.no_telepon || '';
    if (document.getElementById('email')) document.getElementById('email').value = k.email || '';
    if (document.getElementById('alamat')) document.getElementById('alamat').value = k.alamat || '';
    if (document.getElementById('pekerjaan')) document.getElementById('pekerjaan').value = k.pekerjaan || '';
    if (document.getElementById('status_perkawinan')) document.getElementById('status_perkawinan').value = k.status_perkawinan || '';
    if (document.getElementById('pendidikan_terakhir')) document.getElementById('pendidikan_terakhir').value = k.pendidikan_terakhir || '';
    if (document.getElementById('agama')) document.getElementById('agama').value = k.agama || '';
    if (document.getElementById('id_pasien')) document.getElementById('id_pasien').value = '';
    updateAyahSuamiField();
}

function bukaModalTambahKeluarga() {
    const m = document.getElementById('modalTambahKeluarga');
    if (m) {
        m.style.display = 'flex';
        const b = document.getElementById('modalTambahKeluargaBody');
        if (b) b.scrollTop = 0;
    }
}

function tutupModalTambahKeluarga() {
    const m = document.getElementById('modalTambahKeluarga');
    if (m) m.style.display = 'none';
}

function simpanAnggotaKeluarga(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSimpanKeluarga');
    if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan...'; }

    const data = new FormData();
    data.append('nama_lengkap', document.getElementById('kel_nama').value.trim());
    data.append('nik', document.getElementById('kel_nik').value.trim());
    data.append('nama_ibu_kandung', document.getElementById('kel_nama_ibu_kandung') ? document.getElementById('kel_nama_ibu_kandung').value.trim() : '');
    data.append('nama_suami', document.getElementById('kel_nama_suami') ? document.getElementById('kel_nama_suami').value.trim() : '');
    data.append('nama_ayah_kandung', document.getElementById('kel_nama_ayah_kandung') ? document.getElementById('kel_nama_ayah_kandung').value.trim() : '');
    data.append('hubungan', document.getElementById('kel_hubungan').value);
    data.append('tanggal_lahir', document.getElementById('kel_tanggal_lahir').value);
    data.append('jenis_kelamin', document.getElementById('kel_jenis_kelamin').value);
    data.append('no_telepon', document.getElementById('kel_no_telepon').value.trim());
    data.append('email', document.getElementById('kel_email').value.trim());
    data.append('pekerjaan', document.getElementById('kel_pekerjaan') ? document.getElementById('kel_pekerjaan').value.trim() : '');
    data.append('status_perkawinan', document.getElementById('kel_status_perkawinan') ? document.getElementById('kel_status_perkawinan').value : '');
    data.append('pendidikan_terakhir', document.getElementById('kel_pendidikan_terakhir') ? document.getElementById('kel_pendidikan_terakhir').value : '');
    data.append('agama', document.getElementById('kel_agama') ? document.getElementById('kel_agama').value : '');
    data.append('alamat', document.getElementById('kel_alamat').value.trim());

    fetch('simpan_keluarga.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(res => {
        if (btn) { btn.disabled = false; btn.textContent = 'Simpan Anggota Keluarga'; }
        if (res.success && res.data) {
            const k = res.data;
            const list = document.getElementById('familyCardsList');
            const emptyNotice = document.getElementById('emptyFamilyNotice');
            if (emptyNotice) emptyNotice.style.display = 'none';

            const card = document.createElement('div');
            card.className = 'family-member-card';
            card.id = 'cardFamily_' + k.id_keluarga;
            card.style.cssText = 'background:#FFFFFF; border:1.5px solid #E8DEF5; border-radius:14px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; transition:all 0.2s;';
            card.innerHTML = `
                <div>
                    <div style="font-weight:800; font-size:14px; color:#2B1B48;">${escapeHtml(k.nama_lengkap)}</div>
                    <div style="font-size:12px; color:#78668E; margin-top:2px;">
                        NIK: ${escapeHtml(k.nik)} &bull; <span style="color:#6A22A7;font-weight:700;">${escapeHtml(k.hubungan)}</span>
                    </div>
                </div>
                <button type="button" class="btn-pilih-keluarga" style="padding:6px 18px; font-size:12px; font-weight:800; border-radius:999px; background:#F4EDFB; color:#6A22A7; border:1px solid #D8CAEC; cursor:pointer; transition:0.2s;">
                    Pilih
                </button>
            `;
            const pBtn = card.querySelector('.btn-pilih-keluarga');
            pBtn.onclick = function() { pilihAnggotaKeluarga(k, this); };
            list.prepend(card);

            pilihAnggotaKeluarga(k, pBtn);
            tutupModalTambahKeluarga();
            document.getElementById('formTambahKeluarga').reset();
            alert('Anggota keluarga baru berhasil disimpan dan dipilih!');
        } else {
            alert(res.message || 'Gagal menyimpan anggota keluarga.');
        }
    })
    .catch(err => {
        if (btn) { btn.disabled = false; btn.textContent = 'Simpan Anggota Keluarga'; }
        alert('Terjadi kesalahan jaringan.');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function isiDataSendiri() {
    document.getElementById('nik').value = <?= json_encode($nikUser) ?>;
    document.getElementById('nama_lengkap').value = <?= json_encode($namaPasien) ?>;
    if (document.getElementById('nama_ibu_kandung')) document.getElementById('nama_ibu_kandung').value = <?= json_encode($namaIbuKandungUser) ?>;
    if (document.getElementById('nama_suami')) document.getElementById('nama_suami').value = <?= json_encode($namaSuamiUser ?: $namaAyahKandungUser) ?>;
    if (document.getElementById('nama_ayah_kandung')) document.getElementById('nama_ayah_kandung').value = <?= json_encode($namaAyahKandungUser) ?>;
    document.getElementById('tanggal_lahir').value = <?= json_encode($tanggalLahirUser) ?>;
    document.getElementById('jenis_kelamin').value = <?= json_encode($jenisKelaminUser) ?>;
    document.getElementById('no_telepon').value = <?= json_encode($teleponUser) ?>;
    document.getElementById('email').value = <?= json_encode($emailUser) ?>;
    document.getElementById('alamat').value = <?= json_encode($alamatUser) ?>;
    document.getElementById('pekerjaan').value = <?= json_encode($pekerjaanUser) ?>;
    document.getElementById('status_perkawinan').value = <?= json_encode($statusPerkawinanUser) ?>;
    document.getElementById('pendidikan_terakhir').value = <?= json_encode($pendidikanUser) ?>;
    document.getElementById('agama').value = <?= json_encode($agamaUser) ?>;
    document.getElementById('id_pasien').value = '';
    updateAyahSuamiField();
}

function setRujukanChoice(val) {
    document.getElementById('ada_rujukan').value = val;
    const group = document.getElementById('asalRujukanGroup');
    const cTidak = document.getElementById('choiceRujukanTidak');
    const cYa = document.getElementById('choiceRujukanYa');
    group.style.display = val === 'Ya' ? 'block' : 'none';
    if (val === 'Ya') {
        cYa.classList.add('active-choice');
        cTidak.classList.remove('active-choice');
    } else {
        cTidak.classList.add('active-choice');
        cYa.classList.remove('active-choice');
    }
}

function onLayananChange() {
    if (isUpdatingTindakan) return;
    const sel = document.getElementById('jenis_perawatan')?.value || '';
    const poliSel = document.getElementById('id_poli');
    if (!poliSel) return;

    // Jika memilih tindakan kebidanan / kandungan / USG tetapi poli aktif bukan 3 atau 4
    if ((sel.includes('USG') || sel.includes('Kandungan') || sel.includes('Sp.OG') || sel.includes('Kebidanan') || sel.includes('Bersalin')) && !['3', '4'].includes(poliSel.value)) {
        for (let opt of poliSel.options) {
            if (opt.value === '4' || opt.text.toLowerCase().includes('kandungan') || opt.text.toLowerCase().includes('kebidanan')) {
                if (poliSel.value !== opt.value) {
                    poliSel.value = opt.value;
                    onFilterChange();
                }
                break;
            }
        }
        // Otomatis pilih Dokter Spesialis Kandungan (Dr. Eddy Sp.OG / dr. Patricia)
        setTimeout(() => {
            const spogCard = [...document.querySelectorAll('.medical-card:not([style*="display: none"]):not(.is-cuti)')].find(c => {
                const text = (c.innerText || '').toLowerCase();
                return text.includes('sp.og') || text.includes('eddy') || text.includes('patricia') || text.includes('kandungan');
            }) || document.querySelector('.medical-card:not([style*="display: none"]):not(.is-cuti)');
            if (spogCard) pilihTenagaMedis(spogCard);
        }, 60);
    } else if (sel.includes('Gigi') && poliSel.value !== '2') {
        for (let opt of poliSel.options) {
            if (opt.value === '2' || opt.text.toLowerCase().includes('gigi')) {
                if (poliSel.value !== opt.value) {
                    poliSel.value = opt.value;
                    onFilterChange();
                }
                break;
            }
        }
    }
}

function pilihTenagaMedis(el) {
    if (el.classList.contains('is-cuti')) {
        alert('Tenaga medis ini sedang cuti / tidak hadir. Silakan pilih dokter atau bidan lain yang tersedia.');
        return;
    }

    document.querySelectorAll('.medical-card').forEach(card => card.classList.remove('selected'));
    el.classList.add('selected');

    const id = el.dataset.id;
    const jenis = el.dataset.jenis;
    document.getElementById('id_dokter').value = (jenis === 'Dokter') ? id : '';
    document.getElementById('id_bidan').value = (jenis === 'Bidan') ? id : '';

    const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
    if (btnNext) {
        btnNext.disabled = false;
        btnNext.style.opacity = '';
        btnNext.style.cursor = '';
        btnNext.title = '';
    }

    updateSesiVisibility();
    hitungAntreanOtomatis();
}

function getSelectedMedical() {
    return document.querySelector('.medical-card.selected:not([style*="display: none"])') || document.querySelector('.medical-card.selected');
}

function updateSesiVisibility() {
    const groupSesi = document.getElementById('group_sesi');
    const selectSesi = document.getElementById('sesi_kunjungan');
    if (!groupSesi || !selectSesi) return;

    const med = getSelectedMedical();
    const tglInput = document.getElementById('tanggal_kunjungan');
    if (!med || !tglInput || !tglInput.value) {
        groupSesi.style.display = 'none';
        return;
    }

    let schedules = [];
    try {
        schedules = JSON.parse(med.dataset.schedule || '[]');
    } catch(e) {
        schedules = [];
    }

    const d = new Date(tglInput.value + 'T00:00:00');
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const chosenDay = days[d.getDay()];

    const dayScheds = schedules.filter(s => (s.hari || '').toLowerCase() === chosenDay.toLowerCase());

    let hasSore = false;
    let hasPagi = false;
    let soreJam = '16:00 - 20:00 WIB';
    let pagiJam = '09:00 - 12:00 WIB';

    dayScheds.forEach(s => {
        const startH = parseInt((s.jam_mulai || '00').split(':')[0], 10);
        const jamStr = (s.jam_mulai ? s.jam_mulai.substring(0, 5) : '') + ' - ' + (s.jam_selesai ? s.jam_selesai.substring(0, 5) : '') + ' WIB';
        if (startH >= 13) {
            hasSore = true;
            soreJam = jamStr;
        } else {
            hasPagi = true;
            pagiJam = jamStr;
        }
    });

    const activeSessions = [];
    if (hasPagi) activeSessions.push({ val: 'Pagi', label: '🌅 Sesi Pagi (' + pagiJam + ')', cutoff: 720 });
    if (hasSore) activeSessions.push({ val: 'Sore/Malam', label: '🌙 Sesi Sore (' + soreJam + ')', cutoff: 1230 });

    // Jika memiliki lebih dari 1 sesi
    if (activeSessions.length > 1) {
        groupSesi.style.display = 'block';

        const prevVal = selectSesi.value;
        selectSesi.innerHTML = '';
        activeSessions.forEach(sess => {
            const opt = document.createElement('option');
            opt.value = sess.val;
            opt.textContent = sess.label;
            selectSesi.appendChild(opt);
        });

        if (activeSessions.some(s => s.val === prevVal)) {
            selectSesi.value = prevVal;
        } else {
            selectSesi.value = activeSessions[0].val;
        }

        cekKetersediaanSesi(activeSessions);
    } else if (activeSessions.length === 1) {
        // Tampilkan sesi yang sesuai dengan tanggal kunjungan terpilih
        groupSesi.style.display = 'block';
        selectSesi.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = activeSessions[0].val;
        opt.textContent = activeSessions[0].label;
        selectSesi.appendChild(opt);
        selectSesi.value = activeSessions[0].val;
        cekKetersediaanSesi(activeSessions);
    } else {
        groupSesi.style.display = 'none';
        selectSesi.value = 'Pagi';
        const alertBox = document.getElementById('allSessionsClosedAlert');
        if (alertBox) alertBox.style.display = 'none';
    }
}

function cekKetersediaanSesi(activeSessions) {
    const tglInput = document.getElementById('tanggal_kunjungan');
    const selectSesi = document.getElementById('sesi_kunjungan');
    if (!tglInput || !selectSesi) return;

    const tglPilihan = tglInput.value;
    const now = new Date();
    const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const isToday = (tglPilihan === todayStr);

    const nowMinutes = (now.getHours() * 60) + now.getMinutes();

    const selPoliEl = document.getElementById('id_poli');
    const isPersalinan = selPoliEl && (selPoliEl.value === '5' || (selPoliEl.options[selPoliEl.selectedIndex]?.textContent || '').toLowerCase().includes('persalinan'));

    let firstActive = null;
    let totalActive = 0;

    Array.from(selectSesi.options).forEach(opt => {
        const sessObj = (activeSessions && Array.isArray(activeSessions)) ? activeSessions.find(s => s.val === opt.value) : null;
        const cutoff = sessObj ? sessObj.cutoff : (opt.value === 'Pagi' ? 720 : (opt.value === 'Siang' ? 960 : 1230));
        const baseLabel = sessObj ? sessObj.label : opt.textContent;

        if (isPersalinan) {
            opt.disabled = false;
            opt.textContent = baseLabel + ' (✨ Siaga 24 Jam Nonstop)';
            totalActive++;
            if (!firstActive) firstActive = opt.value;
        } else if (isToday && nowMinutes >= cutoff) {
            opt.disabled = true;
            opt.textContent = baseLabel + ' (⛔ Pendaftaran Ditutup)';
        } else {
            opt.disabled = false;
            opt.textContent = baseLabel;
            totalActive++;
            if (!firstActive) firstActive = opt.value;
        }
    });

    const currentSelected = selectSesi.querySelector('option[value="' + selectSesi.value + '"]');
    if (currentSelected && currentSelected.disabled && firstActive) {
        selectSesi.value = firstActive;
    }

    let alertBox = document.getElementById('allSessionsClosedAlert');
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = 'allSessionsClosedAlert';
        alertBox.style.cssText = 'background:#FEE2E2;border:1.5px solid #FCA5A5;color:#991B1B;padding:12px 16px;border-radius:12px;font-size:12.5px;font-weight:700;margin-top:12px;display:none;';
        selectSesi.parentNode.appendChild(alertBox);
    }

    if (groupSesi.style.display === 'none') {
        selectSesi.required = false;
    } else {
        selectSesi.required = true;
    }

    if (isToday && totalActive === 0 && !isPersalinan) {
        alertBox.style.display = 'block';
        alertBox.innerHTML = '⚠️ <strong>Pendaftaran Ditutup:</strong> Seluruh sesi pelayanan untuk tanggal ini telah ditutup. Silakan pilih tanggal besok atau hari lain.';
    } else {
        alertBox.style.display = 'none';
    }
}

// Data tindakan pendaftaran yang dikelola oleh Admin
const allTindakanList = <?= json_encode($dataTindakanList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const initLayananUrl = <?= json_encode($layananTerpilih) ?>;
let isUpdatingTindakan = false;

function populateTindakanForPoli(idPoli, preferredValue = null) {
    const tindakanSel = document.getElementById('jenis_perawatan');
    const hintEl = document.getElementById('tindakan_hint');
    if (!tindakanSel) return;

    isUpdatingTindakan = true;
    const previousValue = preferredValue || tindakanSel.value;
    tindakanSel.innerHTML = '';

    const poliStr = String(idPoli || '').trim();

    if (!poliStr) {
        const defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = '-- Pilih Poli Terlebih Dahulu --';
        tindakanSel.appendChild(defOpt);
        if (hintEl) hintEl.textContent = '💡 Silakan pilih Poli Pelayanan terlebih dahulu';
        isUpdatingTindakan = false;
        return;
    }

    // Filter tindakan berdasarkan id_poli_terkait & instruksi pengguna:
    // 1. Kebidanan (3) & Spesialis Kebidanan/Kandungan (4): MUTLAK TIDAK BOLEH ADA GIGI
    // 2. Poli Umum (1): bisa periksa medis umum, cek darah/laboratorium, dan imunisasi
    // 3. Poli Gigi (2): hanya periksa gigi & mulut
    const matchingTindakan = allTindakanList.filter(tdk => {
        if (tdk.status && tdk.status !== 'aktif') return false;
        const terkait = String(tdk.id_poli_terkait || '').trim();
        const namaLower = (tdk.nama_tindakan || '').toLowerCase();

        // Syarat 1: Kebidanan (3) & Spesialis (4) mutlak TIDAK ADA yang periksa gigi
        if ((poliStr === '3' || poliStr === '4') && namaLower.includes('gigi')) {
            return false;
        }

        // Syarat 2: Poli Gigi (2) hanya tindakan gigi
        if (poliStr === '2') {
            if (namaLower.includes('usg') || namaLower.includes('kebidanan') || namaLower.includes('anc') || namaLower.includes('sp.og') || namaLower.includes('bersalin') || namaLower.includes('imunisasi')) {
                return false;
            }
        }

        // Syarat 3: Poli Umum (1) bisa umum, cek darah/lab, imunisasi (tidak ada spesialis kandungan, kebidanan/bersalin, gigi)
        if (poliStr === '1') {
            if (namaLower.includes('gigi') || namaLower.includes('sp.og') || namaLower.includes('bersalin') || namaLower.includes('usg')) {
                return false;
            }
        }

        // Syarat 4: Persalinan 24 Jam (5) hanya tindakan persalinan / kebidanan bersalin
        if (poliStr === '5') {
            if (namaLower.includes('gigi') || namaLower.includes('umum') || namaLower.includes('darah')) {
                return false;
            }
        }

        // Periksa kesesuaian kolom id_poli_terkait dari database
        if (terkait === '' || terkait === 'all') return true;
        const polis = terkait.split(',').map(s => s.trim());
        return polis.includes(poliStr);
    });

    if (matchingTindakan.length === 0) {
        const fallbackOpt = document.createElement('option');
        if (poliStr === '2') {
            fallbackOpt.value = 'Pemeriksaan Gigi & Mulut';
            fallbackOpt.textContent = '🦷 Pemeriksaan Gigi & Mulut';
        } else if (poliStr === '5') {
            fallbackOpt.value = 'Persalinan 24 Jam (Bersalin)';
            fallbackOpt.textContent = '🤰 Persalinan 24 Jam (Bersalin)';
        } else if (poliStr === '3' || poliStr === '4') {
            fallbackOpt.value = 'Pelayanan Kebidanan & ANC';
            fallbackOpt.textContent = '🤱 Pelayanan Kebidanan & ANC';
        } else {
            fallbackOpt.value = 'Pemeriksaan Medis Umum';
            fallbackOpt.textContent = '🩺 Pemeriksaan Medis Umum';
        }
        tindakanSel.appendChild(fallbackOpt);
    } else {
        matchingTindakan.forEach(tdk => {
            const opt = document.createElement('option');
            opt.value = tdk.nama_tindakan;
            const icon = tdk.icon || '🩺';
            opt.textContent = `${icon} ${tdk.nama_tindakan}`;
            tindakanSel.appendChild(opt);
        });
    }

    // Tentukan item mana yang terpilih
    let found = false;
    if (previousValue) {
        for (let i = 0; i < tindakanSel.options.length; i++) {
            if (tindakanSel.options[i].value === previousValue) {
                tindakanSel.selectedIndex = i;
                found = true;
                break;
            }
        }
    }

    if (!found && initLayananUrl) {
        for (let i = 0; i < tindakanSel.options.length; i++) {
            const optVal = tindakanSel.options[i].value.toLowerCase();
            if (initLayananUrl.includes('usg') && optVal.includes('usg')) {
                tindakanSel.selectedIndex = i;
                found = true;
                break;
            }
            if ((initLayananUrl.includes('persalinan') || initLayananUrl.includes('bersalin')) && optVal.includes('persalinan')) {
                tindakanSel.selectedIndex = i;
                found = true;
                break;
            }
        }
    }

    if (!found && tindakanSel.options.length > 0) {
        tindakanSel.selectedIndex = 0;
    }

    // Update petunjuk text di bawah dropdown
    if (hintEl) {
        if (poliStr === '1') {
            hintEl.innerHTML = '💡 Tersedia Pemeriksaan Medis Umum, Cek Darah / Lab, dan Imunisasi';
        } else if (poliStr === '2') {
            hintEl.innerHTML = '💡 Layanan pemeriksaan dan perawatan kesehatan gigi &amp; mulut';
        } else if (poliStr === '5') {
            hintEl.innerHTML = '💡 <strong>Persalinan 24 Jam Siaga:</strong> Pertolongan persalinan normal dan kebidanan darurat siaga 24 jam nonstop didampingi bidan bersertifikat.';
        } else if (poliStr === '3' || poliStr === '4') {
            hintEl.innerHTML = '💡 Layanan kebidanan, pemeriksaan kehamilan ANC, USG &amp; Sp.OG';
        } else {
            hintEl.innerHTML = '💡 Sesuaikan dengan kebutuhan pemeriksaan Anda';
        }
    }

    isUpdatingTindakan = false;
}

function updateTindakanVisibility() {
    const poliSel = document.getElementById('id_poli');
    const groupTindakan = document.getElementById('group_tindakan');
    const groupPoli = document.getElementById('group_poli');
    const tindakanSel = document.getElementById('jenis_perawatan');
    if (!poliSel || !groupTindakan) return;

    const idPoli = poliSel.value;
    populateTindakanForPoli(idPoli);

    // Selalu tampilkan group tindakan untuk semua poli
    groupTindakan.style.display = 'block';
    if (groupPoli) groupPoli.classList.remove('poli-full');
    if (tindakanSel) tindakanSel.required = true;
}

function pilihPersalinan24Jam() {
    const sel = document.getElementById('id_poli');
    if (!sel) return;
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === '5' || sel.options[i].text.toLowerCase().includes('persalinan')) {
            sel.value = sel.options[i].value;
            break;
        }
    }
    onFilterChange();
    const groupPoli = document.getElementById('group_poli');
    if (groupPoli) {
        groupPoli.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function onFilterChange() {
    updateTindakanVisibility();
    updateSesiVisibility();
    const idPoli = document.getElementById('id_poli').value;
    const tgl = document.getElementById('tanggal_kunjungan').value;
    const msg = document.getElementById('medicalMessage');
    const list = document.getElementById('medicalList');
    const cards = document.querySelectorAll('.medical-card');
    const recBanner = document.getElementById('recommendBanner');
    const previewCard = document.getElementById('queuePreviewCard');

    // Selalu pulihkan status tombol Next setiap kali filter berubah
    const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
    if (btnNext) {
        btnNext.disabled = false;
        btnNext.style.opacity = '';
        btnNext.style.cursor = '';
        btnNext.title = '';
    }

    if (recBanner) recBanner.style.display = 'none';

    if (!idPoli || !tgl) {
        list.style.display = 'none';
        msg.style.display = 'block';
        msg.textContent = 'Silakan pilih poli dan tanggal pemeriksaan terlebih dahulu.';
        if (previewCard) previewCard.style.display = 'none';
        updateSesiVisibility();
        return;
    }

    const dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const chosenDay = dayNames[new Date(tgl + 'T00:00:00').getDay()];

    let visibleCount = 0;
    cards.forEach(card => {
        let schedules = [];
        try { schedules = JSON.parse(card.dataset.schedule || '[]'); } catch(e) {}
        
        // Cek apakah poli cocok
        const matchesPoli = (String(card.dataset.poli) === String(idPoli)) || schedules.some(s => String(s.id_poli) === String(idPoli));
        
        // Tampilkan semua dokter dari poli terkait agar pasien tetap bisa melihat dokter lain
        if (matchesPoli) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
            card.classList.remove('selected');
        }
    });

    if (visibleCount === 0) {
        list.style.display = 'none';
        msg.style.display = 'block';
        msg.textContent = 'Tidak ada dokter atau bidan yang terdaftar pada poli ini.';
        currentPreviewData = null;
        if (previewCard) previewCard.style.display = 'none';
        updateSesiVisibility();
    } else {
        list.style.display = 'grid';
        msg.style.display = 'none';
        
        const currentSelected = getSelectedMedical();
        if (currentSelected && !currentSelected.classList.contains('is-cuti')) {
            updateSesiVisibility();
            hitungAntreanOtomatis();
        } else {
            // Pilih kartu aktif pertama yang bukan cuti
            const firstCard = list.querySelector('.medical-card:not([style*="display: none"]):not(.is-cuti)');
            if (firstCard) {
                pilihTenagaMedis(firstCard);
            } else {
                currentPreviewData = null;
                updateSesiVisibility();
            }
        }
    }
}

async function hitungAntreanOtomatis() {
    const card = getSelectedMedical();
    const tgl = document.getElementById('tanggal_kunjungan')?.value;
    const idPoli = document.getElementById('id_poli')?.value;
    const previewCard = document.getElementById('queuePreviewCard');
    const recBanner = document.getElementById('recommendBanner');
    const recContainer = document.getElementById('recommendSlotsContainer');

    if (!card || !tgl || !idPoli) {
        currentPreviewData = null;
        if (previewCard) previewCard.style.display = 'none';
        if (recBanner) recBanner.style.display = 'none';
        return;
    }

    const isDokter = card.dataset.jenis === 'Dokter';
    const idStaff = card.dataset.id;
    const paramStaff = isDokter ? `dokter=${idStaff}` : `bidan=${idStaff}`;
    const sesiVal = document.getElementById('sesi_kunjungan')?.value || '';
    const paramSesi = sesiVal ? `&sesi=${encodeURIComponent(sesiVal)}` : '';

    try {
        const res = await fetch(`get_jadwal.php?tanggal=${encodeURIComponent(tgl)}&poli=${idPoli}&${paramStaff}${paramSesi}`);
        const data = await res.json();

        if (data.success && data.tenaga && data.tenaga.length > 0) {
            currentPreviewData = data.tenaga[0];
            previewCard.style.display = 'block';

            document.getElementById('previewTenagaNama').textContent = currentPreviewData.nama_tenaga;
            document.getElementById('previewNomorAntrean').textContent = currentPreviewData.nomor_antrean;
            document.getElementById('previewEstimasi').textContent = currentPreviewData.estimasi_pelayanan;
            document.getElementById('previewWaktuDatang').textContent = currentPreviewData.waktu_datang;
            document.getElementById('previewTotalAntrean').textContent = currentPreviewData.total_antrean_terdaftar + ' orang';

            // Jika hari ini pendaftaran ditutup (kurang 1 jam / cutoff / kuota habis)
            if (currentPreviewData.tersedia === false) {
                const isFull = (currentPreviewData.status_label === 'Full Booking' || (currentPreviewData.keterangan_ketersediaan || '').toLowerCase().includes('full booking'));
                if (isFull) {
                    document.getElementById('previewNote').innerHTML = '<span style="color:#D97706;font-weight:800;">🟡 Full Booking — Kuota pendaftaran untuk jadwal ini telah terisi penuh.</span>';
                    document.getElementById('previewNomorAntrean').innerHTML = '<span style="color:#D97706;font-size:14px;font-weight:800;">🟡 Full Booking</span>';
                } else {
                    document.getElementById('previewNote').innerHTML = '<span style="color:#B72B2B;font-weight:800;">⛔ Pendaftaran Ditutup</span>';
                    document.getElementById('previewNomorAntrean').innerHTML = '<span style="color:#B72B2B;font-size:14px;font-weight:800;">⛔ Pendaftaran Ditutup</span>';
                }
                const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
                if (btnNext) {
                    btnNext.disabled = true;
                    btnNext.style.opacity = '0.5';
                    btnNext.style.cursor = 'not-allowed';
                    btnNext.title = isFull ? 'Full Booking untuk jadwal ini' : 'Pendaftaran ditutup untuk jadwal ini';
                }
                
                if (data.jadwal_tersisa_minggu && data.jadwal_tersisa_minggu.length > 0) {
                    recBanner.style.display = 'block';
                    recContainer.innerHTML = '';
                    data.jadwal_tersisa_minggu.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'recommend-chip';
                        btn.innerHTML = `🗓️ <strong>${slot.hari}</strong> (${slot.tanggal_format}) • ${slot.jam_mulai} - ${slot.jam_selesai} WIB &rarr; <u>Pilih</u>`;
                        btn.onclick = function() {
                            document.getElementById('tanggal_kunjungan').value = slot.tanggal;
                            onFilterChange();
                        };
                        recContainer.appendChild(btn);
                    });
                } else {
                    recBanner.style.display = 'none';
                }
            } else {
                const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
                if (btnNext) {
                    btnNext.disabled = false;
                    btnNext.style.opacity = '';
                    btnNext.style.cursor = '';
                    btnNext.title = '';
                }
                recBanner.style.display = 'none';
                document.getElementById('previewNote').textContent = 'Nomor antrean dan jam di atas adalah perkiraan real-time berdasarkan kapasitas dan antrean klinik.';
            }
        } else {
            currentPreviewData = null;
            previewCard.style.display = 'none';
            if (recBanner) recBanner.style.display = 'none';
            const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
            if (btnNext) {
                btnNext.disabled = false;
                btnNext.style.opacity = '';
                btnNext.style.cursor = '';
                btnNext.title = '';
            }
        }
    } catch(err) {
        console.error('Gagal memuat jadwal antrean:', err);
        const btnNext = document.querySelector('.step-panel[data-step="2"] .btn-primary');
        if (btnNext) {
            btnNext.disabled = false;
            btnNext.style.opacity = '';
            btnNext.style.cursor = '';
            btnNext.title = '';
        }
    }
}

function updateConfirmation() {
    const val = id => document.getElementById(id)?.value || '-';
    const card = getSelectedMedical();
    const poli = document.getElementById('id_poli');
    const poliText = poli?.options[poli.selectedIndex]?.text || '-';

    document.getElementById('confirmNama').textContent = val('nama_lengkap');
    document.getElementById('confirmNik').textContent = val('nik');
    const ibuVal = val('nama_ibu_kandung');
    const suamiVal = val('nama_suami');
    const ayahVal = val('nama_ayah_kandung');
    const statusNikahVal = val('status_perkawinan');
    const isBelumMenikahConf = (!statusNikahVal || statusNikahVal === 'Belum Menikah');

    const lblConfirmSuami = document.getElementById('confirmLabelSuami');
    if (lblConfirmSuami) {
        lblConfirmSuami.textContent = isBelumMenikahConf ? 'Nama Ayah Kandung' : 'Nama Suami / Pasangan';
    }

    if (document.getElementById('confirmNamaIbu')) document.getElementById('confirmNamaIbu').textContent = ibuVal !== '-' && ibuVal ? ibuVal : '(Tidak diisi)';
    if (document.getElementById('confirmNamaSuami')) {
        const valToDisplay = (suamiVal !== '-' && suamiVal) ? suamiVal : ((ayahVal !== '-' && ayahVal) ? ayahVal : '(Tidak diisi)');
        document.getElementById('confirmNamaSuami').textContent = valToDisplay;
    }
    const jenisPasienVal = val('field_jenis_pasien');
    document.getElementById('confirmJenisPasien').textContent = (jenisPasienVal === 'BPJS') ? '💳 BPJS Kesehatan' : '💵 Pasien Umum / Mandiri';
    document.getElementById('confirmNomorJkn').textContent = (jenisPasienVal === 'BPJS') ? (val('nomor_antrean_jkn') || '-') : '- (Khusus Pasien BPJS)';
    document.getElementById('confirmLayanan').textContent = val('jenis_perawatan');
    document.getElementById('confirmSesi').textContent = val('sesi_kunjungan');
    document.getElementById('confirmPoli').textContent = poliText;
    document.getElementById('confirmMedis').textContent = card ? card.dataset.nama + ' (' + card.dataset.jenis + ')' : '-';
    document.getElementById('confirmTanggal').textContent = val('tanggal_kunjungan');

    if (currentPreviewData) {
        document.getElementById('confirmNomorAntrean').textContent = currentPreviewData.nomor_antrean;
        document.getElementById('confirmEstimasi').textContent = currentPreviewData.estimasi_pelayanan;
        document.getElementById('confirmWaktuDatang').textContent = currentPreviewData.waktu_datang;
    } else {
        document.getElementById('confirmNomorAntrean').textContent = '-';
        document.getElementById('confirmEstimasi').textContent = '-';
        document.getElementById('confirmWaktuDatang').textContent = '-';
    }

    const adaRuj = val('ada_rujukan');
    const namaPer = val('nama_perujuk');
    document.getElementById('confirmRujukan').textContent = (adaRuj === 'Ya' ? 'Ada Rujukan' : 'Tidak Ada Rujukan') + (namaPer !== '-' && namaPer ? ' (' + namaPer + ')' : '');
    document.getElementById('confirmKeluhan').textContent = val('keluhan') || 'Tidak ada keluhan khusus.';
}

function pilihJenisPenjamin(val) {
    const hidden = document.getElementById('field_jenis_pasien');
    const cardUmum = document.getElementById('cardPenjaminUmum');
    const cardBpjs = document.getElementById('cardPenjaminBpjs');
    const compactStatus = document.getElementById('bpjsCompactStatus');

    if (hidden) hidden.value = val;

    if (val === 'BPJS') {
        cardBpjs.classList.add('active-choice');
        cardUmum.classList.remove('active-choice');
        if (compactStatus) compactStatus.style.display = 'flex';

        const cur = document.getElementById('nomor_antrean_jkn')?.value.trim() || '';
        if (!cur) {
            bukaModalMobileJkn();
        }
    } else {
        cardUmum.classList.add('active-choice');
        cardBpjs.classList.remove('active-choice');
        if (compactStatus) compactStatus.style.display = 'none';
    }
}

function bukaModalMobileJkn() {
    const modal = document.getElementById('modalMobileJkn');
    const cur = document.getElementById('nomor_antrean_jkn')?.value.trim() || '';
    const inp = document.getElementById('modalInputJkn');
    if (inp && cur) inp.value = cur;
    if (modal) modal.style.display = 'block';
}

function tutupModalMobileJkn() {
    const modal = document.getElementById('modalMobileJkn');
    if (modal) modal.style.display = 'none';
}

function simpanNomorJknDariModal() {
    const inp = document.getElementById('modalInputJkn');
    const val = inp ? inp.value.trim() : '';
    if (!val) {
        alert('Silakan masukkan nomor antrean Mobile JKN Anda terlebih dahulu.');
        return;
    }
    const hiddenInp = document.getElementById('nomor_antrean_jkn');
    if (hiddenInp) hiddenInp.value = val;
    const txt = document.getElementById('displayNoJknText');
    if (txt) txt.textContent = val;
    tutupModalMobileJkn();
}

async function simpanDataDiri() {
    const button = document.getElementById('saveDataButton');
    const status = document.getElementById('saveProfileStatus');
    if (!button || !status) return;

    const choice = document.querySelector('input[name="tipe_pasien"]:checked');
    const isKeluarga = choice && choice.value === 'keluarga';

    const required = ['nik','nama_lengkap','tanggal_lahir','jenis_kelamin','no_telepon','alamat'];
    for (const id of required) {
        const el = document.getElementById(id);
        if (!el || !String(el.value || '').trim()) {
            status.style.color = '#B44A4A';
            status.textContent = 'Lengkapi data diri wajib terlebih dahulu.';
            el?.focus();
            return;
        }
    }

    button.disabled = true;
    button.textContent = 'Menyimpan...';
    status.style.color = '#675F73';
    status.textContent = '';

    try {
        if (isKeluarga) {
            const formData = new FormData();
            formData.append('nama_lengkap', document.getElementById('nama_lengkap').value.trim());
            formData.append('nik', document.getElementById('nik').value.trim());
            formData.append('nama_ibu_kandung', document.getElementById('nama_ibu_kandung') ? document.getElementById('nama_ibu_kandung').value.trim() : '');
            formData.append('nama_suami', document.getElementById('nama_suami') ? document.getElementById('nama_suami').value.trim() : '');
            formData.append('tanggal_lahir', document.getElementById('tanggal_lahir').value);
            formData.append('jenis_kelamin', document.getElementById('jenis_kelamin').value);
            formData.append('no_telepon', document.getElementById('no_telepon').value.trim());
            formData.append('email', document.getElementById('email').value.trim());
            formData.append('pekerjaan', document.getElementById('pekerjaan').value.trim());
            formData.append('status_perkawinan', document.getElementById('status_perkawinan').value);
            formData.append('pendidikan_terakhir', document.getElementById('pendidikan_terakhir').value);
            formData.append('agama', document.getElementById('agama').value);
            formData.append('alamat', document.getElementById('alamat').value.trim());

            if (currentSelectedFamilyId > 0) {
                formData.append('action', 'update');
                formData.append('id_keluarga', currentSelectedFamilyId);
                if (currentSelectedFamilyData && currentSelectedFamilyData.hubungan) {
                    formData.append('hubungan', currentSelectedFamilyData.hubungan);
                }
            } else {
                formData.append('action', 'tambah');
                formData.append('hubungan', 'Keluarga');
            }

            const res = await fetch('simpan_keluarga.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success && data.data) {
                status.style.color = '#16a34a';
                status.textContent = data.message || 'Perubahan data keluarga berhasil disimpan.';
                const fam = data.data;

                if (currentSelectedFamilyId > 0) {
                    currentSelectedFamilyData = Object.assign({}, currentSelectedFamilyData, fam);
                    const card = document.getElementById('cardFamily_' + currentSelectedFamilyId);
                    if (card) {
                        const nameDiv = card.querySelector('div > div:first-child');
                        if (nameDiv) nameDiv.textContent = fam.nama_lengkap;
                        const infoDiv = card.querySelector('div > div:nth-child(2)');
                        if (infoDiv) {
                            const hub = fam.hubungan ? ` &bull; <span style="color:#6A22A7;font-weight:700;">${escapeHtml(fam.hubungan)}</span>` : '';
                            infoDiv.innerHTML = `NIK: ${escapeHtml(fam.nik)}${hub}`;
                        }
                        const b = card.querySelector('.btn-pilih-keluarga');
                        if (b) {
                            b.onclick = function() { pilihAnggotaKeluarga(fam, this); };
                        }
                    }
                } else {
                    currentSelectedFamilyId = fam.id_keluarga;
                    currentSelectedFamilyData = fam;
                    const list = document.getElementById('familyCardsList');
                    const emptyNotice = document.getElementById('emptyFamilyNotice');
                    if (emptyNotice) emptyNotice.style.display = 'none';

                    const card = document.createElement('div');
                    card.className = 'family-member-card active-family';
                    card.id = 'cardFamily_' + fam.id_keluarga;
                    card.style.cssText = 'background:#F8F2FC; border:1.5px solid #6A22A7; border-radius:14px; padding:12px 18px; display:flex; justify-content:space-between; align-items:center; transition:all 0.2s;';
                    card.innerHTML = `
                        <div>
                            <div style="font-weight:800; font-size:14px; color:#2B1B48;">${escapeHtml(fam.nama_lengkap)}</div>
                            <div style="font-size:12px; color:#78668E; margin-top:2px;">
                                NIK: ${escapeHtml(fam.nik)} &bull; <span style="color:#6A22A7;font-weight:700;">${escapeHtml(fam.hubungan)}</span>
                            </div>
                        </div>
                        <button type="button" class="btn-pilih-keluarga" style="padding:6px 18px; font-size:12px; font-weight:800; border-radius:999px; background:#6A22A7; color:#FFFFFF; border:1px solid #6A22A7; cursor:pointer; transition:0.2s;">
                            ✓ Terpilih
                        </button>
                    `;
                    const pBtn = card.querySelector('.btn-pilih-keluarga');
                    pBtn.onclick = function() { pilihAnggotaKeluarga(fam, this); };
                    list.prepend(card);
                }
            } else {
                status.style.color = '#B44A4A';
                status.textContent = data.message || 'Gagal menyimpan perubahan keluarga.';
            }
        } else {
            const formData = new FormData();
            formData.append('action', 'save_data_only');
            ['nik','nama_lengkap','nama_ibu_kandung','nama_suami','tanggal_lahir','jenis_kelamin','no_telepon','email','alamat','pekerjaan','status_perkawinan','pendidikan_terakhir','agama'].forEach(id => {
                const el = document.getElementById(id);
                if (el) formData.append(id, el.value);
            });

            const res = await fetch('proses_daftar.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            status.style.color = data.success ? '#16a34a' : '#B44A4A';
            status.textContent = data.message || 'Data diri selesai diproses.';
        }
    } catch(err) {
        status.style.color = '#B44A4A';
        status.textContent = 'Gagal menyimpan data.';
    } finally {
        button.disabled = false;
        button.textContent = '💾 Simpan Perubahan Data';
    }
}

function updateAyahSuamiField() {
    const tglLahirElem = document.getElementById('tanggal_lahir');
    const statusNikahElem = document.getElementById('status_perkawinan');
    const labelElem = document.getElementById('label_ayah_suami');
    const inputSuami = document.getElementById('nama_suami');
    const inputAyah = document.getElementById('nama_ayah_kandung');
    const hintElem = document.getElementById('hint_ayah_suami');

    if (!labelElem || !inputSuami) return;

    let isAnak = false;
    const tglLahirVal = tglLahirElem ? tglLahirElem.value : '';
    if (tglLahirVal) {
        const birthDate = new Date(tglLahirVal);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        if (age < 17) isAnak = true;
    }

    const rawNikah = statusNikahElem ? (statusNikahElem.value || '').trim().toLowerCase() : '';
    const isMenikah = (rawNikah === 'menikah' || rawNikah === 'cerai hidup' || rawNikah === 'cerai mati');
    const isWajib = <?= (isFieldRequired('nama_suami') || isFieldRequired('nama_ayah_kandung')) ? 'true' : 'false' ?>;

    if (isMenikah && !isAnak) {
        labelElem.innerHTML = 'Nama Suami ' + (isWajib ? '*' : '(Opsional)');
        inputSuami.placeholder = 'Contoh: Nama lengkap suami sesuai KTP';
        if (hintElem) {
            hintElem.innerHTML = '💍 Status Menikah: cantumkan nama suami.';
        }
    } else {
        labelElem.innerHTML = 'Nama Ayah Kandung ' + (isWajib ? '*' : '(Opsional)');
        inputSuami.placeholder = 'Contoh: Nama ayah kandung sesuai Kartu Keluarga (KK)';
        if (hintElem) {
            hintElem.innerHTML = isAnak 
                ? '👶 Pasien anak (< 17 tahun): cantumkan nama ayah kandung sesuai Kartu Keluarga.'
                : '👤 Status Belum Menikah / Pasien Anak: cantumkan nama ayah kandung.';
        }
        if (inputAyah && inputSuami.value) {
            inputAyah.value = inputSuami.value;
        }
    }
}

function updateKelAyahSuamiField() {
    const tglElem = document.getElementById('kel_tanggal_lahir');
    const nikahElem = document.getElementById('kel_status_perkawinan');
    const hubElem = document.getElementById('kel_hubungan');
    const labelElem = document.getElementById('kel_label_suami_ayah');
    const inputSuami = document.getElementById('kel_nama_suami');
    const inputAyah = document.getElementById('kel_nama_ayah_kandung');
    if (!labelElem || !inputSuami) return;

    let isAnak = false;
    if (tglElem && tglElem.value) {
        const birthDate = new Date(tglElem.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        if (age < 17) isAnak = true;
    }
    const hubVal = (hubElem ? hubElem.value : '').toLowerCase();
    if (hubVal.includes('anak')) isAnak = true;

    const nikahVal = (nikahElem ? nikahElem.value : '').trim().toLowerCase();
    const isMenikah = (nikahVal === 'menikah' || nikahVal === 'cerai hidup' || nikahVal === 'cerai mati');

    if (isMenikah && !isAnak) {
        labelElem.textContent = 'Nama Suami';
        inputSuami.placeholder = 'Contoh: Nama lengkap suami sesuai KTP';
    } else {
        labelElem.textContent = 'Nama Ayah Kandung';
        inputSuami.placeholder = 'Contoh: Nama ayah kandung sesuai Kartu Keluarga (KK)';
        if (inputAyah && inputSuami.value) inputAyah.value = inputSuami.value;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Pastikan seluruh choice card (tipe pasien, penjamin biaya, rujukan) dapat diklik secara responsif
    document.querySelectorAll('.choice-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            const radio = this.querySelector('input[type="radio"]');
            if (radio && e.target !== radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    // Pasang listener dinamis untuk perubahan status perkawinan dan tanggal lahir
    const tglInput = document.getElementById('tanggal_lahir');
    const nikahInput = document.getElementById('status_perkawinan');
    const suamiInput = document.getElementById('nama_suami');
    const ayahInput = document.getElementById('nama_ayah_kandung');

    if (tglInput) tglInput.addEventListener('change', updateAyahSuamiField);
    if (nikahInput) nikahInput.addEventListener('change', updateAyahSuamiField);
    if (suamiInput) {
        suamiInput.addEventListener('input', function() {
            const raw = (nikahInput ? nikahInput.value : '').trim().toLowerCase();
            const isNikah = (raw === 'menikah' || raw === 'cerai hidup' || raw === 'cerai mati');
            if (!isNikah && ayahInput) {
                ayahInput.value = this.value;
            }
        });
    }
    updateAyahSuamiField();

    const kelSuamiInput = document.getElementById('kel_nama_suami');
    const kelAyahInput = document.getElementById('kel_nama_ayah_kandung');
    if (kelSuamiInput) {
        kelSuamiInput.addEventListener('input', function() {
            const kelNikahElem = document.getElementById('kel_status_perkawinan');
            const rawK = (kelNikahElem ? kelNikahElem.value : '').trim().toLowerCase();
            const isNikahK = (rawK === 'menikah' || rawK === 'cerai hidup' || rawK === 'cerai mati');
            if (!isNikahK && kelAyahInput) {
                kelAyahInput.value = this.value;
            }
        });
    }
    updateKelAyahSuamiField();

    onFilterChange();
    const initDoc = <?= (int)$dokterTerpilih ?>;
    const initBid = <?= (int)$bidanTerpilih ?>;
    const initLay = <?= json_encode($layananTerpilih) ?>;
    if (initDoc || initBid) {
        const target = [...document.querySelectorAll('.medical-card')].find(c => {
            return (initDoc && c.dataset.jenis === 'Dokter' && Number(c.dataset.id) === initDoc)
                || (initBid && c.dataset.jenis === 'Bidan' && Number(c.dataset.id) === initBid);
        });
        if (target && !target.classList.contains('is-cuti')) pilihTenagaMedis(target);
    } else if (initLay && initLay.includes('usg')) {
        onLayananChange();
    }
    showStep(1);
});
</script>
</body>
</html>