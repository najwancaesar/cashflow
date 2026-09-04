<?php
session_start();

include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/sweetalert_helper.php';
$entityKey = trim((string) ($_POST['entity'] ?? ''));
$redirects = [
    'pemasukan' => 'main.php?module=pemasukan',
    'pengeluaran' => 'main.php?module=pengeluaran',
];
$userId = (int) ($_SESSION['id_user'] ?? 0);
$redirect = $redirects[$entityKey] ?? 'main.php?module=home';

if ($userId <= 0) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    show_sweetalert_and_redirect('Akses ditolak', 'Permintaan bulk tidak valid atau sesi form kedaluwarsa.', 'error', $redirect);
}

show_sweetalert_and_redirect(
    'Fitur dinonaktifkan',
    'Bulk action telah dinonaktifkan. Kelola transaksi satu per satu melalui kolom Aksi.',
    'warning',
    $redirect
);
?>
