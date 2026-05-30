<?php
$baseUrl = site_url('inventory/stock/division/movement');
$generateUrl = site_url('inventory/stock/opname/generate');
$genMonth = !empty($date_from ?? '') ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryRows = count($rowsData);
$summaryDivisions = [];
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryValue = 0.0;
foreach ($rowsData as $row) {
  $divisionId = (int)($row['division_id'] ?? 0);
  if ($divisionId > 0) {
    $summaryDivisions[$divisionId] = true;
  }
  $delta = (float)($row['qty_content_delta'] ?? 0);
  $summaryValue += abs($delta) * (float)($row['unit_cost'] ?? 0);
  if ($delta >= 0) {
    $summaryIn += $delta;
  } else {
    $summaryOut += abs($delta);
  }
}
$summaryDivisionCount = count($summaryDivisions);
$destinationValue = strtoupper(trim((string)($destination ?? 'ALL')));
if ($destinationValue === '') {
  $destinationValue = 'ALL';
}
$formatDivisionLabel = static function (string $code, string $name, $fallbackId = '-'): string {
  $code = trim($code);
  $name = trim($name);
  if ($code !== '' && strcasecmp($code, $name) === 0) {
    return $code;
  }
  if ($code !== '' && $name !== '') {
    return $code . ' - ' . $name;
  }
  if ($code !== '') {
    return $code;
  }
  if ($name !== '') {
    return $name;
  }
  return (string)$fallbackId;
};
$formatDestination = static function (string $group): string {
  return strtoupper(trim($group)) === 'EVENT' ? 'Event' : 'Reguler';
};
$buildSourceAudit = static function (array $row): array {
  $refTable = strtolower(trim((string)($row['ref_table'] ?? '')));
  $refId = (int)($row['ref_id'] ?? 0);
  $receiptId = (int)($row['receipt_id'] ?? 0);
  $receiptLineId = (int)($row['receipt_line_id'] ?? 0);
  $labelMap = [
    'pos_stock_commit' => 'POS Commit',
    'pur_purchase_receipt' => 'Receipt PO',
    'pur_store_request_fulfillment' => 'Fulfillment SR',
    'inv_division_stock_adjustment' => 'Adjustment Divisi',
    'inv_division_stock_opening_snapshot' => 'Opening Snapshot',
    'inv_warehouse_stock_opening_snapshot' => 'Opening Snapshot',
  ];
  $label = $labelMap[$refTable] ?? ($refTable !== '' ? strtoupper(str_replace('_', ' ', $refTable)) : '-');
  if ($refId > 0) {
    $label .= ' #' . $refId;
  }
  $meta = [];
  if ($receiptId > 0) {
    $meta[] = 'Receipt #' . $receiptId;
  }
  if ($receiptLineId > 0) {
    $meta[] = 'Line #' . $receiptLineId;
  }
  return ['label' => $label, 'meta' => implode(' | ', $meta)];
};
?>

