<?php
session_start();

include __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/sweetalert_helper.php';
include_once __DIR__ . '/../includes/activity_log_helper.php';
include_once __DIR__ . '/../includes/archive_helper.php';

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

function bulk_transaction_lock_rows($con, array $entity, array $ids, $userId, $archiveReady)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $archiveSelect = $archiveReady ? 'archived_at' : 'NULL AS archived_at';
    $sql = "SELECT {$entity['pk']} AS id, status, {$archiveSelect}
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

function bulk_transaction_relation_count($con, array $entity, array $ids)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 's' . str_repeat('i', count($ids)) . str_repeat('i', count($ids));
    $params = array_merge(
        [$entity['recurring_type']],
        $ids,
        $ids
    );
    $sql = "SELECT
                (SELECT COUNT(*)
                 FROM recurring_generation_log
                 WHERE tipe_transaksi = ? AND id_transaksi IN ({$placeholders}))
              + (SELECT COUNT(*)
                 FROM {$entity['parent_table']}
                 WHERE {$entity['parent_link']} IN ({$placeholders})) AS total";
    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

function bulk_transaction_lock_linked_parents($con, array $entity, array $ids, $userId, $archiveReady)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $archiveSelect = $archiveReady ? 'archived_at' : 'NULL AS archived_at';
    $sql = "SELECT {$entity['parent_pk']} AS id, {$entity['parent_link']} AS child_id, user AS owner_user, {$archiveSelect}
            FROM {$entity['parent_table']}
            WHERE {$entity['parent_link']} IN ({$placeholders})
            ORDER BY {$entity['parent_pk']} ASC
            FOR UPDATE";
    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, str_repeat('i', count($ids)), $ids);
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        if ((int) ($row['owner_user'] ?? 0) !== (int) $userId) {
            $stmt->close();
            throw new DomainException('Relasi transaksi tidak valid atau bukan milik Anda. Tidak ada data yang diubah.');
        }
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function bulk_transaction_update_archive($con, array $entity, array $ids, $userId, $operation)
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if ($operation === 'bulk_archive') {
        $sql = "UPDATE {$entity['table']}
                SET archived_at = NOW(), archived_by = ?
                WHERE {$entity['owner']} = ? AND {$entity['pk']} IN ({$placeholders}) AND archived_at IS NULL";
        $types = 'ii' . str_repeat('i', count($ids));
        $params = array_merge([$userId, $userId], $ids);
    } else {
        $sql = "UPDATE {$entity['table']}
                SET archived_at = NULL, archived_by = NULL
                WHERE {$entity['owner']} = ? AND {$entity['pk']} IN ({$placeholders}) AND archived_at IS NOT NULL";
        $types = 'i' . str_repeat('i', count($ids));
        $params = array_merge([$userId], $ids);
    }

    $stmt = $con->prepare($sql);
    bulk_transaction_bind($stmt, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

$entities = bulk_transaction_entities();
$entityKey = trim((string) ($_POST['entity'] ?? ''));
$entity = $entities[$entityKey] ?? null;
$operation = trim((string) ($_POST['operation'] ?? ''));
$ids = bulk_transaction_ids($_POST['ids'] ?? []);
$userId = (int) ($_SESSION['id_user'] ?? 0);
$returnFilter = cashflow_archive_filter($_POST['return_filter'] ?? 'aktif');
$redirect = $entity ? 'main.php?module=' . $entity['module'] . '&arsip=' . $returnFilter : 'main.php?module=home';

if ($userId <= 0) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}
if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi user.', 'warning', 'main.php?module=home');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    show_sweetalert_and_redirect('Akses ditolak', 'Permintaan bulk action tidak valid atau sesi form kedaluwarsa.', 'error', $redirect);
}
if (!$entity || empty($ids) || !in_array($operation, ['bulk_delete_pending', 'bulk_archive', 'bulk_restore'], true)) {
    show_sweetalert_and_redirect('Data tidak valid', 'Pilih transaksi dan aksi bulk yang valid.', 'error', $redirect);
}

$archiveReady = cashflow_archive_ready($con, $entityKey);
if ($operation !== 'bulk_delete_pending' && !$archiveReady) {
    show_sweetalert_and_redirect('Migration diperlukan', 'Metadata arsip belum tersedia pada database.', 'warning', $redirect);
}

