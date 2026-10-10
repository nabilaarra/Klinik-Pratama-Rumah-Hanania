<?php
// Jangan jalankan pemutar musik di area Admin atau halaman pendaftaran & formulir
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
if (
    stripos($currentUri, '/admin/') !== false || 
    stripos($currentScript, '/admin/') !== false ||
    stripos($currentUri, 'pasien/daftar.php') !== false ||
    stripos($currentScript, 'daftar.php') !== false ||
    stripos($currentScript, 'daftar_akun.php') !== false ||
    stripos($currentScript, 'login.php') !== false ||
    stripos($currentScript, 'lupa_password.php') !== false
) {
    return;
}

// Helper ambil konten jika belum ada
if (!function_exists('getSiteKontenAudio')) {
    function getSiteKontenAudio($key, $default = '') {
        global $conn;
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            if (isset($conn) && $conn instanceof mysqli) {
                $q = @mysqli_query($conn, "SELECT kunci, nilai FROM pengaturan_konten");
                if ($q) {
                    while ($r = mysqli_fetch_assoc($q)) {
                        $cache[$r['kunci']] = $r['nilai'];
                    }
                }
            }
        }
        return isset($cache[$key]) && trim((string)$cache[$key]) !== '' ? $cache[$key] : $default;
    }
}

$audioAktif = getSiteKontenAudio('audio_beranda_aktif', '1');
$audioFile = getSiteKontenAudio('audio_beranda_file', 'uploads/audio/suasana_klinik_hanania.mp3');
$audioJudul = getSiteKontenAudio('audio_beranda_judul', 'Suasana Tenang & Damai - Klinik Pratama Rumah Hanania');

if ($audioAktif !== '1' || empty($audioFile)) {
    return;
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$audioBase = (stripos($scriptName, '/Klinik-Pratama-Rumah-Hanania') !== false) ? '/Klinik-Pratama-Rumah-Hanania' : '';
$audioSrc = $audioBase . '/' . ltrim($audioFile, '/');
?>

<!-- =====================================================
     GLOBAL AMBIENT AUDIO PLAYER (KLINIK RUMAH HANANIA)
===================================================== -->
<div class="hanania-audio-wrap" id="hananiaAudioWrap" aria-live="polite">
    <audio id="hananiaBgAudio" src="<?= htmlspecialchars($audioSrc, ENT_QUOTES, 'UTF-8') ?>" loop preload="auto" playsinline autoplay>
        <source src="<?= htmlspecialchars($audioSrc, ENT_QUOTES, 'UTF-8') ?>" type="audio/mpeg">
    </audio>
    <button type="button" class="hanania-audio-speaker" id="hananiaAudioSpeaker" onclick="toggleHananiaAudio(event)" title="<?= htmlspecialchars($audioJudul, ENT_QUOTES, 'UTF-8') ?> (Klik untuk Putar / Jeda)" aria-label="Musik Suasana Klinik Pratama Rumah Hanania">
        <span id="hananiaAudioIcon">🔇</span>
    </button>
    <div class="hanania-audio-toast" id="hananiaAudioToast" onclick="toggleHananiaAudio(event)" title="Klik untuk memutar / mengatur musik latar">
        <span class="hanania-audio-toast-pulse"></span>
        <span class="hanania-audio-toast-text" id="hananiaAudioToastText">🎵 Musik Suasana Klinik</span>
    </div>
</div>

<style>
/* WRAPPER */
.hanania-audio-wrap {
    position: fixed;
    bottom: 26px;
    left: 26px;
    z-index: 1500;
    display: flex;
    align-items: center;
    gap: 10px;
    pointer-events: none;
}

/* SPEAKER FLOATING BUTTON */
.hanania-audio-speaker {
    pointer-events: auto;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1.5px solid #DDD0F0;
    box-shadow: 0 8px 24px rgba(90, 25, 135, 0.18);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    color: #6A22A7;
    font-size: 20px;
    padding: 0;
    outline: none;
    user-select: none;
    position: relative;
}
.hanania-audio-speaker:hover {
    transform: scale(1.08);
    box-shadow: 0 12px 30px rgba(90, 25, 135, 0.28);
    border-color: #6A22A7;
}
.hanania-audio-speaker:active {
    transform: scale(0.96);
}
.hanania-audio-speaker.is-waiting {
    border-color: #8A4BC8;
    animation: hanania-waiting-pulse 1.8s infinite;
}
@keyframes hanania-waiting-pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 4px 16px rgba(106, 34, 167, 0.2);
    }
    50% {
        transform: scale(1.08);
        box-shadow: 0 8px 26px rgba(106, 34, 167, 0.4);
    }
}
.hanania-audio-speaker.is-playing {
    background: linear-gradient(135deg, #6A22A7 0%, #8A4BC8 100%);
    color: #FFFFFF;
    border-color: #8A4BC8;
    box-shadow: 0 8px 25px rgba(106, 34, 167, 0.38);
    animation: none;
}
.hanania-audio-speaker.is-playing::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 2px solid rgba(138, 75, 200, 0.45);
    animation: hanania-soundwave 2s cubic-bezier(0, 0, 0.2, 1) infinite;
    pointer-events: none;
}

