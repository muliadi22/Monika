<?php
// admin/login.php

// Cookie sesi lebih aman
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
require_once '../config/database.php';

if (isset($_SESSION['admin_logged_in'])) { header("Location: dashboard.php"); exit(); }

/* ---------------- Pengaturan ---------------- */
$MAX_PERCOBAAN   = 5;    // jumlah gagal sebelum dikunci
$DURASI_KUNCI    = 60;   // detik
// Ubah ke true HANYA jika kolom `password` di tabel admin sudah VARCHAR(255),
// agar hash MD5 lama otomatis dinaikkan ke password_hash() saat login berhasil.
$UPGRADE_HASH    = false;

/* ---------------- CSRF token ---------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$info  = "";
$username_val = "";
$sisa_kunci = 0;

if (isset($_GET['logout'])) { $info = "Anda berhasil keluar. Sampai jumpa!"; }
if (isset($_GET['expired'])) { $info = "Sesi Anda berakhir. Silakan login kembali."; }

// Hitung sisa waktu kunci (jika ada)
if (!empty($_SESSION['lock_until']) && $_SESSION['lock_until'] > time()) {
    $sisa_kunci = $_SESSION['lock_until'] - time();
} elseif (!empty($_SESSION['lock_until'])) {
    unset($_SESSION['lock_until']);
    $_SESSION['login_fail'] = 0;
}

if (isset($_POST['login'])) {
    $username_val = trim($_POST['username'] ?? '');
    $password_in  = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.";
    } elseif ($sisa_kunci > 0) {
        $error = "Terlalu banyak percobaan. Coba lagi dalam beberapa saat.";
    } elseif ($username_val === '' || $password_in === '') {
        $error = "Username dan password wajib diisi.";
    } else {
        // Prepared statement (aman dari SQL injection)
        $stmt = mysqli_prepare($conn, "SELECT * FROM admin WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username_val);
        mysqli_stmt_execute($stmt);
        $res   = mysqli_stmt_get_result($stmt);
        $admin = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        $valid = false;
        if ($admin) {
            $stored = $admin['password'];
            if (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon') === 0) {
                // Hash modern
                $valid = password_verify($password_in, $stored);
            } else {
                // Kompatibel dengan data lama (MD5)
                $valid = hash_equals(strtolower($stored), md5($password_in));
                if ($valid && $UPGRADE_HASH) {
                    $baru = password_hash($password_in, PASSWORD_DEFAULT);
                    $up = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE username = ?");
                    mysqli_stmt_bind_param($up, "ss", $baru, $username_val);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);
                }
            }
        }

        if ($valid) {
            session_regenerate_id(true);          // cegah session fixation
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['nama_lengkap']    = $admin['nama_lengkap'];
            $_SESSION['admin_username']  = $admin['username'];
            $_SESSION['login_fail']      = 0;
            unset($_SESSION['lock_until']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: dashboard.php"); exit();
        } else {
            $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
            $sisa = $MAX_PERCOBAAN - $_SESSION['login_fail'];
            if ($sisa <= 0) {
                $_SESSION['lock_until'] = time() + $DURASI_KUNCI;
                $sisa_kunci = $DURASI_KUNCI;
                $error = "Terlalu banyak percobaan gagal. Akun dikunci sementara.";
            } else {
                $error = "Username atau Password salah! Sisa percobaan: $sisa.";
            }
            usleep(400000); // perlambat tebak-tebakan
        }
    }
}
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin Login • Permohonan EICV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        // Terapkan tema sebelum render (hindari kedipan)
        try { var t = localStorage.getItem('eicv-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); document.documentElement.setAttribute('data-theme', t); } catch (e) {}
    </script>
    <style>
        :root {
            --bg: #f5f7ff; --bg-2: #eaf0ff;
            --surface: rgba(255,255,255,.9); --surface-solid: #fff; --surface-border: rgba(255,255,255,.9);
            --field: rgba(255,255,255,.9); --field-border: #dbe2f5;
            --text: #14213d; --muted: #5f6b85;
            --primary: #6366f1; --ok: #10b981; --danger: #ef4444;
            --grad: linear-gradient(135deg,#6366f1 0%,#06b6d4 55%,#34d399 100%);
            --shadow: 0 30px 70px -25px rgba(79,70,229,.45);
        }
        [data-theme="dark"] {
            --bg: #0b1020; --bg-2: #111936;
            --surface: rgba(21,28,58,.88); --surface-solid: #151c3a; --surface-border: rgba(255,255,255,.12);
            --field: rgba(255,255,255,.05); --field-border: rgba(255,255,255,.14);
            --text: #eef2ff; --muted: #9aa6c7; --shadow: 0 30px 70px -25px rgba(0,0,0,.8);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }
        .theme-btn { position: fixed; top: 18px; right: 18px; width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); display: grid; place-items: center; cursor: pointer; transition: transform .25s, color .2s; z-index: 10; }
        .theme-btn:hover { transform: rotate(20deg) scale(1.08); }

        .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 14px; }
        .login-card { width: 100%; max-width: 440px; padding: 42px 36px 34px; border-radius: 32px; background: var(--surface); border: 1px solid var(--surface-border); box-shadow: var(--shadow); animation: rise .8s cubic-bezier(.2,.7,.2,1); position: relative; }
        .login-card.shake { animation: shake .5s; }
        @keyframes rise { from { opacity: 0; transform: translateY(40px) scale(.97); } to { opacity: 1; transform: none; } }
        @keyframes shake { 20%,60% { transform: translateX(-9px); } 40%,80% { transform: translateX(9px); } }

        .lock-icon { width: 84px; height: 84px; border-radius: 26px; background: var(--grad); color: #fff; font-size: 2.2rem; display: grid; place-items: center; margin: 0 auto 18px; box-shadow: 0 20px 38px -12px var(--primary); animation: float 4s ease-in-out infinite; position: relative; }
        .lock-icon::after { content: ''; position: absolute; inset: -8px; border-radius: 32px; border: 2px solid var(--primary); opacity: .35; animation: ring 2.6s ease-out infinite; }
        @keyframes ring { to { transform: scale(1.25); opacity: 0; } }
        @keyframes float { 50% { transform: translateY(-8px); } }
        .text-grad { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
        h1.title { font-size: 1.7rem; font-weight: 800; letter-spacing: -.5px; margin-bottom: 4px; }
        .subtitle { color: var(--muted); font-size: .92rem; }

        .msg { border-radius: 16px; padding: 12px 16px; font-size: .88rem; font-weight: 600; display: flex; gap: 10px; align-items: center; text-align: left; margin-bottom: 18px; animation: rise .4s; }
        .msg.err { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.35); color: var(--danger); }
        .msg.ok  { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.35); color: var(--ok); }

        .field { position: relative; margin-bottom: 18px; text-align: left; }
        .field label { font-size: .8rem; font-weight: 700; color: var(--muted); margin: 0 0 6px 8px; display: block; }
        .field .ic { position: absolute; left: 18px; bottom: 15px; color: var(--primary); font-size: 1.1rem; pointer-events: none; }
        .field input { width: 100%; padding: 14px 48px 14px 48px; border-radius: 18px; border: 1.5px solid var(--field-border); background: var(--field); color: var(--text); font-weight: 600; outline: none; transition: border-color .15s, box-shadow .15s; }
        .field input::placeholder { color: var(--muted); font-weight: 400; opacity: .7; }
        .field input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.18); }
        .field input:disabled { opacity: .55; }
        .eye { position: absolute; right: 10px; bottom: 7px; width: 38px; height: 38px; border: 0; background: none; color: var(--muted); border-radius: 50%; transition: .2s; }
        .eye:hover { color: var(--primary); background: rgba(99,102,241,.1); }
        .caps { display: none; font-size: .76rem; font-weight: 700; color: #f59e0b; margin: 6px 0 0 8px; }
        .caps.show { display: block; animation: rise .3s; }

        .row-opt { display: flex; justify-content: space-between; align-items: center; font-size: .84rem; margin-bottom: 22px; color: var(--muted); }
        .row-opt label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; }
        .row-opt input { accent-color: var(--primary); width: 16px; height: 16px; }

        .btn-login { width: 100%; padding: 15px; border: 0; border-radius: 100px; background: var(--grad); color: #fff; font-weight: 800; font-size: 1rem; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s; box-shadow: 0 14px 28px -10px var(--primary); }
        .btn-login:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 20px 34px -10px var(--primary); }
        .btn-login:disabled { opacity: .7; cursor: not-allowed; }
        .btn-login::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg,transparent,rgba(255,255,255,.5),transparent); transform: skewX(-20deg); transition: .6s; }
        .btn-login:hover:not(:disabled)::after { left: 140%; }

        .back { display: inline-flex; align-items: center; gap: 6px; margin-top: 22px; color: var(--muted); font-weight: 700; font-size: .88rem; text-decoration: none; transition: .25s; }
        .back:hover { color: var(--primary); transform: translateX(-4px); }
        .secure { margin-top: 18px; font-size: .74rem; color: var(--muted); display: flex; justify-content: center; align-items: center; gap: 6px; }
        .secure i { color: var(--ok); }

        /* ===== Latar dinamis ringan (hanya transform & opacity) ===== */
        .bg-aurora { position: fixed; inset: 0; z-index: -5; pointer-events: none;
            background:
                radial-gradient(60% 55% at 12% 8%,  rgba(99,102,241,.30) 0%, transparent 60%),
                radial-gradient(55% 50% at 92% 22%, rgba(6,182,212,.26) 0%, transparent 62%),
                radial-gradient(55% 50% at 78% 95%, rgba(52,211,153,.20) 0%, transparent 60%),
                radial-gradient(50% 45% at 8% 88%,  rgba(244,114,182,.16) 0%, transparent 60%),
                linear-gradient(160deg, var(--bg-2), var(--bg) 55%); contain: strict; }
        [data-theme="dark"] .bg-aurora { background:
                radial-gradient(60% 55% at 12% 8%,  rgba(99,102,241,.38) 0%, transparent 60%),
                radial-gradient(55% 50% at 92% 22%, rgba(6,182,212,.28) 0%, transparent 62%),
                radial-gradient(55% 50% at 78% 95%, rgba(16,185,129,.20) 0%, transparent 60%),
                radial-gradient(50% 45% at 8% 88%,  rgba(236,72,153,.16) 0%, transparent 60%),
                linear-gradient(160deg, var(--bg-2), var(--bg) 55%); }

        /* Bola cahaya melayang (gradient lembut, tanpa blur) */
        .orb { position: fixed; border-radius: 50%; z-index: -4; pointer-events: none; will-change: transform; }
        .orb.a { width: 46vmax; height: 46vmax; left: -14vmax; top: -16vmax; background: radial-gradient(circle, rgba(99,102,241,.30), transparent 65%); animation: drift1 24s ease-in-out infinite alternate; }
        .orb.b { width: 40vmax; height: 40vmax; right: -12vmax; top: 6vh; background: radial-gradient(circle, rgba(6,182,212,.26), transparent 65%); animation: drift2 28s ease-in-out infinite alternate; }
        @keyframes drift1 { to { transform: translate3d(9vw, 8vh, 0) scale(1.1); } }
        @keyframes drift2 { to { transform: translate3d(-8vw, 10vh, 0) scale(1.08); } }

        /* Ikon melayang naik perlahan */
        .floaters { position: fixed; inset: 0; z-index: -3; pointer-events: none; overflow: hidden; }
        .floaters i { position: absolute; bottom: -60px; color: var(--primary); opacity: 0; will-change: transform, opacity; animation: rise-up linear infinite; }
        .floaters i:nth-child(1) { left: 8%;  font-size: 2.1rem; animation-duration: 22s; animation-delay: -2s; }
        .floaters i:nth-child(2) { left: 22%; font-size: 1.5rem; animation-duration: 28s; animation-delay: -12s; color: #06b6d4; }
        .floaters i:nth-child(3) { left: 38%; font-size: 2.4rem; animation-duration: 25s; animation-delay: -7s; }
        .floaters i:nth-child(4) { left: 58%; font-size: 1.7rem; animation-duration: 30s; animation-delay: -18s; color: #10b981; }
        @keyframes rise-up {
            0%   { transform: translate3d(0, 0, 0) rotate(0deg); opacity: 0; }
            12%  { opacity: .22; }
            85%  { opacity: .18; }
            100% { transform: translate3d(30px, -112vh, 0) rotate(40deg); opacity: 0; }
        }

        /* 1. Garis kontur topografi (TAMBAHAN EFEK INDEX) */
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

        /* 2. Sapuan cahaya diagonal (TAMBAHAN EFEK INDEX) */
        .sweep { position: fixed; top: -20%; left: -40%; width: 38vw; height: 140%; z-index: -2; pointer-events: none; will-change: transform;
            background: linear-gradient(100deg, transparent, rgba(99, 102, 241, .07), rgba(6, 182, 212, .06), transparent);
            transform: translateX(0) skewX(-18deg); animation: sweepX 22s ease-in-out infinite; }
        [data-theme="dark"] .sweep { background: linear-gradient(100deg, transparent, rgba(165, 180, 252, .06), rgba(125, 211, 252, .05), transparent); }
        @keyframes sweepX { 0% { transform: translateX(0) skewX(-18deg); } 55%, 100% { transform: translateX(420%) skewX(-18deg); } }

        /* Partikel kecil bergerak (canvas ringan) */
        #netCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: -3; pointer-events: none; }

        .bg-fade { position: fixed; inset: 0; z-index: -1; pointer-events: none; background: radial-gradient(ellipse at 50% 40%, transparent 40%, var(--bg) 115%); opacity: .5; contain: strict; }
        .bg-waves { position: fixed; left: 0; right: 0; bottom: 0; height: 30vh; z-index: -2; pointer-events: none; overflow: hidden; contain: strict; }
        .bg-waves svg { position: absolute; left: 0; bottom: 0; width: 200%; height: 100%; will-change: transform; }
        .bg-waves .w1 { animation: waveX 26s linear infinite; }
        .bg-waves .w2 { animation: waveX 38s linear infinite reverse; opacity: .8; }
        @keyframes waveX { to { transform: translateX(-50%); } }

        /* Jeda semua animasi latar saat mengetik */
        html.typing .orb, html.typing .floaters i, html.typing .bg-waves svg, html.typing .lock-icon, html.typing .lock-icon::after, html.typing .contour, html.typing .sweep { animation-play-state: paused !important; }
        @media print { .bg-aurora, .orb, .floaters, #netCanvas, .bg-waves, .bg-fade, .contour, .sweep { display: none !important; } }
        @media (max-width: 600px) { .floaters i:nth-child(n+3), .orb.b, .sweep { display: none; } .contour { opacity: .7; } }
        @media (max-width: 480px) { .login-card { padding: 34px 22px 28px; border-radius: 26px; } }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
    <!-- ===== LATAR DINAMIS RINGAN ===== -->
    <div class="bg-aurora"></div>
    <div class="orb a"></div><div class="orb b"></div>
    
    <!-- TAMBAHAN ELEMEN EFEK INDEX -->
    <div class="contour" id="contour" aria-hidden="true"></div>
    <div class="sweep" aria-hidden="true"></div>
    
    <div class="floaters" aria-hidden="true">
        <i class="bi bi-shield-check"></i><i class="bi bi-file-earmark-check"></i><i class="bi bi-patch-check-fill"></i><i class="bi bi-key"></i>
    </div>
    <canvas id="netCanvas" aria-hidden="true"></canvas>
    <div class="bg-waves" aria-hidden="true">
        <svg class="w2" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg2" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".16"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".16"/><stop offset="1" stop-color="#34d399" stop-opacity=".14"/></linearGradient></defs>
            <path fill="url(#wg2)" d="M0 110 C 200 60 400 160 600 110 S 1000 60 1200 110 S 1600 160 1800 110 S 2200 60 2400 110 V200 H0Z"/></svg>
        <svg class="w1" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg1" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".22"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".2"/><stop offset="1" stop-color="#f472b6" stop-opacity=".16"/></linearGradient></defs>
            <path fill="url(#wg1)" d="M0 140 C 150 100 350 190 600 140 S 1050 90 1200 140 S 1550 190 1800 140 S 2250 90 2400 140 V200 H0Z"/></svg>
    </div>
    <div class="bg-fade"></div>

    <button class="theme-btn" id="themeToggle" type="button" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i></button>

    <div class="login-wrap">
        <div class="login-card text-center <?php echo $error ? 'shake' : ''; ?>" id="card">
            <div class="lock-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h1 class="title">Portal <span class="text-grad">Admin</span></h1>
            <p class="subtitle mb-4">Masuk untuk mengelola permohonan EICV</p>

            <?php if ($error): ?>
                <div class="msg err" role="alert"><i class="bi bi-exclamation-octagon-fill fs-5"></i><span><?php echo $h($error); ?></span></div>
            <?php endif; ?>
            <?php if ($info && !$error): ?>
                <div class="msg ok" role="status"><i class="bi bi-check-circle-fill fs-5"></i><span><?php echo $h($info); ?></span></div>
            <?php endif; ?>
            <div class="msg err" id="lockMsg" style="display:none;"><i class="bi bi-clock-history fs-5"></i><span>Dikunci sementara. Coba lagi dalam <b id="lockSec">0</b> detik.</span></div>

            <form method="POST" action="" id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo $h($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="login" value="1">

                <div class="field">
                    <label for="username">Username</label>
                    <i class="bi bi-person-fill ic"></i>
                    <input type="text" id="username" name="username" value="<?php echo $h($username_val); ?>" placeholder="Masukkan username" autocomplete="username" required autofocus>
                </div>

                <div class="field" style="margin-bottom:10px;">
                    <label for="password">Password</label>
                    <i class="bi bi-key-fill ic"></i>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                    <button type="button" class="eye" id="togglePw" aria-label="Tampilkan password"><i class="bi bi-eye-fill"></i></button>
                    <div class="caps" id="capsWarn"><i class="bi bi-exclamation-triangle-fill"></i> Caps Lock sedang aktif</div>
                </div>

                <div class="row-opt">
                    <label><input type="checkbox" id="rememberUser"> Ingat username</label>
                    <span><i class="bi bi-lock-fill"></i> Koneksi terenkripsi</span>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="lbl">Login Sekarang <i class="bi bi-box-arrow-in-right ms-1"></i></span>
                </button>
            </form>

            <a href="../index.php" class="back"><i class="bi bi-arrow-left"></i> Beranda User</a>
            <div class="secure"><i class="bi bi-shield-check"></i> Akses khusus Admin</div>
        </div>
    </div>

<script>
(function () {
    const $ = s => document.querySelector(s);

    /* Tema (sinkron dengan halaman lain) */
    const root = document.documentElement, tb = $('#themeToggle');
    const paint = () => tb.innerHTML = root.getAttribute('data-theme') === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
    paint();
    tb.addEventListener('click', () => {
        const t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', t); try { localStorage.setItem('eicv-theme', t); } catch (e) {} paint();
    });

    /* Jeda animasi latar hanya saat benar-benar mengetik (bukan saat sekadar fokus/autofocus) */
    const fm = $('#loginForm'); let tt;
    const pauseTyping = () => { root.classList.add('typing'); clearTimeout(tt); tt = setTimeout(() => root.classList.remove('typing'), 900); };
    fm.addEventListener('input', pauseTyping);

    /* Tampilkan / sembunyikan password */
    const pw = $('#password'), eye = $('#togglePw');
    eye.addEventListener('click', () => {
        const show = pw.type === 'password'; pw.type = show ? 'text' : 'password';
        eye.innerHTML = show ? '<i class="bi bi-eye-slash-fill"></i>' : '<i class="bi bi-eye-fill"></i>'; pw.focus();
    });

    /* Peringatan Caps Lock */
    const caps = $('#capsWarn');
    ['keydown', 'keyup'].forEach(ev => pw.addEventListener(ev, e => caps.classList.toggle('show', e.getModifierState && e.getModifierState('CapsLock'))));
    pw.addEventListener('blur', () => caps.classList.remove('show'));

    /* Ingat username (hanya username, tidak pernah password) */
    const un = $('#username'), rem = $('#rememberUser');
    try { const s = localStorage.getItem('eicv-admin-user'); if (s && !un.value) { un.value = s; rem.checked = true; pw.focus(); } } catch (e) {}

    /* Hitung mundur kunci */
    let sisa = <?php echo (int)$sisa_kunci; ?>;
    const lockMsg = $('#lockMsg'), lockSec = $('#lockSec'), btn = $('#btnLogin');
    function setLocked(on) { btn.disabled = on; un.disabled = on; pw.disabled = on; lockMsg.style.display = on ? 'flex' : 'none'; }
    if (sisa > 0) {
        setLocked(true); lockSec.textContent = sisa;
        const iv = setInterval(() => { sisa--; lockSec.textContent = sisa; if (sisa <= 0) { clearInterval(iv); setLocked(false); un.focus(); } }, 1000);
    }

    /* Submit: simpan username & tampilkan loading */
    $('#loginForm').addEventListener('submit', () => {
        try { rem.checked ? localStorage.setItem('eicv-admin-user', un.value.trim()) : localStorage.removeItem('eicv-admin-user'); } catch (e) {}
        btn.querySelector('.lbl').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memverifikasi…';
        setTimeout(() => { btn.disabled = true; }, 0); // dinonaktifkan setelah data form terbentuk
    });

    // Jika halaman dikembalikan dari cache (tombol Back), pulihkan tombol
    addEventListener('pageshow', e => { if (e.persisted) { btn.disabled = false; btn.querySelector('.lbl').innerHTML = 'Login Sekarang <i class="bi bi-box-arrow-in-right ms-1"></i>'; } });
})();
</script>

<script>
/* Garis kontur topografi: dibuat sekali (statis), lalu hanya bergeser sangat pelan lewat CSS (TAMBAHAN EFEK INDEX) */
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
/* Partikel kecil bergerak: ringan (maks 32 titik, 24 fps, berhenti saat mengetik / tab tersembunyi) */
(function () {
    const cv = document.getElementById('netCanvas');
    if (!cv || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = cv.getContext('2d'), root = document.documentElement;
    let W, H, pts = [], mx = -999, my = -999, raf, last = 0;
    function size() {
        const dpr = 1;
        W = innerWidth; H = innerHeight; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const n = Math.min(32, Math.floor(W * H / 30000));
        pts = Array.from({ length: n }, () => ({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .3, vy: (Math.random() - .5) * .3, r: Math.random() * 1.4 + .8 }));
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
            if (d < 140 && d > 1) { p.x -= dx / d * .3; p.y -= dy / d * .3; }
            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = 'rgba(' + dot + ',' + (dark ? .6 : .4) + ')'; ctx.fill();
            for (let j = i + 1; j < pts.length; j++) {
                const q = pts[j], dist = Math.hypot(p.x - q.x, p.y - q.y);
                if (dist < 110) {
                    ctx.strokeStyle = 'rgba(' + line + ',' + ((dark ? .26 : .18) * (1 - dist / 110)) + ')'; ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                }
            }
        }
    }
    function loop(t) {
        raf = requestAnimationFrame(loop);
        if (root.classList.contains('typing') || t - last < 41) return;
        last = t; draw();
    }
    raf = requestAnimationFrame(loop);
    document.addEventListener('visibilitychange', () => { cancelAnimationFrame(raf); if (!document.hidden) raf = requestAnimationFrame(loop); });
})();
</script>
</body>
</html>