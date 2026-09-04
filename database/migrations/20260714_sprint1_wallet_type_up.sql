-- CashFlow Control - Sprint 1
-- Custom tipe wallet (forward migration)
-- Jalankan sekali secara manual setelah membuat backup database.
-- Kompatibilitas: wallet legacy tetap memakai tipe_wallet dan id_wallet_type tetap NULL.

-- Catatan: DDL MySQL/MariaDB melakukan implicit commit dan tidak transactional.

CREATE TABLE `wallet_type` (
  `id_wallet_type` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `nama_tipe` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(32) NOT NULL DEFAULT 'credit-card',
  `warna` CHAR(7) NOT NULL DEFAULT '#64748B',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_wallet_type`),
  UNIQUE KEY `uniq_wallet_type_user_name` (`user_id`, `nama_tipe`),
  UNIQUE KEY `uniq_wallet_type_owner` (`id_wallet_type`, `user_id`),
  KEY `idx_wallet_type_user_active` (`user_id`, `is_active`),
  CONSTRAINT `fk_wallet_type_user`
    FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `wallet`
  ADD COLUMN `id_wallet_type` INT(11) DEFAULT NULL AFTER `tipe_wallet`,
  ADD KEY `idx_wallet_custom_type_owner` (`id_wallet_type`, `user_id`),
  ADD CONSTRAINT `fk_wallet_custom_type_owner`
    FOREIGN KEY (`id_wallet_type`, `user_id`)
    REFERENCES `wallet_type` (`id_wallet_type`, `user_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT;

-- Verifikasi read-only setelah migration:
-- SHOW COLUMNS FROM wallet LIKE 'id_wallet_type';
-- SHOW CREATE TABLE wallet_type;
-- SELECT COUNT(*) FROM wallet WHERE id_wallet_type IS NOT NULL;
