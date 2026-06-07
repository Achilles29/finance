<?php
$filters = $filters ?? ['period' => 'month', 'date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d'), 'period_label' => 'Bulan Ini'];
$kpi = $kpi ?? [];
$trend = $trend ?? ['labels' => [], 'sales' => [], 'orders' => [], 'po' => [], 'sr' => []];
$posStatusRows = is_array($pos_status_rows ?? null) ? $pos_status_rows : [];
$posScopeRows = is_array($pos_scope_rows ?? null) ? $pos_scope_rows : [];
$stockCards = is_array($stock_cards ?? null) ? $stock_cards : [];
$stockBreakdown = is_array($stock_breakdown ?? null) ? $stock_breakdown : ['warehouse' => [], 'division' => [], 'component' => []];
$criticalStockRows = is_array($critical_stock_rows ?? null) ? $critical_stock_rows : [];
$criticalDivisions = is_array($critical_divisions ?? null) ? $critical_divisions : [];
$criticalDivisionFilter = (int)($critical_division_filter ?? 0);
$recentActivity = is_array($recent_activity ?? null) ? $recent_activity : [];

$formatCurrency = static function ($amount): string {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
};

$statusPalette = [
    'PAID' => '#2e7d32', 'SERVED' => '#2e7d32', 'READY' => '#0288d1',
    'IN_KITCHEN' => '#7b1fa2', 'CONFIRMED' => '#1565c0', 'PENDING' => '#ef6c00',
    'PAID_PARTIAL' => '#f9a825', 'DRAFT' => '#8d6e63', 'VOID' => '#c62828',
    'REFUND_PARTIAL' => '#ad1457', 'REFUND_FULL' => '#6a1b9a', 'APPROVED' => '#00897b',
    'ORDERED' => '#3949ab', 'PARTIAL_RECEIVED' => '#5e35b1', 'RECEIVED' => '#43a047',
    'SUBMITTED' => '#fb8c00', 'PARTIAL_FULFILLED' => '#8e24aa', 'FULFILLED' => '#2e7d32',
];

$statusLabels = [];
$statusTotals = [];
$statusColors = [];
foreach ($posStatusRows as $row) {
    $statusCode = strtoupper((string)($row['status'] ?? '-'));
    $statusLabels[] = $statusCode;
    $statusTotals[] = (int)($row['total'] ?? 0);
    $statusColors[] = $statusPalette[$statusCode] ?? '#8d6e63';
}
?>

