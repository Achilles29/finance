<?php
$baseUrl = site_url('purchase/stock/division');
$generateUrl = site_url('purchase/stock/opname/generate');
$genMonth = !empty($date_from ?? '') ? date('Y-m', strtotime((string)$date_from)) : date('Y-m');
$rowsData = is_array($rows ?? null) ? $rows : [];
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

$parentMap = [];
foreach ($rowsData as $row) {
  $divisionId = (int)($row['division_id'] ?? 0);
  $divisionCode = trim((string)($row['division_code'] ?? ''));
  $divisionName = trim((string)($row['division_name'] ?? ''));
  $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));

  $materialId = (int)($row['material_id'] ?? 0);
  $itemId = (int)($row['item_id'] ?? 0);
  $objectIdentity = $materialId > 0 ? ('M-' . $materialId) : ('I-' . $itemId);
  if ($objectIdentity === 'M-0' || $objectIdentity === 'I-0') {
    $objectIdentity .= '|' . strtoupper(trim((string)($row['material_code'] ?? '') . '|' . (string)($row['item_code'] ?? '')));
  }

  $parentKey = implode('|', [$divisionId, $destinationGroup, $objectIdentity]);
  if (!isset($parentMap[$parentKey])) {
    $parentMap[$parentKey] = [
      'division_id' => $divisionId,
      'division_code' => $divisionCode,
      'division_name' => $divisionName,
      'destination_group' => $destinationGroup,
      'item_code' => trim((string)($row['item_code'] ?? '')),
      'item_name' => trim((string)($row['item_name'] ?? '')),
      'material_code' => trim((string)($row['material_code'] ?? '')),
      'material_name' => trim((string)($row['material_name'] ?? '')),
      'qty_buy_balance' => 0.0,
      'qty_content_balance' => 0.0,
      'total_value' => 0.0,
      'updated_at' => '',
      'children' => [],
    ];
  }

  $profileNameKey = strtoupper(trim((string)($row['profile_name'] ?? '')));
  $profileBrandKey = strtoupper(trim((string)($row['profile_brand'] ?? '')));
  $profileDescKey = strtoupper(trim((string)($row['profile_description'] ?? '')));
  $profileExpiredKey = trim((string)($row['profile_expired_date'] ?? ''));
  $contentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $childKey = implode('|', [
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
    $profileExpiredKey,
    $contentPerBuyKey,
    strtoupper(trim((string)($row['profile_buy_uom_code'] ?? ''))),
    strtoupper(trim((string)($row['profile_content_uom_code'] ?? ''))),
  ]);

  if (!isset($parentMap[$parentKey]['children'][$childKey])) {
    $parentMap[$parentKey]['children'][$childKey] = [
      'profile_key' => (string)($row['profile_key'] ?? ''),
      'profile_name' => (string)($row['profile_name'] ?? '-'),
      'profile_brand' => (string)($row['profile_brand'] ?? '-'),
      'profile_description' => (string)($row['profile_description'] ?? '-'),
      'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
      'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
      'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
      'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
      'qty_buy_balance' => 0.0,
      'qty_content_balance' => 0.0,
      'total_value' => 0.0,
      'updated_at' => '',
    ];
  }

  $qtyBuy = (float)($row['qty_buy_balance'] ?? 0);
  $qtyContent = (float)($row['qty_content_balance'] ?? 0);
  $avgCost = (float)($row['avg_cost_per_content'] ?? 0);
  $rowValue = $qtyContent * $avgCost;
  $updatedAt = (string)($row['updated_at'] ?? '');

  $parentMap[$parentKey]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$parentKey]['qty_content_balance'] += $qtyContent;
  $parentMap[$parentKey]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$parentKey]['updated_at']) {
    $parentMap[$parentKey]['updated_at'] = $updatedAt;
  }

  $parentMap[$parentKey]['children'][$childKey]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$parentKey]['children'][$childKey]['qty_content_balance'] += $qtyContent;
  $parentMap[$parentKey]['children'][$childKey]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$parentKey]['children'][$childKey]['updated_at']) {
    $parentMap[$parentKey]['children'][$childKey]['updated_at'] = $updatedAt;
  }
}

