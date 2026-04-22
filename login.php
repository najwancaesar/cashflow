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
    <title> CashFlow Control</title>
    <link rel="icon" type="image/png" href="assets/img/logocv.jpg">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link id="pagestyle" href="assets/css/material-dashboard.css?v=3.0.0" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>
    <section style="padding: 2em 0;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 text-center mb-5">
                    <h2 class="heading-section">CashFlow Control</h2>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="login-wrap py-5">
                        <div class="img d-flex align-items-center justify-content-center"
                            style="background-image:url(assets/img/logocv.jpg)">
                        </div>
                        <h3 class="text-center mb-0"></h3>
                        <p class="text-center"></p>
                        <form method="post">
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-user"></span></div>
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-lock"></span></div>
                                <input type="password" name="password" class="form-control" placeholder="Password"
                                    required>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="kirim"
                                    class="btn form-control btn-primary rounded submit px-3">LOGIN</button>
                            </div>
                            <div class="form-group">
                                <h5 class="mb-0"> <a href="register.php"
                                        class="text center link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">
                                        Don't have an account?
                                    </a>
                                </h5>
                            </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
        </div>

        <?php
        if (isset($_POST['kirim'])) {
            include "includes/koneksi.php";

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

                    session_regenerate_id(true);
                    $_SESSION['username'] = $sesi['username'];
                    $_SESSION['nama'] = $sesi['nama'];
                    $_SESSION['id_user'] = $sesi['id_user'];
                    $_SESSION['role'] = (($sesi['role'] ?? '') === 'admin') ? 'admin' : 'user';
                    $_SESSION['foto'] = $sesi['foto'] ?: 'default.png';

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

    <?php
    include("./includes/footer.php");
    
    ?>
</body>

</html>
