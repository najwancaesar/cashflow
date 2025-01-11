<?php 
include "includes/koneksi.php";

if($_GET['act'] == 't'){
	$tanggal      	= $_POST['tanggal'];
	$catatan  		= $_POST['catatan'];
	$jumlah         = $_POST['jumlah'];
	$user         	= $_POST['user'];
	$status         = $_POST['status'];
	$aksi			= $_POST['aksi'];

	if($_POST['id_pengeluaran'] == ''){
		$query = "INSERT into pengeluaran(tanggal,catatan,jumlah,user) 
		values('$tanggal','$catatan','$jumlah','$user')";
		$hasil = mysqli_query($con, $query);

		echo "<script>window.alert('Data Berhasil Ditambahkan');
						window.location=('main.php?module=pengeluaran')</script>";
	}else{
		mysqli_query($con, "UPDATE pengeluaran SET tanggal = '$tanggal', catatan = '$catatan', jumlah = '$jumlah' where id_pengeluaran = '$_POST[id_pengeluaran]'");

		echo "<script>window.alert('Data Berhasil Dirubah');
		window.location=('main.php?module=pengeluaran')</script>";
	}
		
			
}

if($_GET['act'] == 'l'){
	$id_pengeluaran      = $_GET['id'];
		
	mysqli_query($con, "UPDATE pengeluaran SET status = 'selesai' where id_pengeluaran = '$id_pengeluaran'");

				echo "<script>window.alert('Data Berhasil Dirubah');
						window.location=('main.php?module=pengeluaran')</script>";
	
}

if($_GET['act'] == 'h'){
$id		= $_GET['id'];

		mysqli_query($con, "Delete from pengeluaran where id_pengeluaran = '$id'");
		
		echo "<script>window.alert('Data Berhasil Dihapus');
				window.location=('main.php?module=pengeluaran')</script>";

}

?>