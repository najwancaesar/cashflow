<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/ui_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";
include_once __DIR__ . "/../includes/wallet_type_helper.php";

if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$userYangSedangLogin = (int) $_SESSION['id_user'];

$walletRows = cashflow_get_user_wallet_balances($con, $userYangSedangLogin);
$walletTypeFeatureReady = cashflow_wallet_type_schema_ready($con);
$legacyWalletTypes = cashflow_selectable_legacy_wallet_types($con);
$walletCardTypeReady = cashflow_wallet_legacy_type_supported($con, 'kartu');
$customWalletTypes = cashflow_get_custom_wallet_types($con, $userYangSedangLogin, false);
$walletCustomTypeMap = cashflow_get_wallet_custom_type_map($con, $userYangSedangLogin);
$walletHistoryMap = [];
foreach ($walletRows as $walletRow) {
    $walletHistoryMap[(int) $walletRow['id_wallet']] = 1;
}
$walletHistorySql = "SELECT wallet_relations.id_wallet, SUM(wallet_relations.total) AS total
                     FROM (
                         SELECT id_wallet, COUNT(*) AS total FROM pemasukan WHERE user = ? AND id_wallet IS NOT NULL GROUP BY id_wallet
                         UNION ALL
                         SELECT id_wallet, COUNT(*) AS total FROM pengeluaran WHERE user = ? AND id_wallet IS NOT NULL GROUP BY id_wallet
                         UNION ALL
                         SELECT wallet_asal_id AS id_wallet, COUNT(*) AS total FROM transfer_wallet WHERE user_id = ? GROUP BY wallet_asal_id
                         UNION ALL
                         SELECT wallet_tujuan_id AS id_wallet, COUNT(*) AS total FROM transfer_wallet WHERE user_id = ? GROUP BY wallet_tujuan_id
                         UNION ALL
                         SELECT id_wallet, COUNT(*) AS total FROM saving_goal_mutasi WHERE user_id = ? AND id_wallet IS NOT NULL GROUP BY id_wallet
                         UNION ALL
                         SELECT id_wallet, COUNT(*) AS total FROM recurring_transaction WHERE user_id = ? AND id_wallet IS NOT NULL GROUP BY id_wallet
                         UNION ALL
                         SELECT id_wallet_pembayaran AS id_wallet, COUNT(*) AS total FROM hutang WHERE user = ? AND id_wallet_pembayaran IS NOT NULL GROUP BY id_wallet_pembayaran
                         UNION ALL
                         SELECT id_wallet_penerimaan AS id_wallet, COUNT(*) AS total FROM piutang WHERE user = ? AND id_wallet_penerimaan IS NOT NULL GROUP BY id_wallet_penerimaan
                     ) AS wallet_relations
                     GROUP BY wallet_relations.id_wallet";
