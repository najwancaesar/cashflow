<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";
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

function fetch_piutang_for_user($con, $piutangId, $userId, $forUpdate = false)
{
	$lockClause = $forUpdate ? ' FOR UPDATE' : '';
	$stmt = $con->prepare("SELECT *
		FROM piutang
		WHERE id_piutang = ? AND user = ?
		LIMIT 1{$lockClause}");
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

function rollback_piutang_transaction($con)
{
	try {
		$con->rollback();
	} catch (Throwable $rollbackError) {
		error_log('Rollback piutang gagal: ' . $rollbackError->getMessage());
	}
}

function handle_piutang_transaction_failure($con, Throwable $error, $fallbackMessage)
{
	rollback_piutang_transaction($con);

	if ($error instanceof DomainException) {
		show_sweetalert_and_redirect('Gagal', $error->getMessage(), 'error', 'main.php?module=piutang');
	}

	error_log('Proses piutang gagal: ' . $error->getMessage());
	show_sweetalert_and_redirect('Gagal', $fallbackMessage, 'error', 'main.php?module=piutang');
}

if($act == 't'){
	require_post_csrf_piutang();
	$id = (int) ($_POST['id_piutang'] ?? 0);
	$tanggal = normalize_required_date_piutang($_POST['tanggal'] ?? '', 'Tanggal transaksi');
	$tanggalJatuhTempo = normalize_optional_due_date_piutang($_POST['tanggal_jatuh_tempo'] ?? '');
	$catatan = trim((string) ($_POST['catatan'] ?? ''));
	$debitur = trim((string) ($_POST['debitur'] ?? ''));
	$jumlahRaw = (string) ($_POST['jumlah'] ?? '');
	$jumlah = nominal_input_to_number($jumlahRaw);
	$status = 'pending';

	if ($debitur === '') {
		show_sweetalert_and_redirect('Gagal', 'Debitur wajib diisi.', 'error', 'main.php?module=piutang');
	}

	if (mb_strlen($debitur, 'UTF-8') > 100) {
		show_sweetalert_and_redirect('Gagal', 'Debitur maksimal 100 karakter.', 'error', 'main.php?module=piutang');
	}

	if (mb_strlen($catatan, 'UTF-8') > 1000) {
		show_sweetalert_and_redirect('Gagal', 'Catatan maksimal 1000 karakter.', 'error', 'main.php?module=piutang');
	}

	if ($jumlah <= 0 || strpos($jumlahRaw, '-') !== false || !preg_match('/^[\d\s.,]+$/', $jumlahRaw)) {
		show_sweetalert_and_redirect('Gagal', 'Nominal piutang wajib lebih besar dari nol.', 'error', 'main.php?module=piutang');
	}

	if ($tanggalJatuhTempo !== null && $tanggalJatuhTempo < $tanggal) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal jatuh tempo tidak boleh lebih awal dari tanggal transaksi.', 'error', 'main.php?module=piutang');
	}

	try {
		$con->begin_transaction();

		if ($id <= 0) {
			$stmt = $con->prepare("INSERT INTO piutang(tanggal, tanggal_jatuh_tempo, catatan, debitur, jumlah, user, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new RuntimeException('Prepare tambah piutang gagal.');
			}
			$stmt->bind_param("ssssdis", $tanggal, $tanggalJatuhTempo, $catatan, $debitur, $jumlah, $user, $status);
			if (!$stmt->execute()) {
				$stmt->close();
				throw new RuntimeException('Tambah piutang gagal.');
			}
			$id = (int) $stmt->insert_id;
			$stmt->close();
			$activityAction = 'tambah';
			$activityDescription = "Menambahkan data piutang ID {$id}.";
			$successMessage = 'Data piutang berhasil ditambahkan.';
		} else {
			$existingPiutang = fetch_piutang_for_user($con, $id, $user, true);
			if (!$existingPiutang) {
				throw new DomainException('Data piutang tidak ditemukan atau bukan milik Anda.');
			}
			if (($existingPiutang['status'] ?? '') === 'selesai' && (int) ($existingPiutang['id_pemasukan'] ?? 0) > 0) {
				$linkedFieldsChanged = (float) ($existingPiutang['jumlah'] ?? 0) !== (float) $jumlah
					|| trim((string) ($existingPiutang['debitur'] ?? '')) !== $debitur
					|| trim((string) ($existingPiutang['catatan'] ?? '')) !== $catatan;

				if ($linkedFieldsChanged) {
					throw new DomainException('Nominal, debitur, dan catatan piutang lunas tidak dapat diubah karena sudah tercatat sebagai pemasukan wallet.');
				}
			}

			$stmt = $con->prepare("UPDATE piutang SET tanggal = ?, tanggal_jatuh_tempo = ?, debitur = ?, catatan = ?, jumlah = ? WHERE id_piutang = ? AND user = ?");
			if (!$stmt) {
				throw new RuntimeException('Prepare edit piutang gagal.');
			}
			$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $debitur, $catatan, $jumlah, $id, $user);
			if (!$stmt->execute()) {
				$stmt->close();
				throw new RuntimeException('Edit piutang gagal.');
			}
			$stmt->close();
			$activityAction = 'edit';
			$activityDescription = "Mengubah data piutang ID {$id}.";
			$successMessage = 'Data piutang berhasil diubah.';
		}

		$con->commit();
	} catch (Throwable $error) {
		handle_piutang_transaction_failure($con, $error, 'Data piutang gagal disimpan.');
	}

	if (function_exists('record_activity')) {
		record_activity($con, 'piutang', $activityAction, $activityDescription);
	}

	show_sweetalert_and_redirect('Berhasil', $successMessage, 'success', 'main.php?module=piutang');
}

