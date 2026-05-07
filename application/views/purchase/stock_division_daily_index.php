<?php
$baseUrl = site_url('purchase/stock/division/daily');
$generateUrl = site_url('purchase/stock/opname/generate');
$genMonth = $month !== '' ? substr((string)$month, 0, 7) : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
$monthlyMap = [];
foreach ($rowsData as $row) {
  $profileNameKey = strtoupper(trim((string)($row['profile_name'] ?? '')));
  $profileBrandKey = strtoupper(trim((string)($row['profile_brand'] ?? '')));
  $profileDescKey = strtoupper(trim((string)($row['profile_description'] ?? '')));
  $profileExpiredKey = trim((string)($row['profile_expired_date'] ?? ''));
  $profileContentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $key = implode('|', [
    (int)($row['division_id'] ?? 0),
    strtoupper((string)($row['destination_group'] ?? 'REGULER')),
    strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
    (int)($row['item_id'] ?? 0),
    (int)($row['material_id'] ?? 0),
    (int)($row['buy_uom_id'] ?? 0),
    (int)($row['content_uom_id'] ?? 0),
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
    $profileExpiredKey,
    $profileContentPerBuyKey,
    strtoupper(trim((string)($row['profile_buy_uom_code'] ?? ''))),
    strtoupper(trim((string)($row['profile_content_uom_code'] ?? ''))),
  ]);

  if (!isset($monthlyMap[$key])) {
    $monthlyMap[$key] = [
      'division_id' => (int)($row['division_id'] ?? 0),
      'division_code' => (string)($row['division_code'] ?? ''),
      'division_name' => (string)($row['division_name'] ?? ''),
      'destination_group' => strtoupper((string)($row['destination_group'] ?? 'REGULER')),
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_id' => (int)($row['item_id'] ?? 0),
      'material_id' => (int)($row['material_id'] ?? 0),
      'profile_key' => (string)($row['profile_key'] ?? ''),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
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
      'waste_component_qty_content' => 0.0,
      'waste_component_qty_pack' => 0.0,
      'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0,
      'spoilage_qty_pack' => 0.0,
      'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0,
      'process_loss_qty_pack' => 0.0,
      'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0,
      'variance_qty_pack' => 0.0,
      'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0,
      'adjustment_plus_qty_pack' => 0.0,
      'adjustment_plus_value' => 0.0,
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
  $wasteComponentQtyContent = $wasteQtyContent + $discardQtyContent;
  $spoilageQtyContent = $spoilQtyContent;
  $processLossQtyContent = (float)($row['process_loss_qty_content'] ?? 0);
  $varianceQtyContent = (float)($row['variance_qty_content'] ?? 0);
  if ($varianceQtyContent <= 0 && $adjustmentQtyContent < 0) {
    $varianceQtyContent = abs($adjustmentQtyContent);
  }
  $adjustmentPlusQtyContent = (float)($row['adjustment_plus_qty_content'] ?? 0);
  if ($adjustmentPlusQtyContent <= 0 && $adjustmentQtyContent > 0) {
    $adjustmentPlusQtyContent = $adjustmentQtyContent;
  }

  $avgCostPerContent = (float)($row['avg_cost_per_content'] ?? 0);
  $wasteComponentValue = (float)($row['waste_total_value'] ?? 0);
  if ($wasteComponentValue <= 0 && $wasteComponentQtyContent > 0) {
    $wasteComponentValue = $wasteComponentQtyContent * $avgCostPerContent;
  }
  $spoilageValue = (float)($row['spoilage_total_value'] ?? 0);
  if ($spoilageValue <= 0 && $spoilageQtyContent > 0) {
    $spoilageValue = $spoilageQtyContent * $avgCostPerContent;
  }
  $processLossValue = (float)($row['process_loss_total_value'] ?? 0);
  if ($processLossValue <= 0 && $processLossQtyContent > 0) {
    $processLossValue = $processLossQtyContent * $avgCostPerContent;
  }
  $varianceValue = (float)($row['variance_total_value'] ?? 0);
  if ($varianceValue <= 0 && $varianceQtyContent > 0) {
    $varianceValue = $varianceQtyContent * $avgCostPerContent;
  }
  $adjustmentPlusValue = (float)($row['adjustment_plus_total_value'] ?? 0);
  if ($adjustmentPlusValue <= 0 && $adjustmentPlusQtyContent > 0) {
    $adjustmentPlusValue = $adjustmentPlusQtyContent * $avgCostPerContent;
  }

  $entry['in_qty_content'] += $inQtyContent;
  $entry['out_qty_content'] += $outQtyContent;
  $entry['adjustment_qty_content'] += $adjustmentQtyContent;
  $entry['discard_qty_content'] += $discardQtyContent;
  $entry['spoil_qty_content'] += $spoilQtyContent;
  $entry['waste_qty_content'] += $wasteQtyContent;
  $entry['waste_component_qty_content'] += $wasteComponentQtyContent;
  $entry['waste_component_value'] += $wasteComponentValue;
  $entry['spoilage_qty_content'] += $spoilageQtyContent;
  $entry['spoilage_value'] += $spoilageValue;
  $entry['process_loss_qty_content'] += $processLossQtyContent;
  $entry['process_loss_value'] += $processLossValue;
  $entry['variance_qty_content'] += $varianceQtyContent;
  $entry['variance_value'] += $varianceValue;
  $entry['adjustment_plus_qty_content'] += $adjustmentPlusQtyContent;
  $entry['adjustment_plus_value'] += $adjustmentPlusValue;

  if ($profileContentPerBuy > 0) {
    $entry['in_qty_pack'] += ($inQtyContent / $profileContentPerBuy);
    $entry['out_qty_pack'] += ($outQtyContent / $profileContentPerBuy);
    $entry['adjustment_qty_pack'] += ($adjustmentQtyContent / $profileContentPerBuy);
    $entry['discard_qty_pack'] += ($discardQtyContent / $profileContentPerBuy);
    $entry['spoil_qty_pack'] += ($spoilQtyContent / $profileContentPerBuy);
    $entry['waste_qty_pack'] += ($wasteQtyContent / $profileContentPerBuy);
    $entry['waste_component_qty_pack'] += ($wasteComponentQtyContent / $profileContentPerBuy);
    $entry['spoilage_qty_pack'] += ($spoilageQtyContent / $profileContentPerBuy);
    $entry['process_loss_qty_pack'] += ($processLossQtyContent / $profileContentPerBuy);
    $entry['variance_qty_pack'] += ($varianceQtyContent / $profileContentPerBuy);
    $entry['adjustment_plus_qty_pack'] += ($adjustmentPlusQtyContent / $profileContentPerBuy);
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
  $aDiv = trim((string)($a['division_name'] ?? ''));
  $bDiv = trim((string)($b['division_name'] ?? ''));
  $cmpDiv = strcasecmp($aDiv, $bDiv);
  if ($cmpDiv !== 0) {
    return $cmpDiv;
  }
  $cmpDest = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmpDest !== 0) {
    return $cmpDest;
  }
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  $cmp = strcasecmp($aName, $bName);
  if ($cmp !== 0) {
    return $cmp;
  }
  return strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
});

$parentMap = [];
foreach ($monthlyRows as $row) {
  $materialId = (int)($row['material_id'] ?? 0);
  $itemId = (int)($row['item_id'] ?? 0);
  $objectIdentity = $materialId > 0 ? ('M-' . $materialId) : ('I-' . $itemId);
  if ($objectIdentity === 'M-0' || $objectIdentity === 'I-0') {
    $objectIdentity .= '|' . strtoupper(trim((string)($row['material_code'] ?? '') . '|' . (string)($row['item_code'] ?? '')));
  }
  $parentKey = implode('|', [
    (int)($row['division_id'] ?? 0),
    strtoupper((string)($row['destination_group'] ?? 'REGULER')),
    $objectIdentity,
  ]);

  if (!isset($parentMap[$parentKey])) {
    $parentMap[$parentKey] = [
      'division_id' => (int)($row['division_id'] ?? 0),
      'division_code' => (string)($row['division_code'] ?? ''),
      'division_name' => (string)($row['division_name'] ?? ''),
      'destination_group' => strtoupper((string)($row['destination_group'] ?? 'REGULER')),
      'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
      'item_code' => (string)($row['item_code'] ?? ''),
      'item_name' => (string)($row['item_name'] ?? ''),
      'material_code' => (string)($row['material_code'] ?? ''),
      'material_name' => (string)($row['material_name'] ?? ''),
      'profile_count' => 0,
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'avg_content_per_buy' => 0.0,
      'opening_qty_content' => 0.0,
      'opening_qty_pack' => 0.0,
      'in_qty_content' => 0.0,
      'in_qty_pack' => 0.0,
      'out_qty_content' => 0.0,
      'out_qty_pack' => 0.0,
      'discard_qty_content' => 0.0,
      'discard_qty_pack' => 0.0,
      'spoil_qty_content' => 0.0,
      'spoil_qty_pack' => 0.0,
      'waste_qty_content' => 0.0,
      'waste_qty_pack' => 0.0,
      'waste_component_qty_content' => 0.0,
      'waste_component_qty_pack' => 0.0,
      'waste_component_value' => 0.0,
      'spoilage_qty_content' => 0.0,
      'spoilage_qty_pack' => 0.0,
      'spoilage_value' => 0.0,
      'process_loss_qty_content' => 0.0,
      'process_loss_qty_pack' => 0.0,
      'process_loss_value' => 0.0,
      'variance_qty_content' => 0.0,
      'variance_qty_pack' => 0.0,
      'variance_value' => 0.0,
      'adjustment_plus_qty_content' => 0.0,
      'adjustment_plus_qty_pack' => 0.0,
      'adjustment_plus_value' => 0.0,
      'adjustment_qty_content' => 0.0,
      'adjustment_qty_pack' => 0.0,
      'closing_qty_content' => 0.0,
      'closing_qty_pack' => 0.0,
      'total_value' => 0.0,
      'avg_cost_per_content' => 0.0,
      'avg_cost_per_pack' => 0.0,
      '_content_per_buy_sum' => 0.0,
      '_hpp_sum' => 0.0,
      '_hpp_pack_sum' => 0.0,
      'children' => [],
    ];
  }

  $avgCostPerContent = (float)($row['avg_cost_per_content'] ?? 0);
  $avgCostPerPack = $avgCostPerContent * (float)($row['profile_content_per_buy'] ?? 0);
  $parent =& $parentMap[$parentKey];

  $parent['profile_count']++;
  $parent['_content_per_buy_sum'] += (float)($row['profile_content_per_buy'] ?? 0);
  $parent['_hpp_sum'] += $avgCostPerContent;
  $parent['_hpp_pack_sum'] += $avgCostPerPack;

  if ($parent['profile_buy_uom_code'] !== '' && (string)($row['profile_buy_uom_code'] ?? '') !== '' && $parent['profile_buy_uom_code'] !== (string)($row['profile_buy_uom_code'] ?? '')) {
    $parent['profile_buy_uom_code'] = 'MIX';
  }
  if ($parent['profile_content_uom_code'] !== '' && (string)($row['profile_content_uom_code'] ?? '') !== '' && $parent['profile_content_uom_code'] !== (string)($row['profile_content_uom_code'] ?? '')) {
    $parent['profile_content_uom_code'] = 'MIX';
  }

  foreach (['opening_qty_content','opening_qty_pack','in_qty_content','in_qty_pack','out_qty_content','out_qty_pack','discard_qty_content','discard_qty_pack','spoil_qty_content','spoil_qty_pack','waste_qty_content','waste_qty_pack','waste_component_qty_content','waste_component_qty_pack','waste_component_value','spoilage_qty_content','spoilage_qty_pack','spoilage_value','process_loss_qty_content','process_loss_qty_pack','process_loss_value','variance_qty_content','variance_qty_pack','variance_value','adjustment_plus_qty_content','adjustment_plus_qty_pack','adjustment_plus_value','adjustment_qty_content','adjustment_qty_pack','closing_qty_content','closing_qty_pack','total_value'] as $metricKey) {
    $parent[$metricKey] += (float)($row[$metricKey] ?? 0);
  }

  $parent['children'][] = $row;
}
unset($parent);

$parentRows = array_values($parentMap);
usort($parentRows, static function (array $a, array $b): int {
  $aDiv = trim((string)($a['division_name'] ?? ''));
  $bDiv = trim((string)($b['division_name'] ?? ''));
  $cmpDiv = strcasecmp($aDiv, $bDiv);
  if ($cmpDiv !== 0) {
    return $cmpDiv;
  }
  $cmpDest = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmpDest !== 0) {
    return $cmpDest;
  }
  $aName = trim(($a['item_name'] ?? '') !== '' ? (string)$a['item_name'] : (string)($a['material_name'] ?? ''));
  $bName = trim(($b['item_name'] ?? '') !== '' ? (string)$b['item_name'] : (string)($b['material_name'] ?? ''));
  return strcasecmp($aName, $bName);
});

