<?php

if (!function_exists('cashflow_calendar_status_meta')) {
    function cashflow_calendar_status_meta($dueDate, DateTimeImmutable $today)
    {
        $dueDate = trim((string) $dueDate);
        if ($dueDate === '') {
            return [
                'key' => 'no_due',
                'label' => 'Tanpa Jatuh Tempo',
                'badge_class' => 'bg-gradient-secondary',
                'accent_class' => 'calendar-accent-secondary',
            ];
        }

        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
        if (!$due) {
            return [
                'key' => 'no_due',
                'label' => 'Tanpa Jatuh Tempo',
                'badge_class' => 'bg-gradient-secondary',
                'accent_class' => 'calendar-accent-secondary',
            ];
        }

        $todayDate = $today->setTime(0, 0);
        $sevenDays = $todayDate->modify('+7 days');
        if ($due < $todayDate) {
            return [
                'key' => 'overdue',
                'label' => 'Terlambat',
                'badge_class' => 'bg-gradient-danger',
                'accent_class' => 'calendar-accent-danger',
            ];
        }
        if ($due == $todayDate) {
            return [
                'key' => 'today',
                'label' => 'Hari Ini',
                'badge_class' => 'bg-gradient-warning',
                'accent_class' => 'calendar-accent-warning',
            ];
        }
        if ($due <= $sevenDays) {
            return [
                'key' => 'next7',
                'label' => '7 Hari ke Depan',
                'badge_class' => 'bg-gradient-warning',
                'accent_class' => 'calendar-accent-warning',
            ];
        }

        return [
            'key' => 'upcoming',
            'label' => 'Mendatang',
            'badge_class' => 'bg-gradient-info',
            'accent_class' => 'calendar-accent-info',
        ];
    }
}

if (!function_exists('cashflow_calendar_recurring_occurrence')) {
    function cashflow_calendar_recurring_occurrence(array $row, DateTimeImmutable $today)
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($row['mulai_dari'] ?? ''));
        if (!$start) {
            return null;
        }

        $end = null;
        if (!empty($row['berakhir_pada'])) {
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['berakhir_pada']);
            if (!$end) {
                return null;
            }
        }

        $currentMonth = $today->modify('first day of this month')->setTime(0, 0);
        $startMonth = $start->modify('first day of this month')->setTime(0, 0);
        $candidateMonth = $startMonth > $currentMonth ? $startMonth : $currentMonth;

        if ($candidateMonth == $currentMonth && !empty($row['current_period_log'])) {
            $candidateMonth = $candidateMonth->modify('first day of next month');
        }

        $day = max(1, min(31, (int) ($row['tanggal_generate'] ?? 1)));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $lastDay = (int) $candidateMonth->format('t');
            $occurrence = $candidateMonth->setDate(
                (int) $candidateMonth->format('Y'),
                (int) $candidateMonth->format('n'),
                min($day, $lastDay)
            );

            if ($occurrence < $start) {
                $candidateMonth = $candidateMonth->modify('first day of next month');
                continue;
            }
            if ($end !== null && $occurrence > $end) {
                return null;
            }

            return $occurrence->format('Y-m-d');
        }

        return null;
    }
}

