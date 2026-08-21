<?php
$activeTab = strtolower(trim((string)($component_tab_active ?? 'stock')));
$groups = [
    [
        'label' => 'Master',
        'links' => [
            ['key' => 'category', 'label' => 'Kategori', 'url' => site_url('production/component-categories')],
            ['key' => 'master', 'label' => 'Master Component', 'url' => site_url('production/component-masters')],
            ['key' => 'formula', 'label' => 'Formula', 'url' => site_url('production/component-formulas')],
            ['key' => 'variable-cost', 'label' => 'Variable Cost', 'url' => site_url('production/component-cost-variables')],
        ],
    ],
    [
        'label' => 'Operasional',
        'links' => [
            ['key' => 'batch',       'label' => 'Batch Produksi',   'url' => site_url('production/component-batches')],
            ['key' => 'daily',       'label' => 'Daily Matrix',     'url' => site_url('production/component-daily')],
            ['key' => 'daily_recon', 'label' => 'Daily Recon',      'url' => site_url('production/component-daily-recon')],
            ['key' => 'stock',       'label' => 'Stok Base/Prepare','url' => site_url('production/component-stock')],
            ['key' => 'monthly',     'label' => 'Stok Bulanan',     'url' => site_url('production/component-monthly')],
            ['key' => 'adjustment',      'label' => 'Adjustment',       'url' => site_url('production/component-adjustments')],
            ['key' => 'opening_monthly', 'label' => 'Opening Bulanan',  'url' => site_url('production/component-opening-monthly')],
            ['key' => 'opening',         'label' => 'Opening',          'url' => site_url('production/component-openings')],
            ['key' => 'movement',    'label' => 'Mutasi',           'url' => site_url('production/component-movements')],
            ['key' => 'lot',         'label' => 'Lot FIFO',         'url' => site_url('production/component-lots')],
            ['key' => 'reconcile',   'label' => 'Reconcile',        'url' => site_url('production/component-reconcile')],
            ['key' => 'opname',      'label' => 'Opname',           'url' => site_url('production/component-opname')],
        ],
    ],
];

$buttonClass = [
    'Master'      => static function (string $key) use ($activeTab): string {
        return $activeTab === $key
            ? 'btn btn-sm btn-info'
            : 'btn btn-sm btn-outline-info';
    },
    'Operasional' => static function (string $key) use ($activeTab): string {
        return $activeTab === $key
            ? 'btn btn-sm btn-primary'
            : 'btn btn-sm btn-outline-primary';
    },
];
?>

<style>
  .component-workbench-group + .component-workbench-group {
    margin-top: .55rem;
  }
  .component-workbench-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .35rem;
  }
  .component-action-stack {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: nowrap;
    justify-content: center;
  }
  .component-action-cell {
    white-space: nowrap;
    width: 1%;
    text-align: center;
  }
  .component-action-btn {
    flex-shrink: 0;
    width: 38px !important;
    min-width: 38px !important;
    height: 38px !important;
    padding: 0 !important;
    border-radius: 10px !important;
  }
  .component-action-btn i,
  .component-action-btn [class^="ri-"],
  .component-action-btn [class*=" ri-"] {
    font-size: 1.12rem !important;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
</style>

<?php foreach ($groups as $group):
    $btnFn = $buttonClass[$group['label']] ?? $stdBtn;
?>
  <div class="d-flex flex-wrap gap-2 align-items-start mb-2 component-workbench-group">
    <div class="component-workbench-label"><?php echo html_escape((string)$group['label']); ?></div>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($group['links'] as $link): ?>
        <a href="<?php echo $link['url']; ?>" class="<?php echo $btnFn((string)$link['key']); ?>"><?php echo html_escape((string)$link['label']); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>