<?php
require_once __DIR__ . '/_guard.php';

$message = '';
$messageType = 'success';

// Handle HAPUS FAQ
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtDel = $db->prepare("DELETE FROM faq WHERE id_faq = ?");
    if ($stmtDel) {
        $stmtDel->bind_param('i', $idHapus);
        if ($stmtDel->execute()) {
            $message = 'Pertanyaan kamus chatbot berhasil dihapus.';
        } else {
            $message = 'Gagal menghapus pertanyaan: ' . $db->error;
            $messageType = 'danger';
        }
        $stmtDel->close();
    }
}

// Handle TOGGLE STATUS FAQ
if (isset($_GET['toggle_status'])) {
    $idToggle = (int)$_GET['toggle_status'];
    $stmtT = $db->prepare("UPDATE faq SET status = IF(status = 'aktif', 'nonaktif', 'aktif') WHERE id_faq = ?");
    if ($stmtT) {
        $stmtT->bind_param('i', $idToggle);
        $stmtT->execute();
        $stmtT->close();
        $message = 'Status pertanyaan chatbot berhasil diperbarui.';
    }
}

// Handle SIMPAN (Tambah / Edit FAQ atau Pengaturan WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_wa'])) {
        $waNumRaw = trim($_POST['chatbot_fallback_wa_number'] ?? '');
        $waName = trim($_POST['chatbot_fallback_wa_name'] ?? '');
        $waMsg = trim($_POST['chatbot_fallback_wa_message'] ?? '');

        // Validasi dan normalisasi format nomor WhatsApp
        $digitsOnly = preg_replace('/[^0-9]/', '', $waNumRaw);
        if (strlen($digitsOnly) < 9 || strlen($digitsOnly) > 16) {
            $message = 'Nomor WhatsApp tidak valid. Harap masukkan nomor ponsel WhatsApp yang benar (minimal 9 digit, contoh: 088510703839).';
            $messageType = 'danger';
        } else {
            // Normalisasi awalan nomor HP
            if (str_starts_with($digitsOnly, '0')) {
                $waNumClean = '62' . substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '8')) {
                $waNumClean = '628' . substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '62')) {
                $waNumClean = $digitsOnly;
            } else {
                $waNumClean = '62' . $digitsOnly;
            }

            $stmtW1 = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('chatbot_fallback_wa_number', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtW1) {
                $stmtW1->bind_param('s', $waNumClean);
                $stmtW1->execute();
                $stmtW1->close();
            }
            $stmtW2 = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('chatbot_fallback_wa_name', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtW2) {
                $stmtW2->bind_param('s', $waName);
                $stmtW2->execute();
                $stmtW2->close();
            }
            $stmtW3 = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('chatbot_fallback_wa_message', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtW3) {
                $stmtW3->bind_param('s', $waMsg);
                $stmtW3->execute();
                $stmtW3->close();
            }

            // Simpan / update ke daftar kontak tersimpan agar admin bisa memilih dengan cepat
            $qList = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'chatbot_wa_saved_contacts' LIMIT 1");
            $savedContacts = [];
            if ($qList && $rList = $qList->fetch_assoc()) {
                $savedContacts = json_decode($rList['nilai'] ?? '[]', true) ?: [];
            }
            $found = false;
            foreach ($savedContacts as &$sc) {
                if (($sc['number'] ?? '') === $waNumClean) {
                    $sc['name'] = $waName;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $savedContacts[] = ['name' => $waName ?: 'Admin CS', 'number' => $waNumClean];
            }
            $savedJson = json_encode(array_values($savedContacts));
            $stmtW4 = $db->prepare("INSERT INTO pengaturan_konten (kunci, nilai) VALUES ('chatbot_wa_saved_contacts', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($stmtW4) {
                $stmtW4->bind_param('s', $savedJson);
                $stmtW4->execute();
                $stmtW4->close();
            }

            $message = 'Pengaturan WhatsApp tujuan fallback HanaBot berhasil disimpan dan diaktifkan!';
        }
    } else {
        $idFaq = (int)($_POST['id_faq'] ?? 0);
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $jawaban = trim($_POST['jawaban'] ?? '');
        $kataKunci = trim($_POST['kata_kunci'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);
        $status = in_array($_POST['status'] ?? 'aktif', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

        if ($pertanyaan === '' || $jawaban === '') {
            $message = 'Pertanyaan dan jawaban wajib diisi.';
            $messageType = 'danger';
        } else {
            if ($idFaq > 0) {
                // Edit
                $stmtUp = $db->prepare("UPDATE faq SET pertanyaan = ?, jawaban = ?, kata_kunci = ?, urutan = ?, status = ? WHERE id_faq = ?");
                if ($stmtUp) {
                    $stmtUp->bind_param('sssisi', $pertanyaan, $jawaban, $kataKunci, $urutan, $status, $idFaq);
                    if ($stmtUp->execute()) {
                        $message = 'Pertanyaan kamus chatbot berhasil diperbarui!';
                    } else {
                        $message = 'Gagal memperbarui data: ' . $db->error;
                        $messageType = 'danger';
                    }
                    $stmtUp->close();
                }
            } else {
                // Tambah Baru
                $stmtIn = $db->prepare("INSERT INTO faq (pertanyaan, jawaban, kata_kunci, urutan, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmtIn) {
                    $stmtIn->bind_param('sssis', $pertanyaan, $jawaban, $kataKunci, $urutan, $status);
                    if ($stmtIn->execute()) {
                        $message = 'Pertanyaan baru berhasil ditambahkan ke kamus HanaBot!';
                    } else {
                        $message = 'Gagal menambahkan pertanyaan: ' . $db->error;
                        $messageType = 'danger';
                    }
                    $stmtIn->close();
                }
            }
        }
    }
}

// Ambil data untuk mode edit jika ada
$editData = null;
if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];
    $stmtGet = $db->prepare("SELECT * FROM faq WHERE id_faq = ? LIMIT 1");
    if ($stmtGet) {
        $stmtGet->bind_param('i', $idEdit);
        $stmtGet->execute();
        $editData = $stmtGet->get_result()->fetch_assoc();
        $stmtGet->close();
    }
}

