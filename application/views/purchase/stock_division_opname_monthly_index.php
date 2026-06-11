<?php
$filters   = is_array($filters ?? null) ? $filters : [];
$rows      = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$generateUrl = isset($generate_url) ? $generate_url : site_url('inventory/stock/opname/generate');

$fmtQty = static function ($v): string {
  $f = round((float)$v, 2);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 2, ',', '.');
};
$fmtCost = static function ($v): string {
  $f = round((float)$v, 0);
  return $f == 0 ? '<span class="text-muted">-</span>' : number_format($f, 0, ',', '.');
};
?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-file-list-3-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Stok Opname Bulanan Divisi'); ?></h4>
      <small class="text-muted">Snapshot stok divisi hasil generate opname bulanan. Data mencerminkan posisi stok akhir bulan per divisi dan tipe destinasi.</small>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-danger" id="btn-generate-opname">
        <i class="ri ri-refresh-line me-1"></i>Generate Opname + Stok Awal
      </button>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'opname_monthly']); ?>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('inventory/stock/opname/division/monthly'); ?>" class="row g-2 align-items-end">
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
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama item / divisi">
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
    </form>
  </div>
</div>

<?php if (empty($rows)): ?>
  <div class="alert alert-info">
    Belum ada data opname bulanan divisi untuk bulan <?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>.
    Klik <strong>Generate Opname + Stok Awal</strong> untuk membuat snapshot dari data stok bulan ini.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle small">
      <thead class="table-light">
        <tr>
          <th>Divisi</th>
          <th>Destinasi</th>
          <th>Nama Item</th>
          <th>UOM (Isi)</th>
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
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
            <td><span class="badge bg-secondary-subtle text-secondary"><?php echo html_escape((string)($row['destination_type'] ?? '-')); ?></span></td>
            <td>
              <div class="fw-semibold"><?php echo html_escape((string)($row['profile_name'] ?? '-')); ?></div>
              <?php if (!empty($row['profile_brand'])): ?>
                <div class="text-muted" style="font-size:.72rem"><?php echo html_escape((string)$row['profile_brand']); ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo html_escape((string)($row['profile_content_uom_code'] ?? '-')); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['opening_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['in_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['out_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['waste_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['spoil_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['adjustment_plus_qty_content'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['adjustment_minus_qty_content'] ?? $row['variance_qty_content'] ?? 0); ?></td>
            <td class="text-end fw-semibold"><?php echo $fmtQty($row['closing_qty_content'] ?? 0); ?></td>
            <td class="text-end text-muted"><?php echo $fmtCost($row['avg_cost_per_content'] ?? 0); ?></td>
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
    const divisionId = document.querySelector('select[name="division_id"]')?.value || '';
    if (!month) { alert('Pilih bulan terlebih dahulu.'); return; }
    if (!confirm('Generate opname divisi bulan ' + month + ' dan carry-forward opening bulan berikutnya?')) return;
    btn.disabled = true;
    btn.textContent = 'Generating...';
    try {
      const res = await fetch(generateUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ stock_scope: 'DIVISION', month, division_id: divisionId })
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
