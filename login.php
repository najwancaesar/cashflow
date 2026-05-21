<?php
session_start();
if (isset($_SESSION['nama'])) {
    header('Location: main.php?module=home');
    exit;
}
?>

<!doctype html>
<html lang="en">

<head>
    <?php include "includes/header.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/auth-dashboard.css">
</head>

<body class="auth-page">
    <section class="auth-shell">
        <div class="auth-frame">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="auth-panel">
                        <div class="auth-brand">
                            <span class="auth-brand-badge">
                                <img src="assets/img/logocv.jpg" alt="CashFlow Control">
                            </span>
                            <span>CashFlow Control</span>
                        </div>
                        <div class="auth-panel-copy">
                            <h3>Selamat datang kembali.</h3>
                            <p class="mt-3 mb-0">
                                Masuk untuk melanjutkan pencatatan cashflow, memantau saldo, dan membaca laporan keuangan pribadi Anda.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="auth-card">
                        <h3>Login</h3>
                        <p class="auth-subtitle">Masukkan akun Anda untuk membuka dashboard cashflow.</p>
                        <form method="post" class="auth-form">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="auth-field">
                                    <span class="material-icons-round">person</span>
                                    <input type="text" name="username" class="auth-input" placeholder="Masukkan username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="auth-field">
                                    <span class="material-icons-round">lock</span>
                                    <input type="password" name="password" class="auth-input" placeholder="Masukkan password" required>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="kirim" class="auth-submit">Masuk ke Dashboard</button>
                            </div>
                            <div class="auth-footer">
                                <p class="auth-helper mb-0">
                                    Belum punya akun?
                                    <a href="register.php" class="auth-link">Daftar sekarang</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
        var options = {
            damping: '0.5'
        }
        Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
    </script>
    <script src="assets/js/material-dashboard.min.js?v=3.0.0"></script>

    <?php include("./includes/footer.php"); ?>
</body>

</html>
<?php
if (isset($_POST['kirim'])) {
    include "includes/koneksi.php";
    include "includes/avatar_helper.php";

    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $activeStatus = '1';
    $stmt = $con->prepare("SELECT * FROM user WHERE username = ? AND is_active = ? LIMIT 1");
    $stmt->bind_param("ss", $username, $activeStatus);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $sesi = $result->fetch_assoc();
        $storedPassword = (string) ($sesi['password'] ?? '');
        $passwordValid = password_verify($password, $storedPassword);
        $shouldUpgradePassword = false;

        if (!$passwordValid && $storedPassword !== '' && hash_equals($storedPassword, $password)) {
            $passwordValid = true;
            $shouldUpgradePassword = true;
        }

        if ($passwordValid) {
            if ($shouldUpgradePassword || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $con->prepare("UPDATE user SET password = ? WHERE id_user = ?");
                $updateStmt->bind_param("si", $newHash, $sesi['id_user']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            $activityStmt = $con->prepare("UPDATE user SET last_login_at = NOW() WHERE id_user = ?");
            $activityStmt->bind_param("i", $sesi['id_user']);
            $activityStmt->execute();
            $activityStmt->close();

            session_regenerate_id(true);
            $_SESSION['username'] = $sesi['username'];
            $_SESSION['nama'] = $sesi['nama'];
            $_SESSION['id_user'] = $sesi['id_user'];
            $_SESSION['role'] = (($sesi['role'] ?? '') === 'admin') ? 'admin' : 'user';
            $_SESSION['foto'] = resolve_profile_photo($sesi['foto'] ?? '');
            $_SESSION['flash_login_success'] = [
                'title' => $_SESSION['role'] === 'admin'
                    ? 'Selamat datang, Admin!'
                    : 'Selamat datang, ' . (string) $sesi['nama'] . '!',
                'text' => $_SESSION['role'] === 'admin'
                    ? 'Panel admin siap digunakan.'
                    : 'Semoga pencatatan keuanganmu hari ini lancar.',
                'icon' => 'success',
            ];

            echo "<script>window.location.href='main.php?module=home';</script>";
        } else {
            echo "<script>Swal.fire({icon:'error',title:'Login gagal',text:'Username atau password salah.'});</script>";
        }
    } else {
        echo "<script>Swal.fire({icon:'error',title:'Login gagal',text:'Username atau password salah.'});</script>";
    }

    $stmt->close();
}
?>
