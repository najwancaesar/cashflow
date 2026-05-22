<?php

if (!function_exists('activity_log_table_exists')) {
    function activity_log_table_exists($con)
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = @$con->prepare("SELECT COUNT(*)
                                    FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = DATABASE()
                                      AND TABLE_NAME = 'activity_log'");
            if (!$stmt) {
                $exists = false;
                return false;
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_row() : [0];
            $stmt->close();

            $exists = (int) ($row[0] ?? 0) > 0;
            return $exists;
        } catch (Throwable $exception) {
            $exists = false;
            return false;
        }
    }
}

if (!function_exists('record_activity')) {
    function record_activity($con, $module, $aksi, $deskripsi = null, $userId = null, $role = null)
    {
        try {
            if (!$con || !activity_log_table_exists($con)) {
                return false;
            }

            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $userId = $userId !== null ? (int) $userId : (int) ($_SESSION['id_user'] ?? 0);
            $dbUserId = $userId > 0 ? $userId : null;
            $role = $role !== null ? strtolower((string) $role) : strtolower((string) ($_SESSION['role'] ?? ''));
            $dbRole = in_array($role, ['admin', 'user'], true) ? $role : null;
            $module = substr(trim((string) $module), 0, 80);
            $aksi = substr(trim((string) $aksi), 0, 120);
            $deskripsi = $deskripsi !== null ? trim((string) $deskripsi) : null;
            $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
            $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

            if ($module === '' || $aksi === '') {
                return false;
            }

            $stmt = @$con->prepare("INSERT INTO activity_log
                (user_id, role, module, aksi, deskripsi, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param("issssss", $dbUserId, $dbRole, $module, $aksi, $deskripsi, $ipAddress, $userAgent);
            $result = $stmt->execute();
            $stmt->close();

            return (bool) $result;
        } catch (Throwable $exception) {
            return false;
        }
    }
}
?>
