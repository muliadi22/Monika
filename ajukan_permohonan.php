<?php 
// ajukan_permohonan.php
require_once 'config/database.php';
include 'includes/header.php'; 

// DAFTAR KLINIK BERSIH: Bersumber 100% dari Database (Master Klinik & Riwayat)
$daftar_klinik = [];

// 1. Ambil data klinik dari tabel master_klinik (dikelola admin)
$query_master = @mysqli_query($conn, "SELECT nama_klinik FROM master_klinik");
if ($query_master) {
    while ($row = mysqli_fetch_assoc($query_master)) {
        $nama = trim($row['nama_klinik']);
        if (!empty($nama)) {
            $daftar_klinik[] = $nama;
        }
    }
}

// 2. Tarik juga riwayat nama klinik yang diketik manual oleh user sebelumnya
$query_riwayat = @mysqli_query($conn, "SELECT DISTINCT nama_klinik FROM permohonan WHERE nama_klinik IS NOT NULL AND nama_klinik != ''");
if ($query_riwayat) {
    while ($row = mysqli_fetch_assoc($query_riwayat)) {
        $nama_db = preg_replace('/\s+/', ' ', trim($row['nama_klinik']));
        
        // Cek agar tidak ada duplikat (mengabaikan huruf besar/kecil)
        $is_duplicate = false;
        foreach ($daftar_klinik as $existing) {
            if (strtolower($existing) === strtolower($nama_db)) {
                $is_duplicate = true;
                break;
            }
        }
        
        if (!$is_duplicate && !empty($nama_db)) {
            $daftar_klinik[] = $nama_db;
        }
    }
}

// 2b. Samakan hitungan dengan menu Kelola Klinik: nama kembar (beda huruf besar/kecil atau spasi ganda) dijadikan satu
$unik_klinik = [];
foreach ($daftar_klinik as $nm) {
    $nm = preg_replace('/\s+/u', ' ', trim($nm));
    $kunci = mb_strtolower($nm, 'UTF-8');
    if ($kunci !== '' && !isset($unik_klinik[$kunci])) $unik_klinik[$kunci] = $nm;
}
$daftar_klinik = array_values($unik_klinik);

// 3. Buang nama klinik yang sudah dihapus admin lewat menu "Kelola Klinik" (data permohonan tetap aman)
$query_sembunyi = @mysqli_query($conn, "SELECT nama_norm FROM klinik_disembunyikan");
if ($query_sembunyi) {
    $disembunyikan = [];
    while ($row = mysqli_fetch_assoc($query_sembunyi)) { $disembunyikan[$row['nama_norm']] = true; }
    if ($disembunyikan) {
        $daftar_klinik = array_values(array_filter($daftar_klinik, function ($nm) use ($disembunyikan) {
            return !isset($disembunyikan[mb_strtolower(preg_replace('/\s+/u', ' ', trim($nm)), 'UTF-8')]);
        }));
    }
}

// Urutkan seluruh daftar klinik sesuai abjad otomatis
sort($daftar_klinik);

// ==========================================================
// DAFTAR KABUPATEN / KOTA (master_kabupaten + riwayat ketikan pemohon)
// Tabel dibuat & diisi otomatis bila belum ada (lihat juga database_kabupaten.sql)
// ==========================================================
$kabupaten_bawaan = ['Balangan', 'Banjar', 'Barito Kuala', 'Hulu Sungai Selatan', 'Hulu Sungai Tengah', 'Hulu Sungai Utara',
                     'Kotabaru', 'Tabalong', 'Tanah Bumbu', 'Tanah Laut', 'Tapin', 'Banjarmasin', 'Banjarbaru'];
$daftar_kabupaten = [];
try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS master_kabupaten (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nama_kabupaten VARCHAR(100) NOT NULL,
        UNIQUE KEY uq_nama_kabupaten (nama_kabupaten)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $rc = mysqli_query($conn, "SELECT COUNT(*) FROM master_kabupaten");
    if ($rc && (int)mysqli_fetch_row($rc)[0] === 0) {
        $st_k = mysqli_prepare($conn, "INSERT IGNORE INTO master_kabupaten (nama_kabupaten) VALUES (?)");
        foreach ($kabupaten_bawaan as $kb) { mysqli_stmt_bind_param($st_k, 's', $kb); mysqli_stmt_execute($st_k); }
    }
    $rk = mysqli_query($conn, "SELECT nama_kabupaten FROM master_kabupaten");
    while ($rk && ($r = mysqli_fetch_assoc($rk))) { $daftar_kabupaten[] = $r['nama_kabupaten']; }
} catch (Throwable $ex) {}
if (!$daftar_kabupaten) $daftar_kabupaten = $kabupaten_bawaan;
try {   // riwayat yang diketik manual pemohon sebelumnya (kolom bisa saja belum ada)
    $rr = mysqli_query($conn, "SELECT DISTINCT nama_kabupaten FROM permohonan WHERE nama_kabupaten IS NOT NULL AND nama_kabupaten != ''");
    while ($rr && ($r = mysqli_fetch_assoc($rr))) { $daftar_kabupaten[] = $r['nama_kabupaten']; }
} catch (Throwable $ex) {}
$unik_kab = [];
foreach ($daftar_kabupaten as $nm) {
    $nm = preg_replace('/\s+/u', ' ', trim($nm)); $kk = mb_strtolower($nm, 'UTF-8');
    if ($kk !== '' && !isset($unik_kab[$kk])) $unik_kab[$kk] = $nm;
}
$daftar_kabupaten = array_values($unik_kab);
sort($daftar_kabupaten, SORT_NATURAL | SORT_FLAG_CASE);
?>

<style>
/* =========================================================
   THEME TOKENS
   ========================================================= */
