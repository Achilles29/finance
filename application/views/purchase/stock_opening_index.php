<?php
$baseUrl = site_url('purchase/stock/opening');
$storeUrl = site_url('purchase/stock/opening/store');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-archive-stack-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Input opening gudang per profile item. Bisa replace saldo live (disarankan) atau add delta.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang Live</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/daily'); ?>" class="btn btn-outline-primary">Stok Bulanan/Daily</a>
  </div>
</div>

<div id="alert-area"></div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Form Opening Gudang</h6>
        <form id="opening-form" class="row g-2" autocomplete="off">
          <div class="col-6">
            <label class="form-label">Snapshot Month</label>
            <input type="month" class="form-control" id="snapshot_month" value="<?php echo date('Y-m'); ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Movement Date</label>
            <input type="date" class="form-control" id="movement_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Item</label>
            <select class="form-select" id="item_id" required>
              <option value="">Pilih item...</option>
              <?php foreach (($items ?? []) as $i): ?>
                <option value="<?php echo (int)$i['id']; ?>"><?php echo html_escape((string)$i['item_code'] . ' - ' . (string)$i['item_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">UOM Beli</label>
            <select class="form-select" id="buy_uom_id" required>
              <option value="">Pilih...</option>
              <?php foreach (($uoms ?? []) as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'] . ' - ' . (string)$u['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">UOM Isi</label>
            <select class="form-select" id="content_uom_id" required>
              <option value="">Pilih...</option>
              <?php foreach (($uoms ?? []) as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['code'] . ' - ' . (string)$u['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label">Qty Beli</label>
            <input type="number" class="form-control" id="opening_qty_buy" min="0" step="0.0001" value="0">
          </div>
          <div class="col-4">
            <label class="form-label">Qty Isi</label>
            <input type="number" class="form-control" id="opening_qty_content" min="0" step="0.0001" value="0" required>
          </div>
          <div class="col-4">
            <label class="form-label">Avg Cost/Isi</label>
            <input type="number" class="form-control" id="opening_avg_cost_per_content" min="0" step="0.000001" value="0">
          </div>
          <div class="col-12">
            <label class="form-label">Profile Name</label>
            <input type="text" class="form-control" id="profile_name" placeholder="Contoh: AIR MINERAL BOTOL">
          </div>
          <div class="col-6">
            <label class="form-label">Merk</label>
            <input type="text" class="form-control" id="profile_brand" placeholder="Contoh: CLEO">
          </div>
          <div class="col-6">
            <label class="form-label">Isi/Beli</label>
            <input type="number" class="form-control" id="profile_content_per_buy" min="0.000001" step="0.000001" value="1">
          </div>
          <div class="col-12">
            <label class="form-label">Keterangan</label>
            <input type="text" class="form-control" id="profile_description" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label">Mode Posting</label>
            <select class="form-select" id="replace_mode">
              <option value="1">Replace saldo live (target = opening)</option>
              <option value="0">Add delta (ditambah ke saldo live)</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="notes" rows="2"></textarea>
          </div>
          <div class="col-12 d-grid">
            <button type="button" class="btn btn-primary" id="btn-save-opening">Simpan Opening</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label mb-1">Bulan</label>
            <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label mb-1">Cari</label>
            <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Item / profile / merk / keterangan">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" class="form-control" name="limit" min="1" max="500" value="<?php echo (int)$limit; ?>">
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
              <th>Bulan</th>
              <th>Item</th>
              <th>Profile</th>
              <th class="text-end">Qty Beli</th>
              <th class="text-end">Qty Isi</th>
              <th class="text-end">Avg Cost</th>
              <th class="text-end">Total Value</th>
              <th>Sumber</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada opening gudang.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo html_escape((string)$r['snapshot_month']); ?></td>
                  <td><?php echo html_escape(trim((string)($r['item_code'] ?? '') . ' - ' . (string)($r['item_name'] ?? ''))); ?></td>
                  <td>
                    <?php echo html_escape((string)($r['profile_name'] ?? '-')); ?><br>
                    <small class="text-muted"><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?> | <?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></small>
                  </td>
                  <td class="text-end"><?php echo number_format((float)$r['opening_qty_buy'], 4, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['opening_qty_content'], 4, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['opening_avg_cost_per_content'], 6, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['opening_total_value'], 2, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)($r['source_type'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var storeUrl = <?php echo json_encode($storeUrl); ?>;
  var alertArea = document.getElementById('alert-area');

  function showAlert(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  document.getElementById('btn-save-opening').addEventListener('click', function () {
    var payload = {
      snapshot_month: document.getElementById('snapshot_month').value,
      movement_date: document.getElementById('movement_date').value,
      item_id: Number(document.getElementById('item_id').value || 0),
      buy_uom_id: Number(document.getElementById('buy_uom_id').value || 0),
      content_uom_id: Number(document.getElementById('content_uom_id').value || 0),
      opening_qty_buy: Number(document.getElementById('opening_qty_buy').value || 0),
      opening_qty_content: Number(document.getElementById('opening_qty_content').value || 0),
      opening_avg_cost_per_content: Number(document.getElementById('opening_avg_cost_per_content').value || 0),
      profile_name: document.getElementById('profile_name').value || null,
      profile_brand: document.getElementById('profile_brand').value || null,
      profile_description: document.getElementById('profile_description').value || null,
      profile_content_per_buy: Number(document.getElementById('profile_content_per_buy').value || 1),
      replace_mode: Number(document.getElementById('replace_mode').value || 1),
      notes: document.getElementById('notes').value || null
    };

    if (!payload.item_id || !payload.buy_uom_id || !payload.content_uom_id || !payload.snapshot_month || !payload.movement_date) {
      showAlert('warning', 'Field wajib belum lengkap.');
      return;
    }

    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan opening.');
      }
      showAlert('success', 'Opening berhasil diposting. Halaman akan dimuat ulang.');
      window.setTimeout(function () { window.location.reload(); }, 700);
    })
    .catch(function (err) {
      showAlert('danger', err.message || 'Gagal simpan opening.');
    });
  });
})();
</script>
