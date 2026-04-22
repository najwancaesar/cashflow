<?php

$server = 'localhost';
$user = 'root';
$password = '';
$db = 'cashflow';



$con = new mysqli($server, $user, $password, $db);

if ($con->connect_error) {

    die('Koneksi Database gagal :' . $con->connect_error);

}

$con->set_charset('utf8mb4');
