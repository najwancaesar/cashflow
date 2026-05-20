<?php
include "includes/koneksi.php";
include_once "includes/csrf_helper.php";

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

function transfer_wallet_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function transfer_wallet_status_badge($status)
{
    $classes = [
        'selesai' => 'bg-gradient-success',
        'pending' => 'bg-gradient-warning',
        'batal' => 'bg-gradient-secondary',
    ];

    return $classes[$status] ?? 'bg-gradient-secondary';
}

function transfer_wallet_type_label($type)
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

$walletAktif = [];
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
}

mysqli_stmt_close($walletStmt);

$transferQuery = "SELECT
                    transfer_wallet.*,
                    wallet_asal.nama_wallet AS nama_wallet_asal,
                    wallet_asal.tipe_wallet AS tipe_wallet_asal,
                    wallet_tujuan.nama_wallet AS nama_wallet_tujuan,
                    wallet_tujuan.tipe_wallet AS tipe_wallet_tujuan
                  FROM transfer_wallet
                  INNER JOIN wallet AS wallet_asal
                    ON wallet_asal.id_wallet = transfer_wallet.wallet_asal_id
                   AND wallet_asal.user_id = transfer_wallet.user_id
                  INNER JOIN wallet AS wallet_tujuan
                    ON wallet_tujuan.id_wallet = transfer_wallet.wallet_tujuan_id
                   AND wallet_tujuan.user_id = transfer_wallet.user_id
                  WHERE transfer_wallet.user_id = ?
                  ORDER BY transfer_wallet.tanggal DESC, transfer_wallet.id_transfer DESC";
$transferStmt = mysqli_prepare($con, $transferQuery);
mysqli_stmt_bind_param($transferStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($transferStmt);
$transferResult = mysqli_stmt_get_result($transferStmt);
$transferRows = [];

while ($row = mysqli_fetch_assoc($transferResult)) {
    $transferRows[] = $row;
}

mysqli_stmt_close($transferStmt);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Transfer Wallet</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Pindahkan saldo antar wallet tanpa mencatatnya sebagai pemasukan atau pengeluaran.
                        </p>
                        <p class="text-sm text-secondary mb-0">
                            Transfer berstatus selesai akan memengaruhi saldo akhir wallet di dashboard.
                        </p>
                    </div>
                    <div class="text-end me-3 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalTransferWallet">
                            <i class="fa fa-exchange" aria-hidden="true"></i> Tambah Transfer
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <?php if (empty($transferRows)) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-exchange text-secondary mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-1">Belum ada transfer wallet.</p>
                                <p class="text-xs text-secondary mb-0">Tambahkan transfer pertama untuk memindahkan saldo antar wallet.</p>
                            </div>
                        <?php } else { ?>
                            <table class="table align-items-center mb-0" id="datatable">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Wallet Asal</th>
                                        <th>Wallet Tujuan</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                        <th>Catatan</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transferRows as $row) { ?>
                                        <?php
                                        $statusTransfer = (string) ($row['status'] ?? 'selesai');
                                        $jumlahTransfer = (float) ($row['jumlah'] ?? 0);
                                        ?>
                                        <tr>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($row['nama_wallet_asal'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(transfer_wallet_type_label($row['tipe_wallet_asal']), ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($row['nama_wallet_tujuan'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(transfer_wallet_type_label($row['tipe_wallet_tujuan']), ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?= transfer_wallet_rupiah($jumlahTransfer) ?></p>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= htmlspecialchars(transfer_wallet_status_badge($statusTransfer), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars(ucfirst($statusTransfer), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p>
                                            </td>
                                            <td class="align-middle">
                                                <a type="button"
                                                    class="text-secondary text-warning font-weight-bold text-xs me-2 btnedittransferwallet"
                                                    data-id="<?= (int) $row['id_transfer'] ?>"
                                                    data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-wallet-asal="<?= (int) $row['wallet_asal_id'] ?>"
                                                    data-wallet-tujuan="<?= (int) $row['wallet_tujuan_id'] ?>"
                                                    data-jumlah="<?= htmlspecialchars(number_format($jumlahTransfer, 0, '', ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-status="<?= htmlspecialchars($statusTransfer, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-catatan="<?= htmlspecialchars($row['catatan'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fa fa-pencil" aria-hidden="true"></i>
                                                </a>

                                                <form action="aksi_transfer_wallet.php?act=h" method="post" class="d-inline">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="id_transfer" value="<?= (int) $row['id_transfer'] ?>">
                                                    <button type="submit"
                                                        data-confirm="true"
                                                        data-confirm-title="Hapus transfer ini?"
                                                        data-confirm-text="Transfer yang dihapus tidak akan dihitung lagi pada saldo wallet."
                                                        data-confirm-confirm-text="Ya, hapus"
                                                        data-confirm-cancel-text="Batal"
                                                        class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                                                        <i class="fa fa-trash" aria-hidden="true"></i>
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

<div class="modal fade" id="modalTransferWallet" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="modalTransferWalletLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_transfer_wallet.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3" id="transfer_wallet_modal_title">Transfer Wallet</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_transfer" id="id_transfer" class="form-control">
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Asal</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="wallet_asal_id" id="wallet_asal_id" required>
                                <option value="">Pilih Wallet Asal</option>
                                <?php foreach ($walletAktif as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>">
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(transfer_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Tujuan</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="wallet_tujuan_id" id="wallet_tujuan_id" required>
                                <option value="">Pilih Wallet Tujuan</option>
                                <?php foreach ($walletAktif as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>">
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(transfer_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if (count($walletAktif) < 2) { ?>
                            <small class="text-secondary px-2 mt-1">Minimal perlu dua wallet aktif untuk melakukan transfer.</small>
                        <?php } ?>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah Transfer</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 500.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Status</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="status" id="status" required>
                                <option value="selesai">Selesai</option>
                                <option value="pending">Pending</option>
                                <option value="batal">Batal</option>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3"></textarea>
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

        $(document).on("click", ".btnedittransferwallet", function() {
            $('#modalTransferWallet').modal('show');
            $('#transfer_wallet_modal_title').text('Edit Transfer Wallet');
            $('#id_transfer').val($(this).attr("data-id"));
            $('#tanggal').val($(this).attr("data-tanggal"));
            $('#wallet_asal_id').val($(this).attr("data-wallet-asal"));
            $('#wallet_tujuan_id').val($(this).attr("data-wallet-tujuan"));
            $('#jumlah').val($(this).attr("data-jumlah"));
            $('#status').val($(this).attr("data-status"));
            $('#catatan').val($(this).attr("data-catatan"));

            if (typeof applyNominalFormatting === 'function') {
                applyNominalFormatting(document.getElementById('jumlah'));
            }
        });

        $('#modalTransferWallet').on('hidden.bs.modal', function() {
            $('#transfer_wallet_modal_title').text('Transfer Wallet');
            $('#id_transfer').val('');
            $('#tanggal').val('<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>');
            $('#wallet_asal_id').val('');
            $('#wallet_tujuan_id').val('');
            $('#jumlah').val('');
            $('#status').val('selesai');
            $('#catatan').val('');
        });
    });
</script>
