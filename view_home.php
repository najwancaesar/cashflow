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

function fetch_category_breakdown($con, $table, $userId, $month, $year, $limit = 5)
{
    $allowedTables = [
        'pemasukan' => 'pemasukan',
        'pengeluaran' => 'pengeluaran',
    ];

    if (!isset($allowedTables[$table])) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $tipeKategori = $allowedTables[$table];
    $sql = "SELECT
                COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS kategori_nama,
                COALESCE(SUM(transaksi.jumlah), 0) AS total_jumlah,
                COUNT(*) AS total_transaksi
            FROM {$table} AS transaksi
            LEFT JOIN kategori
                ON transaksi.id_kategori = kategori.id_kategori
               AND kategori.user_id = transaksi.user
               AND kategori.tipe_kategori = ?
            WHERE transaksi.user = ? AND MONTH(transaksi.tanggal) = ? AND YEAR(transaksi.tanggal) = ?
            GROUP BY COALESCE(kategori.nama_kategori, 'Belum dikategorikan')
            ORDER BY total_jumlah DESC, total_transaksi DESC
            LIMIT {$limit}";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("siii", $tipeKategori, $userId, $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'kategori_nama' => (string) ($row['kategori_nama'] ?? 'Belum dikategorikan'),
            'total_jumlah' => (float) ($row['total_jumlah'] ?? 0),
            'total_transaksi' => (int) ($row['total_transaksi'] ?? 0),
        ];
    }

    $stmt->close();

    return $rows;
}

function build_chart_series_from_breakdown($rows)
{
    $labels = [];
    $values = [];

    foreach ($rows as $row) {
        $labels[] = $row['kategori_nama'];
        $values[] = (float) ($row['total_jumlah'] ?? 0);
    }

    return [
        'labels' => $labels,
        'values' => $values,
    ];
}

function get_top_category_summary($rows, $defaultLabel)
{
    if (empty($rows)) {
        return [
            'kategori_nama' => $defaultLabel,
            'total_jumlah' => 0,
            'total_transaksi' => 0,
            'is_empty' => true,
        ];
    }

    $topRow = $rows[0];
    $topRow['is_empty'] = false;

    return $topRow;
}

function format_datetime_label($value)
{
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y H:i', $timestamp);
}

$tglSekarang = date('Y-m-d');
$bulansekarang = (int) date('m', strtotime($tglSekarang));
$tahunsekarang = (int) date('Y', strtotime($tglSekarang));
$userYangSedangLogin = (int) ($_SESSION["id_user"] ?? 0);
$isAdmin = strtolower((string) ($_SESSION['role'] ?? '')) === 'admin';

if ($userYangSedangLogin <= 0) {
    die("User tidak terdeteksi dalam session.");
}

