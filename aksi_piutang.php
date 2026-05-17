<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include "includes/csrf_helper.php";
$act = $_GET['act'] ?? '';
$user = (int) ($_SESSION['id_user'] ?? 0);

if (!$user) {
	show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola data piutang.', 'warning', 'main.php?module=home');
}

function require_post_csrf_piutang()
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
		show_sweetalert_and_redirect('Akses ditolak', 'Permintaan tidak valid atau sesi form sudah kedaluwarsa.', 'error', 'main.php?module=piutang');
	}
}

function normalize_optional_due_date_piutang($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return null;
	}

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', 'Format tanggal jatuh tempo piutang tidak valid.', 'error', 'main.php?module=piutang');
	}

	[$year, $month, $day] = array_map('intval', explode('-', $value));
	if (!checkdate($month, $day, $year)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', 'Tanggal jatuh tempo piutang tidak valid.', 'error', 'main.php?module=piutang');
	}

	return $value;
}

if($act == 't'){
	require_post_csrf_piutang();
	$id = (int) ($_POST['id_piutang'] ?? 0);
	$tanggal = $_POST['tanggal'] ?? '';
	$tanggalJatuhTempo = normalize_optional_due_date_piutang($_POST['tanggal_jatuh_tempo'] ?? '');
	$catatan = $_POST['catatan'] ?? '';
	$debitur = $_POST['debitur'] ?? '';
	$jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');
	$status = 'pending';

	if ($tanggal === '' || $jumlah <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal dan jumlah piutang wajib diisi.', 'error', 'main.php?module=piutang');
	}

	if($id <= 0){
		$stmt = $con->prepare("INSERT INTO piutang(tanggal, tanggal_jatuh_tempo, catatan, debitur, jumlah, user, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("ssssdis", $tanggal, $tanggalJatuhTempo, $catatan, $debitur, $jumlah, $user, $status);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil ditambahkan.', 'success', 'main.php?module=piutang');
	}else{
		$stmt = $con->prepare("UPDATE piutang SET tanggal = ?, tanggal_jatuh_tempo = ?, debitur = ?, catatan = ?, jumlah = ? WHERE id_piutang = ? AND user = ?");
		$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $debitur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil diubah.', 'success', 'main.php?module=piutang');
	}

			
}

if($act == 'l'){
	require_post_csrf_piutang();
	$id_piutang = (int) ($_POST['id_piutang'] ?? 0);
		
	$stmt = $con->prepare("UPDATE piutang SET status = 'selesai' WHERE id_piutang = ? AND user = ?");
	$stmt->bind_param("ii", $id_piutang, $user);
	$stmt->execute();
	$affectedRows = $stmt->affected_rows;
	$stmt->close();

	if ($affectedRows <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data piutang tidak ditemukan atau sudah selesai.', 'warning', 'main.php?module=piutang');
	}

	show_sweetalert_and_redirect('Berhasil', 'Status piutang berhasil diperbarui.', 'success', 'main.php?module=piutang');
	
}

if($act == 'h'){
	require_post_csrf_piutang();
	$id = (int) ($_POST['id_piutang'] ?? 0);

		$stmt = $con->prepare("DELETE FROM piutang WHERE id_piutang = ? AND user = ?");
		$stmt->bind_param("ii", $id, $user);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		$stmt->close();

		if ($affectedRows <= 0) {
			show_sweetalert_and_redirect('Gagal', 'Data piutang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=piutang');
		}
		
		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil dihapus.', 'success', 'main.php?module=piutang');

}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan piutang tidak dikenali.', 'error', 'main.php?module=piutang');
?>
