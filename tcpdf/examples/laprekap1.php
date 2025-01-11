<?php

include "../../includes/koneksi.php";
$tahun = $_GET['tahun'];
$bulan = $_GET['bulan'];

switch ($bulan) {
	case 1:
		$bulan_tampil = 'Januari';
		break;
	case 2:
		$bulan_tampil = 'Februari';
		break;
	case 3:
		$bulan_tampil = 'Maret';
		break;
	case 4:
		$bulan_tampil = 'April';
		break;
	case 5:
		$bulan_tampil = 'Mei';
		break;
	case 6:
		$bulan_tampil = 'Juni';
		break;
	case 7:
		$bulan_tampil = 'Juli';
		break;
	case 8:
		$bulan_tampil = 'Agustus';
		break;
	case 9:
		$bulan_tampil = 'September';
		break;
	case 10:
		$bulan_tampil = 'Oktober';
		break;
	case 11:
		$bulan_tampil = 'November';
		break;
	case 12:
		$bulan_tampil = 'Desember';
		break;
}

require_once('tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Kamandanu');
$pdf->SetTitle('laporan Rekap');
$pdf->SetSubject('TCPDF Tutorial');
$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' ', PDF_HEADER_STRING);

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 30, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
	require_once(dirname(__FILE__).'/lang/eng.php');
	$pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set font
$pdf->SetFont('times', '', 12);

// add a page
$pdf->AddPage('P');

// set cell padding
$pdf->setCellPaddings(1, 1, 1, 1);

// set cell margins
$pdf->setCellMargins(1, 1, 1, 1);

// set color for background
$pdf->SetFillColor(255, 255, 127);

// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)

$title = <<<OED
<h2>REKAP HASIL KEGIATAN POSYANDU</h2>
OED;
$title2 = <<<OED
<h2>Bulan $bulan_tampil Tahun $tahun</h2>
OED;

$pdf->WriteHTMLCell(0,0,'','',$title,0,1,0,true,'C',true);
$pdf->WriteHTMLCell(0,0,'','',$title2,0,1,0,true,'C',true);

$pdf->SetFont('times', '', 10);
$table_detail = '<table >
		<tr>
			<th style="width:100px;">1</th>
			<th colspan="2" style="width:800px; text-align:left">   Jumlah Bayi / Balita</th>
			<th style="width:150px;"></th>
		</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){
	$sql_umur_bayi = mysqli_query($con, "select * from tbl_bayi");
	$tot = 0;
	while($umur_bayi = mysqli_fetch_array($sql_umur_bayi)){

		//cek kategori umur
		$date = date($tahun."-".$bulan."-30");
		$timeStart = strtotime($umur_bayi['tanggal_lahir']);
		$timeEnd = strtotime("$date");
		// Menambah bulan ini + semua bulan pada tahun sebelumnya
		$numBulan = 1 + (date("Y",$timeEnd)-date("Y",$timeStart))*12;
		// menghitung selisih bulan
		$numBulan += date("m",$timeEnd)-date("m",$timeStart);

		if($numBulan >= $row_kategori_umur['lebih_dari'] && $numBulan <= $row_kategori_umur['kurang_dari']){
			$tot = $tot + 1;
		}
	}
	

$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'.$tot.'</th>
		</tr>';
}
$sql_bayi_lahir = mysqli_query($con, "select count(*) as total from tbl_bayi where month(tanggal_lahir)= '$bulan' and year(tanggal_lahir) = '$tahun'");
$bayi_lahir = mysqli_fetch_array($sql_bayi_lahir);
$table_detail .= '<tr>
					<th style="width:100px;">2</th>
					<th colspan="2" style="width:900px; text-align:left">   Jumlah Kelahiran</th>
					<th style="border:1px solid #000; width:100px;">'.$bayi_lahir['total'].'</th>
				</tr>';
$sql_bayi_mati = mysqli_query($con, "select count(*) as total from tbl_bayi where month(tgl_kematian)= '$bulan' and year(tgl_kematian) = '$tahun'");
$bayi_mati = mysqli_fetch_array($sql_bayi_mati);
$table_detail .= '<tr>
					<th style="width:100px;">3</th>
					<th colspan="2" style="width:900px; text-align:left">   Jumlah Kematian</th>
					<th style="border:1px solid #000; width:100px;">'.$bayi_mati['total'].'</th>
				</tr>';

$table_detail .= '<tr>
				<th style="width:100px;">4</th>
				<th colspan="2" style="width:900px; text-align:left">   Jumlah Bayi / Balita ditimbang</th>
				<th style="width:100px;"></th>
			</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){
	$sql_tumbuh_kembang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$tumbuh_kembang = mysqli_fetch_array($sql_tumbuh_kembang);
$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'.$tumbuh_kembang['total'].'</th>
		</tr>';
}

$table_detail .= '<tr>
				<th style="width:100px;">5</th>
				<th colspan="2" style="width:600px; text-align:left">   Jumlah Bayi / Balita Hasil Penimbangan Naik</th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
			</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){

	//naik
	$sql_naik = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and status_perkembangan = 'Naik' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$naik = mysqli_fetch_array($sql_naik);

	//tidak naik
	$sql_tidaknaik = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and status_perkembangan = 'Tidak Naik' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$tidaknaik = mysqli_fetch_array($sql_tidaknaik);

	//tidak naik
	$sql_baru = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and status_perkembangan = 'Baru' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$baru = mysqli_fetch_array($sql_baru);


$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'. $naik['total'] .'</th>
		</tr>';
}

