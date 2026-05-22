<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/default_categories.php";
include "includes/avatar_helper.php";
include "includes/csrf_helper.php";
include_once "includes/activity_log_helper.php";

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

function clean_text($value)
{
    return trim((string) $value);
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

function fetch_user_by_id($con, $userId)
{
    $stmt = $con->prepare("SELECT * FROM user WHERE id_user = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function validate_role_value($role)
{
    return in_array($role, ['admin', 'user'], true);
}

function validate_active_value($status)
{
    return in_array($status, ['0', '1'], true);
}

function block_admin_self_management($targetUserId, $currentUserId, $redirect)
{
    if ($targetUserId === $currentUserId) {
        show_sweetalert_and_redirect('Aksi dibatasi', 'Gunakan halaman profil pribadi untuk mengelola akun admin Anda sendiri.', 'warning', $redirect);
    }
}

function require_post_csrf_user($redirect)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
        show_sweetalert_and_redirect('Akses ditolak', 'Permintaan tidak valid atau sesi form sudah kedaluwarsa.', 'error', $redirect);
    }
}

function upload_error_message($errorCode)
{
    switch ((int) $errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Ukuran file profil melebihi batas yang diizinkan.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload file profil tidak lengkap. Silakan coba lagi.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Upload file profil gagal diproses. Silakan coba lagi.';
        default:
            return 'Upload file profil tidak valid.';
    }
}

function generate_profile_photo_filename($extension)
{
    try {
        $randomName = bin2hex(random_bytes(16));
    } catch (Exception $exception) {
        $randomName = str_replace('.', '', uniqid('profile_', true));
    }

    return $randomName . '.' . $extension;
}

require_authenticated_user($currentUserId);

if ($act === 't') {
    require_post_csrf_user('main.php?module=pengguna');
    require_admin_user($isAdmin);

    $username = clean_text($_POST['username'] ?? '');
    $nama = clean_text($_POST['nama'] ?? '');
    $email = clean_text($_POST['email'] ?? '');
    $no_telp = clean_text($_POST['no_telp'] ?? '');
    $passwordRaw = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['konfirmasi_password'] ?? '');
    $role = strtolower(clean_text($_POST['role'] ?? 'user'));
    $isActive = clean_text($_POST['is_active'] ?? '1');
    $foto = default_avatar_filename();

    if ($username === '' || $nama === '' || $email === '' || $no_telp === '' || $passwordRaw === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Semua field user wajib diisi.', 'warning', 'main.php?module=pengguna');
    }

    if ($passwordRaw !== $passwordConfirm) {
        show_sweetalert_and_redirect('Konfirmasi gagal', 'Password dan konfirmasi password tidak sesuai.', 'error', 'main.php?module=pengguna');
    }

    if (!validate_role_value($role) || !validate_active_value($isActive)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Role atau status akun tidak valid.', 'error', 'main.php?module=pengguna');
    }

    if (username_or_email_exists($con, $username, $email)) {
        show_sweetalert_and_redirect('Data sudah dipakai', 'Username atau email sudah terdaftar.', 'warning', 'main.php?module=pengguna');
    }

    $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
    $stmt = $con->prepare("INSERT INTO user(username, nama, email, no_telp, role, password, foto, is_active, last_profile_update_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssss", $username, $nama, $email, $no_telp, $role, $password, $foto, $isActive);
    $stmt->execute();
    $newUserId = (int) $stmt->insert_id;
    $stmt->close();

    if ($role === 'user') {
        seed_default_categories_for_user($con, $newUserId);
    }

    record_activity($con, 'pengguna', 'tambah_user', "Menambahkan user ID {$newUserId}.");
    show_sweetalert_and_redirect('Berhasil', 'Pengguna baru berhasil ditambahkan.', 'success', 'main.php?module=pengguna');
}

