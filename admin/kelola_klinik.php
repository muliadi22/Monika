<?php
// admin/kelola_klinik.php
// Mengelola daftar nama klinik yang muncul sebagai saran pada formulir pemohon (ajukan_permohonan.php).
// Menghapus nama di sini TIDAK menghapus data permohonan yang sudah masuk.
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit(); }
if (empty($_SESSION['csrf_klinik'])) $_SESSION['csrf_klinik'] = bin2hex(random_bytes(32));

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rapikan($v) { return preg_replace('/\s+/u', ' ', trim((string)$v)); }
function kunci($v) { return mb_strtolower(rapikan($v), 'UTF-8'); }

$nama_admin = $_SESSION['nama_lengkap'] ?? 'Admin';

// Tabel daftar sembunyi (nama klinik yang tidak lagi disarankan ke pemohon). Dibuat otomatis bila belum ada.
try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS klinik_disembunyikan (
        nama_norm VARCHAR(191) NOT NULL PRIMARY KEY,
        nama_asli VARCHAR(255) NOT NULL,
        dihapus_oleh VARCHAR(100) NULL,
        dihapus_pada DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cek_kol = mysqli_query($conn, "SHOW COLUMNS FROM klinik_disembunyikan LIKE 'sembunyi_daftar'");
    if ($cek_kol && $cek_kol->num_rows === 0) mysqli_query($conn, "ALTER TABLE klinik_disembunyikan ADD COLUMN sembunyi_daftar TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $ex) {}

// ==========================================
// AKSI (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $ok_token = isset($_POST['csrf']) && hash_equals($_SESSION['csrf_klinik'], (string)$_POST['csrf']);
    $pesan = ''; $tipe = 'success';

    if (!$ok_token) {
        $pesan = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.'; $tipe = 'error';
    } else {
        try {
            if ($aksi === 'hapus') {
                $daftar = $_POST['nama'] ?? [];
                if (!is_array($daftar)) $daftar = [$daftar];
                $n = 0;
                $st_m = mysqli_prepare($conn, "DELETE FROM master_klinik WHERE LOWER(TRIM(nama_klinik)) = ?");
                $st_h = mysqli_prepare($conn, "INSERT INTO klinik_disembunyikan (nama_norm, nama_asli, dihapus_oleh, dihapus_pada) VALUES (?, ?, ?, NOW())
                                               ON DUPLICATE KEY UPDATE nama_asli = VALUES(nama_asli), dihapus_oleh = VALUES(dihapus_oleh), dihapus_pada = VALUES(dihapus_pada), sembunyi_daftar = 0");
                foreach ($daftar as $nm) {
                    $asli = rapikan($nm); $nk = kunci($nm);
                    if ($nk === '') continue;
                    try { mysqli_stmt_bind_param($st_m, 's', $nk); mysqli_stmt_execute($st_m); } catch (Throwable $ex) {}   // master_klinik mungkin belum ada
                    mysqli_stmt_bind_param($st_h, 'sss', $nk, $asli, $nama_admin); mysqli_stmt_execute($st_h);
                    $n++;
                }
                $pesan = $n ? "$n nama klinik dihapus dari daftar saran." : 'Tidak ada nama yang dipilih.';
                if (!$n) $tipe = 'info';

            } elseif ($aksi === 'kembalikan') {
                $nk = kunci($_POST['nama'] ?? '');
                if ($nk !== '') {
                    $st = mysqli_prepare($conn, "DELETE FROM klinik_disembunyikan WHERE nama_norm = ?");
                    mysqli_stmt_bind_param($st, 's', $nk); mysqli_stmt_execute($st);
                    $pesan = 'Nama klinik dikembalikan ke daftar saran.';
                }

            } elseif ($aksi === 'kembalikan_semua') {
                $n = count(array_filter(dataKlinik($conn)[1], fn($h) => empty($h['arsip'])));
                mysqli_query($conn, "DELETE FROM klinik_disembunyikan WHERE sembunyi_daftar = 0");
                $pesan = "$n nama klinik dikembalikan ke daftar saran.";

            } elseif ($aksi === 'permanen_semua') {
                [, $hid] = dataKlinik($conn);
                $st_del = mysqli_prepare($conn, "DELETE FROM klinik_disembunyikan WHERE nama_norm = ?");
                $st_arsip = mysqli_prepare($conn, "UPDATE klinik_disembunyikan SET sembunyi_daftar = 1 WHERE nama_norm = ?");
                $n = 0;
                foreach ($hid as $k => $h) {
                    if (!empty($h['arsip'])) continue;
                    // Dipakai permohonan: hilang dari daftar ini tetapi tetap tidak disarankan ke pemohon
                    $st = ((int)($h['jml'] ?? 0) > 0) ? $st_arsip : $st_del;
                    mysqli_stmt_bind_param($st, 's', $k); mysqli_stmt_execute($st); $n++;
                }
                $pesan = "$n nama dihapus permanen dari daftar.";
                if (!$n) $tipe = 'info';

            } elseif ($aksi === 'hapus_permanen') {
                $nk = kunci($_POST['nama'] ?? '');
                if ($nk === '') { $pesan = 'Nama tidak valid.'; $tipe = 'error'; }
                else {
                    $st = mysqli_prepare($conn, "SELECT COUNT(*) FROM permohonan WHERE LOWER(TRIM(nama_klinik)) = ?");
                    mysqli_stmt_bind_param($st, 's', $nk); mysqli_stmt_execute($st);
                    $pakai = (int)mysqli_fetch_row(mysqli_stmt_get_result($st))[0];
                    $sql = $pakai > 0 ? "UPDATE klinik_disembunyikan SET sembunyi_daftar = 1 WHERE nama_norm = ?" : "DELETE FROM klinik_disembunyikan WHERE nama_norm = ?";
                    $st = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($st, 's', $nk); mysqli_stmt_execute($st);
                    $pesan = 'Nama klinik dihapus permanen dari daftar.';
                }

            } elseif ($aksi === 'tambah') {
                $asli = rapikan($_POST['nama'] ?? ''); $nk = kunci($asli);
                if ($nk === '') { $pesan = 'Nama klinik tidak boleh kosong.'; $tipe = 'error'; }
                else {
                    $st = mysqli_prepare($conn, "DELETE FROM klinik_disembunyikan WHERE nama_norm = ?");
                    mysqli_stmt_bind_param($st, 's', $nk); mysqli_stmt_execute($st);
                    $st = mysqli_prepare($conn, "SELECT 1 FROM master_klinik WHERE LOWER(TRIM(nama_klinik)) = ? LIMIT 1");
                    mysqli_stmt_bind_param($st, 's', $nk); mysqli_stmt_execute($st);
                    $ada = mysqli_stmt_get_result($st)->num_rows > 0;
                    if (!$ada) {
                        $st = mysqli_prepare($conn, "INSERT INTO master_klinik (nama_klinik) VALUES (?)");
                        mysqli_stmt_bind_param($st, 's', $asli); mysqli_stmt_execute($st);
                    }
                    $pesan = 'Nama klinik ditambahkan ke daftar saran.';
                }
            }
        } catch (Throwable $ex) {
            $pesan = 'Terjadi kesalahan basis data. Pastikan tabel master_klinik tersedia.'; $tipe = 'error';
        }
    }
    $_SESSION['flash_klinik'] = [$tipe, $pesan];
    header("Location: kelola_klinik.php"); exit();
}

$flash = $_SESSION['flash_klinik'] ?? null; unset($_SESSION['flash_klinik']);

// ==========================================
// KUMPULKAN DATA
// ==========================================
function dataKlinik($conn) {
    $hidden = [];
    $q = @mysqli_query($conn, "SELECT nama_norm, nama_asli, dihapus_oleh, dihapus_pada, sembunyi_daftar FROM klinik_disembunyikan ORDER BY dihapus_pada DESC");
    if ($q) while ($r = mysqli_fetch_assoc($q)) { $r['arsip'] = !empty($r['sembunyi_daftar']); $hidden[$r['nama_norm']] = $r; }

    $aktif = [];   // kunci => ['nama'=>, 'master'=>bool, 'jml'=>int]
    $q = @mysqli_query($conn, "SELECT nama_klinik FROM master_klinik");
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $nm = rapikan($r['nama_klinik']); $k = kunci($nm);
        if ($k === '' || isset($hidden[$k])) continue;
        $aktif[$k] = ['nama' => $nm, 'master' => true, 'jml' => 0];
    }
    $q = @mysqli_query($conn, "SELECT nama_klinik, COUNT(*) AS jml FROM permohonan WHERE nama_klinik IS NOT NULL AND nama_klinik != '' GROUP BY nama_klinik");
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $nm = rapikan($r['nama_klinik']); $k = kunci($nm);
        if ($k === '') continue;
        if (isset($hidden[$k])) { $hidden[$k]['jml'] = ($hidden[$k]['jml'] ?? 0) + (int)$r['jml']; continue; }
        if (!isset($aktif[$k])) $aktif[$k] = ['nama' => $nm, 'master' => false, 'jml' => 0];
        $aktif[$k]['jml'] += (int)$r['jml'];
    }
    uasort($aktif, fn($a, $b) => strcasecmp($a['nama'], $b['nama']));
    return [$aktif, $hidden];
}

