<?php
$baseUrl = site_url('purchase/stock/division/daily');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Daily stok divisi (opening/in/out/adj/closing) per profile.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Divisi</a>
    <a href="<?php echo site_url('purchase/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi Live</a>
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
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / material / profile / merk / keterangan">
      </div>
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
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Divisi</th>
          <th>Objek</th>
          <th>Profile</th>
          <th class="text-end">Opening</th>
          <th class="text-end">In</th>
          <th class="text-end">Out</th>
          <th class="text-end">Adj</th>
          <th class="text-end">Closing</th>
          <th class="text-end">Value</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data daily divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $divisionText = trim((string)($r['division_code'] ?? ''));
              if (trim((string)($r['division_name'] ?? '')) !== '') {
                $divisionText .= ($divisionText !== '' ? ' - ' : '') . trim((string)$r['division_name']);
              }
              if ($divisionText === '') {
                $divisionText = (string)($r['division_id'] ?? '-');
              }

              $itemText = trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''));
              $materialText = trim((string)($r['material_code'] ?? '') . ' - ' . (string)($r['material_name'] ?? ''));
              $objectText = $itemText !== ' -' && $itemText !== '' ? $itemText : ($materialText !== ' -' && $materialText !== '' ? $materialText : '-');
            ?>
            <tr>
              <td><?php echo html_escape((string)$r['movement_date']); ?></td>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
              <td>
                <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
              </td>
              <td class="text-end"><?php echo number_format((float)($r['opening_qty_content'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end text-success"><?php echo number_format((float)($r['in_qty_content'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end text-danger"><?php echo number_format((float)($r['out_qty_content'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($r['adjustment_qty_content'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($r['closing_qty_content'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($r['total_value'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