if ($act === 'u') {
    require_post_csrf_user('main.php?module=pengguna');
    require_admin_user($isAdmin);

    $targetUserId = (int) ($_POST['id_user'] ?? 0);
    block_admin_self_management($targetUserId, $currentUserId, 'main.php?module=pengguna');

    $targetUser = fetch_user_by_id($con, $targetUserId);
    if (!$targetUser) {
        show_sweetalert_and_redirect('User tidak ditemukan', 'Data user yang ingin diubah tidak ditemukan.', 'error', 'main.php?module=pengguna');
    }

    $username = clean_text($_POST['username'] ?? '');
    $nama = clean_text($_POST['nama'] ?? '');
    $email = clean_text($_POST['email'] ?? '');
    $no_telp = clean_text($_POST['no_telp'] ?? '');
    $role = strtolower(clean_text($_POST['role'] ?? 'user'));
    $isActive = clean_text($_POST['is_active'] ?? '1');

    if ($username === '' || $nama === '' || $email === '' || $no_telp === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Semua field edit user wajib diisi.', 'warning', 'main.php?module=pengguna');
    }

    if (!validate_role_value($role) || !validate_active_value($isActive)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Role atau status akun tidak valid.', 'error', 'main.php?module=pengguna');
    }

    if (username_or_email_exists($con, $username, $email, $targetUserId)) {
        show_sweetalert_and_redirect('Data sudah dipakai', 'Username atau email sudah digunakan akun lain.', 'warning', 'main.php?module=pengguna');
    }

    $stmt = $con->prepare("UPDATE user SET username = ?, nama = ?, email = ?, no_telp = ?, role = ?, is_active = ?, last_profile_update_at = NOW() WHERE id_user = ?");
    $stmt->bind_param("ssssssi", $username, $nama, $email, $no_telp, $role, $isActive, $targetUserId);
    $stmt->execute();
    $stmt->close();

    record_activity($con, 'pengguna', 'edit_user', "Mengubah user ID {$targetUserId}.");
    show_sweetalert_and_redirect('Berhasil', 'Data user berhasil diperbarui.', 'success', "main.php?module=pengguna&detail={$targetUserId}");
}

if ($act === 's') {
    require_post_csrf_user('main.php?module=pengguna');
    require_admin_user($isAdmin);

    $targetUserId = (int) ($_POST['id_user'] ?? 0);
    $isActive = clean_text($_POST['value'] ?? '');
    block_admin_self_management($targetUserId, $currentUserId, 'main.php?module=pengguna');

    if ($targetUserId <= 0 || !validate_active_value($isActive)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Permintaan status akun tidak valid.', 'error', 'main.php?module=pengguna');
    }

    if (!fetch_user_by_id($con, $targetUserId)) {
        show_sweetalert_and_redirect('User tidak ditemukan', 'User yang ingin diubah tidak ditemukan.', 'error', 'main.php?module=pengguna');
    }

    $stmt = $con->prepare("UPDATE user SET is_active = ?, last_profile_update_at = NOW() WHERE id_user = ?");
    $stmt->bind_param("si", $isActive, $targetUserId);
    $stmt->execute();
    $stmt->close();

    $statusLabel = $isActive === '1' ? 'aktif' : 'nonaktif';
    record_activity($con, 'pengguna', 'ubah_status_user', "Mengubah status user ID {$targetUserId} menjadi {$statusLabel}.");
    show_sweetalert_and_redirect('Berhasil', 'Status akun berhasil diperbarui.', 'success', "main.php?module=pengguna&detail={$targetUserId}");
}

