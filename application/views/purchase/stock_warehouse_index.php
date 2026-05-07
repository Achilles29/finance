<?php
$baseUrl = site_url('purchase/stock/warehouse');
$generateUrl = site_url('purchase/stock/opname/generate');
$genMonth = date('Y-m');
if (!empty($date_from ?? '')) {
  $genMonth = date('Y-m', strtotime((string)$date_from));
}
$rowsData = is_array($rows ?? null) ? $rows : [];

$parentMap = [];
foreach ($rowsData as $row) {
  $itemId = (int)($row['item_id'] ?? 0);
  $itemCode = trim((string)($row['item_code'] ?? ''));
  $itemName = trim((string)($row['item_name'] ?? ''));
  $objectIdentity = $itemId > 0 ? ('I-' . $itemId) : ('I-0|' . strtoupper($itemCode . '|' . $itemName));

  if (!isset($parentMap[$objectIdentity])) {
    $parentMap[$objectIdentity] = [
      'item_id' => $itemId,
      'item_code' => $itemCode,
      'item_name' => $itemName,
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

  if (!isset($parentMap[$objectIdentity]['children'][$childKey])) {
    $parentMap[$objectIdentity]['children'][$childKey] = [
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

  $parentMap[$objectIdentity]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$objectIdentity]['qty_content_balance'] += $qtyContent;
  $parentMap[$objectIdentity]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$objectIdentity]['updated_at']) {
    $parentMap[$objectIdentity]['updated_at'] = $updatedAt;
  }

  $parentMap[$objectIdentity]['children'][$childKey]['qty_buy_balance'] += $qtyBuy;
  $parentMap[$objectIdentity]['children'][$childKey]['qty_content_balance'] += $qtyContent;
  $parentMap[$objectIdentity]['children'][$childKey]['total_value'] += $rowValue;
  if ($updatedAt !== '' && $updatedAt > $parentMap[$objectIdentity]['children'][$childKey]['updated_at']) {
    $parentMap[$objectIdentity]['children'][$childKey]['updated_at'] = $updatedAt;
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
  $aName = trim((string)($a['item_name'] ?? ''));
  $bName = trim((string)($b['item_name'] ?? ''));
  $cmp = strcasecmp($aName, $bName);
  if ($cmp !== 0) {
    return $cmp;
  }
  return strcasecmp((string)($a['item_code'] ?? ''), (string)($b['item_code'] ?? ''));
});

$summaryProfiles = 0;
$summaryQtyContent = 0.0;
$summaryTotalValue = 0.0;
foreach ($parentRows as $parentRow) {
  $summaryProfiles += (int)($parentRow['profile_count'] ?? 0);
  $summaryQtyContent += (float)($parentRow['qty_content_balance'] ?? 0);
  $summaryTotalValue += (float)($parentRow['total_value'] ?? 0);
}
$summaryItemCount = count($parentRows);
?>

<style>
  .wh-parent-row {
    background: #fff6ef;
    border-top: 2px solid #f0d8ca;
  }
  .wh-child-row td {
    background: #fff;
  }
  .wh-toggle {
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
  .wh-toggle:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .wh-static {
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
  .wh-item-name {
    font-weight: 700;
    color: #4e1f2e;
  }
  .wh-item-code {
    color: #876a65;
    font-size: 0.8rem;
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-building-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posisi stok gudang per profile purchase (nama, merk, keterangan, ukuran/UOM).</small>
  </div>
  <div class="d-flex gap-2">
    <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname gudang bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
      <input type="hidden" name="stock_scope" value="WAREHOUSE">
      <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
      <input type="hidden" name="back_url" value="purchase/stock/warehouse?month=<?php echo rawurlencode($genMonth); ?>">
      <button type="submit" class="btn btn-outline-primary">Generate Opname + Stok Awal</button>
    </form>
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
    <a href="<?php echo site_url('purchase/stock/opening/warehouse'); ?>" class="btn btn-outline-secondary">Opening Gudang</a>
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
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data stok gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $objectText = trim((string)($parent['item_name'] ?? ''));
              $collapseClass = 'wh-parent-' . ($idx + 1);
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;
              $parentAvgCost = (float)($parent['avg_cost_per_content'] ?? 0);

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
            <tr class="wh-parent-row">
              <td>
                <?php if ($isExpandable): ?>
                  <button type="button" class="wh-toggle" data-bs-toggle="collapse" data-bs-target=".<?php echo html_escape($collapseClass); ?>" aria-expanded="false">+</button>
                <?php else: ?>
                  <span class="wh-static">=</span>
                <?php endif; ?>
                <span class="wh-item-name"><?php echo html_escape($objectText !== '' ? $objectText : '-'); ?></span>
              </td>
              <td><?php echo $profileCol; ?></td>
              <td><?php echo $brandCol; ?></td>
              <td><?php echo $descCol; ?></td>
              <td><?php echo $sizeCol; ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_buy_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_content_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num($parentAvgCost); ?></td>
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
              <tr class="wh-child-row <?php echo html_escape($childClass); ?>">
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
