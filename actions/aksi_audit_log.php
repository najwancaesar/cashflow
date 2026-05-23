<?php
session_start();

include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";

function audit_log_redirect()
{
    return '../main.php?module=audit_log';
}

function audit_log_redirect_with_flash($title, $message, $icon)
{
    $redirect = audit_log_redirect();
    set_sweetalert_flash($title, $message, $icon);

    if (!headers_sent()) {
        header('Location: ' . $redirect);
        exit;
    }

    $redirectJson = json_encode($redirect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "<script>window.location.href = {$redirectJson};</script>";
    exit;
}

function audit_log_fail($message)
{
    audit_log_redirect_with_flash('Aksi gagal', $message, 'error');
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    audit_log_fail('Akses audit log hanya tersedia untuk admin.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    audit_log_fail('Cleanup audit log wajib melalui form yang valid.');
}

if (!verify_csrf_token()) {
    audit_log_fail('Token keamanan tidak valid. Silakan coba lagi.');
}

$act = $_GET['act'] ?? '';
if ($act !== 'cleanup') {
    audit_log_fail('Aksi audit log tidak valid.');
}

$retentionDays = (int) ($_POST['older_than_days'] ?? ($_POST['retention_days'] ?? 0));
if (!in_array($retentionDays, [30, 90, 180], true)) {
    audit_log_fail('Pilihan masa retensi audit log tidak valid.');
}

if (!activity_log_table_exists($con)) {
    audit_log_fail('Tabel activity_log belum tersedia.');
}

$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

$deleteStmt = $con->prepare("DELETE FROM activity_log WHERE created_at < ?");
if (!$deleteStmt) {
    audit_log_fail('Cleanup audit log gagal diproses.');
}

$deleteStmt->bind_param("s", $cutoffDate);
$deleteResult = $deleteStmt->execute();
$deletedRows = $deleteStmt->affected_rows;
$deleteStmt->close();

if (!$deleteResult) {
    audit_log_fail('Cleanup audit log gagal diproses.');
}

record_activity(
    $con,
    'audit_log',
    'cleanup',
    "Admin menghapus {$deletedRows} log lebih lama dari {$retentionDays} hari.",
    (int) $_SESSION['id_user'],
    'admin'
);

audit_log_redirect_with_flash(
    'Cleanup selesai',
    "Berhasil menghapus {$deletedRows} log lebih lama dari {$retentionDays} hari.",
    'success'
);
?>
