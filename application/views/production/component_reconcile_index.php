<?php
$filters        = is_array($filters ?? null) ? $filters : [];
$rows           = is_array($rows ?? null) ? $rows : [];
$summary        = is_array($summary ?? null) ? $summary : [];
$divisions      = is_array($divisions ?? null) ? $divisions : [];
$locationOptions= is_array($location_options ?? null) ? $location_options : [];
$asOfDate       = (string)($as_of_date ?? date('Y-m-d'));
$dateFrom       = (string)($filters['date_from'] ?? date('Y-m-01'));
$dateTo         = (string)($filters['date_to'] ?? $asOfDate);
$perPage        = in_array((int)($filters['per_page'] ?? 25), [10, 25, 50, 100], true) ? (int)$filters['per_page'] : 25;

$fmtQty = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};

// — Summary computations —
$totalRows    = count($rows);
$cntMatch     = 0; $cntMismatch = 0;
$uniqueComps  = []; $maxAbsDelta = 0.0;
foreach ($rows as $r) {
    if (!empty($r['is_match'])) { $cntMatch++; } else { $cntMismatch++; }
    $cid = (int)($r['component_id'] ?? 0);
    if ($cid > 0) $uniqueComps[$cid] = true;
    $maxAbsDelta = max($maxAbsDelta,
        abs((float)($r['delta_balance_daily'] ?? 0)),
        abs((float)($r['delta_balance_movement'] ?? 0)),
        abs((float)($r['delta_daily_movement'] ?? 0))
    );
}
$matchRate    = $totalRows > 0 ? round($cntMatch / $totalRows * 100, 1) : 0;
$uniqueCompCount = count($uniqueComps);
?>

