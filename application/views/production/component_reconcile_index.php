<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
$fmtQty = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};
$statusChip = static function (array $row): array {
    return !empty($row['is_match'])
        ? ['label' => 'Match', 'class' => 'ok']
        : ['label' => 'Mismatch', 'class' => 'bad'];
};
?>

<style>
  .component-reconcile-wrap {
    overflow: auto;
    border: 1px solid #e8d2c3;
    border-radius: 18px;
    background: linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 18px 36px -30px rgba(95, 53, 39, .45);
  }
  .component-reconcile-table {
    min-width: 1450px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .component-reconcile-table thead th {
    position: sticky;
    top: 0;
    z-index: 4;
    background: linear-gradient(180deg, #7c1f2d 0%, #9f2f3e 100%);
    color: #fff8f5;
    white-space: nowrap;
  }
  .component-reconcile-table td,
  .component-reconcile-table th {
    vertical-align: top;
    font-size: .82rem;
    border-right: 1px solid #efddd2;
    border-bottom: 1px solid #f3e4da;
  }
  .component-reconcile-table tbody td {
    background: #fff;
  }
  .component-reconcile-table tbody tr:nth-child(even) td {
    background: #fffaf6;
  }
  .component-reconcile-chip {
    display: inline-flex;
    align-items: center;
    padding: .24rem .62rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 800;
  }
  .component-reconcile-chip.ok { background:#e8f8ee; color:#1f7a49; }
  .component-reconcile-chip.bad { background:#fde9e8; color:#b42318; }
  .component-reconcile-filter {
    display:grid;
    grid-template-columns:140px minmax(0,1fr) 170px 170px 150px auto;
    gap:.75rem;
    align-items:end;
  }
  .component-reconcile-kpis {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.8rem;
  }
  .component-reconcile-kpi {
    border:1px solid #ead7cc;
    border-radius:18px;
    background:linear-gradient(180deg,#fff8f4 0%,#fff 100%);
    padding:.9rem 1rem;
  }
  .component-reconcile-kpi .label {
    display:block;
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a5b4d;
    font-weight:800;
    margin-bottom:.22rem;
  }
  .component-reconcile-kpi .value {
    font-size:1.25rem;
    font-weight:900;
    color:#45261d;
  }
  .component-reconcile-audit {
    border:1px solid #ead7cc;
    border-radius:18px;
    background:linear-gradient(180deg,#fffaf6 0%,#fff 100%);
    padding:1rem 1.05rem;
  }
  .component-reconcile-audit-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.75rem;
  }
  .component-reconcile-audit-box {
    border:1px solid #efdfd3;
    border-radius:14px;
    background:#fff;
    padding:.75rem .85rem;
  }
  .component-reconcile-audit-box .label {
    display:block;
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a5b4d;
    font-weight:800;
  }
  .component-reconcile-audit-box .value {
    display:block;
    font-size:1.05rem;
    font-weight:900;
    color:#45261d;
    margin-top:.18rem;
  }
  @media (max-width: 991.98px) {
    .component-reconcile-filter { grid-template-columns:1fr 1fr; }
    .component-reconcile-kpis { grid-template-columns:1fr; }
    .component-reconcile-audit-summary { grid-template-columns:1fr 1fr; }
  }
</style>

<div class="mb-3">
  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'reconcile']); ?>
</div>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-reconcile'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'month'         => (string)($filters['month'] ?? ''),
    'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
    'location_type' => (string)($filters['location_type'] ?? ''),
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h3 class="mb-1">Rekonsiliasi Base/Prepare</h3>
    <div class="text-muted small">Bandingkan saldo live component, closing proyeksi harian hingga tanggal acuan, dan closing hasil movement log.</div>
  </div>
  <div class="text-muted small">Tanggal acuan: <strong><?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?></strong></div>
</div>

<div class="card mb-3" style="border-radius:20px;border-color:#ead7cc;box-shadow:0 16px 34px rgba(58,38,30,.06);">
  <div class="card-body p-3 p-lg-4">
    <form method="get" class="component-reconcile-filter">
      <div>
        <label class="form-label small text-muted mb-1">Tanggal</label>
        <input type="date" name="as_of_date" value="<?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?>" class="form-control">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Cari Component</label>
        <input type="text" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" class="form-control" placeholder="Kode / nama component">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationOptions as $value => $label): ?>
            <option value="<?php echo html_escape((string)$value); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$value) ? 'selected' : ''; ?>><?php echo html_escape((string)$label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua divisi</option>
          <?php foreach ($divisions as $division): ?>
            <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($division['division_name'] ?? $division['division_code'] ?? ('Divisi #' . (int)($division['id'] ?? 0)))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Baris</label>
        <input type="number" min="1" max="500" name="limit" value="<?php echo (int)($filters['limit'] ?? 50); ?>" class="form-control">
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="<?php echo html_escape(site_url('production/component-reconcile')); ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="component-reconcile-kpis mb-3">
  <div class="component-reconcile-kpi"><span class="label">Total Baris</span><div class="value"><?php echo number_format((int)($summary['total'] ?? 0)); ?></div></div>
  <div class="component-reconcile-kpi"><span class="label">Match</span><div class="value"><?php echo number_format((int)($summary['matched'] ?? 0)); ?></div></div>
  <div class="component-reconcile-kpi"><span class="label">Mismatch</span><div class="value"><?php echo number_format((int)($summary['mismatched'] ?? 0)); ?></div></div>
</div>

<div class="component-reconcile-wrap mb-3">
  <table class="table table-hover align-middle component-reconcile-table">
    <thead>
      <tr>
        <th style="min-width:240px;">Component</th>
        <th>Lokasi</th>
        <th class="text-end">Saldo Live</th>
        <th class="text-end">Proyeksi Harian</th>
        <th class="text-end">Movement</th>
        <th class="text-end">Live vs Daily</th>
        <th class="text-end">Live vs Movement</th>
        <th class="text-end">Daily vs Movement</th>
        <th>Status</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data reconcile component.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php $chip = $statusChip($row); ?>
          <tr>
            <td>
              <div class="fw-bold"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
              <div class="text-muted small"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?> | <?php echo html_escape((string)($row['component_type'] ?? '-')); ?> | <?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></div>
            </td>
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><div class="text-muted small"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div></td>
            <td class="text-end"><?php echo $fmtQty($row['balance_qty'] ?? 0); ?></td>
            <td class="text-end"><?php echo $fmtQty($row['daily_qty'] ?? 0); ?><div class="text-muted small"><?php echo html_escape((string)($row['daily_date'] ?? '-')); ?></div></td>
            <td class="text-end"><?php echo $fmtQty($row['movement_qty'] ?? 0); ?><div class="text-muted small"><?php echo html_escape((string)($row['movement_no'] ?? '-')); ?></div></td>
            <td class="text-end <?php echo abs((float)($row['delta_balance_daily'] ?? 0)) < 0.0001 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo $fmtQty($row['delta_balance_daily'] ?? 0); ?></td>
            <td class="text-end <?php echo abs((float)($row['delta_balance_movement'] ?? 0)) < 0.0001 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo $fmtQty($row['delta_balance_movement'] ?? 0); ?></td>
            <td class="text-end <?php echo abs((float)($row['delta_daily_movement'] ?? 0)) < 0.0001 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo $fmtQty($row['delta_daily_movement'] ?? 0); ?></td>
            <td><span class="component-reconcile-chip <?php echo html_escape($chip['class']); ?>"><?php echo html_escape($chip['label']); ?></span></td>
            <td>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary comp-reconcile-audit-btn" data-as-of-date="<?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?>" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>">Audit</button>
                <button type="button" class="btn btn-sm btn-outline-danger comp-reconcile-repair-btn" data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>" data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>" data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>" data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>">Repair</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" style="border-radius:20px;border-color:#ead7cc;box-shadow:0 16px 34px rgba(58,38,30,.06);">
  <div class="card-body p-3 p-lg-4">
    <div class="component-reconcile-audit">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Audit Component</div>
          <h5 class="mb-1 mt-2">Telusuri Sumber Selisih</h5>
          <div class="text-muted small">Audit menampilkan bucket OPENING, PRODUCTION, TRANSFER, VOID, REFUND, ADJUSTMENT, POS, serta detail movement-nya.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger d-none" id="comp_reconcile_repair_current">Repair Component Ini</button>
      </div>
      <div id="comp_reconcile_state" class="text-muted small">Belum ada component yang dipilih.</div>
      <div id="comp_reconcile_body" class="d-none">
        <div id="comp_reconcile_summary" class="component-reconcile-audit-summary mb-3"></div>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Bucket</th>
                <th class="text-end">Jumlah Log</th>
                <th class="text-end">Delta Qty</th>
                <th class="text-end">Nilai Mutasi</th>
                <th>Log Terakhir</th>
              </tr>
            </thead>
            <tbody id="comp_reconcile_buckets"></tbody>
          </table>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>No</th>
                <th>Sumber</th>
                <th class="text-end">Before</th>
                <th class="text-end">Delta</th>
                <th class="text-end">After</th>
                <th>Jenis</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody id="comp_reconcile_movements"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const auditState = document.getElementById('comp_reconcile_state');
  const auditBody = document.getElementById('comp_reconcile_body');
  const summaryEl = document.getElementById('comp_reconcile_summary');
  const bucketEl = document.getElementById('comp_reconcile_buckets');
  const movementEl = document.getElementById('comp_reconcile_movements');
  const repairCurrentBtn = document.getElementById('comp_reconcile_repair_current');
  let currentIdentity = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
  function fmtQty(v) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(Number(v || 0));
  }
  function showAlert(message, title) {
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return Promise.resolve(window.FinanceUI.alert(message, { title: title || 'Informasi' }));
    }
    alert(message);
    return Promise.resolve();
  }
  function askConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return Promise.resolve(window.FinanceUI.confirm(message, options || {}));
    }
    return Promise.resolve(window.confirm(message));
  }
  async function getJson(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memuat data.');
    return json;
  }
  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload || {})
    });
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses data.');
    return json;
  }
  function setState(message, loading) {
    auditState.textContent = message;
    auditState.classList.remove('d-none');
    auditBody.classList.add('d-none');
    repairCurrentBtn.classList.toggle('d-none', !currentIdentity || !!loading);
    repairCurrentBtn.disabled = !!loading || !currentIdentity;
  }
  function setButtonLoading(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + escapeHtml(label || 'Memproses...');
  }
  function clearButtonLoading(button) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }
  function identityFromButton(button) {
    return {
      as_of_date: String(button?.dataset.asOfDate || ''),
      location_type: String(button?.dataset.locationType || ''),
      division_id: Number(button?.dataset.divisionId || 0),
      component_id: Number(button?.dataset.componentId || 0),
      uom_id: Number(button?.dataset.uomId || 0)
    };
  }
  function repairIdentityFromButton(button) {
    return {
      location_type: String(button?.dataset.locationType || ''),
      division_id: Number(button?.dataset.divisionId || 0),
      component_id: Number(button?.dataset.componentId || 0),
      uom_id: Number(button?.dataset.uomId || 0)
    };
  }
  function renderAudit(identity, json) {
    currentIdentity = {
      location_type: String(identity.location_type || ''),
      division_id: Number(identity.division_id || 0),
      component_id: Number(identity.component_id || 0),
      uom_id: Number(identity.uom_id || 0)
    };
    auditState.classList.add('d-none');
    auditBody.classList.remove('d-none');
    repairCurrentBtn.classList.remove('d-none');
    repairCurrentBtn.disabled = false;
    const summary = json.summary || {};
    const buckets = Array.isArray(json.buckets) ? json.buckets : [];
    const movements = Array.isArray(json.movements) ? json.movements : [];
    summaryEl.innerHTML = [
      ['Component', escapeHtml(summary.component_name || '-'), escapeHtml(summary.component_code || '-')],
      ['Verdict', escapeHtml(summary.suspect_table || 'MATCH'), escapeHtml(summary.suspect_reason || 'Semua tabel masih sinkron.')],
      ['Live vs Movement', fmtQty(summary.delta_balance_movement || 0), 'selisih qty'],
      ['Daily vs Movement', fmtQty(summary.delta_daily_movement || 0), `closing daily ${escapeHtml(summary.daily_date || '-')}`],
      ['Lokasi', escapeHtml(summary.location_type || '-'), escapeHtml(summary.division_name || '-')]
    ].map((item) => `<div class="component-reconcile-audit-box"><span class="label">${item[0]}</span><span class="value">${item[1]}</span><div class="text-muted small">${item[2]}</div></div>`).join('');
    bucketEl.innerHTML = buckets.map((bucket) => `
      <tr>
        <td>${escapeHtml(bucket.bucket_label || bucket.bucket_code || '-')}</td>
        <td class="text-end">${Number(bucket.count || 0)}</td>
        <td class="text-end">${fmtQty(bucket.delta_qty || 0)}</td>
        <td class="text-end">${fmtQty(bucket.mutation_value || 0)}</td>
        <td>${escapeHtml(bucket.last_movement_date || '-')}<div class="text-muted small">${escapeHtml(bucket.last_movement_no || '-')}</div></td>
      </tr>
    `).join('') || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada bucket.</td></tr>';
    movementEl.innerHTML = movements.map((row) => `
      <tr>
        <td>${escapeHtml(row.movement_date || '-')}</td>
        <td>${escapeHtml(row.movement_no || '-')}</td>
        <td>${escapeHtml(row.source_label || '-')}<div class="text-muted small">${escapeHtml(row.source_bucket_label || '-')}</div></td>
        <td class="text-end">${fmtQty(row.qty_before || 0)}</td>
        <td class="text-end">${fmtQty(row.qty_delta || 0)}</td>
        <td class="text-end">${fmtQty(row.qty_after || 0)}</td>
        <td>${escapeHtml(row.movement_type_label || row.movement_type || '-')}</td>
        <td>${escapeHtml(row.notes || '-')}</td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada movement.</td></tr>';
  }
  async function loadAudit(identity) {
    currentIdentity = {
      location_type: String(identity.location_type || ''),
      division_id: Number(identity.division_id || 0),
      component_id: Number(identity.component_id || 0),
      uom_id: Number(identity.uom_id || 0)
    };
    setState('Memuat audit component...', true);
    const params = new URLSearchParams();
    Object.keys(identity || {}).forEach((key) => params.set(key, String(identity[key] ?? '')));
    const json = await getJson('<?php echo site_url('production/component-reconcile/audit'); ?>?' + params.toString());
    renderAudit(identity, json);
  }
  async function runRepair(identity, button) {
    const confirmed = await askConfirm('Repair component ini akan rebuild artefak kompatibilitas dan sinkronkan ulang dari movement log untuk identity yang dipilih. Lanjutkan?', {
      title: 'Repair Reconcile Component',
      confirmText: 'Repair',
      cancelText: 'Batal'
    });
    if (!confirmed) return;
    setButtonLoading(button, 'Repair...');
    setState('Menjalankan repair component...', true);
    try {
      const json = await postJson('<?php echo site_url('production/component-reconcile/repair'); ?>', identity);
      await showAlert(json.message || 'Repair selesai dijalankan.', 'Repair Reconcile Component');
      window.location.reload();
    } catch (error) {
      clearButtonLoading(button);
      throw error;
    }
  }

  if (repairCurrentBtn) {
    repairCurrentBtn.addEventListener('click', function () {
      if (!currentIdentity) return;
      runRepair(currentIdentity, repairCurrentBtn).catch((e) => {
        clearButtonLoading(repairCurrentBtn);
        setState(e.message, false);
      });
    });
  }
  document.addEventListener('click', function (event) {
    const auditBtn = event.target.closest('.comp-reconcile-audit-btn');
    if (auditBtn) {
      loadAudit(identityFromButton(auditBtn)).catch((e) => setState(e.message, false));
      return;
    }
    const repairBtn = event.target.closest('.comp-reconcile-repair-btn');
    if (repairBtn) {
      runRepair(repairIdentityFromButton(repairBtn), repairBtn).catch((e) => {
        clearButtonLoading(repairBtn);
        setState(e.message, false);
      });
    }
  });
});
</script>