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
?>

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
      <div class="col-md-1 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary w-100">Go</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No</th>
          <th>Lokasi</th>
          <th>Komponen</th>
          <th>Jenis</th>
          <th class="text-end">Qty In</th>
          <th class="text-end">Qty Out</th>
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
            <tr>
              <td><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></td>
              <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? '-'))); ?></td>
              <td>
                <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></small>
              </td>
              <td><?php echo html_escape((string)($row['movement_type_label'] ?? $row['movement_type'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['qty_in'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['qty_out'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($row['source_module'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

