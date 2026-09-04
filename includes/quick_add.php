<?php
$quickAddIsAdmin = strtolower((string) ($_SESSION['role'] ?? '')) === 'admin';
if ($quickAddIsAdmin) {
    return;
}

$quickAddItems = [
    ['module' => 'pemasukan', 'quick_add' => '1', 'icon' => 'fa-arrow-down', 'label' => 'Pemasukan', 'class' => 'text-success'],
    ['module' => 'pengeluaran', 'quick_add' => '1', 'icon' => 'fa-arrow-up', 'label' => 'Pengeluaran', 'class' => 'text-danger'],
    ['module' => 'transfer_wallet', 'quick_add' => '1', 'icon' => 'fa-exchange', 'label' => 'Transfer Wallet', 'class' => 'text-info'],
    ['module' => 'saving_goal', 'quick_add' => 'setor', 'icon' => 'fa-plus-circle', 'label' => 'Setor Celengan', 'class' => 'text-info'],
    ['module' => 'saving_goal', 'quick_add' => 'tarik', 'icon' => 'fa-minus-circle', 'label' => 'Tarik Celengan', 'class' => 'text-info'],
    ['module' => 'hutang', 'quick_add' => '1', 'icon' => 'fa-handshake-o', 'label' => 'Utang', 'class' => 'text-warning'],
    ['module' => 'piutang', 'quick_add' => '1', 'icon' => 'fa-money', 'label' => 'Piutang', 'class' => 'text-primary'],
];
?>
<div class="modal fade cashflow-quick-add-modal" id="globalQuickAddModal" tabindex="-1"
    aria-labelledby="globalQuickAddModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1" id="globalQuickAddModalLabel">Tambah Transaksi</h5>
                    <p class="text-xs text-secondary mb-0">Pilih jenis pencatatan yang ingin dibuat.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="cashflow-quick-add-grid">
                    <?php foreach ($quickAddItems as $item) { ?>
                        <a class="cashflow-quick-add-item"
                            href="main.php?module=<?= rawurlencode($item['module']) ?>&amp;quick_add=<?= rawurlencode($item['quick_add']) ?>">
                            <span class="cashflow-quick-add-icon <?= htmlspecialchars($item['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                            </span>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