$walletHistoryStmt = null;
try {
    $walletHistoryStmt = $con->prepare($walletHistorySql);
    if (!$walletHistoryStmt) {
        throw new RuntimeException('Wallet history query could not be prepared.');
    }
    $walletHistoryStmt->bind_param(
        'iiiiiiii',
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin,
        $userYangSedangLogin
    );
    if (!$walletHistoryStmt->execute()) {
        throw new RuntimeException('Wallet history query failed.');
    }
    $walletHistoryMap = [];
    $walletHistoryResult = $walletHistoryStmt->get_result();
    while ($walletHistoryResult && ($walletHistoryRow = $walletHistoryResult->fetch_assoc())) {
        $walletHistoryMap[(int) $walletHistoryRow['id_wallet']] = (int) $walletHistoryRow['total'];
    }
    $walletHistoryStmt->close();
} catch (Throwable $error) {
    if ($walletHistoryStmt instanceof mysqli_stmt) {
        $walletHistoryStmt->close();
    }
    error_log('CashFlow wallet history map failed. Destructive wallet controls were disabled.');
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Wallet / Dompet</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Saldo wallet dihitung dari saldo awal dan aktivitas keuangan selesai pada setiap wallet.
                        </p>
                    </div>
                    <div class="cashflow-table-page-actions">
                        <button type="button" class="btn btn-outline-info"
                            <?= $walletTypeFeatureReady ? 'data-bs-toggle="modal" data-bs-target="#modalWalletType"' : 'disabled' ?>
                            title="<?= $walletTypeFeatureReady ? 'Kelola tipe wallet kustom' : 'Jalankan migration Sprint 1 untuk mengaktifkan tipe kustom' ?>">
                            <i class="fa fa-tags" aria-hidden="true"></i> Kelola Tipe
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalWallet">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Wallet
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <?php if (empty($walletRows)) { ?>
                            <div class="border border-radius-lg p-4 text-center cashflow-empty-state">
                                <i class="fa fa-credit-card text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-1">Belum ada wallet. Tambahkan wallet pertama kamu.</p>
                                <p class="text-xs text-secondary mb-0">Wallet pertama akan menjadi default jika belum ada default wallet.</p>
                            </div>
                        <?php } else { ?>
                            <table class="table align-items-center mb-0 cashflow-responsive-data cashflow-table-md" id="datatable">
                                <thead>
                                    <tr>
                                        <th>Nama Wallet</th>
                                        <th>Tipe</th>
                                        <th>Saldo Saat Ini</th>
                                        <th>Default</th>
                                        <th>Status</th>
                                        <th>Diperbarui</th>
                                        <th class="cashflow-action-col">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($walletRows as $row) { ?>
                                        <?php
                                        $isDefault = (string) ($row['is_default'] ?? '0') === '1';
                                        $isActive = (string) ($row['is_active'] ?? '1') === '1';
                                        $hasFinancialHistory = !empty($walletHistoryMap[(int) $row['id_wallet']]);
                                        $targetStatus = $isActive ? '0' : '1';
                                        $customWalletType = $walletCustomTypeMap[(int) $row['id_wallet']] ?? null;
                                        $walletTypeMeta = cashflow_wallet_type_meta($row['tipe_wallet'], $customWalletType);
                                        $walletTypeSelection = $walletTypeMeta['is_custom']
                                            ? 'custom:' . (int) $walletTypeMeta['id_wallet_type']
                                            : 'legacy:' . (string) $row['tipe_wallet'];
                                        ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">
                                                    <?= htmlspecialchars($row['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm text-white cashflow-wallet-type-badge"
                                                    style="background-color: <?= htmlspecialchars($walletTypeMeta['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                                    <i class="fa fa-<?= htmlspecialchars($walletTypeMeta['icon'], ENT_QUOTES, 'UTF-8') ?> me-1" aria-hidden="true"></i>
                                                    <?= htmlspecialchars(cashflow_wallet_type_text($walletTypeMeta), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td data-order="<?= htmlspecialchars((string) ($row['saldo_terkini'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars(cashflow_format_rupiah($row['saldo_terkini'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td data-order="<?= htmlspecialchars((string) ($row['updated_at'] ?? $row['created_at']), ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="badge badge-sm <?= $isDefault ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= $isDefault ? 'Default' : 'Bukan Default' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= $isActive ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= $isActive ? 'AKTIF' : 'NONAKTIF' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0">
                                                    <?= htmlspecialchars(date('d M Y H:i', strtotime($row['updated_at'] ?? $row['created_at']))) ?>
                                                </p>
                                            </td>
                                            <td class="align-middle cashflow-action-col">
                                                <div class="dropdown cashflow-row-action-dropdown">
                                                    <button class="btn btn-outline-secondary btn-sm mb-0 cashflow-row-action-toggle dropdown-toggle"
                                                        type="button" id="walletAction<?= (int) $row['id_wallet'] ?>"
                                                        data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false"
                                                        title="Buka aksi wallet" aria-label="Buka aksi wallet <?= htmlspecialchars($row['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fa fa-ellipsis-v" aria-hidden="true"></i> Aksi
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end cashflow-row-action-menu" aria-labelledby="walletAction<?= (int) $row['id_wallet'] ?>">
                                                        <li>
                                                            <button type="button" class="dropdown-item btneditwallet"
                                                                data-id="<?= (int) $row['id_wallet'] ?>"
                                                                data-nama="<?= htmlspecialchars($row['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-tipe="<?= htmlspecialchars($walletTypeSelection, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-saldo="<?= htmlspecialchars(number_format((float) $row['saldo_awal'], 0, '', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-has-history="<?= $hasFinancialHistory ? '1' : '0' ?>"
                                                                title="Edit wallet" aria-label="Edit wallet">
                                                                <i class="fa fa-pencil me-2" aria-hidden="true"></i>Edit
                                                            </button>
                                                        </li>
                                                        <?php if ($isActive && !$isDefault) { ?>
                                                            <li>
                                                                <form action="actions/aksi_wallet.php?act=d" method="post">
                                                                    <?= csrf_input() ?>
                                                                    <input type="hidden" name="id_wallet" value="<?= (int) $row['id_wallet'] ?>">
                                                                    <button type="submit" class="dropdown-item" data-confirm="true"
                                                                        data-confirm-title="Jadikan wallet default?"
                                                                        data-confirm-text="Wallet ini akan menjadi wallet default akun Anda."
                                                                        data-confirm-confirm-text="Ya, jadikan default" data-confirm-cancel-text="Batal"
                                                                        title="Jadikan Default" aria-label="Jadikan Default">
                                                                        <i class="fa fa-star-o me-2" aria-hidden="true"></i>Jadikan Default
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php } ?>
                                                        <?php if (!$isDefault) { ?>
                                                            <li>
                                                                <form action="actions/aksi_wallet.php?act=s" method="post">
                                                                    <?= csrf_input() ?>
                                                                    <input type="hidden" name="id_wallet" value="<?= (int) $row['id_wallet'] ?>">
                                                                    <input type="hidden" name="value" value="<?= htmlspecialchars($targetStatus, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <button type="submit" class="dropdown-item" data-confirm="true"
                                                                        data-confirm-title="<?= $isActive ? 'Nonaktifkan wallet ini?' : 'Aktifkan wallet ini?' ?>"
                                                                        data-confirm-text="<?= $isActive ? 'Wallet nonaktif tidak tersedia untuk transaksi baru.' : 'Wallet akan aktif kembali.' ?>"
                                                                        data-confirm-confirm-text="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                                                        data-confirm-cancel-text="Batal"
                                                                        title="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>" aria-label="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                                        <i class="fa <?= $isActive ? 'fa-toggle-off' : 'fa-toggle-on' ?> me-2" aria-hidden="true"></i><?= $isActive ? 'NONAKTIFKAN' : 'AKTIFKAN' ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php } else { ?>
                                                            <li><span class="dropdown-item disabled" title="Wallet default tidak dapat dinonaktifkan."><i class="fa fa-lock me-2" aria-hidden="true"></i>Wallet default aktif</span></li>
                                                        <?php } ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if ($hasFinancialHistory) { ?>
                                                            <li><span class="dropdown-item disabled cashflow-row-action-note" title="Tidak dapat dihapus karena memiliki histori finansial."><i class="fa fa-lock me-2" aria-hidden="true"></i>Tidak dapat dihapus karena memiliki histori finansial.</span></li>
                                                        <?php } else { ?>
                                                            <li>
                                                                <form action="actions/aksi_wallet.php?act=h" method="post">
                                                                    <?= csrf_input() ?>
                                                                    <input type="hidden" name="id_wallet" value="<?= (int) $row['id_wallet'] ?>">
                                                                    <button type="submit" class="dropdown-item text-danger" data-confirm="true"
                                                                        data-confirm-title="Hapus wallet ini?"
                                                                        data-confirm-text="Wallet tanpa histori akan dihapus permanen."
                                                                        data-confirm-confirm-text="Ya, hapus" data-confirm-cancel-text="Batal"
                                                                        title="Hapus" aria-label="Hapus">
                                                                        <i class="fa fa-trash me-2" aria-hidden="true"></i>Hapus
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
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

<div class="modal fade" id="modalWallet" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="modalWalletLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="actions/aksi_wallet.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3" id="wallet_modal_title">Wallet</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_wallet" id="id_wallet" class="form-control">
                    <div class="row">
                        <label class="form-label">Nama Wallet</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="nama_wallet" id="nama_wallet" class="form-control" maxlength="100" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Tipe Wallet</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="tipe_wallet" id="tipe_wallet" required>
                                <option value="">Pilih Tipe</option>
                                <optgroup label="Tipe Bawaan">
                                    <?php foreach ($legacyWalletTypes as $legacyKey => $legacyType) { ?>
                                        <option value="legacy:<?= htmlspecialchars($legacyKey, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($legacyType['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </optgroup>
                                <?php if (!$walletCardTypeReady) { ?>
                                    <option value="" disabled>Kartu (jalankan migration tipe Kartu)</option>
                                <?php } ?>
                                <?php if (!empty($customWalletTypes)) { ?>
                                    <optgroup label="Tipe Kustom Saya">
                                        <?php foreach ($customWalletTypes as $customType) { ?>
                                            <?php $customTypeIsActive = (int) ($customType['is_active'] ?? 0) === 1; ?>
                                            <option value="custom:<?= (int) $customType['id_wallet_type'] ?>"
                                                data-wallet-type-active="<?= $customTypeIsActive ? '1' : '0' ?>"
                                                <?= $customTypeIsActive ? '' : 'disabled' ?>>
                                                <?= htmlspecialchars($customType['nama_tipe'], ENT_QUOTES, 'UTF-8') ?><?= $customTypeIsActive ? '' : ' (Nonaktif)' ?>
                                            </option>
                                        <?php } ?>
                                    </optgroup>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Saldo Awal</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="saldo_awal" id="saldo_awal" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="0">
                        </div>
                        <small id="walletSaldoAwalLockMessage" class="text-secondary d-none mt-1">Saldo awal dikunci karena wallet sudah memiliki histori transaksi.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="simpan" class="btn btn-info">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($walletTypeFeatureReady) { ?>
<div class="modal fade" id="modalWalletType" tabindex="-1" aria-labelledby="modalWalletTypeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="modalWalletTypeLabel">Kelola Tipe Wallet</h5>
                    <p class="text-xs text-secondary mb-0">Tipe kustom hanya tersedia untuk akun Anda.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <form action="actions/aksi_wallet_type.php?act=save" method="post" id="walletTypeForm"
                    class="cashflow-wallet-type-form border border-radius-lg p-3 mb-4">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_wallet_type" id="wallet_type_id">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="wallet_type_name">Nama Tipe</label>
                            <input type="text" class="cashflow-modal-control" name="nama_tipe" id="wallet_type_name" maxlength="50" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="wallet_type_icon">Ikon</label>
                            <select class="cashflow-modal-control cashflow-modal-select" name="icon" id="wallet_type_icon" required>
                                <?php foreach (cashflow_wallet_type_icon_options() as $iconKey => $iconLabel) { ?>
                                    <option value="<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($iconLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label" for="wallet_type_color">Warna</label>
                            <input type="color" class="cashflow-color-control w-100" name="warna"
                                id="wallet_type_color" value="#64748B" required>
                        </div>
                        <div class="col-12 col-md-2 d-grid gap-2">
                            <button type="submit" class="btn btn-info mb-0">Simpan</button>
                            <button type="button" class="btn btn-outline-secondary mb-0 d-none" id="walletTypeCancelEdit">Batal</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($customWalletTypes)) { ?>
                    <div class="cashflow-empty-state">
                        <i class="fa fa-tags" aria-hidden="true"></i>
                        <p class="mb-0">Belum ada tipe wallet kustom.</p>
                    </div>
                <?php } else { ?>
                    <div class="cashflow-wallet-type-list">
                        <?php foreach ($customWalletTypes as $customType) { ?>
                            <?php $customTypeActive = (int) ($customType['is_active'] ?? 0) === 1; ?>
                            <div class="cashflow-wallet-type-item">
                                <div class="d-flex align-items-center gap-3 min-width-0">
                                    <span class="cashflow-wallet-type-icon text-white"
                                        style="background-color: <?= htmlspecialchars(cashflow_normalize_wallet_type_color($customType['warna']), ENT_QUOTES, 'UTF-8') ?>;">
                                        <i class="fa fa-<?= htmlspecialchars(cashflow_normalize_wallet_type_icon($customType['icon']), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-width-0">
                                        <p class="text-sm font-weight-bold mb-1 text-truncate"><?= htmlspecialchars($customType['nama_tipe'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <span class="badge badge-sm <?= $customTypeActive ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                            <?= $customTypeActive ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-warning btn-sm mb-0 btn-edit-wallet-type"
                                        data-id="<?= (int) $customType['id_wallet_type'] ?>"
                                        data-name="<?= htmlspecialchars($customType['nama_tipe'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-icon="<?= htmlspecialchars(cashflow_normalize_wallet_type_icon($customType['icon']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-color="<?= htmlspecialchars(cashflow_normalize_wallet_type_color($customType['warna']), ENT_QUOTES, 'UTF-8') ?>">
                                        Edit
                                    </button>
                                    <form action="actions/aksi_wallet_type.php?act=status" method="post" class="m-0">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id_wallet_type" value="<?= (int) $customType['id_wallet_type'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $customTypeActive ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm mb-0">
                                            <?= $customTypeActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <form action="actions/aksi_wallet_type.php?act=delete" method="post" class="m-0">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id_wallet_type" value="<?= (int) $customType['id_wallet_type'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm mb-0"
                                            data-confirm="true"
                                            data-confirm-title="Hapus tipe wallet?"
                                            data-confirm-text="Tipe hanya dapat dihapus jika belum digunakan wallet."
                                            data-confirm-confirm-text="Ya, hapus"
                                            data-confirm-cancel-text="Batal">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script>
    $(document).ready(function() {
        if ($('#datatable').length) {
            $('#datatable').DataTable({
                language: {
                    "paginate": {
                        "first": "&laquo",
                        "last": "&raquo",
                        "next": "&gt",
                        "previous": "&lt"
                    },
                },
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false }
                ],
                order: [[5, 'desc']],
                dom: '<"cashflow-datatable-top"l<"input-group input-group-outline"f>>rt<"cashflow-datatable-bottom"ip><"clear">'
            });
        }

        $('.cashflow-table-page-actions').appendTo('.cashflow-datatable-top');

        $(document).on("click", ".btneditwallet", function() {
            $('#tipe_wallet option[data-wallet-type-active="0"]').prop('disabled', true);
            const selectedType = $(this).attr("data-tipe");
            $('#tipe_wallet option[value="' + selectedType + '"]').prop('disabled', false);
            $('#modalWallet').modal('show');
            $('#wallet_modal_title').text('Edit Wallet');
            $('#id_wallet').val($(this).attr("data-id"));
            $('#nama_wallet').val($(this).attr("data-nama"));
            $('#tipe_wallet').val($(this).attr("data-tipe"));
            $('#saldo_awal').val($(this).attr("data-saldo"));
            const hasHistory = $(this).attr('data-has-history') === '1';
            $('#saldo_awal').prop('readonly', hasHistory).attr('aria-readonly', hasHistory ? 'true' : 'false');
            $('#walletSaldoAwalLockMessage').toggleClass('d-none', !hasHistory);

            if (typeof applyNominalFormatting === 'function') {
                applyNominalFormatting(document.getElementById('saldo_awal'));
            }
        });

        $('#modalWallet').on('hidden.bs.modal', function() {
            $('#wallet_modal_title').text('Wallet');
            $('#id_wallet').val('');
            $('#nama_wallet').val('');
            $('#tipe_wallet').val('');
            $('#saldo_awal').val('').prop('readonly', false).attr('aria-readonly', 'false');
            $('#walletSaldoAwalLockMessage').addClass('d-none');
            $('#tipe_wallet option[data-wallet-type-active="0"]').prop('disabled', true);
        });

        function resetWalletTypeForm() {
            $('#wallet_type_id').val('');
            $('#wallet_type_name').val('');
            $('#wallet_type_icon').val('credit-card');
            $('#wallet_type_color').val('#64748B');
            $('#walletTypeCancelEdit').addClass('d-none');
        }

        $(document).on('click', '.btn-edit-wallet-type', function() {
            $('#wallet_type_id').val($(this).attr('data-id'));
            $('#wallet_type_name').val($(this).attr('data-name'));
            $('#wallet_type_icon').val($(this).attr('data-icon'));
            $('#wallet_type_color').val($(this).attr('data-color'));
            $('#walletTypeCancelEdit').removeClass('d-none');
            document.getElementById('wallet_type_name').focus();
        });

        $('#walletTypeCancelEdit').on('click', resetWalletTypeForm);
        $('#modalWalletType').on('hidden.bs.modal', resetWalletTypeForm);
    });
</script>
