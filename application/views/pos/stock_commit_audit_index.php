<?php
$asOfDate = (string)($as_of_date ?? date('Y-m-d'));
$auditTab = strtolower(trim((string)($audit_tab ?? 'material')));
if (!in_array($auditTab, ['material', 'component'], true)) {
    $auditTab = 'material';
}
$materialCompare = is_array($material_compare ?? null) ? $material_compare : [];
$componentCompare = is_array($component_compare ?? null) ? $component_compare : [];
$materialRows = is_array($materialCompare['rows'] ?? null) ? $materialCompare['rows'] : [];
$materialSummary = is_array($materialCompare['summary'] ?? null) ? $materialCompare['summary'] : [];
$componentRows = is_array($componentCompare['rows'] ?? null) ? $componentCompare['rows'] : [];
$componentSummary = is_array($componentCompare['summary'] ?? null) ? $componentCompare['summary'] : [];
$domainAudit = is_array($domain_audit ?? null) ? $domain_audit : [];
$domainAuditRows = is_array($domainAudit['rows'] ?? null) ? $domainAudit['rows'] : [];
$domainAuditSummary = is_array($domainAudit['summary'] ?? null) ? $domainAudit['summary'] : [];
$failedJobs = is_array($failed_jobs ?? null) ? $failed_jobs : [];
$activeJobs = is_array($active_jobs ?? null) ? $active_jobs : [];

$fmtQty = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};
?>

