-- CashFlow Control - Stabilization A rollback
-- Rollback berhenti otomatis jika masih ada wallet bertipe kartu.
-- Query pemeriksaan read-only sebelum menjalankan:
-- SELECT COUNT(*) AS wallet_kartu FROM `wallet` WHERE `tipe_wallet` = 'kartu';
-- Script ini tidak melakukan backfill/DML.

DROP PROCEDURE IF EXISTS `cashflow_rollback_wallet_card_type`;

DELIMITER //
CREATE PROCEDURE `cashflow_rollback_wallet_card_type`()
BEGIN
  IF EXISTS (SELECT 1 FROM `wallet` WHERE `tipe_wallet` = 'kartu' LIMIT 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Rollback dibatalkan: masih ada wallet bertipe kartu.';
  ELSE
    ALTER TABLE `wallet`
      MODIFY COLUMN `tipe_wallet`
        ENUM('cash','bank','e_wallet','tabungan','lainnya')
        NOT NULL DEFAULT 'lainnya';
  END IF;
END//
DELIMITER ;

CALL `cashflow_rollback_wallet_card_type`();
DROP PROCEDURE IF EXISTS `cashflow_rollback_wallet_card_type`;

-- Verifikasi manual:
-- SHOW COLUMNS FROM `wallet` LIKE 'tipe_wallet';