$table_detail .= '<tr>
				<th style="width:100px;">6</th>
				<th colspan="2" style="width:900px; text-align:left">   Jumlah Bayi / Balita yang ditimbang 6X</th>
				<th style="width:100px;"></th>
			</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){
	$j = 0;
	$sql_jumlah = mysqli_query($con, "select id_bayi, count(*) as jum from tbl_tumbuh_kembang WHERE id_kategori_umur= '$row_kategori_umur[id_kategori_umur]' group by id_bayi");
	while($jum = mysqli_fetch_array($sql_jumlah)){
		if($juml['jum'] > 5){
			$j = $j + 1;
		}
	}
$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'.$j.'</th>
		</tr>';
}

$table_detail .= '<tr>
				<th style="width:100px;">7</th>
				<th colspan="2" style="width:600px; text-align:left">   Jumlah Bayi / Balita Hasil Penimbangan tetap</th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
			</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){

	//naik
	$sql_tetap = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and status_perkembangan = 'Tetap' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$tetap = mysqli_fetch_array($sql_tetap);


$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'. $tetap['total'] .'</th>
		</tr>';
}

$table_detail .= '<tr>
				<th style="width:100px;">8</th>
				<th colspan="2" style="width:600px; text-align:left">   Jumlah Bayi / Balita Hasil Penimbangan Turun</th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
				<th style="width:100px;"></th>
			</tr>';
$sql_kategori_umur = mysqli_query($con,"select * from tbl_kategori_umur");
while($row_kategori_umur=mysqli_fetch_array($sql_kategori_umur)){

	//naik
	$sql_turun = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where tahun_pencatatan = '$tahun' and bulan_pencatatan = '$bulan' and status_perkembangan = 'Turun' and id_kategori_umur = '$row_kategori_umur[id_kategori_umur]'");
	$turun = mysqli_fetch_array($sql_turun);


$table_detail .='<tr style="border:1px solid #000;">
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:500px; text-align:left">* '.$row_kategori_umur['keterangan'].'</th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="width:100px;"></th>
			<th style="border:1px solid #000; width:100px;">'. $turun['total'] .'</th>
		</tr>';
}

$sql_vitamin = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang inner join tbl_detail_pelayanan on tbl_tumbuh_kembang.kode_pencatatan = tbl_detail_pelayanan.kode_pencatatan 
where tbl_tumbuh_kembang.bulan_pencatatan = '$bulan' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and 
tbl_detail_pelayanan.id_kategori_pelayanan = 'P003' or tbl_tumbuh_kembang.bulan_pencatatan = '$bulan' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and 
tbl_detail_pelayanan.id_kategori_pelayanan = 'P004'");
$vitamin = mysqli_fetch_array($sql_vitamin);
$table_detail .= '<tr>
					<th style="width:100px;">9</th>
					<th colspan="2" style="width:900px; text-align:left">   Jumlah Balita Yang Menerima Kapsul Vitamin A</th>
					<th style="border:1px solid #000; width:100px;">'.$vitamin['total'].'</th>
				</tr>';

$sql_bcg = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang inner join tbl_detail_pelayanan on tbl_tumbuh_kembang.kode_pencatatan = tbl_detail_pelayanan.kode_pencatatan 
where tbl_tumbuh_kembang.bulan_pencatatan = '$bulan' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and 
tbl_detail_pelayanan.id_kategori_pelayanan = 'P006'");
$bcg = mysqli_fetch_array($sql_bcg);
$table_detail .= '<tr>
					<th style="width:100px;">10</th>
					<th colspan="2" style="width:900px; text-align:left">   Jumlah Balita Yang Menerima Imunisasi BCG</th>
					<th style="border:1px solid #000; width:100px;">'.$bcg['total'].'</th>
				</tr>';

$sql_campak = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang inner join tbl_detail_pelayanan on tbl_tumbuh_kembang.kode_pencatatan = tbl_detail_pelayanan.kode_pencatatan 
where tbl_tumbuh_kembang.bulan_pencatatan = '$bulan' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and 
tbl_detail_pelayanan.id_kategori_pelayanan = 'P013'");
$campak = mysqli_fetch_array($sql_campak);
$table_detail .= '<tr>
					<th style="width:100px;">11</th>
					<th colspan="2" style="width:900px; text-align:left">   Jumlah Balita Yang Menerima Imunisasi Campak</th>
					<th style="border:1px solid #000; width:100px;">'.$campak['total'].'</th>
				</tr>';

$table_detail .='</table>';



$pdf->WriteHTMLCell(0,0,'','',$table_detail,0,1,0,true,'C',true);



// move pointer to last page
$pdf->lastPage();

// ---------------------------------------------------------
ob_clean();
//Close and output PDF document
//$judul	= '/Penawaran'.'_'.$idsuplier.'.pdf';
//$pdf->IncludeJS("print();");
//$pdf->Output(__DIR__.'/LaporanPengaduan.pdf', 'FD');
//$pdf->Output($judul, 'I');

$pdf->Output('Laporan Pencatatan.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

?>