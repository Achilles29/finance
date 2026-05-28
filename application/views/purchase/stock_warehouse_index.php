<?php
$baseUrl = site_url('inventory/stock/warehouse');
$generateUrl = site_url('inventory/stock/opname/generate');
$lotAuditBaseUrl = site_url('inventory/stock/warehouse/lot');
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
  $contentPerBuyKey = number_format((float)($row['profile_content_per_buy'] ?? 0), 6, '.', '');
  $childKey = implode('|', [
    (string)($row['profile_key'] ?? ''),
    $profileNameKey,
    $profileBrandKey,
    $profileDescKey,
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
  :root {
    --wh-sticky-top: 0px;
  }
  .wh-sticky-head {
    position: fixed;
    top: var(--wh-sticky-top);
    left: 0;
    display: none;
    overflow: hidden;
    z-index: 1035;
    pointer-events: none;
    background: #fff8f4;
    border: 1px solid #ead5ca;
    border-bottom: 0;
    border-radius: 14px 14px 0 0;
    box-shadow: 0 10px 24px rgba(95, 23, 39, 0.12);
  }
  .wh-sticky-head table {
    margin-bottom: 0;
    transform: translateX(0);
    will-change: transform;
  }
  .wh-sticky-head th {
    background: #fff8f4 !important;
    box-shadow: inset 0 -1px 0 #e8d1c5;
    white-space: nowrap;
  }
  .wh-table-wrap {
    overflow-x: auto;
    overflow-y: visible;
  }
  .wh-stock-table thead th {
    background: #fff8f4;
    box-shadow: inset 0 -1px 0 #e8d1c5;
  }
  .wh-stock-table th:first-child,
  .wh-stock-table td:first-child {
    min-width: 280px;
  }
  .wh-parent-row {
    background: #fff6ef;
    border-top: 2px solid #f0d8ca;
  }
  .wh-child-row td {
    background: #fff;
  }
  .wh-name-cell {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    min-width: 0;
  }
  .wh-name-cell-child {
    padding-left: 1.4rem;
    position: relative;
  }
  .wh-name-cell-child::before {
    content: '';
    position: absolute;
    left: 0.55rem;
    top: 0.2rem;
    bottom: 0.2rem;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, #ebd7cc 0%, #d9b6a4 100%);
  }
  .wh-name-body {
    min-width: 0;
    display: grid;
    gap: 0.22rem;
  }
  .wh-name-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.32rem;
  }
  .wh-name-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.48rem;
    font-size: 0.68rem;
    font-weight: 800;
    max-width: 100%;
    color: #855346;
    border: 1px solid #ead5ca;
    background: #fff8f3;
  }
  .wh-name-chip.is-parent {
    border-radius: 999px;
  }
  .wh-name-chip.is-child {
    border-radius: 10px;
    border-style: dashed;
    background: #fffaf7;
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
    line-height: 1.25;
  }
  .wh-item-code {
    color: #876a65;
    font-size: 0.8rem;
  }
  .wh-child-label {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9a6f60;
  }
</style>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-building-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
  <small class="text-muted">Posisi stok gudang per profile purchase (nama, merk, keterangan, ukuran/UOM).</small>
