<?php
// admin/export_pdf.php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in'])) { 
    header("Location: login.php"); 
    exit(); 
}

require('../fpdf/fpdf.php');

// --- MENANGKAP FILTER DARI URL ---
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";
$filter_bulan = (isset($_GET['bulan']) && $_GET['bulan'] != "") ? mysqli_real_escape_string($conn, $_GET['bulan']) : "";
$filter_tahun = (isset($_GET['tahun']) && $_GET['tahun'] != "") ? mysqli_real_escape_string($conn, $_GET['tahun']) : "";
$filter_klinik = (isset($_GET['klinik']) && $_GET['klinik'] != "") ? mysqli_real_escape_string($conn, $_GET['klinik']) : "";

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

$arr_bulan = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];

$teks_subtitle = "Rekap Laporan Keseluruhan";
if (!empty($filter_bulan)) {
    $teks_subtitle = "Rekap Laporan Bulan " . $arr_bulan[$filter_bulan];
    if (!empty($filter_tahun)) $teks_subtitle .= " " . $filter_tahun;
} elseif (!empty($filter_tahun)) {
    $teks_subtitle = "Rekap Laporan Tahun " . $filter_tahun;
}
if (!empty($search_query)) {
    $teks_subtitle .= " (Pencarian: " . $search_query . ")";
}
if (!empty($filter_klinik)) {
    $teks_subtitle .= " (Klinik: " . htmlspecialchars($_GET['klinik']) . ")";
}

// =================================================================================
// MEMBUAT CLASS PDF CUSTOM (MultiCell Table & Auto Header)
// =================================================================================
class PDF_MC_Table extends FPDF {
    var $widths;
    var $aligns;
    var $teks_subtitle = "";
    var $colw = array(); // diisi otomatis sesuai isi data

    function SetWidths($w) { $this->widths = $w; }
    function SetAligns($a) { $this->aligns = $a; }

    function Header() {
        // HANYA TAMPILKAN KOP SURAT DAN JUDUL DI HALAMAN 1
        if ($this->PageNo() == 1) {
            // MENGATUR KOP SURAT
            if(file_exists('../assets/images/kop_surat.jpg')) {
                $lebar_kop = 220; 
                $posisi_x = (297 - $lebar_kop) / 2; 
                
                $this->Image('../assets/images/kop_surat.jpg', $posisi_x, 6, $lebar_kop, 0); 
                $this->SetY(60); // Jarak aman di bawah kop surat
            } else {
                $this->SetFont('Arial','B',11);
                $this->Cell(0, 5, 'KEMENTERIAN KESEHATAN REPUBLIK INDONESIA', 0, 1, 'C');
                $this->SetFont('Arial','B',10);
                $this->Cell(0, 5, 'BALAI KEKARANTINAAN KESEHATAN KELAS I BANJARMASIN', 0, 1, 'C');
                $this->SetFont('Arial','',8);
                $this->Cell(0, 4, 'Jl. A. Yani Km. 21, Banjarbaru, Kalimantan Selatan', 0, 1, 'C');
                $this->SetLineWidth(0.4);
                $this->Line(10, 24, 287, 24);
                $this->SetY(28);
            }

            // JUDUL LAPORAN
            $this->SetFont('Arial','B',11);
            $this->Cell(0, 6, 'LAPORAN REKAPITULASI PERMOHONAN DAN PERMBERIAN EICV', 0, 1, 'C');

            // SUBTITLE
            if(!empty($this->teks_subtitle)){
                $this->SetFont('Arial','I',9);
                $this->Cell(0, 5, $this->teks_subtitle, 0, 1, 'C');
            }
            $this->Ln(5); 
        } else {
            // JIKA HALAMAN 2 DAN SETERUSNYA, Beri jarak margin atas sedikit saja
            $this->SetY(15);
        }

        // HEADER TABEL DENGAN LEBAR PAS 277 mm (Tampil di setiap halaman)
        $this->SetFont('Arial','B',6.5);
        $this->SetFillColor(0, 166, 81); // Hijau Kemenkes
        $this->SetTextColor(255, 255, 255);

        // UBAH 'No Invoice' menjadi 'No Faktur'
        $judul = array('No', 'Tgl Pengajuan', 'Nama Klinik', 'Kabupaten', 'No Faktur', 'Jumlah Stock', 'Jumlah Pemakaian', 'Jumlah Permintaan', 'Jumlah Diberikan', 'Sisa Kuota', 'Nama PIC', 'Status');
        $lebar = $this->colw; // lebar menyesuaikan isi data (total 277 mm)
        for ($i = 0; $i < count($judul); $i++) {
            $this->Cell($lebar[$i], 8, $judul[$i], 1, ($i == count($judul) - 1) ? 1 : 0, 'C', true);
        }
        $this->SetFont('Arial','',7.5);
        $this->SetTextColor(0, 0, 0);
    }

