-- CashFlow Control - Stabilization A
-- Menambahkan tipe wallet bawaan Kartu tanpa backfill atau perubahan data lama.
-- Jalankan hanya setelah backup dan verifikasi bahwa kolom masih menggunakan ENUM legacy.

ALTER TABLE `wallet`
  MODIFY COLUMN `tipe_wallet`
    ENUM('cash','bank','e_wallet','tabungan','kartu','lainnya')
    NOT NULL DEFAULT 'lainnya';

-- Verifikasi manual:
-- SHOW COLUMNS FROM `wallet` LIKE 'tipe_wallet';
