<?php
include "includes/koneksi.php";
include_once "includes/csrf_helper.php";

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

function fetch_monthly_totals($con, $table, $userId, $startDate, $endDate)
{
    $allowedTables = ['pemasukan', 'pengeluaran'];
    if (!in_array($table, $allowedTables, true)) {
        return [];
    }

    $sql = "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bulan, COALESCE(SUM(jumlah), 0) AS total
            FROM {$table}
            WHERE user = ? AND tanggal BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(tanggal, '%Y-%m')";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $totals = [];

    while ($result && ($row = $result->fetch_assoc())) {
        $totals[(string) $row['bulan']] = (float) ($row['total'] ?? 0);
    }

    $stmt->close();

    return $totals;
}

function format_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function dashboard_wallet_type_label($type)
{
    $labels = [
        'cash' => 'CASH',
        'bank' => 'BANK',
        'e_wallet' => 'E-WALLET',
        'tabungan' => 'TABUNGAN',
        'lainnya' => 'LAINNYA',
    ];

    return $labels[$type] ?? 'LAINNYA';
}

function dashboard_wallet_type_badge_class($type)
{
    $classes = [
        'cash' => 'bg-gradient-success',
        'bank' => 'bg-gradient-info',
        'e_wallet' => 'bg-gradient-primary',
        'tabungan' => 'bg-gradient-warning',
        'lainnya' => 'bg-gradient-secondary',
    ];

    return $classes[$type] ?? 'bg-gradient-secondary';
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
$mingguBerjalan = new DateTimeImmutable($tglSekarang);
$tanggalAwalMinggu = $mingguBerjalan->modify('monday this week')->format('Y-m-d');
$tanggalAkhirMinggu = $mingguBerjalan->modify('sunday this week')->format('Y-m-d');
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

$pemasukan_minggu_ini = (float) fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0)
         FROM pemasukan
         WHERE tanggal BETWEEN ? AND ?
           AND status = 'selesai'
           AND user = ?",
        "ssi",
        [$tanggalAwalMinggu, $tanggalAkhirMinggu, $userYangSedangLogin]
);

$pengeluaran_minggu_ini = (float) fetch_single_value(
        $con,
        "SELECT COALESCE(SUM(jumlah), 0)
         FROM pengeluaran
         WHERE tanggal BETWEEN ? AND ?
           AND status = 'selesai'
           AND user = ?",
        "ssi",
        [$tanggalAwalMinggu, $tanggalAkhirMinggu, $userYangSedangLogin]
);

$tpemasukan = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*)
     FROM pemasukan
     WHERE tanggal BETWEEN ? AND ?
       AND status = 'selesai'
       AND user = ?",
    "ssi",
    [$tanggalAwalMinggu, $tanggalAkhirMinggu, $userYangSedangLogin]
);

$tpengeluaran = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*)
     FROM pengeluaran
     WHERE tanggal BETWEEN ? AND ?
       AND status = 'selesai'
       AND user = ?",
    "ssi",
    [$tanggalAwalMinggu, $tanggalAkhirMinggu, $userYangSedangLogin]
);

$transaksi_minggu_ini = $tpemasukan + $tpengeluaran;

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

$pemasukan_bulan_ini = (float) ($pendapatan_bulan['pendapatan_bulan'] ?? 0);
$pengeluaran_bulan_ini = (float) ($pengeluaran_bulan['pengeluaran_bulan'] ?? 0);
$sisa_cashflow_bulan = $pemasukan_bulan_ini - $pengeluaran_bulan_ini;

$total_pemasukan_semua = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0) FROM pemasukan WHERE user = ?",
    "i",
    [$userYangSedangLogin]
);

$total_pengeluaran_semua = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE user = ?",
    "i",
    [$userYangSedangLogin]
);

$total_saldo_keseluruhan = $total_pemasukan_semua - $total_pengeluaran_semua;
$hari_berjalan_bulan_ini = max(1, (int) date('j', strtotime($tglSekarang)));
$rata_pengeluaran_harian = $pengeluaran_bulan_ini / $hari_berjalan_bulan_ini;

$trend_awal_bulan = (new DateTimeImmutable('first day of this month'))->modify('-5 months');
$trend_akhir_bulan = new DateTimeImmutable('last day of this month');
$trend_pemasukan_map = fetch_monthly_totals(
    $con,
    'pemasukan',
    $userYangSedangLogin,
    $trend_awal_bulan->format('Y-m-01'),
    $trend_akhir_bulan->format('Y-m-d')
);
$trend_pengeluaran_map = fetch_monthly_totals(
    $con,
    'pengeluaran',
    $userYangSedangLogin,
    $trend_awal_bulan->format('Y-m-01'),
    $trend_akhir_bulan->format('Y-m-d')
);
$cashflow_trend = [
    'labels' => [],
    'pemasukan' => [],
    'pengeluaran' => [],
    'net' => [],
];

for ($i = 0; $i < 6; $i++) {
    $trend_bulan = $trend_awal_bulan->modify("+{$i} months");
    $trend_key = $trend_bulan->format('Y-m');
    $trend_pemasukan = (float) ($trend_pemasukan_map[$trend_key] ?? 0);
    $trend_pengeluaran = (float) ($trend_pengeluaran_map[$trend_key] ?? 0);

    $cashflow_trend['labels'][] = $trend_bulan->format('M Y');
    $cashflow_trend['pemasukan'][] = $trend_pemasukan;
    $cashflow_trend['pengeluaran'][] = $trend_pengeluaran;
    $cashflow_trend['net'][] = $trend_pemasukan - $trend_pengeluaran;
}

$has_cashflow_trend = (array_sum($cashflow_trend['pemasukan']) + array_sum($cashflow_trend['pengeluaran'])) > 0;
$cashflow_bulan_class = $sisa_cashflow_bulan < 0 ? 'cashflow-icon-expense' : 'cashflow-icon-income';
$saldo_total_class = $total_saldo_keseluruhan < 0 ? 'cashflow-icon-expense' : 'cashflow-icon-report';

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

$q_pemasukan_terbaru = fetch_all_rows(
    $con,
    "SELECT
        pemasukan.*,
        COALESCE(kategori.nama_kategori, 'Tanpa Kategori') AS nama_kategori,
        COALESCE(wallet.nama_wallet, 'Dompet Utama') AS nama_wallet,
        wallet.tipe_wallet
    FROM pemasukan
    LEFT JOIN kategori
        ON pemasukan.id_kategori = kategori.id_kategori
       AND kategori.user_id = pemasukan.user
       AND kategori.tipe_kategori = 'pemasukan'
    LEFT JOIN wallet
        ON pemasukan.id_wallet = wallet.id_wallet
       AND wallet.user_id = pemasukan.user
    WHERE pemasukan.user = ?
    ORDER BY pemasukan.id_pemasukan DESC
    LIMIT 5",
    "i",
    [$userYangSedangLogin]
);

$q_pengeluaran_terbaru = fetch_all_rows(
    $con,
    "SELECT
        pengeluaran.*,
        COALESCE(kategori.nama_kategori, 'Tanpa Kategori') AS nama_kategori,
        COALESCE(wallet.nama_wallet, 'Dompet Utama') AS nama_wallet,
        wallet.tipe_wallet
    FROM pengeluaran
    LEFT JOIN kategori
        ON pengeluaran.id_kategori = kategori.id_kategori
       AND kategori.user_id = pengeluaran.user
       AND kategori.tipe_kategori = 'pengeluaran'
    LEFT JOIN wallet
        ON pengeluaran.id_wallet = wallet.id_wallet
       AND wallet.user_id = pengeluaran.user
    WHERE pengeluaran.user = ?
    ORDER BY pengeluaran.id_pengeluaran DESC
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

$hutang_overdue_count = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM hutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo IS NOT NULL
       AND tanggal_jatuh_tempo < ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$hutang_overdue_total = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0) FROM hutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo IS NOT NULL
       AND tanggal_jatuh_tempo < ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$piutang_overdue_count = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM piutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo IS NOT NULL
       AND tanggal_jatuh_tempo < ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$piutang_overdue_total = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0) FROM piutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo IS NOT NULL
       AND tanggal_jatuh_tempo < ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$hutang_due_today_count = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM hutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo = ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$piutang_due_today_count = (int) fetch_single_value(
    $con,
    "SELECT COUNT(*) FROM piutang
     WHERE user = ? AND status = 'pending'
       AND tanggal_jatuh_tempo = ?",
    "is",
    [$userYangSedangLogin, $tglSekarang]
);

