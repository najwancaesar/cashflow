<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";

function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

function is_valid_tipe_kategori($tipeKategori) {
    return in_array($tipeKategori, ['pemasukan', 'pengeluaran'], true);
}

function kategori_dimiliki_user($idKategori, $userId) {
    global $con;

    $query = "SELECT id_kategori FROM kategori WHERE id_kategori = ? AND user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idKategori, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kategori = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return is_array($kategori);
}

function kategori_duplikat($namaKategori, $tipeKategori, $userId, $excludeId = null) {
    global $con;

    $query = "SELECT id_kategori
              FROM kategori
              WHERE nama_kategori = ? AND tipe_kategori = ? AND user_id = ?";

    if ($excludeId !== null) {
        $query .= " AND id_kategori != ?";
    }

    $query .= " LIMIT 1";

    $stmt = mysqli_prepare($con, $query);

    if ($excludeId !== null) {
        mysqli_stmt_bind_param($stmt, "ssii", $namaKategori, $tipeKategori, $userId, $excludeId);
    } else {
        mysqli_stmt_bind_param($stmt, "ssi", $namaKategori, $tipeKategori, $userId);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kategori = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return is_array($kategori);
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola kategori transaksi.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    $idKategori = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
        ? (int) clean_input($_POST['id_kategori'])
        : null;
    $namaKategori = clean_input($_POST['nama_kategori'] ?? '');
    $tipeKategori = clean_input($_POST['tipe_kategori'] ?? '');

    if ($namaKategori === '' || $tipeKategori === '') {
        show_sweetalert_and_redirect('Gagal!', 'Nama kategori dan tipe kategori wajib diisi.', 'error', 'main.php?module=kategori');
    }

    if (!is_valid_tipe_kategori($tipeKategori)) {
        show_sweetalert_and_redirect('Gagal!', 'Tipe kategori tidak valid.', 'error', 'main.php?module=kategori');
    }

    if ($idKategori !== null && !kategori_dimiliki_user($idKategori, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Kategori yang ingin diubah tidak ditemukan.', 'warning', 'main.php?module=kategori');
    }

    if (kategori_duplikat($namaKategori, $tipeKategori, $userId, $idKategori)) {
        show_sweetalert_and_redirect('Gagal!', 'Kategori dengan nama dan tipe yang sama sudah ada.', 'warning', 'main.php?module=kategori');
    }

    if ($idKategori === null) {
        $query = "INSERT INTO kategori (user_id, nama_kategori, tipe_kategori) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "iss", $userId, $namaKategori, $tipeKategori);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($result) {
            show_sweetalert_and_redirect('Berhasil!', 'Kategori berhasil ditambahkan.', 'success', 'main.php?module=kategori');
        }

        show_sweetalert_and_redirect('Gagal!', 'Kategori gagal ditambahkan.', 'error', 'main.php?module=kategori');
    }

    $query = "UPDATE kategori
              SET nama_kategori = ?, tipe_kategori = ?
              WHERE id_kategori = ? AND user_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ssii", $namaKategori, $tipeKategori, $idKategori, $userId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($result) {
        show_sweetalert_and_redirect('Berhasil!', 'Kategori berhasil diubah.', 'success', 'main.php?module=kategori');
    }

    show_sweetalert_and_redirect('Gagal!', 'Kategori gagal diubah.', 'error', 'main.php?module=kategori');
}

if ($act === 'h') {
    $idKategori = isset($_GET['id']) ? (int) clean_input($_GET['id']) : 0;

    if ($idKategori <= 0) {
        show_sweetalert_and_redirect('Gagal!', 'ID kategori tidak valid.', 'error', 'main.php?module=kategori');
    }

    if (!kategori_dimiliki_user($idKategori, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Kategori yang ingin dihapus tidak ditemukan.', 'warning', 'main.php?module=kategori');
    }

    $query = "DELETE FROM kategori WHERE id_kategori = ? AND user_id = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idKategori, $userId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($result) {
        show_sweetalert_and_redirect('Berhasil!', 'Kategori berhasil dihapus.', 'success', 'main.php?module=kategori');
    }

    show_sweetalert_and_redirect('Gagal!', 'Kategori gagal dihapus.', 'error', 'main.php?module=kategori');
}

show_sweetalert_and_redirect('Gagal!', 'Aksi kategori tidak valid.', 'error', 'main.php?module=kategori');
?>
