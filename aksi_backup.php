<?php
ob_start();
session_start();

include "includes/koneksi.php";
include "includes/sweetalert_helper.php";
include_once "includes/csrf_helper.php";
include_once "includes/activity_log_helper.php";

function backup_redirect()
{
    return 'main.php?module=pengguna';
}

function fail_backup($message = 'Backup data gagal diproses.')
{
    show_sweetalert_and_redirect('Backup gagal', $message, 'error', backup_redirect());
}

function quote_identifier_backup($name)
{
    return '`' . str_replace('`', '``', (string) $name) . '`';
}

function table_exists_backup($con, $table)
{
    $stmt = $con->prepare("SELECT COUNT(*)
                           FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : [0];
    $stmt->close();

    return (int) ($row[0] ?? 0) > 0;
}

function table_columns_backup($con, $table)
{
    $stmt = $con->prepare("SELECT COLUMN_NAME, DATA_TYPE
                           FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = ?
                           ORDER BY ORDINAL_POSITION");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];

    while ($row = $result ? $result->fetch_assoc() : null) {
        $columns[] = [
            'name' => (string) $row['COLUMN_NAME'],
            'type' => strtolower((string) $row['DATA_TYPE']),
        ];
    }

    $stmt->close();

    return $columns;
}

function resolve_filter_column_backup($columns, $candidates)
{
    $available = [];
    foreach ($columns as $column) {
        $available[$column['name']] = true;
    }

    foreach ($candidates as $candidate) {
        if (isset($available[$candidate])) {
            return $candidate;
        }
    }

    return null;
}

function sql_value_backup($con, $value, $type)
{
    if ($value === null) {
        return 'NULL';
    }

    $numericTypes = [
        'bit',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'integer',
        'bigint',
        'decimal',
        'float',
        'double',
        'real',
        'year',
    ];

    if (in_array($type, $numericTypes, true) && is_numeric((string) $value)) {
        return (string) $value;
    }

    return "'" . mysqli_real_escape_string($con, (string) $value) . "'";
}

function rows_for_user_backup($con, $table, $columns, $filterColumn, $userId)
{
    $columnSql = implode(', ', array_map(static function ($column) {
        return quote_identifier_backup($column['name']);
    }, $columns));

    $sql = "SELECT {$columnSql}
            FROM " . quote_identifier_backup($table) . "
            WHERE " . quote_identifier_backup($filterColumn) . " = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result ? $result->fetch_assoc() : null) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

function render_table_backup_sql($con, $table, $filterCandidates, $userId)
{
    if (!table_exists_backup($con, $table)) {
        return "-- Table {$table} not found, skipped.\n\n";
    }

    $columns = table_columns_backup($con, $table);
    if (empty($columns)) {
        return "-- Table {$table} has no readable columns, skipped.\n\n";
    }

    $filterColumn = resolve_filter_column_backup($columns, $filterCandidates);
    if ($filterColumn === null) {
        return "-- Table {$table} has no supported user filter column, skipped.\n\n";
    }

    $rows = rows_for_user_backup($con, $table, $columns, $filterColumn, $userId);
    $sql = "-- Data for table {$table}\n";

    if (empty($rows)) {
        return $sql . "-- No rows for selected user.\n\n";
    }

    $columnNames = array_column($columns, 'name');
    $columnTypes = [];
    foreach ($columns as $column) {
        $columnTypes[$column['name']] = $column['type'];
    }

    $columnSql = implode(', ', array_map('quote_identifier_backup', $columnNames));
    foreach ($rows as $row) {
        $values = [];
        foreach ($columnNames as $columnName) {
            $values[] = sql_value_backup($con, $row[$columnName] ?? null, $columnTypes[$columnName] ?? '');
        }

        $sql .= "INSERT INTO " . quote_identifier_backup($table) . " ({$columnSql}) VALUES (" . implode(', ', $values) . ");\n";
    }

    return $sql . "\n";
}

if (!isset($_SESSION['id_user'])) {
    fail_backup('Silakan login terlebih dahulu.');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    fail_backup('Akses backup hanya tersedia untuk admin.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail_backup('Backup data wajib melalui form yang valid.');
}

if (!verify_csrf_token()) {
    fail_backup('Token keamanan tidak valid. Silakan coba lagi.');
}

$targetUserId = (int) ($_POST['id_user'] ?? 0);
if ($targetUserId <= 0) {
    fail_backup('User yang akan dibackup tidak valid.');
}

$userStmt = $con->prepare("SELECT id_user, nama, username, email, role
                           FROM user
                           WHERE id_user = ?
                           LIMIT 1");
$userStmt->bind_param("i", $targetUserId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$targetUser = $userResult ? $userResult->fetch_assoc() : null;
$userStmt->close();

if (!$targetUser) {
    fail_backup('User yang akan dibackup tidak ditemukan.');
}

record_activity($con, 'backup', 'export_user_backup', "Admin membackup data user ID {$targetUserId}.", (int) $_SESSION['id_user'], 'admin');

$backupTables = [
    'user' => ['id_user'],
    'kategori' => ['user_id', 'user'],
    'budget_kategori' => ['user_id', 'user'],
    'wallet' => ['user_id', 'user'],
    'pemasukan' => ['user', 'user_id'],
    'pengeluaran' => ['user', 'user_id'],
    'hutang' => ['user', 'user_id'],
    'piutang' => ['user', 'user_id'],
    'transfer_wallet' => ['user_id', 'user'],
    'saving_goal' => ['user_id', 'user'],
    'saving_goal_mutasi' => ['user_id', 'user'],
    'recurring_transaction' => ['user_id', 'user'],
    'recurring_generation_log' => ['user_id', 'user'],
];

$backupAt = date('Y-m-d H:i:s');
$filenameTimestamp = date('Ymd_His');
$filename = "cashflow_backup_user_{$targetUserId}_{$filenameTimestamp}.sql";
$safeUserName = (string) ($targetUser['username'] ?? $targetUser['nama'] ?? '-');

$sql = "-- CashFlow Control\n";
$sql .= "-- Jenis backup: Backup data per user\n";
$sql .= "-- ID user: {$targetUserId}\n";
$sql .= "-- Nama user: " . str_replace(["\r", "\n"], ' ', $safeUserName) . "\n";
$sql .= "-- Tanggal backup: {$backupAt}\n";
$sql .= "-- Catatan: Restore dilakukan manual melalui phpMyAdmin/MySQL.\n";
$sql .= "-- Password pada tabel user adalah hash, bukan plaintext.\n";
$sql .= "-- File gambar fisik tidak termasuk dalam backup ini.\n\n";
$sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sql .= "START TRANSACTION;\n";
$sql .= "SET time_zone = \"+00:00\";\n\n";

foreach ($backupTables as $table => $filterCandidates) {
    $sql .= render_table_backup_sql($con, $table, $filterCandidates, $targetUserId);
}

$sql .= "COMMIT;\n";

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($sql));

echo $sql;
exit;
?>
