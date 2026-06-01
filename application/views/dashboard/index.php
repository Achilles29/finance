<?php
$filters = $filters ?? ['period' => 'today', 'date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'), 'period_label' => 'Hari Ini'];
$kpi = $kpi ?? [];
$trend = $trend ?? ['labels' => [], 'sales' => [], 'orders' => []];
$posStatusRows = is_array($pos_status_rows ?? null) ? $pos_status_rows : [];
$posScopeRows = is_array($pos_scope_rows ?? null) ? $pos_scope_rows : [];
$purchaseStatusRows = is_array($purchase_status_rows ?? null) ? $purchase_status_rows : [];
$storeRequestStatusRows = is_array($store_request_status_rows ?? null) ? $store_request_status_rows : [];
$stockCards = is_array($stock_cards ?? null) ? $stock_cards : [];
$criticalStockRows = is_array($critical_stock_rows ?? null) ? $critical_stock_rows : [];
$recentActivity = is_array($recent_activity ?? null) ? $recent_activity : [];

$formatCurrency = static function ($amount): string {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
};

$statusPalette = [
    'PAID' => '#2e7d32',
    'SERVED' => '#2e7d32',
    'READY' => '#0288d1',
    'IN_KITCHEN' => '#7b1fa2',
    'CONFIRMED' => '#1565c0',
    'PENDING' => '#ef6c00',
    'PAID_PARTIAL' => '#f9a825',
    'DRAFT' => '#8d6e63',
    'VOID' => '#c62828',
    'REFUND_PARTIAL' => '#ad1457',
    'REFUND_FULL' => '#6a1b9a',
    'APPROVED' => '#00897b',
    'ORDERED' => '#3949ab',
    'PARTIAL_RECEIVED' => '#5e35b1',
    'RECEIVED' => '#43a047',
    'SUBMITTED' => '#fb8c00',
    'PARTIAL_FULFILLED' => '#8e24aa',
    'FULFILLED' => '#2e7d32',
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
  .fin-dash-shell {
    display: grid;
    gap: 1rem;
  }
  .fin-dash-hero {
    background: linear-gradient(135deg, #f7efe8 0%, #fdf9f6 54%, #fffdfb 100%);
    border: 1px solid rgba(170, 95, 78, 0.14);
    border-radius: 24px;
    padding: 1.25rem 1.3rem;
    box-shadow: 0 14px 30px rgba(109, 47, 30, 0.08);
  }
  .fin-dash-kicker {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #8f2d23;
  }
  .fin-dash-title {
    margin: .45rem 0 .25rem;
    font-size: 1.8rem;
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-desc {
    margin: 0;
    color: #7e6761;
  }
  .fin-dash-filter {
    border-radius: 18px;
    border: 1px solid rgba(170, 95, 78, 0.16);
    background: rgba(255,255,255,.88);
    padding: .9rem;
  }
  .fin-dash-card {
    border: 1px solid rgba(170, 95, 78, 0.16);
    border-radius: 20px;
    box-shadow: 0 12px 24px rgba(109, 47, 30, 0.08);
    background: #fff;
    height: 100%;
  }
  .fin-dash-kpi {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 1rem;
  }
  .fin-dash-kpi-card {
    padding: 1rem 1rem .95rem;
  }
  .fin-dash-kpi-label {
    color: #8d6a63;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
  }
  .fin-dash-kpi-value {
    margin-top: .5rem;
    font-size: 1.6rem;
    line-height: 1.1;
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-kpi-note {
    margin-top: .35rem;
    font-size: .82rem;
    color: #8b7772;
  }
  .fin-dash-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  .fin-dash-section-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-section-sub {
    margin: .18rem 0 0;
    color: #8b7772;
    font-size: .85rem;
  }
  .fin-dash-chart-grid,
  .fin-dash-info-grid,
  .fin-dash-bottom-grid {
    display: grid;
    gap: 1rem;
  }
  .fin-dash-chart-grid {
    grid-template-columns: minmax(0, 1.5fr) minmax(320px, .8fr);
  }
  .fin-dash-info-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .fin-dash-bottom-grid {
    grid-template-columns: minmax(0, 1.1fr) minmax(0, .9fr);
  }
  .fin-dash-scope-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .fin-dash-mini-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .8rem;
  }
  .fin-dash-mini {
    border-radius: 16px;
    padding: .9rem;
    background: linear-gradient(180deg, rgba(249,239,234,.9), rgba(255,255,255,.98));
    border: 1px solid rgba(170,95,78,.14);
  }
  .fin-dash-mini-label {
    color: #8b7772;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
  }
  .fin-dash-mini-value {
    margin-top: .3rem;
    font-size: 1.15rem;
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-list {
    display: grid;
    gap: .7rem;
  }
  .fin-dash-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .8rem;
    border-radius: 16px;
    padding: .9rem 1rem;
    background: #fffaf7;
    border: 1px solid rgba(170,95,78,.14);
  }
  .fin-dash-list-title {
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-list-meta {
    margin-top: .16rem;
    color: #8b7772;
    font-size: .84rem;
  }
  .fin-dash-pill {
    display: inline-flex;
    align-items: center;
    padding: .3rem .62rem;
    border-radius: 999px;
    background: #fff0ea;
    color: #8f2d23;
    border: 1px solid rgba(170,95,78,.16);
    font-size: .75rem;
    font-weight: 800;
  }
  .fin-dash-empty {
    color: #8b7772;
    text-align: center;
    padding: 1rem;
  }
  .fin-dash-scope-card {
    padding: 1rem;
    background: linear-gradient(160deg, rgba(255,247,242,.96), rgba(255,255,255,.98));
  }
  .fin-dash-scope-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
  }
  .fin-dash-scope-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #6f2119;
  }
  .fin-dash-scope-value {
    margin-top: .45rem;
    font-size: 1.55rem;
    font-weight: 800;
    color: #8f2d23;
  }
  .fin-dash-scope-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .8rem;
    margin-top: .95rem;
  }
  .fin-dash-scope-meta-label {
    color: #8b7772;
    font-size: .74rem;
    font-weight: 700;
    text-transform: uppercase;
  }
  .fin-dash-scope-meta-value {
    margin-top: .22rem;
    font-weight: 800;
    color: #6f2119;
  }
  @media (max-width: 1399.98px) {
    .fin-dash-kpi { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .fin-dash-info-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 991.98px) {
    .fin-dash-chart-grid,
    .fin-dash-bottom-grid { grid-template-columns: 1fr; }
    .fin-dash-scope-grid,
    .fin-dash-mini-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 767.98px) {
    .fin-dash-kpi { grid-template-columns: 1fr; }
  }
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
      <div class="fin-dash-kpi-note">Bersih dari paid total <?= $formatCurrency($kpi['gross_sales_total'] ?? 0) ?> dikurangi refund <?= $formatCurrency($kpi['refund_total'] ?? 0) ?>.</div>
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
      <div class="fin-dash-kpi-label">PO Aktif</div>
      <div class="fin-dash-kpi-value"><?= number_format((int)($kpi['open_purchase_order_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fin-dash-kpi-note">Purchase order yang belum selesai dibayar/void.</div>
    </div>
    <div class="fin-dash-card fin-dash-kpi-card">
      <div class="fin-dash-kpi-label">Store Request Pending</div>
      <div class="fin-dash-kpi-value"><?= number_format((int)($kpi['pending_store_request_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="fin-dash-kpi-note">Store request yang masih perlu tindak lanjut.</div>
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
          <h2 class="fin-dash-section-title">Tren Omzet Bersih & Transaksi</h2>
          <p class="fin-dash-section-sub">Paid total harian dikurangi refund POS pada order di periode aktif.</p>
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
          <h2 class="fin-dash-section-title">Status Purchase Order</h2>
          <p class="fin-dash-section-sub">Jumlah dan nilai PO pada periode aktif.</p>
        </div>
      </div>
      <div class="fin-dash-list">
        <?php if (empty($purchaseStatusRows)): ?>
          <div class="fin-dash-empty">Belum ada data purchase order.</div>
        <?php else: ?>
          <?php foreach ($purchaseStatusRows as $row): ?>
            <div class="fin-dash-list-item">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['status'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta">Nilai total <?= $formatCurrency($row['grand_total'] ?? 0) ?></div>
              </div>
              <span class="fin-dash-pill"><?= number_format((int)($row['total'] ?? 0), 0, ',', '.') ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Status Store Request</h2>
          <p class="fin-dash-section-sub">Antrean permintaan internal per status.</p>
        </div>
      </div>
      <div class="fin-dash-list">
        <?php if (empty($storeRequestStatusRows)): ?>
          <div class="fin-dash-empty">Belum ada data store request.</div>
        <?php else: ?>
          <?php foreach ($storeRequestStatusRows as $row): ?>
            <div class="fin-dash-list-item">
              <div>
                <div class="fin-dash-list-title"><?= htmlspecialchars((string)($row['status'] ?? '-')) ?></div>
                <div class="fin-dash-list-meta">Permintaan internal yang tercatat di periode aktif.</div>
              </div>
              <span class="fin-dash-pill"><?= number_format((int)($row['total'] ?? 0), 0, ',', '.') ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="fin-dash-bottom-grid">
    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Aktivitas Terbaru</h2>
          <p class="fin-dash-section-sub">Gabungan transaksi POS, purchase, dan movement inventory terbaru pada periode aktif.</p>
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

    <div class="fin-dash-card p-3">
      <div class="fin-dash-section-head">
        <div>
          <h2 class="fin-dash-section-title">Stok Kritis Detail</h2>
          <p class="fin-dash-section-sub">Item yang sudah menyentuh atau turun di bawah threshold master.</p>
        </div>
      </div>
      <div class="fin-dash-list">
        <?php if (empty($criticalStockRows)): ?>
          <div class="fin-dash-empty">Belum ada item kritis yang terdeteksi.</div>
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
        customFields.forEach(function (node) {
          node.classList.toggle('d-none', !showCustom);
        });
      });
    }

    const rupiah = function (value) {
      return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 0});
    };

    const trendEl = document.querySelector('#financeDashboardTrendChart');
    if (trendEl && window.ApexCharts) {
      const trendChart = new ApexCharts(trendEl, {
        chart: {type: 'line', height: 340, toolbar: {show: false}},
        series: [
          {name: 'Omzet Bersih', type: 'area', data: <?= json_encode(array_values($trend['sales'] ?? [])) ?>},
          {name: 'Refund', type: 'bar', data: <?= json_encode(array_values($trend['refunds'] ?? [])) ?>},
          {name: 'Transaksi', type: 'line', data: <?= json_encode(array_values($trend['orders'] ?? [])) ?>}
        ],
        colors: ['#8f2d23', '#ef6c00', '#1e88e5'],
        stroke: {curve: 'smooth', width: [3, 0, 3]},
        fill: {type: 'solid', opacity: [0.22, 0.9, 1]},
        dataLabels: {enabled: false},
        xaxis: {categories: <?= json_encode(array_values($trend['labels'] ?? [])) ?>},
        yaxis: [
          {labels: {formatter: function (val) { return rupiah(val); }}},
          {opposite: true, labels: {formatter: function (val) { return Number(val || 0).toLocaleString('id-ID'); }}}
        ],
        tooltip: {
          shared: true,
          y: [
            {formatter: function (val) { return rupiah(val); }},
            {formatter: function (val) { return rupiah(val); }},
            {formatter: function (val) { return Number(val || 0).toLocaleString('id-ID') + ' transaksi'; }}
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
