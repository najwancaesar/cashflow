<?php 
// LEGACY NON-ACTIVE ENDPOINT:
// File ini dipertahankan untuk referensi lama dan tidak dipakai
// dalam alur aktif aplikasi cashflow.
include "includes/sweetalert_helper.php";

show_sweetalert_and_redirect('Endpoint lama dinonaktifkan', 'Gunakan modul hutang aktif yang terbaru.', 'info', 'main.php?module=hutang');
?>
<?php
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";

if($_GET['act'] == 't'){
	$tanggal      	= $_POST['tanggal'];
	$catatan  		= $_POST['catatan'];
	$jumlah         = $_POST['jumlah'];
	$user         	= $_POST['user'];

		$query = "INSERT into hutang(tanggal,catatan,jumlah,user) 
		values('$tanggal','$catatan','$jumlah','$user')";
		$hasil = mysqli_query($con, $query);

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil ditambahkan.', 'success', 'main.php?module=hutang');
			
}

if($_GET['act'] == 'l'){
	$id_hutang   = $_GET['id'];
		
	mysqli_query($con, "UPDATE hutang SET status = 'selesai' where id_hutang = '$id_hutang'");

	show_sweetalert_and_redirect('Berhasil', 'Status utang berhasil diperbarui.', 'success', 'main.php?module=hutang');
	
}

if($_GET['act'] == 'h'){
$id		= $_GET['id'];

		mysqli_query($con, "Delete from hutang where id_hutang = '$id'");
		
		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil dihapus.', 'success', 'main.php?module=hutang');

}

?>
