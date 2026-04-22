<?php
include "includes/koneksi.php";

function fetch_single_value($con, $sql, $types = '', $params = [])
{
    $stmt = $con->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();

    return $row[0] ?? 0;
}

function fetch_all_rows($con, $sql, $types = '', $params = [])
{
    $stmt = $con->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

function fetch_monthly_sum_series($con, $table, $userId, $year)
{
    $allowedTables = ['pemasukan', 'pengeluaran'];
    if (!in_array($table, $allowedTables, true)) {
        return array_fill(0, 12, 0);
    }

    $series = array_fill(0, 12, 0);
    $sql = "SELECT MONTH(tanggal) AS month_number, COALESCE(SUM(jumlah), 0) AS total
            FROM {$table}
            WHERE user = ? AND YEAR(tanggal) = ?
            GROUP BY MONTH(tanggal)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $userId, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $monthIndex = (int) ($row['month_number'] ?? 0) - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $series[$monthIndex] = (float) ($row['total'] ?? 0);
        }
    }

    $stmt->close();

    return $series;
}

function fetch_monthly_transaction_count_series($con, $userId, $year)
{
    $series = array_fill(0, 12, 0);
    $tables = ['pemasukan', 'pengeluaran'];

    foreach ($tables as $table) {
        $sql = "SELECT MONTH(tanggal) AS month_number, COUNT(*) AS total
                FROM {$table}
                WHERE user = ? AND YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ii", $userId, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $monthIndex = (int) ($row['month_number'] ?? 0) - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $series[$monthIndex] += (int) ($row['total'] ?? 0);
            }
        }

        $stmt->close();
    }

    return $series;
}

$tglSekarang = date('Y-m-d');
$bulansekarang = (int) date('m', strtotime($tglSekarang));
$tahunsekarang = (int) date('Y', strtotime($tglSekarang));
$userYangSedangLogin = (int) ($_SESSION["id_user"] ?? 0);

if ($userYangSedangLogin <= 0) {
    die("User tidak terdeteksi dalam session.");
}

$pendapatan = [
    'pendapatan_sekarang' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM pemasukan WHERE tanggal = ? AND user = ?",
        "si",
        [$tglSekarang, $userYangSedangLogin]
    ),
];

$pengeluaran = [
    'pengeluaran_sekarang' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE tanggal = ? AND user = ?",
        "si",
        [$tglSekarang, $userYangSedangLogin]
    ),
];

$tpemasukan = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pemasukan WHERE tanggal = ? AND user = ?",
    "si",
    [$tglSekarang, $userYangSedangLogin]
);

$tpengeluaran = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pengeluaran WHERE tanggal = ? AND user = ?",
    "si",
    [$tglSekarang, $userYangSedangLogin]
);

$transaksi_hariini = $tpemasukan + $tpengeluaran;

$tpemasukan_pending = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pemasukan WHERE status = ? AND user = ?",
    "si",
    ['pending', $userYangSedangLogin]
);

$pendapatan_bulan = [
    'pendapatan_bulan' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM pemasukan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
        "iii",
        [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
    ),
];

$pengeluaran_bulan = [
    'pengeluaran_bulan' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
        "iii",
        [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
    ),
];

$tpemasukan_bulan = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pemasukan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
    "iii",
    [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
);

$tpengeluaran_bulan = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pengeluaran WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
    "iii",
    [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
);

$transaksi_bulan = $tpemasukan_bulan + $tpengeluaran_bulan;
$user = (int) fetch_single_value($con, "SELECT COUNT(*) FROM user");

$q_pemasukan_terbaru = fetch_all_rows(
    $con,
    "SELECT pemasukan.*, user.nama
    FROM pemasukan
    INNER JOIN user ON pemasukan.user = user.id_user
    WHERE pemasukan.user = ?
    ORDER BY id_pemasukan DESC
    LIMIT 5",
    "i",
    [$userYangSedangLogin]
);

$q_pengeluaran_terbaru = fetch_all_rows(
    $con,
    "SELECT pengeluaran.*, user.nama
    FROM pengeluaran
    INNER JOIN user ON pengeluaran.user = user.id_user
    WHERE pengeluaran.user = ?
    ORDER BY id_pengeluaran DESC
    LIMIT 5",
    "i",
    [$userYangSedangLogin]
);

