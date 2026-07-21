<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";

// Fungsi untuk membersihkan input
function clean_input($data) {
    return trim((string) $data);
}

function is_valid_transaction_date($value) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $matches)) {
        return false;
    }
    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
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

function resolve_pemasukan_wallet_id($walletId, $userId) {
    if ($walletId !== null) {
        $validatedWalletId = validate_wallet_id($walletId, $userId);

        if ($validatedWalletId === false) {
            show_sweetalert_and_redirect('Gagal!', 'Wallet tujuan tidak valid atau tidak aktif.', 'error', 'main.php?module=pemasukan');
        }

        return $validatedWalletId;
    }

    $defaultWalletId = get_default_active_wallet_id($userId);
    if ($defaultWalletId !== null) {
        return $defaultWalletId;
    }

    show_sweetalert_and_redirect('Gagal!', 'Belum ada wallet aktif. Silakan buat atau aktifkan wallet terlebih dahulu.', 'error', 'main.php?module=pemasukan');
}

function pemasukan_dimiliki_user($idPemasukan, $userId) {
    global $con;

    $query = "SELECT id_pemasukan
              FROM pemasukan
              WHERE id_pemasukan = ? AND user = ?
              LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idPemasukan, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $transaksi = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $transaksi !== false;
}

function fetch_pemasukan_for_update($idPemasukan, $userId) {
    global $con;

    $stmt = $con->prepare("SELECT id_pemasukan, tanggal, catatan, status, jumlah, id_kategori, id_wallet
                           FROM pemasukan
                           WHERE id_pemasukan = ? AND user = ?
                           LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Gagal menyiapkan data pemasukan.');
    }
    $stmt->bind_param('ii', $idPemasukan, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ensure_pemasukan_change_does_not_worsen_negative_balance(array $currentBalances, array $proposedBalances) {
    foreach ($proposedBalances as $walletId => $proposedBalance) {
        $currentBalance = (float) ($currentBalances[$walletId] ?? 0);
        if ($proposedBalance < -0.00001 && $proposedBalance < $currentBalance - 0.00001) {
            throw new DomainException('Perubahan pemasukan akan membuat saldo wallet terkait menjadi atau semakin negatif.');
        }
    }
}

function normalize_pemasukan_ids($input) {
    if (!is_array($input)) {
        return [];
    }

    $ids = array_map('intval', $input);
    $ids = array_filter($ids, static function ($id) {
        return $id > 0;
    });

    return array_values(array_unique($ids));
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi pemasukan.', 'warning', 'main.php?module=home');
}

$act = $_GET['act'] ?? '';
$user = (int) $_SESSION['id_user'];

if ($act == 't') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Tambah atau edit pemasukan wajib melalui form yang valid.', 'warning', 'main.php?module=pemasukan');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pemasukan');
    }

    $tanggal = clean_input($_POST['tanggal'] ?? '');
    $catatan = clean_input($_POST['catatan'] ?? '');
    $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
    $jumlah = nominal_input_to_number($jumlahRaw);
    $status = clean_input($_POST['status'] ?? '');
    $kategoriId = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
        ? (int) $_POST['id_kategori']
        : null;
    $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
        ? (int) $_POST['id_wallet']
        : null;

    if (!is_valid_transaction_date($tanggal) || $jumlah <= 0 || strpos($jumlahRaw, '-') !== false || $status === '') {
        show_sweetalert_and_redirect('Gagal!', 'Tanggal, jumlah, dan status wajib diisi.', 'error', 'main.php?module=pemasukan');
    }

    if (!in_array($status, ['pending', 'selesai'], true)) {
        show_sweetalert_and_redirect('Gagal!', 'Status pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
    }

    $validatedKategoriId = validate_kategori_id($kategoriId, $user, 'pemasukan');
    if ($validatedKategoriId === false) {
        show_sweetalert_and_redirect('Gagal!', 'Kategori pemasukan yang dipilih tidak valid.', 'error', 'main.php?module=pemasukan');
    }

    $validatedWalletId = resolve_pemasukan_wallet_id($walletId, $user);

    if (empty($_POST['id_pemasukan'])) {
        try {
            $con->begin_transaction();
            cashflow_lock_owned_wallets($con, $user, [$validatedWalletId], [$validatedWalletId]);

            $stmt = $con->prepare("INSERT INTO pemasukan(tanggal, catatan, status, jumlah, user, id_kategori, id_wallet)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Gagal menyiapkan penambahan pemasukan.');
            }
            $stmt->bind_param('sssdiii', $tanggal, $catatan, $status, $jumlah, $user, $validatedKategoriId, $validatedWalletId);
            $stmt->execute();
            $newPemasukanId = (int) $con->insert_id;
            $stmt->close();
            $con->commit();
        } catch (Throwable $error) {
            $con->rollback();
            error_log('Tambah pemasukan gagal: ' . $error->getMessage());
            show_sweetalert_and_redirect('Gagal!', 'Gagal menambahkan data.', 'error', 'main.php?module=pemasukan');
        }

        record_activity($con, 'pemasukan', 'tambah', "Menambahkan pemasukan ID {$newPemasukanId}.");
        show_sweetalert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pemasukan');
    } else {
        $id_pemasukan = (int) $_POST['id_pemasukan'];
        if ($id_pemasukan <= 0) {
            show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
        }

        try {
            $con->begin_transaction();
            $existingPemasukan = fetch_pemasukan_for_update($id_pemasukan, $user);
            if (!$existingPemasukan) {
                throw new DomainException('Data pemasukan tidak ditemukan atau bukan milik Anda.');
            }

            $oldWalletId = (int) ($existingPemasukan['id_wallet'] ?? 0);
            cashflow_lock_owned_wallets($con, $user, [$oldWalletId, $validatedWalletId], [$validatedWalletId]);

            $affectedWalletIds = array_values(array_unique([$oldWalletId, $validatedWalletId]));
            $currentBalances = [];
            $proposedBalances = [];
            foreach ($affectedWalletIds as $affectedWalletId) {
                $currentBalances[$affectedWalletId] = cashflow_calculate_wallet_balance($con, $user, $affectedWalletId);
                $proposedBalances[$affectedWalletId] = cashflow_calculate_wallet_balance(
                    $con,
                    $user,
                    $affectedWalletId,
                    null,
                    null,
                    $id_pemasukan
                );
            }
            if ($status === 'selesai') {
                $proposedBalances[$validatedWalletId] += $jumlah;
            }
            ensure_pemasukan_change_does_not_worsen_negative_balance($currentBalances, $proposedBalances);

            $stmt = $con->prepare("UPDATE pemasukan
                                   SET tanggal = ?, status = ?, catatan = ?, jumlah = ?, id_kategori = ?, id_wallet = ?
                                   WHERE id_pemasukan = ? AND user = ?");
            if (!$stmt) {
                throw new RuntimeException('Gagal menyiapkan perubahan pemasukan.');
            }
            $stmt->bind_param('sssdiiii', $tanggal, $status, $catatan, $jumlah, $validatedKategoriId, $validatedWalletId, $id_pemasukan, $user);
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            if ($affectedRows === 0) {
                $con->commit();
                show_sweetalert_and_redirect('Tidak ada perubahan', 'Data pemasukan tidak berubah.', 'info', 'main.php?module=pemasukan');
            }
            $con->commit();
        } catch (DomainException $error) {
            $con->rollback();
            show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=pemasukan');
        } catch (Throwable $error) {
            $con->rollback();
            error_log('Edit pemasukan gagal: ' . $error->getMessage());
            show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah data.', 'error', 'main.php?module=pemasukan');
        }

        record_activity($con, 'pemasukan', 'edit', "Mengubah pemasukan ID {$id_pemasukan}.");
        show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pemasukan');
    }
}

if ($act == 'l') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Ubah status pemasukan wajib melalui form yang valid.', 'warning', 'main.php?module=pemasukan');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pemasukan');
    }

    $id_pemasukan = (int) ($_POST['id_pemasukan'] ?? 0);
    $targetStatus = clean_input($_POST['status'] ?? '');

    if ($id_pemasukan <= 0) {
        show_sweetalert_and_redirect('Gagal!', 'ID pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
    }

    if (!in_array($targetStatus, ['pending', 'selesai'], true)) {
        show_sweetalert_and_redirect('Gagal!', 'Status pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
    }

    try {
        $con->begin_transaction();
        $existingPemasukan = fetch_pemasukan_for_update($id_pemasukan, $user);
        if (!$existingPemasukan) {
            throw new DomainException('Data pemasukan tidak ditemukan atau bukan milik Anda.');
        }
        if ((string) ($existingPemasukan['status'] ?? '') === $targetStatus) {
            $con->commit();
            show_sweetalert_and_redirect('Tidak ada perubahan', 'Status pemasukan tidak berubah.', 'info', 'main.php?module=pemasukan');
        }

        $walletId = (int) ($existingPemasukan['id_wallet'] ?? 0);
        cashflow_lock_owned_wallets($con, $user, [$walletId]);
        $currentBalance = cashflow_calculate_wallet_balance($con, $user, $walletId);
        $proposedBalance = cashflow_calculate_wallet_balance($con, $user, $walletId, null, null, $id_pemasukan);
        if ($targetStatus === 'selesai') {
            $proposedBalance += (float) ($existingPemasukan['jumlah'] ?? 0);
        }
        ensure_pemasukan_change_does_not_worsen_negative_balance(
            [$walletId => $currentBalance],
            [$walletId => $proposedBalance]
        );

        $stmt = $con->prepare("UPDATE pemasukan SET status = ?
                               WHERE id_pemasukan = ? AND user = ?");
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan perubahan status pemasukan.');
        }
        $stmt->bind_param('sii', $targetStatus, $id_pemasukan, $user);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Status pemasukan gagal diperbarui.');
        }
        $stmt->close();
        $con->commit();
    } catch (DomainException $error) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=pemasukan');
    } catch (Throwable $error) {
        $con->rollback();
        error_log('Ubah status pemasukan gagal: ' . $error->getMessage());
        show_sweetalert_and_redirect('Gagal!', 'Status pemasukan gagal diubah.', 'error', 'main.php?module=pemasukan');
    }

    record_activity($con, 'pemasukan', 'ubah_status', "Mengubah status pemasukan ID {$id_pemasukan} menjadi {$targetStatus}.");
    show_sweetalert_and_redirect('Berhasil!', 'Status pemasukan berhasil diubah.', 'success', 'main.php?module=pemasukan');
}

