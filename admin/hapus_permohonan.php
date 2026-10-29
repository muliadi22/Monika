<?php
// admin/hapus_permohonan.php  — hapus data permohonan terpilih beserta file-nya
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: dashboard.php"); exit(); }

$balik = 'dashboard.php';
$qs = [];
foreach (['search', 'bulan', 'tahun'] as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') $qs[$k] = (string)$_POST[$k];
}
if ($qs) $balik .= '?' . http_build_query($qs);
$sep = strpos($balik, '?') === false ? '?' : '&';

if (empty($_POST['csrf']) || empty($_SESSION['csrf_hapus']) || !hash_equals($_SESSION['csrf_hapus'], $_POST['csrf'])) {
    header("Location: {$balik}{$sep}hapus=gagal"); exit();
}

$ids = [];
if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $v) { if (ctype_digit((string)$v)) $ids[] = (int)$v; }
}
$ids = array_values(array_unique($ids));
if (!$ids) { header("Location: {$balik}{$sep}hapus=kosong"); exit(); }

function hapus_file($folder, $nama) {
    $nama = basename((string)$nama);
    if ($nama === '' || $nama === '.' || $nama === '..') return;
    $path = __DIR__ . '/../uploads/' . $folder . '/' . $nama;
    if (is_file($path)) @unlink($path);
}

// Data yang sedang diproses petugas lain (kunci kerja aktif) tidak boleh dihapus
$me = substr(hash('sha256', session_id()), 0, 40);
$ada_kunci = false;
$rc = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'dikunci_sampai'");
if ($rc && mysqli_num_rows($rc) > 0) $ada_kunci = true;

$terhapus = 0; $dilewati = 0;
$sel = mysqli_prepare($conn, $ada_kunci
    ? "SELECT file_syarat, file_terbitan, dikunci_oleh, (dikunci_sampai IS NOT NULL AND dikunci_sampai > NOW()) AS terkunci FROM permohonan WHERE id_permohonan = ?"
    : "SELECT file_syarat, file_terbitan FROM permohonan WHERE id_permohonan = ?");
$del = mysqli_prepare($conn, "DELETE FROM permohonan WHERE id_permohonan = ?");
foreach ($ids as $id) {
    mysqli_stmt_bind_param($sel, "i", $id);
    mysqli_stmt_execute($sel);
    $res = mysqli_stmt_get_result($sel);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) continue;
    if ($ada_kunci && !empty($row['terkunci']) && ($row['dikunci_oleh'] ?? '') !== $me) { $dilewati++; continue; }
    mysqli_stmt_bind_param($del, "i", $id);
    if (mysqli_stmt_execute($del) && mysqli_stmt_affected_rows($del) > 0) {
        hapus_file('dokumen_syarat',   $row['file_syarat']   ?? '');
        hapus_file('dokumen_terbitan', $row['file_terbitan'] ?? '');
        $terhapus++;
    }
}
header("Location: {$balik}{$sep}hapus=ok&n={$terhapus}" . ($dilewati ? "&lewat={$dilewati}" : ""));
exit();