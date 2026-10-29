<?php
// tracking.php
require_once 'config/database.php';
include 'includes/header.php'; 

$data_permohonan = null;
$error_msg = "";
$sudah_cari = false;

// Menerima kode dari form tracking (kode_tracking) maupun kolom lacak cepat di beranda (tiket)
$kode_input = '';
if (isset($_GET['kode_tracking'])) {
    $kode_input = trim($_GET['kode_tracking']);
} elseif (isset($_GET['tiket'])) {
    $kode_input = trim($_GET['tiket']);
}

if ($kode_input !== '') {
    $sudah_cari = true;
    // Prepared statement (aman dari SQL injection)
    $stmt = mysqli_prepare($conn, "SELECT * FROM permohonan WHERE kode_tracking = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $kode_input);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($row) {
        $data_permohonan = $row;
    } else {
        $error_msg = "Maaf, Kode Tracking tidak ditemukan. Periksa kembali penulisannya.";
    }
    mysqli_stmt_close($stmt);
}

// Helper tampilan
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tgl($v) {
    if (!$v || $v === '0000-00-00') return '-';
    $t = strtotime($v);
    return $t ? date('d M Y', $t) : e($v);
}

// Tentukan tahap: 0 = Menunggu, 1 = Diproses, 2 = Selesai, 3 = Ditolak
$tahap = 0; $status = '';
if ($data_permohonan) {
    $status = trim((string)$data_permohonan['status_permohonan']);
    $lower_status = strtolower($status);
    
    if ($lower_status === 'menunggu') { $tahap = 0; $status = 'Menunggu'; }
    elseif ($lower_status === 'diproses') { $tahap = 1; $status = 'Diproses'; }
    elseif ($lower_status === 'selesai') { $tahap = 2; $status = 'Selesai'; }
    elseif ($lower_status === 'ditolak') { $tahap = 3; $status = 'Ditolak'; }
    else { $tahap = 0; $status = 'Menunggu'; }
}
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
    --surface: rgba(255,255,255,.7); --surface-solid: #ffffff; --surface-border: rgba(255,255,255,.85);
    --field: rgba(255,255,255,.9); --field-border: #dbe2f5;
    --text: #14213d; --muted: #5f6b85;
    --primary: #6366f1; --ok: #10b981; --warn: #f59e0b; --danger: #ef4444; --info: #06b6d4;
    --grad: linear-gradient(135deg,#6366f1 0%,#06b6d4 55%,#34d399 100%);
    --grad-warm: linear-gradient(135deg,#f472b6 0%,#fb923c 100%);
    --shadow: 0 20px 50px -20px rgba(79,70,229,.35);
    --radius: 26px;
}
[data-theme="dark"] {
    --bg: #0b1020; --bg-2: #111936;
    --surface: rgba(255,255,255,.06); --surface-solid: #151c3a; --surface-border: rgba(255,255,255,.12);
    --field: rgba(255,255,255,.05); --field-border: rgba(255,255,255,.14);
    --text: #eef2ff; --muted: #9aa6c7; --shadow: 0 20px 60px -20px rgba(0,0,0,.7);
}
html { scroll-behavior: smooth; }
body { background: var(--bg) !important; color: var(--text); transition: background .4s, color .4s; overflow-x: hidden; display: flex; flex-direction: column; min-height: 100vh; }

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
.scroll-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--grad); z-index: 2000; }
#confetti { position: fixed; inset: 0; pointer-events: none; z-index: 2500; }

.bg-waves { position: fixed; left: 0; right: 0; bottom: 0; height: 30vh; z-index: -2; pointer-events: none; overflow: hidden; contain: strict; }
.bg-waves svg { position: absolute; left: 0; bottom: 0; width: 200%; height: 100%; will-change: transform; }
.bg-waves .w1 { animation: waveX 26s linear infinite; }
.bg-waves .w2 { animation: waveX 38s linear infinite reverse; opacity: .8; }
@keyframes waveX { to { transform: translateX(-50%); } }

