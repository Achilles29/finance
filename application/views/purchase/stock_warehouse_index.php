<?php
$baseUrl = site_url('purchase/stock/warehouse');
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryProfiles = count($rowsData);
$summaryItems = [];
$summaryQtyContent = 0.0;
$summaryTotalValue = 0.0;
foreach ($rowsData as $row) {
  $itemId = (int)($row['item_id'] ?? 0);
  if ($itemId > 0) {
    $summaryItems[$itemId] = true;
  }
  $qtyContent = (float)($row['qty_content_balance'] ?? 0);
  $avgCost = (float)($row['avg_cost_per_content'] ?? 0);
  $summaryQtyContent += $qtyContent;
  $summaryTotalValue += ($qtyContent * $avgCost);
}
$summaryItemCount = count($summaryItems);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-building-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posisi stok gudang per profile purchase (nama, merk, keterangan, ukuran/UOM).</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
    <a href="<?php echo site_url('purchase/stock/opening'); ?>" class="btn btn-outline-secondary">Opening Gudang</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Gudang</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/daily'); ?>" class="btn btn-outline-secondary">Stok Bulanan/Daily</a>
    <a href="<?php echo site_url('master/company-account'); ?>" class="btn btn-outline-secondary">Rekening</a>
    <a href="<?php echo site_url('purchase/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari Stok Gudang</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / Profile / Merk / Keterangan / Profile Key">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <input type="number" min="1" max="500" class="form-control" name="limit" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger w-100">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Profile</div><div class="h5 mb-0"><?php echo number_format($summaryProfiles); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Item</div><div class="h5 mb-0"><?php echo number_format($summaryItemCount); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Qty Isi Total</div><div class="h5 mb-0"><?php echo number_format($summaryQtyContent, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryTotalValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Item</th>
          <th>Profile</th>
          <th>Merk</th>
          <th>Keterangan</th>
          <th>Ukuran Isi</th>
          <th class="text-end">Qty Beli</th>
          <th class="text-end">Qty Isi</th>
          <th class="text-end">Avg Cost / Isi</th>
          <th class="text-end">Total Nilai</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data stok gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $qtyContent = (float)($r['qty_content_balance'] ?? 0);
              $avgCost = (float)($r['avg_cost_per_content'] ?? 0);
              $rowTotalValue = $qtyContent * $avgCost;
            ?>
            <tr>
              <td><?php echo html_escape(trim((string)($r['item_code'] ?? '')) . ' - ' . trim((string)($r['item_name'] ?? ''))); ?></td>
              <td><?php echo html_escape((string)($r['profile_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></td>
              <td><?php echo number_format((float)($r['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($r['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></td>
              <td class="text-end"><?php echo ui_num((float)$r['qty_buy_balance']); ?></td>
              <td class="text-end"><?php echo ui_num($qtyContent); ?></td>
              <td class="text-end"><?php echo ui_num($avgCost); ?></td>
              <td class="text-end"><?php echo number_format($rowTotalValue, 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($r['updated_at'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
