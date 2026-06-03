<?php
$baseUrl = site_url('inventory/stock/division/reconcile');
$auditUrl = site_url('inventory/stock/division/reconcile/audit');
$repairUrl = site_url('inventory/stock/division/reconcile/repair');
$rows = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$summary = is_array($summary ?? null) ? $summary : [];

$fmtQty = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};
$fmtText = static function ($value, string $fallback = '-'): string {
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
};
$statusChip = static function (array $row): array {
    if (!empty($row['is_match'])) {
        return ['label' => 'Match', 'class' => 'ok'];
    }
    return ['label' => 'Mismatch Material', 'class' => 'bad'];
};
?>

<style>
  .src-card {
    border:1px solid rgba(225,210,199,.82);
    border-radius:22px;
    background:#fff;
    box-shadow:0 16px 34px rgba(58,38,30,.06);
  }
  .src-filter-grid {
    display:grid;
    grid-template-columns:160px minmax(0, 1fr) 180px 130px auto;
    gap:.75rem;
    align-items:end;
  }
  .src-kpi-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.8rem;
  }
  .src-kpi {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    padding:.9rem 1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .src-kpi .label {
    display:block;
    font-size:.74rem;
    color:#8a776f;
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:.25rem;
    font-weight:800;
  }
  .src-kpi .value {
    font-size:1.3rem;
    font-weight:900;
    color:#2f2628;
  }
  .src-table-wrap { overflow:auto; }
  .src-table th {
    position:sticky;
    top:0;
    z-index:1;
    background:#fff8f4;
    white-space:nowrap;
  }
  .src-table td,
  .src-table th {
    vertical-align:top;
    font-size:.82rem;
  }
  .src-obj-name {
    font-weight:900;
    color:#2f2628;
    line-height:1.2;
  }
  .src-obj-sub {
    font-size:.7rem;
    color:#89766e;
    line-height:1.2;
    margin-top:.18rem;
  }
  .src-metric {
    min-width:110px;
    text-align:right;
    line-height:1.1;
  }
  .src-metric .primary {
    display:block;
    font-weight:900;
    color:#2f2628;
  }
  .src-metric .secondary {
    display:block;
    font-size:.69rem;
    color:#8a776f;
    margin-top:.14rem;
  }
  .src-delta {
    font-weight:900;
    text-align:right;
  }
  .src-delta.ok { color:#1f7a49; }
  .src-delta.bad { color:#b42318; }
  .src-chip {
    display:inline-flex;
    align-items:center;
    padding:.24rem .62rem;
    border-radius:999px;
    font-size:.68rem;
    font-weight:800;
  }
  .src-chip.ok { background:#e8f8ee; color:#1f7a49; }
  .src-chip.bad { background:#fde9e8; color:#b42318; }
  .src-pos-job-shell {
    border:1px solid rgba(225,210,199,.78);
    border-radius:20px;
    padding:1rem 1.05rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .src-pos-job-list {
    display:grid;
    gap:.75rem;
  }
  .src-pos-job-card {
    border:1px solid rgba(225,210,199,.78);
    border-radius:16px;
    padding:.8rem .9rem;
    background:#fff;
  }
  .src-pos-job-top {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.8rem;
    flex-wrap:wrap;
  }
  .src-pos-job-title {
    font-weight:900;
    color:#2f2628;
    line-height:1.2;
  }
  .src-pos-job-meta {
    font-size:.74rem;
    color:#89766e;
    line-height:1.35;
  }
  .src-pos-job-error {
    margin-top:.55rem;
    padding:.65rem .75rem;
    border-radius:12px;
    background:#fff1f2;
    color:#9f1239;
    font-size:.78rem;
    line-height:1.45;
  }
  .src-pos-job-badges {
    display:flex;
    flex-wrap:wrap;
    gap:.35rem;
    justify-content:flex-end;
  }
  .src-pos-job-badge {
    display:inline-flex;
    align-items:center;
    padding:.2rem .58rem;
    border-radius:999px;
    font-size:.68rem;
    font-weight:900;
  }
  .src-pos-job-badge.failed { background:#fee2e2; color:#b91c1c; }
  .src-pos-job-badge.queued { background:#e0f2fe; color:#075985; }
  .src-pos-job-badge.processing { background:#ede9fe; color:#5b21b6; }
  .src-pos-job-badge.success { background:#dcfce7; color:#166534; }
  .src-pos-job-badge.order { background:#f1f5f9; color:#334155; }
  .src-action-cell {
    min-width: 160px;
    white-space: nowrap;
  }
  .src-audit-shell {
    border:1px solid rgba(225,210,199,.78);
    border-radius:20px;
    padding:1rem 1.05rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .src-audit-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.8rem;
  }
  .src-audit-metric {
    border:1px solid rgba(225,210,199,.82);
    border-radius:16px;
    background:#fff;
    padding:.85rem .95rem;
  }
  .src-audit-metric .label {
    display:block;
    font-size:.7rem;
    color:#8a776f;
    text-transform:uppercase;
    letter-spacing:.04em;
    font-weight:800;
    margin-bottom:.18rem;
  }
  .src-audit-metric .value {
    font-size:1.15rem;
    font-weight:900;
    color:#2f2628;
  }
  .src-audit-table th,
  .src-audit-table td {
    font-size:.78rem;
    vertical-align:top;
  }
  @media (max-width: 991.98px) {
    .src-filter-grid { grid-template-columns:1fr 1fr; }
    .src-kpi-grid { grid-template-columns:1fr; }
    .src-audit-summary { grid-template-columns:1fr 1fr; }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h3 class="mb-1">Rekonsiliasi Stok Akhir Divisi</h3>
    <div class="text-muted small">Bandingkan stok bahan baku per divisi antara stok saat ini, material daily, daily divisi, dan closing dari movement mentah. Halaman ini fokus pada <strong>material/profile divisi</strong>, bukan mismatch produk POS.</div>
  </div>
  <div class="text-muted small">Tanggal acuan: <strong><?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?></strong></div>
</div>

<div class="mb-3 d-flex flex-wrap gap-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'compare']); ?>
</div>

<div class="card src-card mb-3">
  <div class="card-body p-3 p-lg-4">
    <form method="get" class="src-filter-grid">
      <div>
        <label class="form-label small text-muted mb-1">Tanggal</label>
        <input type="date" name="as_of_date" value="<?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?>" class="form-control">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Cari Material</label>
        <input type="text" name="q" value="<?php echo html_escape((string)($q ?? '')); ?>" class="form-control" placeholder="Kode / nama material">
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua divisi</option>
          <?php foreach ($divisions as $division): ?>
            <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo ((int)($division_id ?? 0) === (int)($division['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($division['division_name'] ?? $division['division_code'] ?? ('Divisi #' . (int)($division['id'] ?? 0)))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1">Baris</label>
        <input type="number" min="1" max="2000" name="limit" value="<?php echo (int)($limit ?? 300); ?>" class="form-control">
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a href="<?php echo html_escape($baseUrl); ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="src-kpi-grid mb-3">
  <div class="src-kpi">
    <span class="label">Total Baris</span>
    <div class="value"><?php echo number_format((int)($summary['total_rows'] ?? 0)); ?></div>
  </div>
  <div class="src-kpi">
    <span class="label">Match</span>
    <div class="value"><?php echo number_format((int)($summary['match_rows'] ?? 0)); ?></div>
  </div>
  <div class="src-kpi">
    <span class="label">Mismatch Material</span>
    <div class="value"><?php echo number_format((int)($summary['mismatch_rows'] ?? 0)); ?></div>
  </div>
</div>

<div class="card src-card mb-3">
  <div class="card-body p-3 p-lg-4">
    <div class="src-pos-job-shell">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Audit POS Queue</div>
          <h5 class="mb-1 mt-2">Job Stock Commit POS Gagal</h5>
          <div class="text-muted small">Satu pusat audit cepat untuk order POS yang tersimpan tetapi stok divisinya belum berhasil diposting.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <input type="text" id="src_pos_job_q" class="form-control form-control-sm" placeholder="Cari order / commit / job / error" style="min-width:260px;">
          <a href="<?php echo html_escape(site_url('pos/stock-live')); ?>" class="btn btn-sm btn-outline-secondary">Buka Stock Live POS</a>
          <button type="button" class="btn btn-sm btn-outline-primary" id="src_pos_job_reload">Refresh</button>
        </div>
      </div>
      <div id="src_pos_job_list" class="src-pos-job-list"></div>
      <div id="src_pos_job_empty" class="text-muted small d-none">Belum ada job stock commit POS yang gagal.</div>
    </div>
  </div>
</div>

<div class="card src-card">
  <div class="card-body p-0">
    <div class="src-table-wrap">
      <table class="table table-hover align-middle mb-0 src-table">
        <thead>
          <tr>
            <th style="min-width:250px;">Material</th>
            <th>Stok Divisi</th>
            <th>Material Daily</th>
            <th>Daily Divisi</th>
            <th>Movement</th>
            <th>Selisih Stok vs Movement</th>
            <th>Selisih Material Daily</th>
            <th>Selisih Daily Divisi</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data untuk filter ini.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php $chip = $statusChip($row); ?>
              <tr>
                <td>
                  <div class="src-obj-name"><?php echo html_escape($fmtText($row['material_name'] ?? '')); ?></div>
                  <div class="src-obj-sub">
                    <?php echo html_escape($fmtText($row['material_code'] ?? '')); ?>
                    <span class="mx-1">|</span>
                    <?php echo html_escape($fmtText($row['division_name'] ?? '')); ?>
                    <span class="mx-1">|</span>
                    <?php echo html_escape($fmtText($row['destination_name'] ?? 'Reguler')); ?>
                  </div>
                </td>
                <td><div class="src-metric"><span class="primary"><?php echo $fmtQty($row['balance_qty_content'] ?? 0); ?></span><span class="secondary"><?php echo $fmtQty($row['balance_qty_pack'] ?? 0); ?> pack</span></div></td>
                <td><div class="src-metric"><span class="primary"><?php echo $fmtQty($row['matrix_qty_content'] ?? 0); ?></span><span class="secondary"><?php echo $fmtQty($row['matrix_qty_pack'] ?? 0); ?> pack</span></div></td>
                <td><div class="src-metric"><span class="primary"><?php echo $fmtQty($row['daily_qty_content'] ?? 0); ?></span><span class="secondary"><?php echo $fmtQty($row['daily_qty_pack'] ?? 0); ?> pack</span></div></td>
                <td><div class="src-metric"><span class="primary"><?php echo $fmtQty($row['movement_qty_content'] ?? 0); ?></span><span class="secondary"><?php echo $fmtQty($row['movement_qty_pack'] ?? 0); ?> pack</span></div></td>
                <td class="src-delta <?php echo abs((float)($row['delta_balance_vs_movement'] ?? 0)) < 0.0001 ? 'ok' : 'bad'; ?>"><?php echo $fmtQty($row['delta_balance_vs_movement'] ?? 0); ?></td>
                <td class="src-delta <?php echo abs((float)($row['delta_matrix_vs_movement'] ?? 0)) < 0.0001 ? 'ok' : 'bad'; ?>"><?php echo $fmtQty($row['delta_matrix_vs_movement'] ?? 0); ?></td>
                <td class="src-delta <?php echo abs((float)($row['delta_daily_vs_movement'] ?? 0)) < 0.0001 ? 'ok' : 'bad'; ?>"><?php echo $fmtQty($row['delta_daily_vs_movement'] ?? 0); ?></td>
                <td><span class="src-chip <?php echo html_escape($chip['class']); ?>"><?php echo html_escape($chip['label']); ?></span></td>
                <td class="src-action-cell text-end">
                  <div class="d-flex gap-2 justify-content-end">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary src-material-audit-btn"
                      data-as-of-date="<?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?>"
                      data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                      data-item-id="<?php echo (int)($row['item_id'] ?? 0); ?>"
                      data-material-id="<?php echo (int)($row['material_id'] ?? 0); ?>"
                      data-destination="<?php echo html_escape((string)($row['destination_group'] ?? ($destination ?? 'ALL'))); ?>"
                    >Audit</button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger src-material-repair-btn"
                      data-as-of-date="<?php echo html_escape((string)($as_of_date ?? date('Y-m-d'))); ?>"
                      data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                      data-item-id="<?php echo (int)($row['item_id'] ?? 0); ?>"
                      data-material-id="<?php echo (int)($row['material_id'] ?? 0); ?>"
                      data-destination="<?php echo html_escape((string)($row['destination_group'] ?? ($destination ?? 'ALL'))); ?>"
                    >Repair</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card src-card mt-3" id="src_material_audit_card">
  <div class="card-body p-3 p-lg-4">
    <div class="src-audit-shell">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Audit Bahan</div>
          <h5 class="mb-1 mt-2">Lacak Selisih per Bahan</h5>
          <div class="text-muted small">Pilih tombol Audit pada salah satu baris untuk melihat bucket OPENING, PO, SR, VOID, REFUND, ADJUSTMENT, POS, dan detail movement sumbernya.</div>
        </div>
        <div>
          <button type="button" class="btn btn-sm btn-outline-danger d-none" id="src_material_repair_current">Repair Bahan Ini</button>
        </div>
      </div>
      <div id="src_material_audit_state" class="text-muted small">Belum ada bahan yang dipilih.</div>
      <div id="src_material_audit_body" class="d-none">
        <div id="src_material_audit_summary" class="src-audit-summary mb-3"></div>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle src-audit-table mb-0">
            <thead>
              <tr>
                <th>Bucket</th>
                <th class="text-end">Jumlah Log</th>
                <th class="text-end">Delta Content</th>
                <th class="text-end">Delta Buy</th>
                <th class="text-end">Nilai Mutasi</th>
                <th>Log Terakhir</th>
              </tr>
            </thead>
            <tbody id="src_material_audit_buckets"></tbody>
          </table>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle src-audit-table mb-0">
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
            <tbody id="src_material_audit_movements"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const listEl = document.getElementById('src_pos_job_list');
  const emptyEl = document.getElementById('src_pos_job_empty');
  const searchEl = document.getElementById('src_pos_job_q');
  const reloadBtn = document.getElementById('src_pos_job_reload');
  const materialAuditCard = document.getElementById('src_material_audit_card');
  const materialAuditState = document.getElementById('src_material_audit_state');
  const materialAuditBody = document.getElementById('src_material_audit_body');
  const materialAuditSummary = document.getElementById('src_material_audit_summary');
  const materialAuditBuckets = document.getElementById('src_material_audit_buckets');
  const materialAuditMovements = document.getElementById('src_material_audit_movements');
  const materialRepairCurrentBtn = document.getElementById('src_material_repair_current');
  let searchTimer = null;
  let currentMaterialIdentity = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
  function fmtQty(v) {
    const num = Number(v || 0);
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(num);
  }
  function fmtDateTime(v) {
    if (!v) return '-';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return escapeHtml(String(v));
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(dt);
  }
  function showAlert(message, title) {
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return Promise.resolve(window.FinanceUI.alert(message, { title: title || 'Informasi' }));
    }
    console.warn(message);
    return Promise.resolve();
  }
  function askConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return Promise.resolve(window.FinanceUI.confirm(message, options || {}));
    }
    return showAlert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', 'UI Belum Siap').then(() => false);
  }
  async function getJson(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const text = await response.text();
    let json = null;
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
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response backend tidak valid: ' + String(text || '').slice(0, 180)); }
    if (!response.ok || !json.ok) throw new Error(json.message || 'Gagal memproses aksi.');
    return json;
  }
  function jobBadge(status) {
    const value = String(status || '').toUpperCase();
    const map = {
      FAILED: ['failed', 'FAILED'],
      QUEUED: ['queued', 'QUEUED'],
      PROCESSING: ['processing', 'PROCESSING'],
      SUCCESS: ['success', 'SUCCESS'],
      CANCELLED: ['order', 'CANCELLED']
    };
    const row = map[value] || ['order', value || '-'];
    return `<span class="src-pos-job-badge ${row[0]}">${escapeHtml(row[1])}</span>`;
  }
  function stockBadge(status) {
    const value = String(status || '').toUpperCase();
    if (!value) return '';
    return `<span class="src-pos-job-badge order">Stok ${escapeHtml(value)}</span>`;
  }
  function buttonIdentity(button) {
    return {
      as_of_date: String(button?.dataset.asOfDate || ''),
      division_id: Number(button?.dataset.divisionId || 0),
      item_id: Number(button?.dataset.itemId || 0),
      material_id: Number(button?.dataset.materialId || 0),
      destination: String(button?.dataset.destination || 'ALL')
    };
  }
  function setMaterialAuditState(message, loading) {
    materialAuditState.textContent = message;
    materialAuditBody.classList.add('d-none');
    materialAuditState.classList.remove('d-none');
    if (materialRepairCurrentBtn) {
      materialRepairCurrentBtn.classList.toggle('d-none', !currentMaterialIdentity || !!loading);
      materialRepairCurrentBtn.disabled = !!loading || !currentMaterialIdentity;
    }
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
  function renderMaterialAudit(identity, json) {
    currentMaterialIdentity = identity;
    const summary = json.summary || {};
    const buckets = Array.isArray(json.buckets) ? json.buckets : [];
    const movements = Array.isArray(json.movements) ? json.movements : [];
    materialAuditState.classList.add('d-none');
    materialAuditBody.classList.remove('d-none');
    if (materialRepairCurrentBtn) {
      materialRepairCurrentBtn.classList.remove('d-none');
      materialRepairCurrentBtn.disabled = false;
    }
    materialAuditSummary.innerHTML = [
      ['Material', `${escapeHtml(summary.material_name || '-')}`, `${escapeHtml(summary.material_code || '-')}`],
      ['Verdict', escapeHtml(summary.suspect_table || 'MATCH'), escapeHtml(summary.suspect_reason || 'Semua tabel masih sinkron.')],
      ['Stok vs Movement', fmtQty(summary.delta_balance_vs_movement || 0), 'selisih content'],
      ['Daily vs Movement', fmtQty(summary.delta_daily_vs_movement || 0), `closing daily ${escapeHtml(summary.daily_date || '-')}`],
      ['Identity Repair', escapeHtml(String(json.repair_identity_count || 0)), 'identity sumber']
    ].map((item) => `
      <div class="src-audit-metric">
        <span class="label">${item[0]}</span>
        <div class="value">${item[1]}</div>
        <div class="text-muted small">${item[2]}</div>
      </div>
    `).join('');
    materialAuditBuckets.innerHTML = buckets.map((bucket) => `
      <tr>
        <td>${escapeHtml(bucket.bucket_label || bucket.bucket_code || '-')}</td>
        <td class="text-end">${Number(bucket.count || 0)}</td>
        <td class="text-end">${fmtQty(bucket.delta_content || 0)}</td>
        <td class="text-end">${fmtQty(bucket.delta_buy || 0)}</td>
        <td class="text-end">${fmtQty(bucket.mutation_value || 0)}</td>
        <td>${escapeHtml(bucket.last_movement_date || '-')}<div class="text-muted small">${escapeHtml(bucket.last_movement_no || '-')}</div></td>
      </tr>
    `).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada bucket log.</td></tr>';
    materialAuditMovements.innerHTML = movements.map((row) => `
      <tr>
        <td>${escapeHtml(row.movement_date || '-')}</td>
        <td>${escapeHtml(row.movement_no || '-')}</td>
        <td>${escapeHtml(row.source_label || '-')}<div class="text-muted small">${escapeHtml(row.source_bucket_label || '-')}</div></td>
        <td class="text-end">${fmtQty(row.qty_content_before || 0)}</td>
        <td class="text-end">${fmtQty(row.qty_content_delta || 0)}</td>
        <td class="text-end">${fmtQty(row.qty_content_after || 0)}</td>
        <td>${escapeHtml(row.movement_type_label || row.movement_type || '-')}</td>
        <td>${escapeHtml(row.notes || '-')}</td>
      </tr>
    `).join('') || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada movement sumber.</td></tr>';
    materialAuditCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  async function loadMaterialAudit(identity) {
    currentMaterialIdentity = identity;
    setMaterialAuditState('Memuat audit bahan...', true);
    const params = new URLSearchParams();
    Object.keys(identity || {}).forEach((key) => params.set(key, String(identity[key] ?? '')));
    const json = await getJson('<?php echo $auditUrl; ?>?' + params.toString());
    renderMaterialAudit(identity, json);
  }
  async function runMaterialRepair(identity, button) {
    const confirmed = await askConfirm('Repair histori stok bahan ini akan menjalankan rebuild per identity sumber. Lanjutkan?', {
      title: 'Repair Reconcile Bahan',
      confirmText: 'Repair',
      cancelText: 'Batal'
    });
    if (!confirmed) return;
    setButtonLoading(button, 'Repair...');
    setMaterialAuditState('Menjalankan repair bahan...', true);
    try {
      const json = await postJson('<?php echo $repairUrl; ?>', identity);
      await showAlert(json.message || 'Repair selesai dijalankan.', 'Repair Reconcile Bahan');
      window.location.reload();
    } catch (error) {
      clearButtonLoading(button);
      throw error;
    }
  }
  async function loadFailedJobs() {
    const p = new URLSearchParams();
    p.set('q', String(searchEl ? (searchEl.value || '') : ''));
    p.set('limit', '8');
    const json = await getJson('<?php echo site_url('pos/orders/runtime-jobs/failed'); ?>?' + p.toString());
    const rows = Array.isArray(json.rows) ? json.rows : [];
    if (!rows.length) {
      listEl.innerHTML = '';
      emptyEl.classList.remove('d-none');
      return;
    }
    emptyEl.classList.add('d-none');
    listEl.innerHTML = rows.map((row) => `
      <div class="src-pos-job-card">
        <div class="src-pos-job-top">
          <div>
            <div class="src-pos-job-title">${escapeHtml(row.order_no || '-')} | ${escapeHtml(row.commit_no || '-')}</div>
            <div class="src-pos-job-meta">Job ${escapeHtml(row.job_code || '-')} | Outlet ${escapeHtml(row.outlet_name || '-')} | Kasir ${escapeHtml(row.cashier_employee_name || '-')}</div>
            <div class="src-pos-job-meta">Percobaan ${Number(row.attempts || 0)} / ${Number(row.max_attempts || 0)} | Retry setelah ${fmtDateTime(row.run_after || row.updated_at || row.created_at || '')}</div>
          </div>
          <div class="text-end">
            <div class="src-pos-job-badges">
              ${jobBadge(row.status || '')}
              ${stockBadge(row.stock_commit_status || '')}
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2 src-pos-job-retry" data-job-id="${Number(row.id || 0)}">Retry</button>
          </div>
        </div>
        <div class="src-pos-job-error">${escapeHtml(row.last_error || 'Job gagal tanpa pesan detail.')}</div>
      </div>
    `).join('');
  }

  if (searchEl) {
    searchEl.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => loadFailedJobs().catch((e) => showAlert(e.message, 'Audit POS Queue')), 260);
    });
  }
  if (reloadBtn) {
    reloadBtn.addEventListener('click', function () {
      loadFailedJobs().catch((e) => showAlert(e.message, 'Audit POS Queue'));
    });
  }
  if (listEl) {
    listEl.addEventListener('click', async function (event) {
      const retryBtn = event.target.closest('.src-pos-job-retry');
      if (!retryBtn) return;
      const confirmed = await askConfirm('Retry job stock commit POS ini sekarang?', {
        title: 'Retry Stock Commit POS',
        confirmText: 'Retry',
        cancelText: 'Batal'
      });
      if (!confirmed) return;
      const original = retryBtn.innerHTML;
      retryBtn.disabled = true;
      retryBtn.textContent = 'Retry...';
      try {
        await postJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/' + encodeURIComponent(retryBtn.dataset.jobId || '0'), {});
        await loadFailedJobs();
      } catch (e) {
        await showAlert(e.message, 'Retry Stock Commit POS');
      } finally {
        retryBtn.disabled = false;
        retryBtn.innerHTML = original;
      }
    });
  }
  if (materialRepairCurrentBtn) {
    materialRepairCurrentBtn.addEventListener('click', function () {
      if (!currentMaterialIdentity) return;
      runMaterialRepair(currentMaterialIdentity, materialRepairCurrentBtn).catch((e) => {
        clearButtonLoading(materialRepairCurrentBtn);
        setMaterialAuditState(e.message, false);
      });
    });
  }
  document.addEventListener('click', function (event) {
    const auditBtn = event.target.closest('.src-material-audit-btn');
    if (auditBtn) {
      loadMaterialAudit(buttonIdentity(auditBtn)).catch((e) => setMaterialAuditState(e.message, false));
      return;
    }
    const repairBtn = event.target.closest('.src-material-repair-btn');
    if (repairBtn) {
      runMaterialRepair(buttonIdentity(repairBtn), repairBtn).catch((e) => {
        clearButtonLoading(repairBtn);
        setMaterialAuditState(e.message, false);
      });
    }
  });

  loadFailedJobs().catch((e) => showAlert(e.message, 'Audit POS Queue'));
});
</script>
