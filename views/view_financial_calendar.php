<?php
include __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/ui_helper.php';
include_once __DIR__ . '/../includes/financial_calendar_helper.php';

$calendarUserId = (int) ($_SESSION['id_user'] ?? 0);
if ($calendarUserId <= 0 || strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    return;
}

$calendarToday = new DateTimeImmutable('today');
$selectedMonth = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) $calendarToday->format('n');
$selectedYear = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) $calendarToday->format('Y');
$selectedType = trim((string) ($_GET['jenis'] ?? 'all'));
$selectedStatus = trim((string) ($_GET['status'] ?? 'all'));

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int) $calendarToday->format('n');
}
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int) $calendarToday->format('Y');
}

$allowedTypes = ['all', 'hutang', 'piutang', 'recurring', 'saving_goal'];
$allowedStatuses = ['all', 'overdue', 'today', 'next7', 'upcoming', 'no_due'];
if (!in_array($selectedType, $allowedTypes, true)) {
    $selectedType = 'all';
}
if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = 'all';
}

$calendarEvents = cashflow_get_financial_calendar_events($con, $calendarUserId, $calendarToday);
$calendarSummary = cashflow_financial_calendar_summary($calendarEvents);
$filteredCalendarEvents = array_values(array_filter($calendarEvents, static function (array $event) use ($selectedMonth, $selectedYear, $selectedType, $selectedStatus) {
    if ($selectedType !== 'all' && $event['type'] !== $selectedType) {
        return false;
    }
    if ($selectedStatus !== 'all' && $event['status_key'] !== $selectedStatus) {
        return false;
    }

    if ($event['status_key'] === 'no_due') {
        return $selectedStatus === 'no_due';
    }

    $timestamp = strtotime((string) $event['due_date']);
    return $timestamp !== false
        && (int) date('n', $timestamp) === $selectedMonth
        && (int) date('Y', $timestamp) === $selectedYear;
}));

$calendarMonths = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$calendarYears = [(int) $calendarToday->format('Y') - 1, (int) $calendarToday->format('Y'), (int) $calendarToday->format('Y') + 1];
foreach ($calendarEvents as $event) {
    if (!empty($event['due_date'])) {
        $calendarYears[] = (int) substr($event['due_date'], 0, 4);
    }
}
$calendarYears = array_values(array_unique(array_filter($calendarYears)));
sort($calendarYears);
?>

