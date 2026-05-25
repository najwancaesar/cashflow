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

function normalize_required_date_piutang($value, $fieldName)
{
	$value = trim((string) $value);
	if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', $fieldName . ' wajib diisi dengan format tanggal yang valid.', 'error', 'main.php?module=piutang');
	}

	[$year, $month, $day] = array_map('intval', explode('-', $value));
	if (!checkdate($month, $day, $year)) {
		show_sweetalert_and_redirect('Tanggal tidak valid', $fieldName . ' tidak valid.', 'error', 'main.php?module=piutang');
	}

	return $value;
}

function validate_active_wallet_for_user_piutang($con, $walletId, $userId)
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

function fetch_piutang_for_user($con, $piutangId, $userId)
{
	$stmt = $con->prepare("SELECT *
		FROM piutang
		WHERE id_piutang = ? AND user = ?
		LIMIT 1");
	if (!$stmt) {
		return null;
	}

	$stmt->bind_param("ii", $piutangId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$row = $result ? $result->fetch_assoc() : null;
	$stmt->close();

	return $row ?: null;
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

		if (function_exists('record_activity')) {
			record_activity($con, 'piutang', 'tambah', 'Menambahkan data piutang.');
		}

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil ditambahkan.', 'success', 'main.php?module=piutang');
	}else{
		$existingPiutang = fetch_piutang_for_user($con, $id, $user);
		if (!$existingPiutang) {
			show_sweetalert_and_redirect('Gagal', 'Data piutang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=piutang');
		}

		if (($existingPiutang['status'] ?? '') === 'selesai'
			&& (int) ($existingPiutang['id_pemasukan'] ?? 0) > 0
			&& (float) ($existingPiutang['jumlah'] ?? 0) !== (float) $jumlah) {
			show_sweetalert_and_redirect('Tidak dapat mengubah jumlah', 'Piutang yang sudah lunas sudah tercatat sebagai pemasukan wallet. Jumlah tidak diubah agar saldo tetap konsisten.', 'warning', 'main.php?module=piutang');
		}

		$stmt = $con->prepare("UPDATE piutang SET tanggal = ?, tanggal_jatuh_tempo = ?, debitur = ?, catatan = ?, jumlah = ? WHERE id_piutang = ? AND user = ?");
		$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $debitur, $catatan, $jumlah, $id, $user);
		$stmt->execute();
		$stmt->close();

		if (function_exists('record_activity')) {
			record_activity($con, 'piutang', 'edit', "Mengubah data piutang ID {$id}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil diubah.', 'success', 'main.php?module=piutang');
	}
}

if($act == 'l'){
	require_post_csrf_piutang();
	$id_piutang = (int) ($_POST['id_piutang'] ?? 0);
	$walletId = (int) ($_POST['id_wallet_penerimaan'] ?? 0);
	$tanggalLunas = normalize_required_date_piutang($_POST['tanggal_lunas'] ?? '', 'Tanggal lunas');

	if ($id_piutang <= 0 || $walletId <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data pelunasan piutang tidak lengkap.', 'error', 'main.php?module=piutang');
	}

	$wallet = validate_active_wallet_for_user_piutang($con, $walletId, $user);
	if (!$wallet) {
		show_sweetalert_and_redirect('Wallet tidak valid', 'Wallet penerimaan tidak aktif atau bukan milik Anda.', 'error', 'main.php?module=piutang');
	}

	$piutang = fetch_piutang_for_user($con, $id_piutang, $user);
	if (!$piutang) {
		show_sweetalert_and_redirect('Gagal', 'Data piutang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=piutang');
	}

	if (($piutang['status'] ?? '') === 'selesai') {
		if ((int) ($piutang['id_pemasukan'] ?? 0) > 0) {
			show_sweetalert_and_redirect('Sudah lunas', 'Piutang ini sudah lunas dan sudah tercatat ke wallet.', 'info', 'main.php?module=piutang');
		}

		show_sweetalert_and_redirect('Sudah selesai', 'Piutang ini sudah berstatus selesai. Pelunasan ulang tidak dibuat.', 'warning', 'main.php?module=piutang');
	}

	$transactionStarted = false;
	try {
		mysqli_begin_transaction($con);
		$transactionStarted = true;

		$catatanPiutang = trim((string) ($piutang['catatan'] ?? ''));
		$debitur = trim((string) ($piutang['debitur'] ?? '-'));
		$catatanPemasukan = 'Pelunasan piutang dari ' . $debitur . ': ' . $catatanPiutang;
		$jumlah = (float) ($piutang['jumlah'] ?? 0);
		$statusSelesai = 'selesai';
		$idKategori = null;

		$stmtInsert = $con->prepare("INSERT INTO pemasukan (tanggal, catatan, jumlah, user, status, id_kategori, id_wallet)
			VALUES (?, ?, ?, ?, ?, ?, ?)");
		if (!$stmtInsert) {
			throw new Exception('Prepare insert pemasukan gagal.');
		}
		$stmtInsert->bind_param("ssdisii", $tanggalLunas, $catatanPemasukan, $jumlah, $user, $statusSelesai, $idKategori, $walletId);
		if (!$stmtInsert->execute()) {
			$stmtInsert->close();
			throw new Exception('Insert pemasukan gagal.');
		}
		$idPemasukan = (int) $stmtInsert->insert_id;
		$stmtInsert->close();

		$stmtUpdate = $con->prepare("UPDATE piutang
			SET status = 'selesai', tanggal_lunas = ?, id_wallet_penerimaan = ?, id_pemasukan = ?
			WHERE id_piutang = ? AND user = ? AND status = 'pending'");
		if (!$stmtUpdate) {
			throw new Exception('Prepare update piutang gagal.');
		}
		$stmtUpdate->bind_param("siiii", $tanggalLunas, $walletId, $idPemasukan, $id_piutang, $user);
		if (!$stmtUpdate->execute()) {
			$stmtUpdate->close();
			throw new Exception('Update piutang gagal.');
		}
		$affectedRows = $stmtUpdate->affected_rows;
		$stmtUpdate->close();

		if ($affectedRows <= 0) {
			throw new Exception('Piutang sudah berubah status.');
		}

		mysqli_commit($con);
		$transactionStarted = false;

		if (function_exists('record_activity')) {
			record_activity($con, 'piutang', 'lunas', "Melunasi piutang ID {$id_piutang} ke wallet ID {$walletId}; pemasukan ID {$idPemasukan}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Piutang berhasil dilunasi dan pemasukan wallet otomatis dibuat.', 'success', 'main.php?module=piutang');
	} catch (Throwable $e) {
		if ($transactionStarted) {
			mysqli_rollback($con);
		}

		show_sweetalert_and_redirect('Gagal', 'Pelunasan piutang gagal diproses.', 'error', 'main.php?module=piutang');
	}
}

if($act == 'h'){
	require_post_csrf_piutang();
	$id = (int) ($_POST['id_piutang'] ?? 0);

	$piutang = fetch_piutang_for_user($con, $id, $user);
	if (!$piutang) {
		show_sweetalert_and_redirect('Gagal', 'Data piutang tidak ditemukan atau bukan milik Anda.', 'warning', 'main.php?module=piutang');
	}

	$idPemasukan = (int) ($piutang['id_pemasukan'] ?? 0);
	$transactionStarted = false;

	try {
		if ($idPemasukan > 0) {
			mysqli_begin_transaction($con);
			$transactionStarted = true;

			$stmtDeletePemasukan = $con->prepare("DELETE FROM pemasukan WHERE id_pemasukan = ? AND user = ?");
			if (!$stmtDeletePemasukan) {
				throw new Exception('Prepare delete pemasukan gagal.');
			}
			$stmtDeletePemasukan->bind_param("ii", $idPemasukan, $user);
			if (!$stmtDeletePemasukan->execute()) {
				$stmtDeletePemasukan->close();
				throw new Exception('Delete pemasukan gagal.');
			}
			$stmtDeletePemasukan->close();
		}

		$stmt = $con->prepare("DELETE FROM piutang WHERE id_piutang = ? AND user = ?");
		if (!$stmt) {
			throw new Exception('Prepare delete piutang gagal.');
		}
		$stmt->bind_param("ii", $id, $user);
		if (!$stmt->execute()) {
			$stmt->close();
			throw new Exception('Delete piutang gagal.');
		}
		$affectedRows = $stmt->affected_rows;
		$stmt->close();

		if ($affectedRows <= 0) {
			throw new Exception('Data piutang tidak terhapus.');
		}

		if ($transactionStarted) {
			mysqli_commit($con);
			$transactionStarted = false;
		}

		if (function_exists('record_activity')) {
			$description = $idPemasukan > 0
				? "Menghapus piutang ID {$id} beserta pemasukan otomatis ID {$idPemasukan}."
				: "Menghapus piutang ID {$id}.";
			record_activity($con, 'piutang', 'hapus', $description);
		}

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil dihapus.', 'success', 'main.php?module=piutang');
	} catch (Throwable $e) {
		if ($transactionStarted) {
			mysqli_rollback($con);
		}

		show_sweetalert_and_redirect('Gagal', 'Data piutang gagal dihapus.', 'error', 'main.php?module=piutang');
	}
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan piutang tidak dikenali.', 'error', 'main.php?module=piutang');
?>
