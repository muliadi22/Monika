<?php
// admin/dashboard.php
session_start();
date_default_timezone_set('Asia/Makassar');   
require_once '../config/database.php';
if (empty($_SESSION['csrf_hapus'])) $_SESSION['csrf_hapus'] = bin2hex(random_bytes(32));
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Gunakan session_id() sebagai ID unik perangkat
$me = session_id();   

// Cek keberadaan kolom session_lock
function cek_kolom_kunci($conn) {
    if (!empty($_SESSION['kolom_kunci_ok'])) return true;
    $r = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'session_lock'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($ok) $_SESSION['kolom_kunci_ok'] = true;
    return $ok;
}

// Deteksi kuncian berdasarkan session_id unik perangkat
function kunci_lain($row, $me) {
    $lock_ses    = trim((string)($row['session_lock'] ?? ''));
    $sedang_oleh = trim((string)($row['sedang_diproses_oleh'] ?? 'Admin'));
    $waktu_lock  = $row['waktu_lock'] ?? '';
    
    // Jika ada session_lock dan BUKAN milik perangkat ini
    if ($lock_ses !== '' && $lock_ses !== $me) {
        if (!empty($waktu_lock)) {
            $selisih = time() - strtotime($waktu_lock);
            if ($selisih < 180) { // Masih dalam batas kuncian 3 menit
                return $sedang_oleh;
            }
        }
    }
    return null;
}

function badge_kunci($nk) {
    return $nk === null ? '' : '<div class="kunci-badge"><i class="bi bi-lock-fill"></i> Sedang diproses oleh <b>' . e($nk) . '</b></div>';
}

// Alasan penolakan (tampil di bawah lencana status "Ditolak")
function alasan_tolak_html($row) {
    if (($row['status_permohonan'] ?? '') !== 'Ditolak') return '';
    $a = trim((string)($row['alasan_ditolak'] ?? ''));
    if ($a === '') return '<div class="alasan-tolak"><i class="bi bi-chat-left-text-fill"></i> Tanpa alasan tercatat</div>';
    return '<div class="alasan-tolak" title="' . e($a) . '"><i class="bi bi-chat-left-text-fill"></i> ' . e($a) . '</div>';
}

// FUNGSI TOMBOL AKSI: MENGUNCI/MENGHILANGKAN PROSES & MENAMPILKAN LIHAT (MODAL)/UNDUH SAAT SELESAI
function tombol_proses($row, $nk) {
    if (($row['status_permohonan'] ?? '') === 'Selesai') {
        $file_terbitan = $row['file_terbitan'] ?? '';
        if (!empty($file_terbitan)) {
            $url_view = '../uploads/dokumen_terbitan/' . e($file_terbitan) . '?v=' . (int)@filemtime(__DIR__ . '/../uploads/dokumen_terbitan/' . basename((string)$file_terbitan));
            $url_download = '../uploads/dokumen_terbitan/' . e($file_terbitan);
            return '<div class="d-flex align-items-center justify-content-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold btn-lihat-pdf" data-pdf-url="' . $url_view . '" title="Lihat Dokumen"><i class="bi bi-eye-fill me-1"></i> Lihat</button>
                <a href="' . $url_download . '" download class="btn btn-sm text-white rounded-pill px-3 py-1 fw-bold shadow-sm" style="background-color: #10b981; border: none;" title="Unduh Dokumen"><i class="bi bi-cloud-download-fill me-1"></i> Unduh</a>
            </div>';
        } else {
            return '<span class="badge bg-light text-muted border rounded-pill px-3 py-1 small"><i class="bi bi-exclamation-circle me-1"></i> File Tidak Ada</span>';
        }
    }

    // Ditolak: terkunci, tidak bisa diproses lagi (sama seperti Selesai)
    if (($row['status_permohonan'] ?? '') === 'Ditolak') {
        return '<span class="badge rounded-pill px-3 py-2 fw-semibold" style="background: rgba(220,38,38,.10); color:#b91c1c; border:1px solid rgba(220,38,38,.25);" title="Permohonan ditolak - tidak dapat diproses"><i class="bi bi-lock-fill me-1"></i> Terkunci</span>';
    }

    $href = 'proses_permohonan.php?id=' . (int)$row['id_permohonan'];
    if ($nk !== null) {
        return '<a href="' . $href . '" class="btn btn-sm rounded-pill px-4 py-2 fw-semibold btn-terkunci" title="Sedang diproses oleh ' . e($nk) . ' - klik untuk memantau"><i class="bi bi-eye-fill me-1"></i> Pantau</a>';
    }
    return '<a href="' . $href . '" class="btn btn-sm text-white rounded-pill px-4 py-2 fw-semibold" style="background: var(--grad);">Proses <i class="bi bi-arrow-right ms-1"></i></a>';
}

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

function format_tgl_indo($tanggal, $nama_bulan) {
    if (!$tanggal || $tanggal === '0000-00-00') return '-';
    $ts = strtotime($tanggal);
    $d = date('d', $ts);
    $m = date('m', $ts);
    $y = date('Y', $ts);
    return $d . ' ' . ($nama_bulan[$m] ?? '') . ' ' . $y;
}

function no_invoice($row) {
    if (!empty($row['no_invoice'])) return $row['no_invoice'];
    $tgl = !empty($row['tanggal_permohonan']) ? strtotime($row['tanggal_permohonan']) : time();
    return 'INV-' . date('Ymd', $tgl) . '-' . str_pad((string)$row['id_permohonan'], 4, '0', STR_PAD_LEFT);
}

function bangun_query($conn, $search_raw, $bulan_int, $tahun_int, $klinik = "") {
    if ($search_raw !== "") { $bulan_int = 0; $tahun_int = 0; }
    $q = "SELECT *, (session_lock IS NOT NULL AND waktu_lock IS NOT NULL AND waktu_lock > (NOW() - INTERVAL 3 MINUTE)) AS _terkunci FROM permohonan WHERE 1=1";
    if ($search_raw !== "") {
        $indo_date_expr = "CONCAT(DATE_FORMAT(tanggal_permohonan, '%d '),
            CASE MONTH(tanggal_permohonan)
                WHEN 1 THEN 'Januari' WHEN 2 THEN 'Februari' WHEN 3 THEN 'Maret'
                WHEN 4 THEN 'April' WHEN 5 THEN 'Mei' WHEN 6 THEN 'Juni'
                WHEN 7 THEN 'Juli' WHEN 8 THEN 'Agustus' WHEN 9 THEN 'September'
                WHEN 10 THEN 'Oktober' WHEN 11 THEN 'November' WHEN 12 THEN 'Desember'
            END,
            DATE_FORMAT(tanggal_permohonan, ' %Y'))";
        foreach (preg_split('/\s+/', $search_raw) as $kata) {
            if ($kata === '') continue;
            $s = mysqli_real_escape_string($conn, addcslashes($kata, '%_'));
            $q .= " AND (nama_klinik LIKE '%$s%' OR nama_pemohon LIKE '%$s%' OR kode_tracking LIKE '%$s%' OR $indo_date_expr LIKE '%$s%')";
        }
    }
    if ($bulan_int) $q .= " AND MONTH(tanggal_permohonan) = " . (int)$bulan_int;
    if ($tahun_int) $q .= " AND YEAR(tanggal_permohonan) = " . (int)$tahun_int;
    if ($klinik !== "") {
        $k = mysqli_real_escape_string($conn, $klinik);
        $q .= " AND nama_klinik = '$k'";
    }
    return $q . " ORDER BY id_permohonan DESC";
}

function data_grafik($conn, $bulan_int, $tahun_int, $klinik = "") {
    $pendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $awal = strtotime(date('Y-m-01') . ' -11 months');
    $labels = []; $idx = [];
    for ($i = 0; $i < 12; $i++) {
        $t = strtotime(date('Y-m-01', $awal) . " +$i months");
        $idx[date('Y-m', $t)] = $i;
        $labels[] = $pendek[(int)date('n', $t)] . ' ' . date('y', $t);
    }
    $seri = ['menunggu' => array_fill(0, 12, 0), 'diproses' => array_fill(0, 12, 0), 'selesai' => array_fill(0, 12, 0), 'ditolak' => array_fill(0, 12, 0)];
    $mulai = date('Y-m-01', $awal);
    
    $st_q = "SELECT DATE_FORMAT(tanggal_permohonan, '%Y-%m') AS ym, status_permohonan AS st, COUNT(*) AS c
             FROM permohonan WHERE tanggal_permohonan >= ?";
    if ($klinik !== "") {
        $k = mysqli_real_escape_string($conn, $klinik);
        $st_q .= " AND nama_klinik = '$k'";
    }
    $st_q .= " GROUP BY ym, st";
    
    $st = mysqli_prepare($conn, $st_q);
    mysqli_stmt_bind_param($st, 's', $mulai);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        if (!isset($idx[$r['ym']])) continue;
        $k = $r['st'] === 'Menunggu' ? 'menunggu' : ($r['st'] === 'Diproses' ? 'diproses' : ($r['st'] === 'Ditolak' ? 'ditolak' : 'selesai'));
        $seri[$k][$idx[$r['ym']]] += (int)$r['c'];
    }
    mysqli_stmt_close($st);

    $rasio = ['menunggu' => 0, 'diproses' => 0, 'selesai' => 0, 'ditolak' => 0];
    $q = "SELECT status_permohonan AS st, COUNT(*) AS c FROM permohonan WHERE 1=1";
    if ($bulan_int) $q .= " AND MONTH(tanggal_permohonan) = " . (int)$bulan_int;
    if ($tahun_int) $q .= " AND YEAR(tanggal_permohonan) = " . (int)$tahun_int;
    if ($klinik !== "") {
        $k = mysqli_real_escape_string($conn, $klinik);
        $q .= " AND nama_klinik = '$k'";
    }
    $q .= " GROUP BY status_permohonan";
    $rs = mysqli_query($conn, $q);
    while ($rs && ($r = mysqli_fetch_assoc($rs))) {
        $k = $r['st'] === 'Menunggu' ? 'menunggu' : ($r['st'] === 'Diproses' ? 'diproses' : ($r['st'] === 'Ditolak' ? 'ditolak' : 'selesai'));
        $rasio[$k] += (int)$r['c'];
    }

    return ['labels' => $labels, 'menunggu' => $seri['menunggu'], 'diproses' => $seri['diproses'], 'selesai' => $seri['selesai'], 'ditolak' => $seri['ditolak'],
            'rasio' => [$rasio['menunggu'], $rasio['diproses'], $rasio['selesai'], $rasio['ditolak']]];
}

