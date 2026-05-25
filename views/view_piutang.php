<?php
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/csrf_helper.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];
$today = date('Y-m-d');

function format_piutang_due_date($value)
{
	if (empty($value) || $value === '0000-00-00') {
		return '-';
	}

	$timestamp = strtotime((string) $value);
	if ($timestamp === false) {
		return '-';
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
									<th>Jatuh Tempo</th>
									<th>Status Jatuh Tempo</th>
									<th>User</th>
									<th>Status</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
							<?php
							$no = 1;
							while ($row = mysqli_fetch_array($sql)) {
								$dueBadge = piutang_due_badge($row['status'] ?? '', $row['tanggal_jatuh_tempo'] ?? '', $today);
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
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_piutang_due_date($row['tanggal_jatuh_tempo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td class="align-middle text-center text-sm">
										<span class="badge badge-sm <?= htmlspecialchars($dueBadge['class'], ENT_QUOTES, 'UTF-8') ?>">
											<?= htmlspecialchars($dueBadge['label'], ENT_QUOTES, 'UTF-8') ?>
										</span>
									</td>
									<td>
										<p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8') ?></p>
									</td>
									<td class="align-middle text-center text-sm">
										<?php if (($row['status'] ?? '') === 'selesai') { ?>
											<span class="badge badge-sm bg-gradient-success">Selesai</span>
											<?php if (!empty($row['tanggal_lunas']) || !empty($row['wallet_penerimaan_nama'])) { ?>
												<small class="d-block text-xs text-secondary mt-1">
													Diterima ke
													<strong><?= htmlspecialchars($row['wallet_penerimaan_nama'] ?? 'Wallet: -', ENT_QUOTES, 'UTF-8') ?></strong>
													<?php if (!empty($row['wallet_penerimaan_tipe'])) { ?>
														(<?= htmlspecialchars($row['wallet_penerimaan_tipe'], ENT_QUOTES, 'UTF-8') ?>)
													<?php } ?>
													<?php if (!empty($row['tanggal_lunas'])) { ?>
														pada <?= htmlspecialchars(format_piutang_due_date($row['tanggal_lunas']), ENT_QUOTES, 'UTF-8') ?>
													<?php } ?>
												</small>
											<?php } ?>
										<?php } else { ?>
											<button type="button"
												class="badge badge-sm bg-gradient-warning border-0 text-white btnlunaspiutang"
												data-bs-toggle="modal"
												data-bs-target="#modalLunasPiutang"
												data-id="<?= (int) $row['id_piutang'] ?>"
												data-debitur="<?= htmlspecialchars($row['debitur'], ENT_QUOTES, 'UTF-8') ?>"
												data-jumlah="Rp. <?= htmlspecialchars(number_format((float) ($row['jumlah'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
												<?= !$hasActiveWallet ? 'disabled' : '' ?>>
												Pending
											</button>
											<?php if (!$hasActiveWallet) { ?>
												<small class="d-block text-xs text-danger mt-1">Buat/aktifkan wallet terlebih dahulu.</small>
											<?php } ?>
										<?php } ?>
									</td>
									<td class="align-middle">
										<form action="actions/aksi_piutang.php?act=h" method="post" class="d-inline">
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
											data-jatuh_tempo="<?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
										(<?= htmlspecialchars($wallet['tipe_wallet'], ENT_QUOTES, 'UTF-8') ?>)
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
			dom: ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">'
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
	});
</script>
