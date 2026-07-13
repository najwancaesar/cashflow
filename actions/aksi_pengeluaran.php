<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";

function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

function validate_kategori_id($kategoriId, $userId, $tipeKategori) {
    global $con;

    if ($kategoriId === null) {
        return null;
    }

    $query = "SELECT id_kategori
              FROM kategori
              WHERE id_kategori = ? AND user_id = ? AND tipe_kategori = ?
              LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "iis", $kategoriId, $userId, $tipeKategori);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kategori = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $kategori ? (int) $kategori['id_kategori'] : false;
}

function get_default_active_wallet_id($userId) {
    global $con;

    $query = "SELECT id_wallet
              FROM wallet
              WHERE user_id = ? AND is_default = 1 AND is_active = 1
              LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $wallet ? (int) $wallet['id_wallet'] : null;
}

function validate_wallet_id($walletId, $userId) {
    global $con;

    if ($walletId === null) {
        return null;
    }

    $query = "SELECT id_wallet
              FROM wallet
              WHERE id_wallet = ? AND user_id = ? AND is_active = 1
              LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $walletId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wallet = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $wallet ? (int) $wallet['id_wallet'] : false;
}

function resolve_pengeluaran_wallet_id($walletId, $userId) {
    if ($walletId !== null) {
        $validatedWalletId = validate_wallet_id($walletId, $userId);

        if ($validatedWalletId === false) {
            show_sweetalert_and_redirect('Gagal!', 'Wallet sumber tidak valid atau tidak aktif.', 'error', 'main.php?module=pengeluaran');
        }

        return $validatedWalletId;
    }

    $defaultWalletId = get_default_active_wallet_id($userId);
    if ($defaultWalletId !== null) {
        return $defaultWalletId;
    }

    show_sweetalert_and_redirect('Gagal!', 'Belum ada wallet aktif. Silakan buat atau aktifkan wallet terlebih dahulu.', 'error', 'main.php?module=pengeluaran');
}

function pengeluaran_dimiliki_user($idPengeluaran, $userId) {
    global $con;

    $query = "SELECT id_pengeluaran
              FROM pengeluaran
              WHERE id_pengeluaran = ? AND user = ?
              LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idPengeluaran, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $transaksi = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $transaksi !== false;
}

function normalize_pengeluaran_ids($input) {
    if (!is_array($input)) {
        return [];
    }

    $ids = array_map('intval', $input);
    $ids = array_filter($ids, static function ($id) {
        return $id > 0;
    });

    return array_values(array_unique($ids));
}

function fetch_pengeluaran_for_update($idPengeluaran, $userId) {
    global $con;

    $stmt = $con->prepare("SELECT id_pengeluaran, tanggal, catatan, jumlah, user, status, id_kategori, id_wallet
                           FROM pengeluaran
                           WHERE id_pengeluaran = ? AND user = ?
                           LIMIT 1
                           FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan data pengeluaran.');
    }

    $stmt->bind_param('ii', $idPengeluaran, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pengeluaran = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $pengeluaran ?: null;
}

function ensure_pengeluaran_balance_available($walletId, $userId, $jumlah, $excludePengeluaranId = null) {
    global $con;

    $saldoTersedia = cashflow_calculate_wallet_balance(
        $con,
        $userId,
        $walletId,
        $excludePengeluaranId
    );

    if ((float) $jumlah > $saldoTersedia + 0.00001) {
        throw new DomainException('Saldo wallet tidak mencukupi untuk menyelesaikan pengeluaran ini.');
    }

    return $saldoTersedia;
}

function rollback_pengeluaran_transaction() {
    global $con;

    try {
        $con->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Rollback pengeluaran gagal: ' . $rollbackError->getMessage());
    }
}

function handle_pengeluaran_transaction_failure($error, $fallbackMessage) {
    rollback_pengeluaran_transaction();

    if ($error instanceof DomainException) {
        show_sweetalert_and_redirect('Gagal!', $error->getMessage(), 'error', 'main.php?module=pengeluaran');
    }

    error_log('Proses pengeluaran gagal: ' . $error->getMessage());
    show_sweetalert_and_redirect('Gagal!', $fallbackMessage, 'error', 'main.php?module=pengeluaran');
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Gagal!', 'Silakan login terlebih dahulu.', 'error', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi pengeluaran.', 'warning', 'main.php?module=home');
}

