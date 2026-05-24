<?php
$baseUrl = (string)($base_url ?? site_url('inventory/lot-audit'));
$rows = is_array($rows ?? null) ? $rows : [];
$divisionOptions = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$scopeValue = strtoupper(trim((string)($scope ?? 'ALL')));
$pageScope = strtoupper(trim((string)($page_scope ?? $scopeValue)));
$scopeLocked = !empty($scope_locked);
$statusValue = strtoupper(trim((string)($status ?? 'OPEN')));
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

$buildQueryUrl = static function (string $route, array $params = []): string {
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }
    $url = site_url($route);
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
};

$resolveSearchToken = static function (array $row): string {
    $profileKeyValue = trim((string)($row['profile_key'] ?? ''));
    if ($profileKeyValue !== '') {
        return $profileKeyValue;
    }

    $objectCodeValue = trim((string)($row['item_code'] ?? ''));
    if ($objectCodeValue === '') {
        $objectCodeValue = trim((string)($row['material_code'] ?? ''));
    }
    if ($objectCodeValue !== '') {
        return $objectCodeValue;
    }

    $objectNameValue = trim((string)($row['item_name'] ?? ''));
    if ($objectNameValue === '') {
        $objectNameValue = trim((string)($row['material_name'] ?? ''));
    }
    return $objectNameValue;
};

$resolveMonthValue = static function (array $row, string $fallback = ''): string {
    $receiptDateValue = trim((string)($row['receipt_date'] ?? ''));
    if ($receiptDateValue !== '' && preg_match('/^\d{4}-\d{2}/', $receiptDateValue) === 1) {
        return substr($receiptDateValue, 0, 7);
    }
    if ($fallback !== '' && preg_match('/^\d{4}-\d{2}/', $fallback) === 1) {
        return substr($fallback, 0, 7);
    }
    return date('Y-m');
};

$buildStockLinks = static function (array $row) use ($buildQueryUrl, $resolveSearchToken, $resolveMonthValue, $date_from, $date_to): array {
    $links = [];
    $searchToken = $resolveSearchToken($row);
    $monthValue = $resolveMonthValue($row, (string)($date_from ?? ''));
    $scopeName = strtoupper(trim((string)($row['location_scope'] ?? '')));

    if ($scopeName === 'DIVISION') {
        $divisionIdValue = (int)($row['division_id'] ?? 0);
        $destinationParam = strtoupper(trim((string)($row['destination_type'] ?? 'ALL')));
        $baseParams = [
            'q' => $searchToken,
            'division_id' => $divisionIdValue > 0 ? $divisionIdValue : null,
            'destination' => $destinationParam !== '' ? $destinationParam : 'ALL',
            'date_from' => (string)($date_from ?? ''),
            'date_to' => (string)($date_to ?? ''),
        ];

        $links[] = ['label' => 'Stok Divisi', 'url' => $buildQueryUrl('inventory/stock/division', $baseParams)];
        $links[] = ['label' => 'Bulanan', 'url' => $buildQueryUrl('inventory/stock/division/daily', $baseParams + ['month' => $monthValue])];
        $links[] = ['label' => 'Daily Matrix', 'url' => $buildQueryUrl('inventory-material-daily', $baseParams + ['month' => $monthValue])];
        return $links;
    }

    $baseParams = [
        'q' => $searchToken,
        'date_from' => (string)($date_from ?? ''),
        'date_to' => (string)($date_to ?? ''),
    ];
    $links[] = ['label' => 'Stok Gudang', 'url' => $buildQueryUrl('inventory/stock/warehouse', $baseParams)];
    $links[] = ['label' => 'Bulanan', 'url' => $buildQueryUrl('inventory/stock/warehouse/daily', $baseParams + ['month' => $monthValue])];
    $links[] = ['label' => 'Daily Matrix', 'url' => $buildQueryUrl('inventory-warehouse-daily', $baseParams + ['month' => $monthValue])];
    return $links;
};

