<?php
include "includes/koneksi.php";
$userYangSedangLogin = $_SESSION['id_user'];
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
						<h6 class="text-white text-capitalize ps-3">pengeluaran</h6>
					</div>
				</div>
				<div class="card-body px-0 pb-2">
					<div class="text-end me-3">
						<?php if ($_SESSION['role'] == 'dosen' or $_SESSION['role'] == 'mahasiswa') : ?>
							<button type="button" class="btn btn-secondary" data-bs-toggle="modal"
								data-bs-target="#modalTambah">
								<i class="material-icons opacity-10" translate="no">add</i> Tambah Transaksi
							</button>
						<?php endif ?>
					</div>
					<div class="table-responsive p-4 mx-2">
						<table class="table align-items-center mb-0" id="datatable">
							<thead>
								<tr>
									<th>
										Tanggal</th>
									<th>
										Catatan
									</th>
									<th>
										Jumlah pengeluaran</th>
									<th>
										User</th>
									<th>
										Status
									</th>
									<th></th>
								</tr>
							</thead>
							<?php
							$sql = mysqli_query($con, "select * from pengeluaran join user on pengeluaran.user = user.id_user where user.id_user = $userYangSedangLogin");
							$no = 1;
							while ($row = mysqli_fetch_array($sql)) {
							?>

								<tr>
									<td class="align-middle text-center">
										<span class="text-secondary text-xs font-weight-bold"><?= $row['tanggal'] ?></span>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= $row['catatan'] ?></p>
									</td>
									<td>
										<p class="text-xs font-weight-bold mb-0">Rp. <?= number_format($row['jumlah']) ?>
										</p>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= $row['nama'] ?></p>
									</td>
									<td class="align-middle text-center text-sm">
                                        <span
                                            class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                            <?php if ($row['status'] == 'selesai'): ?>
                                                <?= $row['status'] ?>
                                            <?php else : ?>
                                                <a href="aksi_pengeluaran.php?act=l&id=<?php echo $row['id_pengeluaran'] ?>"
                                                    class="text-white">
                                                    <?= $row['status'] ?>
                                                </a>
                                            <?php endif ?>
                                        </span>
                                    </td>
									<td class="align-middle">
										<a href="aksi_pengeluaran.php?&act=h&id=<?php echo $row['id_pengeluaran'] ?>"
											onclick="return confirm('Hapus ?')"
											class="text-secondary text-danger font-weight-bold text-xs">
											<i class="material-icons opacity-10" translate="no">delete
											</i>
										</a>

										<a type="submit"
											data-id="<?php echo $row['id_pengeluaran'] ?>"
											data-tanggal="<?php echo $row['tanggal'] ?>"
                                            data-status="<?php echo $row['status'] ?>"
											data-catatan="<?php echo $row['catatan'] ?>"
											data-jumlah="<?php echo $row['jumlah'] ?>"
											class="text-secondary text-warning font-weight-bold text-xs btneditpengeluaran">
											<i class="material-icons fa fa edit" translate="no">edit
											</i>
										</a>
									</td>
								</tr>

							<?php
								$no++;
							}
							?>
							</tbody>
						</table>
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
			<form action="aksi_pengeluaran.php?act=t" method="post">
				<div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
					<div
						class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
						<h6 class="modal-title text-white text-capitalize ps-3">pengeluaran</h6>
						<button type="button" class="btn-close me-2" data-bs-dismiss="modal"
							aria-label="Close"></button>
					</div>
				</div>
				<div class="modal-body">
					<div class="row">
						<label class="form-label">Tanggal</label>
						<div class="input-group input-group-outline">
							<input type="date" name="tanggal" id="tanggal" class="form-control" required>
							<input type="hidden" value="<?= $_SESSION['id_user'] ?>" name="user">
							<input type="hidden" name="id_pengeluaran" id="id_pengeluaran" class="form-control">
						</div>
					</div>
					<div class="row my-3">
						<label>Catatan</label>
						<div class="input-group input-group-outline">
							<textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3"></textarea>
						</div>
					</div>
					<div class="row my-3">
						<label>Jumlah pengeluaran</label>
						<div class="input-group input-group-outline">
							<input type="number" name="jumlah" id="jumlah" required class="form-control">
						</div>
					</div>
					<div class="row my-3">
						<div class="input-group input-group-outline">
							<select class="form-control" name="status" id="status" required>
								<option value="">Pilih Status</option>
								<option value="selesai">Selesai</option>
								<option value="pending">Pending</option>
							</select>
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