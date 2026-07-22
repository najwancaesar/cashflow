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

function fetch_hutang_for_user($con, $hutangId, $userId, $forUpdate = false)
{
    $lockClause = $forUpdate ? ' FOR UPDATE' : '';
	$stmt = $con->prepare("SELECT *
		FROM hutang
		WHERE id_hutang = ? AND user = ?
		LIMIT 1{$lockClause}");
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

function rollback_hutang_transaction($con)
{
	try {
		$con->rollback();
	} catch (Throwable $rollbackError) {
		error_log('Rollback utang gagal: ' . $rollbackError->getMessage());
	}
}

function handle_hutang_transaction_failure($con, Throwable $error, $fallbackMessage)
{
	rollback_hutang_transaction($con);

	if ($error instanceof DomainException) {
		show_sweetalert_and_redirect('Gagal', $error->getMessage(), 'error', 'main.php?module=hutang');
	}

	error_log('Proses utang gagal: ' . $error->getMessage());
	show_sweetalert_and_redirect('Gagal', $fallbackMessage, 'error', 'main.php?module=hutang');
}

/**
 * Ambil id_kategori untuk kategori bernama $namaKategori milik user.
 * Jika belum ada, buat otomatis lalu kembalikan ID-nya.
 */
function get_or_create_kategori_hutang($con, $userId, $namaKategori, $tipeKategori)
{
    $stmt = $con->prepare("SELECT id_kategori FROM kategori WHERE user_id = ? AND nama_kategori = ? AND tipe_kategori = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("iss", $userId, $namaKategori, $tipeKategori);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        return (int) $row['id_kategori'];
    }

    // Belum ada — buat otomatis
    $ins = $con->prepare("INSERT INTO kategori (user_id, nama_kategori, tipe_kategori) VALUES (?, ?, ?)");
    if (!$ins) {
        return null;
    }
    $ins->bind_param("iss", $userId, $namaKategori, $tipeKategori);
    $ins->execute();
    $newId = (int) $con->insert_id;
    $ins->close();
    return $newId ?: null;
}

if($act == 't'){
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);
	$tanggal = normalize_required_date_hutang($_POST['tanggal'] ?? '', 'Tanggal transaksi');
	$tanggalJatuhTempo = normalize_optional_due_date_hutang($_POST['tanggal_jatuh_tempo'] ?? '');
	$catatan = trim((string) ($_POST['catatan'] ?? ''));
	$kreditur = trim((string) ($_POST['kreditur'] ?? ''));
	$jumlahRaw = (string) ($_POST['jumlah'] ?? '');
	$jumlah = nominal_input_to_number($jumlahRaw);
	$status = 'pending';

	if ($kreditur === '') {
		show_sweetalert_and_redirect('Gagal', 'Kreditur wajib diisi.', 'error', 'main.php?module=hutang');
	}

	if (mb_strlen($kreditur, 'UTF-8') > 100) {
		show_sweetalert_and_redirect('Gagal', 'Kreditur maksimal 100 karakter.', 'error', 'main.php?module=hutang');
	}

	if (mb_strlen($catatan, 'UTF-8') > 1000) {
		show_sweetalert_and_redirect('Gagal', 'Catatan maksimal 1000 karakter.', 'error', 'main.php?module=hutang');
	}

	if ($jumlah <= 0 || strpos($jumlahRaw, '-') !== false || !preg_match('/^[\d\s.,]+$/', $jumlahRaw)) {
		show_sweetalert_and_redirect('Gagal', 'Nominal utang wajib lebih besar dari nol.', 'error', 'main.php?module=hutang');
	}

	if ($tanggalJatuhTempo !== null && $tanggalJatuhTempo < $tanggal) {
		show_sweetalert_and_redirect('Gagal', 'Tanggal jatuh tempo tidak boleh lebih awal dari tanggal transaksi.', 'error', 'main.php?module=hutang');
	}

	try {
		$con->begin_transaction();

		if ($id <= 0) {
			$stmt = $con->prepare("INSERT INTO hutang(tanggal, tanggal_jatuh_tempo, catatan, kreditur, jumlah, user, status) VALUES(?, ?, ?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new RuntimeException('Prepare tambah utang gagal.');
			}
			$stmt->bind_param("ssssdis", $tanggal, $tanggalJatuhTempo, $catatan, $kreditur, $jumlah, $user, $status);
			$stmt->execute();
			$id = (int) $stmt->insert_id;
			$stmt->close();
			$activityAction = 'tambah';
			$activityDescription = "Menambahkan data utang ID {$id}.";
			$successMessage = 'Data utang berhasil ditambahkan.';
		} else {
			$existingHutang = fetch_hutang_for_user($con, $id, $user, true);
			if (!$existingHutang) {
				throw new DomainException('Data utang tidak ditemukan atau bukan milik Anda.');
			}
			if (($existingHutang['status'] ?? '') === 'selesai' && (int) ($existingHutang['id_pengeluaran'] ?? 0) > 0) {
				$linkedFieldsChanged = (float) ($existingHutang['jumlah'] ?? 0) !== (float) $jumlah
					|| trim((string) ($existingHutang['kreditur'] ?? '')) !== $kreditur
					|| trim((string) ($existingHutang['catatan'] ?? '')) !== $catatan;

				if ($linkedFieldsChanged) {
					throw new DomainException('Nominal, kreditur, dan catatan utang lunas tidak dapat diubah karena sudah tercatat sebagai pengeluaran wallet.');
				}
			}

			$stmt = $con->prepare("UPDATE hutang SET tanggal = ?, tanggal_jatuh_tempo = ?, kreditur = ?, catatan = ?, jumlah = ? WHERE id_hutang = ? AND user = ?");
			if (!$stmt) {
				throw new RuntimeException('Prepare edit utang gagal.');
			}
			$stmt->bind_param("ssssdii", $tanggal, $tanggalJatuhTempo, $kreditur, $catatan, $jumlah, $id, $user);
			$stmt->execute();
			$stmt->close();
			$activityAction = 'edit';
			$activityDescription = "Mengubah data utang ID {$id}.";
			$successMessage = 'Data utang berhasil diubah.';
		}

		$con->commit();
	} catch (Throwable $error) {
		handle_hutang_transaction_failure($con, $error, 'Data utang gagal disimpan.');
	}

	if (function_exists('record_activity')) {
		record_activity($con, 'hutang', $activityAction, $activityDescription);
	}

	show_sweetalert_and_redirect('Berhasil', $successMessage, 'success', 'main.php?module=hutang');
}

if($act == 'l'){
	require_post_csrf_hutang();
	$id_hutang = (int) ($_POST['id_hutang'] ?? 0);
	$walletId = (int) ($_POST['id_wallet_pembayaran'] ?? 0);
	$tanggalLunas = normalize_required_date_hutang($_POST['tanggal_lunas'] ?? '', 'Tanggal lunas');

	if ($id_hutang <= 0 || $walletId <= 0) {
		show_sweetalert_and_redirect('Gagal', 'Data pelunasan utang tidak lengkap.', 'error', 'main.php?module=hutang');
	}

	try {
		$con->begin_transaction();
		cashflow_lock_owned_wallets($con, $user, [$walletId], [$walletId]);

		$hutang = fetch_hutang_for_user($con, $id_hutang, $user, true);
		if (!$hutang) {
			throw new DomainException('Data utang tidak ditemukan atau bukan milik Anda.');
		}
		if (($hutang['status'] ?? '') === 'selesai' || (int) ($hutang['id_pengeluaran'] ?? 0) > 0) {
			$con->commit();
			show_sweetalert_and_redirect('Sudah lunas', 'Utang ini sudah lunas dan sudah tercatat ke wallet.', 'info', 'main.php?module=hutang');
		}

		$catatanUtang = trim((string) ($hutang['catatan'] ?? ''));
		$kreditur = trim((string) ($hutang['kreditur'] ?? '-'));
		$catatanPengeluaran = 'Pembayaran utang ke ' . $kreditur . ': ' . $catatanUtang;
		$jumlah = (float) ($hutang['jumlah'] ?? 0);
		if ($jumlah <= 0) {
			throw new DomainException('Nominal utang tidak valid dan tidak dapat dilunasi.');
		}

		$saldoTersedia = cashflow_calculate_wallet_balance($con, $user, $walletId);
		if ($jumlah > $saldoTersedia + 0.00001) {
			throw new DomainException('Saldo wallet tidak mencukupi untuk melunasi utang ini.');
		}

		$statusSelesai = 'selesai';

		// Ambil/buat kategori "Utang" untuk user ini
		$idKategori = get_or_create_kategori_hutang($con, $user, 'Utang', 'pengeluaran');

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
			WHERE id_hutang = ? AND user = ? AND status = 'pending' AND id_pengeluaran IS NULL");
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

		$con->commit();

		if (function_exists('record_activity')) {
			record_activity($con, 'hutang', 'lunas', "Melunasi utang ID {$id_hutang} dari wallet ID {$walletId}; pengeluaran ID {$idPengeluaran}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Utang berhasil dilunasi dan pengeluaran wallet otomatis dibuat.', 'success', 'main.php?module=hutang');
	} catch (Throwable $e) {
		handle_hutang_transaction_failure($con, $e, 'Pelunasan utang gagal diproses.');
	}
}

// Batalkan pelunasan: Selesai → Pending (hapus pengeluaran linked, kembalikan status)
if ($act == 'revert_status') {
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);

	if ($id <= 0) {
		show_sweetalert_and_redirect('Gagal', 'ID utang tidak valid.', 'error', 'main.php?module=hutang');
	}

	try {
		$con->begin_transaction();
		$hutang = fetch_hutang_for_user($con, $id, $user, true);
		if (!$hutang) {
			throw new DomainException('Data utang tidak ditemukan atau bukan milik Anda.');
		}
		if (($hutang['status'] ?? '') !== 'selesai') {
			throw new DomainException('Hanya utang yang sudah lunas yang dapat dibatalkan pelunasannya.');
		}

		$idPengeluaran = (int) ($hutang['id_pengeluaran'] ?? 0);
		$walletId      = (int) ($hutang['id_wallet_pembayaran'] ?? 0);

		if ($idPengeluaran <= 0) {
			throw new DomainException('Utang ini tidak memiliki pengeluaran terkait — tidak bisa dibatalkan via alur ini.');
		}

		// Kunci wallet terdampak
		if ($walletId > 0) {
			cashflow_lock_owned_wallets($con, $user, [$walletId]);
		}

		// Menghapus pengeluaran selalu menambah saldo (tidak ada risiko negatif)
		cashflow_ensure_removing_pengeluaran_wont_worsen_balance($con, $user, $walletId, $idPengeluaran);

		// NULL-kan dulu referensi FK di hutang (ON DELETE RESTRICT mencegah hapus langsung)
		$nullStmt = $con->prepare("UPDATE hutang SET status = 'pending', tanggal_lunas = NULL, id_wallet_pembayaran = NULL, id_pengeluaran = NULL WHERE id_hutang = ? AND user = ?");
		if (!$nullStmt) {
			throw new RuntimeException('Gagal menyiapkan pembaruan utang.');
		}
		$nullStmt->bind_param("ii", $id, $user);
		if (!$nullStmt->execute() || $nullStmt->affected_rows <= 0) {
			$nullStmt->close();
			throw new RuntimeException('Pembaruan status utang gagal.');
		}
		$nullStmt->close();

		// Sekarang aman hapus pengeluaran
		$delStmt = $con->prepare("DELETE FROM pengeluaran WHERE id_pengeluaran = ? AND user = ?");
		if (!$delStmt) {
			throw new RuntimeException('Gagal menyiapkan penghapusan pengeluaran linked.');
		}
		$delStmt->bind_param("ii", $idPengeluaran, $user);
		if (!$delStmt->execute() || $delStmt->affected_rows <= 0) {
			$delStmt->close();
			throw new RuntimeException('Pengeluaran linked gagal dihapus.');
		}
		$delStmt->close();

		$con->commit();

		if (function_exists('record_activity')) {
			record_activity($con, 'hutang', 'revert_status', "Membatalkan pelunasan utang ID {$id}; pengeluaran ID {$idPengeluaran} dihapus.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Pelunasan utang berhasil dibatalkan. Status dikembalikan ke Pending.', 'success', 'main.php?module=hutang');
	} catch (Throwable $e) {
		handle_hutang_transaction_failure($con, $e, 'Pembatalan pelunasan utang gagal diproses.');
	}
}

if($act == 'h'){
	require_post_csrf_hutang();
	$id = (int) ($_POST['id_hutang'] ?? 0);

	if ($id <= 0) {
		show_sweetalert_and_redirect('Gagal', 'ID utang tidak valid.', 'error', 'main.php?module=hutang');
	}

	try {
		$con->begin_transaction();
		$hutang = fetch_hutang_for_user($con, $id, $user, true);
		if (!$hutang) {
			throw new DomainException('Data utang tidak ditemukan atau bukan milik Anda.');
		}
		$idPengeluaran = (int) ($hutang['id_pengeluaran'] ?? 0);
		$walletId      = (int) ($hutang['id_wallet_pembayaran'] ?? 0);
		$isLunas       = ($hutang['status'] ?? '') === 'selesai' && $idPengeluaran > 0;

		if ($isLunas) {
			// Hutang lunas: kunci wallet, hapus pengeluaran linked
			if ($walletId > 0) {
				cashflow_lock_owned_wallets($con, $user, [$walletId]);
			}
			cashflow_ensure_removing_pengeluaran_wont_worsen_balance($con, $user, $walletId, $idPengeluaran);

			// NULL-kan referensi FK dulu (ON DELETE RESTRICT)
			$nullHutang = $con->prepare("UPDATE hutang SET id_pengeluaran = NULL, id_wallet_pembayaran = NULL WHERE id_hutang = ? AND user = ?");
			if (!$nullHutang) {
				throw new RuntimeException('Gagal menyiapkan pembaruan referensi hutang.');
			}
			$nullHutang->bind_param("ii", $id, $user);
			$nullHutang->execute();
			$nullHutang->close();

			// Sekarang aman hapus pengeluaran
			$delPengeluaran = $con->prepare("DELETE FROM pengeluaran WHERE id_pengeluaran = ? AND user = ?");
			if (!$delPengeluaran) {
				throw new RuntimeException('Gagal menyiapkan penghapusan pengeluaran linked.');
			}
			$delPengeluaran->bind_param("ii", $idPengeluaran, $user);
			if (!$delPengeluaran->execute() || $delPengeluaran->affected_rows <= 0) {
				$delPengeluaran->close();
				throw new RuntimeException('Pengeluaran linked gagal dihapus.');
			}
			$delPengeluaran->close();
		}

		// Hapus hutang
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

		$con->commit();

		if (function_exists('record_activity')) {
			$logStatus = $isLunas ? 'lunas (beserta pengeluaran linked)' : 'pending';
			record_activity($con, 'hutang', 'hapus', "Menghapus utang {$logStatus} ID {$id}.");
		}

		show_sweetalert_and_redirect('Berhasil', 'Data utang berhasil dihapus.', 'success', 'main.php?module=hutang');
	} catch (Throwable $e) {
		handle_hutang_transaction_failure($con, $e, 'Data utang gagal dihapus.');
	}
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan utang tidak dikenali.', 'error', 'main.php?module=hutang');
?>
