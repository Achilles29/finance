<?php
$baseUrl = site_url('inventory/item-material-flow');
$storeUrl = site_url('inventory/item-material-flow/store');
$selectedMap = $selected_map ?? null;
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-exchange-funds-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posting konsumsi item menjadi material berdasarkan source divisi operasional.</small>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-6 mb-2">
        <label class="form-label mb-1">Cari Mapping</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / Material / Divisi Operasional">
      </div>
      <div class="col-md-4 mb-2">
        <label class="form-label mb-1">Pilih Mapping</label>
        <select name="map_id" class="form-select">
          <?php foreach ($maps as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$selected_map_id === (int)$m['id']) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)$m['source_division_name'] . ' | ' . $m['item_code'] . ' - ' . $m['item_name'] . ' -> ' . $m['material_code'] . ' - ' . $m['material_name'] . ' (x' . (float)$m['qty_material_per_item'] . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Terapkan</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="post" action="<?php echo $storeUrl; ?>" class="row g-3 align-items-end">
      <input type="hidden" name="map_id" value="<?php echo (int)$selected_map_id; ?>">

      <div class="col-md-3">
        <label class="form-label">Tanggal Transaksi</label>
        <input type="date" class="form-control" name="trx_date" value="<?php echo date('Y-m-d'); ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Qty Item</label>
        <input type="number" step="0.0001" min="0.0001" class="form-control" name="qty_item" id="qty_item" value="1" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Estimasi Qty Material</label>
        <input type="text" class="form-control" id="qty_material_preview" readonly value="0.00">
      </div>

      <div class="col-md-3 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">Simpan Transaksi</button>
      </div>

      <div class="col-12">
        <label class="form-label">Catatan</label>
        <input type="text" class="form-control" name="notes" placeholder="Opsional">
      </div>

      <?php if ($selectedMap): ?>
      <div class="col-12">
        <div class="alert alert-info mb-0 py-2">
          Mapping aktif: <strong><?php echo html_escape((string)$selectedMap['source_division_name']); ?></strong>
          | <?php echo html_escape((string)$selectedMap['item_name']); ?> -> <?php echo html_escape((string)$selectedMap['material_name']); ?>
          | faktor: <strong id="factor_text"><?php echo number_format((float)$selectedMap['qty_material_per_item'], 6, '.', ''); ?></strong>
        </div>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th width="70">No</th>
          <th>No Transaksi</th>
          <th>Tanggal</th>
          <th>Divisi Operasional</th>
          <th>Item</th>
          <th>Material</th>
          <th>Qty Item</th>
          <th>Qty Material</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent_txns)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">Belum ada transaksi flow item->material.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($recent_txns as $r): ?>
            <tr>
              <td class="number-cell"><?php echo $no++; ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['trx_no']); ?></td>
              <td><?php echo html_escape((string)$r['trx_date']); ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['source_division_name']); ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['item_name']); ?></td>
              <td class="text-cell"><?php echo html_escape((string)$r['material_name']); ?></td>
              <td class="number-cell" data-decimal="1" data-value="<?php echo (float)$r['qty_item']; ?>"><?php echo (float)$r['qty_item']; ?></td>
              <td class="number-cell" data-decimal="1" data-value="<?php echo (float)$r['qty_material']; ?>"><?php echo (float)$r['qty_material']; ?></td>
              <td class="text-cell"><?php echo html_escape((string)($r['notes'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var qtyInput = document.getElementById('qty_item');
  var preview = document.getElementById('qty_material_preview');
  var factorText = document.getElementById('factor_text');

  if (!qtyInput || !preview || !factorText) return;

  function recalc() {
    var qty = Number(qtyInput.value || 0);
    var factor = Number(factorText.textContent || 0);
    if (!Number.isFinite(qty) || !Number.isFinite(factor)) {
      preview.value = '0.00';
      return;
    }
    var result = qty * factor;
    preview.value = result.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  qtyInput.addEventListener('input', recalc);
  recalc();
})();
</script>