if($act == 'l'){
	require_post_csrf_piutang();
	$id_piutang = (int) ($_POST['id_piutang'] ?? 0);
	$walletId = (int) ($_POST['id_wallet_penerimaan'] ?? 0);
	$tanggalLunas = normalize_required_date_piutang($_POST['tanggal_lunas'] ?? '', 'Tanggal lunas');

	if ($id_piutang <= 0 || $walletId <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data pelunasan piutang tidak lengkap.', 'error', 'main.php?module=piutang');
	}

	try {
		$con->begin_transaction();
		try {
			cashflow_lock_owned_wallets($con, $user, [$walletId], [$walletId]);
		} catch (DomainException $walletError) {
			throw new DomainException('Wallet penerimaan tidak aktif atau bukan milik Anda.');
		}

		$piutang = fetch_piutang_for_user($con, $id_piutang, $user, true);
		if (!$piutang) {
			throw new DomainException('Data piutang tidak ditemukan atau bukan milik Anda.');
		}
		if (($piutang['status'] ?? '') === 'selesai' || (int) ($piutang['id_pemasukan'] ?? 0) > 0) {
			$con->commit();
			show_sweetalert_and_redirect('Sudah lunas', 'Piutang ini sudah lunas dan sudah tercatat ke wallet.', 'info', 'main.php?module=piutang');
		}

		$catatanPiutang = trim((string) ($piutang['catatan'] ?? ''));
		$debitur = trim((string) ($piutang['debitur'] ?? '-'));
		$catatanPemasukan = 'Pelunasan piutang dari ' . $debitur . ': ' . $catatanPiutang;
		$jumlah = (float) ($piutang['jumlah'] ?? 0);
		if ($jumlah <= 0) {
			throw new DomainException('Nominal piutang tidak valid dan tidak dapat dilunasi.');
		}

		$saldoSebelum = cashflow_calculate_wallet_balance($con, $user, $walletId);
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
			WHERE id_piutang = ? AND user = ? AND status = 'pending' AND id_pemasukan IS NULL");
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

		$saldoSesudah = cashflow_calculate_wallet_balance($con, $user, $walletId);
		if (abs($saldoSesudah - ($saldoSebelum + $jumlah)) > 0.00001) {
			throw new RuntimeException('Perubahan saldo wallet tidak konsisten.');
		}

		$con->commit();

		if (function_exists('record_activity')) {
			record_activity($con, 'piutang', 'lunas', "Melunasi piutang ID {$id_piutang} ke wallet ID {$walletId}; pemasukan ID {$idPemasukan}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Piutang berhasil dilunasi dan pemasukan wallet otomatis dibuat.', 'success', 'main.php?module=piutang');
	} catch (Throwable $e) {
		handle_piutang_transaction_failure($con, $e, 'Pelunasan piutang gagal diproses.');
	}
}

if($act == 'h'){
	require_post_csrf_piutang();
	$id = (int) ($_POST['id_piutang'] ?? 0);

	if ($id <= 0) {
		show_sweetalert_and_redirect('Gagal', 'ID piutang tidak valid.', 'error', 'main.php?module=piutang');
	}

	try {
		$con->begin_transaction();
		$piutang = fetch_piutang_for_user($con, $id, $user, true);
		if (!$piutang) {
			throw new DomainException('Data piutang tidak ditemukan atau bukan milik Anda.');
		}
		$idPemasukan = (int) ($piutang['id_pemasukan'] ?? 0);
		if ($idPemasukan > 0) {
			throw new DomainException('Piutang memiliki pemasukan linked pelunasan tidak dapat dihapus permanen.');
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

		$con->commit();

		if (function_exists('record_activity')) {
			record_activity($con, 'piutang', 'hapus', "Menghapus piutang pending ID {$id}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Data piutang berhasil dihapus.', 'success', 'main.php?module=piutang');
	} catch (Throwable $e) {
		handle_piutang_transaction_failure($con, $e, 'Data piutang gagal dihapus.');
	}
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan piutang tidak dikenali.', 'error', 'main.php?module=piutang');
?>
