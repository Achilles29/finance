<?php
$initialMonth = (string)($month ?? date('Y-m'));
$initialQ = (string)($q ?? '');
$initialDateFrom = (string)($date_from ?? '');
$initialDateTo = (string)($date_to ?? '');
$initialDivisionId = (int)($division_id ?? 0);
$initialDestination = strtoupper(trim((string)($destination ?? 'ALL')));
if ($initialDestination === '') {
  $initialDestination = 'ALL';
}
$initialLimit = (int)($limit ?? 120);
if ($initialLimit <= 0 || $initialLimit > 1000) {
  $initialLimit = 120;
}
$divisionOptions = is_array($divisions ?? null) ? $divisions : [];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title ?? 'Inventory Material Daily'); ?></h4>
    <small class="text-muted">Matrix stok bahan baku per divisi/tujuan, bisa digabung item lalu expand ke profil.</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo site_url('purchase/stock/division'); ?>" class="btn btn-outline-secondary">Stok Divisi Live</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Daily Divisi (List)</a>
    <a href="<?php echo site_url('inventory-warehouse-daily'); ?>" class="btn btn-outline-primary">Warehouse Daily Matrix</a>
  </div>
</div>

<style>
  :root {
    --pmd-col-division: 146px;
    --pmd-col-material: 176px;
    --pmd-col-profile: 166px;
    --pmd-col-summary: 176px;
    --pmd-left-1: 0px;
    --pmd-left-2: var(--pmd-col-division);
    --pmd-left-3: calc(var(--pmd-col-division) + var(--pmd-col-material));
    --pmd-left-4: calc(var(--pmd-col-division) + var(--pmd-col-material) + var(--pmd-col-profile));
    --pmd-date-col: 98px;
    --pmd-header-row-1: 44px;
  }
  .pmd-filter-card,
  .pmd-board-card,
  .pmd-table,
  .pmd-modal-card {
    font-family: "Trebuchet MS", Verdana, sans-serif;
  }
  .pmd-filter-card {
    border: 1px solid #ead9cf;
    border-radius: 16px;
    box-shadow: 0 12px 24px rgba(104, 43, 40, 0.06);
  }
  .pmd-board-card {
    border: 1px solid #ead9cf;
    border-radius: 18px;
    overflow: visible;
    box-shadow: 0 14px 28px rgba(104, 43, 40, 0.08);
  }
  .pmd-board-head {
    background: linear-gradient(135deg, #5f2432, #8c3f49);
    color: #fff;
    padding: 0.8rem 0.95rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }
  .pmd-legend { display: flex; flex-wrap: wrap; gap: 0.4rem; }
  .pmd-legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: rgba(255, 255, 255, 0.14);
    font-size: 0.75rem;
    font-weight: 700;
  }
  .pmd-legend-dot { width: 8px; height: 8px; border-radius: 999px; }
  .pmd-dot-open { background: #2f5b95; }
  .pmd-dot-in { background: #2bb673; }
  .pmd-dot-out { background: #f17f43; }
  .pmd-dot-adj { background: #7f64d9; }
  .pmd-dot-close { background: #4e6b83; }
  .pmd-table-wrap {
    max-height: 68vh;
    overflow: auto;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
  }
  .pmd-table {
    min-width: 1720px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pmd-table thead th,
  .pmd-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pmd-table thead tr:first-child th {
    position: sticky;
    top: 0;
    z-index: 12;
    background: #fff8f4;
    border-bottom: 1px solid #ebd8cf;
    color: #602739;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    white-space: nowrap;
  }
  .pmd-table thead tr:nth-child(2) th {
    position: sticky;
    top: var(--pmd-header-row-1);
    z-index: 11;
    background: #fff2ec;
    border-bottom: 1px solid #ebd8cf;
    color: #7b4f49;
    font-size: 0.73rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    width: var(--pmd-date-col);
    min-width: var(--pmd-date-col);
    max-width: var(--pmd-date-col);
  }
  .pmd-sticky {
    position: sticky !important;
    background: #fffaf8;
    box-shadow: inset -2px 0 0 #e7cfc4, 10px 0 16px -14px rgba(69, 22, 24, 0.35);
    z-index: 9;
  }
  .pmd-sticky-1 { left: var(--pmd-left-1); width: var(--pmd-col-division); min-width: var(--pmd-col-division); max-width: var(--pmd-col-division); }
  .pmd-sticky-2 { left: var(--pmd-left-2); width: var(--pmd-col-material); min-width: var(--pmd-col-material); max-width: var(--pmd-col-material); }
  .pmd-sticky-3 { left: var(--pmd-left-3); width: var(--pmd-col-profile); min-width: var(--pmd-col-profile); max-width: var(--pmd-col-profile); }
  .pmd-sticky-4 { left: var(--pmd-left-4); width: var(--pmd-col-summary); min-width: var(--pmd-col-summary); max-width: var(--pmd-col-summary); border-right: 4px solid #d2a08e; z-index: 13; }
  .pmd-day-head {
    width: calc(var(--pmd-date-col) * 5);
    min-width: calc(var(--pmd-date-col) * 5);
    max-width: calc(var(--pmd-date-col) * 5);
  }
  .pmd-day-head.is-today {
    background: linear-gradient(180deg, #ffcfbe, #ffa37f) !important;
    box-shadow: inset 0 -4px 0 #c75a39;
    color: #4e1d12 !important;
  }
  .pmd-day-weekday {
    display: block;
    font-size: 0.72rem;
    letter-spacing: 0.11em;
    opacity: 0.86;
  }
  .pmd-division-pill {
    display: inline-flex;
    padding: 0.16rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #ebd4ca;
    background: #fff2ec;
    color: #8b3c47;
    font-size: 0.69rem;
    font-weight: 800;
  }
  .pmd-name { font-weight: 800; color: #4e1f2e; line-height: 1.28; }
  .pmd-code { color: #876a65; font-size: 0.79rem; margin-top: 0.14rem; }
  .pmd-profile-line {
    color: #6e5652;
    font-size: 0.82rem;
    line-height: 1.25;
    white-space: normal;
    word-break: break-word;
  }
  .pmd-profile-unit {
    margin-top: 0.18rem;
    color: #7d5648;
    font-size: 0.79rem;
    font-weight: 700;
  }
  .pmd-group-row {
    background: #fff9f6;
    border-top: 2px solid #f0d9cf;
  }
  .pmd-group-row.pmd-group-expandable {
    background: linear-gradient(90deg, #fff4eb, #fffaf6);
  }
  .pmd-group-row.pmd-group-single {
    background: linear-gradient(90deg, #f8fff5, #fafff8);
  }
  .pmd-child-row {
    background: #fff;
  }
  .pmd-toggle-arrow {
    border: 1px solid #d7b6a8;
    background: #fff;
    color: #6a2d3c;
    border-radius: 8px;
    width: 26px;
    height: 24px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.3rem;
    font-size: 0.85rem;
    font-weight: 800;
  }
  .pmd-toggle-arrow:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .pmd-toggle-static {
    display: inline-flex;
    width: 26px;
    height: 24px;
    align-items: center;
    justify-content: center;
    margin-right: 0.3rem;
    border-radius: 8px;
    background: #e9f8df;
    color: #3e7f32;
    font-size: 0.8rem;
    font-weight: 800;
  }
  .pmd-group-toggle {
    width: 100%;
    border: 1px solid #e8cfc4;
    background: #fff4ee;
    color: #6a2d3c;
    border-radius: 10px;
    padding: 0.22rem 0.4rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
  }
  .pmd-group-toggle:hover {
    background: #ffe8dd;
    border-color: #d7b3a5;
  }
  .pmd-summary-line {
    font-size: 0.78rem;
    line-height: 1.25;
    color: #6f4d47;
    white-space: normal;
  }
  .pmd-summary-line strong {
    color: #4e2430;
    font-weight: 800;
  }
  .pmd-metric-cell {
    width: var(--pmd-date-col);
    min-width: var(--pmd-date-col);
    max-width: var(--pmd-date-col);
    text-align: right;
    font-size: 0.79rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    background: #fff;
  }
  .pmd-metric-cell.is-today {
    background: #fff4ed;
  }
  .pmd-metric-cell .pmd-cell-btn {
    border: 1px solid #f0e1db;
    border-radius: 8px;
    display: inline-block;
    padding: 0.18rem 0.26rem;
    width: 100%;
    text-align: right;
    white-space: nowrap;
    line-height: 1.2;
    background: #fff;
    font-size: 0.77rem;
    color: #5f4b46;
  }
  .pmd-metric-cell .pmd-cell-btn:hover {
    border-color: #d6b2a5;
    background: #fff8f3;
  }
  .pmd-metric-open { color: #2f5b95; }
  .pmd-metric-in { color: #248c5d; }
  .pmd-metric-out { color: #cd6b35; }
  .pmd-metric-adj { color: #6a52cf; }
  .pmd-metric-close { color: #4f647d; font-weight: 800; }
  .pmd-date-band-a {
    background-color: #fffefc;
  }
  .pmd-date-band-b {
    background-color: #fff6f1;
  }
  .pmd-date-band-b .pmd-cell-btn {
    background: rgba(255, 255, 255, 0.56);
  }
  .pmd-empty,
  .pmd-loading {
    text-align: center;
    color: #8e6f67;
    padding: 2rem 1rem;
  }
  .pmd-stat-card {
    border: 1px solid #ead9cf;
    border-radius: 14px;
    background: #fff;
    padding: 0.6rem 0.78rem;
  }
  .pmd-stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #8d6b64; font-weight: 800; }
  .pmd-stat-value { margin-top: 0.22rem; font-size: 1.25rem; font-weight: 800; color: #5f2432; }
  .pmd-modal-card {
    border: 1px solid #e9d9d1;
    border-radius: 12px;
    background: #fff9f6;
    padding: 0.75rem 0.9rem;
  }
  @media (max-width: 991.98px) {
    .pmd-sticky { position: static; box-shadow: none; }
    .pmd-sticky-1, .pmd-sticky-2, .pmd-sticky-3, .pmd-sticky-4 { width: auto; min-width: auto; max-width: none; }
    .pmd-table { min-width: 1420px; }
    .pmd-day-head { min-width: 300px; }
  }
</style>

<div id="pmdActionMsg" class="mb-2"></div>

<div class="card pmd-filter-card mb-2">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" id="pmdMonth" class="form-control" value="<?php echo html_escape(substr($initialMonth, 0, 7)); ?>">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label mb-1">Divisi</label>
        <select id="pmdDivision" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisionOptions as $d): ?>
            <?php
              $id = (int)($d['id'] ?? 0);
              $code = trim((string)($d['code'] ?? ''));
              $name = trim((string)($d['name'] ?? ''));
              $label = $code !== '' ? $code . ' - ' . $name : ($name !== '' ? $name : (string)$id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo $id === $initialDivisionId ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select id="pmdDestination" class="form-select">
          <option value="ALL" <?php echo $initialDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $initialDestination === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $initialDestination === 'EVENT' ? 'selected' : ''; ?>>Event</option>
          <option value="BAR" <?php echo $initialDestination === 'BAR' ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN" <?php echo $initialDestination === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="BAR_EVENT" <?php echo $initialDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT" <?php echo $initialDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="OFFICE" <?php echo $initialDestination === 'OFFICE' ? 'selected' : ''; ?>>Office Reguler</option>
          <option value="OTHER" <?php echo $initialDestination === 'OTHER' ? 'selected' : ''; ?>>Lainnya</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" id="pmdQ" class="form-control" value="<?php echo html_escape($initialQ); ?>" placeholder="Material, profile, merk, divisi">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" id="pmdDateFrom" class="form-control" value="<?php echo html_escape($initialDateFrom); ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" id="pmdDateTo" class="form-control" value="<?php echo html_escape($initialDateTo); ?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" id="pmdLimit" class="form-control" min="1" max="1000" value="<?php echo (int)$initialLimit; ?>">
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pmdApply" class="btn btn-primary">Terapkan</button>
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pmdClear" class="btn btn-outline-danger">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-2 mb-2">
  <div class="col-6 col-md-3"><div class="pmd-stat-card"><div class="pmd-stat-label">Profil</div><div id="pmdStatProfiles" class="pmd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pmd-stat-card"><div class="pmd-stat-label">Divisi</div><div id="pmdStatDivisions" class="pmd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pmd-stat-card"><div class="pmd-stat-label">Material</div><div id="pmdStatMaterials" class="pmd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pmd-stat-card"><div class="pmd-stat-label">Nilai Sisa</div><div id="pmdStatValue" class="pmd-stat-value">0,00</div></div></div>
</div>

<div class="card pmd-board-card">
  <div class="pmd-board-head">
    <div>
      <div class="fw-semibold">Matrix Daily Inventory Material</div>
    </div>
    <div class="pmd-legend">
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-open"></span>Awal</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-in"></span>In</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-out"></span>Out</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-adj"></span>Adj</span>
      <span class="pmd-legend-pill"><span class="pmd-legend-dot pmd-dot-close"></span>Akhir</span>
    </div>
  </div>
  <div class="pmd-table-wrap" id="pmdTableWrap">
    <table class="table pmd-table align-middle mb-0">
      <thead id="pmdHead"></thead>
      <tbody id="pmdBody"><tr><td colspan="999" class="pmd-loading">Memuat data...</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="pmdDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Mutasi Harian Material</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pmd-modal-card mb-3">
          <div class="fw-semibold" id="pmdModalTitle">-</div>
          <div class="small text-muted" id="pmdModalSubtitle">-</div>
        </div>
        <div id="pmdModalListWrap" class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Tipe</th>
                <th class="text-end">Delta</th>
                <th class="text-end">Saldo</th>
                <th>Ref</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody id="pmdModalBody"><tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var matrixUrl = <?php echo json_encode((string)($matrix_url ?? site_url('inventory-material-daily/matrix'))); ?>;
  var detailUrl = <?php echo json_encode((string)($detail_url ?? site_url('inventory-daily/cell-detail'))); ?>;
  var defaultMonth = <?php echo json_encode(substr($initialMonth, 0, 7)); ?>;

  var state = {
    month: defaultMonth,
    division_id: <?php echo (int)$initialDivisionId; ?>,
    destination: <?php echo json_encode($initialDestination); ?>,
    q: <?php echo json_encode($initialQ); ?>,
    date_from: <?php echo json_encode($initialDateFrom); ?>,
    date_to: <?php echo json_encode($initialDateTo); ?>,
    limit: <?php echo (int)$initialLimit; ?>,
    dates: [],
    groups: [],
    expanded: {}
  };

  var tableHead = document.getElementById('pmdHead');
  var tableBody = document.getElementById('pmdBody');
  var tableWrap = document.getElementById('pmdTableWrap');
  var modalEl = document.getElementById('pmdDetailModal');
  var modal = (modalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;

  function esc(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function num(value){
    var n = Number(value || 0);
    return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function money(value){
    return num(value);
  }

  function weekdayName(dateText){
    var names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    var d = new Date(dateText + 'T00:00:00');
    if (Number.isNaN(d.getTime())) { return ''; }
    return names[d.getDay()] || '';
  }

  function isToday(dateText){
    var now = new Date();
    var yyyy = String(now.getFullYear());
    var mm = String(now.getMonth() + 1).padStart(2, '0');
    var dd = String(now.getDate()).padStart(2, '0');
    return dateText === (yyyy + '-' + mm + '-' + dd);
  }

  function syncStickyLayout(){
    if (!tableHead) { return; }
    var firstRow = tableHead.querySelector('tr');
    if (!firstRow) { return; }

    var col1 = firstRow.querySelector('.pmd-sticky-1');
    var col2 = firstRow.querySelector('.pmd-sticky-2');
    var col3 = firstRow.querySelector('.pmd-sticky-3');
    var col4 = firstRow.querySelector('.pmd-sticky-4');
    if (col1 && col2 && col3 && col4) {
      var w1 = Math.max(120, Math.ceil(col1.getBoundingClientRect().width));
      var w2 = Math.max(130, Math.ceil(col2.getBoundingClientRect().width));
      var w3 = Math.max(130, Math.ceil(col3.getBoundingClientRect().width));
      var w4 = Math.max(130, Math.ceil(col4.getBoundingClientRect().width));
      var rootStyle = document.documentElement.style;
      rootStyle.setProperty('--pmd-col-division', String(w1) + 'px');
      rootStyle.setProperty('--pmd-col-material', String(w2) + 'px');
      rootStyle.setProperty('--pmd-col-profile', String(w3) + 'px');
      rootStyle.setProperty('--pmd-col-summary', String(w4) + 'px');
      rootStyle.setProperty('--pmd-left-1', '0px');
      rootStyle.setProperty('--pmd-left-2', String(w1) + 'px');
      rootStyle.setProperty('--pmd-left-3', String(w1 + w2) + 'px');
      rootStyle.setProperty('--pmd-left-4', String(w1 + w2 + w3) + 'px');
      var firstHeight = Math.ceil(firstRow.getBoundingClientRect().height);
      if (firstHeight > 0) {
        rootStyle.setProperty('--pmd-header-row-1', String(firstHeight) + 'px');
      }
    }

    var metricHead = tableHead.querySelector('tr:nth-child(2) th.pmd-metric-cell');
    if (metricHead) {
      var metricWidth = Math.max(88, Math.ceil(metricHead.getBoundingClientRect().width));
      document.documentElement.style.setProperty('--pmd-date-col', String(metricWidth) + 'px');
    }

    pinStickyColumns();
  }

  function pinStickyColumns(){
    if (!tableWrap) { return; }
    var rootStyle = getComputedStyle(document.documentElement);
    var w1 = Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-division')) || 146;
    var w2 = Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-material')) || 176;
    var w3 = Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-profile')) || 166;
    var left1 = 0;
    var left2 = left1 + w1;
    var left3 = left2 + w2;
    var left4 = left3 + w3;

    tableWrap.querySelectorAll('.pmd-sticky-1').forEach(function(cell){ cell.style.left = String(left1) + 'px'; });
    tableWrap.querySelectorAll('.pmd-sticky-2').forEach(function(cell){ cell.style.left = String(left2) + 'px'; });
    tableWrap.querySelectorAll('.pmd-sticky-3').forEach(function(cell){ cell.style.left = String(left3) + 'px'; });
    tableWrap.querySelectorAll('.pmd-sticky-4').forEach(function(cell){ cell.style.left = String(left4) + 'px'; });
  }

  function getStickyOffset(){
    var offset = 0;
    if (tableHead) {
      var stickyCells = tableHead.querySelectorAll('tr:first-child th.pmd-sticky');
      stickyCells.forEach(function(cell){
        offset += Math.ceil(cell.getBoundingClientRect().width || 0);
      });
    }
    if (offset > 0) {
      return offset;
    }
    var rootStyle = getComputedStyle(document.documentElement);
    return (
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-division')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-material')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-profile')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pmd-col-summary'))
    ) || 0;
  }

  function scrollToTodayColumn(){
    if (!tableWrap) { return; }
    var todayCell = tableHead.querySelector('.pmd-day-head.is-today');
    if (!todayCell) { return; }
    tableWrap.scrollLeft = Math.max(0, todayCell.offsetLeft - getStickyOffset() - 16);
  }

  function readFilters(){
    state.month = document.getElementById('pmdMonth').value || '';
    state.division_id = parseInt(document.getElementById('pmdDivision').value || '0', 10);
    if (!Number.isFinite(state.division_id) || state.division_id < 0) { state.division_id = 0; }
    state.destination = document.getElementById('pmdDestination').value || 'ALL';
    state.q = document.getElementById('pmdQ').value || '';
    state.date_from = document.getElementById('pmdDateFrom').value || '';
    state.date_to = document.getElementById('pmdDateTo').value || '';
    state.limit = parseInt(document.getElementById('pmdLimit').value || '120', 10);
    if (!Number.isFinite(state.limit) || state.limit <= 0 || state.limit > 1000) {
      state.limit = 120;
    }
  }

  function clearFilters(){
    document.getElementById('pmdMonth').value = defaultMonth;
    document.getElementById('pmdDivision').value = '0';
    document.getElementById('pmdDestination').value = 'ALL';
    document.getElementById('pmdQ').value = '';
    document.getElementById('pmdDateFrom').value = '';
    document.getElementById('pmdDateTo').value = '';
    document.getElementById('pmdLimit').value = '120';
    readFilters();
  }

  function buildQuery(){
    var p = new URLSearchParams();
    if (state.month) { p.set('month', state.month); }
    if (state.q) { p.set('q', state.q); }
    if (state.date_from) { p.set('date_from', state.date_from); }
    if (state.date_to) { p.set('date_to', state.date_to); }
    if (state.division_id > 0) { p.set('division_id', String(state.division_id)); }
    if (state.destination && state.destination !== 'ALL') { p.set('destination', state.destination); }
    p.set('limit', String(state.limit));
    return p.toString();
  }

  function parseJson(response){
    return response.text().then(function(text){
      var data = null;
      try { data = JSON.parse(text); } catch (err) { data = null; }
      if (!response.ok) {
        throw new Error((data && data.message) ? data.message : (text || ('HTTP ' + response.status)));
      }
      if (!data) {
        throw new Error('Respons endpoint tidak valid.');
      }
      return data;
    });
  }

  function showMessage(ok, text){
    var box = document.getElementById('pmdActionMsg');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
  }

  function divisionLabel(row){
    var code = row.division_code || '';
    var name = row.division_name || '';
    if (code && name && String(code).toUpperCase() === String(name).toUpperCase()) {
      return code;
    }
    if (code && name) { return code + ' - ' + name; }
    if (name) { return name; }
    if (code) { return code; }
    return String(row.division_id || '-');
  }

  function destinationLabel(row){
    return String(row.destination_group || '').toUpperCase() === 'EVENT' ? 'Event' : 'Reguler';
  }

  function normalizeProfileDaily(rowDaily, dates){
    var source = rowDaily && typeof rowDaily === 'object' ? rowDaily : {};
    var normalized = {};
    var prevClosing = 0;
    var prevValue = 0;
    var initialized = false;

    dates.forEach(function(dateKey){
      var raw = source[dateKey] || null;
      var opening = initialized ? prevClosing : 0;
      var inQty = 0;
      var outQty = 0;
      var adjQty = 0;
      var closing = opening;
      var mutations = 0;
      var totalValue = prevValue;

      if (raw) {
        opening = Number(raw.opening || opening);
        inQty = Number(raw.in || 0);
        outQty = Number(raw.out || 0);
        adjQty = Number(raw.adjustment || 0);
        closing = Number(raw.closing || opening);
        mutations = Number(raw.mutations || 0);
        totalValue = Number(raw.total_value || totalValue);
      }

      normalized[dateKey] = {
        opening: opening,
        in: inQty,
        out: outQty,
        adjustment: adjQty,
        closing: closing,
        mutations: mutations,
        total_value: totalValue
      };

      prevClosing = closing;
      prevValue = totalValue;
      initialized = true;
    });

    return normalized;
  }

  function calcMetrics(dailyByDate, contentPerBuy, dates){
    var inTotal = 0;
    var outTotal = 0;
    var adjTotal = 0;
    var openingContent = 0;
    var closingContent = 0;
    var totalValue = 0;
    var mutationTotal = 0;

    if (dates.length > 0) {
      openingContent = Number((dailyByDate[dates[0]] && dailyByDate[dates[0]].opening) || 0);
      closingContent = Number((dailyByDate[dates[dates.length - 1]] && dailyByDate[dates[dates.length - 1]].closing) || 0);
      totalValue = Number((dailyByDate[dates[dates.length - 1]] && dailyByDate[dates[dates.length - 1]].total_value) || 0);
    }

    dates.forEach(function(dateKey){
      var day = dailyByDate[dateKey] || {};
      inTotal += Number(day.in || 0);
      outTotal += Number(day.out || 0);
      adjTotal += Number(day.adjustment || 0);
      mutationTotal += Number(day.mutations || 0);
    });

    var packSize = Number(contentPerBuy || 0);
    var openingPack = packSize > 0 ? openingContent / packSize : 0;
    var closingPack = packSize > 0 ? closingContent / packSize : 0;
    var unitPrice = closingContent !== 0 ? totalValue / closingContent : 0;
    var unitPricePack = unitPrice * (packSize > 0 ? packSize : 0);

    return {
      in_total: inTotal,
      out_total: outTotal,
      adjustment_total: adjTotal,
      opening_content: openingContent,
      closing_content: closingContent,
      opening_pack: openingPack,
      closing_pack: closingPack,
      total_value: totalValue,
      hpp: unitPrice,
      unit_price: unitPrice,
      unit_price_pack: unitPricePack,
      mutation_total: mutationTotal
    };
  }

  function buildGroups(rows, dates){
    var map = {};
    var order = [];

    (rows || []).forEach(function(row){
      var key = [
        Number(row.division_id || 0),
        String(row.destination_type || 'OTHER'),
        Number(row.item_id || 0),
        Number(row.material_id || 0)
      ].join('|');

      if (!map[key]) {
        map[key] = {
          key: key,
          division_id: Number(row.division_id || 0),
          division_code: String(row.division_code || ''),
          division_name: String(row.division_name || ''),
          destination_type: String(row.destination_type || 'OTHER'),
          destination_group: String(row.destination_group || 'REGULER'),
          destination_name: String(row.destination_name || 'Reguler'),
          item_id: Number(row.item_id || 0),
          material_id: Number(row.material_id || 0),
          item_code: String(row.item_code || ''),
          item_name: String(row.item_name || ''),
          material_code: String(row.material_code || ''),
          material_name: String(row.material_name || ''),
          children: [],
          daily: {},
          metrics: null
        };
        order.push(key);
      }

      var profileDaily = normalizeProfileDaily(row.daily || {}, dates);
      var profileMetrics = calcMetrics(profileDaily, Number(row.profile_content_per_buy || 0), dates);

      map[key].children.push({
        division_id: Number(row.division_id || 0),
        destination_type: String(row.destination_type || 'OTHER'),
        stock_domain: 'MATERIAL',
        item_id: Number(row.item_id || 0),
        material_id: Number(row.material_id || 0),
        buy_uom_id: Number(row.buy_uom_id || 0),
        content_uom_id: Number(row.content_uom_id || 0),
        profile_key: String(row.profile_key || ''),
        profile_name: String(row.profile_name || ''),
        profile_brand: String(row.profile_brand || ''),
        profile_description: String(row.profile_description || ''),
        profile_content_per_buy: Number(row.profile_content_per_buy || 0),
        profile_buy_uom_code: String(row.profile_buy_uom_code || ''),
        profile_content_uom_code: String(row.profile_content_uom_code || ''),
        daily: profileDaily,
        metrics: profileMetrics
      });
    });

    order.forEach(function(key){
      var group = map[key];
      var summaryDaily = {};
      dates.forEach(function(dateKey){
        summaryDaily[dateKey] = { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
      });

      var agg = {
        in_total: 0,
        out_total: 0,
        adjustment_total: 0,
        opening_content: 0,
        closing_content: 0,
        opening_pack: 0,
        closing_pack: 0,
        total_value: 0,
        mutation_total: 0,
        hpp: 0
      };

      group.children.forEach(function(child){
        dates.forEach(function(dateKey){
          var d = child.daily[dateKey] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0, mutations: 0, total_value: 0 };
          summaryDaily[dateKey].opening += Number(d.opening || 0);
          summaryDaily[dateKey].in += Number(d.in || 0);
          summaryDaily[dateKey].out += Number(d.out || 0);
          summaryDaily[dateKey].adjustment += Number(d.adjustment || 0);
          summaryDaily[dateKey].closing += Number(d.closing || 0);
          summaryDaily[dateKey].mutations += Number(d.mutations || 0);
          summaryDaily[dateKey].total_value += Number(d.total_value || 0);
        });

        agg.in_total += Number(child.metrics.in_total || 0);
        agg.out_total += Number(child.metrics.out_total || 0);
        agg.adjustment_total += Number(child.metrics.adjustment_total || 0);
        agg.opening_content += Number(child.metrics.opening_content || 0);
        agg.closing_content += Number(child.metrics.closing_content || 0);
        agg.opening_pack += Number(child.metrics.opening_pack || 0);
        agg.closing_pack += Number(child.metrics.closing_pack || 0);
        agg.total_value += Number(child.metrics.total_value || 0);
        agg.mutation_total += Number(child.metrics.mutation_total || 0);
      });

      if (agg.closing_content !== 0) {
        agg.hpp = agg.total_value / agg.closing_content;
      } else if (group.children.length > 0) {
        var sumHpp = 0;
        group.children.forEach(function(child){ sumHpp += Number(child.metrics.hpp || 0); });
        agg.hpp = sumHpp / group.children.length;
      }

      group.daily = summaryDaily;
      group.metrics = agg;
    });

    return order.map(function(key){ return map[key]; });
  }

  function calcStats(groups){
    var profileCount = 0;
    var divisionSet = {};
    var materialSet = {};
    var totalValue = 0;

    (groups || []).forEach(function(group){
      profileCount += (group.children || []).length;
      if (group.division_id) { divisionSet[String(group.division_id)] = true; }
      if (group.material_id) { materialSet[String(group.material_id)] = true; }
      totalValue += Number((group.metrics && group.metrics.total_value) || 0);
    });

    document.getElementById('pmdStatProfiles').textContent = profileCount.toLocaleString('id-ID');
    document.getElementById('pmdStatDivisions').textContent = Object.keys(divisionSet).length.toLocaleString('id-ID');
    document.getElementById('pmdStatMaterials').textContent = Object.keys(materialSet).length.toLocaleString('id-ID');
    document.getElementById('pmdStatValue').textContent = money(totalValue);
  }

  function summaryParentHtml(metrics){
    return '' +
      '<div class="pmd-summary-line"><strong>HPP Rata2:</strong> ' + esc(money(metrics.hpp || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Awal Pack:</strong> ' + esc(num(metrics.opening_pack || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Akhir Pack:</strong> ' + esc(num(metrics.closing_pack || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Awal Isi:</strong> ' + esc(num(metrics.opening_content || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Akhir Isi:</strong> ' + esc(num(metrics.closing_content || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Nilai Sisa:</strong> ' + esc(money(metrics.total_value || 0)) + '</div>';
  }

  function summaryChildHtml(metrics){
    return '' +
      '<div class="pmd-summary-line"><strong>Awal Pack:</strong> ' + esc(num(metrics.opening_pack || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Akhir Pack:</strong> ' + esc(num(metrics.closing_pack || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Awal Isi:</strong> ' + esc(num(metrics.opening_content || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Akhir Isi:</strong> ' + esc(num(metrics.closing_content || 0)) + '</div>' +
      '<div class="pmd-summary-line"><strong>Nilai Sisa:</strong> ' + esc(money(metrics.total_value || 0)) + '</div>';
  }

  function headerHtml(){
    var dayTop = state.dates.map(function(dateText, dateIndex){
      var cls = 'pmd-day-head' + (isToday(dateText) ? ' is-today' : '');
      var bandClass = dateIndex % 2 === 0 ? 'pmd-date-band-a' : 'pmd-date-band-b';
      return '<th class="' + cls + ' ' + bandClass + '" colspan="5"><div>' + esc(String(dateText).slice(-2)) + '</div><span class="pmd-day-weekday">' + esc(weekdayName(dateText)) + '</span></th>';
    }).join('');

    var daySub = state.dates.map(function(dateText, dateIndex){
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pmd-date-band-a' : ' pmd-date-band-b';
      return '' +
        '<th class="pmd-metric-cell' + todayCls + bandClass + '">Awal</th>' +
        '<th class="pmd-metric-cell' + todayCls + bandClass + '">In</th>' +
        '<th class="pmd-metric-cell' + todayCls + bandClass + '">Out</th>' +
        '<th class="pmd-metric-cell' + todayCls + bandClass + '">Adj</th>' +
        '<th class="pmd-metric-cell' + todayCls + bandClass + '">Akhir</th>';
    }).join('');

    return '' +
      '<tr>' +
        '<th class="pmd-sticky pmd-sticky-1" rowspan="2">Divisi / Tujuan</th>' +
        '<th class="pmd-sticky pmd-sticky-2" rowspan="2">Material</th>' +
        '<th class="pmd-sticky pmd-sticky-3" rowspan="2">Profil</th>' +
        '<th class="pmd-sticky pmd-sticky-4" rowspan="2">Ringkasan</th>' +
        dayTop +
      '</tr>' +
      '<tr>' + daySub + '</tr>';
  }

  function dayCells(dailyMap, groupIndex, profileIndex){
    var html = '';
    state.dates.forEach(function(dateText, dateIndex){
      var day = dailyMap[dateText] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0 };
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pmd-date-band-a' : ' pmd-date-band-b';
      var items = [
        { key: 'opening', cls: 'pmd-metric-open' },
        { key: 'in', cls: 'pmd-metric-in' },
        { key: 'out', cls: 'pmd-metric-out' },
        { key: 'adjustment', cls: 'pmd-metric-adj' },
        { key: 'closing', cls: 'pmd-metric-close' }
      ];
      items.forEach(function(item){
        var value = Number(day[item.key] || 0);
        var valueText = esc(num(value));
        if (Math.abs(value) > 0.000001) {
          html += '<td class="pmd-metric-cell' + todayCls + bandClass + '">' +
            '<button type="button" class="pmd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>' +
          '</td>';
        } else {
          html += '<td class="pmd-metric-cell' + todayCls + bandClass + '"><span class="pmd-cell-btn ' + item.cls + '">' + valueText + '</span></td>';
        }
      });
    });
    return html;
  }

  function isExpandable(group){
    return Array.isArray(group.children) && group.children.length > 1;
  }

  function isExpanded(group){
    return isExpandable(group) ? !!state.expanded[group.key] : true;
  }

  function groupRowHtml(group, groupIndex){
    var expandable = isExpandable(group);
    var expanded = isExpanded(group);
    var singleProfile = (!expandable && Array.isArray(group.children) && group.children.length === 1) ? group.children[0] : null;
    var divisionText = divisionLabel(group);
    var destinationText = destinationLabel(group);
    var materialName = group.material_name || group.item_name || '-';
    var materialCode = group.material_code || group.item_code || '-';
    var toggleHtml = expandable
      ? '<button type="button" class="pmd-toggle-arrow" title="Expand/Collapse" data-action="toggle-group" data-group-key="' + esc(group.key) + '">' + (expanded ? '&#9662;' : '&#9656;') + '</button>'
      : '<span class="pmd-toggle-static" title="Baris tunggal">1</span>';
    var profileHtml = '<div class="pmd-code">' + (expandable ? ('Memiliki ' + esc(String((group.children || []).length)) + ' rincian profil') : 'Baris profil tunggal') + '</div>';
    if (singleProfile) {
      var singleProfileText = singleProfile.profile_name || '-';
      var singleDetail = [singleProfile.profile_brand || '-', singleProfile.profile_description || '-'].join(' | ');
      var singleUnitInfo = num(singleProfile.profile_content_per_buy || 0) + ' ' + (singleProfile.profile_content_uom_code || '') + ' / ' + (singleProfile.profile_buy_uom_code || '-');
      var singlePriceInfo = 'Harga Satuan: ' + money((singleProfile.metrics && singleProfile.metrics.unit_price) || 0) + ' / ' + (singleProfile.profile_content_uom_code || '-')
        + ' | Harga/Pack: ' + money((singleProfile.metrics && singleProfile.metrics.unit_price_pack) || 0);
      profileHtml = ''
        + '<div class="pmd-profile-line">' + esc(singleProfileText) + '</div>'
        + '<div class="pmd-profile-line">' + esc(singleDetail) + '</div>'
        + '<div class="pmd-profile-unit">' + esc(singleUnitInfo) + '</div>'
        + '<div class="pmd-profile-unit">' + esc(singlePriceInfo) + '</div>';
    }
    var rowClass = expandable ? 'pmd-group-row pmd-group-expandable' : 'pmd-group-row pmd-group-single';
    var parentProfileIndex = singleProfile ? 0 : -1;

    return '' +
      '<tr class="' + rowClass + '">' +
        '<td class="pmd-sticky pmd-sticky-1">' + toggleHtml + '<span class="pmd-division-pill">' + esc(divisionText) + '</span><div class="pmd-code">' + esc(destinationText) + '</div></td>' +
        '<td class="pmd-sticky pmd-sticky-2"><div class="pmd-name">' + esc(materialName) + '</div><div class="pmd-code">' + esc(materialCode) + '</div></td>' +
        '<td class="pmd-sticky pmd-sticky-3">' + profileHtml + '</td>' +
        '<td class="pmd-sticky pmd-sticky-4">' + summaryParentHtml(group.metrics || {}) + '</td>' +
        dayCells(group.daily || {}, groupIndex, parentProfileIndex) +
      '</tr>';
  }

  function profileRowHtml(group, groupIndex, profile, profileIndex){
    var profileText = profile.profile_name || '-';
    var detail = [profile.profile_brand || '-', profile.profile_description || '-'].join(' | ');
    var unitInfo = num(profile.profile_content_per_buy || 0) + ' ' + (profile.profile_content_uom_code || '') + ' / ' + (profile.profile_buy_uom_code || '-');
    var priceInfo = 'Harga Satuan: ' + money((profile.metrics && profile.metrics.unit_price) || 0) + ' / ' + (profile.profile_content_uom_code || '-')
      + ' | Harga/Pack: ' + money((profile.metrics && profile.metrics.unit_price_pack) || 0);

    return '' +
      '<tr class="pmd-child-row">' +
        '<td class="pmd-sticky pmd-sticky-1"></td>' +
        '<td class="pmd-sticky pmd-sticky-2"><div class="pmd-code">Profil Material</div></td>' +
        '<td class="pmd-sticky pmd-sticky-3">' +
          '<div class="pmd-profile-line">' + esc(profileText) + '</div>' +
          '<div class="pmd-profile-line">' + esc(detail) + '</div>' +
          '<div class="pmd-profile-unit">' + esc(unitInfo) + '</div>' +
          '<div class="pmd-profile-unit">' + esc(priceInfo) + '</div>' +
        '</td>' +
        '<td class="pmd-sticky pmd-sticky-4">' + summaryChildHtml(profile.metrics || {}) + '</td>' +
        dayCells(profile.daily || {}, groupIndex, profileIndex) +
      '</tr>';
  }

  function render(options){
    var opts = options || {};
    var keepScroll = !!opts.keepScroll;
    var focusToday = !!opts.focusToday;
    var previousScrollLeft = tableWrap ? tableWrap.scrollLeft : 0;
    tableHead.innerHTML = headerHtml();
    syncStickyLayout();

    if (!state.groups.length) {
      tableBody.innerHTML = '<tr><td colspan="999" class="pmd-empty">Belum ada data untuk filter ini.</td></tr>';
      requestAnimationFrame(syncStickyLayout);
      return;
    }

    var html = '';
    state.groups.forEach(function(group, groupIndex){
      html += groupRowHtml(group, groupIndex);
      if (isExpandable(group) && isExpanded(group)) {
        (group.children || []).forEach(function(profile, profileIndex){
          html += profileRowHtml(group, groupIndex, profile, profileIndex);
        });
      }
    });
    tableBody.innerHTML = html;

    requestAnimationFrame(function(){
      syncStickyLayout();
      pinStickyColumns();
      if (!tableWrap) { return; }
      if (focusToday) {
        scrollToTodayColumn();
      } else if (keepScroll) {
        tableWrap.scrollLeft = previousScrollLeft;
      }
    });
  }

  function renderDetailRows(rows){
    var body = document.getElementById('pmdModalBody');
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada mutasi pada sel ini.</td></tr>';
      return;
    }

    body.innerHTML = rows.map(function(row){
      var refText = '';
      if (row.ref_table) {
        refText = row.ref_table + (row.ref_id ? (' #' + row.ref_id) : '');
      }
      return '<tr>' +
        '<td>' + esc(String(row.created_at || row.movement_date || '-')) + '</td>' +
        '<td>' + esc(String(row.movement_type || '-')) + '</td>' +
        '<td class="text-end fw-semibold">' + esc(num(row.qty_content_delta || 0)) + '</td>' +
        '<td class="text-end">' + esc(num(row.qty_content_after || 0)) + '</td>' +
        '<td>' + esc(refText || '-') + '</td>' +
        '<td>' + esc(String(row.notes || '-')) + '</td>' +
      '</tr>';
    }).join('');
  }

  function openModalFallback(){
    if (!modalEl || modal) { return; }
    modalEl.style.display = 'block';
    modalEl.classList.add('show');
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
  }

  function closeModalFallback(){
    if (!modalEl || modal) { return; }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
  }

  function openDetail(groupIndex, profileIndex, dateText){
    var group = state.groups[groupIndex];
    if (!group || !dateText) { return; }

    var row = null;
    if (profileIndex >= 0 && group.children[profileIndex]) {
      row = group.children[profileIndex];
    }

    var title = group.material_name || group.item_name || '-';
    var subtitleParts = [
      'Tanggal: ' + dateText,
      'Divisi: ' + divisionLabel(group),
      'Tujuan: ' + destinationLabel(group)
    ];
    if (row) {
      subtitleParts.push('Profil: ' + (row.profile_name || '-'));
    }

    document.getElementById('pmdModalTitle').textContent = title;
    document.getElementById('pmdModalSubtitle').textContent = subtitleParts.join(' | ');
    document.getElementById('pmdModalBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Memuat detail mutasi...</td></tr>';

    if (modal) {
      modal.show();
    } else {
      openModalFallback();
    }

    var p = new URLSearchParams();
    p.set('scope', 'DIVISION');
    p.set('movement_date', dateText);
    p.set('division_id', String(group.division_id || 0));
    p.set('stock_domain', 'MATERIAL');
    p.set('item_id', String(group.item_id || 0));
    p.set('material_id', String(group.material_id || 0));

    if (group.destination_type) {
      p.set('destination_type', String(group.destination_type));
    }

    if (row) {
      p.set('buy_uom_id', String(row.buy_uom_id || 0));
      p.set('content_uom_id', String(row.content_uom_id || 0));
      if (row.profile_key) {
        p.set('profile_key', String(row.profile_key));
      }
    } else {
      p.set('buy_uom_id', '0');
      p.set('content_uom_id', '0');
    }

    p.set('limit', '400');

    fetch(detailUrl + '?' + p.toString(), { headers: { 'Accept': 'application/json' } })
      .then(parseJson)
      .then(function(result){
        renderDetailRows(Array.isArray(result.rows) ? result.rows : []);
      })
      .catch(function(err){
        document.getElementById('pmdModalBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">' + esc(err && err.message ? err.message : 'Gagal memuat detail mutasi.') + '</td></tr>';
      });
  }

  function loadData(){
    tableBody.innerHTML = '<tr><td colspan="999" class="pmd-loading">Memuat data...</td></tr>';
    showMessage(true, '');

    fetch(matrixUrl + '?' + buildQuery(), { headers: { 'Accept': 'application/json' } })
      .then(parseJson)
      .then(function(result){
        var data = (result && result.data) ? result.data : {};
        state.dates = Array.isArray(data.dates) ? data.dates : [];
        state.groups = buildGroups(Array.isArray(data.rows) ? data.rows : [], state.dates);
        calcStats(state.groups);
        render({ focusToday: true });
      })
      .catch(function(err){
        tableHead.innerHTML = '';
        tableBody.innerHTML = '<tr><td colspan="999" class="pmd-empty">Gagal memuat matrix harian material.</td></tr>';
        showMessage(false, err && err.message ? err.message : 'Terjadi kesalahan saat memuat data.');
      });
  }

  document.getElementById('pmdApply').addEventListener('click', function(){
    readFilters();
    loadData();
  });

  document.getElementById('pmdClear').addEventListener('click', function(){
    clearFilters();
    loadData();
  });

  document.getElementById('pmdQ').addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') {
      ev.preventDefault();
      readFilters();
      loadData();
    }
  });

  tableBody.addEventListener('click', function(ev){
    var toggle = ev.target && ev.target.closest ? ev.target.closest('[data-action="toggle-group"]') : null;
    if (toggle) {
      var groupKey = toggle.getAttribute('data-group-key') || '';
      if (groupKey) {
        state.expanded[groupKey] = !state.expanded[groupKey];
        render({ keepScroll: true });
      }
      return;
    }

    var btn = ev.target && ev.target.closest ? ev.target.closest('[data-action="detail"]') : null;
    if (!btn) { return; }
    var groupIndex = parseInt(btn.getAttribute('data-group-index') || '', 10);
    var profileIndex = parseInt(btn.getAttribute('data-profile-index') || '', 10);
    var dateText = btn.getAttribute('data-date') || '';
    if (!Number.isFinite(groupIndex) || groupIndex < 0) { return; }
    if (!Number.isFinite(profileIndex)) { profileIndex = -1; }
    openDetail(groupIndex, profileIndex, dateText);
  });

  if (!modal) {
    modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn){
      btn.addEventListener('click', function(event){
        event.preventDefault();
        closeModalFallback();
      });
    });

    modalEl.addEventListener('click', function(event){
      if (event.target === modalEl) {
        closeModalFallback();
      }
    });

    document.addEventListener('keydown', function(event){
      if (event.key === 'Escape' && modalEl.classList.contains('show')) {
        closeModalFallback();
      }
    });
  }

  window.addEventListener('resize', function(){
    syncStickyLayout();
  });

  readFilters();
  loadData();
})();
</script>