foreach ($parentRows as &$parentRow) {
  $count = max(1, (int)($parentRow['profile_count'] ?? 0));
  $parentRow['avg_content_per_buy'] = (float)$parentRow['_content_per_buy_sum'] / $count;
  $parentRow['avg_cost_per_content'] = (float)$parentRow['_hpp_sum'] / $count;
  $parentRow['avg_cost_per_pack'] = (float)$parentRow['_hpp_pack_sum'] / $count;
}
unset($parentRow);

$summaryProfiles = count($monthlyRows);
$summaryDivisions = [];
$summaryInPack = 0.0;
$summaryOutPack = 0.0;
$summaryClosingPack = 0.0;
$summaryIn = 0.0;
$summaryOut = 0.0;
$summaryClosing = 0.0;
$summaryValue = 0.0;
foreach ($monthlyRows as $row) {
  $divisionId = (int)($row['division_id'] ?? 0);
  if ($divisionId > 0) {
    $summaryDivisions[$divisionId] = true;
  }
  $summaryInPack += (float)($row['in_qty_pack'] ?? 0);
  $summaryOutPack += (float)($row['out_qty_pack'] ?? 0);
  $summaryClosingPack += (float)($row['closing_qty_pack'] ?? 0);
  $summaryIn += (float)($row['in_qty_content'] ?? 0);
  $summaryOut += (float)($row['out_qty_content'] ?? 0);
  $summaryClosing += (float)($row['closing_qty_content'] ?? 0);
  $summaryValue += (float)($row['total_value'] ?? 0);
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
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Rekap parent-child per barang divisi dalam rentang 1 bulan (expand untuk detail profil).</small>
  </div>
  <div class="d-flex gap-2">
    <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname divisi bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
      <input type="hidden" name="stock_scope" value="DIVISION">
      <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
      <input type="hidden" name="division_id" value="<?php echo (int)($division_id ?? 0); ?>">
      <input type="hidden" name="destination" value="<?php echo html_escape($destinationValue); ?>">
      <input type="hidden" name="back_url" value="purchase/stock/division/daily?month=<?php echo rawurlencode($genMonth); ?>&division_id=<?php echo (int)($division_id ?? 0); ?>&destination=<?php echo rawurlencode($destinationValue); ?>">
      <button type="submit" class="btn btn-primary">Generate Opname + Stok Awal</button>
    </form>
    <a href="<?php echo site_url('inventory-material-daily'); ?>" class="btn btn-outline-primary">Daily Material Matrix</a>
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Divisi</a>
    <a href="<?php echo site_url('purchase/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi Live</a>
    <a href="<?php echo site_url('purchase/stock/opening/division'); ?>" class="btn btn-outline-secondary">Opening Divisi</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/daily'); ?>" class="btn btn-outline-secondary">Daily Gudang</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
      </div>
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
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / material / profile / merk / keterangan">
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
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">Profil Bulanan</div><div class="h5 mb-0"><?php echo number_format($summaryProfiles); ?></div></div></div></div>
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">Divisi</div><div class="h5 mb-0"><?php echo number_format($summaryDivisionCount); ?></div></div></div></div>
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">In (Pack)</div><div class="h5 mb-0"><?php echo number_format($summaryInPack, 2, ',', '.'); ?></div><small class="text-muted">Isi: <?php echo number_format($summaryIn, 2, ',', '.'); ?></small></div></div></div>
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">Out (Pack)</div><div class="h5 mb-0"><?php echo number_format($summaryOutPack, 2, ',', '.'); ?></div><small class="text-muted">Isi: <?php echo number_format($summaryOut, 2, ',', '.'); ?></small></div></div></div>
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">Closing (Pack)</div><div class="h5 mb-0"><?php echo number_format($summaryClosingPack, 2, ',', '.'); ?></div><small class="text-muted">Isi: <?php echo number_format($summaryClosing, 2, ',', '.'); ?></small></div></div></div>
  <div class="col-6 col-md-2"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>No</th>
          <th>Divisi</th>
          <th>Tujuan</th>
          <th>Nama</th>
          <th>Profil Pack</th>
          <th>Satuan</th>
          <th class="text-end">Isi / Pack</th>
          <th class="text-end">Stok Awal</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">WASTE</th>
          <th class="text-end">SPOILAGE</th>
          <th class="text-end">PROCESS LOSS</th>
          <th class="text-end">VARIANCE</th>
          <th class="text-end">Adjustment +</th>
          <th class="text-end">Stok Akhir</th>
          <th class="text-end">Nilai Total</th>
          <th class="text-end">HPP / Isi</th>
          <th class="text-end">HPP / Pack</th>
          <th class="text-end">Total Isi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="20" class="text-center text-muted py-4">Belum ada data bulanan divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $divisionText = $formatDivisionLabel((string)($parent['division_code'] ?? ''), (string)($parent['division_name'] ?? ''), (string)($parent['division_id'] ?? '-'));
              $itemText = trim((string)($parent['item_name'] ?? ''));
              $materialText = trim((string)($parent['material_name'] ?? ''));
              $objectText = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');
              $destinationText = $formatDestination((string)($parent['destination_group'] ?? 'REGULER'));
              $rowId = 'sdd-parent-' . (int)$idx;
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;
              $totalIsi = (float)($parent['closing_qty_content'] ?? 0);
              $uomPack = (string)($parent['profile_buy_uom_code'] ?? '-');
              $uomContent = (string)($parent['profile_content_uom_code'] ?? '');
              if (is_array($singleChild)) {
                $uomPack = (string)($singleChild['profile_buy_uom_code'] ?? $uomPack);
                $uomContent = (string)($singleChild['profile_content_uom_code'] ?? $uomContent);
              }
            ?>
            <tr class="table-warning">
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary sdd-toggle" data-target="<?php echo html_escape($rowId); ?>">+</button>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
                <span class="ms-1"><?php echo (int)($idx + 1); ?></span>
              </td>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($destinationText); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
              <td>
                <?php if ($isExpandable): ?>
                  <strong><?php echo (int)($parent['profile_count'] ?? 0); ?> profil</strong>
                <?php else: ?>
                  <?php
                    $singleProfile = is_array($singleChild) ? $singleChild : [];
                    $profileLines = [];
                    $brandText = trim((string)($singleProfile['profile_brand'] ?? ''));
                    $descText = trim((string)($singleProfile['profile_description'] ?? ''));
                    $expiredText = trim((string)($singleProfile['profile_expired_date'] ?? ''));
                    if ($brandText !== '' && $brandText !== '-') {
                      $profileLines[] = 'Merk: ' . html_escape($brandText);
                    }
                    if ($descText !== '' && $descText !== '-') {
                      $profileLines[] = 'Ket: ' . html_escape($descText);
                    }
                    if ($expiredText !== '' && $expiredText !== '-') {
                      $profileLines[] = 'Exp: ' . html_escape($expiredText);
                    }
                  ?>
                  <?php if (!empty($profileLines)): ?>
                    <?php echo implode('<br>', $profileLines); ?>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><?php echo html_escape($uomPack); ?></td>
              <td class="text-end">
                <?php
                  $contentPerBuy = is_array($singleChild)
                    ? (float)($singleChild['profile_content_per_buy'] ?? 0)
                    : (float)($parent['avg_content_per_buy'] ?? 0);
                ?>
                <?php echo number_format($contentPerBuy, 2, ',', '.'); ?> <?php echo html_escape($uomContent); ?>
              </td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['opening_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-success"><div class="fw-semibold"><?php echo ui_num((float)($parent['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['in_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end text-danger"><div class="fw-semibold"><?php echo ui_num((float)($parent['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['out_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['waste_component_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['waste_component_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['waste_component_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['spoilage_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['spoilage_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['spoilage_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['process_loss_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['process_loss_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['process_loss_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['variance_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['variance_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['variance_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($parent['adjustment_plus_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['adjustment_plus_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?> | Rp <?php echo number_format((float)($parent['adjustment_plus_value'] ?? 0), 2, ',', '.'); ?></small></td>
              <td class="text-end fw-semibold"><div class="fw-semibold"><?php echo ui_num((float)($parent['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($uomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($parent['closing_qty_content'] ?? 0)); ?> <?php echo html_escape($uomContent); ?></small></td>
              <td class="text-end"><?php echo number_format((float)($parent['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo ui_num((float)($parent['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($parent['avg_cost_per_pack'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num($totalIsi); ?></td>
            </tr>
            <?php if ($isExpandable): ?>
            <?php foreach ((array)($parent['children'] ?? []) as $child): ?>
              <?php
                $childUomPack = (string)($child['profile_buy_uom_code'] ?? '-');
                $childUomContent = (string)($child['profile_content_uom_code'] ?? '');
                $childAvgCostPack = (float)($child['avg_cost_per_content'] ?? 0) * (float)($child['profile_content_per_buy'] ?? 0);
              ?>
              <tr class="table-light <?php echo html_escape($rowId); ?>" style="display:none;">
                <td></td>
                <td><small class="text-muted">Detail Profil</small></td>
                <td></td>
                <td></td>
                <td>
                  <?php echo html_escape((string)($child['profile_name'] ?? '-')); ?><br>
                  <small class="text-muted">Brand: <?php echo html_escape((string)($child['profile_brand'] ?? '-')); ?></small><br>
                  <small class="text-muted">Exp: <?php echo html_escape((string)($child['profile_expired_date'] ?? '-') !== '' ? (string)($child['profile_expired_date'] ?? '-') : '-'); ?></small><br>
                  <small class="text-muted"><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($childUomContent); ?> / <?php echo html_escape($childUomPack); ?></small>
                </td>
                <td><?php echo html_escape($childUomPack); ?></td>
                <td class="text-end"><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape($childUomContent); ?></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['opening_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['opening_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end text-success"><div class="fw-semibold"><?php echo ui_num((float)($child['in_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['in_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end text-danger"><div class="fw-semibold"><?php echo ui_num((float)($child['out_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['out_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['waste_component_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['waste_component_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['waste_component_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['spoilage_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['spoilage_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['spoilage_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['process_loss_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['process_loss_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['process_loss_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['variance_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['variance_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['variance_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end"><div class="fw-semibold"><?php echo ui_num((float)($child['adjustment_plus_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['adjustment_plus_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?> | Rp <?php echo number_format((float)($child['adjustment_plus_value'] ?? 0), 2, ',', '.'); ?></small></td>
                <td class="text-end fw-semibold"><div class="fw-semibold"><?php echo ui_num((float)($child['closing_qty_pack'] ?? 0)); ?> <?php echo html_escape($childUomPack); ?></div><small class="text-muted"><?php echo ui_num((float)($child['closing_qty_content'] ?? 0)); ?> <?php echo html_escape($childUomContent); ?></small></td>
                <td class="text-end"><?php echo number_format((float)($child['total_value'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['avg_cost_per_content'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num($childAvgCostPack); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['closing_qty_content'] ?? 0)); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('.sdd-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var target = btn.getAttribute('data-target');
      if (!target) { return; }
      var rows = document.querySelectorAll('.' + target);
      if (!rows.length) { return; }
      var willShow = rows[0].style.display === 'none';
      rows.forEach(function(row){
        row.style.display = willShow ? '' : 'none';
      });
      btn.textContent = willShow ? '-' : '+';
    });
  });
})();
</script>
