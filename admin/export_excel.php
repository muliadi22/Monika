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

// Tambahkan ekstensi file
$nama_file .= ".xls";

// Memaksa browser mendownload file Excel dengan nama dinamis
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename={$nama_file}");
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

// Helper format tanggal
function fmtDate($date) {
    if(empty($date) || $date == '0000-00-00') return '-';
    return date('d-m-Y', strtotime($date));
}
?>

<center>
    <h2>LAPORAN REKAPITULASI PERMOHONAN DAN PEMBERIAN EICV</h2>
    <p><b><?php echo $teks_subtitle; ?></b></p>
</center>

<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
        <!-- Baris Header Utama (Kategori) -->
        <tr style="background-color: #2c3e50; color: #ffffff; text-align: center; font-weight: bold; font-size: 14px;">
            <th rowspan="2" style="vertical-align: middle;">No</th>
            <th rowspan="2" style="vertical-align: middle;">Kode Tracking</th>
            <th colspan="6" style="background-color: #34495e;">1. DATA PEMOHON & KLINIK</th>
            <th colspan="3" style="background-color: #16a085;">2. DATA EICV & PERMINTAAN</th>
            <th colspan="10" style="background-color: #2980b9;">3. DATA FAKTUR & VAKSIN</th>
            <th colspan="5" style="background-color: #d35400;">4. KEPUTUSAN ADMIN</th>
        </tr>
        
        <!-- Baris Header Sub (Nama Kolom) -->
        <tr style="background-color: #ecf0f1; color: #000000; text-align: center; font-weight: bold; font-size: 12px;">
            <!-- Group 1 -->
            <th>Tgl Permohonan</th>
            <th>Nama Pemohon</th>
            <th>Nama Klinik</th>
            <th>Kabupaten / Kota</th>
            <th>No Izin Penerbitan</th>
            <th>Masa Berlaku Izin</th>
            <!-- Group 2 -->
            <th>Stock Akhir EICV</th>
            <th>Pemakaian EICV</th>
            <th>Jml Permintaan</th>
            <!-- Group 3 -->
            <th>Tgl Faktur</th>
            <th>Nama Distributor</th>
            <th>No Faktur</th>
            <th>Jml Pembelian</th>
            <th>Merek Vaksin</th>
            <th>Batch Vaksin</th>
            <th>Exp Date Vaksin</th>
            <th>Jenis Vaksin</th>
            <th>Jml Vaksin MM</th>
            <th>Jml Vaksin Polio</th>
            <!-- Group 4 -->
            <th>Status Permohonan</th>
            <th>Nama PIC Proses</th>
            <th>Tgl Diproses</th>
            <th>Kuota Diberikan</th>
            <th>Sisa Kuota Faktur</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1;
        if(mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) { 
        ?>
        <!-- vertical-align: middle memastikan data sejajar lurus di tengah baris -->
        <tr style="vertical-align: middle;">
            <td align="center"><?php echo $no++; ?></td>
            <td align="center"><b><?php echo $row['kode_tracking']; ?></b></td>
            
            <!-- Group 1: Pemohon -->
            <td align="center"><?php echo fmtDate($row['tanggal_permohonan']); ?></td>
            <td align="left"><?php echo htmlspecialchars($row['nama_pemohon']); ?></td>
            <td align="left"><?php echo htmlspecialchars($row['nama_klinik']); ?></td>
            <td align="left"><?php echo !empty($row['nama_kabupaten']) ? htmlspecialchars($row['nama_kabupaten']) : '-'; ?></td>
            <td align="center" style="mso-number-format:'\@';"><?php echo htmlspecialchars($row['no_izin_penerbitan']); ?></td>
            <td align="center"><?php echo fmtDate($row['masa_berlaku_izin']); ?></td>
            
            <!-- Group 2: EICV -->
            <td align="center"><?php echo $row['jumlah_stock_akhir_eicv']; ?></td>
            <td align="center"><?php echo $row['jumlah_pemakaian_eicv']; ?></td>
            <td align="center" style="font-weight: bold; color: #16a085;"><?php echo $row['jumlah_permintaan']; ?></td>
            
            <!-- Group 3: Faktur & Vaksin -->
            <td align="center"><?php echo fmtDate($row['tanggal_faktur']); ?></td>
            <!-- Nama Distributor dibikin Rata Kiri -->
            <td align="left"><?php echo htmlspecialchars($row['nama_distributor']); ?></td>
            <td align="center" style="mso-number-format:'\@';"><?php echo htmlspecialchars($row['no_invoice']); ?></td>
            <td align="center"><?php echo $row['jumlah_pembelian']; ?></td>
            <td align="center"><?php echo htmlspecialchars($row['merek_vaksin']); ?></td>
            <td align="center" style="mso-number-format:'\@';"><?php echo htmlspecialchars($row['batch_vaksin']); ?></td>
            <td align="center"><?php echo fmtDate($row['exp_date']); ?></td>
            <td align="center"><?php echo htmlspecialchars($row['jenis_vaksin']); ?></td>
            <td align="center"><?php echo $row['jumlah_vaksin_mm'] ?? '0'; ?></td>
            <td align="center"><?php echo $row['jumlah_vaksin_polio'] ?? '0'; ?></td>
            
            <!-- Group 4: Admin -->
            <td align="center"><b><?php echo $row['status_permohonan']; ?></b></td>
            <td align="left"><?php echo !empty($row['nama_pic']) ? htmlspecialchars($row['nama_pic']) : '-'; ?></td>
            <td align="center"><?php echo fmtDate($row['tanggal_proses']); ?></td>
            <td align="center" style="font-weight: bold; color: #d35400;"><?php echo !empty($row['kuota_disetujui']) ? $row['kuota_disetujui'] : '0'; ?></td>
            <td align="center"><?php echo !empty($row['sisa_kuota_faktur']) ? $row['sisa_kuota_faktur'] : '0'; ?></td>
        </tr>
        <?php 
            }
        } else {
        ?>
        <tr>
            <td colspan="26" align="center" style="padding: 20px; font-style: italic;">Tidak ada data permohonan yang ditemukan untuk filter ini.</td>
        </tr>
        <?php } ?>
    </tbody>
</table>
