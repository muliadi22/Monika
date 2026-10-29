<?php
// index.php
require_once 'config/database.php';
include 'includes/header.php';

// ==== LOGO INSTANSI (ganti dengan logo Anda) ====
$LOGO_1      = 'assets/images/logo_bkk.webp';
$LOGO_1_NAMA = 'Instansi 1';
$LOGO_2      = 'assets/images/logo_karantina.webp';
$LOGO_2_NAMA = 'Instansi 2';
?>

<style>
:root {
    --bg: #f5f7ff;
    --bg-2: #eaf0ff;
    --surface: rgba(255, 255, 255, .65);
    --surface-border: rgba(255, 255, 255, .8);
    --text: #14213d;
    --muted: #5f6b85;
    --primary: #6366f1;
    --secondary: #06b6d4;
    --accent: #f472b6;
    --grad: linear-gradient(135deg, #6366f1 0%, #06b6d4 55%, #34d399 100%);
    --grad-warm: linear-gradient(135deg, #f472b6 0%, #fb923c 100%);
    --shadow: 0 20px 50px -20px rgba(79, 70, 229, .35);
    --radius: 24px;
}
[data-theme="dark"] {
    --bg: #0b1020;
    --bg-2: #111936;
    --surface: rgba(255, 255, 255, .06);
    --surface-border: rgba(255, 255, 255, .12);
    --text: #eef2ff;
    --muted: #9aa6c7;
    --shadow: 0 20px 60px -20px rgba(0, 0, 0, .7);
}

html { scroll-behavior: smooth; scroll-padding-top: 110px; }

body {
    background: var(--bg) !important;
    color: var(--text);
    font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
    transition: background .4s ease, color .4s ease;
    overflow-x: hidden;
}

.text-muted { color: var(--muted) !important; }

@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ---------- LATAR ---------- */
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
.scroll-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--grad); z-index: 2000; }

.bg-waves { position: fixed; left: 0; right: 0; bottom: 0; height: 30vh; z-index: -2; pointer-events: none; overflow: hidden; contain: strict; }
.bg-waves svg { position: absolute; left: 0; bottom: 0; width: 200%; height: 100%; will-change: transform; }
.bg-waves .w1 { animation: waveX 26s linear infinite; }
.bg-waves .w2 { animation: waveX 38s linear infinite reverse; opacity: .8; }
@keyframes waveX { to { transform: translateX(-50%); } }

/* ---------- NAVBAR ---------- */
.nav-modern {
    background: var(--surface);
    border: 1px solid var(--surface-border); border-radius: 100px;
    margin: 14px auto 0; max-width: 1100px; width: calc(100% - 24px);
    box-shadow: var(--shadow); position: sticky; top: 12px; z-index: 1500;
    padding: 10px 16px;
    display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
}
.nav-modern .brand { justify-self: start; }
.nav-modern .nav-links { grid-column: 2; justify-self: center; align-items: center; gap: 4px; }
.nav-modern .nav-right { grid-column: 3; justify-self: end; display: flex; align-items: center; gap: 8px; }
.nav-modern .brand { font-weight: 800; font-size: 1.15rem; color: var(--text); text-decoration: none; display: flex; align-items: center; gap: 10px; }
.brand-logo { width: 38px; height: 38px; border-radius: 12px; background: var(--grad); color: #fff; display: grid; place-items: center; box-shadow: 0 8px 20px -6px var(--primary); }
.nav-links a { color: var(--muted); text-decoration: none; font-weight: 600; font-size: .92rem; padding: 8px 14px; border-radius: 50px; transition: .25s; }
.nav-links a:hover { color: var(--text); background: var(--surface-border); }
.icon-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); display: grid; place-items: center; cursor: pointer; transition: .3s; }
.icon-btn:hover { transform: rotate(20deg) scale(1.08); }
.btn-grad {
    background: var(--grad); color: #fff !important; border: 0; font-weight: 700; border-radius: 100px;
    padding: 10px 22px; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s;
    box-shadow: 0 10px 25px -8px var(--primary);
}
.btn-grad:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -8px var(--primary); }
.btn-grad::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,.5), transparent); transform: skewX(-20deg); transition: .6s; }
.btn-grad:hover::after { left: 140%; }
.btn-ghost { border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); border-radius: 100px; padding: 10px 22px; font-weight: 700; transition: .25s; }
.btn-ghost:hover { background: var(--surface-border); color: var(--text); transform: translateY(-3px); }

