<?php
$filters      = $filters      ?? ['period' => 'today', 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'), 'period_label' => 'Hari Ini'];
$chartFilters = $chart_filters ?? ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'period_label' => 'Bulan Ini'];
$kpi          = $kpi          ?? [];
$trend        = $trend        ?? ['labels' => [], 'sales' => [], 'orders' => [], 'po' => [], 'sr' => []];
$posStatusRows    = is_array($pos_status_rows  ?? null) ? $pos_status_rows  : [];
$posScopeRows     = is_array($pos_scope_rows   ?? null) ? $pos_scope_rows   : [];
$stockBreakdown   = is_array($stock_breakdown  ?? null) ? $stock_breakdown  : ['warehouse' => [], 'division' => [], 'component' => []];
$stockProductLive = is_array($stock_product_live ?? null) ? $stock_product_live : ['summary' => [], 'rows' => []];
$criticalStockRows = is_array($critical_stock_rows ?? null) ? $critical_stock_rows : [];
$recentActivity   = is_array($recent_activity  ?? null) ? $recent_activity  : [];

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

// Group product live rows by division
$productByDivision = [];
foreach (($stockProductLive['rows'] ?? []) as $r) {
    $div = (string)($r['division_name'] ?? 'LAIN');
    $productByDivision[$div][] = $r;
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
  @media (max-width:1399.98px) {
    .fd-kpi { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .fd-3col { grid-template-columns:1fr; }
  }
  @media (max-width:991.98px) {
    .fd-chart-grid, .fd-2col { grid-template-columns:1fr; }
    .fd-2scope { grid-template-columns:1fr; }
  }
  @media (max-width:767.98px) { .fd-kpi { grid-template-columns:1fr; } }
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

  <!-- Stok Produk (Live POS) -->
  <section class="fd-card p-3">
    <div class="fd-sec-head">
      <div>
        <h2 class="fd-sec-title">Stok Produk Live POS</h2>
        <p class="fd-sec-sub">Ketersediaan produk berdasarkan stok bahan baku real-time. Klik produk untuk lihat breakdown resep & bottleneck.</p>
      </div>
      <span class="fd-pill"><?= number_format((int)($stockProductLive['summary']['total'] ?? 0), 0, ',', '.') ?> produk</span>
    </div>

    <?php
    $summ = $stockProductLive['summary'] ?? [];
    $out = (int)($summ['out'] ?? 0);
    $lim = (int)($summ['limited'] ?? 0);
    $avl = (int)($summ['available'] ?? 0);
    $tot = (int)($summ['total'] ?? 0);
    ?>
    <div class="fd-prod-summ">
      <div class="fd-prod-summ-card">
        <div class="fd-prod-summ-val red"><?= $out ?></div>
        <div class="fd-prod-summ-lbl">Habis (OUT)</div>
      </div>
      <div class="fd-prod-summ-card">
        <div class="fd-prod-summ-val orange"><?= $lim ?></div>
        <div class="fd-prod-summ-lbl">Terbatas (LIMITED)</div>
      </div>
      <div class="fd-prod-summ-card">
        <div class="fd-prod-summ-val"><?= $tot ?></div>
        <div class="fd-prod-summ-lbl">Total Dipantau</div>
      </div>
      <div class="fd-prod-summ-card">
        <div class="fd-prod-summ-val green"><?= $avl ?></div>
        <div class="fd-prod-summ-lbl">Aman</div>
      </div>
    </div>

    <?php if (empty($productByDivision)): ?>
      <div class="fd-empty">Data ketersediaan produk belum tersedia.</div>
    <?php else: ?>
      <div class="fd-tabs" id="prodDivTabs">
        <?php $first = true; foreach (array_keys($productByDivision) as $divName): ?>
          <?php
          $rows = $productByDivision[$divName];
          $cntOut = count(array_filter($rows, fn($r) => strtoupper($r['availability_status']) === 'OUT'));
          $cntLim = count(array_filter($rows, fn($r) => strtoupper($r['availability_status']) === 'LIMITED'));
          ?>
          <button class="fd-tab <?= $first ? 'on' : '' ?>" data-pdiv="<?= htmlspecialchars($divName) ?>">
            <?= htmlspecialchars($divName) ?> <?php if ($cntOut > 0): ?><span style="color:#c62828">(<?= $cntOut ?> habis)</span><?php elseif ($cntLim > 0): ?><span style="color:#e65100">(<?= $cntLim ?> terbatas)</span><?php endif; ?>
          </button>
          <?php $first = false; endforeach; ?>
      </div>

      <?php foreach ($productByDivision as $divName => $rows): ?>
        <div class="fd-prod-div-content fd-scroll" data-pdiv="<?= htmlspecialchars($divName) ?>" style="<?= array_key_first($productByDivision) !== $divName ? 'display:none' : '' ?>">
          <?php foreach ($rows as $pr): ?>
            <?php
            $status = strtoupper((string)($pr['availability_status'] ?? 'AVAILABLE'));
            $qty = (float)($pr['qty'] ?? 0);
            $bn = (string)($pr['bottleneck_name_snapshot'] ?? '');
            $isDirty = !empty($pr['is_dirty']);
            $statusClass = $status === 'OUT' ? 'minus' : ($status === 'LIMITED' ? 'kritis' : '');
            $statusLabel = $status === 'OUT' ? 'HABIS' : ($status === 'LIMITED' ? 'TERBATAS' : 'TERSEDIA');
            $pillClass = $status === 'OUT' ? 'minus' : ($status === 'LIMITED' ? 'kritis' : 'ok');
            ?>
            <div class="fd-prod-row" style="margin-bottom:.5rem">
              <div class="fd-prod-head" data-pid="<?= (int)($pr['product_id'] ?? 0) ?>" onclick="fdToggleRecipe(this)">
                <div>
                  <div class="fd-prod-name"><?= htmlspecialchars((string)($pr['product_name'] ?? '-')) ?></div>
                  <div class="fd-prod-meta">
                    <?= $bn !== '' ? 'Bottleneck: <strong>' . htmlspecialchars($bn) . '</strong> · ' : '' ?>
                    Klik untuk lihat resep<?= $isDirty ? ' · <em>Cache lama</em>' : '' ?>
                  </div>
                </div>
                <div class="fd-prod-right">
                  <div>
                    <div class="fd-prod-qty"><?= number_format($qty, 2, ',', '.') ?></div>
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

  // Division sub-tabs
  bindTabs('dvSubTabs', '.fd-dv-loc', 'dvloc');
  bindTabs('cpSubTabs', '.fd-cp-loc', 'cploc');

  // ─── Product division tabs ────────────────────────────────
  document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#prodDivTabs .fd-tab').forEach(function(b) { b.classList.remove('on'); });
      btn.classList.add('on');
      document.querySelectorAll('.fd-prod-div-content').forEach(function(el) {
        el.style.display = el.dataset.pdiv === btn.dataset.pdiv ? '' : 'none';
      });
    });
  });

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
        html += '<tr style="color:#8b7772;font-weight:700"><th style="padding:.3rem 0;text-align:left">Bahan</th><th style="padding:.3rem;text-align:center">Peran</th><th style="padding:.3rem;text-align:right">Per Saji</th><th style="padding:.3rem;text-align:right">Stok Live</th></tr>';
        data.data.recipe.forEach(function(r) {
          const stockCls = r.stock_qty <= 0 ? 'bad' : 'ok';
          const stockFmt = Number(r.stock_qty).toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2});
          html += '<tr style="border-top:1px solid rgba(170,95,78,.1)">';
          html += '<td style="padding:.32rem 0"><span class="fd-item-title">' + (r.ingredient_name||'-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:center"><span class="fd-pill" style="font-size:.7rem">' + (r.ingredient_role||'-') + '</span></td>';
          html += '<td style="padding:.32rem;text-align:right">' + Number(r.qty_per_serve).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + (r.uom_code||'') + '</td>';
          html += '<td style="padding:.32rem;text-align:right"><span class="fd-recipe-stock ' + stockCls + '">' + stockFmt + '</span></td>';
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
