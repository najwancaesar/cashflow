<?php
session_start();
include "includes/koneksi.php";

function clean_input($data) {
    global $con;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($con, $data);
}

function show_alert_and_redirect($title, $text, $icon, $redirect) {
    $titleJson = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $textJson = json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $iconJson = json_encode($icon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $redirectJson = json_encode($redirect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
    echo "<script>
        (function () {
            var title = {$titleJson};
            var text = {$textJson};
            var icon = {$iconJson};
            var redirectTo = {$redirectJson};

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon
                }).then(function () {
                    window.location.href = redirectTo;
                });
            } else {
                alert(title + '\\n' + text);
                window.location.href = redirectTo;
            }
        })();
    </script>";
    echo '</body></html>';
    exit;
}

if (!isset($_SESSION['id_user'])) {
    show_alert_and_redirect('Gagal!', 'Silakan login terlebih dahulu.', 'error', 'login.php');
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
                show_alert_and_redirect('Gagal!', 'Semua field harus diisi!', 'error', 'main.php?module=pengeluaran');
            }

            if (empty($_POST['id_pengeluaran'])) {
                $query = "INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "ssdis", $tanggal, $catatan, $jumlah, $user, $status);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    show_alert_and_redirect('Berhasil!', 'Data berhasil ditambahkan.', 'success', 'main.php?module=pengeluaran');
                }

                $errorMessage = mysqli_error($con);
                mysqli_stmt_close($stmt);
                show_alert_and_redirect('Gagal!', 'Gagal menambahkan data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            } else {
                $id_pengeluaran = (int) clean_input($_POST['id_pengeluaran']);

                $query = "UPDATE pengeluaran 
                          SET tanggal = ?, status = ?, catatan = ?, jumlah = ? 
                          WHERE id_pengeluaran = ? AND user = ?";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, "sssdii", $tanggal, $status, $catatan, $jumlah, $id_pengeluaran, $user);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    show_alert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
                }

                $errorMessage = mysqli_error($con);
                mysqli_stmt_close($stmt);
                show_alert_and_redirect('Gagal!', 'Gagal mengubah data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            }
            break;

        case 'l':
            $id_pengeluaran = (int) clean_input($_GET['id'] ?? '');

            if (empty($id_pengeluaran)) {
                show_alert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $query = "UPDATE pengeluaran 
                      SET status = 'selesai' 
                      WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id_pengeluaran, $_SESSION['id_user']);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                show_alert_and_redirect('Berhasil!', 'Data berhasil diubah.', 'success', 'main.php?module=pengeluaran');
            }

            $errorMessage = mysqli_error($con);
            mysqli_stmt_close($stmt);
            show_alert_and_redirect('Gagal!', 'Gagal mengubah status: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            break;

        case 'h':
            $id = (int) clean_input($_GET['id'] ?? '');

            if (empty($id)) {
                show_alert_and_redirect('Gagal!', 'ID tidak valid!', 'error', 'main.php?module=pengeluaran');
            }

            $query = "DELETE FROM pengeluaran 
            WHERE id_pengeluaran = ? AND user = ?";
            $stmt = mysqli_prepare($con, $query);
            mysqli_stmt_bind_param($stmt, "ii", $id, $_SESSION['id_user']);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                show_alert_and_redirect('Berhasil!', 'Data berhasil dihapus.', 'success', 'main.php?module=pengeluaran');
            }

            $errorMessage = mysqli_error($con);
            mysqli_stmt_close($stmt);
            show_alert_and_redirect('Gagal!', 'Gagal menghapus data: ' . $errorMessage, 'error', 'main.php?module=pengeluaran');
            break;

        default:
            show_alert_and_redirect('Gagal!', 'Aksi tidak valid!', 'error', 'main.php?module=pengeluaran');
    }
} else {
    show_alert_and_redirect('Gagal!', 'Tidak ada aksi yang diterima.', 'error', 'main.php?module=pengeluaran');
}
?>
