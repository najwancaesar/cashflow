<?php
// Daftar modul yang diizinkan
$allowedModules = [
    'home' => "view_home.php",
    'dashboard' => "view_home.php",
    'kategori' => "view_kategori.php",
    'pemasukan' => "view_pemasukan.php",
    'pengeluaran' => "view_pengeluaran.php",
    'hutang' => "view_hutang.php",
    'piutang' => "view_piutang.php",
    'laporan' => "view_laporan.php",
    'pengguna' => "view_pengguna.php",
    'profile' => "view_profile.php",
];

$adminOnlyModules = ['pengguna'];
$userOnlyModules = ['kategori', 'pemasukan', 'pengeluaran', 'hutang', 'piutang', 'laporan'];

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
    include "view_home.php";
}
?>
