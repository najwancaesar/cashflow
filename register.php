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
                                <input type="text" name="username" class="form-control" placeholder="username" required>
                            </div>
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-user"></span></div>
                                <input type="text" name="nama" class="form-control" placeholder="nama lengkap" required>
                            </div>
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-user"></span></div>
                                <input type="email" name="email" class="form-control" placeholder="email" required>
                            </div>
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-lock"></span></div>
                                <input type="password" name="password" class="form-control" placeholder="Password"
                                    required>
                            </div>
                            <div class="form-group">
                                <div class="icon d-flex align-items-center justify-content-center"><span
                                        class="fa fa-user"></span></div>
                                <input type="number" name="no_telp" class="form-control" placeholder="No.telepon"
                                    required>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="kirim"
                                    class="btn form-control btn-primary rounded submit px-3">register</button>
                            </div>
                            <div class="form-group">
                                <h5 class="mb-0"> <a href="login.php" class="text-center link-primary
                                        link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">
                                        I already have an account !
                                    </a>
                                </h5>
                            </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </section>
    <?php
    include("./includes/footer.php");
    ?>
</body>
<?php
if (isset($_POST['kirim'])) {
    include "includes/koneksi.php";
    $username = trim($_POST["username"] ?? '');
    $nama = trim($_POST["nama"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = (string) ($_POST["password"] ?? '');
    $no_telp = trim($_POST["no_telp"] ?? '');
    $role = 'user';
    $foto = 'default.png';
    $is_active = '1';

    $cekStmt = $con->prepare("SELECT id_user FROM user WHERE username = ? OR email = ? LIMIT 1");
    $cekStmt->bind_param("ss", $username, $email);
    $cekStmt->execute();
    $cekResult = $cekStmt->get_result();

    if ($cekResult && $cekResult->num_rows > 0) {
        echo "<script>Swal.fire({icon:'warning',title:'Pendaftaran ditolak',text:'Username atau email sudah terdaftar.'});</script>";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $con->prepare("INSERT INTO user(username, nama, email, password, no_telp, role, foto, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $username, $nama, $email, $hashedPassword, $no_telp, $role, $foto, $is_active);

        if ($stmt->execute()) {
            echo "<script>
                Swal.fire({
                    icon:'success',
                    title:'Akun berhasil dibuat',
                    text:'Silakan login untuk mulai menggunakan aplikasi.'
                }).then(function () {
                    window.location.href='login.php';
                });
            </script>";
        } else {
            echo "<script>Swal.fire({icon:'error',title:'Pendaftaran gagal',text:'Akun gagal dibuat. Silakan coba lagi.'});</script>";
        }

        $stmt->close();
    }

    $cekStmt->close();
}
