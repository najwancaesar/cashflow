<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";

if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$userYangSedangLogin = (int) $_SESSION['id_user'];
$tanggalHariIni = date('Y-m-d');
$periodeBulanIni = (int) date('n');
$periodeTahunIni = (int) date('Y');

function recurring_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function recurring_wallet_type_label($type)
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

function recurring_type_label($type)
{
    return $type === 'pengeluaran' ? 'Pengeluaran' : 'Pemasukan';
}

function recurring_status_badge($isActive)
{
    return (int) $isActive === 1 ? 'bg-gradient-success' : 'bg-gradient-secondary';
}

function recurring_format_date($value)
{
    if (empty($value) || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y', $timestamp);
}

$kategoriRows = [];
$kategoriByType = [
    'pemasukan' => [],
    'pengeluaran' => [],
];
$kategoriQuery = "SELECT id_kategori, nama_kategori, tipe_kategori
                  FROM kategori
                  WHERE user_id = ? AND tipe_kategori IN ('pemasukan', 'pengeluaran')
                  ORDER BY tipe_kategori ASC, nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);

while ($kategori = mysqli_fetch_assoc($kategoriResult)) {
    $kategoriRows[] = $kategori;
    $tipeKategori = (string) $kategori['tipe_kategori'];
    if (isset($kategoriByType[$tipeKategori])) {
        $kategoriByType[$tipeKategori][] = $kategori;
    }
}

mysqli_stmt_close($kategoriStmt);

$walletAktif = [];
$defaultWalletId = '';
$walletQuery = "SELECT id_wallet, nama_wallet, tipe_wallet, is_default
                FROM wallet
                WHERE user_id = ? AND is_active = 1
                ORDER BY is_default DESC, nama_wallet ASC";
