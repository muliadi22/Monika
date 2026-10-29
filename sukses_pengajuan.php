<?php
// sukses_pengajuan.php
include 'includes/header.php';

if (!isset($_GET['kode']) || trim($_GET['kode']) === '') {
    header("Location: index.php");
    exit();
}
$kode = trim($_GET['kode']);
$kode_html = htmlspecialchars($kode, ENT_QUOTES, 'UTF-8');
$kode_url  = urlencode($kode);
?>

<script>
// Terapkan tema lebih awal (mencegah kedipan terang saat mode gelap)
try { var __t = localStorage.getItem('eicv-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); document.documentElement.setAttribute('data-theme', __t); document.documentElement.setAttribute('data-bs-theme', __t); } catch (e) {}
</script>
<style>
/* =========================================================
   THEME TOKENS & BASE
   ========================================================= */
:root {
    --bg: #f5f7ff; --bg-2: #eaf0ff;
    --surface: rgba(255,255,255,.72); --surface-solid: #ffffff; --surface-border: rgba(255,255,255,.9);
    --field: rgba(255,255,255,.9); --field-border: #dbe2f5;
    --text: #14213d; --muted: #5f6b85;
    --primary: #6366f1; --ok: #10b981; --warn: #f59e0b; --danger: #ef4444;
    --grad: linear-gradient(135deg,#6366f1 0%,#06b6d4 55%,#34d399 100%);
    --grad-ok: linear-gradient(135deg,#10b981,#34d399);
    --shadow: 0 30px 70px -25px rgba(79,70,229,.45);
    --radius: 30px;
}
[data-theme="dark"] {
    --bg: #0b1020; --bg-2: #111936;
    --surface: rgba(255,255,255,.06); --surface-solid: #151c3a; --surface-border: rgba(255,255,255,.12);
    --field: rgba(255,255,255,.05); --field-border: rgba(255,255,255,.14);
    --text: #eef2ff; --muted: #9aa6c7; --shadow: 0 30px 70px -25px rgba(0,0,0,.8);
}
html { scroll-behavior: smooth; }
body { background: var(--bg) !important; color: var(--text); transition: background .4s, color .4s; overflow-x: hidden; }

/* =========================================================
   LATAR BELAKANG RINGAN
   ========================================================= */
.bg-aurora {
    position: fixed; inset: 0; z-index: -3;
    background:
        radial-gradient(60% 55% at 12% 8%,  rgba(99, 102, 241, .30) 0%, transparent 60%),
        radial-gradient(55% 50% at 92% 22%, rgba(6, 182, 212, .26) 0%, transparent 62%),
        radial-gradient(55% 50% at 78% 95%, rgba(52, 211, 153, .20) 0%, transparent 60%),
        radial-gradient(50% 45% at 8% 88%,  rgba(244, 114, 182, .16) 0%, transparent 60%),
        linear-gradient(160deg, var(--bg-2), var(--bg) 55%);
    background-size: 150% 150%, 150% 150%, 150% 150%, 150% 150%, 100% 100%;
}
[data-theme="dark"] .bg-aurora {
    background:
        radial-gradient(60% 55% at 12% 8%,  rgba(99, 102, 241, .38) 0%, transparent 60%),
        radial-gradient(55% 50% at 92% 22%, rgba(6, 182, 212, .28) 0%, transparent 62%),
        radial-gradient(55% 50% at 78% 95%, rgba(16, 185, 129, .20) 0%, transparent 60%),
        radial-gradient(50% 45% at 8% 88%,  rgba(236, 72, 153, .16) 0%, transparent 60%),
        linear-gradient(160deg, var(--bg-2), var(--bg) 55%);
    background-size: 150% 150%, 150% 150%, 150% 150%, 150% 150%, 100% 100%;
}
.bg-fade { position: fixed; inset: 0; z-index: -1; pointer-events: none;
    background: radial-gradient(ellipse at 50% 38%, transparent 35%, var(--bg) 110%); opacity: .55; }
    
.bg-waves { position: fixed; left: 0; right: 0; bottom: 0; height: 30vh; z-index: -2; pointer-events: none; overflow: hidden; contain: strict; }
.bg-waves svg { position: absolute; left: 0; bottom: 0; width: 200%; height: 100%; will-change: transform; }
.bg-waves .w1 { animation: waveX 26s linear infinite; }
.bg-waves .w2 { animation: waveX 38s linear infinite reverse; opacity: .8; }
@keyframes waveX { to { transform: translateX(-50%); } }

.bg-aurora, .bg-waves, .bg-fade, .success-card, .pdf-card { 
    will-change: transform; 
    transform: translate3d(0,0,0); 
    -webkit-transform: translate3d(0,0,0); 
    backface-visibility: hidden; 
}
#confetti { position: fixed; inset: 0; pointer-events: none; z-index: 2500; }

/* =========================================================
   UI SUKSES PENGAJUAN
   ========================================================= */
.theme-btn { position: fixed; top: 18px; right: 18px; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); display: grid; place-items: center; cursor: pointer; backdrop-filter: blur(10px); transition: .3s; z-index: 10; touch-action: manipulation; }
.theme-btn:hover { transform: rotate(20deg) scale(1.08); }

.wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 26px; padding: 70px 14px 50px; }
.success-card { width: 100%; max-width: 620px; padding: 46px 38px 36px; border-radius: var(--radius); text-align: center; background: var(--surface); border: 1px solid var(--surface-border); backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px); box-shadow: var(--shadow); animation: rise .8s cubic-bezier(.2,.7,.2,1); }
@keyframes rise { from { opacity: 0; transform: translateY(40px) scale(.97); } to { opacity: 1; transform: none; } }

/* Logo Instansi di Web UI */
.ui-logos { display: flex; justify-content: center; align-items: center; gap: 16px; margin-bottom: 24px; animation: rise 0.6s ease-out; }
.ui-logos img { height: 52px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05)); }
[data-theme="dark"] .ui-logos img { filter: drop-shadow(0 4px 6px rgba(255,255,255,0.05)); }

