<?php

if (!function_exists('cashflow_archive_entities')) {
    function cashflow_archive_entities()
    {
        return [
            'pemasukan' => ['table' => 'pemasukan', 'pk' => 'id_pemasukan', 'owner' => 'user', 'module' => 'pemasukan', 'label' => 'pemasukan'],
            'pengeluaran' => ['table' => 'pengeluaran', 'pk' => 'id_pengeluaran', 'owner' => 'user', 'module' => 'pengeluaran', 'label' => 'pengeluaran'],
            'transfer_wallet' => ['table' => 'transfer_wallet', 'pk' => 'id_transfer', 'owner' => 'user_id', 'module' => 'transfer_wallet', 'label' => 'transfer wallet'],
            'hutang' => ['table' => 'hutang', 'pk' => 'id_hutang', 'owner' => 'user', 'module' => 'hutang', 'label' => 'utang'],
            'piutang' => ['table' => 'piutang', 'pk' => 'id_piutang', 'owner' => 'user', 'module' => 'piutang', 'label' => 'piutang'],
        ];
    }
}

if (!function_exists('cashflow_archive_schema_map')) {
    function cashflow_archive_schema_map($con)
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = array_fill_keys(array_keys(cashflow_archive_entities()), false);
        try {
            $result = $con->query(
                "SELECT TABLE_NAME, COUNT(DISTINCT COLUMN_NAME) AS archive_columns
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('pemasukan','pengeluaran','transfer_wallet','hutang','piutang')
                   AND COLUMN_NAME IN ('archived_at','archived_by')
                 GROUP BY TABLE_NAME"
            );
            while ($result && ($row = $result->fetch_assoc())) {
                if (array_key_exists($row['TABLE_NAME'], $cache)) {
                    $cache[$row['TABLE_NAME']] = (int) $row['archive_columns'] === 2;
                }
            }
        } catch (Throwable $error) {
            error_log('CashFlow archive schema check failed.');
        }

        return $cache;
    }
}

if (!function_exists('cashflow_archive_ready')) {
    function cashflow_archive_ready($con, $entity)
    {
        $map = cashflow_archive_schema_map($con);
        return !empty($map[$entity]);
    }
}

if (!function_exists('cashflow_archive_filter')) {
    function cashflow_archive_filter($value)
    {
        $value = trim((string) $value);
        return in_array($value, ['aktif', 'diarsipkan', 'semua'], true) ? $value : 'aktif';
    }
}

if (!function_exists('cashflow_archive_record_is_archived')) {
    function cashflow_archive_record_is_archived($con, $entityKey, $id, $userId)
    {
        $entities = cashflow_archive_entities();
        $entity = $entities[$entityKey] ?? null;
        if (!$entity || !cashflow_archive_ready($con, $entityKey)) {
            return false;
        }

        $sql = "SELECT archived_at FROM {$entity['table']}
                WHERE {$entity['pk']} = ? AND {$entity['owner']} = ? LIMIT 1";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row && !empty($row['archived_at']);
    }
}

if (!function_exists('cashflow_archive_filter_sql')) {
    function cashflow_archive_filter_sql($alias, $filter, $schemaReady)
    {
        if (!$schemaReady) {
            return $filter === 'diarsipkan' ? '1 = 0' : '1 = 1';
        }

        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($filter === 'diarsipkan') {
            return "{$alias}.archived_at IS NOT NULL";
        }
        if ($filter === 'semua') {
            return '1 = 1';
        }

        return "{$alias}.archived_at IS NULL";
    }
}

if (!function_exists('cashflow_render_archive_filter')) {
    function cashflow_render_archive_filter($module, $filter, $schemaReady)
    {
        $moduleEsc = htmlspecialchars((string) $module, ENT_QUOTES, 'UTF-8');
        $filter = cashflow_archive_filter($filter);
        ?>
        <div class="archive-filter-toolbar px-4 pt-3">
            <?php if (!$schemaReady) { ?>
                <div class="alert alert-warning text-white mb-3" role="alert">
                    Fitur arsip belum aktif. Jalankan migration Sprint 3A secara manual setelah backup database.
                </div>
            <?php } ?>
            <form method="get" action="main.php" class="archive-filter-form cashflow-filter-form">
                <input type="hidden" name="module" value="<?= $moduleEsc ?>">
                <div class="cashflow-filter-field">
                    <label for="archiveFilter<?= $moduleEsc ?>" class="cashflow-filter-label">Tampilkan</label>
                    <div class="cashflow-control-wrap cashflow-select-wrap">
                        <select id="archiveFilter<?= $moduleEsc ?>" name="arsip" class="cashflow-form-control cashflow-select-control" <?= $schemaReady ? '' : 'disabled' ?>>
                            <option value="aktif" <?= $filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="diarsipkan" <?= $filter === 'diarsipkan' ? 'selected' : '' ?>>Diarsipkan</option>
                            <option value="semua" <?= $filter === 'semua' ? 'selected' : '' ?>>Semua</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-info mb-0" <?= $schemaReady ? '' : 'disabled' ?>>Terapkan</button>
            </form>
        </div>
        <?php
    }
}

if (!function_exists('cashflow_render_archive_action')) {
    function cashflow_render_archive_action($entity, $id, $filter, $isArchived, $schemaReady)
    {
        if (!$schemaReady || (int) $id <= 0) {
            return;
        }

        $operation = $isArchived ? 'restore' : 'archive';
        $title = $isArchived ? 'Pulihkan transaksi ini?' : 'Arsipkan transaksi ini?';
        $text = $isArchived
            ? 'Transaksi akan kembali tampil pada daftar aktif.'
            : 'Transaksi disembunyikan dari daftar aktif. Saldo, status, linked transaction, dan laporan tidak berubah.';
        $label = $isArchived ? 'Restore' : 'Arsipkan';
        $icon = $isArchived ? 'fa-undo' : 'fa-archive';
        ?>
        <form action="actions/aksi_archive.php" method="post" class="d-inline archive-action-form">
            <?= csrf_input() ?>
            <input type="hidden" name="entity" value="<?= htmlspecialchars((string) $entity, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="operation" value="<?= htmlspecialchars($operation, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_filter" value="<?= htmlspecialchars(cashflow_archive_filter($filter), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit"
                data-confirm="true"
                data-confirm-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                data-confirm-text="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>"
                data-confirm-confirm-text="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                data-confirm-cancel-text="Batal"
                class="text-secondary <?= $isArchived ? 'text-info' : '' ?> font-weight-bold text-xs border-0 bg-transparent p-0">
                <i class="fa <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>
        <?php
    }
}
