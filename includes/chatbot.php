<!-- =====================================================
     FLOATING CHATBOT WIDGET: HANABOT
     Tampil Mengambang di Seluruh Halaman Publik
===================================================== -->
<style>
.hanabot-float-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9990;
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #6A22A7 0%, #7650A2 100%);
    color: #FFFFFF;
    border: none;
    border-radius: 50px;
    padding: 12px 20px 12px 14px;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(79, 50, 147, 0.38);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 800;
}
.hanabot-float-btn:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 12px 30px rgba(79, 50, 147, 0.48);
}
.hanabot-float-btn .icon-wrap {
    width: 34px;
    height: 34px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    position: relative;
}
.hanabot-float-btn .pulse-dot {
    position: absolute;
    top: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background: #10B981;
    border: 2px solid #6A22A7;
    border-radius: 50%;
}

.hanabot-window {
    position: fixed;
    bottom: 90px;
    right: 24px;
    width: min(390px, calc(100vw - 32px));
    height: 560px;
    max-height: calc(100vh - 120px);
    background: #FFFFFF;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(70, 40, 90, 0.28);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9995;
    border: 1px solid rgba(118, 80, 162, 0.18);
    animation: hanabotSlideUp 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.hanabot-window.active {
    display: flex;
}
@keyframes hanabotSlideUp {
    from { opacity: 0; transform: translateY(18px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.hanabot-header {
    background: linear-gradient(135deg, #6A22A7 0%, #7650A2 100%);
    color: #FFFFFF;
    padding: 16px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.hanabot-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.hanabot-header-avatar {
    width: 38px;
    height: 38px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.hanabot-header-title {
    font-size: 15px;
    font-weight: 800;
    line-height: 1.2;
}
.hanabot-header-status {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 2px;
}
.hanabot-header-status::before {
    content: '';
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10B981;
}
.hanabot-close-btn {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.85);
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
    padding: 4px;
    border-radius: 6px;
    transition: background 0.2s;
}
.hanabot-close-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #FFFFFF;
}

/* QUICK QUESTIONS SCROLL AREA */
.hanabot-quick-wrap {
    padding: 10px 14px 8px;
    background: #FAF8FD;
    border-bottom: 1px solid #EFE8F6;
    overflow-x: auto;
    white-space: nowrap;
    display: flex;
    gap: 8px;
    scrollbar-width: none;
}
.hanabot-quick-wrap::-webkit-scrollbar {
    display: none;
}
.hanabot-quick-btn {
    background: #FFFFFF;
    border: 1px solid #DCCFE8;
    color: #6A22A7;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.hanabot-quick-btn:hover {
    background: #6A22A7;
    color: #FFFFFF;
    border-color: #6A22A7;
}

/* MESSAGES LIST */
.hanabot-messages {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #FAF9FC;
    font-size: 13px;
    line-height: 1.55;
}
.hanabot-msg {
    max-width: 82%;
    padding: 11px 15px;
    border-radius: 14px;
    word-break: break-word;
}
.hanabot-msg.bot {
    align-self: flex-start;
    background: #FFFFFF;
    color: #352D3E;
    border: 1px solid #EAE2F3;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 6px rgba(70, 40, 90, 0.04);
}
.hanabot-msg.user {
    align-self: flex-end;
    background: #6A22A7;
    color: #FFFFFF;
    border-bottom-right-radius: 4px;
}
.hanabot-msg a {
    color: #10B981;
    font-weight: bold;
    text-decoration: underline;
}

/* INPUT FOOTER */
.hanabot-footer {
    padding: 12px 14px;
    background: #FFFFFF;
    border-top: 1px solid #EFE8F6;
    display: flex;
    gap: 8px;
    align-items: center;
}
.hanabot-input {
    flex: 1;
    border: 1.5px solid #E3D9EE;
    border-radius: 24px;
    padding: 10px 16px;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s;
    font-family: inherit;
}
.hanabot-input:focus {
    border-color: #7650A2;
}
.hanabot-send-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #6A22A7;
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    transition: background 0.2s;
}
.hanabot-send-btn:hover {
    background: #38206E;
}

@media(max-width: 600px) {
    .hanabot-window {
        bottom: 12px;
        right: 12px;
        left: 12px;
        width: auto;
        height: calc(100vh - 40px);
        max-height: 580px;
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(70, 40, 90, 0.35);
    }
    .hanabot-float-btn {
        bottom: 20px;
        right: 16px;
        height: 44px;
        padding: 0 14px 0 10px;
        border-radius: 50px;
        font-size: 12.5px;
        gap: 6px;
        box-shadow: 0 6px 20px rgba(79, 50, 147, 0.4);
    }
    .hanabot-float-btn .icon-wrap {
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
    .hanabot-float-btn .hanabot-label {
        display: inline-block !important;
        font-size: 12px;
        font-weight: 800;
    }
    .hanabot-input {
        font-size: 16px !important;
    }
}

/* Hide floating button when chat window is open */
body.hanabot-open #hanabotFloatBtn {
    display: none !important;
}
</style>

<!-- TOMBOL FLOATING CHATBOT -->
<button type="button" class="hanabot-float-btn" id="hanabotFloatBtn" onclick="toggleHanaBot()" aria-label="Buka Tanya HanaBot" title="Tanya HanaBot">
    <div class="icon-wrap">
        <span>🤖</span>
        <span class="pulse-dot"></span>
    </div>
    <span class="hanabot-label">Tanya HanaBot</span>
</button>

<!-- JENDELA CHATBOT -->
<div class="hanabot-window" id="hanabotWindow">
    <div class="hanabot-header">
        <div class="hanabot-header-left">
            <div class="hanabot-header-avatar">🤖</div>
            <div>
                <div class="hanabot-header-title">HanaBot Assistant</div>
                <div class="hanabot-header-status">Online • Siap Membantu 24 Jam</div>
            </div>
        </div>
        <button type="button" class="hanabot-close-btn" onclick="toggleHanaBot()" title="Tutup Chat">✕</button>
    </div>

    <!-- QUICK QUESTIONS BAR -->
    <div class="hanabot-quick-wrap">
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Jadwal dokter dan bidan hari ini')">📅 Jadwal Dokter</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Bagaimana cara mendaftar antrean online?')">📝 Cara Daftar</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Jam buka operasional klinik')">⏰ Jam Buka</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Apakah pemeriksaan kehamilan bisa BPJS?')">💳 Ketentuan BPJS</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Berapa biaya USG 2D dan USG 4D?')">👶 Biaya USG</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Dokter spesialis kandungan Sp.OG dr Eddy')">🩺 dr. Eddy Sp.OG</button>
        <button type="button" class="hanabot-quick-btn" onclick="kirimHanaQuick('Alamat lokasi dan kontak WhatsApp klinik')">📍 Lokasi &amp; Kontak</button>
    </div>

    <!-- MESSAGES LOG -->
    <div class="hanabot-messages" id="hanabotMessages">
        <div class="hanabot-msg bot">
            Halo! 👋 Saya <strong>HanaBot</strong>, asisten virtual resmi Klinik Pratama Rumah Hanania.<br><br>
            Ada yang bisa saya bantu terkait jadwal dokter, cara daftar antrean, tarif USG kehamilan, ketentuan BPJS, atau jam buka klinik?
        </div>
    </div>

    <!-- INPUT BAR -->
    <form class="hanabot-footer" id="hanabotForm" onsubmit="handleHanaSubmit(event)">
        <input type="text" class="hanabot-input" id="hanabotInput" placeholder="Ketik pertanyaan Anda di sini..." autocomplete="off">
        <button type="submit" class="hanabot-send-btn" title="Kirim Pesan">➤</button>
    </form>
</div>

<script>
(function() {
    window.toggleHanaBot = function() {
        const win = document.getElementById('hanabotWindow');
        if (!win) return;
        win.classList.toggle('active');
        if (win.classList.contains('active')) {
            document.body.classList.add('hanabot-open');
            setTimeout(() => {
                const inp = document.getElementById('hanabotInput');
                if (inp) inp.focus();
                scrollHanaBottom();
            }, 100);
        } else {
            document.body.classList.remove('hanabot-open');
        }
    };

    function scrollHanaBottom() {
        const msgBox = document.getElementById('hanabotMessages');
        if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;
    }

    function getHanaApiEndpoint() {
        const path = window.location.pathname;
        const match = path.match(/^(.*\/Klinik-Pratama-Rumah-Hanania)/i);
        const prefix = match ? match[1] : '';
        return prefix + '/chatbot/proses_chat.php';
    }

    function appendHanaMessage(text, sender, isFallback = false, waUrl = '') {
        const box = document.getElementById('hanabotMessages');
        if (!box) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = 'hanabot-msg ' + sender;

        let formatted = text.replace(/\n/g, '<br>');
        if (isFallback && waUrl) {
            formatted += `<div class="hanabot-wa-card" style="margin-top:12px;padding:12px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:12px;box-shadow:0 3px 8px rgba(37,211,102,0.12);">` +
                `<div style="font-size:12px;font-weight:800;color:#166534;margin-bottom:4px;display:flex;align-items:center;gap:6px;">` +
                `<span>💬 Hubungi Admin via WhatsApp</span>` +
                `</div>` +
                `<p style="font-size:11.5px;color:#14532D;margin:0 0 10px 0;line-height:1.45;">Pertanyaan ini belum tersedia di kamus otomatis. Tim customer service kami siap membantu Anda langsung via WhatsApp.</p>` +
                `<a href="${waUrl}" target="_blank" rel="noopener noreferrer" style="display:flex;align-items:center;justify-content:center;gap:8px;background:#25D366;color:#ffffff;padding:9px 14px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:800;box-shadow:0 2px 8px rgba(37,211,102,0.28);line-height:1.2;">` +
                `<span>Chat WhatsApp Admin</span> <span style="font-size:14px;">➔</span>` +
                `</a>` +
                `</div>`;
        }

        msgDiv.innerHTML = formatted;
        box.appendChild(msgDiv);
        scrollHanaBottom();
    }

    window.kirimHanaQuick = function(text) {
        kirimHanaPesan(text);
    };

    window.handleHanaSubmit = function(e) {
        if (e) e.preventDefault();
        const inp = document.getElementById('hanabotInput');
        if (!inp) return;
        const text = inp.value.trim();
        if (!text) return;
        inp.value = '';
        kirimHanaPesan(text);
    };

    async function kirimHanaPesan(text) {
        appendHanaMessage(text, 'user');

        const box = document.getElementById('hanabotMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'hanabot-msg bot';
        typingDiv.id = 'hanaTyping';
        typingDiv.innerHTML = '<em>HanaBot sedang mengetik...</em>';
        if (box) {
            box.appendChild(typingDiv);
            scrollHanaBottom();
        }

        try {
            const endpoint = getHanaApiEndpoint();
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message=' + encodeURIComponent(text)
            });
            const data = await res.json();

            const typingEl = document.getElementById('hanaTyping');
            if (typingEl) typingEl.remove();

            if (data && data.message) {
                appendHanaMessage(data.message, 'bot', !!data.is_fallback, data.whatsapp_url || '');
            } else {
                appendHanaMessage('Maaf, saya belum dapat memahami pertanyaan tersebut. Silakan hubungi admin WhatsApp kami.', 'bot');
            }
        } catch (err) {
            const typingEl = document.getElementById('hanaTyping');
            if (typingEl) typingEl.remove();
            appendHanaMessage('Maaf, koneksi chatbot terganggu. Silakan coba sesaat lagi.', 'bot');
        }
    }
})();
</script>