<style>
  .stock-division-movement-wrap {
    max-height: 74vh;
    overflow: auto;
    border-top: 1px solid #f0e1d6;
  }
  .stock-division-movement-table {
    min-width: 1760px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .stock-division-movement-table thead th {
    position: sticky;
    top: 0;
    z-index: 4;
    background: linear-gradient(180deg, #7c1f2d 0%, #9f2f3e 100%);
    color: #fff8f5;
    border-bottom: 1px solid #7f2936;
    white-space: nowrap;
  }
  .stock-division-movement-table td,
  .stock-division-movement-table th {
    vertical-align: top;
  }
  .stock-division-source-title {
    font-weight: 800;
    color: #2f2628;
  }
  .stock-division-source-meta {
    display: block;
    margin-top: .18rem;
    color: #8a776f;
    font-size: .72rem;
    line-height: 1.35;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Log keluar masuk stok per divisi dari inv_stock_movement_log.</small>
  </div>
  <div class="d-flex gap-1 flex-wrap align-items-center">
    <form method="post" action="<?php echo $generateUrl; ?>" class="d-inline js-stock-division-generate-form">
      <input type="hidden" name="stock_scope" value="DIVISION">
      <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
      <input type="hidden" name="division_id" value="<?php echo (int)($division_id ?? 0); ?>">
      <input type="hidden" name="destination" value="<?php echo html_escape($destinationValue); ?>">
      <input type="hidden" name="back_url" value="inventory/stock/division/movement?date_from=<?php echo rawurlencode((string)($date_from ?? '')); ?>&date_to=<?php echo rawurlencode((string)($date_to ?? '')); ?>&division_id=<?php echo (int)($division_id ?? 0); ?>&destination=<?php echo rawurlencode($destinationValue); ?>">
      <button type="submit" class="btn btn-outline-primary">Generate Opname + Stok Awal</button>
    </form>
    <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'movement']); ?>
    <a href="<?php echo site_url('inventory/fifo-audit'); ?>" class="btn btn-outline-secondary">Audit FIFO</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select class="form-select" name="division_id">
          <option value="">Semua Divisi</option>
          <?php foreach (($divisions ?? []) as $d): ?>
            <?php
              $id = (int)($d['id'] ?? 0);
              $code = trim((string)($d['code'] ?? ''));
              $name = trim((string)($d['name'] ?? ''));
              $label = $code !== '' ? $code . ' - ' . $name : ($name !== '' ? $name : (string)$id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo ((int)$division_id === $id) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select class="form-select" name="destination">
          <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="No mutasi / item / material / profile">
      </div>
      <div class="w-100"></div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)$date_from); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)$date_to); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" name="limit" min="1" max="1000" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
      </div>
      <div class="col-md-1 d-grid">
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Baris Mutasi</div><div class="h5 mb-0"><?php echo number_format($summaryRows); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Divisi</div><div class="h5 mb-0"><?php echo number_format($summaryDivisionCount); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Masuk</div><div class="h5 mb-0 text-success"><?php echo number_format($summaryIn, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Keluar</div><div class="h5 mb-0 text-danger"><?php echo number_format($summaryOut, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="card-body pb-0">
    <small class="fin-audit-note">Audit stok ditampilkan seragam: Before, Delta, After. Nilai bawah menunjukkan satuan beli jika tersedia.</small>
  </div>
  <div class="stock-division-movement-wrap">
    <table class="table table-striped table-hover fin-audit-table stock-division-movement-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Divisi</th>
          <th>Tujuan</th>
          <th>No Mutasi</th>
          <th>Tipe</th>
          <th>Kategori/Alasan</th>
          <th>Objek</th>
          <th>Keterangan</th>
          <th class="text-end col-balance">Before</th>
          <th class="text-end col-delta">Delta Isi</th>
          <th class="text-end col-balance">After</th>
          <th class="text-end col-amount">Unit Cost</th>
          <th class="text-end col-amount">Nilai Mutasi</th>
          <th class="col-ref">Sumber / Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="14" class="text-center text-muted py-4">Belum ada data mutasi divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $sourceAudit = $buildSourceAudit((array)$r);
              $divisionText = $formatDivisionLabel((string)($r['division_code'] ?? ''), (string)($r['division_name'] ?? ''), (string)($r['division_id'] ?? '-'));

              $movementTypeLabel = trim((string)($r['movement_type_label'] ?? $r['movement_type'] ?? '-'));
              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $preferMaterial = (int)($r['material_id'] ?? 0) > 0;
              if ($preferMaterial) {
                $objectText = $materialText !== ' -' && $materialText !== '' ? $materialText : ($itemText !== ' -' && $itemText !== '' ? $itemText : '-');
              } else {
                $objectText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
              }
              $adjustmentCategory = strtoupper(trim((string)($r['adjustment_category'] ?? '')));
              $adjustmentReason = strtolower(trim((string)($r['adjustment_reason_code'] ?? '')));
              $deltaContent = (float)($r['qty_content_delta'] ?? 0);
              $afterContent = (float)($r['qty_content_after'] ?? 0);
              $beforeContent = $afterContent - $deltaContent;
              $deltaBuy = (float)($r['qty_buy_delta'] ?? 0);
              $afterBuy = (float)($r['qty_buy_after'] ?? 0);
              $beforeBuy = $afterBuy - $deltaBuy;
              $destinationText = $formatDestination((string)($r['destination_group'] ?? 'REGULER'));
              $unitCost = (float)($r['unit_cost'] ?? 0);
              $mutationValue = abs($deltaContent) * $unitCost;
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($destinationText); ?></td>
              <td><?php echo html_escape((string)$r['movement_no']); ?></td>
              <td><?php echo html_escape($movementTypeLabel !== '' ? $movementTypeLabel : '-'); ?></td>
              <td>
                <div><?php echo html_escape($adjustmentCategory !== '' ? $adjustmentCategory : '-'); ?></div>
                <small class="text-muted"><?php echo html_escape($adjustmentReason !== '' ? str_replace('_', ' ', $adjustmentReason) : '-'); ?></small>
              </td>
              <td><?php echo html_escape($objectText); ?></td>
              <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
              <td class="text-end col-balance">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($beforeContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($beforeBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-delta <?php echo $deltaContent >= 0 ? 'fin-audit-delta-positive' : 'fin-audit-delta-negative'; ?>">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($deltaContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($deltaBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-balance">
                <div class="fin-audit-metric">
                  <div class="fin-audit-primary"><?php echo ui_num($afterContent); ?></div>
                  <small class="fin-audit-secondary"><?php echo ui_num($afterBuy); ?> <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
                </div>
              </td>
              <td class="text-end col-amount"><?php echo ui_num($unitCost); ?></td>
              <td class="text-end col-amount"><?php echo number_format($mutationValue, 2, ',', '.'); ?></td>
              <td class="col-ref">
                <span class="stock-division-source-title"><?php echo html_escape((string)($sourceAudit['label'] ?? '-')); ?></span>
                <?php if (!empty($sourceAudit['meta'])): ?><span class="stock-division-source-meta"><?php echo html_escape((string)$sourceAudit['meta']); ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(() => {
  document.addEventListener('submit', (event) => {
    const form = event.target.closest('.js-stock-division-generate-form');
    if (!form) {
      return;
    }
    if (!(window.FinanceUI && typeof window.FinanceUI.confirm === 'function')) {
      return;
    }
    event.preventDefault();
    Promise.resolve(window.FinanceUI.confirm('Generate opname divisi bulan ini dan carry-forward opening bulan berikutnya?', {
      title: 'Generate Opname Divisi',
      confirmText: 'Generate',
      cancelText: 'Batal'
    })).then((confirmed) => {
      if (confirmed) {
        form.submit();
      }
    });
  });
})();
</script>
