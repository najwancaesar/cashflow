<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_user'])) {
    header('Location: ./');
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    header('Location: main.php?module=home');
    exit;
}

include "includes/koneksi.php";

function format_user_datetime($value)
{
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y H:i', $timestamp);
}

function fetch_user_count($con, $table, $userId, $column = 'user')
{
    $allowedTables = ['kategori', 'pemasukan', 'pengeluaran', 'hutang', 'piutang'];
    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_row($result) : [0];
    mysqli_stmt_close($stmt);

    return (int) ($row[0] ?? 0);
}

$selectedDetailUserId = isset($_GET['detail']) ? (int) $_GET['detail'] : 0;
$detailUser = null;
$detailSummary = [];

if ($selectedDetailUserId > 0) {
    $detailStmt = mysqli_prepare($con, "SELECT * FROM user WHERE id_user = ? LIMIT 1");
    mysqli_stmt_bind_param($detailStmt, "i", $selectedDetailUserId);
    mysqli_stmt_execute($detailStmt);
    $detailResult = mysqli_stmt_get_result($detailStmt);
    $detailUser = mysqli_fetch_assoc($detailResult) ?: null;
    mysqli_stmt_close($detailStmt);

    if ($detailUser) {
        $detailSummary = [
            'kategori' => fetch_user_count($con, 'kategori', $selectedDetailUserId, 'user_id'),
            'pemasukan' => fetch_user_count($con, 'pemasukan', $selectedDetailUserId),
            'pengeluaran' => fetch_user_count($con, 'pengeluaran', $selectedDetailUserId),
            'hutang' => fetch_user_count($con, 'hutang', $selectedDetailUserId),
            'piutang' => fetch_user_count($con, 'piutang', $selectedDetailUserId),
        ];
    }
}

$users = [];
$userQuery = "SELECT id_user, nama, username, email, no_telp, foto, role, is_active, create_at, last_login_at, last_profile_update_at
              FROM user
              ORDER BY role DESC, create_at DESC, id_user DESC";
$userResult = mysqli_query($con, $userQuery);
while ($row = mysqli_fetch_assoc($userResult)) {
    $users[] = $row;
}
?>

