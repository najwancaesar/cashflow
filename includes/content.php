<?php
// Daftar modul yang diizinkan
$allowedModules = [
    'home' => "view_home.php",
    'pemasukan' => "view_pemasukan.php",
    'pengeluaran' => "view_pengeluaran.php",
    'hutang' => "view_hutang.php",
    'piutang' => "view_piutang.php",
    'laporan' => "view_laporan.php",
    'pengguna' => "view_pengguna.php",
    'profile' => "view_profile.php",
];

// Jika session nama tidak ada, arahkan ke login
if (!isset($_SESSION['nama'])) {
    if (isset($_GET['module']) && $_GET['module'] == 'login') {
        include "view_login.php";
    } else {
        echo "<script>window.location=(href='./')</script>";
    }
    exit;
}

// Dapatkan role user
$role = $_SESSION['role'] ?? null;

// Cek apakah module valid
$module = $_GET['module'] ?? 'home';
if (array_key_exists($module, $allowedModules)) {
    include $allowedModules[$module];
} else {
    include "view_home.php";
}
?>
