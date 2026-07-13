<?php
session_start();
include __DIR__ . "/../includes/koneksi.php";
include __DIR__ . "/../includes/sweetalert_helper.php";
include __DIR__ . "/../includes/nominal_helper.php";
include_once __DIR__ . "/../includes/csrf_helper.php";
include_once __DIR__ . "/../includes/activity_log_helper.php";
include_once __DIR__ . "/../includes/wallet_balance_helper.php";

function recurring_redirect()
{
    return 'main.php?module=recurring';
}

function require_recurring_post_csrf()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        show_sweetalert_and_redirect('Akses ditolak', 'Aksi transaksi berulang wajib melalui form yang valid.', 'warning', recurring_redirect());
    }

    if (!verify_csrf_token()) {
        show_sweetalert_and_redirect('Session kadaluarsa', 'Token keamanan tidak valid. Silakan coba lagi.', 'warning', recurring_redirect());
    }
}

function clean_recurring_text($value)
{
    return trim((string) $value);
}

function validate_recurring_date($value, $nullable = false)
{
    $value = clean_recurring_text($value);
    if ($value === '' && $nullable) {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return false;
    }

    return $date->format('Y-m-d') === $value ? $value : false;
}

function is_valid_recurring_type($tipe)
{
    return in_array($tipe, ['pemasukan', 'pengeluaran'], true);
}

function is_valid_recurring_default_status($status)
{
    return in_array($status, ['pending', 'selesai'], true);
}

