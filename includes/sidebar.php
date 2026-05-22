<?php
$module = $_GET['module'] ?? 'home';
$isAdmin = strtolower($_SESSION['role'] ?? '') === 'admin';
?>
<aside
    class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-3 bg-gradient-faded-info"
    id="sidenav-main">
    <div class="sidenav-header">
        <i class="fa fa-times p-3 cursor-pointer text-white opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
            aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="home">
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
												} ?>" href="home">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-dashboard" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Dashboard</span>
                </a>
            </li>
            <?php if (!$isAdmin) { ?>
            <li class="nav-item mt-3">
                <h6 class="ps-4 ms-2 text-uppercase text-xs text-white font-weight-bolder opacity-8">Transaksi</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'wallet') {
													echo 'active bg-gradient-warning';
												} ?>" href="wallet">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-credit-card" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Wallet</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'transfer_wallet') {
													echo 'active bg-gradient-warning';
												} ?>" href="transfer_wallet">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-exchange" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Transfer Wallet</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'saving_goal') {
													echo 'active bg-gradient-warning';
												} ?>" href="saving_goal">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-bullseye" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Celengan Virtual</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'recurring') {
													echo 'active bg-gradient-warning';
												} ?>" href="recurring">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-refresh" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Transaksi Berulang</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'pemasukan') {
													echo 'active bg-gradient-warning';
												} ?>" href="pemasukan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-arrow-circle-down" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Pemasukan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'pengeluaran') {
													echo 'active bg-gradient-warning';
												} ?>" href="pengeluaran">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-arrow-circle-up" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Pengeluaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'kategori') {
													echo 'active bg-gradient-warning';
												} ?>" href="kategori">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-tags" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Kategori</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'hutang') {
													echo 'active bg-gradient-warning';
												} ?>" href="hutang">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-minus-circle" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">Utang</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php if ($module == 'piutang') {
													echo 'active bg-gradient-warning';
												} ?>" href="piutang">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-handshake-o" aria-hidden="true"></i>
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
												} ?>" href="laporan">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-file-text-o" aria-hidden="true"></i>
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
													} ?>" href="pengguna">
                    <div class="text-white text-center me-2 d-flex align-items-center justify-content-center">
                        <i class="fa fa-users" aria-hidden="true"></i>
                    </div>
                    <span class="nav-link-text ms-1">User Management</span>
                </a>
            </li>
            <?php } ?>


        </ul>
    </div>
</aside>
