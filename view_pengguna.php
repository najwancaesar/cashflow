<?php
include "includes/koneksi.php";
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
                        <h6 class="text-white text-capitalize ps-3">Pengguna</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="text-end me-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalTambah">
                            <i class="material-icons opacity-10" translate="no">add</i> Tambah Pengguna
                        </button>
                    </div>
                    <div class="table-responsive p-4">
                        <table class="table align-items-center mb-0" id="datatable">
                            <thead>
                                <tr>
                                    <th
                                        class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        foto</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        nama
                                    </th>
                                    <th
                                        class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                        username</th>
                                    <th
                                        class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        email</th>
                                    <th
                                        class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        No_telp</th>
                                    <th
                                        class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        Status</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
								$sql = mysqli_query($con, "select * from user");
								$no = 1;
								while ($row = mysqli_fetch_array($sql)) {

								?>
                                <tr>
                                    <td class="align-middle text-center">
                                        <span class="text-secondary text-xs font-weight-bold">
                                            <?php if ($row['foto'] == ""): ?>
                                            <i class="material-icons opacity-10" translate="no">person</i>
                                            <?php else : ?>
                                            <img src="assets/img/profil/<?= $row['foto'] ?>" class="avatar avatar-sm">
                                            <?php endif ?>

                                        </span>
                                    </td>
                                    <td>
                                        <p class="text-xs text-secondary mb-0"><?= $row['nama'] ?></p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0"><?= $row['username'] ?></p>
                                    </td>
                                    <td>
                                        <p class="text-xs text-secondary mb-0"><?= $row['email'] ?></p>
                                    </td>
                                    <td>
                                        <p class="text-xs text-secondary mb-0"><?= $row['no_telp'] ?></p>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span title="Aktifkan/non aktifkan"
                                            class="badge badge-sm <?= ($row['is_active'] == '1') ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">

                                            <a href="aksi_user.php?act=a&id=<?= $row['id_user'] ?>" class="text-white">
                                                <?= $row['is_active'] == '1' ? 'Aktif' : 'Tidak Aktif' ?>
                                            </a>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <a title="lihat" href="main.php?module=profile&id=<?= $row['id_user'] ?>"
                                            class="text-secondary text-danger font-weight-bold text-xs">
                                            <i class="material-icons opacity-10" translate="no">zoom_in
                                            </i>
                                        </a> |
                                        <a title="hapus" href="aksi_user.php?act=h&id=<?= $row['id_user'] ?>"
                                            onclick="return confirm('Hapus ?')"
                                            class="text-secondary text-danger font-weight-bold text-xs">
                                            <i class="material-icons opacity-10" translate="no">delete
                                            </i>
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


<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_user.php?act=t" method="post">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Tambah User</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Nama</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="nama" maxlength="50" class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Username</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="username" maxlength="20" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row my-3">
                        <div class="col">
                            <label class="form-label">Password</label>
                            <div class="input-group input-group-outline">
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Konfirmasi Password</label>
                            <div class="input-group input-group-outline">
                                <input type="password" name="konfirmasi_password" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row my-3">
                        <div class="col">
                            <label>Email</label>
                            <div class="input-group input-group-outline">
                                <input type="email" name="email" required class="form-control">
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">No Telp</label>
                            <div class="input-group input-group-outline">
                                <input type="text" maxlength="13" name="no_telp" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row my-3">
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="mahasiswa">Mahasiswa</option>
                                <option value="dosen">Dosen</option>
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