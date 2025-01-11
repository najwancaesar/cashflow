<?php

include "../../includes/koneksi.php";
$tahun = $_GET['tahun'];

require_once('tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Kamandanu');
$pdf->SetTitle('laporan pencatatan');
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
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
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
$pdf->SetFont('times', '', 8);

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
<h2>Pencatatan Bayi Dalam Wialayah Kerja Posyandu</h2>
OED;
$title2 = <<<OED
<h2>Tahun $tahun</h2>
OED;

$pdf->WriteHTMLCell(0,0,'','',$title,0,1,0,true,'C',true);
$pdf->WriteHTMLCell(0,0,'','',$title2,0,1,0,true,'C',true);


$table_detail = '<table style="border:1px solid #000;">
		<tr>
			<th style="border:1px solid #000; width:40px;">No.</th>
			<th style="border:1px solid #000; width:150px;">Id Bayi</th>
			<th style="border:1px solid #000; width:250px;">Nama Bayi</th>
			<th style="border:1px solid #000; width:25px;">L/P</th>
			<th style="border:1px solid #000; width:35px;">J</th>
			<th style="border:1px solid #000; width:35px;">F</th>
			<th style="border:1px solid #000; width:35px;">M</th>
			<th style="border:1px solid #000; width:35px;">A</th>
			<th style="border:1px solid #000; width:35px;">M</th>
			<th style="border:1px solid #000; width:35px;">J</th>
			<th style="border:1px solid #000; width:35px;">J</th>
			<th style="border:1px solid #000; width:35px;">A</th>
			<th style="border:1px solid #000; width:35px;">S</th>
			<th style="border:1px solid #000; width:35px;">O</th>
			<th style="border:1px solid #000; width:35px;">N</th>
			<th style="border:1px solid #000; width:35px;">D</th>';
