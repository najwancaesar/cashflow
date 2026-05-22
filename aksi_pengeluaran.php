<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include_once "includes/csrf_helper.php";
include_once "includes/activity_log_helper.php";

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
            $jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');
            $status = clean_input($_POST['status'] ?? 'pending');
            $kategoriId = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
                ? (int) clean_input($_POST['id_kategori'])
                : null;
            $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
                ? (int) clean_input($_POST['id_wallet'])
                : null;

            if ($tanggal === '' || $jumlah <= 0 || $status === '') {
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
                $query = "INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status, id_kategori, id_wallet)
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "ssdisii", $tanggal, $catatan, $jumlah, $user, $status, $validatedKategoriId, $validatedWalletId);

                if (mysqli_stmt_execute($stmt)) {
                    $newPengeluaranId = (int) mysqli_insert_id($con);
                    mysqli_stmt_close($stmt);
                    record_activity($con, 'pengeluaran', 'tambah', "Menambahkan pengeluaran ID {$newPengeluaranId}.");
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pengeluaran');
                }

                mysqli_stmt_close($stmt);
                show_sweetalert_and_redirect('Gagal!', 'Gagal menambahkan data.', 'error', 'main.php?module=pengeluaran');
            } else {
                $id_pengeluaran = (int) clean_input($_POST['id_pengeluaran']);
                if (!pengeluaran_dimiliki_user($id_pengeluaran, $user)) {
                    show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
                }

                $query = "UPDATE pengeluaran 
                          SET tanggal = ?, status = ?, catatan = ?, jumlah = ?, id_kategori = ?, id_wallet = ?
                          WHERE id_pengeluaran = ? AND user = ?";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "sssdiiii", $tanggal, $status, $catatan, $jumlah, $validatedKategoriId, $validatedWalletId, $id_pengeluaran, $user);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    record_activity($con, 'pengeluaran', 'edit', "Mengubah pengeluaran ID {$id_pengeluaran}.");
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
                }

                mysqli_stmt_close($stmt);
                show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah data.', 'error', 'main.php?module=pengeluaran');
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

            if (!pengeluaran_dimiliki_user($id_pengeluaran, $user)) {
                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            $query = "UPDATE pengeluaran 
                      SET status = ?
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "sii", $targetStatus, $id_pengeluaran, $user);

            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affectedRows > 0) {
                    record_activity($con, 'pengeluaran', 'ubah_status', "Mengubah status pengeluaran ID {$id_pengeluaran} menjadi {$targetStatus}.");
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
                }

                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            mysqli_stmt_close($stmt);
            show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah status pengeluaran.', 'error', 'main.php?module=pengeluaran');
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

            if (!pengeluaran_dimiliki_user($id_pengeluaran, $user)) {
                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            $query = "DELETE FROM pengeluaran 
            WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id_pengeluaran, $user);
            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affectedRows > 0) {
                    record_activity($con, 'pengeluaran', 'hapus', "Menghapus pengeluaran ID {$id_pengeluaran}.");
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pengeluaran');
                }

                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            mysqli_stmt_close($stmt);
            show_sweetalert_and_redirect('Gagal!', 'Gagal menghapus data.', 'error', 'main.php?module=pengeluaran');
            break;

        default:
            show_sweetalert_and_redirect('Gagal!', 'Aksi tidak valid!', 'error', 'main.php?module=pengeluaran');
    }
} else {
    show_sweetalert_and_redirect('Gagal!', 'Tidak ada aksi yang diterima.', 'error', 'main.php?module=pengeluaran');
}
?>
