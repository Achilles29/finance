<?php
$rows = is_array($rows ?? null) ? $rows : [];
$components = is_array($components ?? null) ? $components : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
?>
<div class="mb-3">
  <h4 class="mb-1">Batch Produksi Base/Prepare</h4>
  <small class="text-muted">Urutan aman: opening & adjustment dulu, baru post batch.</small>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form id="frmBatch">
      <div class="row g-2">
        <div class="col-md-2"><input type="date" class="form-control" name="batch_date" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="col-md-2">
          <select class="form-select" name="location_type" required>
            <?php foreach ($locationOptions as $k => $v): if ($k==='') continue; ?>
            <option value="<?php echo html_escape($k); ?>"><?php echo html_escape($v); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="division_id">
            <option value="">Divisi (opsional)</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="component_id" required>
            <option value="">Pilih Output Komponen</option>
            <?php foreach ($components as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo html_escape($c['component_code'].' - '.$c['component_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><input type="number" step="0.0001" class="form-control" name="output_qty" placeholder="Output Qty" required></div>
      </div>
      <div class="row g-2 mt-1">
        <div class="col-md-3">
          <select class="form-select" name="output_uom_id" required>
            <option value="">Pilih UOM Output</option>
            <?php foreach ($uoms as $u): ?>
              <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape($u['code'].' - '.$u['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-9"><input type="text" class="form-control" name="notes" placeholder="Catatan header"></div>
      </div>
      <div class="mt-2">
        <label class="form-label mb-1">Input Lines JSON</label>
        <textarea class="form-control font-monospace" name="lines_json" rows="5">[{"source_kind":"COMPONENT","component_id":1,"uom_id":1,"qty":1,"unit_cost":0}]</textarea>
      </div>
      <div class="mt-2"><button type="submit" class="btn btn-primary btn-sm">Simpan DRAFT</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>No</th><th>Tanggal</th><th>Output</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="text-center text-muted">Belum ada batch.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?php echo html_escape($r['batch_no']); ?></td>
          <td><?php echo html_escape($r['batch_date']); ?></td>
          <td><?php echo html_escape(($r['component_code'] ?? '').' '.($r['component_name'] ?? '')); ?></td>
          <td><?php echo ui_status_badge((string)$r['status']); ?></td>
          <td class="d-flex gap-1">
            <?php if (strtoupper((string)$r['status']) === 'DRAFT'): ?>
              <button class="btn btn-success btn-sm btn-post" data-id="<?php echo (int)$r['id']; ?>">Post</button>
              <button class="btn btn-danger btn-sm btn-del" data-id="<?php echo (int)$r['id']; ?>">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(() => {
  const postJson = (url, payload) => fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)}).then(r=>r.json());
  const frm = document.getElementById('frmBatch');
  frm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(frm);
    let lines = [];
    try { lines = JSON.parse(String(fd.get('lines_json') || '[]')); } catch(err){ alert('JSON lines tidak valid'); return; }
    const payload = {
      batch_date: fd.get('batch_date'),
      location_type: fd.get('location_type'),
      division_id: fd.get('division_id'),
      component_id: fd.get('component_id'),
      output_qty: fd.get('output_qty'),
      output_uom_id: fd.get('output_uom_id'),
      notes: fd.get('notes'),
      lines
    };
    const res = await postJson('<?php echo site_url('production/component-batches/save'); ?>', payload);
    if (!res.ok) { alert(res.message || 'Gagal'); return; }
    location.reload();
  });
  document.querySelectorAll('.btn-post').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Post batch ini?')) return;
    const res = await postJson('<?php echo site_url('production/component-batches/post'); ?>/' + btn.dataset.id, {});
    if (!res.ok) { alert(res.message || 'Gagal post'); return; }
    location.reload();
  }));
  document.querySelectorAll('.btn-del').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Hapus draft ini?')) return;
    const res = await postJson('<?php echo site_url('production/component-batches/delete'); ?>/' + btn.dataset.id, {});
    if (!res.ok) { alert(res.message || 'Gagal delete'); return; }
    location.reload();
  }));
})();
</script>