// --- ENDPOINT AJAX (real-time & live search) ---
if (isset($_GET['ajax'])) {
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(401); header('Content-Type: application/json; charset=utf-8'); echo '{"error":"auth"}'; exit();
    }
    $GLOBALS['ada_kunci'] = cek_kolom_kunci($conn);
    session_write_close();
    header('Cache-Control: no-store');
    $search_raw = isset($_GET['search']) ? trim($_GET['search']) : "";
    $bulan_int  = isset($_GET['bulan']) && ctype_digit($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
    $tahun_int  = isset($_GET['tahun']) && ctype_digit($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
    $filter_klinik = isset($_GET['klinik']) ? trim($_GET['klinik']) : "";
    if ($bulan_int < 1 || $bulan_int > 12) $bulan_int = 0;
    $result = mysqli_query($conn, bangun_query($conn, $search_raw, $bulan_int, $tahun_int, $filter_klinik));

    if ($_GET['ajax'] === 'sig') {
        $ctx = hash_init('md5'); $total = 0;
        while ($result && ($r = mysqli_fetch_assoc($result))) {
            $total++;
            unset($r['waktu_lock']);
            hash_update($ctx, json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
        }
        $per = []; 
        $rp = mysqli_query($conn, "SELECT YEAR(tanggal_permohonan) AS y, MONTH(tanggal_permohonan) AS m, COUNT(*) AS c FROM permohonan WHERE tanggal_permohonan IS NOT NULL GROUP BY y, m ORDER BY y DESC, m DESC");
        while ($rp && ($r = mysqli_fetch_assoc($rp))) { if ($r['y'] !== null) $per[(int)$r['y']][] = [(int)$r['m'], (int)$r['c']]; }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sig' => hash_final($ctx), 'total' => $total, 'periode' => (object)$per, 'grafik' => data_grafik($conn, $bulan_int, $tahun_int, $filter_klinik)]);
        exit();
    }

    $html = '';
    if ($result && mysqli_num_rows($result) > 0) {
        $no = 0; 
        while ($row = mysqli_fetch_assoc($result)) {
            $no++;
            $st = $row['status_permohonan'];
            $cls = $st === 'Menunggu' ? 'st-menunggu' : ($st === 'Diproses' ? 'st-diproses' : ($st === 'Ditolak' ? 'st-ditolak' : 'st-selesai'));
            $lbl = in_array($st, ['Menunggu', 'Diproses', 'Ditolak'], true) ? $st : 'Selesai';
            $tgl_indo = format_tgl_indo($row['tanggal_permohonan'], $nama_bulan);
            $inv = no_invoice($row);
            $nk = kunci_lain($row, $me);

            $html .= '<tr class="data-row" data-status="'.e($lbl).'" data-tgl="'.e(substr((string)$row['tanggal_permohonan'], 0, 10)).'" data-kunci="'.e($nk ?? '').'">
                <td class="ps-4 text-center"><input type="checkbox" class="form-check-input cek cek-row" value="'.(int)$row['id_permohonan'].'"></td>
                <td class="text-center fw-bold soft small row-no">'.$no.'</td>
                <td class="fw-semibold soft small">'.e($tgl_indo).'</td>
                <td><span class="code-pill">'.e($row['kode_tracking']).'</span></td>
                <td>
                    <div class="fw-bold ink">'.e($row['nama_klinik']).'</div>
                    <div class="soft small">'.e($row['nama_pemohon']).'</div>'.badge_kunci($nk).'
                </td>
                <td><span class="fw-semibold ink">'.(!empty($row['nama_kabupaten']) ? e($row['nama_kabupaten']) : '-').'</span></td>
                <td class="text-center"><span class="num">'.e($row['jumlah_permintaan']).'</span></td>
                <td><span class="badge badge-status '.$cls.'">'.e($lbl).'</span>'.alasan_tolak_html($row).'</td>
                <td class="text-center pe-4">'.tombol_proses($row, $nk).'</td>
            </tr>';
        }
    } else {
        $html = '<tr id="empty-state">
            <td colspan="9" class="text-center py-5 soft fw-semibold">
                <div class="py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                    Belum ada data pengajuan pada periode ini atau hasil pencarian tidak ditemukan.
                </div>
            </td>
        </tr>';
    }
    echo $html;
    exit();
}

if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit(); }
$GLOBALS['ada_kunci'] = cek_kolom_kunci($conn);

$search_raw    = isset($_GET['search']) ? trim($_GET['search']) : "";
$search_query  = mysqli_real_escape_string($conn, $search_raw);
$bulan_int     = isset($_GET['bulan']) && ctype_digit($_GET['bulan']) ? (int)$_GET['bulan'] : 0;
$tahun_int     = isset($_GET['tahun']) && ctype_digit($_GET['tahun']) ? (int)$_GET['tahun'] : 0;
$filter_klinik = isset($_GET['klinik']) ? trim($_GET['klinik']) : "";
if ($bulan_int < 1 || $bulan_int > 12) $bulan_int = 0;

$daftar_tahun = [];
$periode = []; 
$rp = mysqli_query($conn, "SELECT YEAR(tanggal_permohonan) AS y, MONTH(tanggal_permohonan) AS m, COUNT(*) AS c
                           FROM permohonan WHERE tanggal_permohonan IS NOT NULL
                           GROUP BY y, m ORDER BY y DESC, m DESC");
while ($rp && ($r = mysqli_fetch_assoc($rp))) {
    if ($r['y'] === null) continue;
    $periode[(int)$r['y']][] = [(int)$r['m'], (int)$r['c']];
    if (!in_array((int)$r['y'], $daftar_tahun, true)) $daftar_tahun[] = (int)$r['y'];
}

// Ambil daftar nama klinik unik untuk dropdown filter
$daftar_klinik = [];
$rk = mysqli_query($conn, "SELECT DISTINCT nama_klinik FROM permohonan WHERE nama_klinik IS NOT NULL AND nama_klinik != '' ORDER BY nama_klinik ASC");
while ($rk && ($r = mysqli_fetch_assoc($rk))) {
    $daftar_klinik[] = $r['nama_klinik'];
}

if (!$bulan_int && !$tahun_int && $search_raw === '') {
    $bulan_int = (int)date('n');
    $tahun_int = (int)date('Y');
}
$filter_bulan = $bulan_int ? sprintf('%02d', $bulan_int) : "";
$filter_tahun = $tahun_int ? (string)$tahun_int : "";

$result = mysqli_query($conn, bangun_query($conn, $search_raw, $bulan_int, $tahun_int, $filter_klinik));
$jumlah_data = $result ? mysqli_num_rows($result) : 0;

$periode_aktif = 'Semua Periode';
if ($bulan_int && $tahun_int)      $periode_aktif = $nama_bulan[$filter_bulan] . ' ' . $filter_tahun;
elseif ($bulan_int)                $periode_aktif = 'Bulan ' . $nama_bulan[$filter_bulan];
elseif ($tahun_int)                $periode_aktif = 'Tahun ' . $filter_tahun;

$export_qs = 'search=' . urlencode($search_raw) . '&bulan=' . $filter_bulan . '&tahun=' . $filter_tahun . '&klinik=' . urlencode($filter_klinik);
$label_tabel = $search_raw !== '' ? 'Hasil pencarian · semua periode' : $periode_aktif;
if ($filter_klinik !== '') $label_tabel .= ' · ' . e($filter_klinik);

$bln_ini = (int)date('n'); $thn_ini = (int)date('Y');
$tahun_tampil = $tahun_int ?: $thn_ini;
$bulan_nav    = $bulan_int ?: $bln_ini;
$tahun_min    = $daftar_tahun ? min(min($daftar_tahun), $thn_ini) : $thn_ini;
$cnt_bulan = [];
foreach (($periode[$tahun_tampil] ?? []) as [$m, $c]) { $cnt_bulan[$m] = $c; }
$bln_pendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$url_thn = function ($t) use ($bulan_nav, $filter_klinik) { return 'dashboard.php?bulan=' . sprintf('%02d', $bulan_nav) . '&tahun=' . (int)$t . '&klinik=' . urlencode($filter_klinik); };

$grafik = data_grafik($conn, $bulan_int, $tahun_int, $filter_klinik);
?>
<!DOCTYPE html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Eksekutif - Admin Panel</title>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root, [data-theme="light"] {
            --bg: #f8fafc; --surface: rgba(255,255,255,.88); --surface-2: #ffffff; --soft: #f1f5f9;
            --border: #e2e8f0; --text: #1e293b; --muted: #64748b; --primary: #4f46e5;
            --grad: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            --shadow: 0 20px 40px -15px rgba(0,0,0,.07), 0 0 15px rgba(0,0,0,.02);
            --nav: #ffffff; --th: #f8fafc; --row-hover: rgba(99,102,241,.05);
            --aurora1: rgba(99,102,241,.10); --aurora2: rgba(255,126,103,.07);
        }
        [data-theme="dark"] {
            --bg: #0b1020; --surface: rgba(21,27,48,.85); --surface-2: #151b30; --soft: #1b2340;
            --border: #29325a; --text: #e6e9f5; --muted: #98a2c3; --primary: #8b93ff;
            --grad: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            --shadow: 0 20px 40px -15px rgba(0,0,0,.6);
            --nav: #0e1326; --th: #19213d; --row-hover: rgba(139,147,255,.08);
            --aurora1: rgba(99,102,241,.18); --aurora2: rgba(255,126,103,.08);
        }
        html body {
            font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh;
            background-color: var(--bg); color: var(--text);
            background-image: radial-gradient(at 0% 0%, var(--aurora1) 0, transparent 50%),
                              radial-gradient(at 100% 100%, var(--aurora2) 0, transparent 50%);
            background-attachment: fixed; transition: background-color .3s, color .3s;
        }
        html body .soft { color: var(--muted) !important; }
        html body .ink  { color: var(--text) !important; }

        .navbar-admin { background: var(--nav);  border-bottom: 1px solid var(--border); }
        .brand-text { background: var(--grad); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .user-chip { background: var(--surface-2); border: 1px solid var(--border); }

        .glass-card { background: var(--surface);  border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); }

        .btn-executive { border-radius: 50px; padding: .65rem 1.5rem; font-weight: 600; font-size: .9rem; transition: all .25s ease; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
        .btn-executive:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.14); }
        .btn-logout-merah { background-color: #ef4444 !important; color: #ffffff !important; border: 1px solid #dc2626 !important; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25); }
        .btn-logout-merah:hover { background-color: #dc2626 !important; color: #ffffff !important; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4); }
        .theme-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); display: grid; place-items: center; transition: .25s; }
        .theme-btn:hover { transform: rotate(18deg) scale(1.06); }
        [data-theme="light"] .theme-btn .bi-sun-fill { display: none; }
        [data-theme="dark"]  .theme-btn .bi-moon-stars-fill { display: none; }

        .toolbar { display: flex; gap: 12px; align-items: stretch; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .search-wrapper { flex: 1 1 360px; background: var(--surface-2); border: 1.5px solid var(--border); border-radius: 35px; transition: all .3s; display: flex; align-items: center; padding: 0 14px; }
        .search-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.15); }
        .search-wrapper input { border: none !important; background: transparent !important; box-shadow: none !important; padding: .8rem 1rem; font-size: .95rem; color: var(--text); }
        .search-wrapper input::placeholder { color: var(--muted); }

        .table { --bs-table-bg: transparent; --bs-table-color: var(--text); --bs-table-border-color: var(--border); background: transparent; }
        .table th { background: var(--th) !important; border-bottom: 2px solid var(--border); color: var(--muted) !important; font-weight: 700; text-transform: uppercase; font-size: .75rem; letter-spacing: .05em; padding: 1rem; white-space: nowrap; }
        .table td { background: transparent !important; border-bottom: 1px solid var(--border); vertical-align: middle; padding: 1.1rem 1rem; color: var(--text); }
        .cek { width: 18px; height: 18px; cursor: pointer; accent-color: #dc2626; }
        .btn-hapus { display:none; background: linear-gradient(135deg,#ef4444,#b91c1c); border:0; }
        .btn-hapus.show { display:inline-flex; }
        .data-row.dipilih td { background: rgba(239,68,68,.08) !important; }
        .data-row { transition: background .2s; }
        .data-row:hover td { background: var(--row-hover) !important; }
        .code-pill { background: var(--surface-2); color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 5px 10px; font-family: ui-monospace, Consolas, monospace; font-size: .78rem; font-weight: 600; white-space: nowrap; }
        .inv-pill { display: inline-flex; align-items: center; gap: 6px; color: var(--primary); background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.25); border-radius: 8px; padding: 5px 10px; font-family: ui-monospace, Consolas, monospace; font-size: .78rem; font-weight: 700; white-space: nowrap; }
        .num { font-weight: 800; color: var(--primary); }

        .badge-status { padding: .5em 1em; font-weight: 700; font-size: .75rem; border-radius: 30px; border: 1px solid transparent; }
        .st-menunggu { background: rgba(245,158,11,.15); color: #b45309; border-color: rgba(245,158,11,.4); }
        .st-diproses { background: rgba(14,165,233,.15); color: #0369a1; border-color: rgba(14,165,233,.4); }
        .st-selesai  { background: rgba(16,185,129,.15); color: #047857; border-color: rgba(16,185,129,.4); }
        .st-ditolak  { background: rgba(239,68,68,.14); color: #b91c1c; border-color: rgba(239,68,68,.45); }
        [data-theme="dark"] .st-ditolak { color: #fca5a5; }
        .alasan-tolak { margin-top: 6px; max-width: 220px; font-size: .72rem; font-weight: 600; color: #b91c1c; display: flex; gap: 5px; align-items: flex-start; line-height: 1.35; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .alasan-tolak i { margin-right: 4px; }
        [data-theme="dark"] .alasan-tolak { color: #fca5a5; }

        .live-dot { display:inline-flex; align-items:center; gap:6px; font-size:.7rem; font-weight:700; letter-spacing:.4px; padding:3px 10px; border-radius:50px; margin-left:10px; vertical-align:middle; background:rgba(16,185,129,.14); color:#10b981; }
        .live-dot.off { background:rgba(245,158,11,.15); color:#f59e0b; }
        .data-row.row-baru td { animation: rowBaru 2.6s ease-out; }
        @keyframes rowBaru { 0%{background:rgba(99,102,241,.32)} 100%{background:transparent} }
        .data-row.row-ubah td { animation: rowUbah 1.8s ease-out; }
        @keyframes rowUbah { 0%{background:rgba(245,158,11,.28)} 100%{background:transparent} }
        .live-toast { position:fixed; bottom:24px; right:22px; max-width:min(380px, calc(100vw - 44px)); z-index:4000; display:flex; align-items:center; gap:10px; padding:10px 16px; border-radius:16px; font-weight:700; font-size:.9rem; color:#fff; cursor:pointer; background:linear-gradient(135deg,#6366f1,#06b6d4); box-shadow:0 18px 36px -12px rgba(79,70,229,.6); transform:translateX(130%); visibility:hidden; opacity:0; pointer-events:none; transition:transform .35s cubic-bezier(.2,.8,.2,1), opacity .35s, visibility 0s .35s; }
        .live-toast.show { transform:none; visibility:visible; opacity:1; pointer-events:auto; transition:transform .35s cubic-bezier(.2,.8,.2,1), opacity .35s, visibility 0s; }
    </style>
<style id="efek">
:root { --bg-2: #eaf0ff; }
[data-theme="dark"] { --bg-2: #111936; }
html body { background-image: none; }
.bg-aurora { position: fixed; inset: 0; z-index: -3; pointer-events: none;
    background:
        radial-gradient(60% 55% at 12% 8%,  rgba(99,102,241,.30) 0%, transparent 60%),
        radial-gradient(55% 50% at 92% 22%, rgba(6,182,212,.26) 0%, transparent 62%),
        radial-gradient(55% 50% at 78% 95%, rgba(52,211,153,.20) 0%, transparent 60%),
        radial-gradient(50% 45% at 8% 88%,  rgba(244,114,182,.16) 0%, transparent 60%),
        linear-gradient(160deg, var(--bg-2), var(--bg) 55%); }
.bg-fade { position: fixed; inset: 0; z-index: -1; pointer-events: none; contain: strict;
    background: radial-gradient(ellipse at 50% 38%, transparent 35%, var(--bg) 110%); opacity: .45; }
.bg-waves { position: fixed; left: 0; right: 0; bottom: 0; height: 30vh; z-index: -2; pointer-events: none; overflow: hidden; contain: strict; }
.bg-waves svg { position: absolute; left: 0; bottom: 0; width: 200%; height: 100%; will-change: transform; }
.bg-waves .w1 { animation: waveX 26s linear infinite; }
.bg-waves .w2 { animation: waveX 38s linear infinite reverse; opacity: .8; }
@keyframes waveX { to { transform: translateX(-50%); } }
</style>
<style id="grafik">
.chart-card { padding: 1.4rem 1.5rem; height: 100%; }
.chart-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 1rem; }
.chart-title { font-weight: 800; font-size: 1rem; color: var(--text); margin: 0; display: flex; align-items: center; gap: 8px; }
.chart-title i { color: var(--primary); }
.chart-sub { font-size: .78rem; color: var(--muted); margin: 2px 0 0; }
.chart-total { font-size: .75rem; font-weight: 800; color: var(--primary); background: var(--soft); border: 1px solid var(--border); border-radius: 30px; padding: 4px 12px; white-space: nowrap; }
.chart-body { position: relative; height: 270px; }
.donut-wrap { position: relative; height: 190px; }
.donut-center { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; pointer-events: none; }
.donut-center b { font-size: 1.9rem; font-weight: 800; color: var(--text); line-height: 1; }
.donut-center span { font-size: .7rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
.legend-status { list-style: none; margin: 1.1rem 0 0; padding: 0; display: grid; gap: 9px; }
.legend-status li { display: flex; align-items: center; gap: 10px; font-size: .86rem; font-weight: 600; color: var(--text); }
.legend-status .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
.legend-status .n { margin-left: auto; font-weight: 800; }
.legend-status .p { width: 46px; text-align: right; color: var(--muted); font-weight: 600; }
.chart-msg { height: 100%; display: grid; place-items: center; text-align: center; color: var(--muted); font-size: .85rem; font-weight: 600; }

.nav-bln { display: grid; place-items: center; width: 52px; min-height: 52px; border-radius: 50%; background: var(--surface-2); color: var(--text); border: 1.5px solid var(--border); text-decoration: none; transition: .25s; }
.nav-bln:hover { border-color: var(--primary); color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.12); transform: translateY(-1px); }
.nav-bln.disabled { opacity: .4; pointer-events: none; }
.table-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 1rem; }
.table-head-title { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-weight: 800; font-size: 1.05rem; color: var(--text); }
.table-head-title > i { color: var(--primary); }
.table-head-sep { color: var(--muted); }

.kpi { width: 100%; display: flex; align-items: center; gap: 14px; text-align: left; padding: 16px 18px; background: var(--surface); border: 1.5px solid var(--border); border-radius: 20px; color: var(--text); box-shadow: var(--shadow); cursor: pointer; transition: transform .2s, border-color .2s, box-shadow .2s; }
.kpi:hover { transform: translateY(-2px); border-color: var(--primary); }
.kpi.active { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(99,102,241,.16), var(--shadow); }
.kpi-ico { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 1.3rem; flex: none; color: #fff; background: var(--grad); }
.kpi-warn .kpi-ico { background: linear-gradient(135deg, #f59e0b, #f97316); }
.kpi-info .kpi-ico { background: linear-gradient(135deg, #0ea5e9, #3b82f6); }
.kpi-ok   .kpi-ico { background: linear-gradient(135deg, #10b981, #059669); }
.kpi-bad  .kpi-ico { background: linear-gradient(135deg, #f87171, #dc2626); }
.kpi-body { display: flex; flex-direction: column; min-width: 0; }
.kpi-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
.kpi-num { font-size: 1.7rem; line-height: 1.15; font-weight: 800; color: var(--text); }
.kpi-sub { font-size: .74rem; font-weight: 600; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-sub.merah { color: #dc2626; font-weight: 700; }
[data-theme="dark"] .kpi-sub.merah { color: #fca5a5; }
.data-row[data-telat="1"] .st-menunggu { background: rgba(239,68,68,.14); color: #b91c1c; border-color: rgba(239,68,68,.45); }
.data-row[data-telat="1"] .st-menunggu::after { content: " · Terlambat"; }

.bulan-bar { display: flex; align-items: center; gap: 14px; margin-bottom: 1.4rem; flex-wrap: wrap; }
.year-switch { display: flex; align-items: center; gap: 6px; flex: none; }
.year-label { min-width: 64px; text-align: center; font-weight: 800; font-size: 1.05rem; color: var(--text); }
.nav-bln.sm { width: 38px; min-height: 38px; }
.bulan-strip { flex: 1 1 560px; display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 6px; }
.bln-btn { position: relative; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 9px 4px; border-radius: 14px; background: var(--surface-2); border: 1.5px solid var(--border); color: var(--text); text-decoration: none; font-weight: 700; font-size: .84rem; transition: border-color .2s, transform .2s; }
.bln-btn:hover { border-color: var(--primary); transform: translateY(-1px); color: var(--text); }
.bln-btn .bln-count { font-size: .68rem; font-weight: 700; padding: 1px 8px; border-radius: 20px; background: var(--soft); color: var(--muted); }
.bln-btn.kosong { color: var(--muted); font-weight: 600; }
.bln-btn.depan { opacity: .55; }
.bln-btn.kini::after { content: ""; position: absolute; top: 6px; right: 7px; width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
.bln-btn.active { background: var(--grad); border-color: transparent; color: #fff; box-shadow: 0 8px 18px -8px rgba(79,70,229,.6); opacity: 1; }
.bln-btn.active .bln-count { background: rgba(255,255,255,.25); color: #fff; }

.kunci-badge { display: inline-flex; align-items: center; gap: 6px; margin-top: 5px; font-size: .72rem; font-weight: 700; color: #b45309; background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.4); border-radius: 30px; padding: 2px 10px; }
[data-theme="dark"] .kunci-badge { color: #fcd34d; }
.btn-terkunci { background: var(--soft); color: var(--muted); border: 1px solid var(--border); }

.petugas-chip { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 14px; border-radius: 50px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); font-weight: 600; font-size: .85rem; cursor: pointer; transition: border-color .2s; }
.petugas-chip:hover { border-color: var(--primary); }
#namaPetugas { max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.petugas-overlay { position: fixed; inset: 0; z-index: 5000; display: grid; place-items: center; padding: 16px; background: rgba(15,23,42,.55); }
.petugas-overlay[hidden] { display: none; }
.petugas-box { width: min(440px, 100%); background: var(--surface-2); color: var(--text); border: 1px solid var(--border); border-radius: 24px; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.4); }
.petugas-ico { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; font-size: 1.5rem; color: #fff; background: var(--grad); margin-bottom: 14px; }
.petugas-box .form-control { background: var(--soft); color: var(--text); border-color: var(--border); border-radius: 14px; padding: .7rem 1rem; }

.cari-bersih { border: 0; background: transparent; color: var(--muted); font-size: 1.15rem; padding: 6px 8px; border-radius: 50%; display: grid; place-items: center; cursor: pointer; }
.cari-bersih:hover { color: #dc2626; }
.cari-bersih[hidden] { display: none; }
.search-wrapper.memuat .bi-search { animation: cariDenyut .55s ease-in-out infinite alternate; }
@keyframes cariDenyut { from { opacity: 1; } to { opacity: .3; } }
mark.cari { background: linear-gradient(120deg, rgba(250,204,21,.65), rgba(251,146,60,.5)); color: #713f12; font-weight: 800; padding: 0 3px; border-radius: 5px; box-shadow: 0 0 0 1px rgba(245,158,11,.35); }
[data-theme="dark"] mark.cari { background: linear-gradient(120deg, rgba(250,204,21,.32), rgba(251,146,60,.28)); color: #fde68a; box-shadow: 0 0 0 1px rgba(250,204,21,.4); }
.data-row.sembunyi-cari { display: none; }

/* Pratinjau dokumen: ringan saat di-scroll (tanpa scroll bersarang, tanpa sudut membulat pada iframe, animasi latar dijeda) */
#modalLihatPDF .modal-content { display: flex; flex-direction: column; height: 90vh; box-shadow: none; }
#modalLihatPDF .modal-body { flex: 1 1 auto; min-height: 0; height: auto !important; padding: 8px 12px 12px; position: relative; overflow: hidden; }
#frameLihatPDF { display: block; width: 100%; height: 100%; border: 0; border-radius: 0 !important; background: #f8fafc; }
.pdf-memuat { position: absolute; inset: 8px 12px 12px; display: grid; place-items: center; color: var(--muted); font-weight: 700; pointer-events: none; }
.pdf-memuat[hidden] { display: none; }
body.pdf-aktif .bg-waves, body.pdf-aktif .bg-fade { display: none; }
body.pdf-aktif .navbar-brand > div { animation: none; }
.notif-badge { position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 5px; border-radius: 10px; border: 2px solid var(--nav); line-height: 1; }
.notif-dropdown { width: 340px; max-height: 420px; overflow-y: auto; box-shadow: var(--shadow); border-radius: 16px; border: 1px solid var(--border); background: var(--surface-2); padding: 0; z-index: 1050; }
.notif-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: var(--surface-2); z-index: 10; }
.notif-header h6 { margin: 0; font-weight: 800; font-size: 1rem; color: var(--text); }
.notif-header span { font-size: 0.78rem; color: var(--muted); font-weight: 700; }
.notif-item { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; gap: 14px; align-items: flex-start; text-decoration: none; transition: background 0.2s; }
.notif-item:hover { background: var(--row-hover); }
.notif-item:last-child { border-bottom: none; }
.notif-icon { width: 36px; height: 36px; border-radius: 50%; background: rgba(245,158,11,.15); color: #d97706; display: grid; place-items: center; flex: none; font-size: 1rem; margin-top: 2px; }
.notif-text { font-size: 0.85rem; color: var(--text); line-height: 1.4; margin-bottom: 6px; }
.notif-text b { font-weight: 700; }
.notif-time { font-size: 0.75rem; color: var(--muted); font-weight: 600; }
.notif-empty { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 0.9rem; font-weight: 600; }
.marker-baru { font-size: 0.65rem; padding: 3px 8px; border-radius: 20px; margin-left: 8px; vertical-align: middle; animation: pulseBaru 2s infinite; }
@keyframes pulseBaru { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); } 70% { box-shadow: 0 0 0 4px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }
</style>
</head>
<body>
<div class="bg-aurora"></div>
<div class="bg-waves" aria-hidden="true">
    <svg class="w2" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg2" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".16"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".16"/><stop offset="1" stop-color="#34d399" stop-opacity=".14"/></linearGradient></defs>
        <path fill="url(#wg2)" d="M0 110 C 200 60 400 160 600 110 S 1000 60 1200 110 S 1600 160 1800 110 S 2200 60 2400 110 V200 H0Z"/></svg>
    <svg class="w1" viewBox="0 0 2400 200" preserveAspectRatio="none"><defs><linearGradient id="wg1" x1="0" x2="1"><stop offset="0" stop-color="#6366f1" stop-opacity=".22"/><stop offset=".5" stop-color="#06b6d4" stop-opacity=".2"/><stop offset="1" stop-color="#f472b6" stop-opacity=".16"/></linearGradient></defs>
        <path fill="url(#wg1)" d="M0 140 C 150 100 350 190 600 140 S 1050 90 1200 140 S 1550 190 1800 140 S 2250 90 2400 140 V200 H0Z"/></svg>
</div>
<div class="bg-fade"></div>

<nav class="navbar navbar-expand-lg navbar-admin sticky-top py-3 mb-4">
    <div class="container-fluid px-4 px-md-5">
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2" href="dashboard.php">
            <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center shadow-sm" style="background: var(--grad); width: 38px; height: 38px;">
                <i class="bi bi-shield-lock-fill fs-6"></i>
            </div>
            <span class="brand-text">Admin Portal</span>
        </a>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <button type="button" class="theme-btn" id="themeToggle" aria-label="Ganti tema">
                <i class="bi bi-moon-stars-fill"></i><i class="bi bi-sun-fill"></i>
            </button>
            <div class="dropdown">
                <button type="button" class="theme-btn position-relative border-0 shadow-sm" id="notifToggle" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                    <i class="bi bi-bell-fill"></i>
                    <span class="notif-badge" id="notifCount" hidden>0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown mt-2" aria-labelledby="notifToggle">
                    <div class="notif-header">
                        <h6>Notifikasi</h6>
                        <span id="notifSub">terbaru</span>
                    </div>
                    <div id="notifList">
                        <div class="notif-empty" id="notifEmpty">Belum ada notifikasi baru</div>
                    </div>
                </div>
            </div>
            <button type="button" class="petugas-chip" id="btnPetugas" title="Ubah nama petugas">
                <i class="bi bi-person-badge-fill"></i><span id="namaPetugas">Isi nama</span><i class="bi bi-pencil-fill"></i>
            </button>
            <div class="user-chip d-none d-md-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-person-circle fs-5" style="color: var(--primary);"></i>
                <span class="fw-semibold small ink"><?php echo e($_SESSION['nama_lengkap'] ?? 'Admin'); ?></span>
            </div>
            <a href="logout.php" class="btn btn-executive btn-logout-merah px-4 py-2 small">
                <i class="bi bi-box-arrow-right me-1"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 px-md-5 mb-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold ink mb-1">Data Pengajuan Masuk<span class="live-dot" id="liveDot">LIVE</span></h3>
            <p class="soft small mb-0">Kelola verifikasi, rekapitulasi, dan monitoring izin e-ICV klinik secara terpusat.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-executive text-white fw-bold d-flex align-items-center gap-2" style="background: var(--grad);" data-bs-toggle="modal" data-bs-target="#modalBulanan">
                <i class="bi bi-calendar-range"></i> Filter &amp; Cetak Bulanan
            </button>
            <a href="export_pdf.php?<?php echo $export_qs; ?>" id="btnExportPdf" target="_blank" class="btn btn-executive btn-danger text-white d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-pdf-fill"></i> PDF
            </a>
            <a href="export_excel.php?<?php echo $export_qs; ?>" id="btnExportExcel" class="btn btn-executive btn-success text-white d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-excel-fill"></i> Excel
            </a>
            <a href="kelola_klinik.php" class="btn btn-executive text-white fw-bold d-flex align-items-center gap-2" style="background: #0ea5e9;">
                <i class="bi bi-hospital-fill"></i> Kelola Klinik
            </a>
            <button type="button" id="btnHapus" class="btn btn-executive btn-hapus text-white fw-bold align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalHapus">
                <i class="bi bi-trash3-fill"></i> Hapus Terpilih (<span id="jmlPilih">0</span>)
            </button>
        </div>
    </div>

    <!-- KARTU RINGKASAN -->
    <div class="row g-3 mb-4" id="kpiRow">
        <div class="col-6 col-xl">
            <button type="button" class="kpi kpi-total active" data-filter="semua">
                <span class="kpi-ico"><i class="bi bi-inboxes-fill"></i></span>
                <span class="kpi-body"><span class="kpi-label">Total Permohonan</span><b class="kpi-num" id="kpiTotal">–</b><span class="kpi-sub" id="kpiDelta">&nbsp;</span></span>
            </button>
        </div>
        <div class="col-6 col-xl">
            <button type="button" class="kpi kpi-warn" data-filter="Menunggu">
                <span class="kpi-ico"><i class="bi bi-hourglass-split"></i></span>
                <span class="kpi-body"><span class="kpi-label">Menunggu</span><b class="kpi-num" id="kpiMenunggu">–</b><span class="kpi-sub" id="kpiTelat">&nbsp;</span></span>
            </button>
        </div>
        <div class="col-6 col-xl">
            <button type="button" class="kpi kpi-info" data-filter="Diproses">
                <span class="kpi-ico"><i class="bi bi-arrow-repeat"></i></span>
                <span class="kpi-body"><span class="kpi-label">Diproses</span><b class="kpi-num" id="kpiDiproses">–</b><span class="kpi-sub">sedang dikerjakan</span></span>
            </button>
        </div>
        <div class="col-6 col-xl">
            <button type="button" class="kpi kpi-ok" data-filter="Selesai">
                <span class="kpi-ico"><i class="bi bi-check2-circle"></i></span>
                <span class="kpi-body"><span class="kpi-label">Selesai</span><b class="kpi-num" id="kpiSelesai">–</b><span class="kpi-sub" id="kpiPersen">&nbsp;</span></span>
            </button>
        </div>
        <div class="col-6 col-xl">
            <button type="button" class="kpi kpi-bad" data-filter="Ditolak">
                <span class="kpi-ico"><i class="bi bi-x-octagon-fill"></i></span>
                <span class="kpi-body"><span class="kpi-label">Ditolak</span><b class="kpi-num" id="kpiDitolak">–</b><span class="kpi-sub" id="kpiTolakSub">&nbsp;</span></span>
            </button>
        </div>
    </div>

    <!-- GRAFIK -->
    <div class="row g-4 mb-4" id="grafikRow">
        <div class="col-lg-8">
            <div class="glass-card chart-card">
                <div class="chart-head">
                    <div>
                        <h5 class="chart-title"><i class="bi bi-bar-chart-line-fill"></i> Permohonan per Bulan</h5>
                        <p class="chart-sub">12 bulan terakhir, dipisah per status</p>
                    </div>
                    <span class="chart-total" id="totalBulan">Total: 0</span>
                </div>
                <div class="chart-body"><canvas id="chartBulan" role="img"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="glass-card chart-card">
                <div class="chart-head">
                    <div>
                        <h5 class="chart-title"><i class="bi bi-pie-chart-fill"></i> Rasio Status</h5>
                        <p class="chart-sub" id="subRasio"><?php echo e($periode_aktif); ?></p>
                    </div>
                </div>
                <div class="donut-wrap">
                    <canvas id="chartStatus" role="img"></canvas>
                    <div class="donut-center"><b id="donutTotal">0</b><span>Permohonan</span></div>
                </div>
                <ul class="legend-status" id="legendStatus"></ul>
            </div>
        </div>
    </div>

    <!-- AREA TABEL DENGAN ID (Untuk target auto-scroll saat klik KPI) -->
    <div class="glass-card p-4 p-md-5" id="areaTabelData">
        <!-- NAVIGASI BULAN -->
        <div class="bulan-bar">
            <div class="year-switch">
                <a class="nav-bln sm <?php echo $tahun_tampil > $tahun_min ? '' : 'disabled'; ?>" href="<?php echo $tahun_tampil > $tahun_min ? e($url_thn($tahun_tampil - 1)) : '#'; ?>"><i class="bi bi-chevron-left"></i></a>
                <span class="year-label"><?php echo (int)$tahun_tampil; ?></span>
                <a class="nav-bln sm <?php echo $tahun_tampil < $thn_ini ? '' : 'disabled'; ?>" href="<?php echo $tahun_tampil < $thn_ini ? e($url_thn($tahun_tampil + 1)) : '#'; ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="bulan-strip" id="bulanStrip">
                <?php for ($m = 1; $m <= 12; $m++):
                    $bk = sprintf('%02d', $m); $n = $cnt_bulan[$m] ?? 0;
                    $aktif = ($bulan_int === $m && $tahun_int === $tahun_tampil);
                    $depan = ($tahun_tampil > $thn_ini || ($tahun_tampil === $thn_ini && $m > $bln_ini));
                    $kini  = ($tahun_tampil === $thn_ini && $m === $bln_ini);
                ?>
                <a class="bln-btn<?php echo $aktif ? ' active' : ''; ?><?php echo $n ? '' : ' kosong'; ?><?php echo $depan ? ' depan' : ''; ?><?php echo $kini ? ' kini' : ''; ?>"
                   href="dashboard.php?bulan=<?php echo $bk; ?>&amp;tahun=<?php echo (int)$tahun_tampil; ?>&amp;klinik=<?php echo urlencode($filter_klinik); ?>" data-bulan="<?php echo $bk; ?>" data-tahun="<?php echo (int)$tahun_tampil; ?>">
                    <span class="bln-nama"><?php echo e($bln_pendek[$m]); ?></span>
                    <span class="bln-count"><?php echo (int)$n; ?></span>
                </a>
                <?php endfor; ?>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-wrapper">
                <i class="bi bi-search soft fs-5 ms-2"></i>
                <input type="text" id="liveSearchInput" class="form-control" placeholder="Cari berdasarkan klinik, pemohon, kode tracking, tanggal, bulan, atau tahun..." value="<?php echo e($search_raw); ?>" autocomplete="off">
                <button type="button" id="btnBersih" class="cari-bersih" hidden><i class="bi bi-x-circle-fill"></i></button>
            </div>
        </div>

        <div class="table-head">
            <div class="table-head-title">
                <i class="bi bi-table"></i>
                <span>Data Pemohon</span>
                <span class="table-head-sep">·</span>
                <span id="labelTabel"><?php echo e($label_tabel); ?></span>
                <span class="chart-total"><b id="jmlData"><?php echo (int)$jumlah_data; ?></b> data</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 text-center" style="width:46px"><input type="checkbox" class="form-check-input cek" id="cekSemua"></th>
                        <th class="text-center" style="width:60px">No</th>
                        <th>Tanggal</th>
                        <th>Kode Tracking</th>
                        <th>Klinik / Pemohon</th>
                        <th>Kabupaten</th>
                        <th class="text-center">Jumlah Permintaan</th>
                        <th>Status</th>
                        <th class="text-center pe-4" style="min-width:170px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php $no = 0; while ($row = mysqli_fetch_assoc($result)): $no++; ?>
                        <?php
                            $st = $row['status_permohonan'];
                            $cls = $st === 'Menunggu' ? 'st-menunggu' : ($st === 'Diproses' ? 'st-diproses' : ($st === 'Ditolak' ? 'st-ditolak' : 'st-selesai'));
                            $lbl = in_array($st, ['Menunggu', 'Diproses', 'Ditolak'], true) ? $st : 'Selesai';
                            $tgl_indo = format_tgl_indo($row['tanggal_permohonan'], $nama_bulan);
                            $nk = kunci_lain($row, $me);
                        ?>
                        <tr class="data-row" data-status="<?php echo e($lbl); ?>" data-tgl="<?php echo e(substr((string)$row['tanggal_permohonan'], 0, 10)); ?>" data-kunci="<?php echo e($nk ?? ''); ?>">
                            <td class="ps-4 text-center"><input type="checkbox" class="form-check-input cek cek-row" value="<?php echo (int)$row['id_permohonan']; ?>"></td>
                            <td class="text-center fw-bold soft small row-no"><?php echo $no; ?></td>
                            <td class="fw-semibold soft small"><?php echo e($tgl_indo); ?></td>
                            <td><span class="code-pill"><?php echo e($row['kode_tracking']); ?></span></td>
                            <td>
                                <div class="fw-bold ink"><?php echo e($row['nama_klinik']); ?></div>
                                <div class="soft small"><?php echo e($row['nama_pemohon']); ?></div>
                                <?php echo badge_kunci($nk); ?>
                            </td>
                            <td><span class="fw-semibold ink"><?php echo !empty($row['nama_kabupaten']) ? e($row['nama_kabupaten']) : '-'; ?></span></td>
                            <td class="text-center"><span class="num"><?php echo e($row['jumlah_permintaan']); ?></span></td>
                            <td><span class="badge badge-status <?php echo $cls; ?>"><?php echo e($lbl); ?></span><?php echo alasan_tolak_html($row); ?></td>
                            <td class="text-center pe-4"><?php echo tombol_proses($row, $nk); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="empty-state">
                            <td colspan="9" class="text-center py-5 soft fw-semibold">
                                <div class="py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                    Belum ada data pengajuan pada periode ini atau hasil pencarian tidak ditemukan.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="hapus_permohonan.php" class="modal-content rounded-4 border-0 shadow-lg">
            <input type="hidden" name="csrf" value="<?php echo e($_SESSION['csrf_hapus']); ?>">
            <input type="hidden" name="search" id="hapusSearch" value="">
            <input type="hidden" name="bulan" value="<?php echo e($filter_bulan); ?>">
            <input type="hidden" name="tahun" value="<?php echo e($filter_tahun); ?>">
            <input type="hidden" name="klinik" value="<?php echo e($filter_klinik); ?>">
            <div id="hapusIds"></div>
            <div class="modal-body text-center p-4 p-md-5">
                <div class="mb-3" style="font-size:3rem;color:#dc2626"><i class="bi bi-exclamation-octagon-fill"></i></div>
                <h5 class="fw-bold mb-2">Hapus <span id="hapusJumlah">0</span> data?</h5>
                <p class="soft small mb-4">Data permohonan beserta dokumen terpilih akan dihapus permanen.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="bi bi-trash3-fill me-1"></i> Ya, Hapus</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL FILTER BULANAN (DENGAN KLINIK) -->
<div class="modal fade" id="modalBulanan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold mx-auto"><i class="bi bi-calendar-event me-2" style="color: var(--primary);"></i>Filter &amp; Rekapitulasi Bulanan</h5>
            </div>
            <div class="modal-body px-4 py-3">
                <form action="dashboard.php" method="GET" id="formModalBulanan">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold soft">Pilih Klinik (Opsional)</label>
                            <select name="klinik" id="modalSelectKlinik" class="form-select rounded-3 py-2">
                                <option value="">Semua Klinik</option>
                                <?php foreach ($daftar_klinik as $kl): ?>
                                    <option value="<?php echo e($kl); ?>" <?php if ($filter_klinik === $kl) echo 'selected'; ?>><?php echo e($kl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold soft">Bulan</label>
                            <select name="bulan" id="modalSelectBulan" class="form-select rounded-3 py-2">
                                <option value="">Semua Bulan</option>
                                <?php foreach ($nama_bulan as $num => $nama): ?>
                                    <option value="<?php echo $num; ?>" <?php if ($filter_bulan === (string)$num) echo 'selected'; ?>><?php echo e($nama); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold soft">Tahun</label>
                            <select name="tahun" id="modalSelectTahun" class="form-select rounded-3 py-2">
                                <option value="">Semua Tahun</option>
                                <?php foreach ($daftar_tahun as $thn): ?>
                                    <option value="<?php echo (int)$thn; ?>" <?php if ($filter_tahun === (string)$thn) echo 'selected'; ?>><?php echo (int)$thn; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn text-white fw-bold rounded-pill py-2 shadow-sm" style="background: var(--grad);">Terapkan Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL LIHAT PDF -->
<div class="modal fade" id="modalLihatPDF" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0" style="height: 90vh;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Pratinjau Dokumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pdf-memuat" id="pdfMemuat" hidden><span><i class="bi bi-arrow-repeat me-2"></i>Memuat dokumen...</span></div>
                <iframe id="frameLihatPDF" src="" title="Pratinjau dokumen"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NAMA PETUGAS -->
<div class="petugas-overlay" id="dlgPetugas" hidden>
    <form class="petugas-box" id="formPetugas" autocomplete="off">
        <div class="petugas-ico"><i class="bi bi-person-badge-fill"></i></div>
        <h5 class="fw-bold mb-1">Siapa yang sedang bertugas?</h5>
        <p class="soft small mb-3">Nama Anda akan terlihat oleh petugas lain saat Anda memproses permohonan untuk mencegah tabrakan data.</p>
        <input type="text" id="inpPetugas" class="form-control mb-3" maxlength="40" placeholder="Contoh: Budi" required>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" id="batalPetugas">Nanti</button>
            <button type="submit" class="btn text-white rounded-pill px-4 fw-bold" style="background: var(--grad);">Simpan</button>
        </div>
    </form>
</div>

<div class="live-toast" id="liveToast"></div>

<script>
    (function () {
        const chip = document.getElementById('btnPetugas'), teks = document.getElementById('namaPetugas');
        const dlg = document.getElementById('dlgPetugas'), form = document.getElementById('formPetugas'), inp = document.getElementById('inpPetugas');
        const bersih = n => n.replace(/[^\p{L}\p{N} .'\-]/gu, '').replace(/\s+/g, ' ').trim().slice(0, 40);
        function baca() {
            const m = document.cookie.match(/(?:^|; )eicv_petugas=([^;]*)/);
            try { return m ? decodeURIComponent(m[1]) : ''; } catch (e) { return ''; }
        }
        function simpan(n) {
            document.cookie = 'eicv_petugas=' + encodeURIComponent(n) + '; path=/; max-age=31536000; SameSite=Lax';
            try { localStorage.setItem('eicv-petugas', n); } catch (e) {}
        }
        function tampil() { const n = baca(); teks.textContent = n || 'Isi nama'; }
        if (!baca()) { try { const ls = localStorage.getItem('eicv-petugas'); if (ls) simpan(bersih(ls)); } catch (e) {} }
        function buka() { inp.value = baca(); dlg.hidden = false; setTimeout(() => inp.focus(), 30); }
        function tutup() { dlg.hidden = true; }
        chip.addEventListener('click', buka);
        document.getElementById('batalPetugas').addEventListener('click', tutup);
        dlg.addEventListener('click', e => { if (e.target === dlg) tutup(); });
        form.addEventListener('submit', e => { e.preventDefault(); const n = bersih(inp.value); if (!n) { inp.focus(); return; } simpan(n); tampil(); tutup(); });
        tampil();
        if (!baca()) buka();
    })();

    document.getElementById('themeToggle').addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('eicv-theme', next); } catch (e) {}
    });

    const liveSearchInput = document.getElementById('liveSearchInput');
    const tableBody = document.getElementById('tableBody');
    const btnPdf = document.getElementById('btnExportPdf');
    const btnExcel = document.getElementById('btnExportExcel');
    let BULAN = <?php echo json_encode($filter_bulan); ?>;
    let TAHUN = <?php echo json_encode($filter_tahun); ?>;
    let KLINIK = <?php echo json_encode($filter_klinik); ?>;
    let LABEL_PERIODE = <?php echo json_encode($periode_aktif); ?>;

    function syncLinks() {
        const kw = liveSearchInput.value.trim();
        const q = 'search=' + encodeURIComponent(kw) + '&bulan=' + BULAN + '&tahun=' + TAHUN + '&klinik=' + encodeURIComponent(KLINIK);
        btnPdf.href = 'export_pdf.php?' + q;
        btnExcel.href = 'export_excel.php?' + q;
    }
    syncLinks();

    const NAMA_BULAN = <?php echo json_encode($nama_bulan); ?>;
    const liveDot = document.getElementById('liveDot'), liveToast = document.getElementById('liveToast');
    const setDot = ok => { liveDot.classList.toggle('off', !ok); liveDot.textContent = ok ? 'LIVE' : 'OFFLINE'; };
    let toastT; function toast(html) { liveToast.innerHTML = html; liveToast.classList.add('show'); clearTimeout(toastT); toastT = setTimeout(() => liveToast.classList.remove('show'), 5000); }
    liveToast.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); liveToast.classList.remove('show'); });

    const qsBase = () => 'search=' + encodeURIComponent(liveSearchInput.value.trim()) + '&bulan=' + BULAN + '&tahun=' + TAHUN + '&klinik=' + encodeURIComponent(KLINIK);
    const rowsOf = root => Array.from(root.querySelectorAll('.data-row'));
    const keyOf = tr => { const c = tr.querySelector('.cek-row'); return c ? c.value : null; };
    const sigOf = tr => { const c = tr.cloneNode(true); c.querySelectorAll('.row-no, input, .kunci-badge').forEach(n => n.remove()); if (c.cells.length) c.cells[c.cells.length - 1].remove(); return c.textContent.replace(/\s+/g, ' ').trim(); };
    function renumber() { let n = 0; rowsOf(tableBody).forEach(r => { if (r.style.display === 'none' || r.classList.contains('sembunyi-cari')) return; const no = r.querySelector('.row-no'); if (no) no.textContent = ++n; }); }

    const BATAS_TELAT_HARI = 3;
    let PERIODE = <?php echo json_encode((object)$periode); ?>;
    function prevInfo() {
        if (!BULAN || !TAHUN) return null;
        let m = +BULAN - 1, y = +TAHUN; if (m < 1) { m = 12; y--; }
        let t = 0; (PERIODE[y] || []).forEach(([mm, c]) => { if (mm === m) t = c; });
        return { label: NAMA_BULAN[String(m).padStart(2, '0')] + ' ' + y, total: t };
    }
    let filterStatus = 'semua';
    const kartu = Array.from(document.querySelectorAll('#kpiRow .kpi'));
    const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

    function tandaiTelat() {
        const batas = Date.now() - BATAS_TELAT_HARI * 86400000;
        rowsOf(tableBody).forEach(r => {
            const t = Date.parse((r.dataset.tgl || '') + 'T00:00:00');
            r.dataset.telat = (r.dataset.status === 'Menunggu' && t && t < batas) ? '1' : '';
        });
    }
    function perbaruiKpi() {
        const rows = rowsOf(tableBody), c = { Menunggu: 0, Diproses: 0, Selesai: 0, Ditolak: 0 };
        let telat = 0;
        rows.forEach(r => { if (c[r.dataset.status] !== undefined) c[r.dataset.status]++; if (r.dataset.telat === '1') telat++; });
        const total = rows.length;
        setTxt('kpiTotal', total); setTxt('kpiMenunggu', c.Menunggu); setTxt('kpiDiproses', c.Diproses); setTxt('kpiSelesai', c.Selesai); setTxt('kpiDitolak', c.Ditolak);
        setTxt('kpiTolakSub', total ? (c.Ditolak ? Math.round(c.Ditolak * 100 / total) + '% dari total' : 'tidak ada yang ditolak') : 'belum ada data');
        const tl = document.getElementById('kpiTelat');
        tl.textContent = telat ? telat + ' terlambat (>' + BATAS_TELAT_HARI + ' hari)' : 'tidak ada yang terlambat';
        tl.classList.toggle('merah', telat > 0);
        setTxt('kpiPersen', total ? Math.round(c.Selesai * 100 / total) + '% dari total' : 'belum ada data');
        let d = 'Semua periode';
        if (liveSearchInput.value.trim()) d = 'Hasil pencarian';
        else if (prevInfo()) {
            const PV = prevInfo(), sel = total - PV.total;
            d = sel > 0 ? '▲ +' + sel + ' dari ' + PV.label : sel < 0 ? '▼ ' + sel + ' dari ' + PV.label : 'Sama dengan ' + PV.label;
        }
        setTxt('kpiDelta', d);
    }
    function terapkanFilter() {
        let tampil = 0;
        const rows = rowsOf(tableBody);
        rows.forEach(r => {
            const ok = filterStatus === 'semua' || r.dataset.status === filterStatus;
            r.style.display = ok ? '' : 'none'; if (ok) tampil++;
        });
        let k = document.getElementById('kosong-filter');
        if (rows.length && !tampil) {
            if (!k) {
                k = document.createElement('tr'); k.id = 'kosong-filter';
                k.innerHTML = '<td colspan="9" class="text-center py-5 soft fw-semibold">Tidak ada permohonan berstatus ' + filterStatus + ' pada tampilan ini.</td>';
                tableBody.appendChild(k);
            }
        } else if (k) k.remove();
        kartu.forEach(el => el.classList.toggle('active', el.dataset.filter === filterStatus));
        renumber();
    }
    
    // KODE BARU UNTUK SCROLL SAAT KLIK KPI
    kartu.forEach(el => el.addEventListener('click', () => {
        const f = el.dataset.filter;
        filterStatus = (f !== 'semua' && filterStatus === f) ? 'semua' : f;
        terapkanFilter(); updatePilihan();
        
        // Auto-scroll pelan ke arah area tabel
        document.getElementById('areaTabelData').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    
    tandaiTelat(); perbaruiKpi(); terapkanFilter();

    // KODE BARU UNTUK MENANGKAP KLIK TOMBOL LIHAT PDF LALU MEMBUKA MODAL
    const modalLihatPDF = new bootstrap.Modal(document.getElementById('modalLihatPDF'));
    const frameLihatPDF = document.getElementById('frameLihatPDF');

    document.addEventListener('click', function(e) {
        // Karena tabel sering di-refresh AJAX, kita gunakan event delegation
        const btnLihat = e.target.closest('.btn-lihat-pdf');
        if (btnLihat) {
            e.preventDefault();
            const pdfUrl = btnLihat.getAttribute('data-pdf-url');
            // navpanes=0: panel miniatur halaman tidak dirender (lebih ringan); view=FitH: lebar halaman pas
            const urlPdf = pdfUrl + (pdfUrl.indexOf('#') < 0 ? '#navpanes=0&view=FitH' : '');
            document.getElementById('pdfMemuat').hidden = false;
            document.body.classList.add('pdf-aktif');          // jeda animasi latar & polling selama pratinjau terbuka
            frameLihatPDF.src = urlPdf;
            modalLihatPDF.show();
        }
    });

    // Kosongkan iframe ketika modal ditutup agar browser tidak memuat file secara background
    frameLihatPDF.addEventListener('load', function () { document.getElementById('pdfMemuat').hidden = true; });
    document.getElementById('modalLihatPDF').addEventListener('hidden.bs.modal', function () {
        frameLihatPDF.src = '';
        document.getElementById('pdfMemuat').hidden = true;
        document.body.classList.remove('pdf-aktif');
        clearTimeout(pollT); tick();                            // lanjutkan pembaruan data otomatis
    });

    function syncRows(html) {
        const doc = new DOMParser().parseFromString('<table><tbody>' + html + '</tbody></table>', 'text/html');
        const nb = doc.querySelector('tbody'), fresh = rowsOf(nb), have = {};
        let baru = 0, ubah = 0; const namaBaru = [];
        rowsOf(tableBody).forEach(r => { const k = keyOf(r); if (k) have[k] = r; });
        const seen = {}; let prev = null;
        fresh.forEach(fr => {
            const k = keyOf(fr); if (!k) return; seen[k] = true;
            let cur = have[k];
            if (cur) {
                if (cur.__sig === undefined) cur.__sig = sigOf(cur);
                const fs = sigOf(fr);
                if (fs !== cur.__sig) {
                    const old = cur.querySelector('.cek-row'), was = old && old.checked;
                    cur.innerHTML = fr.innerHTML; cur.__sig = fs; cur.dataset.status = fr.dataset.status; cur.dataset.tgl = fr.dataset.tgl; cur.dataset.kunci = fr.dataset.kunci || '';
                    const cb = cur.querySelector('.cek-row'); if (cb && was) cb.checked = true;
                    cur.classList.remove('row-ubah'); void cur.offsetWidth; cur.classList.add('row-ubah'); ubah++;
                }
                if ((cur.dataset.kunci || '') !== (fr.dataset.kunci || '')) {
                    cur.cells[4].innerHTML = fr.cells[4].innerHTML;
                    cur.cells[8].innerHTML = fr.cells[8].innerHTML;
                    cur.dataset.kunci = fr.dataset.kunci || '';
                }
            } else {
                cur = document.importNode(fr, true); cur.__sig = sigOf(fr); cur.classList.add('row-baru'); baru++;
                { const nm = fr.querySelector('.fw-bold.ink'); if (nm) namaBaru.push(nm.textContent.trim()); }
                
                // Tambahkan lencana penanda "Baru" di sebelah label status
                const tdStatus = cur.querySelector('td:nth-child(8)');
                if (tdStatus) {
                    const b = document.createElement('span');
                    b.className = 'badge bg-danger marker-baru';
                    b.textContent = 'Baru';
                    tdStatus.insertBefore(b, tdStatus.childNodes[1] || null);
                }
                
                tableBody.insertBefore(cur, prev ? prev.nextSibling : tableBody.firstChild);
                have[k] = cur;
            }
            const want = prev ? prev.nextSibling : tableBody.firstChild;
            if (want !== cur) tableBody.insertBefore(cur, want);
            prev = cur;
        });
        Object.keys(have).forEach(k => { if (!seen[k]) have[k].remove(); });
        const empty = document.getElementById('empty-state');
        if (fresh.length && empty) empty.remove();
        if (!fresh.length && !empty) { const e = nb.querySelector('#empty-state'); if (e) tableBody.appendChild(document.importNode(e, true)); }
        rowsOf(tableBody).forEach(r => r.classList.remove('sembunyi-cari'));
        tandaiTelat(); perbaruiKpi(); terapkanFilter(); updatePilihan();
        const jd = document.getElementById('jmlData'); if (jd) jd.textContent = fresh.length;
        return { baru, ubah, namaBaru };
    }

    function renderPeriode(per) {
        PERIODE = per;
        document.querySelectorAll('#bulanStrip a[data-bulan]').forEach(a => {
            let c = 0;
            (per[a.dataset.tahun] || []).forEach(([m, n]) => { if (String(m).padStart(2, '0') === a.dataset.bulan) c = n; });
            const el = a.querySelector('.bln-count'); if (el) el.textContent = c;
            a.classList.toggle('kosong', !c);
        });
    }

    const JUDUL_ASLI = document.title;
    let belumDilihat = 0;
    function setBadge() { document.title = (belumDilihat > 0 ? '(' + belumDilihat + ') ' : '') + JUDUL_ASLI; }
    function resetBadge() { if (belumDilihat) { belumDilihat = 0; setBadge(); } }

    const notifList = document.getElementById('notifList');
    const notifEmpty = document.getElementById('notifEmpty');
    const notifCountBadge = document.getElementById('notifCount');
    let unreadNotif = 0;

    function addNotifItem(text) {
        if (notifEmpty) notifEmpty.style.display = 'none';
        const div = document.createElement('a');
        div.href = "#areaTabelData"; 
        div.className = "notif-item";
        div.innerHTML = `
            <div class="notif-icon"><i class="bi bi-bell-fill"></i></div>
            <div>
                <div class="notif-text">${text}</div>
                <div class="notif-time">Baru saja</div>
            </div>
        `;
        notifList.insertBefore(div, notifList.firstChild);
        if (notifList.children.length > 20) notifList.lastChild.remove();
        
        unreadNotif++;
        notifCountBadge.textContent = unreadNotif;
        notifCountBadge.hidden = false;
    }

    document.getElementById('notifToggle').addEventListener('click', () => {
        unreadNotif = 0;
        notifCountBadge.hidden = true;
        resetBadge();
    });

    function notifBaru(n, daftar) {
        belumDilihat += n; setBadge();
        if (daftar && daftar.length > 0) {
            daftar.forEach(nama => {
                addNotifItem(`Pendaftaran baru dari <b>${escHtml(nama)}</b> menunggu persetujuan.`);
            });
        } else {
            addNotifItem(`<b>${n}</b> pendaftaran baru masuk menunggu persetujuan.`);
        }
    }
    const escHtml = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let lastSig = null, lastPer = null, busy = false, fails = 0, pollT, searchCtl = null;
    function refreshTable(silent) {
        return fetch('dashboard.php?ajax=1&' + qsBase(), { credentials: 'same-origin', cache: 'no-store' })
            .then(r => r.text()).then(html => {
                const res = syncRows(html);
                if (liveSearchInput.value.trim()) saringLokal(liveSearchInput.value.trim());   // pertahankan sorotan setelah pembaruan
                if (!silent) {
                    if (res.baru) notifBaru(res.baru, res.namaBaru);
                    if (res.baru) toast('<i class="bi bi-bell-fill"></i> <span>' + res.baru + ' permohonan baru masuk' +
                        (res.namaBaru && res.namaBaru.length ? ' · ' + escHtml(res.namaBaru[0]) + (res.baru > 1 ? ' +' + (res.baru - 1) + ' lainnya' : '') : '') + '</span>');
                    else if (res.ubah) toast('<i class="bi bi-arrow-repeat"></i> ' + res.ubah + ' data diperbarui');
                }
            });
    }
    function tick() {
        if (busy || document.body.classList.contains('pdf-aktif')) return schedule();
        busy = true;
        fetch('dashboard.php?ajax=sig&' + qsBase(), { credentials: 'same-origin', cache: 'no-store' })
            .then(r => { if (r.status === 401) { setDot(false); fails = 99; throw new Error('auth'); } return r.json(); })
            .then(d => {
                setDot(true); fails = 0;
                const per = JSON.stringify(d.periode);
                if (lastPer !== null && per !== lastPer) renderPeriode(d.periode);
                lastPer = per;
                if (d.grafik && window.renderGrafik && !document.hidden) window.renderGrafik(d.grafik);
                if (lastSig === null) { lastSig = d.sig; return; }
                if (d.sig !== lastSig) { lastSig = d.sig; return refreshTable(false); }
            })
            .catch(() => { if (fails < 99) fails++; setDot(false); })
            .then(() => { busy = false; schedule(); });
    }
    function schedule() { clearTimeout(pollT); if (fails >= 99) return; pollT = setTimeout(tick, Math.min((document.hidden ? 8000 : 3000) * (1 + fails), 30000)); }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { resetBadge(); clearTimeout(pollT); tick(); } });
    addEventListener('focus', resetBadge);
    tick();

    let searchT;
    const btnBersih = document.getElementById('btnBersih'), cariBox = document.querySelector('.search-wrapper');
    const kataKunci = kw => kw.toLowerCase().split(/\s+/).filter(Boolean);

    function saringLokal(kw) {
        const kata = kataKunci(kw); let n = 0;
        rowsOf(tableBody).forEach(r => {
            const teks = Array.from(r.cells).slice(2, 5).map(c => c.textContent).join(' ').toLowerCase();
            const ok = kata.every(k => teks.indexOf(k) !== -1);
            r.classList.toggle('sembunyi-cari', !ok); if (ok) n++;
        });
        setTxt('jmlData', n);
        renumber(); updatePilihan();
        sorot(kw);
    }
    /* Sorot huruf/kata yang cocok pada kolom tanggal, kode tracking, dan klinik/pemohon */
    function sorot(kw) {
        const kata = kataKunci(kw).sort((a, b) => b.length - a.length).map(k => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        rowsOf(tableBody).forEach(r => {
            Array.from(r.cells).slice(2, 5).forEach(c => {
                c.querySelectorAll('mark.cari').forEach(m => m.replaceWith(document.createTextNode(m.textContent)));
                c.normalize();
                if (!kata.length || r.classList.contains('sembunyi-cari')) return;
                const re = new RegExp('(' + kata.join('|') + ')', 'gi');
                const w = document.createTreeWalker(c, NodeFilter.SHOW_TEXT), list = [];
                while (w.nextNode()) list.push(w.currentNode);
                list.forEach(t => {
                    if (!re.test(t.nodeValue)) return; re.lastIndex = 0;
                    const f = document.createDocumentFragment(); let last = 0, m;
                    while ((m = re.exec(t.nodeValue))) {
                        if (!m[0]) { re.lastIndex++; continue; }
                        f.appendChild(document.createTextNode(t.nodeValue.slice(last, m.index)));
                        const mk = document.createElement('mark'); mk.className = 'cari'; mk.textContent = m[0]; f.appendChild(mk);
                        last = m.index + m[0].length;
                    }
                    f.appendChild(document.createTextNode(t.nodeValue.slice(last)));
                    t.parentNode.replaceChild(f, t);
                });
            });
        });
    }
    function jalankanCari() {
        if (searchCtl) searchCtl.abort();
        searchCtl = new AbortController();
        lastSig = null;
        cariBox.classList.add('memuat');
        fetch('dashboard.php?ajax=1&' + qsBase(), { credentials: 'same-origin', cache: 'no-store', signal: searchCtl.signal })
            .then(r => r.text()).then(html => { syncRows(html); saringLokal(liveSearchInput.value.trim()); cariBox.classList.remove('memuat'); })
            .catch(err => { if (!err || err.name !== 'AbortError') cariBox.classList.remove('memuat'); });
    }
    liveSearchInput.addEventListener('input', function () {
        const kw = liveSearchInput.value.trim();
        if (searchCtl) searchCtl.abort();
        btnBersih.hidden = !kw;
        syncLinks(); perbaruiKpi();
        setTxt('labelTabel', kw ? 'Hasil pencarian · semua periode' : LABEL_PERIODE);
        saringLokal(kw);
        clearTimeout(searchT);
        searchT = setTimeout(jalankanCari, 90);
    });
    btnBersih.addEventListener('click', () => { liveSearchInput.value = ''; liveSearchInput.dispatchEvent(new Event('input')); liveSearchInput.focus(); });
    btnBersih.hidden = !liveSearchInput.value.trim();

    const cekSemua = document.getElementById('cekSemua');
    const btnHapus = document.getElementById('btnHapus');
    const rowsCek = () => Array.from(document.querySelectorAll('.cek-row'));
    const terlihat = c => { const tr = c.closest('tr'); return tr && tr.style.display !== 'none' && !tr.classList.contains('sembunyi-cari'); };
    
    function updatePilihan() {
        const dipilih = rowsCek().filter(c => c.checked && terlihat(c));
        rowsCek().forEach(c => {
            if (c.closest('tr')) c.closest('tr').classList.toggle('dipilih', c.checked);
        });
        document.getElementById('jmlPilih').textContent = dipilih.length;
        btnHapus.classList.toggle('show', dipilih.length > 0);
        const vis = rowsCek().filter(terlihat);
        if (cekSemua) {
            cekSemua.checked = vis.length > 0 && vis.every(c => c.checked);
            cekSemua.indeterminate = !cekSemua.checked && dipilih.length > 0;
        }
    }

    if (cekSemua) {
        cekSemua.addEventListener('change', () => { 
            rowsCek().filter(terlihat).forEach(c => c.checked = cekSemua.checked); 
            updatePilihan(); 
        });
        document.addEventListener('change', e => { 
            if (e.target.classList.contains('cek-row')) updatePilihan(); 
        });
        document.getElementById('modalHapus').addEventListener('show.bs.modal', () => {
            const dipilih = rowsCek().filter(c => c.checked && terlihat(c));
            document.getElementById('hapusJumlah').textContent = dipilih.length;
            document.getElementById('hapusSearch').value = liveSearchInput.value.trim();
            document.getElementById('hapusIds').innerHTML = dipilih.map(c => '<input type="hidden" name="ids[]" value="' + c.value + '">').join('');
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var G0 = <?php echo json_encode($grafik); ?>;
    var NAMA = ['Menunggu', 'Diproses', 'Selesai', 'Ditolak'];
    var WARNA = ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444'];

    if (typeof Chart === 'undefined') return;

    var css = function (n) { return getComputedStyle(document.documentElement).getPropertyValue(n).trim(); };
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.weight = '600';
    Chart.defaults.animation = reduce ? false : { duration: 450 };

    var bar = new Chart(document.getElementById('chartBulan'), {
        type: 'bar',
        data: {
            labels: G0.labels,
            datasets: [
                { label: NAMA[0], data: G0.menunggu, backgroundColor: WARNA[0], borderRadius: 4, maxBarThickness: 34 },
                { label: NAMA[1], data: G0.diproses, backgroundColor: WARNA[1], borderRadius: 4, maxBarThickness: 34 },
                { label: NAMA[2], data: G0.selesai,  backgroundColor: WARNA[2], borderRadius: 4, maxBarThickness: 34 },
                { label: NAMA[3], data: G0.ditolak,  backgroundColor: WARNA[3], borderRadius: 4, maxBarThickness: 34 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } } },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    var donut = new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: { labels: NAMA, datasets: [{ data: G0.rasio, backgroundColor: WARNA, borderWidth: 3 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } }
    });

    function render(g) {
        bar.data.labels = g.labels;
        bar.data.datasets[0].data = g.menunggu;
        bar.data.datasets[1].data = g.diproses;
        bar.data.datasets[2].data = g.selesai;
        bar.data.datasets[3].data = g.ditolak;
        bar.update('none');

        var total12 = 0;
        [g.menunggu, g.diproses, g.selesai, g.ditolak].forEach(function (a) { a.forEach(function (n) { total12 += n; }); });
        document.getElementById('totalBulan').textContent = 'Total: ' + total12;

        var total = g.rasio[0] + g.rasio[1] + g.rasio[2] + g.rasio[3];
        var d = donut.data.datasets[0];
        d.data = total === 0 ? [1] : g.rasio;
        d.backgroundColor = total === 0 ? [css('--border')] : WARNA;
        donut.update('none');
        document.getElementById('donutTotal').textContent = total;

        document.getElementById('legendStatus').innerHTML = NAMA.map(function (n, i) {
            var p = total ? Math.round(g.rasio[i] * 100 / total) : 0;
            return '<li><span class="dot" style="background:' + WARNA[i] + '"></span>' + n +
                   '<span class="n">' + g.rasio[i] + '</span><span class="p">' + p + '%</span></li>';
        }).join('');
    }

    window.renderGrafik = render;
    render(G0);
})();
</script>
</body>
</html>