<style>
  .fin-dash-shell { display: grid; gap: 1rem; }
  .fin-dash-hero {
    background: linear-gradient(135deg, #f7efe8 0%, #fdf9f6 54%, #fffdfb 100%);
    border: 1px solid rgba(170, 95, 78, 0.14); border-radius: 24px;
    padding: 1.25rem 1.3rem; box-shadow: 0 14px 30px rgba(109, 47, 30, 0.08);
  }
  .fin-dash-kicker { display: inline-flex; align-items: center; gap: .45rem; font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #8f2d23; }
  .fin-dash-title { margin: .45rem 0 .25rem; font-size: 1.8rem; font-weight: 800; color: #6f2119; }
  .fin-dash-desc { margin: 0; color: #7e6761; }
  .fin-dash-filter { border-radius: 18px; border: 1px solid rgba(170, 95, 78, 0.16); background: rgba(255,255,255,.88); padding: .9rem; }
  .fin-dash-card { border: 1px solid rgba(170, 95, 78, 0.16); border-radius: 20px; box-shadow: 0 12px 24px rgba(109, 47, 30, 0.08); background: #fff; height: 100%; }
  .fin-dash-kpi { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1rem; }
  .fin-dash-kpi-card { padding: 1rem 1rem .95rem; }
  .fin-dash-kpi-label { color: #8d6a63; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  .fin-dash-kpi-value { margin-top: .5rem; font-size: 1.6rem; line-height: 1.1; font-weight: 800; color: #6f2119; }
  .fin-dash-kpi-note { margin-top: .35rem; font-size: .82rem; color: #8b7772; }
  .fin-dash-section-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
  .fin-dash-section-title { margin: 0; font-size: 1.08rem; font-weight: 800; color: #6f2119; }
  .fin-dash-section-sub { margin: .18rem 0 0; color: #8b7772; font-size: .85rem; }
  .fin-dash-chart-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.5fr) minmax(320px, .8fr); }
  .fin-dash-info-grid { display: grid; gap: 1rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .fin-dash-bottom-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr); }
  .fin-dash-scope-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
  .fin-dash-mini-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; }
  .fin-dash-mini { border-radius: 16px; padding: .9rem; background: linear-gradient(180deg, rgba(249,239,234,.9), rgba(255,255,255,.98)); border: 1px solid rgba(170,95,78,.14); }
  .fin-dash-mini-label { color: #8b7772; font-size: .75rem; font-weight: 700; text-transform: uppercase; }
  .fin-dash-mini-value { margin-top: .3rem; font-size: 1.15rem; font-weight: 800; color: #6f2119; }
  .fin-dash-list { display: grid; gap: .7rem; }
  .fin-dash-list-item { display: flex; align-items: center; justify-content: space-between; gap: .8rem; border-radius: 16px; padding: .9rem 1rem; background: #fffaf7; border: 1px solid rgba(170,95,78,.14); }
  .fin-dash-list-item.is-minus { background: #fff5f5; border-color: rgba(198,40,40,.18); }
  .fin-dash-list-item.is-kritis { background: #fffaf5; border-color: rgba(239,108,0,.18); }
  .fin-dash-list-title { font-weight: 800; color: #6f2119; }
  .fin-dash-list-meta { margin-top: .16rem; color: #8b7772; font-size: .84rem; }
  .fin-dash-pill { display: inline-flex; align-items: center; padding: .3rem .62rem; border-radius: 999px; background: #fff0ea; color: #8f2d23; border: 1px solid rgba(170,95,78,.16); font-size: .75rem; font-weight: 800; }
  .fin-dash-pill.minus { background: #fff0f0; color: #c62828; border-color: rgba(198,40,40,.2); }
  .fin-dash-pill.kritis { background: #fff8f0; color: #e65100; border-color: rgba(239,108,0,.2); }
  .fin-dash-empty { color: #8b7772; text-align: center; padding: 1rem; }
  .fin-dash-scope-card { padding: 1rem; background: linear-gradient(160deg, rgba(255,247,242,.96), rgba(255,255,255,.98)); }
  .fin-dash-scope-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
  .fin-dash-scope-title { margin: 0; font-size: 1.15rem; font-weight: 800; color: #6f2119; }
  .fin-dash-scope-value { margin-top: .45rem; font-size: 1.55rem; font-weight: 800; color: #8f2d23; }
  .fin-dash-scope-meta-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; margin-top: .95rem; }
  .fin-dash-scope-meta-label { color: #8b7772; font-size: .74rem; font-weight: 700; text-transform: uppercase; }
  .fin-dash-scope-meta-value { margin-top: .22rem; font-weight: 800; color: #6f2119; }
  .fin-dash-stock-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .fin-dash-stock-tab { padding: .38rem .85rem; border-radius: 999px; font-size: .8rem; font-weight: 700; cursor: pointer; border: 1px solid rgba(170,95,78,.2); background: #fff; color: #8b7772; transition: all .14s; }
  .fin-dash-stock-tab.active { background: #8f2d23; color: #fff; border-color: #8f2d23; }
  .fin-dash-div-filter { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .75rem; align-items: center; }
  .fin-dash-div-btn { padding: .28rem .7rem; border-radius: 999px; font-size: .76rem; font-weight: 700; cursor: pointer; border: 1px solid rgba(170,95,78,.2); background: #fff; color: #8b7772; text-decoration: none; transition: all .14s; }
  .fin-dash-div-btn:hover { background: #f7ede9; }
  .fin-dash-div-btn.active { background: #6f2119; color: #fff; border-color: #6f2119; }
  @media (max-width: 1399.98px) {
    .fin-dash-kpi { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .fin-dash-info-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 991.98px) {
    .fin-dash-chart-grid, .fin-dash-bottom-grid { grid-template-columns: 1fr; }
    .fin-dash-scope-grid, .fin-dash-mini-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 767.98px) { .fin-dash-kpi { grid-template-columns: 1fr; } }
</style>

<div class="fin-dash-shell">
  <section class="fin-dash-hero">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
      <div>
        <div class="fin-dash-kicker"><i class="ri-dashboard-line"></i> Ringkasan Finance</div>
        <h1 class="fin-dash-title">Dashboard Operasional</h1>
        <p class="fin-dash-desc">
          Selamat datang, <strong><?= htmlspecialchars($current_user['username'] ?? '') ?></strong>.
          Dashboard ini merangkum transaksi POS, purchase, stok, dan kehadiran dalam periode <strong><?= htmlspecialchars((string)$filters['period_label']) ?></strong>.
        </p>
      </div>
      <span class="fin-dash-pill">Update <?= date('d-m-Y H:i') ?></span>
    </div>
  </section>

  <form method="get" action="<?= base_url('dashboard') ?>" class="fin-dash-filter fin-dash-card">
    <div class="row g-2 align-items-end">
      <div class="col-xl-3 col-md-4">
        <label class="form-label mb-1">Periode</label>
        <select name="period" id="dashboardPeriodSelect" class="form-select">
          <option value="month" <?= ($filters['period'] ?? '') === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
          <option value="today" <?= ($filters['period'] ?? '') === 'today' ? 'selected' : '' ?>>Hari Ini</option>
          <option value="7" <?= ($filters['period'] ?? '') === '7' ? 'selected' : '' ?>>7 Hari Terakhir</option>
          <option value="30" <?= ($filters['period'] ?? '') === '30' ? 'selected' : '' ?>>30 Hari Terakhir</option>
          <option value="custom" <?= ($filters['period'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom</option>
        </select>
      </div>
      <div class="col-xl-3 col-md-4 dashboard-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Dari</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars((string)($filters['date_from'] ?? '')) ?>">
      </div>
      <div class="col-xl-3 col-md-4 dashboard-custom-date <?= ($filters['period'] ?? '') === 'custom' ? '' : 'd-none' ?>">
        <label class="form-label mb-1">Sampai</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars((string)($filters['date_to'] ?? '')) ?>">
      </div>
      <div class="col-xl-3 col-md-12 d-grid">
        <button type="submit" class="btn btn-primary">Terapkan Filter</button>
      </div>
    </div>
  </form>

  <section class="fin-dash-kpi">
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Transaksi POS</div>
      <div class="fin-dash-kpi-value"><?= number_format((int)($kpi['transaction_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fin-dash-kpi-note">Order non draft, non pending, dan non void pada periode terpilih.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Omzet POS</div>
      <div class="fin-dash-kpi-value"><?= $formatCurrency($kpi['sales_total'] ?? 0) ?></div>
      <div class="fin-dash-kpi-note">Paid total <?= $formatCurrency($kpi['gross_sales_total'] ?? 0) ?> dikurangi refund <?= $formatCurrency($kpi['refund_total'] ?? 0) ?>.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Stok Kritis</div>
      <div class="fin-dash-kpi-value"><?= number_format((int)($kpi['critical_stock_total'] ?? 0), 0, ',', '.') ?></div>
      <div class="fin-dash-kpi-note">Row stok gudang, divisi, dan component yang nol atau minus.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Karyawan Hadir</div>
      <div class="fin-dash-kpi-value"><?= number_format((int)($kpi['present_employee_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fin-dash-kpi-note">Attendance PRESENT, LATE, atau HOLIDAY dalam periode aktif.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Nilai PO</div>
      <div class="fin-dash-kpi-value" style="font-size:1.25rem"><?= $formatCurrency($kpi['total_nilai_po'] ?? 0) ?></div>
      <div class="fin-dash-kpi-note">Total nilai purchase order (non-void) dalam periode terpilih.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Nilai SR</div>
      <div class="fin-dash-kpi-value" style="font-size:1.25rem"><?= $formatCurrency($kpi['total_nilai_sr'] ?? 0) ?></div>
      <div class="fin-dash-kpi-note">Estimasi nilai store request dari harga beli katalog dalam periode terpilih.</div>
    </div>
  </section>

  <section class="fin-dash-scope-grid">
    <?php if (empty($posScopeRows)): ?>
      <div class="fin-dash-card fin-dash-scope-card">
        <div class="fin-dash-empty">Belum ada data Reguler atau Event untuk periode ini.</div>
      </div>
    <?php else: ?>
      <?php foreach ($posScopeRows as $scopeRow): ?>
        <div class="fin-dash-card fin-dash-scope-card">
          <div class="fin-dash-scope-head">
            <h2 class="fin-dash-scope-title"><?= htmlspecialchars((string)($scopeRow['scope_label'] ?? '-')) ?></h2>
            <span class="fin-dash-pill"><?= htmlspecialchars((string)($scopeRow['scope_code'] ?? '-')) ?></span>
          </div>
          <div class="fin-dash-list-meta mt-1">Breakdown penjualan bersih POS berdasarkan scope order.</div>
          <div class="fin-dash-scope-value"><?= $formatCurrency($scopeRow['net_sales_total'] ?? 0) ?></div>
          <div class="fin-dash-scope-meta-grid">
            <div>
              <div class="fin-dash-scope-meta-label">Transaksi</div>
              <div class="fin-dash-scope-meta-value"><?= number_format((int)($scopeRow['transaction_count'] ?? 0), 0, ',', '.') ?></div>
            </div>
            <div>
              <div class="fin-dash-scope-meta-label">Paid Total</div>
              <div class="fin-dash-scope-meta-value"><?= $formatCurrency($scopeRow['gross_sales_total'] ?? 0) ?></div>
            </div>
            <div>
              <div class="fin-dash-scope-meta-label">Refund</div>
              <div class="fin-dash-scope-meta-value"><?= $formatCurrency($scopeRow['refund_total'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="fin-dash-chart-grid">
    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Tren Omzet, Nilai PO & Store Request</h2>
          <p class="fin-dash-section-sub">Omzet bersih POS, total nilai PO, dan jumlah SR per hari dalam periode terpilih.</p>
        </div>
        <span class="fin-dash-pill"><?= htmlspecialchars((string)($filters['period_label'] ?? '')) ?></span>
      </div>
      <div id="financeDashboardTrendChart" style="min-height: 340px;"></div>
    </div>
    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Komposisi Status POS</h2>
          <p class="fin-dash-section-sub">Distribusi status order yang tercatat di periode aktif.</p>
        </div>
      </div>
      <div id="financeDashboardStatusChart" style="min-height: 340px;"></div>
    </div>
  </section>

  <section class="fin-dash-info-grid">
    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Kesehatan Stok</h2>
          <p class="fin-dash-section-sub">Ringkasan saldo gudang, divisi, dan base/prepare.</p>
        </div>
      </div>
      <div class="fin-dash-mini-grid">
        <?php if (empty($stockCards)): ?>
          <div class="fin-dash-empty">Belum ada tabel stok yang bisa dibaca.</div>
        <?php else: ?>
          <?php foreach ($stockCards as $card): ?>
            <div class="fin-dash-mini">
              <div class="fin-dash-mini-label"><?= htmlspecialchars((string)($card['label'] ?? '-')) ?></div>
              <div class="fin-dash-mini-value"><?= number_format((int)($card['total_rows'] ?? 0), 0, ',', '.') ?> row</div>
              <div class="fin-dash-list-meta mt-1">Kritis: <?= number_format((int)($card['critical_count'] ?? 0), 0, ',', '.') ?></div>
              <div class="fin-dash-list-meta">Nilai: <?= $formatCurrency($card['total_value'] ?? 0) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Stok Minus & Kritis</h2>
          <p class="fin-dash-section-sub">Item yang minus atau menyentuh threshold per lokasi stok.</p>
        </div>
      </div>
      <div class="fin-dash-stock-tabs">
        <button class="fin-dash-stock-tab active" data-tab="wh">Gudang (<?= count($stockBreakdown['warehouse'] ?? []) ?>)</button>
        <button class="fin-dash-stock-tab" data-tab="dv">Divisi (<?= count($stockBreakdown['division'] ?? []) ?>)</button>
        <button class="fin-dash-stock-tab" data-tab="cp">Base/Prepare (<?= count($stockBreakdown['component'] ?? []) ?>)</button>
      </div>

      <div id="stockTabWh" class="fin-dash-stock-tab-content fin-dash-list">
        <?php if (empty($stockBreakdown['warehouse'])): ?>
          <div class="fin-dash-empty">Tidak ada stok gudang yang minus atau kritis.</div>
        <?php else: ?>
          <?php foreach ($stockBreakdown['warehouse'] as $row): ?>
            <?php $sev = (string)($row['severity'] ?? 'kritis'); ?>
            <div class="fin-dash-list-item is-<?= $sev ?>">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['item_name'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta">Threshold <?= number_format((float)($row['threshold'] ?? 0), 2, ',', '.') ?></div>
              </div>
              <div class="text-end">
                <span class="fin-dash-pill <?= $sev ?>"><?= number_format((float)($row['qty'] ?? 0), 2, ',', '.') ?></span>
                <div class="fin-dash-list-meta"><?= $formatCurrency($row['total_value'] ?? 0) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div id="stockTabDv" class="fin-dash-stock-tab-content fin-dash-list d-none">
        <?php if (empty($stockBreakdown['division'])): ?>
          <div class="fin-dash-empty">Tidak ada stok divisi yang minus atau kritis.</div>
        <?php else: ?>
          <?php foreach ($stockBreakdown['division'] as $row): ?>
            <?php $sev = (string)($row['severity'] ?? 'kritis'); ?>
            <div class="fin-dash-list-item is-<?= $sev ?>">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['item_name'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta"><?= htmlspecialchars((string)($row['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($row['threshold'] ?? 0), 2, ',', '.') ?></div>
              </div>
              <div class="text-end">
                <span class="fin-dash-pill <?= $sev ?>"><?= number_format((float)($row['qty'] ?? 0), 2, ',', '.') ?></span>
                <div class="fin-dash-list-meta"><?= $formatCurrency($row['total_value'] ?? 0) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div id="stockTabCp" class="fin-dash-stock-tab-content fin-dash-list d-none">
        <?php if (empty($stockBreakdown['component'])): ?>
          <div class="fin-dash-empty">Tidak ada stok base/prepare yang minus atau kritis.</div>
        <?php else: ?>
          <?php foreach ($stockBreakdown['component'] as $row): ?>
            <?php $sev = (string)($row['severity'] ?? 'kritis'); ?>
            <div class="fin-dash-list-item is-<?= $sev ?>">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['item_name'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta"><?= htmlspecialchars((string)($row['location_name'] ?? '-')) ?> · Threshold <?= number_format((float)($row['threshold'] ?? 0), 2, ',', '.') ?></div>
              </div>
              <div class="text-end">
                <span class="fin-dash-pill <?= $sev ?>"><?= number_format((float)($row['qty'] ?? 0), 2, ',', '.') ?></span>
                <div class="fin-dash-list-meta"><?= $formatCurrency($row['total_value'] ?? 0) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Aktivitas Terbaru</h2>
          <p class="fin-dash-section-sub">Gabungan transaksi POS, purchase, dan movement inventory.</p>
        </div>
      </div>
      <div class="fin-dash-list">
        <?php if (empty($recentActivity)): ?>
          <div class="fin-dash-empty">Belum ada aktivitas pada periode ini.</div>
        <?php else: ?>
          <?php foreach ($recentActivity as $row): ?>
            <div class="fin-dash-list-item">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['source_label'] ?? '-')) ?> · <?= htmlspecialchars((string)($row['ref_no'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta">Status: <?= htmlspecialchars((string)($row['status'] ?? '-')) ?> · <?= htmlspecialchars(date('d-m-Y H:i', strtotime((string)($row['event_at'] ?? 'now')))) ?></div>
              </div>
              <div class="text-end">
                <div class="fin-dash-list-title"><?= $formatCurrency($row['amount'] ?? 0) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="fin-dash-card p-3">
    <div class="fin-dash-section-head">
      <div>
        <h2 class="fin-dash-section-title">Stok Kritis Detail</h2>
        <p class="fin-dash-section-sub">Item yang menyentuh atau turun di bawah threshold. Filter per divisi untuk breakdown bahan.</p>
      </div>
    </div>

    <?php if (!empty($criticalDivisions)): ?>
    <div class="fin-dash-div-filter">
      <span style="font-size:.8rem;font-weight:700;color:#8b7772;">Filter Divisi:</span>
      <a href="<?= base_url('dashboard') ?>?period=<?= urlencode($filters['period']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>"
         class="fin-dash-div-btn <?= $criticalDivisionFilter === 0 ? 'active' : '' ?>">Semua</a>
      <?php foreach ($criticalDivisions as $div): ?>
        <a href="<?= base_url('dashboard') ?>?period=<?= urlencode($filters['period']) ?>&date_from=<?= urlencode($filters['date_from']) ?>&date_to=<?= urlencode($filters['date_to']) ?>&critical_division_id=<?= (int)$div['id'] ?>"
           class="fin-dash-div-btn <?= $criticalDivisionFilter === (int)$div['id'] ? 'active' : '' ?>">
          <?= htmlspecialchars((string)($div['name'] ?? '')) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fin-dash-list">
      <?php if (empty($criticalStockRows)): ?>
        <div class="fin-dash-empty">
          <?= $criticalDivisionFilter > 0 ? 'Tidak ada stok kritis untuk divisi yang dipilih.' : 'Belum ada item kritis yang terdeteksi.' ?>
        </div>
      <?php else: ?>
        <?php foreach ($criticalStockRows as $row): ?>
          <div class="fin-dash-list-item">
            <div>
              <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['item_name'] ?? '-')) ?></div>
              <div class="fin-dash-list-meta">
                <?= htmlspecialchars((string)($row['stock_scope'] ?? '-')) ?> · <?= htmlspecialchars((string)($row['location_name'] ?? '-')) ?> ·
                Threshold <?= number_format((float)($row['threshold_qty'] ?? 0), 2, ',', '.') ?>
              </div>
            </div>
            <div class="text-end">
              <div class="fin-dash-list-title"><?= number_format((float)($row['qty_balance'] ?? 0), 2, ',', '.') ?></div>
              <div class="fin-dash-list-meta"><?= $formatCurrency($row['total_value'] ?? 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
  (function () {
    const periodSelect = document.getElementById('dashboardPeriodSelect');
    const customFields = Array.from(document.querySelectorAll('.dashboard-custom-date'));
    if (periodSelect) {
      periodSelect.addEventListener('change', function () {
        const showCustom = this.value === 'custom';
        customFields.forEach(function (node) { node.classList.toggle('d-none', !showCustom); });
      });
    }

    // Stock breakdown tabs
    const tabBtns = Array.from(document.querySelectorAll('.fin-dash-stock-tab'));
    const tabContents = {
      wh: document.getElementById('stockTabWh'),
      dv: document.getElementById('stockTabDv'),
      cp: document.getElementById('stockTabCp'),
    };
    tabBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        tabBtns.forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        Object.values(tabContents).forEach(function (el) { if (el) el.classList.add('d-none'); });
        const target = tabContents[btn.dataset.tab];
        if (target) target.classList.remove('d-none');
      });
    });

    const rupiah = function (value) {
      return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 0});
    };

    const trendEl = document.querySelector('#financeDashboardTrendChart');
    if (trendEl && window.ApexCharts) {
      const hasSingleDay = <?= json_encode(count($trend['labels'] ?? []) <= 1) ?>;
      const trendChart = new ApexCharts(trendEl, {
        chart: {type: 'line', height: 340, toolbar: {show: false}},
        series: [
          {name: 'Omzet Bersih', type: hasSingleDay ? 'bar' : 'area', data: <?= json_encode(array_values($trend['sales'] ?? [])) ?>},
          {name: 'Nilai PO', type: hasSingleDay ? 'bar' : 'line', data: <?= json_encode(array_values($trend['po'] ?? [])) ?>},
          {name: 'Jml SR', type: 'line', data: <?= json_encode(array_values($trend['sr'] ?? [])) ?>},
        ],
        colors: ['#8f2d23', '#1e88e5', '#ef6c00'],
        stroke: {curve: 'smooth', width: [3, 2, 2]},
        fill: {type: ['solid', 'solid', 'solid'], opacity: [hasSingleDay ? 0.9 : 0.18, hasSingleDay ? 0.9 : 1, 1]},
        dataLabels: {enabled: false},
        xaxis: {categories: <?= json_encode(array_values($trend['labels'] ?? [])) ?>},
        yaxis: [
          {seriesName: 'Omzet Bersih', labels: {formatter: function (val) { return rupiah(val); }}},
          {seriesName: 'Nilai PO', show: false, labels: {formatter: function (val) { return rupiah(val); }}},
          {seriesName: 'Jml SR', opposite: true, labels: {formatter: function (val) { return Number(val || 0).toLocaleString('id-ID') + ' SR'; }}},
        ],
        tooltip: {
          shared: true,
          y: [
            {formatter: function (val) { return rupiah(val); }},
            {formatter: function (val) { return rupiah(val); }},
            {formatter: function (val) { return Number(val || 0).toLocaleString('id-ID') + ' SR'; }},
          ]
        },
        legend: {position: 'top'}
      });
      trendChart.render();
    }

    const statusEl = document.querySelector('#financeDashboardStatusChart');
    if (statusEl && window.ApexCharts) {
      const totals = <?= json_encode(array_values($statusTotals)) ?>;
      const statusChart = new ApexCharts(statusEl, {
        chart: {type: 'donut', height: 340},
        series: totals.length ? totals : [1],
        labels: totals.length ? <?= json_encode(array_values($statusLabels)) ?> : ['Belum Ada Data'],
        colors: totals.length ? <?= json_encode(array_values($statusColors)) ?> : ['#c7b2ad'],
        legend: {position: 'bottom'},
        dataLabels: {enabled: true},
        tooltip: {y: {formatter: function (val) { return Number(val || 0).toLocaleString('id-ID') + ' order'; }}}
      });
      statusChart.render();
    }
  })();
</script>