<style>
  .sca-shell { display:grid; gap:1rem; }
  .sca-card {
    border:1px solid rgba(225,210,199,.82);
    border-radius:22px;
    background:#fff;
    box-shadow:0 16px 34px rgba(58,38,30,.06);
  }
  .sca-filter-grid {
    display:grid;
    grid-template-columns:180px auto auto;
    gap:.75rem;
    align-items:end;
  }
  .sca-hero-copy { color:#78685d; font-size:.92rem; }
  .sca-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; }
  .sca-kpi {
    border:1px solid rgba(225,210,199,.82);
    border-radius:18px;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    padding:.9rem 1rem;
  }
  .sca-kpi .label {
    display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:#8a776f; font-weight:800; margin-bottom:.18rem;
  }
  .sca-kpi .value { font-size:1.3rem; font-weight:900; color:#2f2628; }
  .sca-tabs { display:flex; flex-wrap:wrap; gap:.55rem; }
  .sca-tab {
    display:inline-flex; align-items:center; gap:.4rem; min-height:40px; padding:.48rem .95rem;
    border-radius:999px; border:1px solid #cdbfb4; background:#f5efea; color:#5a4c45; text-decoration:none; font-weight:800; font-size:.88rem;
  }
  .sca-tab.is-active { background:#34325e; border-color:#34325e; color:#fff; }
  .sca-tab-count { font-size:.74rem; opacity:.9; }
  .sca-summary-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; }
  .sca-summary-box {
    border:1px solid rgba(225,210,199,.82); border-radius:18px; background:#fffdfb; padding:.85rem .95rem;
  }
  .sca-summary-box .label { display:block; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:#8a776f; font-weight:800; margin-bottom:.18rem; }
  .sca-summary-box .value { font-size:1.18rem; font-weight:900; color:#2f2628; }
  .sca-table-wrap { overflow:auto; }
  .sca-table th { white-space:nowrap; font-size:.78rem; background:#fff8f4; position:sticky; top:0; z-index:1; }
  .sca-table td { font-size:.82rem; vertical-align:top; }
  .sca-chip {
    display:inline-flex; align-items:center; padding:.22rem .58rem; border-radius:999px; font-size:.68rem; font-weight:900;
  }
  .sca-chip.ok { background:#e8f8ee; color:#1f7a49; }
  .sca-chip.bad { background:#fde9e8; color:#b42318; }
  .sca-job-list { display:grid; gap:.75rem; }
  .sca-job-card {
    border:1px solid rgba(225,210,199,.82); border-radius:16px; background:#fff; padding:.85rem .95rem;
  }
  .sca-job-top {
    display:flex; justify-content:space-between; gap:.8rem; align-items:flex-start; flex-wrap:wrap;
  }
  .sca-job-title { font-weight:900; color:#2f2628; }
  .sca-job-meta { font-size:.76rem; color:#8a776f; line-height:1.45; }
  .sca-job-error {
    margin-top:.55rem; padding:.7rem .8rem; border-radius:14px; background:#fff1f2; color:#9f1239; font-size:.78rem;
  }
  .sca-job-badges { display:flex; flex-wrap:wrap; gap:.35rem; }
  .sca-job-badge {
    display:inline-flex; align-items:center; padding:.2rem .56rem; border-radius:999px; font-size:.68rem; font-weight:900;
  }
  .sca-job-badge.failed { background:#fee2e2; color:#b91c1c; }
  .sca-job-badge.processing { background:#ede9fe; color:#5b21b6; }
  .sca-job-badge.queued { background:#e0f2fe; color:#075985; }
  .sca-job-badge.order { background:#f1f5f9; color:#334155; }
  @media (max-width: 991.98px) {
    .sca-filter-grid, .sca-kpis, .sca-summary-grid { grid-template-columns:1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'stock-commit-audit']); ?>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
      <h4 class="mb-1">Audit Commit Stok POS</h4>
      <div class="sca-hero-copy">Satu tempat untuk memantau job stock commit POS yang aktif/gagal, lalu membandingkan mismatch bahan baku dan base/prepare tanpa nyasar ke halaman self-order.</div>
    </div>
    <div class="text-muted small">Tanggal acuan: <strong><?php echo html_escape($asOfDate); ?></strong></div>
  </div>

  <div class="sca-shell">
    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <form method="get" class="sca-filter-grid">
          <div>
            <label class="form-label small text-muted mb-1">Tanggal</label>
            <input type="date" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>" class="form-control">
          </div>
          <input type="hidden" name="tab" value="<?php echo html_escape($auditTab); ?>">
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="<?php echo html_escape(site_url('pos/stock-commit-audit')); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
          <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
            <a href="<?php echo html_escape(site_url('pos/stock-live')); ?>" class="btn btn-outline-secondary">Buka Stock Live POS</a>
            <button type="button" class="btn btn-outline-primary" id="sca_process_all_btn">Proses Pending</button>
            <button type="button" class="btn btn-outline-danger" id="sca_retry_failed_btn">Retry Semua Gagal</button>
            <button type="button" class="btn btn-outline-secondary" id="sca_reload_jobs_btn">Refresh Job</button>
          </div>
        </form>
      </div>
    </div>

    <div class="sca-kpis">
      <div class="sca-kpi"><span class="label">Job Aktif</span><div class="value" id="sca_active_job_count"><?php echo number_format(count($activeJobs)); ?></div></div>
      <div class="sca-kpi"><span class="label">Job Gagal</span><div class="value" id="sca_failed_job_count"><?php echo number_format(count($failedJobs)); ?></div></div>
      <div class="sca-kpi"><span class="label">Mismatch Bahan Baku</span><div class="value"><?php echo number_format((int)($materialSummary['mismatch_rows'] ?? 0)); ?></div></div>
      <div class="sca-kpi"><span class="label">Mismatch Base/Prepare</span><div class="value"><?php echo number_format((int)($componentSummary['mismatched'] ?? 0)); ?></div></div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Akar Masalah</div>
            <h5 class="mb-1 mt-2">Profile Produksi Salah Domain</h5>
            <div class="text-muted small">Kalau item punya <code>material_id</code> tetapi profile aktifnya masih <code>ITEM</code>, transaksi produksi bisa pecah antara domain <code>ITEM</code> dan <code>MATERIAL</code>. Ini pangkal mismatch yang harus kita bereskan sebelum retry job commit.</div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo html_escape(site_url('purchase/reclassify-profile-domain')); ?>" class="btn btn-outline-secondary">Tool Snapshot Lama</a>
            <span class="btn btn-light disabled">SQL Audit: <code>2026-06-03b</code></span>
            <span class="btn btn-light disabled">SQL Repair: <code>2026-06-03c</code></span>
          </div>
        </div>

        <div class="sca-summary-grid mb-3">
          <div class="sca-summary-box"><span class="label">Profile Salah Domain</span><div class="value"><?php echo number_format((int)($domainAuditSummary['total_wrong_active_profiles'] ?? 0)); ?></div></div>
          <div class="sca-summary-box"><span class="label">Drift Transaksi</span><div class="value"><?php echo number_format((int)($domainAuditSummary['profiles_with_transaction_drift'] ?? 0)); ?></div></div>
          <div class="sca-summary-box"><span class="label">Snapshot Pecah</span><div class="value"><?php echo number_format((int)($domainAuditSummary['profiles_with_snapshot_split'] ?? 0)); ?></div></div>
        </div>

        <div class="table-responsive sca-table-wrap">
          <table class="table table-sm align-middle sca-table mb-0">
            <thead>
              <tr>
                <th>Profile Salah Domain</th>
                <th>Target Benar</th>
                <th class="text-end">PO Item</th>
                <th class="text-end">Receipt Item</th>
                <th class="text-end">Movement Drift</th>
                <th class="text-end">Snapshot ITEM</th>
                <th class="text-end">Snapshot MATERIAL</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($domainAuditRows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada profile aktif salah domain yang terdeteksi.</td></tr>
              <?php else: ?>
                <?php foreach ($domainAuditRows as $row): ?>
                  <?php
                    $snapshotItem = (int)($row['division_monthly_item_rows'] ?? 0) + (int)($row['warehouse_monthly_item_rows'] ?? 0);
                    $snapshotMaterial = (int)($row['division_monthly_material_rows'] ?? 0) + (int)($row['warehouse_monthly_material_rows'] ?? 0);
                  ?>
                  <tr>
                    <td>
                      <strong><?php echo html_escape((string)($row['catalog_name'] ?? '-')); ?></strong>
                      <div class="text-muted small"><?php echo html_escape((string)($row['brand_name'] ?? '-')); ?> | profile <code><?php echo html_escape((string)($row['profile_key'] ?? '-')); ?></code></div>
                    </td>
                    <td>
                      <strong>MATERIAL</strong>
                      <div class="text-muted small"><?php echo html_escape((string)($row['expected_material_name'] ?? '-')); ?><?php if (!empty($row['expected_material_code'])): ?> | <?php echo html_escape((string)$row['expected_material_code']); ?><?php endif; ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format((int)($row['po_item_rows'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['receipt_item_rows'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format((int)($row['movement_wrong_material_rows'] ?? 0)); ?></td>
                    <td class="text-end"><?php echo number_format($snapshotItem); ?></td>
                    <td class="text-end"><?php echo number_format($snapshotMaterial); ?></td>
                    <td><span class="sca-chip <?php echo !empty($row['has_split_snapshot']) ? 'bad' : 'ok'; ?>"><?php echo !empty($row['has_split_snapshot']) ? 'Split ITEM + MATERIAL' : 'ITEM padahal produksi'; ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Reconcile Workspace</div>
            <h5 class="mb-1 mt-2">Bandingkan Mismatch yang Perlu Kita Bedah</h5>
            <div class="text-muted small">Tab ini bersifat umum untuk POS, bukan khusus self-order. Saat ada stock failed, biasanya mismatch bahan atau component akan lebih cepat terlihat di sini.</div>
          </div>
          <div class="sca-tabs">
            <a href="<?php echo html_escape(site_url('pos/stock-commit-audit?tab=material&as_of_date=' . rawurlencode($asOfDate))); ?>" class="sca-tab <?php echo $auditTab === 'material' ? 'is-active' : ''; ?>">
              <span>Bahan Baku</span>
              <span class="sca-tab-count"><?php echo number_format((int)($materialSummary['mismatch_rows'] ?? 0)); ?></span>
            </a>
            <a href="<?php echo html_escape(site_url('pos/stock-commit-audit?tab=component&as_of_date=' . rawurlencode($asOfDate))); ?>" class="sca-tab <?php echo $auditTab === 'component' ? 'is-active' : ''; ?>">
              <span>Base/Prepare</span>
              <span class="sca-tab-count"><?php echo number_format((int)($componentSummary['mismatched'] ?? 0)); ?></span>
            </a>
          </div>
        </div>

        <?php if ($auditTab === 'material'): ?>
          <div class="sca-summary-grid mb-3">
            <div class="sca-summary-box"><span class="label">Total Baris</span><div class="value"><?php echo number_format((int)($materialSummary['total_rows'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Match</span><div class="value"><?php echo number_format((int)($materialSummary['match_rows'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Mismatch</span><div class="value"><?php echo number_format((int)($materialSummary['mismatch_rows'] ?? 0)); ?></div></div>
          </div>
          <div class="table-responsive sca-table-wrap">
            <table class="table table-sm align-middle sca-table mb-0">
              <thead>
                <tr>
                  <th>Material</th>
                  <th>Divisi</th>
                  <th class="text-end">Stok Divisi</th>
                  <th class="text-end">Material Daily</th>
                  <th class="text-end">Daily Divisi</th>
                  <th class="text-end">Movement</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($materialRows)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data bahan baku.</td></tr>
                <?php else: ?>
                  <?php foreach ($materialRows as $row): ?>
                    <?php $isMatch = !empty($row['is_match']); ?>
                    <tr>
                      <td><strong><?php echo html_escape((string)($row['material_name'] ?? '-')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($row['material_code'] ?? '-')); ?></div></td>
                      <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><div class="text-muted small"><?php echo html_escape((string)($row['destination_name'] ?? 'Reguler')); ?></div></td>
                      <td class="text-end"><?php echo $fmtQty($row['balance_qty_content'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['matrix_qty_content'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['daily_qty_content'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['movement_qty_content'] ?? 0); ?></td>
                      <td><span class="sca-chip <?php echo $isMatch ? 'ok' : 'bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span></td>
                      <td class="text-end"><a href="<?php echo html_escape(site_url('inventory/stock/division/reconcile?as_of_date=' . rawurlencode($asOfDate) . '&division_id=' . (int)($row['division_id'] ?? 0) . '&q=' . rawurlencode((string)($row['material_name'] ?? '')))); ?>" class="btn btn-sm btn-outline-primary">Buka Audit</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="sca-summary-grid mb-3">
            <div class="sca-summary-box"><span class="label">Total Baris</span><div class="value"><?php echo number_format((int)($componentSummary['total'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Match</span><div class="value"><?php echo number_format((int)($componentSummary['matched'] ?? 0)); ?></div></div>
            <div class="sca-summary-box"><span class="label">Mismatch</span><div class="value"><?php echo number_format((int)($componentSummary['mismatched'] ?? 0)); ?></div></div>
          </div>
          <div class="table-responsive sca-table-wrap">
            <table class="table table-sm align-middle sca-table mb-0">
              <thead>
                <tr>
                  <th>Component</th>
                  <th>Lokasi</th>
                  <th class="text-end">Live</th>
                  <th class="text-end">Daily</th>
                  <th class="text-end">Movement</th>
                  <th class="text-end">Live vs Daily</th>
                  <th class="text-end">Live vs Movement</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($componentRows)): ?>
                  <tr><td colspan="9" class="text-center text-muted py-4">Belum ada data base/prepare.</td></tr>
                <?php else: ?>
                  <?php foreach ($componentRows as $row): ?>
                    <?php $isMatch = !empty($row['is_match']); ?>
                    <tr>
                      <td><strong><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></strong><div class="text-muted small"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?> | <?php echo html_escape((string)($row['uom_code'] ?? '-')); ?></div></td>
                      <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?><div class="text-muted small"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div></td>
                      <td class="text-end"><?php echo $fmtQty($row['balance_qty'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['daily_qty'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['movement_qty'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['delta_balance_daily'] ?? 0); ?></td>
                      <td class="text-end"><?php echo $fmtQty($row['delta_balance_movement'] ?? 0); ?></td>
                      <td><span class="sca-chip <?php echo $isMatch ? 'ok' : 'bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span></td>
                      <td class="text-end"><a href="<?php echo html_escape(site_url('production/component-reconcile?as_of_date=' . rawurlencode($asOfDate) . '&division_id=' . (int)($row['division_id'] ?? 0) . '&q=' . rawurlencode((string)($row['component_name'] ?? '')))); ?>" class="btn btn-sm btn-outline-primary">Buka Audit</a></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="sca-card">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <div class="small text-uppercase fw-bold text-muted">Job Runtime POS</div>
            <h5 class="mb-1 mt-2">Queue Stock Commit yang Perlu Perhatian</h5>
            <div class="text-muted small">Daftar ini umum untuk semua order POS, termasuk kasir biasa dan self-order. Saat retry dari sini, backend akan memakai service runtime yang sama dengan stock-live.</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="small text-uppercase fw-bold text-muted mb-2">Sedang Antre / Diproses</div>
            <div class="sca-job-list" id="sca_active_job_list">
              <?php if (empty($activeJobs)): ?>
                <div class="text-muted small">Belum ada job aktif.</div>
              <?php else: ?>
                <?php foreach ($activeJobs as $job): ?>
                  <div class="sca-job-card">
                    <div class="sca-job-top">
                      <div>
                        <div class="sca-job-title"><?php echo html_escape((string)($job['order_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta"><?php echo html_escape((string)($job['job_code'] ?? '-')); ?> | <?php echo html_escape((string)($job['commit_no'] ?? '-')); ?></div>
                      </div>
                      <div class="sca-job-badges">
                        <span class="sca-job-badge <?php echo strtolower((string)($job['status'] ?? 'queued')); ?>"><?php echo html_escape((string)($job['status'] ?? '-')); ?></span>
                        <span class="sca-job-badge order"><?php echo html_escape((string)($job['stock_commit_status'] ?? '-')); ?></span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="small text-uppercase fw-bold text-muted mb-2">Job Gagal</div>
            <div class="sca-job-list" id="sca_failed_job_list">
              <?php if (empty($failedJobs)): ?>
                <div class="text-muted small">Belum ada job gagal.</div>
              <?php else: ?>
                <?php foreach ($failedJobs as $job): ?>
                  <div class="sca-job-card">
                    <div class="sca-job-top">
                      <div>
                        <div class="sca-job-title"><?php echo html_escape((string)($job['order_no'] ?? '-')); ?></div>
                        <div class="sca-job-meta"><?php echo html_escape((string)($job['job_code'] ?? '-')); ?> | <?php echo html_escape((string)($job['commit_no'] ?? '-')); ?></div>
                      </div>
                      <div class="sca-job-badges">
                        <span class="sca-job-badge failed"><?php echo html_escape((string)($job['status'] ?? 'FAILED')); ?></span>
                        <span class="sca-job-badge order"><?php echo html_escape((string)($job['stock_commit_status'] ?? '-')); ?></span>
                      </div>
                    </div>
                    <div class="sca-job-error"><?php echo nl2br(html_escape((string)($job['last_error'] ?? '-'))); ?></div>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                      <button type="button" class="btn btn-sm btn-outline-primary sca_retry_job_btn" data-job-id="<?php echo (int)($job['id'] ?? 0); ?>">Retry</button>
                      <a href="<?php echo html_escape(site_url('pos/orders/draft?q=' . rawurlencode((string)($job['order_no'] ?? '')))); ?>" class="btn btn-sm btn-outline-secondary">Buka Order</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
  function setButtonLoading(button, label) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (label || 'Memproses...');
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

  document.querySelectorAll('.sca_retry_job_btn').forEach((button) => {
    button.addEventListener('click', async function () {
      const jobId = Number(this.dataset.jobId || 0);
      if (jobId <= 0) return;
      const confirmed = await askConfirm('Retry job stock commit POS ini sekarang?', { title: 'Retry Stock Commit POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Retry...');
      try {
        const json = await postJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/' + jobId, {});
        await showAlert(json.message || 'Job berhasil diantrekan ulang.', 'Retry Stock Commit POS');
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal retry job stock commit POS.', 'Retry Stock Commit POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  });

  const processAllBtn = document.getElementById('sca_process_all_btn');
  if (processAllBtn) {
    processAllBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm('Proses seluruh job pending stock commit POS sekarang?', { title: 'Proses Pending Job POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Memproses...');
      try {
        const json = await postJson('<?php echo site_url('pos/orders/runtime-jobs/process-all'); ?>', { limit: 10 });
        await showAlert(
          'Processed: ' + Number((json.processed_count || 0)) + '\n'
          + 'Success: ' + Number((json.success_count || 0)) + '\n'
          + 'Failed: ' + Number((json.failed_count || 0)),
          'Job Runtime POS'
        );
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal memproses pending job POS.', 'Job Runtime POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const retryFailedBtn = document.getElementById('sca_retry_failed_btn');
  if (retryFailedBtn) {
    retryFailedBtn.addEventListener('click', async function () {
      const confirmed = await askConfirm('Retry semua job FAILED yang tampil sekarang? Jalankan ini setelah SQL repair selesai diterapkan.', { title: 'Retry Semua Job Gagal POS' });
      if (!confirmed) return;
      setButtonLoading(this, 'Retry semua...');
      try {
        const json = await postJson('<?php echo site_url('pos/orders/runtime-jobs/retry-failed-all'); ?>', { limit: 15 });
        await showAlert(
          'Processed: ' + Number((json.processed_count || 0)) + '\n'
          + 'Success: ' + Number((json.success_count || 0)) + '\n'
          + 'Failed: ' + Number((json.failed_count || 0)),
          'Retry Semua Job Gagal POS'
        );
        window.location.reload();
      } catch (e) {
        await showAlert(e.message || 'Gagal retry semua job FAILED.', 'Retry Semua Job Gagal POS');
      } finally {
        clearButtonLoading(this);
      }
    });
  }

  const reloadBtn = document.getElementById('sca_reload_jobs_btn');
  if (reloadBtn) {
    reloadBtn.addEventListener('click', function () {
      window.location.reload();
    });
  }
});
</script>