$due_today_total_count = $hutang_due_today_count + $piutang_due_today_count;

$wallet_summary_rows = fetch_all_rows(
    $con,
    "SELECT
        wallet.id_wallet,
        wallet.nama_wallet,
        wallet.tipe_wallet,
        wallet.saldo_awal,
        wallet.is_default,
        COALESCE(pemasukan_wallet.total_pemasukan, 0) AS total_pemasukan,
        COALESCE(pengeluaran_wallet.total_pengeluaran, 0) AS total_pengeluaran,
        COALESCE(transfer_masuk_wallet.total_transfer_masuk, 0) AS total_transfer_masuk,
        COALESCE(transfer_keluar_wallet.total_transfer_keluar, 0) AS total_transfer_keluar,
        COALESCE(celengan_setor_wallet.total_celengan_setor, 0) AS total_celengan_setor,
        COALESCE(celengan_tarik_wallet.total_celengan_tarik, 0) AS total_celengan_tarik
     FROM wallet
     LEFT JOIN (
        SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_pemasukan
        FROM pemasukan
        WHERE user = ?
          AND status = 'selesai'
          AND id_wallet IS NOT NULL
        GROUP BY id_wallet
     ) AS pemasukan_wallet
        ON pemasukan_wallet.id_wallet = wallet.id_wallet
     LEFT JOIN (
        SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_pengeluaran
        FROM pengeluaran
        WHERE user = ?
          AND status = 'selesai'
          AND id_wallet IS NOT NULL
        GROUP BY id_wallet
     ) AS pengeluaran_wallet
        ON pengeluaran_wallet.id_wallet = wallet.id_wallet
     LEFT JOIN (
        SELECT wallet_tujuan_id AS id_wallet, COALESCE(SUM(jumlah), 0) AS total_transfer_masuk
        FROM transfer_wallet
        WHERE user_id = ?
          AND status = 'selesai'
        GROUP BY wallet_tujuan_id
     ) AS transfer_masuk_wallet
        ON transfer_masuk_wallet.id_wallet = wallet.id_wallet
     LEFT JOIN (
        SELECT wallet_asal_id AS id_wallet, COALESCE(SUM(jumlah), 0) AS total_transfer_keluar
        FROM transfer_wallet
        WHERE user_id = ?
          AND status = 'selesai'
        GROUP BY wallet_asal_id
     ) AS transfer_keluar_wallet
        ON transfer_keluar_wallet.id_wallet = wallet.id_wallet
     LEFT JOIN (
        SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_celengan_setor
        FROM saving_goal_mutasi
        WHERE user_id = ?
          AND tipe = 'setor'
          AND id_wallet IS NOT NULL
        GROUP BY id_wallet
     ) AS celengan_setor_wallet
        ON celengan_setor_wallet.id_wallet = wallet.id_wallet
     LEFT JOIN (
        SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_celengan_tarik
        FROM saving_goal_mutasi
        WHERE user_id = ?
          AND tipe = 'tarik'
          AND id_wallet IS NOT NULL
        GROUP BY id_wallet
     ) AS celengan_tarik_wallet
        ON celengan_tarik_wallet.id_wallet = wallet.id_wallet
     WHERE wallet.user_id = ?
       AND wallet.is_active = 1
     ORDER BY wallet.is_default DESC, wallet.nama_wallet ASC",
    "iiiiiii",
    [$userYangSedangLogin, $userYangSedangLogin, $userYangSedangLogin, $userYangSedangLogin, $userYangSedangLogin, $userYangSedangLogin, $userYangSedangLogin]
);

$wallet_summary_items = [];
$wallet_total_saldo_aktif = 0;
$wallet_default_name = 'Belum ada default wallet aktif';

foreach ($wallet_summary_rows as $walletRow) {
    $walletSaldoAwal = (float) ($walletRow['saldo_awal'] ?? 0);
    $walletTotalPemasukan = (float) ($walletRow['total_pemasukan'] ?? 0);
    $walletTotalPengeluaran = (float) ($walletRow['total_pengeluaran'] ?? 0);
    $walletTotalTransferMasuk = (float) ($walletRow['total_transfer_masuk'] ?? 0);
    $walletTotalTransferKeluar = (float) ($walletRow['total_transfer_keluar'] ?? 0);
    $walletTotalCelenganSetor = (float) ($walletRow['total_celengan_setor'] ?? 0);
    $walletTotalCelenganTarik = (float) ($walletRow['total_celengan_tarik'] ?? 0);
    $walletSaldoAkhir = $walletSaldoAwal + $walletTotalPemasukan - $walletTotalPengeluaran + $walletTotalTransferMasuk - $walletTotalTransferKeluar - $walletTotalCelenganSetor + $walletTotalCelenganTarik;
    $walletIsDefault = (int) ($walletRow['is_default'] ?? 0) === 1;

    if ($walletIsDefault) {
        $wallet_default_name = (string) ($walletRow['nama_wallet'] ?? 'Dompet Utama');
    }

    $wallet_total_saldo_aktif += $walletSaldoAkhir;
    $wallet_summary_items[] = [
        'nama_wallet' => (string) ($walletRow['nama_wallet'] ?? '-'),
        'tipe_wallet' => (string) ($walletRow['tipe_wallet'] ?? 'lainnya'),
        'saldo_awal' => $walletSaldoAwal,
        'total_pemasukan' => $walletTotalPemasukan,
        'total_pengeluaran' => $walletTotalPengeluaran,
        'total_transfer_masuk' => $walletTotalTransferMasuk,
        'total_transfer_keluar' => $walletTotalTransferKeluar,
        'total_celengan_setor' => $walletTotalCelenganSetor,
        'total_celengan_tarik' => $walletTotalCelenganTarik,
        'saldo_akhir' => $walletSaldoAkhir,
        'is_default' => $walletIsDefault,
    ];
}

$wallet_aktif_count = count($wallet_summary_items);
$has_wallet_summary = $wallet_aktif_count > 0;

$quick_add_kategori_pemasukan = fetch_all_rows(
    $con,
    "SELECT id_kategori, nama_kategori
     FROM kategori
     WHERE user_id = ? AND tipe_kategori = 'pemasukan'
     ORDER BY nama_kategori ASC",
    "i",
    [$userYangSedangLogin]
);

$quick_add_kategori_pengeluaran = fetch_all_rows(
    $con,
    "SELECT id_kategori, nama_kategori
     FROM kategori
     WHERE user_id = ? AND tipe_kategori = 'pengeluaran'
     ORDER BY nama_kategori ASC",
    "i",
    [$userYangSedangLogin]
);

$quick_add_wallet_rows = fetch_all_rows(
    $con,
    "SELECT id_wallet, nama_wallet, tipe_wallet, is_default
     FROM wallet
     WHERE user_id = ? AND is_active = 1
     ORDER BY is_default DESC, nama_wallet ASC",
    "i",
    [$userYangSedangLogin]
);

$quick_add_celengan_rows = fetch_all_rows(
    $con,
    "SELECT id_goal, nama_goal
     FROM saving_goal
     WHERE user_id = ? AND status = 'aktif'
     ORDER BY updated_at DESC, id_goal DESC",
    "i",
    [$userYangSedangLogin]
);

$quick_add_default_wallet_id = 0;
foreach ($quick_add_wallet_rows as $walletRow) {
    if ((int) ($walletRow['is_default'] ?? 0) === 1) {
        $quick_add_default_wallet_id = (int) $walletRow['id_wallet'];
        break;
    }
}
if ($quick_add_default_wallet_id === 0 && !empty($quick_add_wallet_rows)) {
    $quick_add_default_wallet_id = (int) $quick_add_wallet_rows[0]['id_wallet'];
}

$quick_add_transfer_tujuan_default_id = 0;
foreach ($quick_add_wallet_rows as $walletRow) {
    $walletId = (int) $walletRow['id_wallet'];
    if ($walletId !== $quick_add_default_wallet_id) {
        $quick_add_transfer_tujuan_default_id = $walletId;
        break;
    }
}