$sql_kategori_pelayanan = mysqli_query($con,"select * from tbl_kategori_pelayanan");
while($row_kategori_pelayanan=mysqli_fetch_array($sql_kategori_pelayanan)){
$table_detail .='<th style="border:1px solid #000; width:50px;">'.$row_kategori_pelayanan['nama_pelayanan'].'</th>';
}
$table_detail .='</tr>';
		
		$sql_bayi = mysqli_query($con,"select * from tbl_bayi");
        $no	 =1;
		while($row_bayi=mysqli_fetch_array($sql_bayi)){
			//jenis kelamin
			if($row_bayi['jenis_kelamin'] == 'Laki-Laki'){
				$j = 'L';
			}else{
				$j = 'P';
			}

			for($x=1;$x<13;$x++){
				$sqlbulan = mysqli_query($con,"SELECT * from tbl_tumbuh_kembang where id_bayi = '$row_bayi[id_bayi]' and bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun'");
				$tk = mysqli_fetch_array($sqlbulan);
				$bb_[$x] = $tk['berat_badan'];
				$tb_[$x] = $tk['tinggi_badan'];
			}


$table_detail .='<tr>
					<th style="border:1px solid #000;">'.$no.'</th>
					<th style="border:1px solid #000;">'.$row_bayi['nama_bayi'].'</th>
					<th style="border:1px solid #000;">'.$row_bayi['nama_bayi'].'</th>
					<th style="border:1px solid #000;">'.$j.'</th>';
for($x=1;$x<13;$x++){
	$table_detail .='<th style="border:1px solid #000;">'.$tb_[$x].'/'.$bb_[$x].'</th>';
}
$sql_kategori_pelayanan = mysqli_query($con,"select * from tbl_kategori_pelayanan");
while($row_kategori_pelayanan=mysqli_fetch_array($sql_kategori_pelayanan)){

	$sql_detail_pelayanan = mysqli_query($con,"select * from tbl_tumbuh_kembang inner join tbl_detail_pelayanan on tbl_tumbuh_kembang.kode_pencatatan = tbl_detail_pelayanan.kode_pencatatan where tbl_tumbuh_kembang.id_bayi = '$row_bayi[id_bayi]' and tbl_detail_pelayanan.id_kategori_pelayanan = '$row_kategori_pelayanan[id_kategori_pelayanan]'");
	$detail_pelayanan = mysqli_fetch_array($sql_detail_pelayanan);
	$table_detail .='<th style="border:1px solid #000; width:50px;">'.$detail_pelayanan['tanggal_pencatatan'].'</th>';

}

$table_detail .='</tr>';
$no++;
}
$table_detail .='<tr>
					<th style="border:1px solid #000;">01</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Diwilayah Posyandu Ini</th>';
					for($x=1;$x<13;$x++){
						if($x < 10){
							$xt = '0'.$x;
						}else{
							$xt = $x;
						}
						$tanggal_daftar = $tahun.'-'.$xt.'-31';
						
						$q_cek_daftar = mysqli_query($con, "select count(*) as total from tbl_bayi where tgl_daftar < '$tanggal_daftar'");
						$cek_daftar = mysqli_fetch_array($q_cek_daftar);


						if($cek_daftar['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $cek_daftar['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">02</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Naik Berat Badanya Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						$q_timbang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun' and status_perkembangan = 'Naik'");
						$timbang = mysqli_fetch_array($q_timbang);
						if($timbang['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $timbang['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">03</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Tidak Naik Berat Badanya Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						$q_timbang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun' and status_perkembangan = 'Tidak Naik'");
						$timbang = mysqli_fetch_array($q_timbang);
						if($timbang['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $timbang['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">04</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Baru Pertama Kali Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						$q_timbang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun' and status_perkembangan = 'Baru'");
						$timbang = mysqli_fetch_array($q_timbang);
						if($timbang['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $timbang['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">05</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Ditimbang Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						$q_timbang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun'");
						$timbang = mysqli_fetch_array($q_timbang);
						if($timbang['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $timbang['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">06</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Tidak Hadir Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						if($x < 10){
							$xt = '0'.$x;
						}else{
							$xt = $x;
						}
						$tanggal_daftar = $tahun.'-'.$xt.'-31';
						
						$q_cek_daftar = mysqli_query($con, "select count(*) as total from tbl_bayi where tgl_daftar < '$tanggal_daftar'");
						$cek_daftar = mysqli_fetch_array($q_cek_daftar);
						$daftar = $cek_daftar['total'];

						$q_timbang = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang where bulan_pencatatan = '$x' and tahun_pencatatan = '$tahun'");
						$timbang = mysqli_fetch_array($q_timbang);
						$hadir = $timbang['total'];

						$tidak_hadir = $daftar - $hadir;
						if($tidak_hadir < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $tidak_hadir;
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>
				<tr>
					<th style="border:1px solid #000;">07</th>
					<th colspan="3" style="border:1px solid #000;">Jumlah Bayi Yang Menerima Vitamin A Dibulan Ini</th>';
					for($x=1;$x<13;$x++){
						$q_vit_a = mysqli_query($con, "select count(*) as total from tbl_tumbuh_kembang inner join tbl_detail_pelayanan on tbl_tumbuh_kembang.kode_pencatatan = tbl_detail_pelayanan.kode_pencatatan where tbl_tumbuh_kembang.bulan_pencatatan = '$x' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and tbl_detail_pelayanan.id_kategori_pelayanan = 'P003' or tbl_tumbuh_kembang.bulan_pencatatan = '$x' and tbl_tumbuh_kembang.tahun_pencatatan = '$tahun' and tbl_detail_pelayanan.id_kategori_pelayanan = 'P004'");
						$vit_a = mysqli_fetch_array($q_vit_a);
						if($vit_a['total'] < 1){
							$d_timbang = "";
						}else{
							$d_timbang = $vit_a['total'];
						}
						$table_detail .='<th style="border:1px solid #000;">'.$d_timbang.'</th>';
					}
$table_detail .='</tr>';
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