$walletStmt = mysqli_prepare($con, $walletQuery);
mysqli_stmt_bind_param($walletStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($walletStmt);
$walletResult = mysqli_stmt_get_result($walletStmt);

while ($wallet = mysqli_fetch_assoc($walletResult)) {
    if ($defaultWalletId === '' && (int) ($wallet['is_default'] ?? 0) === 1) {
        $defaultWalletId = (int) $wallet['id_wallet'];
    }
    $walletAktif[] = $wallet;
}

mysqli_stmt_close($walletStmt);

if ($defaultWalletId === '' && !empty($walletAktif)) {
    $defaultWalletId = (int) $walletAktif[0]['id_wallet'];
}

$recurringRows = [];
$recurringQuery = "SELECT
                    recurring_transaction.*,
                    kategori.nama_kategori,
                    wallet.nama_wallet,
                    wallet.tipe_wallet,
                    recurring_generation_log.id_log AS log_bulan_ini
                  FROM recurring_transaction
                  LEFT JOIN kategori
                    ON kategori.id_kategori = recurring_transaction.id_kategori
                   AND kategori.user_id = recurring_transaction.user_id
                   AND kategori.tipe_kategori = recurring_transaction.tipe_transaksi
                  LEFT JOIN wallet
                    ON wallet.id_wallet = recurring_transaction.id_wallet
                   AND wallet.user_id = recurring_transaction.user_id
                  LEFT JOIN recurring_generation_log
                    ON recurring_generation_log.id_recurring = recurring_transaction.id_recurring
                   AND recurring_generation_log.user_id = recurring_transaction.user_id
                   AND recurring_generation_log.periode_bulan = ?
                   AND recurring_generation_log.periode_tahun = ?
                  WHERE recurring_transaction.user_id = ?
                  ORDER BY recurring_transaction.is_active DESC, recurring_transaction.updated_at DESC, recurring_transaction.id_recurring DESC";
$recurringStmt = mysqli_prepare($con, $recurringQuery);
mysqli_stmt_bind_param($recurringStmt, "iii", $periodeBulanIni, $periodeTahunIni, $userYangSedangLogin);
mysqli_stmt_execute($recurringStmt);
$recurringResult = mysqli_stmt_get_result($recurringStmt);

while ($row = mysqli_fetch_assoc($recurringResult)) {
    $recurringRows[] = $row;
}

mysqli_stmt_close($recurringStmt);

$formDisabled = empty($walletAktif) || (empty($kategoriByType['pemasukan']) && empty($kategoriByType['pengeluaran']));
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Transaksi Berulang</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Buat template pemasukan atau pengeluaran rutin, lalu generate manual untuk bulan berjalan.
                        </p>
                        <p class="text-xs text-secondary mb-0">
                            Sistem tidak membuat transaksi otomatis di background. Klik Generate Bulan Ini saat ingin membuat transaksi dari template aktif.
                        </p>
                        <?php if (empty($walletAktif)) { ?>
                            <div class="alert alert-warning text-white mt-3 mb-0" role="alert">
                                Transaksi berulang membutuhkan minimal satu wallet aktif.
                            </div>
                        <?php } ?>
                        <?php if (empty($kategoriByType['pemasukan']) && empty($kategoriByType['pengeluaran'])) { ?>
                            <div class="alert alert-warning text-white mt-3 mb-0" role="alert">
                                Tambahkan kategori pemasukan atau pengeluaran terlebih dahulu sebelum membuat template.
                            </div>
                        <?php } ?>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 me-3 mt-3">
                        <form action="actions/aksi_recurring.php?act=g" method="post" class="d-inline">
                            <?= csrf_input() ?>
                            <button type="submit"
                                class="btn btn-success"
                                data-confirm="true"
                                data-confirm-title="Generate transaksi bulan ini?"
                                data-confirm-text="Template aktif yang belum pernah digenerate pada bulan ini akan dibuat menjadi transaksi."
                                data-confirm-confirm-text="Ya, generate"
                                data-confirm-cancel-text="Batal">
                                <i class="fa fa-refresh" aria-hidden="true"></i> Generate Bulan Ini
                            </button>
                        </form>
                        <button type="button" class="btn btn-secondary" id="btnTambahRecurring" data-bs-toggle="modal"
                            data-bs-target="#modalRecurring" <?= $formDisabled ? 'disabled' : '' ?>>
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Template
                        </button>
                    </div>

                    <div class="table-responsive p-4 mx-2">
                        <?php if (empty($recurringRows)) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-refresh text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-1">Belum ada template transaksi berulang.</p>
                                <p class="text-xs text-secondary mb-0">Tambahkan template pertama untuk transaksi rutin seperti gaji, internet, atau subscription.</p>
                            </div>
                        <?php } else { ?>
                            <table class="table align-items-center mb-0" id="datatableRecurring">
                                <thead>
                                    <tr>
                                        <th>Template</th>
                                        <th>Tipe</th>
                                        <th>Kategori</th>
                                        <th>Wallet</th>
                                        <th>Jumlah</th>
                                        <th>Jadwal</th>
                                        <th>Status</th>
                                        <th>Bulan Ini</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurringRows as $row) { ?>
                                        <?php
                                        $isActive = (int) ($row['is_active'] ?? 0);
                                        $editActiveValue = $isActive === 1 ? '1' : '0';
                                        $statusTransaksiDefault = (string) ($row['status_transaksi_default'] ?? 'pending');
                                        $jumlah = (float) ($row['jumlah'] ?? 0);
                                        $logBulanIni = !empty($row['log_bulan_ini']);
                                        $statusTemplateLabel = $isActive === 1 ? 'AKTIF' : 'NONAKTIF';
                                        $toggleTargetValue = $isActive === 1 ? 0 : 1;
                                        $toggleActionLabel = $isActive === 1 ? 'Nonaktifkan' : 'Aktifkan';
                                        $toggleConfirmTitle = $isActive === 1 ? 'Nonaktifkan template?' : 'Aktifkan template?';
                                        $toggleConfirmText = $isActive === 1
                                            ? 'Template ini akan dinonaktifkan dan tidak ikut digenerate.'
                                            : 'Template ini akan diaktifkan dan ikut digenerate jika memenuhi periode.';
                                        $toggleConfirmButton = $isActive === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan';
                                        $toggleIcon = $isActive === 1 ? 'fa-ban' : 'fa-check-circle';
                                        $toggleTextClass = $isActive === 1 ? 'text-danger' : 'text-success';
                                        ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-1"><?= htmlspecialchars($row['nama_recurring'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= $row['tipe_transaksi'] === 'pengeluaran' ? 'bg-gradient-warning' : 'bg-gradient-info' ?>">
                                                    <?= htmlspecialchars(recurring_type_label($row['tipe_transaksi']), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama_kategori'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($row['nama_wallet'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['tipe_wallet'] ? recurring_wallet_type_label($row['tipe_wallet']) : '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= recurring_rupiah($jumlah) ?></p>
                                                <p class="text-xs text-secondary mb-0">Default: <?= htmlspecialchars(ucfirst($statusTransaksiDefault), ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-1">Tanggal <?= (int) $row['tanggal_generate'] ?> tiap bulan</p>
                                                <p class="text-xs text-secondary mb-0">
                                                    <?= htmlspecialchars(recurring_format_date($row['mulai_dari']), ENT_QUOTES, 'UTF-8') ?>
                                                    sampai
                                                    <?= htmlspecialchars(recurring_format_date($row['berakhir_pada']), ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= recurring_status_badge($isActive) ?>">
                                                    <?= $statusTemplateLabel ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= $logBulanIni ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= $logBulanIni ? 'Sudah Generate' : 'Belum Generate' ?>
                                                </span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="d-flex flex-wrap align-items-center gap-2">
                                                    <button type="button"
                                                        class="text-secondary text-warning font-weight-bold text-xs border-0 bg-transparent p-0 btneditrecurring"
                                                        data-id="<?= (int) $row['id_recurring'] ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama_recurring'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-tipe="<?= htmlspecialchars($row['tipe_transaksi'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-kategori="<?= (int) $row['id_kategori'] ?>"
                                                        data-wallet="<?= (int) $row['id_wallet'] ?>"
                                                        data-jumlah="<?= htmlspecialchars(number_format($jumlah, 0, '', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                        data-catatan="<?= htmlspecialchars($row['catatan'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-tanggal-generate="<?= (int) $row['tanggal_generate'] ?>"
                                                        data-status-default="<?= htmlspecialchars($statusTransaksiDefault, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-mulai="<?= htmlspecialchars($row['mulai_dari'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-berakhir="<?= htmlspecialchars($row['berakhir_pada'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        data-active="<?= htmlspecialchars($editActiveValue, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fa fa-pencil" aria-hidden="true"></i> Edit
                                                    </button>

                                                    <form action="actions/aksi_recurring.php?act=s" method="post" class="d-inline">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="id_recurring" value="<?= (int) $row['id_recurring'] ?>">
                                                        <input type="hidden" name="is_active" value="<?= $toggleTargetValue ?>">
                                                        <button type="submit"
                                                            data-confirm="true"
                                                            data-confirm-title="<?= $toggleConfirmTitle ?>"
                                                            data-confirm-text="<?= $toggleConfirmText ?>"
                                                            data-confirm-confirm-text="<?= $toggleConfirmButton ?>"
                                                            data-confirm-cancel-text="Batal"
                                                            class="<?= $toggleTextClass ?> font-weight-bold text-xs border-0 bg-transparent p-0">
                                                            <i class="fa <?= $toggleIcon ?>" aria-hidden="true"></i>
                                                            <?= $toggleActionLabel ?>
                                                        </button>
                                                    </form>
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

<div class="modal fade" id="modalRecurring" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="actions/aksi_recurring.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3" id="recurring_modal_title">Tambah Transaksi Berulang</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_recurring" id="id_recurring">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Template</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="nama_recurring" id="nama_recurring" class="form-control" maxlength="150" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Transaksi</label>
                            <div class="input-group input-group-outline">
                                <select name="tipe_transaksi" id="tipe_transaksi" class="form-control" required>
                                    <option value="pemasukan" <?= empty($kategoriByType['pemasukan']) && !empty($kategoriByType['pengeluaran']) ? '' : 'selected' ?>>Pemasukan</option>
                                    <option value="pengeluaran" <?= empty($kategoriByType['pemasukan']) && !empty($kategoriByType['pengeluaran']) ? 'selected' : '' ?>>Pengeluaran</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <div class="input-group input-group-outline">
                                <select name="id_kategori" id="id_kategori_recurring" class="form-control" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategoriRows as $kategori) { ?>
                                        <option value="<?= (int) $kategori['id_kategori'] ?>" data-tipe="<?= htmlspecialchars($kategori['tipe_kategori'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($kategori['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <small class="text-secondary">Kategori otomatis disaring sesuai tipe transaksi.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Wallet</label>
                            <div class="input-group input-group-outline">
                                <select name="id_wallet" id="id_wallet_recurring" class="form-control" required>
                                    <option value="">Pilih Wallet</option>
                                    <?php foreach ($walletAktif as $wallet) { ?>
                                        <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === (int) $defaultWalletId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(recurring_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="jumlah" id="jumlah_recurring" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 500.000" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Generate</label>
                            <div class="input-group input-group-outline">
                                <input type="number" name="tanggal_generate" id="tanggal_generate" class="form-control" min="1" max="31" value="1" required>
                            </div>
                            <small class="text-secondary">Jika tanggal melebihi jumlah hari bulan ini, sistem memakai hari terakhir bulan.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Transaksi Default</label>
                            <div class="input-group input-group-outline">
                                <select name="status_transaksi_default" id="status_transaksi_default" class="form-control" required>
                                    <option value="pending">Pending</option>
                                    <option value="selesai">Selesai</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Template</label>
                            <div class="input-group input-group-outline">
                                <select name="is_active" id="is_active_recurring" class="form-control" required>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mulai Dari</label>
                            <div class="input-group input-group-outline">
                                <input type="date" name="mulai_dari" id="mulai_dari" class="form-control" value="<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Berakhir Pada</label>
                            <div class="input-group input-group-outline">
                                <input type="date" name="berakhir_pada" id="berakhir_pada" class="form-control">
                            </div>
                            <small class="text-secondary">Kosongkan jika template tidak punya tanggal akhir.</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Catatan</label>
                            <div class="input-group input-group-outline">
                                <textarea name="catatan" id="catatan_recurring" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info">Simpan Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var defaultWalletId = '<?= htmlspecialchars((string) $defaultWalletId, ENT_QUOTES, 'UTF-8') ?>';
        var today = '<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>';

        if ($('#datatableRecurring').length) {
            $('#datatableRecurring').DataTable({
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

        function filterRecurringCategoryOptions(selectedCategory) {
            var tipe = $('#tipe_transaksi').val();
            var firstValue = '';

            $('#id_kategori_recurring option[data-tipe]').each(function() {
                var option = $(this);
                var shouldShow = option.attr('data-tipe') === tipe;
                option.prop('disabled', !shouldShow);
                option.toggle(shouldShow);

                if (shouldShow && firstValue === '') {
                    firstValue = option.val();
                }
            });

            if (selectedCategory && $('#id_kategori_recurring option[value="' + selectedCategory + '"]:not(:disabled)').length) {
                $('#id_kategori_recurring').val(selectedCategory);
                return;
            }

            $('#id_kategori_recurring').val(firstValue);
        }

        function resetRecurringForm() {
            $('#recurring_modal_title').text('Tambah Transaksi Berulang');
            $('#id_recurring').val('');
            $('#nama_recurring').val('');
            $('#tipe_transaksi').val('<?= empty($kategoriByType['pemasukan']) && !empty($kategoriByType['pengeluaran']) ? 'pengeluaran' : 'pemasukan' ?>');
            filterRecurringCategoryOptions('');
            $('#id_wallet_recurring').val(defaultWalletId || '');
            $('#jumlah_recurring').val('');
            $('#catatan_recurring').val('');
            $('#tanggal_generate').val('1');
            $('#status_transaksi_default').val('pending');
            $('#mulai_dari').val(today);
            $('#berakhir_pada').val('');
            $('#is_active_recurring').val('1');
        }

        $('#btnTambahRecurring').on('click', function() {
            resetRecurringForm();
        });

        $('#tipe_transaksi').on('change', function() {
            filterRecurringCategoryOptions('');
        });

        $(document).on('click', '.btneditrecurring', function() {
            $('#modalRecurring').modal('show');
            $('#recurring_modal_title').text('Edit Transaksi Berulang');
            $('#id_recurring').val($(this).attr('data-id'));
            $('#nama_recurring').val($(this).attr('data-nama'));
            $('#tipe_transaksi').val($(this).attr('data-tipe'));
            filterRecurringCategoryOptions($(this).attr('data-kategori'));
            $('#id_wallet_recurring').val($(this).attr('data-wallet'));
            $('#jumlah_recurring').val($(this).attr('data-jumlah'));
            $('#catatan_recurring').val($(this).attr('data-catatan'));
            $('#tanggal_generate').val($(this).attr('data-tanggal-generate'));
            $('#status_transaksi_default').val($(this).attr('data-status-default'));
            $('#mulai_dari').val($(this).attr('data-mulai'));
            $('#berakhir_pada').val($(this).attr('data-berakhir'));
            var activeValue = String($(this).attr('data-active') || '0') === '1' ? '1' : '0';
            $('#is_active_recurring').val(activeValue);

            if (typeof applyNominalFormatting === 'function') {
                applyNominalFormatting(document.getElementById('jumlah_recurring'));
            }
        });

        $('#modalRecurring').on('hidden.bs.modal', function() {
            resetRecurringForm();
        });

        filterRecurringCategoryOptions('');
    });
</script>
