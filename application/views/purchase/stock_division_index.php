<?php
$baseUrl = site_url('purchase/stock/division');
$rowsData = is_array($rows ?? null) ? $rows : [];
$summaryProfiles = count($rowsData);
$summaryDivisions = [];
$summaryQtyContent = 0.0;
$summaryTotalValue = 0.0;
foreach ($rowsData as $row) {
  $divisionId = (int)($row['division_id'] ?? 0);
  if ($divisionId > 0) {
    $summaryDivisions[$divisionId] = true;
  }
  $qtyContent = (float)($row['qty_content_balance'] ?? 0);
  $avgCost = (float)($row['avg_cost_per_content'] ?? 0);
  $summaryQtyContent += $qtyContent;
  $summaryTotalValue += ($qtyContent * $avgCost);
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
    <h4 class="mb-1"><i class="ri ri-store-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posisi stok divisi operasional per profile purchase.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
    <a href="<?php echo site_url('master/company-account'); ?>" class="btn btn-outline-secondary">Rekening</a>
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Divisi</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Stok Bulanan/Daily Divisi</a>
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
          <th>Objek</th>
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
          <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data stok divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $divisionCode = trim((string)($r['division_code'] ?? ''));
              $divisionName = trim((string)($r['division_name'] ?? ''));
              $divisionText = $formatDivisionLabel($divisionCode, $divisionName, (string)($r['division_id'] ?? '-'));

              $itemCode = trim((string)($r['item_code'] ?? ''));
              $itemName = trim((string)($r['item_name'] ?? ''));
              $materialCode = trim((string)($r['material_code'] ?? ''));
              $materialName = trim((string)($r['material_name'] ?? ''));

              $itemText = trim(($itemCode !== '' ? $itemCode : '') . ($itemName !== '' ? ' - ' . $itemName : ''));
              $materialText = trim(($materialCode !== '' ? $materialCode : '') . ($materialName !== '' ? ' - ' . $materialName : ''));
              $objectText = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');
              $destinationText = $formatDestination((string)($r['destination_group'] ?? 'REGULER'));
              $qtyContent = (float)($r['qty_content_balance'] ?? 0);
              $avgCost = (float)($r['avg_cost_per_content'] ?? 0);
              $totalValue = $qtyContent * $avgCost;
            ?>
            <tr>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($destinationText); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
              <td><?php echo html_escape((string)($r['profile_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></td>
              <td><?php echo number_format((float)($r['profile_content_per_buy'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($r['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></td>
              <td class="text-end"><?php echo ui_num((float)$r['qty_buy_balance']); ?></td>
              <td class="text-end"><?php echo ui_num($qtyContent); ?></td>
              <td class="text-end"><?php echo ui_num((float)$r['avg_cost_per_content']); ?></td>
              <td class="text-end"><?php echo number_format($totalValue, 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($r['updated_at'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