$parentRows = array_values($parentMap);
foreach ($parentRows as &$parentRow) {
  $children = array_values($parentRow['children']);
  usort($children, static function (array $a, array $b): int {
    $cmp = strcasecmp((string)($a['profile_name'] ?? ''), (string)($b['profile_name'] ?? ''));
    if ($cmp !== 0) {
      return $cmp;
    }
    $cmp = strcasecmp((string)($a['profile_brand'] ?? ''), (string)($b['profile_brand'] ?? ''));
    if ($cmp !== 0) {
      return $cmp;
    }
    return strcasecmp((string)($a['profile_description'] ?? ''), (string)($b['profile_description'] ?? ''));
  });

  $parentRow['children'] = $children;
  $parentRow['profile_count'] = count($children);
  $parentRow['avg_cost_per_content'] = ((float)$parentRow['qty_content_balance'] !== 0.0)
    ? ((float)$parentRow['total_value'] / (float)$parentRow['qty_content_balance'])
    : 0.0;
}
unset($parentRow);

usort($parentRows, static function (array $a, array $b): int {
  $cmp = strcasecmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
  if ($cmp !== 0) {
    return $cmp;
  }
  $cmp = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
  if ($cmp !== 0) {
    return $cmp;
  }

  $aName = trim((string)($a['item_name'] ?? ''));
  if ($aName === '') {
    $aName = trim((string)($a['material_name'] ?? ''));
  }
  $bName = trim((string)($b['item_name'] ?? ''));
  if ($bName === '') {
    $bName = trim((string)($b['material_name'] ?? ''));
  }
  return strcasecmp($aName, $bName);
});

$summaryProfiles = 0;
$summaryQtyContent = 0.0;
$summaryTotalValue = 0.0;
$summaryDivisions = [];
foreach ($parentRows as $parentRow) {
  $summaryProfiles += (int)($parentRow['profile_count'] ?? 0);
  $summaryQtyContent += (float)($parentRow['qty_content_balance'] ?? 0);
  $summaryTotalValue += (float)($parentRow['total_value'] ?? 0);
  $divId = (int)($parentRow['division_id'] ?? 0);
  if ($divId > 0) {
    $summaryDivisions[$divId] = true;
  }
}
$summaryDivisionCount = count($summaryDivisions);
?>

