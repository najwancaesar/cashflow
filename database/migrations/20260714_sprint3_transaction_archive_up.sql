-- CashFlow Control - Sprint 3A
-- Transaction archive metadata (forward migration)
-- Jalankan sekali secara manual setelah backup database terverifikasi.
-- DDL MySQL/MariaDB melakukan implicit commit dan tidak transactional.
-- Tidak ada UPDATE massal; seluruh row legacy tetap aktif karena archived_at NULL.

ALTER TABLE `pemasukan`
  ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`,
  ADD KEY `idx_pemasukan_user_archive` (`user`, `archived_at`);

ALTER TABLE `pengeluaran`
  ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`,
  ADD KEY `idx_pengeluaran_user_archive` (`user`, `archived_at`);

ALTER TABLE `transfer_wallet`
  ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`,
  ADD KEY `idx_transfer_user_archive` (`user_id`, `archived_at`);

ALTER TABLE `hutang`
  ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`,
  ADD KEY `idx_hutang_user_archive` (`user`, `archived_at`);

ALTER TABLE `piutang`
  ADD COLUMN `archived_at` DATETIME DEFAULT NULL AFTER `status`,
  ADD COLUMN `archived_by` INT(11) DEFAULT NULL AFTER `archived_at`,
  ADD KEY `idx_piutang_user_archive` (`user`, `archived_at`);

-- Verifikasi read-only setelah migration:
-- SELECT TABLE_NAME, COLUMN_NAME
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('pemasukan','pengeluaran','transfer_wallet','hutang','piutang')
--   AND COLUMN_NAME IN ('archived_at','archived_by');