if ($isAdmin) {
    $totalUser = (int) fetch_single_value($con, "SELECT COUNT(*) FROM user WHERE role = 'user'");
    $userAktif = (int) fetch_single_value($con, "SELECT COUNT(*) FROM user WHERE role = 'user' AND is_active = '1'");
    $userNonAktif = (int) fetch_single_value($con, "SELECT COUNT(*) FROM user WHERE role = 'user' AND is_active = '0'");
    $userBaru = (int) fetch_single_value(
        $con,
        "SELECT COUNT(*) FROM user WHERE role = 'user' AND MONTH(create_at) = ? AND YEAR(create_at) = ?",
        "ii",
        [$bulansekarang, $tahunsekarang]
    );
    $totalAdmin = (int) fetch_single_value($con, "SELECT COUNT(*) FROM user WHERE role = 'admin'");
    $lastLoginUser = fetch_all_rows(
        $con,
        "SELECT nama, username, last_login_at
         FROM user
         WHERE role = 'user' AND last_login_at IS NOT NULL
         ORDER BY last_login_at DESC
         LIMIT 1"
    );
    $recentAccountActivity = fetch_all_rows(
        $con,
        "SELECT id_user, nama, username, role, is_active, create_at, last_login_at, last_profile_update_at
         FROM user
         ORDER BY
            COALESCE(last_login_at, '1970-01-01 00:00:00') DESC,
            COALESCE(last_profile_update_at, '1970-01-01 00:00:00') DESC,
            create_at DESC
         LIMIT 10"
    );

    $lastLoginSummary = $lastLoginUser[0] ?? null;
    ?>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape cashflow-icon-user text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-users" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Total User</p>
                            <h4 class="mb-0"><?= number_format((float) $totalUser) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3"></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape bg-gradient-success shadow-success text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-user-circle" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">User Aktif</p>
                            <h4 class="mb-0"><?= number_format((float) $userAktif) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3"></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape cashflow-icon-pending text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-minus-circle" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">User Nonaktif</p>
                            <h4 class="mb-0"><?= number_format((float) $userNonAktif) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3"></div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape cashflow-icon-user text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-user-circle" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Total Admin</p>
                            <h4 class="mb-0"><?= number_format((float) $totalAdmin) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3"></div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape cashflow-icon-user text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-users" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">User Baru Bulan Ini</p>
                            <h4 class="mb-0"><?= number_format((float) $userBaru) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <p class="mb-0 text-sm text-secondary">Menghitung akun dengan role user yang terdaftar pada bulan berjalan.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-8 col-sm-6">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-header p-3 pt-2">
                        <div class="icon icon-lg icon-shape cashflow-icon-pending text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Last Login User</p>
                            <h5 class="mb-0">
                                <?= htmlspecialchars($lastLoginSummary['nama'] ?? 'Belum ada login user') ?>
                            </h5>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <?php if ($lastLoginSummary) { ?>
                            <p class="mb-0 text-sm text-secondary">
                                <?= htmlspecialchars($lastLoginSummary['username']) ?> login terakhir pada <?= htmlspecialchars(format_datetime_label($lastLoginSummary['last_login_at'])) ?>.
                            </p>
                        <?php } else { ?>
                            <p class="mb-0 text-sm text-secondary">Belum ada user biasa yang tercatat login ke sistem.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card my-4">
                    <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                        <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                            <h6 class="text-white text-capitalize ps-3">Aktivitas Akun Terbaru</h6>
                        </div>
                    </div>
                    <div class="card-body px-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">User</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Role</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tanggal Daftar</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Last Login</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Update Profil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAccountActivity as $activity) { ?>
                                        <tr>
                                            <td>
                                                <div class="px-3 py-2">
                                                    <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($activity['nama']) ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($activity['username']) ?></p>
                                                </div>
                                            </td>
                                            <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars(ucfirst($activity['role'])) ?></p></td>
                                            <td>
                                                <span class="badge badge-sm <?= ($activity['is_active'] ?? '1') === '1' ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                    <?= ($activity['is_active'] ?? '1') === '1' ? 'Aktif' : 'Nonaktif' ?>
                                                </span>
                                            </td>
                                            <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_datetime_label($activity['create_at'])) ?></p></td>
                                            <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_datetime_label($activity['last_login_at'])) ?></p></td>
                                            <td><p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_datetime_label($activity['last_profile_update_at'])) ?></p></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
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

$tpengeluaran_pending = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM pengeluaran WHERE status = ? AND user = ?",
    "si",
    ['pending', $userYangSedangLogin]
);

$total_pending = $tpemasukan_pending + $tpengeluaran_pending;

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
$jumlah_kategori_aktif = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM kategori WHERE user_id = ?",
    "i",
    [$userYangSedangLogin]
);

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

$kategori_pemasukan_breakdown = fetch_category_breakdown(
    $con,
    'pemasukan',
    $userYangSedangLogin,
    $bulansekarang,
    $tahunsekarang,
    5
);

$kategori_pengeluaran_breakdown = fetch_category_breakdown(
    $con,
    'pengeluaran',
    $userYangSedangLogin,
    $bulansekarang,
    $tahunsekarang,
    5
);

$top_kategori_pemasukan = get_top_category_summary($kategori_pemasukan_breakdown, 'Belum ada data pemasukan');
$top_kategori_pengeluaran = get_top_category_summary($kategori_pengeluaran_breakdown, 'Belum ada data pengeluaran');

$chart_kategori_pemasukan = build_chart_series_from_breakdown($kategori_pemasukan_breakdown);
$chart_kategori_pengeluaran = build_chart_series_from_breakdown($kategori_pengeluaran_breakdown);

