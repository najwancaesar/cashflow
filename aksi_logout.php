<?php
session_start();
include "includes/sweetalert_helper.php";
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
