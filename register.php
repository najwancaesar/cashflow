<?php
session_start();
include_once "includes/csrf_helper.php";
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
    <script src="assets/js/auth-password-toggle.js" defer></script>
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
                            <?= csrf_input() ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">alternate_email</span>
                                        <input type="text" name="username" class="auth-input" placeholder="Masukkan username" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full name</label>
                                    <div class="auth-field">
                                        <span class="material-icons-round">badge</span>
                                        <input type="text" name="nama" class="auth-input" placeholder="Masukkan full name" required>
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
                                    <div class="auth-field auth-field--password">
                                        <span class="material-icons-round">lock</span>
                                        <input type="password" id="register-password" name="password" class="auth-input" placeholder="Buat password" required>
                                        <button
                                            type="button"
                                            class="auth-password-toggle"
                                            data-password-toggle
                                            aria-controls="register-password"
                                            aria-label="Tampilkan password"
                                            aria-pressed="false"
                                        >
                                            <span class="material-icons-round" aria-hidden="true">visibility</span>
                                        </button>
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
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token()) {
        echo "<script>Swal.fire({icon:'error',title:'Permintaan tidak valid',text:'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu coba kembali.'});</script>";
        exit;
    }

    include "includes/koneksi.php";
    include "includes/default_categories.php";
    include "includes/avatar_helper.php";

    if (!function_exists('create_default_wallet_for_registered_user')) {
        function create_default_wallet_for_registered_user($con, $userId)
        {
            $userId = (int) $userId;
            if ($userId <= 0) {
                return false;
            }

            $namaWallet = 'Dompet Utama';
            $tipeWallet = 'cash';
            $saldoAwal = 0.00;
            $isDefault = 1;
            $isActive = 1;

            $walletStmt = $con->prepare("INSERT INTO wallet (user_id, nama_wallet, tipe_wallet, saldo_awal, is_default, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$walletStmt) {
                return false;
            }

            $walletStmt->bind_param("issdii", $userId, $namaWallet, $tipeWallet, $saldoAwal, $isDefault, $isActive);
            $created = $walletStmt->execute();
            $walletStmt->close();

            return $created;
        }
    }

    $username = trim($_POST["username"] ?? '');
    $nama = trim($_POST["nama"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = (string) ($_POST["password"] ?? '');
    $no_telp = trim($_POST["no_telp"] ?? '');
    $role = 'user';
    $foto = default_avatar_filename();
    $is_active = '1';

    $validationMessage = '';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,20}$/', $username)) {
        $validationMessage = 'Username harus 3-20 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    } elseif ($nama === '' || mb_strlen($nama, 'UTF-8') > 50) {
        $validationMessage = 'Nama wajib diisi dan maksimal 50 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
        $validationMessage = 'Format email tidak valid atau melebihi 50 karakter.';
    } elseif (!preg_match('/^[0-9]{8,13}$/', $no_telp)) {
        $validationMessage = 'Nomor telepon harus berisi 8-13 digit angka.';
    } elseif (strlen($password) < 6 || strlen($password) > 72) {
        $validationMessage = 'Password harus terdiri dari 6-72 karakter.';
    }

    if ($validationMessage !== '') {
        $validationJson = json_encode($validationMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "<script>Swal.fire({icon:'warning',title:'Data tidak valid',text:{$validationJson}});</script>";
        exit;
    }

    $cekStmt = $con->prepare("SELECT id_user FROM user WHERE username = ? OR email = ? LIMIT 1");
    $cekStmt->bind_param("ss", $username, $email);
    $cekStmt->execute();
    $cekResult = $cekStmt->get_result();

    if ($cekResult && $cekResult->num_rows > 0) {
        echo "<script>Swal.fire({icon:'warning',title:'Pendaftaran ditolak',text:'Username atau email sudah terdaftar.'});</script>";
    } else {
        try {
            $con->begin_transaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                throw new RuntimeException('Password gagal diamankan.');
            }

            $stmt = $con->prepare("INSERT INTO user(username, nama, email, password, no_telp, role, foto, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Pendaftaran gagal disiapkan.');
            }
            $stmt->bind_param("ssssssss", $username, $nama, $email, $hashedPassword, $no_telp, $role, $foto, $is_active);
            if (!$stmt->execute()) {
                throw new RuntimeException('Akun gagal dibuat.');
            }
            $newUserId = (int) $stmt->insert_id;
            $stmt->close();

            seed_default_categories_for_user($con, $newUserId);
            if (!create_default_wallet_for_registered_user($con, $newUserId)) {
                throw new RuntimeException('Wallet default gagal dibuat.');
            }

            $con->commit();
            echo "<script>
                Swal.fire({
                    icon:'success',
                    title:'Akun berhasil dibuat',
                    text:'Silakan login untuk mulai menggunakan aplikasi.'
                }).then(function () {
                    window.location.href='login.php';
                });
            </script>";
        } catch (Throwable $exception) {
            try {
                $con->rollback();
            } catch (Throwable $rollbackException) {
                error_log('CashFlow registration rollback failed.');
            }
            error_log('CashFlow registration failed: ' . $exception->getMessage());
            echo "<script>Swal.fire({icon:'error',title:'Pendaftaran gagal',text:'Akun gagal dibuat. Silakan coba lagi.'});</script>";
        }
    }

    $cekStmt->close();
}
?>
