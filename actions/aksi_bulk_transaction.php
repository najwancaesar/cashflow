<?php
session_start();

include __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/sweetalert_helper.php';
include_once __DIR__ . '/../includes/activity_log_helper.php';

function bulk_transaction_entities()
{
    return [
        'pemasukan' => [
            'table' => 'pemasukan',
            'pk' => 'id_pemasukan',
            'owner' => 'user',
            'module' => 'pemasukan',
            'label' => 'pemasukan',
            'recurring_type' => 'pemasukan',
            'parent_table' => 'piutang',
            'parent_pk' => 'id_piutang',
            'parent_link' => 'id_pemasukan',
            'parent_entity' => 'piutang',
        ],
        'pengeluaran' => [
            'table' => 'pengeluaran',
            'pk' => 'id_pengeluaran',
            'owner' => 'user',
            'module' => 'pengeluaran',
            'label' => 'pengeluaran',
            'recurring_type' => 'pengeluaran',
            'parent_table' => 'hutang',
            'parent_pk' => 'id_hutang',
            'parent_link' => 'id_pengeluaran',
            'parent_entity' => 'hutang',
        ],
    ];
}

function bulk_transaction_ids($value)
{
    if (!is_array($value) || count($value) > 500) {
        return [];
    }

    $ids = [];
    foreach ($value as $rawId) {
        if (!is_scalar($rawId)) {
            return [];
        }
        $rawId = trim((string) $rawId);
        if (!preg_match('/^[1-9][0-9]*$/', $rawId)) {
            return [];
        }
        $ids[] = (int) $rawId;
    }

    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function bulk_transaction_bind($stmt, $types, array $params)
{
    if (!$stmt) {
        throw new RuntimeException('Query bulk action tidak dapat disiapkan.');
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new RuntimeException('Query bulk action gagal dijalankan.');
    }
}

function bulk_transaction_lock_rows($con, array $entity, array $ids, $userId)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT {$entity['pk']} AS id, status
            FROM {$entity['table']}
            WHERE {$entity['owner']} = ? AND {$entity['pk']} IN ({$placeholders})
            ORDER BY {$entity['pk']} ASC
            FOR UPDATE";
    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, 'i' . str_repeat('i', count($ids)), array_merge([$userId], $ids));
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function bulk_transaction_relation_count($con, array $entity, array $ids, $userId)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'is' . str_repeat('i', count($ids)) . 'i' . str_repeat('i', count($ids));
    $params = array_merge(
        [$userId, $entity['recurring_type']],
        $ids,
        [$userId],
        $ids
    );
    $sql = "SELECT
                (SELECT COUNT(*)
                 FROM recurring_generation_log
                 WHERE user_id = ? AND tipe_transaksi = ? AND id_transaksi IN ({$placeholders}))
              + (SELECT COUNT(*)
                 FROM {$entity['parent_table']}
                 WHERE user = ? AND {$entity['parent_link']} IN ({$placeholders})) AS total";
    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

$entities = bulk_transaction_entities();
$entityKey = trim((string) ($_POST['entity'] ?? ''));
$entity = $entities[$entityKey] ?? null;
$operation = trim((string) ($_POST['operation'] ?? ''));
$ids = bulk_transaction_ids($_POST['ids'] ?? []);
$userId = (int) ($_SESSION['id_user'] ?? 0);
$redirect = $entity ? 'main.php?module=' . $entity['module'] : 'main.php?module=home';

if ($userId <= 0) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}
if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi user.', 'warning', 'main.php?module=home');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    show_sweetalert_and_redirect('Akses ditolak', 'Permintaan bulk action tidak valid atau sesi form kedaluwarsa.', 'error', $redirect);
}
if (!$entity || empty($ids) || $operation !== 'bulk_delete_pending') {
    show_sweetalert_and_redirect('Data tidak valid', 'Bulk action hanya menerima maksimal 500 transaksi pending yang valid.', 'error', $redirect);
}

try {
    $con->begin_transaction();
    $rows = bulk_transaction_lock_rows($con, $entity, $ids, $userId);
    if (count($rows) !== count($ids)) {
        throw new DomainException('Sebagian transaksi tidak ditemukan atau bukan milik Anda. Tidak ada data yang diubah.');
    }

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');
        if ($status !== 'pending') {
            throw new DomainException('Hapus Pending Terpilih hanya menerima transaksi pending. Mixed selection ditolak.');
        }
    }

    if (bulk_transaction_relation_count($con, $entity, $ids, $userId) > 0) {
        throw new DomainException('Transaksi linked atau hasil recurring tidak dapat dihapus permanen. Tidak ada data yang diubah.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM {$entity['table']}
            WHERE {$entity['owner']} = ? AND status = 'pending'
              AND {$entity['pk']} IN ({$placeholders})";
    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, 'i' . str_repeat('i', count($ids)), array_merge([$userId], $ids));
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    if ($affectedRows !== count($ids)) {
        throw new RuntimeException('Jumlah transaksi pending yang dihapus tidak sesuai.');
    }

    $con->commit();
} catch (DomainException $error) {
    $con->rollback();
    show_sweetalert_and_redirect('Aksi ditolak', $error->getMessage(), 'warning', $redirect);
} catch (Throwable $error) {
    $con->rollback();
    error_log('CashFlow bulk transaction failed: ' . $error->getMessage());
    show_sweetalert_and_redirect('Gagal', 'Bulk action gagal. Tidak ada data yang diubah.', 'error', $redirect);
}

$count = count($ids);
$message = "Berhasil menghapus {$count} transaksi pending.";
record_activity($con, $entityKey, 'hapus_massal', ucfirst($message));
show_sweetalert_and_redirect('Berhasil', $message, 'success', $redirect);
?>
