<?php 
session_start();
if(!isset($_SESSION['nama'])){
    header('Location: ./');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include "includes/header.php" ?>
</head>

<body class="g-sidenav-show  bg-gray-200">
    <?php include "includes/sidebar.php" ?>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
        <!-- Navbar -->
        <?php include "includes/navbar.php" ?>

        <!-- End Navbar -->
        <script src="assets/js/jquery.js"></script>
        <?php include "includes/content.php" ?>

    </main>
    <!--   Core JS Files   -->
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="assets/js/plugins/chartjs.min.js"></script>
    <script src="assets/vendor/daterangepicker/moment.min.js"></script>
    <script src="assets/vendor/daterangepicker/daterangepicker.js"></script>
    <script src="assets/vendor/datatables/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
        var options = {
            damping: '0.5'
        }
        Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
    </script>
    <!-- Control Center for Material Dashboard: parallax effects, scripts for the example pages etc -->
    <script src="assets/js/material-dashboard.min.js?v=3.0.0"></script>

    <script>
    $(document).on("click", ".btneditpemasukan", function() {
        $('#modalTambah').modal('show');
        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#status').val($(this).attr("data-status"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_pemasukan').val($(this).attr("data-id"));
    });

    $(document).on("click", ".btneditpengeluaran", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_pengeluaran').val($(this).attr("data-id"));
    });

    $(document).on("click", ".btnedithutang", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#kreditur').val($(this).attr("data-kreditur"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_hutang').val($(this).attr("data-id"));
    });

    $(document).on("click", ".btneditpiutang", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#debitur').val($(this).attr("data-debitur"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_piutang').val($(this).attr("data-id"));
    });

    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('[data-confirm="true"]');

        if (!trigger) {
            return;
        }

        event.preventDefault();

        const title = trigger.getAttribute('data-confirm-title') || 'Lanjutkan aksi ini?';
        const text = trigger.getAttribute('data-confirm-text') || 'Pastikan keputusan ini sudah benar.';
        const icon = trigger.getAttribute('data-confirm-icon') || 'warning';
        const confirmText = trigger.getAttribute('data-confirm-confirm-text') || 'Ya, lanjutkan';
        const cancelText = trigger.getAttribute('data-confirm-cancel-text') || 'Batal';
        const href = trigger.getAttribute('href');
        const formSelector = trigger.getAttribute('data-confirm-form');

        const continueAction = function() {
            if (href) {
                window.location.href = href;
                return;
            }

            const form = formSelector ? document.querySelector(formSelector) : trigger.closest('form');

            if (form) {
                form.submit();
            }
        };

        if (typeof Swal === 'undefined') {
            const fallbackMessage = text ? title + "\n\n" + text : title;
            if (window.confirm(fallbackMessage)) {
                continueAction();
            }
            return;
        }

        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: '#0ea5e9',
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            focusCancel: true
        }).then(function(result) {
            if (result.isConfirmed) {
                continueAction();
            }
        });
    });
    </script>
    <?php
    include("./includes/footer.php");
    ?>
</body>

</html>