?>
<style>
.dashboard-stat-card {
    height: 100%;
}

.dashboard-stat-card .card-header {
    padding: 1rem 1rem 0.75rem;
    min-height: auto;
    position: relative;
}

.dashboard-stat-card .icon.icon-shape {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.85rem !important;
    margin-top: -1rem !important;
}

.dashboard-stat-card .icon.icon-shape i {
    font-size: 1.1rem;
}

.dashboard-stat-card .text-end.pt-1 {
    padding-top: 0.2rem !important;
}

.dashboard-stat-card .text-sm {
    font-size: 0.76rem !important;
    line-height: 1.3;
}

.dashboard-stat-card h4 {
    margin-top: 0.2rem;
    margin-bottom: 0;
    font-size: 1.08rem;
    line-height: 1.25;
}

.dashboard-stat-card h5 {
    margin-top: 0.25rem;
    margin-bottom: 0;
    font-size: 1rem;
    line-height: 1.3;
}

.dashboard-stat-card .card-footer {
    padding: 0.65rem 1rem 0.9rem;
}

.dashboard-stat-card .card-footer p {
    margin-bottom: 0;
    line-height: 1.4;
}

@media (min-width: 1200px) {
    .dashboard-stat-card h4 {
        font-size: 1.14rem;
    }

    .dashboard-stat-card h5 {
        font-size: 1.02rem;
    }
}

@media (max-width: 991.98px) {
    .dashboard-stat-card .card-header {
        padding-bottom: 0.7rem;
    }

    .dashboard-stat-card .icon.icon-shape {
        width: 2.6rem;
        height: 2.6rem;
    }

    .dashboard-stat-card h4 {
        font-size: 1.02rem;
    }

    .dashboard-stat-card h5 {
        font-size: 0.98rem;
    }
}

@media (max-width: 767.98px) {
    .dashboard-stat-card .card-header {
        padding: 0.95rem 0.95rem 0.7rem;
    }

    .dashboard-stat-card .icon.icon-shape {
        width: 2.45rem;
        height: 2.45rem;
        margin-top: -0.9rem !important;
    }

    .dashboard-stat-card .icon.icon-shape i {
        font-size: 1rem;
    }

    .dashboard-stat-card .text-sm {
        font-size: 0.74rem !important;
    }

    .dashboard-stat-card h4 {
        font-size: 0.98rem;
    }

    .dashboard-stat-card h5 {
        font-size: 0.94rem;
    }
}

