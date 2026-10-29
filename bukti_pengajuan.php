<?php
// bukti_pengajuan.php
// Membuat PDF "Bukti Pengajuan" (rekap data + kode tracking) langsung dari PHP.

ob_start();
require_once 'config/database.php';
date_default_timezone_set('Asia/Makassar');

/* ---------------- Pengaturan ---------------- */
$NAMA_INSTANSI = 'Balai Kekarantinaan Kesehatan Kelas I Banjarmasin';
$NAMA_SISTEM   = 'MONIKA e-ICV';

/* ---------------- Ambil data ---------------- */
$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';
if ($kode === '' || strlen($kode) > 64) { http_response_code(400); exit('Kode tracking tidak valid.'); }

$stmt = mysqli_prepare($conn, "SELECT * FROM permohonan WHERE kode_tracking = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $kode);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);
if (!$row) { http_response_code(404); exit('Data permohonan tidak ditemukan.'); }

/* =========================================================
   PEMBUAT PDF MINI (FULL VERTICAL A4 LAYOUT + GAMBAR)
   ========================================================= */
class MiniPDF {
    public $W = 595.28, $H = 841.89;     // A4 (poin)
    private $pages = [];
    private $cur = -1;
    private $images = [];
    private static $wR = null, $wB = null;

    function __construct() { $this->addPage(); }
    function addPage() { $this->pages[] = ''; $this->cur = count($this->pages) - 1; }
    function pageCount() { return count($this->pages); }
    function setPage($i) { $this->cur = $i; }

    private static function table($lower, $upper, $extra) {
        $m = [];
        foreach ([$lower, $upper] as $str) {
            preg_match_all('/([A-Za-z])(\d+)/', $str, $mm, PREG_SET_ORDER);
            foreach ($mm as $x) $m[$x[1]] = (int)$x[2];
        }
        return $m + $extra;
    }
    
    private static function widths($bold) {
        if (self::$wR === null) {
            self::$wR = self::table(
                'a556 b556 c500 d556 e556 f278 g556 h556 i222 j222 k500 l222 m833 n556 o556 p556 q556 r333 s500 t278 u556 v500 w722 x500 y500 z500',
                'A667 B667 C722 D722 E667 F611 G778 H722 I278 J500 K667 L556 M833 N722 O778 P667 Q778 R722 S667 T611 U722 V667 W944 X667 Y667 Z611',
                [' '=>278,'!'=>278,'"' =>355,'%'=>889,'&'=>667,"'"=>191,'('=>333,')'=>333,'*'=>389,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,':'=>278,';'=>278,'<'=>584,'='=>584,'>'=>584,'@'=>1015,'['=>278,']'=>278,'|'=>260]
            );
            self::$wB = self::table(
                'a556 b611 c556 d611 e556 f333 g611 h611 i278 j278 k556 l278 m889 n611 o611 p611 q611 r389 s556 t333 u611 v556 w778 x556 y556 z500',
                'A722 B722 C722 D722 E667 F611 G778 H722 I278 J556 K722 L611 M833 N722 O778 P667 Q778 R722 S667 T611 U722 V667 W944 X667 Y667 Z611',
                [' '=>278,'!'=>333,'"' =>474,'%'=>889,'&'=>722,'('=>333,')'=>333,'+'=>584,','=>278,'-'=>333,'.'=>278,'/'=>278,':'=>333,';'=>333,'?'=>611,'@'=>975]
            );
        }
        return $bold ? self::$wB : self::$wR;
    }

    function width($s, $size, $bold = false) {
        $w = self::widths($bold); $t = 0;
        foreach (preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) as $ch) $t += isset($w[$ch]) ? $w[$ch] : 556;
        return $t * $size / 1000;
    }

    function wrap($s, $maxW, $size, $bold = false) {
        $s = trim(preg_replace('/\s+/', ' ', (string)$s));
        if ($s === '') return [''];
        $lines = []; $line = '';
        foreach (explode(' ', $s) as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($try, $size, $bold) <= $maxW) { $line = $try; continue; }
            if ($line !== '') { $lines[] = $line; $line = ''; }
            if ($this->width($word, $size, $bold) > $maxW) {
                $chunk = '';
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                    if ($chunk !== '' && $this->width($chunk . $ch, $size, $bold) > $maxW) { $lines[] = $chunk; $chunk = ''; }
                    $chunk .= $ch;
                }
                $line = $chunk;
            } else { $line = $word; }
        }
        if ($line !== '') $lines[] = $line;
        return $lines;
    }

    private function enc($s) {
        $s = preg_replace('/[ -  ]/', ' ', (string)$s);
        $o = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        if ($o === false) $o = preg_replace('/[^ -~]/', '?', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $o);
    }
    
    private function col($c) { return sprintf('%.3F %.3F %.3F', $c[0] / 255, $c[1] / 255, $c[2] / 255); }
    private function put($s) { $this->pages[$this->cur] .= $s . "\n"; }

    function rect($x, $y, $w, $h, $fill = null, $stroke = null, $lw = 0.6, $dash = false) {
        $op = ($fill && $stroke) ? 'B' : ($fill ? 'f' : 'S');
        $s = 'q ';
        if ($fill)   $s .= $this->col($fill) . ' rg ';
        if ($stroke) $s .= $this->col($stroke) . ' RG ' . sprintf('%.2F w ', $lw);
        if ($dash)   $s .= '[4 3] 0 d ';
        $s .= sprintf('%.2F %.2F %.2F %.2F re %s Q', $x, $this->H - $y - $h, $w, $h, $op);
        $this->put($s);
    }
    
    function line($x1, $y1, $x2, $y2, $color, $lw = 0.6) {
        $this->put(sprintf('q %s RG %.2F w %.2F %.2F m %.2F %.2F l S Q', $this->col($color), $lw, $x1, $this->H - $y1, $x2, $this->H - $y2));
    }
    
    function text($x, $y, $s, $size, $bold = false, $color = [0, 0, 0], $align = 'L') {
        $w = $this->width($s, $size, $bold);
        if ($align === 'R') $x -= $w; elseif ($align === 'C') $x -= $w / 2;
        $this->put(sprintf('BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $this->col($color), $x, $this->H - $y, $this->enc($s)));
    }

    function image($file, $x, $y, $w, $h, $bg_color = [255, 255, 255]) {
        if (!file_exists($file) || !function_exists('imagecreatefromstring')) return; 
        
        $cache_key = $file . '_' . implode(',', $bg_color);
        if (!isset($this->images[$cache_key])) {
            $data = file_get_contents($file);
            $info = @getimagesizefromstring($data);
            if (!$info) return;
            
            if ($info[2] !== IMAGETYPE_JPEG) {
                $img = @imagecreatefromstring($data);
                if (!$img) return;
                
                $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                $fill = imagecolorallocate($bg, $bg_color[0], $bg_color[1], $bg_color[2]);
                imagefill($bg, 0, 0, $fill);
                
                imagealphablending($bg, true);
                imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                
                ob_start();
                imagejpeg($bg, null, 95);
                $data = ob_get_clean();
                imagedestroy($img);
                imagedestroy($bg);
                $info = @getimagesizefromstring($data);
            }
            $this->images[$cache_key] = ['w' => $info[0], 'h' => $info[1], 'data' => $data, 'id' => count($this->images) + 1];
        }
        $img = $this->images[$cache_key];
        $id = $img['id'];
        $this->put(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w, $h, $x, $this->H - $y - $h, $id));
    }

    function output() {
        $n = count($this->pages);
        $o = [];
        $o[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) $kids[] = (5 + $i * 2) . ' 0 R';
        $o[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $o[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $o[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        
        $next_obj = 5 + $n * 2;
        $img_refs = '';
        $img_objs = [];
        foreach ($this->images as $cache_key => $img) {
            $img_obj_id = $next_obj++;
            $img_refs .= sprintf('/I%d %d 0 R ', $img['id'], $img_obj_id);
            $img_objs[$img_obj_id] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $img['w'], $img['h'], strlen($img['data']), $img['data']
            );
        }
        
        $res = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
        if ($img_refs !== '') $res .= ' /XObject << ' . $img_refs . '>>';
        $res .= ' >>';

        for ($i = 0; $i < $n; $i++) {
            $p = 5 + $i * 2; $c = $p + 1;
            $o[$p] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', $this->W, $this->H, $res, $c);
            $stream = $this->pages[$i];
            $o[$c] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }
        
        foreach ($img_objs as $id => $obj) { $o[$id] = $obj; }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        foreach ($o as $id => $body) { $off[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n"; }
        
        $xref = strlen($pdf);
        $cnt = max(array_keys($o)) + 1;
        $pdf .= "xref\n0 $cnt\n0000000000 65535 f \n";
        for ($i = 1; $i < $cnt; $i++) {
            $pdf .= isset($off[$i]) ? sprintf("%010d 00000 n \n", $off[$i]) : "0000000000 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size $cnt /Root 1 0 R /Info << /Title (Bukti Pengajuan Permohonan EICV) /Producer (Portal Permohonan EICV) >> >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}

/* =========================================================
   HELPER FORMAT
   ========================================================= */
$BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function fmt_tgl($v, $BULAN) {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$v, $m) && $m[1] !== '0000' && (int)$m[2] >= 1) {
        return (int)$m[3] . ' ' . $BULAN[(int)$m[2]] . ' ' . $m[1];
    }
    return null;
}
$ANGKA = ['jumlah_stock_akhir_eicv','jumlah_pemakaian_eicv','jumlah_permintaan','jumlah_pembelian','jumlah_vaksin_mm','jumlah_vaksin_polio'];

function nilai($row, $key, $BULAN, $ANGKA) {
    $v = $row[$key];
    if ($v === null || trim((string)$v) === '') return '-';
    $t = fmt_tgl($v, $BULAN);
    if ($t !== null) return $t;
    if (in_array($key, $ANGKA, true) && is_numeric($v)) return number_format((float)$v, 0, ',', '.');
    return (string)$v;
}

$BAGIAN = [
    'Data Pemohon & Klinik' => [
        'Tanggal Permohonan'  => 'tanggal_permohonan',
        'Nama Pemohon'        => 'nama_pemohon',
        'Nama Klinik'         => 'nama_klinik',
        'Kabupaten / Kota'    => 'nama_kabupaten',
        'No Izin Penerbitan'  => 'no_izin_penerbitan',
        'Masa Berlaku Izin'   => 'masa_berlaku_izin',
    ],
    'Data EICV & Permintaan' => [
        'Jumlah Stock Akhir EICV'  => 'jumlah_stock_akhir_eicv',
        'Jumlah Pemakaian EICV'    => 'jumlah_pemakaian_eicv',
        'Jumlah Permintaan Vaksin' => 'jumlah_permintaan',
    ],
    'Data Faktur & Vaksin' => [
        'Tanggal Faktur'     => 'tanggal_faktur',
        'Nama Distributor'   => 'nama_distributor',
        'No Faktur'          => 'no_invoice', // Label diubah ke No Faktur
        'Jumlah Pembelian'   => 'jumlah_pembelian',
        'Merek Vaksin'       => 'merek_vaksin',
        'Batch Vaksin'       => 'batch_vaksin',
        'Exp Date Vaksin'    => 'exp_date',
        'Jenis Vaksin'       => 'jenis_vaksin',
        'Jumlah Vaksin MM'   => 'jumlah_vaksin_mm',
        'Jumlah Vaksin Polio'=> 'jumlah_vaksin_polio',
        'File Penerbitan EICV' => 'file_syarat',
    ],
];

/* =========================================================
   MENYUSUN HALAMAN (FULL VERTICAL A4 & PERFECT FIT)
   ========================================================= */
$pdf = new MiniPDF();
$W = $pdf->W; $H = $pdf->H;
$M = 30; $CW = $W - 2 * $M;

$INK = [20, 33, 61]; $MUTED = [95, 107, 133]; $PRIMARY = [99, 102, 241]; $TEAL = [6, 182, 212];
$SOFT = [238, 240, 255]; $HAIR = [225, 229, 243]; $WHITE = [255, 255, 255];

$status = strtoupper(trim((string)($row['status_permohonan'] ?? 'Menunggu')));
if ($status === '') $status = 'MENUNGGU';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$urlTrack = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base . '/tracking.php?kode_tracking=' . rawurlencode($kode);

/* ---- Kop Surat ---- */
$pdf->rect(0, 0, $W, 62, $PRIMARY);
$pdf->rect(0, 62, $W, 3.5, $TEAL);
$pdf->text($M, 21, mb_strtoupper($NAMA_SISTEM, 'UTF-8'), 9.0, true, [214, 220, 255]);
$pdf->text($M, 42, 'BUKTI PENGAJUAN', 18, true, $WHITE);
$pdf->text($M, 53, 'Rekap data permohonan dan kode tracking', 9.0, false, [226, 232, 255]);

/* ==== LOGO INSTANSI DI POJOK KANAN ==== */
$logoSize = 48; // Ukuran logo proporsional
$yLogo = 7;     // Jarak vertikal agar berada di tengah area biru

$xKarantina = $W - $M - $logoSize; // Logo Karantina diletakkan paling kanan (pojok)
$xBKK       = $xKarantina - $logoSize - 14; // Logo Kemenkes BKK diletakkan di sebelah kiri Karantina

$pdf->image('assets/images/logo_bkk.webp', $xBKK, $yLogo, $logoSize, $logoSize, $PRIMARY);
$pdf->image('assets/images/logo_karantina.webp', $xKarantina, $yLogo, $logoSize, $logoSize, $PRIMARY);

/* ---- Kotak Kode Tracking ---- */
$y = 72;
$pdf->rect($M, $y, $CW, 58, [240, 243, 255], $PRIMARY, 1, true);
$pdf->text($W / 2, $y + 14, 'KODE TRACKING ANDA', 8.5, true, $MUTED, 'C');
$pdf->text($W / 2, $y + 36, $kode, 18, true, $PRIMARY, 'C');
$pdf->text($W / 2, $y + 49, 'Simpan kode ini. Anda membutuhkannya untuk mengecek status permohonan.', 8.5, false, $MUTED, 'C');
$y += 58 + 8;

/* ---- Status Permohonan ---- */
$pdf->rect($M, $y, 130, 20, [254, 243, 199], [245, 158, 11], 0.6);
$pdf->text($M + 65, $y + 13.5, 'STATUS: ' . $status, 9.0, true, [146, 64, 14], 'C');
$y += 20 + 8;

/* ---- Bagian-Bagian Data ---- */
$VAL_X = $M + 175; $VAL_W = $CW - 175 - 10;
foreach ($BAGIAN as $judul => $baris) {
    $ada = [];
    foreach ($baris as $label => $kolom) { if (array_key_exists($kolom, $row)) $ada[$label] = $kolom; }
    if (!$ada) continue;

    $pdf->rect($M, $y, $CW, 19, $SOFT);
    $pdf->rect($M, $y, 3.5, 19, $PRIMARY);
    $pdf->text($M + 12, $y + 13.5, $judul, 9.0, true, $INK);
    $y += 19;

    foreach ($ada as $label => $kolom) {
        $v = nilai($row, $kolom, $BULAN, $ANGKA);
        if ($kolom === 'file_syarat') $v = ($v === '-') ? 'Tidak ada file diunggah' : basename($v);
        
        $lines = $pdf->wrap($v, $VAL_W, 8.8, true);
        $h = count($lines) * 14 + 6;
        
        $pdf->text($M + 12, $y + 11.5, $label, 8.8, false, $MUTED);
        foreach ($lines as $i => $ln) {
            $pdf->text($VAL_X, $y + 11.5 + ($i * 14), $ln, 8.8, true, $INK);
        }
        $y += $h;
        $pdf->line($M, $y, $W - $M, $y, $HAIR, 0.5);
    }
    $y += 6;
}

/* ---- Catatan Penting ---- */
$y += 2;
// Hitung bungkus kata dinamis agar tidak keluar kotak
$max_lebar_catatan = $CW - 24; 
$baris1 = $pdf->wrap('1. Dokumen ini adalah bukti penerimaan data permohonan, bukan persetujuan atau penerbitan izin.', $max_lebar_catatan, 8.2, false);

$total_baris = count($baris1);
$tinggi_kotak = 18 + ($total_baris * 9); // Hitung tinggi kotak secara dinamis

$pdf->rect($M, $y, $CW, $tinggi_kotak, [247, 248, 255], $HAIR, 0.6);
$pdf->text($M + 12, $y + 12, 'Catatan Penting', 9.0, true, $PRIMARY);

$pos_y = $y + 22;
foreach ($baris1 as $teks) {
    $pdf->text($M + 12, $pos_y, $teks, 8.2, false, $INK);
    $pos_y += 9;
}
$y += $tinggi_kotak;

/* ---- Footer ---- */
$pdf->setPage(0);
$pdf->line($M, $H - 28, $W - $M, $H - 28, $HAIR, 0.6);
$pdf->text($M, $H - 16, $NAMA_INSTANSI, 8.2, false, $MUTED);
$pdf->text($W - $M, $H - 16, 'Halaman 1 dari 1', 8.2, true, $MUTED, 'R');

/* ---------------- Kirim ke Browser ---------------- */
$out = $pdf->output();
$nama = 'Bukti-Pengajuan-' . preg_replace('/[^A-Za-z0-9_-]/', '', $kode) . '.pdf';
$disp = isset($_GET['unduh']) ? 'attachment' : 'inline';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disp . '; filename="' . $nama . '"');
header('Content-Length: ' . strlen($out));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $out;
exit;
?>
