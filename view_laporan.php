<?php
include "includes/koneksi.php";

$userYangSedangLogin = (int) ($_SESSION['id_user'] ?? 0);

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$kategoriOptions = [
    'pemasukan' => [],
    'pengeluaran' => [],
];
$reportLogoPath = 'assets/img/logocv.jpg';
$hasReportLogo = is_file(__DIR__ . '/' . $reportLogoPath);

if ($userYangSedangLogin > 0) {
    $kategoriQuery = "SELECT id_kategori, nama_kategori, tipe_kategori
                      FROM kategori
                      WHERE user_id = ?
                      ORDER BY tipe_kategori ASC, nama_kategori ASC";
    $kategoriStmt = mysqli_prepare($con, $kategoriQuery);
    mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
    mysqli_stmt_execute($kategoriStmt);
    $kategoriResult = mysqli_stmt_get_result($kategoriStmt);

    while ($row = mysqli_fetch_assoc($kategoriResult)) {
        $tipeKategori = $row['tipe_kategori'];
        if (isset($kategoriOptions[$tipeKategori])) {
            $kategoriOptions[$tipeKategori][] = [
                'id_kategori' => (int) $row['id_kategori'],
                'nama_kategori' => $row['nama_kategori'],
            ];
        }
    }

    mysqli_stmt_close($kategoriStmt);
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-end">
        <div class="col-6">
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-10 col-md-8">

            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Laporan Transaksi</h6>
                    </div>
                </div>
                <div class="card-body px-2 pb-2">
                    <div class="report-form-brand mx-3 mb-4">
                        <?php if ($hasReportLogo) { ?>
                            <img src="<?= htmlspecialchars($reportLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="CashFlow Control" class="report-form-logo">
                        <?php } ?>
                        <div>
                            <p class="report-form-title mb-1">CASHFLOW CONTROL</p>
                            <p class="report-form-subtitle mb-0">Laporan transaksi pribadi</p>
                        </div>
                    </div>
                    <form method="POST" action="tcpdf/examples/laprekap.php" target="_blank" id="formLaporan">
                        <input type="hidden" name="output" id="output" value="print">
                        <div class="row">
                            <div class="col-sm-6 text-center">Laporan Transaksi</div>
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tabel"
                                        id="pemasukan" value="pemasukan" checked>
                                    <label class="form-check-label" for="pemasukan">
                                        Pemasukan
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tabel"
                                        id="pengeluaran" value="pengeluaran">
                                    <label class="form-check-label" for="pengeluaran">
                                        Pengeluaran
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tabel"
                                        id="hutang" value="hutang">
                                    <label class="form-check-label" for="hutang">
                                        Utang
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tabel"
                                        id="piutang" value="piutang">
                                    <label class="form-check-label" for="piutang">
                                        Piutang
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-sm-6 text-center">
                                Tanggal
                            </div>
                            <div class="col-sm-5">
                                <div class="input-group input-group-outline">
                                    <input
                                        type="text"
                                        class="form-control text-center report-date-range"
                                        name="tanggal"
                                        id="tanggal"
                                        readonly
                                        autocomplete="off"
                                        aria-label="Pilih rentang tanggal laporan"
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-sm-6 text-center">
                                Kategori
                            </div>
                            <div class="col-sm-5">
                                <div class="input-group input-group-outline">
                                    <select class="form-control" name="id_kategori" id="id_kategori">
                                        <option value="">Semua kategori</option>
                                    </select>
                                </div>
                                <small class="text-secondary d-block mt-2" id="kategori-help">
                                    Filter kategori tersedia untuk laporan pemasukan dan pengeluaran.
                                </small>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="d-flex justify-content-center flex-wrap gap-2 my-4 mb-2">
                                <button type="submit" name="cetak" class="btn bg-gradient-info mb-0 report-action" data-output="print">
                                    <i class="fa fa-print" aria-hidden="true"></i>
                                    Preview & Cetak
                                </button>
                                <button type="submit" class="btn btn-outline-info mb-0 report-action" data-output="pdf">
                                    <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                                    Download PDF
                                </button>
                                <button type="submit" class="btn btn-outline-secondary mb-0 report-action" data-output="csv">
                                    <i class="fa fa-download" aria-hidden="true"></i>
                                    Download CSV
                                </button>
                            </div>
                            <small class="text-secondary d-block">
                                Gunakan preview untuk melihat laporan di tab baru, atau download langsung dalam format PDF dan CSV.
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    #tanggal.report-date-range[readonly] {
        cursor: pointer;
        background-color: #fff;
    }

    #tanggal.report-date-range[readonly]:focus {
        cursor: pointer;
    }

    .report-form-brand {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.22);
        border-radius: 0.85rem;
        background: #f8fafc;
    }

    .report-form-logo {
        width: 46px;
        height: 46px;
        border-radius: 0.75rem;
        object-fit: cover;
    }

    .report-form-title {
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    .report-form-subtitle {
        color: #64748b;
        font-size: 0.82rem;
    }
</style>
<script>
    $(function() {
        var start = moment().subtract(29, 'days');
        var end = moment();
        var kategoriOptions = <?= json_encode($kategoriOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function cb(start, end) {
            $('#tanggal').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
        }

        function updateKategoriFilter() {
            var selectedTable = $('input[name="tabel"]:checked').val();
            var categorySelect = $('#id_kategori');
            var categoryHelp = $('#kategori-help');
            var options = kategoriOptions[selectedTable] || [];

            categorySelect.empty();

            if (selectedTable !== 'pemasukan' && selectedTable !== 'pengeluaran') {
                categorySelect.append('<option value="">Tidak menggunakan kategori</option>');
                categorySelect.prop('disabled', true);
                categoryHelp.text('Filter kategori hanya tersedia untuk laporan pemasukan dan pengeluaran.');
                return;
            }

            categorySelect.append('<option value="">Semua kategori</option>');
            $.each(options, function(_, option) {
                categorySelect.append(
                    $('<option></option>')
                    .val(option.id_kategori)
                    .text(option.nama_kategori)
                );
            });

            categorySelect.prop('disabled', false);

            if (options.length === 0) {
                categoryHelp.text('Belum ada kategori ' + selectedTable + '. Laporan tetap bisa dicetak tanpa filter kategori.');
            } else {
                categoryHelp.text('Pilih kategori tertentu atau biarkan "Semua kategori" untuk melihat seluruh data.');
            }
        }

        $('#tanggal').daterangepicker({
            startDate: start,
            endDate: end,
            locale: {
                format: 'YYYY-MM-DD'
            },
            ranges: {
                'Hari ini': [moment(), moment()],
                'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                '7 hari terakhir': [moment().subtract(6, 'days'), moment()],
                '30 hari terakhir': [moment().subtract(29, 'days'), moment()],
                'Bulan ini': [moment().startOf('month'), moment().endOf('month')],
                'Bulan lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'Tahun ini': [moment().startOf('year'), moment().endOf('year')],
                'Tahun lalu': [moment().subtract(1, 'year').startOf('year'), moment().endOf('year')]
            }
        }, cb);

        cb(start, end);
        updateKategoriFilter();

        $('input[name="tabel"]').on('change', function() {
            updateKategoriFilter();
        });

        $('.report-action').on('click', function() {
            $('#output').val($(this).data('output') || 'print');
        });

        $('#formLaporan').on('submit', function(e) {
            if (!$('#tanggal').val()) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tanggal belum dipilih',
                        text: 'Silakan pilih rentang tanggal terlebih dahulu.'
                    });
                } else {
                    alert('Silakan pilih rentang tanggal terlebih dahulu');
                }
            }
        });
    });
</script>
