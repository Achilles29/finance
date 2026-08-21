<?php
$tabScope = strtoupper(trim((string)($tab_scope ?? 'WAREHOUSE')));
$activeTab = strtolower(trim((string)($active_tab ?? 'stock')));

if (!in_array($tabScope, ['WAREHOUSE', 'DIVISION'], true)) {
    return;
}

$buttonClass = static function (string $tabKey) use ($activeTab): string {
    return $activeTab === $tabKey ? 'btn btn-sm btn-dark' : 'btn btn-sm btn-outline-secondary';
};

if ($tabScope === 'DIVISION') {
    $links = [
        ['key' => 'daily_recon',    'label' => 'Daily Recon',            'url' => site_url('inventory/stock/daily-recon/division')],
        ['key' => 'daily_matrix',   'label' => 'Daily Material Matrix',  'url' => site_url('inventory-material-daily')],
        ['key' => 'stock',          'label' => 'Stok Bahan Baku Live',   'url' => site_url('inventory/stock/division')],
        ['key' => 'daily',          'label' => 'Stok Bahan Baku Bulanan','url' => site_url('inventory/stock/division/daily')],
        ['key' => 'adjustment',     'label' => 'Adjustment Bahan Baku',  'url' => site_url('inventory/stock/adjustment/division')],
        ['key' => 'transfer',       'label' => 'Mutasi Bahan Baku',      'url' => site_url('inventory/stock/transfer/division')],
        ['key' => 'movement',       'label' => 'Log Bahan Baku',         'url' => site_url('inventory/stock/division/movement')],
        ['key' => 'stok_awal',      'label' => 'Stok Awal Bahan Baku',   'url' => site_url('inventory/stock/stok-awal/division')],
        ['key' => 'opening',        'label' => 'Opening Manual Bahan Baku', 'url' => site_url('inventory/stock/opening/division')],
        ['key' => 'opname_monthly', 'label' => 'Opname Bahan Baku',     'url' => site_url('inventory/stock/opname/division/monthly')],
        ['key' => 'lot',            'label' => 'Lot Bahan Baku',         'url' => site_url('inventory/stock/division/lot')],
        ['key' => 'fifo_audit',     'label' => 'FIFO Audit Bahan Baku',  'url' => site_url('inventory/fifo-audit')],
        ['key' => 'compare',        'label' => 'Audit Bahan Baku',       'url' => site_url('inventory/stock/division/reconcile')],
    ];
} else {
    $links = [
        ['key' => 'daily_matrix',  'label' => 'Inventory Warehouse Daily',                 'url' => site_url('inventory-warehouse-daily')],
        ['key' => 'stock',         'label' => 'Stok Gudang',                               'url' => site_url('inventory/stock/warehouse')],
        ['key' => 'daily',         'label' => 'Stok Bulanan / Snapshot Harian Gudang',     'url' => site_url('inventory/stock/warehouse/daily')],
        ['key' => 'movement',      'label' => 'Keluar Masuk Stok Gudang',                  'url' => site_url('inventory/stock/warehouse/movement')],
        ['key' => 'stok_awal',     'label' => 'Stok Awal Gudang',                          'url' => site_url('inventory/stock/stok-awal/warehouse')],
        ['key' => 'opening',       'label' => 'Opening Manual Gudang',                     'url' => site_url('inventory/stock/opening/warehouse')],
        ['key' => 'adjustment',    'label' => 'Adjustment Stok Gudang',                    'url' => site_url('inventory/stock/adjustment/warehouse')],
        ['key' => 'opname_monthly','label' => 'Opname Bulanan Gudang',                     'url' => site_url('inventory/stock/opname/warehouse/monthly')],
    ];
}

?>
<div class="d-flex flex-wrap gap-1 align-items-center">
  <?php foreach ($links as $link): ?>
    <a href="<?php echo $link['url']; ?>" class="<?php echo $buttonClass((string)$link['key']); ?>"><?php echo html_escape((string)$link['label']); ?></a>
  <?php endforeach; ?>
</div>
<?php if ($tabScope === 'WAREHOUSE' && in_array($activeTab, ['lot', 'fifo_audit'], true)): ?>
  <div class="d-flex flex-wrap gap-1 align-items-center mt-2">
    <a href="<?php echo site_url('inventory/stock/warehouse/lot'); ?>" class="<?php echo $buttonClass('lot'); ?>">Audit Profil Gudang</a>
    <a href="<?php echo site_url('inventory/fifo-audit?scope=WAREHOUSE'); ?>" class="<?php echo $buttonClass('fifo_audit'); ?>">FIFO Audit Gudang</a>
  </div>
<?php endif; ?>