if (!function_exists('cashflow_get_financial_calendar_events')) {
    function cashflow_get_financial_calendar_events($con, $userId, DateTimeImmutable $today = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return [];
        }

        $today = $today ?: new DateTimeImmutable('today');
        $today = $today->setTime(0, 0);
        $events = [];

        $obligationSql = "SELECT 'hutang' AS event_type, id_hutang AS event_id,
                                 kreditur AS subject_name, catatan AS detail,
                                 tanggal_jatuh_tempo AS due_date, jumlah AS nominal
                          FROM hutang
                          WHERE user = ? AND status = 'pending'
                          UNION ALL
                          SELECT 'piutang' AS event_type, id_piutang AS event_id,
                                 debitur AS subject_name, catatan AS detail,
                                 tanggal_jatuh_tempo AS due_date, jumlah AS nominal
                          FROM piutang
                          WHERE user = ? AND status = 'pending'
                          UNION ALL
                          SELECT 'saving_goal' AS event_type, id_goal AS event_id,
                                 nama_goal AS subject_name, '' AS detail,
                                 target_tanggal AS due_date, target_nominal AS nominal
                          FROM saving_goal
                          WHERE user_id = ? AND status = 'aktif' AND target_tanggal IS NOT NULL";
        $stmt = $con->prepare($obligationSql);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan kalender kewajiban.');
        }
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $type = (string) $row['event_type'];
            $eventId = (int) $row['event_id'];
            $subject = trim((string) ($row['subject_name'] ?? ''));
            $titles = [
                'hutang' => 'Utang kepada ' . ($subject !== '' ? $subject : 'kreditur'),
                'piutang' => 'Piutang dari ' . ($subject !== '' ? $subject : 'debitur'),
                'saving_goal' => 'Target celengan ' . ($subject !== '' ? $subject : 'tanpa nama'),
            ];
            $modules = [
                'hutang' => 'hutang',
                'piutang' => 'piutang',
                'saving_goal' => 'saving_goal',
            ];
            $labels = [
                'hutang' => 'Utang',
                'piutang' => 'Piutang',
                'saving_goal' => 'Celengan',
            ];
            $dueDate = trim((string) ($row['due_date'] ?? ''));
            $statusMeta = cashflow_calendar_status_meta($dueDate, $today);
            $eventKey = $type . ':' . $eventId . ':' . ($dueDate !== '' ? $dueDate : 'none');
            $events[$eventKey] = [
                'key' => $eventKey,
                'type' => $type,
                'type_label' => $labels[$type] ?? ucfirst($type),
                'id' => $eventId,
                'title' => $titles[$type] ?? $subject,
                'detail' => trim((string) ($row['detail'] ?? '')),
                'due_date' => $dueDate,
                'nominal' => (float) ($row['nominal'] ?? 0),
                'status_key' => $statusMeta['key'],
                'status_label' => $statusMeta['label'],
                'badge_class' => $statusMeta['badge_class'],
                'accent_class' => $statusMeta['accent_class'],
                'module' => $modules[$type] ?? 'home',
                'url' => 'main.php?module=' . ($modules[$type] ?? 'home'),
            ];
        }
        $stmt->close();

        $month = (int) $today->format('n');
        $year = (int) $today->format('Y');
        $recurringSql = "SELECT recurring_transaction.id_recurring,
                                recurring_transaction.tipe_transaksi,
                                recurring_transaction.nama_recurring,
                                recurring_transaction.catatan,
                                recurring_transaction.jumlah,
                                recurring_transaction.tanggal_generate,
                                recurring_transaction.mulai_dari,
                                recurring_transaction.berakhir_pada,
                                recurring_generation_log.id_log AS current_period_log
                         FROM recurring_transaction
                         LEFT JOIN recurring_generation_log
                           ON recurring_generation_log.id_recurring = recurring_transaction.id_recurring
                          AND recurring_generation_log.user_id = recurring_transaction.user_id
                          AND recurring_generation_log.periode_bulan = ?
                          AND recurring_generation_log.periode_tahun = ?
                         WHERE recurring_transaction.user_id = ?
                           AND recurring_transaction.is_active = 1";
        $recurringStmt = $con->prepare($recurringSql);
        if (!$recurringStmt) {
            throw new RuntimeException('Gagal menyiapkan kalender recurring.');
        }
        $recurringStmt->bind_param('iii', $month, $year, $userId);
        $recurringStmt->execute();
        $recurringResult = $recurringStmt->get_result();
        while ($recurringResult && ($row = $recurringResult->fetch_assoc())) {
            $dueDate = cashflow_calendar_recurring_occurrence($row, $today);
            if ($dueDate === null) {
                continue;
            }

            $eventId = (int) $row['id_recurring'];
            $eventKey = 'recurring:' . $eventId . ':' . $dueDate;
            $statusMeta = cashflow_calendar_status_meta($dueDate, $today);
            $typeLabel = (string) ($row['tipe_transaksi'] ?? '') === 'pengeluaran'
                ? 'Recurring Pengeluaran'
                : 'Recurring Pemasukan';
            $events[$eventKey] = [
                'key' => $eventKey,
                'type' => 'recurring',
                'type_label' => $typeLabel,
                'id' => $eventId,
                'title' => trim((string) ($row['nama_recurring'] ?? '')) ?: 'Transaksi berulang',
                'detail' => trim((string) ($row['catatan'] ?? '')),
                'due_date' => $dueDate,
                'nominal' => (float) ($row['jumlah'] ?? 0),
                'status_key' => $statusMeta['key'],
                'status_label' => $statusMeta['label'],
                'badge_class' => $statusMeta['badge_class'],
                'accent_class' => $statusMeta['accent_class'],
                'module' => 'recurring',
                'url' => 'main.php?module=recurring',
            ];
        }
        $recurringStmt->close();

        $events = array_values($events);
        usort($events, static function (array $left, array $right) {
            $leftDate = $left['due_date'] !== '' ? $left['due_date'] : '9999-12-31';
            $rightDate = $right['due_date'] !== '' ? $right['due_date'] : '9999-12-31';
            if ($leftDate === $rightDate) {
                return strcmp($left['key'], $right['key']);
            }

            return strcmp($leftDate, $rightDate);
        });

        return $events;
    }
}

if (!function_exists('cashflow_financial_calendar_summary')) {
    function cashflow_financial_calendar_summary(array $events)
    {
        $summary = [
            'overdue' => 0,
            'today' => 0,
            'next7' => 0,
            'upcoming' => 0,
            'no_due' => 0,
            'nearest' => [],
        ];

        foreach ($events as $event) {
            $key = (string) ($event['status_key'] ?? 'upcoming');
            if (array_key_exists($key, $summary) && $key !== 'nearest') {
                $summary[$key]++;
            }
        }

        $dated = array_values(array_filter($events, static function (array $event) {
            return !empty($event['due_date']);
        }));
        usort($dated, static function (array $left, array $right) {
            $rank = ['overdue' => 0, 'today' => 1, 'next7' => 2, 'upcoming' => 3];
            $leftRank = $rank[$left['status_key']] ?? 4;
            $rightRank = $rank[$right['status_key']] ?? 4;
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }
            if ($leftRank === 0) {
                return strcmp($right['due_date'], $left['due_date']);
            }

            return strcmp($left['due_date'], $right['due_date']);
        });
        $summary['nearest'] = array_slice($dated, 0, 3);

        return $summary;
    }
}
