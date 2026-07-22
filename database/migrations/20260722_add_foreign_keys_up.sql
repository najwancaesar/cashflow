-- Allow NULL values for optional category and set existing 0s to NULL
ALTER TABLE `pemasukan` MODIFY COLUMN `id_kategori` int(11) NULL DEFAULT NULL;
UPDATE `pemasukan` SET `id_kategori` = NULL WHERE `id_kategori` = 0;

ALTER TABLE `pengeluaran` MODIFY COLUMN `id_kategori` int(11) NULL DEFAULT NULL;
UPDATE `pengeluaran` SET `id_kategori` = NULL WHERE `id_kategori` = 0;

-- Now add foreign key constraints
ALTER TABLE `pemasukan`
ADD CONSTRAINT `fk_pemasukan_wallet` FOREIGN KEY (`id_wallet`) REFERENCES `wallet`(`id_wallet`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_pemasukan_user` FOREIGN KEY (`user`) REFERENCES `user`(`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_pemasukan_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `pengeluaran`
ADD CONSTRAINT `fk_pengeluaran_wallet` FOREIGN KEY (`id_wallet`) REFERENCES `wallet`(`id_wallet`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_pengeluaran_user` FOREIGN KEY (`user`) REFERENCES `user`(`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_pengeluaran_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `hutang`
ADD CONSTRAINT `fk_hutang_pengeluaran` FOREIGN KEY (`id_pengeluaran`) REFERENCES `pengeluaran`(`id_pengeluaran`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_hutang_user` FOREIGN KEY (`user`) REFERENCES `user`(`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `piutang`
ADD CONSTRAINT `fk_piutang_pemasukan` FOREIGN KEY (`id_pemasukan`) REFERENCES `pemasukan`(`id_pemasukan`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_piutang_user` FOREIGN KEY (`user`) REFERENCES `user`(`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `transfer_wallet`
ADD CONSTRAINT `fk_transfer_wallet_asal` FOREIGN KEY (`wallet_asal_id`) REFERENCES `wallet`(`id_wallet`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_transfer_wallet_tujuan` FOREIGN KEY (`wallet_tujuan_id`) REFERENCES `wallet`(`id_wallet`) ON DELETE RESTRICT ON UPDATE CASCADE,
ADD CONSTRAINT `fk_transfer_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE;
