<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/ui_helper.php";
include_once __DIR__ . "/../includes/wallet_type_helper.php";
include_once __DIR__ . "/../includes/budget_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];
$walletCustomTypeMap = [];
if ($userYangSedangLogin > 0) {
    $loadedWalletCustomTypeMap = cashflow_get_wallet_custom_type_map($con, $userYangSedangLogin);
    $walletCustomTypeMap = is_array($loadedWalletCustomTypeMap) ? $loadedWalletCustomTypeMap : [];
}
$budgetUsageMap = $userYangSedangLogin > 0
    ? cashflow_get_user_budget_usage_map($con, $userYangSedangLogin)
    : [];

function build_pengeluaran_budget_confirmation(array $row, array $budgetUsageMap)
{
    $default = [
        'title' => 'Ubah status transaksi?',
        'text' => 'Status transaksi akan diubah menjadi Selesai.',
    ];

    if ((string) ($row['status'] ?? '') === 'selesai') {
        $default['text'] = 'Status transaksi akan diubah menjadi Pending.';
        return $default;
    }

    $key = cashflow_budget_period_key($row['id_kategori'] ?? 0, $row['tanggal'] ?? '');
    $budget = $key !== '' ? ($budgetUsageMap[$key] ?? null) : null;
    $budgetNominal = (float) ($budget['nominal_budget'] ?? 0);
    if ($budgetNominal <= 0) {
        return $default;
    }

    $projectedUsage = (float) ($budget['total_terpakai'] ?? 0) + (float) ($row['jumlah'] ?? 0);
    if ($projectedUsage < $budgetNominal) {
        return $default;
    }

    return [
        'title' => 'Budget kategori akan terlampaui',
        'text' => 'Jika diselesaikan, penggunaan kategori menjadi Rp. '
            . number_format($projectedUsage)
            . ' dari budget Rp. '
            . number_format($budgetNominal)
            . '. Transaksi tetap boleh dilanjutkan.',
    ];
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$kategoriPengeluaran = [];
$kategoriQuery = "SELECT id_kategori, nama_kategori
                  FROM kategori
                  WHERE user_id = ? AND tipe_kategori = 'pengeluaran'
                  ORDER BY nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);

while ($kategori = mysqli_fetch_assoc($kategoriResult)) {
    $kategoriPengeluaran[] = $kategori;
}

mysqli_stmt_close($kategoriStmt);

$walletAktif = [];
$defaultWalletAktif = null;
$walletQuery = "SELECT id_wallet, nama_wallet, tipe_wallet, is_default
                FROM wallet
                WHERE user_id = ? AND is_active = 1
                ORDER BY is_default DESC, nama_wallet ASC";
