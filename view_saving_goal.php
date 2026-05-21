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

function saving_goal_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function saving_goal_format_date($value)
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

function saving_goal_status_meta($status, $saldo, $target)
{
    if ($status === 'arsip') {
        return ['label' => 'Arsip', 'class' => 'bg-gradient-secondary'];
    }

    if ($status === 'selesai') {
        return ['label' => 'Selesai', 'class' => 'bg-gradient-success'];
    }

    if ((float) $target > 0 && (float) $saldo >= (float) $target) {
        return ['label' => 'Target Tercapai', 'class' => 'bg-gradient-success'];
    }

    return ['label' => 'Aktif', 'class' => 'bg-gradient-info'];
}

function saving_goal_mutasi_badge($tipe)
{
    return $tipe === 'tarik' ? 'bg-gradient-warning' : 'bg-gradient-success';
}

function saving_goal_wallet_type_label($type)
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

function render_saving_goal_table($tableId, $goalRows, $mutasiByGoal, $emptyMessage, $hasWalletAktif)
{
    if (empty($goalRows)) { ?>
        <div class="border border-radius-lg p-4 text-center">
            <i class="fa fa-bullseye text-secondary mb-2" aria-hidden="true"></i>
            <p class="text-sm text-secondary mb-0"><?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php return;
    }
    ?>
    <table class="table align-items-center mb-0" id="<?= htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') ?>">
        <thead>
            <tr>
                <th>Celengan</th>
                <th>Progress</th>
                <th>Target Tanggal</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($goalRows as $row) { ?>
                <?php
                $goalId = (int) $row['id_goal'];
                $targetNominal = (float) ($row['target_nominal'] ?? 0);
                $saldoTerkumpul = (float) ($row['saldo_terkumpul'] ?? 0);
                $progressRaw = $targetNominal > 0 ? ($saldoTerkumpul / $targetNominal) * 100 : 0;
                $progressWidth = min(100, max(0, $progressRaw));
                $status = (string) ($row['status'] ?? 'aktif');
                $statusMeta = saving_goal_status_meta($status, $saldoTerkumpul, $targetNominal);
                $goalMutasi = $mutasiByGoal[$goalId] ?? [];
                ?>
                <tr>
                    <td>
                        <p class="text-xs font-weight-bold mb-1"><?= htmlspecialchars($row['nama_goal'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-secondary mb-0">
                            <?= saving_goal_rupiah($saldoTerkumpul) ?> dari <?= saving_goal_rupiah($targetNominal) ?>
                        </p>
                    </td>
                    <td style="min-width: 180px;">
                        <div class="d-flex align-items-center">
                            <span class="text-xs font-weight-bold me-2"><?= number_format($progressRaw, 1) ?>%</span>
                            <div class="progress w-100">
                                <div class="progress-bar bg-gradient-info" role="progressbar"
                                    style="width: <?= htmlspecialchars((string) $progressWidth, ENT_QUOTES, 'UTF-8') ?>%;"
                                    aria-valuenow="<?= htmlspecialchars((string) $progressWidth, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(saving_goal_format_date($row['target_tanggal']), ENT_QUOTES, 'UTF-8') ?></p>
                    </td>
                    <td>
                        <span class="badge badge-sm <?= htmlspecialchars($statusMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($statusMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="align-middle">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <a type="button"
                                class="text-secondary text-warning font-weight-bold text-xs btneditsavinggoal"
                                data-id="<?= $goalId ?>"
                                data-nama="<?= htmlspecialchars($row['nama_goal'], ENT_QUOTES, 'UTF-8') ?>"
                                data-target="<?= htmlspecialchars(number_format($targetNominal, 0, '', ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-target-tanggal="<?= htmlspecialchars($row['target_tanggal'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa fa-pencil" aria-hidden="true"></i> Edit
                            </a>

                            <?php if ($status === 'aktif' && $hasWalletAktif) { ?>
                                <a type="button"
                                    class="text-secondary text-success font-weight-bold text-xs btnsetorsavinggoal"
                                    data-id="<?= $goalId ?>"
                                    data-nama="<?= htmlspecialchars($row['nama_goal'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Setor
                                </a>

                                <a type="button"
                                    class="text-secondary text-info font-weight-bold text-xs btntariksavinggoal"
                                    data-id="<?= $goalId ?>"
                                    data-nama="<?= htmlspecialchars($row['nama_goal'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-saldo="<?= htmlspecialchars(number_format($saldoTerkumpul, 0, '', ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa fa-minus-circle" aria-hidden="true"></i> Tarik
                                </a>
                            <?php } elseif ($status === 'aktif' && !$hasWalletAktif) { ?>
                                <button type="button" class="text-secondary font-weight-bold text-xs border-0 bg-transparent p-0" disabled>
                                    <i class="fa fa-plus-circle" aria-hidden="true"></i> Setor
                                </button>
                                <button type="button" class="text-secondary font-weight-bold text-xs border-0 bg-transparent p-0" disabled>
                                    <i class="fa fa-minus-circle" aria-hidden="true"></i> Tarik
                                </button>
                            <?php } ?>

                            <button type="button"
                                class="text-secondary font-weight-bold text-xs border-0 bg-transparent p-0"
                                data-bs-toggle="modal"
                                data-bs-target="#modalRiwayatGoal<?= $goalId ?>">
                                <i class="fa fa-history" aria-hidden="true"></i> Riwayat
                            </button>

                            <?php if ($status === 'aktif') { ?>
                                <form action="aksi_saving_goal.php?act=status" method="post" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id_goal" value="<?= $goalId ?>">
                                    <input type="hidden" name="status" value="selesai">
                                    <button type="submit"
                                        data-confirm="true"
                                        data-confirm-title="Tandai celengan selesai?"
                                        data-confirm-text="Celengan selesai akan dikunci dari setor dan tarik sampai diaktifkan kembali."
                                        data-confirm-confirm-text="Ya, tandai selesai"
                                        data-confirm-cancel-text="Batal"
                                        class="text-secondary text-success font-weight-bold text-xs border-0 bg-transparent p-0">
                                        <i class="fa fa-check-circle" aria-hidden="true"></i> Tandai Selesai
                                    </button>
                                </form>
                            <?php } ?>

                            <?php if (in_array($status, ['selesai', 'arsip'], true)) { ?>
                                <form action="aksi_saving_goal.php?act=status" method="post" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id_goal" value="<?= $goalId ?>">
                                    <input type="hidden" name="status" value="aktif">
                                    <button type="submit"
                                        data-confirm="true"
                                        data-confirm-title="Aktifkan kembali celengan ini?"
                                        data-confirm-text="Celengan akan kembali aktif dan bisa menerima setor/tarik."
                                        data-confirm-confirm-text="Ya, aktifkan"
                                        data-confirm-cancel-text="Batal"
                                        class="text-secondary text-info font-weight-bold text-xs border-0 bg-transparent p-0">
                                        <i class="fa fa-undo" aria-hidden="true"></i> Aktifkan
                                    </button>
                                </form>
                            <?php } ?>

                            <?php if ($status !== 'arsip') { ?>
                                <form action="aksi_saving_goal.php?act=status" method="post" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id_goal" value="<?= $goalId ?>">
                                    <input type="hidden" name="status" value="arsip">
                                    <button type="submit"
                                        data-confirm="true"
                                        data-confirm-title="Arsipkan celengan ini?"
                                        data-confirm-text="Celengan akan dipindahkan ke status arsip tanpa menghapus riwayat mutasi."
                                        data-confirm-confirm-text="Ya, arsipkan"
                                        data-confirm-cancel-text="Batal"
                                        class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                                        <i class="fa fa-archive" aria-hidden="true"></i> Arsipkan
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php foreach ($goalRows as $row) { ?>
        <?php
        $goalId = (int) $row['id_goal'];
        $goalMutasi = $mutasiByGoal[$goalId] ?? [];
        ?>
        <div class="modal fade" id="modalRiwayatGoal<?= $goalId ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">Riwayat Mutasi - <?= htmlspecialchars($row['nama_goal'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($goalMutasi)) { ?>
                            <p class="text-sm text-secondary mb-0">Belum ada riwayat mutasi untuk celengan ini.</p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Tipe</th>
                                            <th>Wallet</th>
                                            <th>Jumlah</th>
                                            <th>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($goalMutasi as $mutasi) { ?>
                                            <tr>
                                                <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars(saving_goal_format_date($mutasi['tanggal']), ENT_QUOTES, 'UTF-8') ?></p></td>
                                                <td>
                                                    <span class="badge badge-sm <?= htmlspecialchars(saving_goal_mutasi_badge($mutasi['tipe']), ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars(ucfirst($mutasi['tipe']), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <p class="text-xs text-secondary mb-0">
                                                        <?php if (!empty($mutasi['nama_wallet'])) { ?>
                                                            <?= htmlspecialchars($mutasi['tipe'] === 'setor' ? 'Dari ' : 'Ke ', ENT_QUOTES, 'UTF-8') ?><?= htmlspecialchars($mutasi['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php } else { ?>
                                                            -
                                                        <?php } ?>
                                                    </p>
                                                </td>
                                                <td><p class="text-xs font-weight-bold mb-0"><?= saving_goal_rupiah($mutasi['jumlah']) ?></p></td>
                                                <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars($mutasi['catatan'] ?: '-', ENT_QUOTES, 'UTF-8') ?></p></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php }
}

$goalQuery = "SELECT
                saving_goal.*,
                COALESCE(mutasi.saldo_terkumpul, 0) AS saldo_terkumpul,
                COALESCE(mutasi.total_mutasi, 0) AS total_mutasi
              FROM saving_goal
              LEFT JOIN (
                SELECT
                    id_goal,
                    user_id,
                    COALESCE(SUM(CASE
                        WHEN tipe = 'setor' THEN jumlah
                        WHEN tipe = 'tarik' THEN -jumlah
                        ELSE 0
                    END), 0) AS saldo_terkumpul,
                    COUNT(*) AS total_mutasi
                FROM saving_goal_mutasi
                WHERE user_id = ?
                GROUP BY id_goal, user_id
              ) AS mutasi
                ON mutasi.id_goal = saving_goal.id_goal
               AND mutasi.user_id = saving_goal.user_id
              WHERE saving_goal.user_id = ?
              ORDER BY
                CASE saving_goal.status
                    WHEN 'aktif' THEN 1
                    WHEN 'selesai' THEN 2
                    ELSE 3
                END,
                saving_goal.updated_at DESC,
                saving_goal.id_goal DESC";
$goalStmt = mysqli_prepare($con, $goalQuery);
mysqli_stmt_bind_param($goalStmt, "ii", $userYangSedangLogin, $userYangSedangLogin);
mysqli_stmt_execute($goalStmt);
$goalResult = mysqli_stmt_get_result($goalStmt);
$goalRows = [];

while ($row = mysqli_fetch_assoc($goalResult)) {
    $goalRows[] = $row;
}

mysqli_stmt_close($goalStmt);

$walletQuery = "SELECT id_wallet, nama_wallet, tipe_wallet, is_default
                FROM wallet
                WHERE user_id = ? AND is_active = 1
                ORDER BY is_default DESC, nama_wallet ASC";
$walletStmt = mysqli_prepare($con, $walletQuery);
mysqli_stmt_bind_param($walletStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($walletStmt);
$walletResult = mysqli_stmt_get_result($walletStmt);
$walletAktif = [];
$defaultWalletId = 0;

while ($wallet = mysqli_fetch_assoc($walletResult)) {
    if ((int) ($wallet['is_default'] ?? 0) === 1) {
        $defaultWalletId = (int) $wallet['id_wallet'];
    }
    $walletAktif[] = $wallet;
}

mysqli_stmt_close($walletStmt);

if ($defaultWalletId === 0 && !empty($walletAktif)) {
    $defaultWalletId = (int) $walletAktif[0]['id_wallet'];
}

$hasWalletAktif = count($walletAktif) > 0;

$mutasiQuery = "SELECT
                    saving_goal_mutasi.id_mutasi,
                    saving_goal_mutasi.id_goal,
                    saving_goal_mutasi.id_wallet,
                    saving_goal_mutasi.tanggal,
                    saving_goal_mutasi.tipe,
                    saving_goal_mutasi.jumlah,
                    saving_goal_mutasi.catatan,
                    saving_goal_mutasi.created_at,
                    wallet.nama_wallet
                FROM saving_goal_mutasi
                LEFT JOIN wallet
                    ON wallet.id_wallet = saving_goal_mutasi.id_wallet
                   AND wallet.user_id = saving_goal_mutasi.user_id
                WHERE saving_goal_mutasi.user_id = ?
                ORDER BY saving_goal_mutasi.tanggal DESC, saving_goal_mutasi.id_mutasi DESC";
$mutasiStmt = mysqli_prepare($con, $mutasiQuery);
mysqli_stmt_bind_param($mutasiStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($mutasiStmt);
$mutasiResult = mysqli_stmt_get_result($mutasiStmt);
$mutasiByGoal = [];

while ($mutasi = mysqli_fetch_assoc($mutasiResult)) {
    $goalId = (int) $mutasi['id_goal'];
    if (!isset($mutasiByGoal[$goalId])) {
        $mutasiByGoal[$goalId] = [];
    }
    $mutasiByGoal[$goalId][] = $mutasi;
}

mysqli_stmt_close($mutasiStmt);

$goalAktif = [];
$goalArsip = [];
$totalSaldoAktif = 0;
$totalTargetAktif = 0;

foreach ($goalRows as $row) {
    if (($row['status'] ?? '') === 'arsip') {
        $goalArsip[] = $row;
        continue;
    }

    $goalAktif[] = $row;
    $totalSaldoAktif += (float) ($row['saldo_terkumpul'] ?? 0);
    $totalTargetAktif += (float) ($row['target_nominal'] ?? 0);
}

$totalProgressAktif = $totalTargetAktif > 0 ? min(100, ($totalSaldoAktif / $totalTargetAktif) * 100) : 0;
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-bullseye" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Celengan Aktif</p>
                        <h4 class="mb-0"><?= number_format((float) count($goalAktif)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3"></div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div class="icon icon-lg icon-shape bg-gradient-success shadow-success text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-money" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Saldo Terkumpul</p>
                        <h4 class="mb-0"><?= saving_goal_rupiah($totalSaldoAktif) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3"></div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-12">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div class="icon icon-lg icon-shape bg-gradient-warning shadow-warning text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-line-chart" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Progress Total</p>
                        <h4 class="mb-0"><?= number_format((float) $totalProgressAktif, 1) ?>%</h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">Dari target <?= saving_goal_rupiah($totalTargetAktif) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Celengan Virtual</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Kelola celengan virtual pribadi dengan alokasi dana dari wallet aktif.
                        </p>
                        <p class="text-xs text-secondary mb-0">
                            Setor celengan mengurangi saldo wallet, sedangkan tarik celengan menambah saldo wallet.
                        </p>
                        <?php if (!$hasWalletAktif) { ?>
                            <div class="alert alert-warning text-white mt-3 mb-0" role="alert">
                                Setor/tarik celengan membutuhkan minimal satu wallet aktif.
                            </div>
                        <?php } ?>
                    </div>
                    <div class="text-end me-3 mt-3">
                        <button type="button" class="btn btn-secondary" id="btnTambahSavingGoal" data-bs-toggle="modal"
                            data-bs-target="#modalSavingGoal">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Celengan
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <?php render_saving_goal_table('datatableSavingGoal', $goalAktif, $mutasiByGoal, 'Belum ada celengan virtual. Mulai buat celengan pertamamu, misalnya Dana Darurat atau Beli Laptop.', $hasWalletAktif); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($goalArsip)) { ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card my-4">
                    <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                        <div class="bg-gradient-secondary shadow-secondary border-radius-lg pt-4 pb-3">
                            <h6 class="text-white text-capitalize ps-3">Arsip Celengan Virtual</h6>
                        </div>
                    </div>
                    <div class="card-body px-0 pb-2">
                        <div class="table-responsive p-4 mx-2">
                            <?php render_saving_goal_table('datatableSavingGoalArchive', $goalArsip, $mutasiByGoal, 'Belum ada celengan virtual yang diarsipkan.', $hasWalletAktif); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<div class="modal fade" id="modalSavingGoal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_saving_goal.php?act=goal" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3" id="saving_goal_modal_title">Celengan Virtual</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_goal" id="id_goal" class="form-control">
                    <div class="row">
                        <label class="form-label">Nama Celengan</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="nama_goal" id="nama_goal" class="form-control" maxlength="150" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Target Nominal</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="target_nominal" id="target_nominal" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 5.000.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Target Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="target_tanggal" id="target_tanggal" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Status</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="status" id="status_goal">
                                <option value="aktif">Aktif</option>
                                <option value="selesai">Selesai</option>
                                <option value="arsip">Arsip</option>
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

<div class="modal fade" id="modalSetorSavingGoal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_saving_goal.php?act=setor" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Setor Celengan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_goal" id="setor_id_goal">
                    <p class="text-sm text-secondary" id="setor_goal_name"></p>
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" id="setor_tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Sumber</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet" id="setor_id_wallet" class="form-control" required <?= !$hasWalletAktif ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet Sumber</option>
                                <?php foreach ($walletAktif as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $defaultWalletId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(saving_goal_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah Setor</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="setor_jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" id="setor_catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" <?= !$hasWalletAktif ? 'disabled' : '' ?>>Simpan Setor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTarikSavingGoal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_saving_goal.php?act=tarik" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Tarik Celengan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_goal" id="tarik_id_goal">
                    <p class="text-sm text-secondary mb-1" id="tarik_goal_name"></p>
                    <p class="text-xs text-secondary" id="tarik_goal_saldo"></p>
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" id="tarik_tanggal" class="form-control" value="<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Tujuan</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet" id="tarik_id_wallet" class="form-control" required <?= !$hasWalletAktif ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet Tujuan</option>
                                <?php foreach ($walletAktif as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $defaultWalletId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(saving_goal_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah Tarik</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="tarik_jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" id="tarik_catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning" <?= !$hasWalletAktif ? 'disabled' : '' ?>>Simpan Tarik</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var defaultWalletId = '<?= (int) $defaultWalletId ?>';

        ['#datatableSavingGoal', '#datatableSavingGoalArchive'].forEach(function(selector) {
            if ($(selector).length) {
                $(selector).DataTable({
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
        });

        $('#btnTambahSavingGoal').on('click', function() {
            $('#saving_goal_modal_title').text('Tambah Celengan Virtual');
            $('#id_goal').val('');
            $('#nama_goal').val('');
            $('#target_nominal').val('');
            $('#target_tanggal').val('');
            $('#status_goal').val('aktif');
        });

        $(document).on('click', '.btneditsavinggoal', function() {
            $('#modalSavingGoal').modal('show');
            $('#saving_goal_modal_title').text('Edit Celengan Virtual');
            $('#id_goal').val($(this).attr('data-id'));
            $('#nama_goal').val($(this).attr('data-nama'));
            $('#target_nominal').val($(this).attr('data-target'));
            $('#target_tanggal').val($(this).attr('data-target-tanggal'));
            $('#status_goal').val($(this).attr('data-status'));

            if (typeof applyNominalFormatting === 'function') {
                applyNominalFormatting(document.getElementById('target_nominal'));
            }
        });

        $(document).on('click', '.btnsetorsavinggoal', function() {
            $('#modalSetorSavingGoal').modal('show');
            $('#setor_id_goal').val($(this).attr('data-id'));
            $('#setor_goal_name').text('Celengan: ' + ($(this).attr('data-nama') || '-'));
            $('#setor_tanggal').val('<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>');
            $('#setor_id_wallet').val(defaultWalletId || '');
            $('#setor_jumlah').val('');
            $('#setor_catatan').val('');
        });

        $(document).on('click', '.btntariksavinggoal', function() {
            $('#modalTarikSavingGoal').modal('show');
            $('#tarik_id_goal').val($(this).attr('data-id'));
            $('#tarik_goal_name').text('Celengan: ' + ($(this).attr('data-nama') || '-'));
            $('#tarik_goal_saldo').text('Saldo terkumpul: Rp. ' + Number($(this).attr('data-saldo') || 0).toLocaleString('id-ID'));
            $('#tarik_tanggal').val('<?= htmlspecialchars($tanggalHariIni, ENT_QUOTES, 'UTF-8') ?>');
            $('#tarik_id_wallet').val(defaultWalletId || '');
            $('#tarik_jumlah').val('');
            $('#tarik_catatan').val('');
        });

        $('#modalSavingGoal').on('hidden.bs.modal', function() {
            $('#saving_goal_modal_title').text('Celengan Virtual');
            $('#id_goal').val('');
            $('#nama_goal').val('');
            $('#target_nominal').val('');
            $('#target_tanggal').val('');
            $('#status_goal').val('aktif');
        });
    });
</script>