<style>
.recon-sum-card { border:1px solid #e8e0d4;border-radius:12px;background:#fff;padding:.6rem .85rem;height:100%; }
.recon-sum-card .lbl { font-size:.66rem;color:#999;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap; }
.recon-sum-card .val { font-size:.98rem;font-weight:800;color:#312729;line-height:1.25;margin-top:.1rem; }
.recon-sum-card .sub { font-size:.66rem;color:#bbb;margin-top:.12rem; }
.recon-sum-card.green  { border-color:#b5cca0;background:#f0f7ea; }
.recon-sum-card.green .val { color:#2d6a0a; }
.recon-sum-card.red    { border-color:#f5b8b8;background:#fef4f4; }
.recon-sum-card.red .val { color:#b42318; }
.recon-sum-card.blue   { border-color:#b8c8da;background:#f0f4f8; }
.recon-sum-card.blue .val { color:#1a3a5a; }
.recon-sum-card.amber  { border-color:#e0cc88;background:#fdfaf0; }
.recon-sum-card.amber .val { color:#7a5e00; }
.recon-filter-card { border:1px solid rgba(226,212,200,.88);border-radius:16px;box-shadow:0 4px 14px rgba(58,38,30,.05); }
.recon-table-card  { border:1px solid rgba(226,212,200,.88);border-radius:18px;box-shadow:0 14px 30px rgba(58,38,30,.06); }
.recon-tbl-wrap    { overflow:auto;max-height:72vh; }
.recon-tbl { table-layout:fixed;min-width:910px;margin-bottom:0;border-collapse:separate;border-spacing:0; }
.recon-tbl thead th {
  position:sticky;top:0;z-index:4;
  background:linear-gradient(180deg,#7c1f2d 0%,#9f2f3e 100%);
  color:#fff8f5;border-bottom:1px solid #7f2936;white-space:nowrap;font-size:.75rem;
}
.recon-tbl td,.recon-tbl th { vertical-align:middle;border-right:1px solid #efddd2;border-bottom:1px solid #f3e4da;font-size:.77rem; }
.recon-tbl tbody td { background:#fff; }
.recon-tbl tbody tr:nth-child(even) td { background:#fffaf6; }
.recon-chip { display:inline-flex;align-items:center;padding:.18rem .5rem;border-radius:999px;font-size:.66rem;font-weight:800; }
.recon-chip.ok  { background:#e8f8ee;color:#1f7a49; }
.recon-chip.bad { background:#fde9e8;color:#b42318; }
.recon-audit-wrap { border:1px solid #ead7cc;border-radius:18px;background:linear-gradient(180deg,#fffaf6 0%,#fff 100%);padding:1rem 1.05rem; }
.recon-audit-summary { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin-bottom:1rem; }
.recon-audit-box { border:1px solid #efdfd3;border-radius:14px;background:#fff;padding:.65rem .75rem; }
.recon-audit-box .label { display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#8a5b4d;font-weight:800; }
.recon-audit-box .value { display:block;font-size:.98rem;font-weight:900;color:#45261d;margin-top:.15rem; }
.recon-aksi-cell { text-align:center; }
.recon-aksi-wrap { display:flex;gap:4px;justify-content:center; }
.recon-aksi-wrap .btn { width:58px;height:30px;font-size:.68rem;border-radius:8px;padding:0;display:flex;align-items:center;justify-content:center; }
@media (max-width:767.98px) {
  .recon-audit-summary { grid-template-columns:1fr 1fr; }
}
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Rekonsiliasi Base/Prepare')); ?></h4>
  <small class="text-muted">Bandingkan saldo live, closing harian, dan movement log — per tanggal acuan <strong><?php echo html_escape($asOfDate); ?></strong></small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'reconcile']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-reconcile'),
  'component_type_filters'  => $filters,
  'component_type_active'   => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
  'component_action_params' => array_filter([
    'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
    'location_type' => (string)($filters['location_type'] ?? ''),
  ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<!-- 6 Summary Cards -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card">
      <div class="lbl">Total Baris</div>
      <div class="val"><?php echo number_format($totalRows, 0, ',', '.'); ?></div>
      <div class="sub">identity component</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card green">
      <div class="lbl">Match</div>
      <div class="val"><?php echo number_format($cntMatch, 0, ',', '.'); ?></div>
      <div class="sub">semua delta = 0</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card <?php echo $cntMismatch > 0 ? 'red' : ''; ?>">
      <div class="lbl">Mismatch</div>
      <div class="val"><?php echo number_format($cntMismatch, 0, ',', '.'); ?></div>
      <div class="sub">ada selisih antar tabel</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card <?php echo $matchRate >= 90 ? 'green' : ($matchRate >= 70 ? 'amber' : 'red'); ?>">
      <div class="lbl">Match Rate</div>
      <div class="val"><?php echo number_format($matchRate, 1, ',', '.'); ?>%</div>
      <div class="sub">dari <?php echo $totalRows; ?> baris</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card blue">
      <div class="lbl">Komponen Unik</div>
      <div class="val"><?php echo number_format($uniqueCompCount, 0, ',', '.'); ?></div>
      <div class="sub">distinct component_id</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="recon-sum-card <?php echo $maxAbsDelta > 0.0001 ? 'amber' : 'green'; ?>">
      <div class="lbl">Selisih Terbesar</div>
      <div class="val" style="font-size:.82rem"><?php echo $fmtQty($maxAbsDelta); ?></div>
      <div class="sub">max abs delta</div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3 recon-filter-card border-0">
  <div class="card-body py-2">
    <form method="get" action="<?php echo site_url('production/component-reconcile'); ?>" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Dari</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape($dateFrom); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Sampai / Per</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape($dateTo); ?>">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Cari</label>
        <input type="text" name="q" id="recon-search" class="form-control form-control-sm" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Kode / nama" style="min-width:145px">
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Lokasi</label>
        <select name="location_type" class="form-select form-select-sm" style="min-width:100px">
          <?php foreach ($locationOptions as $value => $label): ?>
            <option value="<?php echo html_escape((string)$value); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$value) ? 'selected' : ''; ?>><?php echo html_escape((string)$label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Divisi</label>
        <select name="division_id" class="form-select form-select-sm" style="min-width:100px">
          <option value="0">Semua</option>
          <?php foreach ($divisions as $division): ?>
            <option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0)) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($division['division_name'] ?? $division['division_code'] ?? ('Divisi #' . (int)($division['id'] ?? 0)))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label mb-1" style="font-size:.78rem">Per Hal</label>
        <select name="per_page" class="form-select form-select-sm" style="min-width:70px">
          <?php foreach ([10, 25, 50, 100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-1">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
        <a href="<?php echo html_escape(site_url('production/component-reconcile')); ?>" class="btn btn-outline-danger btn-sm">Reset</a>
      </div>
      <div class="col-auto ms-auto">
        <button type="button" id="recon-repair-all-btn" class="btn btn-danger btn-sm d-flex align-items-center gap-1">
          <i class="ri ri-refresh-line"></i> Repair All
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Repair All Toast -->
<div id="repair-all-toast" class="d-none position-fixed bottom-0 end-0 p-3" style="z-index:1100">
  <div class="toast show align-items-center border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="repair-all-toast-msg">Sedang repair...</div>
      <button type="button" class="btn-close me-2 m-auto" onclick="document.getElementById('repair-all-toast').classList.add('d-none')"></button>
    </div>
  </div>
</div>

<!-- Reconcile Table -->
<div class="card mb-3 recon-table-card border-0">
  <div class="recon-tbl-wrap">
    <table class="table table-hover recon-tbl">
      <thead>
        <tr>
          <th style="width:150px">Component</th>
          <th style="width:98px">Divisi / Lokasi</th>
          <th style="width:80px" class="text-end">Saldo Live</th>
          <th style="width:80px" class="text-end">Proyeksi</th>
          <th style="width:80px" class="text-end">Movement</th>
          <th style="width:78px" class="text-end">Δ Live/Daily</th>
          <th style="width:78px" class="text-end">Δ Live/Mvmt</th>
          <th style="width:78px" class="text-end">Δ Daily/Mvmt</th>
          <th style="width:68px">Status</th>
          <th style="width:118px" class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody id="recon-tbody">
        <?php if (empty($rows)): ?>
          <tr class="recon-row"><td colspan="10" class="text-center text-muted py-4">Belum ada data reconcile component.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $isMatch = !empty($row['is_match']);
              $searchStr = strtolower(implode(' ', [
                $row['component_name'] ?? '',
                $row['component_code'] ?? '',
                $row['component_type'] ?? '',
                $row['division_name'] ?? '',
                $row['location_type'] ?? '',
                $isMatch ? 'match ok' : 'mismatch selisih',
              ]));
              $d1 = abs((float)($row['delta_balance_daily'] ?? 0));
              $d2 = abs((float)($row['delta_balance_movement'] ?? 0));
              $d3 = abs((float)($row['delta_daily_movement'] ?? 0));
            ?>
            <tr class="recon-row" data-search="<?php echo html_escape($searchStr); ?>">
              <td style="overflow:hidden">
                <div class="fw-bold" style="font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo html_escape((string)($row['component_name'] ?? '-')); ?>"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                <div class="text-muted" style="font-size:.66rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['component_code'] ?? '-')); ?><?php if (!empty($row['uom_code'])): ?> · <?php echo html_escape((string)$row['uom_code']); ?><?php endif; ?></div>
              </td>
              <td style="overflow:hidden">
                <div style="font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                <div class="text-muted" style="font-size:.66rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['location_type'] ?? '-')); ?></div>
              </td>
              <td class="text-end" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo $fmtQty($row['balance_qty'] ?? 0); ?></td>
              <td class="text-end" style="overflow:hidden">
                <div style="font-size:.77rem;white-space:nowrap"><?php echo $fmtQty($row['daily_qty'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.64rem;white-space:nowrap;overflow:hidden"><?php echo html_escape((string)($row['daily_date'] ?? '')); ?></div>
              </td>
              <td class="text-end" style="overflow:hidden">
                <div style="font-size:.77rem;white-space:nowrap"><?php echo $fmtQty($row['movement_qty'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.64rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo html_escape((string)($row['movement_no'] ?? '')); ?></div>
              </td>
              <td class="text-end fw-bold <?php echo $d1 < 0.0001 ? 'text-success' : 'text-danger'; ?>" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo $fmtQty($row['delta_balance_daily'] ?? 0); ?></td>
              <td class="text-end fw-bold <?php echo $d2 < 0.0001 ? 'text-success' : 'text-danger'; ?>" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo $fmtQty($row['delta_balance_movement'] ?? 0); ?></td>
              <td class="text-end fw-bold <?php echo $d3 < 0.0001 ? 'text-success' : 'text-danger'; ?>" style="font-size:.77rem;white-space:nowrap;overflow:hidden"><?php echo $fmtQty($row['delta_daily_movement'] ?? 0); ?></td>
              <td><span class="recon-chip <?php echo $isMatch ? 'ok' : 'bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span></td>
              <td class="recon-aksi-cell">
                <div class="recon-aksi-wrap">
                  <button type="button" class="btn btn-outline-primary comp-reconcile-audit-btn"
                    data-as-of-date="<?php echo html_escape($asOfDate); ?>"
                    data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                    data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                    data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                    data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>">Audit</button>
                  <button type="button" class="btn btn-outline-danger comp-reconcile-repair-btn"
                    data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                    data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                    data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                    data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>">Fix</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="d-flex justify-content-between align-items-center px-3 py-2 flex-wrap gap-2">
    <div id="recon-info" class="text-muted" style="font-size:.78rem"></div>
    <div id="recon-pager" class="d-flex gap-1 flex-wrap"></div>
  </div>
</div>

<!-- Audit Panel -->
<div class="card border-0" style="border-radius:20px;border-color:#ead7cc !important;box-shadow:0 16px 34px rgba(58,38,30,.06);">
  <div class="card-body p-3 p-lg-4">
    <div class="recon-audit-wrap">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <div class="small text-uppercase fw-bold text-muted">Audit Component</div>
          <h5 class="mb-1 mt-2">Telusuri Sumber Selisih</h5>
          <div class="text-muted small">Audit menampilkan bucket OPENING, PRODUCTION, TRANSFER, VOID, REFUND, ADJUSTMENT, POS, serta detail movement-nya.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger d-none" id="comp_reconcile_repair_current">Repair Component Ini</button>
      </div>
      <div id="comp_reconcile_state" class="text-muted small">Belum ada component yang dipilih — klik tombol Audit pada baris di atas.</div>
      <div id="comp_reconcile_body" class="d-none">
        <div id="comp_reconcile_summary" class="recon-audit-summary"></div>
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
(() => {
  // — Pagination —
  const perPage = <?php echo (int)$perPage; ?>;

  function buildPager(pagerId, infoId, total, page, onClick) {
    const pager = document.getElementById(pagerId);
    const info  = document.getElementById(infoId);
    if (!pager) return;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (info) {
      const s = total > 0 ? ((page - 1) * perPage + 1) : 0;
      const e = Math.min(page * perPage, total);
      info.textContent = total > 0
        ? ('Menampilkan ' + s.toLocaleString('id-ID') + '–' + e.toLocaleString('id-ID') + ' dari ' + total.toLocaleString('id-ID') + ' baris')
        : 'Tidak ada data';
    }
    if (totalPages <= 1) { pager.innerHTML = ''; return; }
    const pages = new Set([1]);
    for (let p = Math.max(2, page - 1); p <= Math.min(totalPages - 1, page + 1); p++) pages.add(p);
    pages.add(totalPages);
    let html = '', last = 0;
    for (const p of pages) {
      if (last > 0 && p - last > 1) html += '<span class="btn btn-sm btn-light disabled px-2">…</span>';
      html += '<button class="btn btn-sm ' + (p === page ? 'btn-dark' : 'btn-outline-secondary') + ' px-2" data-p="' + p + '">' + p + '</button>';
      last = p;
    }
    pager.innerHTML = html;
    pager.querySelectorAll('button[data-p]').forEach(btn => btn.addEventListener('click', () => onClick(+btn.dataset.p)));
  }

  function applyFilter(searchVal, page) {
    const allRows = Array.from(document.querySelectorAll('#recon-tbody tr.recon-row'));
    const needle  = searchVal.trim().toLowerCase();
    const visible = allRows.filter(r => !needle || (r.dataset.search || '').includes(needle));
    allRows.forEach(r => { r.style.display = 'none'; });
    const start = (page - 1) * perPage;
    visible.forEach((r, i) => { r.style.display = (i >= start && i < start + perPage) ? '' : 'none'; });
    buildPager('recon-pager', 'recon-info', visible.length, page, p => applyFilter(searchVal, p));
  }

  const searchInput = document.getElementById('recon-search');
  searchInput?.addEventListener('input', () => applyFilter(searchInput.value, 1));
  applyFilter(searchInput ? searchInput.value : '', 1);

  // — Audit / Repair JS —
  const auditState = document.getElementById('comp_reconcile_state');
  const auditBody = document.getElementById('comp_reconcile_body');
  const summaryEl = document.getElementById('comp_reconcile_summary');
  const bucketEl = document.getElementById('comp_reconcile_buckets');
  const movementEl = document.getElementById('comp_reconcile_movements');
  const repairCurrentBtn = document.getElementById('comp_reconcile_repair_current');
  let currentIdentity = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
  function fmtQty(v) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(Number(v || 0));
  }
  function showAlert(message, title) {
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') return Promise.resolve(window.FinanceUI.alert(message, { title: title || 'Informasi' }));
    alert(message); return Promise.resolve();
  }
  function askConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') return Promise.resolve(window.FinanceUI.confirm(message, options || {}));
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
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') { window.FinanceUI.setButtonLoading(button, label); return; }
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + escapeHtml(label || 'Memproses...');
  }
  function clearButtonLoading(button) {
    if (!button) return;
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') { window.FinanceUI.clearButtonLoading(button); return; }
    button.disabled = false;
    if (button.dataset.originalHtml) { button.innerHTML = button.dataset.originalHtml; delete button.dataset.originalHtml; }
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
    currentIdentity = { location_type: String(identity.location_type || ''), division_id: Number(identity.division_id || 0), component_id: Number(identity.component_id || 0), uom_id: Number(identity.uom_id || 0) };
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
      ['Daily vs Movement', fmtQty(summary.delta_daily_movement || 0), 'closing daily ' + escapeHtml(summary.daily_date || '-')],
      ['Lokasi', escapeHtml(summary.location_type || '-'), escapeHtml(summary.division_name || '-')]
    ].map(item => '<div class="recon-audit-box"><span class="label">' + item[0] + '</span><span class="value">' + item[1] + '</span><div class="text-muted small">' + item[2] + '</div></div>').join('');
    bucketEl.innerHTML = buckets.map(bucket => '<tr><td>' + escapeHtml(bucket.bucket_label || bucket.bucket_code || '-') + '</td><td class="text-end">' + Number(bucket.count || 0) + '</td><td class="text-end">' + fmtQty(bucket.delta_qty || 0) + '</td><td class="text-end">' + fmtQty(bucket.mutation_value || 0) + '</td><td>' + escapeHtml(bucket.last_movement_date || '-') + '<div class="text-muted small">' + escapeHtml(bucket.last_movement_no || '-') + '</div></td></tr>').join('') || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada bucket.</td></tr>';
    movementEl.innerHTML = movements.map(row => '<tr><td>' + escapeHtml(row.movement_date || '-') + '</td><td>' + escapeHtml(row.movement_no || '-') + '</td><td>' + escapeHtml(row.source_label || '-') + '<div class="text-muted small">' + escapeHtml(row.source_bucket_label || '-') + '</div></td><td class="text-end">' + fmtQty(row.qty_before || 0) + '</td><td class="text-end">' + fmtQty(row.qty_delta || 0) + '</td><td class="text-end">' + fmtQty(row.qty_after || 0) + '</td><td>' + escapeHtml(row.movement_type_label || row.movement_type || '-') + '</td><td>' + escapeHtml(row.notes || '-') + '</td></tr>').join('') || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada movement.</td></tr>';
  }
  async function loadAudit(identity) {
    currentIdentity = { location_type: String(identity.location_type || ''), division_id: Number(identity.division_id || 0), component_id: Number(identity.component_id || 0), uom_id: Number(identity.uom_id || 0) };
    setState('Memuat audit component...', true);
    const params = new URLSearchParams();
    Object.keys(identity || {}).forEach(key => params.set(key, String(identity[key] ?? '')));
    const json = await getJson('<?php echo site_url('production/component-reconcile/audit'); ?>?' + params.toString());
    renderAudit(identity, json);
    auditBody.closest('.card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  async function runRepair(identity, button) {
    const confirmed = await askConfirm('Repair component ini akan rebuild artefak kompatibilitas dan sinkronkan ulang dari movement log untuk identity yang dipilih. Lanjutkan?', {
      title: 'Repair Reconcile Component', confirmText: 'Repair', cancelText: 'Batal'
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

  repairCurrentBtn?.addEventListener('click', function () {
    if (!currentIdentity) return;
    runRepair(currentIdentity, repairCurrentBtn).catch(e => { clearButtonLoading(repairCurrentBtn); setState(e.message, false); });
  });

  document.getElementById('recon-repair-all-btn')?.addEventListener('click', async function () {
    const btn = this;
    const confirmed = await askConfirm(
      'Repair All akan rebuild inv_component_monthly_stock untuk SEMUA identity component dari movement log. Proses ini mungkin membutuhkan beberapa saat. Lanjutkan?',
      { title: 'Repair All Component', confirmText: 'Repair All', cancelText: 'Batal' }
    );
    if (!confirmed) return;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Repair All...';
    const toast = document.getElementById('repair-all-toast');
    const toastMsg = document.getElementById('repair-all-toast-msg');
    toastMsg.textContent = 'Sedang repair semua component...';
    toast.classList.remove('d-none');
    try {
      const params = {};
      const locSel = document.querySelector('select[name="location_type"]');
      const divSel = document.querySelector('select[name="division_id"]');
      if (locSel?.value) params.location_type = locSel.value;
      if (divSel?.value && divSel.value !== '0') params.division_id = parseInt(divSel.value, 10);
      const json = await postJson('<?php echo site_url('production/component-reconcile/repair-all'); ?>', params);
      toastMsg.textContent = json.message || 'Repair selesai.';
      await showAlert((json.message || 'Repair selesai.') + (json.errors?.length ? '\n\nGagal: ' + json.errors.slice(0,5).join('; ') : ''), 'Repair All Selesai');
      window.location.reload();
    } catch (e) {
      toastMsg.textContent = 'Repair gagal: ' + e.message;
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });

  document.addEventListener('click', function (event) {
    const auditBtn = event.target.closest('.comp-reconcile-audit-btn');
    if (auditBtn) { loadAudit(identityFromButton(auditBtn)).catch(e => setState(e.message, false)); return; }
    const repairBtn = event.target.closest('.comp-reconcile-repair-btn');
    if (repairBtn) { runRepair(repairIdentityFromButton(repairBtn), repairBtn).catch(e => { clearButtonLoading(repairBtn); setState(e.message, false); }); }
  });
})();
</script>
