<?php

date_default_timezone_set('Asia/Jakarta');

$server = 'localhost';
$user = 'root';
$password = '';
$db = 'cashflow';



$con = new mysqli($server, $user, $password, $db);

if ($con->connect_error) {

    error_log('CashFlow DB connect_error: ' . $con->connect_error);
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Layanan Tidak Tersedia</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:60px;">'
        . '<h2>Layanan Sementara Tidak Tersedia</h2>'
        . '<p>Terjadi kesalahan koneksi. Silakan coba lagi beberapa saat atau hubungi administrator.</p>'
        . '</body></html>';
    exit;

}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+07:00'");
