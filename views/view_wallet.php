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

function format_wallet_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function wallet_type_label($type)
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

function wallet_type_badge_class($type)
{
    $classes = [
        'cash' => 'bg-gradient-success',
        'bank' => 'bg-gradient-info',
        'e_wallet' => 'bg-gradient-warning',
        'tabungan' => 'bg-gradient-primary',
        'lainnya' => 'bg-gradient-secondary',
    ];

    return $classes[$type] ?? 'bg-gradient-secondary';
}

$walletQuery = "SELECT id_wallet, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active, created_at, updated_at
                FROM wallet
                WHERE user_id = ?
                ORDER BY is_default DESC, is_active DESC, nama_wallet ASC";
$walletStmt = mysqli_prepare($con, $walletQuery);
mysqli_stmt_bind_param($walletStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($walletStmt);
$walletResult = mysqli_stmt_get_result($walletStmt);
$walletRows = [];

while ($row = mysqli_fetch_assoc($walletResult)) {
    $walletRows[] = $row;
}

mysqli_stmt_close($walletStmt);
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
                            Kelola wallet pribadi untuk persiapan pencatatan transaksi multi-dompet.
                        </p>
                        <p class="text-sm text-secondary mb-0">
                            Phase 1 hanya mengelola data wallet, belum terhubung ke pemasukan dan pengeluaran.
                        </p>
                    </div>
                    <div class="text-end me-3 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalWallet">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Wallet
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <?php if (empty($walletRows)) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-credit-card text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-1">Belum ada wallet. Tambahkan wallet pertama kamu.</p>
                                <p class="text-xs text-secondary mb-0">Wallet pertama akan menjadi default jika belum ada default wallet.</p>
                            </div>
                        <?php } else { ?>
                            <table class="table align-items-center mb-0" id="datatable">
                                <thead>
                                    <tr>
                                        <th>Nama Wallet</th>
                                        <th>Tipe</th>
                                        <th>Saldo Awal</th>
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
                                        ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">
                                                    <?= htmlspecialchars($row['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= htmlspecialchars(wallet_type_badge_class($row['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars(wallet_type_label($row['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= format_wallet_rupiah($row['saldo_awal']) ?></p>
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
                                                    data-tipe="<?= htmlspecialchars($row['tipe_wallet'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-saldo="<?= htmlspecialchars((string) $row['saldo_awal'], ENT_QUOTES, 'UTF-8') ?>">
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
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="e_wallet">E-Wallet</option>
                                <option value="tabungan">Tabungan</option>
                                <option value="lainnya">Lainnya</option>
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
        });
    });
</script>