function fetch_active_wallet_for_recurring($con, $walletId, $userId)
{
    $stmt = $con->prepare("SELECT id_wallet
                           FROM wallet
                           WHERE id_wallet = ? AND user_id = ? AND is_active = 1
                           LIMIT 1");
    $stmt->bind_param("ii", $walletId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $wallet ?: null;
}

function fetch_category_for_recurring($con, $kategoriId, $userId, $tipeTransaksi)
{
    $stmt = $con->prepare("SELECT id_kategori
                           FROM kategori
                           WHERE id_kategori = ? AND user_id = ? AND tipe_kategori = ?
                           LIMIT 1");
    $stmt->bind_param("iis", $kategoriId, $userId, $tipeTransaksi);
    $stmt->execute();
    $result = $stmt->get_result();
    $kategori = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $kategori ?: null;
}

function fetch_recurring_for_user($con, $recurringId, $userId, $forUpdate = false)
{
    $lockClause = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = $con->prepare("SELECT *
                           FROM recurring_transaction
                           WHERE id_recurring = ? AND user_id = ?
                           LIMIT 1{$lockClause}");
    $stmt->bind_param("ii", $recurringId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recurring = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $recurring ?: null;
}

function recurring_period_already_generated($con, $recurringId, $userId, $bulan, $tahun)
{
    $stmt = $con->prepare("SELECT id_log
                           FROM recurring_generation_log
                           WHERE id_recurring = ? AND user_id = ? AND periode_bulan = ? AND periode_tahun = ?
                           LIMIT 1");
    $stmt->bind_param("iiii", $recurringId, $userId, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    $log = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $log !== null;
}

function recurring_template_has_history($con, $recurringId, $userId)
{
    $stmt = $con->prepare("SELECT id_log
                           FROM recurring_generation_log
                           WHERE id_recurring = ? AND user_id = ?
                           LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException('Gagal memeriksa riwayat recurring.');
    }

    $stmt->bind_param('ii', $recurringId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasHistory = $result && $result->fetch_assoc();
    $stmt->close();

    return (bool) $hasHistory;
}

function recurring_rollback($con)
{
    try {
        $con->rollback();
    } catch (Throwable $rollbackError) {
        error_log('Rollback recurring gagal: ' . $rollbackError->getMessage());
    }
}

function recurring_generation_result($status, $reason = '')
{
    return ['status' => $status, 'reason' => $reason];
}

function generate_recurring_occurrence($con, $recurringId, $userId, DateTimeImmutable $period)
{
    $periodeBulan = (int) $period->format('n');
    $periodeTahun = (int) $period->format('Y');
    $startMonth = $period->modify('first day of this month')->format('Y-m-d');
    $endMonth = $period->modify('last day of this month')->format('Y-m-d');
    $lastDayOfMonth = (int) $period->format('t');

    $con->begin_transaction();

    try {
        $row = fetch_recurring_for_user($con, $recurringId, $userId, true);
        if (!$row) {
            throw new DomainException('Template recurring tidak ditemukan atau bukan milik Anda.');
        }

        if ((int) ($row['is_active'] ?? 0) !== 1) {
            $con->commit();
            return recurring_generation_result('skipped', 'inactive');
        }

        $mulaiDari = (string) ($row['mulai_dari'] ?? '');
        $berakhirPada = $row['berakhir_pada'] ?? null;
        if ($mulaiDari === '' || $mulaiDari > $endMonth || ($berakhirPada !== null && $berakhirPada !== '' && $berakhirPada < $startMonth)) {
            $con->commit();
            return recurring_generation_result('skipped', 'outside_period');
        }

        if (recurring_period_already_generated($con, $recurringId, $userId, $periodeBulan, $periodeTahun)) {
            $con->commit();
            return recurring_generation_result('duplicate');
        }

        $tipeTransaksi = (string) ($row['tipe_transaksi'] ?? '');
        $status = (string) ($row['status_transaksi_default'] ?? '');
        $catatan = clean_recurring_text($row['catatan'] ?? '');
        $jumlah = (float) ($row['jumlah'] ?? 0);
        $kategoriId = (int) ($row['id_kategori'] ?? 0);
        $walletId = (int) ($row['id_wallet'] ?? 0);

        if (!is_valid_recurring_type($tipeTransaksi) || !is_valid_recurring_default_status($status) || $jumlah <= 0) {
            throw new DomainException('Konfigurasi template recurring tidak valid.');
        }

        if ($kategoriId <= 0 || !fetch_category_for_recurring($con, $kategoriId, $userId, $tipeTransaksi)) {
            throw new DomainException('Kategori recurring tidak valid atau bukan milik Anda.');
        }

        try {
            cashflow_lock_owned_wallets($con, $userId, [$walletId], [$walletId]);
        } catch (DomainException $walletError) {
            throw new DomainException('Wallet recurring tidak aktif atau bukan milik Anda.');
        }

        $day = min(max(1, (int) ($row['tanggal_generate'] ?? 0)), $lastDayOfMonth);
        $tanggalTransaksi = sprintf('%04d-%02d-%02d', $periodeTahun, $periodeBulan, $day);
        if ($tanggalTransaksi < $mulaiDari || ($berakhirPada !== null && $berakhirPada !== '' && $tanggalTransaksi > $berakhirPada)) {
            $con->commit();
            return recurring_generation_result('skipped', 'occurrence_outside_schedule');
        }

        if ($tipeTransaksi === 'pengeluaran' && $status === 'selesai') {
            $saldoTersedia = cashflow_calculate_wallet_balance($con, $userId, $walletId);
            if ($jumlah > $saldoTersedia + 0.00001) {
                throw new DomainException('Saldo wallet tidak mencukupi untuk recurring pengeluaran ini.');
            }
        }

        if ($tipeTransaksi === 'pemasukan') {
            $stmt = $con->prepare("INSERT INTO pemasukan (tanggal, catatan, status, jumlah, user, id_kategori, id_wallet)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Prepare pemasukan recurring gagal.');
            }
            $stmt->bind_param("sssdiii", $tanggalTransaksi, $catatan, $status, $jumlah, $userId, $kategoriId, $walletId);
        } else {
            $stmt = $con->prepare("INSERT INTO pengeluaran (tanggal, catatan, jumlah, user, status, id_kategori, id_wallet)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new RuntimeException('Prepare pengeluaran recurring gagal.');
            }
            $stmt->bind_param("ssdisii", $tanggalTransaksi, $catatan, $jumlah, $userId, $status, $kategoriId, $walletId);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Transaksi recurring gagal dibuat.');
        }

        $transaksiId = (int) $con->insert_id;
        $stmt->close();

        $logStmt = $con->prepare("INSERT INTO recurring_generation_log
                                  (id_recurring, user_id, periode_bulan, periode_tahun, tipe_transaksi, id_transaksi, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$logStmt) {
            throw new RuntimeException('Prepare riwayat recurring gagal.');
        }
        $logStmt->bind_param("iiiisi", $recurringId, $userId, $periodeBulan, $periodeTahun, $tipeTransaksi, $transaksiId);

        if (!$logStmt->execute()) {
            $logStmt->close();
            throw new RuntimeException('Riwayat recurring gagal dibuat.');
        }

        $logStmt->close();
        $con->commit();
        return recurring_generation_result('generated');
    } catch (mysqli_sql_exception $exception) {
        recurring_rollback($con);
        if ((int) $exception->getCode() === 1062) {
            return recurring_generation_result('duplicate');
        }
        error_log('Generate recurring gagal: ' . $exception->getMessage());
        return recurring_generation_result('failed', 'database_error');
    } catch (DomainException $exception) {
        recurring_rollback($con);
        return recurring_generation_result('failed', $exception->getMessage());
    } catch (Throwable $exception) {
        recurring_rollback($con);
        error_log('Generate recurring gagal: ' . $exception->getMessage());
        return recurring_generation_result('failed', 'processing_error');
    }
}

if (!isset($_SESSION['id_user'])) {
    show_sweetalert_and_redirect('Login diperlukan', 'Silakan login terlebih dahulu.', 'warning', 'login.php');
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    show_sweetalert_and_redirect('Akses dibatasi', 'Admin tidak dapat mengelola transaksi berulang user.', 'warning', 'main.php?module=home');
}

$userId = (int) $_SESSION['id_user'];
$act = $_GET['act'] ?? '';

if ($act === 't') {
    require_recurring_post_csrf();

    $recurringId = isset($_POST['id_recurring']) && $_POST['id_recurring'] !== ''
        ? (int) $_POST['id_recurring']
        : null;
    $namaRecurring = clean_recurring_text($_POST['nama_recurring'] ?? '');
    $tipeTransaksi = clean_recurring_text($_POST['tipe_transaksi'] ?? '');
    $kategoriId = (int) ($_POST['id_kategori'] ?? 0);
    $walletId = (int) ($_POST['id_wallet'] ?? 0);
    $catatan = clean_recurring_text($_POST['catatan'] ?? '');
    $jumlahRaw = (string) ($_POST['jumlah'] ?? '');
    $tanggalGenerate = (int) ($_POST['tanggal_generate'] ?? 0);
    $statusDefault = clean_recurring_text($_POST['status_transaksi_default'] ?? 'pending');
    $mulaiDari = validate_recurring_date($_POST['mulai_dari'] ?? '');
    $berakhirPada = validate_recurring_date($_POST['berakhir_pada'] ?? '', true);
    $isActiveRaw = $_POST['is_active'] ?? '1';

    if ($namaRecurring === '') {
        show_sweetalert_and_redirect('Data belum lengkap', 'Nama transaksi berulang wajib diisi.', 'warning', recurring_redirect());
    }

    if (!is_valid_recurring_type($tipeTransaksi)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tipe transaksi berulang tidak valid.', 'error', recurring_redirect());
    }

    if ($kategoriId <= 0 || !fetch_category_for_recurring($con, $kategoriId, $userId, $tipeTransaksi)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Kategori tidak valid atau bukan milik Anda.', 'error', recurring_redirect());
    }

    if ($walletId <= 0 || !fetch_active_wallet_for_recurring($con, $walletId, $userId)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Wallet tidak valid, tidak aktif, atau bukan milik Anda.', 'error', recurring_redirect());
    }

    if (strpos($jumlahRaw, '-') !== false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transaksi harus lebih dari 0.', 'error', recurring_redirect());
    }

    $jumlah = nominal_input_to_number($jumlahRaw);
    if ($jumlah <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'Jumlah transaksi harus lebih dari 0.', 'error', recurring_redirect());
    }

    if ($tanggalGenerate < 1 || $tanggalGenerate > 31) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal generate wajib di antara 1 sampai 31.', 'error', recurring_redirect());
    }

    if (!is_valid_recurring_default_status($statusDefault)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Status transaksi default tidak valid.', 'error', recurring_redirect());
    }

    if (!in_array((string) $isActiveRaw, ['0', '1'], true)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Status template transaksi berulang tidak valid.', 'error', recurring_redirect());
    }

    if ($mulaiDari === false || $berakhirPada === false) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal mulai atau tanggal berakhir tidak valid.', 'error', recurring_redirect());
    }

    if ($berakhirPada !== null && $mulaiDari > $berakhirPada) {
        show_sweetalert_and_redirect('Data tidak valid', 'Tanggal mulai tidak boleh lebih besar dari tanggal berakhir.', 'error', recurring_redirect());
    }

    $isActive = (string) $isActiveRaw === '1' ? 1 : 0;
    $frekuensi = 'bulanan';

    if ($recurringId === null) {
        $stmt = $con->prepare("INSERT INTO recurring_transaction
            (user_id, tipe_transaksi, id_kategori, id_wallet, nama_recurring, catatan, jumlah, frekuensi, tanggal_generate, status_transaksi_default, mulai_dari, berakhir_pada, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param(
            "isiissdsisssi",
            $userId,
            $tipeTransaksi,
            $kategoriId,
            $walletId,
            $namaRecurring,
            $catatan,
            $jumlah,
            $frekuensi,
            $tanggalGenerate,
            $statusDefault,
            $mulaiDari,
            $berakhirPada,
            $isActive
        );
        $result = $stmt->execute();
        $newRecurringId = (int) $stmt->insert_id;
        $stmt->close();

        if ($result) {
            record_activity($con, 'recurring', 'tambah', "Menambahkan template recurring ID {$newRecurringId}.");
            show_sweetalert_and_redirect('Berhasil', 'Template transaksi berulang berhasil ditambahkan.', 'success', recurring_redirect());
        }

        show_sweetalert_and_redirect('Gagal', 'Template transaksi berulang gagal ditambahkan.', 'error', recurring_redirect());
    }

    if ($recurringId <= 0) {
        show_sweetalert_and_redirect('Akses ditolak', 'Template transaksi berulang tidak ditemukan.', 'warning', recurring_redirect());
    }

    try {
        $con->begin_transaction();
        $existingRecurring = fetch_recurring_for_user($con, $recurringId, $userId, true);
        if (!$existingRecurring) {
            throw new DomainException('Template transaksi berulang tidak ditemukan atau bukan milik Anda.');
        }

        if ((string) $existingRecurring['tipe_transaksi'] !== $tipeTransaksi
            && recurring_template_has_history($con, $recurringId, $userId)) {
            throw new DomainException('Tipe template yang sudah memiliki riwayat generate tidak dapat diubah. Buat template baru untuk tipe transaksi berbeda.');
        }

        $stmt = $con->prepare("UPDATE recurring_transaction
                               SET tipe_transaksi = ?, id_kategori = ?, id_wallet = ?, nama_recurring = ?, catatan = ?, jumlah = ?, frekuensi = ?, tanggal_generate = ?, status_transaksi_default = ?, mulai_dari = ?, berakhir_pada = ?, is_active = ?, updated_at = NOW()
                               WHERE id_recurring = ? AND user_id = ?");
        if (!$stmt) {
            throw new RuntimeException('Prepare edit recurring gagal.');
        }
        $stmt->bind_param(
            "siissdsisssiii",
            $tipeTransaksi,
            $kategoriId,
            $walletId,
            $namaRecurring,
            $catatan,
            $jumlah,
            $frekuensi,
            $tanggalGenerate,
            $statusDefault,
            $mulaiDari,
            $berakhirPada,
            $isActive,
            $recurringId,
            $userId
        );
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Edit recurring gagal.');
        }
        $stmt->close();
        $con->commit();
    } catch (DomainException $exception) {
        recurring_rollback($con);
        show_sweetalert_and_redirect('Tidak dapat mengubah template', $exception->getMessage(), 'warning', recurring_redirect());
    } catch (Throwable $exception) {
        recurring_rollback($con);
        error_log('Edit recurring gagal: ' . $exception->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Template transaksi berulang gagal diperbarui.', 'error', recurring_redirect());
    }

    record_activity($con, 'recurring', 'edit', "Mengubah template recurring ID {$recurringId}.");
    show_sweetalert_and_redirect('Berhasil', 'Template transaksi berulang berhasil diperbarui.', 'success', recurring_redirect());
}

if ($act === 's') {
    require_recurring_post_csrf();

    $recurringId = (int) ($_POST['id_recurring'] ?? 0);
    $isActiveRaw = $_POST['is_active'] ?? '';

    if ($recurringId <= 0 || !in_array((string) $isActiveRaw, ['0', '1'], true)) {
        show_sweetalert_and_redirect('Data tidak valid', 'Permintaan status transaksi berulang tidak valid.', 'error', recurring_redirect());
    }

    if (!fetch_recurring_for_user($con, $recurringId, $userId)) {
        show_sweetalert_and_redirect('Akses ditolak', 'Template transaksi berulang tidak ditemukan.', 'warning', recurring_redirect());
    }

    $isActive = (int) $isActiveRaw;

    $stmt = $con->prepare("UPDATE recurring_transaction
                           SET is_active = ?, updated_at = NOW()
                           WHERE id_recurring = ? AND user_id = ?");
    $stmt->bind_param("iii", $isActive, $recurringId, $userId);
    $result = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($result && $affectedRows >= 0) {
        $message = $isActive === 1 ? 'Template transaksi berulang berhasil diaktifkan.' : 'Template transaksi berulang berhasil dinonaktifkan.';
        $statusLabel = $isActive === 1 ? 'aktif' : 'nonaktif';
        record_activity($con, 'recurring', 'toggle', "Mengubah template recurring ID {$recurringId} menjadi {$statusLabel}.");
        show_sweetalert_and_redirect('Berhasil', $message, 'success', recurring_redirect());
    }

    show_sweetalert_and_redirect('Gagal', 'Status template transaksi berulang gagal diperbarui.', 'error', recurring_redirect());
}

if ($act === 'h') {
    require_recurring_post_csrf();

    $recurringId = (int) ($_POST['id_recurring'] ?? 0);
    if ($recurringId <= 0) {
        show_sweetalert_and_redirect('Data tidak valid', 'ID template recurring tidak valid.', 'error', recurring_redirect());
    }

    try {
        $con->begin_transaction();
        $template = fetch_recurring_for_user($con, $recurringId, $userId, true);
        if (!$template) {
            throw new DomainException('Template recurring tidak ditemukan atau bukan milik Anda.');
        }

        if (recurring_template_has_history($con, $recurringId, $userId)) {
            throw new DomainException('Template yang sudah memiliki riwayat generate tidak dapat dihapus. Nonaktifkan template agar riwayat transaksi tetap utuh.');
        }

        $stmt = $con->prepare('DELETE FROM recurring_transaction WHERE id_recurring = ? AND user_id = ?');
        if (!$stmt) {
            throw new RuntimeException('Prepare hapus recurring gagal.');
        }
        $stmt->bind_param('ii', $recurringId, $userId);
        if (!$stmt->execute() || $stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Hapus recurring gagal.');
        }
        $stmt->close();
        $con->commit();
    } catch (DomainException $exception) {
        recurring_rollback($con);
        show_sweetalert_and_redirect('Tidak dapat menghapus template', $exception->getMessage(), 'warning', recurring_redirect());
    } catch (Throwable $exception) {
        recurring_rollback($con);
        error_log('Hapus recurring gagal: ' . $exception->getMessage());
        show_sweetalert_and_redirect('Gagal', 'Template recurring gagal dihapus.', 'error', recurring_redirect());
    }

    record_activity($con, 'recurring', 'hapus', "Menghapus template recurring ID {$recurringId} yang belum memiliki riwayat generate.");
    show_sweetalert_and_redirect('Berhasil', 'Template recurring berhasil dihapus.', 'success', recurring_redirect());
}

if ($act === 'g') {
    require_recurring_post_csrf();

    $today = new DateTimeImmutable('today');
    $periodeBulan = (int) $today->format('n');
    $periodeTahun = (int) $today->format('Y');
    $startMonth = $today->modify('first day of this month')->format('Y-m-d');
    $endMonth = $today->modify('last day of this month')->format('Y-m-d');

    $query = "SELECT id_recurring
              FROM recurring_transaction
              WHERE user_id = ?
                AND is_active = 1
                AND mulai_dari <= ?
                AND (berakhir_pada IS NULL OR berakhir_pada >= ?)
              ORDER BY id_recurring ASC";
    $stmt = $con->prepare($query);
    $stmt->bind_param("iss", $userId, $endMonth, $startMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    $generated = 0;
    $skippedDuplicate = 0;
    $skippedSchedule = 0;
    $failed = 0;
    $insufficientBalance = 0;

    while ($row = $result->fetch_assoc()) {
        $recurringId = (int) $row['id_recurring'];
        $generationResult = generate_recurring_occurrence($con, $recurringId, $userId, $today);

        if ($generationResult['status'] === 'generated') {
            $generated++;
        } elseif ($generationResult['status'] === 'duplicate') {
            $skippedDuplicate++;
        } elseif ($generationResult['status'] === 'skipped') {
            $skippedSchedule++;
        } else {
            $failed++;
            if ($generationResult['reason'] === 'Saldo wallet tidak mencukupi untuk recurring pengeluaran ini.') {
                $insufficientBalance++;
            }
        }
    }

    $stmt->close();

    if ($generated > 0 && $failed === 0) {
        $text = "{$generated} transaksi berhasil dibuat untuk bulan ini.";
        if ($skippedDuplicate > 0) {
            $text .= " {$skippedDuplicate} template dilewati karena sudah pernah digenerate.";
        }
        record_activity($con, 'recurring', 'generate', "Generate bulan ini: {$generated} dibuat, {$skippedDuplicate} dilewati, {$failed} gagal.");
        show_sweetalert_and_redirect('Generate selesai', $text, 'success', recurring_redirect());
    }

    if ($generated > 0) {
        record_activity($con, 'recurring', 'generate', "Generate bulan ini: {$generated} dibuat, {$skippedDuplicate} dilewati, {$failed} gagal.");
        show_sweetalert_and_redirect('Generate sebagian selesai', "{$generated} transaksi dibuat, {$failed} template gagal diproses, {$skippedDuplicate} template sudah pernah digenerate.", 'warning', recurring_redirect());
    }

    if ($skippedDuplicate > 0 && $failed === 0) {
        show_sweetalert_and_redirect('Tidak ada transaksi baru', 'Semua template aktif sudah pernah digenerate untuk bulan ini.', 'info', recurring_redirect());
    }

    if ($failed > 0) {
        $message = "{$failed} template gagal diproses.";
        if ($insufficientBalance > 0) {
            $message .= " {$insufficientBalance} recurring pengeluaran gagal karena saldo wallet tidak mencukupi.";
        }
        $message .= ' Silakan cek kembali kategori, wallet, saldo, dan konfigurasi template.';
        show_sweetalert_and_redirect('Generate gagal', $message, 'error', recurring_redirect());
    }

    show_sweetalert_and_redirect('Tidak ada transaksi dibuat', 'Belum ada template aktif yang memenuhi periode bulan ini.', 'info', recurring_redirect());
}

show_sweetalert_and_redirect('Aksi tidak valid', 'Permintaan transaksi berulang tidak dikenali.', 'error', recurring_redirect());
?>
