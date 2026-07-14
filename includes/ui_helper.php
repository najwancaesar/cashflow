<?php

if (!function_exists('cashflow_format_date')) {
    function cashflow_format_date($value, $withTime = false)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '-';
        }

        return date($withTime ? 'd M Y H:i' : 'd M Y', $timestamp);
    }
}

if (!function_exists('cashflow_format_rupiah')) {
    function cashflow_format_rupiah($value)
    {
        return 'Rp. ' . number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('cashflow_status_badge_class')) {
    function cashflow_status_badge_class($status)
    {
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['selesai', 'lunas', 'aktif'], true)) {
            return 'bg-gradient-success';
        }
        if ($status === 'pending') {
            return 'bg-gradient-warning';
        }
        if (in_array($status, ['batal', 'gagal'], true)) {
            return 'bg-gradient-danger';
        }
        if (in_array($status, ['mutasi', 'setor', 'tarik', 'internal'], true)) {
            return 'bg-gradient-info';
        }

        return 'bg-gradient-secondary';
    }
}