@keyframes hanania-soundwave {
    0% {
        transform: scale(0.9);
        opacity: 0.85;
    }
    100% {
        transform: scale(1.45);
        opacity: 0;
    }
}

/* FLOATING TOAST / HINT PILL */
.hanania-audio-toast {
    pointer-events: auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid #E4D7F5;
    padding: 7px 14px 7px 10px;
    border-radius: 9999px;
    box-shadow: 0 4px 18px rgba(106, 34, 167, 0.12);
    font-size: 11.5px;
    font-weight: 700;
    color: #4A1775;
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateX(-10px);
    visibility: hidden;
    white-space: nowrap;
}
.hanania-audio-toast.show {
    opacity: 1;
    transform: translateX(0);
    visibility: visible;
}
.hanania-audio-toast:hover {
    background: #6A22A7;
    color: #FFFFFF;
    border-color: #6A22A7;
    transform: translateY(-1px);
}
.hanania-audio-toast-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10B981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: hanania-pulse-green 1.8s infinite;
    flex-shrink: 0;
}
@keyframes hanania-pulse-green {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@media (max-width: 600px) {
    .hanania-audio-wrap {
        bottom: 20px;
        left: 16px;
    }
    .hanania-audio-speaker {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    .hanania-audio-toast {
        max-width: 220px;
        font-size: 10.5px;
        padding: 6px 12px;
        white-space: normal;
        line-height: 1.3;
    }
}
</style>

<script>
(function() {
    const audio = document.getElementById('hananiaBgAudio');
    const icon = document.getElementById('hananiaAudioIcon');
    const speaker = document.getElementById('hananiaAudioSpeaker');
    const toast = document.getElementById('hananiaAudioToast');
    const toastText = document.getElementById('hananiaAudioToastText');
    if (!audio) return;

    // Bersihkan penanda pause lama di localStorage agar tidak terkunci permanen
    try {
        localStorage.removeItem('hanania_audio_user_paused');
    } catch(e) {}

    const TARGET_VOLUME = 0.55;
    let isUserPaused = sessionStorage.getItem('hanania_audio_user_paused') === '1';
    let isPlayPending = false;
    let fadeInterval = null;
    let toastTimer = null;

    // Pulihkan posisi detik lagu sebelumnya agar seamless saat berpindah halaman
    const savedTime = sessionStorage.getItem('hanania_audio_time');
    if (savedTime && !isNaN(parseFloat(savedTime))) {
        const applySavedTime = function() {
            try {
                audio.currentTime = parseFloat(savedTime);
            } catch(e) {}
        };
        if (audio.readyState >= 1) {
            applySavedTime();
        } else {
            audio.addEventListener('loadedmetadata', applySavedTime, { once: true });
        }
    }

    function setPlayingState(playing) {
        if (speaker) {
            if (playing) {
                speaker.classList.remove('is-waiting');
                speaker.classList.add('is-playing');
            } else {
                speaker.classList.remove('is-playing');
            }
        }
        if (icon) icon.textContent = playing ? '🔊' : '🔇';
    }

    function showToast(text, durationMs) {
        if (!toast || !toastText) return;
        if (toastTimer) {
            clearTimeout(toastTimer);
            toastTimer = null;
        }
        toastText.textContent = text;
        toast.classList.add('show');
        if (durationMs && durationMs > 0) {
            toastTimer = setTimeout(() => {
                if (audio && !audio.paused && !audio.muted) {
                    toast.classList.remove('show');
                }
            }, durationMs);
        }
    }

    function hideToast() {
        if (toastTimer) {
            clearTimeout(toastTimer);
            toastTimer = null;
        }
        if (toast) toast.classList.remove('show');
    }

    // Fade-in volume halus agar lagu tidak mengagetkan saat mulai berputar
    function fadeInVolume(targetVol, durationMs) {
        targetVol = targetVol || TARGET_VOLUME;
        durationMs = durationMs || 1000;
        if (fadeInterval) clearInterval(fadeInterval);
        audio.volume = 0.05;
        const steps = 20;
        const stepTime = durationMs / steps;
        const inc = (targetVol - 0.05) / steps;
        let cur = 0.05;
        fadeInterval = setInterval(() => {
            if (audio.paused) {
                clearInterval(fadeInterval);
                return;
            }
            cur += inc;
            if (cur >= targetVol) {
                audio.volume = targetVol;
                clearInterval(fadeInterval);
            } else {
                audio.volume = Math.min(targetVol, cur);
            }
        }, stepTime);
    }

    // Rekam posisi audio saat memutar agar antar halaman tidak mengulang dari 0
    audio.addEventListener('timeupdate', function() {
        if (!audio.paused && audio.currentTime > 0) {
            sessionStorage.setItem('hanania_audio_time', audio.currentTime);
        }
    });

    window.addEventListener('beforeunload', function() {
        if (audio && audio.currentTime > 0) {
            sessionStorage.setItem('hanania_audio_time', audio.currentTime);
        }
    });

    // Toggle manual klik speaker
    window.toggleHananiaAudio = function(e) {
        if (e) e.stopPropagation();
        if (audio.paused || audio.muted) {
            audio.muted = false;
            isUserPaused = false;
            sessionStorage.removeItem('hanania_audio_user_paused');
            removeActivationListeners();
            const p = audio.play();
            if (p !== undefined) {
                p.then(() => {
                    fadeInVolume(TARGET_VOLUME, 600);
                    setPlayingState(true);
                    showToast('🎵 Musik Suasana Klinik Berjalan', 2500);
                }).catch(() => {});
            }
        } else {
            audio.pause();
            isUserPaused = true;
            sessionStorage.setItem('hanania_audio_user_paused', '1');
            setPlayingState(false);
            showToast('⏸️ Musik Dijeda', 2000);
        }
    };

    // Jika pengunjung secara sadar menekan jeda pada sesi tab saat ini, hormati jeda tersebut
    if (isUserPaused) {
        audio.pause();
        setPlayingState(false);
        return;
    }

    // Fungsi aktivasi pemutaran audio
    function attemptPlay(isUserGesture) {
        if (isUserPaused || !audio.paused) return;
        if (isPlayPending) return;
        isPlayPending = true;

        audio.muted = false;
        const playPromise = audio.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                isPlayPending = false;
                removeActivationListeners();
                setPlayingState(true);
                fadeInVolume(TARGET_VOLUME, 900);
                showToast('🎵 Musik Suasana Klinik Berjalan', 3000);
            }).catch((err) => {
                isPlayPending = false;
                // Autoplay Policy browser menahan audio bersuara sebelum ada sentuhan awal pengunjung.
                // Aktifkan mode siaga: speaker berdenyut lembut dan dengarkan gesture pertama pengunjung.
                if (speaker) speaker.classList.add('is-waiting');
                setPlayingState(false);
                showToast('🎵 Sentuh / klik di mana saja untuk dengar musik', 0);
                addActivationListeners();
            });
        } else {
            isPlayPending = false;
        }
    }

    const activationEvents = ['pointerdown', 'touchstart', 'mousedown', 'click', 'keydown'];
    let listenersActive = false;

    function onFirstUserGesture(ev) {
        if (isUserPaused) return;
        removeActivationListeners();
        attemptPlay(true);
    }

    function addActivationListeners() {
        if (listenersActive) return;
        listenersActive = true;
        activationEvents.forEach(evt => {
            window.addEventListener(evt, onFirstUserGesture, { capture: true, passive: true });
            document.addEventListener(evt, onFirstUserGesture, { capture: true, passive: true });
        });
    }

    function removeActivationListeners() {
        if (!listenersActive) return;
        listenersActive = false;
        activationEvents.forEach(evt => {
            window.removeEventListener(evt, onFirstUserGesture, { capture: true, passive: true });
            document.removeEventListener(evt, onFirstUserGesture, { capture: true, passive: true });
        });
    }

    // 1. Eksekusi coba autoplay langsung begitu script terbaca
    attemptPlay(false);

    // 2. Eksekusi coba lagi saat DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (audio.paused && !isUserPaused) {
                attemptPlay(false);
            }
        });
    }

    // 3. Eksekusi coba lagi saat window load selesai (semua asset ter-buffer)
    window.addEventListener('load', function() {
        if (audio.paused && !isUserPaused) {
            attemptPlay(false);
        }
    });

    // 4. Buka kembali saat tab kembali aktif
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !isUserPaused && audio.paused) {
            attemptPlay(false);
        }
    });
})();
</script>
