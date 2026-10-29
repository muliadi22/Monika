<?php
// proses_permohonan.php
session_start();
require_once 'config/database.php'; // Sesuaikan path jika database.php berada di folder config/

if (isset($_POST['submit_permohonan'])) {
    // 1. Tangkap dan sanitasi semua data dari form
    $tanggal_permohonan = mysqli_real_escape_string($conn, $_POST['tanggal_permohonan']);
    $nama_pemohon       = mysqli_real_escape_string($conn, $_POST['nama_pemohon']);
    $nama_klinik        = mysqli_real_escape_string($conn, $_POST['nama_klinik']);
    $nama_kabupaten     = mysqli_real_escape_string($conn, preg_replace('/\s+/u', ' ', trim((string)($_POST['nama_kabupaten'] ?? ''))));
    $no_izin_penerbitan = mysqli_real_escape_string($conn, $_POST['no_izin_penerbitan']);
    $masa_berlaku_izin  = mysqli_real_escape_string($conn, $_POST['masa_berlaku_izin']);
    
    $jumlah_stock_akhir = (int)$_POST['jumlah_stock_akhir_eicv'];
    $jumlah_pemakaian   = (int)$_POST['jumlah_pemakaian_eicv'];
    $jumlah_permintaan  = (int)$_POST['jumlah_permintaan'];
    
    $tanggal_faktur     = mysqli_real_escape_string($conn, $_POST['tanggal_faktur']);
    $nama_distributor   = mysqli_real_escape_string($conn, $_POST['nama_distributor']);
    $no_invoice         = mysqli_real_escape_string($conn, $_POST['no_invoice']);
    $jumlah_pembelian   = (int)$_POST['jumlah_pembelian'];
    $merek_vaksin       = mysqli_real_escape_string($conn, $_POST['merek_vaksin']);
    $batch_vaksin       = mysqli_real_escape_string($conn, $_POST['batch_vaksin']);
    $exp_date           = mysqli_real_escape_string($conn, $_POST['exp_date']);
    $jenis_vaksin       = mysqli_real_escape_string($conn, $_POST['jenis_vaksin']);
    $jumlah_vaksin_mm   = (int)$_POST['jumlah_vaksin_mm'];
    $jumlah_vaksin_polio= (int)$_POST['jumlah_vaksin_polio'];

    // 2. Generate Kode Tracking Unik (Anti-Tabrakan)
    $kode_tracking = 'REQ-' . date('Ymd') . '-' . mt_rand(1000, 9999);

    // 3. Proses Upload File Syarat
    $nama_file_syarat = "";
    if (isset($_FILES['file_syarat']) && $_FILES['file_syarat']['error'] == 0) {
        $ekstensi_diperbolehkan = array('pdf', 'png', 'jpg', 'jpeg');
        $nama_file = $_FILES['file_syarat']['name'];
        $x = explode('.', $nama_file);
        $ekstensi = strtolower(end($x));
        $ukuran = $_FILES['file_syarat']['size'];
        $file_tmp = $_FILES['file_syarat']['tmp_name'];
        
        if (in_array($ekstensi, $ekstensi_diperbolehkan) === true) {
            if ($ukuran < 5242880) { // Maksimal 5MB
                $nama_file_syarat = $kode_tracking . '_' . time() . '.' . $ekstensi;
                $path_upload = 'uploads/dokumen_syarat/' . $nama_file_syarat;
                
                if (!is_dir('uploads/dokumen_syarat/')) {
                    mkdir('uploads/dokumen_syarat/', 0777, true);
                }
                
                move_uploaded_file($file_tmp, $path_upload);
            } else {
                echo "<script>alert('Ukuran file terlalu besar! Maksimal 5MB.'); window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Ekstensi file tidak diizinkan! Hanya PDF, JPG, atau PNG.'); window.history.back();</script>";
            exit;
        }
    }

    // Pastikan kolom nama_kabupaten tersedia (dibuat otomatis bila belum ada)
    try {
        $cek_kab = mysqli_query($conn, "SHOW COLUMNS FROM permohonan LIKE 'nama_kabupaten'");
        if ($cek_kab && mysqli_num_rows($cek_kab) === 0) {
            mysqli_query($conn, "ALTER TABLE permohonan ADD COLUMN nama_kabupaten VARCHAR(100) NULL AFTER nama_klinik");
        }
    } catch (Throwable $ex) {}

    // 4. Simpan ke Database
    $query_insert = "INSERT INTO permohonan (
        kode_tracking, tanggal_permohonan, nama_pemohon, nama_klinik, nama_kabupaten, no_izin_penerbitan, masa_berlaku_izin,
        jumlah_stock_akhir_eicv, jumlah_pemakaian_eicv, jumlah_permintaan,
        tanggal_faktur, nama_distributor, no_invoice, jumlah_pembelian, merek_vaksin, batch_vaksin, exp_date,
        jenis_vaksin, jumlah_vaksin_mm, jumlah_vaksin_polio, file_syarat, status_permohonan
    ) VALUES (
        '$kode_tracking', '$tanggal_permohonan', '$nama_pemohon', '$nama_klinik', '$nama_kabupaten', '$no_izin_penerbitan', '$masa_berlaku_izin',
        $jumlah_stock_akhir, $jumlah_pemakaian, $jumlah_permintaan,
        '$tanggal_faktur', '$nama_distributor', '$no_invoice', $jumlah_pembelian, '$merek_vaksin', '$batch_vaksin', '$exp_date',
        '$jenis_vaksin', $jumlah_vaksin_mm, $jumlah_vaksin_polio, '$nama_file_syarat', 'Menunggu'
    )";

    if (mysqli_query($conn, $query_insert)) {
        
        // ==========================================
        // 5. INTEGRASI NOTIFIKASI WHATSAPP (FONNTE)
        // ==========================================
        $token_fonnte = "Bw19G4vnK4NzoydAn9cy"; // Ganti dengan Token Bot Fonnte Anda
        $nomor_admin  = "085657349689"; // Ganti dengan Nomor WhatsApp Admin Kantor Penerima
        
        $tgl_format = date('d-m-Y', strtotime($tanggal_permohonan));
        
       // PESAN WHATSAPP: KEREN, ELEGAN, & SANTAI TAPI TETAP PROFESIONAL
        $pesan_wa  = "*✨ MONIKA e-ICV ✨*\n\n";
        $pesan_wa .= "Halo Admin, santai sejenak sambil ngopi yuk! ☕ Ada tugas seru nih buat kamu hari ini.\n\n";
        $pesan_wa .= "Sistem *MONIKA e-ICV* baru saja menerima pengajuan permohonan baru yang siap untuk diverifikasi:\n\n";
        $pesan_wa .= "🏢 *Nama Klinik:* $nama_klinik\n";
        $pesan_wa .= "📍 *Kabupaten/Kota:* " . ($nama_kabupaten !== '' ? $nama_kabupaten : '-') . "\n";
        $pesan_wa .= "👤 *Nama Pemohon:* $nama_pemohon\n";
        $pesan_wa .= "📦 *Jumlah Permintaan:* $jumlah_permintaan\n";
        $pesan_wa .= "🔖 *Kode Tracking:* $kode_tracking\n";
        $pesan_wa .= "📅 *Tanggal Pengajuan:* $tgl_format\n\n";
        $pesan_wa .= "Yuk, langsung cuss cek dan proses datanya di *Admin Portal*. Pelan-pelan saja, yang penting tuntas! 💻✨\n\n";
        $pesan_wa .= "_Tetap semangat dan jaga kesehatan ya, you're doing an amazing job!_ 🚀";

        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array(
            'target' => $nomor_admin,
            'message' => $pesan_wa,
            'countryCode' => '62',
          ),
          CURLOPT_HTTPHEADER => array(
            "Authorization: $token_fonnte"
          ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        // ==========================================

        // 6. Redirect Mulus ke Halaman Sukses Anda yang Interaktif
        header("Location: sukses_pengajuan.php?kode=" . urlencode($kode_tracking));
        exit();
        
    } else {
        echo "<script>alert('Gagal menyimpan data ke database: " . mysqli_error($conn) . "'); window.history.back();</script>";
    }
} else {
    header("Location: ajukan_permohonan.php");
    exit;
}
?>