:root {
    --bg: #f5f7ff; --bg-2: #eaf0ff;
    --surface: rgba(255,255,255,.7); --surface-solid: #ffffff; --surface-border: rgba(255,255,255,.85);
    --field: rgba(255,255,255,.9); --field-border: #dbe2f5;
    --text: #14213d; --muted: #5f6b85;
    --primary: #6366f1; --secondary: #06b6d4; --danger: #ef4444; --ok: #10b981;
    --grad: linear-gradient(135deg,#6366f1 0%,#06b6d4 55%,#34d399 100%);
    --shadow: 0 20px 50px -20px rgba(79,70,229,.35);
    --radius: 26px;
}
[data-theme="dark"] {
    --bg: #0b1020; --bg-2: #111936;
    --surface: rgba(255,255,255,.06); --surface-solid: #151c3a; --surface-border: rgba(255,255,255,.12);
    --field: rgba(255,255,255,.05); --field-border: rgba(255,255,255,.14);
    --text: #eef2ff; --muted: #cbd5e1; --shadow: 0 20px 60px -20px rgba(0,0,0,.7);
}
html { scroll-behavior: smooth; }
body { background: var(--bg) !important; color: var(--text); transition: background .4s, color .4s; overflow-x: hidden; }

.text-muted { color: var(--muted) !important; }
.text-primary { color: var(--primary) !important; }

/* Background */
.bg-aurora { position: fixed; inset: 0; z-index: -3; background: radial-gradient(circle at 20% 0%, var(--bg-2), var(--bg) 60%); }
.scroll-progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--grad); z-index: 2000; }

/* Top bar */
.topbar { max-width: 860px; margin: 18px auto 0; padding: 0 12px; display: flex; justify-content: space-between; align-items: center; }
.back-link { color: var(--muted); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px 8px 8px; border-radius: 50px; transition: .25s; }
.back-link:hover { color: var(--text); background: var(--surface); transform: translateX(-4px); }
.icon-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--surface-border); background: var(--surface); color: var(--text); display: grid; place-items: center; cursor: pointer; transition: .3s; backdrop-filter: blur(10px); }
.icon-btn:hover { transform: rotate(20deg) scale(1.08); }

