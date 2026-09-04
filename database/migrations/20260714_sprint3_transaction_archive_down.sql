-- CashFlow Control - Sprint 3A
-- Transaction archive metadata (rollback migration)
-- PERINGATAN: rollback menghapus metadata archive. Pastikan seluruh row yang perlu
-- dikembalikan sudah di-restore dan backup terbaru tersedia sebelum menjalankan.

ALTER TABLE `piutang`
  DROP KEY `idx_piutang_user_archive`,
  DROP COLUMN `archived_by`,
  DROP COLUMN `archived_at`;

ALTER TABLE `hutang`
  DROP KEY `idx_hutang_user_archive`,
  DROP COLUMN `archived_by`,
  DROP COLUMN `archived_at`;

ALTER TABLE `transfer_wallet`
  DROP KEY `idx_transfer_user_archive`,
  DROP COLUMN `archived_by`,
  DROP COLUMN `archived_at`;

ALTER TABLE `pengeluaran`
  DROP KEY `idx_pengeluaran_user_archive`,
  DROP COLUMN `archived_by`,
  DROP COLUMN `archived_at`;

ALTER TABLE `pemasukan`
  DROP KEY `idx_pemasukan_user_archive`,
  DROP COLUMN `archived_by`,
  DROP COLUMN `archived_at`;
