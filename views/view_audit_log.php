<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";

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

function audit_log_valid_date($value)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', (string) $value));

    return checkdate($month, $day, $year);
}

function audit_log_bind_params($stmt, $types, array $values)
{
    if ($types === '' || empty($values)) {
        return;
    }

    $params = [$types];
    foreach ($values as $key => $value) {
        $params[] = &$values[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
}

$logRows = [];
$filterUsers = [];
$moduleOptions = [];
$aksiOptions = [];
$tableExists = false;
$maxAuditRows = 1000;
$filterTanggalAwal = trim((string) ($_GET['tanggal_awal'] ?? ''));
$filterTanggalAkhir = trim((string) ($_GET['tanggal_akhir'] ?? ''));
$filterUserId = (int) ($_GET['user_id'] ?? 0);
$filterModule = trim((string) ($_GET['module_filter'] ?? ''));
$filterAksi = trim((string) ($_GET['aksi'] ?? ''));

if ($filterTanggalAwal !== '' && !audit_log_valid_date($filterTanggalAwal)) {
    $filterTanggalAwal = '';
}

if ($filterTanggalAkhir !== '' && !audit_log_valid_date($filterTanggalAkhir)) {
    $filterTanggalAkhir = '';
}

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
    $userStmt = $con->prepare("SELECT id_user, nama, username
                               FROM user
                               ORDER BY nama ASC, username ASC");
    if ($userStmt) {
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        while ($userRow = $userResult ? $userResult->fetch_assoc() : null) {
            $filterUsers[] = $userRow;
        }
        $userStmt->close();
    }

    $moduleStmt = $con->prepare("SELECT DISTINCT module
                                 FROM activity_log
                                 WHERE module <> ''
                                 ORDER BY module ASC
                                 LIMIT 200");
    if ($moduleStmt) {
        $moduleStmt->execute();
        $moduleResult = $moduleStmt->get_result();
        while ($moduleRow = $moduleResult ? $moduleResult->fetch_assoc() : null) {
            $moduleOptions[] = (string) $moduleRow['module'];
        }
        $moduleStmt->close();
    }

    $aksiStmt = $con->prepare("SELECT DISTINCT aksi
                               FROM activity_log
                               WHERE aksi <> ''
                               ORDER BY aksi ASC
                               LIMIT 300");
    if ($aksiStmt) {
        $aksiStmt->execute();
        $aksiResult = $aksiStmt->get_result();
        while ($aksiRow = $aksiResult ? $aksiResult->fetch_assoc() : null) {
            $aksiOptions[] = (string) $aksiRow['aksi'];
        }
        $aksiStmt->close();
    }

    $whereClauses = [];
    $bindTypes = '';
    $bindValues = [];

    if ($filterTanggalAwal !== '') {
        $whereClauses[] = "activity_log.created_at >= ?";
        $bindTypes .= 's';
        $bindValues[] = $filterTanggalAwal . ' 00:00:00';
    }

    if ($filterTanggalAkhir !== '') {
        $whereClauses[] = "activity_log.created_at <= ?";
        $bindTypes .= 's';
        $bindValues[] = $filterTanggalAkhir . ' 23:59:59';
    }

    if ($filterUserId > 0) {
        $whereClauses[] = "activity_log.user_id = ?";
        $bindTypes .= 'i';
        $bindValues[] = $filterUserId;
    }

    if ($filterModule !== '') {
        $whereClauses[] = "activity_log.module = ?";
        $bindTypes .= 's';
        $bindValues[] = $filterModule;
    }

    if ($filterAksi !== '') {
        $whereClauses[] = "activity_log.aksi = ?";
        $bindTypes .= 's';
        $bindValues[] = $filterAksi;
    }

    $whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
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
              {$whereSql}
              ORDER BY activity_log.created_at DESC, activity_log.id_log DESC
              LIMIT {$maxAuditRows}";
    $stmt = $con->prepare($query);
    if ($stmt) {
        if ($bindTypes !== '') {
            audit_log_bind_params($stmt, $bindTypes, $bindValues);
        }

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
                            Menampilkan maksimal <?= (int) $maxAuditRows ?> aktivitas terbaru sesuai filter.
                        </p>
                        <p class="text-xs text-secondary mb-0">
                            Audit log akan terus bertambah selama aplikasi dipakai. Gunakan cleanup terkontrol jika data lama sudah tidak diperlukan.
                        </p>
                    </div>
                    <?php if ($tableExists) { ?>
                        <div class="px-4 pt-3">
                            <form id="auditLogFilterForm" method="GET" action="main.php" class="audit-log-filter-panel">
                                <input type="hidden" name="module" value="audit_log">
                                <div class="audit-log-filter-grid">
                                    <div class="audit-log-filter-field">
                                        <label for="tanggal_awal">Tanggal Awal</label>
                                        <div class="audit-log-control-wrap date-wrap">
                                            <input type="date" id="tanggal_awal" name="tanggal_awal" class="audit-log-filter-control audit-log-date" value="<?= htmlspecialchars($filterTanggalAwal, ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="audit-log-filter-field">
                                        <label for="tanggal_akhir">Tanggal Akhir</label>
                                        <div class="audit-log-control-wrap date-wrap">
                                            <input type="date" id="tanggal_akhir" name="tanggal_akhir" class="audit-log-filter-control audit-log-date" value="<?= htmlspecialchars($filterTanggalAkhir, ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                    <div class="audit-log-filter-field">
                                        <label for="user_id">User</label>
                                        <div class="audit-log-control-wrap">
                                            <select id="user_id" name="user_id" class="audit-log-filter-control audit-log-select">
                                                <option value="">Semua User</option>
                                                <?php foreach ($filterUsers as $filterUser) { ?>
                                                    <?php
                                                    $filterUserLabel = trim((string) ($filterUser['nama'] ?? '')) . ' (@' . (string) ($filterUser['username'] ?? '-') . ')';
                                                    ?>
                                                    <option value="<?= (int) $filterUser['id_user'] ?>" <?= $filterUserId === (int) $filterUser['id_user'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($filterUserLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="audit-log-filter-field">
                                        <label for="module_filter">Module</label>
                                        <div class="audit-log-control-wrap">
                                            <select id="module_filter" name="module_filter" class="audit-log-filter-control audit-log-select">
                                                <option value="">Semua Module</option>
                                                <?php foreach ($moduleOptions as $moduleOption) { ?>
                                                    <option value="<?= htmlspecialchars($moduleOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filterModule === $moduleOption ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($moduleOption, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="audit-log-filter-field">
                                        <label for="aksi">Aksi</label>
                                        <div class="audit-log-control-wrap">
                                            <select id="aksi" name="aksi" class="audit-log-filter-control audit-log-select">
                                                <option value="">Semua Aksi</option>
                                                <?php foreach ($aksiOptions as $aksiOption) { ?>
                                                    <option value="<?= htmlspecialchars($aksiOption, ENT_QUOTES, 'UTF-8') ?>" <?= $filterAksi === $aksiOption ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($aksiOption, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="audit-log-filter-actions">
                                        <button type="submit" form="auditLogFilterForm" class="btn btn-info mb-0">Filter</button>
                                        <a href="main.php?module=audit_log" class="btn btn-outline-secondary mb-0">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="px-4 pt-3">
                            <div class="border border-radius-lg p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div>
                                    <p class="text-sm font-weight-bold mb-1">Cleanup Audit Log</p>
                                    <p class="text-xs text-secondary mb-0">Hapus log lama secara manual. Log baru untuk aksi cleanup akan tetap dicatat.</p>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <form id="auditLogCleanup30Form" action="actions/aksi_audit_log.php?act=cleanup" method="post" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="older_than_days" value="30">
                                        <button type="submit"
                                            form="auditLogCleanup30Form"
                                            class="btn btn-outline-danger mb-0"
                                            data-confirm="true"
                                            data-confirm-title="Hapus audit log lama?"
                                            data-confirm-text="Audit log lebih lama dari 30 hari akan dihapus permanen."
                                            data-confirm-confirm-text="Ya, hapus"
                                            data-confirm-cancel-text="Batal">
                                            Hapus &gt; 30 hari
                                        </button>
                                    </form>
                                    <form id="auditLogCleanup90Form" action="actions/aksi_audit_log.php?act=cleanup" method="post" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="older_than_days" value="90">
                                        <button type="submit"
                                            form="auditLogCleanup90Form"
                                            class="btn btn-outline-danger mb-0"
                                            data-confirm="true"
                                            data-confirm-title="Hapus audit log lama?"
                                            data-confirm-text="Audit log lebih lama dari 90 hari akan dihapus permanen."
                                            data-confirm-confirm-text="Ya, hapus"
                                            data-confirm-cancel-text="Batal">
                                            Hapus &gt; 90 hari
                                        </button>
                                    </form>
                                    <form id="auditLogCleanup180Form" action="actions/aksi_audit_log.php?act=cleanup" method="post" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="older_than_days" value="180">
                                        <button type="submit"
                                            form="auditLogCleanup180Form"
                                            class="btn btn-outline-danger mb-0"
                                            data-confirm="true"
                                            data-confirm-title="Hapus audit log lama?"
                                            data-confirm-text="Audit log lebih lama dari 180 hari akan dihapus permanen."
                                            data-confirm-confirm-text="Ya, hapus"
                                            data-confirm-cancel-text="Batal">
                                            Hapus &gt; 180 hari
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
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
