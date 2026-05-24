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
            ['key' => 'stock', 'label' => 'Stok Base/Prepare', 'url' => site_url('production/component-stock')],
            ['key' => 'movement', 'label' => 'Mutasi', 'url' => site_url('production/component-movements')],
            ['key' => 'daily', 'label' => 'Daily Matrix', 'url' => site_url('production/component-daily')],
            ['key' => 'opening', 'label' => 'Opening', 'url' => site_url('production/component-openings')],
            ['key' => 'adjustment', 'label' => 'Adjustment', 'url' => site_url('production/component-adjustments')],
            ['key' => 'batch', 'label' => 'Batch Produksi', 'url' => site_url('production/component-batches')],
        ],
    ],
];

$buttonClass = static function (string $key) use ($activeTab): string {
    return $activeTab === $key ? 'btn btn-sm btn-dark' : 'btn btn-sm btn-outline-secondary';
};
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
  }
  .component-action-btn i {
    line-height: 1;
  }
</style>

<?php foreach ($groups as $group): ?>
  <div class="d-flex flex-wrap gap-2 align-items-start mb-2 component-workbench-group">
    <div class="component-workbench-label"><?php echo html_escape((string)$group['label']); ?></div>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($group['links'] as $link): ?>
        <a href="<?php echo $link['url']; ?>" class="<?php echo $buttonClass((string)$link['key']); ?>"><?php echo html_escape((string)$link['label']); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>