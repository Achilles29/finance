<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
?>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Daily Matrix Base/Prepare'); ?></h4>
  <small class="text-muted">Read-only daily rollup base/prepare per tanggal.</small>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-daily'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode / Nama / Divisi">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$label); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-daily'); ?>" class="btn btn-outline-danger">Clear</a>
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
          <th>Lokasi</th>
          <th>Divisi</th>
          <th>Komponen</th>
          <th class="text-end">Opening</th>
          <th class="text-end">In</th>
          <th class="text-end">Out</th>
          <th class="text-end">Adj</th>
          <th class="text-end">Waste</th>
          <th class="text-end">Spoil</th>
          <th class="text-end">Closing</th>
          <th class="text-end">Nilai</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data daily komponen.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['movement_date'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
              <td>
                <div><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <small class="text-muted"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?></small>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['opening_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['in_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['out_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['adjustment_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['waste_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['spoil_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($row['closing_qty'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_value'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