[$aktif, $hidden] = dataKlinik($conn);
$tampil_hapus = array_filter($hidden, fn($h) => empty($h['arsip']));   // yang masih ditampilkan di kotak "Sudah Dihapus"

// Endpoint pembaruan langsung (dipanggil otomatis oleh halaman ini)
if (isset($_GET['ajax'])) {
    $out = [];
    foreach ($aktif as $d) $out[] = ['nama' => $d['nama'], 'master' => $d['master'], 'jml' => $d['jml']];
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode(['sig' => md5(json_encode($out) . count($hidden)), 'aktif' => $out, 'hidden' => count($hidden)]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Klinik - Admin Panel</title>
    <script>
        (function () {
            var t = 'light';
            try { t = localStorage.getItem('eicv-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root, [data-theme="light"] {
            --bg: #f8fafc; --surface: rgba(255,255,255,.92); --surface-2: #ffffff; --soft: #f1f5f9;
            --border: #e2e8f0; --text: #1e293b; --muted: #64748b; --primary: #4f46e5;
            --grad: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%); --nav: rgba(255,255,255,.9);
            --shadow: 0 20px 40px -15px rgba(0,0,0,.07);
        }
        [data-theme="dark"] {
            --bg: #0b1020; --surface: rgba(21,27,48,.92); --surface-2: #151b30; --soft: #1b2340;
            --border: #29325a; --text: #e6e9f5; --muted: #98a2c3; --primary: #8b93ff;
            --nav: rgba(14,19,38,.9); --shadow: 0 20px 40px -15px rgba(0,0,0,.6);
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; background: var(--bg); color: var(--text); }
        .navbar-admin { background: var(--nav); border-bottom: 1px solid var(--border); }
        .brand-text { background: var(--grad); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .user-chip { background: var(--surface-2); border: 1px solid var(--border); color: var(--text); }
        .theme-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); display: grid; place-items: center; }
        [data-theme="light"] .theme-btn .bi-sun-fill, [data-theme="dark"] .theme-btn .bi-moon-stars-fill { display: none; }
        .btn-executive { border-radius: 50px; padding: .6rem 1.4rem; font-weight: 600; font-size: .9rem; }
        .btn-logout-merah { background: #ef4444 !important; color: #fff !important; border: 1px solid #dc2626 !important; }
        .btn-back { background: var(--surface-2); color: var(--text); border: 1px solid var(--border); text-decoration: none; display: inline-flex; align-items: center; }
        .btn-back:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .glass-card { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; box-shadow: var(--shadow); }
        .ink { color: var(--text); } .soft { color: var(--muted); }
        .form-control { background: var(--soft); color: var(--text); border: 1px solid var(--border); border-radius: 14px; }
        .form-control:focus { background: var(--surface-2); color: var(--text); border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,.2); }
        .table { --bs-table-bg: transparent; --bs-table-color: var(--text); --bs-table-border-color: var(--border); }
        .table thead th { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; background: var(--soft); border-bottom: 1px solid var(--border); }
        .table td { vertical-align: middle; }
        .chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 12px; border-radius: 30px; font-size: .74rem; font-weight: 700; }
        .chip-master { background: rgba(99,102,241,.14); color: var(--primary); }
        .chip-riwayat { background: rgba(245,158,11,.16); color: #b45309; }
        [data-theme="dark"] .chip-riwayat { color: #fcd34d; }
        .btn-del { border-radius: 50px; font-weight: 700; font-size: .8rem; padding: 4px 14px; background: rgba(239,68,68,.12); color: #dc2626; border: 1px solid rgba(239,68,68,.35); }
        .btn-del:hover { background: #ef4444; color: #fff; }
        .tb-bulk { display: none; }
        .tb-bulk.show { display: inline-flex; }
        mark.cari { background: linear-gradient(120deg, rgba(250,204,21,.65), rgba(251,146,60,.5)); color: #713f12; padding: 0 3px; border-radius: 5px; font-weight: 800; }
        [data-theme="dark"] mark.cari { background: rgba(250,204,21,.3); color: #fde68a; }
        tr.sembunyi { display: none; }
        /* Notifikasi di tengah atas */
        .notif-wrap { position: fixed; top: 22px; left: 0; right: 0; z-index: 6000; display: flex; flex-direction: column; align-items: center; gap: 10px; pointer-events: none; padding: 0 12px; }
        .notif { position: relative; overflow: hidden; display: flex; align-items: center; gap: 14px; min-width: min(360px, 100%); max-width: min(520px, 100%); padding: 14px 22px 16px 16px; border-radius: 20px; background: var(--surface-2); color: var(--text); border: 1px solid var(--border); box-shadow: 0 24px 50px -12px rgba(15,23,42,.35); opacity: 0; transform: translateY(-24px) scale(.96); transition: transform .35s cubic-bezier(.2,.8,.2,1), opacity .3s; pointer-events: auto; }
        .notif.tampil { opacity: 1; transform: none; }
        .notif-ico { flex: none; width: 42px; height: 42px; border-radius: 14px; display: grid; place-items: center; font-size: 1.3rem; color: #fff; }
        .notif-hapus .notif-ico { background: linear-gradient(135deg, #f87171, #dc2626); }
        .notif-baru .notif-ico { background: linear-gradient(135deg, #34d399, #059669); }
        .notif-info .notif-ico { background: var(--grad); }
        .notif b { display: block; font-size: .95rem; line-height: 1.25; }
        .notif small { color: var(--muted); font-size: .8rem; }
        .notif-bar { position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; transform-origin: left; animation: notifBar var(--dur, 3.2s) linear forwards; }
        .notif-hapus .notif-bar { background: #ef4444; } .notif-baru .notif-bar { background: #10b981; } .notif-info .notif-bar { background: var(--primary); }
        @keyframes notifBar { to { transform: scaleX(0); } }
        .item-hapus { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .item-hapus:last-child { border-bottom: 0; }
        .btn-mini { flex: none; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: .8rem; border: 1px solid var(--border); background: var(--soft); color: var(--muted); cursor: pointer; transition: .2s; padding: 0; }
        .btn-mini-ok:hover { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-mini-del { color: #dc2626; background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); }
        .btn-mini-del:hover { background: #ef4444; border-color: #ef4444; color: #fff; }
        .scroll-area { max-height: 62vh; overflow-y: auto; }
        .scroll-area thead th { position: sticky; top: 0; z-index: 1; }
    </style>
</head>
<body>

<nav class="navbar navbar-admin sticky-top py-3 mb-4">
    <div class="container-fluid px-3 px-md-5 d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2 m-0" href="dashboard.php">
            <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center shadow-sm" style="background: var(--grad); width: 38px; height: 38px;"><i class="bi bi-shield-lock-fill fs-6"></i></div>
            <span class="brand-text">Admin Portal</span>
        </a>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <button type="button" class="theme-btn" id="themeToggle" aria-label="Ganti tema"><i class="bi bi-moon-stars-fill"></i><i class="bi bi-sun-fill"></i></button>
            <div class="user-chip d-none d-md-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-person-circle fs-5" style="color: var(--primary);"></i><span class="fw-semibold small"><?php echo e($nama_admin); ?></span>
            </div>
            <a href="logout.php" class="btn btn-executive btn-logout-merah px-3 py-2 small"><i class="bi bi-box-arrow-right me-1"></i> Keluar</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-md-5 mb-5">
    <div class="mb-3"><a href="dashboard.php" class="btn btn-executive btn-back shadow-sm"><i class="bi bi-arrow-left-circle-fill me-2" style="color: var(--primary);"></i> Kembali ke Dashboard</a></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h4 class="fw-bold ink mb-1"><i class="bi bi-hospital me-2" style="color: var(--primary);"></i>Kelola Nama Klinik</h4>
                        <p class="soft small mb-0">Nama di daftar ini muncul sebagai saran pada formulir pemohon. Menghapus nama <b>tidak</b> menghapus data permohonan yang sudah masuk.</p>
                    </div>
                    <span class="chip chip-master" id="jmlKlinik"><?php echo count($aktif); ?> nama</span>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <input type="text" id="cariKlinik" class="form-control flex-grow-1" style="min-width:220px; max-width:420px;" placeholder="Cari nama klinik..." autocomplete="off">
                    <button type="button" id="btnHapusPilih" class="btn btn-executive btn-danger text-white tb-bulk align-items-center gap-2"><i class="bi bi-trash3-fill"></i> Hapus terpilih (<span id="jmlPilih">0</span>)</button>
                </div>

                <div class="scroll-area rounded-4 border" style="border-color: var(--border) !important;">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th style="width:46px" class="text-center"><input type="checkbox" class="form-check-input" id="cekSemua"></th>
                            <th>Nama Klinik</th>
                            <th class="text-center">Sumber</th>
                            <th class="text-center">Permohonan</th>
                            <th class="text-center" style="width:120px">Aksi</th>
                        </tr></thead>
                        <tbody id="isiKlinik">
                        <?php foreach ($aktif as $k => $d): ?>
                            <tr data-nama="<?php echo e($d['nama']); ?>">
                                <td class="text-center"><input type="checkbox" class="form-check-input cek"></td>
                                <td class="fw-semibold ink nm"><?php echo e($d['nama']); ?></td>
                                <td class="text-center"><?php echo $d['master'] ? '<span class="chip chip-master"><i class="bi bi-database-fill"></i> Master</span>' : '<span class="chip chip-riwayat"><i class="bi bi-clock-history"></i> Ketikan pemohon</span>'; ?></td>
                                <td class="text-center soft"><?php echo (int)$d['jml']; ?></td>
                                <td class="text-center"><button type="button" class="btn btn-del btn-hapus-satu"><i class="bi bi-trash3"></i> Hapus</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$aktif): ?><tr><td colspan="5" class="text-center soft py-4">Belum ada nama klinik.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4 mb-4">
                <h6 class="fw-bold ink mb-3"><i class="bi bi-plus-circle-fill me-2" style="color: var(--primary);"></i>Tambah Nama Klinik</h6>
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_klinik']); ?>">
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="text" name="nama" class="form-control" placeholder="Nama klinik / RS" maxlength="150" required>
                    <button class="btn btn-executive text-white fw-bold" style="background: var(--grad);">Tambah</button>
                </form>
            </div>

            <div class="glass-card p-4">
                <h6 class="fw-bold ink mb-1"><i class="bi bi-eye-slash-fill me-2" style="color: var(--primary);"></i>Sudah Dihapus (<?php echo count($tampil_hapus); ?>)</h6>
                <p class="soft small">Tidak lagi disarankan ke pemohon. Bisa dikembalikan kapan saja.</p>
                <?php if ($tampil_hapus): ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" id="btnKembalikanSemua"><i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan semua</button>
                    <button type="button" class="btn btn-sm btn-danger rounded-pill fw-bold" id="btnPermanenSemua"><i class="bi bi-trash3-fill me-1"></i>Hapus semua</button>
                </div>
                <?php endif; ?>
                <?php if (!$tampil_hapus): ?>
                    <div class="soft small">Belum ada.</div>
                <?php else: ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($tampil_hapus as $k => $h): $dipakai = (int)($h['jml'] ?? 0); ?>
                    <div class="item-hapus">
                        <span class="small ink fw-semibold text-truncate flex-grow-1" title="<?php echo e($h['nama_asli']); ?>"><?php echo e($h['nama_asli']); ?></span>
                        <?php if ($dipakai): ?><span class="chip chip-riwayat" title="Dipakai pada permohonan yang sudah masuk"><?php echo $dipakai; ?> permohonan</span><?php endif; ?>
                        <form method="POST" class="m-0">
                            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_klinik']); ?>">
                            <input type="hidden" name="aksi" value="kembalikan">
                            <input type="hidden" name="nama" value="<?php echo e($h['nama_asli']); ?>">
                            <button class="btn-mini btn-mini-ok" title="Kembalikan ke daftar saran" aria-label="Kembalikan"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </form>
                        <button type="button" class="btn-mini btn-mini-del btn-permanen" data-nama="<?php echo e($h['nama_asli']); ?>" title="Hapus permanen" aria-label="Hapus permanen"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="formPermanen" class="d-none">
    <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_klinik']); ?>">
    <input type="hidden" name="aksi" value="hapus_permanen">
    <input type="hidden" name="nama" id="namaPermanen" value="">
</form>

<form method="POST" id="formMassal" class="d-none">
    <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_klinik']); ?>">
    <input type="hidden" name="aksi" id="aksiMassal" value="">
</form>

<div class="notif-wrap" id="notifWrap" aria-live="polite"></div>

<form method="POST" id="formHapus" class="d-none">
    <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_klinik']); ?>">
    <input type="hidden" name="aksi" value="hapus">
    <div id="isiHapus"></div>
</form>

<script>
    document.getElementById('themeToggle').addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('eicv-theme', next); } catch (e) {}
    });

    const tbody = document.getElementById('isiKlinik');
    const rows = () => Array.from(tbody.querySelectorAll('tr[data-nama]'));
    const cekSemua = document.getElementById('cekSemua'), btnBulk = document.getElementById('btnHapusPilih');
    const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const terlihat = r => !r.classList.contains('sembunyi');

    function updatePilihan() {
        const dip = rows().filter(r => terlihat(r) && r.querySelector('.cek').checked);
        document.getElementById('jmlPilih').textContent = dip.length;
        btnBulk.classList.toggle('show', dip.length > 0);
        const vis = rows().filter(terlihat);
        cekSemua.checked = vis.length > 0 && vis.every(r => r.querySelector('.cek').checked);
    }
    cekSemua.addEventListener('change', () => { rows().filter(terlihat).forEach(r => r.querySelector('.cek').checked = cekSemua.checked); updatePilihan(); });
    tbody.addEventListener('change', e => { if (e.target.classList.contains('cek')) updatePilihan(); });

    // Pencarian langsung + sorotan
    document.getElementById('cariKlinik').addEventListener('input', function () {
        const kw = this.value.trim().toLowerCase(); let n = 0;
        rows().forEach(r => {
            const nama = r.dataset.nama, i = kw ? nama.toLowerCase().indexOf(kw) : 0, ok = i >= 0;
            r.classList.toggle('sembunyi', !ok); if (ok) n++;
            r.querySelector('.nm').innerHTML = (ok && kw) ? esc(nama.slice(0, i)) + '<mark class="cari">' + esc(nama.slice(i, i + kw.length)) + '</mark>' + esc(nama.slice(i + kw.length)) : esc(nama);
        });
        document.getElementById('jmlKlinik').textContent = n + ' nama';
        updatePilihan();
    });

    function konfirmasiHapus(daftar) {
        if (!daftar.length) return;
        const teks = daftar.length === 1
            ? '<b>' + esc(daftar[0]) + '</b> akan dihapus dari daftar saran.'
            : '<b>' + daftar.length + ' nama klinik</b> akan dihapus dari daftar saran.';
        Swal.fire({
            icon: 'warning', title: 'Hapus nama klinik?',
            html: teks + '<br><span style="font-size:.85rem;opacity:.8">Data permohonan yang sudah masuk tidak ikut terhapus.</span>',
            showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
            confirmButtonColor: '#ef4444', background: getComputedStyle(document.body).getPropertyValue('--surface-2') || undefined
        }).then(r => {
            if (!r.isConfirmed) return;
            document.getElementById('isiHapus').innerHTML = daftar.map(n => '<input type="hidden" name="nama[]" value="' + esc(n) + '">').join('');
            document.getElementById('formHapus').submit();
        });
    }
    tbody.addEventListener('click', e => {
        const b = e.target.closest('.btn-hapus-satu'); if (!b) return;
        konfirmasiHapus([b.closest('tr').dataset.nama]);
    });
    document.querySelectorAll('.btn-permanen').forEach(b => b.addEventListener('click', () => {
        Swal.fire({
            icon: 'warning', title: 'Hapus permanen?',
            html: '<b>' + esc(b.dataset.nama) + '</b> akan dihapus total dari daftar ini dan tidak bisa dikembalikan.<br><span style="font-size:.85rem;opacity:.8">Data permohonan yang sudah masuk tidak ikut terhapus.</span>',
            showCancelButton: true, confirmButtonText: 'Ya, hapus permanen', cancelButtonText: 'Batal', confirmButtonColor: '#ef4444'
        }).then(r => { if (r.isConfirmed) { document.getElementById('namaPermanen').value = b.dataset.nama; document.getElementById('formPermanen').submit(); } });
    }));
    function aksiMassal(aksi, opsi) {
        Swal.fire(Object.assign({ icon: 'warning', showCancelButton: true, cancelButtonText: 'Batal' }, opsi)).then(r => {
            if (r.isConfirmed) { document.getElementById('aksiMassal').value = aksi; document.getElementById('formMassal').submit(); }
        });
    }
    const bk = document.getElementById('btnKembalikanSemua'), bp = document.getElementById('btnPermanenSemua');
    if (bk) bk.addEventListener('click', () => aksiMassal('kembalikan_semua', { icon: 'question', title: 'Kembalikan semua?', text: 'Semua nama di daftar "Sudah Dihapus" akan muncul lagi sebagai saran.', confirmButtonText: 'Ya, kembalikan semua', confirmButtonColor: '#4f46e5' }));
    if (bp) bp.addEventListener('click', () => aksiMassal('permanen_semua', { title: 'Hapus semua permanen?', html: 'Semua nama di daftar "Sudah Dihapus" dihapus total dan tidak bisa dikembalikan.<br><span style="font-size:.85rem;opacity:.8">Data permohonan yang sudah masuk tidak ikut terhapus, dan nama-nama ini tidak akan disarankan lagi ke pemohon.</span>', confirmButtonText: 'Ya, hapus semua', confirmButtonColor: '#ef4444' }));
    btnBulk.addEventListener('click', () => konfirmasiHapus(rows().filter(r => terlihat(r) && r.querySelector('.cek').checked).map(r => r.dataset.nama)));

    /* Pembaruan langsung: nama klinik baru dari pemohon masuk tanpa refresh */
    const LABEL_MASTER = '<span class="chip chip-master"><i class="bi bi-database-fill"></i> Master</span>';
    const LABEL_RIWAYAT = '<span class="chip chip-riwayat"><i class="bi bi-clock-history"></i> Ketikan pemohon</span>';
    const cariInput = document.getElementById('cariKlinik');
    let sigTerakhir = <?php echo json_encode(md5(json_encode(array_map(fn($d) => ['nama' => $d['nama'], 'master' => $d['master'], 'jml' => $d['jml']], array_values($aktif))) . count($hidden))); ?>;
    let sedangMuat = false;

    function notif(tipe, judul, sub, ms) {
        ms = ms || 3200;
        const ikon = { hapus: 'bi-trash3-fill', baru: 'bi-hospital-fill', info: 'bi-check-lg' }[tipe] || 'bi-info-lg';
        const el = document.createElement('div');
        el.className = 'notif notif-' + tipe; el.style.setProperty('--dur', ms + 'ms');
        el.innerHTML = '<div class="notif-ico"><i class="bi ' + ikon + '"></i></div><div><b>' + esc(judul) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</div><span class="notif-bar"></span>';
        document.getElementById('notifWrap').appendChild(el);
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('tampil')));
        setTimeout(() => { el.classList.remove('tampil'); setTimeout(() => el.remove(), 350); }, ms);
    }
    const toastKecil = teks => notif('baru', teks, 'Dari formulir pemohon · masuk otomatis');

    function terapkan(data) {
        const dipilih = new Set(rows().filter(r => r.querySelector('.cek').checked).map(r => r.dataset.nama));
        const lama = new Set(rows().map(r => r.dataset.nama));
        const baru = [];
        tbody.innerHTML = data.aktif.length ? data.aktif.map(d => {
            const adalah = !lama.has(d.nama); if (adalah) baru.push(d.nama);
            return '<tr data-nama="' + esc(d.nama) + '"' + (adalah ? ' style="background: rgba(16,185,129,.14)"' : '') + '>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input cek"' + (dipilih.has(d.nama) ? ' checked' : '') + '></td>' +
                '<td class="fw-semibold ink nm">' + esc(d.nama) + '</td>' +
                '<td class="text-center">' + (d.master ? LABEL_MASTER : LABEL_RIWAYAT) + '</td>' +
                '<td class="text-center soft">' + d.jml + '</td>' +
                '<td class="text-center"><button type="button" class="btn btn-del btn-hapus-satu"><i class="bi bi-trash3"></i> Hapus</button></td></tr>';
        }).join('') : '<tr><td colspan="5" class="text-center soft py-4">Belum ada nama klinik.</td></tr>';
        cariInput.dispatchEvent(new Event('input'));          // terapkan lagi pencarian & sorotan yang sedang aktif
        if (!cariInput.value.trim()) document.getElementById('jmlKlinik').textContent = data.aktif.length + ' nama';
        if (baru.length && lama.size) notif('baru', baru.length === 1 ? 'Klinik baru masuk' : baru.length + ' klinik baru masuk', baru.length === 1 ? baru[0] : baru.slice(0, 3).join(', ') + (baru.length > 3 ? ' dan lainnya' : ''), 4200);
        setTimeout(() => rows().forEach(r => r.style.background = ''), 2500);
    }
    function periksa() {
        if (sedangMuat || document.hidden || (document.body.classList.contains('swal2-shown') && !document.body.classList.contains('swal2-toast-shown'))) return;
        sedangMuat = true;
        fetch('kelola_klinik.php?ajax=1', { credentials: 'same-origin', cache: 'no-store' })
            .then(r => r.json())
            .then(d => { if (d.sig !== sigTerakhir) { sigTerakhir = d.sig; terapkan(d); } })
            .catch(() => {})
            .then(() => { sedangMuat = false; });
    }
    setInterval(periksa, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) periksa(); });

    <?php if ($flash && $flash[1] !== ''): ?>
    (function () {
        const t = <?php echo json_encode($flash[0]); ?>, m = <?php echo json_encode($flash[1]); ?>;
        const hapus = /dihapus dari daftar saran|dihapus permanen/.test(m);
        notif(t === 'error' ? 'hapus' : (hapus ? 'hapus' : 'info'), t === 'error' ? 'Gagal' : (hapus ? 'Nama klinik dihapus' : 'Berhasil'), m, 3600);
    })();
    <?php endif; ?>
</script>
</body>
</html>