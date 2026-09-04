<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";

function require_transfer_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi transfer wallet wajib melalui form yang valid.', 'warning', 'main.php?module=transfer_wallet');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=transfer_wallet');
    }
}

function clean_transfer_text($value)
{
    return trim((string) $value);
}

function validate_transfer_date($value)
{
    $value = clean_transfer_text($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return false;
    }

    return $date->format('Y-m-d') === $value ? $value : false;
}

function is_valid_transfer_status($status)
{
    return in_array($status, ['pending', 'selesai', 'batal'], true);
}

function fetch_active_wallet_for_transfer($con, $walletId, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet
                           FROM wallet
                           WHERE id_wallet = ? AND user_id = ? AND is_active = 1
                           LIMIT 1");
    $stmt->bind_param("ii", $walletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $wallet ?: null;
}

function transfer_dimiliki_user($con, $transferId, $userId)
{
    $stmt = $con->prepare("SELECT id_transfer
                           FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $transferId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $transfer !== null;
}

function fetch_transfer_status_for_user($con, $transferId, $userId)
{
    $stmt = $con->prepare("SELECT status
                           FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $transferId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $transfer['status'] ?? null;
}

function fetch_transfer_for_user($con, $transferId, $userId, $forUpdate = false)
{
    $lockClause = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $con->prepare("SELECT id_transfer, user_id, wallet_asal_id, wallet_tujuan_id, jumlah, status
                           FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ?
                           LIMIT 1{$lockClause}");
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan data transfer.');
    }
    $stmt->bind_param('ii', $transferId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function count_active_wallets_for_transfer($con, $userId)
{
    $stmt = $con->prepare("SELECT COUNT(*)
                           FROM wallet
                           WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transfer wallet user.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    require_transfer_post_csrf();

    $transferId = isset($_POST['id_transfer']) && $_POST['id_transfer'] !== ''
        ? (int) $_POST['id_transfer']
        : null;
    $tanggal = validate_transfer_date($_POST['tanggal'] ?? '');
    $walletAsalId = (int) ($_POST['wallet_asal_id'] ?? 0);
    $walletTujuanId = (int) ($_POST['wallet_tujuan_id'] ?? 0);
    $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
    $status = clean_transfer_text($_POST['status'] ?? 'selesai');
    $catatan = clean_transfer_text($_POST['catatan'] ?? '');

    if ($tanggal === false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal transfer wajib valid dengan format YYYY-MM-DD.', 'error', 'main.php?module=transfer_wallet');
    }

    if (count_active_wallets_for_transfer($con, $userId) < 2) {
        show_sweetalert_and_redirect('Transfer belum bisa dilakukan', 'Transfer wallet membutuhkan minimal 2 wallet aktif.', 'warning', 'main.php?module=transfer_wallet');
    }

    if ($walletAsalId <= 0 || $walletTujuanId <= 0) {
        show_sweetalert_and_redirect('Data belum lengkap', 'Wallet asal dan wallet tujuan wajib dipilih.', 'warning', 'main.php?module=transfer_wallet');
    }

    if ($walletAsalId === $walletTujuanId) {
        show_sweetalert_and_redirect('Data tidak valid', 'Wallet asal dan wallet tujuan tidak boleh sama.', 'warning', 'main.php?module=transfer_wallet');
    }

    if (!fetch_active_wallet_for_transfer($con, $walletAsalId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet asal tidak valid, tidak aktif, atau bukan milik Anda.', 'error', 'main.php?module=transfer_wallet');
    }

    if (!fetch_active_wallet_for_transfer($con, $walletTujuanId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet tujuan tidak valid, tidak aktif, atau bukan milik Anda.', 'error', 'main.php?module=transfer_wallet');
    }

    if (strpos($jumlahRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transfer harus lebih dari 0.', 'error', 'main.php?module=transfer_wallet');
    }

    $jumlah = nominal_input_to_number($jumlahRaw);
    if ($jumlah <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transfer harus lebih dari 0.', 'error', 'main.php?module=transfer_wallet');
    }

    if (!is_valid_transfer_status($status)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Status transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    try {
        $con->begin_transaction();

        $existingTransfer = null;
        $walletIdsToLock = [$walletAsalId, $walletTujuanId];
        if ($transferId !== null) {
            if ($transferId <= 0) {
                throw new DomainException('ID transfer tidak valid.');
            }
            $existingTransfer = fetch_transfer_for_user($con, $transferId, $userId, true);
            if (!$existingTransfer) {
                throw new DomainException('Transfer yang ingin diubah tidak ditemukan atau bukan milik Anda.');
            }
            $walletIdsToLock[] = (int) $existingTransfer['wallet_asal_id'];
            $walletIdsToLock[] = (int) $existingTransfer['wallet_tujuan_id'];
        }

        cashflow_lock_owned_wallets($con, $userId, $walletIdsToLock, [$walletAsalId, $walletTujuanId]);

        $excludeTransferId = $existingTransfer ? (int) $existingTransfer['id_transfer'] : null;
        $affectedWalletIds = array_values(array_unique(array_map('intval', $walletIdsToLock)));
        $baseBalances = [];
        $currentBalances = [];
        foreach ($affectedWalletIds as $affectedWalletId) {
            $baseBalances[$affectedWalletId] = cashflow_calculate_wallet_balance(
                $con,
                $userId,
                $affectedWalletId,
                null,
                $excludeTransferId
            );
            $currentBalances[$affectedWalletId] = cashflow_calculate_wallet_balance($con, $userId, $affectedWalletId);
        }

        $proposedBalances = $baseBalances;
        if ($status === 'selesai') {
            $proposedBalances[$walletAsalId] -= $jumlah;
            $proposedBalances[$walletTujuanId] += $jumlah;
            if ($proposedBalances[$walletAsalId] < -0.00001) {
                throw new DomainException('Saldo wallet asal tidak mencukupi untuk transfer ini.');
            }
        }

        foreach ($proposedBalances as $affectedWalletId => $proposedBalance) {
            if ($proposedBalance < -0.00001 && $proposedBalance < $currentBalances[$affectedWalletId] - 0.00001) {
                throw new DomainException('Perubahan transfer akan membuat saldo wallet terkait semakin negatif.');
            }
        }

        if ($existingTransfer === null) {
            $stmt = $con->prepare("INSERT INTO transfer_wallet (user_id, wallet_asal_id, wallet_tujuan_id, tanggal, jumlah, catatan, status, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                throw new RuntimeException('Gagal menyiapkan transfer baru.');
            }
            $stmt->bind_param("iiisdss", $userId, $walletAsalId, $walletTujuanId, $tanggal, $jumlah, $catatan, $status);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Transfer wallet gagal ditambahkan.');
            }
            $savedTransferId = (int) $stmt->insert_id;
            $stmt->close();
            $activityAction = 'tambah';
            $activityText = "Menambahkan transfer wallet ID {$savedTransferId}.";
            $successMessage = 'Transfer wallet berhasil ditambahkan.';
        } else {
            $stmt = $con->prepare("UPDATE transfer_wallet
                                   SET wallet_asal_id = ?, wallet_tujuan_id = ?, tanggal = ?, jumlah = ?, catatan = ?, status = ?, updated_at = NOW()
                                   WHERE id_transfer = ? AND user_id = ?");
            if (!$stmt) {
                throw new RuntimeException('Gagal menyiapkan perubahan transfer.');
            }
            $stmt->bind_param("iisdssii", $walletAsalId, $walletTujuanId, $tanggal, $jumlah, $catatan, $status, $transferId, $userId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Transfer wallet gagal diperbarui.');
            }
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            if ($affectedRows === 0) {
                $con->commit();
                show_sweetalert_and_redirect('Tidak ada perubahan', 'Data transfer tidak berubah.', 'info', 'main.php?module=transfer_wallet');
            }
            $savedTransferId = (int) $transferId;
            $activityAction = 'edit';
            $activityText = "Mengubah transfer wallet ID {$savedTransferId}.";
            $successMessage = 'Transfer wallet berhasil diperbarui.';
        }

        $con->commit();
        record_activity($con, 'transfer_wallet', $activityAction, $activityText);
        show_sweetalert_and_redirect('Berhasil', $successMessage, 'success', 'main.php?module=transfer_wallet');
    } catch (DomainException $error) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=transfer_wallet');
    } catch (Throwable $error) {
        $con->rollback();
        error_log('Transfer wallet gagal: ' . $error->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal diproses.', 'error', 'main.php?module=transfer_wallet');
    }
}

if ($act === 'h') {
    require_transfer_post_csrf();

    $transferId = (int) ($_POST['id_transfer'] ?? 0);
    if ($transferId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    try {
        $con->begin_transaction();
        $transfer = fetch_transfer_for_user($con, $transferId, $userId, true);
        if (!$transfer) {
            throw new DomainException('Transfer yang ingin dibatalkan tidak ditemukan atau bukan milik Anda.');
        }
        if ((string) $transfer['status'] === 'batal') {
            $con->commit();
            show_sweetalert_and_redirect('Tidak ada perubahan', 'Transfer wallet sudah berstatus batal.', 'info', 'main.php?module=transfer_wallet');
        }

        $walletAsalId = (int) $transfer['wallet_asal_id'];
        $walletTujuanId = (int) $transfer['wallet_tujuan_id'];
        cashflow_lock_owned_wallets($con, $userId, [$walletAsalId, $walletTujuanId]);

        if ((string) $transfer['status'] === 'selesai') {
            $saldoTujuanTanpaTransfer = cashflow_calculate_wallet_balance($con, $userId, $walletTujuanId, null, $transferId);
            if ($saldoTujuanTanpaTransfer < -0.00001) {
                throw new DomainException('Transfer selesai tidak dapat dibatalkan karena akan membuat saldo wallet tujuan negatif.');
            }
        }

        $stmt = $con->prepare("UPDATE transfer_wallet
                               SET status = 'batal', updated_at = NOW()
                               WHERE id_transfer = ? AND user_id = ? AND status <> 'batal'");
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan pembatalan transfer.');
        }
        $stmt->bind_param('ii', $transferId, $userId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        if ($affectedRows !== 1) {
            throw new RuntimeException('Status transfer gagal diperbarui.');
        }

        $con->commit();
        record_activity($con, 'transfer_wallet', 'batal', "Membatalkan transfer wallet ID {$transferId}.");
        show_sweetalert_and_redirect('Berhasil', 'Transfer wallet berhasil dibatalkan.', 'success', 'main.php?module=transfer_wallet');
    } catch (DomainException $error) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=transfer_wallet');
    } catch (Throwable $error) {
        $con->rollback();
        error_log('Pembatalan transfer gagal: ' . $error->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal dibatalkan.', 'error', 'main.php?module=transfer_wallet');
    }
}

if ($act === 'hp') {
    require_transfer_post_csrf();

    $transferId = (int) ($_POST['id_transfer'] ?? 0);
    if ($transferId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    try {
        $con->begin_transaction();
        $transfer = fetch_transfer_for_user($con, $transferId, $userId, true);
        if (!$transfer) {
            throw new DomainException('Transfer yang ingin dihapus tidak ditemukan atau bukan milik Anda.');
        }
        if ((string) $transfer['status'] !== 'pending') {
            throw new DomainException('Hanya transfer pending yang dapat dihapus permanen.');
        }

        $stmt = $con->prepare("DELETE FROM transfer_wallet
                               WHERE id_transfer = ? AND user_id = ? AND status = 'pending'");
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan penghapusan transfer.');
        }
        $stmt->bind_param('ii', $transferId, $userId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        if ($affectedRows !== 1) {
            throw new RuntimeException('Transfer pending gagal dihapus.');
        }

        $con->commit();
        record_activity($con, 'transfer_wallet', 'hapus_permanen', "Menghapus permanen transfer pending ID {$transferId}.");
        show_sweetalert_and_redirect('Berhasil', 'Transfer pending berhasil dihapus permanen.', 'success', 'main.php?module=transfer_wallet');
    } catch (DomainException $error) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=transfer_wallet');
    } catch (Throwable $error) {
        $con->rollback();
        error_log('Penghapusan transfer gagal: ' . $error->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal dihapus.', 'error', 'main.php?module=transfer_wallet');
    }
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan transfer wallet tidak dikenali.', 'error', 'main.php?module=transfer_wallet');
?>
