<?php

// Bagian Home

if (isset($_SESSION['nama'])) {
    if ($_SESSION['role'] == 'admin') {
        if ($_GET['module'] == 'home') {
            include "view_home.php";
        } else if ($_GET['module'] == 'pemasukan') {
            include "view_pemasukan.php";
        } else if ($_GET['module'] == 'pengeluaran') {
            include "view_pengeluaran.php";
        } else if ($_GET['module'] == 'hutang') {
            include "view_hutang.php";
        } else if ($_GET['module'] == 'piutang') {
            include "view_piutang.php";
        } else if ($_GET['module'] == 'laporan') {
            include "view_laporan.php";
        } else if ($_GET['module'] == 'pengguna') {
            include "view_pengguna.php";
        } else if ($_GET['module'] == 'profile') {
            include "view_profile.php";
        } else if ($_GET['module'] == 'laporan') {
            include "view_laporan.php";
        } else {
            include "view_home.php";
        }
    } else if ($_SESSION['role'] == 'dosen') {
        if ($_GET['module'] == 'home') {
            include "view_home.php";
        } else if ($_GET['module'] == 'pemasukan') {
            include "view_pemasukan.php";
        } else if ($_GET['module'] == 'pengeluaran') {
            include "view_pengeluaran.php";
        } else if ($_GET['module'] == 'hutang') {
            include "view_hutang.php";
        } else if ($_GET['module'] == 'piutang') {
            include "view_piutang.php";
        } else if ($_GET['module'] == 'laporan') {
            include "view_laporan.php";
        } else if ($_GET['module'] == 'profile') {
            include "view_profile.php";
        } else {
            include "view_home.php";
        }
    } else if ($_SESSION['role'] == 'mahasiswa') {
        if ($_GET['module'] == 'home') {
            include "view_home.php";
        } else if ($_GET['module'] == 'pemasukan') {
            include "view_pemasukan.php";
        } else if ($_GET['module'] == 'pengeluaran') {
            include "view_pengeluaran.php";
        } else if ($_GET['module'] == 'hutang') {
            include "view_hutang.php";
        } else if ($_GET['module'] == 'piutang') {
            include "view_piutang.php";
        } else if ($_GET['module'] == 'laporan') {
            include "view_laporan.php";
        } else if ($_GET['module'] == 'profile') {
            include "view_profile.php";
        } else {
            include "view_home.php";
        }
    }
} else {

    if ($_GET['module'] == 'login') {

        include "view_login.php";
    } else {

        echo "<script>window.location=(href='./')</script>";
    }
}