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
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola data utang.', 'warning', 'main.php?module=home');
}

function require_post_csrf_hutang()
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
		show_sweetalert_and_redirect('Akses ditolak', 'Permintaan tidak valid atau sesi form sudah kedaluwarsa.', 'error', 'main.php?module=hutang');
	}
}

function normalize_optional_due_date_hutang($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return null;
	}

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', 'Format tanggal jatuh tempo utang tidak valid.', 'error', 'main.php?module=hutang');
	}

	[$year, $month, $day] = array_map('intval', explode('-', $value));
	if (!checkdate($month, $day, $year)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', 'Tanggal jatuh tempo utang tidak valid.', 'error', 'main.php?module=hutang');
	}

	return $value;
}

if($act == 't'){
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);
	$tanggal = $_POST['tanggal'] ?? '';
	$tanggalJatuhTempo = normalize_optional_due_date_hutang($_POST['tanggal_jatuh_tempo'] ?? '');
	$catatan = $_POST['catatan'] ?? '';
	$kreditur = $_POST['kreditur'] ?? '';
	$jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');
	$status = 'pending';

	if ($tanggal === '' || $jumlah <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal dan jumlah utang wajib diisi.', 'error', 'main.php?module=hutang');
	}

	if($id <= 0){
		$stmt = $con->prepare("INSERT INTO hutang(tanggal, tanggal_jatuh_tempo, catatan, kreditur, jumlah, user, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("ssssdis", $tanggal, $tanggalJatuhTempo, $catatan, $kreditur, $jumlah, $user, $status);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil ditambahkan.', 'success', 'main.php?module=hutang');
	}else{
		$stmt = $con->prepare("UPDATE hutang SET tanggal = ?, tanggal_jatuh_tempo = ?, kreditur = ?, catatan = ?, jumlah = ? WHERE id_hutang = ? AND user = ?");
		$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $kreditur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil diubah.', 'success', 'main.php?module=hutang');
	}

			
}

if($act == 'l'){
	require_post_csrf_hutang();
	$id_hutang = (int) ($_POST['id_hutang'] ?? 0);
		
	$stmt = $con->prepare("UPDATE hutang SET status = 'selesai' WHERE id_hutang = ? AND user = ?");
	$stmt->bind_param("ii", $id_hutang, $user);
	$stmt->execute();
	$affectedRows = $stmt->affected_rows;
	$stmt->close();

	if ($affectedRows <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data utang tidak ditemukan atau sudah selesai.', 'warning', 'main.php?module=hutang');
	}

	show_sweetalert_and_redirect('Berhasil', 'Status utang berhasil diperbarui.', 'success', 'main.php?module=hutang');
	
}

if($act == 'h'){
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);

		$stmt = $con->prepare("DELETE FROM hutang WHERE id_hutang = ? AND user = ?");
		$stmt->bind_param("ii", $id, $user);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		$stmt->close();

		if ($affectedRows <= 0) {
			show_sweetalert_and_redirect('Gagal', 'Data utang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=hutang');
		}
		
		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil dihapus.', 'success', 'main.php?module=hutang');

}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan utang tidak dikenali.', 'error', 'main.php?module=hutang');
?>