/* ---------- HERO & LOGO ---------- */
.hero { padding: 0 0 8px; text-align: center; position: relative; }
.hero-logos { display: flex; justify-content: space-between; align-items: center; max-width: 940px; margin: 0 auto; padding: 0 20px; }
.hero-logos figure { margin: 0; padding: 0; }
.hero-logos .logo-chip { display: flex; align-items: center; justify-content: center; transition: transform .3s; height: 250px; }
.hero-logos .logo-chip img { height: 250px; width: auto; max-width: 420px; object-fit: contain; display: block; }
.hero-logos .logo-chip:hover { transform: translateY(-4px) scale(1.04); }
[data-theme="dark"] .hero-logos .logo-chip img { filter: drop-shadow(0 0 1.5px rgba(255,255,255,.85)) drop-shadow(0 6px 18px rgba(255,255,255,.12)); }
@media (max-width: 768px) {
    .hero-logos { padding: 0 10px; max-width: 100%; justify-content: space-around; }
    .hero-logos .logo-chip, .hero-logos .logo-chip img { height: 120px; }
}
@media (max-width: 576px) {
    .hero-logos .logo-chip, .hero-logos .logo-chip img { height: 80px; }
}

.pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 100px; background: var(--surface); border: 1px solid var(--surface-border); font-weight: 600; font-size: .85rem; color: var(--muted); }
.pulse-dot { width: 9px; height: 9px; border-radius: 50%; background: #22c55e; position: relative; }
.pulse-dot::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: #22c55e; animation: ping 1.6s infinite; }
@keyframes ping { to { transform: scale(3.2); opacity: 0; } }

.hero h1 { font-weight: 800; font-size: clamp(2.3rem, 6vw, 4.4rem); line-height: 1.15; letter-spacing: -1.5px; margin: 0 0 12px; min-height: 2.3em; }
.text-grad { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; background-size: 200% 200%; animation: shine 6s linear infinite; }
@keyframes shine { to { background-position: 200% 0; } }
.typed-cursor { display: inline-block; width: 3px; height: .9em; background: var(--primary); margin-left: 4px; vertical-align: -.08em; animation: blink 1s steps(1) infinite; }
@keyframes blink { 50% { opacity: 0; } }
.hero p.lead { color: var(--muted); max-width: 640px; margin: 0 auto; font-size: 1.12rem; }

/* ---------- KARTU ---------- */
.glass-card {
    background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius);
    box-shadow: var(--shadow); position: relative; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease;
}
.glass-card:hover { transform: translateY(-4px); }
.glass-card::after {
    content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 1.5px; opacity: 0; transition: .3s;
    background: var(--grad); -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none;
}
.glass-card:hover::after { opacity: 1; }
.menu-card .icon-box {
    width: 92px; height: 92px; border-radius: 28px; display: grid; place-items: center; font-size: 2.4rem; color: #fff;
    background: var(--grad); margin: 0 auto 22px; box-shadow: 0 18px 35px -10px var(--primary);
    animation: float 4s ease-in-out infinite;
}
.menu-card.warm .icon-box { background: var(--grad-warm); box-shadow: 0 18px 35px -10px #f472b6; animation-delay: -1.5s; }
@keyframes float { 50% { transform: translateY(-10px); } }
.menu-card .cta { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: var(--primary); }
.menu-card.warm .cta { color: #f0589b; }
.menu-card .cta i { transition: transform .3s; }
.menu-card:hover .cta i { transform: translateX(8px); }
.feature-tags span { font-size: .75rem; font-weight: 700; padding: 5px 12px; border-radius: 50px; background: var(--surface-border); color: var(--muted); }

.section-title { font-weight: 800; font-size: clamp(1.7rem, 4vw, 2.5rem); letter-spacing: -.8px; }
.step-card { padding: 28px 24px; height: 100%; }
.step-num { width: 46px; height: 46px; border-radius: 14px; background: var(--grad); color: #fff; font-weight: 800; display: grid; place-items: center; margin-bottom: 16px; }
.step-card h5 { font-weight: 700; }
.step-card p { color: var(--muted); margin: 0; font-size: .95rem; }

/* ---------- FAQ ---------- */
.faq-item { border-radius: 18px; background: var(--surface); border: 1px solid var(--surface-border); margin-bottom: 12px; overflow: hidden; transition: box-shadow .2s, border-color .2s, background .2s; }
.faq-q { width: 100%; background: none; border: 0; color: var(--text); text-align: left; padding: 18px 22px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: color .15s; -webkit-tap-highlight-color: transparent; }
.faq-q i { color: var(--primary); transition: transform .14s ease-out; }
.faq-item.open .faq-q i { transform: rotate(45deg); }
.faq-item:hover { border-color: color-mix(in srgb, var(--primary) 45%, transparent); }
.faq-item.open { box-shadow: 0 10px 30px -14px color-mix(in srgb, var(--primary) 55%, transparent); }
.faq-a { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .14s ease-out; color: var(--muted); }
.faq-a > div { overflow: hidden; min-height: 0; padding: 0 22px; opacity: 0; transition: opacity .1s ease-out, padding .14s ease-out; }
.faq-item.open .faq-a { grid-template-rows: 1fr; }
.faq-item.open .faq-a > div { padding-bottom: 18px; opacity: 1; }

/* ---------- REVEAL + LAINNYA ---------- */
.reveal { opacity: 0; transform: translateY(40px); transition: opacity .35s ease, transform .35s cubic-bezier(.2, .7, .2, 1); will-change: opacity, transform; }
.reveal.show { opacity: 1; transform: none; will-change: auto; }
.d1 { transition-delay: .1s; } .d2 { transition-delay: .2s; } .d3 { transition-delay: .3s; } .d4 { transition-delay: .4s; }

.cta-banner { padding: 50px 30px; text-align: center; background: var(--grad); color: #fff; border-radius: 32px; position: relative; overflow: hidden; box-shadow: var(--shadow); }
.cta-banner::before { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(255,255,255,.15); border-radius: 50%; top: -120px; right: -80px; }
.cta-banner .btn-light { border-radius: 100px; font-weight: 700; padding: 12px 30px; transition: .3s; }
.cta-banner .btn-light:hover { transform: scale(1.06); }

.to-top { position: fixed; bottom: 24px; right: 24px; width: 48px; height: 48px; border-radius: 50%; border: 0; background: var(--grad); color: #fff; box-shadow: var(--shadow); opacity: 0; pointer-events: none; transform: translateY(20px); transition: .3s; z-index: 1500; }
.to-top.show { opacity: 1; pointer-events: auto; transform: none; }
.toast-mini { position: fixed; bottom: 24px; left: 50%; transform: translate(-50%, 120px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 100px; font-weight: 600; transition: .4s; z-index: 3000; }
.toast-mini.show { transform: translate(-50%, 0); }

@media (max-width: 768px) {
    .nav-modern { border-radius: 24px; }
}

/* ---------- ANIMASI MASUK (ringan) ---------- */
html { --b: .1s; }
.in-el { animation: inUp .95s cubic-bezier(.2, .8, .2, 1) calc(var(--b) + var(--d, 0s)) backwards; }
@keyframes inUp { from { opacity: 0; transform: translateY(14px); } }
.in-logo { animation: inLogo 1.1s cubic-bezier(.2, .8, .2, 1) calc(var(--b) + var(--d, 0s)) backwards; }
@keyframes inLogo { from { opacity: 0; transform: scale(.94); } }
.in-nav { animation: inNav .9s cubic-bezier(.2, .8, .2, 1) var(--b) backwards; }
@keyframes inNav { from { opacity: 0; transform: translateY(-12px); } }
.hero h1 .ln { display: block; overflow: hidden; padding: .06em 0 .12em; margin-bottom: -.1em; }
.hero h1 .ln > span.rise { display: inline-block; animation: lineRise 1s cubic-bezier(.2, .85, .2, 1) calc(var(--b) + var(--d, .3s)) backwards; }
@keyframes lineRise { from { transform: translateY(10px); opacity: 0; } }
.nav-links a { animation: inUp .7s cubic-bezier(.2,.8,.2,1) backwards; }
.nav-links a:nth-child(1) { animation-delay: calc(var(--b) + .25s); }
.nav-links a:nth-child(2) { animation-delay: calc(var(--b) + .33s); }
.nav-links a:nth-child(3) { animation-delay: calc(var(--b) + .41s); }
.nav-right > * { animation: inUp .7s cubic-bezier(.2,.8,.2,1) calc(var(--b) + .3s) backwards; }

html { overscroll-behavior-y: none; }
body { -webkit-font-smoothing: antialiased; }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
    .reveal { opacity: 1 !important; transform: none !important; }
}
</style>

<style>
/* =======================================================
   TAMBAHAN LATAR: profesional, bersih & ringan
   - garis kontur halus (statis), cahaya menyapu pelan,
     bola cahaya lembut, titik jaringan tipis
   - hanya transform & opacity, tanpa blur / filter berat
   ======================================================= */
.bg-fade { opacity: .35; }

/* 1. Garis kontur topografi: memudar di tengah agar konten tetap bersih */
.contour { position: fixed; inset: -4%; z-index: -3; pointer-events: none; opacity: .9;
    -webkit-mask-image: radial-gradient(ellipse 62% 55% at 50% 42%, transparent 18%, #000 92%);
            mask-image: radial-gradient(ellipse 62% 55% at 50% 42%, transparent 18%, #000 92%);
    animation: contourDrift 70s ease-in-out infinite alternate; will-change: transform; }
.contour svg { width: 100%; height: 100%; display: block; }
.contour path { fill: none; stroke-width: 1.3; stroke-linecap: round; stroke-linejoin: round; }
.contour .c-a path { stroke: rgba(99, 102, 241, .20); }
.contour .c-b path { stroke: rgba(6, 182, 212, .20); }
[data-theme="dark"] .contour .c-a path { stroke: rgba(165, 180, 252, .16); }
[data-theme="dark"] .contour .c-b path { stroke: rgba(125, 211, 252, .15); }
@keyframes contourDrift { to { transform: translate3d(-14px, 12px, 0); } }

/* 2. Bola cahaya lembut */
.orb { position: fixed; border-radius: 50%; z-index: -3; pointer-events: none; will-change: transform; }
.orb.o1 { width: 46vmax; height: 46vmax; left: -16vmax; top: 18vh; background: radial-gradient(circle, rgba(99, 102, 241, .15), transparent 65%); animation: orbA 30s ease-in-out infinite alternate; }
.orb.o2 { width: 42vmax; height: 42vmax; right: -14vmax; top: -12vmax; background: radial-gradient(circle, rgba(6, 182, 212, .17), transparent 65%); animation: orbB 34s ease-in-out infinite alternate; }
[data-theme="dark"] .orb.o1 { background: radial-gradient(circle, rgba(99, 102, 241, .25), transparent 65%); }
[data-theme="dark"] .orb.o2 { background: radial-gradient(circle, rgba(6, 182, 212, .22), transparent 65%); }
@keyframes orbA { to { transform: translate3d(7vw, 8vh, 0) scale(1.08); } }
@keyframes orbB { to { transform: translate3d(-7vw, 10vh, 0) scale(1.07); } }

/* 3. Sapuan cahaya diagonal yang sangat halus */
.sweep { position: fixed; top: -20%; left: -40%; width: 38vw; height: 140%; z-index: -2; pointer-events: none; will-change: transform;
    background: linear-gradient(100deg, transparent, rgba(99, 102, 241, .07), rgba(6, 182, 212, .06), transparent);
    transform: translateX(0) skewX(-18deg); animation: sweepX 22s ease-in-out infinite; }
[data-theme="dark"] .sweep { background: linear-gradient(100deg, transparent, rgba(165, 180, 252, .06), rgba(125, 211, 252, .05), transparent); }
@keyframes sweepX { 0% { transform: translateX(0) skewX(-18deg); } 55%, 100% { transform: translateX(420%) skewX(-18deg); } }

/* 4. Titik jaringan tipis (canvas ringan) */
#netCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: -2; pointer-events: none; }

@media (max-width: 600px) { .orb.o2, .sweep { display: none; } .contour { opacity: .7; } }
@media print { .contour, .orb, .sweep, #netCanvas { display: none !important; } }
@media (prefers-reduced-motion: reduce) { .contour, .orb, .sweep { animation: none !important; } }
</style>

<style>
/* =======================================================
   POLES TAMPILAN: lebih rapi, profesional & tajam
   (terang maupun gelap; tanpa blur / filter berat)
   ======================================================= */
:root {
    --card: rgba(255, 255, 255, .88);
    --card-border: rgba(99, 102, 241, .14);
    --card-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 14px 34px -14px rgba(49, 46, 129, .20);
    --card-shadow-hover: 0 2px 4px rgba(15, 23, 42, .05), 0 30px 60px -20px rgba(79, 70, 229, .34);
    --chip: rgba(99, 102, 241, .08);
    --chip-warm: rgba(244, 114, 182, .12);
}
[data-theme="dark"] {
    --card: rgba(255, 255, 255, .055);
    --card-border: rgba(255, 255, 255, .10);
    --card-shadow: 0 1px 0 rgba(255, 255, 255, .06) inset, 0 18px 44px -18px rgba(0, 0, 0, .75);
    --card-shadow-hover: 0 1px 0 rgba(255, 255, 255, .10) inset, 0 30px 64px -20px rgba(0, 0, 0, .9), 0 0 0 1px rgba(129, 140, 248, .30);
    --chip: rgba(129, 140, 248, .14);
    --chip-warm: rgba(244, 114, 182, .16);
}
html { scrollbar-width: thin; scrollbar-color: rgba(99, 102, 241, .5) transparent; }
::selection { background: rgba(99, 102, 241, .28); }

/* Navbar */
.nav-modern { background: var(--card); border: 1px solid var(--card-border); box-shadow: var(--card-shadow); }
.nav-links a { position: relative; }
.nav-links a:hover { background: transparent; color: var(--text); }
.nav-links a::after { content: ''; position: absolute; left: 50%; bottom: 3px; width: 0; height: 2px; border-radius: 2px; background: var(--grad); transition: width .25s ease, left .25s ease; }
.nav-links a:hover::after { width: calc(100% - 28px); left: 14px; }
.icon-btn { border-color: var(--card-border); background: var(--card); }

/* Judul bagian: garis aksen tipis */
.section-title { position: relative; padding-bottom: 16px; margin-bottom: 8px; }
.section-title::after { content: ''; position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); width: 54px; height: 4px; border-radius: 4px; background: var(--grad); }

/* Kartu umum */
.glass-card { background: var(--card); border: 1px solid var(--card-border); box-shadow: var(--card-shadow); transition: transform .22s cubic-bezier(.2, .8, .2, 1), box-shadow .22s ease, border-color .22s ease; }
.glass-card:hover { transform: translateY(-6px); box-shadow: var(--card-shadow-hover); border-color: transparent; }

/* Kartu layanan */
.menu-card::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 4px; background: var(--grad); }
.menu-card.warm::before { background: var(--grad-warm); }
.menu-card .icon-box { border-radius: 26px; box-shadow: 0 18px 34px -12px var(--primary), inset 0 1px 0 rgba(255, 255, 255, .38); }
.menu-card.warm .icon-box { box-shadow: 0 18px 34px -12px #f472b6, inset 0 1px 0 rgba(255, 255, 255, .38); }
.menu-card h3 { letter-spacing: -.4px; }
.feature-tags span { background: transparent; border: 1px solid var(--card-border); color: var(--muted); }
.menu-card .cta { padding: 10px 20px; border-radius: 100px; background: var(--chip); transition: background .2s ease; }
.menu-card.warm .cta { background: var(--chip-warm); }
.menu-card:hover .cta { background: rgba(99, 102, 241, .18); }
.menu-card.warm:hover .cta { background: rgba(244, 114, 182, .24); }

/* Kartu langkah: angka latar (01, 02, dll) DIHILANGKAN SESUAI PERMINTAAN */
.step-num { box-shadow: 0 10px 22px -8px var(--primary), 0 0 0 6px rgba(99, 102, 241, .10); }

/* FAQ */
.faq-item { background: var(--card); border: 1px solid var(--card-border); box-shadow: var(--card-shadow); position: relative; }
.faq-item::before { content: ''; position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px; border-radius: 3px; background: var(--grad); opacity: 0; transition: opacity .2s ease; }
.faq-item.open::before { opacity: 1; }
.faq-q i { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 50%; background: var(--chip); font-size: .85rem; flex: none; }
.faq-item.open .faq-q i { background: var(--primary); color: #fff; }

/* Banner ajakan */
.cta-banner { background: radial-gradient(120% 140% at 0% 0%, rgba(255, 255, 255, .20), transparent 52%), var(--grad); box-shadow: 0 30px 64px -26px rgba(79, 70, 229, .65); }
.cta-banner::after { content: ''; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background: repeating-linear-gradient(135deg, rgba(255, 255, 255, .07) 0 2px, transparent 2px 18px);
    -webkit-mask-image: linear-gradient(90deg, transparent 10%, #000 90%); mask-image: linear-gradient(90deg, transparent 10%, #000 90%); }
.cta-banner .btn-light { color: #4338ca; font-weight: 800; box-shadow: 0 14px 28px -12px rgba(0, 0, 0, .4); }
</style>

<style>
/* =======================================================
   TAMPILAN HERO "MONIKA": nama aplikasi besar & menarik
   (hanya transform / opacity; ringan)
   ======================================================= */
.hero h1.monika-title { min-height: 0; margin: 4px 0 6px; letter-spacing: 0; line-height: 1.05; }
.monika-title .brand-big {
    display: inline-block; font-weight: 800; font-size: clamp(3.4rem, 13vw, 8rem); letter-spacing: .07em; padding-left: .07em; line-height: 1.02;
    background-image: linear-gradient(100deg, #4f46e5 0%, #06b6d4 38%, #10b981 62%, #4f46e5 100%); background-size: 220% 100%;
    filter: drop-shadow(0 14px 22px rgba(79, 70, 229, .22));
}
[data-theme="dark"] .monika-title .brand-big { background-image: linear-gradient(100deg, #a5b4fc 0%, #67e8f9 38%, #6ee7b7 62%, #a5b4fc 100%); filter: drop-shadow(0 10px 24px rgba(99, 102, 241, .45)); }
.monika-title .sub-big { display: inline; font-weight: 700; font-size: clamp(1.05rem, 3.1vw, 1.9rem); letter-spacing: -.2px; color: var(--text); }
.monika-title .typed-cursor { height: .8em; }
.monika-bar { width: 96px; height: 5px; border-radius: 6px; margin: 18px auto 30px; background: var(--grad); box-shadow: 0 6px 16px -4px var(--primary); }
@media (max-width: 576px) { .monika-title .brand-big { letter-spacing: .04em; } }
</style>

<!-- Latar -->
<div class="scroll-progress" id="scrollProgress"></div>
<div class="bg-aurora"></div>
<div class="bg-waves" aria-hidden="true">
    <svg class="w2" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg2" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".16"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".16"/><stop offset="1" stop-color="#34d399" stop-opacity=".14"/></linearGradient></defs>
        <path fill="url(#wg2)" d="M0 110 C 200 60 400 160 600 110 S 1000 60 1200 110 S 1600 160 1800 110 S 2200 60 2400 110 V200 H0Z"/></svg>
    <svg class="w1" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg1" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".22"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".2"/><stop offset="1" stop-color="#f472b6" stop-opacity=".16"/></linearGradient></defs>
        <path fill="url(#wg1)" d="M0 140 C 150 100 350 190 600 140 S 1050 90 1200 140 S 1550 190 1800 140 S 2250 90 2400 140 V200 H0Z"/></svg>
</div>
<div class="bg-fade"></div>

<!-- Aksen latar tambahan -->
<div class="orb o1"></div><div class="orb o2"></div>
<div class="contour" id="contour" aria-hidden="true"></div>
<div class="sweep" aria-hidden="true"></div>
<canvas id="netCanvas" aria-hidden="true"></canvas>

<!-- NAVBAR -->
<nav class="nav-modern in-nav">
    <a href="index.php" class="brand">
        <span>MONIKA <span class="text-grad">e-ICV</span></span>
    </a>
    <div class="nav-links d-none d-md-flex">
        <a href="#layanan">Layanan</a>
        <a href="#alur">Alur</a>
        <a href="#faq">FAQ</a>
    </div>
    <div class="nav-right">
        <button class="icon-btn" id="themeToggle" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i></button>
        <a href="admin/login.php" class="btn btn-grad d-none d-sm-inline-block">
            <i class="bi bi-person-lock me-1"></i> Admin
        </a>
        <a href="admin/login.php" class="icon-btn d-sm-none" aria-label="Admin"><i class="bi bi-person-lock"></i></a>
    </div>
</nav>

<main class="container pb-5">

    <!-- HERO -->
    <section class="hero">
        <div class="hero-logos">
            <figure class="in-logo" style="--d:.1s">
                <span class="logo-chip"><img src="<?php echo htmlspecialchars($LOGO_1); ?>" alt="Logo <?php echo htmlspecialchars($LOGO_1_NAMA); ?>" onerror="this.onerror=null;console.warn('Logo 1 tidak ditemukan: '+this.getAttribute('src'));this.src='assets/images/logo1.svg';"></span>
            </figure>
            <figure class="in-logo" style="--d:.25s">
                <span class="logo-chip"><img src="<?php echo htmlspecialchars($LOGO_2); ?>" alt="Logo <?php echo htmlspecialchars($LOGO_2_NAMA); ?>" onerror="this.onerror=null;console.warn('Logo 2 tidak ditemukan: '+this.getAttribute('src'));this.src='assets/images/logo2.svg';"></span>
            </figure>
        </div>

        <h1 class="monika-title">
            <span class="ln"><span class="rise" style="--d:.35s"><span class="text-grad brand-big">MONIKA</span></span></span>
            <span class="ln"><span class="rise" style="--d:.5s"><span class="sub-big" id="typed">Monitoring dan Informasi Kuota e-ICV</span><span class="typed-cursor"></span></span></span>
        </h1>
        <div class="monika-bar in-el" style="--d:.6s" aria-hidden="true"></div>
    </section>

    <!-- LAYANAN -->
    <section id="layanan" class="pt-4 pb-5">
        <div class="text-center mb-5 reveal">
            <h2 class="section-title">Pilih <span class="text-grad">Layanan</span> Anda</h2>
            <p class="text-muted">Dua langkah mudah untuk mengurus dokumen Anda.</p>
        </div>
        <div class="row justify-content-center g-4 g-lg-5">
            <div class="col-md-6 col-lg-5 reveal d1">
                <a href="ajukan_permohonan.php" class="text-decoration-none">
                    <div class="glass-card menu-card p-5 text-center h-100" style="color: var(--text);">
                        <div class="icon-box"><i class="bi bi-envelope-paper-heart"></i></div>
                        <h3 class="fw-bold mb-3">Ajukan Permohonan</h3>
                        <p class="text-muted">Isi formulir dengan data lengkap dan dapatkan kode tracking Anda.</p>
                        <div class="feature-tags d-flex flex-wrap justify-content-center gap-2 mb-4">
                            <span>Formulir digital</span><span>Tanpa antre</span><span>Kode tracking</span>
                        </div>
                        <span class="cta">Mulai Mengajukan <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-5 reveal d2">
                <a href="tracking.php" class="text-decoration-none">
                    <div class="glass-card menu-card warm p-5 text-center h-100" style="color: var(--text);">
                        <div class="icon-box"><i class="bi bi-search-heart"></i></div>
                        <h3 class="fw-bold mb-3">Lacak Dokumen</h3>
                        <p class="text-muted">Pantau proses dan unduh file persetujuan menggunakan kode tracking Anda.</p>
                        <div class="feature-tags d-flex flex-wrap justify-content-center gap-2 mb-4">
                            <span>Real-time</span><span>Unduh file</span><span>Riwayat status</span>
                        </div>
                        <span class="cta">Cek Status <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- ALUR -->
    <section id="alur" class="py-5">
        <div class="text-center mb-5 reveal">
            <h2 class="section-title">Bagaimana <span class="text-grad">Cara Kerjanya?</span></h2>
            <p class="text-muted">Empat langkah sederhana dari pengajuan hingga dokumen siap.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3 reveal"><div class="glass-card step-card"><div class="step-num">1</div><h5>Isi Formulir</h5><p>Lengkapi data pemohon dan unggah dokumen pendukung.</p></div></div>
            <div class="col-md-6 col-lg-3 reveal d1"><div class="glass-card step-card"><div class="step-num">2</div><h5>Terima Kode</h5><p>Simpan kode tracking yang diberikan sistem.</p></div></div>
            <div class="col-md-6 col-lg-3 reveal d2"><div class="glass-card step-card"><div class="step-num">3</div><h5>Diverifikasi</h5><p>Tim admin memeriksa dan memproses permohonan Anda.</p></div></div>
            <div class="col-md-6 col-lg-3 reveal d3"><div class="glass-card step-card"><div class="step-num">4</div><h5>Unduh Hasil</h5><p>Pantau status dan unduh file persetujuan dari halaman tracking.</p></div></div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5">
        <div class="text-center mb-5 reveal">
            <h2 class="section-title">Pertanyaan <span class="text-grad">Umum</span></h2>
        </div>
        <div class="mx-auto reveal" style="max-width: 760px;">
            <div class="faq-item">
                <button class="faq-q">Di mana saya mendapatkan kode tracking? <i class="bi bi-plus-lg"></i></button>
                <div class="faq-a"><div>kode tracking ditampilkan otomatis setelah Anda berhasil mengirim formulir permohonan. Simpan atau salin kodenya.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-q">Berapa lama proses permohonan? <i class="bi bi-plus-lg"></i></button>
                <div class="faq-a"><div>Lama proses tergantung kelengkapan dokumen. Anda dapat memantau perkembangannya kapan saja lewat halaman Lacak Dokumen.</div></div>
            </div>
            <div class="faq-item">
                <button class="faq-q">Di mana saya mengunduh file persetujuan? <i class="bi bi-plus-lg"></i></button>
                <div class="faq-a"><div>Setelah permohonan disetujui, tombol unduh akan muncul pada halaman tracking menggunakan kode tracking Anda.</div></div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-4 reveal">
        <div class="cta-banner">
            <h2 class="fw-bold mb-2 position-relative">Siap mengajukan permohonan?</h2>
            <p class="mb-4 position-relative" style="opacity:.9">Mulai sekarang, hanya butuh beberapa menit.</p>
            <a href="ajukan_permohonan.php" class="btn btn-light position-relative">Ajukan Permohonan <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </section>

</main>

<button class="to-top" id="toTop" aria-label="Kembali ke atas"><i class="bi bi-arrow-up"></i></button>
<div class="toast-mini" id="toast"></div>

<script>
(function () {
    /* Selalu mulai dari paling atas saat masuk / refresh */
    try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) {}
    if (location.hash) { try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {} }
    document.documentElement.style.scrollBehavior = 'auto';
    window.scrollTo(0, 0);
    addEventListener('load', () => { window.scrollTo(0, 0); setTimeout(() => { document.documentElement.style.scrollBehavior = ''; }, 50); });
    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => [...c.querySelectorAll(s)];

    /* Tema terang/gelap (tersimpan) */
    const root = document.documentElement, themeBtn = $('#themeToggle');
    const setTheme = t => {
        root.setAttribute('data-theme', t);
        themeBtn.innerHTML = t === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
        try { localStorage.setItem('eicv-theme', t); } catch (e) {}
    };
    let saved = null; try { saved = localStorage.getItem('eicv-theme'); } catch (e) {}
    setTheme(saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    themeBtn.addEventListener('click', () => { setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); toast('Tema diganti'); });

    /* Toast */
    let tt; function toast(msg) { const t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 2200); }

    /* Efek mengetik */
    const words = ['Monitoring dan Informasi Kuota e-ICV'];
    let w = 0, c = 0, del = false; const typed = $('#typed');
    (function type() {
        if (!typed) return;
        const word = words[w];
        typed.textContent = word.slice(0, c);
        if (!del && c < word.length) { c++; setTimeout(type, 90); }
        else if (!del) { del = true; setTimeout(type, 1500); }
        else if (c > 0) { c--; setTimeout(type, 45); }
        else { del = false; w = (w + 1) % words.length; setTimeout(type, 300); }
    })();

    /* Muncul saat di-scroll */
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('show'); io.unobserve(e.target); } }), { threshold: .12 });
    /* Bagian Layanan langsung tampil saat dibuka / di-refresh (tidak menunggu di-scroll) */
    $$('#layanan .reveal').forEach(el => el.classList.add('show'));
    $$('.reveal:not(.show)').forEach(el => io.observe(el));

    /* Progress scroll + tombol ke atas */
    const bar = $('#scrollProgress'), topBtn = $('#toTop');
    addEventListener('scroll', () => {
        const h = document.documentElement;
        const p = h.scrollTop / (h.scrollHeight - h.clientHeight);
        if (bar) bar.style.width = (p * 100) + '%';
        if (topBtn) topBtn.classList.toggle('show', h.scrollTop > 500);
    }, { passive: true });
    if (topBtn) topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    /* FAQ */
    $$('.faq-item').forEach(item => {
        const q = $('.faq-q', item);
        if (!q) return;
        q.addEventListener('click', () => {
            const open = item.classList.contains('open');
            $$('.faq-item.open').forEach(i => i.classList.remove('open'));
            if (!open) item.classList.add('open');
        });
    });
})();
</script>

<script>
/* Garis kontur topografi: dibuat sekali (statis), lalu hanya bergeser sangat pelan lewat CSS */
(function () {
    const el = document.getElementById('contour'); if (!el) return;
    function line(i) {
        const y0 = 40 + i * 58; let d = '';
        for (let x = 0; x <= 1620; x += 20) {
            const y = y0 + 26 * Math.sin(x / 260 + i * .35) + 18 * Math.sin(x / 130 - i * .5) + 12 * Math.cos(x / 410 + i * .2);
            d += (x ? ' L' : 'M') + x + ',' + Math.round(y);
        }
        return '<path d="' + d + '"/>';
    }
    let a = '', b = '';
    for (let i = 0; i < 9; i++) a += line(i);
    for (let i = 9; i < 18; i++) b += line(i);
    el.innerHTML = '<svg viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid slice"><g class="c-a">' + a + '</g><g class="c-b">' + b + '</g></svg>';
})();
</script>

<script>
/* Titik jaringan tipis: ringan (maks 22 titik, 24 fps, berhenti saat tab tersembunyi / mode kurangi gerakan) */
(function () {
    const cv = document.getElementById('netCanvas');
    if (!cv || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = cv.getContext('2d'), root = document.documentElement;
    let W, H, pts = [], mx = -999, my = -999, raf, last = 0;
    function size() {
        W = innerWidth; H = innerHeight; cv.width = W; cv.height = H;
        const n = Math.min(22, Math.floor(W * H / 42000));
        pts = Array.from({ length: n }, () => ({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25, r: Math.random() * 1.2 + .8 }));
    }
    let rt; addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(size, 150); });
    addEventListener('mousemove', e => { mx = e.clientX; my = e.clientY; }, { passive: true });
    size();
    function draw() {
        const dark = root.getAttribute('data-theme') === 'dark';
        const dot = dark ? '165,180,252' : '79,70,229', line = dark ? '125,211,252' : '99,102,241';
        ctx.clearRect(0, 0, W, H);
        for (let i = 0; i < pts.length; i++) {
            const p = pts[i]; p.x += p.vx; p.y += p.vy;
            if (p.x < 0 || p.x > W) p.vx *= -1;
            if (p.y < 0 || p.y > H) p.vy *= -1;
            const dx = mx - p.x, dy = my - p.y, d = Math.hypot(dx, dy);
            if (d < 130 && d > 1) { p.x -= dx / d * .3; p.y -= dy / d * .3; }
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = 'rgba(' + dot + ',' + (dark ? .5 : .34) + ')'; ctx.fill();
            for (let j = i + 1; j < pts.length; j++) {
                const q = pts[j], dist = Math.hypot(p.x - q.x, p.y - q.y);
                if (dist < 140) {
                    ctx.strokeStyle = 'rgba(' + line + ',' + ((dark ? .20 : .14) * (1 - dist / 140)) + ')'; ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                }
            }
        }
    }
    function loop(t) { raf = requestAnimationFrame(loop); if (t - last < 41) return; last = t; draw(); }
    raf = requestAnimationFrame(loop);
    document.addEventListener('visibilitychange', () => { cancelAnimationFrame(raf); if (!document.hidden) raf = requestAnimationFrame(loop); });
})();
</script>

<?php include 'includes/footer.php'; ?>