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
        ['key' => 'daily_matrix', 'label' => 'Daily Material Matrix', 'url' => site_url('inventory-material-daily')],
        ['key' => 'stock',        'label' => 'Stok Divisi',            'url' => site_url('inventory/stock/division')],
        ['key' => 'opening',      'label' => 'Opening',                'url' => site_url('inventory/stock/opening/division')],
        ['key' => 'daily_recon',  'label' => 'Daily Recon',            'url' => site_url('inventory/stock/daily-recon/division')],
        ['key' => 'adjustment',   'label' => 'Adjustment',             'url' => site_url('inventory/stock/adjustment/division')],
        ['key' => 'movement',     'label' => 'Keluar Masuk',           'url' => site_url('inventory/stock/division/movement')],
        ['key' => 'daily',        'label' => 'Stok Bulanan / Daily',   'url' => site_url('inventory/stock/division/daily')],
        ['key' => 'compare',      'label' => 'Banding Stok Akhir',     'url' => site_url('inventory/stock/division/reconcile')],
        ['key' => 'lot',          'label' => 'Lot',                    'url' => site_url('inventory/stock/division/lot')],
    ];
} else {
    $links = [
        ['key' => 'daily_matrix', 'label' => 'Snapshot Harian Gudang', 'url' => site_url('inventory-warehouse-daily')],
        ['key' => 'stock', 'label' => 'Stok Gudang', 'url' => site_url('inventory/stock/warehouse')],
        ['key' => 'opening', 'label' => 'Opening Gudang', 'url' => site_url('inventory/stock/opening/warehouse')],
        ['key' => 'adjustment', 'label' => 'Adjustment Gudang', 'url' => site_url('inventory/stock/adjustment/warehouse')],
        ['key' => 'movement', 'label' => 'Keluar Masuk Gudang', 'url' => site_url('inventory/stock/warehouse/movement')],
        ['key' => 'daily', 'label' => 'Stok Bulanan/Daily', 'url' => site_url('inventory/stock/warehouse/daily')],
        ['key' => 'lot', 'label' => 'Lot Gudang', 'url' => site_url('inventory/stock/warehouse/lot')],
    ];
}

foreach ($links as $link):
?>
  <a href="<?php echo $link['url']; ?>" class="<?php echo $buttonClass((string)$link['key']); ?>"><?php echo html_escape((string)$link['label']); ?></a>
<?php endforeach; ?>