$hutang = [
    'utang_sekarang' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM hutang WHERE tanggal = ? AND user = ?",
        "si",
        [$tglSekarang, $userYangSedangLogin]
    ),
];

$piutang = [
    'piutang_sekarang' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM piutang WHERE tanggal = ? AND user = ?",
        "si",
        [$tglSekarang, $userYangSedangLogin]
    ),
];

$utang_bulan = [
    'utang_bulan' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM hutang WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
        "iii",
        [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
    ),
];

$piutang_bulan = [
    'piutang_bulan' => fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0) FROM piutang WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND user = ?",
        "iii",
        [$bulansekarang, $tahunsekarang, $userYangSedangLogin]
    ),
];

$chart = [
    'cpdt' => fetch_monthly_sum_series($con, 'pemasukan', $userYangSedangLogin, $tahunsekarang),
    'cpgt' => fetch_monthly_sum_series($con, 'pengeluaran', $userYangSedangLogin, $tahunsekarang),
    'ctt' => fetch_monthly_transaction_count_series($con, $userYangSedangLogin, $tahunsekarang),
];

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-dark text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pendapatan Hari Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($pendapatan['pendapatan_sekarang'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">receipt</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pengeluaran Hari Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($pengeluaran['pengeluaran_sekarang'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-success text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">notes</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Transaksi Hari Ini</p>
                        <h4 class="mb-0"><?= number_format((float) ($transaksi_hariini ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">autorenew</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pending</p>
                        <h4 class="mb-0"><?= number_format((float) ($tpemasukan_pending ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-dark text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pendapatan Bulan Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($pendapatan_bulan['pendapatan_bulan'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">receipt</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pengeluaran Bulan Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($pengeluaran_bulan['pengeluaran_bulan'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-success text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">notes</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Transaksi Bulan Ini</p>
                        <h4 class="mb-0"><?= number_format((float) ($transaksi_bulan ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">person</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pengguna</p>
                        <h4 class="mb-0"><?= number_format((float) ($user ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-6 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-dark text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Utang Bulan Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($utang_bulan['utang_bulan'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape bg-gradient-info shadow-info text-center border-radius-xl mt-n4 position-absolute">
                        <i class="material-icons opacity-10" translate="no">receipt</i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Piutang Bulan Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) ($piutang_bulan['piutang_bulan'] ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
    </div>
    <div class="row mb-4">
        <div class="card my-4">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                    <h6 class="text-white text-capitalize ps-3">Pemasukan Terbaru</h6>
                </div>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status</th>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Tanggal</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Catatan
                                </th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                    Jumlah Pemasukan</th>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($q_pemasukan_terbaru as $row) { ?>
                            <tr>
                                <td class="align-middle text-center text-sm">
                                    <span
                                        class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-info' : 'bg-gradient-secondary' ?>">
                                        <?php if ($row['status'] == 'selesai'): ?>
                                        <?= $row['status'] ?>
                                        <?php else : ?>
                                        <a href="" class="text-white">
                                            <?= $row['status'] ?>
                                        </a>
                                        <?php endif ?>
                                    </span>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="text-secondary text-xs font-weight-bold"><?= $row['tanggal'] ?></span>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= $row['catatan'] ?></p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= $row['nama'] ?></p>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="card my-4">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                    <h6 class="text-white text-capitalize ps-3">Pengeluaran Terbaru</h6>
                </div>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status</th>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Tanggal</th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Catatan
                                </th>
                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                    Jumlah Pengeluaran</th>
                                <th
                                    class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($q_pengeluaran_terbaru as $row) { ?>
                            <tr>
                                <td class="align-middle text-center text-sm">
                                    <span
                                        class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-info' : 'bg-gradient-secondary' ?>">
                                        <?php if ($row['status'] == 'selesai'): ?>
                                        <?= $row['status'] ?>
                                        <?php else : ?>
                                        <a href="" class="text-white">
                                            <?= $row['status'] ?>
                                        </a>
                                        <?php endif ?>
                                    </span>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="text-secondary text-xs font-weight-bold"><?= $row['tanggal'] ?></span>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= $row['catatan'] ?></p>
                                </td>
                                <td>
                                    <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </p>
                                </td>
                                <td>
                                    <p class="text-xs text-secondary mb-0"><?= $row['nama'] ?></p>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var pendapatanCanvas = document.getElementById("chart-pendapatan");
    if (pendapatanCanvas) {
    var ctx1 = pendapatanCanvas.getContext("2d");

    new Chart(ctx1, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov",
                "Dec"
            ],
            datasets: [{
                label: "Pemasukan",
                tension: 0,
                borderWidth: 0,
                pointRadius: 5,
                pointBackgroundColor: "rgba(255, 255, 255, .8)",
                pointBorderColor: "transparent",
                borderColor: "rgba(255, 255, 255, .8)",
                borderColor: "rgba(255, 255, 255, .8)",
                borderWidth: 4,
                backgroundColor: "transparent",
                fill: true,
                data: <?= json_encode($chart['cpdt']) ?>,
                maxBarThickness: 6

            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: {
                y: {
                    grid: {
                        drawBorder: false,
                        display: true,
                        drawOnChartArea: true,
                        drawTicks: false,
                        borderDash: [5, 5],
                        color: 'rgba(255, 255, 255, .2)'
                    },
                    ticks: {
                        display: true,
                        color: '#f8f9fa',
                        padding: 10,
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                    }
                },
                x: {
                    grid: {
                        drawBorder: false,
                        display: false,
                        drawOnChartArea: false,
                        drawTicks: false,
                        borderDash: [5, 5]
                    },
                    ticks: {
                        display: true,
                        color: '#f8f9fa',
                        padding: 10,
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                    }
                },
            },
        },
    });
    }

    var pengeluaranCanvas = document.getElementById("chart-pengeluaran");
    if (pengeluaranCanvas) {
    var ctx2 = pengeluaranCanvas.getContext("2d");

    new Chart(ctx2, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov",
                "Dec"
            ],
            datasets: [{
                label: "Pengeluaran",
                tension: 0,
                borderWidth: 0,
                pointRadius: 5,
                pointBackgroundColor: "rgba(255, 255, 255, .8)",
                pointBorderColor: "transparent",
                borderColor: "rgba(255, 255, 255, .8)",
                borderWidth: 4,
                backgroundColor: "transparent",
                fill: true,
                data: <?= json_encode($chart['cpgt']) ?>,
                maxBarThickness: 6

            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: {
                y: {
                    grid: {
                        drawBorder: false,
                        display: true,
                        drawOnChartArea: true,
                        drawTicks: false,
                        borderDash: [5, 5],
                        color: 'rgba(255, 255, 255, .2)'
                    },
                    ticks: {
                        display: true,
                        padding: 10,
                        color: '#f8f9fa',
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                    }
                },
                x: {
                    grid: {
                        drawBorder: false,
                        display: false,
                        drawOnChartArea: false,
                        drawTicks: false,
                        borderDash: [5, 5]
                    },
                    ticks: {
                        display: true,
                        color: '#f8f9fa',
                        padding: 10,
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                    }
                },
            },
        },
    });
    }

    var transaksiCanvas = document.getElementById("chart-transaksi");
    if (transaksiCanvas) {
    var ctx3 = transaksiCanvas.getContext("2d");

    new Chart(ctx3, {
        type: "bar",
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov",
                "Dec"
            ],
            datasets: [{
                label: "Transaksi",
                tension: 0.4,
                borderWidth: 0,
                borderRadius: 4,
                borderSkipped: false,
                backgroundColor: "rgba(255, 255, 255, .8)",
                data: <?= json_encode($chart['ctt']) ?>,
                maxBarThickness: 6
            }, ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: {
                y: {
                    grid: {
                        drawBorder: false,
                        display: true,
                        drawOnChartArea: true,
                        drawTicks: false,
                        borderDash: [5, 5],
                        color: 'rgba(255, 255, 255, .2)'
                    },
                    ticks: {
                        suggestedMin: 0,
                        suggestedMax: 500,
                        beginAtZero: true,
                        padding: 10,
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                        color: "#fff"
                    },
                },
                x: {
                    grid: {
                        drawBorder: false,
                        display: true,
                        drawOnChartArea: true,
                        drawTicks: false,
                        borderDash: [5, 5],
                        color: 'rgba(255, 255, 255, .2)'
                    },
                    ticks: {
                        display: true,
                        color: '#f8f9fa',
                        padding: 10,
                        font: {
                            size: 14,
                            weight: 300,
                            family: "Roboto",
                            style: 'normal',
                            lineHeight: 2
                        },
                    }
                },
            },
        },
    });
    }

})
</script>