<div class="container-fluid py-4 financial-calendar-page">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Kalender Keuangan</h6>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="calendar-summary-grid mb-4">
                        <?php
                        $summaryCards = [
                            ['key' => 'overdue', 'label' => 'Terlambat', 'class' => 'calendar-accent-danger'],
                            ['key' => 'today', 'label' => 'Hari Ini', 'class' => 'calendar-accent-warning'],
                            ['key' => 'next7', 'label' => '7 Hari ke Depan', 'class' => 'calendar-accent-warning'],
                            ['key' => 'upcoming', 'label' => 'Mendatang', 'class' => 'calendar-accent-info'],
                            ['key' => 'no_due', 'label' => 'Tanpa Jatuh Tempo', 'class' => 'calendar-accent-secondary'],
                        ];
                        foreach ($summaryCards as $card) { ?>
                            <div class="calendar-summary-card <?= htmlspecialchars($card['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="text-xs text-secondary"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= number_format((int) $calendarSummary[$card['key']]) ?></strong>
                            </div>
                        <?php } ?>
                    </div>

                    <form method="get" action="main.php" class="calendar-filter-grid mb-4">
                        <input type="hidden" name="module" value="kalender_keuangan">
                        <div>
                            <label for="calendarMonth" class="form-label">Bulan</label>
                            <div class="input-group input-group-outline">
                                <select id="calendarMonth" name="bulan" class="form-control">
                                    <?php foreach ($calendarMonths as $monthNumber => $monthName) { ?>
                                        <option value="<?= (int) $monthNumber ?>" <?= $selectedMonth === $monthNumber ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="calendarYear" class="form-label">Tahun</label>
                            <div class="input-group input-group-outline">
                                <select id="calendarYear" name="tahun" class="form-control">
                                    <?php foreach ($calendarYears as $year) { ?>
                                        <option value="<?= (int) $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>><?= (int) $year ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="calendarType" class="form-label">Jenis</label>
                            <div class="input-group input-group-outline">
                                <select id="calendarType" name="jenis" class="form-control">
                                    <option value="all" <?= $selectedType === 'all' ? 'selected' : '' ?>>Semua Jenis</option>
                                    <option value="hutang" <?= $selectedType === 'hutang' ? 'selected' : '' ?>>Utang</option>
                                    <option value="piutang" <?= $selectedType === 'piutang' ? 'selected' : '' ?>>Piutang</option>
                                    <option value="recurring" <?= $selectedType === 'recurring' ? 'selected' : '' ?>>Recurring</option>
                                    <option value="saving_goal" <?= $selectedType === 'saving_goal' ? 'selected' : '' ?>>Celengan</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="calendarStatus" class="form-label">Status</label>
                            <div class="input-group input-group-outline">
                                <select id="calendarStatus" name="status" class="form-control">
                                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="overdue" <?= $selectedStatus === 'overdue' ? 'selected' : '' ?>>Terlambat</option>
                                    <option value="today" <?= $selectedStatus === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                                    <option value="next7" <?= $selectedStatus === 'next7' ? 'selected' : '' ?>>7 Hari ke Depan</option>
                                    <option value="upcoming" <?= $selectedStatus === 'upcoming' ? 'selected' : '' ?>>Mendatang</option>
                                    <option value="no_due" <?= $selectedStatus === 'no_due' ? 'selected' : '' ?>>Tanpa Jatuh Tempo</option>
                                </select>
                            </div>
                        </div>
                        <div class="calendar-filter-actions">
                            <button type="submit" class="btn btn-info mb-0">Tampilkan</button>
                            <a href="main.php?module=kalender_keuangan" class="btn btn-outline-secondary mb-0">Reset</a>
                        </div>
                    </form>

                    <?php if (empty($filteredCalendarEvents)) { ?>
                        <div class="calendar-empty-state">
                            <i class="fa fa-calendar-o text-secondary mb-2" aria-hidden="true"></i>
                            <p class="text-sm font-weight-bold mb-1">Tidak ada agenda pada filter ini.</p>
                            <p class="text-xs text-secondary mb-0">Coba periode atau jenis lain. Item tanpa tanggal dapat dilihat melalui status Tanpa Jatuh Tempo.</p>
                        </div>
                    <?php } else { ?>
                        <div class="financial-calendar-list">
                            <?php foreach ($filteredCalendarEvents as $event) { ?>
                                <article class="financial-calendar-event <?= htmlspecialchars($event['accent_class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="calendar-event-date">
                                        <span class="text-xs text-secondary">Tanggal</span>
                                        <strong><?= htmlspecialchars(cashflow_format_date($event['due_date']), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="calendar-event-main cashflow-long-text">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="mb-0"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h6>
                                            <span class="badge badge-sm <?= htmlspecialchars($event['badge_class'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($event['status_label'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-secondary mb-1">
                                            <?= htmlspecialchars($event['type_label'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($event['detail'] !== '') { ?> &bull; <?= htmlspecialchars($event['detail'], ENT_QUOTES, 'UTF-8') ?><?php } ?>
                                        </p>
                                        <p class="text-sm font-weight-bold mb-0"><?= htmlspecialchars(cashflow_format_rupiah($event['nominal']), ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="calendar-event-action">
                                        <a href="<?= htmlspecialchars($event['url'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-info mb-0">
                                            Buka <?= htmlspecialchars($event['type_label'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
