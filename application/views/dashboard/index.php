<?php
$filters      = $filters      ?? ['period' => 'today', 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'), 'period_label' => 'Hari Ini'];
$chartFilters = $chart_filters ?? ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'period_label' => 'Bulan Ini'];
$kpi          = $kpi          ?? [];
$trend        = $trend        ?? ['labels' => [], 'sales' => [], 'orders' => [], 'po' => [], 'sr' => []];
$posStatusRows    = is_array($pos_status_rows  ?? null) ? $pos_status_rows  : [];
$posScopeRows     = is_array($pos_scope_rows   ?? null) ? $pos_scope_rows   : [];
$stockBreakdown   = is_array($stock_breakdown  ?? null) ? $stock_breakdown  : ['warehouse' => [], 'division' => [], 'component' => []];
$stockProductLive = is_array($stock_product_live ?? null) ? $stock_product_live : ['summary' => [], 'rows' => []];
$adjustmentSummary = is_array($adjustment_summary ?? null) ? $adjustment_summary : [
    'daily' => ['label' => 'Hari Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
    'weekly' => ['label' => 'Minggu Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
    'monthly' => ['label' => 'Bulan Ini', 'warehouse' => ['rows' => [], 'totals' => []], 'division' => ['rows' => [], 'totals' => []], 'component' => ['rows' => [], 'totals' => []]],
];
$topSellingProducts = is_array($top_selling_products ?? null) ? $top_selling_products : [
    'daily' => ['label' => 'Hari Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
    'weekly' => ['label' => 'Minggu Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
    'monthly' => ['label' => 'Bulan Ini', 'groups' => ['FOOD' => ['label' => 'Food', 'rows' => []], 'BEVERAGE' => ['label' => 'Beverage', 'rows' => []]]],
];
$criticalStockRows  = is_array($critical_stock_rows   ?? null) ? $critical_stock_rows   : [];
$negativeStockRows   = is_array($negative_stock_rows    ?? null) ? $negative_stock_rows    : [];
$recentActivity      = is_array($recent_activity        ?? null) ? $recent_activity        : [];
$prodLiveHiddenCats  = is_array($prod_live_hidden_cats  ?? null) ? $prod_live_hidden_cats  : [];

$cur = static fn($v): string => 'Rp ' . number_format((float)$v, 0, ',', '.');

$statusPalette = [
    'PAID'=>'#2e7d32','SERVED'=>'#2e7d32','READY'=>'#0288d1','IN_KITCHEN'=>'#7b1fa2',
    'CONFIRMED'=>'#1565c0','PENDING'=>'#ef6c00','PAID_PARTIAL'=>'#f9a825','DRAFT'=>'#8d6e63',
    'VOID'=>'#c62828','APPROVED'=>'#00897b','ORDERED'=>'#3949ab','PARTIAL_RECEIVED'=>'#5e35b1',
    'RECEIVED'=>'#43a047','SUBMITTED'=>'#fb8c00','PARTIAL_FULFILLED'=>'#8e24aa','FULFILLED'=>'#2e7d32',
];
$sLabels = []; $sTotals = []; $sColors = [];
foreach ($posStatusRows as $r) {
    $c = strtoupper((string)($r['status'] ?? '-'));
    $sLabels[] = $c; $sTotals[] = (int)($r['total'] ?? 0); $sColors[] = $statusPalette[$c] ?? '#8d6e63';
}

// Group product live rows by division (skip EVENT division)
$productByDivision = [];
$productCatsByDiv  = [];
foreach (($stockProductLive['rows'] ?? []) as $r) {
    $div = (string)($r['division_name'] ?? 'LAIN');
    if (strtoupper($div) === 'EVENT') continue;
    $productByDivision[$div][] = $r;
    $cat = (string)($r['category_name'] ?? '');
    if ($cat !== '' && !in_array($cat, $productCatsByDiv[$div] ?? [], true)) {
        $productCatsByDiv[$div][] = $cat;
    }
}

// Group stock breakdown division by location_name for sub-tabs
$divisionSubTabs = [];
foreach (($stockBreakdown['division'] ?? []) as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $divisionSubTabs[$loc][] = $r;
}
$componentSubTabs = [];
foreach (($stockBreakdown['component'] ?? []) as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $componentSubTabs[$loc][] = $r;
}

// Group critical stock rows by division/scope for JS filter
$criticalByDivision = ['ALL' => $criticalStockRows];
foreach ($criticalStockRows as $r) {
    $loc = (string)($r['location_name'] ?? '-');
    $criticalByDivision[$loc][] = $r;
}
$criticalLocations = array_keys(array_diff_key($criticalByDivision, ['ALL' => true]));
?>

<style>
  .fd { display:grid; gap:1rem; }
  .fd-hero { background:linear-gradient(135deg,#f7efe8 0%,#fdf9f6 54%,#fffdfb 100%); border:1px solid rgba(170,95,78,.14); border-radius:24px; padding:1.25rem 1.3rem; box-shadow:0 14px 30px rgba(109,47,30,.08); }
  .fd-kicker { display:inline-flex; align-items:center; gap:.45rem; font-size:.78rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#8f2d23; }
  .fd-title { margin:.45rem 0 .25rem; font-size:1.8rem; font-weight:800; color:#6f2119; }
  .fd-desc { margin:0; color:#7e6761; }
  .fd-card { border:1px solid rgba(170,95,78,.16); border-radius:20px; box-shadow:0 12px 24px rgba(109,47,30,.08); background:#fff; height:100%; }
  .fd-filter { border-radius:18px; border:1px solid rgba(170,95,78,.16); background:rgba(255,255,255,.88); padding:.9rem; }
  .fd-kpi { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:1rem; }
  .fd-kpi-c { padding:1rem 1rem .95rem; }
  .fd-kpi-lbl { color:#8d6a63; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
  .fd-kpi-val { margin-top:.5rem; font-size:1.6rem; line-height:1.1; font-weight:800; color:#6f2119; }
  .fd-kpi-note { margin-top:.35rem; font-size:.82rem; color:#8b7772; }
  .fd-sec-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
  .fd-sec-title { margin:0; font-size:1.08rem; font-weight:800; color:#6f2119; }
  .fd-sec-sub { margin:.18rem 0 0; color:#8b7772; font-size:.85rem; }
  .fd-chart-grid { display:grid; gap:1rem; grid-template-columns:minmax(0,1.5fr) minmax(320px,.8fr); }
  .fd-3col { display:grid; gap:1rem; grid-template-columns:repeat(3,minmax(0,1fr)); }
  .fd-2col { display:grid; gap:1rem; grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr); }
  .fd-2scope { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
  .fd-scope-card { padding:1rem; background:linear-gradient(160deg,rgba(255,247,242,.96),rgba(255,255,255,.98)); }
  .fd-scope-head { display:flex; justify-content:space-between; align-items:center; gap:.75rem; }
  .fd-scope-title { margin:0; font-size:1.15rem; font-weight:800; color:#6f2119; }
  .fd-scope-val { margin-top:.45rem; font-size:1.55rem; font-weight:800; color:#8f2d23; }
  .fd-scope-meta { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; margin-top:.95rem; }
  .fd-scope-meta-lbl { color:#8b7772; font-size:.74rem; font-weight:700; text-transform:uppercase; }
  .fd-scope-meta-val { margin-top:.22rem; font-weight:800; color:#6f2119; }
  .fd-list { display:grid; gap:.7rem; }
  .fd-item { display:flex; align-items:center; justify-content:space-between; gap:.8rem; border-radius:16px; padding:.9rem 1rem; background:#fffaf7; border:1px solid rgba(170,95,78,.14); }
  .fd-item.minus { background:#fff5f5; border-color:rgba(198,40,40,.18); }
  .fd-item.kritis { background:#fffaf5; border-color:rgba(239,108,0,.18); }
  .fd-item-title { font-weight:800; color:#6f2119; }
  .fd-item-meta { margin-top:.16rem; color:#8b7772; font-size:.84rem; }
  .fd-pill { display:inline-flex; align-items:center; padding:.3rem .62rem; border-radius:999px; background:#fff0ea; color:#8f2d23; border:1px solid rgba(170,95,78,.16); font-size:.75rem; font-weight:800; }
  .fd-pill.minus { background:#fff0f0; color:#c62828; border-color:rgba(198,40,40,.2); }
  .fd-pill.kritis { background:#fff8f0; color:#e65100; border-color:rgba(239,108,0,.2); }
  .fd-pill.ok { background:#f0fff4; color:#2e7d32; border-color:rgba(46,125,50,.2); }
  .fd-empty { color:#8b7772; text-align:center; padding:1rem; }
  /* Tabs */
  .fd-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.75rem; }
  .fd-tab { padding:.35rem .8rem; border-radius:999px; font-size:.78rem; font-weight:700; cursor:pointer; border:1px solid rgba(170,95,78,.2); background:#fff; color:#8b7772; transition:all .13s; }
  .fd-tab.on { background:#8f2d23; color:#fff; border-color:#8f2d23; }
  .fd-sub-tab { padding:.28rem .65rem; border-radius:999px; font-size:.74rem; font-weight:700; cursor:pointer; border:1px solid rgba(170,95,78,.18); background:#fff; color:#8b7772; transition:all .13s; }
  .fd-sub-tab.on { background:#6f2119; color:#fff; border-color:#6f2119; }
  /* Scrollable list */
  .fd-scroll { max-height:380px; overflow-y:auto; display:grid; gap:.7rem; padding-right:.25rem; scrollbar-width:thin; }
  .fd-scroll::-webkit-scrollbar { width:4px; }
  .fd-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  /* Product stock */
  .fd-prod-row { border-radius:16px; border:1px solid rgba(170,95,78,.14); overflow:hidden; }
  .fd-prod-head { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; background:#fffaf7; cursor:pointer; }
  .fd-prod-head:hover { background:#fff3ec; }
  .fd-prod-name { font-weight:800; color:#6f2119; font-size:.95rem; }
  .fd-prod-meta { margin-top:.14rem; color:#8b7772; font-size:.82rem; }
  .fd-prod-right { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
  .fd-prod-qty { font-weight:800; font-size:1rem; color:#6f2119; text-align:right; }
  .fd-prod-uom { font-size:.74rem; color:#8b7772; }
  .fd-prod-body { display:none; border-top:1px solid rgba(170,95,78,.12); padding:.8rem 1rem; background:#fff; }
  .fd-prod-body.open { display:block; }
  .fd-recipe-row { display:flex; align-items:center; justify-content:space-between; padding:.35rem 0; border-bottom:1px solid rgba(170,95,78,.08); font-size:.84rem; }
  .fd-recipe-row:last-child { border-bottom:0; }
  .fd-recipe-name { font-weight:700; color:#4a2c2a; }
  .fd-recipe-stock { font-weight:800; }
  .fd-recipe-stock.ok { color:#2e7d32; }
  .fd-recipe-stock.bad { color:#c62828; }
  .fd-prod-summ { display:grid; grid-template-columns:repeat(4,1fr); gap:.6rem; margin-bottom:.9rem; }
  .fd-prod-summ-card { border-radius:14px; padding:.7rem; text-align:center; border:1px solid rgba(170,95,78,.14); }
  .fd-prod-summ-val { font-size:1.5rem; font-weight:800; color:#6f2119; }
  .fd-prod-summ-val.red { color:#c62828; }
  .fd-prod-summ-val.orange { color:#e65100; }
  .fd-prod-summ-val.green { color:#2e7d32; }
  .fd-prod-summ-lbl { font-size:.73rem; font-weight:700; color:#8b7772; text-transform:uppercase; margin-top:.2rem; }
  .fd-adjust-grid { display:grid; gap:1rem; grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr); align-items:stretch; }
  .fd-adjust-panel { display:flex; flex-direction:column; overflow:hidden; min-height:0; }
  .fd-adjust-panel-summary { height:760px; }
  .fd-adjust-panel-list { height:760px; }
  .fd-adjust-period { display:flex; flex-direction:column; flex:1; min-height:0; }
  .fd-adj-content { display:flex; flex-direction:column; flex:1; min-height:0; }
  .fd-adjust-scroll { flex:1; min-height:0; max-height:none; overflow-y:auto; display:grid; gap:.7rem; padding-right:.25rem; scrollbar-width:thin; }
  .fd-adjust-panel-list .fd-adjust-scroll { max-height:620px; }
  .fd-adjust-panel-summary .fd-adjust-scroll { max-height:680px; }
  .fd-adjust-scroll::-webkit-scrollbar { width:4px; }
  .fd-adjust-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  .fd-adjust-card { border-radius:16px; border:1px solid rgba(170,95,78,.14); background:#fffaf7; padding:.9rem 1rem; }
  .fd-adjust-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
  .fd-adjust-title { font-weight:800; color:#6f2119; }
  .fd-adjust-code { margin-top:.12rem; color:#8b7772; font-size:.8rem; }
  .fd-adjust-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.55rem; margin-top:.72rem; }
  .fd-adjust-metric { border-radius:12px; border:1px solid rgba(170,95,78,.12); background:#fff; padding:.5rem .6rem; }
  .fd-adjust-metric .lbl { display:block; font-size:.69rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#8b7772; }
  .fd-adjust-metric .val { display:block; margin-top:.18rem; font-weight:800; color:#6f2119; }
  .fd-adjust-metric .val.plus { color:#2e7d32; }
  .fd-adjust-metric .val.minus { color:#c62828; }
  .fd-adjust-detail { margin-top:.72rem; border-top:1px dashed rgba(170,95,78,.18); padding-top:.72rem; }
  .fd-adjust-detail summary { cursor:pointer; list-style:none; font-size:.82rem; font-weight:800; color:#8f2d23; }
  .fd-adjust-detail summary::-webkit-details-marker { display:none; }
  .fd-adjust-detail-list { display:grid; gap:.5rem; margin-top:.65rem; }
  .fd-adjust-detail-item { border-radius:12px; border:1px solid rgba(170,95,78,.12); background:#fff; padding:.6rem .72rem; }
  .fd-adjust-detail-top { display:flex; justify-content:space-between; gap:.75rem; align-items:flex-start; }
  .fd-adjust-detail-meta { color:#8b7772; font-size:.78rem; margin-top:.14rem; }
  .fd-topprod-period-grid { display:grid; gap:1rem; grid-template-columns:repeat(2,minmax(0,1fr)); }
  .fd-topprod-card { padding:1rem; background:linear-gradient(160deg,rgba(255,247,242,.96),rgba(255,255,255,.98)); }
  .fd-topprod-scroll { max-height:820px; overflow-y:auto; padding-right:.25rem; scrollbar-width:thin; }
  .fd-topprod-scroll::-webkit-scrollbar { width:4px; }
  .fd-topprod-scroll::-webkit-scrollbar-thumb { background:rgba(170,95,78,.25); border-radius:4px; }
  .fd-topprod-subtitle { margin:.2rem 0 .45rem; font-size:.8rem; font-weight:800; color:#8f2d23; text-transform:uppercase; letter-spacing:.04em; }
  .fd-topprod-row { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; border-radius:14px; padding:.72rem .78rem; background:#fff; border:1px solid rgba(170,95,78,.12); }
  .fd-topprod-rank { width:1.9rem; height:1.9rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:#8f2d23; color:#fff; font-size:.76rem; font-weight:800; flex-shrink:0; }
  .fd-topprod-name { font-weight:800; color:#6f2119; }
  .fd-topprod-meta { margin-top:.15rem; color:#8b7772; font-size:.79rem; }
  .fd-topprod-qty { font-weight:800; color:#6f2119; text-align:right; }
  .fd-topprod-net { margin-top:.15rem; color:#2e7d32; font-size:.78rem; font-weight:700; text-align:right; }
  @media (max-width:1399.98px) {
    .fd-kpi { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .fd-3col { grid-template-columns:1fr; }
    .fd-topprod-period-grid { grid-template-columns:1fr; }
  }
  @media (max-width:991.98px) {
    .fd-chart-grid, .fd-2col, .fd-adjust-grid { grid-template-columns:1fr; }
    .fd-2scope { grid-template-columns:1fr; }
    .fd-adjust-panel-summary, .fd-adjust-panel-list { height:auto; }
  }
  @media (max-width:767.98px) { .fd-kpi { grid-template-columns:1fr; } }
  /* Category filter */
  .fd-cat-cb { width:.85rem; height:.85rem; cursor:pointer; accent-color:#8f2d23; }
  #prodCatFilterBtn.active { background:#8f2d23; color:#fff; border-color:#8f2d23; }
</style>

<div class="fd">
  <!-- Hero -->
  <section class="fd-hero">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
      <div>
        <div class="fd-kicker"><i class="ri-dashboard-line"></i> Ringkasan Finance</div>
        <h1 class="fd-title">Dashboard Operasional</h1>
        <p class="fd-desc">Selamat datang, <strong><?= htmlspecialchars($current_user['username'] ?? '') ?></strong>. Data KPI: <strong><?= htmlspecialchars($filters['period_label']) ?></strong>. Grafik: <strong><?= htmlspecialchars($chartFilters['period_label']) ?></strong>.</p>
      </div>
      <span class="fd-pill">Update <?= date('d-m-Y H:i') ?></span>
    </div>
  </section>

  <!-- Filter -->
  <form method="get" action="<?= base_url('dashboard') ?>" class="fd-filter fd-card">
    <div class="row g-2 align-items-end">
      <div class="col-xl-3 col-md-4">
        <label class="form-label mb-1">Periode KPI</label>
        <select name="period" id="dashPeriod" class="form-select">
          <option value="today"  <?= ($filters['period'] ?? '') === 'today'  ? 'selected' : '' ?>>Hari Ini</option>
          <option value="month"  <?= ($filters['period'] ?? '') === 'month'  ? 'selected' : '' ?>>Bulan Ini</option>
          <option value="7"      <?= ($filters['period'] ?? '') === '7'      ? 'selected' : '' ?>>7 Hari Terakhir</option>
          <option value="30"     <?= ($filters['period'] ?? '') === '30'     ? 'selected' : '' ?>>30 Hari Terakhir</option>
          <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom</option>
        </select>
      </div>
      <div class="col-xl-3 col-md-4 fd-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Dari</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-xl-3 col-md-4 fd-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Sampai</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-xl-3 col-md-12 d-grid">
        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
      </div>
    </div>
  </form>

  <!-- KPI -->
  <section class="fd-kpi">
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Transaksi POS</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['transaction_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">Order aktif (non-draft, non-void) pada periode terpilih.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Omzet POS</div>
      <div class="fd-kpi-val"><?= $cur($kpi['sales_total'] ?? 0) ?></div>
      <div class="fd-kpi-note">Gross <?= $cur($kpi['gross_sales_total'] ?? 0) ?> − refund <?= $cur($kpi['refund_total'] ?? 0) ?>.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Stok Kritis</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['critical_stock_total'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">Row stok gudang, divisi, dan component yang nol atau minus.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Karyawan Hadir</div>
      <div class="fd-kpi-val"><?= number_format((int)($kpi['present_employee_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fd-kpi-note">PRESENT / LATE / HOLIDAY dalam periode aktif.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Nilai PO</div>
      <div class="fd-kpi-val" style="font-size:1.25rem"><?= $cur($kpi['total_nilai_po'] ?? 0) ?></div>
      <div class="fd-kpi-note">Total PO non-void dalam periode terpilih.</div>
    </div>
    <div class="fd-card fd-kpi-c">
      <div class="fd-kpi-lbl">Nilai SR</div>
      <div class="fd-kpi-val" style="font-size:1.25rem"><?= $cur($kpi['total_nilai_sr'] ?? 0) ?></div>
      <div class="fd-kpi-note">Estimasi nilai store request (harga katalog) dalam periode.</div>
    </div>
  </section>

  <!-- ===== ALERT: Stok Negatif ===== -->
  <?php if (!empty($negativeStockRows)): ?>
  <section id="negativeStockAlert" style="border-radius:20px;border:1.5px solid rgba(198,40,40,.35);background:linear-gradient(135deg,#fff5f5 0%,#fff9f9 100%);padding:1.15rem 1.3rem;box-shadow:0 8px 24px rgba(198,40,40,.10);">
    <div class="fd-sec-head" style="margin-bottom:.75rem;">
      <div>
        <h2 class="fd-sec-title" style="color:#c62828;display:flex;align-items:center;gap:.5rem;">
          <i class="ri-error-warning-fill" style="color:#c62828;"></i>
          Hutang Stok Negatif — <?= count($negativeStockRows) ?> Item
        </h2>
        <p class="fd-sec-sub" style="color:#8b2020;">Stok di bawah nol akibat penggunaan POS sebelum batch diproduksi. Segera lakukan batch produksi atau penyesuaian stok.</p>
      </div>
      <button onclick="document.getElementById('negStockBody').classList.toggle('d-none')" class="btn btn-sm btn-outline-danger" style="border-radius:12px;white-space:nowrap;">
        Lihat Detail
      </button>
    </div>
    <div id="negStockBody" class="d-none">
      <?php
      // Group: location_name → [material|component] → rows
      $negByDiv = [];
      foreach ($negativeStockRows as $r) {
          $loc = (string)($r['location_name'] ?? 'Lainnya');
          $cat = ((string)($r['stock_type'] ?? '') === 'component') ? 'component' : 'material';
          $negByDiv[$loc][$cat][] = $r;
      }
      uksort($negByDiv, function ($a, $b) {
          static $o = ['BAR' => 0, 'KITCHEN' => 1, 'Gudang Pusat' => 99];
          return (($o[$a] ?? 50) <=> ($o[$b] ?? 50)) ?: strcmp($a, $b);
      });
      $divIds = [];
      foreach ($negByDiv as $loc => $_cats) {
          $divIds[$loc] = 'negDiv_' . preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($loc));
      }
      $fdItemRow = function (array $r): void {
          $qty      = (float)($r['qty_balance'] ?? 0);
          $uom      = htmlspecialchars((string)($r['uom_code'] ?? ''));
          $type     = (string)($r['stock_type'] ?? '');
          $itemName = htmlspecialchars((string)($r['item_name'] ?? '-'));
          $da = 'data-neg-adj="1"'
              . ' data-stock-type="' . htmlspecialchars($type) . '"'
              . ' data-item-name="' . $itemName . '"'
              . ' data-uom-code="' . $uom . '"';
          if ($type === 'component') {
              $da .= ' data-component-id="' . (int)($r['component_id'] ?? 0) . '"'
                  . ' data-uom-id="' . (int)($r['uom_id'] ?? 0) . '"'
                  . ' data-division-id="' . (int)($r['division_id'] ?? 0) . '"'
                  . ' data-location-type="' . htmlspecialchars((string)($r['location_type'] ?? '')) . '"';
          } else {
              $da .= ' data-item-id="' . (int)($r['item_id'] ?? 0) . '"'
                  . ' data-content-uom-id="' . (int)($r['content_uom_id'] ?? 0) . '"'
                  . ' data-division-id="' . (int)($r['division_id'] ?? 0) . '"'
                  . ' data-destination-type="' . htmlspecialchars((string)($r['destination_type'] ?? '')) . '"';
          }
          echo '<div class="fd-item minus" style="align-items:center;">'
              . '<div style="flex:1;min-width:0"><div class="fd-item-title" style="color:#c62828;">' . $itemName . '</div></div>'
              . '<div class="d-flex align-items-center gap-2 flex-shrink-0">'
              . '<button type="button" class="btn btn-sm py-0 px-1" title="Buat Adjustment" '
              . 'style="border:1px solid #f5a5a5;border-radius:8px;background:#fff5f5;color:#c62828;line-height:1.6" ' . $da . '>'
              . '<i class="ri ri-edit-line"></i></button>'
              . '<span class="fd-pill minus">' . number_format($qty, 2, ',', '.') . ' ' . $uom . '</span>'
              . '</div>'
              . '</div>';
      };
      $first1 = true;
      ?>

      <!-- Level-1 tab: Divisi -->
      <ul class="nav nav-tabs mb-2" role="tablist" style="font-size:.82rem">
        <?php foreach ($negByDiv as $loc => $cats): ?>
          <?php $total = array_sum(array_map('count', $cats)); ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link<?= $first1 ? ' active' : '' ?> py-1 px-2"
                    id="<?= $divIds[$loc] ?>_tab" data-bs-toggle="tab"
                    data-bs-target="#<?= $divIds[$loc] ?>" type="button" role="tab">
              <?= htmlspecialchars($loc) ?>
              <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:.7rem"><?= $total ?></span>
            </button>
          </li>
          <?php $first1 = false; ?>
        <?php endforeach; ?>
      </ul>

      <div class="tab-content">
        <?php $first2 = true; foreach ($negByDiv as $loc => $cats): ?>
          <?php
          $divId   = $divIds[$loc];
          $hasMat  = !empty($cats['material']);
          $hasComp = !empty($cats['component']);
          ?>
          <div class="tab-pane fade<?= $first2 ? ' show active' : '' ?>" id="<?= $divId ?>" role="tabpanel">
            <?php if ($hasMat && $hasComp): ?>
              <!-- Level-2 tab: Bahan Baku / Component -->
              <ul class="nav nav-pills mb-2" role="tablist" style="font-size:.78rem">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active py-1 px-2"
                          id="<?= $divId ?>_mat_tab" data-bs-toggle="tab"
                          data-bs-target="#<?= $divId ?>_mat" type="button" role="tab">
                    Bahan Baku
                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:.68rem"><?= count($cats['material']) ?></span>
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link py-1 px-2"
                          id="<?= $divId ?>_comp_tab" data-bs-toggle="tab"
                          data-bs-target="#<?= $divId ?>_comp" type="button" role="tab">
                    Component (Base/Prepare)
                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:.68rem"><?= count($cats['component']) ?></span>
                  </button>
                </li>
              </ul>
              <div class="tab-content">
                <div class="tab-pane fade show active fd-scroll" id="<?= $divId ?>_mat" role="tabpanel" style="max-height:220px">
                  <?php foreach ($cats['material'] as $r) { $fdItemRow($r); } ?>
                </div>
                <div class="tab-pane fade fd-scroll" id="<?= $divId ?>_comp" role="tabpanel" style="max-height:220px">
                  <?php foreach ($cats['component'] as $r) { $fdItemRow($r); } ?>
                </div>
              </div>
            <?php elseif ($hasMat): ?>
              <div class="fd-scroll" style="max-height:220px">
                <?php foreach ($cats['material'] as $r) { $fdItemRow($r); } ?>
              </div>
            <?php elseif ($hasComp): ?>
              <div class="fd-scroll" style="max-height:220px">
                <?php foreach ($cats['component'] as $r) { $fdItemRow($r); } ?>
              </div>
            <?php endif; ?>
          </div>
          <?php $first2 = false; endforeach; ?>
      </div>

      <div class="mt-2" style="font-size:.82rem;color:#8b2020;">
        <i class="ri-information-line"></i>
        Stok negatif = kasir sudah input order POS sebelum batch tersedia.
        Ini <strong>by design</strong> untuk flexibilitas kasir.
        Lot fisik sudah 0; saldo ini perlu dikompensasi oleh batch produksi atau adjustment stok.
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Modal: Adjustment Cepat Stok Negatif -->
  <div class="modal fade" id="negAdjModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
      <div class="modal-content" style="border:0;border-radius:22px;overflow:hidden;box-shadow:0 24px 60px -28px rgba(120,0,0,.55)">
        <div class="modal-header" style="background:linear-gradient(135deg,#fff5f5 0%,#fff9f9 100%);border-bottom:1px solid #fcc;padding:.9rem 1.1rem">
          <div>
            <div style="font-size:.66rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#c62828">
              <i class="ri ri-error-warning-fill me-1"></i>Adjustment Cepat — Stok Negatif
            </div>
            <h5 class="modal-title mb-0" id="negAdjModalTitle" style="color:#5d160d;font-size:.95rem">-</h5>
            <div class="small" id="negAdjModalSub" style="color:#8b2020;font-size:.75rem">-</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:1rem 1.1rem">
          <div id="negAdjAlert" class="mb-3"></div>
          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Jenis Koreksi</label>
              <select class="form-select form-select-sm" id="negAdjAction">
                <option value="PLUS">Adjustment Plus</option>
                <option value="WASTE">Waste</option>
                <option value="SPOIL">Spoil</option>
                <option value="MINUS">Minus / Variance</option>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Tanggal</label>
              <input type="date" class="form-control form-control-sm" id="negAdjDate" required>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-5">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700" id="negAdjQtyLabel">Qty</label>
              <input type="number" min="0.001" step="0.01" class="form-control form-control-sm" id="negAdjQty" value="">
            </div>
            <div class="col-7">
              <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Alasan</label>
              <select class="form-select form-select-sm" id="negAdjReason"></select>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label mb-1" style="font-size:.74rem;font-weight:700">Catatan</label>
            <textarea class="form-control form-control-sm" id="negAdjNote" rows="2" placeholder="Catatan adjustment (opsional)"></textarea>
          </div>
        </div>
        <div class="modal-footer" style="background:#fff9f9;border-top:1px solid #fcc;padding:.7rem 1.1rem">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-sm btn-danger" id="negAdjSubmitBtn">
            <i class="ri ri-upload-2-line me-1"></i>Simpan &amp; Posting
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Stok Produk (Live POS) -->
  <section class="fd-card p-3">
    <?php
    $summ = $stockProductLive['summary'] ?? [];
    $out  = (int)($summ['out']       ?? 0);
    $lim  = (int)($summ['limited']   ?? 0);
    $avl  = (int)($summ['available'] ?? 0);
    $tot  = (int)($summ['total']     ?? 0);
    ?>
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Stok Produk Live POS</h2>
        <p class="fd-sec-sub">Ketersediaan produk berdasarkan stok bahan baku real-time. Klik produk untuk lihat breakdown resep &amp; bottleneck.</p>
      </div>
      <span class="fd-pill"><?= $tot ?> produk</span>
    </div>
    <div class="fd-prod-summ">
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val red"><?= $out ?></div><div class="fd-prod-summ-lbl">Habis (OUT)</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val orange"><?= $lim ?></div><div class="fd-prod-summ-lbl">Terbatas (LIMITED)</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val"><?= $tot ?></div><div class="fd-prod-summ-lbl">Total Dipantau</div></div>
      <div class="fd-prod-summ-card"><div class="fd-prod-summ-val green"><?= $avl ?></div><div class="fd-prod-summ-lbl">Aman</div></div>
    </div>

    <?php if (empty($productByDivision)): ?>
      <div class="fd-empty">Data ketersediaan produk belum tersedia.</div>
    <?php else: ?>
      <div class="d-flex align-items-center gap-2" style="margin-bottom:.75rem">
        <div class="fd-tabs mb-0 flex-grow-1" id="prodDivTabs" style="margin-bottom:0">
          <?php $first = true; foreach (array_keys($productByDivision) as $divName): ?>
            <?php
            $divRows = $productByDivision[$divName];
            $cntOut  = count(array_filter($divRows, fn($r) => strtoupper($r['availability_status']) === 'OUT'));
            $cntLim  = count(array_filter($divRows, fn($r) => strtoupper($r['availability_status']) === 'LIMITED'));
            ?>
            <button class="fd-tab <?= $first ? 'on' : '' ?>" data-pdiv="<?= htmlspecialchars($divName) ?>">
              <?= htmlspecialchars($divName) ?>
              <?php if ($cntOut > 0): ?><span style="color:#c62828">(<?= $cntOut ?> habis)</span><?php elseif ($cntLim > 0): ?><span style="color:#e65100">(<?= $cntLim ?> terbatas)</span><?php endif; ?>
            </button>
            <?php $first = false; endforeach; ?>
        </div>
        <button type="button" id="prodCatFilterBtn" title="Filter Kategori"
                style="flex-shrink:0;padding:.28rem .7rem;border-radius:999px;font-size:.74rem;font-weight:700;cursor:pointer;border:1px solid rgba(170,95,78,.2);background:#fff;color:#8b7772;transition:all .13s">
          <i class="ri ri-filter-3-line"></i> Kategori
        </button>
      </div>

      <?php foreach ($productByDivision as $divName => $_ignored): ?>
        <?php $cats = $productCatsByDiv[$divName] ?? []; if (empty($cats)) continue; ?>
        <div class="fd-cat-filter-wrap" data-pdiv-filter="<?= htmlspecialchars($divName) ?>" style="display:none;margin-bottom:.75rem">
          <div style="background:#fff8f5;border:1px solid rgba(170,95,78,.18);border-radius:14px;padding:.7rem 1rem">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span style="font-size:.73rem;font-weight:800;color:#6f2119;text-transform:uppercase;letter-spacing:.05em">
                Kategori — <?= htmlspecialchars($divName) ?>
              </span>
              <div class="d-flex align-items-center gap-3">
                <button type="button" onclick="fdCatAll('<?= htmlspecialchars($divName, ENT_QUOTES) ?>',true)"
                        style="font-size:.72rem;font-weight:700;color:#2e7d32;background:none;border:none;cursor:pointer;padding:0">Pilih Semua</button>
                <button type="button" onclick="fdCatAll('<?= htmlspecialchars($divName, ENT_QUOTES) ?>',false)"
                        style="font-size:.72rem;font-weight:700;color:#8b7772;background:none;border:none;cursor:pointer;padding:0">Hapus Semua</button>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" data-pdiv-checks="<?= htmlspecialchars($divName) ?>">
              <?php
              $hiddenThisDiv = $prodLiveHiddenCats[$divName] ?? [];
              foreach ($cats as $cat):
                  $isChecked = !in_array($cat, $hiddenThisDiv, true);
              ?>
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.74rem;cursor:pointer;background:#fff;border:1px solid rgba(170,95,78,.18);border-radius:999px;padding:.22rem .6rem;user-select:none">
                  <input type="checkbox" class="fd-cat-cb" <?= $isChecked ? 'checked' : '' ?>
                         data-div="<?= htmlspecialchars($divName, ENT_QUOTES) ?>"
                         data-cat="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"
                         onchange="fdCatFilter('<?= htmlspecialchars($divName, ENT_QUOTES) ?>')">
                  <?= htmlspecialchars($cat) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="d-flex align-items-center gap-3">
              <button type="button"
                      onclick="fdCatSave('<?= htmlspecialchars($divName, ENT_QUOTES) ?>', this)"
                      style="padding:.3rem .9rem;border-radius:999px;font-size:.74rem;font-weight:800;cursor:pointer;border:1px solid #8f2d23;background:#8f2d23;color:#fff">
                <i class="ri ri-save-line"></i> Simpan Pengaturan
              </button>
              <span class="fd-cat-save-status" style="font-size:.72rem;font-weight:700;color:#2e7d32;opacity:0;transition:opacity .4s"></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php foreach ($productByDivision as $divName => $divRows): ?>
        <div class="fd-prod-div-content fd-scroll" data-pdiv="<?= htmlspecialchars($divName) ?>"
             style="<?= array_key_first($productByDivision) !== $divName ? 'display:none' : '' ?>">
          <?php foreach ($divRows as $pr): ?>
            <?php
            $status     = strtoupper((string)($pr['availability_status'] ?? 'AVAILABLE'));
            $qty        = (float)($pr['qty'] ?? 0);
            $qtyFloor   = floor($qty);
            $bn         = (string)($pr['bottleneck_name_snapshot'] ?? '');
            $isDirty    = !empty($pr['is_dirty']);
            $pillClass  = $status === 'OUT' ? 'minus' : ($status === 'LIMITED' ? 'kritis' : 'ok');
            $statusLabel = $status === 'OUT' ? 'HABIS' : ($status === 'LIMITED' ? 'TERBATAS' : 'TERSEDIA');
            $catName    = (string)($pr['category_name'] ?? '');
            ?>
            <div class="fd-prod-row" data-category="<?= htmlspecialchars($catName, ENT_QUOTES) ?>" style="margin-bottom:.5rem">
              <div class="fd-prod-head" data-pid="<?= (int)($pr['product_id'] ?? 0) ?>" onclick="fdToggleRecipe(this)">
                <div>
                  <div class="fd-prod-name"><?= htmlspecialchars((string)($pr['product_name'] ?? '-')) ?></div>
                  <div class="fd-prod-meta">
                    <?php if ($catName !== ''): ?><span style="font-size:.73rem;color:#a06050;font-weight:700"><?= htmlspecialchars($catName) ?></span><?= $bn !== '' ? ' · ' : '' ?><?php endif; ?>
                    <?= $bn !== '' ? 'Bottleneck: <strong>' . htmlspecialchars($bn) . '</strong> · ' : '' ?>Klik untuk lihat resep<?= $isDirty ? ' · <em>Cache lama</em>' : '' ?>
                  </div>
                </div>
                <div class="fd-prod-right">
                  <div>
                    <div class="fd-prod-qty"><?= number_format($qtyFloor, 0, ',', '.') ?></div>
                    <div class="fd-prod-uom"><?= htmlspecialchars((string)($pr['uom_code'] ?? '')) ?></div>
                  </div>
                  <span class="fd-pill <?= $pillClass ?>"><?= $statusLabel ?></span>
                  <i class="ri-arrow-down-s-line" style="color:#8b7772"></i>
                </div>
              </div>
              <div class="fd-prod-body" id="recipe-<?= (int)($pr['product_id'] ?? 0) ?>">
                <div class="fd-item-meta">Memuat resep...</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Ringkasan Adjustment -->
  <section class="fd-adjust-grid">
    <div class="fd-card p-3 fd-adjust-panel fd-adjust-panel-list">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Ringkasan Adjustment</h2>
          <p class="fd-sec-sub">Ringkasan per bahan/komponen untuk hari ini, minggu ini, dan bulan ini. Breakdown menampilkan line detail, bukan hanya nomor nota.</p>
        </div>
        <span class="fd-pill">3 periode</span>
      </div>

      <div class="fd-tabs" id="adjPeriodTabs">
        <?php foreach (['daily' => 'Hari Ini', 'weekly' => 'Minggu Ini', 'monthly' => 'Bulan Ini'] as $periodKey => $periodLabel): ?>
          <button class="fd-tab<?= $periodKey === 'daily' ? ' on' : '' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>"><?= htmlspecialchars($periodLabel) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
        <?php $periodBlock = (array)($adjustmentSummary[$periodKey] ?? []); ?>
        <div class="fd-adj-period<?= $periodKey === 'daily' ? '' : ' d-none' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>">
          <div class="fd-tabs" id="adjScopeTabs-<?= htmlspecialchars($periodKey) ?>">
            <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
              <button class="fd-sub-tab<?= $adjKey === 'warehouse' ? ' on' : '' ?>" data-adjscope="<?= htmlspecialchars($adjKey) ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>">
                <?= htmlspecialchars($adjLabel) ?> (<?= count((array)($periodBlock[$adjKey]['rows'] ?? [])) ?>)
              </button>
            <?php endforeach; ?>
          </div>

          <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
            <?php $adjRows = (array)($periodBlock[$adjKey]['rows'] ?? []); ?>
            <div class="fd-adj-content<?= $adjKey === 'warehouse' ? '' : ' d-none' ?>" data-adjperiod="<?= htmlspecialchars($periodKey) ?>" data-adjscope="<?= htmlspecialchars($adjKey) ?>">
              <?php if (empty($adjRows)): ?>
                <div class="fd-empty">Belum ada adjustment <?= strtolower($adjLabel) ?> pada periode <?= htmlspecialchars(strtolower((string)($periodBlock['label'] ?? '-'))) ?>.</div>
              <?php else: ?>
                <div class="fd-adjust-scroll">
                  <?php foreach ($adjRows as $adjRow): ?>
                    <?php
                    $detailRows = (array)($adjRow['details'] ?? []);
                    $qtyOutTotal = $adjKey === 'component'
                      ? (float)($adjRow['qty_waste'] ?? 0) + (float)($adjRow['qty_spoil'] ?? 0) + (float)($adjRow['qty_minus'] ?? 0)
                      : (float)($adjRow['qty_waste'] ?? 0) + (float)($adjRow['qty_spoil'] ?? 0) + (float)($adjRow['qty_process_loss'] ?? 0) + (float)($adjRow['qty_variance'] ?? 0);
                    ?>
                    <div class="fd-adjust-card">
                      <div class="fd-adjust-head">
                        <div>
                          <div class="fd-adjust-title"><?= htmlspecialchars((string)($adjRow['object_name'] ?? '-')) ?></div>
                          <div class="fd-adjust-code"><?= htmlspecialchars((string)($adjRow['object_code'] ?? '-')) ?> · <?= htmlspecialchars((string)($adjRow['location_name'] ?? '-')) ?> · <?= (int)($adjRow['doc_count'] ?? 0) ?> nota / <?= (int)($adjRow['line_count'] ?? 0) ?> line</div>
                        </div>
                        <span class="fd-pill"><?= $cur((float)($adjRow['net_value_total'] ?? 0)) ?></span>
                      </div>
                      <div class="fd-adjust-metrics">
                        <div class="fd-adjust-metric">
                          <span class="lbl">Qty Keluar</span>
                          <span class="val minus"><?= number_format($qtyOutTotal, 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Adj Plus</span>
                          <span class="val plus"><?= number_format((float)($adjRow['qty_plus'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Net Value</span>
                          <span class="val <?= ((float)($adjRow['net_value_total'] ?? 0)) >= 0 ? 'plus' : 'minus' ?>"><?= $cur((float)($adjRow['net_value_total'] ?? 0)) ?></span>
                        </div>
                      </div>
                      <div class="fd-adjust-metrics">
                        <div class="fd-adjust-metric">
                          <span class="lbl">Waste</span>
                          <span class="val minus"><?= number_format((float)($adjRow['qty_waste'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl">Spoil</span>
                          <span class="val minus"><?= number_format((float)($adjRow['qty_spoil'] ?? 0), 2, ',', '.') ?></span>
                        </div>
                        <div class="fd-adjust-metric">
                          <span class="lbl"><?= $adjKey === 'component' ? 'Minus' : 'Variance / Loss' ?></span>
                          <span class="val minus"><?= number_format($adjKey === 'component' ? (float)($adjRow['qty_minus'] ?? 0) : ((float)($adjRow['qty_process_loss'] ?? 0) + (float)($adjRow['qty_variance'] ?? 0)), 2, ',', '.') ?></span>
                        </div>
                      </div>

                      <details class="fd-adjust-detail">
                        <summary>Lihat breakdown detail (<?= count($detailRows) ?> line)</summary>
                        <?php if (empty($detailRows)): ?>
                          <div class="fd-empty" style="padding:.7rem 0 0">Tidak ada detail line.</div>
                        <?php else: ?>
                          <div class="fd-adjust-detail-list">
                            <?php foreach ($detailRows as $detailRow): ?>
                              <?php
                              $detailQtyOut = $adjKey === 'component'
                                ? (float)($detailRow['qty_waste'] ?? 0) + (float)($detailRow['qty_spoil'] ?? 0) + (float)($detailRow['qty_minus'] ?? 0)
                                : (float)($detailRow['qty_waste'] ?? 0) + (float)($detailRow['qty_spoil'] ?? 0) + (float)($detailRow['qty_process_loss'] ?? 0) + (float)($detailRow['qty_variance'] ?? 0);
                              $detailMeta = trim(implode(' · ', array_filter([
                                (string)($detailRow['adjustment_no'] ?? ''),
                                (string)($detailRow['adjustment_date'] ?? ''),
                                (string)($detailRow['profile_label'] ?? ''),
                              ])));
                              $detailNote = trim(implode(' | ', array_filter([
                                (string)($detailRow['note'] ?? ''),
                                (string)($detailRow['header_notes'] ?? ''),
                              ])));
                              ?>
                              <div class="fd-adjust-detail-item">
                                <div class="fd-adjust-detail-top">
                                  <div>
                                    <div class="fd-adjust-title" style="font-size:.9rem"><?= htmlspecialchars($detailMeta !== '' ? $detailMeta : '-') ?></div>
                                    <div class="fd-adjust-detail-meta"><?= htmlspecialchars($detailNote !== '' ? $detailNote : 'Tanpa catatan') ?></div>
                                  </div>
                                  <div class="text-end">
                                    <div class="fd-adjust-title" style="font-size:.9rem"><?= number_format($detailQtyOut, 2, ',', '.') ?><?= !empty($detailRow['uom_code']) ? ' ' . htmlspecialchars((string)$detailRow['uom_code']) : '' ?></div>
                                    <div class="fd-adjust-detail-meta">+<?= number_format((float)($detailRow['qty_plus'] ?? 0), 2, ',', '.') ?> · <?= $cur(((float)($detailRow['qty_plus'] ?? 0) - $detailQtyOut) * (float)($detailRow['unit_cost'] ?? 0)) ?></div>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </details>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="fd-card p-3 fd-adjust-panel fd-adjust-panel-summary">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Ringkasan Per Tab</h2>
          <p class="fd-sec-sub">Angka utama menampilkan total net value. Nilai keluar menunjukkan total sisi pengurang, dan plus menunjukkan total adjustment penambah.</p>
        </div>
      </div>
      <div class="fd-adjust-scroll">
        <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
          <?php $periodBlock = (array)($adjustmentSummary[$periodKey] ?? []); ?>
          <div style="margin-bottom:1rem">
            <div class="fd-sec-title" style="font-size:.95rem;margin-bottom:.6rem"><?= htmlspecialchars((string)($periodBlock['label'] ?? '-')) ?></div>
            <div class="fd-list">
              <?php foreach (['warehouse' => 'Gudang', 'division' => 'Bahan Baku', 'component' => 'Component'] as $adjKey => $adjLabel): ?>
                <?php $adjTotals = (array)($periodBlock[$adjKey]['totals'] ?? []); ?>
                <div class="fd-item">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars($adjLabel) ?></div>
                    <div class="fd-item-meta"><?= (int)($adjTotals['group_count'] ?? 0) ?> bahan · <?= (int)($adjTotals['doc_count'] ?? 0) ?> nota · <?= (int)($adjTotals['line_count'] ?? 0) ?> line</div>
                  </div>
                  <div class="text-end">
                    <div class="fd-item-title"><?= $cur((float)($adjTotals['net_value_total'] ?? 0)) ?></div>
                    <div class="fd-item-meta">Keluar <?= $cur((float)($adjTotals['value_out_total'] ?? 0)) ?> · Plus <?= $cur((float)($adjTotals['value_plus_total'] ?? 0)) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Produk Terbanyak -->
  <section class="fd-card p-3">
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Penjualan Produk Terbanyak</h2>
        <p class="fd-sec-sub">Peringkat berdasarkan qty terjual. Dipisah per hari ini, minggu ini, dan bulan ini.</p>
      </div>
    </div>
    <div class="fd-tabs" id="topProdPeriodTabs">
      <?php foreach (['daily' => 'Hari Ini', 'weekly' => 'Minggu Ini', 'monthly' => 'Bulan Ini'] as $periodKey => $periodLabel): ?>
        <button class="fd-tab<?= $periodKey === 'daily' ? ' on' : '' ?>" data-topperiod="<?= htmlspecialchars($periodKey) ?>"><?= htmlspecialchars($periodLabel) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach (['daily', 'weekly', 'monthly'] as $periodKey): ?>
      <?php
      $periodBlock = (array)($topSellingProducts[$periodKey] ?? []);
      $periodGroups = (array)($periodBlock['groups'] ?? []);
      $periodCount = 0;
      foreach ($periodGroups as $groupBlock) { $periodCount += count((array)($groupBlock['rows'] ?? [])); }
      ?>
      <div class="fd-topprod-period<?= $periodKey === 'daily' ? '' : ' d-none' ?>" data-topperiod="<?= htmlspecialchars($periodKey) ?>">
        <div class="fd-sec-head" style="margin-bottom:.75rem">
          <div>
            <h2 class="fd-sec-title"><?= htmlspecialchars((string)($periodBlock['label'] ?? '-')) ?></h2>
            <p class="fd-sec-sub">Top produk berdasarkan qty, dipisah Food dan Beverage.</p>
          </div>
          <span class="fd-pill"><?= $periodCount ?> baris</span>
        </div>
        <div class="fd-topprod-period-grid">
          <?php foreach (['FOOD', 'BEVERAGE'] as $groupKey): ?>
            <?php
            $groupBlock = (array)($periodGroups[$groupKey] ?? ['label' => $groupKey, 'rows' => []]);
            $periodRows = (array)($groupBlock['rows'] ?? []);
            ?>
            <div class="fd-card fd-topprod-card">
              <div class="fd-topprod-subtitle"><?= htmlspecialchars((string)($groupBlock['label'] ?? $groupKey)) ?></div>
              <div class="fd-topprod-scroll">
                <div class="fd-list">
                <?php if (empty($periodRows)): ?>
                  <div class="fd-empty" style="padding:.5rem 0">Belum ada penjualan <?= htmlspecialchars(strtolower((string)($groupBlock['label'] ?? $groupKey))) ?>.</div>
                <?php else: ?>
                  <?php foreach ($periodRows as $idx => $periodRow): ?>
                    <div class="fd-topprod-row">
                      <div class="d-flex align-items-start gap-2">
                        <span class="fd-topprod-rank"><?= (int)$idx + 1 ?></span>
                        <div>
                          <div class="fd-topprod-name"><?= htmlspecialchars((string)($periodRow['product_name'] ?? '-')) ?></div>
                          <div class="fd-topprod-meta"><?= htmlspecialchars((string)($periodRow['product_code'] ?? '-')) ?> · <?= (int)($periodRow['order_count'] ?? 0) ?> order</div>
                        </div>
                      </div>
                      <div>
                        <div class="fd-topprod-qty"><?= number_format((float)($periodRow['qty_total'] ?? 0), 0, ',', '.') ?></div>
                        <div class="fd-topprod-net"><?= $cur((float)($periodRow['net_total'] ?? 0)) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- Scope -->
  <section class="fd-2scope">
    <?php if (empty($posScopeRows)): ?>
      <div class="fd-card fd-scope-card">
        <div class="fd-empty">Belum ada data Reguler atau Event untuk periode ini.</div>
      </div>
    <?php else: ?>
      <?php foreach ($posScopeRows as $sr): ?>
        <div class="fd-card fd-scope-card">
          <div class="fd-scope-head">
            <h2 class="fd-scope-title"><?= htmlspecialchars((string)($sr['scope_label'] ?? '-')) ?></h2>
            <span class="fd-pill"><?= htmlspecialchars((string)($sr['scope_code'] ?? '-')) ?></span>
          </div>
          <div class="fd-item-meta mt-1">Penjualan bersih POS berdasarkan scope order.</div>
          <div class="fd-scope-val"><?= $cur($sr['net_sales_total'] ?? 0) ?></div>
          <div class="fd-scope-meta">
            <div><div class="fd-scope-meta-lbl">Transaksi</div><div class="fd-scope-meta-val"><?= number_format((int)($sr['transaction_count'] ?? 0), 0, ',', '.') ?></div></div>
            <div><div class="fd-scope-meta-lbl">Paid Total</div><div class="fd-scope-meta-val"><?= $cur($sr['gross_sales_total'] ?? 0) ?></div></div>
            <div><div class="fd-scope-meta-lbl">Refund</div><div class="fd-scope-meta-val"><?= $cur($sr['refund_total'] ?? 0) ?></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <!-- Charts -->
  <section class="fd-chart-grid">
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Tren Omzet, Nilai PO & Store Request</h2>
          <p class="fd-sec-sub">Omzet bersih POS, total nilai PO, dan jumlah SR per hari (<?= htmlspecialchars($chartFilters['period_label']) ?>).</p>
        </div>
        <span class="fd-pill"><?= htmlspecialchars($chartFilters['period_label']) ?></span>
      </div>
      <div id="fdTrendChart" style="min-height:320px;"></div>
    </div>
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Komposisi Status POS</h2>
          <p class="fd-sec-sub">Distribusi status order dalam periode KPI terpilih.</p>
        </div>
      </div>
      <div id="fdStatusChart" style="min-height:320px;"></div>
    </div>
  </section>

  <!-- Stok Minus & Kritis -->
  <section class="fd-2col">
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Stok Minus & Kritis</h2>
          <p class="fd-sec-sub">Item yang minus atau menyentuh threshold per lokasi stok.</p>
        </div>
      </div>

      <div class="fd-tabs" id="stockMainTabs">
        <button class="fd-tab on" data-stab="wh">Gudang (<?= count($stockBreakdown['warehouse'] ?? []) ?>)</button>
        <button class="fd-tab" data-stab="dv">Divisi (<?= count($stockBreakdown['division'] ?? []) ?>)</button>
        <button class="fd-tab" data-stab="cp">Base/Prepare (<?= count($stockBreakdown['component'] ?? []) ?>)</button>
      </div>

      <!-- Gudang -->
      <div id="stWh" class="fd-stab-content">
        <?php if (empty($stockBreakdown['warehouse'])): ?>
          <div class="fd-empty">Tidak ada stok gudang yang minus atau kritis.</div>
        <?php else: ?>
          <div class="fd-scroll">
            <?php foreach ($stockBreakdown['warehouse'] as $r): ?>
              <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
              <div class="fd-item <?= $sev ?>">
                <div>
                  <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                  <div class="fd-item-meta">Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                </div>
                <div class="text-end">
                  <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                  <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Divisi dengan sub-tab per lokasi -->
      <div id="stDv" class="fd-stab-content d-none">
        <?php if (empty($divisionSubTabs)): ?>
          <div class="fd-empty">Tidak ada stok divisi yang minus atau kritis.</div>
        <?php else: ?>
          <div class="fd-tabs" id="dvSubTabs" style="margin-bottom:.65rem">
            <?php $dvFirst = true; foreach (array_keys($divisionSubTabs) as $loc): ?>
              <button class="fd-sub-tab <?= $dvFirst ? 'on' : '' ?>" data-dvloc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?> (<?= count($divisionSubTabs[$loc]) ?>)</button>
              <?php $dvFirst = false; endforeach; ?>
          </div>
          <?php foreach ($divisionSubTabs as $loc => $rows): ?>
            <div class="fd-dv-loc fd-scroll" data-dvloc="<?= htmlspecialchars($loc) ?>" <?= array_key_first($divisionSubTabs) !== $loc ? 'style="display:none"' : '' ?>>
              <?php foreach ($rows as $r): ?>
                <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
                <div class="fd-item <?= $sev ?>" style="margin-bottom:.5rem">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                    <div class="fd-item-meta">Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                  </div>
                  <div class="text-end">
                    <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                    <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Base/Prepare dengan sub-tab per lokasi -->
      <div id="stCp" class="fd-stab-content d-none">
        <?php if (empty($componentSubTabs)): ?>
          <div class="fd-empty">Tidak ada stok base/prepare yang minus atau kritis.</div>
        <?php else: ?>
          <div class="fd-tabs" id="cpSubTabs" style="margin-bottom:.65rem">
            <?php $cpFirst = true; foreach (array_keys($componentSubTabs) as $loc): ?>
              <button class="fd-sub-tab <?= $cpFirst ? 'on' : '' ?>" data-cploc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?> (<?= count($componentSubTabs[$loc]) ?>)</button>
              <?php $cpFirst = false; endforeach; ?>
          </div>
          <?php foreach ($componentSubTabs as $loc => $rows): ?>
            <div class="fd-cp-loc fd-scroll" data-cploc="<?= htmlspecialchars($loc) ?>" <?= array_key_first($componentSubTabs) !== $loc ? 'style="display:none"' : '' ?>>
              <?php foreach ($rows as $r): ?>
                <?php $sev = (string)($r['severity'] ?? 'kritis'); ?>
                <div class="fd-item <?= $sev ?>" style="margin-bottom:.5rem">
                  <div>
                    <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
                    <div class="fd-item-meta"><?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($r['threshold'] ?? 0), 2, ',', '.') ?></div>
                  </div>
                  <div class="text-end">
                    <span class="fd-pill <?= $sev ?>"><?= number_format((float)($r['qty'] ?? 0), 2, ',', '.') ?></span>
                    <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Aktivitas Terbaru -->
    <div class="fd-card p-3">
      <div class="fd-sec-head">
        <div>
          <h2 class="fd-sec-title">Aktivitas Terbaru</h2>
          <p class="fd-sec-sub">POS, purchase, dan inventory dalam periode KPI.</p>
        </div>
      </div>
      <div class="fd-list">
        <?php if (empty($recentActivity)): ?>
          <div class="fd-empty">Belum ada aktivitas pada periode ini.</div>
        <?php else: ?>
          <?php foreach ($recentActivity as $r): ?>
            <div class="fd-item">
              <div>
                <div class="fd-item-title"><?= htmlspecialchars((string)($r['source_label'] ?? '-')) ?> · <?= htmlspecialchars((string)($r['ref_no'] ?? '-')) ?></div>
                <div class="fd-item-meta">Status: <?= htmlspecialchars((string)($r['status'] ?? '-')) ?> · <?= date('d-m H:i', strtotime((string)($r['event_at'] ?? 'now'))) ?></div>
              </div>
              <div class="fd-item-title text-end"><?= $cur($r['amount'] ?? 0) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Stok Kritis Detail — JS filter, no reload -->
  <section class="fd-card p-3">
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Stok Kritis Detail</h2>
        <p class="fd-sec-sub">Item yang menyentuh atau turun di bawah threshold. Filter per lokasi tanpa reload.</p>
      </div>
    </div>

    <?php if (!empty($criticalLocations)): ?>
    <div class="fd-tabs" id="critTabs">
      <button class="fd-tab on" data-critloc="ALL">Semua</button>
      <?php foreach ($criticalLocations as $loc): ?>
        <button class="fd-tab" data-critloc="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fd-list" id="critList">
      <?php if (empty($criticalStockRows)): ?>
        <div class="fd-empty">Belum ada item kritis yang terdeteksi.</div>
      <?php else: ?>
        <?php foreach ($criticalStockRows as $r): ?>
          <div class="fd-item" data-critloc="<?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?>">
            <div>
              <div class="fd-item-title"><?= htmlspecialchars((string)($r['item_name'] ?? '-')) ?></div>
              <div class="fd-item-meta"><?= htmlspecialchars((string)($r['stock_scope'] ?? '-')) ?> · <?= htmlspecialchars((string)($r['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($r['threshold_qty'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="text-end">
              <div class="fd-item-title"><?= number_format((float)($r['qty_balance'] ?? 0), 2, ',', '.') ?></div>
              <div class="fd-item-meta"><?= $cur($r['total_value'] ?? 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
window.addEventListener('load', function () {
  // Period filter
  const periodSel = document.getElementById('dashPeriod');
  if (periodSel) {
    periodSel.addEventListener('change', function () {
      document.querySelectorAll('.fd-custom-date').forEach(function (el) {
        el.classList.toggle('d-none', this.value !== 'custom');
      }.bind(this));
    });
  }

  // ─── Charts ───────────────────────────────────────────────
  const rupiah = function (v) { return 'Rp ' + Number(v||0).toLocaleString('id-ID', {maximumFractionDigits:0}); };

  const trendEl = document.getElementById('fdTrendChart');
  if (trendEl && window.ApexCharts) {
    const single = <?= json_encode(count($trend['labels'] ?? []) <= 1) ?>;
    new ApexCharts(trendEl, {
      chart: { type: 'line', height: 320, toolbar: { show: false } },
      series: [
        { name: 'Omzet Bersih', type: single ? 'bar' : 'area', data: <?= json_encode(array_values($trend['sales'] ?? [])) ?> },
        { name: 'Nilai PO',     type: single ? 'bar' : 'line', data: <?= json_encode(array_values($trend['po']    ?? [])) ?> },
        { name: 'Jml SR',       type: 'line',                  data: <?= json_encode(array_values($trend['sr']    ?? [])) ?> },
      ],
      colors: ['#8f2d23','#1e88e5','#ef6c00'],
      stroke: { curve: 'smooth', width: [3,2,2] },
      fill:   { type: ['solid','solid','solid'], opacity: [single ? .9 : .18, single ? .9 : 1, 1] },
      dataLabels: { enabled: false },
      xaxis: { categories: <?= json_encode(array_values($trend['labels'] ?? [])) ?> },
      yaxis: [
        { seriesName: 'Omzet Bersih', labels: { formatter: function(v) { return rupiah(v); } } },
        { seriesName: 'Nilai PO', show: false },
        { seriesName: 'Jml SR', opposite: true, labels: { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' SR'; } } },
      ],
      tooltip: { shared: true, y: [
        { formatter: function(v) { return rupiah(v); } },
        { formatter: function(v) { return rupiah(v); } },
        { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' SR'; } },
      ]},
      legend: { position: 'top' }
    }).render();
  }

  const statusEl = document.getElementById('fdStatusChart');
  if (statusEl && window.ApexCharts) {
    const totals = <?= json_encode(array_values($sTotals)) ?>;
    new ApexCharts(statusEl, {
      chart: { type: 'donut', height: 320 },
      series: totals.length ? totals : [1],
      labels: totals.length ? <?= json_encode(array_values($sLabels)) ?> : ['Belum Ada Data'],
      colors: totals.length ? <?= json_encode(array_values($sColors)) ?> : ['#c7b2ad'],
      legend: { position: 'bottom' },
      dataLabels: { enabled: true },
      tooltip: { y: { formatter: function(v) { return Number(v||0).toLocaleString('id-ID')+' order'; } } }
    }).render();
  }

  // ─── Stock main tabs ──────────────────────────────────────
  function bindTabs(tabsId, contentsSelector, dataAttr) {
    const container = document.getElementById(tabsId);
    if (!container) return;
    container.querySelectorAll('.fd-tab,.fd-sub-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        container.querySelectorAll('.fd-tab,.fd-sub-tab').forEach(function(b) { b.classList.remove('on'); });
        btn.classList.add('on');
        document.querySelectorAll(contentsSelector).forEach(function (el) {
          el.style.display = el.dataset[dataAttr] === btn.dataset[dataAttr] ? '' : 'none';
        });
      });
    });
  }

  // Main stock tabs
  const stockTabBtns = document.querySelectorAll('#stockMainTabs .fd-tab');
  const stockContents = { wh: document.getElementById('stWh'), dv: document.getElementById('stDv'), cp: document.getElementById('stCp') };
  stockTabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      stockTabBtns.forEach(function(b) { b.classList.remove('on'); });
      btn.classList.add('on');
      Object.values(stockContents).forEach(function(el) { if(el) el.classList.add('d-none'); });
      const target = stockContents[btn.dataset.stab];
      if (target) target.classList.remove('d-none');
    });
  });

  // Adjustment period tabs
  const adjPeriodBtns = document.querySelectorAll('#adjPeriodTabs .fd-tab');
  adjPeriodBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const periodKey = btn.dataset.adjperiod;
      adjPeriodBtns.forEach(function (node) { node.classList.remove('on'); });
      btn.classList.add('on');
      document.querySelectorAll('.fd-adj-period').forEach(function (panel) {
        panel.classList.toggle('d-none', panel.dataset.adjperiod !== periodKey);
      });
    });
  });

  // Adjustment scope tabs per period
  document.querySelectorAll('[id^="adjScopeTabs-"]').forEach(function (container) {
    container.querySelectorAll('.fd-sub-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const periodKey = btn.dataset.adjperiod;
        const scopeKey = btn.dataset.adjscope;
        container.querySelectorAll('.fd-sub-tab').forEach(function (node) { node.classList.remove('on'); });
        btn.classList.add('on');
        document.querySelectorAll('.fd-adj-content[data-adjperiod="' + periodKey + '"]').forEach(function (panel) {
          panel.classList.toggle('d-none', panel.dataset.adjscope !== scopeKey);
        });
      });
    });
  });

  // Top product period tabs
  const topProdBtns = document.querySelectorAll('#topProdPeriodTabs .fd-tab');
  topProdBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const periodKey = btn.dataset.topperiod;
      topProdBtns.forEach(function (node) { node.classList.remove('on'); });
      btn.classList.add('on');
      document.querySelectorAll('.fd-topprod-period').forEach(function (panel) {
        panel.classList.toggle('d-none', panel.dataset.topperiod !== periodKey);
      });
    });
  });

  // Division sub-tabs
  bindTabs('dvSubTabs', '.fd-dv-loc', 'dvloc');
  bindTabs('cpSubTabs', '.fd-cp-loc', 'cploc');

  // ─── Product division tabs + category filter (DB-shared) ────
  var SAVE_CAT_URL = <?= json_encode(site_url('dashboard/save_prod_live_cats')) ?>;

  window.fdCatFilter = function(divName) {
    var checks  = document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb');
    var enabled = new Set();
    checks.forEach(function(cb) { if (cb.checked) enabled.add(cb.dataset.cat); });
    var content = document.querySelector('.fd-prod-div-content[data-pdiv="' + divName + '"]');
    if (content) {
      content.querySelectorAll('.fd-prod-row').forEach(function(row) {
        row.style.display = (enabled.size === 0 || enabled.has(row.dataset.category)) ? '' : 'none';
      });
    }
  };

  window.fdCatSave = function(divName, btn) {
    var checks  = document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb');
    var hidden  = [];
    checks.forEach(function(cb) { if (!cb.checked) hidden.push(cb.dataset.cat); });

    var wrap   = btn.closest('.fd-cat-filter-wrap');
    var status = wrap ? wrap.querySelector('.fd-cat-save-status') : null;

    btn.disabled = true;
    btn.textContent = 'Menyimpan…';
    if (status) { status.textContent = ''; status.style.opacity = '0'; }

    var fd = new FormData();
    fd.append('division', divName);
    hidden.forEach(function(c) { fd.append('hidden_cats[]', c); });

    fetch(SAVE_CAT_URL, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-save-line"></i> Simpan Pengaturan';
        if (status) {
          status.textContent = data.ok ? '✓ Tersimpan — berlaku untuk semua pengguna' : '✗ Gagal menyimpan';
          status.style.color  = data.ok ? '#2e7d32' : '#c62828';
          status.style.opacity = '1';
          setTimeout(function() { status.style.opacity = '0'; }, 3000);
        }
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri ri-save-line"></i> Simpan Pengaturan';
        if (status) { status.textContent = '✗ Gagal'; status.style.color = '#c62828'; status.style.opacity = '1'; setTimeout(function() { status.style.opacity = '0'; }, 2500); }
      });
  };

  window.fdCatAll = function(divName, checked) {
    document.querySelectorAll('[data-pdiv-checks="' + divName + '"] .fd-cat-cb').forEach(function(cb) { cb.checked = checked; });
    window.fdCatFilter(divName);
  };

  // Apply filter on page load based on PHP-rendered checkbox state (from DB)
  <?php foreach (array_keys($productByDivision) as $divName): ?>
  fdCatFilter(<?= json_encode($divName) ?>);
  <?php endforeach; ?>

  document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function(b) { b.classList.remove('on'); });
      btn.classList.add('on');
      var pdiv = btn.dataset.pdiv;
      document.querySelectorAll('.fd-prod-div-content').forEach(function(el) {
        el.style.display = el.dataset.pdiv === pdiv ? '' : 'none';
      });
      // Move category filter panel to active division, keep visible state
      var filterBtn = document.getElementById('prodCatFilterBtn');
      var wasActive = filterBtn && filterBtn.classList.contains('active');
      document.querySelectorAll('.fd-cat-filter-wrap').forEach(function(p) { p.style.display = 'none'; });
      if (wasActive) {
        var panel = document.querySelector('[data-pdiv-filter="' + pdiv + '"]');
        if (panel) panel.style.display = '';
      }
    });
  });

  (function() {
    var filterBtn = document.getElementById('prodCatFilterBtn');
    if (!filterBtn) return;
    filterBtn.addEventListener('click', function() {
      var activePdiv = (document.querySelector('#prodDivTabs .fd-tab.on') || {}).dataset?.pdiv;
      if (!activePdiv) return;
      document.querySelectorAll('.fd-cat-filter-wrap').forEach(function(p) { p.style.display = 'none'; });
      if (filterBtn.classList.contains('active')) {
        filterBtn.classList.remove('active');
      } else {
        var panel = document.querySelector('[data-pdiv-filter="' + activePdiv + '"]');
        if (panel) { panel.style.display = ''; filterBtn.classList.add('active'); }
      }
    });
  })();

  // ─── Product recipe expand ────────────────────────────────
  const recipeCache = {};
  window.fdToggleRecipe = function (head) {
    const pid = head.dataset.pid;
    const body = document.getElementById('recipe-' + pid);
    if (!body) return;
    const isOpen = body.classList.contains('open');
    if (isOpen) { body.classList.remove('open'); return; }

    body.classList.add('open');
    if (recipeCache[pid]) { body.innerHTML = recipeCache[pid]; return; }

    body.innerHTML = '<div style="color:#8b7772;padding:.4rem 0">Memuat resep...</div>';
    fetch(BASE_URL + 'dashboard/product_recipe_stock?product_id=' + pid)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok || !data.data.recipe.length) {
          body.innerHTML = '<div style="color:#8b7772">Tidak ada data resep.</div>';
          recipeCache[pid] = body.innerHTML;
          return;
        }
        let html = '<table style="width:100%;font-size:.83rem;border-collapse:collapse">';
        html += '<tr style="color:#8b7772;font-weight:700"><th style="padding:.3rem 0;text-align:left">Bahan</th><th style="padding:.3rem;text-align:center">Jenis Sumber</th><th style="padding:.3rem;text-align:center">Peran</th><th style="padding:.3rem;text-align:right">Per Saji</th><th style="padding:.3rem;text-align:right">Stok Live</th><th style="padding:.3rem;text-align:right">Sisa Produk</th></tr>';
        data.data.recipe.forEach(function(r) {
          const stockCls = r.stock_qty <= 0 ? 'bad' : 'ok';
          const stockFmt = Number(r.stock_qty).toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2});
          const servingFmt = (r.available_servings === null || typeof r.available_servings === 'undefined')
            ? '-'
            : Number(r.available_servings).toLocaleString('id-ID', {maximumFractionDigits:0});
          const srcType  = r.source_type || '-';
          html += '<tr style="border-top:1px solid rgba(170,95,78,.1)">';
          html += '<td style="padding:.32rem 0"><span class="fd-item-title">' + (r.ingredient_name||'-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:center"><span class="fd-pill" style="font-size:.7rem;' + (srcType==='bahan baku'?'background:#e8f5e9;color:#1b5e20':srcType==='base'?'background:#e3f2fd;color:#0d47a1':srcType==='prepare'?'background:#fff3e0;color:#e65100':'') + '">' + srcType + '</span></td>';
          html += '<td style="padding:.32rem;text-align:center"><span class="fd-pill" style="font-size:.7rem">' + (r.ingredient_role||'-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:right">' + Number(r.qty_per_serve).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + (r.uom_code||'') + '</td>';
          html += '<td style="padding:.32rem;text-align:right"><span class="fd-recipe-stock ' + stockCls + '">' + stockFmt + '</span></td>';
          html += '<td style="padding:.32rem;text-align:right"><span class="fd-recipe-stock ' + stockCls + '">' + servingFmt + '</span></td>';
          html += '</tr>';
        });
        html += '</table>';
        body.innerHTML = html;
        recipeCache[pid] = html;
      })
      .catch(function() {
        body.innerHTML = '<div style="color:#c62828">Gagal memuat resep.</div>';
      });
  };

  // ─── Adjustment Cepat Stok Negatif ─────────────────────────
  (function () {
    const MAT_STORE_URL  = <?= json_encode(site_url('inventory/stock/adjustment/store')) ?>;
    const COMP_SAVE_URL  = <?= json_encode(site_url('production/component-adjustments/save')) ?>;
    const COMP_POST_URL  = <?= json_encode(site_url('production/component-adjustments/post')) ?>;

    const REASONS = {
      ADJUSTMENT_PLUS:  { opening_correction: 'Opening Correction', stock_found: 'Stock Found', manual_reclass: 'Manual Reclass', other: 'Other' },
      WASTE:            { cancel_order: 'Cancel Order', kitchen_error: 'Kitchen Error', overproduction: 'Overproduction', spillage: 'Spillage / Tumpah', expired_opened: 'Expired Opened', other: 'Other' },
      SPOILAGE:         { expired: 'Expired', temperature_abuse: 'Temperature Abuse', contamination: 'Contamination', improper_storage: 'Improper Storage', overstock: 'Overstock', other: 'Other' },
      ADJUSTMENT_MINUS: { counting_error: 'Counting Error', system_mismatch: 'System Mismatch', unrecorded_usage: 'Unrecorded Usage', process_loss: 'Process Loss', theft_suspected: 'Theft Suspected', other: 'Other' },
      VARIANCE:         { over_usage: 'Over Usage', under_usage: 'Under Usage', counting_error: 'Counting Error', system_mismatch: 'System Mismatch', unrecorded_usage: 'Unrecorded Usage', other: 'Other' },
    };

    function reasonCat(action, stockType) {
      if (action === 'PLUS')  return 'ADJUSTMENT_PLUS';
      if (action === 'WASTE') return 'WASTE';
      if (action === 'SPOIL') return 'SPOILAGE';
      if (action === 'MINUS') return stockType === 'component' ? 'ADJUSTMENT_MINUS' : 'VARIANCE';
      return 'ADJUSTMENT_PLUS';
    }

    const modalEl    = document.getElementById('negAdjModal');
    const titleEl    = document.getElementById('negAdjModalTitle');
    const subEl      = document.getElementById('negAdjModalSub');
    const alertEl    = document.getElementById('negAdjAlert');
    const actionSel  = document.getElementById('negAdjAction');
    const dateInp    = document.getElementById('negAdjDate');
    const qtyInp     = document.getElementById('negAdjQty');
    const qtyLbl     = document.getElementById('negAdjQtyLabel');
    const reasonSel  = document.getElementById('negAdjReason');
    const noteArea   = document.getElementById('negAdjNote');
    const submitBtn  = document.getElementById('negAdjSubmitBtn');
    if (!modalEl || !submitBtn) return;

    let _ctx = {};

    function esc(s) {
      const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML;
    }
    function showAlert(type, msg) {
      alertEl.innerHTML = msg ? '<div class="alert alert-' + type + ' py-2 small mb-0">' + msg + '</div>' : '';
    }
    function fillReasons(cat) {
      const opts = REASONS[cat] || {};
      reasonSel.innerHTML = Object.keys(opts).map(k => '<option value="' + k + '">' + esc(opts[k]) + '</option>').join('');
    }
    function syncAction() {
      const action = actionSel.value;
      const cat = reasonCat(action, _ctx.stockType || '');
      fillReasons(cat);
      qtyLbl.textContent = 'Qty ' + (_ctx.uomCode || '') + (action === 'PLUS' ? ' (tambah)' : ' (kurang)');
    }

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-neg-adj="1"]');
      if (!btn) return;
      _ctx = {
        stockType:       btn.dataset.stockType      || '',
        itemName:        btn.dataset.itemName        || '-',
        uomCode:         btn.dataset.uomCode         || '',
        qtyBalance:      parseFloat(btn.dataset.qtyBalance || 0),
        // material/warehouse
        itemId:          parseInt(btn.dataset.itemId || 0, 10),
        contentUomId:    parseInt(btn.dataset.contentUomId || 0, 10),
        divisionId:      parseInt(btn.dataset.divisionId || 0, 10),
        destinationType: btn.dataset.destinationType || '',
        // component
        componentId:     parseInt(btn.dataset.componentId || 0, 10),
        uomId:           parseInt(btn.dataset.uomId || 0, 10),
        locationType:    btn.dataset.locationType || '',
      };

      titleEl.textContent = _ctx.itemName;
      const locLabel = _ctx.stockType === 'component'
        ? (_ctx.locationType || '-')
        : (_ctx.destinationType || (_ctx.stockType === 'warehouse' ? 'Gudang Pusat' : '-'));
      subEl.textContent = (_ctx.stockType === 'component' ? 'Component' : 'Bahan Baku')
        + ' · ' + locLabel
        + ' · Saldo: ' + _ctx.qtyBalance.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + _ctx.uomCode;
      actionSel.value = 'PLUS';
      dateInp.value   = new Date().toISOString().slice(0, 10);
      qtyInp.value    = '';
      noteArea.value  = '';
      showAlert('', '');
      syncAction();

      const modal = window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(modalEl);
      if (modal) modal.show();
    });

    actionSel.addEventListener('change', syncAction);

    submitBtn.addEventListener('click', async function () {
      const action = actionSel.value;
      const qty    = parseFloat(qtyInp.value || 0);
      const date   = dateInp.value;
      const reason = reasonSel.value || 'other';
      const note   = noteArea.value.trim();

      if (!date)    { showAlert('danger', 'Pilih tanggal adjustment.'); return; }
      if (!(qty > 0)) { showAlert('danger', 'Qty harus lebih dari 0.'); return; }

      submitBtn.disabled = true;
      showAlert('info', 'Menyimpan...');

      try {
        if (_ctx.stockType === 'component') {
          const line = {
            component_id: _ctx.componentId,
            uom_id:       _ctx.uomId,
            selected_lot_id: '',
            qty_spoil:    action === 'SPOIL' ? qty : 0,
            spoil_reason_code:           action === 'SPOIL' ? reason : 'other',
            qty_waste:    action === 'WASTE' ? qty : 0,
            waste_reason_code:            action === 'WASTE' ? reason : 'other',
            qty_adjust_neg: action === 'MINUS' ? qty : 0,
            adjustment_minus_reason_code: action === 'MINUS' ? reason : 'other',
            qty_adjust_pos: action === 'PLUS'  ? qty : 0,
            adjustment_plus_reason_code:  action === 'PLUS'  ? reason : 'other',
            unit_cost: 0,
            note: note,
          };
          const saveBody = {
            adjustment_date: date,
            location_type:   _ctx.locationType,
            division_id:     _ctx.divisionId || '',
            notes:           note,
            lines:           [line],
          };
          const saveRes  = await fetch(COMP_SAVE_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(saveBody) });
          const saveData = await saveRes.json();
          if (!saveRes.ok || !saveData.ok) throw new Error(saveData.message || 'Gagal menyimpan adjustment component.');
          const postRes  = await fetch(COMP_POST_URL + '/' + encodeURIComponent(saveData.id || 0), { method:'POST', headers:{'Content-Type':'application/json'}, body:'{}' });
          const postData = await postRes.json();
          if (!postRes.ok || !postData.ok) throw new Error(postData.message || 'Gagal posting adjustment component.');

        } else {
          const scope = _ctx.stockType === 'warehouse' ? 'WAREHOUSE' : 'DIVISION';
          const line  = {
            item_id:                       _ctx.itemId,
            content_uom_id:                _ctx.contentUomId,
            qty_waste_content:             action === 'WASTE' ? qty : 0,
            waste_reason_code:             action === 'WASTE' ? reason : 'other',
            qty_spoil_content:             action === 'SPOIL' ? qty : 0,
            spoil_reason_code:             action === 'SPOIL' ? reason : 'other',
            qty_variance_content:          action === 'MINUS' ? qty : 0,
            variance_reason_code:          action === 'MINUS' ? reason : 'other',
            qty_adjustment_plus_content:   action === 'PLUS'  ? qty : 0,
            adjustment_plus_reason_code:   action === 'PLUS'  ? reason : 'other',
            unit_cost: 0,
          };
          const body = {
            stock_scope:      scope,
            division_id:      scope === 'DIVISION' ? _ctx.divisionId : '',
            destination_type: scope === 'DIVISION' ? _ctx.destinationType : '',
            adjustment_date:  date,
            notes:            note,
            auto_post:        true,
            lines:            [line],
          };
          const res  = await fetch(MAT_STORE_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
          const data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || 'Gagal menyimpan adjustment bahan baku.');
        }

        showAlert('success', 'Adjustment berhasil diposting. Memuat ulang...');
        window.setTimeout(function () { window.location.reload(); }, 800);
      } catch (err) {
        showAlert('danger', esc(err.message || 'Terjadi kesalahan.'));
        submitBtn.disabled = false;
      }
    });
  })();

  // ─── Stok Kritis Detail — JS filter no reload ─────────────
  const critTabs = document.getElementById('critTabs');
  const critList = document.getElementById('critList');
  if (critTabs && critList) {
    critTabs.querySelectorAll('.fd-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        critTabs.querySelectorAll('.fd-tab').forEach(function(b) { b.classList.remove('on'); });
        btn.classList.add('on');
        const loc = btn.dataset.critloc;
        critList.querySelectorAll('.fd-item').forEach(function (row) {
          row.style.display = (loc === 'ALL' || row.dataset.critloc === loc) ? '' : 'none';
        });
      });
    });
  }
});
</script>
