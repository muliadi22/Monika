<?php
// admin/export_excel.php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in'])) { 
    header("Location: login.php"); 
    exit(); 
}

// --- MENANGKAP FILTER DARI URL ---
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";
$filter_bulan = (isset($_GET['bulan']) && $_GET['bulan'] != "") ? mysqli_real_escape_string($conn, $_GET['bulan']) : "";
$filter_tahun = (isset($_GET['tahun']) && $_GET['tahun'] != "") ? mysqli_real_escape_string($conn, $_GET['tahun']) : "";
$filter_klinik = (isset($_GET['klinik']) && $_GET['klinik'] != "") ? mysqli_real_escape_string($conn, $_GET['klinik']) : "";

// Array Bulan
$arr_bulan = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];

// --- MEMBUAT NAMA FILE & TEKS SUBTITLE DINAMIS ---
$nama_file = "Laporan_Rekap_EICV";

if (!empty($filter_bulan)) {
    $teks_subtitle = "Periode Laporan: Bulan " . $arr_bulan[$filter_bulan];
    $nama_file .= "_" . $arr_bulan[$filter_bulan];
    if (!empty($filter_tahun)) {
        $teks_subtitle .= " " . $filter_tahun;
        $nama_file .= "_" . $filter_tahun;
    }
} elseif (!empty($filter_tahun)) {
    $teks_subtitle = "Periode Laporan: Tahun " . $filter_tahun;
    $nama_file .= "_Tahun_" . $filter_tahun;
} else {
    $teks_subtitle = "Periode Laporan: Keseluruhan Data";
    $nama_file .= "_Keseluruhan";
}

if (!empty($search_query)) {
    $teks_subtitle .= " | Pencarian: '" . $search_query . "'";
}

if (!empty($filter_klinik)) {
    $teks_subtitle .= " | Klinik: " . htmlspecialchars($_GET['klinik']);
    $nama_file .= "_" . preg_replace('/[^A-Za-z0-9]/', '', $_GET['klinik']);
}

$nama_file .= ".xls";