// Ambil semua daftar FAQ
$listFaq = [];
$resAll = $db->query("SELECT * FROM faq ORDER BY urutan ASC, id_faq ASC");
if ($resAll) {
    while ($r = $resAll->fetch_assoc()) {
        $listFaq[] = $r;
    }
}

// Ambil Pengaturan WhatsApp Tujuan Fallback HanaBot
$currentWaNumber = '088510703839';
$currentWaName = 'Admin Pelayanan Klinik Pratama Rumah Hanania';
$currentWaMessage = 'Halo Admin Klinik Pratama Rumah Hanania, saya ingin bertanya perihal: {pertanyaan}';
$savedWaContacts = [
    ['name' => 'Admin CS Utama Klinik', 'number' => '088510703839'],
    ['name' => 'Pelayanan & Bidan Jaga 24 Jam', 'number' => '085714178178'],
    ['name' => 'Pendaftaran & Informasi Pasien', 'number' => '082810703839'],
];

$qWa = $db->query("SELECT kunci, nilai FROM pengaturan_konten WHERE kunci IN ('chatbot_fallback_wa_number', 'chatbot_fallback_wa_name', 'chatbot_fallback_wa_message', 'chatbot_wa_saved_contacts')");
if ($qWa) {
    while ($rWa = $qWa->fetch_assoc()) {
        if ($rWa['kunci'] === 'chatbot_fallback_wa_number' && trim($rWa['nilai']) !== '') $currentWaNumber = $rWa['nilai'];
        if ($rWa['kunci'] === 'chatbot_fallback_wa_name' && trim($rWa['nilai']) !== '') $currentWaName = $rWa['nilai'];
        if ($rWa['kunci'] === 'chatbot_fallback_wa_message' && trim($rWa['nilai']) !== '') $currentWaMessage = $rWa['nilai'];
        if ($rWa['kunci'] === 'chatbot_wa_saved_contacts' && trim($rWa['nilai']) !== '') {
            $parsed = json_decode($rWa['nilai'], true);
            if (is_array($parsed) && !empty($parsed)) {
                $savedWaContacts = $parsed;
            }
        }
    }
}

adminPageStart('Kelola Chatbot HanaBot', 'chatbot');
?>

