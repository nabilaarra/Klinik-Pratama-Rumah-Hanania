<?php
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die("Koneksi database tidak tersedia.\n");
}
$db->set_charset('utf8mb4');

require_once __DIR__ . '/../includes/poin_helper.php';

echo "=== MEMULAI SINKRONISASI & PERHITUNGAN ULANG POIN PASIEN ===\n\n";

// 1. Pastikan pengaturan nilai poin default di database sudah tepat
$db->query("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('poin_kunjungan_bpjs', '1') ON DUPLICATE KEY UPDATE nilai = '1'");
$db->query("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('poin_kunjungan_biasa', '2') ON DUPLICATE KEY UPDATE nilai = '2'");
$db->query("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('poin_kunjungan_usg', '3') ON DUPLICATE KEY UPDATE nilai = '3'");
$db->query("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('poin_kunjungan_bersalin', '10') ON DUPLICATE KEY UPDATE nilai = '10'");

$teksPanduanBaru = "1. Dapatkan poin reward di setiap kunjungan pelayanan:\n"
    . "   • Kunjungan BPJS = 1 Poin\n"
    . "   • Kunjungan Biasa (Tanpa USG) = 2 Poin\n"
    . "   • Kunjungan USG = 3 Poin\n"
    . "   • Kunjungan Bersalin = 10 Poin\n"
    . "2. Dapatkan bonus +1 Poin dengan memberikan ulasan rating bintang 5 di Google Review.\n"
    . "3. Pilih reward atau voucher yang sesuai dengan jumlah poin yang Anda miliki.\n"
    . "4. Ajukan penukaran dan tunggu persetujuan (ACC) dari Admin Klinik.\n"
    . "5. Tunjukkan kode voucher resmi yang telah disetujui kepada petugas pendaftaran/kasir saat kunjungan berikutnya.";
