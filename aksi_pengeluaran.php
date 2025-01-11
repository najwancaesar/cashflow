
<?php
ob_start(); // Mulai output buffering
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
    echo "<script>
        Swal.fire({
            title: 'Gagal!',
            text: 'Silakan login terlebih dahulu.',
            icon: 'error'
        }).then(() => {
            window.location = 'login.php';
        });
    </script>";
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
                echo "<script>
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Semua field harus diisi!',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
                exit;
            }

            if (empty($_POST['id_pengeluaran'])) {
                $query = "INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "ssdis", $tanggal, $catatan, $jumlah, $user, $status);

                if (mysqli_stmt_execute($stmt)) {
                    echo "<script>
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Data berhasil ditambahkan.',
                            icon: 'success'
                        }).then(() => {
                            window.location = 'main.php?module=pengeluaran';
                        });
                    </script>";
                } else {
                    echo "<script>
                        Swal.fire({
                            title: 'Gagal!',
                            text: 'Gagal menambahkan data: " . mysqli_error($con) . "',
                            icon: 'error'
                        }).then(() => {
                            window.location = 'main.php?module=pengeluaran';
                        });
                    </script>";
                }
            } else {
                $id_pengeluaran = clean_input($_POST['id_pengeluaran']);

                $query = "UPDATE pengeluaran 
                          SET tanggal = ?, status = ?, catatan = ?, jumlah = ? 
                          WHERE id_pengeluaran = ? AND user = ?";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "sssdis", $tanggal, $status, $catatan, $jumlah, $id_pengeluaran, $user);

                if (mysqli_stmt_execute($stmt)) {
                    echo "<script>
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Data berhasil diubah.',
                            icon: 'success'
                        }).then(() => {
                            window.location = 'main.php?module=pengeluaran';
                        });
                    </script>";
                } else {
                    echo "<script>
                        Swal.fire({
                            title: 'Gagal!',
                            text: 'Gagal mengubah data: " . mysqli_error($con) . "',
                            icon: 'error'
                        }).then(() => {
                            window.location = 'main.php?module=pengeluaran';
                        });
                    </script>";
                }
            }
            break;

        case 'l': // Update status menjadi selesai
            $id_pengeluaran = clean_input($_GET['id'] ?? '');

            if (empty($id_pengeluaran)) {
                echo "<script>
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'ID tidak valid!',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
                exit;
            }

            $query = "UPDATE pengeluaran 
                      SET status = 'selesai' 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id_pengeluaran, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil diubah.',
                        icon: 'success'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
            } else {
                echo "<script>
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal mengubah status: " . mysqli_error($con) . "',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
            }
            break;

        case 'h': // Hapus data
            $id = clean_input($_GET['id'] ?? '');

            if (empty($id)) {
                echo "<script>
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'ID tidak valid!',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
                exit;
            }

            $query = "DELETE FROM pengeluaran 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                echo "<script>
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil dihapus.',
                        icon: 'success'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
            } else {
                echo "<script>
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Gagal menghapus data: " . mysqli_error($con) . "',
                        icon: 'error'
                    }).then(() => {
                        window.location = 'main.php?module=pengeluaran';
                    });
                </script>";
            }
            break;

        default:
            echo "<script>
                Swal.fire({
                    title: 'Gagal!',
                    text: 'Aksi tidak valid!',
                    icon: 'error'
                }).then(() => {
                    window.location = 'main.php?module=pengeluaran';
                });
            </script>";
    }
} else {
    echo "<script>
        Swal.fire({
            title: 'Gagal!',
            text: 'Tidak ada aksi yang diterima.',
            icon: 'error'
        }).then(() => {
            window.location = 'main.php?module=pengeluaran';
        });
    </script>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Data berhasil di ubah.',
                icon: 'success'
            }).then(() => {
                window.location = 'main.php?module=pengeluaran';
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Akhiri output buffering
?>