    // Fungsi canggih untuk Auto-Row / MultiCell
    function Row($data) {
        $nb = 0;
        for($i=0; $i<count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = 5 * $nb; // Tinggi sel = 5mm dikali jumlah baris teks ke bawah
        
        $this->CheckPageBreak($h);
        
        for($i=0; $i<count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'C'; // Default rata tengah
            if($i == 2 || $i == 3 || $i == 10) $a = 'L'; // Nama Klinik, Kabupaten, dan Nama PIC diset rata Kiri
            
            $x = $this->GetX();
            $y = $this->GetY();
            
            $this->Rect($x, $y, $w, $h); // Gambar kotak sel
            $this->MultiCell($w, 5, $data[$i], 0, $a); // Isi teks (otomatis turun jika kepanjangan)
            $this->SetXY($x + $w, $y); // Geser X ke kolom berikutnya
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if($this->GetY()+$h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("
", '', (string)$txt);
        $nb = strlen($s);
        if($nb > 0 and $s[$nb-1] == "
") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "
") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c == ' ') $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) { if($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }
}

// =================================================================================
// PROSES RENDER PDF
// =================================================================================
function latin($v) {
    $v = (string)$v;
    $r = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $v);
    return $r === false ? $v : $r;
}

// 1) Kumpulkan semua baris dulu agar lebar kolom bisa dihitung dari isi data
$judul_kolom = array('No', 'Tgl Pengajuan', 'Nama Klinik', 'Kabupaten', 'No Faktur', 'Jumlah Stock', 'Jumlah Pemakaian', 'Jumlah Permintaan', 'Jumlah Diberikan', 'Sisa Kuota', 'Nama PIC', 'Status');
$baris = array();
$no = 1;
while($result && ($row = mysqli_fetch_assoc($result))) {
    $tgl              = !empty($row['tanggal_permohonan']) ? date('d/m/y', strtotime($row['tanggal_permohonan'])) : '-';
    $nama_klinik      = $row['nama_klinik'];
    $nama_kabupaten   = !empty($row['nama_kabupaten']) ? $row['nama_kabupaten'] : '-';
    $no_invoice       = !empty($row['no_invoice']) ? $row['no_invoice'] : '-';
    $jumlah_stock     = isset($row['jumlah_stock_akhir_eicv']) ? $row['jumlah_stock_akhir_eicv'] : (isset($row['jumlah_stock']) ? $row['jumlah_stock'] : '0');
    $jumlah_pemakaian = isset($row['jumlah_pemakaian_eicv']) ? $row['jumlah_pemakaian_eicv'] : (isset($row['jumlah_pemakaian']) ? $row['jumlah_pemakaian'] : '0');
    $jumlah_permintaan= isset($row['jumlah_permintaan']) ? $row['jumlah_permintaan'] : '0';
    $jumlah_diberikan = isset($row['kuota_disetujui']) ? $row['kuota_disetujui'] : '0';
    $sisa_kuota       = isset($row['sisa_kuota_faktur']) ? $row['sisa_kuota_faktur'] : '0';
    $nama_pic         = !empty($row['nama_pic']) ? $row['nama_pic'] : '-';
    $status           = !empty($row['status_permohonan']) ? $row['status_permohonan'] : '-';

    $baris[] = array(
        $no++, $tgl, latin($nama_klinik), latin($nama_kabupaten), latin($no_invoice), $jumlah_stock, $jumlah_pemakaian,
        $jumlah_permintaan, $jumlah_diberikan, $sisa_kuota, latin($nama_pic), $status
    );
}

$pdf = new PDF_MC_Table('L', 'mm', 'A4');
$pdf->teks_subtitle = $teks_subtitle;
$pdf->SetLeftMargin(10);
$pdf->SetRightMargin(10);

// 2) Hitung lebar kolom otomatis (total tepat 277 mm)
$TOTAL = 277; $PAD = 3; // padding kiri-kanan sel
$n = count($judul_kolom);
$min = array(); $nat = array();
for ($i = 0; $i < $n; $i++) {
    $pdf->SetFont('Arial', 'B', 6.5);
    $hdr = $pdf->GetStringWidth($judul_kolom[$i]) + $PAD;           // judul tidak boleh terpotong
    $pdf->SetFont('Arial', '', 7.5);
    $maxFull = 0; $maxWord = 0;
    foreach ($baris as $b) {
        $t = (string)$b[$i];
        $maxFull = max($maxFull, $pdf->GetStringWidth($t));
        foreach (preg_split('/\s+/', $t) as $w) { $maxWord = max($maxWord, $pdf->GetStringWidth($w)); }
    }
    $min[$i] = max($hdr, $maxWord + $PAD);       // minimal: judul & kata terpanjang muat
    $nat[$i] = max($min[$i], $maxFull + $PAD);   // ideal: isi terpanjang muat satu baris
}
$sumNat = array_sum($nat); $sumMin = array_sum($min);
$lebar = array();
if ($sumNat <= $TOTAL) {
    // Semua muat satu baris: bagi sisa ruang proporsional agar tabel penuh
    $extra = $TOTAL - $sumNat;
    for ($i = 0; $i < $n; $i++) $lebar[$i] = $nat[$i] + $extra * ($nat[$i] / $sumNat);
} elseif ($sumMin < $TOTAL) {
    // Ada isi panjang: kolom pendek tetap ringkas, sisa ruang dibagi ke kolom yang butuh lebih
    $room = $TOTAL - $sumMin; $need = $sumNat - $sumMin;
    for ($i = 0; $i < $n; $i++) $lebar[$i] = $min[$i] + $room * (($nat[$i] - $min[$i]) / $need);
} else {
    for ($i = 0; $i < $n; $i++) $lebar[$i] = $min[$i] * $TOTAL / $sumMin;
}
$pdf->colw = $lebar;
$pdf->SetWidths($lebar);

$pdf->AddPage();
$pdf->SetFont('Arial','',7.5);
$pdf->SetTextColor(0, 0, 0);

// 3) Cetak baris (tinggi tiap baris menyesuaikan isi terpanjang)
if (count($baris) > 0) {
    foreach ($baris as $b) { $pdf->Row($b); }
} else {
    $pdf->Cell(277, 10, 'Tidak ada data ditemukan untuk filter ini', 1, 1, 'C');
}

$pdf->Output('I', 'Laporan_Rekap_EICV.pdf');
exit();
?>
