<?php

$server = 'localhost';
$user = 'root';
$password = '';
$db = 'db_transaksi';



$con = new mysqli($server, $user, $password, $db);

if ($con->connect_error) {

    die('Koneksi Database gagal :' . $con->connect_error);

}