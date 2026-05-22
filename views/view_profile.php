<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_user'])) {
    header('Location: ./');
    exit;
}

// Query untuk mengambil data user dengan id_user = 1

include __DIR__ . "/../includes/koneksi.php";
include_once __DIR__ . "/../includes/avatar_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
$idUser = $_SESSION['id_user'] ?? 0;
$stmtUser = $con->prepare("SELECT * FROM user WHERE id_user = ?");
$stmtUser->bind_param("i", $idUser);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
$accountLabel = (($user['role'] ?? '') === 'admin') ? 'Administrator' : 'Personal Account';
?>
<div class="container-fluid px-2 px-md-4">
    <div class="page-header min-height-300 border-radius-xl mt-4"
        style="background-image: url('https://images.unsplash.com/photo-1531512073830-ba890ca4eba2?ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');">
        <span class="mask  bg-gradient-info  opacity-6"></span>
    </div>
    <div class="card card-body mx-3 mx-md-4 mt-n6">
        <div class="row gx-4 mb-2">
            <div class="col-auto">
                <div class="avatar avatar-xl position-relative">
                    <img src="<?= htmlspecialchars(profile_photo_src($user['foto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="profile_image"
                        class="w-100 border-radius-lg shadow-sm">
                </div>
            </div>
            <div class="col-auto my-auto">
                <div class="h-100">
                    <h5 class="mb-1">
                        <?= htmlspecialchars($user['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <p class="mb-0 font-weight-normal text-sm">
                        <?= $accountLabel ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 my-sm-auto ms-sm-auto me-sm-0 mx-auto mt-3">
                <div class="row mb-2">
                    <a class="mb-0 px-0 py-1 active btn" data-bs-toggle="tab" href="javascript:;" role="tab"
                        aria-selected="true">
                        <i class="fa fa-user-circle text-lg position-relative" aria-hidden="true"></i>
                        <span class="ms-1">Profil</span>
                    </a>
                </div>
                <div class="row mb-2">
                    <button class="mb-0 px-0 py-1 btn btn-warning" data-bs-toggle="modal"
                        data-bs-target="#modalEditProfil">
                        <i class="fa fa-pencil text-lg position-relative" aria-hidden="true"></i>
                        <span class="ms-1">Ubah Profil</span>
                    </button>
                </div>
                <div class="row mb-2">
                    <button class="mb-0 px-0 py-1 btn btn-danger" data-bs-toggle="modal"
                        data-bs-target="#modalEditPassword">
                        <i class="fa fa-lock text-lg position-relative" aria-hidden="true"></i>
                        <span class="ms-1">Ganti Password</span>
                    </button>
                </div>
                <div class="row mb-2">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="row">
                <div class="col-12 col-xl-4">
                    <div class="card card-plain h-100">
                        <div class="card-header pb-0 p-3">
                            <div class="row">
                                <div class="col-md-8 d-flex align-items-center">
                                    <h6 class="mb-0">Informasi Profil</h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <hr class="horizontal gray-light my-4">
                            <ul class="list-group">
                                <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong
                                        class="text-dark">Username :</strong>
                                    &nbsp; <?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item border-0 ps-0 pt-0 text-sm"><strong
                                        class="text-dark">Nama:</strong> &nbsp;
                                    <?= htmlspecialchars($user['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Nomor
                                        Telepon:</strong>
                                    &nbsp; <?= htmlspecialchars($user['no_telp'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item border-0 ps-0 text-sm"><strong
                                        class="text-dark">Email:</strong> &nbsp;
                                    <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal edit Profil -->
<div class="modal fade" id="modalEditProfil" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="actions/aksi_user.php?act=e" method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>
                <input type="hidden" name="id_user" value="<?= (int) $user['id_user'] ?>">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Edit Profil</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row my-3">
                        <label class="form-label">Foto</label>
                        <div class="input-group input-group-outline">
                            <input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        </div>
                        <small class="text-secondary">Format JPG, JPEG, atau PNG. Maksimal 2MB.</small>
                    </div>
                    <div class="row my-3">
                        <div class="col">
                            <label class="form-label">Nama</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="nama" value="<?= htmlspecialchars($user['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="50"
                                    class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Username</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="20"
                                    class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row my-3">
                        <div class="col">
                            <label>Email</label>
                            <div class="input-group input-group-outline">
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                                    class="form-control">
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">No Telp</label>
                            <div class="input-group input-group-outline">
                                <input type="text" maxlength="13" value="<?= htmlspecialchars($user['no_telp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" name="no_telp"
                                    class="form-control" required>
                            </div>
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
<!-- Modal edit password -->
<div class="modal fade" id="modalEditPassword" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="actions/aksi_user.php?act=p" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="id_user" value="<?= (int) $user['id_user'] ?>">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Edit Password</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row my-3">
                        <label class="form-label">Password</label>
                        <div class="input-group input-group-outline">
                            <input type="password" name="password_baru" class="form-control" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Konfirmasi Password</label>
                        <div class="input-group input-group-outline">
                            <input type="password" name="konfirmasi_password" class="form-control" required>
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
