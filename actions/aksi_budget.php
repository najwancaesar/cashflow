<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/sweetalert_helper.php";

$redirectKategori = 'main.php?module=kategori';

function redirect_budget($title, $text, $icon = 'info')
{
    global $redirectKategori;
    show_sweetalert_and_redirect($title, $text, $icon, $redirectKategori);
}

function parse_budget_nominal($value)
{
    $rawValue = trim((string) $value);

    if ($rawValue === '' || strpos($rawValue, '-') !== false) {
        return null;
    }

    $normalized = preg_replace('/[^\d]/', '', $rawValue);

    if ($normalized === '') {
        return null;
    }

    return ltrim($normalized, '0') === '' ? '0' : ltrim($normalized, '0');
}

if (!isset($_SESSION['id_user'])) {
    redirect_budget('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    redirect_budget('Akses dibatasi', 'Admin tidak dapat mengelola budget kategori.', 'warning');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect_budget('Gagal!', 'Request budget tidak valid.', 'error');
}

if (!verify_csrf_token()) {
    redirect_budget('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning');
}

$userId = (int) $_SESSION['id_user'];
$idKategori = isset($_POST['id_kategori']) ? (int) $_POST['id_kategori'] : 0;
$bulan = isset($_POST['bulan']) ? (int) $_POST['bulan'] : 0;
$tahun = isset($_POST['tahun']) ? (int) $_POST['tahun'] : 0;
$nominalBudget = parse_budget_nominal($_POST['nominal_budget'] ?? '');

if ($idKategori <= 0) {
    redirect_budget('Gagal!', 'Kategori budget tidak valid.', 'error');
}

if ($bulan < 1 || $bulan > 12) {
    redirect_budget('Gagal!', 'Bulan budget tidak valid.', 'error');
}

if ($tahun < 2000 || $tahun > 2100) {
    redirect_budget('Gagal!', 'Tahun budget tidak valid.', 'error');
}

if ($nominalBudget === null) {
    redirect_budget('Gagal!', 'Nominal budget harus angka 0 atau lebih.', 'error');
}

$kategoriQuery = "SELECT id_kategori
                  FROM kategori
                  WHERE id_kategori = ? AND user_id = ? AND tipe_kategori = 'pengeluaran'
                  LIMIT 1";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "ii", $idKategori, $userId);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);
$kategori = mysqli_fetch_assoc($kategoriResult);
mysqli_stmt_close($kategoriStmt);

if (!$kategori) {
    redirect_budget('Akses ditolak', 'Kategori pengeluaran tidak ditemukan untuk akun Anda.', 'warning');
}

$budgetQuery = "INSERT INTO budget_kategori (user_id, id_kategori, bulan, tahun, nominal_budget)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nominal_budget = VALUES(nominal_budget),
                    updated_at = CURRENT_TIMESTAMP";
$budgetStmt = mysqli_prepare($con, $budgetQuery);
mysqli_stmt_bind_param($budgetStmt, "iiiis", $userId, $idKategori, $bulan, $tahun, $nominalBudget);
$result = mysqli_stmt_execute($budgetStmt);
mysqli_stmt_close($budgetStmt);

if ($result) {
    redirect_budget('Berhasil!', 'Budget kategori berhasil disimpan.', 'success');
}

redirect_budget('Gagal!', 'Budget kategori gagal disimpan.', 'error');
?>
