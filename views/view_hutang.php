<?php
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/csrf_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];
$today = date('Y-m-d');

function format_hutang_due_date($value)
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

function hutang_due_badge($status, $dueDate, $today)
{
    if ((string) $status === 'selesai') {
        return ['label' => 'Selesai', 'class' => 'bg-gradient-success'];
    }

    if (empty($dueDate) || $dueDate === '0000-00-00') {
        return ['label' => 'Tidak Ada Jatuh Tempo', 'class' => 'bg-gradient-secondary'];
    }

    if ($dueDate < $today) {
        return ['label' => 'Terlambat', 'class' => 'bg-gradient-danger'];
    }

    if ($dueDate === $today) {
        return ['label' => 'Jatuh Tempo Hari Ini', 'class' => 'bg-gradient-warning'];
    }

    return ['label' => 'Belum Jatuh Tempo', 'class' => 'bg-gradient-info'];
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$activeWallets = [];
$stmtWallet = $con->prepare("SELECT id_wallet, nama_wallet, tipe_wallet, is_default
    FROM wallet
    WHERE user_id = ? AND is_active = 1
    ORDER BY is_default DESC, nama_wallet ASC");
$stmtWallet->bind_param("i", $userYangSedangLogin);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
while ($walletRow = $walletResult ? $walletResult->fetch_assoc() : null) {
    $activeWallets[] = $walletRow;
}
$stmtWallet->close();
$hasActiveWallet = !empty($activeWallets);

$stmtHutang = $con->prepare("SELECT hutang.*, user.nama,
        wallet.nama_wallet AS wallet_pembayaran_nama,
        wallet.tipe_wallet AS wallet_pembayaran_tipe,
        pengeluaran.id_pengeluaran AS linked_pengeluaran_id
    FROM hutang
    INNER JOIN user ON hutang.user = user.id_user
    LEFT JOIN wallet ON hutang.id_wallet_pembayaran = wallet.id_wallet AND wallet.user_id = hutang.user
    LEFT JOIN pengeluaran ON hutang.id_pengeluaran = pengeluaran.id_pengeluaran AND pengeluaran.user = hutang.user
    WHERE user.id_user = ?
    ORDER BY hutang.tanggal DESC, hutang.id_hutang DESC");
$stmtHutang->bind_param("i", $userYangSedangLogin);
$stmtHutang->execute();
$sql = $stmtHutang->get_result();
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
                        <h6 class="text-white text-capitalize ps-3">Utang</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="text-end me-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalTambah">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <table class="table align-items-center mb-0" id="datatable">
                            <thead>
                                <tr>
                                    <th>
                                        Tanggal</th>
                                    <th>
                                        Kreditur
                                    </th>
                                    <th>Jumlah Utang</th>
                                    <th>
                                        Catatan
                                    </th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status Jatuh Tempo</th>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = 1;
                            while ($row = mysqli_fetch_array($sql)) {
                                $dueBadge = hutang_due_badge($row['status'] ?? '', $row['tanggal_jatuh_tempo'] ?? '', $today);
                            ?>

                            <tr>
                                <td class="align-middle text-center">
                                    <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['kreditur'], ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_hutang_due_date($row['tanggal_jatuh_tempo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="align-middle text-center text-sm">
                                    <span class="badge badge-sm <?= htmlspecialchars($dueBadge['class'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($dueBadge['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="align-middle text-center text-sm">
                                    <?php if (($row['status'] ?? '') === 'selesai') { ?>
                                        <span class="badge badge-sm bg-gradient-success">Selesai</span>
                                        <?php if (!empty($row['tanggal_lunas']) || !empty($row['wallet_pembayaran_nama'])) { ?>
                                            <small class="d-block text-xs text-secondary mt-1">
                                                Dibayar dari
                                                <strong><?= htmlspecialchars($row['wallet_pembayaran_nama'] ?? 'Wallet: -', ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php if (!empty($row['wallet_pembayaran_tipe'])) { ?>
                                                    (<?= htmlspecialchars($row['wallet_pembayaran_tipe'], ENT_QUOTES, 'UTF-8') ?>)
                                                <?php } ?>
                                                <?php if (!empty($row['tanggal_lunas'])) { ?>
                                                    pada <?= htmlspecialchars(format_hutang_due_date($row['tanggal_lunas']), ENT_QUOTES, 'UTF-8') ?>
                                                <?php } ?>
                                            </small>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <button type="button"
                                            class="badge badge-sm bg-gradient-warning border-0 text-white btnlunashutang"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalLunasHutang"
                                            data-id="<?= (int) $row['id_hutang'] ?>"
                                            data-kreditur="<?= htmlspecialchars($row['kreditur'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-jumlah="Rp. <?= htmlspecialchars(number_format((float) ($row['jumlah'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= !$hasActiveWallet ? 'disabled' : '' ?>>
                                            Pending
                                        </button>
                                        <?php if (!$hasActiveWallet) { ?>
                                            <small class="d-block text-xs text-danger mt-1">Buat/aktifkan wallet terlebih dahulu.</small>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                                <td class="align-middle">
                                    <form action="actions/aksi_hutang.php?act=h" method="post" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id_hutang" value="<?= (int) $row['id_hutang'] ?>">
                                        <button type="submit"
                                            data-confirm="true"
                                            data-confirm-title="Hapus data utang ini?"
                                            data-confirm-text="Data utang yang dihapus tidak bisa dikembalikan."
                                            data-confirm-confirm-text="Ya, hapus"
                                            data-confirm-cancel-text="Batal"
                                            class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                                            <i class="fa fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <a type="submit" data-id="<?= (int) $row['id_hutang'] ?>"
                                        data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-kreditur="<?= htmlspecialchars($row['kreditur'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-jatuh_tempo="<?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        class="text-secondary text-warning font-weight-bold text-xs btnedithutang">
                                        <i class="fa fa-pencil" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>

                            <?php
                                $no++;
                            }
                            ?>
                            </tbody>
                        </table>
                        <?php $stmtHutang->close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Simpan -->
<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="actions/aksi_hutang.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">hutang</h6>
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
                            <input type="hidden" name="id_hutang" id="id_hutang" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Tanggal Jatuh Tempo</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Kreditur</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="kreditur" id="kreditur" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Jumlah Hutang</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="jumlah" required class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 500.000">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Catatan</label>
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

<!-- Modal Pelunasan Utang -->
<div class="modal fade" id="modalLunasHutang" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="modalLunasHutangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="actions/aksi_hutang.php?act=l" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3" id="modalLunasHutangLabel">Lunasi Utang</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_hutang" id="lunas_id_hutang">
                    <p class="text-sm text-secondary mb-3" id="lunas_hutang_info">Pilih wallet pembayaran untuk melunasi utang.</p>
                    <div class="row my-3">
                        <label>Wallet Pembayaran</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet_pembayaran" id="id_wallet_pembayaran" class="form-control" required>
                                <option value="">Pilih wallet</option>
                                <?php foreach ($activeWallets as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>">
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= htmlspecialchars($wallet['tipe_wallet'], ENT_QUOTES, 'UTF-8') ?>)
                                        <?= (int) ($wallet['is_default'] ?? 0) === 1 ? ' - Default' : '' ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Tanggal Lunas</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal_lunas" id="tanggal_lunas_hutang" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit"
                        class="btn btn-info"
                        data-confirm="true"
                        data-confirm-title="Lunasi utang?"
                        data-confirm-text="Sistem akan membuat pengeluaran otomatis dari wallet yang dipilih."
                        data-confirm-confirm-text="Ya, lunasi"
                        data-confirm-cancel-text="Batal">Lunasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
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

    $(document).on("click", ".btnedithutang", function() {
        $('#tanggal_jatuh_tempo').val($(this).attr("data-jatuh_tempo") || '');
    });

    $(document).on("click", 'button[data-bs-target="#modalTambah"]', function() {
        $('#tanggal_jatuh_tempo').val('');
    });

    $(document).on("click", ".btnlunashutang", function() {
        $('#lunas_id_hutang').val($(this).attr("data-id"));
        $('#tanggal_lunas_hutang').val('<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>');
        $('#lunas_hutang_info').text('Lunasi utang ke ' + ($(this).attr("data-kreditur") || '-') + ' sebesar ' + ($(this).attr("data-jumlah") || 'Rp. 0') + '.');
    });
});
</script>
