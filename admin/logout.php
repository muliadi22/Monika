<?php
// admin/logout.php
session_start();

// Kosongkan semua data sesi
$_SESSION = [];

// Hapus cookie sesi di browser
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Hancurkan sesi di server
session_destroy();

// Cegah halaman admin tersimpan di cache (tombol Back tidak menampilkan data lama)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: login.php?logout=1");
exit();
?>