$quick_add_has_wallet = count($quick_add_wallet_rows) > 0;
$quick_add_has_two_wallets = count($quick_add_wallet_rows) >= 2;
$quick_add_can_pemasukan = $quick_add_has_wallet && count($quick_add_kategori_pemasukan) > 0;
$quick_add_can_pengeluaran = $quick_add_has_wallet && count($quick_add_kategori_pengeluaran) > 0;
$quick_add_can_celengan = $quick_add_has_wallet && count($quick_add_celengan_rows) > 0;

$celengan_virtual_rows = fetch_all_rows(
    $con,
    "SELECT
        saving_goal.id_goal,
        saving_goal.nama_goal,
        saving_goal.target_nominal,
        saving_goal.target_tanggal,
        saving_goal.updated_at,
        COALESCE(mutasi.saldo_terkumpul, 0) AS saldo_terkumpul
     FROM saving_goal
     LEFT JOIN (
        SELECT
            id_goal,
            user_id,
            COALESCE(SUM(CASE
                WHEN tipe = 'setor' THEN jumlah
                WHEN tipe = 'tarik' THEN -jumlah
                ELSE 0
            END), 0) AS saldo_terkumpul
        FROM saving_goal_mutasi
        WHERE user_id = ?
        GROUP BY id_goal, user_id
     ) AS mutasi
        ON mutasi.id_goal = saving_goal.id_goal
       AND mutasi.user_id = saving_goal.user_id
     WHERE saving_goal.user_id = ?
       AND saving_goal.status = 'aktif'
     ORDER BY saving_goal.updated_at DESC, saving_goal.id_goal DESC",
    "ii",
    [$userYangSedangLogin, $userYangSedangLogin]
);

$celengan_virtual_items = [];
$celengan_virtual_total_count = 0;
$celengan_virtual_total_saldo = 0;
$celengan_virtual_total_target = 0;

foreach ($celengan_virtual_rows as $goalRow) {
    $targetNominal = (float) ($goalRow['target_nominal'] ?? 0);
    $saldoTerkumpul = (float) ($goalRow['saldo_terkumpul'] ?? 0);
    $progressRaw = $targetNominal > 0 ? ($saldoTerkumpul / $targetNominal) * 100 : 0;

    $celengan_virtual_total_count++;
    $celengan_virtual_total_saldo += $saldoTerkumpul;
    $celengan_virtual_total_target += $targetNominal;
    $celengan_virtual_items[] = [
        'id_goal' => (int) ($goalRow['id_goal'] ?? 0),
        'nama_goal' => (string) ($goalRow['nama_goal'] ?? '-'),
        'target_nominal' => $targetNominal,
        'saldo_terkumpul' => $saldoTerkumpul,
        'progress_raw' => $progressRaw,
        'progress_width' => min(100, max(0, $progressRaw)),
        'target_tercapai' => $targetNominal > 0 && $saldoTerkumpul >= $targetNominal,
        'updated_at' => (string) ($goalRow['updated_at'] ?? ''),
    ];
}

usort($celengan_virtual_items, function ($left, $right) {
    $progressCompare = ($right['progress_raw'] <=> $left['progress_raw']);
    if ($progressCompare !== 0) {
        return $progressCompare;
    }

    return strcmp((string) $right['updated_at'], (string) $left['updated_at']);
});

$celengan_virtual_top_items = array_slice($celengan_virtual_items, 0, 3);
$celengan_virtual_total_progress = $celengan_virtual_total_target > 0
    ? min(100, ($celengan_virtual_total_saldo / $celengan_virtual_total_target) * 100)
    : 0;
$has_celengan_virtual = $celengan_virtual_total_count > 0;

$budget_summary_rows = fetch_all_rows(
    $con,
    "SELECT
        budget_kategori.id_kategori,
        kategori.nama_kategori,
        budget_kategori.nominal_budget,
        COALESCE(pengeluaran_bulan.total_pengeluaran, 0) AS total_terpakai
     FROM budget_kategori
     INNER JOIN kategori
        ON kategori.id_kategori = budget_kategori.id_kategori
       AND kategori.user_id = budget_kategori.user_id
       AND kategori.tipe_kategori = 'pengeluaran'
     LEFT JOIN (
        SELECT id_kategori, COALESCE(SUM(jumlah), 0) AS total_pengeluaran
        FROM pengeluaran
        WHERE user = ?
          AND status = 'selesai'
          AND MONTH(tanggal) = ?
          AND YEAR(tanggal) = ?
        GROUP BY id_kategori
     ) AS pengeluaran_bulan
        ON pengeluaran_bulan.id_kategori = budget_kategori.id_kategori
     WHERE budget_kategori.user_id = ?
       AND budget_kategori.bulan = ?
       AND budget_kategori.tahun = ?
     ORDER BY kategori.nama_kategori ASC",
    "iiiiii",
    [$userYangSedangLogin, $bulansekarang, $tahunsekarang, $userYangSedangLogin, $bulansekarang, $tahunsekarang]
);

$total_budget_bulan_ini = 0;
$budget_terpakai_bulan_ini = 0;
$budget_aman_count = 0;
$budget_warning_count = 0;
$budget_over_count = 0;
$budget_attention_rows = [];

foreach ($budget_summary_rows as $budgetRow) {
    $budgetNominal = max(0, (float) ($budgetRow['nominal_budget'] ?? 0));
    $budgetTerpakai = max(0, (float) ($budgetRow['total_terpakai'] ?? 0));
    $budgetPersen = $budgetNominal > 0 ? ($budgetTerpakai / $budgetNominal) * 100 : 0;

    $total_budget_bulan_ini += $budgetNominal;
    $budget_terpakai_bulan_ini += $budgetTerpakai;

    if ($budgetNominal <= 0) {
        continue;
    }

    if ($budgetPersen >= 100) {
        $budget_over_count++;
        $budgetBadgeClass = 'bg-gradient-danger';
        $budgetLabel = 'Over Budget';
    } elseif ($budgetPersen >= 80) {
        $budget_warning_count++;
        $budgetBadgeClass = 'bg-gradient-warning';
        $budgetLabel = 'Warning';
    } else {
        $budget_aman_count++;
        $budgetBadgeClass = 'bg-gradient-success';
        $budgetLabel = 'Aman';
    }

    if ($budgetPersen >= 80) {
        $budget_attention_rows[] = [
            'nama_kategori' => (string) ($budgetRow['nama_kategori'] ?? 'Tanpa kategori'),
            'nominal_budget' => $budgetNominal,
            'total_terpakai' => $budgetTerpakai,
            'percentage' => $budgetPersen,
            'badge_class' => $budgetBadgeClass,
            'label' => $budgetLabel,
        ];
    }
}

usort($budget_attention_rows, function ($left, $right) {
    return ($right['percentage'] <=> $left['percentage'])
        ?: ($right['total_terpakai'] <=> $left['total_terpakai']);
});

$budget_attention_rows = array_slice($budget_attention_rows, 0, 5);
$has_budget_summary = count($budget_summary_rows) > 0;
$budget_sisa_bulan_ini = max(0, $total_budget_bulan_ini - $budget_terpakai_bulan_ini);
$budget_pemakaian_persen = $total_budget_bulan_ini > 0
    ? ($budget_terpakai_bulan_ini / $total_budget_bulan_ini) * 100
    : 0;
$budget_total_badge_class = $budget_pemakaian_persen >= 100
    ? 'bg-gradient-danger'
    : ($budget_pemakaian_persen >= 80 ? 'bg-gradient-warning' : 'bg-gradient-success');
$budget_total_icon_class = $budget_pemakaian_persen >= 100
    ? 'cashflow-icon-expense'
    : ($budget_pemakaian_persen >= 80 ? 'cashflow-icon-pending' : 'cashflow-icon-report');

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

$bulan_lalu = (new DateTimeImmutable('first day of this month'))->modify('-1 month');
$bulan_lalu_nomor = (int) $bulan_lalu->format('m');
$tahun_bulan_lalu = (int) $bulan_lalu->format('Y');