/* =========================================================
   UI TRACKING
   ========================================================= */
.topbar { max-width: 860px; width: 100%; margin: 18px auto 0; padding: 0 12px; display: flex; justify-content: space-between; align-items: center; }
.back-link { color: var(--muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px 8px 8px; border-radius: 50px; transition: .25s; }
.back-link:hover { color: var(--text); background: var(--surface); transform: translateX(-4px); }
.icon-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); display: grid; place-items: center; cursor: pointer; transition: .3s; backdrop-filter: blur(10px); }
.icon-btn:hover { transform: rotate(20deg) scale(1.08); }

.wrap { max-width: 860px; margin: 0 auto 60px; padding: 0 12px; width: 100%; flex-grow: 1; }
.glass-card { background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: var(--shadow); }
.text-grad { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }

/* Hero */
.t-hero { text-align: center; padding: 30px 10px 10px; }
.head-icon { width: 78px; height: 78px; border-radius: 24px; background: var(--grad-warm); color: #fff; font-size: 2rem; display: grid; place-items: center; margin: 0 auto 16px; box-shadow: 0 18px 35px -10px #f472b6; animation: float 4s ease-in-out infinite; }
@keyframes float { 50% { transform: translateY(-8px); } }
.t-hero h2 { font-weight: 800; letter-spacing: -.6px; font-size: clamp(1.8rem,5vw,2.6rem); }

/* Search */
.search-bar { max-width: 640px; margin: 26px auto 0; padding: 8px; border-radius: 100px; display: flex; gap: 8px; align-items: center; transition: .3s; }
.search-bar:focus-within { box-shadow: 0 0 0 4px rgba(99,102,241,.25), var(--shadow); }
.search-bar .lead-ic { padding-left: 18px; color: var(--primary); font-size: 1.2rem; }
.search-bar input { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; color: var(--text); padding: 12px 6px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; }
.search-bar input::placeholder { color: var(--muted); font-weight: 500; letter-spacing: 0; text-transform: none; }
.btn-grad { background: var(--grad); color: #fff !important; border: 0; font-weight: 700; border-radius: 100px; padding: 12px 26px; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s; box-shadow: 0 10px 25px -8px var(--primary); }
.btn-grad:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -8px var(--primary); }
.btn-grad::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg,transparent,rgba(255,255,255,.5),transparent); transform: skewX(-20deg); transition: .6s; }
.btn-grad:hover::after { left: 140%; }

.recent { max-width: 640px; margin: 14px auto 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; font-size: .8rem; color: var(--muted); }
.recent button.chip { border: 1px solid var(--field-border); background: var(--surface); color: var(--text); padding: 5px 14px; border-radius: 50px; font-weight: 700; font-size: .78rem; transition: .25s; }
.recent button.chip:hover { background: var(--primary); color: #fff; transform: translateY(-2px); }
.recent .clr { background: none; border: 0; color: var(--muted); text-decoration: underline; font-size: .75rem; }

.alert-modern { max-width: 640px; margin: 18px auto 0; padding: 16px 22px; border-radius: 20px; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.35); color: var(--danger); font-weight: 600; display: flex; gap: 12px; align-items: center; animation: shake .45s; }
@keyframes shake { 20%,60% { transform: translateX(-6px); } 40%,80% { transform: translateX(6px); } }