/* Card */
.form-shell { max-width: 860px; margin: 22px auto 60px; padding: 0 12px; }
.glass-card { background: var(--surface); border: 1px solid var(--surface-border); border-radius: var(--radius); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: var(--shadow); }
.form-head { text-align: center; padding: 38px 24px 8px; }
.head-icon { width: 78px; height: 78px; border-radius: 24px; background: var(--grad); color: #fff; font-size: 2rem; display: grid; place-items: center; margin: 0 auto 16px; box-shadow: 0 18px 35px -10px var(--primary); animation: float 4s ease-in-out infinite; }
@keyframes float { 50% { transform: translateY(-8px); } }
.text-grad { background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
.form-head h2 { font-weight: 800; letter-spacing: -.6px; }
.form-head p { color: var(--muted) !important; }

/* Stepper */
.stepper { display: flex; align-items: flex-start; justify-content: space-between; padding: 22px 28px 6px; position: relative; }
.stepper::before { content: ''; position: absolute; top: 40px; left: 12%; right: 12%; height: 3px; background: var(--field-border); border-radius: 3px; }
.stepper .fill { position: absolute; top: 40px; left: 12%; height: 3px; width: 0; background: var(--grad); border-radius: 3px; transition: width .5s cubic-bezier(.2,.7,.2,1); }
.step-dot { position: relative; z-index: 1; text-align: center; flex: 1; cursor: default; }
.step-dot .dot { width: 38px; height: 38px; margin: 0 auto 8px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: .9rem; background: var(--surface-solid); border: 2px solid var(--field-border); color: var(--muted); transition: .35s; }
.step-dot .lbl { font-size: .75rem; font-weight: 700; color: var(--muted); transition: .3s; }
.step-dot.active .dot { background: var(--grad); color: #fff; border-color: transparent; transform: scale(1.15); box-shadow: 0 10px 22px -6px var(--primary); }
.step-dot.active .lbl { color: var(--text); }
.step-dot.done .dot { background: var(--ok); color: #fff; border-color: transparent; }
.step-dot.done { cursor: pointer; }

/* Panels */
.form-body { padding: 18px 32px 34px; }
.panel { display: none; animation: panelIn .5s cubic-bezier(.2,.7,.2,1); }
.panel.active { display: block; }
.panel.back { animation-name: panelBack; }
@keyframes panelIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: none; } }
@keyframes panelBack { from { opacity: 0; transform: translateX(-40px); } to { opacity: 1; transform: none; } }
.panel-title { font-weight: 800; font-size: 1.2rem; margin-bottom: 4px; color: var(--text); }
.panel-sub { color: var(--muted) !important; font-size: .9rem; margin-bottom: 22px; font-weight: 500; }

/* Fields */
.form-floating > .form-control { background: var(--field); border: 1.5px solid var(--field-border); border-radius: 16px; color: var(--text); height: calc(3.6rem + 2px); transition: .25s; }
.form-floating > label { color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.form-floating > .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.18); background: var(--field); color: var(--text); outline: none; }
.form-floating > .form-control:focus ~ label, .form-floating > .form-control:not(:placeholder-shown) ~ label { color: var(--primary); }
.form-floating > input[type="date"] ~ label { color: var(--primary); }
.form-control.invalid { border-color: var(--danger) !important; box-shadow: 0 0 0 4px rgba(239,68,68,.15) !important; animation: shake .4s; }
.form-control.valid { border-color: var(--ok); }
@keyframes shake { 20%,60% { transform: translateX(-6px); } 40%,80% { transform: translateX(6px); } }
.err-msg { display: none; color: var(--danger); font-size: .8rem; font-weight: 600; margin: 4px 0 0 6px; }
.field-wrap.has-error .err-msg { display: block; }
[data-theme="dark"] input[type="date"] { color-scheme: dark; }

/* Custom Dropdown */
.klinik-dropdown { position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 1000; background: var(--surface-solid); border: 1px solid var(--field-border); border-radius: 18px; max-height: 260px; overflow-y: auto; list-style: none; padding: 6px; margin: 0; box-shadow: 0 20px 40px -12px rgba(0,0,0,.25); display: none; animation: panelIn .25s; }
.klinik-dropdown::-webkit-scrollbar { width: 6px; }
.klinik-dropdown::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.klinik-dropdown li { padding: 10px 14px; border-radius: 12px; cursor: pointer; font-size: .93rem; color: var(--text); display: flex; align-items: center; gap: 10px; transition: .2s ease; }
.klinik-dropdown li i { color: var(--primary); opacity: .7; }
.klinik-dropdown li.active, .klinik-dropdown li:hover { background: rgba(99,102,241,.12); color: var(--primary); font-weight: 600; }
.klinik-dropdown li mark { background: rgba(99,102,241,.25); color: inherit; border-radius: 4px; padding: 0 2px; }
.klinik-dropdown li.custom { border: 1.5px dashed var(--primary); margin-bottom: 4px; font-weight: 600; color: var(--primary); }
.klinik-dropdown li.custom small { color: var(--muted); font-weight: 500; }
.klinik-count { font-size: .75rem; color: var(--muted) !important; margin: 4px 0 0 6px; }

/* Upload */
.dropzone { border: 2px dashed var(--field-border); border-radius: 22px; padding: 34px 20px; text-align: center; cursor: pointer; transition: .3s; background: var(--field); position: relative; }
.dropzone:hover, .dropzone.drag { border-color: var(--primary); background: rgba(99,102,241,.08); transform: translateY(-2px); }
.dropzone .dz-icon { font-size: 2.6rem; color: var(--primary); display: block; animation: float 3s ease-in-out infinite; }
.dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.file-chip { display: none; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 18px; background: var(--field); border: 1.5px solid var(--ok); margin-top: 14px; }
.file-chip.show { display: flex; animation: panelIn .4s; }
.file-chip .thumb { width: 52px; height: 52px; border-radius: 12px; background: rgba(16,185,129,.15); display: grid; place-items: center; color: var(--ok); font-size: 1.5rem; overflow: hidden; flex-shrink: 0; }
.file-chip .thumb img { width: 100%; height: 100%; object-fit: cover; }
.file-chip .info { flex: 1; min-width: 0; }
.file-chip .info b { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-chip .info small { color: var(--muted); }

/* Review */
.review-group { background: var(--field); border: 1px solid var(--field-border); border-radius: 18px; padding: 16px 18px; margin-bottom: 14px; }
.review-group h6 { font-weight: 800; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; color: var(--primary); display: flex; justify-content: space-between; align-items: center; }
.review-group h6 button { background: none; border: 0; color: var(--muted); font-size: .8rem; font-weight: 700; text-transform: none; letter-spacing: 0; }
.review-group h6 button:hover { color: var(--primary); }
.review-row { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; border-bottom: 1px dashed var(--field-border); font-size: .9rem; }
.review-row:last-child { border: 0; }
.review-row span:first-child { color: var(--muted); }
.review-row span:last-child { font-weight: 700; text-align: right; word-break: break-word; color: var(--text); }

/* Buttons */
.nav-btns { display: flex; justify-content: space-between; gap: 12px; margin-top: 28px; }
.btn-grad { background: var(--grad); color: #fff !important; border: 0; font-weight: 700; border-radius: 100px; padding: 13px 30px; position: relative; overflow: hidden; transition: transform .25s, box-shadow .25s; box-shadow: 0 10px 25px -8px var(--primary); }
.btn-grad:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -8px var(--primary); }
.btn-grad:disabled { opacity: .75; transform: none; }
.btn-grad::after { content: ''; position: absolute; top: 0; left: -120%; width: 60%; height: 100%; background: linear-gradient(120deg,transparent,rgba(255,255,255,.5),transparent); transform: skewX(-20deg); transition: .6s; }
.btn-grad:hover::after { left: 140%; }
.btn-ghost { border: 1px solid var(--field-border); background: var(--surface); color: var(--text); border-radius: 100px; padding: 13px 26px; font-weight: 700; transition: .25s; }
.btn-ghost:hover { background: var(--field); transform: translateY(-3px); color: var(--text); }
.draft-note { text-align: center; font-size: .78rem; color: var(--muted) !important; margin-top: 16px; }
.draft-note i { color: var(--ok); }
.btn-clear { background: none; border: 0; color: var(--muted) !important; text-decoration: underline; font-size: .78rem; }

.toast-mini { position: fixed; bottom: 24px; left: 50%; transform: translate(-50%,120px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 100px; font-weight: 600; transition: .4s; z-index: 3000; max-width: 90vw; text-align: center; }
.toast-mini.show { transform: translate(-50%,0); }

@media (max-width: 576px) {
    .form-body { padding: 14px 18px 26px; }
    .stepper { padding: 18px 12px 4px; }
    .step-dot .lbl { font-size: .65rem; }
    .nav-btns .btn { flex: 1; padding-left: 14px; padding-right: 14px; }
}
</style>

<script>
    try {
        const saved = localStorage.getItem('eicv-theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
        document.documentElement.setAttribute('data-bs-theme', saved);
    } catch (e) {}
</script>

<div class="scroll-progress" id="scrollProgress"></div>
<div class="bg-aurora"></div>

<div class="topbar">
    <a href="index.php" class="back-link"><i class="bi bi-arrow-left-short fs-4"></i> Kembali ke Beranda</a>
    <button class="icon-btn" id="themeToggle" type="button" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i></button>
</div>

<div class="form-shell">
    <div class="glass-card">
        <div class="form-head">
            <div class="head-icon"><i class="bi bi-pencil-square"></i></div>
            <h2 class="text-grad">Formulir Permohonan Baru</h2>
            <p class="text-muted small mb-0">Lengkapi data dalam 4 langkah singkat. Isian Anda tersimpan otomatis sebagai draf.</p>
        </div>

        <div class="stepper" id="stepper">
            <div class="fill" id="stepFill"></div>
            <div class="step-dot active" data-step="0"><div class="dot">1</div><div class="lbl">Pemohon</div></div>
            <div class="step-dot" data-step="1"><div class="dot">2</div><div class="lbl">EICV</div></div>
            <div class="step-dot" data-step="2"><div class="dot">3</div><div class="lbl">Vaksin</div></div>
            <div class="step-dot" data-step="3"><div class="dot">4</div><div class="lbl">Kirim</div></div>
        </div>

        <div class="form-body">
            <form action="proses_ajukan.php" method="POST" enctype="multipart/form-data" id="mainForm" novalidate>

                <!-- ============ STEP 1 ============ -->
                <section class="panel active" data-title="Data Pemohon & Klinik">
                    <div class="panel-title"><i class="bi bi-person-badge text-primary me-2"></i>Data Pemohon &amp; Klinik</div>
                    <div class="panel-sub">Siapa yang mengajukan dan dari klinik mana.</div>

                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="tgl_mohon" name="tanggal_permohonan" placeholder=" " required>
                            <label for="tgl_mohon">Tanggal Permohonan</label>
                        </div>
                        <div class="err-msg">Tanggal permohonan wajib diisi.</div>
                    </div>

                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nm_pemohon" name="nama_pemohon" placeholder="Nama Lengkap" required>
                            <label for="nm_pemohon">Nama Pemohon</label>
                        </div>
                        <div class="err-msg">Nama pemohon wajib diisi.</div>
                    </div>

                    <!-- Nama klinik + dropdown pencarian -->
                    <div class="field-wrap mb-3 position-relative">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nm_klinik" name="nama_klinik" placeholder="Nama Klinik" autocomplete="off" required role="combobox" aria-expanded="false" aria-controls="klinikDropdown">
                            <label for="nm_klinik">Nama Klinik (Pilih / Ketik)</label>
                        </div>
                        <ul class="klinik-dropdown" id="klinikDropdown" role="listbox"></ul>
                        <div class="klinik-count" id="klinikCount"></div>
                        <div class="err-msg">Nama klinik wajib diisi.</div>
                    </div>

                    <!-- Nama kabupaten/kota + dropdown pencarian (sama seperti nama klinik) -->
                    <div class="field-wrap mb-3 position-relative">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nm_kabupaten" name="nama_kabupaten" placeholder="Nama Kabupaten" autocomplete="off" required role="combobox" aria-expanded="false" aria-controls="kabupatenDropdown">
                            <label for="nm_kabupaten">Kabupaten / Kota (Pilih / Ketik)</label>
                        </div>
                        <ul class="klinik-dropdown" id="kabupatenDropdown" role="listbox"></ul>
                        <div class="klinik-count" id="kabupatenCount"></div>
                        <div class="err-msg">Nama kabupaten/kota wajib diisi.</div>
                    </div>

                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="no_izin" name="no_izin_penerbitan" placeholder="No Izin" required>
                            <label for="no_izin">No Izin Penerbitan</label>
                        </div>
                        <div class="err-msg">Nomor izin penerbitan wajib diisi.</div>
                    </div>

                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="masa_berlaku_izin" name="masa_berlaku_izin" placeholder=" " required>
                            <label for="masa_berlaku_izin">Masa Berlaku Izin</label>
                        </div>
                        <div class="err-msg">Masa berlaku izin wajib diisi.</div>
                    </div>
                </section>

                <!-- ============ STEP 2 ============ -->
                <section class="panel" data-title="Data EICV & Permintaan">
                    <div class="panel-title"><i class="bi bi-clipboard2-pulse text-primary me-2"></i>Data EICV &amp; Permintaan</div>
                    <div class="panel-sub">Stok, pemakaian, dan jumlah vaksin yang diminta.</div>

                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="number" min="0" class="form-control" id="stok_eicv" name="jumlah_stock_akhir_eicv" placeholder="0" required>
                            <label for="stok_eicv">Jumlah Stock Akhir EICV</label>
                        </div>
                        <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                    </div>
                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="number" min="0" class="form-control" id="pakai_eicv" name="jumlah_pemakaian_eicv" placeholder="0" required>
                            <label for="pakai_eicv">Jumlah Pemakaian EICV</label>
                        </div>
                        <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                    </div>
                    <div class="field-wrap mb-3">
                        <div class="form-floating">
                            <input type="number" min="0" class="form-control" id="jml_minta" name="jumlah_permintaan" placeholder="0" required>
                            <label for="jml_minta">Jumlah Permintaan</label>
                        </div>
                        <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                    </div>
                </section>

                <!-- ============ STEP 3 ============ -->
                <section class="panel" data-title="Data Faktur & Vaksin">
                    <div class="panel-title"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Data Faktur &amp; Vaksin</div>
                    <div class="panel-sub">Detail pembelian dan vaksin yang diterima.</div>

                    <div class="row g-3">
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="tgl_faktur" name="tanggal_faktur" placeholder=" " required>
                                <label for="tgl_faktur">Tanggal Faktur</label>
                            </div>
                            <div class="err-msg">Tanggal faktur wajib diisi.</div>
                        </div>
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="distributor" name="nama_distributor" placeholder="Distributor" required>
                                <label for="distributor">Nama Distributor</label>
                            </div>
                            <div class="err-msg">Nama distributor wajib diisi.</div>
                        </div>
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="invoice" name="no_invoice" placeholder="Invoice" required>
                                <label for="invoice">No Faktur</label>
                            </div>
                            <div class="err-msg">No invoice wajib diisi.</div>
                        </div>
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="number" min="0" class="form-control" id="jml_beli" name="jumlah_pembelian" placeholder="0" required>
                                <label for="jml_beli">Jumlah Pembelian</label>
                            </div>
                            <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                        </div>
                        
                        <!-- ============ Custom Dropdown: Merek Vaksin ============ -->
                        <div class="col-md-6 field-wrap position-relative">
                            <div class="form-floating">
                                <input type="text" class="form-control pe-5" id="merek" name="merek_vaksin" placeholder="Merek Vaksin" autocomplete="off" required readonly style="cursor: pointer; background-color: var(--field);">
                                <label for="merek">Merek Vaksin</label>
                                <i class="bi bi-chevron-down position-absolute" id="merekIcon" style="right: 18px; top: 20px; font-size: 1.1rem; color: var(--muted); pointer-events: none; transition: transform .3s;"></i>
                            </div>
                            
                            <ul class="klinik-dropdown shadow-lg" id="merekDropdown" style="max-height: 190px;">
                                <?php
                                $query_merek_vaksin = @mysqli_query($conn, "SELECT nama_merek FROM master_merek_vaksin ORDER BY nama_merek ASC");
                                if ($query_merek_vaksin) {
                                    while ($rmv = mysqli_fetch_assoc($query_merek_vaksin)) {
                                        $val_merek = htmlspecialchars($rmv['nama_merek']);
                                        echo '<li data-val="' . $val_merek . '">' . $val_merek . '</li>';
                                    }
                                }
                                ?>
                            </ul>
                            <div class="err-msg">Merek vaksin wajib diisi.</div>
                        </div>
                        
                        <!-- ============ Custom Dropdown: Jenis Vaksin (DIPINDAH KE SINI) ============ -->
                        <div class="col-md-6 field-wrap position-relative">
                            <div class="form-floating">
                                <input type="text" class="form-control pe-5" id="jenis" name="jenis_vaksin" placeholder="Jenis Vaksin" autocomplete="off" required readonly style="cursor: pointer; background-color: var(--field);">
                                <label for="jenis">Jenis Vaksin</label>
                                <i class="bi bi-chevron-down position-absolute" id="jenisIcon" style="right: 18px; top: 20px; font-size: 1.1rem; color: var(--muted); pointer-events: none; transition: transform .3s;"></i>
                            </div>
                            
                            <ul class="klinik-dropdown shadow-lg" id="jenisDropdown" style="max-height: 190px;">
                                <?php
                                $query_jenis_vaksin = @mysqli_query($conn, "SELECT nama_jenis FROM master_jenis_vaksin ORDER BY nama_jenis ASC");
                                if ($query_jenis_vaksin) {
                                    while ($rjv = mysqli_fetch_assoc($query_jenis_vaksin)) {
                                        // Mengubah teks langsung menjadi KAPITAL
                                        $val_jenis = strtoupper(htmlspecialchars($rjv['nama_jenis']));
                                        echo '<li data-val="' . $val_jenis . '">' . $val_jenis . '</li>';
                                    }
                                }
                                ?>
                            </ul>
                            <div class="err-msg">Jenis vaksin wajib diisi.</div>
                        </div>
                        <!-- =========================================================================== -->

                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="exp_date" name="exp_date" placeholder=" " required>
                                <label for="exp_date">Exp Date</label>
                            </div>
                            <div class="err-msg">Exp Date Vaksin MM</div>
                        </div>

                        <!-- ============ Batch Vaksin (DIPINDAH KE SINI) ============ -->
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="batch" name="batch_vaksin" placeholder="Batch" required>
                                <label for="batch">Batch Vaksin</label>
                            </div>
                            <div class="err-msg">Batch vaksin wajib diisi.</div>
                        </div>
                        <!-- ========================================================= -->

                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="number" min="0" class="form-control" id="vaksin_mm" name="jumlah_vaksin_mm" placeholder="0" required>
                                <label for="vaksin_mm">Jumlah Vaksin MM</label>
                            </div>
                            <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                        </div>
                        <div class="col-md-6 field-wrap">
                            <div class="form-floating">
                                <input type="number" min="0" class="form-control" id="vaksin_polio" name="jumlah_vaksin_polio" placeholder="0" required>
                                <label for="vaksin_polio">Jumlah Vaksin Polio</label>
                            </div>
                            <div class="err-msg">Isi dengan angka 0 atau lebih.</div>
                        </div>
                    </div>
                </section>

                <!-- ============ STEP 4 ============ -->
                <section class="panel" data-title="Dokumen & Konfirmasi">
                    <div class="panel-title"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Dokumen &amp; Konfirmasi</div>
                    <div class="panel-sub">Unggah file penerbitan EICV, lalu periksa kembali data Anda.</div>

                    <div class="dropzone" id="dropzone">
                        <input type="file" name="file_syarat" id="fileInput" accept=".pdf,.jpg,.jpeg,.png">
                        <i class="bi bi-cloud-arrow-up-fill dz-icon"></i>
                        <div class="fw-bold mt-2">Tarik &amp; lepas file di sini</div>
                        <div class="text-muted small">atau klik untuk memilih &bull; PDF / JPG / PNG &bull; maks. 5 MB</div>
                    </div>
                    <div class="file-chip" id="fileChip">
                        <div class="thumb" id="fileThumb"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="info"><b id="fileName"></b><small id="fileSize"></small></div>
                        <button type="button" class="icon-btn" id="fileRemove" aria-label="Hapus file"><i class="bi bi-x-lg"></i></button>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3"><i class="bi bi-card-checklist text-primary me-2"></i>Ringkasan Data</h6>
                    <div id="reviewBox"></div>
                </section>

                <div class="nav-btns">
                    <button type="button" class="btn btn-ghost" id="btnPrev" style="visibility:hidden;"><i class="bi bi-arrow-left me-2"></i>Kembali</button>
                    <button type="button" class="btn btn-grad" id="btnNext">Lanjut <i class="bi bi-arrow-right ms-2"></i></button>
                    <button type="submit" name="submit_permohonan" value="1" class="btn btn-grad d-none" id="btnSubmit">
                        <span class="btn-label">Kirim Permohonan <i class="bi bi-send ms-2"></i></span>
                    </button>
                </div>

                <div class="draft-note"><i class="bi bi-check-circle-fill"></i> <span id="draftStatus">Draf tersimpan otomatis di perangkat ini.</span> &bull; <button type="button" class="btn-clear" id="clearDraft">Hapus draf</button></div>
            </form>
        </div>
    </div>
</div>

<div class="toast-mini" id="toast"></div>

<script>
(function () {
    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => [...c.querySelectorAll(s)];
    const form = $('#mainForm'), panels = $$('.panel'), dots = $$('.step-dot');
    const btnPrev = $('#btnPrev'), btnNext = $('#btnNext'), btnSubmit = $('#btnSubmit');
    let cur = 0;

    const root = document.documentElement, themeBtn = $('#themeToggle');
    const updateThemeIcon = t => {
        themeBtn.innerHTML = t === 'dark' ? '<i class="bi bi-sun-fill"></i>' : '<i class="bi bi-moon-stars-fill"></i>';
    };
    updateThemeIcon(root.getAttribute('data-theme') || 'light');

    themeBtn.addEventListener('click', () => {
        const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', current);
        root.setAttribute('data-bs-theme', current);
        updateThemeIcon(current);
        try { localStorage.setItem('eicv-theme', current); } catch (e) {}
        toast('Tema diganti');
    });

    let tt; function toast(msg) { const t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 2600); }

    const pad = n => String(n).padStart(2, '0'), now = new Date();
    const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    $('#tgl_mohon').value = todayStr;      const DRAFT_KEY = 'eicv-draft-v1'; const textFields = () => $$('input:not([type=file]):not([type=submit]), textarea, select', form);
    function saveDraft() {
        const data = {}; textFields().forEach(f => { if (f.name) data[f.name] = f.value; });
        try { localStorage.setItem(DRAFT_KEY, JSON.stringify(data)); $('#draftStatus').textContent = 'Draf tersimpan otomatis ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); } catch (e) {}
    }
    function loadDraft() {
        try {
            const d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); if (!d) return;
            let n = 0; textFields().forEach(f => { if (d[f.name] !== undefined && d[f.name] !== '') { f.value = d[f.name]; n++; } });
            if (n > 1) toast('Draf sebelumnya dipulihkan');
        } catch (e) {}
    }
    loadDraft();
    let dt; form.addEventListener('input', () => { clearTimeout(dt); dt = setTimeout(() => saveDraft(), 900); });
    $('#clearDraft').addEventListener('click', () => {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
        form.reset(); $('#tgl_mohon').value = todayStr; clearFile();
        $$('.form-control').forEach(f => f.classList.remove('valid', 'invalid')); goTo(0); toast('Draf dihapus'); });      function validateField(f) { const wrap = f.closest('.field-wrap'); let ok = f.checkValidity() && (f.type === 'number' ? f.value !== '' && +f.value >= 0 : f.value.trim() !== ''); f.classList.toggle('invalid', !ok); f.classList.toggle('valid', ok); if (wrap) wrap.classList.toggle('has-error', !ok); return ok; } function validateStep(i) { let first = null; $$
('input[required]', panels[i]).forEach(f => { if (!validateField(f) && !first) first = f; });
        if (first) { first.focus(); toast('Mohon lengkapi isian yang ditandai merah'); return false; }
        return true;
    }

    function goTo(i, back) {
        cur = i;
        panels.forEach((p, k) => { p.classList.toggle('active', k === i); p.classList.toggle('back', !!back); });
        dots.forEach((d, k) => { d.classList.toggle('active', k === i); d.classList.toggle('done', k < i); d.querySelector('.dot').innerHTML = k < i ? '<i class="bi bi-check-lg"></i>' : k + 1; });
        $('#stepFill').style.width = (i / (dots.length - 1)) * 76 + '%';
        btnPrev.style.visibility = i === 0 ? 'hidden' : 'visible';
        btnNext.classList.toggle('d-none', i === panels.length - 1);
        btnSubmit.classList.toggle('d-none', i !== panels.length - 1);
        if (i === panels.length - 1) buildReview();
        $('.stepper').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    btnNext.addEventListener('click', () => { if (validateStep(cur)) goTo(cur + 1); });
    btnPrev.addEventListener('click', () => goTo(cur - 1, true));
    dots.forEach((d, k) => d.addEventListener('click', () => { if (k < cur) goTo(k, true); }));

    const labels = {
        tanggal_permohonan: 'Tanggal Permohonan', nama_pemohon: 'Nama Pemohon', nama_klinik: 'Nama Klinik', nama_kabupaten: 'Kabupaten / Kota', no_izin_penerbitan: 'No Izin', masa_berlaku_izin: 'Masa Berlaku Izin',
        jumlah_stock_akhir_eicv: 'Stock Akhir EICV', jumlah_pemakaian_eicv: 'Pemakaian EICV', jumlah_permintaan: 'Permintaan Vaksin',
        tanggal_faktur: 'Tanggal Faktur', nama_distributor: 'Distributor', no_invoice: 'No Faktur', jumlah_pembelian: 'Jumlah Pembelian', merek_vaksin: 'Merek Vaksin',
        batch_vaksin: 'Batch', exp_date: 'Exp Date', jenis_vaksin: 'Jenis Vaksin', jumlah_vaksin_mm: 'Vaksin MM', jumlah_vaksin_polio: 'Vaksin Polio'
    };
    const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtVal = f => f.type === 'date' && f.value ? new Date(f.value + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : f.value;
    function buildReview() {
        const titles = ['Pemohon & Klinik', 'EICV & Permintaan', 'Faktur & Vaksin'];
        $('#reviewBox').innerHTML = titles.map((t, k) =>
            `<div class="review-group"><h6>${t}<button type="button" data-edit="${k}"><i class="bi bi-pencil"></i> Ubah</button></h6>` +
            $$('input[name]', panels[k]).map(f => `<div class="review-row"><span>${labels[f.name] || f.name}</span><span>${esc(fmtVal(f) || '-')}</span></div>`).join('') + '</div>'
        ).join('');
        $$('[data-edit]',$('#reviewBox')).forEach(b => b.addEventListener('click', () => goTo(+b.dataset.edit, true)));
    }

    /* === LOGIKA DROPDOWN KLINIK === */
    const daftarKlinik = <?php echo json_encode($daftar_klinik); ?>;
    const inp = $('#nm_klinik'), dd = $('#klinikDropdown'), cnt = $('#klinikCount');
    let idx = -1, shown = [];
    const HINT_DEFAULT = 'Pilih dari ' + daftarKlinik.length + ' klinik/RS yang sudah terdaftar, atau ketik nama klinik Anda sendiri.';
    cnt.textContent = HINT_DEFAULT;
    const hl = (name, kw) => { if (!kw) return esc(name); const i = name.toLowerCase().indexOf(kw); return i < 0 ? esc(name) : esc(name.slice(0, i)) + '<mark>' + esc(name.slice(i, i + kw.length)) + '</mark>' + esc(name.slice(i + kw.length)); };
    
    function renderDD() {
        const raw = inp.value.replace(/\s+/g, ' ').trim(), kw = raw.toLowerCase();
        const matches = daftarKlinik.filter(k => k.toLowerCase().includes(kw));
        const exact = daftarKlinik.some(k => k.toLowerCase() === kw);
        shown = []; idx = -1;
        if (raw && !exact) shown.push({ label: raw, custom: true });
        matches.forEach(k => shown.push({ label: k }));
        dd.innerHTML = shown.map((s, i) => s.custom
            ? `<li role="option" class="custom" data-i="${i}"><i class="bi bi-pencil-square"></i><span>Gunakan nama ini: <b>${esc(s.label)}</b> <small>(ketik manual)</small></span></li>`
            : `<li role="option" data-i="${i}"><i class="bi bi-hospital"></i><span>${hl(s.label, kw)}</span></li>`).join('');
        dd.style.display = shown.length ? 'block' : 'none'; inp.setAttribute('aria-expanded', shown.length ? 'true' : 'false');
    }
    
    function closeDD() { dd.style.display = 'none'; inp.setAttribute('aria-expanded', 'false'); }
    function pick(i) { inp.value = shown[i].label; closeDD(); validateField(inp); saveDraft(); $('#nm_kabupaten').focus(); } inp.addEventListener('blur', () => { inp.value = inp.value.replace(/\s+/g, ' ').trim(); }); function setActive(n) { const items = $$('li[data-i]', dd); if (!items.length) return;
        idx = (n + items.length) % items.length;
        items.forEach((li, k) => li.classList.toggle('active', k === idx)); items[idx].scrollIntoView({ block: 'nearest' });
    }
    inp.addEventListener('focus', renderDD);
    inp.addEventListener('input', renderDD);
    inp.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (dd.style.display === 'none') renderDD(); setActive(idx + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(idx - 1); }
        else if (e.key === 'Enter' && dd.style.display === 'block') { e.preventDefault(); e.stopPropagation(); if (idx >= 0) pick(idx); else { closeDD(); if (inp.value.trim()) { validateField(inp); $('#nm_kabupaten').focus(); } } }
        else if (e.key === 'Escape' || e.key === 'Tab') closeDD();
    });
    dd.addEventListener('mousedown', e => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });


    /* === LOGIKA DROPDOWN KABUPATEN / KOTA (sama seperti klinik) === */
    const daftarKab = <?php echo json_encode($daftar_kabupaten); ?>;
    const kInp = $('#nm_kabupaten'), kDD = $('#kabupatenDropdown'), kCnt = $('#kabupatenCount');
    let kIdx = -1, kShown = [];
    kCnt.textContent = 'Pilih dari ' + daftarKab.length + ' kabupaten/kota, atau ketik nama kabupaten/kota sendiri.';

    function renderKab() {
        const raw = kInp.value.replace(/\s+/g, ' ').trim(), kw = raw.toLowerCase();
        const matches = daftarKab.filter(k => k.toLowerCase().includes(kw));
        const exact = daftarKab.some(k => k.toLowerCase() === kw);
        kShown = []; kIdx = -1;
        if (raw && !exact) kShown.push({ label: raw, custom: true });
        matches.forEach(k => kShown.push({ label: k }));
        kDD.innerHTML = kShown.map((s, i) => s.custom
            ? `<li role="option" class="custom" data-i="${i}"><i class="bi bi-pencil-square"></i><span>Gunakan nama ini: <b>${esc(s.label)}</b> <small>(ketik manual)</small></span></li>`
            : `<li role="option" data-i="${i}"><i class="bi bi-geo-alt-fill"></i><span>${hl(s.label, kw)}</span></li>`).join('');
        kDD.style.display = kShown.length ? 'block' : 'none'; kInp.setAttribute('aria-expanded', kShown.length ? 'true' : 'false');
    }
    function closeKabDD() { kDD.style.display = 'none'; kInp.setAttribute('aria-expanded', 'false'); }
    function pickKab(i) { kInp.value = kShown[i].label; closeKabDD(); validateField(kInp); saveDraft(); $('#no_izin').focus(); }
    kInp.addEventListener('blur', () => { kInp.value = kInp.value.replace(/\s+/g, ' ').trim(); });
    function setActiveKab(n) {
        const items = $$('li[data-i]', kDD); if (!items.length) return;
        kIdx = (n + items.length) % items.length;
        items.forEach((li, k) => li.classList.toggle('active', k === kIdx)); items[kIdx].scrollIntoView({ block: 'nearest' });
    }
    kInp.addEventListener('focus', renderKab);
    kInp.addEventListener('input', renderKab);
    kInp.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (kDD.style.display === 'none') renderKab(); setActiveKab(kIdx + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActiveKab(kIdx - 1); }
        else if (e.key === 'Enter' && kDD.style.display === 'block') { e.preventDefault(); e.stopPropagation(); if (kIdx >= 0) pickKab(kIdx); else { closeKabDD(); if (kInp.value.trim()) { validateField(kInp); $('#no_izin').focus(); } } }
        else if (e.key === 'Escape' || e.key === 'Tab') closeKabDD();
    });
    kDD.addEventListener('mousedown', e => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pickKab(+li.dataset.i); } });
    document.addEventListener('click', e => { if (!kInp.contains(e.target) && !kDD.contains(e.target)) closeKabDD(); });


    /* === LOGIKA CUSTOM DROPDOWN MEREK VAKSIN === */
    const merekInp = $('#merek'), merekDD = $('#merekDropdown'), merekIcon = $('#merekIcon'); const merekItems = $$('li[data-val]', merekDD);

    function closeMerekDD() {
        merekDD.style.display = 'none';
        merekIcon.style.transform = 'none';
        merekInp.setAttribute('aria-expanded', 'false');
    }

    merekInp.addEventListener('click', (e) => {
        e.stopPropagation();
        closeDD(); // Tutup klinik
        closeKabDD(); // Tutup kabupaten
        if (typeof closeJenisDD === 'function') closeJenisDD(); // Tutup jenis
        const isShow = merekDD.style.display === 'block';
        merekDD.style.display = isShow ? 'none' : 'block';
        merekIcon.style.transform = isShow ? 'none' : 'rotate(180deg)';
        merekInp.setAttribute('aria-expanded', !isShow);
    });

    merekItems.forEach(li => {
        li.addEventListener('click', (e) => {
            e.stopPropagation();
            merekInp.value = li.dataset.val;
            validateField(merekInp);
            saveDraft();
            closeMerekDD();
            $('#jenis').focus(); // Otomatis pindah ke Jenis Vaksin setelah memilih Merek
        });
    });


    /* === LOGIKA CUSTOM DROPDOWN JENIS VAKSIN === */
    const jenisInp = $('#jenis'), jenisDD = $('#jenisDropdown'), jenisIcon = $('#jenisIcon'); const jenisItems = $$('li[data-val]', jenisDD);

    function closeJenisDD() {
        jenisDD.style.display = 'none';
        jenisIcon.style.transform = 'none';
        jenisInp.setAttribute('aria-expanded', 'false');
    }

    jenisInp.addEventListener('click', (e) => {
        e.stopPropagation();
        closeDD(); // Tutup klinik
        closeKabDD(); // Tutup kabupaten
        closeMerekDD(); // Tutup merek
        const isShow = jenisDD.style.display === 'block';
        jenisDD.style.display = isShow ? 'none' : 'block';
        jenisIcon.style.transform = isShow ? 'none' : 'rotate(180deg)';
        jenisInp.setAttribute('aria-expanded', !isShow);
    });

    jenisItems.forEach(li => {
        li.addEventListener('click', (e) => {
            e.stopPropagation();
            jenisInp.value = li.dataset.val;
            validateField(jenisInp);
            saveDraft();
            closeJenisDD();
            $('#exp_date').focus(); // Otomatis pindah ke Exp Date setelah memilih Jenis
        });
    });


    /* Tutup Dropdown jika klik di luar area */
    document.addEventListener('click', e => { 
        if (!inp.contains(e.target) && !dd.contains(e.target)) closeDD(); 
        if (!merekInp.contains(e.target) && !merekDD.contains(e.target)) closeMerekDD();
        if (!jenisInp.contains(e.target) && !jenisDD.contains(e.target)) closeJenisDD();
    });


    /* === LOGIKA FILE UPLOAD === */
    const fileInput = $('#fileInput'), dz = $('#dropzone'), chip = $('#fileChip');
    const MAX = 5 * 1024 * 1024, OK_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    const fmtSize = b => b > 1048576 ? (b / 1048576).toFixed(2) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    function clearFile() { fileInput.value = ''; chip.classList.remove('show'); dz.style.display = ''; }
    function showFile(f) {
        const ext = f.name.split('.').pop().toLowerCase();
        if (!OK_EXT.includes(ext)) { clearFile(); toast('Format tidak didukung (PDF/JPG/PNG).'); return; }
        if (f.size > MAX) { clearFile(); toast('Ukuran file melebihi 5 MB'); return; }
        $('#fileName').textContent = f.name; $('#fileSize').textContent = fmtSize(f.size) + ' • ' + ext.toUpperCase();
        const th = $('#fileThumb');
        if (['jpg', 'jpeg', 'png'].includes(ext)) { const img = new Image(); img.src = URL.createObjectURL(f); th.innerHTML = ''; th.appendChild(img); }
        else th.innerHTML = '<i class="bi bi-file-earmark-pdf-fill"></i>';
        chip.classList.add('show'); dz.style.display = 'none';
    }
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) showFile(fileInput.files[0]); });
    $('#fileRemove').addEventListener('click', clearFile);

    form.addEventListener('submit', e => {
        for (let i = 0; i < panels.length - 1; i++) { if (!validateStep(i)) { e.preventDefault(); goTo(i, true); return; } }
        btnSubmit.querySelector('.btn-label').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim…';
        try { localStorage.removeItem(DRAFT_KEY); } catch (err) {}
    });

    const bar = $('#scrollProgress');
    addEventListener('scroll', () => { const h = document.documentElement; bar.style.width = (h.scrollTop / (h.scrollHeight - h.clientHeight) * 100) + '%'; }, { passive: true });

    goTo(0);
    window.scrollTo(0, 0);
})();
</script>

<?php include 'includes/footer.php'; ?>
