<?php

if (!function_exists('cashflow_legacy_wallet_types')) {
    function cashflow_legacy_wallet_types()
    {
        return [
            'cash' => ['label' => 'Cash', 'icon' => 'money', 'color' => '#22c55e'],
            'bank' => ['label' => 'Bank', 'icon' => 'university', 'color' => '#0ea5e9'],
            'e_wallet' => ['label' => 'E-Wallet', 'icon' => 'mobile', 'color' => '#f59e0b'],
            'tabungan' => ['label' => 'Tabungan', 'icon' => 'credit-card', 'color' => '#6366f1'],
            'lainnya' => ['label' => 'Lainnya', 'icon' => 'briefcase', 'color' => '#64748b'],
        ];
    }
}

if (!function_exists('cashflow_wallet_type_schema_ready')) {
    function cashflow_wallet_type_schema_ready($con)
    {
        static $readyByConnection = [];
        $connectionKey = function_exists('spl_object_id') ? spl_object_id($con) : 0;

        if (array_key_exists($connectionKey, $readyByConnection)) {
            return $readyByConnection[$connectionKey];
        }

        try {
            $tableResult = $con->query("SHOW TABLES LIKE 'wallet_type'");
            $tableExists = $tableResult && $tableResult->num_rows > 0;
            if ($tableResult) {
                $tableResult->free();
            }

            $columnResult = $con->query("SHOW COLUMNS FROM `wallet` LIKE 'id_wallet_type'");
            $columnExists = $columnResult && $columnResult->num_rows > 0;
            if ($columnResult) {
                $columnResult->free();
            }

            $readyByConnection[$connectionKey] = $tableExists && $columnExists;
        } catch (Throwable $error) {
            error_log('CashFlow wallet type schema check failed.');
            $readyByConnection[$connectionKey] = false;
        }

        return $readyByConnection[$connectionKey];
    }
}

if (!function_exists('cashflow_wallet_type_icon_options')) {
    function cashflow_wallet_type_icon_options()
    {
        return [
            'money' => 'Uang Tunai',
            'university' => 'Bank',
            'credit-card' => 'Kartu',
            'mobile' => 'Perangkat / E-Wallet',
            'briefcase' => 'Bisnis',
            'star' => 'Favorit',
        ];
    }
}

if (!function_exists('cashflow_normalize_wallet_type_icon')) {
    function cashflow_normalize_wallet_type_icon($icon)
    {
        $icon = trim((string) $icon);
        return array_key_exists($icon, cashflow_wallet_type_icon_options()) ? $icon : 'credit-card';
    }
}

if (!function_exists('cashflow_normalize_wallet_type_color')) {
    function cashflow_normalize_wallet_type_color($color)
    {
        $color = strtoupper(trim((string) $color));
        return preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '#64748B';
    }
}

