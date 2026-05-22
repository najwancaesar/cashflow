<?php
session_start();
include "../../includes/koneksi.php";

if (!isset($_SESSION['id_user'])) {
    die("Silakan login terlebih dahulu");
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    die("Admin tidak dapat mencetak laporan transaksi user.");
}

function normalize_report_date($value)
{
    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date->format('Y-m-d') === $value ? $value : null;
}

function parse_report_date_range($dateRange, $tanggalAwal = '', $tanggalAkhir = '')
{
    $tanggalAwalRaw = trim((string) $tanggalAwal);
    $tanggalAkhirRaw = trim((string) $tanggalAkhir);
    $tglAwal = normalize_report_date($tanggalAwal);
    $tglAkhir = normalize_report_date($tanggalAkhir);

    if ($tanggalAwalRaw !== '' || $tanggalAkhirRaw !== '') {
        if ($tglAwal === null || $tglAkhir === null) {
            return null;
        }

        return [$tglAwal, $tglAkhir];
    }

    $dates = explode(' - ', (string) $dateRange);
    if (count($dates) !== 2) {
        return null;
    }

    $tglAwal = normalize_report_date($dates[0]);
    $tglAkhir = normalize_report_date($dates[1]);
    if ($tglAwal === null || $tglAkhir === null) {
        return null;
    }

    return [$tglAwal, $tglAkhir];
}

function report_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function build_report_filename($table, $tglAwal, $tglAkhir, $extension)
{
    return sprintf(
        'cashflow-%s-%s-sampai-%s.%s',
        strtolower((string) $table),
        $tglAwal,
        $tglAkhir,
        $extension
    );
}

$id_user = (int) $_SESSION['id_user'];
$dateRange = parse_report_date_range(
    $_POST['tanggal'] ?? '',
    $_POST['tanggal_awal'] ?? '',
    $_POST['tanggal_akhir'] ?? ''
);

if ($dateRange === null) {
    die("Format tanggal tidak valid");
}

[$tgl_awal, $tgl_akhir] = $dateRange;

if ($tgl_awal > $tgl_akhir) {
    die("Tanggal awal tidak boleh lebih besar dari tanggal akhir");
}

$tabel = $_POST['tabel'] ?? 'pemasukan';
$allowed_tables = ['pemasukan', 'pengeluaran', 'hutang', 'piutang', 'transfer_wallet', 'saving_goal', 'gabungan'];
$output = $_POST['output'] ?? 'print';
$allowedOutputs = ['print', 'pdf', 'csv'];
$reportLabels = [
    'pemasukan' => 'Pemasukan',
    'pengeluaran' => 'Pengeluaran',
    'hutang' => 'Hutang',
    'piutang' => 'Piutang',
    'transfer_wallet' => 'Transfer Wallet',
    'saving_goal' => 'Celengan Virtual',
    'gabungan' => 'Gabungan',
];

if (!in_array($tabel, $allowed_tables, true)) {
    die("Tabel tidak valid");
}

if (!in_array($output, $allowedOutputs, true)) {
    $output = 'print';
}

$supportsKategori = in_array($tabel, ['pemasukan', 'pengeluaran'], true);
$supportsWallet = in_array($tabel, ['pemasukan', 'pengeluaran', 'transfer_wallet', 'saving_goal', 'gabungan'], true);
$isTransferReport = $tabel === 'transfer_wallet';
$isSavingGoalReport = $tabel === 'saving_goal';
$isCombinedReport = $tabel === 'gabungan';
$idKategori = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== '' ? (int) $_POST['id_kategori'] : null;
$idWallet = isset($_POST['id_wallet']) && $_POST['id_wallet'] !== '' ? (int) $_POST['id_wallet'] : null;
$selectedKategoriLabel = 'Semua kategori';
$selectedWalletLabel = 'Semua Wallet';

if (!$supportsKategori) {
    $idKategori = null;
}

if (!$supportsWallet) {
    $idWallet = null;
}

if ($supportsKategori && $idKategori !== null) {
    $kategoriQuery = "SELECT nama_kategori
                      FROM kategori
                      WHERE id_kategori = ? AND user_id = ? AND tipe_kategori = ?
                      LIMIT 1";
    $kategoriStmt = mysqli_prepare($con, $kategoriQuery);
    mysqli_stmt_bind_param($kategoriStmt, "iis", $idKategori, $id_user, $tabel);
    mysqli_stmt_execute($kategoriStmt);
    $kategoriResult = mysqli_stmt_get_result($kategoriStmt);
    $kategoriRow = mysqli_fetch_assoc($kategoriResult);
    mysqli_stmt_close($kategoriStmt);

    if (!$kategoriRow) {
        die("Kategori tidak valid");
    }

    $selectedKategoriLabel = $kategoriRow['nama_kategori'];
}

if ($supportsWallet && $idWallet !== null) {
    $walletQuery = "SELECT nama_wallet
                    FROM wallet
                    WHERE id_wallet = ? AND user_id = ?
                    LIMIT 1";
    $walletStmt = mysqli_prepare($con, $walletQuery);
    mysqli_stmt_bind_param($walletStmt, "ii", $idWallet, $id_user);
    mysqli_stmt_execute($walletStmt);
    $walletResult = mysqli_stmt_get_result($walletStmt);
    $walletRow = mysqli_fetch_assoc($walletResult);
    mysqli_stmt_close($walletStmt);

    if (!$walletRow) {
        die("Wallet tidak valid");
    }

    $selectedWalletLabel = $walletRow['nama_wallet'];
}

$queryUser = "SELECT * FROM user WHERE id_user = ?";
$stmtUser = mysqli_prepare($con, $queryUser);
mysqli_stmt_bind_param($stmtUser, "i", $id_user);
mysqli_stmt_execute($stmtUser);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUser));
mysqli_stmt_close($stmtUser);

$reportRows = [];
$summaryRows = [];
$combinedSummary = [
    'pemasukan' => ['label' => 'Total Pemasukan', 'total' => 0, 'count' => 0],
    'pengeluaran' => ['label' => 'Total Pengeluaran', 'total' => 0, 'count' => 0],
    'transfer' => ['label' => 'Total Transfer Wallet', 'total' => 0, 'count' => 0],
    'setor_celengan' => ['label' => 'Total Setor Celengan', 'total' => 0, 'count' => 0],
    'tarik_celengan' => ['label' => 'Total Tarik Celengan', 'total' => 0, 'count' => 0],
];
$total = 0;

