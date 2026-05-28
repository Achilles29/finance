<?php
$filters = is_array($filters ?? null) ? $filters : [];
$matrix = is_array($matrix ?? null) ? $matrix : [];
$dates = is_array($matrix['dates'] ?? null) ? $matrix['dates'] : [];
$rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$selectedMonth = (string)($filters['month'] ?? date('Y-m'));
$todayDate = date('Y-m-d');
$todayInView = strpos($todayDate, $selectedMonth . '-') === 0;
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$adjustmentReasonOptions = [
  'WASTE' => [
    'cancel_order' => 'Cancel Order',
    'kitchen_error' => 'Kitchen Error',
    'overproduction' => 'Overproduction',
    'spillage' => 'Spillage / Tumpah',
    'expired_opened' => 'Expired Opened',
    'other' => 'Other',
  ],
  'SPOILAGE' => [
    'expired' => 'Expired',
    'temperature_abuse' => 'Temperature Abuse',
    'contamination' => 'Contamination',
    'improper_storage' => 'Improper Storage',
    'overstock' => 'Overstock',
    'other' => 'Other',
  ],
  'ADJUSTMENT_PLUS' => [
    'opening_correction' => 'Opening Correction',
    'stock_found' => 'Stock Found',
    'manual_reclass' => 'Manual Reclass',
    'other' => 'Other',
  ],
  'ADJUSTMENT_MINUS' => [
    'counting_error' => 'Counting Error',
    'system_mismatch' => 'System Mismatch',
    'unrecorded_usage' => 'Unrecorded Usage',
    'process_loss' => 'Process Loss',
    'theft_suspected' => 'Theft Suspected',
    'other' => 'Other',
  ],
];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT') {
    return 'Event';
  }
  if ($value === 'BAR' || $value === 'KITCHEN') {
    return 'Reguler';
  }
  return $value !== '' ? $value : '-';
};
$divisionLabel = static function (array $row): string {
  $code = trim((string)($row['division_code'] ?? $row['code'] ?? ''));
  $name = trim((string)($row['division_name'] ?? $row['name'] ?? ''));
  if ($code !== '' && $name !== '') {
    return $code . ' - ' . $name;
  }
  return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$lotAverageCost = static function (array $lotSummary): float {
  $balanceQty = (float)($lotSummary['balance_qty'] ?? 0);
  if ($balanceQty <= 0) {
    return 0.0;
  }
  return round((float)($lotSummary['total_value'] ?? 0) / $balanceQty, 6);
};
$isCurrentMonthView = $selectedMonth === date('Y-m');
$dailyMatrixColspan = (int)(4 + count($dates));
?>

<style>
  .component-daily-matrix-wrap {
    max-height: 74vh;
    overflow: auto;
    border: 1px solid #e8d2c3;
    border-radius: 18px;
    background:
      radial-gradient(circle at top right, rgba(232, 123, 72, .08), transparent 28%),
      linear-gradient(180deg, #fffaf5 0%, #fff 100%);
    box-shadow: 0 18px 36px -30px rgba(95, 53, 39, .45), inset 0 0 0 1px rgba(255, 255, 255, .55);
  }
  .component-daily-matrix {
    min-width: 2280px;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .component-daily-matrix th,
  .component-daily-matrix td {
    vertical-align: top;
    border-right: 1px solid #efddd2;
    border-bottom: 1px solid #f3e4da;
  }
  .component-daily-matrix thead th {
    position: sticky;
    top: 0;
    z-index: 7;
    background: linear-gradient(180deg, #7c1f2d 0%, #9f2f3e 100%);
    border-bottom: 1px solid #7f2936;
    color: #fff8f5;
    box-shadow: 0 6px 14px -12px rgba(58, 12, 20, .85);
  }
  .component-daily-matrix thead th.component-daily-date-col {
    min-width: 158px;
    padding: .45rem .4rem .5rem;
  }
  .component-daily-matrix tbody td {
    background: #fff;
    padding: .5rem;
  }
  .component-daily-matrix tbody tr:nth-child(even) td {
    background: #fffaf6;
  }
  .component-daily-fixed {
    position: sticky;
    left: 0;
    z-index: 6;
    background: #fff !important;
    min-width: 270px;
    max-width: 270px;
    box-shadow: 1px 0 0 #eadccf, 20px 0 22px -20px rgba(107, 70, 54, .42);
  }
  .component-daily-fixed-2 {
    position: sticky;
    left: 270px;
    z-index: 6;
    background: #fff !important;
    min-width: 150px;
    max-width: 150px;
    box-shadow: 1px 0 0 #eadccf, 18px 0 20px -20px rgba(107, 70, 54, .32);
  }
  .component-daily-fixed-3 {
    position: sticky;
    left: 420px;
    z-index: 6;
    background: #fff !important;
    min-width: 260px;
    max-width: 260px;
    box-shadow: 1px 0 0 #eadccf, 18px 0 20px -20px rgba(107, 70, 54, .26);
  }
  .component-daily-matrix thead .component-daily-fixed,
  .component-daily-matrix thead .component-daily-fixed-2,
  .component-daily-matrix thead .component-daily-fixed-3 {
    z-index: 9;
    background: linear-gradient(180deg, #6f1928 0%, #8a2938 100%) !important;
  }
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed,
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed-2,
  .component-daily-matrix tbody tr:nth-child(even) .component-daily-fixed-3 {
    background: #fff7f1 !important;
  }
  .component-daily-total-group {
    min-width: 176px;
    background: #fcf1e8 !important;
  }
  .component-daily-today {
    background: linear-gradient(180deg, #ffb29d 0%, #ff8f73 100%) !important;
    color: #5d160d !important;
    border-bottom-color: #df6847 !important;
  }
  .component-daily-today-soft {
    background: #fff1ec !important;
  }
  .component-daily-today-close {
    background: #ffe0d6 !important;
    color: #7b2416;
  }
  .component-daily-today-anchor {
    scroll-margin-left: 720px;
  }
  .component-daily-legend {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
  }
  .component-daily-legend-swatch {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid #ef9d86;
    background: #ffb29d;
  }
  .component-daily-headcard {
    display: grid;
    gap: .18rem;
    justify-items: center;
    text-align: center;
    line-height: 1.05;
  }
  .component-daily-headcard .day {
    font-size: 1.28rem;
    font-weight: 900;
    letter-spacing: .02em;
  }
  .component-daily-headcard .weekday {
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    opacity: .92;
  }
  .component-daily-headcard .full-date {
    font-size: .71rem;
    opacity: .88;
  }
  .component-daily-headcard .today-tag {
    margin-top: .1rem;
    padding: .14rem .42rem;
    border-radius: 999px;
    font-size: .61rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    background: rgba(93, 22, 13, .12);
    color: inherit;
  }
  .component-daily-summary-card {
    min-width: 0;
    white-space: normal;
    line-height: 1.35;
  }
  .component-daily-summary-card .summary-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    font-weight: 600;
    color: #5f3527;
  }
  .component-daily-summary-card .summary-sub {
    font-size: .74rem;
    color: #7f675f;
  }
  .component-daily-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .42rem;
    margin-top: .38rem;
  }
  .component-daily-summary-metric {
    border: 1px solid #eadccf;
    border-radius: 12px;
    background: #fffaf6;
    padding: .42rem .48rem;
  }
  .component-daily-summary-metric .label {
    display: block;
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8a5b4d;
  }
  .component-daily-summary-metric strong {
    display: block;
    font-size: .88rem;
    color: #503125;
    line-height: 1.18;
  }
  .component-daily-summary-inline {
    margin-top: .42rem;
    font-size: .7rem;
    color: #8a5b4d;
  }
  .component-daily-date-cell,
  .component-daily-total-cell {
    min-width: 158px;
    padding: .42rem !important;
  }
  .component-daily-metric-card {
    display: grid;
    gap: .34rem;
    min-height: 146px;
    padding: .55rem .6rem;
    border-radius: 16px;
    border: 1px solid #efd9ca;
    background:
      linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(255,249,244,.98) 100%);
    box-shadow: 0 14px 22px -24px rgba(95, 53, 39, .55);
  }
  .component-daily-total-cell .component-daily-metric-card {
    background: linear-gradient(180deg, #fff9f3 0%, #ffefe4 100%);
    border-color: #eccdb8;
  }
  .component-daily-metric-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .5rem;
    align-items: center;
    font-size: .72rem;
    line-height: 1.15;
  }
  .component-daily-metric-row + .component-daily-metric-row {
    padding-top: .32rem;
    border-top: 1px dashed #edd7c8;
  }
  .component-daily-metric-row .label {
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #8a5b4d;
  }
  .component-daily-metric-row .value {
    font-weight: 800;
    color: #5c3326;
    text-align: right;
  }
  .component-daily-metric-row.metric-in .label,
  .component-daily-metric-row.metric-in .value {
    color: #0e8b40;
  }
  .component-daily-metric-row.metric-out .label,
  .component-daily-metric-row.metric-out .value {
    color: #d7662a;
  }
  .component-daily-metric-row.metric-adj .label,
  .component-daily-metric-row.metric-adj .value {
    color: #5d46d7;
  }
  .component-daily-metric-row.metric-close .label,
  .component-daily-metric-row.metric-close .value {
    color: #9a5a00;
  }
  .component-daily-metric-actions {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    justify-content: flex-end;
  }
  .component-daily-cell-note {
    display: block;
    font-size: .64rem;
    line-height: 1.25;
    color: #9a7a6e;
    white-space: normal;
    text-align: right;
  }
  .component-daily-lot-parent-toggle {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    border: 1px solid #e3d1c5;
    border-radius: 999px;
    padding: .18rem .55rem;
    background: #fff;
    color: #7a4c3f;
    font-size: .72rem;
    font-weight: 600;
  }
  .component-daily-lot-parent-toggle i {
    transition: transform .18s ease;
  }
  .component-daily-lot-parent-toggle[aria-expanded="true"] i {
    transform: rotate(180deg);
  }
  .component-daily-lot-child-row td {
    background: #fffdfa !important;
    border-top: 0;
  }
  .component-daily-lot-child-row .component-daily-fixed,
  .component-daily-lot-child-row .component-daily-fixed-2,
  .component-daily-lot-child-row .component-daily-fixed-3 {
    background: #fffaf6 !important;
  }
  .component-daily-lot-child-row .component-daily-fixed {
    padding-left: 1rem !important;
  }
  .component-daily-lot-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .16rem .48rem;
    border-radius: 999px;
    background: #fff4ea;
    border: 1px solid #ead3c2;
    color: #8a5b4d;
    font-size: .66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .component-daily-lot-child-row .component-daily-summary-card {
    min-width: 0;
  }
  .component-daily-child-note {
    display: block;
    font-size: .64rem;
    color: #9a7a6e;
    white-space: normal;
  }
  .component-daily-lot-empty {
    color: #d5b6a3;
  }
  .component-daily-adjust-btn {
    min-width: 26px;
    height: 26px;
    padding: 0;
    border-radius: 10px;
  }
  .component-daily-adjust-btn.btn-pencil {
    border-color: #e7b75e;
    color: #9a5a00;
    background: #fff7e5;
  }
  .component-daily-adjust-btn.btn-pencil:hover {
    border-color: #d29a34;
    color: #6f4200;
    background: #ffe8bf;
  }
  .component-daily-adjust-btn.btn-plus {
    border-color: #b9dfc4;
    color: #0c6a35;
    background: #effbf3;
  }
  .component-daily-adjust-btn.btn-plus:hover {
    border-color: #7fc497;
    color: #084c25;
    background: #dff5e7;
  }
  .component-adjust-quick-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .8rem;
  }
  .component-adjust-quick-grid .full {
    grid-column: 1 / -1;
  }
    gap: .9rem;
    border: 1px solid #ebdfd6;
    background: #fffaf6;
    border-radius: 14px;
    padding: .85rem 1rem;
  .component-adjust-modal-dialog {
    max-width: min(980px, calc(100vw - 1.5rem));
  }
  .component-adjust-modal-content {
    border: 0;
    border-radius: 24px;
    overflow: hidden;
    background:
      radial-gradient(circle at top right, rgba(232, 123, 72, .12), transparent 24%),
      linear-gradient(180deg, #fffdfb 0%, #fff8f3 100%);
    box-shadow: 0 28px 60px -36px rgba(73, 26, 18, .55);
  }
  .component-adjust-modal-header {
    padding: 1rem 1.15rem 1rem 1.2rem;
    border-bottom: 1px solid #f0ddd1;
    background:
      linear-gradient(180deg, rgba(255,255,255,.92) 0%, rgba(255,247,241,.94) 100%);
  }
  .component-adjust-modal-titlewrap {
    display: grid;
    gap: .2rem;
  }
  .component-adjust-modal-kicker {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .66rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #9a5b43;
  }
  .component-adjust-modal-subtitle {
    font-size: .78rem;
    color: #8c6a5a;
  }
  .component-adjust-modal-header .modal-title {
    color: #4f2d22;
    font-weight: 800;
  }
  .component-adjust-modal-body {
    padding: 1rem 1.15rem 1.05rem;
  }
  }
    border: 1px solid #edd8ca;
    background:
      linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(255,248,241,.98) 100%);
    border-radius: 20px;
    padding: .95rem 1rem;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
  }
  .component-adjust-quick-context .fw-semibold {
    font-size: 1.02rem;
    color: #4f2d22;
  }
  .component-adjust-quick-context .small {
    color: #8d6c5b !important;
  }
  .component-adjust-section {
    border: 1px solid #efddd1;
    border-radius: 18px;
    padding: .9rem;
    background: rgba(255,255,255,.72);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
  }
  .component-adjust-section + .component-adjust-section {
    margin-top: .85rem;
  }
  .component-adjust-section-title {
    margin-bottom: .7rem;
    font-size: .74rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #8f5946;
  }
  .component-adjust-field {
    display: grid;
    gap: .35rem;
  }
  .component-adjust-field .form-label {
    margin-bottom: 0;
    font-size: .74rem;
    font-weight: 800;
    color: #694236;
  }
  .component-adjust-modal-content .form-control,
  .component-adjust-modal-content .form-select {
    min-height: 48px;
    border-radius: 14px;
    border-color: #ead6c8;
    background: #fff;
    color: #533226;
    box-shadow: none;
  }
  .component-adjust-modal-content textarea.form-control {
    min-height: 92px;
    resize: vertical;
  }
  .component-adjust-modal-content .form-control:focus,
  .component-adjust-modal-content .form-select:focus {
    border-color: #d78966;
    box-shadow: 0 0 0 .22rem rgba(214, 125, 84, .14);
  }
  .component-adjust-modal-content .form-control[readonly] {
    background: #fff6ef;
    color: #7a4f40;
    font-weight: 700;
  }
  .component-adjust-modal-footer {
    padding: .95rem 1.15rem 1.1rem;
    border-top: 1px solid #f0ddd1;
    background: rgba(255,252,249,.92);
  }
  .component-adjust-modal-footer .btn {
    min-width: 138px;
    min-height: 44px;
    border-radius: 14px;
    font-weight: 800;
  }
  .component-adjust-modal-footer .btn-primary {
    border-color: #cf5335;
    background: linear-gradient(180deg, #dd5d3a 0%, #c94428 100%);
    box-shadow: 0 14px 26px -20px rgba(201, 68, 40, .9);
  }
  .component-adjust-modal-footer .btn-primary:hover {
    border-color: #b53b21;
    background: linear-gradient(180deg, #cf5335 0%, #b83a20 100%);
  }
    padding: .85rem 1rem;
  }
  .component-batch-quick-usage {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .5rem;
  }
  @media (max-width: 991.98px) {
    .component-adjust-quick-grid {
      grid-template-columns: 1fr;
    }
    .component-daily-summary-grid {
      grid-template-columns: 1fr;
    }
    .component-daily-matrix-wrap {
      max-height: none;
    }
    .component-adjust-modal-dialog {
      max-width: calc(100vw - 1rem);
    }
    .component-adjust-modal-footer {
      flex-direction: column;
    }
    .component-adjust-modal-footer .btn {
      width: 100%;
    }
  }
</style>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-calendar-check-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Daily Matrix Base/Prepare'); ?></h4>
      <small class="text-muted">Matrix harian komponen per tanggal: opening, in, out, adjustment, closing.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo site_url('production/component-lots'); ?>" class="btn btn-outline-secondary btn-sm">Lot FIFO</a>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'daily']); ?>
<?php $this->load->view('production/_component_type_tabs', [
  'component_type_base_url' => site_url('production/component-daily'),
  'component_type_filters' => $filters,
  'component_type_active' => (string)($filters['type'] ?? ''),
]); ?>

<?php
$locationFilterValue = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT' || $value === 'EVENT') {
    return 'EVENT';
  }
  if ($value === 'BAR' || $value === 'KITCHEN' || $value === 'REGULER') {
    return 'REGULER';
  }
  return '';
};
$buildLotUrl = static function (array $row, string $status = 'ALL') use ($locationFilterValue): string {
  $params = [
    'q' => trim((string)($row['component_code'] ?? $row['component_name'] ?? '')),
    'status' => $status,
    'location_type' => $locationFilterValue((string)($row['location_type'] ?? '')),
    'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
    'type' => strtoupper(trim((string)($row['component_type'] ?? ''))),
  ];
  return site_url('production/component-lots') . '?' . http_build_query(array_filter($params, static function ($value) {
    return $value !== null && $value !== '';
  }));
};
?>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-daily'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama component / divisi">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Bulan</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape($selectedMonth); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $division): ?>
            <?php $optionId = (int)($division['id'] ?? 0); ?>
            <option value="<?php echo $optionId; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === $optionId) ? 'selected' : ''; ?>><?php echo html_escape($divisionLabel((array)$division)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>><?php echo html_escape((string)$label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-daily'); ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-2">
    <div class="component-daily-matrix-wrap" id="componentDailyMatrixWrap">
      <table class="table table-sm table-striped component-daily-matrix">
        <thead>
          <tr>
            <th class="component-daily-fixed">Komponen</th>
            <th class="component-daily-fixed-2">Divisi</th>
            <th class="component-daily-fixed-3">Ringkasan</th>
            <?php foreach ($dates as $date): ?>
              <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
              <th class="text-center component-daily-date-col <?php echo $isToday ? 'component-daily-today component-daily-today-anchor' : ''; ?>" <?php echo $isToday ? 'data-today-anchor="1"' : ''; ?>>
                <div class="component-daily-headcard">
                  <span class="day"><?php echo html_escape((string)($date['day'] ?? '')); ?></span>
                  <span class="weekday"><?php echo html_escape((string)($date['weekday'] ?? '')); ?></span>
                  <span class="full-date"><?php echo html_escape((string)($date['date'] ?? '')); ?></span>
                  <?php if ($isToday): ?><span class="today-tag">Hari Ini</span><?php endif; ?>
                </div>
              </th>
            <?php endforeach; ?>
            <th class="text-center component-daily-total-group">
              <div class="component-daily-headcard">
                <span class="day">SUM</span>
                <span class="weekday">Total Bulan</span>
                <span class="full-date">Closing terakhir</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="<?php echo $dailyMatrixColspan; ?>" class="text-center text-muted py-4">Belum ada data daily komponen pada filter ini.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $rowIndex => $row): ?>
              <?php $lotSummary = is_array($row['lot_summary'] ?? null) ? $row['lot_summary'] : []; ?>
              <?php $lotRows = array_values((array)($lotSummary['rows'] ?? [])); ?>
              <?php $hasLotChildren = count($lotRows) > 1; ?>
              <?php $singleLot = (!$hasLotChildren && !empty($lotRows)) ? (array)$lotRows[0] : null; ?>
              <?php $lotToggleId = 'componentDailyLot_' . (int)$rowIndex; ?>
              <?php $avgLotCost = $lotAverageCost($lotSummary); ?>
              <?php $summaryClosingQty = (float)($row['total_closing'] ?? 0); ?>
              <?php $summaryAvgCost = (float)($row['avg_cost'] ?? 0); ?>
              <?php $summaryTotalValue = (float)($row['total_value'] ?? 0); ?>
              <?php if ($isCurrentMonthView && (float)($lotSummary['balance_qty'] ?? 0) > 0): ?>
                <?php $summaryClosingQty = (float)($lotSummary['balance_qty'] ?? 0); ?>
                <?php $summaryAvgCost = $avgLotCost; ?>
                <?php $summaryTotalValue = (float)($lotSummary['total_value'] ?? 0); ?>
              <?php endif; ?>
              <tr>
                <td class="component-daily-fixed">
                  <div><a href="<?php echo html_escape($buildLotUrl((array)$row, 'ALL')); ?>" class="fw-semibold text-decoration-none"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></a></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['component_type'] ?? '-')); ?> • <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </td>
                <td class="component-daily-fixed-2">
                  <div><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></small>
                </td>
                <td class="component-daily-fixed-3">
                  <div class="component-daily-summary-card">
                    <div class="summary-head">
                      <span><?php echo html_escape((string)($row['component_type'] ?? '-')); ?></span>
                      <?php if ($hasLotChildren): ?>
                        <button type="button" class="component-daily-lot-parent-toggle" data-lot-toggle="<?php echo html_escape($lotToggleId); ?>" aria-expanded="false">
                          <span><?php echo (int)$lotSummary['lot_count']; ?> lot aktif</span>
                          <i class="ri ri-arrow-down-s-line"></i>
                        </button>
                      <?php elseif (!empty($lotSummary['lot_count'])): ?>
                        <span class="badge bg-label-secondary">1 lot aktif</span>
                      <?php endif; ?>
                    </div>
                    <div class="component-daily-summary-grid">
                      <div class="component-daily-summary-metric">
                        <span class="label">Closing</span>
                        <strong><?php echo number_format($summaryClosingQty, 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label">Nilai</span>
                        <strong><?php echo number_format($summaryTotalValue, 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label"><?php echo $hasLotChildren ? 'Avg Parent' : 'Avg Cost'; ?></span>
                        <strong><?php echo number_format($hasLotChildren ? $avgLotCost : $summaryAvgCost, 2, ',', '.'); ?></strong>
                      </div>
                      <div class="component-daily-summary-metric">
                        <span class="label"><?php echo $hasLotChildren ? 'Range Lot' : 'Lot Cost'; ?></span>
                        <strong><?php echo number_format((float)($lotSummary['min_unit_cost'] ?? 0), 2, ',', '.'); ?><?php if ((float)($lotSummary['max_unit_cost'] ?? 0) !== (float)($lotSummary['min_unit_cost'] ?? 0)): ?> - <?php echo number_format((float)($lotSummary['max_unit_cost'] ?? 0), 2, ',', '.'); ?><?php endif; ?></strong>
                      </div>
                    </div>
                    <div class="summary-sub mt-2"><?php echo $hasLotChildren ? 'Parent menampilkan total saldo dan rata-rata. Expand untuk melihat tiap lot.' : (!empty($lotSummary['has_mixed_cost']) ? 'Cost campur pada lot aktif tunggal ini.' : 'Cost lot aktif masih seragam.'); ?></div>
                  </div>
                </td>
                <?php foreach ($dates as $date): ?>
                  <?php $cell = (array)($row['days'][(string)($date['date'] ?? '')] ?? []); ?>
                  <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
                  <?php $usageOutQty = (float)($cell['usage_out'] ?? 0); ?>
                  <?php $wasteQty = (float)($cell['waste'] ?? 0); ?>
                  <?php $spoilQty = (float)($cell['spoil'] ?? 0); ?>
                  <td class="component-daily-date-cell <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                    <div class="component-daily-metric-card <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                      <div class="component-daily-metric-row metric-open">
                        <span class="label">Awal</span>
                        <span class="value"><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <div class="component-daily-metric-row metric-in">
                        <span class="label">In</span>
                        <span class="value">
                          <span class="component-daily-metric-actions">
                            <span><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span>
                            <button
                              type="button"
                              class="btn btn-sm component-daily-adjust-btn btn-plus"
                              data-action="quick-batch"
                              data-batch-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                              data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                              data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                              data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                              data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                              data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                              data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                              data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                              data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                              title="Input batch produksi"
                              aria-label="Input batch produksi"
                            ><i class="ri ri-add-line"></i></button>
                          </span>
                        </span>
                      </div>
                      <div class="component-daily-metric-row metric-out">
                        <span class="label">Out</span>
                        <span class="value"><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <div class="component-daily-metric-row metric-adj">
                        <span class="label">Adj</span>
                        <span class="value">
                          <span class="component-daily-metric-actions">
                            <span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span>
                            <?php if (!$hasLotChildren): ?>
                              <button
                                type="button"
                                class="btn btn-sm component-daily-adjust-btn btn-pencil"
                                data-action="quick-adjust"
                                data-adjustment-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                                data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                                data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                                data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                                data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                                data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                                data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                                data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                                data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                                data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>"
                                data-selected-lot-id="<?php echo (int)($singleLot['id'] ?? 0); ?>"
                                data-lot-label="<?php echo html_escape((string)($singleLot['lot_no'] ?? '')); ?>"
                                title="Input adjustment cepat"
                                aria-label="Input adjustment cepat"
                              ><i class="ri ri-edit-line"></i></button>
                            <?php endif; ?>
                          </span>
                        </span>
                      </div>
                      <div class="component-daily-metric-row metric-close <?php echo $isToday ? 'component-daily-today-close' : ''; ?>">
                        <span class="label">Akhir</span>
                        <span class="value"><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></span>
                      </div>
                      <?php if ($usageOutQty > 0 || $wasteQty > 0 || $spoilQty > 0): ?>
                        <small class="component-daily-cell-note">
                          <?php
                            $outNotes = [];
                            if ($usageOutQty > 0) {
                              $outNotes[] = 'Use ' . number_format($usageOutQty, 2, ',', '.');
                            }
                            if ($wasteQty > 0) {
                              $outNotes[] = 'Waste ' . number_format($wasteQty, 2, ',', '.');
                            }
                            if ($spoilQty > 0) {
                              $outNotes[] = 'Spoil ' . number_format($spoilQty, 2, ',', '.');
                            }
                            echo html_escape(implode(' | ', $outNotes));
                          ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endforeach; ?>
                <td class="component-daily-total-cell component-daily-total-group">
                  <div class="component-daily-metric-card">
                    <div class="component-daily-metric-row metric-open">
                      <span class="label">Awal</span>
                      <span class="value"><?php echo number_format((float)($row['total_opening'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-in">
                      <span class="label">In</span>
                      <span class="value"><?php echo number_format((float)($row['total_in'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-out">
                      <span class="label">Out</span>
                      <span class="value"><?php echo number_format((float)($row['total_out'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-adj">
                      <span class="label">Adj</span>
                      <span class="value"><?php echo number_format((float)($row['total_adj'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                    <div class="component-daily-metric-row metric-close">
                      <span class="label">Akhir</span>
                      <span class="value"><?php echo number_format((float)($row['total_closing'] ?? 0), 2, ',', '.'); ?></span>
                    </div>
                  </div>
                </td>
              </tr>
              <?php if ($hasLotChildren): ?>
                <?php $lotDailyRows = array_values((array)($row['lot_daily_rows'] ?? [])); ?>
                <?php foreach ($lotDailyRows as $lotRow): ?>
                  <tr class="component-daily-lot-child-row d-none" data-lot-group="<?php echo html_escape($lotToggleId); ?>">
                    <td class="component-daily-fixed">
                      <div class="component-daily-lot-badge">Lot</div>
                      <div class="mt-1 fw-semibold"><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></div>
                      <small class="text-muted">Child lot aktif</small>
                    </td>
                    <td class="component-daily-fixed-2">
                      <div>Masuk <?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo !empty($lotRow['expiry_date']) ? 'Exp ' . html_escape((string)$lotRow['expiry_date']) : 'Tanpa expiry'; ?></small>
                    </td>
                    <td class="component-daily-fixed-3">
                      <div class="component-daily-summary-card">
                        <div class="summary-head">
                          <span>Child Lot</span>
                          <a href="<?php echo html_escape($buildLotUrl((array)$row, 'OPEN')); ?>" class="text-decoration-none small">FIFO</a>
                        </div>
                        <div class="component-daily-summary-grid">
                          <div class="component-daily-summary-metric">
                            <span class="label">Tanggal Masuk</span>
                            <strong><?php echo html_escape((string)($lotRow['receipt_date'] ?? '-')); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Qty Masuk</span>
                            <strong><?php echo number_format((float)($lotRow['qty_in_total'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Cost</span>
                            <strong><?php echo number_format((float)($lotRow['unit_cost'] ?? 0), 2, ',', '.'); ?></strong>
                          </div>
                          <div class="component-daily-summary-metric">
                            <span class="label">Sisa</span>
                            <strong><?php echo number_format((float)($lotRow['total_closing'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></strong>
                          </div>
                        </div>
                        <div class="component-daily-summary-inline">Tanggal masuk, qty, dan cost lot tampil tetap di panel kiri.</div>
                      </div>
                    </td>
                    <?php foreach ($dates as $date): ?>
                      <?php $cell = (array)($lotRow['days'][(string)($date['date'] ?? '')] ?? []); ?>
                      <?php $isToday = $todayInView && (string)($date['date'] ?? '') === $todayDate; ?>
                      <?php $isReceiptDate = (string)($lotRow['receipt_date'] ?? '') === (string)($date['date'] ?? ''); ?>
                      <td class="component-daily-date-cell <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                        <div class="component-daily-metric-card <?php echo $isToday ? 'component-daily-today-soft' : ''; ?>">
                          <div class="component-daily-metric-row metric-open">
                            <span class="label">Awal</span>
                            <span class="value"><?php echo number_format((float)($cell['opening'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-in">
                            <span class="label">In</span>
                            <span class="value"><?php echo number_format((float)($cell['in'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-out">
                            <span class="label">Out</span>
                            <span class="value"><?php echo number_format((float)($cell['out'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <div class="component-daily-metric-row metric-adj">
                            <span class="label">Adj</span>
                            <span class="value">
                              <span class="component-daily-metric-actions">
                                <span><?php echo number_format((float)($cell['adj'] ?? 0), 2, ',', '.'); ?></span>
                                <button
                                  type="button"
                                  class="btn btn-sm component-daily-adjust-btn btn-pencil"
                                  data-action="quick-adjust"
                                  data-adjustment-date="<?php echo html_escape((string)($date['date'] ?? '')); ?>"
                                  data-location-type="<?php echo html_escape((string)($row['location_type'] ?? '')); ?>"
                                  data-location-label="<?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?>"
                                  data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                                  data-division-name="<?php echo html_escape((string)($row['division_name'] ?? '-')); ?>"
                                  data-component-id="<?php echo (int)($row['component_id'] ?? 0); ?>"
                                  data-component-name="<?php echo html_escape((string)($row['component_name'] ?? '')); ?>"
                                  data-uom-id="<?php echo (int)($row['uom_id'] ?? 0); ?>"
                                  data-uom-code="<?php echo html_escape((string)($row['uom_code'] ?? '')); ?>"
                                  data-available-qty="<?php echo html_escape(number_format((float)($cell['closing'] ?? 0), 4, '.', '')); ?>"
                                  data-selected-lot-id="<?php echo (int)($lotRow['id'] ?? 0); ?>"
                                  data-lot-label="<?php echo html_escape((string)($lotRow['lot_no'] ?? '')); ?>"
                                  title="Input adjustment cepat lot"
                                  aria-label="Input adjustment cepat lot"
                                ><i class="ri ri-edit-line"></i></button>
                              </span>
                            </span>
                          </div>
                          <div class="component-daily-metric-row metric-close <?php echo $isToday ? 'component-daily-today-close' : ''; ?>">
                            <span class="label">Akhir</span>
                            <span class="value"><?php echo number_format((float)($cell['closing'] ?? 0), 2, ',', '.'); ?></span>
                          </div>
                          <?php if ($isReceiptDate && (float)($cell['in'] ?? 0) > 0): ?>
                            <small class="component-daily-cell-note">Tanggal masuk lot</small>
                          <?php endif; ?>
                        </div>
                      </td>
                    <?php endforeach; ?>
                    <td class="component-daily-total-cell component-daily-total-group">
                      <div class="component-daily-metric-card">
                        <div class="component-daily-metric-row metric-open">
                          <span class="label">Awal</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_opening'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-in">
                          <span class="label">In</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_in'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-out">
                          <span class="label">Out</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_out'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-adj">
                          <span class="label">Adj</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_adj'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                        <div class="component-daily-metric-row metric-close">
                          <span class="label">Akhir</span>
                          <span class="value"><?php echo number_format((float)($lotRow['total_closing'] ?? 0), 2, ',', '.'); ?></span>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-2">
      <div class="small text-muted">* Kolom Close total menampilkan nilai closing terakhir yang tercatat pada bulan berjalan.</div>
      <?php if ($todayInView): ?>
        <div class="small text-muted component-daily-legend"><span class="component-daily-legend-swatch"></span>Kolom hari ini ditandai biru muda.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyAdjustModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable component-adjust-modal-dialog">
    <div class="modal-content component-adjust-modal-content">
      <div class="modal-header component-adjust-modal-header">
        <div class="component-adjust-modal-titlewrap">
          <span class="component-adjust-modal-kicker">Daily Matrix Adjustment</span>
          <h5 class="modal-title">Adjustment Cepat Komponen</h5>
          <div class="component-adjust-modal-subtitle">Koreksi spoil, waste, plus, dan minus langsung dari matrix harian.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body component-adjust-modal-body">
        <div id="componentDailyAdjustAlert" class="mb-3"></div>
        <div class="component-adjust-quick-context mb-3">
          <div class="fw-semibold" id="qdaComponentLabel">-</div>
          <div class="small text-muted" id="qdaContextLabel">-</div>
        </div>
        <form id="componentDailyAdjustForm">
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Konteks</div>
            <div class="component-adjust-quick-grid">
              <div class="component-adjust-field">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" id="qdaDate" required>
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Stok Tercatat</label>
                <input type="text" class="form-control" id="qdaAvailable" readonly>
              </div>
            </div>
          </div>
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Koreksi Keluar</div>
            <div class="component-adjust-quick-grid">
              <div class="component-adjust-field">
                <label class="form-label">Spoil</label>
                <input type="number" min="0" step="0.01" class="form-control" id="qdaSpoil" value="0">
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Alasan Spoil</label>
                <select class="form-select" id="qdaSpoilReason"></select>
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Waste</label>
                <input type="number" min="0" step="0.01" class="form-control" id="qdaWaste" value="0">
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Alasan Waste</label>
                <select class="form-select" id="qdaWasteReason"></select>
              </div>
            </div>
          </div>
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Koreksi Selisih</div>
            <div class="component-adjust-quick-grid">
              <div class="component-adjust-field">
                <label class="form-label">Plus</label>
                <input type="number" min="0" step="0.01" class="form-control" id="qdaPlus" value="0">
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Alasan Plus</label>
                <select class="form-select" id="qdaPlusReason"></select>
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Harga Plus</label>
                <input type="number" min="0" step="0.0001" class="form-control" id="qdaUnitCost" value="0">
              </div>
              <div class="component-adjust-field">
                <label class="form-label">Minus</label>
                <input type="number" min="0" step="0.01" class="form-control" id="qdaMinus" value="0">
              </div>
              <div class="component-adjust-field full">
                <label class="form-label">Alasan Minus</label>
                <select class="form-select" id="qdaMinusReason"></select>
              </div>
            </div>
          </div>
          <div class="component-adjust-section">
            <div class="component-adjust-section-title">Catatan</div>
            <div class="component-adjust-field">
              <label class="form-label">Catatan Tambahan</label>
              <textarea class="form-control" id="qdaNote" rows="2" placeholder="Catatan adjustment cepat"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer component-adjust-modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="qdaSubmitBtn">Simpan & Post</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentDailyBatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Batch Produksi Cepat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="componentDailyBatchAlert" class="mb-3"></div>
        <div class="component-adjust-quick-context mb-3">
          <div class="fw-semibold" id="qdbComponentLabel">-</div>
          <div class="small text-muted" id="qdbContextLabel">-</div>
        </div>
        <form id="componentDailyBatchForm" class="component-adjust-quick-grid">
          <div>
            <label class="form-label">Tanggal Batch</label>
            <input type="date" class="form-control" id="qdbDate" required>
          </div>
          <div>
            <label class="form-label">Mode Produksi</label>
            <select class="form-select" id="qdbScalingMode">
              <option value="BATCH">Sesuai Resep</option>
              <option value="REFERENCE">Sesuai Bahan Acuan</option>
            </select>
          </div>
          <div id="qdbBatchCountWrap">
            <label class="form-label">Jumlah Batch</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="qdbBatchCount" value="1.00">
          </div>
          <div class="d-none" id="qdbReferenceLineWrap">
            <label class="form-label">Bahan Acuan</label>
            <select class="form-select" id="qdbReferenceLine"></select>
          </div>
          <div class="d-none" id="qdbReferenceQtyWrap">
            <label class="form-label">Qty Aktual Acuan</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="qdbReferenceQty" value="">
          </div>
          <div class="full">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="qdbNotes" rows="2" placeholder="Catatan batch cepat"></textarea>
          </div>
        </form>
        <div class="component-batch-quick-preview mt-3">
          <div class="fw-semibold mb-1" id="qdbPreviewOutput">Preview output belum tersedia.</div>
          <div class="small text-muted" id="qdbPreviewNote">Lengkapi parameter batch untuk memuat preview.</div>
          <div class="component-batch-quick-usage" id="qdbPreviewUsage"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-success" id="qdbSubmitBtn">Simpan & Post</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-lot-toggle]');
    if (!button) {
      return;
    }
    const targetId = String(button.getAttribute('data-lot-toggle') || '');
    if (targetId === '') {
      return;
    }
    const targets = Array.from(document.querySelectorAll('[data-lot-group="' + targetId.replace(/"/g, '\\"') + '"]'));
    if (!targets.length) {
      return;
    }
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    targets.forEach((target) => target.classList.toggle('d-none', isExpanded));
    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
  });
})();

(() => {
  const wrap = document.getElementById('componentDailyMatrixWrap');
  const todayAnchor = wrap?.querySelector('[data-today-anchor="1"]');
  if (!wrap || !todayAnchor) {
    return;
  }

  window.requestAnimationFrame(() => {
    const stickyOffset = 700;
    const targetLeft = Math.max(todayAnchor.offsetLeft - stickyOffset, 0);
    wrap.scrollTo({
      left: targetLeft,
      behavior: 'smooth'
    });
  });
})();

(() => {
  const modalEl = document.getElementById('componentDailyAdjustModal');
  const form = document.getElementById('componentDailyAdjustForm');
  const submitBtn = document.getElementById('qdaSubmitBtn');
  const alertHost = document.getElementById('componentDailyAdjustAlert');
  if (!modalEl || !form || !submitBtn) {
    return;
  }

  function getModalInstance() {
    if (!(window.bootstrap && window.bootstrap.Modal)) {
      return null;
    }
    return window.bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  const reasonOptions = <?php echo json_encode($adjustmentReasonOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-adjustments/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-adjustments/post'); ?>';
  const state = {
    componentId: 0,
    componentName: '',
    locationType: '',
    divisionId: 0,
    selectedLotId: 0,
    lotLabel: '',
    uomId: 0,
    uomCode: ''
  };

  const fields = {
    componentLabel: document.getElementById('qdaComponentLabel'),
    contextLabel: document.getElementById('qdaContextLabel'),
    date: document.getElementById('qdaDate'),
    available: document.getElementById('qdaAvailable'),
    spoil: document.getElementById('qdaSpoil'),
    spoilReason: document.getElementById('qdaSpoilReason'),
    waste: document.getElementById('qdaWaste'),
    wasteReason: document.getElementById('qdaWasteReason'),
    plus: document.getElementById('qdaPlus'),
    plusReason: document.getElementById('qdaPlusReason'),
    unitCost: document.getElementById('qdaUnitCost'),
    minus: document.getElementById('qdaMinus'),
    minusReason: document.getElementById('qdaMinusReason'),
    note: document.getElementById('qdaNote')
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    alertHost.innerHTML = message ? '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>' : '';
  }

  function fillReasonSelect(select, category, selectedValue) {
    const options = reasonOptions?.[category] || {};
    select.innerHTML = Object.keys(options).map((key) => {
      return '<option value="' + escapeHtml(key) + '"' + (String(selectedValue || 'other') === key ? ' selected' : '') + '>' + escapeHtml(options[key]) + '</option>';
    }).join('');
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Respons server bukan JSON valid.');
    }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Permintaan gagal diproses.');
    }
    return json;
  }

  function resetForm() {
    fields.date.value = '';
    fields.available.value = '';
    fields.spoil.value = '0';
    fields.waste.value = '0';
    fields.plus.value = '0';
    fields.unitCost.value = '0';
    fields.minus.value = '0';
    fields.note.value = '';
    fillReasonSelect(fields.spoilReason, 'SPOILAGE', 'other');
    fillReasonSelect(fields.wasteReason, 'WASTE', 'other');
    fillReasonSelect(fields.plusReason, 'ADJUSTMENT_PLUS', 'other');
    fillReasonSelect(fields.minusReason, 'ADJUSTMENT_MINUS', 'other');
    renderAlert('', '');
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="quick-adjust"]');
    if (!button) {
      return;
    }
    resetForm();
    state.componentId = Number(button.dataset.componentId || 0);
    state.componentName = String(button.dataset.componentName || '');
    state.locationType = String(button.dataset.locationType || '');
    state.divisionId = Number(button.dataset.divisionId || 0);
    state.selectedLotId = Number(button.dataset.selectedLotId || 0);
    state.lotLabel = String(button.dataset.lotLabel || '');
    state.uomId = Number(button.dataset.uomId || 0);
    state.uomCode = String(button.dataset.uomCode || '');
    fields.componentLabel.textContent = state.componentName || '-';
    fields.contextLabel.textContent = [button.dataset.locationLabel || '-', button.dataset.divisionName || '-', state.lotLabel ? ('Lot ' + state.lotLabel) : '', state.uomCode || '-', button.dataset.adjustmentDate || '-'].filter(Boolean).join(' • ');
    fields.date.value = String(button.dataset.adjustmentDate || '');
    fields.available.value = String(button.dataset.availableQty || '0') + ' ' + state.uomCode;
    const modal = getModalInstance();
    if (!modal) {
      window.alert('Modal adjustment belum siap. Muat ulang halaman lalu coba lagi.');
      return;
    }
    modal.show();
  });

  submitBtn.addEventListener('click', async () => {
    if (!state.componentId || !state.uomId || !fields.date.value) {
      renderAlert('danger', 'Konteks adjustment belum lengkap.');
      return;
    }

    const payload = {
      adjustment_date: fields.date.value,
      location_type: state.locationType,
      division_id: state.divisionId || '',
      notes: String(fields.note.value || ''),
      lines: [{
        component_id: state.componentId,
        uom_id: state.uomId,
        selected_lot_id: state.selectedLotId > 0 ? state.selectedLotId : '',
        qty_spoil: parseFloat(fields.spoil.value || '0') || 0,
        spoil_reason_code: String(fields.spoilReason.value || 'other'),
        qty_waste: parseFloat(fields.waste.value || '0') || 0,
        waste_reason_code: String(fields.wasteReason.value || 'other'),
        qty_adjust_pos: parseFloat(fields.plus.value || '0') || 0,
        adjustment_plus_reason_code: String(fields.plusReason.value || 'other'),
        unit_cost: parseFloat(fields.unitCost.value || '0') || 0,
        qty_adjust_neg: parseFloat(fields.minus.value || '0') || 0,
        adjustment_minus_reason_code: String(fields.minusReason.value || 'other'),
        note: String(fields.note.value || '')
      }]
    };

    const totalQty = payload.lines[0].qty_spoil + payload.lines[0].qty_waste + payload.lines[0].qty_adjust_pos + payload.lines[0].qty_adjust_neg;
    if (totalQty <= 0) {
      renderAlert('danger', 'Isi minimal satu nilai adjustment.');
      return;
    }
    if (payload.lines[0].qty_adjust_pos > 0 && payload.lines[0].unit_cost <= 0) {
      renderAlert('danger', 'Harga Plus wajib diisi jika qty plus lebih dari 0.');
      return;
    }

    submitBtn.disabled = true;
    renderAlert('info', 'Menyimpan adjustment...');
    try {
      const saveResult = await postJson(saveUrl, payload);
      await postJson(postBaseUrl + '/' + encodeURIComponent(String(saveResult.id || 0)), {});
      renderAlert('success', 'Adjustment berhasil diposting. Memuat ulang data...');
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      renderAlert('danger', error.message || 'Adjustment gagal diproses.');
    } finally {
      submitBtn.disabled = false;
    }
  });
})();

(() => {
  const modalEl = document.getElementById('componentDailyBatchModal');
  const submitBtn = document.getElementById('qdbSubmitBtn');
  if (!modalEl || !submitBtn) {
    return;
  }

  function getModalInstance() {
    if (!(window.bootstrap && window.bootstrap.Modal)) {
      return null;
    }
    return window.bootstrap.Modal.getOrCreateInstance(modalEl);
  }

  const previewUrl = '<?php echo site_url('production/component-batches/preview'); ?>';
  const saveUrl = '<?php echo site_url('production/component-batches/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-batches/post'); ?>';
  const alertHost = document.getElementById('componentDailyBatchAlert');
  const state = { componentId: 0, divisionId: 0, locationType: '', uomId: 0, uomCode: '', componentName: '' };
  let currentPreview = null;

  const fields = {
    componentLabel: document.getElementById('qdbComponentLabel'),
    contextLabel: document.getElementById('qdbContextLabel'),
    date: document.getElementById('qdbDate'),
    scalingMode: document.getElementById('qdbScalingMode'),
    batchCount: document.getElementById('qdbBatchCount'),
    referenceLine: document.getElementById('qdbReferenceLine'),
    referenceQty: document.getElementById('qdbReferenceQty'),
    notes: document.getElementById('qdbNotes'),
    previewOutput: document.getElementById('qdbPreviewOutput'),
    previewNote: document.getElementById('qdbPreviewNote'),
    previewUsage: document.getElementById('qdbPreviewUsage'),
    batchCountWrap: document.getElementById('qdbBatchCountWrap'),
    referenceLineWrap: document.getElementById('qdbReferenceLineWrap'),
    referenceQtyWrap: document.getElementById('qdbReferenceQtyWrap')
  };

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    alertHost.innerHTML = message ? '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>' : '';
  }

  function formatQty(value) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    }).then(async (response) => {
      const text = await response.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (error) {
        throw new Error('Respons server bukan JSON valid.');
      }
      if (!response.ok || !json.ok) {
        throw new Error(json.message || 'Permintaan gagal diproses.');
      }
      return json;
    });
  }

  function syncModeUi() {
    const mode = String(fields.scalingMode.value || 'BATCH').toUpperCase();
    const isReference = mode === 'REFERENCE';
    fields.batchCountWrap.classList.toggle('d-none', isReference);
    fields.referenceLineWrap.classList.toggle('d-none', !isReference);
    fields.referenceQtyWrap.classList.toggle('d-none', !isReference);
  }

  function resetPreview(message) {
    currentPreview = null;
    fields.previewOutput.textContent = 'Preview output belum tersedia.';
    fields.previewNote.textContent = message || 'Lengkapi parameter batch untuk memuat preview.';
    fields.previewUsage.innerHTML = '';
  }

  function renderReferenceOptions(rows, selectedValue) {
    const list = Array.isArray(rows) ? rows : [];
    fields.referenceLine.innerHTML = '<option value="">Pilih bahan acuan...</option>' + list.map((row) => {
      return '<option value="' + escapeHtml(row.line_no) + '"' + (String(selectedValue || '') === String(row.line_no) ? ' selected' : '') + '>' +
        escapeHtml((row.label || '-') + ' • ' + formatQty(row.base_qty || 0) + ' ' + (row.uom_code || '')) +
      '</option>';
    }).join('');
  }

  function renderPreview(preview) {
    currentPreview = preview;
    const component = preview.component || {};
    fields.previewOutput.textContent = 'Output ' + formatQty(preview.output_qty || 0) + ' ' + String(component.uom_code || state.uomCode || '');
    fields.previewNote.textContent = String(preview.scaling_mode || 'BATCH').toUpperCase() === 'REFERENCE'
      ? 'Output dihitung dari bahan acuan aktual.'
      : 'Output dihitung dari kelipatan batch resep dasar.';
    renderReferenceOptions(preview.reference_options || [], (preview.reference || {}).line_no || '');
    const usageLines = (Array.isArray(preview.lines) ? preview.lines : []).filter((line) => {
      const role = String(line.plan_role || '').toUpperCase();
      return role === 'MATERIAL_USAGE' || role === 'COMPONENT_USAGE';
    });
    fields.previewUsage.innerHTML = usageLines.map((line) => {
      return '<span class="badge text-bg-light border">' +
        escapeHtml(line.source_label || '-') + ': ' + escapeHtml(formatQty(line.required_qty || 0)) + ' ' + escapeHtml(line.uom_code || '') +
      '</span>';
    }).join('');
    if (Array.isArray(preview.issues) && preview.issues.length) {
      renderAlert('danger', preview.issues.join(' | '));
    } else {
      renderAlert('', '');
    }
  }

  async function loadPreview() {
    if (!state.componentId || !state.locationType) {
      resetPreview('Konteks batch belum lengkap.');
      return;
    }
    const mode = String(fields.scalingMode.value || 'BATCH').toUpperCase();
    const params = new URLSearchParams({
      component_id: String(state.componentId),
      location_type: String(state.locationType),
      scaling_mode: mode,
      batch_count: String(fields.batchCount.value || ''),
      reference_line_no: String(fields.referenceLine.value || ''),
      reference_actual_qty: String(fields.referenceQty.value || '')
    });
    if (mode === 'REFERENCE') {
      if (!fields.referenceLine.value) {
        resetPreview('Pilih bahan acuan untuk melihat preview batch.');
        return;
      }
      if (!(parseFloat(fields.referenceQty.value || '0') > 0)) {
        resetPreview('Isi qty aktual bahan acuan terlebih dahulu.');
        return;
      }
    } else if (!(parseFloat(fields.batchCount.value || '0') > 0)) {
      resetPreview('Jumlah batch harus lebih dari 0.');
      return;
    }

    try {
      const response = await fetch(previewUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const text = await response.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (error) {
        throw new Error('Respons preview batch bukan JSON valid.');
      }
      if (!response.ok || !json.ok) {
        throw new Error(json.message || 'Preview batch gagal dimuat.');
      }
      renderPreview(json);
    } catch (error) {
      currentPreview = null;
      renderAlert('danger', error.message || 'Preview batch gagal dimuat.');
      resetPreview('Preview batch gagal dimuat.');
    }
  }

  function resetForm() {
    fields.date.value = '';
    fields.scalingMode.value = 'BATCH';
    fields.batchCount.value = '1.00';
    fields.referenceQty.value = '';
    fields.notes.value = '';
    renderReferenceOptions([], '');
    syncModeUi();
    resetPreview('Lengkapi parameter batch untuk memuat preview.');
    renderAlert('', '');
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action="quick-batch"]');
    if (!button) {
      return;
    }
    resetForm();
    state.componentId = Number(button.dataset.componentId || 0);
    state.divisionId = Number(button.dataset.divisionId || 0);
    state.locationType = String(button.dataset.locationType || '');
    state.uomId = Number(button.dataset.uomId || 0);
    state.uomCode = String(button.dataset.uomCode || '');
    state.componentName = String(button.dataset.componentName || '');
    fields.componentLabel.textContent = state.componentName || '-';
    fields.contextLabel.textContent = [button.dataset.locationLabel || '-', button.dataset.divisionName || '-', state.uomCode || '-', button.dataset.batchDate || '-'].join(' • ');
    fields.date.value = String(button.dataset.batchDate || '');
    const modal = getModalInstance();
    if (!modal) {
      window.alert('Modal batch belum siap. Muat ulang halaman lalu coba lagi.');
      return;
    }
    modal.show();
    loadPreview();
  });

  fields.scalingMode.addEventListener('change', () => { syncModeUi(); loadPreview(); });
  fields.batchCount.addEventListener('input', loadPreview);
  fields.batchCount.addEventListener('change', loadPreview);
  fields.referenceLine.addEventListener('change', loadPreview);
  fields.referenceQty.addEventListener('input', loadPreview);
  fields.referenceQty.addEventListener('change', loadPreview);

  submitBtn.addEventListener('click', async () => {
    if (!state.componentId || !state.divisionId || !state.locationType || !fields.date.value) {
      renderAlert('danger', 'Konteks batch belum lengkap.');
      return;
    }
    if (!currentPreview) {
      renderAlert('danger', 'Preview batch belum siap.');
      return;
    }
    if (currentPreview.summary && currentPreview.summary.has_shortage) {
      renderAlert('danger', 'Batch masih punya shortage dan belum bisa diposting.');
      return;
    }
    const payload = {
      batch_date: String(fields.date.value || ''),
      location_type: state.locationType,
      division_id: state.divisionId,
      component_id: state.componentId,
      output_qty: String(currentPreview.output_qty || ''),
      output_uom_id: String((currentPreview.component || {}).uom_id || state.uomId || ''),
      scaling_mode: String(fields.scalingMode.value || 'BATCH'),
      batch_count: String(fields.batchCount.value || ''),
      reference_line_no: String(fields.referenceLine.value || ''),
      reference_actual_qty: String(fields.referenceQty.value || ''),
      notes: String(fields.notes.value || '')
    };

    submitBtn.disabled = true;
    renderAlert('info', 'Menyimpan batch...');
    try {
      const saveResult = await postJson(saveUrl, payload);
      await postJson(postBaseUrl + '/' + encodeURIComponent(String(saveResult.id || 0)), {});
      renderAlert('success', 'Batch berhasil diposting. Memuat ulang data...');
      window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
      renderAlert('danger', error.message || 'Batch gagal diproses.');
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
</script>

