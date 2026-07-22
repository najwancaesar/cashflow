<?php
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/ui_helper.php";
include_once __DIR__ . "/../includes/wallet_type_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];
$walletCustomTypeMap = cashflow_get_wallet_custom_type_map($con, $userYangSedangLogin);
$today = date('Y-m-d');

function format_piutang_due_date($value)
{
	if (empty($value) || $value === '0000-00-00') {
		return 'Tanpa jatuh tempo';
	}

	$timestamp = strtotime((string) $value);
	if ($timestamp === false) {
		return 'Tanpa jatuh tempo';
	}

	return date('d M Y', $timestamp);
}

function piutang_due_badge($status, $dueDate, $today)
{
	if ((string) $status === 'selesai') {
		return ['label' => 'Selesai', 'class' => 'bg-gradient-success'];
	}

	if (empty($dueDate) || $dueDate === '0000-00-00') {
		return ['label' => 'Tidak Ada Jatuh Tempo', 'class' => 'bg-gradient-secondary'];
	}

	if ($dueDate < $today) {
		return ['label' => 'Terlambat', 'class' => 'bg-gradient-danger'];
	}

	if ($dueDate === $today) {
		return ['label' => 'Jatuh Tempo Hari Ini', 'class' => 'bg-gradient-warning'];
	}

	return ['label' => 'Belum Jatuh Tempo', 'class' => 'bg-gradient-info'];
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$activeWallets = [];
$stmtWallet = $con->prepare("SELECT id_wallet, nama_wallet, tipe_wallet, is_default
	FROM wallet
	WHERE user_id = ? AND is_active = 1
	ORDER BY is_default DESC, nama_wallet ASC");
$stmtWallet->bind_param("i", $userYangSedangLogin);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
while ($walletRow = $walletResult ? $walletResult->fetch_assoc() : null) {
	$activeWallets[] = $walletRow;
}
$stmtWallet->close();
$hasActiveWallet = !empty($activeWallets);

$stmtPiutang = $con->prepare("SELECT piutang.*, user.nama,
		wallet.nama_wallet AS wallet_penerimaan_nama,
		wallet.tipe_wallet AS wallet_penerimaan_tipe,
		pemasukan.id_pemasukan AS linked_pemasukan_id
	FROM piutang
	INNER JOIN user ON piutang.user = user.id_user
	LEFT JOIN wallet ON piutang.id_wallet_penerimaan = wallet.id_wallet AND wallet.user_id = piutang.user
	LEFT JOIN pemasukan ON piutang.id_pemasukan = pemasukan.id_pemasukan AND pemasukan.user = piutang.user
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
					<p class="cashflow-feature-description">Catat dana yang harus diterima. Pelunasan piutang akan membuat pemasukan pada wallet penerimaan yang dipilih.</p>
					<div class="cashflow-table-page-actions">
						<button type="button" class="btn btn-secondary mb-0" data-bs-toggle="modal"
							data-bs-target="#modalTambah">
							<i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Transaksi
						</button>
					</div>
					<div class="table-responsive cashflow-table-scroll p-4 mx-2">
						<table class="table align-items-center mb-0 cashflow-responsive-data cashflow-table-lg" id="datatable">
							<thead>
								<tr>
									<th>
										Tanggal</th>
									<th>
										Debitur
									</th>
									<th>Jumlah Piutang</th>
									<th class="cashflow-long-text-col">
										Catatan
									</th>
									<th>Jatuh Tempo</th>
									<th>User</th>
									<th>Status</th>
									<th class="cashflow-action-col">Aksi</th>
								</tr>
							</thead>
							<tbody>
							<?php
							$no = 1;
							while ($row = mysqli_fetch_array($sql)) {
								$dueBadge = piutang_due_badge($row['status'] ?? '', $row['tanggal_jatuh_tempo'] ?? '', $today);
							?>

								<tr>
								<td class="align-middle text-center" data-order="<?= htmlspecialchars((string) $row['tanggal'], ENT_QUOTES, 'UTF-8') ?>">
										<span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?></span>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
								<td data-order="<?= htmlspecialchars((string) ($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
									<p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars(cashflow_format_rupiah($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
										</p>
									</td>
									<td class="cashflow-long-text-col">
										<p class="text-xs text-secondary mb-0 cashflow-long-text"><?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
								<td data-order="<?= htmlspecialchars((string) ($row['tanggal_jatuh_tempo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
									<p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_piutang_due_date($row['tanggal_jatuh_tempo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td class="align-middle text-center text-sm">
										<?php if (($row['status'] ?? '') === 'selesai') { ?>
											<span class="badge badge-sm bg-gradient-success" style="cursor:pointer;"
												data-piutang-revert="true"
												data-id="<?= (int) $row['id_piutang'] ?>"
												data-debitur="<?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?>"
												title="Klik untuk batalkan pelunasan"
											>Selesai ↩</span>
											<?php if (!empty($row['tanggal_lunas']) || !empty($row['wallet_penerimaan_nama'])) { ?>
												<small class="d-block text-xs text-secondary mt-1">
													Diterima ke
													<strong><?= htmlspecialchars($row['wallet_penerimaan_nama'] ?? 'Wallet: -', ENT_QUOTES, 'UTF-8') ?></strong>
												<?php if (!empty($row['id_wallet_penerimaan'])) { ?>
													(<?= cashflow_wallet_type_inline_html(cashflow_wallet_type_meta_for_wallet($row['wallet_penerimaan_tipe'], $row['id_wallet_penerimaan'], $walletCustomTypeMap)) ?>)
												<?php } ?>
													<?php if (!empty($row['tanggal_lunas'])) { ?>
														pada <?= htmlspecialchars(format_piutang_due_date($row['tanggal_lunas']), ENT_QUOTES, 'UTF-8') ?>
													<?php } ?>
												</small>
											<?php } ?>
										<?php } else { ?>
											<button type="button"
												class="badge badge-sm <?= htmlspecialchars($dueBadge['class'], ENT_QUOTES, 'UTF-8') ?> border-0 text-white btnlunaspiutang"
												data-bs-toggle="modal"
												data-bs-target="#modalLunasPiutang"
												data-id="<?= (int) $row['id_piutang'] ?>"
												data-debitur="<?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?>"
											data-jumlah="<?= htmlspecialchars(cashflow_format_rupiah($row['jumlah'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
												<?= !$hasActiveWallet ? 'disabled' : '' ?>>
												<?= htmlspecialchars($dueBadge['label'], ENT_QUOTES, 'UTF-8') ?>
											</button>
											<?php if (!$hasActiveWallet) { ?>
												<small class="d-block text-xs text-danger mt-1">Buat/aktifkan wallet terlebih dahulu.</small>
											<?php } ?>
										<?php } ?>
									</td>
										<td class="align-middle cashflow-action-col">
											<div class="cashflow-action-group">
											<?php
											$isLunasRow = ($row['status'] ?? '') === 'selesai' && !empty($row['id_pemasukan']);
											$deleteTitle = $isLunasRow
												? 'Hapus piutang lunas ini?'
												: 'Hapus data piutang ini?';
											$deleteText = $isLunasRow
												? 'PERHATIAN: Menghapus piutang yang sudah lunas akan IKUT menghapus catatan pemasukan terkait dan mengurangi saldo wallet. Tindakan ini tidak bisa dibatalkan.'
												: 'Data piutang yang dihapus tidak bisa dikembalikan.';
											?>
											<form action="actions/aksi_piutang.php?act=h" method="post" class="d-inline">
												<?= csrf_input() ?>
												<input type="hidden" name="id_piutang" value="<?= (int) $row['id_piutang'] ?>">
												<button type="submit"
													data-confirm="true"
													data-confirm-title="<?= htmlspecialchars($deleteTitle, ENT_QUOTES, 'UTF-8') ?>"
													data-confirm-text="<?= htmlspecialchars($deleteText, ENT_QUOTES, 'UTF-8') ?>"
													data-confirm-confirm-text="Ya, hapus"
													data-confirm-cancel-text="Batal"
													class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0"
												title="Hapus" aria-label="Hapus">
													<i class="fa fa-trash" aria-hidden="true"></i>
												</button>
											</form>

										<a href="#" role="button"
											data-id="<?= (int) $row['id_piutang'] ?>"
											data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8') ?>"
											data-debitur="<?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?>"
											data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES, 'UTF-8') ?>"
											data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES, 'UTF-8') ?>"
											data-jatuh_tempo="<?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
											class="text-secondary text-warning font-weight-bold text-xs btneditpiutang"
											title="Edit piutang" aria-label="Edit piutang">
											<i class="fa fa-pencil" aria-hidden="true"></i>
										</a>
										</div>
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
			<form action="actions/aksi_piutang.php?act=t" method="post">
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
						<label>Tanggal Jatuh Tempo</label>
						<div class="input-group input-group-outline">
							<input type="date" name="tanggal_jatuh_tempo" id="tanggal_jatuh_tempo" class="form-control">
						</div>
					</div>
					<div class="row my-3">
						<label>Debitur</label>
						<div class="input-group input-group-outline">
							<input type="text" name="debitur" id="debitur" class="form-control" maxlength="100" required>
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
							<textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3" maxlength="1000"></textarea>
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

<!-- Modal Pelunasan Piutang -->
<div class="modal fade" id="modalLunasPiutang" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
	aria-labelledby="modalLunasPiutangLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<form action="actions/aksi_piutang.php?act=l" method="post">
				<?= csrf_input() ?>
				<div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
					<div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
						<h6 class="modal-title text-white text-capitalize ps-3" id="modalLunasPiutangLabel">Lunasi Piutang</h6>
						<button type="button" class="btn-close me-2" data-bs-dismiss="modal"
							aria-label="Close"></button>
					</div>
				</div>
				<div class="modal-body">
					<input type="hidden" name="id_piutang" id="lunas_id_piutang">
					<p class="text-sm text-secondary mb-3" id="lunas_piutang_info">Pilih wallet penerimaan untuk melunasi piutang.</p>
					<div class="row my-3">
						<label>Wallet Penerimaan</label>
						<div class="input-group input-group-outline">
							<select name="id_wallet_penerimaan" id="id_wallet_penerimaan" class="form-control" required>
								<option value="">Pilih wallet</option>
								<?php foreach ($activeWallets as $wallet) { ?>
									<option value="<?= (int) $wallet['id_wallet'] ?>">
										<?= htmlspecialchars($wallet['nama_wallet'], ENT_QUOTES, 'UTF-8') ?>
										(<?= htmlspecialchars(cashflow_wallet_type_text(cashflow_wallet_type_meta_from_row($wallet, $walletCustomTypeMap)), ENT_QUOTES, 'UTF-8') ?>)
										<?= (int) ($wallet['is_default'] ?? 0) === 1 ? ' - Default' : '' ?>
									</option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="row my-3">
						<label>Tanggal Lunas</label>
						<div class="input-group input-group-outline">
							<input type="date" name="tanggal_lunas" id="tanggal_lunas_piutang" value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>" class="form-control" required>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="submit"
						class="btn btn-info"
						data-confirm="true"
						data-confirm-title="Lunasi piutang?"
						data-confirm-text="Sistem akan membuat pemasukan otomatis ke wallet yang dipilih."
						data-confirm-confirm-text="Ya, lunasi"
						data-confirm-cancel-text="Batal">Lunasi</button>
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
			columnDefs: [
				{ targets: -1, orderable: false, searchable: false }
			],
			order: [[0, 'desc']],
			dom: '<"cashflow-datatable-top"l<"input-group input-group-outline"f>>rt<"cashflow-datatable-bottom"ip><"clear">'
		});

		$(document).on("click", ".btneditpiutang", function() {
			$('#tanggal_jatuh_tempo').val($(this).attr("data-jatuh_tempo") || '');
		});

		$(document).on("click", 'button[data-bs-target="#modalTambah"]', function() {
			$('#tanggal_jatuh_tempo').val('');
		});

		$(document).on("click", ".btnlunaspiutang", function() {
			$('#lunas_id_piutang').val($(this).attr("data-id"));
			$('#tanggal_lunas_piutang').val('<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>');
			$('#lunas_piutang_info').text('Lunasi piutang dari ' + ($(this).attr("data-debitur") || '-') + ' sebesar ' + ($(this).attr("data-jumlah") || 'Rp. 0') + '.');
		});

		// Handler klik badge "Selesai ↩" — batalkan pelunasan piutang
		$(document).on("click", "[data-piutang-revert]", function() {
			var id = $(this).attr("data-id");
			var debitur = $(this).attr("data-debitur") || '-';
			if (!id) return;
			Swal.fire({
				title: 'Batalkan pelunasan piutang?',
				html: 'Membatalkan pelunasan piutang dari <strong>' + debitur + '</strong> akan <strong>menghapus catatan pemasukan terkait</strong> dan mengurangi saldo wallet penerimaan.<br><br>Status piutang akan kembali ke <em>Pending</em>.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#d33',
				cancelButtonColor: '#6c757d',
				confirmButtonText: 'Ya, batalkan pelunasan',
				cancelButtonText: 'Tidak'
			}).then(function(result) {
				if (result.isConfirmed) {
					$('#revert-piutang-id').val(id);
					$('#form-revert-piutang').submit();
				}
			});
		});
	});
</script>

<!-- Form tersembunyi untuk revert status piutang -->
<form id="form-revert-piutang" action="actions/aksi_piutang.php?act=revert_status" method="post" style="display:none;">
	<?= csrf_input() ?>
	<input type="hidden" name="id_piutang" id="revert-piutang-id" value="">
</form>
