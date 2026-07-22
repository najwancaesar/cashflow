<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/ui_helper.php";
include_once __DIR__ . "/../includes/wallet_type_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];
$walletCustomTypeMap = [];
if ($userYangSedangLogin > 0) {
    $loadedWalletCustomTypeMap = cashflow_get_wallet_custom_type_map($con, $userYangSedangLogin);
    $walletCustomTypeMap = is_array($loadedWalletCustomTypeMap) ? $loadedWalletCustomTypeMap : [];
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$kategoriPemasukan = [];
$kategoriQuery = "SELECT id_kategori, nama_kategori
                  FROM kategori
                  WHERE user_id = ? AND tipe_kategori = 'pemasukan'
                  ORDER BY nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);

while ($kategori = mysqli_fetch_assoc($kategoriResult)) {
    $kategoriPemasukan[] = $kategori;
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
                       pemasukan.*,
                       kategori.nama_kategori,
                       wallet.nama_wallet,
                       wallet.tipe_wallet,
                       wallet.is_active AS wallet_is_active,
                       recurring_generation_log.id_log AS recurring_log_id,
                       piutang.id_piutang AS linked_piutang_id
                   FROM pemasukan
                   LEFT JOIN kategori
                       ON pemasukan.id_kategori = kategori.id_kategori
                      AND kategori.user_id = pemasukan.user
                      AND kategori.tipe_kategori = 'pemasukan'
                   LEFT JOIN wallet
                       ON pemasukan.id_wallet = wallet.id_wallet
                      AND wallet.user_id = pemasukan.user
                   LEFT JOIN recurring_generation_log
                       ON recurring_generation_log.user_id = pemasukan.user
                      AND recurring_generation_log.tipe_transaksi = 'pemasukan'
                      AND recurring_generation_log.id_transaksi = pemasukan.id_pemasukan
                   LEFT JOIN piutang
                       ON piutang.user = pemasukan.user
                      AND piutang.id_pemasukan = pemasukan.id_pemasukan
                   WHERE pemasukan.user = ?
                   ORDER BY pemasukan.tanggal DESC, pemasukan.id_pemasukan DESC";
$transaksiStmt = mysqli_prepare($con, $transaksiQuery);
mysqli_stmt_bind_param($transaksiStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($transaksiStmt);
$transaksiResult = mysqli_stmt_get_result($transaksiStmt);

$transaksiRows = [];
while ($row = mysqli_fetch_assoc($transaksiResult)) {
    $transaksiRows[] = $row;
}
mysqli_stmt_close($transaksiStmt);

$renderPemasukanRow = function (array $row) use ($defaultWalletName, $defaultWalletId, $walletCustomTypeMap) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayTypeMeta = cashflow_wallet_type_meta_from_row($row, $walletCustomTypeMap);
    $walletDisplayType = cashflow_wallet_type_text($walletDisplayTypeMeta);
    $editWalletId = !empty($row['id_wallet']) && (string) ($row['wallet_is_active'] ?? '0') === '1'
        ? (int) $row['id_wallet']
        : $defaultWalletId;
?>
    <tr>
        <td class="align-middle text-center" data-order="<?= htmlspecialchars((string) $row['tanggal'], ENT_QUOTES, 'UTF-8') ?>">
            <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars(cashflow_format_date($row['tanggal']), ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td class="cashflow-long-text-col">
            <p class="text-xs text-secondary mb-0 cashflow-long-text"><?= htmlspecialchars($row['catatan']) ?></p>
        </td>
        <td data-order="<?= htmlspecialchars((string) ($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
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
            <form action="actions/aksi_pemasukan.php?act=l" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pemasukan" value="<?= (int) $row['id_pemasukan'] ?>">
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
        <td class="align-middle cashflow-action-col">
            <div class="cashflow-action-group">
            <?php if (empty($row['recurring_log_id']) && empty($row['linked_piutang_id'])) { ?>
            <form action="actions/aksi_pemasukan.php?act=h" method="post" class="d-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="id_pemasukan" value="<?= (int) $row['id_pemasukan'] ?>">
                <button type="submit"
                    data-confirm="true"
                    data-confirm-title="Hapus pemasukan ini?"
                    data-confirm-text="Data pemasukan yang dihapus tidak bisa dikembalikan."
                    data-confirm-confirm-text="Ya, hapus"
                    data-confirm-cancel-text="Batal"
                    class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0"
                    title="Hapus" aria-label="Hapus">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
            </form>
            <?php } ?>

            <a href="#" role="button"
                data-id="<?= (int) $row['id_pemasukan'] ?>"
                data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                class="text-secondary text-warning font-weight-bold text-xs btneditpemasukan"
                title="Edit pemasukan" aria-label="Edit pemasukan">
                <i class="fa fa-pencil" aria-hidden="true"></i>
            </a>
            </div>
        </td>
    </tr>
<?php
};

$renderMobilePemasukanCard = function (array $row) use ($defaultWalletName, $defaultWalletId, $walletCustomTypeMap) {
    $statusTransaksi = (string) ($row['status'] ?? 'pending');
    $targetStatus = $statusTransaksi === 'selesai' ? 'pending' : 'selesai';
    $targetStatusLabel = ucfirst($targetStatus);
    $walletDisplayName = $row['nama_wallet'] ?: $defaultWalletName;
    $walletDisplayTypeMeta = cashflow_wallet_type_meta_from_row($row, $walletCustomTypeMap);
    $walletDisplayType = cashflow_wallet_type_text($walletDisplayTypeMeta);
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
            <span class="mobile-transaction-label">Jumlah Pemasukan</span>
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
                <form action="actions/aksi_pemasukan.php?act=l" method="post" class="d-inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_pemasukan" value="<?= (int) $row['id_pemasukan'] ?>">
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
                <?php if (empty($row['recurring_log_id']) && empty($row['linked_piutang_id'])) { ?>
                <form action="actions/aksi_pemasukan.php?act=h" method="post" class="d-inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id_pemasukan" value="<?= (int) $row['id_pemasukan'] ?>">
                    <button type="submit"
                        data-confirm="true"
                        data-confirm-title="Hapus pemasukan ini?"
                        data-confirm-text="Data pemasukan yang dihapus tidak bisa dikembalikan."
                        data-confirm-confirm-text="Ya, hapus"
                        data-confirm-cancel-text="Batal"
                        class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0"
                        title="Hapus" aria-label="Hapus">
                        <i class="fa fa-trash" aria-hidden="true"></i>
                    </button>
                </form>
                <?php } ?>

                <a href="#" role="button"
                    data-id="<?= (int) $row['id_pemasukan'] ?>"
                    data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                    data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                    data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                    data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                    data-wallet="<?= htmlspecialchars((string) $editWalletId, ENT_QUOTES) ?>"
                    class="text-secondary text-warning font-weight-bold text-xs btneditpemasukan"
                    title="Edit pemasukan" aria-label="Edit pemasukan">
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
                        <h6 class="text-white text-capitalize ps-3">Pemasukan</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <p class="cashflow-feature-description">Catat seluruh uang yang masuk ke wallet. Hanya transaksi berstatus selesai yang menambah saldo.</p>
                    <div class="desktop-transaction-section d-none d-md-block">
                        <div class="cashflow-table-page-actions">
                            <button type="button" class="btn btn-secondary mb-0 add-transaction-btn" data-bs-toggle="modal"
                                data-bs-target="#modalTambah">
                                <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                            </button>
                        </div>
                        <div class="table-responsive cashflow-table-scroll p-4 mx-2">
                            <table class="table align-items-center mb-0 transaction-table" id="datatablePemasukanDesktop" data-skip-responsive="true">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th class="cashflow-long-text-col">Catatan</th>
                                        <th>Kategori</th>
                                        <th>Jumlah Pemasukan</th>
                                        <th>Wallet</th>
                                        <th>Status</th>
                                        <th class="cashflow-action-col">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksiRows as $row) {
                                        $renderPemasukanRow($row);
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mobile-transaction-section d-block d-md-none">
                        <div class="mobile-transaction-toolbar">
                            <?php if (!empty($transaksiRows)) { ?>
                                <div class="cashflow-toolbar-group cashflow-toolbar-data">
                                    <div class="w-100">
                                        <label class="form-label fw-bold text-sm mb-2" for="mobilePemasukanSearch">Search:</label>
                                        <input type="search" class="form-control mobile-transaction-search" id="mobilePemasukanSearch" data-target="#mobilePemasukanList" placeholder="Ketik untuk mencari data...">
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
                        <div class="mobile-transaction-list px-4 mx-2" id="mobilePemasukanList">
                            <?php if (empty($transaksiRows)) { ?>
                                <div class="cashflow-empty-state">
                                    <i class="fa fa-arrow-circle-down" aria-hidden="true"></i>
                                    <p class="mb-1">Belum ada pemasukan.</p>
                                    <small>Gunakan tombol Tambah Transaksi untuk membuat pencatatan pertama.</small>
                                </div>
                            <?php } else { ?>
                                <?php foreach ($transaksiRows as $row) {
                                    $renderMobilePemasukanCard($row);
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
            <form action="actions/aksi_pemasukan.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Pemasukan</h6>
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
                            <input type="hidden" name="id_pemasukan" id="id_pemasukan" class="form-control">
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
                                <?php foreach ($kategoriPemasukan as $kategori) { ?>
                                    <option value="<?= (int) $kategori['id_kategori'] ?>">
                                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if (empty($kategoriPemasukan)) { ?>
                            <small class="text-secondary px-2 mt-1">Belum ada kategori pemasukan. Tambahkan lewat menu Kategori.</small>
                        <?php } ?>
                    </div>
                    <div class="row my-3">
                        <label>Wallet Tujuan</label>
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
                        <label>Jumlah Pemasukan</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="jumlah" required class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 1.000.000">
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
            "emptyTable": "Belum ada pemasukan.",
            "zeroRecords": "Tidak ada pemasukan yang cocok dengan pencarian.",
            "paginate": {
                "first": "&laquo",
                "last": "&raquo",
                "next": "&gt",
                "previous": "&lt"
            },
        };
        var datatableDom = '<"cashflow-datatable-top"l<"input-group input-group-outline"f>>rt<"cashflow-datatable-bottom"ip><"clear">';

        $('#datatablePemasukanDesktop').DataTable({
            language: datatableLanguage,
            columnDefs: [
                { targets: -1, orderable: false, searchable: false }
            ],
            order: [[0, 'desc']],
            dom: datatableDom
        });

        $(document).on("click", ".btneditpemasukan", function() {
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
