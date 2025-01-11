<?php
if ($_SESSION['role'] == 'Admin') {
	$role = 1;
} elseif ($_SESSION['role'] == 'mahasiswa') {
	$role = 2;
} else {
	$role = 3;
}
?>
<aside
    class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3 bg-gradient-faded-info"
    id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-white opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
            aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="main.php?module=dashboard">
            <img src="assets/img/logocv.jpg" class="navbar-brand-img h-100" alt="main_logo">
            <span class="ms-1 font-weight-bold text-white" translate="no" style="font-size: 12px;">CashFlow
                Control</span>
        </a>
    </div>
    <hr class="horizontal light mt-0 mb-2">
    <div class="collapse navbar-collapse  w-auto h-auto max-height-vh-100" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'dashboard') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=dashboard">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Transaksi</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'pemasukan') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=pemasukan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Pemasukan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'pengeluaran') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=pengeluaran">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">receipt_long</i>
                    </div>
                    <span class="nav-link-text ms-1">Pengeluaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'hutang') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=hutang">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Utang</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'piutang') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=piutang">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">receipt_long</i>
                    </div>
                    <span class="nav-link-text ms-1">Piutang</span>
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Laporan</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'laporan') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=laporan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">print</i>
                    </div>
                    <span class="nav-link-text ms-1">Cetak Laporan</span>
                </a>
            </li>

            <?php if ($_SESSION['role'] == 'admin') { ?>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Pengaturan</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($_GET['module'] == 'pengguna' || $_GET['module'] == 'profile') {
														echo 'active bg-gradient-warning';
													} ?>" href="main.php?module=pengguna">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">person</i>
                    </div>
                    <span class="nav-link-text ms-1">User Management</span>
                </a>
            </li>
            <?php } ?>


        </ul>
    </div>
</aside>