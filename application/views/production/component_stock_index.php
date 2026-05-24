<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
?>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Base/Prepare'); ?></h4>
  <small class="text-muted">Read-only saldo live base/prepare per lokasi.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'stock']); ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-stock'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode / Nama Komponen / Divisi">
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
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-stock'); ?>" class="btn btn-outline-danger">Clear</a>
        <a href="<?php echo site_url('production/component-movements'); ?>" class="btn btn-outline-secondary">Lihat Mutasi</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Lokasi</th>
          <th>Divisi</th>
          <th>Kode</th>
          <th>Nama Komponen</th>
          <th>Tipe</th>
          <th class="text-end">Qty</th>
          <th>UOM</th>
          <th class="text-end">Avg Cost</th>
          <th class="text-end">Total Nilai</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data stok komponen.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['component_code'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['qty_on_hand'] ?? 0), 4, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['avg_cost'] ?? 0), 6, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['total_value'] ?? 0), 2, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($row['updated_at'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