<div class="container-fluid py-4">
    <?php if ($detailUser) { ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                        <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center">
                            <h6 class="text-white text-capitalize ps-3 mb-0">Detail User</h6>
                            <a href="main.php?module=pengguna" class="btn btn-sm btn-light me-3 mb-0">Tutup Detail</a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <?php if ($detailUser['foto'] === 'default.png' || $detailUser['foto'] === '') { ?>
                                    <i class="material-icons text-info" style="font-size: 64px;" translate="no">person</i>
                                <?php } else { ?>
                                    <img src="assets/img/profil/<?= htmlspecialchars($detailUser['foto']) ?>" class="avatar avatar-xxl shadow" alt="foto-user">
                                <?php } ?>
                            </div>
                            <div class="col-md-5">
                                <h5 class="mb-1"><?= htmlspecialchars($detailUser['nama']) ?></h5>
                                <p class="text-sm text-secondary mb-1">@<?= htmlspecialchars($detailUser['username']) ?></p>
                                <p class="text-sm text-secondary mb-0"><?= htmlspecialchars($detailUser['email']) ?></p>
                            </div>
                            <div class="col-md-5">
                                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                    <span class="badge badge-sm <?= ($detailUser['role'] ?? 'user') === 'admin' ? 'bg-gradient-dark' : 'bg-gradient-info' ?>">
                                        <?= htmlspecialchars(ucfirst($detailUser['role'] ?? 'user')) ?>
                                    </span>
                                    <span class="badge badge-sm <?= ($detailUser['is_active'] ?? '1') === '1' ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                        <?= ($detailUser['is_active'] ?? '1') === '1' ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </div>
                                <ul class="list-group mt-3">
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Tanggal daftar:</strong> <?= htmlspecialchars(format_user_datetime($detailUser['create_at'])) ?></li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Last login:</strong> <?= htmlspecialchars(format_user_datetime($detailUser['last_login_at'])) ?></li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">Perubahan akun terakhir:</strong> <?= htmlspecialchars(format_user_datetime($detailUser['last_profile_update_at'])) ?></li>
                                    <li class="list-group-item border-0 ps-0 text-sm"><strong class="text-dark">No. Telp:</strong> <?= htmlspecialchars($detailUser['no_telp']) ?></li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Kategori</p>
                                    <h6 class="mb-0"><?= number_format((float) ($detailSummary['kategori'] ?? 0)) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Pemasukan</p>
                                    <h6 class="mb-0"><?= number_format((float) ($detailSummary['pemasukan'] ?? 0)) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Pengeluaran</p>
                                    <h6 class="mb-0"><?= number_format((float) ($detailSummary['pengeluaran'] ?? 0)) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Utang</p>
                                    <h6 class="mb-0"><?= number_format((float) ($detailSummary['hutang'] ?? 0)) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Piutang</p>
                                    <h6 class="mb-0"><?= number_format((float) ($detailSummary['piutang'] ?? 0)) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-xs text-secondary mb-1">Status Akun</p>
                                    <h6 class="mb-0"><?= ($detailUser['is_active'] ?? '1') === '1' ? 'Aktif' : 'Nonaktif' ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Manajemen User</h6>
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
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Foto</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nama</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Role</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tanggal Daftar</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Last Login</th>
                                    <th class="text-secondary opacity-7"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $row) { ?>
                                    <?php
                                    $isSelf = (int) $row['id_user'] === (int) $_SESSION['id_user'];
                                    $isActive = ($row['is_active'] ?? '1') === '1';
                                    ?>
                                    <tr>
                                        <td class="align-middle text-center">
                                            <?php if (($row['foto'] ?? '') === '' || ($row['foto'] ?? '') === 'default.png') { ?>
                                                <i class="material-icons opacity-10" translate="no">person</i>
                                            <?php } else { ?>
                                                <img src="assets/img/profil/<?= htmlspecialchars($row['foto']) ?>" class="avatar avatar-sm" alt="foto-user">
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($row['nama']) ?></p>
                                            <p class="text-xs text-secondary mb-0">@<?= htmlspecialchars($row['username']) ?></p>
                                            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($row['email']) ?></p>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm <?= ($row['role'] ?? 'user') === 'admin' ? 'bg-gradient-dark' : 'bg-gradient-info' ?>">
                                                <?= htmlspecialchars(ucfirst($row['role'] ?? 'user')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm <?= $isActive ? 'bg-gradient-success' : 'bg-gradient-secondary' ?>">
                                                <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_user_datetime($row['create_at'])) ?></p>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0"><?= htmlspecialchars(format_user_datetime($row['last_login_at'])) ?></p>
                                        </td>
                                        <td class="align-middle">
                                            <a href="main.php?module=pengguna&detail=<?= (int) $row['id_user'] ?>" class="text-secondary font-weight-bold text-xs me-2">
                                                <i class="material-icons opacity-10" translate="no">visibility</i>
                                            </a>
                                            <?php if (!$isSelf) { ?>
                                                <a type="button"
                                                    class="text-secondary text-warning font-weight-bold text-xs me-2 btnedituser"
                                                    data-id="<?= (int) $row['id_user'] ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>"
                                                    data-username="<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>"
                                                    data-no_telp="<?= htmlspecialchars($row['no_telp'], ENT_QUOTES) ?>"
                                                    data-role="<?= htmlspecialchars($row['role'], ENT_QUOTES) ?>"
                                                    data-is_active="<?= htmlspecialchars($row['is_active'], ENT_QUOTES) ?>">
                                                    <i class="material-icons opacity-10" translate="no">edit</i>
                                                </a>

                                                <a href="aksi_user.php?act=s&id=<?= (int) $row['id_user'] ?>&value=<?= $isActive ? '0' : '1' ?>"
                                                    data-confirm="true"
                                                    data-confirm-title="<?= $isActive ? 'Nonaktifkan user ini?' : 'Aktifkan user ini?' ?>"
                                                    data-confirm-text="<?= $isActive ? 'User tidak akan bisa login sampai diaktifkan kembali.' : 'User akan bisa login kembali ke sistem.' ?>"
                                                    data-confirm-confirm-text="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                                    data-confirm-cancel-text="Batal"
                                                    class="text-secondary <?= $isActive ? 'text-secondary' : 'text-success' ?> font-weight-bold text-xs me-2">
                                                    <i class="material-icons opacity-10" translate="no"><?= $isActive ? 'toggle_off' : 'toggle_on' ?></i>
                                                </a>

                                                <a type="button"
                                                    class="text-secondary text-info font-weight-bold text-xs me-2 btnresetpassworduser"
                                                    data-id="<?= (int) $row['id_user'] ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES) ?>">
                                                    <i class="material-icons opacity-10" translate="no">lock_reset</i>
                                                </a>

                                                <a title="hapus" href="aksi_user.php?act=h&id=<?= (int) $row['id_user'] ?>"
                                                    data-confirm="true"
                                                    data-confirm-title="Hapus pengguna ini?"
                                                    data-confirm-text="Akun yang dihapus tidak bisa dipulihkan lagi."
                                                    data-confirm-confirm-text="Ya, hapus"
                                                    data-confirm-cancel-text="Batal"
                                                    class="text-secondary text-danger font-weight-bold text-xs">
                                                    <i class="material-icons opacity-10" translate="no">delete</i>
                                                </a>
                                            <?php } else { ?>
                                                <span class="text-xs text-secondary">Kelola akun sendiri lewat Profil</span>
                                            <?php } ?>
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
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_user.php?act=t" method="post">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Tambah User</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    <div class="row my-3">
                        <div class="col">
                            <label class="form-label">Role</label>
                            <div class="input-group input-group-outline">
                                <select name="role" class="form-control" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Status</label>
                            <div class="input-group input-group-outline">
                                <select name="is_active" class="form-control" required>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
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

<div class="modal fade" id="modalEditUser" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <form action="aksi_user.php?act=u" method="post">
                <input type="hidden" name="id_user" id="edit_user_id">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Edit User</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col">
                            <label class="form-label">Nama</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="nama" id="edit_nama" maxlength="50" class="form-control" required>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Username</label>
                            <div class="input-group input-group-outline">
                                <input type="text" name="username" id="edit_username" maxlength="20" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row my-3">
                        <div class="col">
                            <label>Email</label>
                            <div class="input-group input-group-outline">
                                <input type="email" name="email" id="edit_email" required class="form-control">
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">No Telp</label>
                            <div class="input-group input-group-outline">
                                <input type="text" maxlength="13" name="no_telp" id="edit_no_telp" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="row my-3">
                        <div class="col">
                            <label class="form-label">Role</label>
                            <div class="input-group input-group-outline">
                                <select name="role" id="edit_role" class="form-control" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <label class="form-label">Status</label>
                            <div class="input-group input-group-outline">
                                <select name="is_active" id="edit_is_active" class="form-control" required>
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="simpan" class="btn btn-info">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalResetPasswordUser" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_user.php?act=r" method="post">
                <input type="hidden" name="id_user" id="reset_user_id">
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Reset Password User</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <p class="text-sm text-secondary" id="reset_user_name">Atur password baru untuk user terpilih.</p>
                    <div class="row my-3">
                        <label class="form-label">Password Baru</label>
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
                    <button type="submit" name="simpan" class="btn btn-info">Reset Password</button>
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
