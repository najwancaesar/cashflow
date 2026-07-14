<?php

if (!function_exists('cashflow_wallet_scalar')) {
    function cashflow_wallet_scalar($con, $sql, $types, array $params)
    {
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan perhitungan saldo wallet.');
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_row() : null;
        $stmt->close();

        return (float) ($row[0] ?? 0);
    }
}

if (!function_exists('cashflow_lock_owned_wallets')) {
    function cashflow_lock_owned_wallets($con, $userId, array $walletIds, array $activeRequiredWalletIds = [])
    {
        $walletIds = array_values(array_unique(array_filter(array_map('intval', $walletIds), static function ($walletId) {
            return $walletId > 0;
        })));
        sort($walletIds, SORT_NUMERIC);

        $activeRequired = array_fill_keys(array_map('intval', $activeRequiredWalletIds), true);
        $wallets = [];

        foreach ($walletIds as $walletId) {
            $stmt = $con->prepare("SELECT id_wallet, user_id, saldo_awal, is_active
                                   FROM wallet
                                   WHERE id_wallet = ? AND user_id = ?
                                   LIMIT 1
                                   FOR UPDATE");
            if (!$stmt) {
                throw new RuntimeException('Gagal mengunci wallet.');
            }

            $stmt->bind_param('ii', $walletId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $wallet = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$wallet) {
                throw new DomainException('Wallet tidak valid atau bukan milik Anda.');
            }

            if (isset($activeRequired[$walletId]) && (int) $wallet['is_active'] !== 1) {
                throw new DomainException('Wallet sumber tidak valid atau tidak aktif.');
            }

            $wallets[$walletId] = $wallet;
        }

        return $wallets;
    }
}

if (!function_exists('cashflow_wallet_balance_from_components')) {
    function cashflow_wallet_balance_from_components(array $components)
    {
        return (float) ($components['saldo_awal'] ?? 0)
            + (float) ($components['total_pemasukan'] ?? 0)
            - (float) ($components['total_pengeluaran'] ?? 0)
            + (float) ($components['total_transfer_masuk'] ?? 0)
            - (float) ($components['total_transfer_keluar'] ?? 0)
            - (float) ($components['total_celengan_setor'] ?? 0)
            + (float) ($components['total_celengan_tarik'] ?? 0);
    }
}

if (!function_exists('cashflow_calculate_wallet_balance')) {
    function cashflow_calculate_wallet_balance($con, $userId, $walletId, $excludePengeluaranId = null, $excludeTransferId = null, $excludePemasukanId = null)
    {
        $saldoAwal = cashflow_wallet_scalar(
            $con,
            "SELECT COALESCE(saldo_awal, 0)
             FROM wallet
             WHERE id_wallet = ? AND user_id = ?
             LIMIT 1",
            'ii',
            [$walletId, $userId]
        );

        $pemasukanSql = "SELECT COALESCE(SUM(jumlah), 0)
                         FROM pemasukan
                         WHERE user = ? AND id_wallet = ? AND status = 'selesai'";
        $pemasukanTypes = 'ii';
        $pemasukanParams = [$userId, $walletId];
        if ($excludePemasukanId !== null && (int) $excludePemasukanId > 0) {
            $pemasukanSql .= ' AND id_pemasukan <> ?';
            $pemasukanTypes .= 'i';
            $pemasukanParams[] = (int) $excludePemasukanId;
        }
        $totalPemasukan = cashflow_wallet_scalar($con, $pemasukanSql, $pemasukanTypes, $pemasukanParams);

        $pengeluaranSql = "SELECT COALESCE(SUM(jumlah), 0)
                           FROM pengeluaran
                           WHERE user = ? AND id_wallet = ? AND status = 'selesai'";
        $pengeluaranTypes = 'ii';
        $pengeluaranParams = [$userId, $walletId];
        if ($excludePengeluaranId !== null && (int) $excludePengeluaranId > 0) {
            $pengeluaranSql .= ' AND id_pengeluaran <> ?';
            $pengeluaranTypes .= 'i';
            $pengeluaranParams[] = (int) $excludePengeluaranId;
        }
        $totalPengeluaran = cashflow_wallet_scalar(
            $con,
            $pengeluaranSql,
            $pengeluaranTypes,
            $pengeluaranParams
        );

        $transferMasukSql = "SELECT COALESCE(SUM(jumlah), 0)
                             FROM transfer_wallet
                             WHERE user_id = ? AND wallet_tujuan_id = ? AND status = 'selesai'";
        $transferKeluarSql = "SELECT COALESCE(SUM(jumlah), 0)
                              FROM transfer_wallet
                              WHERE user_id = ? AND wallet_asal_id = ? AND status = 'selesai'";
        $transferTypes = 'ii';
        $transferParams = [$userId, $walletId];
        if ($excludeTransferId !== null && (int) $excludeTransferId > 0) {
            $transferMasukSql .= ' AND id_transfer <> ?';
            $transferKeluarSql .= ' AND id_transfer <> ?';
            $transferTypes .= 'i';
            $transferParams[] = (int) $excludeTransferId;
        }
        $totalTransferMasuk = cashflow_wallet_scalar($con, $transferMasukSql, $transferTypes, $transferParams);
        $totalTransferKeluar = cashflow_wallet_scalar($con, $transferKeluarSql, $transferTypes, $transferParams);

        $totalSetorCelengan = cashflow_wallet_scalar(
            $con,
            "SELECT COALESCE(SUM(jumlah), 0)
             FROM saving_goal_mutasi
             WHERE user_id = ? AND id_wallet = ? AND tipe = 'setor'",
            'ii',
            [$userId, $walletId]
        );

        $totalTarikCelengan = cashflow_wallet_scalar(
            $con,
            "SELECT COALESCE(SUM(jumlah), 0)
             FROM saving_goal_mutasi
             WHERE user_id = ? AND id_wallet = ? AND tipe = 'tarik'",
            'ii',
            [$userId, $walletId]
        );

        return cashflow_wallet_balance_from_components([
            'saldo_awal' => $saldoAwal,
            'total_pemasukan' => $totalPemasukan,
            'total_pengeluaran' => $totalPengeluaran,
            'total_transfer_masuk' => $totalTransferMasuk,
            'total_transfer_keluar' => $totalTransferKeluar,
            'total_celengan_setor' => $totalSetorCelengan,
            'total_celengan_tarik' => $totalTarikCelengan,
        ]);
    }
}

if (!function_exists('cashflow_get_user_wallet_balances')) {
    function cashflow_get_user_wallet_balances($con, $userId, $activeOnly = false)
    {
        $activeCondition = $activeOnly ? ' AND wallet.is_active = 1' : '';
        $sql = "SELECT
                    wallet.id_wallet,
                    wallet.user_id,
                    wallet.nama_wallet,
                    wallet.tipe_wallet,
                    wallet.saldo_awal,
                    wallet.is_default,
                    wallet.is_active,
                    wallet.created_at,
                    wallet.updated_at,
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
                    WHERE user = ? AND status = 'selesai' AND id_wallet IS NOT NULL
                    GROUP BY id_wallet
                ) AS pemasukan_wallet ON pemasukan_wallet.id_wallet = wallet.id_wallet
                LEFT JOIN (
                    SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_pengeluaran
                    FROM pengeluaran
                    WHERE user = ? AND status = 'selesai' AND id_wallet IS NOT NULL
                    GROUP BY id_wallet
                ) AS pengeluaran_wallet ON pengeluaran_wallet.id_wallet = wallet.id_wallet
                LEFT JOIN (
                    SELECT wallet_tujuan_id AS id_wallet, COALESCE(SUM(jumlah), 0) AS total_transfer_masuk
                    FROM transfer_wallet
                    WHERE user_id = ? AND status = 'selesai'
                    GROUP BY wallet_tujuan_id
                ) AS transfer_masuk_wallet ON transfer_masuk_wallet.id_wallet = wallet.id_wallet
                LEFT JOIN (
                    SELECT wallet_asal_id AS id_wallet, COALESCE(SUM(jumlah), 0) AS total_transfer_keluar
                    FROM transfer_wallet
                    WHERE user_id = ? AND status = 'selesai'
                    GROUP BY wallet_asal_id
                ) AS transfer_keluar_wallet ON transfer_keluar_wallet.id_wallet = wallet.id_wallet
                LEFT JOIN (
                    SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_celengan_setor
                    FROM saving_goal_mutasi
                    WHERE user_id = ? AND tipe = 'setor' AND id_wallet IS NOT NULL
                    GROUP BY id_wallet
                ) AS celengan_setor_wallet ON celengan_setor_wallet.id_wallet = wallet.id_wallet
                LEFT JOIN (
                    SELECT id_wallet, COALESCE(SUM(jumlah), 0) AS total_celengan_tarik
                    FROM saving_goal_mutasi
                    WHERE user_id = ? AND tipe = 'tarik' AND id_wallet IS NOT NULL
                    GROUP BY id_wallet
                ) AS celengan_tarik_wallet ON celengan_tarik_wallet.id_wallet = wallet.id_wallet
                WHERE wallet.user_id = ?{$activeCondition}
                ORDER BY wallet.is_default DESC, wallet.is_active DESC, wallet.nama_wallet ASC";

        $stmt = $con->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Gagal menyiapkan ringkasan saldo wallet.');
        }

        $stmt->bind_param('iiiiiii', $userId, $userId, $userId, $userId, $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallets = [];

        while ($result && ($wallet = $result->fetch_assoc())) {
            $wallet['saldo_terkini'] = cashflow_wallet_balance_from_components($wallet);
            $wallets[] = $wallet;
        }

        $stmt->close();

        return $wallets;
    }
}
