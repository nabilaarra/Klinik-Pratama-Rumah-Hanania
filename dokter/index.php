<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) die('Koneksi database tidak ditemukan.');
$db->set_charset('utf8mb4');
function eD($value){return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');}
function getStaffPhotoUrl($foto) {
    $foto = trim((string)$foto);
    if ($foto !== '') {
        if (strpos($foto, 'default_') !== false) return '';
        if (strpos($foto, 'http://') === 0 || strpos($foto, 'https://') === 0) return $foto;
        $clean = ltrim($foto, '/');
        if (strpos($clean, 'uploads/') === 0 && file_exists(__DIR__ . '/../' . $clean)) return '../' . $clean;
        if (file_exists(__DIR__ . '/../uploads/tenaga/' . $clean)) return '../uploads/tenaga/' . $clean;
        if (file_exists(__DIR__ . '/../uploads/' . $clean)) return '../uploads/' . $clean;
        if (file_exists(__DIR__ . '/../assets/img/' . $clean)) return '../assets/img/' . $clean;
    }
    return '';
}
function loadAllStaff(mysqli $db): array {
    $rows = [];
    $sql = "
        SELECT d.id_dokter AS id, d.nama_dokter AS nama, d.spesialisasi AS peran, d.foto, COALESCE(p.nama_poli, 'Poli Umum') AS poli, 'Dokter' AS jenis, d.status
        FROM dokter d
        LEFT JOIN poli p ON p.id_poli = d.id_poli
        WHERE d.status != 'nonaktif'
        UNION ALL
        SELECT b.id_bidan AS id, b.nama_bidan AS nama, 'Bidan' AS peran, b.foto, COALESCE(p.nama_poli, 'Poli Kebidanan') AS poli, 'Bidan' AS jenis, b.status
        FROM bidan b
        LEFT JOIN poli p ON p.id_poli = b.id_poli
        WHERE b.status != 'nonaktif'
    ";
    $result = $db->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    // Urutan konsisten: DOKTER terlebih dahulu, kemudian BIDAN (Bu Gita Marissa pertama di antara bidan)
    usort($rows, function($a, $b) {
        $typeOrder = ($a['jenis'] === 'Dokter' ? 0 : 1) <=> ($b['jenis'] === 'Dokter' ? 0 : 1);
        if ($typeOrder !== 0) return $typeOrder;
        if ($a['jenis'] === 'Bidan') {
            $isGitaA = (stripos((string)$a['nama'], 'gita') !== false) ? 0 : 1;
            $isGitaB = (stripos((string)$b['nama'], 'gita') !== false) ? 0 : 1;
            if ($isGitaA !== $isGitaB) return $isGitaA <=> $isGitaB;
        }
        return strcasecmp((string)$a['nama'], (string)$b['nama']);
    });
    return $rows;
}
$allStaff = loadAllStaff($db);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dokter &amp; Bidan - Klinik Pratama Rumah Hanania</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#FAF7FD;color:#352D3E;font-family:Arial,Helvetica,sans-serif}
.page{width:min(1180px,calc(100% - 32px));margin:auto;padding:42px 0 70px}
.hero{padding:42px 28px;background:linear-gradient(180deg, #F6F1FC 0%, #EDE6F8 100%);color:#2B1B48;border-bottom:1px solid #E5D5FA}
.hero h1{margin:0 0 8px;font-size:34px;font-weight:900;color:#2B1B48;letter-spacing:-0.5px}
.hero p{margin:0;line-height:1.7;font-size:14.5px;color:#5D4D73}
.filter-tabs{display:flex;gap:10px;margin:28px 0 20px;flex-wrap:wrap}
.filter-btn{padding:9px 18px;border-radius:999px;border:1px solid #E4D9EF;background:#fff;color:#675F73;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s}
.filter-btn:hover{background:#F6EDFF;color:#4F3293}
.filter-btn.active{background:#cea2fd;color:#2E1065;border-color:#b57df9;box-shadow:0 4px 14px rgba(206,162,253,.45)}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.card{background:#fff;border:1px solid #E5D5FA;border-radius:18px;padding:22px;box-shadow:0 8px 24px rgba(70,45,90,.05);display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
.card:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(206,162,253,.25)}
.card-top{display:flex;align-items:center;gap:16px;margin-bottom:14px}
.photo{width:70px;height:70px;border-radius:16px;background:#F6EDFF;object-fit:cover;border:1px solid #E5D5FA;display:flex;align-items:center;justify-content:center;font-size:28px;overflow:hidden;flex-shrink:0}
.name{font-weight:800;color:#5E2F9D;font-size:16px;line-height:1.3}
.badge-jenis{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;background:#F6EDFF;color:#5E2F9D;margin-bottom:4px}
.badge-cuti{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;background:#FEE2E2;color:#DC2626;border:1px solid #FCA5A5;margin-bottom:4px;margin-left:4px;}
.role{color:#675F73;font-size:12.5px;margin-top:2px}
.poli{margin-top:10px;padding:8px 12px;background:#FAF7FD;border-radius:10px;border:1px solid #E8DFF0;color:#4B4453;font-size:12px;font-weight:600}
.actions{display:flex;gap:10px;margin-top:auto;padding-top:18px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 16px;border-radius:10px;text-decoration:none;font-size:12.5px;font-weight:800;flex:1;text-align:center;transition:all .2s}
.primary{background:linear-gradient(135deg, #cea2fd 0%, #ad6ef5 100%);color:#2E1065;box-shadow:0 4px 12px rgba(206,162,253,.35)}
.primary:hover{background:linear-gradient(135deg, #bf8afb 0%, #9955e8 100%);color:#fff;}
.secondary{background:#F6EDFF;color:#5E2F9D;border:1px solid #E5D5FA;}
.secondary:hover{background:#EFE3FC;border-color:#b57df9;}
@media(max-width:860px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:540px){.grid{grid-template-columns:1fr}.hero{padding:34px 20px}.hero h1{font-size:28px}}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<section class="hero">
    <div class="page" style="padding-top:0;padding-bottom:0;">
        <h1>Dokter &amp; Bidan</h1>
        <p>Tenaga kesehatan profesional Klinik Pratama Rumah Hanania yang siap melayani kebutuhan kesehatan Anda dan keluarga.</p>
    </div>
</section>
<main class="page">
    <div class="filter-tabs">
        <button type="button" class="filter-btn active" onclick="filterStaff('semua', this)">Semua Tenaga Medis (<?= count($allStaff) ?>)</button>
        <button type="button" class="filter-btn" onclick="filterStaff('Dokter', this)">Dokter</button>
        <button type="button" class="filter-btn" onclick="filterStaff('Bidan', this)">Bidan</button>
    </div>

    <div class="grid" id="staffGrid">
        <?php foreach ($allStaff as $item): 
            $param = ($item['jenis'] === 'Dokter') ? 'dokter' : 'bidan';
            $isCuti = (($item['status'] ?? '') === 'cuti');
        ?>
        <article class="card" data-jenis="<?= eD($item['jenis']) ?>">
            <div class="card-top">
                <?php 
                $photoUrl = getStaffPhotoUrl($item['foto'] ?? '');
                $avatarEmoji = ($item['jenis'] === 'Dokter') ? '🩺' : '👶';
                ?>
                <?php if (!empty($photoUrl)): ?>
                    <img class="photo" src="<?= eD($photoUrl) ?>" alt="<?= eD($item['nama']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="photo" style="display:none;"><span><?= $avatarEmoji ?></span></div>
                <?php else: ?>
                    <div class="photo"><span><?= $avatarEmoji ?></span></div>
                <?php endif; ?>
                <div>
                    <div>
                        <span class="badge-jenis"><?= eD($item['jenis']) ?></span>
                        <?php if ($isCuti): ?>
                            <span class="badge-cuti">🔴 Sedang Cuti / Tidak Hadir</span>
                        <?php endif; ?>
                    </div>
                    <div class="name"><?= eD($item['nama']) ?></div>
                    <div class="role"><?= eD($item['peran'] ?: $item['jenis']) ?></div>
                </div>
            </div>
            <div class="poli">🏥 Poli: <?= eD($item['poli'] ?: 'Sesuai Jadwal') ?></div>
            <div class="actions">
                <?php if ($isCuti): ?>
                    <button type="button" class="btn" style="background:#FEE2E2;color:#DC2626;cursor:not-allowed;border:1px solid #FCA5A5;" disabled>🔴 Sedang Cuti</button>
                <?php else: ?>
                    <a class="btn primary" href="../pasien/daftar.php?<?= $param ?>=<?= (int)$item['id'] ?>">Daftar ke Klinik</a>
                <?php endif; ?>
                <a class="btn secondary" href="jadwal.php?<?= $param ?>=<?= (int)$item['id'] ?>">Lihat Jadwal</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function filterStaff(jenis, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#staffGrid .card').forEach(card => {
        if (jenis === 'semua' || card.dataset.jenis === jenis) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
</body>
</html>
