<?php 
include "includes/koneksi.php";

if($_GET['act'] == 't'){
	$tanggal      	= $_POST['tanggal'];
	$catatan  		= $_POST['catatan'];
	$jumlah         = $_POST['jumlah'];
	$user         	= $_POST['user'];

		$query = "INSERT into hutang(tanggal,catatan,jumlah,user) 
		values('$tanggal','$catatan','$jumlah','$user')";
		$hasil = mysqli_query($con, $query);

		echo "<script>window.alert('Data Berhasil Ditambahkan');
						window.location=('main.php?module=hutang')</script>";
			
}

if($_GET['act'] == 'l'){
	$id_hutang   = $_GET['id'];
		
	mysqli_query($con, "UPDATE hutang SET status = 'selesai' where id_hutang = '$id_hutang'");

				echo "<script>window.alert('Data Berhasil Dirubah');
						window.location=('main.php?module=hutang')</script>";
	
}

if($_GET['act'] == 'h'){
$id		= $_GET['id'];

		mysqli_query($con, "Delete from hutang where id_hutang = '$id'");
		
		echo "<script>window.alert('Data Berhasil Dihapus');
				window.location=('main.php?module=hutang')</script>";

}

?>