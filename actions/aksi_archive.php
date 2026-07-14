<?php
session_start();
include __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/csrf_helper.php';
include_once __DIR__ . '/../includes/sweetalert_helper.php';
include_once __DIR__ . '/../includes/activity_log_helper.php';
include_once __DIR__ . '/../includes/archive_helper.php';

/*
 * Archive transaksi dihentikan berdasarkan keputusan bisnis final.
 * Endpoint dipertahankan agar bookmark/form lama mendapat penolakan yang jelas,
 * tanpa mengubah metadata archived_at/archived_by yang sudah tersimpan.
 */
$disabledArchiveEntities = cashflow_archive_entities();
$disabledArchiveEntityKey = trim((string) ($_POST['entity'] ?? ''));
$disabledArchiveEntity = $disabledArchiveEntities[$disabledArchiveEntityKey] ?? null;
$disabledArchiveRedirect = $disabledArchiveEntity
    ? 'main.php?module=' . $disabledArchiveEntity['module']
    : 'main.php?module=home';

if ((int) ($_SESSION['id_user'] ?? 0) <= 0) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    show_sweetalert_and_redirect('Akses ditolak', 'Permintaan Archive/Restore tidak valid.', 'error', $disabledArchiveRedirect);
}
show_sweetalert_and_redirect(
    'Fitur tidak tersedia',
    'Archive dan Restore tidak lagi digunakan pada modul transaksi ini. Metadata lama tetap dipertahankan.',
    'warning',
    $disabledArchiveRedirect
);
exit;

function cashflow_archive_action_lock_record($con, $entityKey, $id, $userId)
{
    $entities = cashflow_archive_entities();
    $entity = $entities[$entityKey] ?? null;
    if (!$entity || !cashflow_archive_ready($con, $entityKey)) {
        throw new RuntimeException('Metadata arsip transaksi belum tersedia.');
    }

    $extraColumns = [
        'hutang' => ', id_pengeluaran',
        'piutang' => ', id_pemasukan',
    ];
    $extraSelect = $extraColumns[$entityKey] ?? '';
    $sql = "SELECT {$entity['pk']}, archived_at{$extraSelect}
            FROM {$entity['table']}
            WHERE {$entity['pk']} = ? AND {$entity['owner']} = ?
            LIMIT 1 FOR UPDATE";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Gagal mengunci transaksi arsip.');
    }
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function cashflow_archive_action_find_parent($con, $childEntity, $childId, $userId)
{
    $relations = [
        'pengeluaran' => ['table' => 'hutang', 'pk' => 'id_hutang', 'link' => 'id_pengeluaran', 'owner' => 'user'],
        'pemasukan' => ['table' => 'piutang', 'pk' => 'id_piutang', 'link' => 'id_pemasukan', 'owner' => 'user'],
    ];
    $relation = $relations[$childEntity] ?? null;
    if (!$relation) {
        return 0;
    }

    $sql = "SELECT {$relation['pk']}
            FROM {$relation['table']}
            WHERE {$relation['link']} = ? AND {$relation['owner']} = ?
            LIMIT 1";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Gagal memeriksa relasi transaksi.');
    }
    $stmt->bind_param('ii', $childId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row[$relation['pk']] ?? 0);
}

function cashflow_archive_action_resolve_targets($con, $entityKey, $id, $userId)
{
    $parentRelations = [
        'hutang' => ['link' => 'id_pengeluaran', 'child' => 'pengeluaran'],
        'piutang' => ['link' => 'id_pemasukan', 'child' => 'pemasukan'],
    ];
    $childRelations = [
        'pengeluaran' => ['parent' => 'hutang', 'link' => 'id_pengeluaran'],
        'pemasukan' => ['parent' => 'piutang', 'link' => 'id_pemasukan'],
    ];

    if (isset($parentRelations[$entityKey])) {
        $relation = $parentRelations[$entityKey];
        $parent = cashflow_archive_action_lock_record($con, $entityKey, $id, $userId);
        if (!$parent) {
            throw new DomainException('Transaksi tidak ditemukan atau bukan milik Anda.');
        }

        $targets = [['entity' => $entityKey, 'id' => $id]];
        $childId = (int) ($parent[$relation['link']] ?? 0);
        if ($childId > 0) {
            $child = cashflow_archive_action_lock_record($con, $relation['child'], $childId, $userId);
            if (!$child) {
                throw new DomainException('Transaksi linked tidak ditemukan. Archive dibatalkan agar data tetap konsisten.');
            }
            $targets[] = ['entity' => $relation['child'], 'id' => $childId];
        }

        return $targets;
    }

    if (isset($childRelations[$entityKey])) {
        $relation = $childRelations[$entityKey];
        $parentId = cashflow_archive_action_find_parent($con, $entityKey, $id, $userId);
        if ($parentId > 0) {
            $parent = cashflow_archive_action_lock_record($con, $relation['parent'], $parentId, $userId);
            if (!$parent || (int) ($parent[$relation['link']] ?? 0) !== $id) {
                throw new DomainException('Relasi transaksi berubah. Archive dibatalkan agar data tetap konsisten.');
            }
            $child = cashflow_archive_action_lock_record($con, $entityKey, $id, $userId);
            if (!$child) {
                throw new DomainException('Transaksi linked tidak ditemukan atau bukan milik Anda.');
            }

            return [
                ['entity' => $relation['parent'], 'id' => $parentId],
                ['entity' => $entityKey, 'id' => $id],
            ];
        }
    }

    $record = cashflow_archive_action_lock_record($con, $entityKey, $id, $userId);
    if (!$record) {
        throw new DomainException('Transaksi tidak ditemukan atau bukan milik Anda.');
    }

    return [['entity' => $entityKey, 'id' => $id]];
}

