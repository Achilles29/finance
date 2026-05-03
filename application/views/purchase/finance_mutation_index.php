<?php
$storeUrl = site_url('finance/mutations/store');
$baseUrl = site_url('finance/mutations');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-exchange-funds-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">CRUD rekening tetap di master account. Halaman ini untuk mutasi saldo + log audit.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('finance/accounts'); ?>" class="btn btn-outline-primary">Master Rekening</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
  </div>
</div>

<div id="alert-area"></div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Input Mutasi Rekening</h6>
        <form id="mutation-form" class="row g-2" autocomplete="off">
          <div class="col-12">
            <label class="form-label">Rekening</label>
            <select class="form-select" id="account_id" required>
              <option value="">Pilih rekening...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' - ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 2, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Tipe</label>
            <select class="form-select" id="mutation_type" required>
              <option value="IN">IN</option>
              <option value="OUT">OUT</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Tanggal</label>
            <input type="date" class="form-control" id="mutation_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Jumlah</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="amount" required>
          </div>
          <div class="col-12">
            <label class="form-label">Reference No</label>
            <input type="text" class="form-control" id="reference_no" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="notes" rows="2" placeholder="Opsional"></textarea>
          </div>
          <div class="col-12 d-grid">
            <button type="button" class="btn btn-primary" id="btn-save-mutation">Simpan Mutasi</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Total IN</small>
          <h5 class="mb-0 text-success">Rp <?php echo number_format((float)($summary['in_total'] ?? 0), 2, ',', '.'); ?></h5>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Total OUT</small>
          <h5 class="mb-0 text-danger">Rp <?php echo number_format((float)($summary['out_total'] ?? 0), 2, ',', '.'); ?></h5>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted d-block mb-1">Jumlah Log</small>
          <h5 class="mb-0"><?php echo number_format((int)($summary['rows_total'] ?? 0)); ?></h5>
        </div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body py-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label mb-1">Filter Rekening</label>
            <select name="account_id" class="form-select">
              <option value="">Semua rekening</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)$filter_account_id === (int)$a['id']) ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$a['account_code'] . ' - ' . (string)$a['account_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Dari</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)$date_from); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Sampai</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)$date_to); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" name="limit" min="1" max="1000" class="form-control" value="<?php echo (int)$limit; ?>">
          </div>
          <div class="col-md-2 d-grid">
            <button class="btn btn-outline-primary" type="submit">Filter</button>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>No Mutasi</th>
              <th>Rekening</th>
              <th>Tipe</th>
              <th class="text-end">Amount</th>
              <th class="text-end">Before</th>
              <th class="text-end">After</th>
              <th>Ref</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data mutasi.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo html_escape((string)$r['mutation_date']); ?></td>
                  <td><?php echo html_escape((string)$r['mutation_no']); ?></td>
                  <td><?php echo html_escape(trim((string)($r['account_code'] ?? '') . ' - ' . (string)($r['account_name'] ?? ''))); ?></td>
                  <td>
                    <?php if ((string)$r['mutation_type'] === 'IN'): ?>
                      <span class="badge bg-label-success">IN</span>
                    <?php else: ?>
                      <span class="badge bg-label-danger">OUT</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end"><?php echo number_format((float)$r['amount'], 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['balance_before'], 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)$r['balance_after'], 2, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)($r['ref_no'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
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

  document.getElementById('btn-save-mutation').addEventListener('click', function () {
    var payload = {
      account_id: Number(document.getElementById('account_id').value || 0),
      mutation_type: document.getElementById('mutation_type').value,
      mutation_date: document.getElementById('mutation_date').value,
      amount: Number(document.getElementById('amount').value || 0),
      reference_no: document.getElementById('reference_no').value || null,
      notes: document.getElementById('notes').value || null
    };

    if (!payload.account_id || !payload.mutation_type || !payload.mutation_date || payload.amount <= 0) {
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
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan mutasi.');
      }
      showAlert('success', 'Mutasi berhasil disimpan. Halaman akan dimuat ulang.');
      window.setTimeout(function () { window.location.reload(); }, 600);
    })
    .catch(function (err) {
      showAlert('danger', err.message || 'Gagal simpan mutasi.');
    });
  });
})();
</script>