// HEADER UNTUK EXPORT EXCEL YANG AMAN & STABIL DI SEMUA PERANGKAT
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$nama_file}\"");
header("Cache-Control: max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// --- MEMBANGUN QUERY ---
$query = "SELECT * FROM permohonan WHERE 1=1";

if(!empty($search_query)) {
    $query .= " AND (nama_klinik LIKE '%$search_query%' OR nama_pemohon LIKE '%$search_query%' OR kode_tracking LIKE '%$search_query%')";
}
if(!empty($filter_bulan)) {
    $query .= " AND MONTH(tanggal_permohonan) = '$filter_bulan'";
}
if(!empty($filter_tahun)) {
    $query .= " AND YEAR(tanggal_permohonan) = '$filter_tahun'";
}
if(!empty($filter_klinik)) {
    $query .= " AND nama_klinik = '$filter_klinik'";
}
$query .= " ORDER BY tanggal_permohonan DESC, id_permohonan DESC";
$result = mysqli_query($conn, $query);

function fmtDate($date) {
    if(empty($date) || $date == '0000-00-00') return '-';
    return date('d-m-Y', strtotime($date));
}

// DEFINISI WARNA KATEGORI KOLOM
$bg_umum   = '#f2f2f2'; // Abu-abu
$bg_klinik = '#d9e1f2'; // Biru Pastel
$bg_eicv   = '#e2efda'; // Hijau Pastel
$bg_faktur = '#fff2cc'; // Kuning Pastel
$bg_admin  = '#fce4d6'; // Oranye Pastel
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Rekap Data</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        .title { font-size: 16px; font-weight: bold; text-align: center; color: #000000; font-family: Calibri, sans-serif; }
        .subtitle { font-size: 12px; text-align: center; margin-bottom: 20px; color: #000000; font-family: Calibri, sans-serif; }
        table { border-collapse: collapse; font-family: Calibri, sans-serif; font-size: 11pt; }
        th, td { border: 0.5pt solid #000000; padding: 5px; vertical-align: middle; white-space: nowrap; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
    </style>
</head>
<body>

<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td colspan="26" class="title">LAPORAN REKAPITULASI PERMOHONAN DAN PEMBERIAN EICV</td>
    </tr>
    <tr>
        <td colspan="26" class="subtitle"><b><?php echo htmlspecialchars($teks_subtitle); ?></b></td>
    </tr>
</table>

<table border="1" cellspacing="0" cellpadding="4">
    <thead>
        <!-- Baris Header Utama (Kategori) -->
        <tr style="color: #000000; text-align: center; font-weight: bold; font-size: 11pt;">
            <th rowspan="2" style="background-color: <?php echo $bg_umum; ?>;">No</th>
            <th rowspan="2" style="background-color: <?php echo $bg_umum; ?>;">Kode Tracking</th>
            <th colspan="6" style="background-color: <?php echo $bg_klinik; ?>;">1. DATA PEMOHON & KLINIK</th>
            <th colspan="3" style="background-color: <?php echo $bg_eicv; ?>;">2. DATA EICV & PERMINTAAN</th>
            <th colspan="10" style="background-color: <?php echo $bg_faktur; ?>;">3. DATA FAKTUR & VAKSIN</th>
            <th colspan="5" style="background-color: <?php echo $bg_admin; ?>;">4. KEPUTUSAN ADMIN</th>
        </tr>
        
        <!-- Baris Header Sub (Nama Kolom) -->
        <tr style="color: #000000; text-align: center; font-weight: bold; font-size: 11pt;">
            <!-- Group 1 -->
            <th style="background-color: <?php echo $bg_klinik; ?>;">Tgl Permohonan</th>
            <th style="background-color: <?php echo $bg_klinik; ?>;">Nama Pemohon</th>
            <th style="background-color: <?php echo $bg_klinik; ?>;">Nama Klinik</th>
            <th style="background-color: <?php echo $bg_klinik; ?>;">Kabupaten / Kota</th>
            <th style="background-color: <?php echo $bg_klinik; ?>;">No Izin Penerbitan</th>
            <th style="background-color: <?php echo $bg_klinik; ?>;">Masa Berlaku Izin</th>
            <!-- Group 2 -->
            <th style="background-color: <?php echo $bg_eicv; ?>;">Stock Akhir EICV</th>
            <th style="background-color: <?php echo $bg_eicv; ?>;">Pemakaian EICV</th>
            <th style="background-color: <?php echo $bg_eicv; ?>;">Jml Permintaan</th>
            <!-- Group 3 -->
            <th style="background-color: <?php echo $bg_faktur; ?>;">Tgl Faktur</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Nama Distributor</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">No Faktur</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Jml Pembelian</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Merek Vaksin</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Batch Vaksin</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Exp Date Vaksin</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Jenis Vaksin</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Jml Vaksin MM</th>
            <th style="background-color: <?php echo $bg_faktur; ?>;">Jml Vaksin Polio</th>
            <!-- Group 4 -->
            <th style="background-color: <?php echo $bg_admin; ?>;">Status Permohonan</th>
            <th style="background-color: <?php echo $bg_admin; ?>;">Nama PIC Proses</th>
            <th style="background-color: <?php echo $bg_admin; ?>;">Tgl Diproses</th>
            <th style="background-color: <?php echo $bg_admin; ?>;">Kuota Diberikan</th>
            <th style="background-color: <?php echo $bg_admin; ?>;">Sisa Kuota Faktur</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if($result && mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) { 
                
                // --- Logika Pewarnaan Spesial Kolom Status ---
                $st = $row['status_permohonan'];
                $bg_st = '#ffffff'; $color_st = '#000000';
                if ($st == 'Menunggu') { 
                    $bg_st = '#fef3c7'; $color_st = '#92400e'; 
                } elseif ($st == 'Diproses') { 
                    $bg_st = '#e0f2fe'; $color_st = '#0369a1'; 
                } elseif ($st == 'Selesai') { 
                    $bg_st = '#d1fae5'; $color_st = '#065f46'; 
                } elseif ($st == 'Ditolak') { 
                    $bg_st = '#fee2e2'; $color_st = '#991b1b'; 
                }
        ?>
        <tr>
            <!-- UMUM -->
            <td class="text-center" style="background-color: <?php echo $bg_umum; ?>; mso-number-format:'\@';"><?php echo $no++; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_umum; ?>; mso-number-format:'\@';"><b><?php echo htmlspecialchars($row['kode_tracking']); ?></b></td>
            
            <!-- KLINIK -->
            <td class="text-center" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo fmtDate($row['tanggal_permohonan']); ?></td>
            <td class="text-left" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['nama_pemohon']); ?></td>
            <td class="text-left" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['nama_klinik']); ?></td>
            <td class="text-left" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo !empty($row['nama_kabupaten']) ? htmlspecialchars($row['nama_kabupaten']) : '-'; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['no_izin_penerbitan']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_klinik; ?>; mso-number-format:'\@';"><?php echo fmtDate($row['masa_berlaku_izin']); ?></td>
            
            <!-- EICV -->
            <td class="text-center" style="background-color: <?php echo $bg_eicv; ?>; mso-number-format:'0';"><?php echo (int)$row['jumlah_stock_akhir_eicv']; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_eicv; ?>; mso-number-format:'0';"><?php echo (int)$row['jumlah_pemakaian_eicv']; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_eicv; ?>; font-weight: bold; mso-number-format:'0'; color: #000000;"><?php echo (int)$row['jumlah_permintaan']; ?></td>
            
            <!-- FAKTUR -->
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo fmtDate($row['tanggal_faktur']); ?></td>
            <td class="text-left" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['nama_distributor']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['no_invoice']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'0';"><?php echo (int)$row['jumlah_pembelian']; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['merek_vaksin']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['batch_vaksin']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo fmtDate($row['exp_date']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'\@';"><?php echo htmlspecialchars($row['jenis_vaksin']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'0';"><?php echo (int)($row['jumlah_vaksin_mm'] ?? 0); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_faktur; ?>; mso-number-format:'0';"><?php echo (int)($row['jumlah_vaksin_polio'] ?? 0); ?></td>
            
            <!-- ADMIN -->
            <td class="text-center" style="background-color: <?php echo $bg_st; ?>; color: <?php echo $color_st; ?>; mso-number-format:'\@';"><b><?php echo htmlspecialchars($row['status_permohonan']); ?></b></td>
            <td class="text-left" style="background-color: <?php echo $bg_admin; ?>; mso-number-format:'\@';"><?php echo !empty($row['nama_pic']) ? htmlspecialchars($row['nama_pic']) : '-'; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_admin; ?>; mso-number-format:'\@';"><?php echo fmtDate($row['tanggal_proses']); ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_admin; ?>; font-weight: bold; mso-number-format:'0'; color: #000000;"><?php echo !empty($row['kuota_disetujui']) ? (int)$row['kuota_disetujui'] : 0; ?></td>
            <td class="text-center" style="background-color: <?php echo $bg_admin; ?>; mso-number-format:'0';"><?php echo !empty($row['sisa_kuota_faktur']) ? (int)$row['sisa_kuota_faktur'] : 0; ?></td>
        </tr>
        <?php 
            }
        } else {
        ?>
        <tr>
            <td colspan="26" class="text-center" style="padding: 20px; font-style: italic; background-color: #ffffff;">Tidak ada data permohonan yang ditemukan untuk filter ini.</td>
        </tr>
        <?php } ?>
    </tbody>
</table>

</body>
</html>