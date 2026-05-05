<?php
$runUrl = site_url('purchase/rebuild-impact/run');
$statusOptions = is_array($status_options ?? null) ? $status_options : [];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1">Rebuild Impact Purchase</h4>
    <small class="text-muted">Resync dampak stok/finance purchase secara terstruktur: by transaksi, by item, by filter, atau global.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Kembali ke Purchase</a>
    <a href="<?php echo site_url('purchase-orders/logs'); ?>" class="btn btn-outline-secondary">Log Purchase</a>
  </div>
</div>

<div id="alert-area"></div>

<div class="card mb-3">
  <div class="card-body">
    <form id="rebuild-form" class="row g-3" autocomplete="off">
      <div class="col-md-3">
        <label class="form-label">Scope</label>
        <select class="form-select" id="scope" name="scope">
          <option value="TRANSACTION">By Transaksi</option>
          <option value="ITEM">By Item/Material</option>
          <option value="FILTER">By Filter (Tanggal/Status)</option>
          <option value="GLOBAL">Global</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">PO ID</label>
        <input type="number" class="form-control" id="purchase_order_id" min="1" placeholder="Contoh: 123">
      </div>
      <div class="col-md-3">
        <label class="form-label">PO No</label>
        <input type="text" class="form-control" id="po_no" placeholder="Contoh: PO202605040001">
      </div>
      <div class="col-md-2">
        <label class="form-label">Item ID</label>
        <input type="number" class="form-control" id="item_id" min="1" placeholder="Opsional">
      </div>
      <div class="col-md-2">
        <label class="form-label">Material ID</label>
        <input type="number" class="form-control" id="material_id" min="1" placeholder="Opsional">
      </div>

      <div class="col-md-2">
        <label class="form-label">Date From</label>
        <input type="date" class="form-control" id="date_from">
      </div>
      <div class="col-md-2">
        <label class="form-label">Date To</label>
        <input type="date" class="form-control" id="date_to">
      </div>
      <div class="col-md-2">
        <label class="form-label">Limit</label>
        <input type="number" class="form-control" id="limit" min="1" max="5000" value="300">
      </div>
      <div class="col-md-6">
        <label class="form-label d-block mb-2">Status Filter</label>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($statusOptions as $status): ?>
            <?php $code = strtoupper((string)$status); ?>
            <div class="form-check form-check-inline m-0">
              <input class="form-check-input rebuild-status" type="checkbox" value="<?php echo html_escape($code); ?>" id="st-<?php echo html_escape(strtolower($code)); ?>" <?php echo in_array($code, ['RECEIVED', 'PAID'], true) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="st-<?php echo html_escape(strtolower($code)); ?>"><?php echo html_escape($code); ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-12 d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-primary btn-run" data-dry-run="1">Dry Run</button>
        <button type="button" class="btn btn-warning btn-run" data-dry-run="0">Execute Rebuild</button>
        <span id="scope-help" class="text-muted small align-self-center"></span>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3" id="summary-card" style="display:none;">
  <div class="card-body">
    <h6 class="mb-3">Ringkasan Hasil</h6>
    <div class="row g-2" id="summary-grid"></div>
  </div>
</div>

