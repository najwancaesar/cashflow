<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include_once "includes/csrf_helper.php";

function clean_wallet_text($value)
{
    return trim((string) $value);
}

function is_valid_wallet_type($type)
{
    return in_array($type, ['cash', 'bank', 'e_wallet', 'tabungan', 'lainnya'], true);
}

function require_wallet_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi wallet wajib melalui form yang valid.', 'warning', 'main.php?module=wallet');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=wallet');
    }
}

function fetch_wallet_by_id($con, $walletId, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet, user_id, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active
                           FROM wallet
                           WHERE id_wallet = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $walletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $wallet ?: null;
}

function count_active_wallets($con, $userId)
{
    $stmt = $con->prepare("SELECT COUNT(*) AS total FROM wallet WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function user_has_default_wallet($con, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet FROM wallet WHERE user_id = ? AND is_default = 1 LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasDefault = $result && $result->num_rows > 0;
    $stmt->close();

    return $hasDefault;
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola wallet user pada phase ini.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    require_wallet_post_csrf();

    $walletId = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== ''
        ? (int) $_POST['id_wallet']
        : null;
    $namaWallet = clean_wallet_text($_POST['nama_wallet'] ?? '');
    $tipeWallet = clean_wallet_text($_POST['tipe_wallet'] ?? '');
    $saldoAwalRaw = (string) ($_POST['saldo_awal'] ?? '');

    if ($namaWallet === '' || $tipeWallet === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Nama wallet dan tipe wallet wajib diisi.', 'warning', 'main.php?module=wallet');
    }

    if (!is_valid_wallet_type($tipeWallet)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tipe wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    if (strpos($saldoAwalRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Saldo awal tidak boleh bernilai negatif.', 'error', 'main.php?module=wallet');
    }

    $saldoAwal = nominal_input_to_number($saldoAwalRaw);
    if ($saldoAwal < 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Saldo awal tidak boleh bernilai negatif.', 'error', 'main.php?module=wallet');
    }

    if ($walletId === null) {
        $isDefault = user_has_default_wallet($con, $userId) ? 0 : 1;
        $isActive = 1;

        $stmt = $con->prepare("INSERT INTO wallet (user_id, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("issdii", $userId, $namaWallet, $tipeWallet, $saldoAwal, $isDefault, $isActive);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            show_sweetalert_and_redirect('Berhasil', 'Wallet berhasil ditambahkan.', 'success', 'main.php?module=wallet');
        }

        show_sweetalert_and_redirect('Gagal', 'Wallet gagal ditambahkan.', 'error', 'main.php?module=wallet');
    }

    if ($walletId <= 0 || !fetch_wallet_by_id($con, $walletId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet yang ingin diubah tidak ditemukan.', 'warning', 'main.php?module=wallet');
    }

    $stmt = $con->prepare("UPDATE wallet
                           SET nama_wallet = ?, tipe_wallet = ?, saldo_awal = ?, updated_at = NOW()
                           WHERE id_wallet = ? AND user_id = ?");
    $stmt->bind_param("ssdii", $namaWallet, $tipeWallet, $saldoAwal, $walletId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        show_sweetalert_and_redirect('Berhasil', 'Wallet berhasil diperbarui.', 'success', 'main.php?module=wallet');
    }

    show_sweetalert_and_redirect('Gagal', 'Wallet gagal diperbarui.', 'error', 'main.php?module=wallet');
}

if ($act === 's') {
    require_wallet_post_csrf();

    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    $targetStatus = clean_wallet_text($_POST['value'] ?? '');

    if ($walletId <= 0 || !in_array($targetStatus, ['0', '1'], true)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Permintaan status wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    $wallet = fetch_wallet_by_id($con, $walletId, $userId);
    if (!$wallet) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet yang ingin diubah tidak ditemukan.', 'warning', 'main.php?module=wallet');
    }

    if ($targetStatus === '0') {
        if ((string) ($wallet['is_default'] ?? '0') === '1') {
            show_sweetalert_and_redirect('Aksi dibatasi', 'Wallet default tidak boleh dinonaktifkan.', 'warning', 'main.php?module=wallet');
        }

        if (count_active_wallets($con, $userId) <= 1) {
            show_sweetalert_and_redirect('Aksi dibatasi', 'Minimal harus ada satu wallet aktif.', 'warning', 'main.php?module=wallet');
        }
    }

    $targetStatusInt = (int) $targetStatus;
    $stmt = $con->prepare("UPDATE wallet SET is_active = ?, updated_at = NOW() WHERE id_wallet = ? AND user_id = ?");
    $stmt->bind_param("iii", $targetStatusInt, $walletId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        show_sweetalert_and_redirect('Berhasil', 'Status wallet berhasil diperbarui.', 'success', 'main.php?module=wallet');
    }

    show_sweetalert_and_redirect('Gagal', 'Status wallet gagal diperbarui.', 'error', 'main.php?module=wallet');
}

if ($act === 'd') {
    require_wallet_post_csrf();

    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    if ($walletId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID wallet tidak valid.', 'error', 'main.php?module=wallet');
    }

    $wallet = fetch_wallet_by_id($con, $walletId, $userId);
    if (!$wallet) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet yang ingin dijadikan default tidak ditemukan.', 'warning', 'main.php?module=wallet');
    }

    if ((string) ($wallet['is_active'] ?? '0') !== '1') {
        show_sweetalert_and_redirect('Aksi dibatasi', 'Wallet nonaktif tidak bisa dijadikan default.', 'warning', 'main.php?module=wallet');
    }

    mysqli_begin_transaction($con);

    try {
        $resetStmt = $con->prepare("UPDATE wallet SET is_default = 0, updated_at = NOW() WHERE user_id = ?");
        $resetStmt->bind_param("i", $userId);
        $resetStmt->execute();
        $resetStmt->close();

        $defaultStmt = $con->prepare("UPDATE wallet SET is_default = 1, updated_at = NOW() WHERE id_wallet = ? AND user_id = ?");
        $defaultStmt->bind_param("ii", $walletId, $userId);
        $defaultStmt->execute();
        $affectedRows = $defaultStmt->affected_rows;
        $defaultStmt->close();

        if ($affectedRows < 1) {
            mysqli_rollback($con);
            show_sweetalert_and_redirect('Gagal', 'Wallet default gagal diperbarui.', 'error', 'main.php?module=wallet');
        }

        mysqli_commit($con);
        show_sweetalert_and_redirect('Berhasil', 'Wallet default berhasil diperbarui.', 'success', 'main.php?module=wallet');
    } catch (Throwable $exception) {
        mysqli_rollback($con);
        show_sweetalert_and_redirect('Gagal', 'Wallet default gagal diperbarui.', 'error', 'main.php?module=wallet');
    }
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan wallet tidak dikenali.', 'error', 'main.php?module=wallet');
?>