function cashflow_archive_action_update_target($con, array $target, $operation, $userId)
{
    $entities = cashflow_archive_entities();
    $entity = $entities[$target['entity']] ?? null;
    if (!$entity) {
        throw new RuntimeException('Jenis transaksi arsip tidak valid.');
    }
    $targetId = (int) ($target['id'] ?? 0);
    if ($targetId <= 0) {
        throw new RuntimeException('ID transaksi arsip tidak valid.');
    }

    if ($operation === 'archive') {
        $sql = "UPDATE {$entity['table']}
                SET archived_at = NOW(), archived_by = ?
                WHERE {$entity['pk']} = ? AND {$entity['owner']} = ? AND archived_at IS NULL";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan proses arsip.');
        }
        $stmt->bind_param('iii', $userId, $targetId, $userId);
    } else {
        $sql = "UPDATE {$entity['table']}
                SET archived_at = NULL, archived_by = NULL
                WHERE {$entity['pk']} = ? AND {$entity['owner']} = ? AND archived_at IS NOT NULL";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan proses restore.');
        }
        $stmt->bind_param('ii', $targetId, $userId);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Status arsip transaksi gagal diperbarui.');
    }
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    if ($affectedRows < 0 || $affectedRows > 1) {
        throw new RuntimeException('Jumlah transaksi yang diperbarui tidak valid.');
    }

    return $affectedRows;
}

$entities = cashflow_archive_entities();
$entityKey = trim((string) ($_POST['entity'] ?? ''));
$entity = $entities[$entityKey] ?? null;
$operation = trim((string) ($_POST['operation'] ?? ''));
$id = (int) ($_POST['id'] ?? 0);
$userId = (int) ($_SESSION['id_user'] ?? 0);
$returnFilter = cashflow_archive_filter($_POST['return_filter'] ?? 'aktif');
$redirect = $entity ? 'main.php?module=' . $entity['module'] . '&arsip=' . $returnFilter : 'main.php?module=home';

if ($userId <= 0) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}
if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengarsipkan transaksi user.', 'warning', 'main.php?module=home');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
    show_sweetalert_and_redirect('Akses ditolak', 'Permintaan arsip tidak valid atau sesi form kedaluwarsa.', 'error', $redirect);
}
if (!$entity || $id <= 0 || !in_array($operation, ['archive', 'restore'], true)) {
    show_sweetalert_and_redirect('Data tidak valid', 'Permintaan arsip tidak dikenali.', 'error', $redirect);
}
if (!cashflow_archive_ready($con, $entityKey)) {
    show_sweetalert_and_redirect('Migration diperlukan', 'Metadata arsip belum tersedia pada database.', 'warning', $redirect);
}

try {
    $con->begin_transaction();
    $targets = cashflow_archive_action_resolve_targets($con, $entityKey, $id, $userId);
    $affectedRows = 0;
    foreach ($targets as $target) {
        $affectedRows += cashflow_archive_action_update_target($con, $target, $operation, $userId);
    }
    $con->commit();
} catch (DomainException $error) {
    $con->rollback();
    show_sweetalert_and_redirect('Tidak ada perubahan', $error->getMessage(), 'warning', $redirect);
} catch (Throwable $error) {
    $con->rollback();
    error_log('Archive transaction failed: ' . $error->getMessage());
    show_sweetalert_and_redirect('Gagal', 'Transaksi gagal diperbarui.', 'error', $redirect);
}

if ($affectedRows === 0) {
    show_sweetalert_and_redirect(
        'Tidak ada perubahan',
        $operation === 'archive' ? 'Transaksi sudah diarsipkan.' : 'Transaksi sudah aktif.',
        'info',
        $redirect
    );
}

$activityAction = $operation === 'archive' ? 'arsip' : 'restore';
$activityText = $operation === 'archive' ? 'Mengarsipkan' : 'Memulihkan';
$linkedText = count($targets) > 1 ? ' beserta transaksi linked' : '';
record_activity($con, $entityKey, $activityAction, "{$activityText} {$entity['label']} ID {$id}{$linkedText}.");
$hasLinkedTarget = count($targets) > 1;
show_sweetalert_and_redirect(
    'Berhasil',
    $operation === 'archive'
        ? ($hasLinkedTarget
            ? 'Transaksi dan relasi terkait berhasil diarsipkan. Saldo dan laporan tidak berubah.'
            : 'Transaksi berhasil diarsipkan. Saldo dan laporan tidak berubah.')
        : ($hasLinkedTarget
            ? 'Transaksi dan relasi terkait berhasil dipulihkan ke daftar aktif.'
            : 'Transaksi berhasil dipulihkan ke daftar aktif.'),
    'success',
    $redirect
);
