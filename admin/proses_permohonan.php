<?php
// admin/proses_permohonan.php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit(); }

$me = session_id();
$nama_admin_sekarang = $_SESSION['nama_lengkap'] ?? 'Admin';
$id_permohonan = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ==========================================
// ENDPOINT AJAX 1: HEARTBEAT (PING)
// ==========================================
if (isset($_GET['ajax_ping'])) {
    $waktu_sql = date('Y-m-d H:i:s');
    mysqli_query($conn, "UPDATE permohonan SET waktu_lock = '$waktu_sql' WHERE id_permohonan = $id_permohonan AND session_lock = '$me'");
    exit();
}

// ==========================================
// ENDPOINT AJAX 2: MELEPAS KUNCI (UNLOAD/KELUAR)
// ==========================================
if (isset($_GET['ajax_release'])) {
    mysqli_query($conn, "UPDATE permohonan SET sedang_diproses_oleh = NULL, session_lock = NULL, waktu_lock = NULL WHERE id_permohonan = $id_permohonan AND session_lock = '$me'");
    exit();
}

// ==========================================
// ENDPOINT AJAX 3: POLLING ADMIN 2
// ==========================================
if (isset($_GET['ajax_lock'])) {
    $res_l = mysqli_query($conn, "SELECT sedang_diproses_oleh, session_lock, waktu_lock, status_permohonan FROM permohonan WHERE id_permohonan = $id_permohonan");
    $row_l = mysqli_fetch_assoc($res_l);
    
    $lock_ses = trim((string)($row_l['session_lock'] ?? ''));
    $lock_oleh = trim((string)($row_l['sedang_diproses_oleh'] ?? 'Admin Lain'));
    $waktu_l = $row_l['waktu_lock'] ?? '';
    $status_saat_ini = $row_l['status_permohonan'] ?? '';
    
    $is_locked = false;
    if ($lock_ses !== '' && $lock_ses !== $me) {
        if (!empty($waktu_l) && (time() - strtotime($waktu_l)) <= 5) {
            $is_locked = true; 
        }
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['locked' => $is_locked, 'admin' => $lock_oleh, 'status' => $status_saat_ini]);
    exit();
}

$msg = "";
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$nama_bulan_indo = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

function tglWeb($v, $nama_bulan_indo) {
    if (empty($v) || $v === '0000-00-00' || strtotime($v) === false) return '-';
    $t = strtotime($v);
    return date('d', $t) . ' ' . $nama_bulan_indo[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function txtPdf($v) {
    $v = ($v === null || $v === '') ? '-' : (string)$v;
    $r = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $v);
    return $r === false ? $v : $r;
}

// ==========================================
// FUNGSI HELPER: MENGGABUNGKAN LAMPIRAN KE DALAM UKURAN A4
// ==========================================
function appendLampiranToPdf($pdf, $filePath, $has_fpdi) {
    if (!file_exists($filePath)) return;
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // Ukuran standar Kertas A4 (dalam mm)
    $a4_w = 210;
    $a4_h = 297;
    $margin = 15; // Margin agar lampiran tidak terlalu mepet pinggir

    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $pdf->AddPage('P', 'A4'); // Paksa tambah halaman A4
        $size = @getimagesize($filePath);
        if ($size) {
            $imgW = $size[0]; 
            $imgH = $size[1];
            // Hitung skala agar gambar muat di dalam A4 dikurangi margin
            $ratio = min(($a4_w - ($margin * 2)) / $imgW, ($a4_h - ($margin * 2)) / $imgH);
            $newW = $imgW * $ratio;
            $newH = $imgH * $ratio;
            // Posisikan gambar di tengah (Center)
            $x = ($a4_w - $newW) / 2;
            $y = ($a4_h - $newH) / 2;
            $pdf->Image($filePath, $x, $y, $newW, $newH);
        } else {
            $pdf->Image($filePath, 15, 15, 180);
        }
    } elseif ($ext === 'pdf' && $has_fpdi) {
        try {
            $pageCount = $pdf->setSourceFile($filePath);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                $tplW = isset($size['width']) ? $size['width'] : (isset($size['w']) ? $size['w'] : 210);
                $tplH = isset($size['height']) ? $size['height'] : (isset($size['h']) ? $size['h'] : 297);
                
                $pdf->AddPage('P', 'A4'); // Selalu paksakan A4 Portrait
                
                // Hitung skala agar template PDF terlampir muat di dalam A4 dikurangi margin
                $ratio = min(($a4_w - ($margin * 2)) / $tplW, ($a4_h - ($margin * 2)) / $tplH);
                $newW = $tplW * $ratio;
                $newH = $tplH * $ratio;
                
                // Posisikan ke tengah halaman
                $x = ($a4_w - $newW) / 2;
                $y = ($a4_h - $newH) / 2;
                
                $pdf->useTemplate($templateId, $x, $y, $newW, $newH);
            }
        } catch (Exception $e) {
            // Abaikan file error/terenkripsi
        }
    }
}

// Siapkan basis data untuk status "Ditolak": kolom alasan_ditolak + nilai "Ditolak" pada kolom status (bila berupa ENUM)
if (empty($_SESSION['kolom_tolak_ok'])) {
    try {
        $ck = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'nama_kabupaten'");
        if ($ck && $ck->num_rows === 0) mysqli_query($conn, "ALTER TABLE permohonan ADD COLUMN nama_kabupaten VARCHAR(100) NULL AFTER nama_klinik");
        $c1 = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'alasan_ditolak'");
        if ($c1 && $c1->num_rows === 0) mysqli_query($conn, "ALTER TABLE permohonan ADD COLUMN alasan_ditolak TEXT NULL");
        $c2 = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'status_permohonan'");
        $k2 = $c2 ? mysqli_fetch_assoc($c2) : null;
        if ($k2 && stripos($k2['Type'], 'enum(') === 0 && stripos($k2['Type'], "'Ditolak'") === false) {
            $tipe_baru = substr($k2['Type'], 0, -1) . ",'Ditolak')";
            $def = ($k2['Default'] !== null && $k2['Default'] !== '') ? " DEFAULT '" . mysqli_real_escape_string($conn, $k2['Default']) . "'" : '';
            $nul = ($k2['Null'] === 'YES') ? ' NULL' : ' NOT NULL';
            mysqli_query($conn, "ALTER TABLE permohonan MODIFY status_permohonan $tipe_baru$nul$def");
        }
        $_SESSION['kolom_tolak_ok'] = true;
    } catch (Throwable $ex) {}
}

$query = "SELECT * FROM permohonan WHERE id_permohonan = $id_permohonan";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if(!$data) { die("Data tidak ditemukan!"); }

// ==========================================
// LOGIKA PENGAMBILALIHAN KUNCI
// ==========================================
$cek_ses_lock = $data['session_lock'] ?? null;
$waktu_lock = $data['waktu_lock'] ?? null;
$waktu_sekarang = time();
$is_locked_by_other = false;
$admin_pembawa_kunci = $data['sedang_diproses_oleh'] ?? 'Admin Lain';

if (!empty($cek_ses_lock) && $cek_ses_lock !== $me) {
    $selisih_waktu = $waktu_sekarang - strtotime($waktu_lock);
    if ($selisih_waktu <= 5) { 
        $is_locked_by_other = true; 
    } else {
        $is_locked_by_other = false;
    }
}

