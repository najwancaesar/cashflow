<?php
session_start();
include __DIR__ . '/../includes/koneksi.php';
include __DIR__ . '/../includes/sweetalert_helper.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/activity_log_helper.php';
include_once __DIR__ . '/../includes/wallet_type_helper.php';

function wallet_type_redirect($title, $message, $icon = 'warning')
{
    show_sweetalert_and_redirect($title, $message, $icon, 'main.php?module=wallet');
}

function wallet_type_execute_statement($stmt, &$errorCode = 0)
{
    try {
        $success = $stmt->execute();
        $errorCode = (int) $stmt->errno;
        return $success;
    } catch (Throwable $exception) {
        $errorCode = (int) $exception->getCode();
        error_log('CashFlow wallet type statement failed with code ' . $errorCode . '.');
        return false;
    }
}

function wallet_type_name_exists_for_user($con, $userId, $name, $excludeTypeId = 0)
{
    $stmt = $con->prepare("SELECT 1
                           FROM wallet_type
                           WHERE user_id = ?
                             AND LOWER(TRIM(nama_tipe)) = LOWER(?)
                             AND id_wallet_type <> ?
                           LIMIT 1");
    if (!$stmt) {
        error_log('CashFlow wallet type duplicate-name check could not be prepared.');
        return true;
    }

    $stmt->bind_param('isi', $userId, $name, $excludeTypeId);
    $errorCode = 0;
    $success = wallet_type_execute_statement($stmt, $errorCode);
    $result = $success ? $stmt->get_result() : false;
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return !$success || $exists;
}

function wallet_type_is_owned_by_user($con, $typeId, $userId)
{
    $stmt = $con->prepare("SELECT 1 FROM wallet_type WHERE id_wallet_type = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $typeId, $userId);
    $errorCode = 0;
    $success = wallet_type_execute_statement($stmt, $errorCode);
    $result = $success ? $stmt->get_result() : false;
    $owned = $result && $result->num_rows === 1;
    $stmt->close();

    return $owned;
}

if (!isset($_SESSION['id_user'])) {
    wallet_type_redirect('Login diperlukan', 'Silakan login terlebih dahulu.');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    wallet_type_redirect('Akses dibatasi', 'Admin tidak dapat mengelola tipe wallet user.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    wallet_type_redirect('Akses ditolak', 'Aksi tipe wallet wajib melalui form yang valid.');
}

if (!verify_csrf_token()) {
    wallet_type_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.');
}

if (!cashflow_wallet_type_schema_ready($con)) {
    wallet_type_redirect('Fitur belum aktif', 'Jalankan migration custom tipe wallet Sprint 1 terlebih dahulu.', 'info');
}

$userId = (int) $_SESSION['id_user'];
$act = (string) ($_GET['act'] ?? '');

if ($act === 'save') {
    $typeId = (int) ($_POST['id_wallet_type'] ?? 0);
    $name = trim((string) ($_POST['nama_tipe'] ?? ''));
    $icon = trim((string) ($_POST['icon'] ?? ''));
    $color = strtoupper(trim((string) ($_POST['warna'] ?? '')));

    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($typeId < 0 || $name === '' || $nameLength > 50) {
        wallet_type_redirect('Data tidak valid', 'Nama tipe wajib diisi dan maksimal 50 karakter.', 'error');
    }

    if (!array_key_exists($icon, cashflow_wallet_type_icon_options())) {
        wallet_type_redirect('Data tidak valid', 'Ikon tipe wallet tidak diizinkan.', 'error');
    }

    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
        wallet_type_redirect('Data tidak valid', 'Warna tipe wallet harus menggunakan format hex #RRGGBB.', 'error');
    }

    if ($typeId > 0 && !wallet_type_is_owned_by_user($con, $typeId, $userId)) {
        wallet_type_redirect('Data tidak ditemukan', 'Tipe wallet tidak ditemukan.', 'warning');
    }

    if (wallet_type_name_exists_for_user($con, $userId, $name, $typeId)) {
        wallet_type_redirect('Nama sudah dipakai', 'Gunakan nama tipe wallet lain.', 'warning');
    }

    if ($typeId > 0) {
        $stmt = $con->prepare("UPDATE wallet_type
                               SET nama_tipe = ?, icon = ?, warna = ?, updated_at = NOW()
                               WHERE id_wallet_type = ? AND user_id = ?");
        if (!$stmt) {
            wallet_type_redirect('Gagal', 'Tipe wallet gagal diperbarui.', 'error');
        }
        $stmt->bind_param('sssii', $name, $icon, $color, $typeId, $userId);
        $errorCode = 0;
        $success = wallet_type_execute_statement($stmt, $errorCode);
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($errorCode === 1062) {
            wallet_type_redirect('Nama sudah dipakai', 'Gunakan nama tipe wallet lain.', 'warning');
        }
        if (!$success || $affected < 0) {
            wallet_type_redirect('Gagal', 'Tipe wallet gagal diperbarui.', 'error');
        }

        record_activity($con, 'wallet_type', 'edit', "Mengubah tipe wallet ID {$typeId}.");
        wallet_type_redirect('Berhasil', 'Tipe wallet berhasil diperbarui.', 'success');
    }

    $stmt = $con->prepare("INSERT INTO wallet_type (user_id, nama_tipe, icon, warna, is_active, created_at, updated_at)
                           VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
    if (!$stmt) {
        wallet_type_redirect('Gagal', 'Tipe wallet gagal ditambahkan.', 'error');
    }
    $stmt->bind_param('isss', $userId, $name, $icon, $color);
    $errorCode = 0;
    $success = wallet_type_execute_statement($stmt, $errorCode);
    $newTypeId = (int) $stmt->insert_id;
    $stmt->close();

    if ($errorCode === 1062) {
        wallet_type_redirect('Nama sudah dipakai', 'Gunakan nama tipe wallet lain.', 'warning');
    }
    if (!$success) {
        wallet_type_redirect('Gagal', 'Tipe wallet gagal ditambahkan.', 'error');
    }

    record_activity($con, 'wallet_type', 'tambah', "Menambahkan tipe wallet ID {$newTypeId}.");
    wallet_type_redirect('Berhasil', 'Tipe wallet berhasil ditambahkan.', 'success');
}

if ($act === 'status') {
    $typeId = (int) ($_POST['id_wallet_type'] ?? 0);
    $isActive = (string) ($_POST['is_active'] ?? '');
    if ($typeId <= 0 || !in_array($isActive, ['0', '1'], true)) {
        wallet_type_redirect('Data tidak valid', 'Permintaan status tipe wallet tidak valid.', 'error');
    }

    $isActiveInt = (int) $isActive;
    $stmt = $con->prepare("UPDATE wallet_type SET is_active = ?, updated_at = NOW()
                           WHERE id_wallet_type = ? AND user_id = ?");
    if (!$stmt) {
        wallet_type_redirect('Gagal', 'Status tipe wallet gagal diperbarui.', 'error');
    }
    $stmt->bind_param('iii', $isActiveInt, $typeId, $userId);
    $errorCode = 0;
    $success = wallet_type_execute_statement($stmt, $errorCode);
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$success || $affected < 1) {
        wallet_type_redirect('Data tidak ditemukan', 'Tipe wallet tidak ditemukan atau status tidak berubah.', 'warning');
    }

    $label = $isActiveInt === 1 ? 'aktif' : 'nonaktif';
    record_activity($con, 'wallet_type', 'ubah_status', "Mengubah tipe wallet ID {$typeId} menjadi {$label}.");
    wallet_type_redirect('Berhasil', 'Status tipe wallet berhasil diperbarui.', 'success');
}

if ($act === 'delete') {
    $typeId = (int) ($_POST['id_wallet_type'] ?? 0);
    if ($typeId <= 0) {
        wallet_type_redirect('Data tidak valid', 'ID tipe wallet tidak valid.', 'error');
    }

    $usageStmt = $con->prepare("SELECT COUNT(*) AS total
                                FROM wallet
                                WHERE user_id = ? AND id_wallet_type = ?");
    if (!$usageStmt) {
        wallet_type_redirect('Gagal', 'Pemakaian tipe wallet gagal diperiksa.', 'error');
    }
    $usageStmt->bind_param('ii', $userId, $typeId);
    $usageErrorCode = 0;
    if (!wallet_type_execute_statement($usageStmt, $usageErrorCode)) {
        $usageStmt->close();
        wallet_type_redirect('Gagal', 'Pemakaian tipe wallet gagal diperiksa.', 'error');
    }
    $usageResult = $usageStmt->get_result();
    $usageRow = $usageResult ? $usageResult->fetch_assoc() : ['total' => 0];
    $usageStmt->close();

    if ((int) ($usageRow['total'] ?? 0) > 0) {
        wallet_type_redirect('Tipe masih digunakan', 'Nonaktifkan tipe atau ubah tipe pada wallet terkait sebelum menghapusnya.', 'warning');
    }

    $stmt = $con->prepare("DELETE FROM wallet_type WHERE id_wallet_type = ? AND user_id = ?");
    if (!$stmt) {
        wallet_type_redirect('Gagal', 'Tipe wallet gagal dihapus.', 'error');
    }
    $stmt->bind_param('ii', $typeId, $userId);
    $errorCode = 0;
    $success = wallet_type_execute_statement($stmt, $errorCode);
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$success || $affected < 1) {
        wallet_type_redirect('Data tidak ditemukan', 'Tipe wallet tidak ditemukan.', 'warning');
    }

    record_activity($con, 'wallet_type', 'hapus', "Menghapus tipe wallet ID {$typeId} yang belum digunakan.");
    wallet_type_redirect('Berhasil', 'Tipe wallet berhasil dihapus.', 'success');
}

wallet_type_redirect('Aksi tidak valid', 'Permintaan tipe wallet tidak dikenali.', 'error');
