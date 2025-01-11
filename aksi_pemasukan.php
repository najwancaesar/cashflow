<?php
include "includes/koneksi.php";

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

if ($_GET['act'] == 't') {
    $tanggal = clean_input($_POST['tanggal']);
    $catatan = clean_input($_POST['catatan']);
    $jumlah = clean_input($_POST['jumlah']);
    $user = clean_input($_POST['user']);
    $status = clean_input($_POST['status']);
    // $aksi = clean_input($_POST['aksi']);
    $idUser = clean_input($_POST['user']);

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
        $id_pemasukan = clean_input($_POST['id_pemasukan']);
        $query = "UPDATE pemasukan 
                 SET tanggal = ?, status = ?, catatan = ?, jumlah = ? 
                 WHERE id_pemasukan = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, "sssdi", $tanggal, $status, $catatan, $jumlah, $id_pemasukan);
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

if ($_GET['act'] == 'l') {
    $id_pemasukan = clean_input($_GET['id']);
    $query = "UPDATE pemasukan SET status = 'selesai' WHERE id_pemasukan = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_pemasukan);
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

if ($_GET['act'] == 'h') {
    $id = clean_input($_GET['id']);
    $query = "DELETE FROM pemasukan WHERE id_pemasukan = ?";
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
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