<style>
.chatbot-top-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: stretch;
    margin-bottom: 24px;
}
.wa-preset-chip {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #D1D5DB;
    background: #FFFFFF;
    font-size: 11.5px;
    font-weight: 700;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.wa-preset-chip:hover {
    border-color: #10B981;
    background: #ECFDF5;
    color: #065F46;
}
.wa-preset-chip.active {
    border-color: #10B981;
    background: #D1FAE5;
    color: #065F46;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
}
@media (max-width: 960px) {
    .chatbot-top-grid {
        grid-template-columns: 1fr !important;
        gap: 16px !important;
    }
}
</style>

<div class="admin-page">

    <!-- HEADER TITLE -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:22px;font-weight:900;color:var(--hanania-primary);letter-spacing:-.3px;margin:0 0 4px;">
                🤖 Pengelolaan Kamus Chatbot HanaBot
            </h2>
            <p style="font-size:13px;color:var(--hanania-muted);margin:0;">
                Atur tujuan nomor WhatsApp untuk pertanyaan di luar kamus, tambahkan pertanyaan baru, dan pantau kamus pengetahuan resmi HanaBot.
            </p>
        </div>
        <div>
            <?php if ($editData): ?>
                <a href="chatbot.php" class="btn btn-sm btn-light">✕ Batal Edit &amp; Tambah Baru</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="margin-bottom:20px;padding:12px 18px;border-radius:12px;font-size:13.5px;display:flex;align-items:center;gap:10px;">
            <span style="font-size:18px;"><?= $messageType === 'success' ? '✅' : '⚠️' ?></span>
            <div><?= adminEsc($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- BARIS ATAS: CARD PENGATURAN WA & CARD TAMBAH PERTANYAAN (SEBELAHAN DI DESKTOP) -->
    <div class="chatbot-top-grid">

        <!-- 1. CARD KIRI: PENGATURAN & TAMBAH NOMOR WHATSAPP TUJUAN (FALLBACK CHATBOT) -->
        <section class="admin-card" style="border:1.5px solid #25D366;background:#F9FDF9;margin-bottom:0;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
                <div class="admin-card-head" style="margin-bottom:14px;border-bottom:1px solid #D1FAE5;padding-bottom:12px;">
                    <div>
                        <div class="admin-card-title" style="color:#065F46;font-size:15px;display:flex;align-items:center;gap:8px;">
                            <span>💬</span> Atur &amp; Pilih WhatsApp Bantuan HanaBot
                        </div>
                        <div class="admin-card-subtitle" style="color:#047857;margin-top:2px;">
                            Tentukan ke siapa pesan dialihkan jika pertanyaan pasien belum tercatat di kamus bot.
                        </div>
                    </div>
                </div>

                <!-- PRESET CEPAT PILIH / GANTI NOMOR WA -->
                <div style="margin-bottom:14px;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;padding:12px;">
                    <div style="font-size:11.5px;font-weight:800;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                        <span>⚡</span> Pilihan Cepat Nomor Tujuan Tersedia:
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php foreach ($savedWaContacts as $contact): 
                            $cName = $contact['name'] ?? 'Admin';
                            $cNum = $contact['number'] ?? '';
                            $isActive = (preg_replace('/[^0-9]/', '', $currentWaNumber) === preg_replace('/[^0-9]/', '', $cNum));
                        ?>
                            <button type="button" 
                                    class="wa-preset-chip <?= $isActive ? 'active' : '' ?>" 
                                    onclick="pilihWaPreset('<?= adminEsc(addslashes($cName)) ?>', '<?= adminEsc(addslashes($cNum)) ?>')"
                                    title="Klik untuk memilih nomor ini">
                                <span><?= $isActive ? '🟢' : '⚪' ?></span>
                                <span><?= adminEsc($cName) ?> (<?= adminEsc($cNum) ?>)</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form method="POST" action="chatbot.php" id="formSettingWa">
                    <input type="hidden" name="simpan_wa" value="1">
                    
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;color:#1E293B;">
                            Nama Penerima / Unit CS Tujuan <span style="color:#E53E3E;">*</span>
                        </label>
                        <input type="text" id="waRecipientName" name="chatbot_fallback_wa_name" class="filter-input" style="width:100%;font-size:13px;" placeholder="Contoh: Admin Pelayanan Klinik Hanania" value="<?= adminEsc($currentWaName) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;color:#1E293B;">
                            Nomor WhatsApp Tujuan (Admin / Dokter / Bidan) <span style="color:#E53E3E;">*</span>
                        </label>
                        <input type="text" id="waRecipientNumber" name="chatbot_fallback_wa_number" required class="filter-input" style="width:100%;font-size:13px;" placeholder="Contoh: 088510703839 atau 6288510703839" value="<?= adminEsc($currentWaNumber) ?>">
                        <small style="font-size:11px;color:#047857;margin-top:3px;display:block;font-weight:600;">
                            ✓ Boleh format 08..., 62..., atau +62... (otomatis dinormalisasi). Anda dapat memasukkan nomor baru apa saja di sini.
                        </small>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;color:#1E293B;">
                            Format Pesan Otomatis WhatsApp
                        </label>
                        <textarea name="chatbot_fallback_wa_message" rows="3" class="filter-input" style="width:100%;font-size:13px;line-height:1.45;resize:vertical;" placeholder="Contoh: Halo {admin}, saya ingin bertanya perihal: {pertanyaan}"><?= adminEsc($currentWaMessage) ?></textarea>
                        <small style="font-size:11px;color:#4B5563;margin-top:4px;display:block;">
                            💡 Tag <code>{pertanyaan}</code> otomatis diisi pertanyaan pasien, dan <code>{admin}</code> otomatis diganti nama penerima.
                        </small>
                    </div>

                    <button type="submit" class="btn" style="width:100%;background:#10B981;color:#fff;font-weight:800;border:none;box-shadow:0 4px 14px rgba(16,185,129,0.3);padding:11px;font-size:13px;">
                        💾 Simpan &amp; Aktifkan Nomor WhatsApp Tujuan
                    </button>
                </form>
            </div>
        </section>

        <!-- 2. CARD KANAN: FORM TAMBAH / EDIT PERTANYAAN KAMUS -->
        <section class="admin-card" style="margin-bottom:0;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
                <div class="admin-card-head" style="margin-bottom:14px;border-bottom:1px solid var(--hanania-border);padding-bottom:12px;">
                    <div>
                        <div class="admin-card-title" style="font-size:15px;display:flex;align-items:center;gap:8px;">
                            <span><?= $editData ? '✏️' : '➕' ?></span> 
                            <span><?= $editData ? 'Edit Pertanyaan Kamus' : 'Tambah Pertanyaan Baru' ?></span>
                        </div>
                        <div class="admin-card-subtitle" style="margin-top:2px;">
                            Isi pertanyaan umum pasien dan jawaban resmi yang akan dijawab otomatis oleh HanaBot.
                        </div>
                    </div>
                </div>

                <form method="POST" action="chatbot.php">
                    <input type="hidden" name="id_faq" value="<?= (int)($editData['id_faq'] ?? 0) ?>">

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="pertanyaan" style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;">
                            Pertanyaan Pasien <span style="color:#E53E3E;">*</span>
                        </label>
                        <input type="text" id="pertanyaan" name="pertanyaan" required
                               class="filter-input" style="width:100%;font-size:13px;"
                               placeholder="Contoh: Bagaimana alur pendaftaran periksa kehamilan?"
                               value="<?= adminEsc($editData['pertanyaan'] ?? '') ?>">
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="jawaban" style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;">
                            Jawaban HanaBot <span style="color:#E53E3E;">*</span>
                        </label>
                        <textarea id="jawaban" name="jawaban" required rows="4"
                                  class="filter-input" style="width:100%;font-size:13px;resize:vertical;min-height:90px;line-height:1.5;"
                                  placeholder="Tuliskan jawaban yang akan disampaikan HanaBot kepada pasien..."><?= adminEsc($editData['jawaban'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" for="kata_kunci" style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;">
                            Kata Kunci Pencocokan (Pisahkan dengan koma)
                        </label>
                        <input type="text" id="kata_kunci" name="kata_kunci"
                               class="filter-input" style="width:100%;font-size:13px;"
                               placeholder="Contoh: hamil, kehamilan, periksa, dokter kandungan, usg"
                               value="<?= adminEsc($editData['kata_kunci'] ?? '') ?>">
                        <small style="font-size:11px;color:var(--hanania-muted);margin-top:2px;display:block;">
                            Membantu bot mengenali variasi kata yang diketik pasien di chat.
                        </small>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="urutan" style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;">
                                Urutan Tampil
                            </label>
                            <input type="number" id="urutan" name="urutan" min="0" max="999"
                                   class="filter-input" style="width:100%;font-size:13px;"
                                   value="<?= (int)($editData['urutan'] ?? 0) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="status" style="font-size:12px;font-weight:700;display:block;margin-bottom:5px;">
                                Status Pertanyaan
                            </label>
                            <select id="status" name="status" class="filter-input" style="width:100%;font-size:13px;">
                                <option value="aktif" <?= ($editData['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (Tampil)</option>
                                <option value="nonaktif" <?= ($editData['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width:100%;padding:11px;font-size:13px;font-weight:800;">
                        <?= $editData ? '💾 Simpan Perubahan Pertanyaan' : '➕ Tambahkan ke Kamus HanaBot' ?>
                    </button>
                </form>
            </div>
        </section>

    </div>

    <!-- ========================================================= -->
    <!-- BARIS BAWAH: KAMUS PENGETAHUAN HANABOT (LEBAR PENUH 100%) -->
    <!-- ========================================================= -->
    <section class="admin-card" style="padding:0;overflow:hidden;width:100%;">
        <div class="admin-card-head" style="padding:18px 24px 14px;border-bottom:1px solid var(--hanania-border);margin:0;background:#FAF8FD;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <div class="admin-card-title" style="font-size:16px;display:flex;align-items:center;gap:8px;">
                    <span>📚</span> Kamus Pengetahuan HanaBot (<?= count($listFaq) ?> Pertanyaan Terdaftar)
                </div>
                <div class="admin-card-subtitle" style="margin-top:2px;">
                    Pertanyaan berstatus aktif otomatis dijawab langsung oleh asisten virtual dan sebagian tampil pada Quick Questions.
                </div>
            </div>
            <div style="font-size:12px;color:var(--hanania-muted);font-weight:700;">
                Tabel Kamus Full-Width
            </div>
        </div>

        <div class="admin-table-responsive" style="overflow-x:auto;width:100%;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#FCFAFF;">
                        <th style="width:50px;text-align:center;">No</th>
                        <th style="width:280px;">Pertanyaan Pasien &amp; Kata Kunci</th>
                        <th>Jawaban Resmi HanaBot</th>
                        <th style="width:95px;text-align:center;">Status</th>
                        <th style="width:130px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listFaq)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:35px;color:var(--hanania-muted);">
                                Belum ada pertanyaan dalam kamus chatbot. Tambahkan pertanyaan pertama melalui formulir di atas!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listFaq as $idx => $f): ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--hanania-muted);">
                                    <?= $idx + 1 ?>
                                </td>
                                <td>
                                    <strong style="color:var(--hanania-primary);line-height:1.4;display:block;font-size:13.5px;">
                                        <?= adminEsc($f['pertanyaan']) ?>
                                    </strong>
                                    <?php if (!empty($f['kata_kunci'])): ?>
                                        <div style="margin-top:5px;display:flex;flex-wrap:wrap;gap:4px;">
                                            <?php foreach (explode(',', $f['kata_kunci']) as $kw): 
                                                $kw = trim($kw);
                                                if ($kw === '') continue;
                                            ?>
                                                <span style="font-size:11px;color:var(--hanania-primary);background:var(--hanania-purple-soft);border:1px solid var(--hanania-lavender);padding:2px 8px;border-radius:6px;">
                                                    🔑 <?= adminEsc($kw) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="line-height:1.6;color:var(--hanania-text);max-height:110px;overflow-y:auto;font-size:12.5px;padding-right:6px;">
                                        <?= nl2br(adminEsc($f['jawaban'])) ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <a href="chatbot.php?toggle_status=<?= (int)$f['id_faq'] ?>" 
                                       title="Klik untuk ubah status aktif/nonaktif"
                                       class="badge <?= $f['status'] === 'aktif' ? 'badge-success' : 'badge-warning' ?>"
                                       style="cursor:pointer;text-decoration:none;display:inline-block;padding:5px 10px;font-size:11px;font-weight:800;">
                                        <?= $f['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
                                    </a>
                                </td>
                                <td style="text-align:center;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:6px;align-items:center;">
                                        <a href="chatbot.php?edit=<?= (int)$f['id_faq'] ?>" class="btn btn-sm btn-light" style="padding:6px 10px;font-size:12px;font-weight:700;">
                                            ✏️ Edit
                                        </a>
                                        <a href="chatbot.php?hapus=<?= (int)$f['id_faq'] ?>" 
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus pertanyaan ini dari kamus chatbot?');"
                                           class="btn btn-sm" style="padding:6px 10px;font-size:12px;font-weight:700;background:#FDEAEA;color:#B72B2B;">
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

<script>
function pilihWaPreset(name, number) {
    const inputName = document.getElementById('waRecipientName');
    const inputNumber = document.getElementById('waRecipientNumber');
    if (inputName) inputName.value = name;
    if (inputNumber) inputNumber.value = number;
    
    document.querySelectorAll('.wa-preset-chip').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(number)) {
            btn.classList.add('active');
        }
    });
}
</script>

<?php
adminPageEnd();
?>
