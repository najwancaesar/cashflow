<?php
error_reporting(0);
session_start();
if (isset($_SESSION['username'])) {
    echo "<script>window.location=(href='main.php?module=home')</script>";
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
                            <div class="form-group" id="form-select">
                                <select class="form-control" name="role" required>
                                    <option value="" class="bg-success">Pilih Role</option>
                                    <option value="mahasiswa" class="bg-secondary">Mahasiswa</option>
                                    <option value="dosen" class="bg-secondary">Dosen</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="kirim"
                                    class="btn form-control btn-primary rounded submit px-3">register</button>
                            </div>
                            <div class="form-group">
                                <h5 class="mb-0"> <a href="index.php" class="text-center link-primary
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
<style>
#form-select {
    color: black;
}
</style>
<?php
if (isset($_POST['kirim'])) {
    include "includes/koneksi.php";
    $username = $_POST["username"];
    $nama = $_POST["nama"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $no_telp = $_POST["no_telp"];
    $role = $_POST["role"];


    $sql = "INSERT INTO user(username, nama, email, password, no_telp, role) VALUES ('$username','$nama','$email','$password','$no_telp','$role')";

    if (mysqli_query($con, $sql)) {
        echo "<script>window.alert('Akun berhasil di buat !');
			window.location=('index.php')</script>";
    } else {
        echo "Account failed :" . $sql . "<br>" . mysqli_error($koneksi);
    }
}