<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

function pengeluaran_wallet_type_label($type)
{
    $labels = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'e_wallet' => 'E-Wallet',
        'tabungan' => 'Tabungan',
        'lainnya' => 'Lainnya',
    ];

    return $labels[$type] ?? 'Lainnya';
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
                       wallet.is_active AS wallet_is_active
                   FROM pengeluaran
                   LEFT JOIN kategori
                       ON pengeluaran.id_kategori = kategori.id_kategori
                      AND kategori.user_id = pengeluaran.user
                      AND kategori.tipe_kategori = 'pengeluaran'
                   LEFT JOIN wallet
                       ON pengeluaran.id_wallet = wallet.id_wallet
                      AND wallet.user_id = pengeluaran.user
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

$renderPengeluaranRow = function (array $row, bool $includeBulkColumn = false) use ($defaultWalletName, $defaultWalletId) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayType = $row['tipe_wallet'] ? pengeluaran_wallet_type_label($row['tipe_wallet']) : 'Fallback';
    $editWalletId = !empty($row['id_wallet']) && (string) ($row['wallet_is_active'] ?? '0') === '1'
        ? (int) $row['id_wallet']
        : $defaultWalletId;
?>
    <tr>
        <?php if ($includeBulkColumn) { ?>
            <td class="bulk-select-col text-center">
                <input
                    type="checkbox"
                    class="bulk-select-row bulk-pengeluaran-checkbox"
                    name="id_pengeluaran[]"
                    value="<?= (int) $row['id_pengeluaran'] ?>"
                    form="bulkDeletePengeluaranForm"
                    aria-label="Pilih transaksi pengeluaran ini">
            </td>
        <?php } ?>
        <td class="align-middle text-center">
            <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal']) ?></span>
        </td>
        <td>
            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan']) ?></p>
        </td>
        <td>
            <p class="text-xs text-secondary mb-0">
                <?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?>
            </p>
        </td>
        <td>
            <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?></p>
        </td>
        <td>
            <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($walletDisplayName, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($walletDisplayType, ENT_QUOTES, 'UTF-8') ?></p>
        </td>
        <td class="align-middle text-center text-sm">
            <form action="actions/aksi_pengeluaran.php?act=l" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($targetStatus, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit"
                    data-confirm="true"
                    data-confirm-title="Ubah status transaksi?"
                    data-confirm-text="Status transaksi akan diubah menjadi <?= htmlspecialchars($targetStatusLabel, ENT_QUOTES, 'UTF-8') ?>."
                    data-confirm-confirm-text="Ya, ubah"
                    data-confirm-cancel-text="Batal"
                    class="badge badge-sm <?= $statusTransaksi === 'selesai' ? 'bg-gradient-success' : 'bg-gradient-warning' ?> border-0 text-white">
                    <?= htmlspecialchars($statusTransaksi, ENT_QUOTES, 'UTF-8') ?>
                </button>
            </form>
        </td>
        <td class="align-middle">
            <form action="actions/aksi_pengeluaran.php?act=h" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                <button type="submit"
                    data-confirm="true"
                    data-confirm-title="Hapus pengeluaran ini?"
                    data-confirm-text="Data pengeluaran yang dihapus tidak bisa dikembalikan."
                    data-confirm-confirm-text="Ya, hapus"
                    data-confirm-cancel-text="Batal"
                    class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
            </form>

            <a type="submit"
                data-id="<?= (int) $row['id_pengeluaran'] ?>"
                data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                class="text-secondary text-warning font-weight-bold text-xs btneditpengeluaran">
                <i class="fa fa-pencil" aria-hidden="true"></i>
            </a>
        </td>
    </tr>
<?php
};

$renderMobilePengeluaranCard = function (array $row) use ($defaultWalletName, $defaultWalletId) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayType = $row['tipe_wallet'] ? pengeluaran_wallet_type_label($row['tipe_wallet']) : 'Fallback';
    $editWalletId = !empty($row['id_wallet']) && (string) ($row['wallet_is_active'] ?? '0') === '1'
        ? (int) $row['id_wallet']
        : $defaultWalletId;
    $searchText = strtolower(trim(implode(' ', [
        (string) ($row['tanggal'] ?? ''),
        (string) ($row['catatan'] ?? ''),
        (string) ($row['nama_kategori'] ?? ''),
        (string) ($row['nama_wallet'] ?? ''),
        (string) ($row['tipe_wallet'] ?? ''),
        (string) ($row['status'] ?? ''),
        (string) ($row['jumlah'] ?? ''),
    ])));
?>
    <article class="mobile-transaction-card" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Tanggal</span>
            <span class="mobile-transaction-value"><?= htmlspecialchars($row['tanggal']) ?></span>
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
            <span class="mobile-transaction-value fw-bold">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?></span>
        </div>
        <div class="mobile-transaction-row">
            <span class="mobile-transaction-label">Wallet</span>
            <span class="mobile-transaction-value">
                <strong><?= htmlspecialchars($walletDisplayName, ENT_QUOTES, 'UTF-8') ?></strong><br>
                <small><?= htmlspecialchars($walletDisplayType, ENT_QUOTES, 'UTF-8') ?></small>
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
                        data-confirm-title="Ubah status transaksi?"
                        data-confirm-text="Status transaksi akan diubah menjadi <?= htmlspecialchars($targetStatusLabel, ENT_QUOTES, 'UTF-8') ?>."
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
                <form action="actions/aksi_pengeluaran.php?act=h" method="post" class="d-inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_pengeluaran" value="<?= (int) $row['id_pengeluaran'] ?>">
                    <button type="submit"
                        data-confirm="true"
                        data-confirm-title="Hapus pengeluaran ini?"
                        data-confirm-text="Data pengeluaran yang dihapus tidak bisa dikembalikan."
                        data-confirm-confirm-text="Ya, hapus"
                        data-confirm-cancel-text="Batal"
                        class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                        <i class="fa fa-trash" aria-hidden="true"></i>
                    </button>
                </form>

                <a type="submit"
                    data-id="<?= (int) $row['id_pengeluaran'] ?>"
                    data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                    data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                    data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                    data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                    data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                    class="text-secondary text-warning font-weight-bold text-xs btneditpengeluaran">
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
                    <form id="bulkDeletePengeluaranForm" action="actions/aksi_pengeluaran.php?act=bulk_delete" method="post" class="d-none">
                        <?= csrf_input() ?>
                    </form>
                    <div class="desktop-transaction-section d-none d-md-block">
                        <div class="transaction-table-toolbar desktop-bulk-toolbar">
                            <div class="transaction-toolbar-actions">
                                <button
                                    type="submit"
                                    form="bulkDeletePengeluaranForm"
                                    id="bulkDeletePengeluaranBtn"
                                    class="btn btn-outline-danger mb-0 bulk-delete-btn"
                                    disabled
                                    data-confirm="true"
                                    data-confirm-title="Hapus transaksi terpilih?"
                                    data-confirm-text="Data pengeluaran yang dipilih akan dihapus dan tidak bisa dikembalikan."
                                    data-confirm-confirm-text="Ya, hapus"
                                    data-confirm-cancel-text="Batal"
                                    data-confirm-form="#bulkDeletePengeluaranForm">
                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                    <span class="bulk-delete-label">Hapus Terpilih</span>
                                </button>
                                <button type="button" class="btn btn-secondary mb-0 add-transaction-btn" data-bs-toggle="modal"
                                    data-bs-target="#modalTambah">
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive p-4 mx-2">
                            <table class="table align-items-center mb-0 transaction-table" id="datatablePengeluaranDesktop" data-skip-responsive="true">
                                <thead>
                                    <tr>
                                        <th class="bulk-select-col text-center">
                                            <input type="checkbox" id="selectAllPengeluaran" class="bulk-select-all" aria-label="Pilih semua pengeluaran">
                                        </th>
                                        <th>Tanggal</th>
                                        <th>Catatan</th>
                                        <th>Kategori</th>
                                        <th>Jumlah Pengeluaran</th>
                                        <th>Wallet</th>
                                        <th>Status</th>
                                        <th></th>
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
                        <div class="px-4 mx-2 mb-3">
                            <button type="button" class="btn btn-secondary w-100 mb-0 add-transaction-btn" data-bs-toggle="modal"
                                data-bs-target="#modalTambah">
                                <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                            </button>
                        </div>
                        <div class="px-4 mx-2 mb-3">
                            <label class="form-label fw-bold text-sm mb-2" for="mobilePengeluaranSearch">Search:</label>
                            <input type="search" class="form-control mobile-transaction-search" id="mobilePengeluaranSearch" data-target="#mobilePengeluaranList" placeholder="Ketik untuk mencari data...">
                        </div>
                        <div class="mobile-transaction-list px-4 mx-2" id="mobilePengeluaranList">
                            <?php foreach ($transaksiRows as $row) {
                                $renderMobilePengeluaranCard($row);
                            } ?>
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
            <form action="actions/aksi_pengeluaran.php?act=t" method="post">
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
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(pengeluaran_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
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
        var datatableLanguage = {
            "paginate": {
                "first": "&laquo",
                "last": "&raquo",
                "next": "&gt",
                "previous": "&lt"
            },
        };
        var datatableDom = ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">';

        var pengeluaranDesktopTable = $('#datatablePengeluaranDesktop').DataTable({
            language: datatableLanguage,
            columnDefs: [
                { targets: 0, orderable: false, searchable: false },
                { targets: -1, orderable: false, searchable: false }
            ],
            dom: datatableDom
        });

        var selectedPengeluaranIds = {};
        var bulkPengeluaranResizeTimer = null;

        function syncBulkPengeluaranFormInputs() {
            var $form = $('#bulkDeletePengeluaranForm');
            $form.find('.js-bulk-generated-input').remove();

            Object.keys(selectedPengeluaranIds).forEach(function(id) {
                $('<input>', {
                    type: 'hidden',
                    name: 'id_pengeluaran[]',
                    value: id,
                    class: 'js-bulk-generated-input'
                }).appendTo($form);
            });
        }

        function syncPengeluaranCheckboxNodes(nodes) {
            $(nodes).find('.bulk-pengeluaran-checkbox').each(function() {
                this.checked = selectedPengeluaranIds[this.value] === true;
            });
        }

        function updateBulkPengeluaranState() {
            var selectedCount = Object.keys(selectedPengeluaranIds).length;
            var $button = $('#bulkDeletePengeluaranBtn');
            var $label = $button.find('.bulk-delete-label');
            var filteredNodes = pengeluaranDesktopTable.rows({ search: 'applied' }).nodes();
            var $filteredCheckboxes = $(filteredNodes).find('.bulk-pengeluaran-checkbox');
            var filteredCount = $filteredCheckboxes.length;
            var filteredCheckedCount = 0;
            var selectAll = $('#selectAllPengeluaran').get(0);

            $filteredCheckboxes.each(function() {
                if (selectedPengeluaranIds[this.value] === true) {
                    filteredCheckedCount++;
                }
            });

            syncBulkPengeluaranFormInputs();
            $button.prop('disabled', selectedCount === 0);
            $label.text(selectedCount > 0 ? 'Hapus Terpilih (' + selectedCount + ')' : 'Hapus Terpilih');

            if (selectAll) {
                selectAll.checked = filteredCount > 0 && filteredCheckedCount === filteredCount;
                selectAll.indeterminate = filteredCheckedCount > 0 && filteredCheckedCount < filteredCount;
            }
        }

        $('#selectAllPengeluaran').on('change', function() {
            var checked = this.checked;
            var filteredNodes = pengeluaranDesktopTable.rows({ search: 'applied' }).nodes();

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

        $('#bulkDeletePengeluaranForm').on('submit', function() {
            syncBulkPengeluaranFormInputs();
        });

        $(window).on('resize orientationchange', function() {
            clearTimeout(bulkPengeluaranResizeTimer);
            bulkPengeluaranResizeTimer = setTimeout(function() {
                pengeluaranDesktopTable.columns.adjust();
                syncPengeluaranCheckboxNodes(pengeluaranDesktopTable.rows({ page: 'current' }).nodes());
                updateBulkPengeluaranState();
            }, 180);
        });

        syncPengeluaranCheckboxNodes(pengeluaranDesktopTable.rows({ page: 'current' }).nodes());
        updateBulkPengeluaranState();

        $(document).on("click", ".btneditpengeluaran", function() {
            var walletId = $(this).attr("data-wallet") || defaultWalletId;
            $('#id_wallet').val(walletId);
        });

        $('#modalTambah').on('hidden.bs.modal', function() {
            $('#id_wallet').val(defaultWalletId);
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
