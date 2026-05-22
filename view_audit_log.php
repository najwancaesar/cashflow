<?php
include "includes/koneksi.php";

if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    echo "<script>window.location.href='home';</script>";
    exit;
}

function audit_log_format_datetime($value)
{
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y H:i:s', $timestamp);
}

$logRows = [];
$tableExists = false;

$checkStmt = $con->prepare("SELECT COUNT(*)
                            FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'activity_log'");
if ($checkStmt) {
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult ? $checkResult->fetch_row() : [0];
    $tableExists = (int) ($checkRow[0] ?? 0) > 0;
    $checkStmt->close();
}

if ($tableExists) {
    $query = "SELECT
                activity_log.id_log,
                activity_log.user_id,
                activity_log.role,
                activity_log.module,
                activity_log.aksi,
                activity_log.deskripsi,
                activity_log.ip_address,
                activity_log.created_at,
                user.nama,
                user.username
              FROM activity_log
              LEFT JOIN user
                ON user.id_user = activity_log.user_id
              ORDER BY activity_log.created_at DESC, activity_log.id_log DESC
              LIMIT 200";
    $stmt = $con->prepare($query);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result ? $result->fetch_assoc() : null) {
            $logRows[] = $row;
        }

        $stmt->close();
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Audit Log</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Menampilkan maksimal 200 aktivitas terbaru user dan admin.
                        </p>
                    </div>
                    <div class="table-responsive p-4">
                        <?php if (!$tableExists) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-history text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-0">Tabel activity_log belum tersedia. Jalankan SQL manual audit log terlebih dahulu.</p>
                            </div>
                        <?php } elseif (empty($logRows)) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-history text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-0">Belum ada aktivitas yang tercatat.</p>
                            </div>
                        <?php } else { ?>
                            <table class="table align-items-center mb-0" id="datatableAuditLog">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Module</th>
                                        <th>Aksi</th>
                                        <th>Deskripsi</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logRows as $row) { ?>
                                        <?php
                                        $userLabel = '-';
                                        if (!empty($row['nama']) || !empty($row['username'])) {
                                            $userLabel = trim((string) ($row['nama'] ?? '')) . ' (@' . (string) ($row['username'] ?? '-') . ')';
                                        } elseif (!empty($row['user_id'])) {
                                            $userLabel = 'User ID ' . (int) $row['user_id'];
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(audit_log_format_datetime($row['created_at']), ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= ($row['role'] ?? '') === 'admin' ? 'bg-gradient-dark' : 'bg-gradient-info' ?>">
                                                    <?= htmlspecialchars(ucfirst((string) ($row['role'] ?? '-')), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['module'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($row['aksi'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['deskripsi'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['ip_address'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($('#datatableAuditLog').length) {
        $('#datatableAuditLog').DataTable({
            order: [],
            language: {
                "paginate": {
                    "first": "&laquo",
                    "last": "&raquo",
                    "next": "&gt",
                    "previous": "&lt"
                },
            },
            dom: ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">'
        });
    }
});
</script>
