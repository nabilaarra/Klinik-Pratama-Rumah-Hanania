<?php 
session_start(); 
require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;

// Ambil pertanyaan sering diajukan untuk tombol quick questions
$quickList = [];
if ($db instanceof mysqli) {
    $resQ = $db->query("SELECT id_faq, pertanyaan FROM faq WHERE status = 'aktif' ORDER BY urutan ASC, id_faq ASC LIMIT 6");
    if ($resQ) {
        while ($r = $resQ->fetch_assoc()) {
            $quickList[] = $r['pertanyaan'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>HanaBot - Asisten Informasi Klinik Pratama Rumah Hanania</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--hanania-primary: #4F3293;
			--hanania-secondary: #7650A2;
			--hanania-soft: #EAE4F9;
			--hanania-border: #E5DBF0;
			--hanania-text: #2D253B;
			--hanania-muted: #6B6278;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			background: #F8F5FC;
			color: var(--hanania-text);
			font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}
		.page {
			width: min(800px, calc(100% - 32px));
			margin: auto;
			padding: 36px 0 60px;
			flex: 1;
		}
		.hero {
			padding: 30px 28px;
			background: linear-gradient(135deg, #4F3293, #7650A2);
			color: #fff;
			border-radius: 18px 18px 0 0;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
			box-shadow: 0 8px 24px rgba(79, 50, 147, 0.15);
		}
		.hero-text h1 {
			margin: 0 0 6px;
			font-size: 26px;
			font-weight: 800;
		}
		.hero-text p {
			margin: 0;
			line-height: 1.5;
			font-size: 13.5px;
			opacity: 0.92;
		}
		.hero-badge {
			background: rgba(255, 255, 255, 0.2);
			border: 1px solid rgba(255, 255, 255, 0.35);
			padding: 6px 14px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: 700;
			white-space: nowrap;
		}
		.chat-wrapper {
			background: #fff;
			border: 1px solid var(--hanania-border);
			border-top: 0;
			border-radius: 0 0 18px 18px;
			overflow: hidden;
			box-shadow: 0 10px 30px rgba(79, 50, 147, 0.06);
			display: flex;
			flex-direction: column;
		}
		.messages {
			height: 380px;
			overflow-y: auto;
			padding: 22px;
			background: #FAF8FD;
			display: flex;
			flex-direction: column;
			gap: 12px;
		}
		.message {
			max-width: 82%;
			padding: 12px 16px;
			border-radius: 14px;
			background: var(--hanania-soft);
			color: var(--hanania-primary);
			font-size: 13.5px;
			line-height: 1.6;
			border-bottom-left-radius: 4px;
			word-break: break-word;
		}
		.message.user {
			margin-left: auto;
			background: var(--hanania-primary);
			color: #fff;
			border-bottom-left-radius: 14px;
			border-bottom-right-radius: 4px;
		}
		.chat-wa-card {
			margin-top: 10px;
			padding-top: 8px;
			border-top: 1px dashed rgba(79, 50, 147, 0.25);
		}
		.btn-wa-direct {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			background: #25D366;
			color: #FFFFFF !important;
			text-decoration: none;
			padding: 8px 14px;
			border-radius: 8px;
			font-size: 12.5px;
			font-weight: 800;
			box-shadow: 0 3px 10px rgba(37, 211, 102, 0.25);
			transition: all 0.2s;
			margin-top: 4px;
		}
		.btn-wa-direct:hover {
			background: #20BA5A;
			transform: translateY(-1px);
		}
		.quick-section {
			padding: 12px 18px;
			background: #FFFFFF;
			border-top: 1px solid var(--hanania-border);
		}
		.quick-label {
			font-size: 11px;
			font-weight: 800;
			color: var(--hanania-muted);
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-bottom: 8px;
		}
		.quick-pills {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}
		.quick-btn {
			background: var(--hanania-soft);
			border: 1px solid var(--hanania-border);
			color: var(--hanania-primary);
			padding: 6px 12px;
			border-radius: 16px;
			font-size: 12px;
			font-weight: 700;
			cursor: pointer;
			transition: all 0.15s ease;
			font-family: inherit;
		}
		.quick-btn:hover {
			background: var(--hanania-primary);
			color: #FFFFFF;
			border-color: var(--hanania-primary);
			transform: translateY(-1px);
		}
		.form-bar {
			display: flex;
			gap: 10px;
			padding: 14px 18px;
			border-top: 1px solid var(--hanania-border);
			background: #FFFFFF;
		}
		.form-bar input {
			min-width: 0;
			flex: 1;
			padding: 12px 16px;
			border: 1px solid var(--hanania-border);
			border-radius: 12px;
			font-family: inherit;
			font-size: 13.5px;
			color: var(--hanania-text);
			outline: none;
			transition: border-color 0.2s;
		}
		.form-bar input:focus {
			border-color: var(--hanania-primary);
		}
		.form-bar button {
			border: none;
			border-radius: 12px;
			padding: 0 20px;
			background: var(--hanania-primary);
			color: #fff;
			font-size: 14px;
			font-weight: 700;
			cursor: pointer;
			transition: all 0.2s;
		}
		.form-bar button:hover {
			background: #3D2575;
		}
		.status-text {
			font-size: 11.5px;
			color: var(--hanania-muted);
			padding: 0 18px 8px;
			min-height: 18px;
		}
		@media (max-width: 600px) {
			.hero {
				flex-direction: column;
				align-items: flex-start;
				padding: 20px;
			}
			.messages {
				height: 320px;
				padding: 16px;
			}
		}
	</style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main class="page">
	<section class="hero">
		<div class="hero-text">
			<h1>HanaBot</h1>
			<p>Asisten informasi interaktif seputar jadwal, layanan, pendaftaran, dan antrean Klinik Pratama Rumah Hanania.</p>
		</div>
		<div class="hero-badge">⚡ Asisten Virtual 24 Jam</div>
	</section>

	<section class="chat-wrapper">
		<div class="messages" id="messages">
			<div class="message">
				Halo! 👋 Saya <strong>HanaBot</strong>. Silakan pilih salah satu pertanyaan umum di bawah atau ketik pertanyaan Anda sendiri di kolom chat.
			</div>
		</div>

		<div class="status-text" id="statusText"></div>

		<!-- QUICK QUESTIONS / FAQ PILLS -->
		<div class="quick-section">
			<div class="quick-label">💡 Pertanyaan yang Sering Diajukan:</div>
			<div class="quick-pills" id="quickPills">
				<?php if (!empty($quickList)): ?>
					<?php foreach ($quickList as $q): ?>
						<button type="button" class="quick-btn" onclick="sendQuick(<?= htmlspecialchars(json_encode($q), ENT_QUOTES, 'UTF-8') ?>)">
							<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>
						</button>
					<?php endforeach; ?>
				<?php else: ?>
					<button type="button" class="quick-btn" onclick="sendQuick('Bagaimana cara mendaftar pemeriksaan online di Rumah Hanania?')">Cara Mendaftar Online?</button>
					<button type="button" class="quick-btn" onclick="sendQuick('Bagaimana urutan nomor antrean online diatur?')">Urutan Nomor Antrean?</button>
					<button type="button" class="quick-btn" onclick="sendQuick('Kapan saya harus hadir di klinik?')">Kapan Harus Hadir?</button>
					<button type="button" class="quick-btn" onclick="sendQuick('Apakah Klinik Pratama Rumah Hanania menerima pasien BPJS?')">Menerima BPJS?</button>
				<?php endif; ?>
			</div>
		</div>

		<!-- CHAT FORM INPUT -->
		<form class="form-bar" id="chatForm">
			<input id="messageInput" autocomplete="off" placeholder="Tulis pertanyaan seputar layanan atau jadwal klinik..." required>
			<button type="submit">Kirim ➤</button>
		</form>
	</section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const messages = document.getElementById('messages');
const statusText = document.getElementById('statusText');

function sendQuick(questionText) {
	input.value = questionText;
	form.dispatchEvent(new Event('submit'));
}

form.addEventListener('submit', async (e) => {
	e.preventDefault();
	const msg = input.value.trim();
	if (!msg) return;

	// Append user bubble
	const userDiv = document.createElement('div');
	userDiv.className = 'message user';
	userDiv.textContent = msg;
	messages.appendChild(userDiv);
	input.value = '';
	messages.scrollTop = messages.scrollHeight;

	statusText.textContent = 'HanaBot sedang mencari jawaban...';

	try {
		const res = await fetch('proses_chat.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ message: msg })
		});
		const data = await res.json();

		const botDiv = document.createElement('div');
		botDiv.className = 'message';
		botDiv.innerHTML = (data.message || 'Maaf, terjadi kendala.').replace(/\n/g, '<br>');

		if (data.is_fallback && data.whatsapp_url) {
			const waCard = document.createElement('div');
			const waTargetName = data.whatsapp_name || 'Admin';
			const waTargetNum = data.whatsapp_number || '088510703839';
			waCard.innerHTML = `<a href="${data.whatsapp_url}" target="_blank" rel="noopener noreferrer" class="btn-wa-direct">💬 Hubungi ${waTargetName} via WhatsApp (${waTargetNum})</a>`;
			botDiv.appendChild(waCard);
		}

		messages.appendChild(botDiv);
	} catch (err) {
		const errDiv = document.createElement('div');
		errDiv.className = 'message';
		errDiv.textContent = 'Maaf, layanan chatbot sedang dalam pemeliharaan. Silakan hubungi klinik via kontak yang tersedia.';
		messages.appendChild(errDiv);
	} finally {
		statusText.textContent = '';
		messages.scrollTop = messages.scrollHeight;
	}
});
</script>
</body>
</html>
