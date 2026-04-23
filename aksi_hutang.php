<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
$act = $_GET['act'] ?? '';
$user = (int) ($_SESSION['id_user'] ?? 0);

if (!$user) {
	show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola data utang.', 'warning', 'main.php?module=home');
}

if($act == 't'){
	$id = (int) ($_POST['id_hutang'] ?? 0);
	$tanggal = $_POST['tanggal'] ?? '';
	$catatan = $_POST['catatan'] ?? '';
	$kreditur = $_POST['kreditur'] ?? '';
	$jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');

	if ($tanggal === '' || $jumlah <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal dan jumlah utang wajib diisi.', 'error', 'main.php?module=hutang');
	}

	if($_POST['id_hutang'] == ''){
		$stmt = $con->prepare("INSERT INTO hutang(tanggal, catatan, kreditur, jumlah, user) VALUES(?, ?, ?, ?, ?)");
		$stmt->bind_param("sssdi", $tanggal, $catatan, $kreditur, $jumlah, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil ditambahkan.', 'success', 'main.php?module=hutang');
	}else{
		$stmt = $con->prepare("UPDATE hutang SET tanggal = ?, kreditur = ?, catatan = ?, jumlah = ? WHERE id_hutang = ? AND user = ?");
		$stmt->bind_param("sssdii", $tanggal, $kreditur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil diubah.', 'success', 'main.php?module=hutang');
	}

			
}

if($act == 'l'){
	$id_hutang = (int) ($_GET['id'] ?? 0);
		
	$stmt = $con->prepare("UPDATE hutang SET status = 'selesai' WHERE id_hutang = ? AND user = ?");
	$stmt->bind_param("ii", $id_hutang, $user);
	$stmt->execute();
	$stmt->close();

	show_sweetalert_and_redirect('Berhasil', 'Status utang berhasil diperbarui.', 'success', 'main.php?module=hutang');
	
}

if($act == 'h'){
$id = (int) ($_GET['id'] ?? 0);

		$stmt = $con->prepare("DELETE FROM hutang WHERE id_hutang = ? AND user = ?");
		$stmt->bind_param("ii", $id, $user);
		$stmt->execute();
		$stmt->close();
		
		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil dihapus.', 'success', 'main.php?module=hutang');

}

?>
