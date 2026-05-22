<?php
$rows = is_array($rows ?? null) ? $rows : [];
$components = is_array($components ?? null) ? $components : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
?>
<div class="mb-3">
  <h4 class="mb-1">Opening Base/Prepare</h4>
  <small class="text-muted">Simpan sebagai DRAFT lalu POST untuk update stok+ledger.</small>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form id="frmOpening">
      <div class="row g-2">
        <div class="col-md-2"><input type="date" class="form-control" name="opening_date" value="<?php echo date('Y-m-d'); ?>" required></div>
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
        <div class="col-md-5"><input type="text" class="form-control" name="notes" placeholder="Catatan header"></div>
      </div>
      <div class="mt-2">
        <label class="form-label mb-1">Lines JSON</label>
        <textarea class="form-control font-monospace" name="lines_json" rows="5">[{"component_id":1,"uom_id":1,"opening_qty":1,"unit_cost":0}]</textarea>
      </div>
      <div class="mt-2"><button type="submit" class="btn btn-primary btn-sm">Simpan DRAFT</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>No</th><th>Tanggal</th><th>Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="text-center text-muted">Belum ada dokumen.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?php echo html_escape($r['opening_no']); ?></td>
          <td><?php echo html_escape($r['opening_date']); ?></td>
          <td><?php echo html_escape($r['location_type']); ?></td>
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
  const frm = document.getElementById('frmOpening');
  frm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(frm);
    let lines = [];
    try { lines = JSON.parse(String(fd.get('lines_json') || '[]')); } catch(err){ alert('JSON lines tidak valid'); return; }
    const payload = {
      opening_date: fd.get('opening_date'),
      location_type: fd.get('location_type'),
      division_id: fd.get('division_id'),
      notes: fd.get('notes'),
      lines
    };
    const res = await postJson('<?php echo site_url('production/component-openings/save'); ?>', payload);
    if (!res.ok) { alert(res.message || 'Gagal'); return; }
    location.reload();
  });
  document.querySelectorAll('.btn-post').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Post opening ini?')) return;
    const res = await postJson('<?php echo site_url('production/component-openings/post'); ?>/' + btn.dataset.id, {});
    if (!res.ok) { alert(res.message || 'Gagal post'); return; }
    location.reload();
  }));
  document.querySelectorAll('.btn-del').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Hapus draft ini?')) return;
    const res = await postJson('<?php echo site_url('production/component-openings/delete'); ?>/' + btn.dataset.id, {});
    if (!res.ok) { alert(res.message || 'Gagal delete'); return; }
    location.reload();
  }));
})();
</script>
