<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";

$act = $_GET['act'] ?? '';
$currentUserId = (int) ($_SESSION['id_user'] ?? 0);
$currentUserRole = strtolower((string) ($_SESSION['role'] ?? ''));
$isAdmin = $currentUserRole === 'admin';

function require_authenticated_user($currentUserId)
{
    if ($currentUserId <= 0) {
        show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
    }
}

function require_admin_user($isAdmin)
{
    if (!$isAdmin) {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi ini hanya dapat dilakukan oleh admin.', 'error', 'main.php?module=home');
    }
}

function require_account_owner($targetUserId, $currentUserId)
{
    if ($targetUserId <= 0 || $targetUserId !== $currentUserId) {
        show_sweetalert_and_redirect('Akses ditolak', 'Anda hanya dapat mengubah akun milik sendiri.', 'error', 'main.php?module=profile');
    }
}

function username_or_email_exists($con, $username, $email, $excludeUserId = 0)
{
    if ($excludeUserId > 0) {
        $stmt = $con->prepare("SELECT id_user FROM user WHERE (username = ? OR email = ?) AND id_user <> ? LIMIT 1");
        $stmt->bind_param("ssi", $username, $email, $excludeUserId);
    } else {
        $stmt = $con->prepare("SELECT id_user FROM user WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

require_authenticated_user($currentUserId);

if ($act == 't') {
    require_admin_user($isAdmin);

    if (($_POST['konfirmasi_password'] ?? '') == ($_POST['password'] ?? '')) {
        $username = trim($_POST['username'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');
        $password = password_hash((string) $_POST['password'], PASSWORD_DEFAULT);
        $role = 'user';
        $foto = 'default.png';
        $is_active = '1';

        if (username_or_email_exists($con, $username, $email)) {
            show_sweetalert_and_redirect('Data sudah dipakai', 'Username atau email sudah terdaftar.', 'warning', 'main.php?module=pengguna');
        }

        $stmt = $con->prepare("INSERT INTO user(username, nama, email, no_telp, role, password, foto, is_active) VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $nama, $email, $no_telp, $role, $password, $foto, $is_active);
        $stmt->execute();
        $stmt->close();

        show_sweetalert_and_redirect('Berhasil', 'Pengguna baru berhasil ditambahkan.', 'success', 'main.php?module=pengguna');
    }

    show_sweetalert_and_redirect('Konfirmasi gagal', 'Password dan konfirmasi password tidak sesuai.', 'error', 'main.php?module=pengguna');
}

if ($act == 'e') {
    $id_user = (int) ($_POST['id_user'] ?? 0);
    require_account_owner($id_user, $currentUserId);

    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');

    if (username_or_email_exists($con, $username, $email, $id_user)) {
        show_sweetalert_and_redirect('Data sudah dipakai', 'Username atau email sudah digunakan akun lain.', 'warning', "main.php?module=profile&id=$id_user");
    }

    if (!empty($_FILES['foto']['name'])) {
        $foto = $_FILES['foto']['name'];
        $lokasi = $_FILES['foto']['tmp_name'];
        $ukuran = (int) ($_FILES['foto']['size'] ?? 0);

        $ekstensi = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
        $valid_file = ['jpeg', 'jpg', 'png'];

        if (in_array($ekstensi, $valid_file, true) == 0) {
            show_sweetalert_and_redirect('File tidak valid', 'Gunakan file dengan format JPG, JPEG, atau PNG.', 'warning', "main.php?module=profile&id=$id_user");
        } elseif ($ukuran > 50000000) {
            show_sweetalert_and_redirect('Ukuran terlalu besar', 'Ukuran file profil melebihi batas yang diizinkan.', 'warning', "main.php?module=profile&id=$id_user");
        } else {
            $namafile = time() . "_" . $foto;
            move_uploaded_file($lokasi, "assets/img/profil/" . $namafile);

            $stmt = $con->prepare("UPDATE user SET username = ?, nama = ?, email = ?, no_telp = ?, foto = ? WHERE id_user = ?");
            $stmt->bind_param("sssssi", $username, $nama, $email, $no_telp, $namafile, $id_user);
            $stmt->execute();
            $stmt->close();

            $_SESSION['username'] = $username;
            $_SESSION['nama'] = $nama;
            $_SESSION['foto'] = $namafile;

            show_sweetalert_and_redirect('Berhasil', 'Profil berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
        }
    }

    $stmt = $con->prepare("UPDATE user SET username = ?, nama = ?, email = ?, no_telp = ? WHERE id_user = ?");
    $stmt->bind_param("ssssi", $username, $nama, $email, $no_telp, $id_user);
    $stmt->execute();
    $stmt->close();

    $_SESSION['username'] = $username;
    $_SESSION['nama'] = $nama;

    show_sweetalert_and_redirect('Berhasil', 'Profil berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
}

if ($act == 'p') {
    $id_user = (int) ($_POST['id_user'] ?? 0);
    require_account_owner($id_user, $currentUserId);

    if (($_POST['password_baru'] ?? '') == ($_POST['konfirmasi_password'] ?? '')) {
        $password = password_hash((string) $_POST['password_baru'], PASSWORD_DEFAULT);

        $stmt = $con->prepare("UPDATE user SET password = ? WHERE id_user = ?");
        $stmt->bind_param("si", $password, $id_user);
        $stmt->execute();
        $stmt->close();

        show_sweetalert_and_redirect('Berhasil', 'Password berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
    }

    show_sweetalert_and_redirect('Konfirmasi gagal', 'Password baru dan konfirmasi password tidak sesuai.', 'error', "main.php?module=profile&id=$id_user");
}

if ($act == 'a') {
    require_admin_user($isAdmin);

    $id = (int) ($_GET['id'] ?? 0);
    $statusAktif = '1';
    $stmt = $con->prepare("SELECT id_user FROM user WHERE id_user = ? AND is_active = ? LIMIT 1");
    $stmt->bind_param("is", $id, $statusAktif);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result && $result->num_rows > 0) {
        $stmt = $con->prepare("UPDATE user SET is_active = ? WHERE id_user = ?");
        $stmt->bind_param("si", $statusAktif, $id);
        $stmt->execute();
        $stmt->close();
    }

    show_sweetalert_and_redirect('Berhasil', 'Status pengguna berhasil diperbarui.', 'success', 'main.php?module=pengguna');
}

if ($act == 'h') {
    require_admin_user($isAdmin);

    $id = (int) ($_GET['id'] ?? 0);

    $stmt = $con->prepare("DELETE FROM user WHERE id_user = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    show_sweetalert_and_redirect('Berhasil', 'Pengguna berhasil dihapus.', 'success', 'main.php?module=pengguna');
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan yang Anda kirim tidak dikenali.', 'error', 'main.php?module=home');

?>
