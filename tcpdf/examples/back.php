<?php

include "../../koneksi.php";
//$awal = $_GET['awal'];
//$akhir = $_GET['akhir'];

$awal = '2019-10-01';
$akhir = '2019-10-15';

// Include the main TCPDF library (search for installation path).
require_once('tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Kamandanu');
$pdf->SetTitle('Laporan Perhitungan Insentif');
$pdf->SetSubject('TCPDF Tutorial');
$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE . ' ', PDF_HEADER_STRING);

// set header and footer fonts
$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
	require_once(dirname(__FILE__) . '/lang/eng.php');
	$pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set font
$pdf->SetFont('times', '', 10);

// add a page
$pdf->AddPage('L');

// set cell padding
$pdf->setCellPaddings(1, 1, 1, 1);

// set cell margins
$pdf->setCellMargins(1, 1, 1, 1);

// set color for background
$pdf->SetFillColor(255, 255, 127);

// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)

$title = <<<OED
<h2>Laporan Perhitungan Insentif Redaksi </h2>
OED;
$title2 = <<<OED
<h2>Tgl 01 Oktober - 15 Oktober 2019</h2>
OED;

$pdf->WriteHTMLCell(0, 0, '', '', $title, 0, 1, 0, true, 'C', true);
$pdf->WriteHTMLCell(0, 0, '', '', $title2, 0, 1, 0, true, 'C', true);


$table = '<table style="border:1px solid #000; padding:6px;">
		<tr>
			<th colspan="6" style="border:1px solid #000; width:930px;">DATA BERITA- Andi Mardani</th>
		</tr>
		<tr style = "background-color:#ccc;">
			<th style="border:1px solid #000; width:50px;">No.</th>
			<th style="border:1px solid #000; width:180px;">Keterangan</th>
			<th style="border:1px solid #000; width:100px;">Skor</th>
			<th style="border:1px solid #000; width:100px;">Bobot</th>
			<th style="border:1px solid #000; width:100px;">Nilai</th>
			<th style="border:1px solid #000; width:200px;">Nilai Insentif</th>
			<th style="border:1px solid #000; width:200px;">Insentif Diterima</th>
		</tr>
		
								<tr>
								<th style="border:1px solid #000; width:50;">1</th>
								<th style="border:1px solid #000;">Headline</th>
								<th style="border:1px solid #000;">20</th>
								<th style="border:1px solid #000;">5</th>
								<th style="border:1px solid #000;">100</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 260,000</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">2</th>
								<th style="border:1px solid #000;">Boks Depan</th>
								<th style="border:1px solid #000;">1</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 10,400</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">3</th>
								<th style="border:1px solid #000;">Hal 1</th>
								<th style="border:1px solid #000;">8</th>
								<th style="border:1px solid #000;">3</th>
								<th style="border:1px solid #000;">24</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 62,400</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">4</th>
								<th style="border:1px solid #000;">Headline Dalam</th>
								<th style="border:1px solid #000;">18</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">72</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 187,200</th>
								</tr>';




$table .= '		<tr>
			<th colspan="2" style="border:1px solid #000;">JUMLAH SKOR :</th>
			<th style="border:1px solid #000;">47</th>
			<th colspan="3" style="border:1px solid #000;">JUMLAH</th>
			<th style="border:1px solid #000;">Rp. 520,000</th>
		</tr></table>';

$table1 = '<table style="border:1px solid #000; padding:6px;">
		<tr>
			<th colspan="6" style="border:1px solid #000; width:930px;">DATA BERITA- Eko Iskandar</th>
		</tr>
		<tr style = "background-color:#ccc;">
			<th style="border:1px solid #000; width:50px;">No.</th>
			<th style="border:1px solid #000; width:180px;">Keterangan</th>
			<th style="border:1px solid #000; width:100px;">Skor</th>
			<th style="border:1px solid #000; width:100px;">Bobot</th>
			<th style="border:1px solid #000; width:100px;">Nilai</th>
			<th style="border:1px solid #000; width:200px;">Nilai Insentif</th>
			<th style="border:1px solid #000; width:200px;">Insentif Diterima</th>
		</tr>
		
								<tr>
								<th style="border:1px solid #000; width:50;">1</th>
								<th style="border:1px solid #000;">Headline</th>
								<th style="border:1px solid #000;">11</th>
								<th style="border:1px solid #000;">5</th>
								<th style="border:1px solid #000;">55</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 143,000</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">2</th>
								<th style="border:1px solid #000;">Boks Depan</th>
								<th style="border:1px solid #000;">6</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">24</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 62,400</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">3</th>
								<th style="border:1px solid #000;">Hal 1</th>
								<th style="border:1px solid #000;">11</th>
								<th style="border:1px solid #000;">3</th>
								<th style="border:1px solid #000;">33</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 85,800</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">4</th>
								<th style="border:1px solid #000;">Headline Dalam</th>
								<th style="border:1px solid #000;">19</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">76</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 197,600</th>
								</tr>';




