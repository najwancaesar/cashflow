<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include_once "includes/csrf_helper.php";

function require_transfer_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi transfer wallet wajib melalui form yang valid.', 'warning', 'main.php?module=transfer_wallet');
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', 'main.php?module=transfer_wallet');
    }
}

function clean_transfer_text($value)
{
    return trim((string) $value);
}

function validate_transfer_date($value)
{
    $value = clean_transfer_text($value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return false;
    }

    return $date->format('Y-m-d') === $value ? $value : false;
}

function is_valid_transfer_status($status)
{
    return in_array($status, ['pending', 'selesai', 'batal'], true);
}

function fetch_active_wallet_for_transfer($con, $walletId, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet
                           FROM wallet
                           WHERE id_wallet = ? AND user_id = ? AND is_active = 1
                           LIMIT 1");
    $stmt->bind_param("ii", $walletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $wallet ?: null;
}

function transfer_dimiliki_user($con, $transferId, $userId)
{
    $stmt = $con->prepare("SELECT id_transfer
                           FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $transferId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $transfer !== null;
}

function fetch_transfer_status_for_user($con, $transferId, $userId)
{
    $stmt = $con->prepare("SELECT status
                           FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $transferId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transfer = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $transfer['status'] ?? null;
}

function count_active_wallets_for_transfer($con, $userId)
{
    $stmt = $con->prepare("SELECT COUNT(*)
                           FROM wallet
                           WHERE user_id = ? AND is_active = 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

function transfer_single_value($con, $sql, $types = '', $params = [])
{
    $stmt = $con->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $bindParams = [$types];
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    return (float) ($row[0] ?? 0);
}

function hitung_saldo_wallet($con, $userId, $walletId, $excludeTransferId = null)
{
    $saldoAwal = transfer_single_value(
        $con,
        "SELECT COALESCE(saldo_awal, 0)
         FROM wallet
         WHERE id_wallet = ? AND user_id = ?
         LIMIT 1",
        "ii",
        [$walletId, $userId]
    );

    $totalPemasukan = transfer_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0)
         FROM pemasukan
         WHERE user = ? AND id_wallet = ? AND status = 'selesai'",
        "ii",
        [$userId, $walletId]
    );

    $totalPengeluaran = transfer_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0)
         FROM pengeluaran
         WHERE user = ? AND id_wallet = ? AND status = 'selesai'",
        "ii",
        [$userId, $walletId]
    );

    $transferMasukSql = "SELECT COALESCE(SUM(jumlah), 0)
                         FROM transfer_wallet
                         WHERE user_id = ? AND wallet_tujuan_id = ? AND status = 'selesai'";
    $transferMasukTypes = "ii";
    $transferMasukParams = [$userId, $walletId];

    $transferKeluarSql = "SELECT COALESCE(SUM(jumlah), 0)
                          FROM transfer_wallet
                          WHERE user_id = ? AND wallet_asal_id = ? AND status = 'selesai'";
    $transferKeluarTypes = "ii";
    $transferKeluarParams = [$userId, $walletId];

    if ($excludeTransferId !== null && (int) $excludeTransferId > 0) {
        $transferMasukSql .= " AND id_transfer <> ?";
        $transferMasukTypes .= "i";
        $transferMasukParams[] = (int) $excludeTransferId;

        $transferKeluarSql .= " AND id_transfer <> ?";
        $transferKeluarTypes .= "i";
        $transferKeluarParams[] = (int) $excludeTransferId;
    }

    $totalTransferMasuk = transfer_single_value($con, $transferMasukSql, $transferMasukTypes, $transferMasukParams);
    $totalTransferKeluar = transfer_single_value($con, $transferKeluarSql, $transferKeluarTypes, $transferKeluarParams);

    return $saldoAwal + $totalPemasukan - $totalPengeluaran + $totalTransferMasuk - $totalTransferKeluar;
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transfer wallet user.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    require_transfer_post_csrf();

    $transferId = isset($_POST['id_transfer']) && $_POST['id_transfer'] !== ''
        ? (int) $_POST['id_transfer']
        : null;
    $tanggal = validate_transfer_date($_POST['tanggal'] ?? '');
    $walletAsalId = (int) ($_POST['wallet_asal_id'] ?? 0);
    $walletTujuanId = (int) ($_POST['wallet_tujuan_id'] ?? 0);
    $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
    $status = clean_transfer_text($_POST['status'] ?? 'selesai');
    $catatan = clean_transfer_text($_POST['catatan'] ?? '');

    if ($tanggal === false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal transfer wajib valid dengan format YYYY-MM-DD.', 'error', 'main.php?module=transfer_wallet');
    }

    if (count_active_wallets_for_transfer($con, $userId) < 2) {
        show_sweetalert_and_redirect('Transfer belum bisa dilakukan', 'Transfer wallet membutuhkan minimal 2 wallet aktif.', 'warning', 'main.php?module=transfer_wallet');
    }

    if ($walletAsalId <= 0 || $walletTujuanId <= 0) {
        show_sweetalert_and_redirect('Data belum lengkap', 'Wallet asal dan wallet tujuan wajib dipilih.', 'warning', 'main.php?module=transfer_wallet');
    }

    if ($walletAsalId === $walletTujuanId) {
        show_sweetalert_and_redirect('Data tidak valid', 'Wallet asal dan wallet tujuan tidak boleh sama.', 'warning', 'main.php?module=transfer_wallet');
    }

    if (!fetch_active_wallet_for_transfer($con, $walletAsalId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet asal tidak valid, tidak aktif, atau bukan milik Anda.', 'error', 'main.php?module=transfer_wallet');
    }

    if (!fetch_active_wallet_for_transfer($con, $walletTujuanId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Wallet tujuan tidak valid, tidak aktif, atau bukan milik Anda.', 'error', 'main.php?module=transfer_wallet');
    }

    if (strpos($jumlahRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transfer harus lebih dari 0.', 'error', 'main.php?module=transfer_wallet');
    }

    $jumlah = nominal_input_to_number($jumlahRaw);
    if ($jumlah <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transfer harus lebih dari 0.', 'error', 'main.php?module=transfer_wallet');
    }

    if (!is_valid_transfer_status($status)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Status transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    if ($status === 'selesai') {
        $saldoWalletAsal = hitung_saldo_wallet($con, $userId, $walletAsalId, $transferId);
        if ($saldoWalletAsal + 0.0001 < $jumlah) {
            show_sweetalert_and_redirect('Saldo tidak cukup', 'Saldo wallet asal tidak mencukupi untuk transfer ini.', 'warning', 'main.php?module=transfer_wallet');
        }
    }

    if ($transferId === null) {
        $stmt = $con->prepare("INSERT INTO transfer_wallet (user_id, wallet_asal_id, wallet_tujuan_id, tanggal, jumlah, catatan, status, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("iiisdss", $userId, $walletAsalId, $walletTujuanId, $tanggal, $jumlah, $catatan, $status);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            show_sweetalert_and_redirect('Berhasil', 'Transfer wallet berhasil ditambahkan.', 'success', 'main.php?module=transfer_wallet');
        }

        show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal ditambahkan.', 'error', 'main.php?module=transfer_wallet');
    }

    if ($transferId <= 0 || !transfer_dimiliki_user($con, $transferId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Transfer yang ingin diubah tidak ditemukan.', 'warning', 'main.php?module=transfer_wallet');
    }

    $stmt = $con->prepare("UPDATE transfer_wallet
                           SET wallet_asal_id = ?, wallet_tujuan_id = ?, tanggal = ?, jumlah = ?, catatan = ?, status = ?, updated_at = NOW()
                           WHERE id_transfer = ? AND user_id = ?");
    $stmt->bind_param("iisdssii", $walletAsalId, $walletTujuanId, $tanggal, $jumlah, $catatan, $status, $transferId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        show_sweetalert_and_redirect('Berhasil', 'Transfer wallet berhasil diperbarui.', 'success', 'main.php?module=transfer_wallet');
    }

    show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal diperbarui.', 'error', 'main.php?module=transfer_wallet');
}

if ($act === 'h') {
    require_transfer_post_csrf();

    $transferId = (int) ($_POST['id_transfer'] ?? 0);
    if ($transferId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    if (!transfer_dimiliki_user($con, $transferId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Transfer yang ingin dibatalkan tidak ditemukan.', 'warning', 'main.php?module=transfer_wallet');
    }

    $stmt = $con->prepare("UPDATE transfer_wallet
                           SET status = 'batal', updated_at = NOW()
                           WHERE id_transfer = ? AND user_id = ? AND status <> 'batal'");
    $stmt->bind_param("ii", $transferId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows > 0) {
        show_sweetalert_and_redirect('Berhasil', 'Transfer wallet berhasil dibatalkan.', 'success', 'main.php?module=transfer_wallet');
    }

    show_sweetalert_and_redirect('Tidak ada perubahan', 'Transfer wallet sudah berstatus batal.', 'warning', 'main.php?module=transfer_wallet');
}

if ($act === 'hp') {
    require_transfer_post_csrf();

    $transferId = (int) ($_POST['id_transfer'] ?? 0);
    if ($transferId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID transfer tidak valid.', 'error', 'main.php?module=transfer_wallet');
    }

    $statusTransfer = fetch_transfer_status_for_user($con, $transferId, $userId);
    if ($statusTransfer === null) {
        show_sweetalert_and_redirect('Akses ditolak', 'Transfer yang ingin dihapus tidak ditemukan.', 'warning', 'main.php?module=transfer_wallet');
    }

    if (!in_array($statusTransfer, ['pending', 'batal'], true)) {
        show_sweetalert_and_redirect('Aksi ditolak', 'Transfer selesai harus dibatalkan terlebih dahulu sebelum dihapus permanen.', 'warning', 'main.php?module=transfer_wallet');
    }

    $stmt = $con->prepare("DELETE FROM transfer_wallet
                           WHERE id_transfer = ? AND user_id = ? AND status IN ('pending', 'batal')");
    $stmt->bind_param("ii", $transferId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows > 0) {
        show_sweetalert_and_redirect('Berhasil', 'Transfer wallet berhasil dihapus permanen.', 'success', 'main.php?module=transfer_wallet');
    }

    show_sweetalert_and_redirect('Gagal', 'Transfer wallet gagal dihapus permanen.', 'error', 'main.php?module=transfer_wallet');
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan transfer wallet tidak dikenali.', 'error', 'main.php?module=transfer_wallet');
?>