$insight_pemasukan_bulan_ini = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0)
     FROM pemasukan
     WHERE user = ? AND status = 'selesai'
       AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?",
    "iii",
    [$userYangSedangLogin, $bulansekarang, $tahunsekarang]
);

$insight_pengeluaran_bulan_ini = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0)
     FROM pengeluaran
     WHERE user = ? AND status = 'selesai'
       AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?",
    "iii",
    [$userYangSedangLogin, $bulansekarang, $tahunsekarang]
);

$insight_pengeluaran_bulan_lalu = (float) fetch_single_value(
    $con,
    "SELECT COALESCE(SUM(jumlah), 0)
     FROM pengeluaran
     WHERE user = ? AND status = 'selesai'
       AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?",
    "iii",
    [$userYangSedangLogin, $bulan_lalu_nomor, $tahun_bulan_lalu]
);

$insight_top_pengeluaran_rows = fetch_all_rows(
    $con,
    "SELECT
        COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS kategori_nama,
        COALESCE(SUM(pengeluaran.jumlah), 0) AS total_jumlah,
        COUNT(*) AS total_transaksi
     FROM pengeluaran
     LEFT JOIN kategori
        ON kategori.id_kategori = pengeluaran.id_kategori
       AND kategori.user_id = pengeluaran.user
       AND kategori.tipe_kategori = 'pengeluaran'
     WHERE pengeluaran.user = ?
       AND pengeluaran.status = 'selesai'
       AND MONTH(pengeluaran.tanggal) = ?
       AND YEAR(pengeluaran.tanggal) = ?
     GROUP BY COALESCE(kategori.nama_kategori, 'Belum dikategorikan')
     ORDER BY total_jumlah DESC, total_transaksi DESC
     LIMIT 1",
    "iii",
    [$userYangSedangLogin, $bulansekarang, $tahunsekarang]
);

$insight_top_pengeluaran = $insight_top_pengeluaran_rows[0] ?? null;
$insight_rata_harian = $insight_pengeluaran_bulan_ini / $hari_berjalan_bulan_ini;
$insight_rasio_pengeluaran = $insight_pemasukan_bulan_ini > 0
    ? ($insight_pengeluaran_bulan_ini / $insight_pemasukan_bulan_ini) * 100
    : null;
$has_monthly_insight = ($insight_pemasukan_bulan_ini + $insight_pengeluaran_bulan_ini + $insight_pengeluaran_bulan_lalu) > 0
    || $insight_top_pengeluaran !== null;

$insight_kategori_label = $insight_top_pengeluaran
    ? (string) ($insight_top_pengeluaran['kategori_nama'] ?? 'Belum dikategorikan')
    : 'Belum ada data';
$insight_kategori_total = $insight_top_pengeluaran
    ? (float) ($insight_top_pengeluaran['total_jumlah'] ?? 0)
    : 0;
$insight_kategori_transaksi = $insight_top_pengeluaran
    ? (int) ($insight_top_pengeluaran['total_transaksi'] ?? 0)
    : 0;

if (!$has_monthly_insight) {
    $insight_perbandingan_text = 'Belum cukup data untuk membandingkan pengeluaran bulan ini.';
    $insight_perbandingan_badge = 'bg-gradient-secondary';
} elseif ($insight_pengeluaran_bulan_lalu <= 0 && $insight_pengeluaran_bulan_ini > 0) {
    $insight_perbandingan_text = 'Pengeluaran bulan ini mulai tercatat; bulan lalu belum ada pengeluaran selesai.';
    $insight_perbandingan_badge = 'bg-gradient-warning';
} elseif ($insight_pengeluaran_bulan_lalu <= 0) {
    $insight_perbandingan_text = 'Pengeluaran bulan ini sama dengan bulan lalu.';
    $insight_perbandingan_badge = 'bg-gradient-success';
} else {
    $insight_selisih_pengeluaran = $insight_pengeluaran_bulan_ini - $insight_pengeluaran_bulan_lalu;
    $insight_persen_perubahan = abs($insight_selisih_pengeluaran / $insight_pengeluaran_bulan_lalu * 100);

    if (abs($insight_selisih_pengeluaran) < 0.01) {
        $insight_perbandingan_text = 'Pengeluaran bulan ini sama dengan bulan lalu.';
        $insight_perbandingan_badge = 'bg-gradient-success';
    } elseif ($insight_selisih_pengeluaran > 0) {
        $insight_perbandingan_text = 'Pengeluaran bulan ini naik ' . number_format((float) $insight_persen_perubahan, 1) . '% dibanding bulan lalu.';
        $insight_perbandingan_badge = 'bg-gradient-danger';
    } else {
        $insight_perbandingan_text = 'Pengeluaran bulan ini turun ' . number_format((float) $insight_persen_perubahan, 1) . '% dibanding bulan lalu.';
        $insight_perbandingan_badge = 'bg-gradient-success';
    }
}

$insight_kategori_sentence = $insight_top_pengeluaran
    ? 'Kategori paling boros bulan ini adalah ' . $insight_kategori_label . '.'
    : 'Belum ada kategori pengeluaran selesai bulan ini.';
