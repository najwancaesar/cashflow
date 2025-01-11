<?php
include "includes/koneksi.php";

if ($_GET['act'] == 't') {
	$tanggal      	= $_POST['tanggal'];
	$catatan  		= $_POST['catatan'];
	$jumlah         = $_POST['jumlah'];
	$user         	= $_POST['user'];
	$status         = $_POST['status'];
	$aksi			= $_POST['aksi'];
	$idUser 		= $_POST['user'];

	if ($_POST['id_pemasukan'] == '') {
		$query = "INSERT into pemasukan(tanggal,catatan,status,jumlah,user) 
		values('$tanggal','$catatan','$status','$jumlah','$user')";
		$hasil = mysqli_query($con, $query);

		echo "<script>window.alert('Data Berhasil Ditambahkan');
						window.location=('main.php?module=pemasukan')</script>";
	} else {
		mysqli_query($con, "UPDATE pemasukan SET tanggal = '$tanggal', status = '$status', catatan = '$catatan', jumlah = '$jumlah' where id_pemasukan = '$_POST[id_pemasukan]'");

		echo "<script>window.alert('Data Berhasil Dirubah');
		window.location=('main.php?module=pemasukan')</script>";
	}
}

if ($_GET['act'] == 'l') {
	$id_pemasukan      = $_GET['id'];

	mysqli_query($con, "UPDATE pemasukan SET status = 'selesai' where id_pemasukan = '$id_pemasukan'");

	echo "<script>window.alert('Data Berhasil Dirubah');
						window.location=('main.php?module=pemasukan')</script>";
}

if ($_GET['act'] == 'h') {
	$id		= $_GET['id'];

	mysqli_query($con, "Delete from pemasukan where id_pemasukan = '$id'");

	echo "<script>window.alert('Data Berhasil Dihapus');
				window.location=('main.php?module=pemasukan')</script>";
}
