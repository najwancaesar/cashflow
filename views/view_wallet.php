<?php
include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
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

function format_wallet_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

$walletRows = cashflow_get_user_wallet_balances($con, $userYangSedangLogin);
$walletTypeFeatureReady = cashflow_wallet_type_schema_ready($con);
$legacyWalletTypes = cashflow_legacy_wallet_types();
$customWalletTypes = cashflow_get_custom_wallet_types($con, $userYangSedangLogin, false);
$walletCustomTypeMap = cashflow_get_wallet_custom_type_map($con, $userYangSedangLogin);
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
                    <div class="d-flex flex-wrap justify-content-end gap-2 me-3 mt-3 cashflow-page-actions">
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
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($walletRows as $row) { ?>
                                        <?php
                                        $isDefault = (string) ($row['is_default'] ?? '0') === '1';
                                        $isActive = (string) ($row['is_active'] ?? '1') === '1';
                                        $targetStatus = $isActive ? '0' : '1';
                                        $targetStatusLabel = $isActive ? 'Nonaktif' : 'Aktif';
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
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= format_wallet_rupiah($row['saldo_terkini']) ?></p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= $isDefault ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= $isDefault ? 'Default' : 'Bukan Default' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= $isActive ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0">
                                                    <?= htmlspecialchars(date('d M Y H:i', strtotime($row['updated_at'] ?? $row['created_at']))) ?>
                                                </p>
                                            </td>
                                            <td class="align-middle">
                                                <a type="button"
                                                    class="text-secondary text-warning font-weight-bold text-xs me-2 btneditwallet"
                                                    data-id="<?= (int) $row['id_wallet'] ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-tipe="<?= htmlspecialchars($walletTypeSelection, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-saldo="<?= htmlspecialchars(number_format((float) $row['saldo_awal'], 0, '', ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fa fa-pencil" aria-hidden="true"></i>
                                                </a>

                                                <form action="actions/aksi_wallet.php?act=s" method="post" class="d-inline">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="id_wallet" value="<?= (int) $row['id_wallet'] ?>">
                                                    <input type="hidden" name="value" value="<?= htmlspecialchars($targetStatus, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit"
                                                        data-confirm="true"
                                                        data-confirm-title="<?= $isActive ? 'Nonaktifkan wallet ini?' : 'Aktifkan wallet ini?' ?>"
                                                        data-confirm-text="<?= $isActive ? 'Wallet nonaktif tidak disiapkan untuk transaksi berikutnya.' : 'Wallet akan aktif kembali.' ?>"
                                                        data-confirm-confirm-text="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                                        data-confirm-cancel-text="Batal"
                                                        class="text-secondary <?= $isActive ? 'text-success' : 'text-secondary' ?> font-weight-bold text-xs me-2 border-0 bg-transparent p-0">
                                                        <i class="fa <?= $isActive ? 'fa-toggle-on' : 'fa-toggle-off' ?>" aria-hidden="true"></i>
                                                    </button>
                                                </form>

                                                <form action="actions/aksi_wallet.php?act=d" method="post" class="d-inline">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="id_wallet" value="<?= (int) $row['id_wallet'] ?>">
                                                    <button type="submit"
                                                        data-confirm="true"
                                                        data-confirm-title="Jadikan wallet default?"
                                                        data-confirm-text="Wallet ini akan menjadi wallet default akun Anda."
                                                        data-confirm-confirm-text="Ya, jadikan default"
                                                        data-confirm-cancel-text="Batal"
                                                        class="text-secondary text-info font-weight-bold text-xs border-0 bg-transparent p-0">
                                                        <i class="fa fa-star<?= $isDefault ? '' : '-o' ?>" aria-hidden="true"></i>
                                                    </button>
                                                </form>
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
                        <div class="col-md-5">
                            <label class="form-label" for="wallet_type_name">Nama Tipe</label>
                            <input type="text" class="form-control" name="nama_tipe" id="wallet_type_name" maxlength="50" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="wallet_type_icon">Ikon</label>
                            <select class="form-control" name="icon" id="wallet_type_icon" required>
                                <?php foreach (cashflow_wallet_type_icon_options() as $iconKey => $iconLabel) { ?>
                                    <option value="<?= htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($iconLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="wallet_type_color">Warna</label>
                            <input type="color" class="form-control form-control-color w-100" name="warna"
                                id="wallet_type_color" value="#64748B" required>
                        </div>
                        <div class="col-md-2 d-grid gap-2">
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
                dom: ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">'
            });
        }

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

            if (typeof applyNominalFormatting === 'function') {
                applyNominalFormatting(document.getElementById('saldo_awal'));
            }
        });

        $('#modalWallet').on('hidden.bs.modal', function() {
            $('#wallet_modal_title').text('Wallet');
            $('#id_wallet').val('');
            $('#nama_wallet').val('');
            $('#tipe_wallet').val('');
            $('#saldo_awal').val('');
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