if ($act == 'h') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Hapus pemasukan wajib melalui form yang valid.', 'warning', 'main.php?module=pemasukan');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=pemasukan');
    }

    $id_pemasukan = (int) ($_POST['id_pemasukan'] ?? 0);
    if ($id_pemasukan <= 0) {
        show_sweetalert_and_redirect('Gagal!', 'ID pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
    }

    try {
        $con->begin_transaction();

        $statusStmt = $con->prepare("SELECT status FROM pemasukan
                                     WHERE id_pemasukan = ? AND user = ?
                                     LIMIT 1 FOR UPDATE");
        if (!$statusStmt) {
            throw new RuntimeException('Gagal menyiapkan pemeriksaan pemasukan.');
        }
        $statusStmt->bind_param('ii', $id_pemasukan, $user);
        $statusStmt->execute();
        $statusRow = $statusStmt->get_result()->fetch_assoc();
        $statusStmt->close();
        if (!$statusRow) {
            throw new DomainException('Data pemasukan tidak ditemukan atau bukan milik Anda.');
        }
        if (($statusRow['status'] ?? '') !== 'pending') {
            throw new DomainException('Pemasukan selesai tidak dapat dihapus permanen agar saldo dan histori tetap utuh.');
        }

        $relationStmt = $con->prepare("SELECT id_log AS relation_id
                                       FROM recurring_generation_log
                                       WHERE user_id = ? AND tipe_transaksi = 'pemasukan' AND id_transaksi = ?
                                       UNION ALL
                                       SELECT id_piutang AS relation_id
                                       FROM piutang
                                       WHERE user = ? AND id_pemasukan = ?
                                       LIMIT 1");
        if (!$relationStmt) {
            throw new RuntimeException('Gagal memeriksa relasi pemasukan.');
        }
        $relationStmt->bind_param('iiii', $user, $id_pemasukan, $user, $id_pemasukan);
        $relationStmt->execute();
        $hasProtectedRelation = (bool) $relationStmt->get_result()->fetch_assoc();
        $relationStmt->close();
        if ($hasProtectedRelation) {
            throw new DomainException('Pemasukan linked atau hasil recurring tidak dapat dihapus permanen.');
        }

        $stmt = $con->prepare("DELETE FROM pemasukan
                               WHERE id_pemasukan = ? AND user = ? AND status = 'pending'");
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan penghapusan pemasukan.');
        }
        $stmt->bind_param('ii', $id_pemasukan, $user);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Pemasukan gagal dihapus atau statusnya sudah berubah.');
        }
        $stmt->close();
        $con->commit();
    } catch (DomainException $error) {
        $con->rollback();
        show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', 'main.php?module=pemasukan');
    } catch (Throwable $error) {
        $con->rollback();
        error_log('Hapus pemasukan gagal: ' . $error->getMessage());
        show_sweetalert_and_redirect('Gagal!', 'Pemasukan gagal dihapus. Tidak ada data yang diubah.', 'error', 'main.php?module=pemasukan');
    }

    record_activity($con, 'pemasukan', 'hapus', "Menghapus pemasukan pending ID {$id_pemasukan}.");
    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pemasukan');
}

show_sweetalert_and_redirect('Gagal!', 'Aksi pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
?>