if ($act === 'r') {
    require_post_csrf_user('main.php?module=pengguna');
    require_admin_user($isAdmin);

    $targetUserId = (int) ($_POST['id_user'] ?? 0);
    block_admin_self_management($targetUserId, $currentUserId, 'main.php?module=pengguna');

    $passwordBaru = (string) ($_POST['password_baru'] ?? '');
    $konfirmasi = (string) ($_POST['konfirmasi_password'] ?? '');

    if ($passwordBaru === '' || $konfirmasi === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Password baru dan konfirmasi wajib diisi.', 'warning', 'main.php?module=pengguna');
    }

    if ($passwordBaru !== $konfirmasi) {
        show_sweetalert_and_redirect('Konfirmasi gagal', 'Password baru dan konfirmasi password tidak sesuai.', 'error', 'main.php?module=pengguna');
    }

    if (!fetch_user_by_id($con, $targetUserId)) {
        show_sweetalert_and_redirect('User tidak ditemukan', 'User yang ingin direset password-nya tidak ditemukan.', 'error', 'main.php?module=pengguna');
    }

    $passwordHash = password_hash($passwordBaru, PASSWORD_DEFAULT);
    $stmt = $con->prepare("UPDATE user SET password = ?, last_profile_update_at = NOW() WHERE id_user = ?");
    $stmt->bind_param("si", $passwordHash, $targetUserId);
    $stmt->execute();
    $stmt->close();

    record_activity($con, 'pengguna', 'reset_password_user', "Reset password user ID {$targetUserId}.");
    show_sweetalert_and_redirect('Berhasil', 'Password user berhasil direset.', 'success', "main.php?module=pengguna&detail={$targetUserId}");
}

