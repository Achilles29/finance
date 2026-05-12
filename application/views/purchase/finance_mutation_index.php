<?php
$storeUrl = site_url('finance/mutations/store');
$baseUrl = site_url('finance/mutations');
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];

$buildQuery = static function ($overrides = []) use ($filter_account_id, $date_from, $date_to, $pg) {
  $base = [
    'account_id' => $filter_account_id ?? '',
    'date_from' => $date_from ?? '',
    'date_to' => $date_to ?? '',
    'per_page' => $pg['per_page'] ?? 25,
    'page' => $pg['page'] ?? 1,
  ];
  return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
  if ($totalPages <= 7) {
    return range(1, $totalPages);
  }
  $items = [1];
  $start = max(2, $page - 1);
  $end = min($totalPages - 1, $page + 1);
  if ($start > 2) {
    $items[] = '...';
  }
  for ($i = $start; $i <= $end; $i++) {
    $items[] = $i;
  }
  if ($end < $totalPages - 1) {
    $items[] = '...';
  }
  $items[] = $totalPages;
  return $items;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1 fw-bold"><i class="ri ri-exchange-funds-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">CRUD rekening tetap di master account. Halaman ini untuk mutasi saldo + log audit.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('finance/accounts'); ?>" class="btn btn-outline-primary">Master Rekening</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
  </div>
</div>

<div id="alert-area"></div>
<style>
  .mutation-log-table .col-date,
  .mutation-log-table .col-no,
  .mutation-log-table .col-type,
  .mutation-log-table .col-amount,
  .mutation-log-table .col-balance,
  .mutation-log-table .col-ref {
    white-space: nowrap;
  }
  .mutation-log-table .col-notes {
    min-width: 220px;
    max-width: 360px;
    white-space: normal;
    word-break: break-word;
  }
</style>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3 fw-bold">Input Mutasi Rekening</h6>
        <form id="mutation-form" class="row g-2" autocomplete="off">
          <div class="col-12">
            <label class="form-label">Jenis Mutasi</label>
            <select class="form-select" id="mutation_type" required>
              <option value="IN">IN (Dana Masuk)</option>
              <option value="OUT">OUT (Dana Keluar)</option>
              <option value="TRANSFER">TRANSFER (Antar Rekening)</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" id="account_id_label">Rekening</label>
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
          <div class="col-12 d-none" id="to_account_wrap">
            <label class="form-label">Rekening Tujuan</label>
            <select class="form-select" id="to_account_id">
              <option value="">Pilih rekening tujuan...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' - ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 2, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
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
          <div class="col-md-3">
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
          <div class="col-md-1">
            <label class="form-label mb-1">Per</label>
            <select name="per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100, 200] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page'] === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary" type="submit">Filter</button>
            <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 mutation-log-table">
          <thead>
            <tr>
              <th class="col-date">Tanggal</th>
              <th class="col-no">No Mutasi</th>
              <th>Rekening</th>
              <th class="col-type">Tipe</th>
              <th class="text-end col-amount">Amount</th>
              <th class="text-end col-balance">Before</th>
              <th class="text-end col-balance">After</th>
              <th class="col-ref">Ref</th>
              <th class="col-notes">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data mutasi.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="col-date"><?php echo html_escape((string)$r['mutation_date']); ?></td>
                  <td class="col-no"><?php echo html_escape((string)$r['mutation_no']); ?></td>
                  <td><?php echo html_escape(trim((string)($r['account_code'] ?? '') . ' - ' . (string)($r['account_name'] ?? ''))); ?></td>
                  <td class="col-type">
                    <?php if ((string)$r['mutation_type'] === 'IN'): ?>
                      <span class="badge bg-label-success">IN</span>
                    <?php else: ?>
                      <span class="badge bg-label-danger">OUT</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end col-amount"><?php echo number_format((float)$r['amount'], 2, ',', '.'); ?></td>
                  <td class="text-end col-balance"><?php echo number_format((float)$r['balance_before'], 2, ',', '.'); ?></td>
                  <td class="text-end col-balance"><?php echo number_format((float)$r['balance_after'], 2, ',', '.'); ?></td>
                  <td class="col-ref"><?php echo html_escape((string)($r['ref_no'] ?? '-')); ?></td>
                  <td class="col-notes"><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pg['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?> (Total <?php echo (int)$pg['total']; ?>)</small>
        <div class="btn-group">
          <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
          <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '...'): ?>
              <span class="btn btn-sm btn-outline-secondary disabled">...</span>
            <?php else: ?>
              <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('finance/mutations?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var storeUrl = <?php echo json_encode($storeUrl); ?>;
  var alertArea = document.getElementById('alert-area');
  var mutationTypeEl = document.getElementById('mutation_type');
  var toAccountWrapEl = document.getElementById('to_account_wrap');
  var accountIdLabelEl = document.getElementById('account_id_label');
  var toAccountIdEl = document.getElementById('to_account_id');

  function showAlert(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function syncMutationForm() {
    var type = mutationTypeEl.value || 'IN';
    var isTransfer = type === 'TRANSFER';
    toAccountWrapEl.classList.toggle('d-none', !isTransfer);
    toAccountIdEl.required = isTransfer;
    accountIdLabelEl.textContent = isTransfer ? 'Rekening Sumber' : 'Rekening';
  }

  mutationTypeEl.addEventListener('change', syncMutationForm);
  syncMutationForm();

  var submitBtn = document.getElementById('btn-save-mutation');
  var defaultBtnHtml = submitBtn.innerHTML;

  document.getElementById('btn-save-mutation').addEventListener('click', function () {
    var type = mutationTypeEl.value || 'IN';
    var payload = {
      account_id: Number(document.getElementById('account_id').value || 0),
      to_account_id: Number(toAccountIdEl.value || 0),
      mutation_type: type,
      mutation_date: document.getElementById('mutation_date').value,
      amount: Number(document.getElementById('amount').value || 0),
      reference_no: document.getElementById('reference_no').value || null,
      notes: document.getElementById('notes').value || null
    };

    if (!payload.account_id || !payload.mutation_type || !payload.mutation_date || payload.amount <= 0) {
      showAlert('warning', 'Field wajib belum lengkap.');
      return;
    }
    if (payload.mutation_type === 'TRANSFER' && (!payload.to_account_id || payload.to_account_id === payload.account_id)) {
      showAlert('warning', 'Rekening tujuan wajib dipilih dan harus berbeda dari rekening sumber.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';

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
      submitBtn.disabled = false;
      submitBtn.innerHTML = defaultBtnHtml;
    });
  });
})();
</script>