$table1 .= '		<tr>
			<th colspan="2" style="border:1px solid #000;">JUMLAH SKOR :</th>
			<th style="border:1px solid #000;">47</th>
			<th colspan="3" style="border:1px solid #000;">JUMLAH</th>
			<th style="border:1px solid #000;">Rp. 488,800</th>
		</tr></table>';

$table2 = '<table style="border:1px solid #000; padding:6px;">
		<tr>
			<th colspan="6" style="border:1px solid #000; width:930px;">DATA BERITA- Antonio Juao SB</th>
		</tr>
		<tr style = "background-color:#ccc;">
			<th style="border:1px solid #000; width:50px;">No.</th>
			<th style="border:1px solid #000; width:180px;">Keterangan</th>
			<th style="border:1px solid #000; width:100px;">Skor</th>
			<th style="border:1px solid #000; width:100px;">Bobot</th>
			<th style="border:1px solid #000; width:100px;">Nilai</th>
			<th style="border:1px solid #000; width:200px;">Nilai Insentif</th>
			<th style="border:1px solid #000; width:200px;">Insentif Diterima</th>
		</tr>
		
								<tr>
								<th style="border:1px solid #000; width:50;">1</th>
								<th style="border:1px solid #000;">Headline</th>
								<th style="border:1px solid #000;">2</th>
								<th style="border:1px solid #000;">5</th>
								<th style="border:1px solid #000;">10</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 26,000</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">2</th>
								<th style="border:1px solid #000;">Boks Depan</th>
								<th style="border:1px solid #000;">0</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;"></th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 0</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">3</th>
								<th style="border:1px solid #000;">Hal 1</th>
								<th style="border:1px solid #000;">0</th>
								<th style="border:1px solid #000;">3</th>
								<th style="border:1px solid #000;">0</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 0</th>
								</tr>
								<tr>
								<th style="border:1px solid #000; width:50;">4</th>
								<th style="border:1px solid #000;">Headline Dalam</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">4</th>
								<th style="border:1px solid #000;">16</th>
								<th style="border:1px solid #000;">Rp. 2600</th>
								<th style="border:1px solid #000;">Rp. 41,600</th>
								</tr>';




$table2 .= '		<tr>
			<th colspan="2" style="border:1px solid #000;">JUMLAH SKOR :</th>
			<th style="border:1px solid #000;">6</th>
			<th colspan="3" style="border:1px solid #000;">JUMLAH</th>
			<th style="border:1px solid #000;">Rp. 67,600</th>
		</tr></table>';

$tabletotalberita = '<table style="border:1px solid #000; padding:6px;">
		<tr>
			<th style="border:1px solid #000; width:730px;">TOTAL</th>
			<th style="border:1px solid #000; width:200px;">Rp. 1,074,600</th>
		</tr></table>';

$pdf->WriteHTMLCell(0, 0, '', '', $table, 0, 1, 0, true, 'C', true);
$pdf->WriteHTMLCell(0, 0, '', '', $table1, 0, 1, 0, true, 'C', true);
$pdf->WriteHTMLCell(0, 0, '', '', $table2, 0, 1, 0, true, 'C', true);
$pdf->WriteHTMLCell(0, 0, '', '', $tabletotalberita, 0, 1, 0, true, 'C', true);



$today = date("d-m-Y");
$ttd = '<table>';
$ttd .= '
		<tr style="padding:30px;">
			<th>Tangerang, ' . $today . '</th>
			<th></th>
			<th></th>
		</tr>
		<tr>
			<th></th>
			<th></th>
			<th></th>
		</tr>
		<tr>
			<th></th>
			<th></th>
			<th></th>
		</tr>
		<tr>
			<th></th>
			<th></th>
			<th></th>
		</tr>
		<tr style="padding:30px;">
			<th style="padding:30px;"></th>
			<th></th>
			<th></th>
		</tr>
		<tr style="padding:2px;">
			<th><u>Miftakhuddin</u></th>
			<th></th>
			<th><u>Andi Ahmadi</u></th>
		</tr>
		<tr style="padding:1px;">
			<th>Wakil dosen Redaksi </th>
			<th></th>
			<th>General Manager</th>
		</tr>';

$ttd .= '</table>';
$pdf->WriteHTMLCell(0, 0, '', '', $ttd, 0, 1, 0, true, 'C', true);



// move pointer to last page
$pdf->lastPage();

// ---------------------------------------------------------
ob_clean();
//Close and output PDF document
//$judul	= '/Penawaran'.'_'.$idsuplier.'.pdf';
//$pdf->IncludeJS("print();");
//$pdf->Output(__DIR__.'/LaporanPengaduan.pdf', 'FD');
//$pdf->Output($judul, 'I');

$pdf->Output('Laporan Pengaduan.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