</div>
<div class="d-flex flex-wrap gap-1 align-items-center mb-3">
  <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname gudang bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
    <input type="hidden" name="stock_scope" value="WAREHOUSE">
    <input type="hidden" name="month" value="<?php echo html_escape($genMonth); ?>">
    <input type="hidden" name="back_url" value="inventory/stock/warehouse?month=<?php echo rawurlencode($genMonth); ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger">Generate Opname + Stok Awal</button>
  </form>
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'stock']); ?>
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
  <div class="wh-sticky-head" id="whStickyHead" aria-hidden="true"></div>
  <div class="table-responsive wh-table-wrap">
    <table class="table table-striped table-hover mb-0 wh-stock-table" id="whStockTable">
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
          <th class="text-end">HPP / Pack</th>
          <th class="text-end">Total Nilai</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parentRows)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Belum ada data stok gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($parentRows as $idx => $parent): ?>
            <?php
              $objectText = trim((string)($parent['item_name'] ?? ''));
              $objectCode = trim((string)($parent['item_code'] ?? ''));
              $collapseClass = 'wh-parent-' . ($idx + 1);
              $isExpandable = ((int)($parent['profile_count'] ?? 0) > 1);
              $singleChild = (!$isExpandable && !empty($parent['children'])) ? $parent['children'][0] : null;
              $parentAvgCost = (float)($parent['avg_cost_per_content'] ?? 0);
              $parentCPB = is_array($singleChild) ? (float)($singleChild['profile_content_per_buy'] ?? 0) : 0.0;
              $parentAvgCostPerPack = $parentCPB > 0 ? $parentAvgCost * $parentCPB : null;

              $profileCol = '<strong>' . (int)($parent['profile_count'] ?? 0) . ' profil</strong>';
              $brandCol = '-';
              $descCol = 'Parent';
              $sizeCol = '-';
              if (is_array($singleChild)) {
                $profileText = trim((string)($singleChild['profile_name'] ?? '-'));
                $lotUrl = $lotAuditBaseUrl
                  . '?scope=WAREHOUSE&status=ALL&profile_key=' . rawurlencode((string)($singleChild['profile_key'] ?? ''));
                $profileCol = html_escape($profileText);
                $profileCol .= '<div class="small mt-1"><a href="' . html_escape($lotUrl) . '">Lihat Lot</a></div>';
                $brandCol = html_escape((string)($singleChild['profile_brand'] ?? '-'));
                $descCol = html_escape((string)($singleChild['profile_description'] ?? '-'));
                $sizeCol = number_format((float)($singleChild['profile_content_per_buy'] ?? 0), 2, ',', '.')
                  . ' ' . html_escape((string)($singleChild['profile_content_uom_code'] ?? ''))
                  . ' / ' . html_escape((string)($singleChild['profile_buy_uom_code'] ?? ''));
              }
            ?>
            <tr class="wh-parent-row">
              <td>
                <div class="wh-name-cell">
                  <?php if ($isExpandable): ?>
                    <button type="button" class="wh-toggle" data-bs-toggle="collapse" data-bs-target=".<?php echo html_escape($collapseClass); ?>" aria-expanded="false">+</button>
                  <?php else: ?>
                    <span class="wh-static">=</span>
                  <?php endif; ?>
                  <div class="wh-name-body">
                    <div class="wh-item-name"><?php echo html_escape($objectText !== '' ? $objectText : '-'); ?></div>
                    <div class="wh-name-meta">
                      <?php if ($objectCode !== ''): ?>
                        <span class="wh-name-chip is-parent"><?php echo html_escape($objectCode); ?></span>
                      <?php endif; ?>
                      <span class="wh-name-chip is-parent">Item Gudang</span>
                      <?php if ($isExpandable): ?>
                        <span class="wh-name-chip is-parent"><?php echo (int)($parent['profile_count'] ?? 0); ?> profil</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td><?php echo $profileCol; ?></td>
              <td><?php echo $brandCol; ?></td>
              <td><?php echo $descCol; ?></td>
              <td><?php echo $sizeCol; ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_buy_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num((float)($parent['qty_content_balance'] ?? 0)); ?></td>
              <td class="text-end fw-semibold"><?php echo ui_num($parentAvgCost); ?></td>
              <td class="text-end fw-semibold"><?php echo $parentAvgCostPerPack !== null ? ui_num($parentAvgCostPerPack) : '-'; ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($parent['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($parent['updated_at'] ?? '-')); ?></td>
            </tr>

            <?php if ($isExpandable): ?>
            <?php foreach (($parent['children'] ?? []) as $child): ?>
              <?php
                $profileText = trim((string)($child['profile_name'] ?? '-'));
                $lotUrl = $lotAuditBaseUrl
                  . '?scope=WAREHOUSE&status=ALL&profile_key=' . rawurlencode((string)($child['profile_key'] ?? ''));
                $childAvgCost = ((float)($child['qty_content_balance'] ?? 0) !== 0.0)
                  ? ((float)($child['total_value'] ?? 0) / (float)($child['qty_content_balance'] ?? 0))
                  : 0.0;
                $childCPB = (float)($child['profile_content_per_buy'] ?? 0);
                $childAvgCostPerPack = $childCPB > 0 ? $childAvgCost * $childCPB : 0.0;
                $childClass = 'collapse ' . $collapseClass;
              ?>
              <tr class="wh-child-row <?php echo html_escape($childClass); ?>">
                <td>
                  <div class="wh-name-cell wh-name-cell-child">
                    <div class="wh-name-body">
                      <div class="wh-child-label">Profil Turunan</div>
                      <div class="wh-item-name"><?php echo html_escape($objectText !== '' ? $objectText : '-'); ?></div>
                      <div class="wh-name-meta">
                        <?php if ($objectCode !== ''): ?>
                          <span class="wh-name-chip is-child"><?php echo html_escape($objectCode); ?></span>
                        <?php endif; ?>
                        <span class="wh-name-chip is-child">Child</span>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <?php echo html_escape($profileText); ?>
                  <div class="small mt-1"><a href="<?php echo html_escape($lotUrl); ?>">Lihat Lot</a></div>
                </td>
                <td><?php echo html_escape((string)($child['profile_brand'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($child['profile_description'] ?? '-')); ?></td>
                <td><?php echo number_format((float)($child['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($child['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($child['profile_buy_uom_code'] ?? '')); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['qty_buy_balance'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num((float)($child['qty_content_balance'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num($childAvgCost); ?></td>
                <td class="text-end"><?php echo ui_num($childAvgCostPerPack); ?></td>
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

<script>
(function(){
  function syncWarehouseStickyTop(){
    var navbar = document.getElementById('layout-navbar') || document.querySelector('.layout-navbar');
    var topOffset = navbar ? Math.ceil(navbar.getBoundingClientRect().height) : 0;
    document.documentElement.style.setProperty('--wh-sticky-top', topOffset + 'px');
    return topOffset;
  }

  function initWarehouseFloatingHeader(){
    var wrapper = document.querySelector('.wh-table-wrap');
    var table = document.getElementById('whStockTable');
    var host = document.getElementById('whStickyHead');
    if (!wrapper || !table || !host) { return; }
    var thead = table.querySelector('thead');
    if (!thead) { return; }

    host.innerHTML = '<table class="' + table.className + '"><thead>' + thead.innerHTML + '</thead></table>';
    var cloneTable = host.querySelector('table');
    var cloneHead = cloneTable ? cloneTable.querySelector('thead') : null;
    if (!cloneTable || !cloneHead) { return; }

    function syncFloatingHeader(){
      var stickyTop = syncWarehouseStickyTop();
      var wrapperRect = wrapper.getBoundingClientRect();
      var tableRect = table.getBoundingClientRect();
      var originalThs = Array.prototype.slice.call(thead.querySelectorAll('th'));
      var cloneThs = Array.prototype.slice.call(cloneHead.querySelectorAll('th'));
      originalThs.forEach(function(th, index){
        if (!cloneThs[index]) { return; }
        var width = Math.ceil(th.getBoundingClientRect().width);
        cloneThs[index].style.width = width + 'px';
        cloneThs[index].style.minWidth = width + 'px';
        cloneThs[index].style.maxWidth = width + 'px';
      });
      cloneTable.style.width = Math.ceil(table.getBoundingClientRect().width) + 'px';
      cloneTable.style.transform = 'translateX(' + (-wrapper.scrollLeft) + 'px)';

      var headerHeight = Math.ceil(thead.getBoundingClientRect().height || 0);
      var shouldShow = wrapperRect.top <= stickyTop && tableRect.bottom > (stickyTop + headerHeight);
      host.style.display = shouldShow ? 'block' : 'none';
      host.style.top = stickyTop + 'px';
      host.style.left = Math.ceil(wrapperRect.left) + 'px';
      host.style.width = Math.ceil(wrapperRect.width) + 'px';
    }

    wrapper.addEventListener('scroll', syncFloatingHeader, { passive: true });
    window.addEventListener('scroll', syncFloatingHeader, { passive: true });
    window.addEventListener('resize', syncFloatingHeader);
    requestAnimationFrame(syncFloatingHeader);
  }

  syncWarehouseStickyTop();
  window.addEventListener('resize', syncWarehouseStickyTop);
  initWarehouseFloatingHeader();
})();
</script>