$buildSourceLink = static function (array $row) use ($buildQueryUrl): array {
    $sourceTableValue = trim((string)($row['source_table'] ?? ''));
    $sourceIdValue = (int)($row['source_id'] ?? 0);
    if ($sourceTableValue === '' || $sourceIdValue <= 0) {
        return ['label' => '', 'url' => ''];
    }

    if ($sourceTableValue === 'pur_purchase_receipt') {
        $purchaseOrderId = (int)($row['receipt_purchase_order_id'] ?? 0);
        $receiptId = (int)($row['receipt_id'] ?? $sourceIdValue);
        if ($purchaseOrderId > 0 && $receiptId > 0) {
            return [
                'label' => trim((string)($row['receipt_no'] ?? '')) !== '' ? ('Receipt ' . (string)$row['receipt_no']) : 'Detail Receipt',
                'url' => site_url('purchase-orders/detail/' . $purchaseOrderId) . '#po-receipt-' . $receiptId,
            ];
        }
    }

    if ($sourceTableValue === 'pur_store_request_fulfillment') {
        $requestId = (int)($row['source_store_request_id'] ?? 0);
        if ($requestId > 0) {
            $fulfillmentNo = trim((string)($row['source_fulfillment_no'] ?? ''));
            return [
                'label' => $fulfillmentNo !== '' ? ('Fulfillment ' . $fulfillmentNo) : 'Detail Fulfillment',
                'url' => site_url('store-requests/detail/' . $requestId) . '#sr-fulfillment-' . $sourceIdValue,
            ];
        }
    }

    if ($sourceTableValue === 'inv_component_batch') {
        $batchNo = trim((string)($row['source_batch_no'] ?? ''));
        return [
            'label' => $batchNo !== '' ? ('Batch ' . $batchNo) : 'Batch Produksi',
            'url' => $buildQueryUrl('production/component-batches', [
                'q' => $batchNo !== '' ? $batchNo : $sourceIdValue,
            ]) . '#component-batch-' . $sourceIdValue,
        ];
    }

    if ($sourceTableValue === 'inv_stock_adjustment') {
      $scopeName = strtoupper(trim((string)($row['location_scope'] ?? 'WAREHOUSE')));
      $route = $scopeName === 'DIVISION'
        ? 'inventory/stock/adjustment/division'
        : 'inventory/stock/adjustment/warehouse';
      return [
        'label' => 'Adjustment Stok',
        'url' => site_url($route) . '#stock-adjustment-' . $sourceIdValue,
      ];
    }

    return ['label' => '', 'url' => ''];
};

$headerMonth = '';
if (!empty($date_from ?? '') && preg_match('/^\d{4}-\d{2}/', (string)$date_from) === 1) {
    $headerMonth = substr((string)$date_from, 0, 7);
} else {
    $headerMonth = date('Y-m');
}