<div class="card" id="result-card" style="display:none;">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>PO</th>
          <th>Status</th>
          <th>Request Date</th>
          <th>Result</th>
          <th>Receipt Effect</th>
          <th>Payment Effect</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody id="result-tbody"></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var runUrl = <?php echo json_encode($runUrl); ?>;
  var alertArea = document.getElementById('alert-area');
  var scopeInput = document.getElementById('scope');
  var scopeHelp = document.getElementById('scope-help');
  var summaryCard = document.getElementById('summary-card');
  var resultCard = document.getElementById('result-card');
  var summaryGrid = document.getElementById('summary-grid');
  var resultBody = document.getElementById('result-tbody');

  function esc(value) {
    var tmp = document.createElement('div');
    tmp.textContent = value == null ? '' : String(value);
    return tmp.innerHTML;
  }

  function setAlert(type, message) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + message
      + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
      + '</div>';
  }

  function getCheckedStatuses() {
    var checked = [];
    document.querySelectorAll('.rebuild-status:checked').forEach(function (el) {
      checked.push(String(el.value || '').toUpperCase());
    });
    return checked;
  }

  function getPayload(dryRun) {
    return {
      scope: String(scopeInput.value || 'GLOBAL').toUpperCase(),
      purchase_order_id: Number(document.getElementById('purchase_order_id').value || 0),
      po_no: (document.getElementById('po_no').value || '').trim(),
      item_id: Number(document.getElementById('item_id').value || 0),
      material_id: Number(document.getElementById('material_id').value || 0),
      date_from: document.getElementById('date_from').value || '',
      date_to: document.getElementById('date_to').value || '',
      statuses: getCheckedStatuses(),
      limit: Number(document.getElementById('limit').value || 300),
      dry_run: !!dryRun
    };
  }

  function validatePayload(payload) {
    if (payload.scope === 'TRANSACTION' && payload.purchase_order_id <= 0 && payload.po_no === '') {
      return 'Scope TRANSACTION membutuhkan PO ID atau PO No.';
    }
    if (payload.scope === 'ITEM' && payload.item_id <= 0 && payload.material_id <= 0) {
      return 'Scope ITEM membutuhkan Item ID atau Material ID.';
    }
    if (payload.date_from && payload.date_to && payload.date_from > payload.date_to) {
      return 'Date From tidak boleh lebih besar dari Date To.';
    }
    if (payload.limit <= 0 || payload.limit > 5000) {
      return 'Limit harus di antara 1 sampai 5000.';
    }
    return '';
  }

  function renderSummary(summary) {
    var data = summary || {};
    var keys = [
      ['total_candidates', 'Total Kandidat'],
      ['planned', 'Planned'],
      ['processed', 'Processed'],
      ['success', 'Success'],
      ['changed', 'Changed'],
      ['unchanged', 'Unchanged'],
      ['failed', 'Failed'],
      ['skipped', 'Skipped']
    ];

    var html = keys.map(function (it) {
      var key = it[0];
      var label = it[1];
      var value = Number(data[key] || 0);
      return '<div class="col-md-3 col-6"><div class="border rounded p-2 h-100">'
        + '<small class="text-muted d-block">' + esc(label) + '</small>'
        + '<div class="fw-semibold fs-5">' + esc(value) + '</div>'
        + '</div></div>';
    }).join('');

    summaryGrid.innerHTML = html;
    summaryCard.style.display = '';
  }

  function renderRows(rows) {
    var list = Array.isArray(rows) ? rows : [];
    if (!list.length) {
      resultBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada baris hasil.</td></tr>';
      resultCard.style.display = '';
      return;
    }

    resultBody.innerHTML = list.map(function (row) {
      var poText = (row.po_no || '') !== '' ? row.po_no : ('PO#' + String(row.purchase_order_id || '-'));
      return '<tr>'
        + '<td>' + esc(poText) + '</td>'
        + '<td>' + esc(row.status || '-') + '</td>'
        + '<td>' + esc(row.request_date || '-') + '</td>'
        + '<td><span class="badge bg-secondary">' + esc(row.result || '-') + '</span></td>'
        + '<td>' + esc(row.receipt_effect || '-') + '</td>'
        + '<td>' + esc(row.payment_effect || '-') + '</td>'
        + '<td>' + esc(row.message || '-') + '</td>'
        + '</tr>';
    }).join('');

    resultCard.style.display = '';
  }

  function refreshScopeHelp() {
    var scope = String(scopeInput.value || '').toUpperCase();
    var textMap = {
      TRANSACTION: 'By Transaksi: fokus 1 PO tertentu (PO ID atau PO No).',
      ITEM: 'By Item/Material: proses PO yang memiliki item/material terkait.',
      FILTER: 'By Filter: proses berdasarkan rentang tanggal dan/atau status.',
      GLOBAL: 'Global: proses massal (default status RECEIVED + PAID jika status filter kosong).'
    };
    scopeHelp.textContent = textMap[scope] || '';
  }

  document.querySelectorAll('.btn-run').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dryRun = String(btn.getAttribute('data-dry-run') || '1') === '1';
      var payload = getPayload(dryRun);
      var err = validatePayload(payload);
      if (err) {
        setAlert('warning', err);
        return;
      }

      if (!dryRun) {
        var ok = window.confirm('Jalankan EXECUTE rebuild? Proses ini bisa membuat posting ulang dampak pada PO eligible.');
        if (!ok) {
          return;
        }
      }

      btn.disabled = true;
      fetch(runUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      })
      .then(function (r) {
        return r.json().then(function (j) {
          return { status: r.status, json: j };
        });
      })
      .then(function (res) {
        if (res.status >= 400 || !res.json || !res.json.ok) {
          throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal menjalankan rebuild impact.');
        }

        setAlert('success', esc(res.json.message || 'Proses rebuild selesai.'));
        var data = res.json.data || {};
        renderSummary(data.summary || {});
        renderRows(data.rows || []);
      })
      .catch(function (error) {
        setAlert('danger', esc(error.message || 'Gagal menjalankan rebuild impact.'));
      })
      .finally(function () {
        btn.disabled = false;
      });
    });
  });

  scopeInput.addEventListener('change', refreshScopeHelp);
  refreshScopeHelp();
})();
</script>