@media (max-width: 575.98px) {
    .dashboard-stat-card .card-header {
        padding-right: 0.85rem;
        padding-left: 0.85rem;
    }

    .dashboard-stat-card .card-footer {
        padding-right: 0.85rem;
        padding-left: 0.85rem;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-income text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-arrow-circle-down" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-expense text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-arrow-circle-up" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-report text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-exchange" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-pending text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-clock-o" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Pending</p>
                        <h4 class="mb-0"><?= number_format((float) ($total_pending ?? 0)) ?></h4>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-income text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-money" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-expense text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-credit-card" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-report text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-list-alt" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-user text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-users" aria-hidden="true"></i>
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
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-category text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-tags" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Kategori Aktif</p>
                        <h4 class="mb-0"><?= number_format((float) ($jumlah_kategori_aktif ?? 0)) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">Jumlah kategori yang sudah Anda buat untuk pemasukan dan pengeluaran.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-income text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-line-chart" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Top Kategori Pemasukan</p>
                        <h5 class="mb-0"><?= htmlspecialchars($top_kategori_pemasukan['kategori_nama']) ?></h5>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <?php if ($top_kategori_pemasukan['is_empty']) { ?>
                        <p class="mb-0 text-sm text-secondary">Belum ada pemasukan pada bulan berjalan.</p>
                    <?php } else { ?>
                        <p class="mb-0 text-sm text-secondary">
                            Rp. <?= number_format((float) $top_kategori_pemasukan['total_jumlah']) ?> dari <?= number_format((float) $top_kategori_pemasukan['total_transaksi']) ?> transaksi bulan ini.
                        </p>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-12">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-expense text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-pie-chart" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Top Kategori Pengeluaran</p>
                        <h5 class="mb-0"><?= htmlspecialchars($top_kategori_pengeluaran['kategori_nama']) ?></h5>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <?php if ($top_kategori_pengeluaran['is_empty']) { ?>
                        <p class="mb-0 text-sm text-secondary">Belum ada pengeluaran pada bulan berjalan.</p>
                    <?php } else { ?>
                        <p class="mb-0 text-sm text-secondary">
                            Rp. <?= number_format((float) $top_kategori_pengeluaran['total_jumlah']) ?> dari <?= number_format((float) $top_kategori_pengeluaran['total_transaksi']) ?> transaksi bulan ini.
                        </p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6 mb-lg-0 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <h6 class="mb-0">Breakdown Kategori Pemasukan</h6>
                    <p class="text-sm text-secondary mb-0">Periode bulan berjalan</p>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($kategori_pemasukan_breakdown)) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-line-chart text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum ada data kategori pemasukan untuk bulan ini.</p>
                            <p class="text-xs text-secondary mb-0">Transaksi akan muncul di sini setelah Anda menambahkan pemasukan.</p>
                        </div>
                    <?php } else { ?>
                        <div class="chart">
                            <canvas id="chart-kategori-pemasukan" class="chart-canvas" height="220"></canvas>
                        </div>
                        <div class="mt-3">
                            <?php foreach ($kategori_pemasukan_breakdown as $row) { ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div>
                                        <p class="text-sm font-weight-bold mb-0"><?= htmlspecialchars($row['kategori_nama']) ?></p>
                                        <p class="text-xs text-secondary mb-0"><?= number_format((float) $row['total_transaksi']) ?> transaksi</p>
                                    </div>
                                    <p class="text-sm font-weight-bold mb-0">Rp. <?= number_format((float) $row['total_jumlah']) ?></p>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <h6 class="mb-0">Breakdown Kategori Pengeluaran</h6>
                    <p class="text-sm text-secondary mb-0">Periode bulan berjalan</p>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($kategori_pengeluaran_breakdown)) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-pie-chart text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum ada data kategori pengeluaran untuk bulan ini.</p>
                            <p class="text-xs text-secondary mb-0">Transaksi akan muncul di sini setelah Anda menambahkan pengeluaran.</p>
                        </div>
                    <?php } else { ?>
                        <div class="chart">
                            <canvas id="chart-kategori-pengeluaran" class="chart-canvas" height="220"></canvas>
                        </div>
                        <div class="mt-3">
                            <?php foreach ($kategori_pengeluaran_breakdown as $row) { ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div>
                                        <p class="text-sm font-weight-bold mb-0"><?= htmlspecialchars($row['kategori_nama']) ?></p>
                                        <p class="text-xs text-secondary mb-0"><?= number_format((float) $row['total_transaksi']) ?> transaksi</p>
                                    </div>
                                    <p class="text-sm font-weight-bold mb-0">Rp. <?= number_format((float) $row['total_jumlah']) ?></p>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-6 col-sm-6 mb-xl-0 mb-4">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-debt text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-minus-circle" aria-hidden="true"></i>
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
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-receivable text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-handshake-o" aria-hidden="true"></i>
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
                                        class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
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
                                        class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
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
    var pemasukanKategoriCanvas = document.getElementById("chart-kategori-pemasukan");
    if (pemasukanKategoriCanvas) {
        new Chart(pemasukanKategoriCanvas.getContext("2d"), {
            type: "doughnut",
            data: {
                labels: <?= json_encode($chart_kategori_pemasukan['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chart_kategori_pemasukan['values']) ?>,
                    backgroundColor: ['#22c55e', '#14b8a6', '#0ea5e9', '#3b82f6', '#94a3b8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 16
                        }
                    }
                }
            }
        });
    }

    var pengeluaranKategoriCanvas = document.getElementById("chart-kategori-pengeluaran");
    if (pengeluaranKategoriCanvas) {
        new Chart(pengeluaranKategoriCanvas.getContext("2d"), {
            type: "doughnut",
            data: {
                labels: <?= json_encode($chart_kategori_pengeluaran['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chart_kategori_pengeluaran['values']) ?>,
                    backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#fb7185', '#94a3b8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 16
                        }
                    }
                }
            }
        });
    }
});
</script>
