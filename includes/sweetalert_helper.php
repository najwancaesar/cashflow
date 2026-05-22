<?php

if (!function_exists('ensure_cashflow_session')) {
    function ensure_cashflow_session()
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
}

if (!function_exists('set_sweetalert_flash')) {
    function set_sweetalert_flash($title, $text, $icon = 'info')
    {
        ensure_cashflow_session();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash_message'] = [
                'title' => (string) $title,
                'text' => (string) $text,
                'icon' => (string) $icon,
            ];
        }
    }
}

if (!function_exists('cashflow_allowed_clean_modules')) {
    function cashflow_allowed_clean_modules()
    {
        return [
            'home',
            'dashboard',
            'wallet',
            'transfer_wallet',
            'saving_goal',
            'recurring',
            'pemasukan',
            'pengeluaran',
            'kategori',
            'hutang',
            'piutang',
            'laporan',
            'profile',
            'pengguna',
            'audit_log',
        ];
    }
}

if (!function_exists('clean_module_url')) {
    function clean_module_url($module, $queryParams = [])
    {
        $module = trim((string) $module);
        if (!in_array($module, cashflow_allowed_clean_modules(), true)) {
            return 'home';
        }

        if (!is_array($queryParams)) {
            $queryParams = [];
        }

        unset($queryParams['module']);
        $queryParams = array_filter($queryParams, static function ($value) {
            return $value !== null && $value !== '';
        });

        $queryString = http_build_query($queryParams);

        return $queryString === '' ? $module : $module . '?' . $queryString;
    }
}

if (!function_exists('normalize_cashflow_redirect_url')) {
    function normalize_cashflow_redirect_url($redirect)
    {
        $redirect = trim((string) $redirect);
        if ($redirect === '') {
            return './';
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $redirect) || strpos($redirect, '//') === 0) {
            return $redirect;
        }

        $parts = parse_url($redirect);
        if (!is_array($parts)) {
            return $redirect;
        }

        $path = ltrim((string) ($parts['path'] ?? ''), './');
        if (strtolower($path) !== 'main.php') {
            return $redirect;
        }

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        $module = $queryParams['module'] ?? '';
        if (!in_array((string) $module, cashflow_allowed_clean_modules(), true)) {
            return $redirect;
        }

        $cleanUrl = clean_module_url($module, $queryParams);
        if (!empty($parts['fragment'])) {
            $cleanUrl .= '#' . $parts['fragment'];
        }

        return $cleanUrl;
    }
}

if (!function_exists('cashflow_redirect_prefix_for_current_script')) {
    function cashflow_redirect_prefix_for_current_script()
    {
        $scriptDir = basename(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

        return $scriptDir === 'actions' ? '../' : '';
    }
}

if (!function_exists('cashflow_adjust_redirect_for_current_script')) {
    function cashflow_adjust_redirect_for_current_script($redirect)
    {
        $redirect = trim((string) $redirect);
        if ($redirect === '') {
            return $redirect;
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $redirect) || strpos($redirect, '//') === 0) {
            return $redirect;
        }

        if (
            strpos($redirect, '/') === 0 ||
            strpos($redirect, './') === 0 ||
            strpos($redirect, '../') === 0 ||
            strpos($redirect, '#') === 0
        ) {
            return $redirect;
        }

        $prefix = cashflow_redirect_prefix_for_current_script();

        return $prefix === '' ? $redirect : $prefix . $redirect;
    }
}

if (!function_exists('redirect_with_sweetalert_flash')) {
    function redirect_with_sweetalert_flash($title, $text, $icon = 'info', $redirect = '')
    {
        $redirect = cashflow_adjust_redirect_for_current_script(normalize_cashflow_redirect_url($redirect));

        set_sweetalert_flash($title, $text, $icon);

        if (!headers_sent()) {
            header('Location: ' . $redirect);
            exit;
        }

        $redirectJson = json_encode($redirect, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "<script>window.location.href = {$redirectJson};</script>";
        exit;
    }
}

if (!function_exists('show_sweetalert_and_redirect')) {
    function show_sweetalert_and_redirect($title, $text, $icon = 'info', $redirect = '')
    {
        redirect_with_sweetalert_flash($title, $text, $icon, $redirect);
    }
}

if (!function_exists('render_sweetalert_flash_script')) {
    function render_sweetalert_flash_script()
    {
        ensure_cashflow_session();

        $flash = null;
        if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        } elseif (isset($_SESSION['flash_login_success']) && is_array($_SESSION['flash_login_success'])) {
            $flash = $_SESSION['flash_login_success'];
            unset($_SESSION['flash_login_success']);
        }

        if (!is_array($flash)) {
            return;
        }

        $flashTitle = json_encode((string) ($flash['title'] ?? 'Informasi'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $flashText = json_encode((string) ($flash['text'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $flashIcon = json_encode((string) ($flash['icon'] ?? 'info'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: <?= $flashTitle ?>,
                    text: <?= $flashText ?>,
                    icon: <?= $flashIcon ?>,
                    confirmButtonColor: '#0ea5e9'
                });
            }
        });
        </script>
        <?php
    }
}