if ($act === 'e') {
    require_post_csrf_user('main.php?module=profile');
    $id_user = (int) ($_POST['id_user'] ?? 0);
    require_account_owner($id_user, $currentUserId);

    $username = clean_text($_POST['username'] ?? '');
    $nama = clean_text($_POST['nama'] ?? '');
    $email = clean_text($_POST['email'] ?? '');
    $no_telp = clean_text($_POST['no_telp'] ?? '');

    if (username_or_email_exists($con, $username, $email, $id_user)) {
        show_sweetalert_and_redirect('Data sudah dipakai', 'Username atau email sudah digunakan akun lain.', 'warning', "main.php?module=profile&id=$id_user");
    }

    $fotoUpload = $_FILES['foto'] ?? null;
    $uploadError = (int) ($fotoUpload['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
        if ($uploadError !== UPLOAD_ERR_OK) {
            show_sweetalert_and_redirect('Upload gagal', upload_error_message($uploadError), 'warning', "main.php?module=profile&id=$id_user");
        }

        $maxPhotoSize = 2 * 1024 * 1024;
        $ukuran = (int) ($fotoUpload['size'] ?? 0);
        $lokasi = (string) ($fotoUpload['tmp_name'] ?? '');
        $namaAsli = (string) ($fotoUpload['name'] ?? '');

        if ($ukuran <= 0 || $ukuran > $maxPhotoSize) {
            show_sweetalert_and_redirect('Ukuran terlalu besar', 'Ukuran foto profil maksimal 2MB.', 'warning', "main.php?module=profile&id=$id_user");
        }

        if ($lokasi === '' || !is_uploaded_file($lokasi)) {
            show_sweetalert_and_redirect('Upload gagal', 'File profil tidak valid. Silakan coba lagi.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $allowedPhotoTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];
        $ekstensi = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

        if (!array_key_exists($ekstensi, $allowedPhotoTypes)) {
            show_sweetalert_and_redirect('File tidak valid', 'Gunakan file dengan format JPG, JPEG, atau PNG.', 'warning', "main.php?module=profile&id=$id_user");
        }

        if (!function_exists('finfo_open') || !defined('FILEINFO_MIME_TYPE')) {
            show_sweetalert_and_redirect('Upload gagal', 'Validasi file profil tidak tersedia. Silakan hubungi admin.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            show_sweetalert_and_redirect('Upload gagal', 'Validasi file profil gagal. Silakan coba lagi.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $mimeType = finfo_file($finfo, $lokasi);
        finfo_close($finfo);

        if ($mimeType !== $allowedPhotoTypes[$ekstensi]) {
            show_sweetalert_and_redirect('File tidak valid', 'Isi file profil tidak sesuai dengan format gambar yang diizinkan.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profil' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            show_sweetalert_and_redirect('Upload gagal', 'Folder upload profil tidak siap. Silakan hubungi admin.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $namafile = generate_profile_photo_filename($ekstensi);
        $tujuanUpload = $uploadDir . $namafile;

        if (!move_uploaded_file($lokasi, $tujuanUpload)) {
            show_sweetalert_and_redirect('Upload gagal', 'Foto profil gagal disimpan. Silakan coba lagi.', 'warning', "main.php?module=profile&id=$id_user");
        }

        $stmt = $con->prepare("UPDATE user SET username = ?, nama = ?, email = ?, no_telp = ?, foto = ?, last_profile_update_at = NOW() WHERE id_user = ?");
        $stmt->bind_param("sssssi", $username, $nama, $email, $no_telp, $namafile, $id_user);
        $stmt->execute();
        $stmt->close();

        $_SESSION['username'] = $username;
        $_SESSION['nama'] = $nama;
        $_SESSION['foto'] = $namafile;

        show_sweetalert_and_redirect('Berhasil', 'Profil berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
    }

    $stmt = $con->prepare("UPDATE user SET username = ?, nama = ?, email = ?, no_telp = ?, last_profile_update_at = NOW() WHERE id_user = ?");
    $stmt->bind_param("ssssi", $username, $nama, $email, $no_telp, $id_user);
    $stmt->execute();
    $stmt->close();

    $_SESSION['username'] = $username;
    $_SESSION['nama'] = $nama;

    show_sweetalert_and_redirect('Berhasil', 'Profil berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
}

if ($act === 'p') {
    require_post_csrf_user('main.php?module=profile');
    $id_user = (int) ($_POST['id_user'] ?? 0);
    require_account_owner($id_user, $currentUserId);

    $passwordBaru = (string) ($_POST['password_baru'] ?? '');
    $konfirmasiPassword = (string) ($_POST['konfirmasi_password'] ?? '');

    if (trim($passwordBaru) === '' || trim($konfirmasiPassword) === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Password baru dan konfirmasi password wajib diisi.', 'warning', "main.php?module=profile&id=$id_user");
    }

    if ($passwordBaru !== $konfirmasiPassword) {
        show_sweetalert_and_redirect('Konfirmasi gagal', 'Password baru dan konfirmasi password tidak sesuai.', 'error', "main.php?module=profile&id=$id_user");
    }

    if (strlen($passwordBaru) < 6) {
        show_sweetalert_and_redirect('Password terlalu pendek', 'Password baru minimal 6 karakter.', 'warning', "main.php?module=profile&id=$id_user");
    }

    $password = password_hash($passwordBaru, PASSWORD_DEFAULT);

    $stmt = $con->prepare("UPDATE user SET password = ?, last_profile_update_at = NOW() WHERE id_user = ?");
    $stmt->bind_param("si", $password, $id_user);
    $stmt->execute();
    $stmt->close();

    show_sweetalert_and_redirect('Berhasil', 'Password berhasil diperbarui.', 'success', "main.php?module=profile&id=$id_user");
}

if ($act === 'h') {
    require_post_csrf_user('main.php?module=pengguna');
    require_admin_user($isAdmin);

    $targetUserId = (int) ($_POST['id_user'] ?? 0);
    block_admin_self_management($targetUserId, $currentUserId, 'main.php?module=pengguna');

    if ($targetUserId <= 0 || !fetch_user_by_id($con, $targetUserId)) {
        show_sweetalert_and_redirect('User tidak ditemukan', 'Pengguna yang ingin dihapus tidak ditemukan.', 'error', 'main.php?module=pengguna');
    }

    $stmt = $con->prepare("DELETE FROM user WHERE id_user = ?");
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows > 0) {
        record_activity($con, 'pengguna', 'hapus_user', "Menghapus user ID {$targetUserId}.");
        show_sweetalert_and_redirect('Berhasil', 'Pengguna berhasil dihapus.', 'success', 'main.php?module=pengguna');
    }

    show_sweetalert_and_redirect('Gagal', 'Pengguna gagal dihapus.', 'error', 'main.php?module=pengguna');
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan yang Anda kirim tidak dikenali.', 'error', 'main.php?module=home');
?>
