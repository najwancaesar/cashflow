<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_type_helper.php";

function clean_wallet_text($value)
{
    return trim((string) $value);
}

function is_valid_wallet_type($type)
{
    return array_key_exists($type, cashflow_legacy_wallet_types());
}

function require_wallet_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi wallet wajib melalui form yang valid.', 'warning', 'main.php?module=wallet');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=wallet');
    }
}

function fetch_wallet_by_id($con, $walletId, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet, user_id, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active
                           FROM wallet
                           WHERE id_wallet = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $walletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $wallet ?: null;
}

function count_active_wallets($con, $userId)
{
    $stmt = $con->prepare("SELECT COUNT(*) AS total FROM wallet WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function user_has_default_wallet($con, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet FROM wallet WHERE user_id = ? AND is_default = 1 LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasDefault = $result && $result->num_rows > 0;
    $stmt->close();

    return $hasDefault;
}

function count_wallet_financial_relations($con, $walletId)
{
    $sql = "SELECT
                (SELECT COUNT(*) FROM pemasukan WHERE id_wallet = ?)
              + (SELECT COUNT(*) FROM pengeluaran WHERE id_wallet = ?)
              + (SELECT COUNT(*) FROM transfer_wallet WHERE wallet_asal_id = ?)
              + (SELECT COUNT(*) FROM transfer_wallet WHERE wallet_tujuan_id = ?)
              + (SELECT COUNT(*) FROM saving_goal_mutasi WHERE id_wallet = ?)
              + (SELECT COUNT(*) FROM recurring_transaction WHERE id_wallet = ?)
              + (SELECT COUNT(*) FROM hutang WHERE id_wallet_pembayaran = ?)
              + (SELECT COUNT(*) FROM piutang WHERE id_wallet_penerimaan = ?)
              AS total";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        error_log('CashFlow wallet relation check could not be prepared.');
        return null;
    }

    $stmt->bind_param(
        'iiiiiiii',
        $walletId,
        $walletId,
        $walletId,
        $walletId,
        $walletId,
        $walletId,
        $walletId,
        $walletId
    );
    if (!$stmt->execute()) {
        $stmt->close();
        error_log('CashFlow wallet relation check failed.');
        return null;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola wallet user pada phase ini.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    require_wallet_post_csrf();

    $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
        ? (int) $_POST['id_wallet']
        : null;
    $namaWallet = clean_wallet_text($_POST['nama_wallet'] ?? '');
    $tipeWalletSelection = clean_wallet_text($_POST['tipe_wallet'] ?? '');
    $saldoAwalRaw = (string) ($_POST['saldo_awal'] ?? '');

    if ($namaWallet === '' || $tipeWalletSelection === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Nama wallet dan tipe wallet wajib diisi.', 'warning', 'main.php?module=wallet');
    }

    $existingWallet = null;
    if ($walletId !== null) {
        if ($walletId <= 0) {
            show_sweetalert_and_redirect('Data tidak valid', 'ID wallet tidak valid.', 'error', 'main.php?module=wallet');
        }

        $existingWallet = fetch_wallet_by_id($con, $walletId, $userId);
        if (!$existingWallet) {
            show_sweetalert_and_redirect('Akses ditolak', 'Wallet yang ingin diubah tidak ditemukan.', 'warning', 'main.php?module=wallet');
        }
    }

    $resolvedWalletType = cashflow_resolve_wallet_type_selection($con, $userId, $tipeWalletSelection, $walletId);
    if (!$resolvedWalletType) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tipe wallet tidak valid.', 'error', 'main.php?module=wallet');
    }
    $tipeWallet = $resolvedWalletType['legacy_type'];
    $customWalletTypeId = $resolvedWalletType['custom_type_id'];
    $walletTypeSchemaReady = cashflow_wallet_type_schema_ready($con);

    if (strpos($saldoAwalRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Saldo awal tidak boleh bernilai negatif.', 'error', 'main.php?module=wallet');
    }

    $saldoAwal = nominal_input_to_number($saldoAwalRaw);
    if ($saldoAwal < 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Saldo awal tidak boleh bernilai negatif.', 'error', 'main.php?module=wallet');
    }

    if ($walletId === null) {
        $isDefault = user_has_default_wallet($con, $userId) ? 0 : 1;
        $isActive = 1;

        if ($walletTypeSchemaReady) {
            $stmt = $con->prepare("INSERT INTO wallet (user_id, nama_wallet, tipe_wallet, id_wallet_type, saldo_awal, is_default, is_active, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issidii", $userId, $namaWallet, $tipeWallet, $customWalletTypeId, $saldoAwal, $isDefault, $isActive);
        } else {
            $stmt = $con->prepare("INSERT INTO wallet (user_id, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("issdii", $userId, $namaWallet, $tipeWallet, $saldoAwal, $isDefault, $isActive);
        }
        $result = $stmt->execute();
        $newWalletId = (int) $stmt->insert_id;
        $stmt->close();

        if ($result) {
            record_activity($con, 'wallet', 'tambah', "Menambahkan wallet ID {$newWalletId}.");
            show_sweetalert_and_redirect('Berhasil', 'Wallet berhasil ditambahkan.', 'success', 'main.php?module=wallet');
        }

        show_sweetalert_and_redirect('Gagal', 'Wallet gagal ditambahkan.', 'error', 'main.php?module=wallet');
    }

    if ($walletTypeSchemaReady) {
        $stmt = $con->prepare("UPDATE wallet
                               SET nama_wallet = ?, tipe_wallet = ?, id_wallet_type = ?, saldo_awal = ?, updated_at = NOW()
                               WHERE id_wallet = ? AND user_id = ?");
        $stmt->bind_param("ssidii", $namaWallet, $tipeWallet, $customWalletTypeId, $saldoAwal, $walletId, $userId);
    } else {
        $stmt = $con->prepare("UPDATE wallet
                               SET nama_wallet = ?, tipe_wallet = ?, saldo_awal = ?, updated_at = NOW()
                               WHERE id_wallet = ? AND user_id = ?");
        $stmt->bind_param("ssdii", $namaWallet, $tipeWallet, $saldoAwal, $walletId, $userId);
    }
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        record_activity($con, 'wallet', 'edit', "Mengubah wallet ID {$walletId}.");
        show_sweetalert_and_redirect('Berhasil', 'Wallet berhasil diperbarui.', 'success', 'main.php?module=wallet');
    }

    show_sweetalert_and_redirect('Gagal', 'Wallet gagal diperbarui.', 'error', 'main.php?module=wallet');
}

if ($act === 's') {
    require_wallet_post_csrf();

    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    $targetStatus = clean_wallet_text($_POST['value'] ?? '');

    if ($walletId <= 0 || !in_array($targetStatus, ['0', '1'], true)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Permintaan status wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    $targetStatusInt = (int) $targetStatus;
    try {
        $con->begin_transaction();
        $lockStmt = $con->prepare("SELECT id_wallet, is_default, is_active
                                   FROM wallet
                                   WHERE user_id = ?
                                   ORDER BY id_wallet ASC
                                   FOR UPDATE");
        if (!$lockStmt) {
            throw new RuntimeException('Gagal mengunci daftar wallet.');
        }
        $lockStmt->bind_param('i', $userId);
        $lockStmt->execute();
        $result = $lockStmt->get_result();
        $wallet = null;
        $activeWalletCount = 0;
        while ($row = $result->fetch_assoc()) {
            if ((string) ($row['is_active'] ?? '0') === '1') {
                $activeWalletCount++;
            }
            if ((int) $row['id_wallet'] === $walletId) {
                $wallet = $row;
            }
        }
        $lockStmt->close();

        if (!$wallet) {
            throw new DomainException('Wallet yang ingin diubah tidak ditemukan atau bukan milik Anda.');
        }
        if ((string) ($wallet['is_active'] ?? '0') === $targetStatus) {
            $con->commit();
            $statusLabel = $targetStatus === '1' ? 'aktif' : 'nonaktif';
            show_sweetalert_and_redirect('Tidak ada perubahan', "Wallet sudah berstatus {$statusLabel}.", 'info', 'main.php?module=wallet');
        }
        if ($targetStatus === '0' && (string) ($wallet['is_default'] ?? '0') === '1') {
            throw new DomainException('Wallet default tidak boleh dinonaktifkan.');
        }
        if ($targetStatus === '0' && $activeWalletCount <= 1) {
            throw new DomainException('Minimal harus ada satu wallet aktif.');
        }

        $stmt = $con->prepare("UPDATE wallet SET is_active = ?, updated_at = NOW()
                               WHERE id_wallet = ? AND user_id = ? AND is_active <> ?");
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan perubahan status wallet.');
        }
        $stmt->bind_param('iiii', $targetStatusInt, $walletId, $userId, $targetStatusInt);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        if ($affectedRows !== 1) {
            throw new RuntimeException('Status wallet berubah saat diproses.');
        }
        $con->commit();
    } catch (DomainException $exception) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi dibatasi', $exception->getMessage(), 'warning', 'main.php?module=wallet');
    } catch (Throwable $exception) {
        $con->rollback();
        error_log('CashFlow wallet status update failed: ' . $exception->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Status wallet gagal diperbarui.', 'error', 'main.php?module=wallet');
    }

    $statusLabel = $targetStatusInt === 1 ? 'aktif' : 'nonaktif';
    record_activity($con, 'wallet', 'ubah_status', "Mengubah wallet ID {$walletId} menjadi {$statusLabel}.");
    show_sweetalert_and_redirect('Berhasil', 'Status wallet berhasil diperbarui.', 'success', 'main.php?module=wallet');
}

if ($act === 'd') {
    require_wallet_post_csrf();

    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    if ($walletId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    $wallet = fetch_wallet_by_id($con, $walletId, $userId);
    if (!$wallet) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet yang ingin dijadikan default tidak ditemukan.', 'warning', 'main.php?module=wallet');
    }

    if ((string) ($wallet['is_active'] ?? '0') !== '1') {
        show_sweetalert_and_redirect('Aksi dibatasi', 'Wallet nonaktif tidak bisa dijadikan default.', 'warning', 'main.php?module=wallet');
    }


    if ((string) ($wallet['is_default'] ?? '0') === '1') {
        show_sweetalert_and_redirect('Tidak ada perubahan', 'Wallet ini sudah menjadi wallet default.', 'info', 'main.php?module=wallet');
    }

    mysqli_begin_transaction($con);

    try {
        $resetStmt = $con->prepare("UPDATE wallet SET is_default = 0, updated_at = NOW() WHERE user_id = ?");
        $resetStmt->bind_param("i", $userId);
        $resetStmt->execute();
        $resetStmt->close();

        $defaultStmt = $con->prepare("UPDATE wallet SET is_default = 1, updated_at = NOW() WHERE id_wallet = ? AND user_id = ?");
        $defaultStmt->bind_param("ii", $walletId, $userId);
        $defaultStmt->execute();
        $affectedRows = $defaultStmt->affected_rows;
        $defaultStmt->close();

        if ($affectedRows < 1) {
            mysqli_rollback($con);
            show_sweetalert_and_redirect('Gagal', 'Wallet default gagal diperbarui.', 'error', 'main.php?module=wallet');
        }

        mysqli_commit($con);
        record_activity($con, 'wallet', 'set_default', "Menjadikan wallet ID {$walletId} sebagai default.");
        show_sweetalert_and_redirect('Berhasil', 'Wallet default berhasil diperbarui.', 'success', 'main.php?module=wallet');
    } catch (Throwable $exception) {
        mysqli_rollback($con);
        show_sweetalert_and_redirect('Gagal', 'Wallet default gagal diperbarui.', 'error', 'main.php?module=wallet');
    }
}

if ($act === 'h') {
    require_wallet_post_csrf();

    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    if ($walletId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    try {
        $con->begin_transaction();

        $lockStmt = $con->prepare("SELECT id_wallet, is_default
                                   FROM wallet
                                   WHERE id_wallet = ? AND user_id = ?
                                   LIMIT 1 FOR UPDATE");
        if (!$lockStmt) {
            throw new RuntimeException('Gagal mengunci wallet.');
        }
        $lockStmt->bind_param('ii', $walletId, $userId);
        $lockStmt->execute();
        $wallet = $lockStmt->get_result()->fetch_assoc();
        $lockStmt->close();

        if (!$wallet) {
            throw new DomainException('Wallet tidak ditemukan atau bukan milik Anda.');
        }

        $relationCount = count_wallet_financial_relations($con, $walletId);
        if ($relationCount === null) {
            throw new RuntimeException('Relasi wallet tidak dapat diverifikasi.');
        }
        if ($relationCount > 0) {
            throw new DomainException('Wallet memiliki histori atau relasi finansial dan tidak dapat dihapus permanen. Gunakan Nonaktifkan.');
        }

        $deleteStmt = $con->prepare('DELETE FROM wallet WHERE id_wallet = ? AND user_id = ?');
        if (!$deleteStmt) {
            throw new RuntimeException('Gagal menyiapkan penghapusan wallet.');
        }
        $deleteStmt->bind_param('ii', $walletId, $userId);
        $deleteStmt->execute();
        $affectedRows = $deleteStmt->affected_rows;
        $deleteStmt->close();
        if ($affectedRows !== 1) {
            throw new RuntimeException('Wallet gagal dihapus.');
        }

        if ((string) ($wallet['is_default'] ?? '0') === '1') {
            $replacementStmt = $con->prepare("SELECT id_wallet
                                              FROM wallet
                                              WHERE user_id = ?
                                              ORDER BY is_active DESC, id_wallet ASC
                                              LIMIT 1 FOR UPDATE");
            if (!$replacementStmt) {
                throw new RuntimeException('Gagal memilih wallet default pengganti.');
            }
            $replacementStmt->bind_param('i', $userId);
            $replacementStmt->execute();
            $replacement = $replacementStmt->get_result()->fetch_assoc();
            $replacementStmt->close();

            if ($replacement) {
                $replacementId = (int) $replacement['id_wallet'];
                $defaultStmt = $con->prepare('UPDATE wallet SET is_default = 1, updated_at = NOW() WHERE id_wallet = ? AND user_id = ?');
                if (!$defaultStmt) {
                    throw new RuntimeException('Gagal menyiapkan wallet default pengganti.');
                }
                $defaultStmt->bind_param('ii', $replacementId, $userId);
                $defaultStmt->execute();
                if ($defaultStmt->affected_rows !== 1) {
                    $defaultStmt->close();
                    throw new RuntimeException('Wallet default pengganti gagal diperbarui.');
                }
                $defaultStmt->close();
            }
        }

        $con->commit();
    } catch (DomainException $exception) {
        $con->rollback();
        show_sweetalert_and_redirect('Wallet tidak dapat dihapus', $exception->getMessage(), 'warning', 'main.php?module=wallet');
    } catch (Throwable $exception) {
        $con->rollback();
        error_log('CashFlow wallet hard delete failed: ' . $exception->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Wallet gagal dihapus karena relasinya tidak dapat diverifikasi.', 'error', 'main.php?module=wallet');
    }

    record_activity($con, 'wallet', 'hapus', "Menghapus wallet ID {$walletId} yang tidak memiliki relasi finansial.");
    show_sweetalert_and_redirect('Berhasil', 'Wallet tanpa histori berhasil dihapus permanen.', 'success', 'main.php?module=wallet');
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan wallet tidak dikenali.', 'error', 'main.php?module=wallet');
?>
