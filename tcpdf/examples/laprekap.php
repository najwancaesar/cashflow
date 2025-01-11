<?php

include "../../includes/koneksi.php";
$tgl_awal = $_GET['tgl_awal'];
$tgl_akhir 	= $_GET['tgl_akhir'];


// Include the main TCPDF library (search for installation path).
require_once('tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('CashFlow');
$pdf->SetTitle('Laporan Transaksi CashFlow Control');
$pdf->SetSubject('TCPDF');
$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

// remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, 10, PDF_MARGIN_RIGHT);
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
$pdf->SetFont('times', '', 10);

// add a page
$pdf->AddPage('P');

// set cell padding
$pdf->setCellPaddings(1, 1, 1, 1);

// set cell margins
$pdf->setCellMargins(1, 1, 1, 1);

// set color for background
$pdf->SetFillColor(255, 255, 127);


$pdf->SetFont('times', '', 12); 
$today = date("d-m-Y");

$table 			= $_POST['tabel'];
$tanggal 		= $_POST['tanggal'];
$pecahTanggal 	= explode(' - ', $tanggal);
$tglMulai 		= date('Y-m-d', strtotime($pecahTanggal[0]));
$tglAkhir 		= date('Y-m-d', strtotime(end($pecahTanggal)));


$header = '<table>';
$header .= '
		<tr>
			<th style="padding:30px; text-align: center;"></th>
		</tr>
		<tr style="padding:80px; text-align: center;">
			<th style="padding:80px; text-align: center;"><h3><b>CASHFLOW ITPGT</b></h3></th>
		</tr>
		<tr style="padding:30px; text-align: center;">
			<th>POLITEKNIK GAJAH TUNGGAL PRODI TEKNOLOGI INFORMASI</th>
		</tr>
		
		<tr style="padding:30px;">
			<th><hr/></th>
		</tr>
		';

$header .= '</table>';
$pdf->WriteHTMLCell(0,0,'','',$header,0,1,0,true,'C',true);

if($table == 'pemasukan'){
	$q_transaksi = mysqli_query($con, "select * from pemasukan 
			inner join user on pemasukan.user = user.id_user  
			where pemasukan.status = 'selesai' and pemasukan.tanggal between '$tglMulai' and '$tglAkhir'");

	$table_header = '<table>';
	$table_header .= '
			<tr>
				<th style="padding:150px; text-align: center; font-size: 14;">
					<b>Laporan Pemasukan<br></b> <span style="font-size: 10;"> Tanggal : '.date('d M Y', strtotime($tglMulai)).' s/d '.date('d M Y', strtotime($tglAkhir)). '</span>
				</th>
			</tr>
			';
	
	$table_header .= '</table>';
	$pdf->WriteHTMLCell(0,0,'','',$table_header,0,1,0,true,'C',true);
	
	
	$tabel = '<table style="padding:5px; font-size: 10;">';
	$tabel .= '
			<tr>
				<th style="border:1px solid #000; width:100px;"><b>No. </b></th>
				<th style="border:1px solid #000; width:180px;"><b>Tanggal</b></th>
				<th style="border:1px solid #000; width:190px;"><b>Jumlah</b></th>
				<th style="border:1px solid #000; width:480px;"><b>Catatan</b></th>
				<th style="border:1px solid #000; width:100px;"><b>Status</b></th>
			</tr>';
				
			
			$no = 1;
			$total = 0;
			while($transaksi = mysqli_fetch_array($q_transaksi)){
				$tabel .='
							<tr>
								<th style="border:1px solid #000;">'.$no.'</th>
								<th style="border:1px solid #000; text-align: left;">'.date('d M Y', strtotime($transaksi['tanggal'])).'</th>
								<th style="border:1px solid #000; text-align: right;">Rp. '.number_format($transaksi['jumlah']).'</th>
								<th style="border:1px solid #000; text-align: left;">'.$transaksi['catatan'].'</th>
								<th style="border:1px solid #000; text-align: left;">'.$transaksi['status'].'</th>
							</tr>
						';
				$no++;
				$total = $total + $transaksi['jumlah'];
				$f_total = number_format( $total,0,',','.');
			}
			$tabel .='
			<tr border="0">
				<th style="border:0px solid #000;" colspan="2"><b> Total </b></th>
				<th style="border:0px solid #000; text-align: right;"><b> Rp. '.$f_total.' </b></th>
				<th style="border:0px solid #000;" colspan="2"></th>
			</tr>
			';
	
	$tabel .= '</table>';
}elseif($table == 'pengeluaran'){
	$q_transaksi = mysqli_query($con, "select * from pengeluaran 
			inner join user on pengeluaran.user = user.id_user and pengeluaran.tanggal between '$tglMulai' and '$tglAkhir'
			");

	$table_header = '<table>';
	$table_header .= '
			<tr>
				<th style="padding:150px; text-align: center; font-size: 14;">
					<b>Laporan Pengeluaran<br></b> <span style="font-size: 10;"> Tanggal : '.date('d M Y', strtotime($tglMulai)).' s/d '.date('d M Y', strtotime($tglAkhir)). '</span>
				</th>
			</tr>
			';
	
	$table_header .= '</table>';
	$pdf->WriteHTMLCell(0,0,'','',$table_header,0,1,0,true,'C',true);
	
	
	$tabel = '<table style="padding:5px; font-size: 10;">';
	$tabel .= '
			<tr>
				<th style="border:1px solid #000; width:100px;"><b>No. </b></th>
				<th style="border:1px solid #000; width:180px;"><b>Tanggal</b></th>
				<th style="border:1px solid #000; width:190px;"><b>Jumlah</b></th>
				<th style="border:1px solid #000; width:550px;"><b>Catatan</b></th>
			</tr>';
				
			
			$no = 1;
			$total = 0;
			while($transaksi = mysqli_fetch_array($q_transaksi)){
				$tabel .='
							<tr>
								<th style="border:1px solid #000;">'.$no.'</th>
								<th style="border:1px solid #000; text-align: left;">'.date('d M Y', strtotime($transaksi['tanggal'])).'</th>
								<th style="border:1px solid #000; text-align: right;">Rp. '.number_format($transaksi['jumlah']).'</th>
								<th style="border:1px solid #000; text-align: left;">'.$transaksi['catatan'].'</th>
							</tr>
						';
				$no++;
				$total = $total + $transaksi['jumlah'];
				$f_total = number_format( $total,0,',','.');
			}
			$tabel .='
			<tr border="0">
				<th style="border:0px solid #000;" colspan="2"><b> Total </b></th>
				<th style="border:0px solid #000; text-align: right;"><b> Rp. '.$f_total.' </b></th>
				<th style="border:0px solid #000;" colspan="1"></th>
			</tr>
			';
	
	$tabel .= '</table>';
}elseif($table == 'hutang'){
	$q_transaksi = mysqli_query($con, "select * from hutang 
			inner join user on hutang.user = user.id_user and hutang.tanggal between '$tglMulai' and '$tglAkhir'
			");

	$table_header = '<table>';
	$table_header .= '
			<tr>
				<th style="padding:150px; text-align: center; font-size: 14;">
					<b>Laporan Utang<br></b> <span style="font-size: 10;"> Tanggal : '.date('d M Y', strtotime($tglMulai)).' s/d '.date('d M Y', strtotime($tglAkhir)). '</span>
				</th>
			</tr>
			';
	
	$table_header .= '</table>';
	$pdf->WriteHTMLCell(0,0,'','',$table_header,0,1,0,true,'C',true);
	
	
	$tabel = '<table style="padding:5px; font-size: 10;">';
	$tabel .= '
			<tr>
				<th style="border:1px solid #000; width:100px;"><b>No. </b></th>
				<th style="border:1px solid #000; width:180px;"><b>Tanggal</b></th>
				<th style="border:1px solid #000; width:190px;"><b>Jumlah</b></th>
				<th style="border:1px solid #000; width:550px;"><b>Catatan</b></th>
			</tr>';
				
			
			$no = 1;
			$total = 0;
			while($transaksi = mysqli_fetch_array($q_transaksi)){
				$tabel .='
							<tr>
								<th style="border:1px solid #000;">'.$no.'</th>
								<th style="border:1px solid #000; text-align: left;">'.date('d M Y', strtotime($transaksi['tanggal'])).'</th>
								<th style="border:1px solid #000; text-align: right;">Rp. '.number_format($transaksi['jumlah']).'</th>
								<th style="border:1px solid #000; text-align: left;">'.$transaksi['catatan'].'</th>
							</tr>
						';
				$no++;
				$total = $total + $transaksi['jumlah'];
				$f_total = number_format( $total,0,',','.');
			}
			$tabel .='
			<tr border="0">
				<th style="border:0px solid #000;" colspan="2"><b> Total </b></th>
				<th style="border:0px solid #000; text-align: right;"><b> Rp. '.$f_total.' </b></th>
				<th style="border:0px solid #000;" colspan="1"></th>
			</tr>
			';
	
	$tabel .= '</table>';
}elseif($table == 'piutang'){
	$q_transaksi = mysqli_query($con, "select * from piutang 
			inner join user on piutang.user = user.id_user and piutang.tanggal between '$tglMulai' and '$tglAkhir'
			");

	$table_header = '<table>';
	$table_header .= '
			<tr>
				<th style="padding:150px; text-align: center; font-size: 14;">
					<b>Laporan Piutang<br></b> <span style="font-size: 10;"> Tanggal : '.date('d M Y', strtotime($tglMulai)).' s/d '.date('d M Y', strtotime($tglAkhir)). '</span>
				</th>
			</tr>
			';
	
	$table_header .= '</table>';
	$pdf->WriteHTMLCell(0,0,'','',$table_header,0,1,0,true,'C',true);
	
	
	$tabel = '<table style="padding:5px; font-size: 10;">';
	$tabel .= '
			<tr>
				<th style="border:1px solid #000; width:100px;"><b>No. </b></th>
				<th style="border:1px solid #000; width:180px;"><b>Tanggal</b></th>
				<th style="border:1px solid #000; width:190px;"><b>Jumlah</b></th>
				<th style="border:1px solid #000; width:550px;"><b>Catatan</b></th>
			</tr>';
				
			
			$no = 1;
			$total = 0;
			while($transaksi = mysqli_fetch_array($q_transaksi)){
				$tabel .='
							<tr>
								<th style="border:1px solid #000;">'.$no.'</th>
								<th style="border:1px solid #000; text-align: left;">'.date('d M Y', strtotime($transaksi['tanggal'])).'</th>
								<th style="border:1px solid #000; text-align: right;">Rp. '.number_format($transaksi['jumlah']).'</th>
								<th style="border:1px solid #000; text-align: left;">'.$transaksi['catatan'].'</th>
							</tr>
						';
				$no++;
				$total = $total + $transaksi['jumlah'];
				$f_total = number_format( $total,0,',','.');
			}
			$tabel .='
			<tr border="0">
				<th style="border:0px solid #000;" colspan="2"><b> Total </b></th>
				<th style="border:0px solid #000; text-align: right;"><b> Rp. '.$f_total.' </b></th>
				<th style="border:0px solid #000;" colspan="1"></th>
			</tr>
			';
	
	$tabel .= '</table>';
}

$pdf->WriteHTMLCell(0,0,'','',$tabel,0,1,0,true,'C',true);






// move pointer to last page
$pdf->lastPage();

// ---------------------------------------------------------
ob_clean();

$pdf->Output('Laporan Rekap Transaksi CV Anugrah Pratama Prakarsa.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

?>