$walletStmt = mysqli_prepare($con, $walletQuery);
mysqli_stmt_bind_param($walletStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($walletStmt);
$walletResult = mysqli_stmt_get_result($walletStmt);

while ($wallet = mysqli_fetch_assoc($walletResult)) {
    $walletAktif[] = $wallet;

    if ($defaultWalletAktif === null && (string) ($wallet['is_default'] ?? '0') === '1') {
        $defaultWalletAktif = $wallet;
    }
}

mysqli_stmt_close($walletStmt);

$defaultWalletId = $defaultWalletAktif ? (int) $defaultWalletAktif['id_wallet'] : '';
$defaultWalletName = $defaultWalletAktif ? (string) $defaultWalletAktif['nama_wallet'] : 'Dompet Utama';

$transaksiQuery = "SELECT
                       pengeluaran.*,
                       kategori.nama_kategori,
                       wallet.nama_wallet,
                       wallet.tipe_wallet,
                       wallet.is_active AS wallet_is_active,
                       recurring_generation_log.id_log AS recurring_log_id,
                       hutang.id_hutang AS linked_hutang_id
                   FROM pengeluaran
                   LEFT JOIN kategori
                       ON pengeluaran.id_kategori = kategori.id_kategori
                      AND kategori.user_id = pengeluaran.user
                      AND kategori.tipe_kategori = 'pengeluaran'
                   LEFT JOIN wallet
                       ON pengeluaran.id_wallet = wallet.id_wallet
                      AND wallet.user_id = pengeluaran.user
                   LEFT JOIN recurring_generation_log
                       ON recurring_generation_log.user_id = pengeluaran.user
                      AND recurring_generation_log.tipe_transaksi = 'pengeluaran'
                      AND recurring_generation_log.id_transaksi = pengeluaran.id_pengeluaran
                   LEFT JOIN hutang
                       ON hutang.user = pengeluaran.user
                      AND hutang.id_pengeluaran = pengeluaran.id_pengeluaran
                   WHERE pengeluaran.user = ?
                   ORDER BY pengeluaran.tanggal DESC, pengeluaran.id_pengeluaran DESC";
$transaksiStmt = mysqli_prepare($con, $transaksiQuery);
mysqli_stmt_bind_param($transaksiStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($transaksiStmt);
$transaksiResult = mysqli_stmt_get_result($transaksiStmt);

$transaksiRows = [];
while ($row = mysqli_fetch_assoc($transaksiResult)) {
    $transaksiRows[] = $row;
}
mysqli_stmt_close($transaksiStmt);

$renderPengeluaranRow = function (array $row, bool $includeBulkColumn = false) use ($defaultWalletName, $defaultWalletId, $walletCustomTypeMap, $budgetUsageMap) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $bulkSelectable = $statusTransaksi === 'pending'
        && empty($row['recurring_log_id'])
        && empty($row['linked_hutang_id']);
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayTypeMeta = cashflow_wallet_type_meta_from_row($row, $walletCustomTypeMap);
    $walletDisplayType = cashflow_wallet_type_text($walletDisplayTypeMeta);
    $budgetConfirmation = build_pengeluaran_budget_confirmation($row, $budgetUsageMap);
    $editWalletId = !empty($row['id_wallet']) && (string) ($row['wallet_is_active'] ?? '0') === '1'
        ? (int) $row['id_wallet']
        : $defaultWalletId;
?>
    <tr>
        <?php if ($includeBulkColumn) { ?>
            <td class="bulk-select-col text-center">
                <?php if ($bulkSelectable) { ?>
                <input
                    type="checkbox"
                    class="bulk-select-row bulk-pengeluaran-checkbox"
                    value="<?= (int) $row['id_pengeluaran'] ?>"
                    aria-label="Pilih transaksi pengeluaran ini">
                <?php } else { ?>
                    <input type="checkbox" disabled aria-label="Transaksi ini tidak tersedia untuk bulk delete" title="Hanya transaksi pending tanpa relasi yang dapat dipilih">
                <?php } ?>
            </td>
        <?php } ?>
        <td class="align-middle text-center">
            <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars(cashflow_format_date($row['tanggal']), ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td class="cashflow-long-text-col">
            <p class="text-xs text-secondary mb-0 cashflow-long-text"><?= htmlspecialchars($row['catatan']) ?></p>
        </td>
        <td>
            <p class="text-xs text-secondary mb-0">
                <?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?>
            </p>
        </td>
        <td>
            <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars(cashflow_format_rupiah($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?></p>
        </td>
        <td>
            <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($walletDisplayName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-secondary mb-0"><?= cashflow_wallet_type_inline_html($walletDisplayTypeMeta) ?></p>
        </td>
        <td class="align-middle text-center text-sm">
            <form action="actions/aksi_pengeluaran.php?act=l" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($targetStatus, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit"
                    data-confirm="true"
                    data-confirm-title="<?= htmlspecialchars($budgetConfirmation['title'], ENT_QUOTES, 'UTF-8') ?>"
                    data-confirm-text="<?= htmlspecialchars($budgetConfirmation['text'], ENT_QUOTES, 'UTF-8') ?>"
                    data-confirm-confirm-text="Ya, ubah"
                    data-confirm-cancel-text="Batal"
                    class="badge badge-sm <?= $statusTransaksi === 'selesai' ? 'bg-gradient-success' : 'bg-gradient-warning' ?> border-0 text-white">
                    <?= htmlspecialchars($statusTransaksi, ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        </td>
        <td class="align-middle cashflow-action-col">
            <div class="cashflow-action-group">
            <?php if ($statusTransaksi === 'pending' && empty($row['recurring_log_id']) && empty($row['linked_hutang_id'])) { ?>
            <form action="actions/aksi_pengeluaran.php?act=h" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                <button type="submit"
                    data-confirm="true"
                    data-confirm-title="Hapus pengeluaran ini?"
                    data-confirm-text="Data pengeluaran yang dihapus tidak bisa dikembalikan."
                    data-confirm-confirm-text="Ya, hapus"
                    data-confirm-cancel-text="Batal"
                    class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0"
                    title="Hapus pengeluaran pending" aria-label="Hapus pengeluaran pending">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
            </form>
            <?php } ?>

            <a href="#" role="button"
                data-id="<?= (int) $row['id_pengeluaran'] ?>"
                data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                class="text-secondary text-warning font-weight-bold text-xs btneditpengeluaran"
                title="Edit pengeluaran" aria-label="Edit pengeluaran">
                <i class="fa fa-pencil" aria-hidden="true"></i>
            </a>
            </div>
        </td>
    </tr>
<?php
};

$renderMobilePengeluaranCard = function (array $row) use ($defaultWalletName, $defaultWalletId, $walletCustomTypeMap, $budgetUsageMap) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayTypeMeta = cashflow_wallet_type_meta_from_row($row, $walletCustomTypeMap);
    $walletDisplayType = cashflow_wallet_type_text($walletDisplayTypeMeta);
    $budgetConfirmation = build_pengeluaran_budget_confirmation($row, $budgetUsageMap);
    $editWalletId = !empty($row['id_wallet']) && (string) ($row['wallet_is_active'] ?? '0') === '1'
        ? (int) $row['id_wallet']
        : $defaultWalletId;
    $searchText = strtolower(trim(implode(' ', [
        (string) ($row['tanggal'] ?? ''),
        (string) ($row['catatan'] ?? ''),
        (string) ($row['nama_kategori'] ?? ''),
        (string) ($row['nama_wallet'] ?? ''),
        $walletDisplayType,
        (string) ($row['status'] ?? ''),
        (string) ($row['jumlah'] ?? ''),
    ])));
?>
    <article class="mobile-transaction-card" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Tanggal</span>
            <span class="mobile-transaction-value"><?= htmlspecialchars(cashflow_format_date($row['tanggal']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Catatan</span>
            <span class="mobile-transaction-value"><?= htmlspecialchars($row['catatan']) ?></span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Kategori</span>
            <span class="mobile-transaction-value"><?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?></span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Jumlah Pengeluaran</span>
            <span class="mobile-transaction-value fw-bold"><?= htmlspecialchars(cashflow_format_rupiah($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Wallet</span>
            <span class="mobile-transaction-value">
                <strong><?= htmlspecialchars($walletDisplayName, ENT_QUOTES, 'UTF-8') ?></strong><br>
                <small><?= cashflow_wallet_type_inline_html($walletDisplayTypeMeta) ?></small>
            </span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Status</span>
            <span class="mobile-transaction-value">
                <form action="actions/aksi_pengeluaran.php?act=l" method="post" class="d-inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($targetStatus, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit"
                        data-confirm="true"
                        data-confirm-title="<?= htmlspecialchars($budgetConfirmation['title'], ENT_QUOTES, 'UTF-8') ?>"
                        data-confirm-text="<?= htmlspecialchars($budgetConfirmation['text'], ENT_QUOTES, 'UTF-8') ?>"
                        data-confirm-confirm-text="Ya, ubah"
                        data-confirm-cancel-text="Batal"
                        class="badge badge-sm <?= $statusTransaksi === 'selesai' ? 'bg-gradient-success' : 'bg-gradient-warning' ?> border-0 text-white">
                        <?= htmlspecialchars($statusTransaksi, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </form>
            </span>
        </div>
        <div class="mobile-transaction-row mobile-transaction-actions-row">
            <span class="mobile-transaction-label">Aksi</span>
            <span class="mobile-transaction-value mobile-transaction-actions">
                <?php if ($statusTransaksi === 'pending' && empty($row['recurring_log_id']) && empty($row['linked_hutang_id'])) { ?>
                <form action="actions/aksi_pengeluaran.php?act=h" method="post" class="d-inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                    <button type="submit"
                        data-confirm="true"
                        data-confirm-title="Hapus pengeluaran ini?"
                        data-confirm-text="Data pengeluaran yang dihapus tidak bisa dikembalikan."
                        data-confirm-confirm-text="Ya, hapus"
                        data-confirm-cancel-text="Batal"
                        class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0"
                        title="Hapus pengeluaran pending" aria-label="Hapus pengeluaran pending">
                        <i class="fa fa-trash" aria-hidden="true"></i>
                    </button>
                </form>
                <?php } ?>

                <a href="#" role="button"
                    data-id="<?= (int) $row['id_pengeluaran'] ?>"
                    data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                    data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                    data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                    data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                    data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                    class="text-secondary text-warning font-weight-bold text-xs btneditpengeluaran"
                    title="Edit pengeluaran" aria-label="Edit pengeluaran">
                    <i class="fa fa-pencil" aria-hidden="true"></i>
                </a>
            </span>
        </div>
    </article>
<?php
};
?>

<div class="container-fluid py-4">
    <div class="row justify-content-end">
        <div class="col-6">
        </div>
    </div>
    <div class="row">
        <div class="col-12">

            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Pengeluaran</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <form id="bulkPengeluaranBulkDeletePendingForm"
                        action="actions/aksi_bulk_transaction.php" method="post" class="d-none bulk-pengeluaran-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="entity" value="pengeluaran">
                        <input type="hidden" name="operation" value="bulk_delete_pending">
                    </form>
                    <div class="desktop-transaction-section d-none d-md-block">
                        <div class="cashflow-toolbar-panel desktop-bulk-toolbar">
                            <div id="pengeluaranDataTableControls" class="cashflow-toolbar-group cashflow-toolbar-data"></div>
                            <div class="cashflow-toolbar-group cashflow-toolbar-actions">
                                    <button type="submit" form="bulkPengeluaranBulkDeletePendingForm"
                                        class="btn btn-outline-danger mb-0 bulk-delete-btn bulk-pengeluaran-action" disabled
                                        data-base-label="Hapus Pending Terpilih"
                                        data-confirm="true" data-confirm-title="Hapus transaksi pending terpilih?"
                                        data-confirm-text="Hanya pengeluaran pending tanpa relasi yang akan dihapus permanen. Mixed selection ditolak."
                                        data-confirm-confirm-text="Ya, hapus" data-confirm-cancel-text="Batal"
                                        data-confirm-form="#bulkPengeluaranBulkDeletePendingForm">
                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                        <span class="bulk-action-label">Hapus Pending Terpilih</span>
                                    </button>
                                <button type="button" class="btn btn-secondary mb-0 add-transaction-btn" data-bs-toggle="modal"
                                    data-bs-target="#modalTambah">
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive cashflow-table-scroll p-4 mx-2">
                            <table class="table align-items-center mb-0 transaction-table" id="datatablePengeluaranDesktop" data-skip-responsive="true">
                                <thead>
                                    <tr>
                                        <th class="bulk-select-col text-center">
                                            <input type="checkbox" id="selectAllPengeluaran" class="bulk-select-all" aria-label="Pilih semua pengeluaran">
                                        </th>
                                        <th>Tanggal</th>
                                        <th class="cashflow-long-text-col">Catatan</th>
                                        <th>Kategori</th>
                                        <th>Jumlah Pengeluaran</th>
                                        <th>Wallet</th>
                                        <th>Status</th>
                                        <th class="cashflow-action-col">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksiRows as $row) {
                                        $renderPengeluaranRow($row, true);
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mobile-transaction-section d-block d-md-none">
                        <div class="cashflow-toolbar-panel mobile-transaction-toolbar">
                            <?php if (!empty($transaksiRows)) { ?>
                                <div class="cashflow-toolbar-group cashflow-toolbar-data">
                                    <div class="w-100">
                                        <label class="form-label fw-bold text-sm mb-2" for="mobilePengeluaranSearch">Search:</label>
                                        <input type="search" class="form-control mobile-transaction-search" id="mobilePengeluaranSearch" data-target="#mobilePengeluaranList" placeholder="Ketik untuk mencari data...">
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="cashflow-toolbar-group cashflow-toolbar-actions">
                                <button type="button" class="btn btn-secondary w-100 mb-0 add-transaction-btn" data-bs-toggle="modal"
                                    data-bs-target="#modalTambah">
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                                </button>
                            </div>
                        </div>
                        <div class="mobile-transaction-list px-4 mx-2" id="mobilePengeluaranList">
                            <?php if (empty($transaksiRows)) { ?>
                                <div class="cashflow-empty-state">
                                    <i class="fa fa-arrow-circle-up" aria-hidden="true"></i>
                                    <p class="mb-1">Belum ada pengeluaran.</p>
                                    <small>Gunakan tombol Tambah Transaksi untuk membuat pencatatan pertama.</small>
                                </div>
                            <?php } else { ?>
                                <?php foreach ($transaksiRows as $row) {
                                    $renderMobilePengeluaranCard($row);
                                } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="actions/aksi_pengeluaran.php?act=t" method="post" id="pengeluaranTransactionForm" class="budget-warning-expense-form">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Pengeluaran</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" id="tanggal" class="form-control" required>
                            <input type="hidden" value="<?= (int) $_SESSION['id_user'] ?>" name="user">
                            <input type="hidden" name="id_pengeluaran" id="id_pengeluaran" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Kategori</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="id_kategori" id="id_kategori">
                                <option value="">Belum dikategorikan</option>
                                <?php foreach ($kategoriPengeluaran as $kategori) { ?>
                                    <option value="<?= (int) $kategori['id_kategori'] ?>">
                                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if (empty($kategoriPengeluaran)) { ?>
                            <small class="text-secondary px-2 mt-1">Belum ada kategori pengeluaran. Tambahkan lewat menu Kategori.</small>
                        <?php } ?>
                    </div>
                    <div class="row my-3">
                        <label>Wallet Sumber</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="id_wallet" id="id_wallet" required>
                                <option value="">Pilih Wallet</option>
                                <?php foreach ($walletAktif as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === (int) $defaultWalletId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(cashflow_wallet_type_text(cashflow_wallet_type_meta_from_row($wallet, $walletCustomTypeMap)), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if (empty($walletAktif)) { ?>
                            <small class="text-secondary px-2 mt-1">Belum ada wallet aktif. Tambahkan atau aktifkan wallet terlebih dahulu lewat menu Wallet.</small>
                        <?php } ?>
                    </div>
                    <div class="row my-3">
                        <label>Jumlah Pengeluaran</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="jumlah" required class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 250.000">
                        </div>
                    </div>
                    <div class="row my-3">
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="status" id="status" required>
                                <option value="">Pilih Status</option>
                                <option value="selesai">Selesai</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
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

<script>
    $(document).ready(function() {
        var defaultWalletId = "<?= htmlspecialchars((string) $defaultWalletId, ENT_QUOTES, 'UTF-8') ?>";
        var budgetUsageMap = <?= json_encode($budgetUsageMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var originalExpenseBudgetState = null;
        var datatableLanguage = {
            "emptyTable": "Belum ada pengeluaran.",
            "zeroRecords": "Tidak ada pengeluaran yang cocok dengan pencarian.",
            "paginate": {
                "first": "&laquo",
                "last": "&raquo",
                "next": "&gt",
                "previous": "&lt"
            },
        };
        var datatableDom = '<"cashflow-datatable-top"l<"input-group input-group-outline"f>>rt<"cashflow-datatable-bottom"ip><"clear">';

        var pengeluaranDesktopTable = $('#datatablePengeluaranDesktop').DataTable({
            language: datatableLanguage,
            columnDefs: [
                { targets: 0, orderable: false, searchable: false },
                { targets: -1, orderable: false, searchable: false }
            ],
            dom: datatableDom,
            initComplete: function() {
                $(this.api().table().container())
                    .find('.cashflow-datatable-top')
                    .appendTo('#pengeluaranDataTableControls');
            }
        });

        var selectedPengeluaranIds = {};

        function syncBulkPengeluaranFormInputs() {
            $('.bulk-pengeluaran-form').each(function() {
                var $form = $(this);
                $form.find('.js-bulk-generated-input').remove();
                Object.keys(selectedPengeluaranIds).forEach(function(id) {
                    $('<input>', {
                        type: 'hidden',
                        name: 'ids[]',
                        value: id,
                        class: 'js-bulk-generated-input'
                    }).appendTo($form);
                });
            });
        }

        function syncPengeluaranCheckboxNodes(nodes) {
            $(nodes).find('.bulk-pengeluaran-checkbox').each(function() {
                this.checked = Object.prototype.hasOwnProperty.call(selectedPengeluaranIds, this.value);
            });
        }

        function updateBulkPengeluaranState() {
            var selectedCount = Object.keys(selectedPengeluaranIds).length;
            var currentNodes = pengeluaranDesktopTable.rows({ page: 'current' }).nodes();
            var $currentCheckboxes = $(currentNodes).find('.bulk-pengeluaran-checkbox');
            var currentCount = $currentCheckboxes.length;
            var currentCheckedCount = 0;
            var selectAll = $('#selectAllPengeluaran').get(0);
            $currentCheckboxes.each(function() {
                if (Object.prototype.hasOwnProperty.call(selectedPengeluaranIds, this.value)) currentCheckedCount++;
            });

            syncBulkPengeluaranFormInputs();
            $('.bulk-pengeluaran-action').each(function() {
                var $button = $(this);
                var baseLabel = $button.attr('data-base-label');
                var validSelection = selectedCount > 0;
                $button.prop('disabled', !validSelection);
                $button.find('.bulk-action-label').text(validSelection ? baseLabel + ' (' + selectedCount + ')' : baseLabel);
            });

            if (selectAll) {
                selectAll.checked = currentCount > 0 && currentCheckedCount === currentCount;
                selectAll.indeterminate = currentCheckedCount > 0 && currentCheckedCount < currentCount;
            }
        }

        $('#selectAllPengeluaran').on('change', function() {
            var checked = this.checked;
            var filteredNodes = pengeluaranDesktopTable.rows({ page: 'current' }).nodes();

            $(filteredNodes).find('.bulk-pengeluaran-checkbox').each(function() {
                this.checked = checked;
                if (checked) {
                    selectedPengeluaranIds[this.value] = true;
                } else {
                    delete selectedPengeluaranIds[this.value];
                }
            });

            updateBulkPengeluaranState();
        });

        $(document).on('change', '.bulk-pengeluaran-checkbox', function() {
            if (this.checked) {
                selectedPengeluaranIds[this.value] = true;
            } else {
                delete selectedPengeluaranIds[this.value];
            }

            updateBulkPengeluaranState();
        });

        pengeluaranDesktopTable.on('draw', function() {
            syncPengeluaranCheckboxNodes(pengeluaranDesktopTable.rows({ page: 'current' }).nodes());
            updateBulkPengeluaranState();
        });

        $('.bulk-pengeluaran-form').on('submit', function() {
            syncBulkPengeluaranFormInputs();
        });

        syncPengeluaranCheckboxNodes(pengeluaranDesktopTable.rows({ page: 'current' }).nodes());
        updateBulkPengeluaranState();

        function budgetPeriodKey(categoryId, transactionDate) {
            var category = parseInt(categoryId, 10);
            var date = String(transactionDate || '');
            if (!category || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                return '';
            }

            return date.slice(0, 7) + ':' + category;
        }

        function budgetNominalNumber(value) {
            var digits = String(value || '').replace(/\D/g, '');
            return digits ? Number(digits) : 0;
        }

        function continueExpenseSubmit(form, submitter) {
            form.dataset.budgetWarningConfirmed = 'true';
            if (typeof form.requestSubmit === 'function') {
                submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                return;
            }

            form.submit();
        }

        $('#pengeluaranTransactionForm').on('submit', function(event) {
            var form = this;
            if (form.dataset.budgetWarningConfirmed === 'true') {
                delete form.dataset.budgetWarningConfirmed;
                return;
            }

            var status = String($(form).find('[name="status"]').val() || '');
            var categoryId = $(form).find('[name="id_kategori"]').val();
            var transactionDate = $(form).find('[name="tanggal"]').val();
            var amount = budgetNominalNumber($(form).find('[name="jumlah"]').val());
            var key = budgetPeriodKey(categoryId, transactionDate);
            var budget = key ? budgetUsageMap[key] : null;
            var budgetNominal = Number(budget && budget.nominal_budget || 0);

            if (status !== 'selesai' || amount <= 0 || budgetNominal <= 0) {
                return;
            }

            var currentUsage = Number(budget.total_terpakai || 0);
            if (originalExpenseBudgetState && originalExpenseBudgetState.status === 'selesai') {
                var originalKey = budgetPeriodKey(
                    originalExpenseBudgetState.categoryId,
                    originalExpenseBudgetState.transactionDate
                );
                if (originalKey === key) {
                    currentUsage = Math.max(0, currentUsage - originalExpenseBudgetState.amount);
                }
            }

            var projectedUsage = currentUsage + amount;
            if (projectedUsage < budgetNominal) {
                return;
            }

            event.preventDefault();
            var nativeEvent = event.originalEvent || event;
            var submitter = nativeEvent.submitter || null;
            var warningText = 'Penggunaan kategori akan menjadi Rp. '
                + projectedUsage.toLocaleString('id-ID')
                + ' dari budget Rp. '
                + budgetNominal.toLocaleString('id-ID')
                + '. Budget tidak memblokir transaksi.';

            if (typeof Swal === 'undefined') {
                if (window.confirm('Budget kategori akan terlampaui\n\n' + warningText + '\n\nTetap simpan pengeluaran?')) {
                    continueExpenseSubmit(form, submitter);
                }
                return;
            }

            Swal.fire({
                title: 'Budget kategori akan terlampaui',
                text: warningText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Tetap simpan',
                cancelButtonText: 'Periksa kembali',
                confirmButtonColor: '#0ea5e9',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true,
                focusCancel: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    continueExpenseSubmit(form, submitter);
                }
            });
        });

        $(document).on("click", ".btneditpengeluaran", function() {
            var walletId = $(this).attr("data-wallet") || defaultWalletId;
            $('#id_wallet').val(walletId);
            originalExpenseBudgetState = {
                status: String($(this).attr('data-status') || ''),
                transactionDate: String($(this).attr('data-tanggal') || ''),
                categoryId: String($(this).attr('data-kategori') || ''),
                amount: Number($(this).attr('data-jumlah') || 0)
            };
        });

        $(document).on('click', '[data-bs-target="#modalTambah"]:not(.btneditpengeluaran)', function() {
            originalExpenseBudgetState = null;
            delete document.getElementById('pengeluaranTransactionForm').dataset.budgetWarningConfirmed;
        });

        $('#modalTambah').on('hidden.bs.modal', function() {
            $('#id_wallet').val(defaultWalletId);
            originalExpenseBudgetState = null;
            delete document.getElementById('pengeluaranTransactionForm').dataset.budgetWarningConfirmed;
        });

        $(document).on('input', '.mobile-transaction-search', function() {
            var targetSelector = $(this).attr('data-target');
            var $list = $(targetSelector);
            var query = ($(this).val() || '').toLowerCase().trim();

            if (!$list.length) {
                return;
            }

            $list.find('.mobile-transaction-card').each(function() {
                var haystack = $(this).attr('data-search') || $(this).text().toLowerCase();
                $(this).toggle(!query || haystack.indexOf(query) !== -1);
            });
        });
    });
</script>
