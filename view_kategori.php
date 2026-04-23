<?php
include "includes/koneksi.php";

if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$userYangSedangLogin = (int) $_SESSION['id_user'];

$kategoriQuery = "SELECT id_kategori, nama_kategori, tipe_kategori, created_at
                  FROM kategori
                  WHERE user_id = ?
                  ORDER BY tipe_kategori ASC, nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "i", $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Kategori Transaksi</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Kelola kategori pemasukan dan pengeluaran milik akun Anda untuk dipakai di form transaksi.
                        </p>
                        <p class="text-sm text-secondary mb-0">
                            Kategori umum disiapkan otomatis saat akun dibuat, dan Anda tetap bisa menambah kategori sendiri kapan saja.
                        </p>
                    </div>
                    <div class="text-end me-3 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalTambah">
                            <i class="material-icons opacity-10" translate="no">add</i> Tambah Kategori
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <table class="table align-items-center mb-0" id="datatable">
                            <thead>
                                <tr>
                                    <th>Nama Kategori</th>
                                    <th>Tipe</th>
                                    <th>Dibuat</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($kategoriResult)) { ?>
                                    <tr>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm <?= $row['tipe_kategori'] === 'pemasukan' ? 'bg-gradient-success' : 'bg-gradient-info' ?>">
                                                <?= htmlspecialchars(ucfirst($row['tipe_kategori'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0">
                                                <?= htmlspecialchars(date('d M Y H:i', strtotime($row['created_at']))) ?>
                                            </p>
                                        </td>
                                        <td class="align-middle">
                                            <a href="aksi_kategori.php?act=h&id=<?= (int) $row['id_kategori'] ?>"
                                                data-confirm="true"
                                                data-confirm-title="Hapus kategori ini?"
                                                data-confirm-text="Kategori yang dihapus tidak akan otomatis menghapus transaksi lama."
                                                data-confirm-confirm-text="Ya, hapus"
                                                data-confirm-cancel-text="Batal"
                                                class="text-secondary text-danger font-weight-bold text-xs">
                                                <i class="material-icons opacity-10" translate="no">delete</i>
                                            </a>

                                            <a type="button"
                                                data-id="<?= (int) $row['id_kategori'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES) ?>"
                                                data-tipe="<?= htmlspecialchars($row['tipe_kategori'], ENT_QUOTES) ?>"
                                                class="text-secondary text-warning font-weight-bold text-xs btneditkategori">
                                                <i class="material-icons fa fa edit" translate="no">edit</i>
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

<?php mysqli_stmt_close($kategoriStmt); ?>

<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_kategori.php?act=t" method="post">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Kategori</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_kategori" id="id_kategori" class="form-control">
                    <div class="row">
                        <label class="form-label">Nama Kategori</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="nama_kategori" id="nama_kategori" class="form-control" maxlength="100" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Tipe Kategori</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="tipe_kategori" id="tipe_kategori" required>
                                <option value="">Pilih Tipe</option>
                                <option value="pemasukan">Pemasukan</option>
                                <option value="pengeluaran">Pengeluaran</option>
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
