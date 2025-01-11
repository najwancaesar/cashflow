<?php
include "includes/koneksi.php";
$tglSekarang = date('Y-m-d');
$bulansekarang = date('m', strtotime($tglSekarang));
$tahunsekarang = date('Y', strtotime($tglSekarang));
$kemarin = date('Y-m-d', strtotime('yesterday'));
$bulanLalu = date('Y-m-d', strtotime('last month'));
$userYangSedangLogin = $_SESSION["id_user"];

$q_pendapatan = mysqli_query($con, "select sum(jumlah) as pendapatan_sekarang from pemasukan where date(tanggal) = '$tglSekarang' and user =$userYangSedangLogin");
$pendapatan = mysqli_fetch_array($q_pendapatan);

$q_pengeluaran = mysqli_query($con, "select sum(jumlah) as pengeluaran_sekarang from pengeluaran where date(tanggal) = '$tglSekarang' and user =$userYangSedangLogin");
$pengeluaran = mysqli_fetch_array($q_pengeluaran);


$q_transaksi = mysqli_query($con, "select sum(jumlah) as transaksi_sekarang from pemasukan where date(tanggal) = '$tglSekarang' and user=$userYangSedangLogin");
$transaksi = mysqli_fetch_array($q_transaksi);

$q_tpemasukan = mysqli_query($con, "select * from pemasukan where date(tanggal) = '$tglSekarang' and user=$userYangSedangLogin");
$tpemasukan = mysqli_num_rows($q_tpemasukan);

$q_tpengeluaran = mysqli_query($con, "select * from pengeluaran where date(tanggal) = '$tglSekarang' and user=$userYangSedangLogin");
$tpengeluaran = mysqli_num_rows($q_tpengeluaran);

$transaksi_hariini = $tpemasukan + $tpengeluaran;

$q_tpemasukan = mysqli_query($con, "select * from pemasukan where status = 'pending' and user=$userYangSedangLogin");
$tpemasukan_pending = mysqli_num_rows($q_tpemasukan);


// bulan

$q_pendapatan_bulan = mysqli_query($con, "select sum(jumlah) as pendapatan_bulan 
from pemasukan where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin");
$pendapatan_bulan = mysqli_fetch_array($q_pendapatan_bulan);

$q_pengeluaran = mysqli_query($con, "select sum(jumlah) as pengeluaran_bulan from pengeluaran where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin ");
$pengeluaran_bulan = mysqli_fetch_array($q_pengeluaran);

$q_tpemasukan_bulan = mysqli_query($con, "select * from pemasukan where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin");
$tpemasukan_bulan = mysqli_num_rows($q_tpemasukan_bulan);

$q_tpengeluaran_bulan = mysqli_query($con, "select * from pengeluaran where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin");
$tpengeluaran_bulan = mysqli_num_rows($q_tpengeluaran_bulan);

$transaksi_bulan = $tpemasukan_bulan + $tpengeluaran_bulan;

// user
$q_user = mysqli_query($con, "select * from user ");
$user = mysqli_num_rows($q_user);



// Memeriksa apakah 'id_user' ada dalam session
if (isset($_SESSION['id_user'])) {
    $id_user = $_SESSION['id_user']; // Mendapatkan ID user dari session
} else {
    // Jika tidak ada, beri pesan error atau arahkan ke halaman login
    die("User tidak terdeteksi dalam session.");
}

// Menyesuaikan query untuk hanya menampilkan data untuk pengguna yang sedang aktif
$q_pemasukan_terbaru = mysqli_query($con, "
    SELECT * 
    FROM pemasukan
    INNER JOIN user ON pemasukan.user = user.id_user
    WHERE pemasukan.user = '$id_user' 
    ORDER BY id_pemasukan DESC
    LIMIT 5
");


$q_pengeluaran_terbaru = mysqli_query($con, "
    SELECT * 
    FROM pengeluaran
    INNER JOIN user ON pengeluaran.user = user.id_user
    WHERE pengeluaran.user = '$id_user'  -- Menambahkan filter untuk user yang aktif
    ORDER BY id_pengeluaran DESC
    LIMIT 5
");



$q_hutang = mysqli_query($con, "select sum(jumlah) as utang_sekarang from pemasukan where date(tanggal) = '$tglSekarang' and user=$userYangSedangLogin");
$hutang = mysqli_fetch_array($q_hutang);

$q_piutang = mysqli_query($con, "select sum(jumlah) as piutang_sekarang from piutang where date(tanggal) = '$tglSekarang' and user=$userYangSedangLogin");
$piutang = mysqli_fetch_array($q_piutang);


// bulan

$q_utang_bulan = mysqli_query($con, "select sum(jumlah) as utang_bulan 
from hutang where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin");
$utang_bulan = mysqli_fetch_array($q_utang_bulan);

$q_piutang = mysqli_query($con, "select sum(jumlah) as piutang_bulan from piutang where MONTH(tanggal) = '$bulansekarang' and YEAR(tanggal) = '$tahunsekarang ' and user=$userYangSedangLogin ");
$piutang_bulan = mysqli_fetch_array($q_piutang);

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
                        <h4 class="mb-0">Rp. <?= number_format($pendapatan['pendapatan_sekarang']) ?></h4>
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
                        <h4 class="mb-0">Rp. <?= number_format($pengeluaran['pengeluaran_sekarang']) ?></h4>
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
                        <h4 class="mb-0"><?= number_format($transaksi_hariini) ?></h4>
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
                        <h4 class="mb-0"><?= number_format($tpemasukan_pending) ?></h4>
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
                        <h4 class="mb-0">Rp. <?= number_format($pendapatan_bulan['pendapatan_bulan']) ?></h4>
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
                        <h4 class="mb-0">Rp. <?= number_format($pengeluaran_bulan['pengeluaran_bulan']) ?></h4>
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
                        <h4 class="mb-0"><?= number_format($transaksi_bulan) ?></h4>
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
                        <h4 class="mb-0"><?= number_format($user) ?></h4>
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
                        <h4 class="mb-0">Rp. <?= number_format($utang_bulan['utang_bulan']) ?></h4>
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
                        <h4 class="mb-0">Rp. <?= number_format($piutang_bulan['piutang_bulan']) ?></h4>
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
                            <?php while ($row = mysqli_fetch_array($q_pemasukan_terbaru)) { ?>
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
                                    <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format($row['jumlah']) ?>
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
                            <?php while ($row = mysqli_fetch_array($q_pengeluaran_terbaru)) { ?>
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
                                    <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format($row['jumlah']) ?>
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
    var ctx1 = document.getElementById("chart-pendapatan").getContext("2d");

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

    var ctx2 = document.getElementById("chart-pengeluaran").getContext("2d");

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

    var ctx3 = document.getElementById("chart-transaksi").getContext("2d");

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

})
</script>