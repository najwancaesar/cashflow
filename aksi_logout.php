<?php
session_start();
include "includes/sweetalert_helper.php";
$_SESSION = [];
session_unset();
session_destroy();

show_sweetalert_and_redirect('Sampai jumpa', 'Terima kasih sudah menggunakan CashFlow Control.', 'success', './');
?>
