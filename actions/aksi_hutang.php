<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
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

function normalize_required_date_hutang($value, $fieldName)
{
	$value = trim((string) $value);
	if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', $fieldName . ' wajib diisi dengan format tanggal yang valid.', 'error', 'main.php?module=hutang');
	}

	[$year, $month, $day] = array_map('intval', explode('-', $value));
	if (!checkdate($month, $day, $year)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', $fieldName . ' tidak valid.', 'error', 'main.php?module=hutang');
	}

	return $value;
}

function validate_active_wallet_for_user_hutang($con, $walletId, $userId)
{
	$stmt = $con->prepare("SELECT id_wallet, nama_wallet, tipe_wallet
		FROM wallet
		WHERE id_wallet = ? AND user_id = ? AND is_active = 1
		LIMIT 1");
	if (!$stmt) {
		return false;
	}

	$stmt->bind_param("ii", $walletId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$wallet = $result ? $result->fetch_assoc() : null;
	$stmt->close();

	return $wallet ?: false;
}

function fetch_hutang_for_user($con, $hutangId, $userId)
{
	$stmt = $con->prepare("SELECT *
		FROM hutang
		WHERE id_hutang = ? AND user = ?
		LIMIT 1");
	if (!$stmt) {
		return null;
	}

	$stmt->bind_param("ii", $hutangId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result ? $result->fetch_assoc() : null;
	$stmt->close();

	return $row ?: null;
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

		if (function_exists('record_activity')) {
			record_activity($con, 'hutang', 'tambah', 'Menambahkan data utang.');
		}

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil ditambahkan.', 'success', 'main.php?module=hutang');
	}else{
		$existingHutang = fetch_hutang_for_user($con, $id, $user);
		if (!$existingHutang) {
			show_sweetalert_and_redirect('Gagal', 'Data utang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=hutang');
		}

		if (($existingHutang['status'] ?? '') === 'selesai'
			&& (int) ($existingHutang['id_pengeluaran'] ?? 0) > 0
			&& (float) ($existingHutang['jumlah'] ?? 0) !== (float) $jumlah) {
			show_sweetalert_and_redirect('Tidak dapat mengubah jumlah', 'Utang yang sudah lunas sudah tercatat sebagai pengeluaran wallet. Jumlah tidak diubah agar saldo tetap konsisten.', 'warning', 'main.php?module=hutang');
		}

		$stmt = $con->prepare("UPDATE hutang SET tanggal = ?, tanggal_jatuh_tempo = ?, kreditur = ?, catatan = ?, jumlah = ? WHERE id_hutang = ? AND user = ?");
		$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $kreditur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		if (function_exists('record_activity')) {
			record_activity($con, 'hutang', 'edit', "Mengubah data utang ID {$id}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil diubah.', 'success', 'main.php?module=hutang');
	}
}

if($act == 'l'){
	require_post_csrf_hutang();
	$id_hutang = (int) ($_POST['id_hutang'] ?? 0);
	$walletId = (int) ($_POST['id_wallet_pembayaran'] ?? 0);
	$tanggalLunas = normalize_required_date_hutang($_POST['tanggal_lunas'] ?? '', 'Tanggal lunas');

	if ($id_hutang <= 0 || $walletId <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data pelunasan utang tidak lengkap.', 'error', 'main.php?module=hutang');
	}

	$wallet = validate_active_wallet_for_user_hutang($con, $walletId, $user);
	if (!$wallet) {
		show_sweetalert_and_redirect('Wallet tidak valid', 'Wallet pembayaran tidak aktif atau bukan milik Anda.', 'error', 'main.php?module=hutang');
	}

	$hutang = fetch_hutang_for_user($con, $id_hutang, $user);
	if (!$hutang) {
		show_sweetalert_and_redirect('Gagal', 'Data utang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=hutang');
	}

	if (($hutang['status'] ?? '') === 'selesai') {
		if ((int) ($hutang['id_pengeluaran'] ?? 0) > 0) {
			show_sweetalert_and_redirect('Sudah lunas', 'Utang ini sudah lunas dan sudah tercatat ke wallet.', 'info', 'main.php?module=hutang');
		}

		show_sweetalert_and_redirect('Sudah selesai', 'Utang ini sudah berstatus selesai. Pelunasan ulang tidak dibuat.', 'warning', 'main.php?module=hutang');
	}

	$transactionStarted = false;
	try {
		mysqli_begin_transaction($con);
		$transactionStarted = true;

		$catatanUtang = trim((string) ($hutang['catatan'] ?? ''));
		$kreditur = trim((string) ($hutang['kreditur'] ?? '-'));
		$catatanPengeluaran = 'Pembayaran utang ke ' . $kreditur . ': ' . $catatanUtang;
		$jumlah = (float) ($hutang['jumlah'] ?? 0);
		$statusSelesai = 'selesai';
		$idKategori = null;

		$stmtInsert = $con->prepare("INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status, id_kategori, id_wallet)
			VALUES (?, ?, ?, ?, ?, ?, ?)");
		if (!$stmtInsert) {
			throw new Exception('Prepare insert pengeluaran gagal.');
		}
		$stmtInsert->bind_param("ssdisii", $tanggalLunas, $catatanPengeluaran, $jumlah, $user, $statusSelesai, $idKategori, $walletId);
		if (!$stmtInsert->execute()) {
			$stmtInsert->close();
			throw new Exception('Insert pengeluaran gagal.');
		}
		$idPengeluaran = (int) $stmtInsert->insert_id;
		$stmtInsert->close();

		$stmtUpdate = $con->prepare("UPDATE hutang
			SET status = 'selesai', tanggal_lunas = ?, id_wallet_pembayaran = ?, id_pengeluaran = ?
			WHERE id_hutang = ? AND user = ? AND status = 'pending'");
		if (!$stmtUpdate) {
			throw new Exception('Prepare update utang gagal.');
		}
		$stmtUpdate->bind_param("siiii", $tanggalLunas, $walletId, $idPengeluaran, $id_hutang, $user);
		if (!$stmtUpdate->execute()) {
			$stmtUpdate->close();
			throw new Exception('Update utang gagal.');
		}
		$affectedRows = $stmtUpdate->affected_rows;
		$stmtUpdate->close();

		if ($affectedRows <= 0) {
			throw new Exception('Utang sudah berubah status.');
		}

		mysqli_commit($con);
		$transactionStarted = false;

		if (function_exists('record_activity')) {
			record_activity($con, 'hutang', 'lunas', "Melunasi utang ID {$id_hutang} dari wallet ID {$walletId}; pengeluaran ID {$idPengeluaran}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Utang berhasil dilunasi dan pengeluaran wallet otomatis dibuat.', 'success', 'main.php?module=hutang');
	} catch (Throwable $e) {
		if ($transactionStarted) {
			mysqli_rollback($con);
		}

		show_sweetalert_and_redirect('Gagal', 'Pelunasan utang gagal diproses.', 'error', 'main.php?module=hutang');
	}
}

if($act == 'h'){
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);

	$hutang = fetch_hutang_for_user($con, $id, $user);
	if (!$hutang) {
		show_sweetalert_and_redirect('Gagal', 'Data utang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=hutang');
	}

	$idPengeluaran = (int) ($hutang['id_pengeluaran'] ?? 0);
	$transactionStarted = false;

	try {
		if ($idPengeluaran > 0) {
			mysqli_begin_transaction($con);
			$transactionStarted = true;

			$stmtDeletePengeluaran = $con->prepare("DELETE FROM pengeluaran WHERE id_pengeluaran = ? AND user = ?");
			if (!$stmtDeletePengeluaran) {
				throw new Exception('Prepare delete pengeluaran gagal.');
			}
			$stmtDeletePengeluaran->bind_param("ii", $idPengeluaran, $user);
			if (!$stmtDeletePengeluaran->execute()) {
				$stmtDeletePengeluaran->close();
				throw new Exception('Delete pengeluaran gagal.');
			}
			$stmtDeletePengeluaran->close();
		}

		$stmt = $con->prepare("DELETE FROM hutang WHERE id_hutang = ? AND user = ?");
		if (!$stmt) {
			throw new Exception('Prepare delete utang gagal.');
		}
		$stmt->bind_param("ii", $id, $user);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new Exception('Delete utang gagal.');
		}
		$affectedRows = $stmt->affected_rows;
		$stmt->close();

		if ($affectedRows <= 0) {
			throw new Exception('Data utang tidak terhapus.');
		}

		if ($transactionStarted) {
			mysqli_commit($con);
			$transactionStarted = false;
		}

		if (function_exists('record_activity')) {
			$description = $idPengeluaran > 0
				? "Menghapus utang ID {$id} beserta pengeluaran otomatis ID {$idPengeluaran}."
				: "Menghapus utang ID {$id}.";
			record_activity($con, 'hutang', 'hapus', $description);
		}

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil dihapus.', 'success', 'main.php?module=hutang');
	} catch (Throwable $e) {
		if ($transactionStarted) {
			mysqli_rollback($con);
		}

		show_sweetalert_and_redirect('Gagal', 'Data utang gagal dihapus.', 'error', 'main.php?module=hutang');
	}
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan utang tidak dikenali.', 'error', 'main.php?module=hutang');
?>
