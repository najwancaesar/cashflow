<?php

date_default_timezone_set('Asia/Jakarta');

$server = 'localhost';
$user = 'root';
$password = '';
$db = 'cashflow';



$con = new mysqli($server, $user, $password, $db);

if ($con->connect_error) {

    die('Koneksi Database gagal :' . $con->connect_error);

}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+07:00'");
