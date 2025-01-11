<?php 
include "includes/koneksi.php";

if($_GET['act'] == 't'){
	if($_POST['konfirmasi_password'] == $_POST['password']){
		$username 	   = $_POST['username'];
		$nama          = $_POST['nama'];
		$email         = $_POST['email'];
		$no_telp       = $_POST['no_telp'];
		$password       = $_POST['password'];
		$role       	= $_POST['role'];

		$query = "INSERT into user(username,nama,email,no_telp,role,password,foto) 
		values('$username','$nama','$email','$no_telp','$role','$password','')";
		$hasil = mysqli_query($con, $query);

		echo "<script>window.alert('Data Berhasil Ditambahkan');
						window.location=('main.php?module=pengguna')</script>";
	}else{
		echo "<script>window.alert('Gagal. Password Tidak Sesuai');
						window.location=('main.php?module=pengguna')</script>";
	}
	
			
}

if($_GET['act'] == 'e'){
	$id_user      	= $_POST['id_user'];
	$username  		= $_POST['username'];
	$nama       	= $_POST['nama'];
	$email       	= $_POST['email'];
	$no_telp        = $_POST['no_telp'];

	if($_FILES['foto']['name'] != ""){
        $foto      = $_FILES['foto']['name'];
        $lokasi    = $_FILES['foto']['tmp_name'];
        $ukuran    = $_FILES['foto']['size'];
        $tipe_file = $_FILES['foto']['type'];

        $ekstensi1  = explode('.', $foto);
        $ekstensi  = $ekstensi1[1];
        $valid_file = array('jpeg', 'jpg', 'png');

        if(in_array($ekstensi, $valid_file) == 0){
            echo "<script>window.alert('Peringatan!! File Tidak Validd');
						window.location=('main.php?module=profile&id=$id_user')</script>";
        }elseif($ukuran > 50000000){
            echo "<script>window.alert('Peringatan!! File Tidak Valid');
						window.location=('main.php?module=profile&id=$id_user')</script>";
        }else{
            $namafile = time()."_".$foto;
            move_uploaded_file($lokasi, "assets/img/profil/".$namafile);

            mysqli_query($con, "UPDATE user SET username='$username', nama = '$nama', email = '$email', no_telp = '$no_telp', foto = '$namafile' where id_user= '$id_user'");

			echo "<script>window.alert('Data User Berhasil Dirubah');
						window.location=('main.php?module=profile&id=$id_user')</script>";
        }

        
    }else{
		mysqli_query($con, "UPDATE user SET username='$username', nama = '$nama', email = '$email', no_telp = '$no_telp' where id_user= '$id_user'");

		echo "<script>window.alert('Data User Berhasil Dirubah');
						window.location=('main.php?module=profile&id=$id_user')</script>";
	}

				

}

if($_GET['act'] == 'p'){
	if($_POST['password_baru'] == $_POST['konfirmasi_password']){
		$password       = $_POST['password_baru'];
		$id_user        = $_POST['id_user'];

		mysqli_query($con, "UPDATE user SET password ='$password' where id_user= '$id_user'");

		echo "<script>window.alert('Password Berhasil Dirubah');
		window.location=('main.php?module=profile&id=$id_user')</script>";
	}else{
		echo "<script>window.alert('Gagal. Password Tidak Sesuai');
						window.location=('main.php?module=pengguna')</script>";
	}

}

if($_GET['act'] == 'a'){
	$id		= $_GET['id'];
	$cek = mysqli_query($con, "SELECT * FROM user where id_user = $id and is_active = '1'");
	if(mysqli_num_rows($cek) > 0) {
		mysqli_query($con, "UPDATE user SET is_active = '1' where id_user= $id");
	}
	// else{
	// 	mysqli_query($con, "UPDATE user SET is_active = '0' where id_user= $id");
	// 	echo "<script> window.alert"
	// }

	// 			echo "<script>window.alert('Data User Berhasil Diaktifkan');
	// 					window.location=('main.php?module=pengguna')</script>";
}

if($_GET['act'] == 'h'){
$id		= $_GET['id'];

		mysqli_query($con, "Delete from user where id_user = '$id'");
		
		echo "<script>window.alert('Data Berhasil Dihapus');
				window.location=('main.php?module=pengguna')</script>";

}

?>