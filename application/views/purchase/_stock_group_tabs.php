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
        ['key' => 'movement',       'label' => 'mutasi Bahan Baku',      'url' => site_url('inventory/stock/division/movement')],
        ['key' => 'stok_awal',      'label' => 'Stok Awal Bahan Baku',   'url' => site_url('inventory/stock/stok-awal/division')],
        ['key' => 'opening',        'label' => 'Opening Manual Bahan Baku', 'url' => site_url('inventory/stock/opening/division')],
        ['key' => 'opname_monthly', 'label' => 'Opname Bahan Baku',     'url' => site_url('inventory/stock/opname/division/monthly')],
        ['key' => 'lot',            'label' => 'Lot Bahan Baku',         'url' => site_url('inventory/stock/division/lot')],
        ['key' => 'fifo_audit',     'label' => 'FIFO Audit Bahan Baku',  'url' => site_url('inventory/fifo-audit')],
        ['key' => 'compare',        'label' => 'Audit Bahan Baku',       'url' => site_url('inventory/stock/division/reconcile')],
    ];
} else {
    $links = [
        ['key' => 'daily_matrix',  'label' => 'Snapshot Harian Gudang', 'url' => site_url('inventory-warehouse-daily')],
        ['key' => 'stock',         'label' => 'Stok Gudang',            'url' => site_url('inventory/stock/warehouse')],
        ['key' => 'opening',       'label' => 'Opening Gudang',         'url' => site_url('inventory/stock/opening/warehouse')],
        ['key' => 'adjustment',    'label' => 'Adjustment Gudang',      'url' => site_url('inventory/stock/adjustment/warehouse')],
        ['key' => 'movement',      'label' => 'Keluar Masuk Gudang',    'url' => site_url('inventory/stock/warehouse/movement')],
        ['key' => 'daily',         'label' => 'Stok Bulanan/Daily',     'url' => site_url('inventory/stock/warehouse/daily')],
        ['key' => 'lot',           'label' => 'Lot Gudang',             'url' => site_url('inventory/stock/warehouse/lot')],
        ['key' => 'fifo_audit',    'label' => 'FIFO Audit Gudang',      'url' => site_url('inventory/fifo-audit')],
        ['key' => 'opname_monthly','label' => 'Opname Bulanan',         'url' => site_url('inventory/stock/opname/warehouse/monthly')],
    ];
}

foreach ($links as $link):
?>
  <a href="<?php echo $link['url']; ?>" class="<?php echo $buttonClass((string)$link['key']); ?>"><?php echo html_escape((string)$link['label']); ?></a>
<?php endforeach; ?>
