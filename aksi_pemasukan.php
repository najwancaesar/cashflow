<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

$act = $_GET['act'] ?? '';

if ($act == 't') {
    $tanggal = clean_input($_POST['tanggal']);
    $catatan = clean_input($_POST['catatan']);
    $jumlah = clean_input($_POST['jumlah']);
    $user = (int) $_SESSION['id_user'];
    $status = clean_input($_POST['status']);

    if ($_POST['id_pemasukan'] == '') {
        $query = "INSERT INTO pemasukan(tanggal, catatan, status, jumlah, user) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssdi", $tanggal, $catatan, $status, $jumlah, $user);
        $hasil = mysqli_stmt_execute($stmt);

        if ($hasil) {
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil ditambahkan',
                        icon: 'success'
                    }).then(() => {
                        window.location = 'main.php?module=pemasukan';
                    });
                });
            </script>
            <?php
        } else {
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal menambahkan data',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pemasukan';
                    });
                });
            </script>
            <?php
        }
    } else {
        $id_pemasukan = (int) clean_input($_POST['id_pemasukan']);
        $query = "UPDATE pemasukan 
                 SET tanggal = ?, status = ?, catatan = ?, jumlah = ? 
                 WHERE id_pemasukan = ? AND user = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssdii", $tanggal, $status, $catatan, $jumlah, $id_pemasukan, $user);
        $hasil = mysqli_stmt_execute($stmt);

        if ($hasil) {
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil diubah',
                        icon: 'success'
                    }).then(() => {
                        window.location = 'main.php?module=pemasukan';
                    });
                });
            </script>
            <?php
        } else {
            ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal mengubah data',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pemasukan';
                    });
                });
            </script>
            <?php
        }
    }
}

if ($act == 'l') {
    $id_pemasukan = (int) clean_input($_GET['id']);
    $query = "UPDATE pemasukan SET status = 'selesai' WHERE id_pemasukan = ? AND user = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $id_pemasukan, $user);
    $hasil = mysqli_stmt_execute($stmt);

    if ($hasil) {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data berhasil diubah',
                    icon: 'success'
                }).then(() => {
                    window.location = 'main.php?module=pemasukan';
                });
            });
        </script>
        <?php
    } else {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Gagal mengubah status',
                    icon: 'error'
                }).then(() => {
                    window.location = 'main.php?module=pemasukan';
                });
            });
        </script>
        <?php
    }
}

if ($act == 'h') {
    $id = (int) clean_input($_GET['id']);
    $query = "DELETE FROM pemasukan WHERE id_pemasukan = ? AND user = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "ii", $id, $user);
    $hasil = mysqli_stmt_execute($stmt);

    if ($hasil) {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Data berhasil dihapus',
                    icon: 'success'
                }).then(() => {
                    window.location = 'main.php?module=pemasukan';
                });
            });
        </script>
        <?php
    } else {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Gagal menghapus data',
                    icon: 'error'
                }).then(() => {
                    window.location = 'main.php?module=pemasukan';
                });
            });
        </script>
        <?php
    }
}
?>