$insight_rasio_sentence = $insight_rasio_pengeluaran !== null
    ? 'Rasio pengeluaran terhadap pemasukan bulan ini ' . number_format((float) $insight_rasio_pengeluaran, 1) . '%.'
    : 'Belum ada pemasukan selesai bulan ini, sehingga rasio belum bisa dihitung.';

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
                        <p class="text-sm mb-0 text-capitalize">Pemasukan Minggu Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) $pemasukan_minggu_ini) ?></h4>
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
                        <p class="text-sm mb-0 text-capitalize">Pengeluaran Minggu Ini</p>
                        <h4 class="mb-0">Rp. <?= number_format((float) $pengeluaran_minggu_ini) ?></h4>
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
                        <p class="text-sm mb-0 text-capitalize">Transaksi Minggu Ini</p>
                        <h4 class="mb-0"><?= number_format((float) $transaksi_minggu_ini) ?></h4>
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
                        <p class="text-sm mb-0 text-capitalize">Pemasukan Bulan Ini</p>
                        <h4 class="mb-0"><?= format_rupiah($pemasukan_bulan_ini) ?></h4>
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
                        <h4 class="mb-0"><?= format_rupiah($pengeluaran_bulan_ini) ?></h4>
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
                        class="icon icon-lg icon-shape <?= $cashflow_bulan_class ?> text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-balance-scale" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Sisa Cashflow Bulan Ini</p>
                        <h4 class="mb-0 <?= $sisa_cashflow_bulan < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= format_rupiah($sisa_cashflow_bulan) ?>
                        </h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary"><?= number_format((float) ($transaksi_bulan ?? 0)) ?> transaksi bulan ini.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape <?= $saldo_total_class ?> text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-money" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Total Saldo Keseluruhan</p>
                        <h4 class="mb-0 <?= $total_saldo_keseluruhan < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= format_rupiah($total_saldo_keseluruhan) ?>
                        </h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">Semua pemasukan dikurangi semua pengeluaran.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <div>
                        <h6 class="mb-0">Quick Add</h6>
                        <p class="text-sm text-secondary mb-0">Catat transaksi lebih cepat dari dashboard.</p>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-xl-0 mb-3">
                            <button type="button" class="btn w-100 border border-radius-lg p-3 text-start h-100 bg-white"
                                data-bs-toggle="modal" data-bs-target="#modalQuickAddPemasukan">
                                <span class="badge bg-gradient-success mb-2"><i class="fa fa-arrow-circle-down" aria-hidden="true"></i></span>
                                <h6 class="mb-1">Pemasukan</h6>
                                <p class="text-xs text-secondary mb-0">Catat dana masuk selesai.</p>
                            </button>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-xl-0 mb-3">
                            <button type="button" class="btn w-100 border border-radius-lg p-3 text-start h-100 bg-white"
                                data-bs-toggle="modal" data-bs-target="#modalQuickAddPengeluaran">
                                <span class="badge bg-gradient-danger mb-2"><i class="fa fa-arrow-circle-up" aria-hidden="true"></i></span>
                                <h6 class="mb-1">Pengeluaran</h6>
                                <p class="text-xs text-secondary mb-0">Catat dana keluar selesai.</p>
                            </button>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-md-0 mb-3">
                            <button type="button" class="btn w-100 border border-radius-lg p-3 text-start h-100 bg-white"
                                data-bs-toggle="modal" data-bs-target="#modalQuickAddTransfer">
                                <span class="badge bg-gradient-info mb-2"><i class="fa fa-exchange" aria-hidden="true"></i></span>
                                <h6 class="mb-1">Transfer Wallet</h6>
                                <p class="text-xs text-secondary mb-0">Pindahkan saldo antar wallet.</p>
                            </button>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <button type="button" class="btn w-100 border border-radius-lg p-3 text-start h-100 bg-white"
                                data-bs-toggle="modal" data-bs-target="#modalQuickAddSetorCelengan">
                                <span class="badge bg-gradient-primary mb-2"><i class="fa fa-bullseye" aria-hidden="true"></i></span>
                                <h6 class="mb-1">Setor Celengan</h6>
                                <p class="text-xs text-secondary mb-0">Tambah saldo Celengan Virtual.</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                        <div>
                            <h6 class="mb-0">Saldo Wallet</h6>
                            <p class="text-sm text-secondary mb-0">Saldo akhir dihitung dari saldo awal, pemasukan selesai, dan pengeluaran selesai.</p>
                        </div>
                        <span class="badge badge-sm <?= $has_wallet_summary ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                            <?= number_format((float) $wallet_aktif_count) ?> wallet aktif
                        </span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <?php if (!$has_wallet_summary) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-credit-card text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum ada wallet. Tambahkan wallet terlebih dahulu.</p>
                            <p class="text-xs text-secondary mb-0">Wallet aktif akan muncul di sini setelah dibuat lewat menu Wallet.</p>
                        </div>
                    <?php } else { ?>
                        <div class="row">
                            <div class="col-lg-4 col-sm-6 mb-lg-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Total Saldo Wallet Aktif</p>
                                    <h6 class="mb-0 <?= $wallet_total_saldo_aktif < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= format_rupiah($wallet_total_saldo_aktif) ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-6 mb-lg-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Jumlah Wallet Aktif</p>
                                    <h6 class="mb-0"><?= number_format((float) $wallet_aktif_count) ?> wallet</h6>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-12">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Wallet Default</p>
                                    <h6 class="mb-0"><?= htmlspecialchars($wallet_default_name, ENT_QUOTES, 'UTF-8') ?></h6>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Wallet</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tipe</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Saldo Awal</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Pemasukan</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Pengeluaran</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Transfer Masuk</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Transfer Keluar</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Setor Celengan</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tarik Celengan</th>
                                        <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Saldo Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wallet_summary_items as $walletRow) { ?>
                                        <tr>
                                            <td>
                                                <div class="py-2">
                                                    <p class="text-sm font-weight-bold mb-0"><?= htmlspecialchars($walletRow['nama_wallet'], ENT_QUOTES, 'UTF-8') ?></p>
                                                    <?php if ($walletRow['is_default']) { ?>
                                                        <span class="badge badge-sm bg-gradient-info">Default</span>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm <?= htmlspecialchars(dashboard_wallet_type_badge_class($walletRow['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars(dashboard_wallet_type_label($walletRow['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-secondary mb-0"><?= format_rupiah($walletRow['saldo_awal']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-success mb-0"><?= format_rupiah($walletRow['total_pemasukan']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-danger mb-0"><?= format_rupiah($walletRow['total_pengeluaran']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-success mb-0"><?= format_rupiah($walletRow['total_transfer_masuk']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-danger mb-0"><?= format_rupiah($walletRow['total_transfer_keluar']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-danger mb-0"><?= format_rupiah($walletRow['total_celengan_setor']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm text-success mb-0"><?= format_rupiah($walletRow['total_celengan_tarik']) ?></p>
                                            </td>
                                            <td class="align-middle text-end">
                                                <p class="text-sm font-weight-bold mb-0 <?= $walletRow['saldo_akhir'] < 0 ? 'text-danger' : 'text-success' ?>">
                                                    <?= format_rupiah($walletRow['saldo_akhir']) ?>
                                                </p>
                                            </td>
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

    <div class="row mt-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                        <div>
                            <h6 class="mb-0">Celengan Virtual</h6>
                            <p class="text-sm text-secondary mb-0">Progress celengan virtual aktif yang terhubung dengan saldo wallet.</p>
                        </div>
                        <a href="main.php?module=saving_goal" class="btn btn-sm btn-outline-info mb-0 align-self-start align-self-md-center">
                            Kelola Celengan
                        </a>
                    </div>
                </div>
                <div class="card-body p-3">
                    <?php if (!$has_celengan_virtual) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-bullseye text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum ada celengan aktif. Buat celengan virtual pertamamu.</p>
                            <a href="main.php?module=saving_goal" class="btn btn-sm btn-info mb-0 mt-2">Buat Celengan</a>
                        </div>
                    <?php } else { ?>
                        <div class="row">
                            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Celengan Aktif</p>
                                    <h6 class="mb-0"><?= number_format((float) $celengan_virtual_total_count) ?> celengan</h6>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-lg-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Saldo Terkumpul</p>
                                    <h6 class="mb-0 text-success"><?= format_rupiah($celengan_virtual_total_saldo) ?></h6>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-sm-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Total Target</p>
                                    <h6 class="mb-0"><?= format_rupiah($celengan_virtual_total_target) ?></h6>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Progress Total</p>
                                    <h6 class="mb-2"><?= number_format((float) $celengan_virtual_total_progress, 1) ?>%</h6>
                                    <div class="progress">
                                        <div class="progress-bar bg-gradient-info" role="progressbar"
                                            style="width: <?= htmlspecialchars((string) $celengan_virtual_total_progress, ENT_QUOTES, 'UTF-8') ?>%;"
                                            aria-valuenow="<?= htmlspecialchars((string) $celengan_virtual_total_progress, ENT_QUOTES, 'UTF-8') ?>"
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <?php foreach ($celengan_virtual_top_items as $goalItem) { ?>
                                <div class="col-lg-4 col-md-6 mb-lg-0 mb-3">
                                    <div class="border border-radius-lg p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <div>
                                                <p class="text-sm font-weight-bold mb-1"><?= htmlspecialchars($goalItem['nama_goal'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-secondary mb-0">
                                                    <?= format_rupiah($goalItem['saldo_terkumpul']) ?> dari <?= format_rupiah($goalItem['target_nominal']) ?>
                                                </p>
                                            </div>
                                            <?php if ($goalItem['target_tercapai']) { ?>
                                                <span class="badge badge-sm bg-gradient-success">Target Tercapai</span>
                                            <?php } ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-xs font-weight-bold"><?= number_format((float) $goalItem['progress_raw'], 1) ?>%</span>
                                            <div class="progress w-100">
                                                <div class="progress-bar bg-gradient-info" role="progressbar"
                                                    style="width: <?= htmlspecialchars((string) $goalItem['progress_width'], ENT_QUOTES, 'UTF-8') ?>%;"
                                                    aria-valuenow="<?= htmlspecialchars((string) $goalItem['progress_width'], ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                        <div>
                            <h6 class="mb-0">Tren Cashflow 6 Bulan Terakhir</h6>
                            <p class="text-sm text-secondary mb-0">Pemasukan, pengeluaran, dan net cashflow pribadi.</p>
                        </div>
                        <span class="badge badge-sm <?= $total_saldo_keseluruhan < 0 ? 'bg-gradient-danger' : 'bg-gradient-success' ?>">
                            Saldo: <?= format_rupiah($total_saldo_keseluruhan) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <?php if (!$has_cashflow_trend) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-line-chart text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum ada data cashflow untuk 6 bulan terakhir.</p>
                            <p class="text-xs text-secondary mb-0">Grafik akan muncul setelah Anda menambahkan pemasukan atau pengeluaran.</p>
                        </div>
                    <?php } else { ?>
                        <div class="chart" style="min-height: 320px;">
                            <canvas id="chart-cashflow-trend" class="chart-canvas" height="320"></canvas>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-expense text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-calendar" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Rata-rata Pengeluaran Harian</p>
                        <h4 class="mb-0"><?= format_rupiah($rata_pengeluaran_harian) ?></h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">Dihitung dari total pengeluaran bulan ini dibagi <?= number_format((float) $hari_berjalan_bulan_ini) ?> hari berjalan.</p>
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
                        <p class="text-sm mb-0 text-capitalize">Insight Pengeluaran Terbesar</p>
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
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header pb-0 p-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                        <div>
                            <h6 class="mb-0">Insight Bulan Ini</h6>
                            <p class="text-sm text-secondary mb-0">Ringkasan ringan berdasarkan transaksi selesai bulan berjalan.</p>
                        </div>
                        <span class="badge badge-sm <?= htmlspecialchars($insight_perbandingan_badge, ENT_QUOTES, 'UTF-8') ?>">
                            Transaksi selesai
                        </span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <?php if (!$has_monthly_insight) { ?>
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-lightbulb-o text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Belum cukup data untuk menampilkan insight bulan ini.</p>
                            <p class="text-xs text-secondary mb-0">Insight akan muncul setelah ada pemasukan atau pengeluaran berstatus selesai.</p>
                        </div>
                    <?php } else { ?>
                        <div class="row">
                            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Kategori Paling Boros</p>
                                    <h6 class="mb-1"><?= htmlspecialchars($insight_kategori_label, ENT_QUOTES, 'UTF-8') ?></h6>
                                    <p class="text-xs text-secondary mb-0">
                                        <?= format_rupiah($insight_kategori_total) ?> dari <?= number_format((float) $insight_kategori_transaksi) ?> transaksi.
                                    </p>
                                </div>
                            </div>
                            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Rata-rata Harian</p>
                                    <h6 class="mb-1"><?= format_rupiah($insight_rata_harian) ?></h6>
                                    <p class="text-xs text-secondary mb-0"><?= number_format((float) $hari_berjalan_bulan_ini) ?> hari berjalan bulan ini.</p>
                                </div>
                            </div>
                            <div class="col-xl-3 col-sm-6 mb-sm-0 mb-4">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Rasio Pengeluaran</p>
                                    <h6 class="mb-1">
                                        <?= $insight_rasio_pengeluaran !== null ? number_format((float) $insight_rasio_pengeluaran, 1) . '%' : '-' ?>
                                    </h6>
                                    <p class="text-xs text-secondary mb-0">Terhadap pemasukan selesai bulan ini.</p>
                                </div>
                            </div>
                            <div class="col-xl-3 col-sm-6">
                                <div class="border border-radius-lg p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Vs Bulan Lalu</p>
                                    <span class="badge badge-sm <?= htmlspecialchars($insight_perbandingan_badge, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= format_rupiah($insight_pengeluaran_bulan_ini) ?>
                                    </span>
                                    <p class="text-xs text-secondary mb-0 mt-2">Bulan lalu <?= format_rupiah($insight_pengeluaran_bulan_lalu) ?>.</p>
                                </div>
                            </div>
                        </div>
                        <div class="border border-radius-lg p-3 mt-3">
                            <p class="text-sm font-weight-bold mb-2">Catatan Insight</p>
                            <p class="text-sm text-secondary mb-1">
                                <i class="fa fa-pie-chart me-2" aria-hidden="true"></i><?= htmlspecialchars($insight_kategori_sentence, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="text-sm text-secondary mb-1">
                                <i class="fa fa-percent me-2" aria-hidden="true"></i><?= htmlspecialchars($insight_rasio_sentence, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <p class="text-sm text-secondary mb-0">
                                <i class="fa fa-exchange me-2" aria-hidden="true"></i><?= htmlspecialchars($insight_perbandingan_text, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$has_budget_summary) { ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <h6 class="mb-0">Budget Bulan Ini</h6>
                                <p class="text-sm text-secondary mb-0">Ringkasan budget kategori pengeluaran.</p>
                            </div>
                            <span class="badge badge-sm bg-gradient-secondary">Belum diatur</span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="border border-radius-lg p-4 text-center">
                            <i class="fa fa-money text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm text-secondary mb-1">Budget bulan ini belum diatur.</p>
                            <p class="text-xs text-secondary mb-0">Atur budget pada kategori pengeluaran lewat menu Kategori.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="row mt-4">
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card h-100 dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div
                            class="icon icon-lg icon-shape cashflow-icon-report text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-flag" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Total Budget Bulan Ini</p>
                            <h4 class="mb-0"><?= format_rupiah($total_budget_bulan_ini) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <p class="mb-0 text-sm text-secondary"><?= number_format((float) count($budget_summary_rows)) ?> kategori diatur.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card h-100 dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div
                            class="icon icon-lg icon-shape cashflow-icon-expense text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-shopping-cart" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Budget Terpakai</p>
                            <h4 class="mb-0"><?= format_rupiah($budget_terpakai_bulan_ini) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <p class="mb-0 text-sm text-secondary">Hanya pengeluaran selesai pada kategori berbudget.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                <div class="card h-100 dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div
                            class="icon icon-lg icon-shape cashflow-icon-income text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-shield" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Sisa Budget Total</p>
                            <h4 class="mb-0"><?= format_rupiah($budget_sisa_bulan_ini) ?></h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <p class="mb-0 text-sm text-secondary">Total budget dikurangi pemakaian bulan ini.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card h-100 dashboard-stat-card">
                    <div class="card-header p-3 pt-2">
                        <div
                            class="icon icon-lg icon-shape <?= htmlspecialchars($budget_total_icon_class, ENT_QUOTES, 'UTF-8') ?> text-center border-radius-xl mt-n4 position-absolute">
                            <i class="fa fa-tachometer" aria-hidden="true"></i>
                        </div>
                        <div class="text-end pt-1">
                            <p class="text-sm mb-0 text-capitalize">Pemakaian Budget</p>
                            <h4 class="mb-0"><?= number_format((float) $budget_pemakaian_persen, 1) ?>%</h4>
                        </div>
                    </div>
                    <hr class="dark horizontal my-0">
                    <div class="card-footer p-3">
                        <span class="badge badge-sm <?= htmlspecialchars($budget_total_badge_class, ENT_QUOTES, 'UTF-8') ?>">
                            <?= $budget_pemakaian_persen >= 100 ? 'Over Budget' : ($budget_pemakaian_persen >= 80 ? 'Warning' : 'Aman') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-4 mb-lg-0 mb-4">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <h6 class="mb-0">Status Budget</h6>
                        <p class="text-sm text-secondary mb-0">Kategori pengeluaran bulan berjalan</p>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="badge badge-sm bg-gradient-success">Aman</span>
                            <p class="text-sm font-weight-bold mb-0"><?= number_format((float) $budget_aman_count) ?> kategori</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="badge badge-sm bg-gradient-warning">Warning</span>
                            <p class="text-sm font-weight-bold mb-0"><?= number_format((float) $budget_warning_count) ?> kategori</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span class="badge badge-sm bg-gradient-danger">Over Budget</span>
                            <p class="text-sm font-weight-bold mb-0"><?= number_format((float) $budget_over_count) ?> kategori</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header pb-0 p-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                            <div>
                                <h6 class="mb-0">Kategori Hampir/Over Budget</h6>
                                <p class="text-sm text-secondary mb-0">Diurutkan berdasarkan persentase pemakaian tertinggi.</p>
                            </div>
                            <span class="badge badge-sm <?= htmlspecialchars($budget_total_badge_class, ENT_QUOTES, 'UTF-8') ?>">
                                <?= number_format((float) $budget_pemakaian_persen, 1) ?>% total
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($budget_attention_rows)) { ?>
                            <div class="border border-radius-lg p-4 text-center">
                                <i class="fa fa-check-circle text-success mb-2" aria-hidden="true"></i>
                                <p class="text-sm text-secondary mb-1">Belum ada kategori yang mencapai 80% budget.</p>
                                <p class="text-xs text-secondary mb-0">Kategori warning dan over budget akan muncul di sini.</p>
                            </div>
                        <?php } else { ?>
                            <?php foreach ($budget_attention_rows as $budgetRow) { ?>
                                <?php $budgetProgressWidth = min(100, (float) $budgetRow['percentage']); ?>
                                <div class="py-2 border-bottom">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                                        <div>
                                            <p class="text-sm font-weight-bold mb-0"><?= htmlspecialchars($budgetRow['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="text-xs text-secondary mb-0">
                                                <?= format_rupiah($budgetRow['total_terpakai']) ?> dari <?= format_rupiah($budgetRow['nominal_budget']) ?>
                                            </p>
                                        </div>
                                        <span class="badge badge-sm <?= htmlspecialchars($budgetRow['badge_class'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($budgetRow['label'], ENT_QUOTES, 'UTF-8') ?> - <?= number_format((float) $budgetRow['percentage'], 1) ?>%
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 0.45rem;">
                                        <div class="progress-bar <?= htmlspecialchars($budgetRow['badge_class'], ENT_QUOTES, 'UTF-8') ?>"
                                            role="progressbar"
                                            style="width: <?= htmlspecialchars((string) $budgetProgressWidth, ENT_QUOTES, 'UTF-8') ?>%;"
                                            aria-valuenow="<?= htmlspecialchars((string) round($budgetProgressWidth, 2), ENT_QUOTES, 'UTF-8') ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

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
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-debt text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Hutang Terlambat</p>
                        <h4 class="mb-0 text-danger"><?= number_format((float) $hutang_overdue_count) ?> item</h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">
                        Total pending <?= format_rupiah($hutang_overdue_total) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-receivable text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-bell" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Piutang Terlambat</p>
                        <h4 class="mb-0 text-danger"><?= number_format((float) $piutang_overdue_count) ?> item</h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">
                        Total pending <?= format_rupiah($piutang_overdue_total) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-12">
            <div class="card h-100 dashboard-stat-card">
                <div class="card-header p-3 pt-2">
                    <div
                        class="icon icon-lg icon-shape cashflow-icon-pending text-center border-radius-xl mt-n4 position-absolute">
                        <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                    </div>
                    <div class="text-end pt-1">
                        <p class="text-sm mb-0 text-capitalize">Jatuh Tempo Hari Ini</p>
                        <h4 class="mb-0 text-warning"><?= number_format((float) $due_today_total_count) ?> item</h4>
                    </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-3">
                    <p class="mb-0 text-sm text-secondary">
                        Hutang <?= number_format((float) $hutang_due_today_count) ?> item, piutang <?= number_format((float) $piutang_due_today_count) ?> item.
                    </p>
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
                <div class="table-responsive p-0 dashboard-latest-desktop">
                    <table class="table align-items-center mb-0 dashboard-latest-table dashboard-income-table">
                        <thead>
                            <tr>
                                <th
                                    class="col-status text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status</th>
                                <th
                                    class="col-date text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Tanggal</th>
                                <th class="col-note text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Catatan
                                </th>
                                <th class="col-category text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Kategori
                                </th>
                                <th class="col-amount text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Jumlah Pemasukan</th>
                                <th
                                    class="col-wallet text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Wallet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($q_pemasukan_terbaru as $row) { ?>
                            <tr>
                                <td class="col-status align-middle text-sm">
                                    <span
                                        class="badge badge-sm dashboard-status-badge <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
                                        <?php if ($row['status'] == 'selesai'): ?>
                                        <?= htmlspecialchars($row['status']) ?>
                                        <?php else : ?>
                                        <a href="" class="text-white">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </a>
                                        <?php endif ?>
                                    </span>
                                </td>
                                <td class="col-date align-middle">
                                    <span class="text-secondary text-xs font-weight-bold dashboard-date"><?= htmlspecialchars($row['tanggal']) ?></span>
                                </td>
                                <td class="col-note">
                                    <p class="text-xs text-secondary mb-0 dashboard-note"><?= htmlspecialchars($row['catatan']) ?></p>
                                </td>
                                <td class="col-category">
                                    <p class="text-xs text-secondary mb-0 dashboard-category"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="col-amount">
                                    <p class="text-xs font-weight-bold mb-0 dashboard-amount">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </p>
                                </td>
                                <td class="col-wallet">
                                    <p class="text-xs font-weight-bold mb-0 dashboard-wallet-name"><?= htmlspecialchars($row['nama_wallet'] ?? 'Dompet Utama', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-secondary mb-0 dashboard-wallet-type"><?= htmlspecialchars($row['tipe_wallet'] ? dashboard_wallet_type_label($row['tipe_wallet']) : 'Fallback', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="dashboard-latest-mobile">
                    <?php if (empty($q_pemasukan_terbaru)) { ?>
                        <div class="dashboard-transaction-empty">
                            Belum ada pemasukan terbaru.
                        </div>
                    <?php } else { ?>
                        <?php foreach ($q_pemasukan_terbaru as $row) { ?>
                            <?php
                            $statusPemasukan = (string) ($row['status'] ?? '-');
                            $walletTypePemasukan = !empty($row['tipe_wallet']) ? dashboard_wallet_type_label($row['tipe_wallet']) : 'Fallback';
                            ?>
                            <article class="dashboard-transaction-card dashboard-transaction-income">
                                <div class="dashboard-transaction-card-header">
                                    <span class="badge badge-sm dashboard-status-badge <?= ($statusPemasukan === 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
                                        <?= htmlspecialchars($statusPemasukan, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="dashboard-transaction-amount dashboard-transaction-amount-income">
                                        Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </span>
                                </div>
                                <div class="dashboard-transaction-meta">
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Tanggal</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['tanggal'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Kategori</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['nama_kategori'] ?? 'Tanpa Kategori'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Wallet</span>
                                        <span class="dashboard-transaction-value">
                                            <span class="dashboard-transaction-wallet-name"><?= htmlspecialchars((string) ($row['nama_wallet'] ?? 'Dompet Utama'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="dashboard-transaction-wallet-type"><?= htmlspecialchars($walletTypePemasukan, ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Catatan</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['catatan'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php } ?>
                    <?php } ?>
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
                <div class="table-responsive p-0 dashboard-latest-desktop">
                    <table class="table align-items-center mb-0 dashboard-latest-table dashboard-expense-table">
                        <thead>
                            <tr>
                                <th
                                    class="col-status text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status</th>
                                <th
                                    class="col-date text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Tanggal</th>
                                <th class="col-note text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Catatan
                                </th>
                                <th class="col-category text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Kategori
                                </th>
                                <th class="col-amount text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Jumlah Pengeluaran</th>
                                <th
                                    class="col-wallet text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Wallet</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($q_pengeluaran_terbaru as $row) { ?>
                            <tr>
                                <td class="col-status align-middle text-sm">
                                    <span
                                        class="badge badge-sm dashboard-status-badge <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
                                        <?php if ($row['status'] == 'selesai'): ?>
                                        <?= htmlspecialchars($row['status']) ?>
                                        <?php else : ?>
                                        <a href="" class="text-white">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </a>
                                        <?php endif ?>
                                    </span>
                                </td>
                                <td class="col-date align-middle">
                                    <span class="text-secondary text-xs font-weight-bold dashboard-date"><?= htmlspecialchars($row['tanggal']) ?></span>
                                </td>
                                <td class="col-note">
                                    <p class="text-xs text-secondary mb-0 dashboard-note"><?= htmlspecialchars($row['catatan']) ?></p>
                                </td>
                                <td class="col-category">
                                    <p class="text-xs text-secondary mb-0 dashboard-category"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                                <td class="col-amount">
                                    <p class="text-xs font-weight-bold mb-0 dashboard-amount">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </p>
                                </td>
                                <td class="col-wallet">
                                    <p class="text-xs font-weight-bold mb-0 dashboard-wallet-name"><?= htmlspecialchars($row['nama_wallet'] ?? 'Dompet Utama', ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-xs text-secondary mb-0 dashboard-wallet-type"><?= htmlspecialchars($row['tipe_wallet'] ? dashboard_wallet_type_label($row['tipe_wallet']) : 'Fallback', ENT_QUOTES, 'UTF-8') ?></p>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="dashboard-latest-mobile">
                    <?php if (empty($q_pengeluaran_terbaru)) { ?>
                        <div class="dashboard-transaction-empty">
                            Belum ada pengeluaran terbaru.
                        </div>
                    <?php } else { ?>
                        <?php foreach ($q_pengeluaran_terbaru as $row) { ?>
                            <?php
                            $statusPengeluaran = (string) ($row['status'] ?? '-');
                            $walletTypePengeluaran = !empty($row['tipe_wallet']) ? dashboard_wallet_type_label($row['tipe_wallet']) : 'Fallback';
                            ?>
                            <article class="dashboard-transaction-card dashboard-transaction-expense">
                                <div class="dashboard-transaction-card-header">
                                    <span class="badge badge-sm dashboard-status-badge <?= ($statusPengeluaran === 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
                                        <?= htmlspecialchars($statusPengeluaran, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="dashboard-transaction-amount dashboard-transaction-amount-expense">
                                        Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
                                    </span>
                                </div>
                                <div class="dashboard-transaction-meta">
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Tanggal</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['tanggal'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Kategori</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['nama_kategori'] ?? 'Tanpa Kategori'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Wallet</span>
                                        <span class="dashboard-transaction-value">
                                            <span class="dashboard-transaction-wallet-name"><?= htmlspecialchars((string) ($row['nama_wallet'] ?? 'Dompet Utama'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="dashboard-transaction-wallet-type"><?= htmlspecialchars($walletTypePengeluaran, ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </div>
                                    <div class="dashboard-transaction-meta-row">
                                        <span class="dashboard-transaction-label">Catatan</span>
                                        <span class="dashboard-transaction-value"><?= htmlspecialchars((string) ($row['catatan'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQuickAddPemasukan" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_pemasukan.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Quick Add Pemasukan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$quick_add_can_pemasukan) { ?>
                        <div class="alert alert-warning text-white" role="alert">
                            Pemasukan membutuhkan minimal satu kategori pemasukan dan satu wallet aktif.
                        </div>
                    <?php } ?>
                    <input type="hidden" name="user" value="<?= (int) $userYangSedangLogin ?>">
                    <input type="hidden" name="id_pemasukan" value="">
                    <input type="hidden" name="status" value="selesai">
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tglSekarang, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Kategori Pemasukan</label>
                        <div class="input-group input-group-outline">
                            <select name="id_kategori" class="form-control" required <?= empty($quick_add_kategori_pemasukan) ? 'disabled' : '' ?>>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($quick_add_kategori_pemasukan as $kategori) { ?>
                                    <option value="<?= (int) $kategori['id_kategori'] ?>">
                                        <?= htmlspecialchars($kategori['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Tujuan</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet" class="form-control" required <?= !$quick_add_has_wallet ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet</option>
                                <?php foreach ($quick_add_wallet_rows as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $quick_add_default_wallet_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(dashboard_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 1.000.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" <?= !$quick_add_can_pemasukan ? 'disabled' : '' ?>>Simpan Pemasukan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQuickAddPengeluaran" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_pengeluaran.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Quick Add Pengeluaran</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$quick_add_can_pengeluaran) { ?>
                        <div class="alert alert-warning text-white" role="alert">
                            Pengeluaran membutuhkan minimal satu kategori pengeluaran dan satu wallet aktif.
                        </div>
                    <?php } ?>
                    <input type="hidden" name="user" value="<?= (int) $userYangSedangLogin ?>">
                    <input type="hidden" name="id_pengeluaran" value="">
                    <input type="hidden" name="status" value="selesai">
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tglSekarang, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Kategori Pengeluaran</label>
                        <div class="input-group input-group-outline">
                            <select name="id_kategori" class="form-control" required <?= empty($quick_add_kategori_pengeluaran) ? 'disabled' : '' ?>>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($quick_add_kategori_pengeluaran as $kategori) { ?>
                                    <option value="<?= (int) $kategori['id_kategori'] ?>">
                                        <?= htmlspecialchars($kategori['nama_kategori'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Sumber</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet" class="form-control" required <?= !$quick_add_has_wallet ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet</option>
                                <?php foreach ($quick_add_wallet_rows as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $quick_add_default_wallet_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(dashboard_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 250.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger" <?= !$quick_add_can_pengeluaran ? 'disabled' : '' ?>>Simpan Pengeluaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQuickAddTransfer" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_transfer_wallet.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Quick Add Transfer Wallet</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$quick_add_has_two_wallets) { ?>
                        <div class="alert alert-warning text-white" role="alert">
                            Transfer membutuhkan minimal 2 wallet aktif.
                        </div>
                    <?php } ?>
                    <input type="hidden" name="id_transfer" value="">
                    <input type="hidden" name="status" value="selesai">
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tglSekarang, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Asal</label>
                        <div class="input-group input-group-outline">
                            <select name="wallet_asal_id" class="form-control" required <?= !$quick_add_has_two_wallets ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet Asal</option>
                                <?php foreach ($quick_add_wallet_rows as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $quick_add_default_wallet_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(dashboard_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Tujuan</label>
                        <div class="input-group input-group-outline">
                            <select name="wallet_tujuan_id" class="form-control" required <?= !$quick_add_has_two_wallets ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet Tujuan</option>
                                <?php foreach ($quick_add_wallet_rows as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $quick_add_transfer_tujuan_default_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(dashboard_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah Transfer</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 500.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info" <?= !$quick_add_has_two_wallets ? 'disabled' : '' ?>>Simpan Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQuickAddSetorCelengan" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_saving_goal.php?act=setor" method="post">
                <?= csrf_input() ?>
                <div class="modal-header">
                    <h6 class="modal-title">Quick Add Setor Celengan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$quick_add_has_wallet) { ?>
                        <div class="alert alert-warning text-white" role="alert">Belum ada wallet aktif.</div>
                    <?php } ?>
                    <?php if (empty($quick_add_celengan_rows)) { ?>
                        <div class="alert alert-warning text-white" role="alert">Belum ada celengan aktif.</div>
                    <?php } ?>
                    <div class="row">
                        <label class="form-label">Tanggal</label>
                        <div class="input-group input-group-outline">
                            <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tglSekarang, ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Celengan Virtual</label>
                        <div class="input-group input-group-outline">
                            <select name="id_goal" class="form-control" required <?= empty($quick_add_celengan_rows) ? 'disabled' : '' ?>>
                                <option value="">Pilih Celengan</option>
                                <?php foreach ($quick_add_celengan_rows as $goal) { ?>
                                    <option value="<?= (int) $goal['id_goal'] ?>">
                                        <?= htmlspecialchars($goal['nama_goal'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Wallet Sumber</label>
                        <div class="input-group input-group-outline">
                            <select name="id_wallet" class="form-control" required <?= !$quick_add_has_wallet ? 'disabled' : '' ?>>
                                <option value="">Pilih Wallet</option>
                                <?php foreach ($quick_add_wallet_rows as $wallet) { ?>
                                    <option value="<?= (int) $wallet['id_wallet'] ?>" <?= (int) $wallet['id_wallet'] === $quick_add_default_wallet_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars(dashboard_wallet_type_label($wallet['tipe_wallet']), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Jumlah Setor</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 500.000" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" <?= !$quick_add_can_celengan ? 'disabled' : '' ?>>Simpan Setor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var cashflowTrendCanvas = document.getElementById("chart-cashflow-trend");
    if (cashflowTrendCanvas) {
        new Chart(cashflowTrendCanvas.getContext("2d"), {
            type: "line",
            data: {
                labels: <?= json_encode($cashflow_trend['labels']) ?>,
                datasets: [{
                    label: "Pemasukan",
                    data: <?= json_encode($cashflow_trend['pemasukan']) ?>,
                    borderColor: "#22c55e",
                    backgroundColor: "rgba(34, 197, 94, 0.12)",
                    tension: 0.35,
                    fill: false
                }, {
                    label: "Pengeluaran",
                    data: <?= json_encode($cashflow_trend['pengeluaran']) ?>,
                    borderColor: "#ef4444",
                    backgroundColor: "rgba(239, 68, 68, 0.12)",
                    tension: 0.35,
                    fill: false
                }, {
                    label: "Net Cashflow",
                    data: <?= json_encode($cashflow_trend['net']) ?>,
                    borderColor: "#0ea5e9",
                    backgroundColor: "rgba(14, 165, 233, 0.12)",
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 16
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = Number(context.parsed.y || 0).toLocaleString("id-ID");
                                return context.dataset.label + ": Rp. " + value;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return "Rp. " + Number(value || 0).toLocaleString("id-ID");
                            }
                        }
                    }
                }
            }
        });
    }

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