if (!$is_locked_by_other && !isset($_POST['update_proses']) && !isset($_POST['tolak_permohonan'])) {
    $waktu_sql = date('Y-m-d H:i:s');
    $safe_nama = mysqli_real_escape_string($conn, $nama_admin_sekarang);
    mysqli_query($conn, "UPDATE permohonan SET sedang_diproses_oleh = '$safe_nama', session_lock = '$me', waktu_lock = '$waktu_sql' WHERE id_permohonan = $id_permohonan");
}

// ==========================================
// PROSES SIMPAN FORMULAR & UPLOAD FILE
// ==========================================
if (isset($_POST['tolak_permohonan'])) {
    if ($is_locked_by_other) {
        $_SESSION['flash_msg'] = "Gagal menolak! Data ini masih aktif diisi oleh: " . $admin_pembawa_kunci;
        header("Location: proses_permohonan.php?id=" . $id_permohonan);
        exit();
    }
    $alasan = trim(preg_replace('/\R{3,}/u', "\n\n", (string)($_POST['alasan_tolak'] ?? '')));
    if (mb_strlen($alasan, 'UTF-8') > 1000) $alasan = mb_substr($alasan, 0, 1000, 'UTF-8');
    if ($alasan === '') {
        $_SESSION['flash_msg'] = "Alasan penolakan wajib diisi.";
        header("Location: proses_permohonan.php?id=" . $id_permohonan);
        exit();
    }
    // Berkas terbitan lama (jika ada) dihapus karena permohonan tidak jadi diterbitkan
    if (!empty($data['file_terbitan']) && file_exists("../uploads/dokumen_terbitan/" . basename($data['file_terbitan']))) {
        @unlink("../uploads/dokumen_terbitan/" . basename($data['file_terbitan']));
    }
    $st_t = mysqli_prepare($conn, "UPDATE permohonan SET status_permohonan = 'Ditolak', alasan_ditolak = ?, nama_pic = ?, tanggal_proses = CURDATE(),
                                   file_terbitan = NULL, sedang_diproses_oleh = NULL, session_lock = NULL, waktu_lock = NULL WHERE id_permohonan = ?");
    mysqli_stmt_bind_param($st_t, 'ssi', $alasan, $nama_admin_sekarang, $id_permohonan);
    mysqli_stmt_execute($st_t);
    $_SESSION['flash_msg'] = "Permohonan ditolak. Alasan penolakan tersimpan dan tampil di halaman tracking pemohon.";
    header("Location: proses_permohonan.php?id=" . $id_permohonan); exit();
}

if(isset($_POST['update_proses'])) {
    if ($is_locked_by_other) {
        $_SESSION['flash_msg'] = "Gagal menyimpan! Data ini masih aktif diisi oleh: " . $admin_pembawa_kunci;
        header("Location: proses_permohonan.php?id=" . $id_permohonan);
        exit();
    }

    $status_baru = $_POST['status_permohonan'];
    $sql_alasan = ($status_baru === 'Ditolak') ? '' : ', alasan_ditolak = NULL';   // keluar dari status Ditolak = alasan dihapus
    $nama_pic = !empty($_POST['nama_pic']) ? mysqli_real_escape_string($conn, $_POST['nama_pic']) : "";
    $tanggal_proses = !empty($_POST['tanggal_proses']) ? $_POST['tanggal_proses'] : "";
    $kuota_disetujui = !empty($_POST['kuota_disetujui']) ? intval($_POST['kuota_disetujui']) : 0;
    $sisa_kuota = !empty($_POST['sisa_kuota_faktur']) ? intval($_POST['sisa_kuota_faktur']) : 0;

    $q_pic = $nama_pic ? "'$nama_pic'" : "NULL";
    $q_tgl = $tanggal_proses ? "'$tanggal_proses'" : "NULL";
    $q_kuota = $kuota_disetujui ? $kuota_disetujui : "NULL";
    $q_sisa = $sisa_kuota ? $sisa_kuota : "NULL";

    $kode_tracking = $data['kode_tracking'];

    // PROSES UPLOAD FILE BILLING
    $sql_billing = "";
    $nama_billing = "";
    if (isset($_FILES['file_billing']) && $_FILES['file_billing']['error'] == UPLOAD_ERR_OK) {
        if (!is_dir("../uploads/dokumen_billing/")) { @mkdir("../uploads/dokumen_billing/", 0777, true); }
        $ext = strtolower(pathinfo($_FILES['file_billing']['name'], PATHINFO_EXTENSION));
        $nama_billing = "BILLING_" . $kode_tracking . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES['file_billing']['tmp_name'], "../uploads/dokumen_billing/" . $nama_billing);
        $sql_billing = ", file_billing = '$nama_billing'";
    }

    // PROSES UPLOAD FILE SBBK
    $sql_sbbk = "";
    $nama_sbbk = "";
    if (isset($_FILES['file_sbbk']) && $_FILES['file_sbbk']['error'] == UPLOAD_ERR_OK) {
        if (!is_dir("../uploads/dokumen_sbbk/")) { @mkdir("../uploads/dokumen_sbbk/", 0777, true); }
        $ext = strtolower(pathinfo($_FILES['file_sbbk']['name'], PATHINFO_EXTENSION));
        $nama_sbbk = "SBBK_" . $kode_tracking . "_" . time() . "." . $ext;
        move_uploaded_file($_FILES['file_sbbk']['tmp_name'], "../uploads/dokumen_sbbk/" . $nama_sbbk);
        $sql_sbbk = ", file_sbbk = '$nama_sbbk'";
    }

    if($status_baru != 'Selesai') {
        if(!empty($data['file_terbitan']) && file_exists("../uploads/dokumen_terbitan/" . $data['file_terbitan'])) {
            @unlink("../uploads/dokumen_terbitan/" . $data['file_terbitan']);
        }
        $query_update = "UPDATE permohonan SET 
                            status_permohonan = '$status_baru', nama_pic = $q_pic, tanggal_proses = $q_tgl, 
                            kuota_disetujui = $q_kuota, sisa_kuota_faktur = $q_sisa
                            $sql_billing $sql_sbbk,
                            file_terbitan = NULL, sedang_diproses_oleh = NULL, session_lock = NULL, waktu_lock = NULL $sql_alasan
                         WHERE id_permohonan = $id_permohonan";
        mysqli_query($conn, $query_update);
        $_SESSION['flash_msg'] = "Perubahan berhasil disimpan!"; 
        
    } else {
        $query_update = "UPDATE permohonan SET 
                            status_permohonan = '$status_baru', nama_pic = $q_pic, tanggal_proses = $q_tgl, 
                            kuota_disetujui = $q_kuota, sisa_kuota_faktur = $q_sisa 
                            $sql_billing $sql_sbbk,
                            sedang_diproses_oleh = NULL, session_lock = NULL, waktu_lock = NULL $sql_alasan
                         WHERE id_permohonan = $id_permohonan";
        mysqli_query($conn, $query_update);

        $data_terbaru = mysqli_fetch_assoc(mysqli_query($conn, $query));

        // GENERATE PDF
        require('../fpdf/fpdf.php');
        $has_fpdi = false;
        $possible_paths = ['../fpdf/fpdi/src/autoload.php', '../fpdf/FPDI-2.6.9/src/autoload.php', '../fpdf/fpdi/fpdi.php'];
        foreach ($possible_paths as $p) {
            if (file_exists($p)) { require_once($p); $has_fpdi = true; break; }
        }
        
        if ($has_fpdi && class_exists('setasign\Fpdi\Fpdi')) { $pdf = new \setasign\Fpdi\Fpdi('P', 'mm', 'A4'); } 
        elseif ($has_fpdi && class_exists('FPDI')) { $pdf = new FPDI('P', 'mm', 'A4'); } 
        else { $pdf = new FPDF('P', 'mm', 'A4'); $has_fpdi = false; }

        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(25.4, 10, 25.4);
        $pdf->AddPage('P', 'A4');

        if (file_exists('../assets/images/kop_template.jpg')) $pdf->Image('../assets/images/kop_template.jpg', -1.8, 1.2, 215.6, 0);
        elseif (file_exists('../assets/images/kop_surat.jpg')) $pdf->Image('../assets/images/kop_surat.jpg', 15, 10, 180, 0);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY(0, 51.2);
        $pdf->Cell(210, 5.4, 'PERMOHONAN DAN PEMBERIAN EICV', 0, 0, 'C');

        $X_LABEL = 25.4; $X_TITIK = 88.9; $X_NILAI = 94.0; $LEBAR_NILAI = 190 - 94.0; $Y0 = 60.3; $PITCH = 8.3;

        $susunan = [
            ['Tanggal Permohonan', tglWeb($data_terbaru['tanggal_permohonan'], $nama_bulan_indo)],
            ['Nama Klinik', $data_terbaru['nama_klinik']], ['Kabupaten / Kota', ($data_terbaru['nama_kabupaten'] ?? '') !== '' ? $data_terbaru['nama_kabupaten'] : '-'], ['No Izin Penerbitan', $data_terbaru['no_izin_penerbitan']],
            ['Berlaku Sampai', tglWeb($data_terbaru['masa_berlaku_izin'], $nama_bulan_indo)], 0.6,
            ['Jumlah Stock eICV', $data_terbaru['jumlah_stock_akhir_eicv']], ['Jumlah Pemakaian eICV', $data_terbaru['jumlah_pemakaian_eicv']],
            ['Jumlah Permintaan', $data_terbaru['jumlah_permintaan']], 0.6, ['Nama Pemohon', $data_terbaru['nama_pemohon']], 0.6,
            ['Tanggal Faktur Pembelian', tglWeb($data_terbaru['tanggal_faktur'], $nama_bulan_indo)], ['No Faktur', $data_terbaru['no_invoice']],
            ['Nama Distributor', $data_terbaru['nama_distributor']], ['Jumlah Pembelian', $data_terbaru['jumlah_pembelian']],
            ['Merek Vaksin', $data_terbaru['merek_vaksin']], ['Jenis Vaksin', $data_terbaru['jenis_vaksin']],
            ['No Batch', $data_terbaru['batch_vaksin']], ['Exp Date', tglWeb($data_terbaru['exp_date'], $nama_bulan_indo)],
            ['Jumlah Vaksin MM', $data_terbaru['jumlah_vaksin_mm']], ['Jumlah Vaksin Polio', $data_terbaru['jumlah_vaksin_polio']],
            'GARIS',
            ['Nama PIC', $data_terbaru['nama_pic']], ['Tanggal Proses', tglWeb($data_terbaru['tanggal_proses'], $nama_bulan_indo)],
            ['Kuota Diberikan', $data_terbaru['kuota_disetujui']], ['Sisa Kuota Faktur', $data_terbaru['sisa_kuota_faktur']],
        ];

        $slot = 0.0;
        foreach ($susunan as $br) {
            if ($br === 'GARIS') {
                $slot += 0.5; $yGaris = $Y0 + $slot * $PITCH;
                $pdf->SetDrawColor(0, 0, 0); $pdf->SetLineWidth(0.4);
                $pdf->Line(25.4, $yGaris, 184.6, $yGaris); $slot += 0.9; continue;
            }
            if (is_numeric($br)) { $slot += $br; continue; }
            $y = $Y0 + $slot * $PITCH - 1.8;
            $pdf->SetFont('Arial', '', 11); $pdf->SetXY($X_LABEL, $y); $pdf->Cell($X_TITIK - $X_LABEL, $PITCH, txtPdf($br[0]), 0, 0, 'L');
            $pdf->SetXY($X_TITIK, $y); $pdf->Cell(4, $PITCH, ':', 0, 0, 'L');
            $nilai = txtPdf($br[1]); $ukuran = 11; $pdf->SetFont('Arial', 'B', $ukuran);
            while ($pdf->GetStringWidth($nilai) > $LEBAR_NILAI && $ukuran > 8) { $ukuran -= 0.5; $pdf->SetFont('Arial', 'B', $ukuran); }
            while ($pdf->GetStringWidth($nilai) > $LEBAR_NILAI && strlen($nilai) > 4) { $nilai = substr($nilai, 0, -4) . '...'; }
            $pdf->SetXY($X_NILAI, $y); $pdf->Cell($LEBAR_NILAI, $PITCH, $nilai, 0, 0, 'L');
            $slot += 1;
        }

        // MENGGABUNGKAN LAMPIRAN BILLING (DIPAKSA A4)
        $path_billing_final = !empty($nama_billing) ? "../uploads/dokumen_billing/" . $nama_billing : 
                             (!empty($data_terbaru['file_billing']) ? "../uploads/dokumen_billing/" . $data_terbaru['file_billing'] : "");
        if (!empty($path_billing_final)) {
            appendLampiranToPdf($pdf, $path_billing_final, $has_fpdi);
        }

        // MENGGABUNGKAN LAMPIRAN SBBK (DIPAKSA A4)
        $path_sbbk_final = !empty($nama_sbbk) ? "../uploads/dokumen_sbbk/" . $nama_sbbk : 
                          (!empty($data_terbaru['file_sbbk']) ? "../uploads/dokumen_sbbk/" . $data_terbaru['file_sbbk'] : "");
        if (!empty($path_sbbk_final)) {
            appendLampiranToPdf($pdf, $path_sbbk_final, $has_fpdi);
        }

        // SIMPAN SATU FILE LENGKAP
        $nama_file_terbitan = "TERBITAN_" . $data_terbaru['kode_tracking'] . ".pdf";
        if (!is_dir("../uploads/dokumen_terbitan/")) { @mkdir("../uploads/dokumen_terbitan/", 0777, true); }
        $lokasi_simpan = "../uploads/dokumen_terbitan/" . $nama_file_terbitan;
        $pdf->Output('F', $lokasi_simpan);
        
        mysqli_query($conn, "UPDATE permohonan SET file_terbitan='$nama_file_terbitan' WHERE id_permohonan=$id_permohonan");
        $_SESSION['flash_msg'] = "Proses Selesai! PDF Surat Terbitan & Lampiran berhasil digabung menjadi 1 file."; 
    }

    header("Location: proses_permohonan.php?id=" . $id_permohonan); exit();
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Data - Admin Panel</title>
    <script>
        (function () {
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root, [data-theme="light"] {
            --bg: #f8fafc; --surface: rgba(255,255,255,.88); --surface-2: #ffffff; --soft: #f1f5f9;
            --border: #e2e8f0; --text: #1e293b; --muted: #64748b; --primary: #4f46e5; --drag-hover: rgba(79, 70, 229, 0.1);
            --grad: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            --shadow: 0 20px 40px -15px rgba(0,0,0,.07), 0 0 15px rgba(0,0,0,.02);
            --nav: rgba(255,255,255,.8); --aurora1: rgba(99,102,241,.10); --aurora2: rgba(255,126,103,.07);
        }
        [data-theme="dark"] {
            --bg: #0b1020; --surface: rgba(21,27,48,.85); --surface-2: #151b30; --soft: #1b2340;
            --border: #29325a; --text: #e6e9f5; --muted: #98a2c3; --primary: #8b93ff; --drag-hover: rgba(139, 147, 255, 0.15);
            --grad: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
            --shadow: 0 20px 40px -15px rgba(0,0,0,.6);
            --nav: rgba(14,19,38,.8); --aurora1: rgba(99,102,241,.18); --aurora2: rgba(255,126,103,.08);
        }

        html body {
            font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh;
            background-color: var(--bg); color: var(--text);
            background-image: radial-gradient(at 0% 0%, var(--aurora1) 0, transparent 50%),
                              radial-gradient(at 100% 100%, var(--aurora2) 0, transparent 50%);
            background-attachment: fixed; transition: background-color .3s, color .3s;
        }

        .navbar-admin { background: var(--nav); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); }
        .brand-text { background: var(--grad); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .user-chip { background: var(--surface-2); border: 1px solid var(--border); color: var(--text); }
        .glass-card { background: var(--surface); backdrop-filter: blur(16px); border-radius: 24px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .btn-executive { border-radius: 50px; padding: .65rem 1.5rem; font-weight: 600; font-size: .9rem; transition: all .25s ease; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
        
        .theme-btn { width: 42px; height: 42px; border-radius: 50%; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); display: grid; place-items: center; transition: .25s; }
        .theme-btn:hover { transform: rotate(18deg) scale(1.06); }
        [data-theme="light"] .theme-btn .bi-sun-fill { display: none; }
        [data-theme="dark"]  .theme-btn .bi-moon-stars-fill { display: none; }
        .btn-logout-merah { background-color: #ef4444 !important; color: #ffffff !important; border: 1px solid #dc2626 !important; }
        .btn-logout-merah:hover { background-color: #dc2626 !important; transform: translateY(-2px); }

        .btn-back-dashboard { background: var(--surface-2) !important; color: var(--text) !important; border: 1px solid var(--border) !important; border-radius: 50px !important; font-weight: 600 !important; padding: 0.65rem 1.5rem !important; transition: all 0.25s ease !important; display: inline-flex !important; align-items: center !important; text-decoration: none !important; }
        .btn-back-dashboard:hover { background: var(--primary) !important; color: #ffffff !important; border-color: var(--primary) !important; transform: translateY(-2px); }

        .btn-submit-generate { background: var(--primary) !important; color: #ffffff !important; border: none !important; font-size: 1.05rem !important; transition: all 0.25s ease !important; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3) !important; }
        .btn-submit-generate:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(99, 102, 241, 0.45) !important; }

        .data-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; font-weight: 800; margin-bottom: 2px; letter-spacing: 0.5px; }
        .data-value { font-size: 1.05rem; color: var(--text); font-weight: 600; margin-bottom: 18px; word-wrap: break-word; }
        .section-title { color: var(--primary); font-weight: 700; font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }

        .form-control, .form-select { background-color: var(--soft); color: var(--text); border: 1px solid var(--border); border-radius: 12px; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.2); background-color: var(--surface-2); color: var(--text); }
        
        .form-box { background: var(--soft); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
        .workflow-step { font-size: 0.84rem; line-height: 1.5; margin-bottom: 0.3rem; display: flex; align-items: flex-start; gap: 6px; }

        .drop-zone { border: 2px dashed var(--border); border-radius: 12px; padding: 1.5rem 1rem; text-align: center; background: var(--surface-2); cursor: pointer; transition: all 0.3s ease; position: relative; }
        .drop-zone:hover, .drop-zone.dragover { border-color: var(--primary); background: var(--drag-hover); }
        .drop-zone input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .drop-zone-icon { font-size: 2rem; color: var(--primary); margin-bottom: 10px; display: block; }
        .drop-zone-text { font-size: 0.85rem; color: var(--muted); font-weight: 500; }
        .drop-zone-file-name { display: block; margin-top: 8px; font-size: 0.9rem; font-weight: 700; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .group-title { font-weight: 700; font-size: 1.05rem; color: var(--text); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid var(--soft); padding-bottom: 8px; }
        .group-title i { color: var(--primary); }
        .detail-group { margin-bottom: 1.5rem; padding-bottom: 0.5rem; }

        .stepper-container { display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 2rem; padding: 0 10px; }
        .stepper-line { position: absolute; top: 50%; left: 0; right: 0; height: 3px; background: var(--border); transform: translateY(-50%); z-index: 1; }
        .stepper-progress { position: absolute; top: 50%; left: 0; height: 3px; background: var(--primary); transform: translateY(-50%); z-index: 2; transition: width 0.4s ease; }
        .step-item { position: relative; z-index: 3; display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer; }
        .step-icon { width: 40px; height: 40px; border-radius: 50%; background: var(--surface-2); border: 2px solid var(--border); display: grid; place-items: center; font-size: 1.1rem; color: var(--muted); transition: all 0.3s ease; }
        .step-label { font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; transition: all 0.3s ease; }
        
        .step-item.active .step-icon { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.2); }
        .step-item.active .step-label { color: var(--primary); }
        .step-item.completed .step-icon { background: var(--surface-2); border-color: var(--primary); color: var(--primary); }
        .stepper-locked { pointer-events: none; opacity: 0.6; }

        .modal-content { border-radius: 20px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); }
        .modal-header { border-bottom: 1px solid var(--border); }

        /* Tombol & panel tolak (dipisah dari stepper status) */
        .tolak-area { margin: -0.6rem 0 1.2rem; display: flex; justify-content: center; }
        .btn-tolak { border-radius: 50px; font-weight: 700; font-size: .85rem; padding: .5rem 1.3rem; color: #dc2626; background: rgba(239,68,68,.08); border: 1.5px solid rgba(239,68,68,.45); display: inline-flex; align-items: center; gap: 8px; transition: .2s; }
        .btn-tolak:hover { background: #ef4444; color: #fff; border-color: #ef4444; transform: translateY(-2px); }
        .btn-tolak:disabled { opacity: .5; pointer-events: none; }
        .tolak-info { margin-bottom: 1.2rem; padding: 14px 16px; border-radius: 16px; background: rgba(239,68,68,.08); border: 1.5px solid rgba(239,68,68,.4); }
        .tolak-info .judul { color: #dc2626; font-weight: 800; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .tolak-info .isi { font-size: .92rem; font-weight: 600; white-space: pre-line; word-break: break-word; }
        .tolak-info small { color: var(--muted); }
        [data-theme="dark"] .btn-tolak, [data-theme="dark"] .tolak-info .judul { color: #fca5a5; }
        .alasan-chip { border: 1px solid var(--border); background: var(--soft); color: var(--text); border-radius: 50px; font-size: .78rem; font-weight: 700; padding: 5px 12px; transition: .2s; }
        .alasan-chip:hover { border-color: #ef4444; color: #dc2626; }
        .step-item.tertolak-reset .step-icon { opacity: .55; }

        /* Pratinjau PDF ringan: saat jendela pratinjau terbuka, efek berat di latar dimatikan agar scroll tidak patah-patah */
        body.pdf-aktif, body.pdf-aktif *, body.pdf-aktif *::before, body.pdf-aktif *::after { backdrop-filter: none !important; -webkit-backdrop-filter: none !important; animation-play-state: paused !important; }
        body.pdf-aktif { background-image: none !important; background-attachment: scroll !important; }
        body.pdf-aktif .navbar-admin, body.pdf-aktif .glass-card { box-shadow: none !important; }
        body.pdf-aktif .modal-backdrop { backdrop-filter: none !important; }
        #modalLihatLengkap .modal-content, #docPreviewModal .modal-content { overflow: visible; box-shadow: none; }
        #modalLihatLengkap .modal-dialog, #docPreviewModal .modal-dialog { will-change: auto; }

        /* Pratinjau dokumen lengkap (di dalam aplikasi, ringan saat di-scroll) */
        #modalLihatLengkap .modal-content { display: flex; flex-direction: column; height: 90vh; box-shadow: none; }
        #modalLihatLengkap .modal-body { flex: 1 1 auto; min-height: 0; padding: 8px 12px 12px; position: relative; overflow: hidden; }
        #frameLihatLengkap { display: block; width: 100%; height: 100%; border: 0; background: #f8fafc; }
        .pdf-memuat { position: absolute; inset: 8px 12px 12px; display: grid; place-items: center; color: var(--muted); font-weight: 700; pointer-events: none; }
        .pdf-memuat[hidden] { display: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-admin sticky-top py-3 mb-4">
    <div class="container-fluid px-3 px-md-5 d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold fs-4 d-flex align-items-center gap-2 m-0" href="dashboard.php" id="linkBrand">
            <div class="rounded-3 p-2 text-white d-flex align-items-center justify-content-center shadow-sm" style="background: var(--grad); width: 38px; height: 38px;">
                <i class="bi bi-shield-lock-fill fs-6"></i>
            </div>
            <span class="brand-text">Admin Portal</span>
        </a>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <button type="button" class="theme-btn" id="themeToggle" aria-label="Ganti tema">
                <i class="bi bi-moon-stars-fill"></i><i class="bi bi-sun-fill"></i>
            </button>
            <div class="user-chip d-none d-md-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-person-circle fs-5" style="color: var(--primary);"></i>
                <span class="fw-semibold small"><?php echo e($nama_admin_sekarang); ?></span>
            </div>
            <a href="logout.php" id="linkLogout" class="btn btn-executive btn-logout-merah px-3 py-2 small">
                <i class="bi bi-box-arrow-right me-1 d-none d-sm-inline-block"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-md-5 mt-2 mb-3">
    <a href="dashboard.php" id="linkDashboard" class="btn btn-back-dashboard shadow-sm">
        <i class="bi bi-arrow-left-circle-fill me-2" style="color: var(--primary);"></i> Kembali ke Dashboard
    </a>
</div>

<div class="container-fluid px-3 px-md-5 mb-5">

    <?php if($is_locked_by_other): ?>
        <div class="alert alert-warning border-0 rounded-4 fw-bold shadow-sm d-flex align-items-center gap-3 px-4 py-3 mb-4" style="background: rgba(245,158,11,.15); color: #b45309;">
            <i class="bi bi-cone-striped fs-3"></i>
            <div>
                Peringatan: Data ini sedang diisi oleh <b><?php echo e($admin_pembawa_kunci); ?></b>.<br>
                <span class="fw-normal small">Anda berada dalam Mode Pantau (Hanya membaca) untuk mencegah tabrakan. Saat admin tersebut selesai/keluar, halaman otomatis dimuat ulang dan Anda dapat langsung mengisi data.</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-stretch">
        <!-- KOLOM KIRI: DETAIL PEMOHON -->
        <div class="col-lg-7">
            <div class="glass-card p-4 p-md-5 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4" style="border-color: var(--border) !important;">
                    <div class="section-title m-0"><i class="bi bi-person-lines-fill"></i> Detail Pemohon</div>
                    <span class="badge user-chip border shadow-sm px-3 py-2 fs-6 rounded-pill" style="font-family: monospace;"><?php echo e($data['kode_tracking']); ?></span>
                </div>
                
                <div class="flex-grow-1">
                    <div class="detail-group">
                        <div class="group-title"><i class="bi bi-person-badge"></i> Data Pemohon & Klinik</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-label">Tanggal Permohonan</div><div class="data-value"><?php echo tglWeb($data['tanggal_permohonan'], $nama_bulan_indo); ?></div>
                                <div class="data-label">Nama Pemohon</div><div class="data-value"><?php echo htmlspecialchars($data['nama_pemohon']); ?></div>
                                <div class="data-label">Nama Klinik</div><div class="data-value" style="color: var(--primary); font-size: 1.15rem;"><?php echo htmlspecialchars($data['nama_klinik']); ?></div>
                                <div class="data-label">Kabupaten / Kota</div><div class="data-value"><?php echo !empty($data['nama_kabupaten']) ? htmlspecialchars($data['nama_kabupaten']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-label">No Izin Penerbitan</div><div class="data-value"><?php echo htmlspecialchars($data['no_izin_penerbitan']); ?></div>
                                <div class="data-label text-danger">Masa Berlaku Izin</div><div class="data-value text-danger"><?php echo (!empty($data['masa_berlaku_izin']) && $data['masa_berlaku_izin'] != '0000-00-00') ? tglWeb($data['masa_berlaku_izin'], $nama_bulan_indo) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="group-title"><i class="bi bi-clipboard2-pulse"></i> Data EICV & Permintaan</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-label">Jumlah Stock Akhir EICV</div><div class="data-value"><?php echo $data['jumlah_stock_akhir_eicv']; ?></div>
                                <div class="data-label">Jumlah Pemakaian EICV</div><div class="data-value"><?php echo $data['jumlah_pemakaian_eicv']; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-label" style="color: #10b981;">Jumlah Permintaan</div><div class="data-value" style="color: #10b981; font-size: 1.15rem;"><?php echo $data['jumlah_permintaan']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="group-title"><i class="bi bi-receipt-cutoff"></i> Data Faktur & Vaksin</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="data-label">Tanggal Faktur</div><div class="data-value"><?php echo tglWeb($data['tanggal_faktur'], $nama_bulan_indo); ?></div>
                                <div class="data-label">Nama Distributor</div><div class="data-value"><?php echo htmlspecialchars($data['nama_distributor']); ?></div>
                                <div class="data-label">No Faktur</div><div class="data-value"><?php echo htmlspecialchars($data['no_invoice']); ?></div>
                                <div class="data-label">Jumlah Pembelian</div><div class="data-value"><?php echo $data['jumlah_pembelian']; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="data-label">Merek Vaksin</div><div class="data-value"><?php echo htmlspecialchars($data['merek_vaksin']); ?></div>
                                <div class="data-label">Jenis Vaksin</div><div class="data-value"><?php echo htmlspecialchars($data['jenis_vaksin']); ?></div>
                                <div class="data-label">No Batch</div><div class="data-value"><?php echo htmlspecialchars($data['batch_vaksin']); ?></div>
                                <div class="data-label">Exp Date</div><div class="data-value"><?php echo tglWeb($data['exp_date'], $nama_bulan_indo); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($data['file_syarat']): ?>
                <div class="mt-auto pt-3 border-top" style="border-color: var(--border) !important;">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-4" style="background: var(--surface-2); border: 1px dashed var(--primary);">
                        <div>
                            <div class="data-label mb-1"><i class="bi bi-paperclip"></i> Dokumen Syarat</div>
                            <div class="fw-semibold small text-muted"><?php echo htmlspecialchars($data['file_syarat']); ?></div>
                        </div>
                        <button type="button" class="btn btn-sm px-4 py-2 text-white fw-bold rounded-pill" style="background: var(--grad);" data-bs-toggle="modal" data-bs-target="#docPreviewModal">
                            <i class="bi bi-eye-fill me-1"></i> Pratinjau
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KOLOM KANAN: AKSI ADMIN -->
        <div class="col-lg-5">
            <div class="glass-card p-4 p-md-5 h-100 d-flex flex-column" style="border: 2px solid var(--primary);">
                <div class="section-title"><i class="bi bi-sliders"></i> Aksi Admin</div>
                
                <?php if(!$is_locked_by_other): ?>
                <div class="alert mb-4 border rounded-4" style="background: var(--aurora1); border-color: var(--border) !important; color: var(--text);">
                    <div class="fw-bold mb-2 d-flex align-items-center gap-2" style="color: var(--primary);"><i class="bi bi-info-circle-fill fs-5"></i> Alur Proses:</div>
                    <div class="workflow-step"><span class="badge bg-primary rounded-circle p-1 me-1" style="width:16px;height:16px;font-size:9px;display:inline-grid;place-items:center;">1</span> Ubah status ke <b>"Selesai"</b>.</div>
                    <div class="workflow-step"><span class="badge bg-primary rounded-circle p-1 me-1" style="width:16px;height:16px;font-size:9px;display:inline-grid;place-items:center;">2</span> Isi Form Terbitan, Upload Billing Pembayaran & SBBK EICV.</div>
                    <div class="workflow-step"><span class="badge bg-primary rounded-circle p-1 me-1" style="width:16px;height:16px;font-size:9px;display:inline-grid;place-items:center;">3</span> Dokumen otomatis digabung jadi<b class="ms-1">1 File PDF</b>.</div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="formProses" enctype="multipart/form-data" class="flex-grow-1 d-flex flex-column">
                    <div class="data-label mb-3" style="color: var(--text);">Status Pengajuan</div>
                    <div class="stepper-container <?php echo $is_locked_by_other ? 'stepper-locked' : ''; ?>">
                        <div class="stepper-line"></div>
                        <div class="stepper-progress" id="stepperProgress" style="width: 0%;"></div>
                        <input type="hidden" name="status_permohonan" id="inputStatus" value="<?php echo $data['status_permohonan']; ?>">

                        <div class="step-item completed" data-status="Menunggu" data-progress="0">
                            <div class="step-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="step-label">Menunggu</div>
                        </div>
                        <div class="step-item" data-status="Diproses" data-progress="50">
                            <div class="step-icon"><i class="bi bi-gear-fill"></i></div>
                            <div class="step-label">Diproses</div>
                        </div>
                        <div class="step-item" data-status="Selesai" data-progress="100">
                            <div class="step-icon"><i class="bi bi-check-lg"></i></div>
                            <div class="step-label">Selesai</div>
                        </div>
                    </div>

                    <?php if (($data['status_permohonan'] ?? '') === 'Ditolak'): ?>
                    <div class="tolak-info">
                        <div class="judul"><i class="bi bi-x-octagon-fill"></i> Permohonan Ditolak</div>
                        <div class="isi"><?php echo !empty($data['alasan_ditolak']) ? e($data['alasan_ditolak']) : 'Tidak ada alasan tercatat.'; ?></div>
                        <small>Untuk membuka kembali, pilih status Menunggu / Diproses / Selesai lalu klik Simpan Perubahan.</small>
                    </div>
                    <?php endif; ?>

                    <?php if(!$is_locked_by_other): ?>
                    <div class="tolak-area">
                        <button type="button" class="btn-tolak" data-bs-toggle="modal" data-bs-target="#modalTolak"><i class="bi bi-x-octagon-fill"></i> <?php echo (($data['status_permohonan'] ?? '') === 'Ditolak') ? 'Ubah Alasan Penolakan' : 'Tolak Permohonan'; ?></button>
                    </div>
                    <?php endif; ?>

                    <div class="form-box mb-3 mt-2">
                        <div class="data-label mb-3" style="color: var(--text);"><i class="bi bi-pen me-1"></i> Isian Form Terbitan</div>
                        <div class="form-floating mb-3"><input type="text" name="nama_pic" class="form-control fw-semibold" id="pic" value="<?php echo htmlspecialchars($data['nama_pic'] ?? ''); ?>" <?php echo $is_locked_by_other ? 'readonly' : ''; ?>><label for="pic">Nama PIC</label></div>
                        <div class="form-floating mb-3"><input type="date" name="tanggal_proses" class="form-control fw-semibold" id="tglp" value="<?php echo $data['tanggal_proses']; ?>" <?php echo $is_locked_by_other ? 'readonly' : ''; ?>><label for="tglp">Tanggal Proses</label></div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><div class="form-floating"><input type="number" name="kuota_disetujui" class="form-control fw-semibold" id="ksetuju" value="<?php echo $data['kuota_disetujui']; ?>" <?php echo $is_locked_by_other ? 'readonly' : ''; ?>><label for="ksetuju">Kuota Diberikan</label></div></div>
                            <div class="col-6"><div class="form-floating"><input type="number" name="sisa_kuota_faktur" class="form-control fw-semibold" id="sfaktur" value="<?php echo $data['sisa_kuota_faktur']; ?>" <?php echo $is_locked_by_other ? 'readonly' : ''; ?>><label for="sfaktur">Sisa Kuota Faktur</label></div></div>
                        </div>

                        <div class="row g-3 pt-3 mt-1 border-top" style="border-color: var(--border) !important;">
                            <div class="col-6">
                                <label class="data-label mb-2" style="color: var(--text);">Billing Pembayaran</label>
                                <div class="drop-zone" id="dropZoneBilling" style="<?php echo $is_locked_by_other ? 'opacity:0.6; pointer-events:none;' : ''; ?>">
                                    <i class="bi bi-cloud-arrow-up-fill drop-zone-icon"></i>
                                    <span class="drop-zone-text">Tarik ke sini<br>atau klik</span>
                                    <span class="drop-zone-file-name" id="fileNameBilling"><?php echo !empty($data['file_billing']) ? $data['file_billing'] : ''; ?></span>
                                    <input type="file" name="file_billing" id="fileInputBilling" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked_by_other ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="data-label mb-2" style="color: var(--text);">SBBK EICV</label>
                                <div class="drop-zone" id="dropZoneSbbk" style="<?php echo $is_locked_by_other ? 'opacity:0.6; pointer-events:none;' : ''; ?>">
                                    <i class="bi bi-cloud-arrow-up-fill drop-zone-icon"></i>
                                    <span class="drop-zone-text">Tarik ke sini<br>atau klik</span>
                                    <span class="drop-zone-file-name" id="fileNameSbbk"><?php echo !empty($data['file_sbbk']) ? $data['file_sbbk'] : ''; ?></span>
                                    <input type="file" name="file_sbbk" id="fileInputSbbk" accept=".pdf,.jpg,.jpeg,.png" <?php echo $is_locked_by_other ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mb-3" id="draftIndicator" style="display: none; transition: opacity 0.3s;">
                        <span class="badge rounded-pill shadow-sm" style="background: var(--aurora1); color: var(--text); border: 1px solid var(--border);">
                            <i class="bi bi-cloud-check-fill text-success me-1"></i> Draf otomatis tersimpan
                        </span>
                    </div>

                    <div class="mt-auto">
                        <!-- BAGIAN TOMBOL LIHAT DOKUMEN YANG DIPERKECIL -->
                        <?php if($data['status_permohonan'] == 'Selesai' && !empty($data['file_terbitan'])): ?>
                            <div class="mb-4 text-center">
                                <button type="button" id="btnLihatLengkap" class="btn btn-outline-primary fw-bold rounded-pill px-4 py-2" style="border-width: 2px; font-size: 0.95rem;"
                                        data-pdf-url="../uploads/dokumen_terbitan/<?php echo e($data['file_terbitan']); ?>?v=<?php echo (int)@filemtime(__DIR__ . '/../uploads/dokumen_terbitan/' . basename((string)$data['file_terbitan'])); ?>">
                                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Lihat Dokumen Lengkap 
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if(!$is_locked_by_other): ?>
                        <button type="submit" name="update_proses" class="btn btn-submit-generate w-100 rounded-pill py-3 fw-bold">
                            Simpan Perubahan
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if($data['file_syarat']): ?>
<div class="modal fade" id="docPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="color: var(--primary);"><i class="bi bi-file-earmark-text me-2"></i>Pratinjau Dokumen Syarat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: 75vh; background: #525659;">
                <?php 
                    $ext = strtolower(pathinfo($data['file_syarat'], PATHINFO_EXTENSION));
                    if($ext === 'pdf'):
                ?>
                    <iframe src="../uploads/dokumen_syarat/<?php echo $data['file_syarat']; ?>#toolbar=0" width="100%" height="100%" style="border: none;"></iframe>
                <?php else: ?>
                    <div class="w-100 h-100 d-flex justify-content-center align-items-center" style="overflow: auto; padding: 20px;">
                        <img src="../uploads/dokumen_syarat/<?php echo $data['file_syarat']; ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if($data['status_permohonan'] == 'Selesai' && !empty($data['file_terbitan'])): ?>
<div class="modal fade" id="modalLihatLengkap" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="color: var(--primary);"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Dokumen Lengkap · <?php echo e($data['kode_tracking']); ?></h5>
                <a href="../uploads/dokumen_terbitan/<?php echo e($data['file_terbitan']); ?>" download class="btn btn-sm btn-outline-primary rounded-pill fw-bold ms-auto me-3"><i class="bi bi-download me-1"></i> Unduh</a>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="pdf-memuat" id="pdfMemuat" hidden><span><i class="bi bi-arrow-repeat me-2"></i>Memuat dokumen...</span></div>
                <iframe id="frameLihatLengkap" src="" title="Dokumen lengkap"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if(!$is_locked_by_other): ?>
<div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="" id="formTolak">
                <input type="hidden" name="tolak_permohonan" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" style="color: #dc2626;"><i class="bi bi-x-octagon-fill me-2"></i>Tolak Permohonan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2" style="color: var(--muted);">Permohonan <b style="color: var(--text);"><?php echo e($data['kode_tracking']); ?></b> · <?php echo e($data['nama_klinik']); ?>. Alasan ini akan tampil kepada pemohon di halaman tracking.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3" id="alasanCepat">
                        <button type="button" class="alasan-chip">Dokumen tidak lengkap</button>
                        <button type="button" class="alasan-chip">Data tidak sesuai</button>
                        <button type="button" class="alasan-chip">Izin sudah kedaluwarsa</button>
                        <button type="button" class="alasan-chip">Kuota tidak mencukupi</button>
                    </div>
                    <label for="alasanTolak" class="data-label mb-1" style="color: var(--text);">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="alasan_tolak" id="alasanTolak" class="form-control" rows="4" maxlength="1000" required placeholder="Tuliskan alasan penolakan dengan jelas..."><?php echo e(($data['status_permohonan'] ?? '') === 'Ditolak' ? ($data['alasan_ditolak'] ?? '') : ''); ?></textarea>
                    <div class="text-end small mt-1" style="color: var(--muted);"><span id="hitungAlasan">0</span>/1000</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-ghost-modal rounded-pill px-4 fw-bold" data-bs-dismiss="modal" style="border: 1px solid var(--border); background: var(--soft); color: var(--text);">Batal</button>
                    <button type="submit" class="btn rounded-pill px-4 fw-bold text-white" style="background: #ef4444;"><i class="bi bi-x-octagon-fill me-1"></i> Tolak Permohonan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.addEventListener('load', () => { window.scrollTo(0, 0); });

    /* Matikan efek berat di latar selama pratinjau dokumen terbuka (scroll PDF jadi ringan) */
    ['modalLihatLengkap', 'docPreviewModal'].forEach(id => {
        const m = document.getElementById(id); if (!m) return;
        m.addEventListener('show.bs.modal', () => document.body.classList.add('pdf-aktif'));
        m.addEventListener('hidden.bs.modal', () => document.body.classList.remove('pdf-aktif'));
    });

    (function () {
        const ta = document.getElementById('alasanTolak'); if (!ta) return;
        const hit = document.getElementById('hitungAlasan');
        const upd = () => { hit.textContent = ta.value.length; };
        ta.addEventListener('input', upd); upd();
        document.querySelectorAll('#alasanCepat .alasan-chip').forEach(b => b.addEventListener('click', () => {
            const t = b.textContent.trim();
            if (ta.value.indexOf(t) === -1) ta.value = ta.value.trim() ? ta.value.trim().replace(/[.;]$/, '') + '; ' + t : t;
            upd(); ta.focus();
        }));
        document.getElementById('modalTolak').addEventListener('shown.bs.modal', () => ta.focus());
        document.getElementById('formTolak').addEventListener('submit', () => { try { localStorage.removeItem('draft_proses_<?php echo $id_permohonan; ?>'); } catch (e) {} });
    })();

    (function () {
        const btn = document.getElementById('btnLihatLengkap'), el = document.getElementById('modalLihatLengkap');
        if (!btn || !el) return;
        const modal = new bootstrap.Modal(el), frame = document.getElementById('frameLihatLengkap'), memuat = document.getElementById('pdfMemuat');
        btn.addEventListener('click', () => {
            const u = btn.getAttribute('data-pdf-url');
            memuat.hidden = false;
            frame.src = u + (u.indexOf('#') < 0 ? '#navpanes=0&view=FitH' : '');
            modal.show();
        });
        frame.addEventListener('load', () => { memuat.hidden = true; });
        el.addEventListener('hidden.bs.modal', () => { frame.src = ''; memuat.hidden = true; });
    })();

    document.getElementById('themeToggle').addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('eicv-theme', next); } catch (e) {}
    });

    function setupDropZone(dropZoneId, inputId, fileNameId) {
        const dropZone = document.getElementById(dropZoneId);
        const inputElement = document.getElementById(inputId);
        const fileNameDisplay = document.getElementById(fileNameId);
        if(!dropZone || !inputElement) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false); });
        inputElement.addEventListener('change', () => {
            if (inputElement.files && inputElement.files.length > 0) {
                fileNameDisplay.textContent = inputElement.files[0].name;
                dropZone.querySelector('.drop-zone-text').style.display = 'none';
                dropZone.querySelector('.drop-zone-icon').classList.replace('bi-cloud-arrow-up-fill', 'bi-file-earmark-check-fill');
                dropZone.querySelector('.drop-zone-icon').style.color = '#10b981'; 
            }
        });
        
        if(fileNameDisplay.textContent.trim() !== "") {
            dropZone.querySelector('.drop-zone-text').style.display = 'none';
            dropZone.querySelector('.drop-zone-icon').classList.replace('bi-cloud-arrow-up-fill', 'bi-file-earmark-check-fill');
            dropZone.querySelector('.drop-zone-icon').style.color = '#10b981'; 
        }
    }
    setupDropZone('dropZoneBilling', 'fileInputBilling', 'fileNameBilling');
    setupDropZone('dropZoneSbbk', 'fileInputSbbk', 'fileNameSbbk');

    const isLockedByOther = <?php echo $is_locked_by_other ? 'true' : 'false'; ?>;
    const formProses = document.getElementById('formProses');
    const draftKey = 'draft_proses_<?php echo $id_permohonan; ?>';
    const inputFields = ['pic', 'tglp', 'ksetuju', 'sfaktur', 'inputStatus'];
    const draftIndicator = document.getElementById('draftIndicator');
    
    const stepItems = document.querySelectorAll('.step-item');
    const inputStatus = document.getElementById('inputStatus');
    const stepperProgress = document.getElementById('stepperProgress');

    function updateVisualStepper(statusValue) {
        if (statusValue === 'Ditolak') {   // ditolak: stepper dikosongkan (tidak ada tahap aktif)
            stepperProgress.style.width = '0%';
            stepItems.forEach(step => step.classList.remove('active', 'completed'));
            return;
        }
        let targetIndex = 0;
        if(statusValue === 'Diproses') { stepperProgress.style.width = '50%'; targetIndex = 1; }
        else if(statusValue === 'Selesai') { stepperProgress.style.width = '100%'; targetIndex = 2; }
        else { stepperProgress.style.width = '0%'; targetIndex = 0; }

        stepItems.forEach((step, idx) => {
            step.classList.remove('active', 'completed');
            if (idx < targetIndex) step.classList.add('completed');
            else if (idx === targetIndex) step.classList.add('active');
        });
    }

    if(!isLockedByOther) {
        try {
            const savedDraft = localStorage.getItem(draftKey);
            if (savedDraft) {
                const draftData = JSON.parse(savedDraft);
                let hasDraft = false;
                inputFields.forEach(id => {
                    if (draftData[id] !== undefined && document.getElementById(id)) {
                        document.getElementById(id).value = draftData[id];
                        hasDraft = true;
                    }
                });
                if(draftData['inputStatus']) updateVisualStepper(draftData['inputStatus']);
                if(hasDraft) draftIndicator.style.display = 'block';
            } else {
                updateVisualStepper(inputStatus.value);
            }
        } catch (e) {
            updateVisualStepper(inputStatus.value);
        }

        let draftTimeout;
        formProses.addEventListener('input', () => {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(() => {
                const dataToSave = {};
                inputFields.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) dataToSave[id] = el.value;
                });
                localStorage.setItem(draftKey, JSON.stringify(dataToSave));
                draftIndicator.style.display = 'block';
            }, 800);
        });

        stepItems.forEach((item) => {
            item.addEventListener('click', () => {
                const newStatus = item.getAttribute('data-status');
                inputStatus.value = newStatus;
                updateVisualStepper(newStatus);
                formProses.dispatchEvent(new Event('input'));
            });
        });
    } else {
        updateVisualStepper(inputStatus.value);
    }

    <?php if($is_locked_by_other): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Peringatan: Data Sedang Diisi',
            html: 'Admin <b><?php echo e($admin_pembawa_kunci); ?></b> sedang memproses data ini secara aktif.<br><br>Anda masuk dalam <b>Mode Pantau</b> (tombol simpan disembunyikan) untuk mencegah tabrakan data. Mohon tunggu, layar Anda akan dimuat ulang otomatis begitu admin tersebut selesai.',
            icon: 'warning',
            confirmButtonText: 'Baik, Saya Mengerti',
            confirmButtonColor: '#4f46e5',
            allowOutsideClick: false
        });
    });
    <?php endif; ?>

    function releaseLock() {
        if (!isLockedByOther) {
            navigator.sendBeacon('proses_permohonan.php?id=<?php echo $id_permohonan; ?>&ajax_release=1');
        }
    }
    window.addEventListener('beforeunload', function() { releaseLock(); });
    document.getElementById('linkDashboard').addEventListener('click', releaseLock);
    document.getElementById('linkLogout').addEventListener('click', releaseLock);
    document.getElementById('linkBrand').addEventListener('click', releaseLock);

    if (!isLockedByOther) {
        setInterval(() => { fetch('proses_permohonan.php?id=<?php echo $id_permohonan; ?>&ajax_ping=1', {cache: 'no-store'}).catch(()=>{}); }, 2500); 
    }

    setInterval(() => {
        fetch('proses_permohonan.php?id=<?php echo $id_permohonan; ?>&ajax_lock=1')
            .then(res => res.json())
            .then(data => {
                if (isLockedByOther && !data.locked) {
                    Swal.fire({
                        title: 'Giliran Anda!',
                        text: 'Admin sebelumnya telah selesai atau keluar dari halaman ini. Anda sekarang bisa mengisi data.',
                        icon: 'success',
                        timer: 2500,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => { window.location.reload(); });
                } 
            }).catch(() => {});
    }, 2000);
</script>

<?php if($msg): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        localStorage.removeItem('draft_proses_<?php echo $id_permohonan; ?>');
        Swal.fire({
            icon: 'success',
            title: '<span style="font-size: 1.4rem; font-weight: 800; color: #10b981;">Berhasil!</span>',
            html: '<span style="font-size: 1.05rem; color: var(--muted);"><?php echo addslashes(e($msg)); ?></span>',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            position: 'center', 
            width: '420px',
            padding: '2rem',
            background: 'var(--surface-2)',
            backdrop: 'rgba(15, 23, 42, 0.65)',
            customClass: {
                popup: 'glass-card border-0 rounded-4 shadow-lg'
            }
        });
    });
</script>
<?php endif; ?>
</body>
</html>