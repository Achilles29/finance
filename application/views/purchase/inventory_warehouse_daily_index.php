<?php
$initialMonth = (string)($month ?? date('Y-m'));
$generateUrl = site_url('purchase/stock/opname/generate');
$initialQ = (string)($q ?? '');
$initialDateFrom = (string)($date_from ?? '');
$initialDateTo = (string)($date_to ?? '');
$initialLimit = (int)($limit ?? 120);
if ($initialLimit <= 0 || $initialLimit > 1000) {
  $initialLimit = 120;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($title ?? 'Inventory Warehouse Daily'); ?></h4>
    <small class="text-muted">Matrix stok gudang per hari dengan ringkasan item yang bisa di-expand per profil.</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <form method="post" action="<?php echo $generateUrl; ?>" onsubmit="return confirm('Generate opname gudang bulan ini dan carry-forward opening bulan berikutnya?');" class="d-inline">
      <input type="hidden" name="stock_scope" value="WAREHOUSE">
      <input type="hidden" name="month" value="<?php echo html_escape(substr($initialMonth, 0, 7)); ?>">
      <input type="hidden" name="back_url" value="inventory-warehouse-daily?month=<?php echo rawurlencode(substr($initialMonth, 0, 7)); ?>">
      <button type="submit" class="btn btn-primary">Generate Opname + Stok Awal</button>
    </form>
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang Live</a>
    <a href="<?php echo site_url('purchase/stock/warehouse/daily'); ?>" class="btn btn-outline-secondary">Daily Gudang (List)</a>
    <a href="<?php echo site_url('inventory-material-daily'); ?>" class="btn btn-outline-primary">Bahan Baku Daily Matrix</a>
  </div>
</div>

<style>
  :root {
    --pwd-col-kind: 74px;
    --pwd-col-item: 178px;
    --pwd-col-profile: 166px;
    --pwd-col-summary: 176px;
    --pwd-left-1: 0px;
    --pwd-left-2: var(--pwd-col-kind);
    --pwd-left-3: calc(var(--pwd-col-kind) + var(--pwd-col-item));
    --pwd-left-4: calc(var(--pwd-col-kind) + var(--pwd-col-item) + var(--pwd-col-profile));
    --pwd-date-col: 98px;
    --pwd-header-row-1: 44px;
  }
  .pwd-filter-card,
  .pwd-board-card,
  .pwd-scroll-table,
  .pwd-modal-card {
    font-family: "Trebuchet MS", Verdana, sans-serif;
  }
  .pwd-filter-card {
    border: 1px solid #ead9cf;
    border-radius: 16px;
    box-shadow: 0 12px 24px rgba(104, 43, 40, 0.06);
  }
  .pwd-board-card {
    border: 1px solid #ead9cf;
    border-radius: 18px;
    overflow: visible;
    box-shadow: 0 14px 28px rgba(104, 43, 40, 0.08);
  }
  .pwd-board-head {
    background: linear-gradient(135deg, #5f2432, #8c3f49);
    color: #fff;
    padding: 0.8rem 0.95rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
  }
  .pwd-legend { display: flex; flex-wrap: wrap; gap: 0.4rem; }
  .pwd-legend-pill {
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
  .pwd-legend-dot { width: 8px; height: 8px; border-radius: 999px; }
  .pwd-dot-open { background: #2f5b95; }
  .pwd-dot-in { background: #2bb673; }
  .pwd-dot-out { background: #f17f43; }
  .pwd-dot-adj { background: #7f64d9; }
  .pwd-dot-close { background: #4e6b83; }
  .pwd-table-wrap {
    overflow: auto;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    isolation: isolate;
  }
  .pwd-matrix-shell {
    display: grid;
    grid-template-columns: calc(var(--pwd-col-kind) + var(--pwd-col-item) + var(--pwd-col-profile) + var(--pwd-col-summary)) minmax(0, 1fr);
    align-items: start;
  }
  .pwd-freeze-pane {
    position: relative;
    z-index: 2;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
    border-right: 4px solid #d2a08e;
    box-shadow: 12px 0 22px -18px rgba(95, 23, 39, 0.26);
  }
  .pwd-scroll-pane {
    min-width: 0;
    overflow-x: auto;
    overflow-y: hidden;
    position: relative;
    background: linear-gradient(180deg, #fffdfa 0%, #fff6f1 100%);
  }
  .pwd-freeze-table {
    width: calc(var(--pwd-col-kind) + var(--pwd-col-item) + var(--pwd-col-profile) + var(--pwd-col-summary));
    min-width: calc(var(--pwd-col-kind) + var(--pwd-col-item) + var(--pwd-col-profile) + var(--pwd-col-summary));
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-scroll-table {
    min-width: 1120px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-freeze-table thead th,
  .pwd-freeze-table tbody td,
  .pwd-scroll-table thead th,
  .pwd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pwd-freeze-table td {
    background: #fffaf7;
    color: #4a2430;
    box-shadow: inset -2px 0 0 #e6c9bd;
  }
  .pwd-freeze-col-1 { width: var(--pwd-col-kind); min-width: var(--pwd-col-kind); max-width: var(--pwd-col-kind); }
  .pwd-freeze-col-2 { width: var(--pwd-col-item); min-width: var(--pwd-col-item); max-width: var(--pwd-col-item); }
  .pwd-freeze-col-3 { width: var(--pwd-col-profile); min-width: var(--pwd-col-profile); max-width: var(--pwd-col-profile); }
  .pwd-freeze-col-4 { width: var(--pwd-col-summary); min-width: var(--pwd-col-summary); max-width: var(--pwd-col-summary); }
  .pwd-scroll-table {
    min-width: 1660px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .pwd-scroll-table thead th,
  .pwd-scroll-table tbody td {
    padding: 0.42rem 0.45rem;
    vertical-align: middle;
  }
  .pwd-scroll-table thead tr:first-child th {
    position: sticky;
    top: 0;
    z-index: 52;
    background: #fff8f4;
    border-bottom: 1px solid #ebd8cf;
    color: #602739;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    white-space: nowrap;
  }
  .pwd-freeze-table thead tr:first-child th {
    position: sticky;
    top: 0;
    z-index: 52;
    background: #fff8f4;
    border-bottom: 1px solid #ebd8cf;
    height: var(--pwd-header-row-1);
    min-height: var(--pwd-header-row-1);
  }
  .pwd-freeze-table thead tr:nth-child(2) th {
    position: sticky;
    top: var(--pwd-header-row-1);
    z-index: 51;
    background: #fff2ec;
    border-bottom: 1px solid #ebd8cf;
    color: #7b4f49;
    font-size: 0.73rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }
  .pwd-freeze-spacer {
    color: transparent;
    line-height: 0;
    font-size: 0;
  }
  .pwd-scroll-table thead tr:nth-child(2) th {
    position: sticky;
    top: var(--pwd-header-row-1);
    z-index: 51;
    background: #fff2ec;
    border-bottom: 1px solid #ebd8cf;
    color: #7b4f49;
    font-size: 0.73rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    width: var(--pwd-date-col);
    min-width: var(--pwd-date-col);
    max-width: var(--pwd-date-col);
  }
  .pwd-day-head {
    width: calc(var(--pwd-date-col) * 5);
    min-width: calc(var(--pwd-date-col) * 5);
    max-width: calc(var(--pwd-date-col) * 5);
  }
  .pwd-day-head.is-today {
    background: linear-gradient(180deg, #ffcfbe, #ffa37f) !important;
    box-shadow: inset 0 -4px 0 #c75a39;
    color: #4e1d12 !important;
  }
  .pwd-day-weekday {
    display: block;
    font-size: 0.72rem;
    letter-spacing: 0.11em;
    opacity: 0.86;
  }
  .pwd-kind-pill {
    display: inline-flex;
    padding: 0.15rem 0.48rem;
    border-radius: 999px;
    border: 1px solid #ebd4ca;
    background: #fff2ec;
    color: #8b3c47;
    font-size: 0.69rem;
    font-weight: 800;
  }
  .pwd-name { font-weight: 800; color: #4e1f2e; line-height: 1.28; }
  .pwd-code { color: #876a65; font-size: 0.79rem; margin-top: 0.14rem; }
  .pwd-profile-line {
    color: #6e5652;
    font-size: 0.82rem;
    line-height: 1.25;
    white-space: normal;
    word-break: break-word;
  }
  .pwd-profile-unit {
    margin-top: 0.18rem;
    color: #7d5648;
    font-size: 0.79rem;
    font-weight: 700;
  }
  .pwd-group-row {
    background: #fff9f6;
    border-top: 2px solid #f0d9cf;
  }
  .pwd-group-row.pwd-group-expandable {
    background: linear-gradient(90deg, #fff4eb, #fffaf6);
  }
  .pwd-group-row.pwd-group-single {
    background: linear-gradient(90deg, #f8fff5, #fafff8);
  }
  .pwd-child-row {
    background: #fff;
  }
  .pwd-toggle-arrow {
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
  .pwd-toggle-arrow:hover {
    background: #ffece2;
    border-color: #c99f8f;
  }
  .pwd-toggle-static {
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
  .pwd-group-toggle {
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
  .pwd-group-toggle:hover {
    background: #ffe8dd;
    border-color: #d7b3a5;
  }
  .pwd-summary-line {
    font-size: 0.78rem;
    line-height: 1.25;
    color: #6f4d47;
    white-space: normal;
  }
  .pwd-summary-line strong {
    color: #4e2430;
    font-weight: 800;
  }
  .pwd-metric-cell {
    width: var(--pwd-date-col);
    min-width: var(--pwd-date-col);
    max-width: var(--pwd-date-col);
    text-align: right;
    font-size: 0.79rem;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    background: #fff;
  }
  .pwd-metric-cell.is-today {
    background: #fff4ed;
  }
  .pwd-metric-cell .pwd-cell-btn {
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
  .pwd-metric-cell .pwd-cell-btn:hover {
    border-color: #d6b2a5;
    background: #fff8f3;
  }
  .pwd-metric-open { color: #2f5b95; }
  .pwd-metric-in { color: #248c5d; }
  .pwd-metric-out { color: #cd6b35; }
  .pwd-metric-adj { color: #6a52cf; }
  .pwd-metric-close { color: #4f647d; font-weight: 800; }
  .pwd-date-band-a {
    background-color: #fffefc;
  }
  .pwd-date-band-b {
    background-color: #fff6f1;
  }
  .pwd-date-band-b .pwd-cell-btn {
    background: rgba(255, 255, 255, 0.56);
  }
  .pwd-empty,
  .pwd-loading {
    text-align: center;
    color: #8e6f67;
    padding: 2rem 1rem;
  }
  .pwd-stat-card {
    border: 1px solid #ead9cf;
    border-radius: 14px;
    background: #fff;
    padding: 0.6rem 0.78rem;
  }
  .pwd-stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #8d6b64; font-weight: 800; }
  .pwd-stat-value { margin-top: 0.22rem; font-size: 1.25rem; font-weight: 800; color: #5f2432; }
  .pwd-modal-card {
    border: 1px solid #e9d9d1;
    border-radius: 12px;
    background: #fff9f6;
    padding: 0.75rem 0.9rem;
  }
  @media (max-width: 991.98px) {
    .pwd-scroll-table { min-width: 1320px; }
    .pwd-day-head { min-width: 300px; }
    .pwd-matrix-shell { grid-template-columns: 560px minmax(0, 1fr); }
    .pwd-scroll-table { min-width: 1320px; }
  }
</style>

<div id="pwdActionMsg" class="mb-2"></div>

<div class="card pwd-filter-card mb-2">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" id="pwdMonth" class="form-control" value="<?php echo html_escape(substr($initialMonth, 0, 7)); ?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label mb-1">Cari</label>
        <input type="text" id="pwdQ" class="form-control" value="<?php echo html_escape($initialQ); ?>" placeholder="Item, bahan baku, profile, merk">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" id="pwdDateFrom" class="form-control" value="<?php echo html_escape($initialDateFrom); ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" id="pwdDateTo" class="form-control" value="<?php echo html_escape($initialDateTo); ?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" id="pwdLimit" class="form-control" min="1" max="1000" value="<?php echo (int)$initialLimit; ?>">
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pwdApply" class="btn btn-primary">Terapkan</button>
      </div>
      <div class="col-6 col-md-1 d-grid">
        <button type="button" id="pwdClear" class="btn btn-outline-danger">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="row g-2 mb-2">
  <div class="col-6 col-md-3"><div class="pwd-stat-card"><div class="pwd-stat-label">Profil</div><div id="pwdStatProfiles" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card"><div class="pwd-stat-label">Item</div><div id="pwdStatItems" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card"><div class="pwd-stat-label">Bahan Baku</div><div id="pwdStatMaterials" class="pwd-stat-value">0</div></div></div>
  <div class="col-6 col-md-3"><div class="pwd-stat-card"><div class="pwd-stat-label">Nilai Sisa</div><div id="pwdStatValue" class="pwd-stat-value">0,00</div></div></div>
</div>

<div class="card pwd-board-card">
  <div class="pwd-board-head">
    <div>
      <div class="fw-semibold">Matrix Daily Inventory Gudang</div>
    </div>
    <div class="pwd-legend">
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-open"></span>Awal</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-in"></span>In</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-out"></span>Out</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-adj"></span>Adj</span>
      <span class="pwd-legend-pill"><span class="pwd-legend-dot pwd-dot-close"></span>Akhir</span>
    </div>
  </div>
  <div class="pwd-matrix-shell" id="pwdMatrixShell">
    <div class="pwd-freeze-pane">
      <table class="table pwd-freeze-table align-middle mb-0">
        <thead id="pwdFreezeHead"></thead>
        <tbody id="pwdFreezeBody"><tr><td colspan="4" class="pwd-loading">Memuat data...</td></tr></tbody>
      </table>
    </div>
    <div class="pwd-scroll-pane pwd-table-wrap" id="pwdTableWrap">
      <table class="table pwd-scroll-table align-middle mb-0">
        <thead id="pwdScrollHead"></thead>
        <tbody id="pwdScrollBody"><tr><td colspan="999" class="pwd-loading">Memuat data...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="pwdDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Mutasi Harian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pwd-modal-card mb-3">
          <div class="fw-semibold" id="pwdModalTitle">-</div>
          <div class="small text-muted" id="pwdModalSubtitle">-</div>
        </div>
        <div id="pwdModalListWrap" class="table-responsive">
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
            <tbody id="pwdModalBody"><tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr></tbody>
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
  var matrixUrl = <?php echo json_encode((string)($matrix_url ?? site_url('inventory-warehouse-daily/matrix'))); ?>;
  var detailUrl = <?php echo json_encode((string)($detail_url ?? site_url('inventory-daily/cell-detail'))); ?>;
  var defaultMonth = <?php echo json_encode(substr($initialMonth, 0, 7)); ?>;

  var state = {
    month: defaultMonth,
    q: <?php echo json_encode($initialQ); ?>,
    date_from: <?php echo json_encode($initialDateFrom); ?>,
    date_to: <?php echo json_encode($initialDateTo); ?>,
    limit: <?php echo (int)$initialLimit; ?>,
    dates: [],
    groups: [],
    expanded: {}
  };

  var freezeHead = document.getElementById('pwdFreezeHead');
  var freezeBody = document.getElementById('pwdFreezeBody');
  var tableHead = document.getElementById('pwdScrollHead');
  var tableBody = document.getElementById('pwdScrollBody');
  var tableWrap = document.getElementById('pwdTableWrap');
  var matrixShell = document.getElementById('pwdMatrixShell');
  var modalEl = document.getElementById('pwdDetailModal');
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

  var stickySyncFrame = 0;

  function applyStickyLayout(){
    var freezeHeaderRow = freezeHead.querySelector('tr:nth-child(2)');
    if (!freezeHeaderRow || freezeHeaderRow.children.length < 4) { return; }

    var c1 = freezeHeaderRow.children[0];
    var c2 = freezeHeaderRow.children[1];
    var c3 = freezeHeaderRow.children[2];
    var c4 = freezeHeaderRow.children[3];

    var w1 = Math.max(64, Math.ceil(c1.getBoundingClientRect().width));
    var w2 = Math.max(140, Math.ceil(c2.getBoundingClientRect().width));
    var w3 = Math.max(140, Math.ceil(c3.getBoundingClientRect().width));
    var w4 = Math.max(150, Math.ceil(c4.getBoundingClientRect().width));

    var rootStyle = document.documentElement.style;
    rootStyle.setProperty('--pwd-col-kind', w1 + 'px');
    rootStyle.setProperty('--pwd-col-item', w2 + 'px');
    rootStyle.setProperty('--pwd-col-profile', w3 + 'px');
    rootStyle.setProperty('--pwd-col-summary', w4 + 'px');
    var firstHeaderRow = tableHead.querySelector('tr');
    var firstHeaderHeight = firstHeaderRow ? Math.max(0, Math.ceil(firstHeaderRow.getBoundingClientRect().height)) : 0;
    if (firstHeaderHeight > 0) {
      rootStyle.setProperty('--pwd-header-row-1', firstHeaderHeight + 'px');
    }
    var metricHead = tableHead.querySelector('tr:nth-child(2) th.pwd-metric-cell');
    if (metricHead) {
      var metricWidth = Math.max(86, Math.ceil(metricHead.getBoundingClientRect().width));
      rootStyle.setProperty('--pwd-date-col', metricWidth + 'px');
    }
    rootStyle.setProperty('--pwd-left-1', '0px');
    rootStyle.setProperty('--pwd-left-2', w1 + 'px');
    rootStyle.setProperty('--pwd-left-3', (w1 + w2) + 'px');
    rootStyle.setProperty('--pwd-left-4', (w1 + w2 + w3) + 'px');
  }

  function syncStickyLayout(){
    if (stickySyncFrame) {
      cancelAnimationFrame(stickySyncFrame);
    }
    stickySyncFrame = requestAnimationFrame(function(){
      stickySyncFrame = 0;
      applyStickyLayout();
    });
  }

  function getStickyOffset(){
    var rootStyle = getComputedStyle(document.documentElement);
    return (
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-kind')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-item')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-profile')) +
      Number.parseFloat(rootStyle.getPropertyValue('--pwd-col-summary'))
    ) || 0;
  }

  function scrollToTodayColumn(){
    if (!tableWrap) { return; }
    var todayCell = tableHead.querySelector('.pwd-day-head.is-today');
    if (!todayCell) { return; }
    tableWrap.scrollLeft = Math.max(0, todayCell.offsetLeft - getStickyOffset() - 16);
  }

  function readFilters(){
    state.month = document.getElementById('pwdMonth').value || '';
    state.q = document.getElementById('pwdQ').value || '';
    state.date_from = document.getElementById('pwdDateFrom').value || '';
    state.date_to = document.getElementById('pwdDateTo').value || '';
    state.limit = parseInt(document.getElementById('pwdLimit').value || '120', 10);
    if (!Number.isFinite(state.limit) || state.limit <= 0 || state.limit > 1000) {
      state.limit = 120;
    }
  }

  function clearFilters(){
    document.getElementById('pwdMonth').value = defaultMonth;
    document.getElementById('pwdQ').value = '';
    document.getElementById('pwdDateFrom').value = '';
    document.getElementById('pwdDateTo').value = '';
    document.getElementById('pwdLimit').value = '120';
    readFilters();
  }

  function buildQuery(){
    var p = new URLSearchParams();
    if (state.month) { p.set('month', state.month); }
    if (state.q) { p.set('q', state.q); }
    if (state.date_from) { p.set('date_from', state.date_from); }
    if (state.date_to) { p.set('date_to', state.date_to); }
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
    var box = document.getElementById('pwdActionMsg');
    if (!box) { return; }
    if (!text) {
      box.innerHTML = '';
      return;
    }
    box.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + esc(text) + '</div>';
  }

  function objectLabel(row){
    var code = row.stock_domain === 'MATERIAL' ? (row.material_code || '') : (row.item_code || '');
    var name = row.stock_domain === 'MATERIAL' ? (row.material_name || '') : (row.item_name || '');
    if (!name && row.material_name) {
      name = row.material_name;
      code = row.material_code || code;
    }
    if (!name && row.item_name) {
      name = row.item_name;
      code = row.item_code || code;
    }
    return {
      name: name || '-',
      code: code || '-'
    };
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
        if (!initialized) {
          opening = Number(raw.opening || opening);
        } else {
          opening = Number(raw.opening || opening);
        }
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
      var materialId = Number(row.material_id || 0);
      var itemId = Number(row.item_id || 0);
      var objectKey = materialId > 0 ? ('M-' + materialId) : ('I-' + itemId);
      if (objectKey === 'M-0' || objectKey === 'I-0') {
        objectKey += '|' + String(row.material_code || row.item_code || '').toUpperCase();
      }
      var key = objectKey;
      if (!map[key]) {
        var obj = objectLabel(row);
        map[key] = {
          key: key,
          stock_domain: String(row.stock_domain || ''),
          item_id: Number(row.item_id || 0),
          material_id: Number(row.material_id || 0),
          item_code: String(row.item_code || ''),
          item_name: String(row.item_name || ''),
          material_code: String(row.material_code || ''),
          material_name: String(row.material_name || ''),
          object_name: obj.name,
          object_code: obj.code,
          children: [],
          daily: {},
          metrics: null
        };
        order.push(key);
      }

      var profileDaily = normalizeProfileDaily(row.daily || {}, dates);
      var profileMetrics = calcMetrics(profileDaily, Number(row.profile_content_per_buy || 0), dates);

      map[key].children.push({
        stock_domain: String(row.stock_domain || ''),
        item_id: Number(row.item_id || 0),
        material_id: Number(row.material_id || 0),
        buy_uom_id: Number(row.buy_uom_id || 0),
        content_uom_id: Number(row.content_uom_id || 0),
        profile_key: String(row.profile_key || ''),
        profile_name: String(row.profile_name || ''),
        profile_brand: String(row.profile_brand || ''),
        profile_description: String(row.profile_description || ''),
        profile_expired_date: String(row.profile_expired_date || ''),
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
    var itemSet = {};
    var materialSet = {};
    var totalValue = 0;

    (groups || []).forEach(function(group){
      profileCount += (group.children || []).length;
      if (group.item_id) { itemSet[String(group.item_id)] = true; }
      if (group.material_id) { materialSet[String(group.material_id)] = true; }
      totalValue += Number((group.metrics && group.metrics.total_value) || 0);
    });

    document.getElementById('pwdStatProfiles').textContent = profileCount.toLocaleString('id-ID');
    document.getElementById('pwdStatItems').textContent = Object.keys(itemSet).length.toLocaleString('id-ID');
    document.getElementById('pwdStatMaterials').textContent = Object.keys(materialSet).length.toLocaleString('id-ID');
    document.getElementById('pwdStatValue').textContent = money(totalValue);
  }

  function summaryParentHtml(metrics){
    return '' +
      '<div class="pwd-summary-line"><strong>HPP Rata2:</strong> ' + esc(money(metrics.hpp || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Awal Pack:</strong> ' + esc(num(metrics.opening_pack || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Akhir Pack:</strong> ' + esc(num(metrics.closing_pack || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Awal Isi:</strong> ' + esc(num(metrics.opening_content || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Akhir Isi:</strong> ' + esc(num(metrics.closing_content || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Nilai Sisa:</strong> ' + esc(money(metrics.total_value || 0)) + '</div>';
  }

  function summaryChildHtml(metrics){
    return '' +
      '<div class="pwd-summary-line"><strong>Awal Pack:</strong> ' + esc(num(metrics.opening_pack || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Akhir Pack:</strong> ' + esc(num(metrics.closing_pack || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Awal Isi:</strong> ' + esc(num(metrics.opening_content || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Akhir Isi:</strong> ' + esc(num(metrics.closing_content || 0)) + '</div>' +
      '<div class="pwd-summary-line"><strong>Nilai Sisa:</strong> ' + esc(money(metrics.total_value || 0)) + '</div>';
  }

  function freezeHeaderHtml(){
    return '' +
      '<tr>' +
        '<th class="pwd-freeze-spacer" colspan="4"></th>' +
      '</tr>' +
      '<tr>' +
        '<th class="pwd-freeze-col-1">Jenis</th>' +
        '<th class="pwd-freeze-col-2">Item / Bahan Baku</th>' +
        '<th class="pwd-freeze-col-3">Profil</th>' +
        '<th class="pwd-freeze-col-4">Ringkasan</th>' +
      '</tr>' +
      '';
  }

  function headerHtml(){
    var dayTop = state.dates.map(function(dateText, dateIndex){
      var cls = 'pwd-day-head' + (isToday(dateText) ? ' is-today' : '');
      var bandClass = dateIndex % 2 === 0 ? 'pwd-date-band-a' : 'pwd-date-band-b';
      return '<th class="' + cls + ' ' + bandClass + '" colspan="5"><div>' + esc(String(dateText).slice(-2)) + '</div><span class="pwd-day-weekday">' + esc(weekdayName(dateText)) + '</span></th>';
    }).join('');

    var daySub = state.dates.map(function(dateText, dateIndex){
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pwd-date-band-a' : ' pwd-date-band-b';
      return '' +
        '<th class="pwd-metric-cell' + todayCls + bandClass + '">Awal</th>' +
        '<th class="pwd-metric-cell' + todayCls + bandClass + '">In</th>' +
        '<th class="pwd-metric-cell' + todayCls + bandClass + '">Out</th>' +
        '<th class="pwd-metric-cell' + todayCls + bandClass + '">Adj</th>' +
        '<th class="pwd-metric-cell' + todayCls + bandClass + '">Akhir</th>';
    }).join('');

    return '<tr>' + dayTop + '</tr><tr>' + daySub + '</tr>';
  }

  function dayCells(dailyMap, groupIndex, profileIndex){
    var html = '';
    state.dates.forEach(function(dateText, dateIndex){
      var day = dailyMap[dateText] || { opening: 0, in: 0, out: 0, adjustment: 0, closing: 0 };
      var todayCls = isToday(dateText) ? ' is-today' : '';
      var bandClass = dateIndex % 2 === 0 ? ' pwd-date-band-a' : ' pwd-date-band-b';
      var items = [
        { key: 'opening', cls: 'pwd-metric-open' },
        { key: 'in', cls: 'pwd-metric-in' },
        { key: 'out', cls: 'pwd-metric-out' },
        { key: 'adjustment', cls: 'pwd-metric-adj' },
        { key: 'closing', cls: 'pwd-metric-close' }
      ];
      items.forEach(function(item){
        var value = Number(day[item.key] || 0);
        var valueText = esc(num(value));
        if (Math.abs(value) > 0.000001) {
          html += '<td class="pwd-metric-cell' + todayCls + bandClass + '">' +
            '<button type="button" class="pwd-cell-btn ' + item.cls + '" data-action="detail" data-group-index="' + groupIndex + '" data-profile-index="' + profileIndex + '" data-date="' + esc(dateText) + '">' + valueText + '</button>' +
          '</td>';
        } else {
          html += '<td class="pwd-metric-cell' + todayCls + bandClass + '"><span class="pwd-cell-btn ' + item.cls + '">' + valueText + '</span></td>';
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

  function freezeGroupRowHtml(group){
    var kind = group.stock_domain === 'MATERIAL' ? 'Bahan Baku' : 'Item';
    var expandable = isExpandable(group);
    var expanded = isExpanded(group);
    var singleProfile = (!expandable && Array.isArray(group.children) && group.children.length === 1) ? group.children[0] : null;
    var toggleHtml = expandable
      ? '<button type="button" class="pwd-toggle-arrow" title="Expand/Collapse" data-action="toggle-group" data-group-key="' + esc(group.key) + '">' + (expanded ? '&#9662;' : '&#9656;') + '</button>'
      : '<span class="pwd-toggle-static" title="Baris tunggal">1</span>';
    var profileHtml = '<div class="pwd-code">' + (expandable ? ('Memiliki ' + esc(String((group.children || []).length)) + ' rincian profil') : 'Baris profil tunggal') + '</div>';
    if (singleProfile) {
      var singleProfileText = singleProfile.profile_name || '-';
      var singleDetail = [singleProfile.profile_brand || '-', singleProfile.profile_description || '-'].join(' | ');
      var singleExpiredInfo = singleProfile.profile_expired_date ? ('Exp: ' + singleProfile.profile_expired_date) : 'Exp: -';
      var singleUnitInfo = num(singleProfile.profile_content_per_buy || 0) + ' ' + (singleProfile.profile_content_uom_code || '') + ' / ' + (singleProfile.profile_buy_uom_code || '-');
      var singlePriceInfo = 'Harga Satuan: ' + money((singleProfile.metrics && singleProfile.metrics.unit_price) || 0) + ' / ' + (singleProfile.profile_content_uom_code || '-')
        + ' | Harga/Pack: ' + money((singleProfile.metrics && singleProfile.metrics.unit_price_pack) || 0);
      profileHtml = ''
        + '<div class="pwd-profile-line">' + esc(singleProfileText) + '</div>'
        + '<div class="pwd-profile-line">' + esc(singleDetail) + '</div>'
        + '<div class="pwd-profile-unit">' + esc(singleExpiredInfo) + '</div>'
        + '<div class="pwd-profile-unit">' + esc(singleUnitInfo) + '</div>'
        + '<div class="pwd-profile-unit">' + esc(singlePriceInfo) + '</div>';
    }
    var rowClass = expandable ? 'pwd-group-row pwd-group-expandable' : 'pwd-group-row pwd-group-single';
    return '' +
      '<tr class="' + rowClass + '">' +
        '<td class="pwd-freeze-col-1">' + toggleHtml + '<span class="pwd-kind-pill">' + esc(kind) + '</span></td>' +
        '<td class="pwd-freeze-col-2"><div class="pwd-name">' + esc(group.object_name || '-') + '</div><div class="pwd-code">' + esc(group.object_code || '-') + '</div></td>' +
        '<td class="pwd-freeze-col-3">' + profileHtml + '</td>' +
        '<td class="pwd-freeze-col-4">' + summaryParentHtml(group.metrics || {}) + '</td>' +
      '</tr>';
  }

  function groupRowHtml(group, groupIndex){
    var singleProfile = (!isExpandable(group) && Array.isArray(group.children) && group.children.length === 1) ? group.children[0] : null;
    var parentProfileIndex = singleProfile ? 0 : -1;
    var rowClass = isExpandable(group) ? 'pwd-group-row pwd-group-expandable' : 'pwd-group-row pwd-group-single';
    return '<tr class="' + rowClass + '">' + dayCells(group.daily || {}, groupIndex, parentProfileIndex) + '</tr>';
  }

  function freezeProfileRowHtml(profile){
    var profileText = profile.profile_name || '-';
    var detail = [profile.profile_brand || '-', profile.profile_description || '-'].join(' | ');
    var expiredInfo = profile.profile_expired_date ? ('Exp: ' + profile.profile_expired_date) : 'Exp: -';
    var unitInfo = num(profile.profile_content_per_buy || 0) + ' ' + (profile.profile_content_uom_code || '') + ' / ' + (profile.profile_buy_uom_code || '-');
    var priceInfo = 'Harga Satuan: ' + money((profile.metrics && profile.metrics.unit_price) || 0) + ' / ' + (profile.profile_content_uom_code || '-')
      + ' | Harga/Pack: ' + money((profile.metrics && profile.metrics.unit_price_pack) || 0);

    return '' +
      '<tr class="pwd-child-row">' +
        '<td class="pwd-freeze-col-1"></td>' +
        '<td class="pwd-freeze-col-2"><div class="pwd-code">Profil Item</div></td>' +
        '<td class="pwd-freeze-col-3">' +
          '<div class="pwd-profile-line">' + esc(profileText) + '</div>' +
          '<div class="pwd-profile-line">' + esc(detail) + '</div>' +
          '<div class="pwd-profile-unit">' + esc(expiredInfo) + '</div>' +
          '<div class="pwd-profile-unit">' + esc(unitInfo) + '</div>' +
          '<div class="pwd-profile-unit">' + esc(priceInfo) + '</div>' +
        '</td>' +
        '<td class="pwd-freeze-col-4">' + summaryChildHtml(profile.metrics || {}) + '</td>' +
      '</tr>';
  }

  function profileRowHtml(group, groupIndex, profile, profileIndex){
    return '<tr class="pwd-child-row">' + dayCells(profile.daily || {}, groupIndex, profileIndex) + '</tr>';
  }

  function syncPaneRowHeights(){
    var freezeRows = Array.prototype.slice.call(freezeBody.querySelectorAll('tr'));
    var scrollRows = Array.prototype.slice.call(tableBody.querySelectorAll('tr'));
    var count = Math.min(freezeRows.length, scrollRows.length);
    var index;
    for (index = 0; index < count; index += 1) {
      freezeRows[index].style.height = '';
      scrollRows[index].style.height = '';
      var height = Math.max(freezeRows[index].offsetHeight, scrollRows[index].offsetHeight);
      freezeRows[index].style.height = height + 'px';
      scrollRows[index].style.height = height + 'px';
    }
  }

  function render(options){
    var opts = options || {};
    var keepScroll = !!opts.keepScroll;
    var focusToday = !!opts.focusToday;
    var previousScrollLeft = tableWrap ? tableWrap.scrollLeft : 0;
    freezeHead.innerHTML = freezeHeaderHtml();
    tableHead.innerHTML = headerHtml();
    syncStickyLayout();

    if (!state.groups.length) {
      freezeBody.innerHTML = '<tr><td colspan="4" class="pwd-empty">Belum ada data untuk filter ini.</td></tr>';
      tableBody.innerHTML = '<tr><td colspan="' + Math.max(1, state.dates.length * 5) + '" class="pwd-empty"></td></tr>';
      requestAnimationFrame(function(){
        syncStickyLayout();
        syncPaneRowHeights();
      });
      return;
    }

    var freezeHtml = '';
    var html = '';
    state.groups.forEach(function(group, groupIndex){
      freezeHtml += freezeGroupRowHtml(group);
      html += groupRowHtml(group, groupIndex);
      if (isExpandable(group) && isExpanded(group)) {
        (group.children || []).forEach(function(profile, profileIndex){
          freezeHtml += freezeProfileRowHtml(profile);
          html += profileRowHtml(group, groupIndex, profile, profileIndex);
        });
      }
    });
    freezeBody.innerHTML = freezeHtml;
    tableBody.innerHTML = html;

    requestAnimationFrame(function(){
      syncStickyLayout();
      syncPaneRowHeights();
      if (!tableWrap) { return; }
      if (focusToday) {
        scrollToTodayColumn();
      } else if (keepScroll) {
        tableWrap.scrollLeft = previousScrollLeft;
      }
    });
  }

  function renderDetailRows(rows){
    var body = document.getElementById('pwdModalBody');
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
        '<td>' + esc(String(row.movement_type_label || row.movement_type || '-')) + '</td>' +
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

    var title = group.object_name || '-';
    var subtitleParts = ['Tanggal: ' + dateText];
    if (row) {
      subtitleParts.push('Profil: ' + (row.profile_name || '-'));
    } else {
      subtitleParts.push('Ringkasan Item');
    }

    document.getElementById('pwdModalTitle').textContent = title;
    document.getElementById('pwdModalSubtitle').textContent = subtitleParts.join(' | ');
    document.getElementById('pwdModalBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Memuat detail mutasi...</td></tr>';

    if (modal) {
      modal.show();
    } else {
      openModalFallback();
    }

    var p = new URLSearchParams();
    p.set('scope', 'WAREHOUSE');
    p.set('movement_date', dateText);
    p.set('stock_domain', String(group.stock_domain || ''));
    p.set('item_id', String(group.item_id || 0));
    p.set('material_id', String(group.material_id || 0));

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
        document.getElementById('pwdModalBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">' + esc(err && err.message ? err.message : 'Gagal memuat detail mutasi.') + '</td></tr>';
      });
  }

  function loadData(){
    tableBody.innerHTML = '<tr><td colspan="999" class="pwd-loading">Memuat data...</td></tr>';
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
        freezeHead.innerHTML = '';
        tableHead.innerHTML = '';
        freezeBody.innerHTML = '<tr><td colspan="4" class="pwd-empty">Gagal memuat matrix harian.</td></tr>';
        tableBody.innerHTML = '<tr><td colspan="1" class="pwd-empty"></td></tr>';
        showMessage(false, err && err.message ? err.message : 'Terjadi kesalahan saat memuat data.');
      });
  }

  document.getElementById('pwdApply').addEventListener('click', function(){
    readFilters();
    loadData();
  });

  document.getElementById('pwdClear').addEventListener('click', function(){
    clearFilters();
    loadData();
  });

  document.getElementById('pwdQ').addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') {
      ev.preventDefault();
      readFilters();
      loadData();
    }
  });

  matrixShell.addEventListener('click', function(ev){
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
    syncPaneRowHeights();
  });

  readFilters();
  loadData();
})();
</script>