$stTeks = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('poin_panduan_teks', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
if ($stTeks) {
    $stTeks->bind_param('s', $teksPanduanBaru);
    $stTeks->execute();
    $stTeks->close();
}

// 2. Ambil daftar pasien yang sudah mengklaim Google Review
$claimedRevIds = [];
$qRev = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'google_review_claimed_pasien_ids' LIMIT 1");
if ($qRev && $rRev = $qRev->fetch_assoc()) {
    $claimedRevIds = json_decode($rRev['nilai'] ?: '[]', true) ?: [];
}

// Bersihkan riwayat_poin lama untuk digantikan audit log akurat per pendaftaran & review
$db->query("TRUNCATE TABLE riwayat_poin");

// 3. Iterasi setiap pasien di database
$qPatients = $db->query("SELECT id_pasien, nama_lengkap, reward_poin FROM pasien ORDER BY id_pasien ASC");

while ($p = $qPatients->fetch_assoc()) {
    $idPasien = (int)$p['id_pasien'];
    $namaPasien = $p['nama_lengkap'];
    $saldoLama = (int)$p['reward_poin'];

    // Ambil seluruh pendaftaran aktif pasien (bukan dibatalkan) diurutkan tanggal dan ID
    $qRegs = $db->query("
        SELECT p.*, pl.nama_poli 
        FROM pendaftaran p
        LEFT JOIN poli pl ON p.id_poli = pl.id_poli
        WHERE p.id_pasien = $idPasien AND p.status_pendaftaran != 'dibatalkan'
        ORDER BY p.tanggal_kunjungan ASC, p.id_pendaftaran ASC
    ");

    $runningSaldo = 0;
    $totalKunjungan = 0;

    while ($reg = $qRegs->fetch_assoc()) {
        $totalKunjungan++;
        $calc = hitungPoinKunjungan($db, $reg['jenis_pasien'], $reg['jenis_perawatan'], $reg['keluhan'], (int)$reg['id_poli']);
        $poinVisit = (int)$calc['poin'];
        $tipeVisit = $calc['tipe'];
        $kodeVisit = $calc['kode'];

        $runningSaldo += $poinVisit;

        $tglDaftar = !empty($reg['tanggal_kunjungan']) ? $reg['tanggal_kunjungan'] . ' ' . ($reg['jam_kunjungan'] ?: '08:00:00') : date('Y-m-d H:i:s');
        $namaPoli = $reg['nama_poli'] ?: 'Poli Umum';
        $ketVisit = "Kunjungan {$namaPoli}: {$tipeVisit} (+{$poinVisit} Poin) [Reg #{$reg['id_pendaftaran']}]";
        $katVisit = 'kunjungan_' . $kodeVisit;
        $createdSource = ($reg['sumber_pendaftaran'] === 'offline') ? 'Pendaftaran Langsung' : 'Pendaftaran Online';

        $stR = $db->prepare("INSERT INTO riwayat_poin (id_pasien, jenis, jumlah_poin, saldo_akhir, kategori, keterangan, created_by, created_at) VALUES (?, 'tambah', ?, ?, ?, ?, ?, ?)");
        if ($stR) {
            $stR->bind_param('isissss', $idPasien, $poinVisit, $runningSaldo, $katVisit, $ketVisit, $createdSource, $tglDaftar);
            $stR->execute();
            $stR->close();
        }
    }

    // Cek klaim Google review
    if (in_array($idPasien, $claimedRevIds, true)) {
        $runningSaldo += 1;
        $stRev = $db->prepare("INSERT INTO riwayat_poin (id_pasien, jenis, jumlah_poin, saldo_akhir, kategori, keterangan, created_by, created_at) VALUES (?, 'tambah', 1, ?, 'review_google', 'Apresiasi Ulasan Google Review Bintang 5 (+1 Poin)', 'Google Review', NOW())");
        if ($stRev) {
            $stRev->bind_param('iis', $idPasien, $runningSaldo);
            $stRev->execute();
            $stRev->close();
        }
    }

    // Cek penukaran voucher yang disetujui (ACC)
    $qVch = $db->query("SELECT * FROM penukaran_poin WHERE id_pasien = $idPasien AND status = 'disetujui' ORDER BY id_penukaran ASC");
    while ($vch = $qVch->fetch_assoc()) {
        // Sesuaikan nominal poin voucher ke range baru (misal: 5 poin)
        $poinPotong = 5;
        $runningSaldo = max(0, $runningSaldo - $poinPotong);
        // Update di tabel penukaran_poin agar konsisten
        $idPen = (int)$vch['id_penukaran'];
        $db->query("UPDATE penukaran_poin SET poin_digunakan = $poinPotong WHERE id_penukaran = $idPen");

        $ketVch = "Klaim Penukaran Voucher: {$vch['nama_reward']} (-{$poinPotong} Poin)";
        $stV = $db->prepare("INSERT INTO riwayat_poin (id_pasien, jenis, jumlah_poin, saldo_akhir, kategori, keterangan, created_by, created_at) VALUES (?, 'kurang', ?, ?, 'klaim_voucher', ?, 'Admin Klinik', ?)");
        if ($stV) {
            $tglVch = $vch['created_at'] ?: date('Y-m-d H:i:s');
            $stV->bind_param('iiiss', $idPasien, $poinPotong, $runningSaldo, $ketVch, $tglVch);
            $stV->execute();
            $stV->close();
        }
    }

    // Update saldo akhir ke tabel pasien
    $db->query("UPDATE pasien SET reward_poin = $runningSaldo WHERE id_pasien = $idPasien");

    echo sprintf(
        "✓ Pasien #%-2d %-18s : Saldo Lama = %3d Pts  ==>  Saldo Baru = %2d Pts (%d Kunjungan)\n",
        $idPasien,
        $namaPasien,
        $saldoLama,
        $runningSaldo,
        $totalKunjungan
    );
}

echo "\n=== SINKRONISASI SELESAI DENGAN SUKSES! ===\n";