if (isset($_GET['act'])) {
    $action = $_GET['act'];
    $user = (int) $_SESSION['id_user'];

    switch ($action) {
        case 't': // Tambah atau Update
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                show_sweetalert_and_redirect('Akses ditolak', 'Tambah atau edit pengeluaran wajib melalui form yang valid.', 'warning', 'main.php?module=pengeluaran');
            }

            if (!verify_csrf_token()) {
                show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pengeluaran');
            }

            $tanggal = clean_input($_POST['tanggal'] ?? '');
            $catatan = clean_input($_POST['catatan'] ?? '');
            $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
            $jumlah = nominal_input_to_number($jumlahRaw);
            $status = clean_input($_POST['status'] ?? 'pending');
            $kategoriId = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
                ? (int) clean_input($_POST['id_kategori'])
                : null;
            $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
                ? (int) clean_input($_POST['id_wallet'])
                : null;

            if ($tanggal === '' || $jumlah <= 0 || strpos($jumlahRaw, '-') !== false || $status === '') {
                show_sweetalert_and_redirect('Gagal!', 'Tanggal, jumlah, dan status wajib diisi!', 'error', 'main.php?module=pengeluaran');
            }

            if (!in_array($status, ['pending', 'selesai'], true)) {
                show_sweetalert_and_redirect('Gagal!', 'Status pengeluaran tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $validatedKategoriId = validate_kategori_id($kategoriId, $user, 'pengeluaran');
            if ($validatedKategoriId === false) {
                show_sweetalert_and_redirect('Gagal!', 'Kategori pengeluaran yang dipilih tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $validatedWalletId = resolve_pengeluaran_wallet_id($walletId, $user);

            if (empty($_POST['id_pengeluaran'])) {
                try {
                    $con->begin_transaction();
                    cashflow_lock_owned_wallets($con, $user, [$validatedWalletId], [$validatedWalletId]);

                    if ($status === 'selesai') {
                        ensure_pengeluaran_balance_available($validatedWalletId, $user, $jumlah);
                    }

                    $stmt = $con->prepare("INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status, id_kategori, id_wallet)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt) {
                        throw new RuntimeException('Gagal menyiapkan penambahan pengeluaran.');
                    }

                    $stmt->bind_param('ssdisii', $tanggal, $catatan, $jumlah, $user, $status, $validatedKategoriId, $validatedWalletId);
                    $stmt->execute();
                    $newPengeluaranId = (int) $con->insert_id;
                    $stmt->close();
                    $con->commit();
                } catch (Throwable $error) {
                    handle_pengeluaran_transaction_failure($error, 'Gagal menambahkan data.');
                }

                record_activity($con, 'pengeluaran', 'tambah', "Menambahkan pengeluaran ID {$newPengeluaranId}.");
                show_sweetalert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pengeluaran');
            } else {
                $id_pengeluaran = (int) clean_input($_POST['id_pengeluaran']);
                if ($id_pengeluaran <= 0) {
                    show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
                }

                try {
                    $con->begin_transaction();
                    $existingPengeluaran = fetch_pengeluaran_for_update($id_pengeluaran, $user);
                    if (!$existingPengeluaran) {
                        throw new DomainException('Data pengeluaran tidak ditemukan atau bukan milik Anda.');
                    }

                    $oldWalletId = (int) ($existingPengeluaran['id_wallet'] ?? 0);
                    cashflow_lock_owned_wallets(
                        $con,
                        $user,
                        [$oldWalletId, $validatedWalletId],
                        [$validatedWalletId]
                    );

                    if ($status === 'selesai') {
                        $excludePengeluaranId = $oldWalletId === $validatedWalletId
                            ? $id_pengeluaran
                            : null;
                        ensure_pengeluaran_balance_available(
                            $validatedWalletId,
                            $user,
                            $jumlah,
                            $excludePengeluaranId
                        );
                    }

                    $stmt = $con->prepare("UPDATE pengeluaran
                                           SET tanggal = ?, status = ?, catatan = ?, jumlah = ?, id_kategori = ?, id_wallet = ?
                                           WHERE id_pengeluaran = ? AND user = ?");
                    if (!$stmt) {
                        throw new RuntimeException('Gagal menyiapkan perubahan pengeluaran.');
                    }

                    $stmt->bind_param('sssdiiii', $tanggal, $status, $catatan, $jumlah, $validatedKategoriId, $validatedWalletId, $id_pengeluaran, $user);
                    $stmt->execute();
                    $stmt->close();
                    $con->commit();
                } catch (Throwable $error) {
                    handle_pengeluaran_transaction_failure($error, 'Gagal mengubah data.');
                }

                record_activity($con, 'pengeluaran', 'edit', "Mengubah pengeluaran ID {$id_pengeluaran}.");
                show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
            }
            break;

        case 'l':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                show_sweetalert_and_redirect('Akses ditolak', 'Ubah status pengeluaran wajib melalui form yang valid.', 'warning', 'main.php?module=pengeluaran');
            }

            if (!verify_csrf_token()) {
                show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pengeluaran');
            }

            $id_pengeluaran = (int) ($_POST['id_pengeluaran'] ?? 0);
            $targetStatus = clean_input($_POST['status'] ?? '');

            if (empty($id_pengeluaran)) {
                show_sweetalert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            if (!in_array($targetStatus, ['pending', 'selesai'], true)) {
                show_sweetalert_and_redirect('Gagal!', 'Status pengeluaran tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            try {
                $con->begin_transaction();
                $existingPengeluaran = fetch_pengeluaran_for_update($id_pengeluaran, $user);
                if (!$existingPengeluaran) {
                    throw new DomainException('Data pengeluaran tidak ditemukan atau bukan milik Anda.');
                }

                if ((string) $existingPengeluaran['status'] === $targetStatus) {
                    $con->commit();
                    show_sweetalert_and_redirect('Berhasil!', 'Status pengeluaran tidak berubah.', 'success', 'main.php?module=pengeluaran');
                }

                $existingWalletId = (int) ($existingPengeluaran['id_wallet'] ?? 0);
                if ($existingWalletId <= 0) {
                    throw new DomainException('Wallet sumber pengeluaran tidak valid.');
                }

                cashflow_lock_owned_wallets(
                    $con,
                    $user,
                    [$existingWalletId],
                    $targetStatus === 'selesai' ? [$existingWalletId] : []
                );

                if ($targetStatus === 'selesai') {
                    ensure_pengeluaran_balance_available(
                        $existingWalletId,
                        $user,
                        (float) $existingPengeluaran['jumlah'],
                        $id_pengeluaran
                    );
                }

                $stmt = $con->prepare("UPDATE pengeluaran
                                       SET status = ?
                                       WHERE id_pengeluaran = ? AND user = ?");
                if (!$stmt) {
                    throw new RuntimeException('Gagal menyiapkan perubahan status pengeluaran.');
                }

                $stmt->bind_param('sii', $targetStatus, $id_pengeluaran, $user);
                $stmt->execute();
                if ($stmt->affected_rows <= 0) {
                    $stmt->close();
                    throw new RuntimeException('Status pengeluaran gagal diperbarui.');
                }
                $stmt->close();
                $con->commit();
            } catch (Throwable $error) {
                handle_pengeluaran_transaction_failure($error, 'Gagal mengubah status pengeluaran.');
            }

            record_activity($con, 'pengeluaran', 'ubah_status', "Mengubah status pengeluaran ID {$id_pengeluaran} menjadi {$targetStatus}.");
            show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
            break;

        case 'h':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                show_sweetalert_and_redirect('Akses ditolak', 'Hapus pengeluaran wajib melalui form yang valid.', 'warning', 'main.php?module=pengeluaran');
            }

            if (!verify_csrf_token()) {
                show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pengeluaran');
            }

            $id_pengeluaran = (int) ($_POST['id_pengeluaran'] ?? 0);

            if ($id_pengeluaran <= 0) {
                show_sweetalert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            try {
                $con->begin_transaction();
                $existingPengeluaran = fetch_pengeluaran_for_update($id_pengeluaran, $user);
                if (!$existingPengeluaran) {
                    throw new DomainException('Data pengeluaran tidak ditemukan atau bukan milik Anda.');
                }

                $stmt = $con->prepare("DELETE FROM pengeluaran
                                       WHERE id_pengeluaran = ? AND user = ?");
                if (!$stmt) {
                    throw new RuntimeException('Gagal menyiapkan penghapusan pengeluaran.');
                }

                $stmt->bind_param('ii', $id_pengeluaran, $user);
                $stmt->execute();
                if ($stmt->affected_rows <= 0) {
                    $stmt->close();
                    throw new RuntimeException('Pengeluaran gagal dihapus.');
                }
                $stmt->close();
                $con->commit();
            } catch (Throwable $error) {
                handle_pengeluaran_transaction_failure($error, 'Gagal menghapus data.');
            }

            record_activity($con, 'pengeluaran', 'hapus', "Menghapus pengeluaran ID {$id_pengeluaran}.");
            show_sweetalert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pengeluaran');
            break;

        case 'bulk_delete':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                show_sweetalert_and_redirect('Akses ditolak', 'Hapus pengeluaran terpilih wajib melalui form yang valid.', 'warning', 'main.php?module=pengeluaran');
            }

            if (!verify_csrf_token()) {
                show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pengeluaran');
            }

            $ids = normalize_pengeluaran_ids($_POST['id_pengeluaran'] ?? []);

            if (empty($ids)) {
                show_sweetalert_and_redirect('Tidak ada data dipilih', 'Pilih minimal satu pengeluaran untuk dihapus.', 'warning', 'main.php?module=pengeluaran');
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $countQuery = "SELECT COUNT(*) AS total
                           FROM pengeluaran
                           WHERE user = ? AND id_pengeluaran IN ({$placeholders})";
            $countStmt = mysqli_prepare($con, $countQuery);
            $countTypes = 'i' . str_repeat('i', count($ids));
            $countParams = array_merge([$user], $ids);
            mysqli_stmt_bind_param($countStmt, $countTypes, ...$countParams);
            mysqli_stmt_execute($countStmt);
            $countResult = mysqli_stmt_get_result($countStmt);
            $countRow = mysqli_fetch_assoc($countResult);
            mysqli_stmt_close($countStmt);

            if ((int) ($countRow['total'] ?? 0) !== count($ids)) {
                show_sweetalert_and_redirect('Akses ditolak', 'Sebagian data tidak valid atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            $deleteQuery = "DELETE FROM pengeluaran
                            WHERE user = ? AND id_pengeluaran IN ({$placeholders})";
            $deleteStmt = mysqli_prepare($con, $deleteQuery);
            $deleteTypes = 'i' . str_repeat('i', count($ids));
            $deleteParams = array_merge([$user], $ids);
            mysqli_stmt_bind_param($deleteStmt, $deleteTypes, ...$deleteParams);
            $hasil = mysqli_stmt_execute($deleteStmt);
            $affectedRows = mysqli_stmt_affected_rows($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            if ($hasil && $affectedRows > 0) {
                if (function_exists('record_activity')) {
                    record_activity($con, 'pengeluaran', 'hapus_massal', "Menghapus massal pengeluaran sebanyak {$affectedRows} data.");
                }
                show_sweetalert_and_redirect('Berhasil!', "Berhasil menghapus {$affectedRows} data pengeluaran.", 'success', 'main.php?module=pengeluaran');
            }

            show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran terpilih gagal dihapus.', 'error', 'main.php?module=pengeluaran');
            break;

        default:
            show_sweetalert_and_redirect('Gagal!', 'Aksi tidak valid!', 'error', 'main.php?module=pengeluaran');
    }
} else {
    show_sweetalert_and_redirect('Gagal!', 'Tidak ada aksi yang diterima.', 'error', 'main.php?module=pengeluaran');
}
?>
