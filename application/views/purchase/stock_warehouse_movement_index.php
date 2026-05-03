<?php
$baseUrl = site_url('purchase/stock/warehouse/movement');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-arrow-left-right-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Log keluar masuk stok gudang dari inv_stock_movement_log.</small>
  </div>
  <div class="d-flex gap-2">
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
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No Mutasi</th>
          <th>Tipe</th>
          <th>Objek</th>
          <th>Profile</th>
          <th class="text-end">Delta Isi</th>
          <th class="text-end">Saldo Isi</th>
          <th class="text-end">Unit Cost</th>
          <th>Ref</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data mutasi gudang.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $objectText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
              $deltaContent = (float)($r['qty_content_delta'] ?? 0);
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape((string)$r['movement_no']); ?></td>
              <td><?php echo html_escape((string)$r['movement_type']); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
              <td>
                <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
              </td>
              <td class="text-end <?php echo $deltaContent >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($deltaContent, 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($r['qty_content_after'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($r['unit_cost'] ?? 0), 6, ',', '.'); ?></td>
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
