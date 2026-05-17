<?php
include "includes/koneksi.php";

$userYangSedangLogin = (int) $_SESSION['id_user'];

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$kategoriPemasukan = [];
$kategoriQuery = "SELECT id_kategori, nama_kategori
                  FROM kategori
                  WHERE user_id = ? AND tipe_kategori = 'pemasukan'
                  ORDER BY nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);

while ($kategori = mysqli_fetch_assoc($kategoriResult)) {
    $kategoriPemasukan[] = $kategori;
}

mysqli_stmt_close($kategoriStmt);

$transaksiQuery = "SELECT pemasukan.*, user.nama, kategori.nama_kategori
                   FROM pemasukan
                   INNER JOIN user ON pemasukan.user = user.id_user
                   LEFT JOIN kategori
                       ON pemasukan.id_kategori = kategori.id_kategori
                      AND kategori.user_id = pemasukan.user
                      AND kategori.tipe_kategori = 'pemasukan'
                   WHERE user.id_user = ?
                   ORDER BY pemasukan.tanggal DESC, pemasukan.id_pemasukan DESC";
$transaksiStmt = mysqli_prepare($con, $transaksiQuery);
mysqli_stmt_bind_param($transaksiStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($transaksiStmt);
$transaksiResult = mysqli_stmt_get_result($transaksiStmt);
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
                        <h6 class="text-white text-capitalize ps-3">Pemasukan</h6>
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
                                    <th>Tanggal</th>
                                    <th>Catatan</th>
                                    <th>Kategori</th>
                                    <th>Jumlah Pemasukan</th>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($transaksiResult)) { ?>
                                    <tr>
                                        <td class="align-middle text-center">
                                            <span class="text-secondary text-xs font-weight-bold"><?= htmlspecialchars($row['tanggal']) ?></span>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['catatan']) ?></p>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0">
                                                <?= htmlspecialchars($row['nama_kategori'] ?? 'Belum dikategorikan') ?>
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">Rp. <?= number_format((float) ($row['jumlah'] ?? 0)) ?></p>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['nama']) ?></p>
                                        </td>
                                        <td class="align-middle text-center text-sm">
                                            <span
                                                class="badge badge-sm <?= ($row['status'] == 'selesai') ? 'bg-gradient-success' : 'bg-gradient-warning' ?>">
                                                <?php if ($row['status'] == 'selesai'): ?>
                                                    <?= htmlspecialchars($row['status']) ?>
                                                <?php else : ?>
                                                    <a href="aksi_pemasukan.php?act=l&id=<?= (int) $row['id_pemasukan'] ?>"
                                                        class="text-white">
                                                        <?= htmlspecialchars($row['status']) ?>
                                                    </a>
                                                <?php endif ?>
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <a href="aksi_pemasukan.php?&act=h&id=<?= (int) $row['id_pemasukan'] ?>"
                                                data-confirm="true"
                                                data-confirm-title="Hapus pemasukan ini?"
                                                data-confirm-text="Data pemasukan yang dihapus tidak bisa dikembalikan."
                                                data-confirm-confirm-text="Ya, hapus"
                                                data-confirm-cancel-text="Batal"
                                                class="text-secondary text-danger font-weight-bold text-xs">
                                                <i class="fa fa-trash" aria-hidden="true"></i>
                                            </a>

                                            <a type="submit"
                                                data-id="<?= (int) $row['id_pemasukan'] ?>"
                                                data-tanggal="<?= htmlspecialchars($row['tanggal'], ENT_QUOTES) ?>"
                                                data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                                                data-catatan="<?= htmlspecialchars($row['catatan'], ENT_QUOTES) ?>"
                                                data-jumlah="<?= htmlspecialchars($row['jumlah'], ENT_QUOTES) ?>"
                                                data-kategori="<?= htmlspecialchars((string) ($row['id_kategori'] ?? ''), ENT_QUOTES) ?>"
                                                class="text-secondary text-warning font-weight-bold text-xs btneditpemasukan">
                                                <i class="fa fa-pencil" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php mysqli_stmt_close($transaksiStmt); ?>

<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_pemasukan.php?act=t" method="post">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Pemasukan</h6>
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
                            <input type="hidden" name="id_pemasukan" id="id_pemasukan" class="form-control">
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Catatan</label>
                        <div class="input-group input-group-outline">
                            <textarea name="catatan" id="catatan" class="form-control" cols="10" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label>Kategori</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="id_kategori" id="id_kategori">
                                <option value="">Belum dikategorikan</option>
                                <?php foreach ($kategoriPemasukan as $kategori) { ?>
                                    <option value="<?= (int) $kategori['id_kategori'] ?>">
                                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php if (empty($kategoriPemasukan)) { ?>
                            <small class="text-secondary px-2 mt-1">Belum ada kategori pemasukan. Tambahkan lewat menu Kategori.</small>
                        <?php } ?>
                    </div>
                    <div class="row my-3">
                        <label>Jumlah Pemasukan</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="jumlah" id="jumlah" required class="form-control js-format-nominal" inputmode="numeric" autocomplete="off" placeholder="Contoh: 1.000.000">
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
