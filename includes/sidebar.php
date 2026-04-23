<?php
$module = $_GET['module'] ?? 'home';
$isAdmin = strtolower($_SESSION['role'] ?? '') === 'admin';
?>
<aside
    class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3 bg-gradient-faded-info"
    id="sidenav-main">
    <div class="sidenav-header">
        <i class="fas fa-times p-3 cursor-pointer text-white opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
            aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="main.php?module=home">
            <img src="assets/img/logocv.jpg" class="navbar-brand-img h-100" alt="main_logo">
            <span class="ms-1 font-weight-bold text-white" translate="no" style="font-size: 12px;">CashFlow
                Control</span>
        </a>
    </div>
    <hr class="horizontal light mt-0 mb-2">
    <div class="collapse navbar-collapse w-auto h-auto" id="sidenav-collapse-main">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link text-white <?php if (in_array($module, ['home', 'dashboard'], true)) {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=home">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <?php if (!$isAdmin) { ?>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Transaksi</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'pemasukan') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=pemasukan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Pemasukan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'pengeluaran') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=pengeluaran">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">receipt_long</i>
                    </div>
                    <span class="nav-link-text ms-1">Pengeluaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'kategori') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=kategori">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">category</i>
                    </div>
                    <span class="nav-link-text ms-1">Kategori</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'hutang') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=hutang">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">table_view</i>
                    </div>
                    <span class="nav-link-text ms-1">Utang</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'piutang') {
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
                <a class="nav-link text-white <?php if ($module == 'laporan') {
													echo 'active bg-gradient-warning';
												} ?>" href="main.php?module=laporan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="material-icons opacity-10" translate="no">print</i>
                    </div>
                    <span class="nav-link-text ms-1">Cetak Laporan</span>
                </a>
            </li>
            <?php } ?>

            <?php if ($isAdmin) { ?>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Admin</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'pengguna') {
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
