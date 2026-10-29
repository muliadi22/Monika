<?php
// config/database.php
$host = "localhost";
$user = "root";       // Sesuaikan jika ada password di XAMPP Anda
$pass = ""; 
$db   = "db_permohonan";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>