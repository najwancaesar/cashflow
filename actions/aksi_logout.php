<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    $invalidRedirect = !empty($_SESSION['id_user']) ? 'main.php?module=home' : 'login.php';
    show_sweetalert_and_redirect(
        'Permintaan tidak valid',
        'Permintaan logout tidak valid. Silakan coba kembali.',
        'error',
        $invalidRedirect
    );
}

$logoutUserId = (int) ($_SESSION['id_user'] ?? 0);
$logoutRole = strtolower((string) ($_SESSION['role'] ?? ''));
include_once __DIR__ . "/../includes/activity_log_helper.php";
record_activity($con, 'auth', 'logout', 'Logout dari aplikasi.', $logoutUserId, $logoutRole);
$_SESSION = [];
session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_write_close();
session_id('');
session_start();
show_sweetalert_and_redirect('Sampai jumpa', 'Terima kasih sudah menggunakan CashFlow Control.', 'success', 'login.php');
?>