try {
    $con->begin_transaction();
    $rows = bulk_transaction_lock_rows($con, $entity, $ids, $userId, $archiveReady);
    if (count($rows) !== count($ids)) {
        throw new DomainException('Sebagian transaksi tidak ditemukan atau bukan milik Anda. Tidak ada data yang diubah.');
    }

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? '');
        $isArchived = !empty($row['archived_at']);
        if ($operation === 'bulk_delete_pending' && ($status !== 'pending' || $isArchived)) {
            throw new DomainException('Hapus Pending Terpilih hanya menerima transaksi pending aktif. Mixed selection ditolak.');
        }
        if ($operation === 'bulk_archive' && ($status !== 'selesai' || $isArchived)) {
            throw new DomainException('Arsipkan Terpilih hanya menerima transaksi selesai yang masih aktif. Mixed selection ditolak.');
        }
        if ($operation === 'bulk_restore' && !$isArchived) {
            throw new DomainException('Restore Terpilih hanya menerima transaksi yang sedang diarsipkan. Mixed selection ditolak.');
        }
    }

    $affectedRows = 0;
    if ($operation === 'bulk_delete_pending') {
        if (bulk_transaction_relation_count($con, $entity, $ids) > 0) {
            throw new DomainException('Transaksi linked atau hasil recurring tidak dapat dihapus permanen. Tidak ada data yang diubah.');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $archiveClause = $archiveReady ? ' AND archived_at IS NULL' : '';
        $sql = "DELETE FROM {$entity['table']}
                WHERE {$entity['owner']} = ? AND status = 'pending'
                  AND {$entity['pk']} IN ({$placeholders}){$archiveClause}";
        $stmt = $con->prepare($sql);
        bulk_transaction_bind($stmt, 'i' . str_repeat('i', count($ids)), array_merge([$userId], $ids));
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        if ($affectedRows !== count($ids)) {
            throw new RuntimeException('Jumlah transaksi pending yang dihapus tidak sesuai.');
        }
    } else {
        $parentArchiveReady = cashflow_archive_ready($con, $entity['parent_entity']);
        $parentRows = bulk_transaction_lock_linked_parents($con, $entity, $ids, $userId, $parentArchiveReady);
        if (!empty($parentRows) && !$parentArchiveReady) {
            throw new DomainException('Metadata arsip transaksi linked belum tersedia. Tidak ada data yang diubah.');
        }

        foreach ($parentRows as $parentRow) {
            $parentArchived = !empty($parentRow['archived_at']);
            if (($operation === 'bulk_archive' && $parentArchived)
                || ($operation === 'bulk_restore' && !$parentArchived)) {
                throw new DomainException('State transaksi linked tidak sinkron. Bulk action dibatalkan tanpa perubahan.');
            }
        }

        $affectedRows = bulk_transaction_update_archive($con, $entity, $ids, $userId, $operation);
        if ($affectedRows !== count($ids)) {
            throw new RuntimeException('Jumlah transaksi yang diperbarui tidak sesuai.');
        }

        if (!empty($parentRows)) {
            $parentIds = array_map(function ($row) {
                return (int) $row['id'];
            }, $parentRows);
            $parentEntity = cashflow_archive_entities()[$entity['parent_entity']];
            $parentAffected = bulk_transaction_update_archive($con, $parentEntity, $parentIds, $userId, $operation);
            if ($parentAffected !== count($parentIds)) {
                throw new RuntimeException('Transaksi linked gagal diperbarui secara atomik.');
            }
        }
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
if ($operation === 'bulk_delete_pending') {
    $activityAction = 'hapus_massal';
    $message = "Berhasil menghapus {$count} transaksi pending.";
} elseif ($operation === 'bulk_archive') {
    $activityAction = 'arsip_massal';
    $message = "Berhasil mengarsipkan {$count} transaksi selesai. Saldo dan laporan tidak berubah.";
} else {
    $activityAction = 'restore_massal';
    $message = "Berhasil memulihkan {$count} transaksi dari arsip.";
}

record_activity($con, $entityKey, $activityAction, ucfirst($message));
show_sweetalert_and_redirect('Berhasil', $message, 'success', $redirect);
?>
