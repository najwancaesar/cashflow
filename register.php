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
                            <h3>Mulai kelola cashflow pribadi.</h3>
                            <p class="mt-3 mb-0">
                                Buat akun sederhana untuk mencatat pemasukan, pengeluaran, utang, piutang, dan laporan dalam satu dashboard.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="auth-card">
                        <h3>Register</h3>
                        <p class="auth-subtitle">Isi data akun Anda untuk mulai memakai aplikasi.</p>
                        <form method="post" class="auth-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">alternate_email</span>
                                        <input type="text" name="username" class="auth-input" placeholder="Masukkan username" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">badge</span>
                                        <input type="text" name="nama" class="auth-input" placeholder="Masukkan nama lengkap" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <div class="auth-field">
                                    <span class="material-icons-round">mail</span>
                                    <input type="email" name="email" class="auth-input" placeholder="Masukkan email aktif" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">lock</span>
                                        <input type="password" name="password" class="auth-input" placeholder="Buat password" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. Telepon</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">call</span>
                                        <input type="text" name="no_telp" class="auth-input" placeholder="Contoh: 08123456789" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" name="kirim" class="auth-submit">Buat Akun</button>
                            </div>
                            <div class="auth-footer">
                                <p class="auth-helper mb-0">
                                    Sudah punya akun?
                                    <a href="login.php" class="auth-link">Masuk di sini</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php include("./includes/footer.php"); ?>
</body>

</html>
<?php
if (isset($_POST['kirim'])) {
    include "includes/koneksi.php";
    include "includes/default_categories.php";

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
            $newUserId = (int) $stmt->insert_id;
            seed_default_categories_for_user($con, $newUserId);

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
?>
