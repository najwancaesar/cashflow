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
$allowed_tables = ['pemasukan', 'pengeluaran', 'hutang', 'piutang'];
$output = $_POST['output'] ?? 'print';
$allowedOutputs = ['print', 'pdf', 'csv'];

if (!in_array($tabel, $allowed_tables, true)) {
    die("Tabel tidak valid");
}

if (!in_array($output, $allowedOutputs, true)) {
    $output = 'print';
}

$supportsKategori = in_array($tabel, ['pemasukan', 'pengeluaran'], true);
$supportsWallet = in_array($tabel, ['pemasukan', 'pengeluaran'], true);
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
$jenisLaporan = ucfirst($tabel);
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

    if ($supportsKategori && !empty($summaryRows)) {
        fputcsv($csvOutput, [], ';');
        fputcsv($csvOutput, ['Rekap Total Per Kategori'], ';');
        fputcsv($csvOutput, ['Kategori', 'Jumlah Transaksi', 'Total'], ';');

        foreach ($summaryRows as $row) {
            fputcsv($csvOutput, [
                $row['nama_kategori'] ?? 'Belum dikategorikan',
                (string) ((int) ($row['total_transaksi'] ?? 0)),
                (string) ((float) ($row['total_jumlah'] ?? 0)),
            ], ';');
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

        if ($supportsKategori && !empty($summaryRows)) {
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
    <title>Laporan <?= ucfirst($tabel) ?></title>
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
                    <p class="summary-label">Kategori</p>
                    <p class="summary-value"><?= htmlspecialchars($labelKategoriRingkas) ?></p>
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

    <?php if (empty($reportRows)) { ?>
        <div class="empty-state">
            <h4>Data laporan tidak ditemukan</h4>
            <p>Tidak ada transaksi <?= htmlspecialchars($tabel) ?> pada periode dan filter yang dipilih.</p>
        </div>
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

        <?php if ($supportsKategori) { ?>
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
