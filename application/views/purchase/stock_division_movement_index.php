<?php
$baseUrl = site_url('purchase/stock/division/movement');
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
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Log keluar masuk stok per divisi dari inv_stock_movement_log.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi Live</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Daily Divisi</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/movement'); ?>" class="btn btn-outline-secondary">Mutasi Gudang</a>
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
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Divisi</th>
          <th>Tujuan</th>
          <th>No Mutasi</th>
          <th>Tipe</th>
          <th>Objek</th>
          <th class="text-end">Delta Isi</th>
          <th class="text-end">Saldo Isi</th>
          <th class="text-end">Unit Cost</th>
          <th class="text-end">Nilai Mutasi</th>
          <th>Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Belum ada data mutasi divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $divisionText = $formatDivisionLabel((string)($r['division_code'] ?? ''), (string)($r['division_name'] ?? ''), (string)($r['division_id'] ?? '-'));

              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $objectText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
              $deltaContent = (float)($r['qty_content_delta'] ?? 0);
              $destinationText = $formatDestination((string)($r['destination_group'] ?? 'REGULER'));
              $unitCost = (float)($r['unit_cost'] ?? 0);
              $mutationValue = abs($deltaContent) * $unitCost;
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($destinationText); ?></td>
              <td><?php echo html_escape((string)$r['movement_no']); ?></td>
              <td><?php echo html_escape((string)$r['movement_type']); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
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
