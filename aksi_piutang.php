<?php 
include "includes/koneksi.php";

if($_GET['act'] == 't'){
	$id      		= $_POST['id_piutang'];
	$tanggal      	= $_POST['tanggal'];
	$catatan  		= $_POST['catatan'];
	$debitur  		= $_POST['debitur'];
	$jumlah         = $_POST['jumlah'];
	$user         	= $_POST['user'];

	if($_POST['id_piutang'] == ''){
		$query = "INSERT into piutang(tanggal,catatan,debitur,jumlah,user) 
		values('$tanggal','$catatan','$debitur','$jumlah','$user')";
		$hasil = mysqli_query($con, $query);

		echo "<script>window.alert('Data Berhasil Ditambahkan');
						window.location=('main.php?module=piutang')</script>";
	}else{
		mysqli_query($con, "UPDATE piutang SET tanggal = '$tanggal', debitur = '$debitur', catatan = '$catatan', jumlah = '$jumlah' where id_piutang = '$id'");

		echo "<script>window.alert('Data Berhasil Dirubah');
						window.location=('main.php?module=piutang')</script>";
	}

			
}

if($_GET['act'] == 'l'){
	$id_piutang   = $_GET['id'];
		
	mysqli_query($con, "UPDATE piutang SET status = 'selesai' where id_piutang = '$id_piutang'");

				echo "<script>window.alert('Data Berhasil Dirubah');
						window.location=('main.php?module=piutang')</script>";
	
}

if($_GET['act'] == 'h'){
$id		= $_GET['id'];

		mysqli_query($con, "Delete from piutang where id_piutang = '$id'");
		
		echo "<script>window.alert('Data Berhasil Dihapus');
				window.location=('main.php?module=piutang')</script>";

}

?>