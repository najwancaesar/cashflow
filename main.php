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

<body class="g-sidenav-show bg-gray-200">
    <?php include "includes/sidebar.php" ?>

    <main class="main-content position-relative border-radius-lg app-main-content">
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
    <style>
    body.g-sidenav-show {
        overflow-x: hidden;
    }

    .app-main-content {
        min-height: 100vh;
        max-height: none !important;
        height: auto !important;
        overflow: visible !important;
    }

    @media (min-width: 1200px) {
        body.g-sidenav-show {
            overflow-y: auto;
        }

        body.g-sidenav-show .app-main-content {
            min-height: 100vh;
        }
    }
    </style>

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
    function getNominalDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function formatNominalValue(value) {
        const digits = getNominalDigits(value);

        if (!digits) {
            return '';
        }

        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function applyNominalFormatting(input) {
        if (!input) {
            return;
        }

        const rawValue = input.value || '';
        const selectionStart = typeof input.selectionStart === 'number' ? input.selectionStart : rawValue.length;
        const digitsBeforeCursor = getNominalDigits(rawValue.slice(0, selectionStart)).length;
        const formattedValue = formatNominalValue(rawValue);

        input.value = formattedValue;

        if (typeof input.setSelectionRange === 'function') {
            let cursorPosition = formattedValue.length;

            if (digitsBeforeCursor === 0) {
                cursorPosition = 0;
            } else {
                let countedDigits = 0;
                for (let i = 0; i < formattedValue.length; i++) {
                    if (/\d/.test(formattedValue.charAt(i))) {
                        countedDigits++;
                    }

                    if (countedDigits >= digitsBeforeCursor) {
                        cursorPosition = i + 1;
                        break;
                    }
                }
            }

            input.setSelectionRange(cursorPosition, cursorPosition);
        }
    }

    function initializeNominalInputs(scope) {
        const root = scope || document;
        root.querySelectorAll('.js-format-nominal').forEach(function(input) {
            input.value = formatNominalValue(input.value);
        });
    }

    document.addEventListener('input', function(event) {
        if (event.target.classList.contains('js-format-nominal')) {
            applyNominalFormatting(event.target);
        }
    });

    document.addEventListener('submit', function(event) {
        const form = event.target;

        if (!form || typeof form.querySelectorAll !== 'function') {
            return;
        }

        form.querySelectorAll('.js-format-nominal').forEach(function(input) {
            input.value = getNominalDigits(input.value);
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        initializeNominalInputs(document);
    });

    $(document).on("click", ".btneditpemasukan", function() {
        $('#modalTambah').modal('show');
        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#status').val($(this).attr("data-status"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_kategori').val($(this).attr("data-kategori"));
        $('#id_pemasukan').val($(this).attr("data-id"));
        applyNominalFormatting(document.getElementById('jumlah'));
    });

    $(document).on("click", ".btneditpengeluaran", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#status').val($(this).attr("data-status"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_kategori').val($(this).attr("data-kategori"));
        $('#id_pengeluaran').val($(this).attr("data-id"));
        applyNominalFormatting(document.getElementById('jumlah'));
    });

    $(document).on("click", ".btneditkategori", function() {
        $('#modalTambah').modal('show');
        $('#id_kategori').val($(this).attr("data-id"));
        $('#nama_kategori').val($(this).attr("data-nama"));
        $('#tipe_kategori').val($(this).attr("data-tipe"));
    });

    $(document).on("click", ".btnedituser", function() {
        $('#modalEditUser').modal('show');
        $('#edit_user_id').val($(this).attr("data-id"));
        $('#edit_nama').val($(this).attr("data-nama"));
        $('#edit_username').val($(this).attr("data-username"));
        $('#edit_email').val($(this).attr("data-email"));
        $('#edit_no_telp').val($(this).attr("data-no_telp"));
        $('#edit_role').val($(this).attr("data-role"));
        $('#edit_is_active').val($(this).attr("data-is_active"));
    });

    $(document).on("click", ".btnresetpassworduser", function() {
        $('#modalResetPasswordUser').modal('show');
        $('#reset_user_id').val($(this).attr("data-id"));
        $('#reset_user_name').text('Atur password baru untuk ' + ($(this).attr("data-nama") || 'user terpilih') + '.');
    });

    $(document).on("click", ".btnedithutang", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#kreditur').val($(this).attr("data-kreditur"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_hutang').val($(this).attr("data-id"));
        applyNominalFormatting(document.getElementById('jumlah'));
    });

    $(document).on("click", ".btneditpiutang", function() {
        $('#modalTambah').modal('show');

        $('#tanggal').val($(this).attr("data-tanggal"));
        $('#debitur').val($(this).attr("data-debitur"));
        $('#catatan').val($(this).attr("data-catatan"));
        $('#jumlah').val($(this).attr("data-jumlah"));
        $('#id_piutang').val($(this).attr("data-id"));
        applyNominalFormatting(document.getElementById('jumlah'));
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
</body>

</html>
