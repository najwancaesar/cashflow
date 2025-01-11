<?php
session_start();
include "../../includes/koneksi.php";

// Validasi user login
if (!isset($_SESSION['id_user'])) {
    die("Silakan login terlebih dahulu");
}

$id_user = $_SESSION['id_user'];

if (isset($_POST['tanggal'])) {
    $dates = explode(' - ', $_POST['tanggal']);
    if (count($dates) === 2) {
        $tgl_awal = date('Y-m-d', strtotime($dates[0]));
        $tgl_akhir = date('Y-m-d', strtotime($dates[1]));
    } else {
        die("Format tanggal tidak valid");
    }
}

$tabel = $_POST['tabel'] ?? 'pemasukan';
$allowed_tables = ['pemasukan', 'pengeluaran', 'hutang', 'piutang'];

if (!in_array($tabel, $allowed_tables)) {
    die("Tabel tidak valid");
}

// Query dimodifikasi untuk menambahkan filter user id
$query = "SELECT $tabel.*, user.username 
          FROM $tabel 
          INNER JOIN user ON $tabel.user = user.id_user 
          WHERE $tabel.user = ? 
          AND $tabel.tanggal BETWEEN ? AND ?";

if ($tabel === 'pemasukan') {
    $query .= " AND $tabel.status = 'selesai'";
}

$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, "iss", $id_user, $tgl_awal, $tgl_akhir);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Ambil data user untuk header
$query_user = "SELECT * FROM user WHERE id_user = ?";
$stmt_user = mysqli_prepare($con, $query_user);
mysqli_stmt_bind_param($stmt_user, "i", $id_user);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan <?= ucfirst($tabel) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            margin-bottom: 20px;
        }
        .user-info {
            text-align: left;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>CASHFLOW ITPGT</h2>
        <h3>POLITEKNIK GAJAH TUNGGAL PRODI TEKNOLOGI INFORMASI</h3>
        <hr>
        <h3>Laporan <?= ucfirst($tabel) ?></h3>
        <p>Periode: <?= date('d M Y', strtotime($tgl_awal)) ?> s/d <?= date('d M Y', strtotime($tgl_akhir)) ?></p>
    </div>

    <div class="user-info">
        <p>Username: <?= htmlspecialchars($user_data['username']) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jumlah</th>
                <th>Catatan</th>
                <?php if($tabel == 'pemasukan'): ?>
                    <th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total = 0;
            while($row = mysqli_fetch_assoc($result)): 
                $total += $row['jumlah'];
            ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                    <td align="right">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['catatan']) ?></td>
                    <?php if($tabel == 'pemasukan'): ?>
                        <td><?= $row['status'] ?></td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td align="right">Rp <?= number_format($total, 0, ',', '.') ?></td>
                <td colspan="<?= ($tabel == 'pemasukan' ? '2' : '1') ?>"></td>
            </tr>
        </tbody>
    </table>

    <div class="no-print">
        <button onclick="window.print()">Cetak Laporan</button>
        <button onclick="window.close()">Tutup</button>
    </div>
</body>
</html>