<style>
  .dv-parent-row {
    background: #fff6ef;
    border-top: 2px solid #f0d8ca;
  }
  .dv-child-row td {
    background: #fff;
  }
  .dv-toggle {
    border: 1px solid #d7b6a8;
    background: #fff;
    color: #6a2d3c;
    border-radius: 8px;
    width: 26px;
    height: 24px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.35rem;
    font-size: 0.85rem;
    font-weight: 800;
  }
  .dv-toggle:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .dv-static {
    display: inline-flex;
    width: 26px;
    height: 24px;
    align-items: center;
    justify-content: center;
    margin-right: 0.35rem;
    border-radius: 8px;
    background: #e9f8df;
    color: #3e7f32;
    font-size: 0.8rem;
    font-weight: 800;
  }
  .dv-object-name {
    font-weight: 700;
    color: #4e1f2e;
  }
  .dv-object-code {
    color: #876a65;
    font-size: 0.8rem;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-store-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posisi stok divisi operasional per profile purchase.</small>
  </div>
  <div class="d-flex gap-2">
    <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname divisi bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
      <input type="hidden" name="stock_scope" value="DIVISION">
      <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
      <input type="hidden" name="division_id" value="<?php echo (int)($division_id ?? 0); ?>">
      <input type="hidden" name="destination" value="<?php echo html_escape($destinationValue); ?>">
      <input type="hidden" name="back_url" value="purchase/stock/division?date_from=<?php echo rawurlencode((string)($date_from ?? '')); ?>&date_to=<?php echo rawurlencode((string)($date_to ?? '')); ?>&division_id=<?php echo (int)($division_id ?? 0); ?>&destination=<?php echo rawurlencode($destinationValue); ?>">
      <button type="submit" class="btn btn-outline-primary">Generate Opname + Stok Awal</button>
    </form>
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
    <a href="<?php echo site_url('master/company-account'); ?>" class="btn btn-outline-secondary">Rekening</a>
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Divisi</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Stok Bulanan/Daily Divisi</a>
    <a href="<?php echo site_url('purchase/stock/opening/division'); ?>" class="btn btn-outline-secondary">Opening Divisi</a>
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang</a>
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
              $label = $formatDivisionLabel($code, $name, $id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo ((int)($division_id ?? 0) === $id) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Tujuan</label>
        <select class="form-select" name="destination">
          <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua Tujuan</option>
          <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari Stok Divisi</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Divisi / Item / Material / Profile / Merk / Keterangan">
      </div>
      <div class="w-100"></div>
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
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Divisi</div><div class="h5 mb-0"><?php echo number_format($summaryDivisionCount); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Qty Isi Total</div><div class="h5 mb-0"><?php echo number_format($summaryQtyContent, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Nilai</div><div class="h5 mb-0"><?php echo number_format($summaryTotalValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Divisi</th>
          <th>Tujuan</th>
          <th>Nama Barang</th>
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
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data stok divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $divisionText = $formatDivisionLabel(
                (string)($parent['division_code'] ?? ''),
                (string)($parent['division_name'] ?? ''),
                (string)($parent['division_id'] ?? '-')
              );
              $destinationText = $formatDestination((string)($parent['destination_group'] ?? 'REGULER'));

              $itemCode = trim((string)($parent['item_code'] ?? ''));
              $itemName = trim((string)($parent['item_name'] ?? ''));
              $materialCode = trim((string)($parent['material_code'] ?? ''));
              $materialName = trim((string)($parent['material_name'] ?? ''));

              $itemText = trim($itemName);
              $materialText = trim($materialName);
              $objectText = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');

              $collapseClass = 'dv-parent-' . ($idx + 1);
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;

              $profileCol = '<strong>' . (int)($parent['profile_count'] ?? 0) . ' profil</strong>';
              $brandCol = '-';
              $descCol = 'Parent';
              $sizeCol = '-';
              if (is_array($singleChild)) {
                $profileText = trim((string)($singleChild['profile_name'] ?? '-'));
                $expiredText = trim((string)($singleChild['profile_expired_date'] ?? ''));
                if ($expiredText !== '') {
                  $profileText .= ' (Exp: ' . $expiredText . ')';
                }
                $profileCol = html_escape($profileText);
                $brandCol = html_escape((string)($singleChild['profile_brand'] ?? '-'));
                $descCol = html_escape((string)($singleChild['profile_description'] ?? '-'));
                $sizeCol = number_format((float)($singleChild['profile_content_per_buy'] ?? 0), 2, ',', '.')
                  . ' ' . html_escape((string)($singleChild['profile_content_uom_code'] ?? ''))
                  . ' / ' . html_escape((string)($singleChild['profile_buy_uom_code'] ?? ''));
              }
            ?>
            <tr class="dv-parent-row">
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($destinationText); ?></td>
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="dv-toggle" data-bs-toggle="collapse" data-bs-target=".<?php echo html_escape($collapseClass); ?>" aria-expanded="false">+</button>
                <?php else: ?>
                  <span class="dv-static">=</span>
                <?php endif; ?>
                <span class="dv-object-name"><?php echo html_escape($objectText); ?></span>
              </td>
              <td><?php echo $profileCol; ?></td>
              <td><?php echo $brandCol; ?></td>
              <td><?php echo $descCol; ?></td>
              <td><?php echo $sizeCol; ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_buy_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_content_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['avg_cost_per_content'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($parent['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($parent['updated_at'] ?? '-')); ?></td>
            </tr>

            <?php if ($isExpandable): ?>
            <?php foreach (($parent['children'] ?? []) as $child): ?>
              <?php
                $profileText = trim((string)($child['profile_name'] ?? '-'));
                $expiredText = trim((string)($child['profile_expired_date'] ?? ''));
                if ($expiredText !== '') {
                  $profileText .= ' (Exp: ' . $expiredText . ')';
                }
                $childAvgCost = ((float)($child['qty_content_balance'] ?? 0) !== 0.0)
                  ? ((float)($child['total_value'] ?? 0) / (float)($child['qty_content_balance'] ?? 0))
                  : 0.0;
                $childClass = 'collapse ' . $collapseClass;
              ?>
              <tr class="dv-child-row <?php echo html_escape($childClass); ?>">
                <td></td>
                <td></td>
                <td class="ps-4 text-muted">Profil Item</td>
                <td><?php echo html_escape($profileText); ?></td>
                <td><?php echo html_escape((string)($child['profile_brand'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($child['profile_description'] ?? '-')); ?></td>
                <td><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($child['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($child['profile_buy_uom_code'] ?? '')); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['qty_buy_balance'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['qty_content_balance'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num($childAvgCost); ?></td>
                <td class="text-end"><?php echo number_format((float)($child['total_value'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($child['updated_at'] ?? '-')); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
