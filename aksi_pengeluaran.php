<?php
session_start();
include "includes/koneksi.php";

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

// Validasi user sudah login
if (!isset($_SESSION['id_user'])) {
    echo "<script>window.alert('Silakan login terlebih dahulu');
          window.location=('login.php')</script>";
    exit;
}

if (isset($_GET['act'])) {
    $action = $_GET['act'];

    switch ($action) {
        case 't': // Tambah atau Update
            $tanggal = clean_input($_POST['tanggal'] ?? '');
            $catatan = clean_input($_POST['catatan'] ?? '');
            $jumlah = clean_input($_POST['jumlah'] ?? '');
            $user = $_SESSION['id_user'];
            $status = clean_input($_POST['status'] ?? 'pending');

            if (empty($tanggal) || empty($jumlah)) {
                echo "<script>window.alert('Semua field harus diisi!');
                      window.location=('main.php?module=pengeluaran')</script>";
                exit;
            }

            if (!strtotime($tanggal)) {
                echo "<script>window.alert('Format tanggal tidak valid!');
                      window.location=('main.php?module=pengeluaran')</script>";
                exit;
            }

            if (!is_numeric($jumlah)) {
                echo "<script>window.alert('Jumlah harus berupa angka!');
                      window.location=('main.php?module=pengeluaran')</script>";
                exit;
            }

            if (empty($_POST['id_pengeluaran'])) {
                $query = "INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "ssdis", $tanggal, $catatan, $jumlah, $user, $status);

                if (mysqli_stmt_execute($stmt)) {
                    echo "<script>window.alert('Data Berhasil Ditambahkan');
                          window.location=('main.php?module=pengeluaran')</script>";
                } else {
                    echo "<script>window.alert('Gagal menambahkan data: " . mysqli_error($con) . "');
                          window.location=('main.php?module=pengeluaran')</script>";
                }
            } else {
                $id_pengeluaran = clean_input($_POST['id_pengeluaran']);

                $query = "UPDATE pengeluaran 
                          SET tanggal = ?, status = ?, catatan = ?, jumlah = ? 
                          WHERE id_pengeluaran = ? AND user = ?";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "sssdis", $tanggal, $status, $catatan, $jumlah, $id_pengeluaran, $user);

                if (mysqli_stmt_execute($stmt)) {
                    echo "<script>window.alert('Data Berhasil Diubah');
                          window.location=('main.php?module=pengeluaran')</script>";
                } else {
                    echo "<script>window.alert('Gagal mengubah data: " . mysqli_error($con) . "');
                          window.location=('main.php?module=pengeluaran')</script>";
                }
            }
            break;

        case 'l': // Update status menjadi selesai
            $id_pengeluaran = clean_input($_GET['id'] ?? '');

            if (empty($id_pengeluaran)) {
                echo "<script>window.alert('ID tidak valid!');
                      window.location=('main.php?module=pengeluaran')</script>";
                exit;
            }

            $query = "UPDATE pengeluaran 
                      SET status = 'selesai' 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id_pengeluaran, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>window.alert('Status Berhasil Diubah');
                      window.location=('main.php?module=pengeluaran')</script>";
            } else {
                echo "<script>window.alert('Gagal mengubah status: " . mysqli_error($con) . "');
                      window.location=('main.php?module=pengeluaran')</script>";
            }
            break;

        case 'h': // Hapus data
            $id = clean_input($_GET['id'] ?? '');

            if (empty($id)) {
                echo "<script>window.alert('ID tidak valid!');
                      window.location=('main.php?module=pengeluaran')</script>";
                exit;
            }

            $query = "DELETE FROM pengeluaran 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>window.alert('Data Berhasil Dihapus');
                      window.location=('main.php?module=pengeluaran')</script>";
            } else {
                echo "<script>window.alert('Gagal menghapus data: " . mysqli_error($con) . "');
                      window.location=('main.php?module=pengeluaran')</script>";
            }
            break;

        default:
            echo "<script>window.alert('Aksi tidak valid!');
                  window.location=('main.php?module=pengeluaran')</script>";
    }
} else {
    echo "<script>window.location=('main.php?module=pengeluaran')</script>";
}
