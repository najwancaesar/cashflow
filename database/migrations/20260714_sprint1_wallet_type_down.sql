-- CashFlow Control - Sprint 1
-- Rollback custom tipe wallet.
-- PERINGATAN: rollback menghapus seluruh definisi tipe kustom.
-- Backup database dan pastikan seluruh wallet sudah kembali memakai tipe legacy.
-- Preflight read-only (opsional) untuk melihat apakah rollback dapat dijalankan:
-- SELECT COUNT(*) AS wallet_masih_memakai_tipe_kustom FROM wallet WHERE id_wallet_type IS NOT NULL;
-- Nama/icon/warna tipe kustom tidak dapat dipulihkan setelah DROP TABLE tanpa backup.
-- Script akan berhenti dengan SQLSTATE 45000 jika masih ada wallet yang memakai tipe kustom.

-- Catatan: DDL MySQL/MariaDB melakukan implicit commit dan tidak transactional.

DROP PROCEDURE IF EXISTS `cashflow_rollback_wallet_type_sprint1`;

DELIMITER $$
CREATE PROCEDURE `cashflow_rollback_wallet_type_sprint1`()
BEGIN
  IF EXISTS (SELECT 1 FROM `wallet` WHERE `id_wallet_type` IS NOT NULL LIMIT 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rollback dibatalkan: masih ada wallet yang memakai tipe kustom.';
  ELSE
    ALTER TABLE `wallet`
      DROP FOREIGN KEY `fk_wallet_custom_type_owner`,
      DROP INDEX `idx_wallet_custom_type_owner`,
      DROP COLUMN `id_wallet_type`;

    DROP TABLE `wallet_type`;
  END IF;
END$$
DELIMITER ;

CALL `cashflow_rollback_wallet_type_sprint1`();
DROP PROCEDURE IF EXISTS `cashflow_rollback_wallet_type_sprint1`;
