<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT') {
    return 'Event';
  }
  if ($value === 'BAR' || $value === 'KITCHEN') {
    return 'Reguler';
  }
  return $value !== '' ? $value : '-';
};
$movementOptions = [
  '' => 'Semua Jenis',
  'OPENING' => 'Opening',
  'PRODUCTION_IN' => 'Hasil Produksi',
  'PRODUCTION_OUT' => 'Pemakaian Produksi',
  'TRANSFER_IN' => 'Transfer Masuk',
  'TRANSFER_OUT' => 'Transfer Keluar',
  'USAGE' => 'Pemakaian',
  'WASTE' => 'Waste',
  'SPOIL' => 'Spoilage',
  'ADJUSTMENT_PLUS' => 'Adjustment Plus',
  'ADJUSTMENT_MINUS' => 'Adjustment Minus',
  'VOID_REVERSE' => 'Pembatalan Void',
];
$formatQty = static function ($value): string {
  return number_format((float)$value, 2, ',', '.');
};
$buildSourceLabel = static function (array $row): array {
  $sourceTable = strtolower(trim((string)($row['source_table'] ?? '')));
  $sourceModule = strtoupper(trim((string)($row['source_module'] ?? '')));
  $sourceId = (int)($row['source_id'] ?? 0);
  $sourceLineId = (int)($row['source_line_id'] ?? 0);
  $labelMap = [
    'pos_stock_commit' => 'POS Commit',
    'inv_component_batch' => 'Batch Produksi',
    'inv_component_adjustment' => 'Adjustment Component',
    'inv_component_opening' => 'Opening Component',
  ];
  $label = $labelMap[$sourceTable] ?? ($sourceTable !== '' ? strtoupper(str_replace('_', ' ', $sourceTable)) : ($sourceModule !== '' ? $sourceModule : '-'));
  if ($sourceId > 0) {
    $label .= ' #' . $sourceId;
  }
  $meta = [];
  if ($sourceModule !== '') {
    $meta[] = 'Module ' . $sourceModule;
  }
  if ($sourceLineId > 0) {
    $meta[] = 'Line #' . $sourceLineId;
  }
  if (!empty($row['lot_no_snapshot'])) {
    $meta[] = 'Lot ' . trim((string)$row['lot_no_snapshot']);
  }
  if (!empty($row['received_date_snapshot'])) {
    $meta[] = 'Terima ' . trim((string)$row['received_date_snapshot']);
  }
  return ['label' => $label, 'meta' => implode(' | ', $meta)];
};
?>

<style>
  .component-movement-wrap {
    max-height: 74vh;
    overflow: auto;
    border: 1px solid #e8d2c3;
    border-radius: 18px;
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 18px 36px -30px rgba(95, 53, 39, .45);
  }
  .component-movement-table {
    min-width: 1580px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .component-movement-table thead th {
    position: sticky;
    top: 0;
    z-index: 4;
    background: linear-gradient(180deg, #7c1f2d 0%, #9f2f3e 100%);
    color: #fff8f5;
    border-bottom: 1px solid #7f2936;
    white-space: nowrap;
  }
  .component-movement-table td,
  .component-movement-table th {
    vertical-align: top;
    border-right: 1px solid #efddd2;
    border-bottom: 1px solid #f3e4da;
    font-size: .83rem;
  }
  .component-movement-table tbody td {
    background: #fff;
  }
  .component-movement-table tbody tr:nth-child(even) td {
    background: #fffaf6;
  }
  .component-movement-metric {
    line-height: 1.2;
  }
  .component-movement-metric strong {
    display: block;
    font-size: .9rem;
    color: #2f2628;
  }
  .component-movement-metric small {
    color: #8a776f;
  }
  .component-movement-delta-plus strong { color: #166534; }
  .component-movement-delta-minus strong { color: #b42318; }
  .component-movement-source-title {
    font-weight: 800;
    color: #2f2628;
  }
  .component-movement-source-meta,
  .component-movement-source-note {
    display: block;
    color: #8a776f;
    font-size: .72rem;
    line-height: 1.35;
    margin-top: .2rem;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-exchange-funds-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Mutasi Base/Prepare'); ?></h4>
  <small class="text-muted">Read-only ledger keluar masuk base/prepare.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'movement']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-movements'),
  'component_type_filters' => $filters,
  'component_type_active' => (string)($filters['type'] ?? ''),
]); ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-movements'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No mutasi / nama component">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Jenis</label>
        <select name="movement_type" class="form-select">
          <?php foreach ($movementOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['movement_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari</label>
        <input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai</label>
        <input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Baris</label>
        <input type="number" name="limit" min="1" max="500" class="form-control" value="<?php echo (int)($filters['limit'] ?? 50); ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary w-100">Go</button>
        <a href="<?php echo site_url('production/component-movements'); ?>" class="btn btn-outline-danger w-100">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="component-movement-wrap">
    <table class="table table-striped table-hover component-movement-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No</th>
          <th>Lokasi</th>
          <th>Komponen</th>
          <th>Jenis</th>
          <th class="text-end">Before</th>
          <th class="text-end">Delta</th>
          <th class="text-end">After</th>
          <th class="text-end">Unit Cost</th>
          <th class="text-end">Total Cost</th>
          <th>Sumber</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data mutasi komponen.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $source = $buildSourceLabel((array)$row); ?>
            <?php $deltaQty = (float)($row['qty_delta'] ?? 0); ?>
            <tr>
              <td>
                <div><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape(substr((string)($row['movement_datetime'] ?? ''), 11, 8) ?: '-'); ?></small>
              </td>
              <td><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></td>
              <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? '-'))); ?></td>
              <td>
                <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?> | <?php echo html_escape((string)($row['division_name'] ?? '-')); ?></small>
              </td>
              <td><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
              <td class="text-end">
                <div class="component-movement-metric">
                  <strong><?php echo $formatQty($row['qty_before'] ?? 0); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end <?php echo $deltaQty >= 0 ? 'component-movement-delta-plus' : 'component-movement-delta-minus'; ?>">
                <div class="component-movement-metric">
                  <strong><?php echo $formatQty($deltaQty); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end">
                <div class="component-movement-metric">
                  <strong><?php echo $formatQty($row['qty_after'] ?? 0); ?></strong>
                  <small><?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td>
                <span class="component-movement-source-title"><?php echo html_escape((string)($source['label'] ?? '-')); ?></span>
                <?php if (!empty($source['meta'])): ?><span class="component-movement-source-meta"><?php echo html_escape((string)$source['meta']); ?></span><?php endif; ?>
                <?php if (!empty($row['notes'])): ?><span class="component-movement-source-note"><?php echo html_escape((string)$row['notes']); ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

