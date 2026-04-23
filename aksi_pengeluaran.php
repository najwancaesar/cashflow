<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";

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
            $tanggal = clean_input($_POST['tanggal'] ?? '');
            $catatan = clean_input($_POST['catatan'] ?? '');
            $jumlah = nominal_input_to_number($_POST['jumlah'] ?? '');
            $status = clean_input($_POST['status'] ?? 'pending');
            $kategoriId = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== ''
                ? (int) clean_input($_POST['id_kategori'])
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

            if (empty($_POST['id_pengeluaran'])) {
                $query = "INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status, id_kategori)
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "ssdisi", $tanggal, $catatan, $jumlah, $user, $status, $validatedKategoriId);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pengeluaran');
                }

                $errorMessage = mysqli_error($con);
                mysqli_stmt_close($stmt);
                show_sweetalert_and_redirect('Gagal!', 'Gagal menambahkan data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            } else {
                $id_pengeluaran = (int) clean_input($_POST['id_pengeluaran']);
                if (!pengeluaran_dimiliki_user($id_pengeluaran, $user)) {
                    show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
                }

                $query = "UPDATE pengeluaran 
                          SET tanggal = ?, status = ?, catatan = ?, jumlah = ?, id_kategori = ?
                          WHERE id_pengeluaran = ? AND user = ?";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "sssdiii", $tanggal, $status, $catatan, $jumlah, $validatedKategoriId, $id_pengeluaran, $user);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
                }

                $errorMessage = mysqli_error($con);
                mysqli_stmt_close($stmt);
                show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            }
            break;

        case 'l':
            $id_pengeluaran = (int) clean_input($_GET['id'] ?? '');

            if (empty($id_pengeluaran)) {
                show_sweetalert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $query = "UPDATE pengeluaran 
                      SET status = 'selesai' 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id_pengeluaran, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affectedRows > 0) {
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
                }

                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            $errorMessage = mysqli_error($con);
            mysqli_stmt_close($stmt);
            show_sweetalert_and_redirect('Gagal!', 'Gagal mengubah status: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            break;

        case 'h':
            $id = (int) clean_input($_GET['id'] ?? '');

            if (empty($id)) {
                show_sweetalert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $query = "DELETE FROM pengeluaran 
            WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id, $_SESSION['id_user']);
            if (mysqli_stmt_execute($stmt)) {
                $affectedRows = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);
                if ($affectedRows > 0) {
                    show_sweetalert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pengeluaran');
                }

                show_sweetalert_and_redirect('Gagal!', 'Data pengeluaran tidak ditemukan atau bukan milik Anda.', 'error', 'main.php?module=pengeluaran');
            }

            $errorMessage = mysqli_error($con);
            mysqli_stmt_close($stmt);
            show_sweetalert_and_redirect('Gagal!', 'Gagal menghapus data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            break;

        default:
            show_sweetalert_and_redirect('Gagal!', 'Aksi tidak valid!', 'error', 'main.php?module=pengeluaran');
    }
} else {
    show_sweetalert_and_redirect('Gagal!', 'Tidak ada aksi yang diterima.', 'error', 'main.php?module=pengeluaran');
}
?>
