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
$locationFilterValue = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'EVENT') {
    return 'EVENT';
  }
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'REGULER') {
    return 'REGULER';
  }
  return '';
};
$buildLotUrl = static function (array $row, string $status = 'ALL') use ($locationFilterValue): string {
  $params = [
    'q' => trim((string)($row['component_code'] ?? $row['component_name'] ?? '')),
    'status' => $status,
    'location_type' => $locationFilterValue((string)($row['location_type'] ?? '')),
    'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
    'type' => strtoupper(trim((string)($row['component_type'] ?? ''))),
  ];
  return site_url('production/component-lots') . '?' . http_build_query(array_filter($params, static function ($value) {
    return $value !== null && $value !== '';
  }));
};
$lotAverageCost = static function (array $lotSummary): float {
  $balanceQty = (float)($lotSummary['balance_qty'] ?? 0);
  if ($balanceQty <= 0) {
    return 0.0;
  }
  return round((float)($lotSummary['total_value'] ?? 0) / $balanceQty, 6);
};
?>

<style>
  .component-lot-summary-card {
    min-width: 250px;
    white-space: normal;
    line-height: 1.4;
  }
  .component-lot-summary-card .lot-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    font-weight: 600;
    color: #5f3527;
  }
  .component-lot-summary-card .lot-sub {
    font-size: .74rem;
    color: #7f675f;
  }
  .component-lot-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .4rem;
    margin-top: .45rem;
  }
  .component-lot-summary-metric {
    border: 1px solid #eadccf;
    border-radius: 10px;
    background: #fffaf6;
    padding: .38rem .45rem;
  }
  .component-lot-summary-metric .label {
    display: block;
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .component-lot-summary-metric strong {
    display: block;
    font-size: .83rem;
    color: #503125;
  }
  .component-lot-parent-toggle {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid #e3d1c5;
    border-radius: 999px;
    padding: .18rem .55rem;
    background: #fff;
    color: #7a4c3f;
    font-size: .72rem;
    font-weight: 600;
  }
  .component-lot-parent-toggle i {
    transition: transform .18s ease;
  }
  .component-lot-parent-toggle[aria-expanded="true"] i {
    transform: rotate(180deg);
  }
  .component-lot-single {
    margin-top: .45rem;
    padding: .45rem .55rem;
    border-radius: 12px;
    background: #fffdfb;
    border: 1px solid #efe0d5;
    font-size: .74rem;
  }
  .component-lot-child-row td {
    background: #fffcf8 !important;
  }
  .component-lot-child-card {
    padding: .85rem 1rem;
    border: 1px solid #efdfd3;
    border-radius: 14px;
    background: linear-gradient(180deg, #fffdf9 0%, #fff8f3 100%);
  }
  .component-lot-child-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: .75rem;
  }
  .component-lot-child-item {
    border: 1px solid #ecdccf;
    border-radius: 14px;
    background: #fff;
    padding: .75rem .85rem;
    box-shadow: 0 10px 18px -20px rgba(95, 53, 39, .6);
  }
  .component-lot-child-item .lot-name {
    font-size: .84rem;
    font-weight: 700;
    color: #5f3527;
  }
  .component-lot-child-item .lot-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem .75rem;
    margin-top: .2rem;
    font-size: .72rem;
    color: #8a5b4d;
  }
  .component-lot-child-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .45rem;
    margin-top: .65rem;
  }
  .component-lot-child-metric {
    padding: .42rem .48rem;
    border: 1px solid #f0e1d6;
    border-radius: 10px;
    background: #fffaf6;
  }
  .component-lot-child-metric .label {
    display: block;
    font-size: .63rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .component-lot-child-metric strong {
    display: block;
    font-size: .79rem;
    color: #503125;
  }
</style>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Base/Prepare'); ?></h4>
      <small class="text-muted">Read-only saldo live base/prepare per lokasi.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('production/component-lots'); ?>" class="btn btn-outline-secondary btn-sm">Lot FIFO</a>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'stock']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-stock'),
  'component_type_filters' => $filters,
  'component_type_active' => (string)($filters['type'] ?? ''),
]); ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-stock'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama komponen / divisi">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-stock'); ?>" class="btn btn-outline-danger">Clear</a>
        <a href="<?php echo site_url('production/component-movements'); ?>" class="btn btn-outline-secondary">Lihat Mutasi</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Lokasi</th>
          <th>Divisi</th>
          <th>Nama Komponen</th>
          <th>Tipe</th>
          <th>Ringkasan Lot</th>
          <th class="text-end">Qty</th>
          <th>UOM</th>
          <th class="text-end">Avg Cost</th>
          <th class="text-end">Total Nilai</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data stok komponen.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $rowIndex => $row): ?>
            <?php $lotSummary = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : []; ?>
            <?php $lotRows = array_values((array)($lotSummary['rows'] ?? [])); ?>
            <?php $hasLotChildren = count($lotRows) > 1; ?>
            <?php $avgLotCost = $lotAverageCost($lotSummary); ?>
            <?php $lotToggleId = 'componentStockLot_' . (int)$rowIndex; ?>
            <tr>
              <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? '-'))); ?></td>
              <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
              <td>
                <a href="<?php echo html_escape($buildLotUrl((array)$row, 'ALL')); ?>" class="fw-semibold text-decoration-none"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></a>
              </td>
              <td><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></td>
              <td>
                <div class="component-lot-summary-card">
                  <div class="lot-head">
                      <span><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></span>
                      <?php if ($hasLotChildren): ?>
                        <button type="button" class="component-lot-parent-toggle" data-lot-toggle="<?php echo html_escape($lotToggleId); ?>" aria-expanded="false">
                          <span><?php echo (int)($lotSummary['lot_count'] ?? 0); ?> lot aktif</span>
                          <i class="ri ri-arrow-down-s-line"></i>
                        </button>
                      <?php elseif (!empty($lotSummary['lot_count'])): ?>
                        <span class="badge bg-label-secondary">1 lot aktif</span>
                      <?php endif; ?>
                  </div>
                    <div class="component-lot-summary-grid">
                      <div class="component-lot-summary-metric">
                        <span class="label">Qty</span>
                        <strong><?php echo number_format((float)($row['qty_on_hand'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                      </div>
                      <div class="component-lot-summary-metric">
                        <span class="label">Nilai</span>
                        <strong><?php echo number_format((float)($row['total_value'] ?? 0), 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-lot-summary-metric">
                        <span class="label"><?php echo $hasLotChildren ? 'Avg Parent' : 'Avg Cost'; ?></span>
                        <strong><?php echo number_format($hasLotChildren ? $avgLotCost : (float)($row['avg_cost'] ?? 0), 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-lot-summary-metric">
                        <span class="label"><?php echo $hasLotChildren ? 'Range Lot' : 'Lot Cost'; ?></span>
                        <strong><?php echo number_format((float)($lotSummary['min_unit_cost'] ?? 0), 2, ',', '.'); ?><?php if ((float)($lotSummary['max_unit_cost'] ?? 0) !== (float)($lotSummary['min_unit_cost'] ?? 0)): ?> - <?php echo number_format((float)($lotSummary['max_unit_cost'] ?? 0), 2, ',', '.'); ?><?php endif; ?></strong>
                      </div>
                    </div>
                    <div class="lot-sub mt-2"><?php echo $hasLotChildren ? 'Parent menampilkan total saldo dan rata-rata. Expand untuk melihat tiap lot.' : (!empty($lotSummary['has_mixed_cost']) ? 'Cost campur pada lot aktif tunggal ini.' : 'Cost lot aktif masih seragam.'); ?></div>
                  <?php if (!$hasLotChildren && !empty($lotRows)): ?>
                    <?php $singleLot = (array)$lotRows[0]; ?>
                    <div class="component-lot-single">
                      <div class="fw-semibold"><?php echo html_escape((string)($singleLot['lot_no'] ?? '-')); ?></div>
                      <div><?php echo number_format((float)($singleLot['qty_balance'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?> • <?php echo number_format((float)($singleLot['unit_cost'] ?? 0), 2, ',', '.'); ?></div>
                      <div class="text-muted">Terima <?php echo html_escape((string)($singleLot['receipt_date'] ?? '-')); ?><?php echo !empty($singleLot['expiry_date']) ? ' • Exp ' . html_escape((string)$singleLot['expiry_date']) : ''; ?></div>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['qty_on_hand'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['avg_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($row['updated_at'] ?? '-')); ?></td>
            </tr>
            <?php if ($hasLotChildren): ?>
              <tr id="<?php echo html_escape($lotToggleId); ?>" class="component-lot-child-row d-none">
                <td colspan="10">
                  <div class="component-lot-child-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                      <div>
                        <div class="fw-semibold">Rincian lot aktif</div>
                        <div class="small text-muted">Parent total <?php echo number_format((float)($lotSummary['balance_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?> • avg <?php echo number_format($avgLotCost, 2, ',', '.'); ?></div>
                      </div>
                      <a href="<?php echo html_escape($buildLotUrl((array)$row, 'OPEN')); ?>" class="btn btn-outline-secondary btn-sm">Buka FIFO lot</a>
                    </div>
                    <div class="component-lot-child-grid">
                      <?php foreach ($lotRows as $lotRow): ?>
                        <div class="component-lot-child-item">
                          <div class="lot-name"><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></div>
                          <div class="lot-meta">
                            <span>Terima <?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></span>
                            <span>Expiry <?php echo html_escape((string)($lotRow['expiry_date'] ?? '-')); ?></span>
                          </div>
                          <div class="component-lot-child-metrics">
                            <div class="component-lot-child-metric">
                              <span class="label">Qty</span>
                              <strong><?php echo number_format((float)($lotRow['qty_balance'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                            </div>
                            <div class="component-lot-child-metric">
                              <span class="label">HPP</span>
                              <strong><?php echo number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.'); ?></strong>
                            </div>
                            <div class="component-lot-child-metric">
                              <span class="label">Nilai</span>
                              <strong><?php echo number_format((float)($lotRow['total_value'] ?? 0), 2, ',', '.'); ?></strong>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(() => {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-lot-toggle]');
    if (!button) {
      return;
    }
    const targetId = String(button.getAttribute('data-lot-toggle') || '');
    const target = targetId !== '' ? document.getElementById(targetId) : null;
    if (!target) {
      return;
    }
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    target.classList.toggle('d-none', isExpanded);
    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
  });
})();
</script>

