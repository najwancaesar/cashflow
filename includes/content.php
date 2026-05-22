<?php
// Daftar modul yang diizinkan
$allowedModules = [
    'home' => __DIR__ . "/../views/view_home.php",
    'dashboard' => __DIR__ . "/../views/view_home.php",
    'wallet' => __DIR__ . "/../views/view_wallet.php",
    'transfer_wallet' => __DIR__ . "/../views/view_transfer_wallet.php",
    'saving_goal' => __DIR__ . "/../views/view_saving_goal.php",
    'recurring' => __DIR__ . "/../views/view_recurring.php",
    'kategori' => __DIR__ . "/../views/view_kategori.php",
    'pemasukan' => __DIR__ . "/../views/view_pemasukan.php",
    'pengeluaran' => __DIR__ . "/../views/view_pengeluaran.php",
    'hutang' => __DIR__ . "/../views/view_hutang.php",
    'piutang' => __DIR__ . "/../views/view_piutang.php",
    'laporan' => __DIR__ . "/../views/view_laporan.php",
    'pengguna' => __DIR__ . "/../views/view_pengguna.php",
    'audit_log' => __DIR__ . "/../views/view_audit_log.php",
    'profile' => __DIR__ . "/../views/view_profile.php",
];

$adminOnlyModules = ['pengguna', 'audit_log'];
$userOnlyModules = ['wallet', 'transfer_wallet', 'saving_goal', 'recurring', 'kategori', 'pemasukan', 'pengeluaran', 'hutang', 'piutang', 'laporan'];

// Jika session nama tidak ada, arahkan ke login
if (!isset($_SESSION['nama'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

// Dapatkan role user
$role = strtolower((string) ($_SESSION['role'] ?? ''));
$isAdmin = $role === 'admin';

// Cek apakah module valid
$module = $_GET['module'] ?? 'home';
if (in_array($module, $adminOnlyModules, true) && !$isAdmin) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Akses ditolak',
                    text: 'Modul pengguna hanya bisa diakses oleh admin.'
                }).then(function () {
                    window.location.href = 'main.php?module=home';
                });
            } else {
                window.location.href = 'main.php?module=home';
            }
        });
    </script>";
} elseif (in_array($module, $userOnlyModules, true) && $isAdmin) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Akses dibatasi',
                    text: 'Admin hanya dapat memantau sistem dan mengelola user.'
                }).then(function () {
                    window.location.href = 'main.php?module=home';
                });
            } else {
                window.location.href = 'main.php?module=home';
            }
        });
    </script>";
} elseif (array_key_exists($module, $allowedModules)) {
    include $allowedModules[$module];
} else {
    include __DIR__ . "/../views/view_home.php";
}
?>
