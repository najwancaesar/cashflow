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
                    <form method="POST" action="tcpdf/examples/laprekap.php" target="_blank">
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
									<input type="text" class="form-control text-center" name="tanggal" id="tanggal">
								</div>
							</div>
						</div>
						<div class="text-center">
							<button type="submit" name="cetak" class="btn bg-gradient-info my-4 mb-2">
								<i class="material-icons opacity-10">print</i>
								Cetak
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	$(function() {
    var start = moment().subtract(29, 'days');
    var end = moment();

    function cb(start, end) {
        $('#tanggal').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
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
            'Tahun lalu': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
        }
    }, cb);

    cb(start, end);

    // Validasi form sebelum submit
    $('form').on('submit', function(e) {
        if (!$('#tanggal').val()) {
            e.preventDefault();
            alert('Silakan pilih rentang tanggal terlebih dahulu');
        }
    });
});

</script>