if (!function_exists('cashflow_get_custom_wallet_types')) {
    function cashflow_get_custom_wallet_types($con, $userId, $activeOnly = false)
    {
        $stmt = null;

        try {
            if (!cashflow_wallet_type_schema_ready($con)) {
                return [];
            }

            $activeSql = $activeOnly ? ' AND is_active = 1' : '';
            $stmt = $con->prepare("SELECT id_wallet_type, user_id, nama_tipe, icon, warna, is_active, created_at, updated_at
                                   FROM wallet_type
                                   WHERE user_id = ?{$activeSql}
                                   ORDER BY is_active DESC, nama_tipe ASC");
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param('i', $userId);
            if (!$stmt->execute()) {
                $stmt->close();
                return [];
            }

            $result = $stmt->get_result();
            $types = [];
            while ($result && ($row = $result->fetch_assoc())) {
                $types[] = $row;
            }
            $stmt->close();

            return $types;
        } catch (Throwable $error) {
            if ($stmt) {
                $stmt->close();
            }
            error_log('CashFlow custom wallet type query failed.');
            return [];
        }
    }
}

if (!function_exists('cashflow_get_wallet_custom_type_map')) {
    function cashflow_get_wallet_custom_type_map($con, $userId)
    {
        $stmt = null;

        try {
            if (!cashflow_wallet_type_schema_ready($con)) {
                return [];
            }

            $stmt = $con->prepare("SELECT wallet.id_wallet, wallet_type.id_wallet_type, wallet_type.nama_tipe,
                                          wallet_type.icon, wallet_type.warna, wallet_type.is_active
                                   FROM wallet
                                   INNER JOIN wallet_type
                                       ON wallet_type.id_wallet_type = wallet.id_wallet_type
                                      AND wallet_type.user_id = wallet.user_id
                                   WHERE wallet.user_id = ?");
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
                $map[(int) $row['id_wallet']] = $row;
            }
            $stmt->close();

            return $map;
        } catch (Throwable $error) {
            if ($stmt) {
                $stmt->close();
            }
            error_log('CashFlow wallet custom type map query failed.');
            return [];
        }
    }
}

if (!function_exists('cashflow_resolve_wallet_type_selection')) {
    function cashflow_resolve_wallet_type_selection($con, $userId, $selection, $walletId = null)
    {
        $selection = trim((string) $selection);
        $legacyTypes = cashflow_legacy_wallet_types();
        $legacyKey = strpos($selection, 'legacy:') === 0 ? substr($selection, 7) : $selection;

        if (isset($legacyTypes[$legacyKey])) {
            return ['legacy_type' => $legacyKey, 'custom_type_id' => null];
        }

        if (strpos($selection, 'custom:') !== 0 || !cashflow_wallet_type_schema_ready($con)) {
            return null;
        }

        $customTypeId = (int) substr($selection, 7);
        if ($customTypeId <= 0) {
            return null;
        }

        $walletId = (int) $walletId;
        $stmt = $con->prepare("SELECT wallet_type.id_wallet_type
                               FROM wallet_type
                               WHERE wallet_type.id_wallet_type = ?
                                 AND wallet_type.user_id = ?
                                 AND (
                                     wallet_type.is_active = 1
                                     OR EXISTS (
                                         SELECT 1
                                         FROM wallet
                                         WHERE wallet.id_wallet = ?
                                           AND wallet.user_id = ?
                                           AND wallet.id_wallet_type = wallet_type.id_wallet_type
                                     )
                                 )
                               LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iiii', $customTypeId, $userId, $walletId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $valid = $result && $result->num_rows === 1;
        $stmt->close();

        return $valid ? ['legacy_type' => 'lainnya', 'custom_type_id' => $customTypeId] : null;
    }
}

if (!function_exists('cashflow_wallet_type_meta')) {
    function cashflow_wallet_type_meta($legacyType, $customType = null)
    {
        if (is_array($customType) && !empty($customType['id_wallet_type'])) {
            return [
                'label' => (string) ($customType['nama_tipe'] ?? 'Tipe Kustom'),
                'icon' => cashflow_normalize_wallet_type_icon($customType['icon'] ?? ''),
                'color' => cashflow_normalize_wallet_type_color($customType['warna'] ?? ''),
                'is_custom' => true,
                'id_wallet_type' => (int) $customType['id_wallet_type'],
                'is_active' => (int) ($customType['is_active'] ?? 0) === 1,
            ];
        }

        $legacyTypes = cashflow_legacy_wallet_types();
        $meta = $legacyTypes[$legacyType] ?? $legacyTypes['lainnya'];
        $meta['is_custom'] = false;
        $meta['id_wallet_type'] = null;
        $meta['is_active'] = true;
        return $meta;
    }
}

if (!function_exists('cashflow_wallet_type_meta_for_wallet')) {
    function cashflow_wallet_type_meta_for_wallet($legacyType, $walletId, $customTypeMap)
    {
        $walletId = (int) $walletId;
        $customType = is_array($customTypeMap) && isset($customTypeMap[$walletId])
            ? $customTypeMap[$walletId]
            : null;

        return cashflow_wallet_type_meta($legacyType, $customType);
    }
}

if (!function_exists('cashflow_wallet_type_meta_from_row')) {
    function cashflow_wallet_type_meta_from_row($row, $customTypeMap, $walletIdKey = 'id_wallet', $legacyTypeKey = 'tipe_wallet')
    {
        return cashflow_wallet_type_meta_for_wallet(
            $row[$legacyTypeKey] ?? 'lainnya',
            $row[$walletIdKey] ?? 0,
            $customTypeMap
        );
    }
}

if (!function_exists('cashflow_wallet_type_text')) {
    function cashflow_wallet_type_text($meta, $showInactive = true)
    {
        $label = trim((string) ($meta['label'] ?? 'Lainnya'));
        if ($label === '') {
            $label = 'Lainnya';
        }

        if ($showInactive && !($meta['is_active'] ?? true)) {
            $label .= ' (Nonaktif)';
        }

        return $label;
    }
}

if (!function_exists('cashflow_wallet_type_inline_html')) {
    function cashflow_wallet_type_inline_html($meta, $showInactive = true)
    {
        $label = cashflow_wallet_type_text($meta, $showInactive);
        $icon = cashflow_normalize_wallet_type_icon($meta['icon'] ?? '');
        $color = cashflow_normalize_wallet_type_color($meta['color'] ?? '');

        return '<span class="cashflow-wallet-type-inline">'
            . '<i class="fa fa-' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"'
            . ' style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';" aria-hidden="true"></i>'
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }
}
