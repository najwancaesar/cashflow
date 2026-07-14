<?php

if (!function_exists('cashflow_budget_status')) {
    function cashflow_budget_status($budgetNominal, $totalTerpakai)
    {
        $budgetNominal = (float) $budgetNominal;
        $totalTerpakai = (float) $totalTerpakai;

        if ($budgetNominal <= 0) {
            return [
                'label' => 'Belum diatur',
                'badge_class' => 'bg-gradient-secondary',
                'progress_class' => 'bg-gradient-secondary',
                'percentage' => 0.0,
                'width' => 0.0,
            ];
        }

        $percentage = ($totalTerpakai / $budgetNominal) * 100;
        if ($percentage >= 100) {
            $label = 'Terlampaui';
            $badgeClass = 'bg-gradient-danger';
            $progressClass = 'bg-gradient-danger';
        } elseif ($percentage >= 90) {
            $label = 'Hampir Habis';
            $badgeClass = 'bg-gradient-warning';
            $progressClass = 'bg-gradient-warning';
        } elseif ($percentage >= 70) {
            $label = 'Perhatian';
            $badgeClass = 'bg-gradient-info';
            $progressClass = 'bg-gradient-info';
        } else {
            $label = 'Aman';
            $badgeClass = 'bg-gradient-success';
            $progressClass = 'bg-gradient-success';
        }

        return [
            'label' => $label,
            'badge_class' => $badgeClass,
            'progress_class' => $progressClass,
            'percentage' => $percentage,
            'width' => min(100.0, max(0.0, $percentage)),
        ];
    }
}

if (!function_exists('cashflow_budget_period_key')) {
    function cashflow_budget_period_key($categoryId, $date)
    {
        $categoryId = (int) $categoryId;
        $timestamp = strtotime((string) $date);
        if ($categoryId <= 0 || $timestamp === false) {
            return '';
        }

        return date('Y-m', $timestamp) . ':' . $categoryId;
    }
}

if (!function_exists('cashflow_get_user_budget_usage_map')) {
    function cashflow_get_user_budget_usage_map($con, $userId)
    {
        $stmt = null;

        try {
            $stmt = $con->prepare(
                "SELECT
                    budget_kategori.id_budget,
                    budget_kategori.id_kategori,
                    budget_kategori.bulan,
                    budget_kategori.tahun,
                    budget_kategori.nominal_budget,
                    COALESCE(SUM(pengeluaran.jumlah), 0) AS total_terpakai
                 FROM budget_kategori
                 LEFT JOIN pengeluaran
                    ON pengeluaran.user = budget_kategori.user_id
                   AND pengeluaran.id_kategori = budget_kategori.id_kategori
                   AND pengeluaran.status = 'selesai'
                   AND MONTH(pengeluaran.tanggal) = budget_kategori.bulan
                   AND YEAR(pengeluaran.tanggal) = budget_kategori.tahun
                 WHERE budget_kategori.user_id = ?
                 GROUP BY
                    budget_kategori.id_budget,
                    budget_kategori.id_kategori,
                    budget_kategori.bulan,
                    budget_kategori.tahun,
                    budget_kategori.nominal_budget"
            );
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('i', $userId);
            if (!$stmt->execute()) {
                $stmt->close();
                return [];
            }

            $result = $stmt->get_result();
            $map = [];
            while ($result && ($row = $result->fetch_assoc())) {
                $key = sprintf(
                    '%04d-%02d:%d',
                    (int) $row['tahun'],
                    (int) $row['bulan'],
                    (int) $row['id_kategori']
                );
                $map[$key] = [
                    'id_budget' => (int) $row['id_budget'],
                    'id_kategori' => (int) $row['id_kategori'],
                    'bulan' => (int) $row['bulan'],
                    'tahun' => (int) $row['tahun'],
                    'nominal_budget' => (float) $row['nominal_budget'],
                    'total_terpakai' => (float) $row['total_terpakai'],
                ];
            }
            $stmt->close();

            return $map;
        } catch (Throwable $error) {
            if ($stmt) {
                $stmt->close();
            }
            error_log('CashFlow budget usage query failed.');
            return [];
        }
    }
}
