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

if (!function_exists('redirect_with_sweetalert_flash')) {
    function redirect_with_sweetalert_flash($title, $text, $icon = 'info', $redirect = '')
    {
        $redirect = trim((string) $redirect);
        if ($redirect === '') {
            $redirect = './';
        }

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