if ($supportsKategori) {
    $mainQuery = "SELECT laporan.*, user.username,
                    COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS nama_kategori,
                    COALESCE(wallet.nama_wallet, 'Tanpa wallet') AS nama_wallet
                  FROM {$tabel} AS laporan
                  INNER JOIN user ON laporan.user = user.id_user
                  LEFT JOIN kategori
                    ON laporan.id_kategori = kategori.id_kategori
                   AND kategori.user_id = laporan.user
                   AND kategori.tipe_kategori = ?
                  LEFT JOIN wallet
                    ON laporan.id_wallet = wallet.id_wallet
                   AND wallet.user_id = laporan.user
                  WHERE laporan.user = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $mainTypes = "siss";
    $mainParams = [$tabel, $id_user, $tgl_awal, $tgl_akhir];

    if ($tabel === 'pemasukan') {
        $mainQuery .= " AND laporan.status = 'selesai'";
    }

    if ($idKategori !== null) {
        $mainQuery .= " AND laporan.id_kategori = ?";
        $mainTypes .= "i";
        $mainParams[] = $idKategori;
    }

    if ($idWallet !== null) {
        $mainQuery .= " AND laporan.id_wallet = ?";
        $mainTypes .= "i";
        $mainParams[] = $idWallet;
    }

    $mainQuery .= " ORDER BY laporan.tanggal ASC, laporan.id_{$tabel} ASC";

    $stmt = mysqli_prepare($con, $mainQuery);
    mysqli_stmt_bind_param($stmt, $mainTypes, ...$mainParams);

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reportRows[] = $row;
        $total += (float) ($row['jumlah'] ?? 0);
    }

    mysqli_stmt_close($stmt);

    $summaryQuery = "SELECT
                        COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS nama_kategori,
                        COALESCE(SUM(laporan.jumlah), 0) AS total_jumlah,
                        COUNT(*) AS total_transaksi
                    FROM {$tabel} AS laporan
                    LEFT JOIN kategori
                      ON laporan.id_kategori = kategori.id_kategori
                     AND kategori.user_id = laporan.user
                     AND kategori.tipe_kategori = ?
                    WHERE laporan.user = ?
                      AND laporan.tanggal BETWEEN ? AND ?";

    if ($tabel === 'pemasukan') {
        $summaryQuery .= " AND laporan.status = 'selesai'";
    }

    if ($idKategori !== null) {
        $summaryQuery .= " AND laporan.id_kategori = ?";
    }

    if ($idWallet !== null) {
        $summaryQuery .= " AND laporan.id_wallet = ?";
    }

    $summaryQuery .= " GROUP BY COALESCE(kategori.nama_kategori, 'Belum dikategorikan')
                       ORDER BY total_jumlah DESC, total_transaksi DESC";

    $stmtSummary = mysqli_prepare($con, $summaryQuery);
    $summaryTypes = "siss";
    $summaryParams = [$tabel, $id_user, $tgl_awal, $tgl_akhir];

    if ($idKategori !== null) {
        $summaryTypes .= "i";
        $summaryParams[] = $idKategori;
    }

    if ($idWallet !== null) {
        $summaryTypes .= "i";
        $summaryParams[] = $idWallet;
    }

    mysqli_stmt_bind_param($stmtSummary, $summaryTypes, ...$summaryParams);

    mysqli_stmt_execute($stmtSummary);
    $summaryResult = mysqli_stmt_get_result($stmtSummary);

    while ($row = mysqli_fetch_assoc($summaryResult)) {
        $summaryRows[] = $row;
    }

    mysqli_stmt_close($stmtSummary);
} elseif ($isTransferReport) {
    $mainQuery = "SELECT laporan.*,
                    COALESCE(wallet_asal.nama_wallet, 'Wallet tidak ditemukan') AS nama_wallet_asal,
                    COALESCE(wallet_tujuan.nama_wallet, 'Wallet tidak ditemukan') AS nama_wallet_tujuan
                  FROM transfer_wallet AS laporan
                  LEFT JOIN wallet AS wallet_asal
                    ON laporan.wallet_asal_id = wallet_asal.id_wallet
                   AND wallet_asal.user_id = laporan.user_id
                  LEFT JOIN wallet AS wallet_tujuan
                    ON laporan.wallet_tujuan_id = wallet_tujuan.id_wallet
                   AND wallet_tujuan.user_id = laporan.user_id
                  WHERE laporan.user_id = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $mainTypes = "iss";
    $mainParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $mainQuery .= " AND (laporan.wallet_asal_id = ? OR laporan.wallet_tujuan_id = ?)";
        $mainTypes .= "ii";
        $mainParams[] = $idWallet;
        $mainParams[] = $idWallet;
    }

    $mainQuery .= " ORDER BY laporan.tanggal ASC, laporan.id_transfer ASC";

    $stmt = mysqli_prepare($con, $mainQuery);
    mysqli_stmt_bind_param($stmt, $mainTypes, ...$mainParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reportRows[] = $row;
        $total += (float) ($row['jumlah'] ?? 0);
    }

    mysqli_stmt_close($stmt);

    $summaryQuery = "SELECT
                        laporan.status AS nama_rekap,
                        COUNT(*) AS total_transaksi,
                        COALESCE(SUM(laporan.jumlah), 0) AS total_jumlah
                    FROM transfer_wallet AS laporan
                    WHERE laporan.user_id = ?
                      AND laporan.tanggal BETWEEN ? AND ?";
    $summaryTypes = "iss";
    $summaryParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $summaryQuery .= " AND (laporan.wallet_asal_id = ? OR laporan.wallet_tujuan_id = ?)";
        $summaryTypes .= "ii";
        $summaryParams[] = $idWallet;
        $summaryParams[] = $idWallet;
    }

    $summaryQuery .= " GROUP BY laporan.status
                       ORDER BY total_jumlah DESC, total_transaksi DESC";

    $stmtSummary = mysqli_prepare($con, $summaryQuery);
    mysqli_stmt_bind_param($stmtSummary, $summaryTypes, ...$summaryParams);
    mysqli_stmt_execute($stmtSummary);
    $summaryResult = mysqli_stmt_get_result($stmtSummary);

    while ($row = mysqli_fetch_assoc($summaryResult)) {
        $summaryRows[] = $row;
    }

    mysqli_stmt_close($stmtSummary);
} elseif ($isSavingGoalReport) {
    $mainQuery = "SELECT laporan.*,
                    saving_goal.nama_goal,
                    COALESCE(wallet.nama_wallet, '-') AS nama_wallet
                  FROM saving_goal_mutasi AS laporan
                  INNER JOIN saving_goal
                    ON laporan.id_goal = saving_goal.id_goal
                   AND saving_goal.user_id = laporan.user_id
                  LEFT JOIN wallet
                    ON laporan.id_wallet = wallet.id_wallet
                   AND wallet.user_id = laporan.user_id
                  WHERE laporan.user_id = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $mainTypes = "iss";
    $mainParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $mainQuery .= " AND laporan.id_wallet = ?";
        $mainTypes .= "i";
        $mainParams[] = $idWallet;
    }

    $mainQuery .= " ORDER BY laporan.tanggal ASC, laporan.id_mutasi ASC";

    $stmt = mysqli_prepare($con, $mainQuery);
    mysqli_stmt_bind_param($stmt, $mainTypes, ...$mainParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reportRows[] = $row;
        $total += (float) ($row['jumlah'] ?? 0);
    }

    mysqli_stmt_close($stmt);

    $summaryQuery = "SELECT
                        saving_goal.nama_goal AS nama_rekap,
                        COUNT(*) AS total_transaksi,
                        COALESCE(SUM(CASE WHEN laporan.tipe = 'setor' THEN laporan.jumlah ELSE 0 END), 0) AS total_setor,
                        COALESCE(SUM(CASE WHEN laporan.tipe = 'tarik' THEN laporan.jumlah ELSE 0 END), 0) AS total_tarik,
                        COALESCE(SUM(CASE WHEN laporan.tipe = 'setor' THEN laporan.jumlah ELSE -laporan.jumlah END), 0) AS saldo_bersih
                    FROM saving_goal_mutasi AS laporan
                    INNER JOIN saving_goal
                      ON laporan.id_goal = saving_goal.id_goal
                     AND saving_goal.user_id = laporan.user_id
                    WHERE laporan.user_id = ?
                      AND laporan.tanggal BETWEEN ? AND ?";
    $summaryTypes = "iss";
    $summaryParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $summaryQuery .= " AND laporan.id_wallet = ?";
        $summaryTypes .= "i";
        $summaryParams[] = $idWallet;
    }

    $summaryQuery .= " GROUP BY saving_goal.id_goal, saving_goal.nama_goal
                       ORDER BY saldo_bersih DESC, total_transaksi DESC";

    $stmtSummary = mysqli_prepare($con, $summaryQuery);
    mysqli_stmt_bind_param($stmtSummary, $summaryTypes, ...$summaryParams);
    mysqli_stmt_execute($stmtSummary);
    $summaryResult = mysqli_stmt_get_result($stmtSummary);

    while ($row = mysqli_fetch_assoc($summaryResult)) {
        $summaryRows[] = $row;
    }

    mysqli_stmt_close($stmtSummary);
} elseif ($isCombinedReport) {
    $incomeQuery = "SELECT laporan.id_pemasukan AS id_ref,
                    laporan.tanggal,
                    laporan.jumlah,
                    laporan.status,
                    laporan.catatan,
                    COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS nama_kategori,
                    COALESCE(wallet.nama_wallet, 'Tanpa wallet') AS nama_wallet
                  FROM pemasukan AS laporan
                  LEFT JOIN kategori
                    ON laporan.id_kategori = kategori.id_kategori
                   AND kategori.user_id = laporan.user
                   AND kategori.tipe_kategori = 'pemasukan'
                  LEFT JOIN wallet
                    ON laporan.id_wallet = wallet.id_wallet
                   AND wallet.user_id = laporan.user
                  WHERE laporan.user = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $incomeTypes = "iss";
    $incomeParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $incomeQuery .= " AND laporan.id_wallet = ?";
        $incomeTypes .= "i";
        $incomeParams[] = $idWallet;
    }

    $incomeStmt = mysqli_prepare($con, $incomeQuery);
    mysqli_stmt_bind_param($incomeStmt, $incomeTypes, ...$incomeParams);
    mysqli_stmt_execute($incomeStmt);
    $incomeResult = mysqli_stmt_get_result($incomeStmt);

    while ($row = mysqli_fetch_assoc($incomeResult)) {
        $amount = (float) ($row['jumlah'] ?? 0);
        $reportRows[] = [
            'tanggal' => $row['tanggal'],
            'jenis_aktivitas' => 'Pemasukan',
            'wallet_sumber' => $row['nama_wallet'] ?? 'Tanpa wallet',
            'detail' => $row['nama_kategori'] ?? 'Belum dikategorikan',
            'nominal' => $amount,
            'status' => $row['status'] ?? '-',
            'catatan' => $row['catatan'] ?? '-',
            'sort_order' => 1,
            'id_ref' => (int) ($row['id_ref'] ?? 0),
        ];
        $combinedSummary['pemasukan']['total'] += $amount;
        $combinedSummary['pemasukan']['count']++;
        $total += $amount;
    }

    mysqli_stmt_close($incomeStmt);

    $expenseQuery = "SELECT laporan.id_pengeluaran AS id_ref,
                    laporan.tanggal,
                    laporan.jumlah,
                    laporan.status,
                    laporan.catatan,
                    COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS nama_kategori,
                    COALESCE(wallet.nama_wallet, 'Tanpa wallet') AS nama_wallet
                  FROM pengeluaran AS laporan
                  LEFT JOIN kategori
                    ON laporan.id_kategori = kategori.id_kategori
                   AND kategori.user_id = laporan.user
                   AND kategori.tipe_kategori = 'pengeluaran'
                  LEFT JOIN wallet
                    ON laporan.id_wallet = wallet.id_wallet
                   AND wallet.user_id = laporan.user
                  WHERE laporan.user = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $expenseTypes = "iss";
    $expenseParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $expenseQuery .= " AND laporan.id_wallet = ?";
        $expenseTypes .= "i";
        $expenseParams[] = $idWallet;
    }

    $expenseStmt = mysqli_prepare($con, $expenseQuery);
    mysqli_stmt_bind_param($expenseStmt, $expenseTypes, ...$expenseParams);
    mysqli_stmt_execute($expenseStmt);
    $expenseResult = mysqli_stmt_get_result($expenseStmt);

    while ($row = mysqli_fetch_assoc($expenseResult)) {
        $amount = (float) ($row['jumlah'] ?? 0);
        $reportRows[] = [
            'tanggal' => $row['tanggal'],
            'jenis_aktivitas' => 'Pengeluaran',
            'wallet_sumber' => $row['nama_wallet'] ?? 'Tanpa wallet',
            'detail' => $row['nama_kategori'] ?? 'Belum dikategorikan',
            'nominal' => $amount,
            'status' => $row['status'] ?? '-',
            'catatan' => $row['catatan'] ?? '-',
            'sort_order' => 2,
            'id_ref' => (int) ($row['id_ref'] ?? 0),
        ];
        $combinedSummary['pengeluaran']['total'] += $amount;
        $combinedSummary['pengeluaran']['count']++;
        $total += $amount;
    }

    mysqli_stmt_close($expenseStmt);

    $transferQuery = "SELECT laporan.id_transfer AS id_ref,
                    laporan.tanggal,
                    laporan.jumlah,
                    laporan.status,
                    laporan.catatan,
                    COALESCE(wallet_asal.nama_wallet, 'Wallet tidak ditemukan') AS nama_wallet_asal,
                    COALESCE(wallet_tujuan.nama_wallet, 'Wallet tidak ditemukan') AS nama_wallet_tujuan
                  FROM transfer_wallet AS laporan
                  LEFT JOIN wallet AS wallet_asal
                    ON laporan.wallet_asal_id = wallet_asal.id_wallet
                   AND wallet_asal.user_id = laporan.user_id
                  LEFT JOIN wallet AS wallet_tujuan
                    ON laporan.wallet_tujuan_id = wallet_tujuan.id_wallet
                   AND wallet_tujuan.user_id = laporan.user_id
                  WHERE laporan.user_id = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $transferTypes = "iss";
    $transferParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $transferQuery .= " AND (laporan.wallet_asal_id = ? OR laporan.wallet_tujuan_id = ?)";
        $transferTypes .= "ii";
        $transferParams[] = $idWallet;
        $transferParams[] = $idWallet;
    }

    $transferStmt = mysqli_prepare($con, $transferQuery);
    mysqli_stmt_bind_param($transferStmt, $transferTypes, ...$transferParams);
    mysqli_stmt_execute($transferStmt);
    $transferResult = mysqli_stmt_get_result($transferStmt);

    while ($row = mysqli_fetch_assoc($transferResult)) {
        $amount = (float) ($row['jumlah'] ?? 0);
        $walletAsal = $row['nama_wallet_asal'] ?? 'Wallet tidak ditemukan';
        $walletTujuan = $row['nama_wallet_tujuan'] ?? 'Wallet tidak ditemukan';
        $reportRows[] = [
            'tanggal' => $row['tanggal'],
            'jenis_aktivitas' => 'Transfer Wallet',
            'wallet_sumber' => $walletAsal,
            'detail' => 'Dari ' . $walletAsal . ' ke ' . $walletTujuan,
            'nominal' => $amount,
            'status' => $row['status'] ?? '-',
            'catatan' => $row['catatan'] ?? '-',
            'sort_order' => 3,
            'id_ref' => (int) ($row['id_ref'] ?? 0),
        ];
        $combinedSummary['transfer']['total'] += $amount;
        $combinedSummary['transfer']['count']++;
        $total += $amount;
    }

    mysqli_stmt_close($transferStmt);

    $savingQuery = "SELECT laporan.id_mutasi AS id_ref,
                    laporan.tanggal,
                    laporan.tipe,
                    laporan.jumlah,
                    laporan.catatan,
                    saving_goal.nama_goal,
                    COALESCE(wallet.nama_wallet, '-') AS nama_wallet
                  FROM saving_goal_mutasi AS laporan
                  INNER JOIN saving_goal
                    ON laporan.id_goal = saving_goal.id_goal
                   AND saving_goal.user_id = laporan.user_id
                  LEFT JOIN wallet
                    ON laporan.id_wallet = wallet.id_wallet
                   AND wallet.user_id = laporan.user_id
                  WHERE laporan.user_id = ?
                    AND laporan.tanggal BETWEEN ? AND ?";
    $savingTypes = "iss";
    $savingParams = [$id_user, $tgl_awal, $tgl_akhir];

    if ($idWallet !== null) {
        $savingQuery .= " AND laporan.id_wallet = ?";
        $savingTypes .= "i";
        $savingParams[] = $idWallet;
    }

    $savingStmt = mysqli_prepare($con, $savingQuery);
    mysqli_stmt_bind_param($savingStmt, $savingTypes, ...$savingParams);
    mysqli_stmt_execute($savingStmt);
    $savingResult = mysqli_stmt_get_result($savingStmt);

    while ($row = mysqli_fetch_assoc($savingResult)) {
        $amount = (float) ($row['jumlah'] ?? 0);
        $tipeMutasi = (string) ($row['tipe'] ?? '');
        $summaryKey = $tipeMutasi === 'tarik' ? 'tarik_celengan' : 'setor_celengan';
        $reportRows[] = [
            'tanggal' => $row['tanggal'],
            'jenis_aktivitas' => $tipeMutasi === 'tarik' ? 'Tarik Celengan' : 'Setor Celengan',
            'wallet_sumber' => $row['nama_wallet'] ?? '-',
            'detail' => $row['nama_goal'] ?? '-',
            'nominal' => $amount,
            'status' => '-',
            'catatan' => $row['catatan'] ?? '-',
            'sort_order' => $tipeMutasi === 'tarik' ? 5 : 4,
            'id_ref' => (int) ($row['id_ref'] ?? 0),
        ];
        $combinedSummary[$summaryKey]['total'] += $amount;
        $combinedSummary[$summaryKey]['count']++;
        $total += $amount;
    }

    mysqli_stmt_close($savingStmt);

    usort($reportRows, function ($left, $right) {
        $dateCompare = strcmp((string) ($left['tanggal'] ?? ''), (string) ($right['tanggal'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        $orderCompare = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
        if ($orderCompare !== 0) {
            return $orderCompare;
        }

        return ((int) ($left['id_ref'] ?? 0)) <=> ((int) ($right['id_ref'] ?? 0));
    });

    foreach ($combinedSummary as $summaryItem) {
        $summaryRows[] = [
            'nama_rekap' => $summaryItem['label'],
            'total_transaksi' => $summaryItem['count'],
            'total_jumlah' => $summaryItem['total'],
        ];
    }
} else {
    $mainQuery = "SELECT laporan.*, user.username
                  FROM {$tabel} AS laporan
                  INNER JOIN user ON laporan.user = user.id_user
                  WHERE laporan.user = ?
                    AND laporan.tanggal BETWEEN ? AND ?
                  ORDER BY laporan.tanggal ASC";
    $stmt = mysqli_prepare($con, $mainQuery);
    mysqli_stmt_bind_param($stmt, "iss", $id_user, $tgl_awal, $tgl_akhir);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $reportRows[] = $row;
        $total += (float) ($row['jumlah'] ?? 0);
    }

    mysqli_stmt_close($stmt);
}

$jumlahTransaksi = count($reportRows);
$tanggalCetak = date('d M Y H:i');
$jenisLaporan = $reportLabels[$tabel] ?? ucfirst($tabel);
$labelKategoriRingkas = $supportsKategori ? $selectedKategoriLabel : 'Tidak menggunakan kategori';
$labelWalletRingkas = $supportsWallet ? $selectedWalletLabel : 'Tidak menggunakan wallet';
$periodeLabel = date('d M Y', strtotime($tgl_awal)) . ' s/d ' . date('d M Y', strtotime($tgl_akhir));
$copyrightYear = date('Y');
$reportLogoPath = realpath(__DIR__ . '/../../assets/img/logocv.jpg');
$hasReportLogo = $reportLogoPath !== false && is_file($reportLogoPath);
$reportLogoPdfPath = $hasReportLogo ? str_replace('\\', '/', $reportLogoPath) : '';
$reportLogoWebPath = '../../assets/img/logocv.jpg';

if ($output === 'csv') {
    $filename = build_report_filename($tabel, $tgl_awal, $tgl_akhir, 'csv');

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $csvOutput = fopen('php://output', 'w');
    fprintf($csvOutput, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($csvOutput, ['CashFlow Control'], ';');
    fputcsv($csvOutput, ['Jenis Laporan', $jenisLaporan], ';');
    fputcsv($csvOutput, ['Periode', $periodeLabel], ';');
    fputcsv($csvOutput, ['Kategori', $labelKategoriRingkas], ';');
    fputcsv($csvOutput, ['Wallet', $labelWalletRingkas], ';');
    fputcsv($csvOutput, ['Jumlah Transaksi', (string) $jumlahTransaksi], ';');
    fputcsv($csvOutput, ['Total Nominal', (string) $total], ';');
    fputcsv($csvOutput, [], ';');

    if ($isCombinedReport) {
        fputcsv($csvOutput, ['tanggal', 'jenis_aktivitas', 'wallet_sumber', 'detail', 'nominal', 'status', 'catatan'], ';');

        foreach ($reportRows as $row) {
            fputcsv($csvOutput, [
                date('d M Y', strtotime($row['tanggal'])),
                $row['jenis_aktivitas'] ?? '-',
                $row['wallet_sumber'] ?? '-',
                $row['detail'] ?? '-',
                (string) ((float) ($row['nominal'] ?? 0)),
                $row['status'] ?? '-',
                trim((string) ($row['catatan'] ?? '-')),
            ], ';');
        }
    } elseif ($isTransferReport) {
        fputcsv($csvOutput, ['Tanggal', 'Wallet Asal', 'Wallet Tujuan', 'Jumlah', 'Status', 'Catatan'], ';');

        foreach ($reportRows as $row) {
            fputcsv($csvOutput, [
                date('d M Y', strtotime($row['tanggal'])),
                $row['nama_wallet_asal'] ?? 'Wallet tidak ditemukan',
                $row['nama_wallet_tujuan'] ?? 'Wallet tidak ditemukan',
                (string) ((float) ($row['jumlah'] ?? 0)),
                $row['status'] ?? '-',
                trim((string) ($row['catatan'] ?? '-')),
            ], ';');
        }
    } elseif ($isSavingGoalReport) {
        fputcsv($csvOutput, ['Tanggal', 'Celengan', 'Tipe', 'Wallet', 'Jumlah', 'Catatan'], ';');

        foreach ($reportRows as $row) {
            fputcsv($csvOutput, [
                date('d M Y', strtotime($row['tanggal'])),
                $row['nama_goal'] ?? '-',
                $row['tipe'] ?? '-',
                $row['nama_wallet'] ?? '-',
                (string) ((float) ($row['jumlah'] ?? 0)),
                trim((string) ($row['catatan'] ?? '-')),
            ], ';');
        }
    } else {
        $headers = ['No', 'Tanggal'];
        if ($supportsKategori) {
            $headers[] = 'Kategori';
        }
        if ($supportsWallet) {
            $headers[] = 'Wallet';
        }
        $headers[] = 'Jumlah';
        $headers[] = 'Catatan';
        if ($tabel === 'pemasukan') {
            $headers[] = 'Status';
        }

        fputcsv($csvOutput, $headers, ';');

        foreach ($reportRows as $index => $row) {
            $line = [
                $index + 1,
                date('d M Y', strtotime($row['tanggal'])),
            ];

            if ($supportsKategori) {
                $line[] = $row['nama_kategori'] ?? 'Belum dikategorikan';
            }
            if ($supportsWallet) {
                $line[] = $row['nama_wallet'] ?? 'Tanpa wallet';
            }

            $line[] = (string) ((float) ($row['jumlah'] ?? 0));
            $line[] = trim((string) ($row['catatan'] ?? '-'));

            if ($tabel === 'pemasukan') {
                $line[] = $row['status'] ?? '-';
            }

            fputcsv($csvOutput, $line, ';');
        }
    }

    if (!empty($summaryRows)) {
        fputcsv($csvOutput, [], ';');

        if ($isCombinedReport) {
            fputcsv($csvOutput, ['Ringkasan Laporan Gabungan'], ';');
            fputcsv($csvOutput, ['Aktivitas', 'Jumlah Transaksi', 'Total'], ';');

            foreach ($summaryRows as $row) {
                fputcsv($csvOutput, [
                    $row['nama_rekap'] ?? '-',
                    (string) ((int) ($row['total_transaksi'] ?? 0)),
                    (string) ((float) ($row['total_jumlah'] ?? 0)),
                ], ';');
            }
        } elseif ($supportsKategori) {
            fputcsv($csvOutput, ['Rekap Total Per Kategori'], ';');
            fputcsv($csvOutput, ['Kategori', 'Jumlah Transaksi', 'Total'], ';');

            foreach ($summaryRows as $row) {
                fputcsv($csvOutput, [
                    $row['nama_kategori'] ?? 'Belum dikategorikan',
                    (string) ((int) ($row['total_transaksi'] ?? 0)),
                    (string) ((float) ($row['total_jumlah'] ?? 0)),
                ], ';');
            }
        } elseif ($isTransferReport) {
            fputcsv($csvOutput, ['Rekap Transfer Per Status'], ';');
            fputcsv($csvOutput, ['Status', 'Jumlah Transaksi', 'Total'], ';');

            foreach ($summaryRows as $row) {
                fputcsv($csvOutput, [
                    $row['nama_rekap'] ?? '-',
                    (string) ((int) ($row['total_transaksi'] ?? 0)),
                    (string) ((float) ($row['total_jumlah'] ?? 0)),
                ], ';');
            }
        } elseif ($isSavingGoalReport) {
            fputcsv($csvOutput, ['Rekap Celengan Virtual'], ';');
            fputcsv($csvOutput, ['Celengan', 'Jumlah Transaksi', 'Total Setor', 'Total Tarik', 'Saldo Bersih'], ';');

            foreach ($summaryRows as $row) {
                fputcsv($csvOutput, [
                    $row['nama_rekap'] ?? '-',
                    (string) ((int) ($row['total_transaksi'] ?? 0)),
                    (string) ((float) ($row['total_setor'] ?? 0)),
                    (string) ((float) ($row['total_tarik'] ?? 0)),
                    (string) ((float) ($row['saldo_bersih'] ?? 0)),
                ], ';');
            }
        }
    }

    fclose($csvOutput);
    exit;
}

if ($output === 'pdf') {
    require_once(__DIR__ . '/tcpdf_include.php');

    if (!class_exists('CashFlowReportPdf')) {
        class CashFlowReportPdf extends TCPDF
        {
            public function Footer()
            {
                $this->SetY(-10);
                $this->SetFont('helvetica', '', 7.5);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(
                    0,
                    5,
                    'Copyright CashFlow Control ' . date('Y') . '. Laporan ini dicetak otomatis dari sistem CashFlow Control. Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(),
                    0,
                    false,
                    'C'
                );
            }
        }
    }

    $pdf = new CashFlowReportPdf('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('CashFlow Control');
    $pdf->SetAuthor('CashFlow Control');
    $pdf->SetTitle('Laporan ' . $jenisLaporan);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(11, 11, 11);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 8);

    $pdfMetaHtml = $supportsWallet
        ? '<table class="meta-table">
        <tr>
            <td width="18%" class="meta-label">Jenis laporan</td><td width="3%">:</td><td width="29%">' . report_escape($jenisLaporan) . '</td>
            <td width="14%" class="meta-label">Periode</td><td width="3%">:</td><td width="33%">' . report_escape($periodeLabel) . '</td>
        </tr>
        <tr>
            <td width="18%" class="meta-label">Kategori</td><td width="3%">:</td><td width="29%">' . report_escape($labelKategoriRingkas) . '</td>
            <td width="14%" class="meta-label">Wallet</td><td width="3%">:</td><td width="33%">' . report_escape($labelWalletRingkas) . '</td>
        </tr>
        <tr>
            <td width="18%" class="meta-label">Dicetak pada</td><td width="3%">:</td><td width="29%">' . report_escape($tanggalCetak) . '</td>
            <td width="14%" class="meta-label">Dicetak oleh</td><td width="3%">:</td><td width="33%">' . report_escape($user_data['nama'] ?? $user_data['username'] ?? '-') . ' (' . report_escape($user_data['username'] ?? '-') . ')</td>
        </tr>
    </table>'
        : '<table class="meta-table">
        <tr>
            <td width="18%" class="meta-label">Jenis laporan</td><td width="3%">:</td><td width="29%">' . report_escape($jenisLaporan) . '</td>
            <td width="14%" class="meta-label">Periode</td><td width="3%">:</td><td width="33%">' . report_escape($periodeLabel) . '</td>
        </tr>
        <tr>
            <td width="18%" class="meta-label">Kategori</td><td width="3%">:</td><td width="29%">' . report_escape($labelKategoriRingkas) . '</td>
            <td width="14%" class="meta-label">Dicetak pada</td><td width="3%">:</td><td width="33%">' . report_escape($tanggalCetak) . '</td>
        </tr>
        <tr>
            <td width="18%" class="meta-label">Dicetak oleh</td><td width="3%">:</td><td width="79%">' . report_escape($user_data['nama'] ?? $user_data['username'] ?? '-') . ' (' . report_escape($user_data['username'] ?? '-') . ')</td>
        </tr>
    </table>';


    if ($isCombinedReport) {
        $pdfSummaryHtml = '<table class="summary-table">
        <tr>
            <td width="20%"><span class="summary-label">Total Pemasukan</span><br>Rp ' . number_format((float) $combinedSummary['pemasukan']['total'], 0, ',', '.') . '</td>
            <td width="20%"><span class="summary-label">Total Pengeluaran</span><br>Rp ' . number_format((float) $combinedSummary['pengeluaran']['total'], 0, ',', '.') . '</td>
            <td width="20%"><span class="summary-label">Total Transfer</span><br>Rp ' . number_format((float) $combinedSummary['transfer']['total'], 0, ',', '.') . '</td>
            <td width="20%"><span class="summary-label">Setor Celengan</span><br>Rp ' . number_format((float) $combinedSummary['setor_celengan']['total'], 0, ',', '.') . '</td>
            <td width="20%"><span class="summary-label">Tarik Celengan</span><br>Rp ' . number_format((float) $combinedSummary['tarik_celengan']['total'], 0, ',', '.') . '</td>
        </tr>
    </table>';
    } else {
        $pdfSummaryHtml = $supportsWallet
            ? '<table class="summary-table">
        <tr>
            <td width="19%"><span class="summary-label">Periode Laporan</span><br>' . report_escape($periodeLabel) . '</td>
            <td width="14%"><span class="summary-label">Jenis Laporan</span><br>' . report_escape($jenisLaporan) . '</td>
            <td width="18%"><span class="summary-label">Kategori</span><br>' . report_escape($labelKategoriRingkas) . '</td>
            <td width="17%"><span class="summary-label">Wallet</span><br>' . report_escape($labelWalletRingkas) . '</td>
            <td width="14%"><span class="summary-label">Transaksi</span><br>' . number_format((float) $jumlahTransaksi, 0, ',', '.') . '</td>
            <td width="18%"><span class="summary-label">Total Nominal</span><br>Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
        </tr>
    </table>'
        : '<table class="summary-table">
        <tr>
            <td width="22%"><span class="summary-label">Periode Laporan</span><br>' . report_escape($periodeLabel) . '</td>
            <td width="17%"><span class="summary-label">Jenis Laporan</span><br>' . report_escape($jenisLaporan) . '</td>
            <td width="21%"><span class="summary-label">Kategori</span><br>' . report_escape($labelKategoriRingkas) . '</td>
            <td width="17%"><span class="summary-label">Jumlah Transaksi</span><br>' . number_format((float) $jumlahTransaksi, 0, ',', '.') . ' transaksi</td>
            <td width="23%"><span class="summary-label">Total Nominal</span><br>Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
        </tr>
    </table>';
    }

    $pdfHtml = '
    <style>
        h1, h2, h3, p { margin: 0; }
        table { border-collapse: collapse; width: 100%; font-size: 8px; }
        th, td { border: 0.45px solid #d1d5db; padding: 3px 4px; line-height: 1.25; }
        th { background-color: #eef2f7; color: #0f172a; font-weight: bold; }
        .brand-table td { border: none; padding: 0; vertical-align: top; }
        .brand-title { font-size: 15px; font-weight: bold; color: #0f172a; }
        .brand-subtitle { color: #0f172a; font-size: 9px; font-weight: bold; }
        .brand-note { color: #64748b; font-size: 8.2px; }
        .brand-date { color: #64748b; font-size: 8px; text-align: right; }
        .report-heading { font-size: 12px; font-weight: bold; color: #0f172a; }
        .rule { border-top: 1.2px solid #0ea5e9; height: 1px; }
        .meta-table td { border: none; padding: 1px 2px; font-size: 8px; line-height: 1.25; }
        .meta-label { color: #64748b; }
        .summary-table td { border: 0.45px solid #dbeafe; background-color: #f8fbff; padding: 5px 5px; font-size: 7.8px; line-height: 1.25; }
        .summary-label { color: #64748b; font-weight: bold; }
        .report-table th, .report-table td { font-size: 7.1px; padding: 2.5px 3px; line-height: 1.22; }
        .muted { color: #64748b; font-size: 8px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total-row td { font-weight: bold; background-color: #f9fafb; }
        .spacer { height: 5px; }
        .spacer-sm { height: 3px; }
    </style>
    <table class="brand-table"><tr>
        <td width="68%">
            <p class="brand-title">CASHFLOW CONTROL</p>
            <p class="brand-subtitle">Catatan keuangan pribadi</p>
            <p class="brand-note">Laporan transaksi pribadi yang dibuat otomatis dari sistem CashFlow Control</p>
        </td>
        <td width="32%" class="brand-date">
            Dicetak pada:<br>' . report_escape($tanggalCetak) . '
        </td>
    </tr></table>
    <div class="spacer-sm"></div>
    <div class="rule"></div>
    <div class="spacer"></div>
    <p class="report-heading">Laporan ' . report_escape($jenisLaporan) . '</p>
    <div class="spacer-sm"></div>
    ' . $pdfMetaHtml . '
    <div class="spacer"></div>
    ' . $pdfSummaryHtml . '
    <div class="spacer"></div>';

    if (empty($reportRows)) {
        $pdfHtml .= '<p>Tidak ada data laporan untuk filter yang dipilih.</p>';
    } else {
        if ($isCombinedReport) {
            $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                <th width="5%" class="center">No</th>
                <th width="11%">Tanggal</th>
                <th width="14%">Jenis</th>
                <th width="15%">Wallet/Sumber</th>
                <th width="18%">Detail</th>
                <th width="14%">Nominal</th>
                <th width="9%">Status</th>
                <th width="14%">Catatan</th>
            </tr></thead><tbody>';

            foreach ($reportRows as $index => $row) {
                $pdfHtml .= '<tr>
                    <td class="center">' . ($index + 1) . '</td>
                    <td>' . report_escape(date('d M Y', strtotime($row['tanggal']))) . '</td>
                    <td>' . report_escape($row['jenis_aktivitas'] ?? '-') . '</td>
                    <td>' . report_escape($row['wallet_sumber'] ?? '-') . '</td>
                    <td>' . report_escape($row['detail'] ?? '-') . '</td>
                    <td class="right">Rp ' . number_format((float) ($row['nominal'] ?? 0), 0, ',', '.') . '</td>
                    <td>' . report_escape($row['status'] ?? '-') . '</td>
                    <td>' . nl2br(report_escape($row['catatan'] ?? '-')) . '</td>
                </tr>';
            }

            $pdfHtml .= '</tbody></table>';
        } elseif ($isTransferReport) {
            $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                <th width="5%" class="center">No</th>
                <th width="12%">Tanggal</th>
                <th width="17%">Wallet Asal</th>
                <th width="17%">Wallet Tujuan</th>
                <th width="15%">Jumlah</th>
                <th width="10%">Status</th>
                <th width="24%">Catatan</th>
            </tr></thead><tbody>';

            foreach ($reportRows as $index => $row) {
                $pdfHtml .= '<tr>
                    <td class="center">' . ($index + 1) . '</td>
                    <td>' . report_escape(date('d M Y', strtotime($row['tanggal']))) . '</td>
                    <td>' . report_escape($row['nama_wallet_asal'] ?? 'Wallet tidak ditemukan') . '</td>
                    <td>' . report_escape($row['nama_wallet_tujuan'] ?? 'Wallet tidak ditemukan') . '</td>
                    <td class="right">Rp ' . number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') . '</td>
                    <td>' . report_escape($row['status'] ?? '-') . '</td>
                    <td>' . nl2br(report_escape($row['catatan'] ?? '-')) . '</td>
                </tr>';
            }

            $pdfHtml .= '<tr class="total-row">
                <td colspan="4">Total</td>
                <td class="right">Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
                <td colspan="2"></td>
            </tr></tbody></table>';
        } elseif ($isSavingGoalReport) {
            $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                <th width="5%" class="center">No</th>
                <th width="12%">Tanggal</th>
                <th width="20%">Celengan</th>
                <th width="10%">Tipe</th>
                <th width="16%">Wallet</th>
                <th width="15%">Jumlah</th>
                <th width="22%">Catatan</th>
            </tr></thead><tbody>';

            foreach ($reportRows as $index => $row) {
                $pdfHtml .= '<tr>
                    <td class="center">' . ($index + 1) . '</td>
                    <td>' . report_escape(date('d M Y', strtotime($row['tanggal']))) . '</td>
                    <td>' . report_escape($row['nama_goal'] ?? '-') . '</td>
                    <td>' . report_escape($row['tipe'] ?? '-') . '</td>
                    <td>' . report_escape($row['nama_wallet'] ?? '-') . '</td>
                    <td class="right">Rp ' . number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') . '</td>
                    <td>' . nl2br(report_escape($row['catatan'] ?? '-')) . '</td>
                </tr>';
            }

            $pdfHtml .= '<tr class="total-row">
                <td colspan="5">Total</td>
                <td class="right">Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
                <td></td>
            </tr></tbody></table>';
        } else {
            $pdfShowStatus = in_array($tabel, ['pemasukan', 'pengeluaran'], true);

            if ($supportsWallet && $supportsKategori) {
                $pdfWidths = ['no' => '5%', 'tanggal' => '12%', 'kategori' => '15%', 'wallet' => '15%', 'jumlah' => '16%', 'catatan' => '27%', 'status' => '10%'];
            } elseif ($supportsKategori) {
                $pdfWidths = ['no' => '5%', 'tanggal' => '13%', 'kategori' => '18%', 'wallet' => '0%', 'jumlah' => '17%', 'catatan' => '35%', 'status' => '12%'];
            } else {
                $pdfWidths = ['no' => '5%', 'tanggal' => '15%', 'kategori' => '0%', 'wallet' => '0%', 'jumlah' => '20%', 'catatan' => '60%', 'status' => '0%'];
            }

            $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                <th width="' . $pdfWidths['no'] . '" class="center">No</th>
                <th width="' . $pdfWidths['tanggal'] . '">Tanggal</th>';

            if ($supportsKategori) {
                $pdfHtml .= '<th width="' . $pdfWidths['kategori'] . '">Kategori</th>';
            }

            if ($supportsWallet) {
                $pdfHtml .= '<th width="' . $pdfWidths['wallet'] . '">Wallet</th>';
            }

            $pdfHtml .= '<th width="' . $pdfWidths['jumlah'] . '">Jumlah</th>
                <th width="' . $pdfWidths['catatan'] . '">Catatan</th>';

            if ($pdfShowStatus) {
                $pdfHtml .= '<th width="' . $pdfWidths['status'] . '">Status</th>';
            }

            $pdfHtml .= '</tr></thead><tbody>';

            foreach ($reportRows as $index => $row) {
                $pdfHtml .= '<tr>
                    <td class="center">' . ($index + 1) . '</td>
                    <td>' . report_escape(date('d M Y', strtotime($row['tanggal']))) . '</td>';

                if ($supportsKategori) {
                    $pdfHtml .= '<td>' . report_escape($row['nama_kategori'] ?? 'Belum dikategorikan') . '</td>';
                }

                if ($supportsWallet) {
                    $pdfHtml .= '<td>' . report_escape($row['nama_wallet'] ?? 'Tanpa wallet') . '</td>';
                }

                $pdfHtml .= '<td class="right">Rp ' . number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') . '</td>
                    <td>' . nl2br(report_escape($row['catatan'] ?? '-')) . '</td>';

                if ($pdfShowStatus) {
                    $pdfHtml .= '<td>' . report_escape($row['status'] ?? '-') . '</td>';
                }

                $pdfHtml .= '</tr>';
            }

            $totalLabelColspan = 2 + ($supportsKategori ? 1 : 0) + ($supportsWallet ? 1 : 0);
            $pdfHtml .= '<tr class="total-row">
                <td colspan="' . $totalLabelColspan . '">Total</td>
                <td class="right">Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
                <td colspan="' . (1 + ($pdfShowStatus ? 1 : 0)) . '"></td>
            </tr>';
            $pdfHtml .= '</tbody></table>';
        }

        if (!empty($summaryRows)) {
            if ($isCombinedReport) {
                $pdfHtml .= '<div class="spacer"></div><h3 style="font-size:10px;">Ringkasan Laporan Gabungan</h3><div class="spacer-sm"></div>';
                $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                    <th width="50%">Aktivitas</th>
                    <th width="20%">Jumlah Transaksi</th>
                    <th width="30%">Total</th>
                </tr></thead><tbody>';

                foreach ($summaryRows as $row) {
                    $pdfHtml .= '<tr>
                        <td>' . report_escape($row['nama_rekap'] ?? '-') . '</td>
                        <td class="center">' . number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') . '</td>
                    </tr>';
                }

                $pdfHtml .= '</tbody></table>';
            } elseif ($supportsKategori) {
                $pdfHtml .= '<div class="spacer"></div><h3 style="font-size:10px;">Rekap Total Per Kategori</h3><div class="spacer-sm"></div>';
                $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                    <th width="50%">Kategori</th>
                    <th width="20%">Jumlah Transaksi</th>
                    <th width="30%">Total</th>
                </tr></thead><tbody>';

                foreach ($summaryRows as $row) {
                    $pdfHtml .= '<tr>
                        <td>' . report_escape($row['nama_kategori'] ?? 'Belum dikategorikan') . '</td>
                        <td class="center">' . number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') . '</td>
                    </tr>';
                }

                $pdfHtml .= '</tbody></table>';
            } elseif ($isTransferReport) {
                $pdfHtml .= '<div class="spacer"></div><h3 style="font-size:10px;">Rekap Transfer Per Status</h3><div class="spacer-sm"></div>';
                $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                    <th width="50%">Status</th>
                    <th width="20%">Jumlah Transaksi</th>
                    <th width="30%">Total</th>
                </tr></thead><tbody>';

                foreach ($summaryRows as $row) {
                    $pdfHtml .= '<tr>
                        <td>' . report_escape($row['nama_rekap'] ?? '-') . '</td>
                        <td class="center">' . number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') . '</td>
                    </tr>';
                }

                $pdfHtml .= '</tbody></table>';
            } elseif ($isSavingGoalReport) {
                $pdfHtml .= '<div class="spacer"></div><h3 style="font-size:10px;">Rekap Celengan Virtual</h3><div class="spacer-sm"></div>';
                $pdfHtml .= '<table class="report-table" cellpadding="3"><thead><tr>
                    <th width="28%">Celengan</th>
                    <th width="14%">Transaksi</th>
                    <th width="19%">Total Setor</th>
                    <th width="19%">Total Tarik</th>
                    <th width="20%">Saldo Bersih</th>
                </tr></thead><tbody>';

                foreach ($summaryRows as $row) {
                    $pdfHtml .= '<tr>
                        <td>' . report_escape($row['nama_rekap'] ?? '-') . '</td>
                        <td class="center">' . number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['total_setor'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['total_tarik'] ?? 0), 0, ',', '.') . '</td>
                        <td class="right">Rp ' . number_format((float) ($row['saldo_bersih'] ?? 0), 0, ',', '.') . '</td>
                    </tr>';
                }

                $pdfHtml .= '</tbody></table>';
            }
        }
    }

    $pdf->writeHTML($pdfHtml, true, false, true, false, '');
    $pdf->Output(build_report_filename($tabel, $tgl_awal, $tgl_akhir, 'pdf'), 'D');
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Laporan <?= htmlspecialchars($jenisLaporan) ?></title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        color: #1f2937;
        background: #ffffff;
    }

    .header {
        margin-bottom: 24px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 16px;
    }

    .brand-row {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .brand-logo {
        width: 54px;
        height: 54px;
        border-radius: 12px;
        object-fit: cover;
    }

    .brand-title {
        margin: 0 0 4px;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .brand-subtitle {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }

    .report-title {
        margin: 18px 0 8px;
        font-size: 22px;
    }

    .header-meta {
        display: table;
        width: 100%;
        margin-top: 16px;
    }

    .header-meta-row {
        display: table-row;
    }

    .header-meta-label,
    .header-meta-value {
        display: table-cell;
        padding: 4px 0;
        font-size: 13px;
        vertical-align: top;
    }

    .header-meta-label {
        width: 140px;
        color: #64748b;
    }

    .summary-grid {
        width: 100%;
        margin-bottom: 22px;
        border-collapse: separate;
        border-spacing: 12px 0;
        margin-left: -12px;
        margin-right: -12px;
    }

    .summary-grid td {
        border: 0;
        padding: 0;
        width: 20%;
    }

    .summary-box {
        border: 1px solid #dbeafe;
        background: #f8fbff;
        border-radius: 12px;
        padding: 14px 16px;
        min-height: 88px;
    }

    .summary-label {
        margin: 0 0 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }

    .summary-value {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.4;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    th,
    td {
        border: 1px solid #d1d5db;
        padding: 8px;
        text-align: left;
        vertical-align: top;
    }

    th {
        background-color: #f3f4f6;
    }

    .total-row {
        font-weight: bold;
        background-color: #f9fafb;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        padding: 20px;
        text-align: center;
        margin-bottom: 20px;
        background: #f8fafc;
        border-radius: 12px;
    }

    .summary-title {
        margin-top: 24px;
        margin-bottom: 12px;
    }

    .report-footer {
        margin-top: 28px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
        color: #64748b;
        font-size: 12px;
        text-align: center;
    }

    .no-print button {
        padding: 10px 16px;
        margin-right: 8px;
        border: 0;
        cursor: pointer;
        border-radius: 8px;
        font-weight: 600;
    }

    .print-btn {
        background: #0ea5e9;
        color: #ffffff;
    }

    .close-btn {
        background: #e2e8f0;
        color: #0f172a;
    }

    @media print {
        .no-print {
            display: none;
        }

        body {
            padding: 0;
        }

        .summary-grid {
            border-spacing: 8px 0;
        }
    }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand-row">
            <?php if ($hasReportLogo) { ?>
                <img src="<?= htmlspecialchars($reportLogoWebPath, ENT_QUOTES, 'UTF-8') ?>" alt="CashFlow Control" class="brand-logo">
            <?php } ?>
            <div>
                <p class="brand-title">CASHFLOW CONTROL</p>
                <p class="brand-subtitle">Laporan transaksi pribadi yang dicetak langsung dari sistem internal</p>
            </div>
        </div>
        <h2 class="report-title">Laporan <?= htmlspecialchars($jenisLaporan) ?></h2>

        <div class="header-meta">
            <div class="header-meta-row">
                <div class="header-meta-label">Jenis laporan</div>
                <div class="header-meta-value">: <?= htmlspecialchars($jenisLaporan) ?></div>
            </div>
            <div class="header-meta-row">
                <div class="header-meta-label">Periode</div>
                <div class="header-meta-value">: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></div>
            </div>
            <div class="header-meta-row">
                <div class="header-meta-label">Kategori</div>
                <div class="header-meta-value">: <?= htmlspecialchars($labelKategoriRingkas) ?></div>
            </div>
            <div class="header-meta-row">
                <div class="header-meta-label">Wallet</div>
                <div class="header-meta-value">: <?= htmlspecialchars($labelWalletRingkas) ?></div>
            </div>
            <div class="header-meta-row">
                <div class="header-meta-label">Dicetak pada</div>
                <div class="header-meta-value">: <?= htmlspecialchars($tanggalCetak) ?></div>
            </div>
            <div class="header-meta-row">
                <div class="header-meta-label">Dicetak oleh</div>
                <div class="header-meta-value">: <?= htmlspecialchars($user_data['nama'] ?? $user_data['username'] ?? '-') ?> (<?= htmlspecialchars($user_data['username'] ?? '-') ?>)</div>
            </div>
        </div>
    </div>

    <?php if ($isCombinedReport) { ?>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Total Pemasukan</p>
                        <p class="summary-value">Rp <?= number_format((float) $combinedSummary['pemasukan']['total'], 0, ',', '.') ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Total Pengeluaran</p>
                        <p class="summary-value">Rp <?= number_format((float) $combinedSummary['pengeluaran']['total'], 0, ',', '.') ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Total Transfer</p>
                        <p class="summary-value">Rp <?= number_format((float) $combinedSummary['transfer']['total'], 0, ',', '.') ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Setor Celengan</p>
                        <p class="summary-value">Rp <?= number_format((float) $combinedSummary['setor_celengan']['total'], 0, ',', '.') ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Tarik Celengan</p>
                        <p class="summary-value">Rp <?= number_format((float) $combinedSummary['tarik_celengan']['total'], 0, ',', '.') ?></p>
                    </div>
                </td>
            </tr>
        </table>
    <?php } else { ?>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Periode Laporan</p>
                        <p class="summary-value"><?= date('d M Y', strtotime($tgl_awal)) ?><br>s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Jenis Laporan</p>
                        <p class="summary-value"><?= htmlspecialchars($jenisLaporan) ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label"><?= $supportsKategori ? 'Kategori' : ($supportsWallet ? 'Wallet' : 'Kategori') ?></p>
                        <p class="summary-value"><?= htmlspecialchars($supportsKategori ? $labelKategoriRingkas : ($supportsWallet ? $labelWalletRingkas : $labelKategoriRingkas)) ?></p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Jumlah Transaksi</p>
                        <p class="summary-value"><?= number_format((float) $jumlahTransaksi, 0, ',', '.') ?> transaksi</p>
                    </div>
                </td>
                <td>
                    <div class="summary-box">
                        <p class="summary-label">Total Nominal</p>
                        <p class="summary-value">Rp <?= number_format((float) $total, 0, ',', '.') ?></p>
                    </div>
                </td>
            </tr>
        </table>
    <?php } ?>

    <?php if (empty($reportRows)) { ?>
        <div class="empty-state">
            <h4>Data laporan tidak ditemukan</h4>
            <p>Tidak ada data <?= htmlspecialchars($jenisLaporan) ?> pada periode dan filter yang dipilih.</p>
        </div>
    <?php } else { ?>
        <?php if ($isCombinedReport) { ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Jenis Aktivitas</th>
                        <th>Wallet/Sumber</th>
                        <th>Detail</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $index => $row) { ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['jenis_aktivitas'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['wallet_sumber'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['detail'] ?? '-') ?></td>
                            <td align="right">Rp <?= number_format((float) ($row['nominal'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } elseif ($isTransferReport) { ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Wallet Asal</th>
                        <th>Wallet Tujuan</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $index => $row) { ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_wallet_asal'] ?? 'Wallet tidak ditemukan') ?></td>
                            <td><?= htmlspecialchars($row['nama_wallet_tujuan'] ?? 'Wallet tidak ditemukan') ?></td>
                            <td align="right">Rp <?= number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="total-row">
                        <td colspan="4">Total</td>
                        <td align="right">Rp <?= number_format((float) $total, 0, ',', '.') ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        <?php } elseif ($isSavingGoalReport) { ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Celengan</th>
                        <th>Tipe</th>
                        <th>Wallet</th>
                        <th>Jumlah</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $index => $row) { ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($row['nama_goal'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['tipe'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['nama_wallet'] ?? '-') ?></td>
                            <td align="right">Rp <?= number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="total-row">
                        <td colspan="5">Total</td>
                        <td align="right">Rp <?= number_format((float) $total, 0, ',', '.') ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <?php if ($supportsKategori) { ?>
                            <th>Kategori</th>
                        <?php } ?>
                        <?php if ($supportsWallet) { ?>
                            <th>Wallet</th>
                        <?php } ?>
                        <th>Jumlah</th>
                        <th>Catatan</th>
                        <?php if ($tabel == 'pemasukan'): ?>
                            <th>Status</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportRows as $index => $row) { ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                            <?php if ($supportsKategori) { ?>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?></td>
                            <?php } ?>
                            <?php if ($supportsWallet) { ?>
                                <td><?= htmlspecialchars($row['nama_wallet'] ?? 'Tanpa wallet') ?></td>
                            <?php } ?>
                            <td align="right">Rp <?= number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                            <?php if ($tabel == 'pemasukan'): ?>
                                <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php } ?>
                    <tr class="total-row">
                        <td colspan="<?= 2 + ($supportsKategori ? 1 : 0) + ($supportsWallet ? 1 : 0) ?>">Total</td>
                        <td align="right">Rp <?= number_format((float) $total, 0, ',', '.') ?></td>
                        <td colspan="<?= ($supportsKategori ? ($tabel == 'pemasukan' ? '2' : '1') : ($tabel == 'pemasukan' ? '2' : '1')) ?>"></td>
                    </tr>
                </tbody>
            </table>
        <?php } ?>

        <?php if (!empty($summaryRows)) { ?>
            <?php if ($isCombinedReport) { ?>
                <h4 class="summary-title">Ringkasan Laporan Gabungan</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Aktivitas</th>
                            <th>Jumlah Transaksi</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $row) { ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_rekap'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } elseif ($supportsKategori) { ?>
                <h4 class="summary-title">Rekap Total Per Kategori</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jumlah Transaksi</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $row) { ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?></td>
                                <td><?= number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } elseif ($isTransferReport) { ?>
                <h4 class="summary-title">Rekap Transfer Per Status</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Jumlah Transaksi</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $row) { ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_rekap'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['total_jumlah'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } elseif ($isSavingGoalReport) { ?>
                <h4 class="summary-title">Rekap Celengan Virtual</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Celengan</th>
                            <th>Jumlah Transaksi</th>
                            <th>Total Setor</th>
                            <th>Total Tarik</th>
                            <th>Saldo Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $row) { ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_rekap'] ?? '-') ?></td>
                                <td><?= number_format((float) ($row['total_transaksi'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['total_setor'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['total_tarik'] ?? 0), 0, ',', '.') ?></td>
                                <td align="right">Rp <?= number_format((float) ($row['saldo_bersih'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        <?php } ?>
    <?php } ?>

    <div class="report-footer">
        © CashFlow Control <?= htmlspecialchars($copyrightYear) ?>. Laporan ini dicetak otomatis dari sistem CashFlow Control.
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="print-btn">Cetak Laporan</button>
        <button onclick="window.close()" class="close-btn">Tutup</button>
    </div>
</body>

</html>
