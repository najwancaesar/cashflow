<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include_once "includes/csrf_helper.php";

// Fungsi untuk membersihkan input
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

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi pemasukan.', 'warning', 'main.php?module=home');
}

$act = $_GET['act'] ?? '';
$user = (int) $_SESSION['id_user'];

if ($act == 't') {
    $tanggal = clean_input($_POST['tanggal'] ?? '');
    $catatan = clean_input($_POST['catatan'] ?? '');
    $jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');
    $status = clean_input($_POST['status'] ?? '');
    $kategoriId = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
        ? (int) clean_input($_POST['id_kategori'])
        : null;
    $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
        ? (int) clean_input($_POST['id_wallet'])
        : null;

    if ($tanggal === '' || $jumlah <= 0 || $status === '') {
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

    if ($_POST['id_pemasukan'] == '') {
        $query = "INSERT INTO pemasukan(tanggal, catatan, status, jumlah, user, id_kategori, id_wallet)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssdiii", $tanggal, $catatan, $status, $jumlah, $user, $validatedKategoriId, $validatedWalletId);
        $hasil = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($hasil) {
            show_sweetalert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pemasukan');
        } else {
            show_sweetalert_and_redirect('Gagal!', 'Gagal menambahkan data.', 'error', 'main.php?module=pemasukan');
        }
    } else {
        $id_pemasukan = (int) clean_input($_POST['id_pemasukan']);
        if (!pemasukan_dimiliki_user($id_pemasukan, $user)) {
            show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
        }

        $query = "UPDATE pemasukan 
                 SET tanggal = ?, status = ?, catatan = ?, jumlah = ?, id_kategori = ?, id_wallet = ?
                 WHERE id_pemasukan = ? AND user = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssdiiii", $tanggal, $status, $catatan, $jumlah, $validatedKategoriId, $validatedWalletId, $id_pemasukan, $user);
        $hasil = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($hasil) {
            show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pemasukan');
        } else {
            show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah data.', 'error', 'main.php?module=pemasukan');
        }
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

    if (!pemasukan_dimiliki_user($id_pemasukan, $user)) {
        show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
    }

    $query = "UPDATE pemasukan SET status = ? WHERE id_pemasukan = ? AND user = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "sii", $targetStatus, $id_pemasukan, $user);
    $hasil = mysqli_stmt_execute($stmt);
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($hasil && $affectedRows > 0) {
        show_sweetalert_and_redirect('Berhasil!', 'Status pemasukan berhasil diubah.', 'success', 'main.php?module=pemasukan');
    } else {
        show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
    }
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

    if (!pemasukan_dimiliki_user($id_pemasukan, $user)) {
        show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
    }

    $query = "DELETE FROM pemasukan WHERE id_pemasukan = ? AND user = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $id_pemasukan, $user);
    $hasil = mysqli_stmt_execute($stmt);
    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($hasil && $affectedRows > 0) {
        show_sweetalert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pemasukan');
    } else {
        show_sweetalert_and_redirect('Gagal!', 'Data pemasukan tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pemasukan');
    }
}

show_sweetalert_and_redirect('Gagal!', 'Aksi pemasukan tidak valid.', 'error', 'main.php?module=pemasukan');
?>
