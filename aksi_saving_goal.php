<?php
session_start();
include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include "includes/nominal_helper.php";
include_once "includes/csrf_helper.php";

function saving_goal_redirect()
{
    return 'main.php?module=saving_goal';
}

function require_saving_goal_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi celengan wajib melalui form yang valid.', 'warning', saving_goal_redirect());
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', saving_goal_redirect());
    }
}

function clean_saving_goal_text($value)
{
    return trim((string) $value);
}

function validate_saving_goal_date($value, $nullable = false)
{
    $value = clean_saving_goal_text($value);
    if ($value === '' && $nullable) {
        return null;
    }

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

function is_valid_saving_goal_status($status)
{
    return in_array($status, ['aktif', 'selesai', 'arsip'], true);
}

function fetch_saving_goal_by_id($con, $goalId, $userId)
{
    $stmt = $con->prepare("SELECT id_goal, user_id, nama_goal, target_nominal, target_tanggal, status
                           FROM saving_goal
                           WHERE id_goal = ? AND user_id = ?
                           LIMIT 1");
    $stmt->bind_param("ii", $goalId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $goal = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $goal ?: null;
}

function hitung_saldo_saving_goal($con, $goalId, $userId)
{
    $stmt = $con->prepare("SELECT
                              COALESCE(SUM(CASE
                                WHEN tipe = 'setor' THEN jumlah
                                WHEN tipe = 'tarik' THEN -jumlah
                                ELSE 0
                              END), 0) AS saldo
                           FROM saving_goal_mutasi
                           WHERE id_goal = ? AND user_id = ?");
    $stmt->bind_param("ii", $goalId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['saldo' => 0];
    $stmt->close();

    return (float) ($row['saldo'] ?? 0);
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola celengan user.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 'goal') {
    require_saving_goal_post_csrf();

    $goalId = isset($_POST['id_goal']) && $_POST['id_goal'] !== ''
        ? (int) $_POST['id_goal']
        : null;
    $namaGoal = clean_saving_goal_text($_POST['nama_goal'] ?? '');
    $targetRaw = (string) ($_POST['target_nominal'] ?? '');
    $targetTanggal = validate_saving_goal_date($_POST['target_tanggal'] ?? '', true);
    $status = clean_saving_goal_text($_POST['status'] ?? 'aktif');

    if ($namaGoal === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Nama target tabungan wajib diisi.', 'warning', saving_goal_redirect());
    }

    if (strpos($targetRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Target nominal harus lebih dari 0.', 'error', saving_goal_redirect());
    }

    $targetNominal = nominal_input_to_number($targetRaw);
    if ($targetNominal <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Target nominal harus lebih dari 0.', 'error', saving_goal_redirect());
    }

    if ($targetTanggal === false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Target tanggal wajib valid dengan format YYYY-MM-DD.', 'error', saving_goal_redirect());
    }

    if (!is_valid_saving_goal_status($status)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Status target tabungan tidak valid.', 'error', saving_goal_redirect());
    }

    if ($goalId === null) {
        $status = 'aktif';
        $stmt = $con->prepare("INSERT INTO saving_goal (user_id, nama_goal, target_nominal, target_tanggal, status, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("isdss", $userId, $namaGoal, $targetNominal, $targetTanggal, $status);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            show_sweetalert_and_redirect('Berhasil', 'Target tabungan berhasil ditambahkan.', 'success', saving_goal_redirect());
        }

        show_sweetalert_and_redirect('Gagal', 'Target tabungan gagal ditambahkan.', 'error', saving_goal_redirect());
    }

    if ($goalId <= 0 || !fetch_saving_goal_by_id($con, $goalId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Target tabungan yang ingin diubah tidak ditemukan.', 'warning', saving_goal_redirect());
    }

    $stmt = $con->prepare("UPDATE saving_goal
                           SET nama_goal = ?, target_nominal = ?, target_tanggal = ?, status = ?, updated_at = NOW()
                           WHERE id_goal = ? AND user_id = ?");
    $stmt->bind_param("sdssii", $namaGoal, $targetNominal, $targetTanggal, $status, $goalId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        show_sweetalert_and_redirect('Berhasil', 'Target tabungan berhasil diperbarui.', 'success', saving_goal_redirect());
    }

    show_sweetalert_and_redirect('Gagal', 'Target tabungan gagal diperbarui.', 'error', saving_goal_redirect());
}

if ($act === 'setor' || $act === 'tarik') {
    require_saving_goal_post_csrf();

    $goalId = (int) ($_POST['id_goal'] ?? 0);
    $tanggal = validate_saving_goal_date($_POST['tanggal'] ?? '');
    $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
    $catatan = clean_saving_goal_text($_POST['catatan'] ?? '');

    if ($goalId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID target tabungan tidak valid.', 'error', saving_goal_redirect());
    }

    $goal = fetch_saving_goal_by_id($con, $goalId, $userId);
    if (!$goal) {
        show_sweetalert_and_redirect('Akses ditolak', 'Target tabungan tidak ditemukan.', 'warning', saving_goal_redirect());
    }

    if (($goal['status'] ?? '') === 'arsip') {
        show_sweetalert_and_redirect('Aksi dibatasi', 'Target tabungan arsip tidak bisa menerima mutasi.', 'warning', saving_goal_redirect());
    }

    if ($tanggal === false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal mutasi wajib valid dengan format YYYY-MM-DD.', 'error', saving_goal_redirect());
    }

    if (strpos($jumlahRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah mutasi harus lebih dari 0.', 'error', saving_goal_redirect());
    }

    $jumlah = nominal_input_to_number($jumlahRaw);
    if ($jumlah <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah mutasi harus lebih dari 0.', 'error', saving_goal_redirect());
    }

    if ($act === 'tarik') {
        $saldoTerkumpul = hitung_saldo_saving_goal($con, $goalId, $userId);
        if ($saldoTerkumpul + 0.0001 < $jumlah) {
            show_sweetalert_and_redirect('Saldo tidak cukup', 'Jumlah tarik tidak boleh melebihi saldo terkumpul.', 'warning', saving_goal_redirect());
        }
    }

    $tipe = $act === 'setor' ? 'setor' : 'tarik';
    $stmt = $con->prepare("INSERT INTO saving_goal_mutasi (id_goal, user_id, tanggal, tipe, jumlah, catatan, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissds", $goalId, $userId, $tanggal, $tipe, $jumlah, $catatan);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        $message = $tipe === 'setor' ? 'Setor celengan berhasil ditambahkan.' : 'Tarik celengan berhasil ditambahkan.';
        show_sweetalert_and_redirect('Berhasil', $message, 'success', saving_goal_redirect());
    }

    show_sweetalert_and_redirect('Gagal', 'Mutasi celengan gagal ditambahkan.', 'error', saving_goal_redirect());
}

if ($act === 'status') {
    require_saving_goal_post_csrf();

    $goalId = (int) ($_POST['id_goal'] ?? 0);
    $status = clean_saving_goal_text($_POST['status'] ?? '');

    if ($goalId <= 0 || !is_valid_saving_goal_status($status)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Permintaan status target tabungan tidak valid.', 'error', saving_goal_redirect());
    }

    if (!fetch_saving_goal_by_id($con, $goalId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Target tabungan tidak ditemukan.', 'warning', saving_goal_redirect());
    }

    $stmt = $con->prepare("UPDATE saving_goal SET status = ?, updated_at = NOW() WHERE id_goal = ? AND user_id = ?");
    $stmt->bind_param("sii", $status, $goalId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        show_sweetalert_and_redirect('Berhasil', 'Status target tabungan berhasil diperbarui.', 'success', saving_goal_redirect());
    }

    show_sweetalert_and_redirect('Gagal', 'Status target tabungan gagal diperbarui.', 'error', saving_goal_redirect());
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan celengan tidak dikenali.', 'error', saving_goal_redirect());
?>
