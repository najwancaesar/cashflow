<?php
include "includes/koneksi.php";
include "includes/csrf_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$stmtPiutang = $con->prepare("SELECT piutang.*, user.nama
	FROM piutang
	INNER JOIN user ON piutang.user = user.id_user
	WHERE user.id_user = ?
	ORDER BY piutang.tanggal DESC, piutang.id_piutang DESC");
$stmtPiutang->bind_param("i", $userYangSedangLogin);
$stmtPiutang->execute();
$sql = $stmtPiutang->get_result();
?>


<div class="container-fluid py-4">
	<div class="row justify-content-end">
		<div class="col-6">
		</div>
	</div>
	<div class="row">
		<div class="col-12">

			<div class="card my-4">
				<div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
					<div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
						<h6 class="text-white text-capitalize ps-3">Piutang</h6>
					</div>	
				</div>
				<div class="card-body px-0 pb-2">
					<div class="text-end me-3">
						<button type="button" class="btn btn-secondary" data-bs-toggle="modal"
							data-bs-target="#modalTambah">
							<i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
						</button>
					</div>
					<div class="table-responsive p-4 mx-2">
						<table class="table align-items-center mb-0" id="datatable">
							<thead>
								<tr>
									<th>
										Tanggal</th>
									<th>
										Debitur
									</th>
									<th>Jumlah Piutang</th>
									<th>
										Catatan
									</th>
									<th>User</th>
									<th>Status</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							<?php
							$no = 1;
							while ($row = mysqli_fetch_array($sql)) {
							?>

								<tr>
									<td class="align-middle text-center">
										<span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?></span>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td>
										<p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?>
										</p>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td class="align-middle text-center text-sm">
										<?php if (($row['status'] ?? '') === 'selesai') { ?>
											<span class="badge badge-sm bg-gradient-success">Selesai</span>
										<?php } else { ?>
											<form action="aksi_piutang.php?act=l" method="post" class="d-inline">
												<?= csrf_input() ?>
												<input type="hidden" name="id_piutang" value="<?= (int) $row['id_piutang'] ?>">
												<button type="submit"
													data-confirm="true"
													data-confirm-title="Tandai piutang selesai?"
													data-confirm-text="Status piutang akan diubah menjadi selesai."
													data-confirm-confirm-text="Ya, selesaikan"
													data-confirm-cancel-text="Batal"
													class="badge badge-sm bg-gradient-warning border-0 text-white">
													Pending
												</button>
											</form>
										<?php } ?>
									</td>
									<td class="align-middle">
										<form action="aksi_piutang.php?act=h" method="post" class="d-inline">
											<?= csrf_input() ?>
											<input type="hidden" name="id_piutang" value="<?= (int) $row['id_piutang'] ?>">
											<button type="submit"
												data-confirm="true"
												data-confirm-title="Hapus data piutang ini?"
												data-confirm-text="Data piutang yang dihapus tidak bisa dikembalikan."
												data-confirm-confirm-text="Ya, hapus"
												data-confirm-cancel-text="Batal"
												class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
												<i class="fa fa-trash" aria-hidden="true"></i>
											</button>
										</form>

										<a type="submit"
											data-id="<?= (int) $row['id_piutang'] ?>"
											data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?>"
											data-debitur="<?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?>"
											data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?>"
											data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES, 'UTF-8') ?>"
											class="text-secondary text-warning font-weight-bold text-xs btneditpiutang">
											<i class="fa fa-pencil" aria-hidden="true"></i>
										</a>
									</td>
								</tr>

							<?php
								$no++;
							}
							?>
							</tbody>
						</table>
						<?php $stmtPiutang->close(); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Modal Simpan -->
<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
	aria-labelledby="staticBackdropLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<form action="aksi_piutang.php?act=t" method="post">
				<?= csrf_input() ?>
				<div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
					<div
						class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
						<h6 class="modal-title text-white text-capitalize ps-3">piutang</h6>
						<button type="button" class="btn-close me-2" data-bs-dismiss="modal"
							aria-label="Close"></button>
					</div>
				</div>
				<div class="modal-body">
					<div class="row">
						<label class="form-label">Tanggal</label>
						<div class="input-group input-group-outline">
							<input type="date" name="tanggal" id="tanggal" class="form-control" required>
							<input type="hidden" value="<?= (int) $_SESSION['id_user'] ?>" name="user">
							<input type="hidden" name="id_piutang" id="id_piutang" class="form-control">
						</div>
					</div>
					<div class="row my-3">
						<label>Debitur</label>
						<div class="input-group input-group-outline">
							<input type="text" name="debitur" id="debitur" class="form-control">
						</div>
					</div>
					<div class="row my-3">
						<label>Jumlah piutang</label>
						<div class="input-group input-group-outline">
							<input type="text" name="jumlah" id="jumlah" required class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 750.000">
						</div>
					</div>
					<div class="row my-3">
						<label>Catatan</label>
						<div class="input-group input-group-outline">
							<textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3"></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="submit" name="simpan" class="btn btn-info">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	$(document).ready(function() {
		$('#datatable').DataTable({
			language: {
				"paginate": {
					"first": "&laquo",
					"last": "&raquo",
					"next": "&gt",
					"previous": "&lt"
				},
			},
			dom: ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">'
		});
	});
</script>
