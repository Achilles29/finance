<?php
$filters  = is_array($filters ?? null) ? $filters : [];
$rows     = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$generateUrl = isset($generate_url) ? $generate_url : site_url('production/component-openings/generate-monthly');

$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];

$fmtQty = static function ($v): string {
  $f = round((float)$v, 2);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
};
$fmtCost = static function ($v): string {
  $f = round((float)$v, 0);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 0, ',', '.');
};
$typeLabel = static function (string $t): string {
  return $t === 'BASE' ? '<span class="badge bg-primary-subtle text-primary">Base</span>'
       : ($t === 'PREPARE' ? '<span class="badge bg-warning-subtle text-warning">Prepare</span>' : '<span class="badge bg-secondary-subtle text-secondary">' . htmlspecialchars($t) . '</span>');
};
$locLabel = static function (string $loc): string {
  switch (strtoupper($loc)) {
    case 'BAR':           return '<span class="badge bg-info-subtle text-info">BAR</span>';
    case 'KITCHEN':       return '<span class="badge bg-success-subtle text-success">KITCHEN</span>';
    case 'BAR_EVENT':     return '<span class="badge bg-danger-subtle text-danger">BAR Event</span>';
    case 'KITCHEN_EVENT': return '<span class="badge bg-warning-subtle text-warning">KITCHEN Event</span>';
    default:              return '<span class="badge bg-secondary-subtle text-secondary">' . htmlspecialchars($loc) . '</span>';
  }
};
?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-file-list-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Opname Bulanan Component'); ?></h4>
      <small class="text-muted">Snapshot stok component hasil generate opname bulanan. Data mencerminkan posisi stok akhir bulan di setiap lokasi dan divisi.</small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <button type="button" class="btn btn-sm btn-outline-danger" id="btn-generate-opname">
        <i class="ri ri-refresh-line me-1"></i>Generate Opname + Stok Awal
      </button>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opname']); ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-opname'); ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?php echo (int)($div['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($div['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape(trim((string)($div['division_code'] ?? $div['code'] ?? '')) . ' - ' . trim((string)($div['division_name'] ?? $div['name'] ?? ''))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape($key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === $key) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama component / divisi">
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
    </form>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="alert alert-info">
    Belum ada data opname bulanan untuk bulan <?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>.
    Klik <strong>Generate Opname + Stok Awal</strong> untuk membuat snapshot dari data stok bulan ini.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle small">
      <thead class="table-light">
        <tr>
          <th>Lokasi</th>
          <th>Divisi</th>
          <th>Tipe</th>
          <th>Component</th>
          <th>UOM</th>
          <th class="text-end">Opening</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">Waste</th>
          <th class="text-end">Spoil</th>
          <th class="text-end">Adj+</th>
          <th class="text-end">Adj-</th>
          <th class="text-end">Closing</th>
          <th class="text-end">Avg Cost</th>
          <th>Di-generate</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?php echo $locLabel((string)($row['location_type'] ?? '')); ?></td>
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
            <td><?php echo $typeLabel((string)($row['component_type'] ?? '')); ?></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
              <div class="text-muted" style="font-size:.72rem"><?php echo html_escape((string)($row['component_code'] ?? '')); ?></div>
            </td>
            <td><?php echo html_escape((string)($row['uom_code'] ?? $row['uom_name'] ?? '-')); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['opening_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['in_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['out_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['waste_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['spoil_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['adjustment_plus_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['adjustment_minus_qty'] ?? 0); ?></td>
            <td class="text-end fw-semibold"><?php echo $fmtQty($row['closing_qty'] ?? 0); ?></td>
            <td class="text-end text-muted"><?php echo $fmtCost($row['avg_cost'] ?? 0); ?></td>
            <td class="text-muted" style="white-space:nowrap">
              <?php if (!empty($row['generated_by_name'])): ?>
                <div><?php echo html_escape((string)$row['generated_by_name']); ?></div>
              <?php endif; ?>
              <?php if (!empty($row['generated_at'])): ?>
                <div style="font-size:.7rem"><?php echo html_escape(substr((string)$row['generated_at'], 0, 16)); ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="text-muted small mt-1"><?php echo count($rows); ?> baris</div>
<?php endif; ?>

<script>
(function () {
  const generateUrl = <?php echo json_encode($generateUrl); ?>;

  document.getElementById('btn-generate-opname')?.addEventListener('click', async () => {
    const btn = document.getElementById('btn-generate-opname');
    const month = document.querySelector('input[name="month"]')?.value || '';
    const locationType = document.querySelector('select[name="location_type"]')?.value || '';
    const divisionId = document.querySelector('select[name="division_id"]')?.value || '';

    if (!month) {
      alert('Pilih bulan terlebih dahulu.');
      return;
    }
    if (!confirm('Generate opname bulan ' + month + ' dan carry-forward opening bulan berikutnya?')) {
      return;
    }
    btn.disabled = true;
    btn.textContent = 'Generating...';
    try {
      const res = await fetch(generateUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ month, location_type: locationType, division_id: divisionId })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        alert('Gagal: ' + (data.message || res.statusText));
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Generate Opname + Stok Awal';
        return;
      }
      window.location.reload();
    } catch (err) {
      alert('Error: ' + err.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Generate Opname + Stok Awal';
    }
  });
})();
</script>