/* Result */
.result { margin-top: 36px; animation: rise .7s cubic-bezier(.2,.7,.2,1); }
@keyframes rise { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: none; } }
.res-head { padding: 26px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-bottom: 1px solid var(--field-border); }
.code-pill { display: inline-flex; align-items: center; gap: 10px; background: var(--field); border: 1.5px dashed var(--primary); color: var(--primary); font-weight: 800; letter-spacing: 1px; padding: 8px 8px 8px 18px; border-radius: 100px; }
.code-pill button { border: 0; background: var(--primary); color: #fff; width: 32px; height: 32px; border-radius: 50%; transition: .25s; }
.code-pill button:hover { transform: scale(1.12); }

.status-banner { margin: 26px 30px 0; padding: 22px 24px; border-radius: 22px; display: flex; align-items: center; gap: 18px; color: #fff; position: relative; overflow: hidden; }
.status-banner::before { content: ''; position: absolute; width: 220px; height: 220px; border-radius: 50%; background: rgba(255,255,255,.14); top: -90px; right: -60px; }
.status-banner.s0 { background: linear-gradient(135deg,#f59e0b,#fb923c); }
.status-banner.s1 { background: linear-gradient(135deg,#6366f1,#06b6d4); }
.status-banner.s2 { background: linear-gradient(135deg,#10b981,#34d399); }
.status-banner.sx { background: linear-gradient(135deg,#ef4444,#dc2626); }
.status-banner .big-ic { width: 62px; height: 62px; border-radius: 20px; background: rgba(255,255,255,.22); display: grid; place-items: center; font-size: 1.9rem; flex-shrink: 0; }
.status-banner h4 { margin: 0; font-weight: 800; }
.status-banner p { margin: 2px 0 0; opacity: .92; font-size: .92rem; }
.spin { display: inline-block; animation: spin 2.4s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.pulse { animation: pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 50% { transform: scale(1.12); } }

/* Timeline */
.timeline { display: flex; padding: 34px 30px 24px; position: relative; }
.timeline::before { content: ''; position: absolute; top: 56px; left: calc(30px + 16.6%); right: calc(30px + 16.6%); height: 4px; background: var(--field-border); border-radius: 4px; }
.timeline .fill { position: absolute; top: 56px; left: calc(30px + 16.6%); height: 4px; width: 0; background: var(--grad); border-radius: 4px; transition: width 1.2s cubic-bezier(.2,.7,.2,1) .3s; }
.tl-step { flex: 1; text-align: center; position: relative; z-index: 1; }
.tl-step .node { width: 48px; height: 48px; margin: 0 auto 10px; border-radius: 50%; display: grid; place-items: center; background: var(--surface-solid); border: 3px solid var(--field-border); color: var(--muted); font-size: 1.15rem; transition: .5s; }
.tl-step .t { font-weight: 800; font-size: .88rem; color: var(--muted); }
.tl-step .d { font-size: .75rem; color: var(--muted); }
.tl-step.done .node { background: var(--ok); border-color: transparent; color: #fff; }
.tl-step.done .t { color: var(--text); }
.tl-step.current .node { background: var(--grad); border-color: transparent; color: #fff; transform: scale(1.18); box-shadow: 0 0 0 8px rgba(99,102,241,.18), 0 12px 24px -8px var(--primary); animation: pulse 2s ease-in-out infinite; }
.tl-step.current .t { color: var(--primary); }

.timeline.tolak .fill { background: linear-gradient(90deg, var(--ok), var(--danger)); }
.tl-step.rejected .node { background: linear-gradient(135deg,#ef4444,#dc2626); border-color: transparent; color: #fff; transform: scale(1.18); box-shadow: 0 0 0 8px rgba(239,68,68,.18), 0 12px 24px -8px #ef4444; animation: pulse 2s ease-in-out infinite; }
.tl-step.rejected .t { color: var(--danger); }
.btn-ajukan-ulang { display: inline-flex; align-items: center; gap: 8px; margin-top: 16px; padding: 11px 24px; border-radius: 100px; font-weight: 700; text-decoration: none; color: #fff !important; background: var(--grad); box-shadow: 0 10px 22px -8px var(--primary); transition: .25s; }
.btn-ajukan-ulang:hover { transform: translateY(-3px); }
.decision { margin: 18px 30px 24px; padding: 24px; border-radius: 22px; background: var(--field); border: 2px dashed var(--ok); }
.decision.decision-reject { border-color: var(--danger); background: rgba(239,68,68,.05); border-style: solid; }
.decision h6 { font-weight: 800; color: var(--ok); display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.decision.decision-reject h6 { color: var(--danger); }
.big-num { font-size: 2rem; font-weight: 800; }
.btn-download { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; margin-top: 18px; padding: 16px; border-radius: 100px; background: linear-gradient(135deg,#10b981,#06b6d4); color: #fff !important; font-weight: 800; text-decoration: none; box-shadow: 0 14px 28px -10px #10b981; transition: .3s; }
.btn-download:hover { transform: translateY(-4px); box-shadow: 0 20px 34px -10px #10b981; }
.wait-box { margin-top: 18px; padding: 14px; border-radius: 16px; text-align: center; font-weight: 600; font-size: .9rem; background: rgba(245,158,11,.12); color: var(--warn); }

.toast-mini { position: fixed; bottom: 24px; left: 50%; transform: translate(-50%,120px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 100px; font-weight: 600; transition: .4s; z-index: 3000; max-width: 90vw; text-align: center; }
.toast-mini.show { transform: translate(-50%,0); }

@media (max-width: 576px) {
    .res-head, .decision { padding-left: 18px; padding-right: 18px; }
    .status-banner { margin-left: 18px; margin-right: 18px; }
    .decision { margin-left: 18px; margin-right: 18px; }
    .timeline { padding-left: 12px; padding-right: 12px; }
    .timeline::before, .timeline .fill { left: calc(12px + 16.6%); }
    .timeline::before { right: calc(12px + 16.6%); }
    .search-bar { border-radius: 24px; flex-wrap: wrap; }
    .search-bar .btn-grad { width: 100%; }
}
@media print {
    .bg-aurora, .topbar, .search-bar, .recent, .scroll-progress, #confetti { display: none !important; }
    body { background: #fff !important; color: #000; } .glass-card { box-shadow: none; backdrop-filter: none; border: 1px solid #ccc; }
}
@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }

/* =========================================================
   PERBAIKAN KETERBACAAN MODE GELAP
   ========================================================= */
.text-muted, .text-secondary, .text-body-secondary { color: var(--muted) !important; }
.text-dark, .text-black { color: var(--text) !important; }
.form-floating > label::after { background: transparent !important; }
kbd { background: var(--surface-solid); color: var(--text); border: 1px solid var(--field-border, var(--surface-border)); }
footer, footer p, footer small, footer span, footer a { color: var(--muted) !important; }
input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--text) !important; caret-color: var(--text);
    box-shadow: 0 0 0 1000px var(--surface-solid) inset !important; transition: background-color 9999s ease-out;
}
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

<canvas id="confetti"></canvas>

<div class="topbar">
    <a href="index.php" class="back-link"><i class="bi bi-arrow-left-short fs-4"></i> Kembali ke Beranda</a>
    <button class="icon-btn" id="themeToggle" type="button" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i></button>
</div>

<div class="wrap">

    <!-- Hero + pencarian -->
    <div class="t-hero">
        <div class="head-icon"><i class="bi bi-search-heart"></i></div>
        <h2>Lacak <span class="text-grad">Dokumen</span> Anda</h2>
        <p class="text-muted mb-0">Pantau proses permohonan secara real-time</p>

        <form action="tracking.php" method="GET" class="glass-card search-bar" id="searchForm">
            <i class="bi bi-ticket-perforated lead-ic"></i>
            <input type="text" name="kode_tracking" id="kodeInput" value="<?php echo e($kode_input); ?>" placeholder="Masukkan Kode: REQ-XXXXX" autocomplete="off" required>
            <button type="submit" class="btn btn-grad"><i class="bi bi-search me-1"></i> Lacak</button>
        </form>

        <div class="recent" id="recentBox" style="display:none;"></div>

        <?php if ($error_msg != ""): ?>
            <div class="alert-modern"><i class="bi bi-x-circle-fill fs-4"></i><div><?php echo e($error_msg); ?></div></div>
        <?php endif; ?>
    </div>

    <?php if ($data_permohonan != null): 
        $d = $data_permohonan;
        $kode_aman = e($d['kode_tracking']);
        $kls = $tahap === 0 ? 's0' : ($tahap === 1 ? 's1' : ($tahap === 2 ? 's2' : 'sx'));
        $fillPct = $tahap <= 0 ? 0 : ($tahap === 1 ? 50 : 100);
    ?>
    <div class="result" id="resultBox" data-status="<?php echo e($status); ?>" data-kode="<?php echo $kode_aman; ?>">
        <div class="glass-card">

            <div class="res-head">
                <div>
                    <small class="text-muted fw-semibold d-block mb-1">Nama Klinik</small>
                    <div class="fw-bold fs-5"><i class="bi bi-hospital text-primary me-1"></i> <?php echo e($d['nama_klinik']); ?></div>
                    <?php if (!empty($d['nama_kabupaten'])): ?><div class="text-muted small mt-1"><i class="bi bi-geo-alt-fill me-1"></i><?php echo e($d['nama_kabupaten']); ?></div><?php endif; ?>
                </div>
                <div class="code-pill">
                    <span id="kodeText"><?php echo $kode_aman; ?></span>
                    <button type="button" id="copyKode" aria-label="Salin kode"><i class="bi bi-clipboard"></i></button>
                </div>
            </div>

            <!-- DESAIN JIKA DITOLAK (Terpisah Total) -->
            <?php if ($tahap === 3): ?>
                <div class="status-banner sx">
                    <div class="big-ic"><i class="bi bi-x-octagon-fill pulse"></i></div>
                    <div class="position-relative">
                        <h4>Permohonan Ditolak</h4>
                        <p>Mohon maaf, permohonan e-ICV Anda tidak dapat diproses lebih lanjut.</p>
                    </div>
                </div>
                
                <!-- Timeline: berhenti di "Ditolak" -->
                <div class="timeline tolak" id="timeline" data-fill="100">
                    <div class="fill" id="tlFill"></div>
                    <div class="tl-step done">
                        <div class="node"><i class="bi bi-check-lg"></i></div>
                        <div class="t">Diterima</div><div class="d">Permohonan masuk</div>
                    </div>
                    <div class="tl-step done">
                        <div class="node"><i class="bi bi-check-lg"></i></div>
                        <div class="t">Diperiksa</div><div class="d">Verifikasi admin</div>
                    </div>
                    <div class="tl-step rejected">
                        <div class="node"><i class="bi bi-x-lg"></i></div>
                        <div class="t">Ditolak</div><div class="d"><?php echo tgl($d['tanggal_proses'] ?? ''); ?></div>
                    </div>
                </div>

                <div class="decision decision-reject mt-2">
                    <h6><i class="bi bi-exclamation-triangle-fill"></i> Alasan Penolakan</h6>
                    <div class="mb-3 mt-3">
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--danger); background: var(--surface-solid); padding: 18px; border-radius: 12px; border: 1.5px dashed rgba(239,68,68,0.4);">
                            <?php echo !empty($d['alasan_ditolak']) ? nl2br(e($d['alasan_ditolak'])) : 'Tidak ada keterangan/alasan spesifik yang diberikan.'; ?>
                        </div>
                    </div>
                    <?php if (!empty($d['nama_pic'])): ?>
                    <div class="text-muted small">Diverifikasi oleh: <b><?php echo e($d['nama_pic']); ?></b> pada tanggal <b><?php echo tgl($d['tanggal_proses']); ?></b></div>
                    <?php endif; ?>
                    <a href="ajukan_permohonan.php" class="btn-ajukan-ulang"><i class="bi bi-arrow-repeat"></i> Ajukan Permohonan Baru</a>
                </div>

            <!-- DESAIN JIKA NORMAL (Menunggu, Diproses, Selesai) -->
            <?php else: ?>
                <div class="status-banner <?php echo $kls; ?>">
                    <div class="big-ic">
                        <?php if ($tahap === 0): ?><i class="bi bi-hourglass-split pulse"></i>
                        <?php elseif ($tahap === 1): ?><i class="bi bi-gear-fill spin"></i>
                        <?php elseif ($tahap === 2): ?><i class="bi bi-patch-check-fill"></i><?php endif; ?>
                    </div>
                    <div class="position-relative">
                        <h4>
                            <?php 
                            if ($tahap === 0) echo 'Menunggu Verifikasi';
                            elseif ($tahap === 1) echo 'Sedang Diproses';
                            elseif ($tahap === 2) echo 'Permohonan Selesai';
                            ?>
                        </h4>
                        <p>
                            <?php 
                            if ($tahap === 0) echo 'Permohonan Anda sudah diterima dan menunggu giliran diperiksa oleh tim admin.';
                            elseif ($tahap === 1) echo 'Tim admin sedang memeriksa dan memproses permohonan Anda.';
                            elseif ($tahap === 2) echo 'Proses telah selesai. Lihat informasi keputusan di bawah.';
                            ?>
                        </p>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="timeline" id="timeline" data-fill="<?php echo $fillPct; ?>">
                    <div class="fill" id="tlFill"></div>
                    <div class="tl-step <?php echo $tahap > 0 ? 'done' : 'current'; ?>">
                        <div class="node"><i class="bi <?php echo $tahap > 0 ? 'bi-check-lg' : 'bi-inbox-fill'; ?>"></i></div>
                        <div class="t">Diterima</div><div class="d">Menunggu</div>
                    </div>
                    <div class="tl-step <?php echo $tahap > 1 ? 'done' : ($tahap === 1 ? 'current' : ''); ?>">
                        <div class="node"><i class="bi <?php echo $tahap > 1 ? 'bi-check-lg' : 'bi-gear-fill'; ?>"></i></div>
                        <div class="t">Diproses</div><div class="d">Verifikasi admin</div>
                    </div>
                    <div class="tl-step <?php echo $tahap === 2 ? 'done' : ''; ?>">
                        <div class="node"><i class="bi <?php echo $tahap === 2 ? 'bi-check-lg' : 'bi-flag-fill'; ?>"></i></div>
                        <div class="t">Selesai</div><div class="d">Dokumen siap</div>
                    </div>
                </div>

                <!-- Informasi Keputusan (hanya jika selesai) -->
                <?php if ($tahap === 2): ?>
                <div class="decision">
                    <h6><i class="bi bi-award-fill"></i> Informasi Keputusan</h6>
                    <div class="row g-3">
                        <div class="col-6"><small class="text-muted d-block">PIC Proses</small><b><?php echo e($d['nama_pic']); ?></b></div>
                        <div class="col-6"><small class="text-muted d-block">Kuota Disetujui</small><span class="big-num text-grad"><?php echo e($d['kuota_disetujui']); ?></span></div>
                    </div>

                    <?php if (!empty($d['file_terbitan'])): ?>
                        <a href="uploads/dokumen_terbitan/<?php echo rawurlencode(basename($d['file_terbitan'])); ?>" class="btn-download" target="_blank" rel="noopener">
                            <i class="bi bi-file-earmark-pdf-fill fs-4"></i> Unduh PDF Hasil Terbitan
                        </a>
                    <?php else: ?>
                        <div class="wait-box"><i class="bi bi-clock-history"></i> Dokumen PDF sedang diproses. Halaman ini akan diperbarui otomatis.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <?php endif; ?>

</div>

<div class="toast-mini" id="toast"></div>

<script>
(function () {
    const $ = (s, c = document) => c.querySelector(s);

    /* ---------- Tema ---------- */
    const root = document.documentElement, themeBtn = $('#themeToggle');
    const setTheme = t => {
        root.setAttribute('data-theme', t); root.setAttribute('data-bs-theme', t);
        themeBtn.innerHTML = t === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
        try { localStorage.setItem('eicv-theme', t); } catch (e) {}
    };
    let st = null; try { st = localStorage.getItem('eicv-theme'); } catch (e) {}
    setTheme(st || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    themeBtn.addEventListener('click', () => setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

    /* ---------- Toast ---------- */
    let tt; function toast(m) { const t = $('#toast'); t.textContent = m; t.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 2400); }
    function copyText(txt, okMsg) {
        const done = () => toast(okMsg);
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done).catch(() => fallback());
        else fallback();
        function fallback() { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) { toast('Gagal menyalin'); } ta.remove(); }
    }

    /* ---------- Input: otomatis huruf besar + shortcut "/" ---------- */
    const input = $('#kodeInput');
    input.addEventListener('input', () => { const p = input.selectionStart; input.value = input.value.toUpperCase().replace(/\s+/g, ''); input.setSelectionRange(p, p); });
    addEventListener('keydown', e => { if (e.key === '/' && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) { e.preventDefault(); input.focus(); } });

    /* ---------- Riwayat pencarian (lokal di perangkat) ---------- */
    const KEY = 'eicv-recent-codes';
    const getRecent = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } };
    const setRecent = a => { try { localStorage.setItem(KEY, JSON.stringify(a.slice(0, 5))); } catch (e) {} };
    const box = $('#recentBox');
    const escH = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function renderRecent() {
        const r = getRecent(); if (!r.length) { box.style.display = 'none'; return; }
        box.style.display = 'flex';
        box.innerHTML = '<span>Terakhir dilacak:</span>' + r.map(c => `<button type="button" class="chip" data-c="${escH(c)}">${escH(c)}</button>`).join('') + '<button type="button" class="clr" id="clrRecent">hapus</button>';
        box.querySelectorAll('.chip').forEach(b => b.addEventListener('click', () => { input.value = b.dataset.c; $('#searchForm').submit(); }));
        $('#clrRecent').addEventListener('click', () => { setRecent([]); renderRecent(); });
    }
    renderRecent();

    const result = $('#resultBox');
    if (result) {
        const kode = result.dataset.kode;
        setRecent([kode, ...getRecent().filter(c => c !== kode)]); renderRecent();

        /* animasi garis timeline */
        const tl = $('#timeline');
        if (tl) requestAnimationFrame(() => setTimeout(() => { $('#tlFill').style.width = `calc((100% - 60px - 33.2%) * ${tl.dataset.fill / 100})`; }, 50));

        /* salin kode */
        $('#copyKode').addEventListener('click', () => copyText(kode, 'Kode tracking disalin'));

        /* konfeti saat status selesai */
        const status = result.dataset.status;
        if (status === 'Selesai') {
            let seen = false; try { seen = sessionStorage.getItem('confetti-' + kode); sessionStorage.setItem('confetti-' + kode, '1'); } catch (e) {}
            if (!seen) launchConfetti();
        }
    }

    /* ---------- Konfeti ringan (canvas) ---------- */
    function launchConfetti() {
        const cv = $('#confetti'), ctx = cv.getContext('2d');
        const resize = () => { cv.width = innerWidth; cv.height = innerHeight; }; resize(); addEventListener('resize', resize);
        const colors = ['#6366f1', '#06b6d4', '#34d399', '#f472b6', '#fb923c', '#fde047'];
        const ps = Array.from({ length: 140 }, () => ({ x: Math.random() * cv.width, y: -20 - Math.random() * cv.height * .5, w: 6 + Math.random() * 6, h: 8 + Math.random() * 8, c: colors[Math.random() * colors.length | 0], vy: 2 + Math.random() * 4, vx: -2 + Math.random() * 4, r: Math.random() * 6, vr: -.2 + Math.random() * .4 }));
        const t0 = performance.now();
        (function frame(t) {
            ctx.clearRect(0, 0, cv.width, cv.height);
            ps.forEach(p => { p.x += p.vx; p.y += p.vy; p.r += p.vr; ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore(); });
            if (t - t0 < 4200) requestAnimationFrame(frame); else ctx.clearRect(0, 0, cv.width, cv.height);
        })(t0);
    }

    /* ---------- Progress scroll ---------- */
    const bar = $('#scrollProgress');
    addEventListener('scroll', () => { const h = document.documentElement; bar.style.width = (h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight) * 100) + '%'; }, { passive: true });
})();
</script>

<?php include 'includes/footer.php'; ?>