ALTER TABLE `transfer_wallet`
DROP FOREIGN KEY `fk_transfer_wallet_asal`,
DROP FOREIGN KEY `fk_transfer_wallet_tujuan`,
DROP FOREIGN KEY `fk_transfer_user`;

ALTER TABLE `piutang`
DROP FOREIGN KEY `fk_piutang_pemasukan`,
DROP FOREIGN KEY `fk_piutang_user`;

ALTER TABLE `hutang`
DROP FOREIGN KEY `fk_hutang_pengeluaran`,
DROP FOREIGN KEY `fk_hutang_user`;

ALTER TABLE `pengeluaran`
DROP FOREIGN KEY `fk_pengeluaran_wallet`,
DROP FOREIGN KEY `fk_pengeluaran_user`,
DROP FOREIGN KEY `fk_pengeluaran_kategori`;

ALTER TABLE `pemasukan`
DROP FOREIGN KEY `fk_pemasukan_wallet`,
DROP FOREIGN KEY `fk_pemasukan_user`,
DROP FOREIGN KEY `fk_pemasukan_kategori`;

-- Revert id_kategori to NOT NULL and convert NULLs back to 0
UPDATE `pengeluaran` SET `id_kategori` = 0 WHERE `id_kategori` IS NULL;
ALTER TABLE `pengeluaran` MODIFY COLUMN `id_kategori` int(11) NOT NULL;

UPDATE `pemasukan` SET `id_kategori` = 0 WHERE `id_kategori` IS NULL;
ALTER TABLE `pemasukan` MODIFY COLUMN `id_kategori` int(11) NOT NULL;
