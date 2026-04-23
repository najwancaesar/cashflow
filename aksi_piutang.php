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
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola data piutang.', 'warning', 'main.php?module=home');
}

if($act == 't'){
	$id = (int) ($_POST['id_piutang'] ?? 0);
	$tanggal = $_POST['tanggal'] ?? '';
	$catatan = $_POST['catatan'] ?? '';
	$debitur = $_POST['debitur'] ?? '';
	$jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');

	if ($tanggal === '' || $jumlah <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal dan jumlah piutang wajib diisi.', 'error', 'main.php?module=piutang');
	}

	if($_POST['id_piutang'] == ''){
		$stmt = $con->prepare("INSERT INTO piutang(tanggal, catatan, debitur, jumlah, user) VALUES(?, ?, ?, ?, ?)");
		$stmt->bind_param("sssdi", $tanggal, $catatan, $debitur, $jumlah, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil ditambahkan.', 'success', 'main.php?module=piutang');
	}else{
		$stmt = $con->prepare("UPDATE piutang SET tanggal = ?, debitur = ?, catatan = ?, jumlah = ? WHERE id_piutang = ? AND user = ?");
		$stmt->bind_param("sssdii", $tanggal, $debitur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil diubah.', 'success', 'main.php?module=piutang');
	}

			
}

if($act == 'l'){
	$id_piutang = (int) ($_GET['id'] ?? 0);
		
	$stmt = $con->prepare("UPDATE piutang SET status = 'selesai' WHERE id_piutang = ? AND user = ?");
	$stmt->bind_param("ii", $id_piutang, $user);
	$stmt->execute();
	$stmt->close();

	show_sweetalert_and_redirect('Berhasil', 'Status piutang berhasil diperbarui.', 'success', 'main.php?module=piutang');
	
}

if($act == 'h'){
$id = (int) ($_GET['id'] ?? 0);

		$stmt = $con->prepare("DELETE FROM piutang WHERE id_piutang = ? AND user = ?");
		$stmt->bind_param("ii", $id, $user);
		$stmt->execute();
		$stmt->close();
		
		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil dihapus.', 'success', 'main.php?module=piutang');

}

?>
