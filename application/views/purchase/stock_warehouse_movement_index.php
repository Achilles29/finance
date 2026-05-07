<?php
$baseUrl = site_url('purchase/stock/warehouse/movement');
$generateUrl = site_url('purchase/stock/opname/generate');
$genMonth = !empty($date_from ?? '') ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryRows = count($rowsData);
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryValue = 0.0;
foreach ($rowsData as $row) {
  $delta = (float)($row['qty_content_delta'] ?? 0);
  $summaryValue += abs($delta) * (float)($row['unit_cost'] ?? 0);
  if ($delta >= 0) {
    $summaryIn += $delta;
  } else {
    $summaryOut += abs($delta);
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Log keluar masuk stok gudang dari inv_stock_movement_log.</small>
  </div>
  <div class="d-flex gap-2">
    <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname gudang bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
      <input type="hidden" name="stock_scope" value="WAREHOUSE">
      <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
      <input type="hidden" name="back_url" value="purchase/stock/warehouse/movement?date_from=<?php echo rawurlencode((string)($date_from ?? '')); ?>&date_to=<?php echo rawurlencode((string)($date_to ?? '')); ?>">
      <button type="submit" class="btn btn-outline-primary">Generate Opname + Stok Awal</button>
    </form>
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang Live</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/daily'); ?>" class="btn btn-outline-secondary">Daily Gudang</a>
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Mutasi Divisi</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="No mutasi / item / material / profile / merk">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)$date_from); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)$date_to); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" name="limit" min="1" max="1000" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
      </div>
      <div class="col-md-2 d-grid">
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Baris Mutasi</div><div class="h5 mb-0"><?php echo number_format($summaryRows); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Masuk</div><div class="h5 mb-0 text-success"><?php echo number_format($summaryIn, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Keluar</div><div class="h5 mb-0 text-danger"><?php echo number_format($summaryOut, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Mutasi</th>
          <th>Tipe</th>
          <th>Kategori/Alasan</th>
          <th>Objek</th>
          <th>Profile</th>
          <th>Keterangan</th>
          <th class="text-end">Delta Isi</th>
          <th class="text-end">Saldo Isi</th>
          <th class="text-end">Unit Cost</th>
          <th class="text-end">Nilai Mutasi</th>
          <th>Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data mutasi gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
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
              $unitCost = (float)($r['unit_cost'] ?? 0);
              $mutationValue = abs($deltaContent) * $unitCost;
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape((string)$r['movement_no']); ?></td>
              <td><?php echo html_escape($movementTypeLabel !== '' ? $movementTypeLabel : '-'); ?></td>
              <td>
                <div><?php echo html_escape($adjustmentCategory !== '' ? $adjustmentCategory : '-'); ?></div>
                <small class="text-muted"><?php echo html_escape($adjustmentReason !== '' ? str_replace('_', ' ', $adjustmentReason) : '-'); ?></small>
              </td>
              <td><?php echo html_escape($objectText); ?></td>
              <td>
                <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
              </td>
              <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
              <td class="text-end <?php echo $deltaContent >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo ui_num($deltaContent); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($r['qty_content_after'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num($unitCost); ?></td>
              <td class="text-end"><?php echo number_format($mutationValue, 2, ',', '.'); ?></td>
              <td>
                <?php echo html_escape((string)($r['ref_table'] ?? '-')); ?>
                <?php if (!empty($r['ref_id'])): ?>#<?php echo (int)$r['ref_id']; ?><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