/* Centang animasi */
.check-wrap { width: 110px; height: 110px; margin: 0 auto 22px; position: relative; }
.check-wrap::before, .check-wrap::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--ok); opacity: .25; animation: ripple 2.4s ease-out infinite; }
.check-wrap::after { animation-delay: 1.2s; }
@keyframes ripple { to { transform: scale(1.7); opacity: 0; } }
.check-wrap svg { position: relative; z-index: 1; width: 110px; height: 110px; filter: drop-shadow(0 16px 24px rgba(16,185,129,.45)); }
.check-circle { fill: none; stroke: var(--ok); stroke-width: 6; stroke-dasharray: 314; stroke-dashoffset: 314; animation: draw .8s ease forwards; transform-origin: center; transform: rotate(-90deg); }
.check-fill { fill: var(--ok); opacity: 0; animation: fillIn .4s ease .7s forwards; }
.check-mark { fill: none; stroke: #fff; stroke-width: 7; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 60; stroke-dashoffset: 60; animation: draw .5s ease .95s forwards; }
@keyframes draw { to { stroke-dashoffset: 0; } }
@keyframes fillIn { to { opacity: 1; } }

.text-grad { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
h1.title { font-weight: 800; letter-spacing: -.8px; font-size: clamp(1.8rem,5vw,2.4rem); margin-bottom: 10px; }
.lead-text { color: var(--muted) !important; max-width: 470px; margin: 0 auto; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(245,158,11,.16); color: var(--warn); border: 1px solid rgba(245,158,11,.4); font-weight: 800; font-size: .82rem; padding: 3px 12px; border-radius: 50px; vertical-align: middle; }

/* Kode tracking */
.code-box { margin: 28px 0 14px; padding: 24px 20px 22px; border-radius: 24px; border: 2px dashed var(--primary); background: linear-gradient(135deg, rgba(99,102,241,.1), rgba(6,182,212,.08)); position: relative; overflow: hidden; }
.code-box::before { content: ''; position: absolute; width: 180px; height: 180px; border-radius: 50%; background: rgba(99,102,241,.12); top: -80px; right: -50px; }
.code-label { color: var(--muted); font-size: .82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; position: relative; }
.code-value { font-size: clamp(1.4rem,5.2vw,2.1rem); font-weight: 800; letter-spacing: 2px; color: var(--primary); user-select: all; word-break: break-all; position: relative; line-height: 1.25; }
[data-theme="dark"] .code-value { color: #a5b4fc; }
.copy-main { margin-top: 16px; position: relative; display: inline-flex; align-items: center; gap: 8px; border: 0; background: var(--primary); color: #fff; font-weight: 700; font-size: .9rem; padding: 10px 22px; border-radius: 100px; box-shadow: 0 10px 22px -8px var(--primary); transition: .25s; touch-action: manipulation; }
.copy-main:hover { transform: translateY(-3px); }
.copy-main.done { background: var(--ok); box-shadow: 0 10px 22px -8px var(--ok); }

.warn-box { display: flex; gap: 10px; align-items: flex-start; text-align: left; padding: 13px 16px; border-radius: 16px; background: rgba(239,68,68,.09); border: 1px solid rgba(239,68,68,.3); color: var(--danger); font-size: .87rem; font-weight: 600; margin-bottom: 22px; }
[data-theme="dark"] .warn-box { color: #fca5a5; }
.warn-box i { margin-top: 2px; }

/* Langkah berikutnya */
.next-steps { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 24px; }
.ns { padding: 14px 8px; border-radius: 18px; background: var(--field); border: 1px solid var(--field-border); font-size: .78rem; font-weight: 700; color: var(--muted); }
.ns i { display: block; font-size: 1.4rem; margin-bottom: 4px; color: var(--primary); }
.ns.on { border-color: var(--ok); color: var(--text); }
.ns.on i { color: var(--ok); }

/* Tombol */
.btn-grad { background: var(--grad); color: #fff !important; border: 0; font-weight: 800; border-radius: 100px; padding: 14px 28px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s; box-shadow: 0 14px 28px -10px var(--primary); touch-action: manipulation; }
.btn-grad:hover { transform: translateY(-3px); box-shadow: 0 20px 34px -10px var(--primary); }
.btn-grad::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg,transparent,rgba(255,255,255,.5),transparent); transform: skewX(-20deg); transition: .6s; }
.btn-grad:hover::after { left: 140%; }
.btn-ghost { border: 1px solid var(--field-border); background: var(--surface); color: var(--text) !important; border-radius: 100px; padding: 12px 20px; font-weight: 700; font-size: .9rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: .25s; cursor: pointer; touch-action: manipulation; }
.btn-ghost:hover { background: var(--field); transform: translateY(-3px); }
.main-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.main-actions > * { flex: 1 1 200px; }
.export-box { margin-top: 14px; }
.export-btn { display: flex; align-items: center; gap: 14px; text-align: left; padding: 14px 20px; border-radius: 22px; text-decoration: none !important; color: #fff !important; background: linear-gradient(135deg,#ef4444 0%,#f472b6 100%); box-shadow: 0 16px 30px -12px #ef4444; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s; touch-action: manipulation; }
.export-btn:hover { transform: translateY(-3px); box-shadow: 0 22px 36px -12px #ef4444; }
.export-btn::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent); transform: skewX(-20deg); transition: .6s; }
.export-btn:hover::after { left: 140%; }
.export-btn .ex-ic { width: 50px; height: 50px; border-radius: 16px; background: rgba(255,255,255,.22); display: grid; place-items: center; font-size: 1.6rem; flex-shrink: 0; }
.export-btn .ex-txt { flex: 1; min-width: 0; line-height: 1.25; }
.export-btn .ex-txt b { display: block; font-size: 1.05rem; font-weight: 800; }
.export-btn .ex-txt small { opacity: .92; font-weight: 600; font-size: .78rem; }
.export-btn .ex-dl { font-size: 1.3rem; animation: bob 1.8s ease-in-out infinite; }
@keyframes bob { 50% { transform: translateY(4px); } }

/* Kartu bukti PDF */
.pdf-card { width: 100%; max-width: 820px; padding: 22px 22px 22px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--surface-border); backdrop-filter: blur(22px); -webkit-backdrop-filter: blur(22px); box-shadow: var(--shadow); opacity: 0; transform: translateY(40px); transition: opacity .8s ease, transform .8s cubic-bezier(.2,.7,.2,1); }
.pdf-card.show { opacity: 1; transform: none; }
.pdf-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; }
.pdf-title { display: flex; align-items: center; gap: 12px; text-align: left; }
.pdf-ic { width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg,#ef4444,#f472b6); color: #fff; display: grid; place-items: center; font-size: 1.4rem; box-shadow: 0 10px 20px -8px #ef4444; flex-shrink: 0; }
.pdf-title b { display: block; font-weight: 800; }
.pdf-title small { color: var(--muted); }
.pdf-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.pdf-actions .btn-grad { padding: 11px 22px; font-size: .9rem; }
.pdf-frame { width: 100%; height: 680px; border: 1px solid var(--field-border); border-radius: 18px; background: #fff; display: block; }
.pdf-note { display: none; color: var(--muted); font-size: .85rem; text-align: center; }
@media (max-width: 768px) { .pdf-frame { display: none; } .pdf-note { display: block; } .pdf-actions { width: 100%; } .pdf-actions > * { flex: 1 1 100%; } }

.toast-mini { position: fixed; bottom: 24px; left: 50%; transform: translate(-50%,120px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 100px; font-weight: 600; transition: .4s; z-index: 3000; max-width: 90vw; text-align: center; }
.toast-mini.show { transform: translate(-50%,0); }

@media (max-width: 480px) { .success-card { padding: 36px 20px 28px; } .next-steps { gap: 6px; } }
@media print {
    .bg-aurora, .theme-btn, .main-actions, .export-box, #confetti, .copy-main, .toast-mini { display: none !important; }
    body { background: #fff !important; color: #000; } .success-card { box-shadow: none; backdrop-filter: none; border: 1px solid #ccc; }
}
@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } .check-circle, .check-mark { stroke-dashoffset: 0; } .check-fill { opacity: 1; } }

/* =========================================================
   PERBAIKAN KETERBACAAN MODE GELAP
   ========================================================= */
html body .soft { color: var(--muted) !important; }
.text-muted, .text-secondary, .text-body-secondary { color: var(--muted) !important; }
footer, footer p, footer small, footer span, footer a { color: var(--muted) !important; }
</style>

<!-- ===== LATAR BELAKANG ===== -->
<div class="bg-aurora"></div>
<div class="bg-waves" aria-hidden="true">
    <svg class="w2" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg2" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".16"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".16"/><stop offset="1" stop-color="#34d399" stop-opacity=".14"/></linearGradient></defs>
        <path fill="url(#wg2)" d="M0 110 C 200 60 400 160 600 110 S 1000 60 1200 110 S 1600 160 1800 110 S 2200 60 2400 110 V200 H0Z"/></svg>
    <svg class="w1" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg1" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".22"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".2"/><stop offset="1" stop-color="#f472b6" stop-opacity=".16"/></linearGradient></defs>
        <path fill="url(#wg1)" d="M0 140 C 150 100 350 190 600 140 S 1050 90 1200 140 S 1550 190 1800 140 S 2250 90 2400 140 V200 H0Z"/></svg>
</div>
<div class="bg-fade"></div>

<canvas id="confetti"></canvas>

<button class="theme-btn" id="themeToggle" type="button" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i></button>

<div class="wrap">
    <div class="success-card">

        <!-- LOGO INSTANSI DITAMBAHKAN DI SINI -->
        <div class="ui-logos">
            <img src="assets/images/logo_bkk.webp" alt="Logo BKK Banjarmasin">
            <img src="assets/images/logo_karantina.webp" alt="Logo Karantina">
        </div>

        <div class="check-wrap">
            <svg viewBox="0 0 110 110" aria-hidden="true">
                <circle class="check-fill" cx="55" cy="55" r="50"/>
                <circle class="check-circle" cx="55" cy="55" r="50"/>
                <path class="check-mark" d="M34 57 L49 72 L78 40"/>
            </svg>
        </div>

        <h1 class="title">Pengajuan <span class="text-grad">Berhasil!</span></h1>
        <p class="lead-text soft mb-0">
            Terima kasih, data permohonan Anda telah kami terima dan saat ini berstatus
            <span class="status-badge"><i class="bi bi-hourglass-split"></i> Menunggu</span>
        </p>

        <div class="code-box">
            <div class="code-label"><i class="bi bi-ticket-perforated me-1"></i> Kode Tracking Anda</div>
            <div class="code-value" id="kodeText"><?php echo $kode_html; ?></div>
            <button type="button" class="copy-main" id="copyKode"><i class="bi bi-clipboard"></i> <span>Salin Kode</span></button>
        </div>

        <div class="warn-box">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Harap simpan atau salin Kode Tracking di atas. Anda membutuhkannya untuk mengecek status permohonan.</span>
        </div>

        <div class="next-steps">
            <div class="ns on"><i class="bi bi-check-circle-fill"></i>Terkirim</div>
            <div class="ns"><i class="bi bi-gear"></i>Diproses admin</div>
            <div class="ns"><i class="bi bi-file-earmark-check"></i>Dokumen siap</div>
        </div>

        <div class="main-actions">
            <a href="tracking.php?kode_tracking=<?php echo $kode_url; ?>" class="btn-grad"><i class="bi bi-search"></i> Cek Status Sekarang</a>
            <a href="index.php" class="btn-ghost"><i class="bi bi-house"></i> Kembali ke Beranda</a>
        </div>

        <div class="export-box">
            <a href="bukti_pengajuan.php?kode=<?php echo $kode_url; ?>&amp;unduh=1" class="export-btn" id="exportPdf">
                <span class="ex-ic"><i class="bi bi-file-earmark-pdf-fill"></i></span>
                <span class="ex-txt"><b>Export PDF</b><small>Simpan bukti berisi data pengajuan &amp; kode tracking</small></span>
                <i class="bi bi-download ex-dl"></i>
            </a>
        </div>
    </div>
    
    <!-- Kartu Pratinjau Bukti PDF -->
    <div class="pdf-card" id="pdfCard">
        <div class="pdf-head">
            <div class="pdf-title">
                <div class="pdf-ic"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                <div><b>Bukti Pengajuan (PDF)</b><small>Rekap data pengajuan dan kode tracking Anda</small></div>
            </div>
            <div class="pdf-actions">
                <a href="bukti_pengajuan.php?kode=<?php echo $kode_url; ?>" target="_blank" rel="noopener" class="btn-ghost"><i class="bi bi-box-arrow-up-right"></i> Buka di Tab Baru</a>
            </div>
        </div>
        <!-- Iframe ini memanggil file PDF yang sebelumnya sudah kita beri logo -->
        <iframe class="pdf-frame" id="pdfFrame" title="Bukti Pengajuan PDF" data-src="bukti_pengajuan.php?kode=<?php echo $kode_url; ?>#view=FitH"></iframe>
        <div class="pdf-note"><i class="bi bi-info-circle"></i> Pratinjau PDF tersedia di layar yang lebih besar. Gunakan tombol Export PDF pada kartu di atas.</div>
    </div>
</div>

<div class="toast-mini" id="toast"></div>

<script>
(function () {
    const $ = s => document.querySelector(s);
    const KODE = <?php echo json_encode($kode); ?>;

    /* Tema */
    const root = document.documentElement, tb = $('#themeToggle');
    const paint = () => tb.innerHTML = root.getAttribute('data-theme') === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
    paint();
    tb.addEventListener('click', () => {
        const t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', t); root.setAttribute('data-bs-theme', t);
        try { localStorage.setItem('eicv-theme', t); } catch (e) {} paint();
    });

    /* Toast + salin */
    let tt; function toast(m) { const t = $('#toast'); t.textContent = m; t.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 2400); }
    function copyText(txt, ok) {
        const done = () => toast(ok);
        const fallback = () => { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) { toast('Gagal menyalin, salin manual'); } ta.remove(); };
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done).catch(fallback); else fallback();
    }

    const btn = $('#copyKode');
    btn.addEventListener('click', () => {
        copyText(KODE, 'Kode tracking disalin');
        btn.classList.add('done'); btn.querySelector('span').textContent = 'Tersalin!'; btn.querySelector('i').className = 'bi bi-check-lg';
        setTimeout(() => { btn.classList.remove('done'); btn.querySelector('span').textContent = 'Salin Kode'; btn.querySelector('i').className = 'bi bi-clipboard'; }, 2200);
    });

    /* Simpan ke riwayat lacak */
    try {
        const K = 'eicv-recent-codes', r = JSON.parse(localStorage.getItem(K) || '[]');
        localStorage.setItem(K, JSON.stringify([KODE, ...r.filter(c => c !== KODE)].slice(0, 5)));
    } catch (e) {}

    /* Konfeti */
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const cv = $('#confetti'), ctx = cv.getContext('2d');
        const resize = () => { cv.width = innerWidth; cv.height = innerHeight; }; resize(); addEventListener('resize', resize);
        const colors = ['#6366f1', '#06b6d4', '#34d399', '#f472b6', '#fb923c', '#fde047'];
        const ps = Array.from({ length: 150 }, () => ({ x: Math.random() * cv.width, y: -20 - Math.random() * cv.height * .6, w: 6 + Math.random() * 6, h: 8 + Math.random() * 8, c: colors[Math.random() * colors.length | 0], vy: 2 + Math.random() * 4, vx: -2 + Math.random() * 4, r: Math.random() * 6, vr: -.2 + Math.random() * .4 }));
        const t0 = performance.now();
        setTimeout(function () {
            (function frame(t) {
                ctx.clearRect(0, 0, cv.width, cv.height);
                ps.forEach(p => { p.x += p.vx; p.y += p.vy; p.r += p.vr; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore(); });
                if (t - t0 < 5200) requestAnimationFrame(frame); else ctx.clearRect(0, 0, cv.width, cv.height);
            })(performance.now());
        }, 600);
    }
    
    $('#exportPdf').addEventListener('click', () => toast('Mengunduh bukti pengajuan (PDF)…'));

    /* Bukti PDF muncul otomatis setelah animasi UI (kurang lebih 1.1 detik) */
    const pc = $('#pdfCard'), fr = $('#pdfFrame');
    setTimeout(function () { pc.classList.add('show'); fr.src = fr.dataset.src; }, 1100);

})();
</script>

<?php include 'includes/footer.php'; ?>