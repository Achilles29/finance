<?php
$baseUrl = site_url('purchase/stock/warehouse/daily');
$rowsData = is_array($rows ?? null) ? $rows : [];
$monthlyMap = [];
foreach ($rowsData as $row) {
  $key = implode('|', [
    strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
    (int)($row['item_id'] ?? 0),
    (int)($row['material_id'] ?? 0),
    (int)($row['buy_uom_id'] ?? 0),
    (int)($row['content_uom_id'] ?? 0),
    (string)($row['profile_key'] ?? ''),
  ]);

  if (!isset($monthlyMap[$key])) {
    $monthlyMap[$key] = [
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'opening_qty_content' => 0.0,
      'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0,
      'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0,
      'out_qty_pack' => 0.0,
      'adjustment_qty_content' => 0.0,
      'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0,
      'closing_qty_pack' => 0.0,
      'total_value' => 0.0,
      'avg_cost_per_content' => 0.0,
      'discard_qty_content' => 0.0,
      'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0,
      'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0,
      'waste_qty_pack' => 0.0,
      '_min_date' => null,
      '_max_date' => null,
    ];
  }

  $movementDate = (string)($row['movement_date'] ?? '');
  $entry =& $monthlyMap[$key];
  $profileContentPerBuy = (float)($row['profile_content_per_buy'] ?? 0);
  $inQtyContent = (float)($row['in_qty_content'] ?? 0);
  $outQtyContent = (float)($row['out_qty_content'] ?? 0);
  $adjustmentQtyContent = (float)($row['adjustment_qty_content'] ?? 0);
  $discardQtyContent = (float)($row['discard_qty_content'] ?? 0);
  $spoilQtyContent = (float)($row['spoil_qty_content'] ?? 0);
  $wasteQtyContent = (float)($row['waste_qty_content'] ?? 0);

  $entry['in_qty_content'] += $inQtyContent;
  $entry['out_qty_content'] += $outQtyContent;
  $entry['adjustment_qty_content'] += $adjustmentQtyContent;
  $entry['discard_qty_content'] += $discardQtyContent;
  $entry['spoil_qty_content'] += $spoilQtyContent;
  $entry['waste_qty_content'] += $wasteQtyContent;

  if ($profileContentPerBuy > 0) {
    $entry['in_qty_pack'] += ($inQtyContent / $profileContentPerBuy);
    $entry['out_qty_pack'] += ($outQtyContent / $profileContentPerBuy);
    $entry['adjustment_qty_pack'] += ($adjustmentQtyContent / $profileContentPerBuy);
    $entry['discard_qty_pack'] += ($discardQtyContent / $profileContentPerBuy);
    $entry['spoil_qty_pack'] += ($spoilQtyContent / $profileContentPerBuy);
    $entry['waste_qty_pack'] += ($wasteQtyContent / $profileContentPerBuy);
  }

  if ($entry['_min_date'] === null || ($movementDate !== '' && $movementDate < $entry['_min_date'])) {
    $entry['_min_date'] = $movementDate;
    $entry['opening_qty_content'] = (float)($row['opening_qty_content'] ?? 0);
    $entry['opening_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['opening_qty_content'] / $profileContentPerBuy) : 0.0;
  }
  if ($entry['_max_date'] === null || ($movementDate !== '' && $movementDate > $entry['_max_date'])) {
    $entry['_max_date'] = $movementDate;
    $entry['closing_qty_content'] = (float)($row['closing_qty_content'] ?? 0);
    $entry['closing_qty_pack'] = $profileContentPerBuy > 0 ? ($entry['closing_qty_content'] / $profileContentPerBuy) : 0.0;
    $entry['total_value'] = (float)($row['total_value'] ?? 0);
    $entry['avg_cost_per_content'] = (float)($row['avg_cost_per_content'] ?? 0);
  }
}

$monthlyRows = array_values($monthlyMap);
usort($monthlyRows, static function (array $a, array $b): int {
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  $cmp = strcasecmp($aName, $bName);
  if ($cmp !== 0) {
    return $cmp;
  }
  return strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
});

$summaryRows = count($monthlyRows);
$summaryInPack = 0.0;
$summaryOutPack = 0.0;
$summaryClosingPack = 0.0;
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryClosing = 0.0;
$summaryValue = 0.0;
foreach ($monthlyRows as $row) {
  $summaryInPack += (float)($row['in_qty_pack'] ?? 0);
  $summaryOutPack += (float)($row['out_qty_pack'] ?? 0);
  $summaryClosingPack += (float)($row['closing_qty_pack'] ?? 0);
  $summaryIn += (float)($row['in_qty_content'] ?? 0);
  $summaryOut += (float)($row['out_qty_content'] ?? 0);
  $summaryClosing += (float)($row['closing_qty_content'] ?? 0);
  $summaryValue += (float)($row['total_value'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Rekap per profil barang dalam rentang 1 bulan (1 baris per profil).</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('inventory-warehouse-daily'); ?>" class="btn btn-outline-primary">Daily Gudang Matrix</a>
    <a href="<?php echo site_url('purchase/stock/opening'); ?>" class="btn btn-outline-primary">Opening Gudang</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Gudang</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Daily Divisi</a>
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang Live</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / profile / merk / keterangan">
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
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Profil Bulanan</div><div class="h5 mb-0"><?php echo number_format($summaryRows); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total In (Pack)</div><div class="h5 mb-0 text-success"><?php echo number_format($summaryInPack, 2, ',', '.'); ?></div><small class="text-muted">Isi: <?php echo number_format($summaryIn, 2, ',', '.'); ?></small></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Out (Pack)</div><div class="h5 mb-0 text-danger"><?php echo number_format($summaryOutPack, 2, ',', '.'); ?></div><small class="text-muted">Isi: <?php echo number_format($summaryOut, 2, ',', '.'); ?></small></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Profil Pack</th>
          <th>Satuan</th>
          <th class="text-end">Isi / Pack</th>
          <th class="text-end">Stok Awal</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">Terbuang</th>
          <th class="text-end">Spoil</th>
          <th class="text-end">Waste</th>
          <th class="text-end">Penyesuaian</th>
          <th class="text-end">Stok Akhir</th>
          <th class="text-end">Nilai Total</th>
          <th class="text-end">HPP / Isi</th>
          <th class="text-end">HPP / Pack</th>
          <th class="text-end">Total Isi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($monthlyRows)): ?>
          <tr><td colspan="17" class="text-center text-muted py-4">Belum ada data bulanan gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($monthlyRows as $idx => $r): ?>
            <?php
              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $nameText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
              $totalIsi = (float)($r['closing_qty_content'] ?? 0);
              $uomPack = (string)($r['profile_buy_uom_code'] ?? '-');
              $uomContent = (string)($r['profile_content_uom_code'] ?? '');
              $avgCostPack = (float)($r['avg_cost_per_content'] ?? 0) * (float)($r['profile_content_per_buy'] ?? 0);
            ?>
            <tr>
              <td><?php echo (int)($idx + 1); ?></td>
              <td><?php echo html_escape($nameText); ?></td>
              <td>
                <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?></small><br>
                <small class="text-muted"><?php echo number_format((float)($r['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($r['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></small>
              </td>
              <td><?php echo html_escape($uomPack); ?></td>
              <td class="text-end"><?php echo number_format((float)($r['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($uomContent); ?></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($r['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['opening_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-success"><div class="fw-semibold"><?php echo ui_num((float)($r['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['in_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-danger"><div class="fw-semibold"><?php echo ui_num((float)($r['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['out_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($r['discard_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['discard_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($r['spoil_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['spoil_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($r['waste_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['waste_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($r['adjustment_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['adjustment_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end fw-semibold"><div class="fw-semibold"><?php echo ui_num((float)($r['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($r['closing_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><?php echo number_format((float)($r['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo ui_num((float)($r['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num($avgCostPack); ?></td>
              <td class="text-end"><?php echo ui_num($totalIsi); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
