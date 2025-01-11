<?php

include "../../includes/koneksi.php";
$id_pendaftaran = $_GET['id'];
$q_daftar = mysqli_query($con,"select * from tbl_pendaftaran inner join tbl_pasien on tbl_pendaftaran.id_pasien = tbl_pasien.id_pasien 
inner join tbl_user on tbl_pendaftaran.kode_user = tbl_user.kode_user
where tbl_pendaftaran.id_pendaftaran = '$id_pendaftaran'");
$daftar =mysqli_fetch_array($q_daftar);

$sql_jasa 	= "SELECT sum(harga) as total_jasa FROM tbl_detail_jasa where id_pendaftaran = '$id_pendaftaran'"; 
$hasil_jasa 	= mysqli_query($con,$sql_jasa); 
$row_jasa	= mysqli_fetch_array($hasil_jasa);

$sql_obat 	= "SELECT sum(sub_total) as total_obat FROM tbl_detail_obat where id_pendaftaran = '$id_pendaftaran'"; 
$hasil_obat 	= mysqli_query($con,$sql_obat); 
$row_obat	= mysqli_fetch_array($hasil_obat);

$total = $row_jasa['total_jasa'] + $row_obat['total_obat'] ;


// Include the main TCPDF library (search for installation path).
require_once('tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Kamandanu');
$pdf->SetTitle('Bukti Rekam Medis');
$pdf->SetSubject('TCPDF Tutorial');
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
$pdf->SetFont('times', '', 12);

// add a page
$pdf->AddPage('R');

// set cell padding
$pdf->setCellPaddings(1, 1, 1, 1);

// set cell margins
$pdf->setCellMargins(1, 1, 1, 1);

// set color for background
$pdf->SetFillColor(255, 255, 127);


$pdf->SetFont('times', '', 11); 
$today = date("d-m-Y");
$header = '<table style="padding: 5px;" border="0">';
$header .= '
		<tr style="padding:80px; text-align: left;">
			<th colspan="2" style="padding:80px; text-align: left;"><h3><b>Laporan Rekam Medis </b></h3></th>
		</tr>
		<tr style="padding:80px; text-align: left;">
			<th colspan="2" style="padding:80px; text-align: left;"><h4><b>Klinik Bidan Bening</b></h4></th>
		</tr>
		<tr style="padding:80px; text-align: left;">
			<th colspan="2" style="padding:10px; text-align: left;"><p>Jl. Sindangkasih No. 14</p></th>
		</tr>
		<tr style="padding:30px; text-align: left;">
			<th></th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Kode Pasien</th>
			<th style="width:250px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['id_pasien'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Nama Pasien</th>
			<th style="width:400px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['nama_pasien'].'</th>
			<th style="width:50px; text-align: left;"></th>
			<th style="width:200px; text-align: left;">No. Tlp</th>
			<th style="width:200px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['no_hp'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Alamat</th>
			<th style="width:400px; height:150; text-align: left; border-style: solid solid solid solid;" rowspan="2">: '.$daftar['alamat'].'</th>
			<th style="width:50px; text-align: left;"></th>
			<th style="width:200px; text-align: left;">Gol. Darah</th>
			<th style="width:100px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['gol_darah'].'</th>
		</tr>
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Keluhan</th>
			<th style="width:850px; text-align: left; height: 75; border-style: solid solid solid solid;">: '.$daftar['keluhan'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Tekanan Darah</th>
			<th style="width:250px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['tekanan_darah'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Berat Badan</th>
			<th style="width:250px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['berat_badan'].'</th>
		</tr>
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Umur Kehamilan</th>
			<th style="width:300px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['umur_kehamilan'].'</th>
			<th style="width:50px; text-align: left;"></th>
			<th style="width:200px; text-align: left;">Kaki Bengkak</th>
			<th style="width:300px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['kaki_bengkak'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Tinggi Fundus</th>
			<th style="width:300px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['tinggi_fundus'].'</th>
			<th style="width:50px; text-align: left;"></th>
			<th style="width:200px; text-align: left;">Letak Janin</th>
			<th style="width:300px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['iletak_janin'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Hasil Pemeriksaan Lab</th>
			<th style="width:850px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['hasil_pemeriksaan_lab'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Tindakan</th>
			<th style="width:850px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['tindakan'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Nasihat</th>
			<th style="width:850px; text-align: left;; border-style: solid solid solid solid;">: '.$daftar['nasihat'].'</th>
		</tr>
		<tr>
			<th style="width:200px; text-align: left;">Tgl Kemabali</th>
			<th style="width:200px; text-align: left; border-style: solid solid solid solid;">: '.$daftar['tgl_kembali'].'</th>
		</tr>
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
				<td></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;">Bidan Pemeriksa</td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;"></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;"></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		<tr>
				<td style="font-size: 25px; width:700px;"><u>'.$daftar['nama'].'</u></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		';


$header .= '</table>';
$pdf->WriteHTMLCell(0,0,'','',$header,0,1,0,true,'C',true);

$pdf->AddPage();

$header = '<table>';
$header .= '
		<tr style="padding:80px; text-align: center;">
			<th style="padding:80px; text-align: center;"><h3><b>Klinik Bidan Bening</b></h3></th>
		</tr>
		<tr style="padding:30px; text-align: center;">
			<th>Jl. Sindangkasih No. 14</th>
		</tr>
		<tr>
			<th style="padding:80px; text-align: center;">Kab. Ciamis</th>
		</tr>
		<tr>
			<th style="padding:80px; text-align: center;">Tlp (0265) 87236253</th>
		</tr>
		<tr style="padding:30px;">
			<th><hr /></th>
		</tr>
		';

$header .= '</table>';
$pdf->WriteHTMLCell(0,0,'','',$header,0,1,0,true,'C',true);

$table_suplier = '<table>';
$table_suplier .= '
		<tr>
			<th style="padding:150px; text-align: center;">
				<h2><b>Kwitansi Pembayaran</b></h2>
			</th>
		</tr>
		';

$table_suplier .= '</table>';
$pdf->WriteHTMLCell(0,0,'','',$table_suplier,0,1,0,true,'C',true);

$header = '<table style="padding: 5px;" border="0">';
$header .= '
		
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
		<th>
			<table border="1">
				<tr style="padding: 10px;">
					<th style="width:550px;"><b>Tindakan</b></th>
					<th style="width:150px;"><b> Biaya</b></th>
				</tr>';
				$sql_jasa = mysqli_query($con,"select * from tbl_detail_jasa 
				inner join tbl_jasa on tbl_detail_jasa.kode_jasa = tbl_jasa.kode_jasa 
				where tbl_detail_jasa.id_pendaftaran = '$id_pendaftaran'");
				while($jasa=mysqli_fetch_array($sql_jasa)){
				$header .='<tr>
									<th>'.$jasa['nama'].'</th>
									<th>'.$jasa['harga'].'</th>
								</tr>';

				}
$header .='	</table>
</th>
		</tr>
		
		<tr>
			<th style="text-align: left;"></th>
		</tr>
		<tr>
		<th>
			<table border="1">
				<tr style="padding: 10px;">
					<th style="width:300px;"><b>Nama Obat</b></th>
					<th style="width:150px;"><b>Harga Satuan</b></th>
					<th style="width:100px;"><b>Qty</b></th>
					<th style="width:150px;"><b>Sub Total</b></th>
					<th style="width:350px;"><b>Keterangan</b></th>
				</tr>';
				$sql_obat = mysqli_query($con,"select * from tbl_detail_obat 
				inner join tbl_obat on tbl_detail_obat.kode_obat = tbl_obat.kode_obat 
				where tbl_detail_obat.id_pendaftaran = '$id_pendaftaran'");
				while($obat=mysqli_fetch_array($sql_obat)){
				$header .='<tr>
									<th>'.$obat['nama_obat'].'</th>
									<th>'.$obat['harga_satuan'].'</th>
									<th>'.$obat['qty'].'</th>
									<th>'.$obat['sub_total'].'</th>
									<th>'.$obat['keterangan'].'</th>
								</tr>';

				}
$header .='</table>
			</th>
		</tr>
		<tr>
				<td></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;">Bidan Pemeriksa</td>
				<td style="font-size: 30px; width:350px;"><b>Total Rp.  '.number_format($total).'</b></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;"></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		<tr>
				<td style="font-size: 20px; width:700px;"></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		<tr>
				<td style="font-size: 25px; width:700px;"><u>'.$daftar['nama'].'</u></td>
				<td style="font-size: 30px; width:350px;"></td>
		</tr>
		';


$header .= '</table>';
$pdf->WriteHTMLCell(0,0,'','',$header,0,1,0,true,'C',true);


// move pointer to last page
$pdf->lastPage();

// ---------------------------------------------------------
ob_clean();

$pdf->Output('Bukti Rekam Medis.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+

?>