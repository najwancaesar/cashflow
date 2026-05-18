<?php
session_start();
include "../../includes/koneksi.php";

if (!isset($_SESSION['id_user'])) {
    die("Silakan login terlebih dahulu");
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    die("Admin tidak dapat mencetak laporan transaksi user.");
}

function parse_report_date_range($dateRange)
{
    $dates = explode(' - ', (string) $dateRange);
    if (count($dates) !== 2) {
        return null;
    }

    $timestampAwal = strtotime(trim($dates[0]));
    $timestampAkhir = strtotime(trim($dates[1]));

    if ($timestampAwal === false || $timestampAkhir === false) {
        return null;
    }

    $tglAwal = date('Y-m-d', $timestampAwal);
    $tglAkhir = date('Y-m-d', $timestampAkhir);

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
$dateRange = parse_report_date_range($_POST['tanggal'] ?? '');

if ($dateRange === null) {
    die("Format tanggal tidak valid");
}

[$tgl_awal, $tgl_akhir] = $dateRange;

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
$idKategori = isset($_POST['id_kategori']) && $_POST['id_kategori'] !== '' ? (int) $_POST['id_kategori'] : null;
$selectedKategoriLabel = 'Semua kategori';

if (!$supportsKategori) {
    $idKategori = null;
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
                    COALESCE(kategori.nama_kategori, 'Belum dikategorikan') AS nama_kategori
                  FROM {$tabel} AS laporan
                  INNER JOIN user ON laporan.user = user.id_user
                  LEFT JOIN kategori
                    ON laporan.id_kategori = kategori.id_kategori
                   AND kategori.user_id = laporan.user
                   AND kategori.tipe_kategori = ?
                  WHERE laporan.user = ?
                    AND laporan.tanggal BETWEEN ? AND ?";

    if ($tabel === 'pemasukan') {
        $mainQuery .= " AND laporan.status = 'selesai'";
    }

    if ($idKategori !== null) {
        $mainQuery .= " AND laporan.id_kategori = ?";
    }

    $mainQuery .= " ORDER BY laporan.tanggal ASC, laporan.id_{$tabel} ASC";

    $stmt = mysqli_prepare($con, $mainQuery);

    if ($idKategori !== null) {
        mysqli_stmt_bind_param($stmt, "sissi", $tabel, $id_user, $tgl_awal, $tgl_akhir, $idKategori);
    } else {
        mysqli_stmt_bind_param($stmt, "siss", $tabel, $id_user, $tgl_awal, $tgl_akhir);
    }

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

    $summaryQuery .= " GROUP BY COALESCE(kategori.nama_kategori, 'Belum dikategorikan')
                       ORDER BY total_jumlah DESC, total_transaksi DESC";

    $stmtSummary = mysqli_prepare($con, $summaryQuery);

    if ($idKategori !== null) {
        mysqli_stmt_bind_param($stmtSummary, "sissi", $tabel, $id_user, $tgl_awal, $tgl_akhir, $idKategori);
    } else {
        mysqli_stmt_bind_param($stmtSummary, "siss", $tabel, $id_user, $tgl_awal, $tgl_akhir);
    }

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
    fputcsv($csvOutput, ['Jumlah Transaksi', (string) $jumlahTransaksi], ';');
    fputcsv($csvOutput, ['Total Nominal', (string) $total], ';');
    fputcsv($csvOutput, [], ';');

    $headers = ['No', 'Tanggal'];
    if ($supportsKategori) {
        $headers[] = 'Kategori';
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
                $this->SetY(-12);
                $this->SetFont('helvetica', '', 8);
                $this->SetTextColor(100, 116, 139);
                $this->Cell(
                    0,
                    6,
                    '© CashFlow Control ' . date('Y') . ' | Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(),
                    0,
                    false,
                    'C'
                );
            }
        }
    }

    $pdf = new CashFlowReportPdf('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('CashFlow Control');
    $pdf->SetAuthor('CashFlow Control');
    $pdf->SetTitle('Laporan ' . $jenisLaporan);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 16);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    $pdfLogoHtml = $hasReportLogo
        ? '<td width="58"><img src="' . report_escape($reportLogoPdfPath) . '" width="42" height="42"></td>'
        : '';

    $pdfHtml = '
    <style>
        h1, h2, h3, p { margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        th { background-color: #f3f4f6; font-weight: bold; }
        .brand-table td { border: none; padding: 0; vertical-align: middle; }
        .brand-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #0f172a; }
        .brand-subtitle { color: #64748b; font-size: 11px; }
        .rule { border-top: 2px solid #0ea5e9; height: 1px; }
        .meta-table td { border: none; padding: 2px 0; }
        .summary-table td { border: 1px solid #dbeafe; background-color: #f8fbff; padding: 8px; }
        .muted { color: #64748b; font-size: 11px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total-row td { font-weight: bold; background-color: #f9fafb; }
        .spacer { height: 10px; }
    </style>
    <table class="brand-table"><tr>
        ' . $pdfLogoHtml . '
        <td>
            <p class="brand-title">CASHFLOW CONTROL</p>
            <p class="brand-subtitle">Laporan transaksi pribadi dari sistem CashFlow Control</p>
        </td>
    </tr></table>
    <div class="spacer"></div>
    <div class="rule"></div>
    <div class="spacer"></div>
    <h2 style="font-size:16px;">Laporan ' . report_escape($jenisLaporan) . '</h2>
    <div class="spacer"></div>
    <table class="meta-table">
        <tr><td width="110">Jenis laporan</td><td>: ' . report_escape($jenisLaporan) . '</td></tr>
        <tr><td width="110">Periode</td><td>: ' . report_escape($periodeLabel) . '</td></tr>
        <tr><td width="110">Kategori</td><td>: ' . report_escape($labelKategoriRingkas) . '</td></tr>
        <tr><td width="110">Dicetak pada</td><td>: ' . report_escape($tanggalCetak) . '</td></tr>
        <tr><td width="110">Dicetak oleh</td><td>: ' . report_escape($user_data['nama'] ?? $user_data['username'] ?? '-') . ' (' . report_escape($user_data['username'] ?? '-') . ')</td></tr>
    </table>
    <div class="spacer"></div>
    <table class="summary-table">
        <tr>
            <td width="20%"><strong>Jenis</strong><br>' . report_escape($jenisLaporan) . '</td>
            <td width="26%"><strong>Periode</strong><br>' . report_escape($periodeLabel) . '</td>
            <td width="22%"><strong>Kategori</strong><br>' . report_escape($labelKategoriRingkas) . '</td>
            <td width="14%"><strong>Transaksi</strong><br>' . number_format((float) $jumlahTransaksi, 0, ',', '.') . '</td>
            <td width="18%"><strong>Total</strong><br>Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
        </tr>
    </table>
    <div class="spacer"></div>';

    if (empty($reportRows)) {
        $pdfHtml .= '<p>Tidak ada data laporan untuk filter yang dipilih.</p>';
    } else {
        $pdfHtml .= '<table><thead><tr>
            <th width="5%" class="center">No</th>
            <th width="12%">Tanggal</th>';

        if ($supportsKategori) {
            $pdfHtml .= '<th width="18%">Kategori</th>';
        }

        $pdfHtml .= '<th width="15%">Jumlah</th>
            <th width="' . ($supportsKategori ? ($tabel === 'pemasukan' ? '38%' : '50%') : ($tabel === 'pemasukan' ? '50%' : '62%')) . '">Catatan</th>';

        if ($tabel === 'pemasukan') {
            $pdfHtml .= '<th width="12%">Status</th>';
        }

        $pdfHtml .= '</tr></thead><tbody>';

        foreach ($reportRows as $index => $row) {
            $pdfHtml .= '<tr>
                <td class="center">' . ($index + 1) . '</td>
                <td>' . report_escape(date('d M Y', strtotime($row['tanggal']))) . '</td>';

            if ($supportsKategori) {
                $pdfHtml .= '<td>' . report_escape($row['nama_kategori'] ?? 'Belum dikategorikan') . '</td>';
            }

            $pdfHtml .= '<td class="right">Rp ' . number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') . '</td>
                <td>' . nl2br(report_escape($row['catatan'] ?? '-')) . '</td>';

            if ($tabel === 'pemasukan') {
                $pdfHtml .= '<td>' . report_escape($row['status'] ?? '-') . '</td>';
            }

            $pdfHtml .= '</tr>';
        }

        $pdfHtml .= '<tr class="total-row">
            <td colspan="' . ($supportsKategori ? '3' : '2') . '">Total</td>
            <td class="right">Rp ' . number_format((float) $total, 0, ',', '.') . '</td>
            <td colspan="' . ($tabel === 'pemasukan' ? '2' : '1') . '"></td>
        </tr>';
        $pdfHtml .= '</tbody></table>';

        if ($supportsKategori && !empty($summaryRows)) {
            $pdfHtml .= '<div class="spacer"></div><h3 style="font-size:13px;">Rekap Total Per Kategori</h3><div class="spacer"></div>';
            $pdfHtml .= '<table><thead><tr>
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
                        <td align="right">Rp <?= number_format((float) ($row['jumlah'] ?? 0), 0, ',', '.') ?></td>
                        <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                        <?php if ($tabel == 'pemasukan'): ?>
                            <td><?= htmlspecialchars($row['status'] ?? '-') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php } ?>
                <tr class="total-row">
                    <td colspan="<?= $supportsKategori ? '3' : '2' ?>">Total</td>
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