$summaryLotCount = count($rows);
$summaryBalance = 0.0;
$summaryValue = 0.0;
foreach ($rows as $row) {
    $summaryBalance += (float)($row['qty_balance'] ?? 0);
    $summaryValue += (float)($row['qty_balance'] ?? 0) * (float)($row['unit_cost'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-stack-line page-title-icon"></i><?php echo html_escape($title ?? 'Audit Lot Material'); ?></h4>
    <small class="text-muted"><?php echo html_escape((string)($subtitle ?? 'Posisi lot FIFO per scope, profile, dan lokasi stok.')); ?></small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo site_url('inventory/fifo-audit'); ?>" class="btn btn-outline-secondary">Audit FIFO</a>
    <?php if ($pageScope === 'WAREHOUSE'): ?>
      <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'WAREHOUSE', 'active_tab' => 'lot']); ?>
    <?php elseif ($pageScope === 'DIVISION'): ?>
      <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'lot']); ?>
    <?php else: ?>
      <a href="<?php echo site_url('inventory/stock/warehouse/lot'); ?>" class="btn btn-outline-secondary">Lot Gudang</a>
      <a href="<?php echo site_url('inventory/stock/division/lot'); ?>" class="btn btn-outline-secondary">Lot Divisi</a>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <?php if ($scopeLocked): ?>
        <input type="hidden" name="scope" value="<?php echo html_escape($scopeValue); ?>">
      <?php else: ?>
      <div class="col-md-2">
        <label class="form-label mb-1">Scope</label>
        <select class="form-select" name="scope">
          <option value="ALL" <?php echo $scopeValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="WAREHOUSE" <?php echo $scopeValue === 'WAREHOUSE' ? 'selected' : ''; ?>>Warehouse</option>
          <option value="DIVISION" <?php echo $scopeValue === 'DIVISION' ? 'selected' : ''; ?>>Division</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="OPEN" <?php echo $statusValue === 'OPEN' ? 'selected' : ''; ?>>Open</option>
          <option value="ALL" <?php echo $statusValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="CLOSED" <?php echo $statusValue === 'CLOSED' ? 'selected' : ''; ?>>Closed</option>
        </select>
      </div>
      <?php if ($scopeValue !== 'WAREHOUSE'): ?>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select class="form-select" name="division_id" id="lotDivision">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisionOptions as $divisionRow): ?>
            <?php
              $id = (int)($divisionRow['id'] ?? 0);
              $label = $formatDivisionLabel((string)($divisionRow['code'] ?? ''), (string)($divisionRow['name'] ?? ''), $id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo ((int)($division_id ?? 0) === $id) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select class="form-select" name="destination" id="lotDestination">
          <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
          <option value="BAR" <?php echo $destinationValue === 'BAR' ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN" <?php echo $destinationValue === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="BAR_EVENT" <?php echo $destinationValue === 'BAR_EVENT' ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT" <?php echo $destinationValue === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="OFFICE" <?php echo $destinationValue === 'OFFICE' ? 'selected' : ''; ?>>Office</option>
          <option value="GUDANG" <?php echo $destinationValue === 'GUDANG' ? 'selected' : ''; ?>>Gudang</option>
          <option value="OTHER" <?php echo $destinationValue === 'OTHER' ? 'selected' : ''; ?>>Other</option>
        </select>
      </div>
      <?php else: ?>
        <input type="hidden" name="division_id" value="<?php echo (int)($division_id ?? 0); ?>">
        <input type="hidden" name="destination" value="ALL">
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($q ?? '')); ?>" placeholder="Lot / profile / item / material / source ref">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Profile Key</label>
        <input type="text" class="form-control" name="profile_key" value="<?php echo html_escape((string)($profile_key ?? '')); ?>" placeholder="Exact profile key">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" min="1" max="500" name="limit" value="<?php echo (int)($limit ?? 200); ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Jumlah Lot</div><div class="h5 mb-0"><?php echo number_format($summaryLotCount); ?></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Qty Balance Total</div><div class="h5 mb-0"><?php echo number_format($summaryBalance, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Nilai Estimasi</div><div class="h5 mb-0">Rp <?php echo number_format($summaryValue, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0 fin-audit-table">
      <thead>
        <tr>
          <th>Lot</th>
          <th>Scope</th>
          <th>Objek</th>
          <th>Profile</th>
          <th>Receipt</th>
          <th class="text-end col-balance">Qty In</th>
          <th class="text-end col-delta">Qty Out</th>
          <th class="text-end col-balance">Balance</th>
          <th class="text-end col-amount">Unit Cost</th>
          <th>Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada lot yang sesuai filter.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $divisionText = $formatDivisionLabel((string)($row['division_code'] ?? ''), (string)($row['division_name'] ?? ''), (string)($row['division_id'] ?? '-'));
              $objectText = trim((string)($row['item_name'] ?? ''));
              if ($objectText === '') {
                  $objectText = trim((string)($row['material_name'] ?? ''));
              }
              $objectCode = trim((string)($row['item_code'] ?? ''));
              if ($objectCode === '') {
                  $objectCode = trim((string)($row['material_code'] ?? ''));
              }
              $profileText = trim((string)($row['profile_key'] ?? ''));
              if ($profileText === '') {
                  $profileText = '-';
              }
              $sourceLink = $buildSourceLink($row);
              $stockLinks = $buildStockLinks($row);
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo html_escape((string)($row['lot_no'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo !empty($row['parent_lot_no']) ? 'Parent: ' . html_escape((string)$row['parent_lot_no']) : 'Root lot'; ?></div>
              </td>
              <td>
                <div><?php echo html_escape((string)($row['location_scope'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo html_escape((string)($row['destination_name'] ?? '-')); ?><?php echo !empty($row['division_id']) ? ' | ' . html_escape($divisionText) : ''; ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?php echo html_escape($objectText !== '' ? $objectText : '-'); ?></div>
                <div class="small text-muted"><?php echo html_escape($objectCode !== '' ? $objectCode : '-'); ?></div>
              </td>
              <td>
                <div class="small text-break"><?php echo html_escape($profileText); ?></div>
                <div class="small text-muted"><?php echo html_escape((string)($row['buy_uom_code'] ?? '-')); ?> -> <?php echo html_escape((string)($row['content_uom_code'] ?? '-')); ?></div>
              </td>
              <td>
                <div><?php echo html_escape((string)($row['receipt_date'] ?? '-')); ?></div>
                <div class="small text-muted"><?php echo !empty($row['expiry_date']) ? 'Exp ' . html_escape((string)$row['expiry_date']) : 'Tanpa expiry'; ?></div>
              </td>
              <td class="text-end col-balance"><?php echo number_format((float)($row['qty_in'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end col-delta fin-audit-delta-negative"><?php echo number_format((float)($row['qty_out'] ?? 0) * -1, 2, ',', '.'); ?></td>
              <td class="text-end col-balance fw-semibold"><?php echo number_format((float)($row['qty_balance'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end col-amount">Rp <?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
              <td>
                <div class="small"><?php echo html_escape((string)($row['source_table'] ?? '-')); ?><?php echo !empty($row['source_id']) ? ' #' . (int)$row['source_id'] : ''; ?></div>
                <?php if (!empty($sourceLink['url']) && !empty($sourceLink['label'])): ?>
                  <div class="small"><a href="<?php echo html_escape($sourceLink['url']); ?>"><?php echo html_escape($sourceLink['label']); ?></a></div>
                <?php endif; ?>
                <div class="small text-muted">Issue line: <?php echo number_format((int)($row['issue_line_count'] ?? 0)); ?> | Qty keluar: <?php echo number_format((float)($row['issue_qty_total'] ?? 0), 2, ',', '.'); ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var divisionEl = document.getElementById('lotDivision');
  var destinationEl = document.getElementById('lotDestination');
  var guardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  if (!divisionEl || !destinationEl) {
    return;
  }

  function applyDestinationGuard() {
    var divisionId = String(divisionEl.value || '');
    var allowed = guardMap[divisionId] || [];
    Array.prototype.forEach.call(destinationEl.options, function (opt) {
      var value = String(opt.value || '').toUpperCase();
      if (!divisionId || value === 'ALL' || value === 'REGULER' || value === 'EVENT' || value === 'GUDANG') {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      var keep = allowed.indexOf(value) >= 0;
      opt.hidden = !keep;
      opt.disabled = !keep;
    });
    if (destinationEl.selectedOptions.length === 0 || destinationEl.selectedOptions[0].disabled) {
      destinationEl.value = 'ALL';
    }
  }

  divisionEl.addEventListener('change', applyDestinationGuard);
  applyDestinationGuard();
})();
</script>