<?php
$filters = is_array($filters ?? null) ? $filters : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$pct = static function ($value): string { return number_format((float)$value, 1, ',', '.') . '%'; };
$date = static function ($value): string {
    $time = strtotime((string)$value);
    return $time ? date('d M Y', $time) : '-';
};
$maxValue = static function (array $rows, string $field): float {
    $max = 0.0;
    foreach ($rows as $row) { $max = max($max, (float)($row[$field] ?? 0)); }
    return $max;
};
$purchaseRows = (array)($dashboard['purchase_rows'] ?? []);
$usageRows = (array)($dashboard['usage_rows'] ?? []);
$batchRows = (array)($dashboard['batch_rows'] ?? []);
$shrinkageRows = (array)($dashboard['shrinkage_rows'] ?? []);
$expenseRows = (array)($dashboard['expense_rows'] ?? []);
$dataSources = (array)($dashboard['data_sources'] ?? []);
$netSales = (float)($summary['net_sales'] ?? 0);
$hpp = (float)($summary['hpp_final'] ?? 0);
$grossProfit = (float)($summary['gross_profit'] ?? 0);
$cashExpense = (float)($summary['cash_expense'] ?? 0);
$waterfallMax = max($netSales, $hpp, $grossProfit, $cashExpense, 1);
?>
<?php $this->load->view('pos/_report_styles'); ?>
<style>
  .cost-shell { padding: 1rem; border-radius: 30px; background: radial-gradient(circle at 4% 0%, rgba(255, 209, 137, .28), transparent 30%), radial-gradient(circle at 96% 8%, rgba(17, 96, 81, .13), transparent 26%), linear-gradient(180deg, #fffaf3 0%, #fff 48%, #fcfaf7 100%); }
  .cost-hero { position: relative; overflow: hidden; border-radius: 26px; padding: 1.35rem; color: #fff; background: linear-gradient(125deg, #351d24 0%, #7f1f31 49%, #b54532 100%); box-shadow: 0 18px 42px rgba(81, 26, 35, .22); }
  .cost-hero::after { content: ''; position: absolute; width: 330px; height: 330px; border: 1px solid rgba(255,255,255,.16); border-radius: 50%; right: -115px; top: -175px; box-shadow: 0 0 0 42px rgba(255,255,255,.04), 0 0 0 84px rgba(255,255,255,.035); }
  .cost-eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .16em; opacity: .75; }
  .cost-title { margin: .2rem 0 .35rem; font-size: clamp(1.55rem, 2.6vw, 2.2rem); line-height: 1.05; font-weight: 850; letter-spacing: -.035em; }
  .cost-copy { max-width: 650px; margin: 0; color: rgba(255,255,255,.83); }
  .cost-flow { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: .45rem; margin-top: 1rem; }
  .cost-flow span { border: 1px solid rgba(255,255,255,.24); background: rgba(255,255,255,.09); padding: .3rem .65rem; border-radius: 999px; font-size: .74rem; font-weight: 700; }
  .cost-filter { margin-top: .95rem; padding: .9rem; border: 1px solid #eadacc; background: rgba(255,255,255,.92); border-radius: 18px; }
  .cost-filter-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(170px, 1.35fr) 106px; gap: .65rem; align-items: end; }
  .cost-filter .form-label { color: #725b50; font-size: .72rem; font-weight: 800; letter-spacing: .055em; text-transform: uppercase; margin-bottom: .3rem; }
  .cost-filter .form-control, .cost-filter .form-select, .cost-filter .btn { height: 42px; }
  .cost-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .7rem; margin-top: 1rem; }
  .cost-kpi { min-height: 117px; position: relative; overflow: hidden; border-radius: 18px; padding: .85rem; background: #fff; border: 1px solid #eee0d5; box-shadow: 0 10px 28px rgba(65, 40, 24, .06); }
  .cost-kpi::before { content: ''; position: absolute; width: 50px; height: 50px; right: -17px; top: -20px; border-radius: 50%; background: var(--accent-soft); }
  .cost-kpi .label { color: #867068; font-weight: 800; text-transform: uppercase; font-size: .68rem; letter-spacing: .055em; }
  .cost-kpi .value { margin-top: .46rem; color: #38251e; font-weight: 850; letter-spacing: -.025em; font-size: clamp(1rem, 1.65vw, 1.35rem); line-height: 1.08; }
  .cost-kpi .note { margin-top: .38rem; color: #947b6f; font-size: .72rem; line-height: 1.25; }
  .cost-kpi.sales { --accent-soft: #dff5eb; }.cost-kpi.hpp { --accent-soft: #ffe9c3; }.cost-kpi.margin { --accent-soft: #dbe8ff; }.cost-kpi.production { --accent-soft: #e8def8; }.cost-kpi.loss { --accent-soft: #fde0df; }.cost-kpi.cash { --accent-soft: #e5e7eb; }
  .cost-section { margin-top: 1rem; border: 1px solid #eaded5; border-radius: 22px; background: rgba(255,255,255,.91); box-shadow: 0 12px 32px rgba(65, 40, 24, .055); overflow: hidden; }
  .cost-section-head { display: flex; gap: 1rem; justify-content: space-between; align-items: flex-start; padding: 1rem 1.05rem .75rem; border-bottom: 1px solid #f0e6df; }
  .cost-section-title { margin: 0; color: #39261e; font-size: 1rem; font-weight: 850; }.cost-section-sub { margin: .22rem 0 0; color: #8c7569; font-size: .78rem; }
  .cost-waterfall { padding: 1rem 1.05rem 1.1rem; display: grid; gap: .75rem; }
  .cost-water-row { display: grid; grid-template-columns: 134px minmax(80px, 1fr) 135px; gap: .75rem; align-items: center; }.cost-water-label { color: #6e584c; font-weight: 750; font-size: .82rem; }.cost-water-money { color: #432e25; text-align: right; font-size: .82rem; font-weight: 850; }
  .cost-water-track { height: 13px; overflow: hidden; border-radius: 99px; background: #f2ece7; }.cost-water-fill { height: 100%; min-width: 4px; border-radius: inherit; background: var(--fill); }.cost-water-row.sales { --fill: #258562; }.cost-water-row.hpp { --fill: #d28a2e; }.cost-water-row.margin { --fill: #2f6cae; }.cost-water-row.cash { --fill: #64748b; }
  .cost-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 1rem; margin-top: 1rem; }.cost-grid .cost-section { margin-top: 0; }.cost-list { padding: .35rem 1.05rem .8rem; }.cost-list-row { padding: .7rem 0; border-bottom: 1px solid #f1e8e2; }.cost-list-row:last-child { border-bottom: 0; }.cost-list-main { display: flex; align-items: baseline; justify-content: space-between; gap: .7rem; color: #463026; font-size: .85rem; font-weight: 800; }.cost-list-main span:last-child { white-space: nowrap; }.cost-list-meta { display: flex; justify-content: space-between; gap: .7rem; margin-top: .25rem; color: #90796e; font-size: .75rem; }.cost-list-bar { height: 5px; margin-top: .45rem; border-radius: 99px; background: #f4eee9; overflow: hidden; }.cost-list-bar span { display: block; height: 100%; background: #b12b36; border-radius: inherit; }
  .cost-table-wrap { overflow-x: auto; }.cost-table { margin: 0; min-width: 580px; }.cost-table th { background: #fff9f5; color: #7b6357; font-size: .69rem; text-transform: uppercase; letter-spacing: .055em; white-space: nowrap; }.cost-table td { color: #513a2e; font-size: .82rem; vertical-align: middle; }.cost-table td strong { font-weight: 850; }.cost-empty { padding: 1.4rem 1rem; color: #927c70; font-size: .84rem; text-align: center; }.cost-source-note { margin-top: 1rem; border-left: 4px solid #b68745; padding: .85rem 1rem; background: #fff9ed; color: #725b3d; border-radius: 0 14px 14px 0; font-size: .79rem; line-height: 1.45; }.cost-source-note strong { color: #5e4629; }
  @media (max-width: 1300px) { .cost-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }.cost-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(150px, 1.25fr) 106px; }
  @media (max-width: 900px) { .cost-filter-grid, .cost-grid { grid-template-columns: 1fr 1fr; }.cost-filter-grid .cost-filter-action { grid-column: span 2; }.cost-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 560px) { .cost-shell { padding: .6rem; }.cost-hero { padding: 1.05rem; }.cost-filter-grid, .cost-grid { grid-template-columns: 1fr; }.cost-filter-grid .cost-filter-action { grid-column: auto; }.cost-kpis { grid-template-columns: 1fr 1fr; gap: .5rem; }.cost-kpi { min-height: 106px; padding: .7rem; }.cost-water-row { grid-template-columns: 1fr 80px; }.cost-water-track { grid-column: span 2; order: 3; }.cost-water-money { text-align: right; }.cost-section-head { padding: .85rem; }.cost-waterfall, .cost-list { padding-left: .85rem; padding-right: .85rem; } }
</style>

<div class="cost-shell">
  <section class="cost-hero">
    <div class="cost-eyebrow">OPERATIONS INTELLIGENCE</div>
    <h1 class="cost-title">Cost Control</h1>
    <p class="cost-copy">Satu layar untuk membaca perjalanan biaya: bahan dibeli, dipakai produksi, menjadi HPP, hilang sebagai waste, hingga uang yang benar-benar keluar.</p>
    <div class="cost-flow"><span>Belanja Bahan</span><span>Produksi Batch</span><span>HPP Penjualan</span><span>Margin</span><span>Kas Operasi</span></div>
  </section>

  <?php $this->load->view('pos/_report_nav'); ?>

  <form method="get" class="cost-filter" action="<?php echo site_url('pos/reports/cost-control'); ?>">
    <div class="cost-filter-grid">
      <div><label class="form-label" for="cost-date-from">Dari tanggal</label><input id="cost-date-from" class="form-control" type="date" name="date_from" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>"></div>
      <div><label class="form-label" for="cost-date-to">Sampai tanggal</label><input id="cost-date-to" class="form-control" type="date" name="date_to" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>"></div>
      <div><label class="form-label" for="cost-location">Lokasi operasi</label><select id="cost-location" class="form-select" name="location_type"><option value="ALL">Semua lokasi</option><option value="REGULAR" <?php echo ($filters['location_type'] ?? '') === 'REGULAR' ? 'selected' : ''; ?>>Reguler</option><option value="EVENT" <?php echo ($filters['location_type'] ?? '') === 'EVENT' ? 'selected' : ''; ?>>Event</option></select></div>
      <div><label class="form-label" for="cost-division">Divisi</label><select id="cost-division" class="form-select" name="division_id"><option value="0">Semua divisi</option><?php foreach ((array)($divisions ?? []) as $division): ?><option value="<?php echo (int)($division['id'] ?? 0); ?>" <?php echo (int)($filters['division_id'] ?? 0) === (int)($division['id'] ?? 0) ? 'selected' : ''; ?>><?php echo html_escape((string)($division['division_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
      <div class="cost-filter-action"><button type="submit" class="btn btn-primary w-100"><i class="ri-filter-3-line me-1"></i>Terapkan</button></div>
    </div>
  </form>

  <section class="cost-kpis">
    <article class="cost-kpi sales"><div class="label">Penjualan Bersih</div><div class="value"><?php echo $money($netSales); ?></div><div class="note">Setelah diskon dan refund</div></article>
    <article class="cost-kpi hpp"><div class="label">HPP Final</div><div class="value"><?php echo $money($hpp); ?></div><div class="note">HPP jual, refund, dan koreksi</div></article>
    <article class="cost-kpi margin"><div class="label">Laba Kotor</div><div class="value <?php echo $grossProfit < 0 ? 'text-danger' : ''; ?>"><?php echo $money($grossProfit); ?></div><div class="note">Margin <?php echo $pct($summary['gross_margin_pct'] ?? 0); ?></div></article>
    <article class="cost-kpi production"><div class="label">Biaya Produksi</div><div class="value"><?php echo $money($summary['production_cost'] ?? 0); ?></div><div class="note"><?php echo number_format((int)($summary['production_batch_count'] ?? 0)); ?> batch sudah posted</div></article>
    <article class="cost-kpi loss"><div class="label">Waste &amp; Susut</div><div class="value text-danger"><?php echo $money($summary['waste_total'] ?? 0); ?></div><div class="note"><?php echo $pct($summary['waste_ratio_pct'] ?? 0); ?> dari penjualan bersih</div></article>
    <article class="cost-kpi cash"><div class="label">Kas Operasional</div><div class="value"><?php echo $money($cashExpense); ?></div><div class="note">Sisa kas vs laba kotor: <?php echo $money($summary['cash_after_gross_profit'] ?? 0); ?></div></article>
  </section>

  <section class="cost-section">
    <div class="cost-section-head"><div><h2 class="cost-section-title">Jembatan Margin</h2><p class="cost-section-sub">Perbandingan nominal utama dalam periode terpilih. Ini bukan penjumlahan biaya, melainkan posisi setiap aliran biaya.</p></div><span class="pos-report-badge info">Periode <?php echo html_escape($date($filters['date_from'] ?? '') . ' - ' . $date($filters['date_to'] ?? '')); ?></span></div>
    <div class="cost-waterfall">
      <?php foreach ([['sales','Penjualan bersih',$netSales], ['hpp','HPP final',$hpp], ['margin','Laba kotor',$grossProfit], ['cash','Kas operasional',$cashExpense]] as $water): ?>
        <div class="cost-water-row <?php echo $water[0]; ?>"><div class="cost-water-label"><?php echo html_escape($water[1]); ?></div><div class="cost-water-track"><div class="cost-water-fill" style="width: <?php echo min(100, max(0, ((float)$water[2] / $waterfallMax * 100))); ?>%"></div></div><div class="cost-water-money"><?php echo $money($water[2]); ?></div></div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="cost-grid">
    <section class="cost-section"><div class="cost-section-head"><div><h2 class="cost-section-title">Bahan Baku: dibeli vs dipakai</h2><p class="cost-section-sub">Pembelian mengikuti receipt posted. Pemakaian mengikuti input batch produksi posted.</p></div><span class="pos-report-badge warning"><?php echo $money($summary['material_purchase_value'] ?? 0); ?> dibeli</span></div>
      <div class="row g-0"><div class="col-md-6 border-end"><div class="px-3 pt-3"><strong class="small text-uppercase text-muted">Top pembelian bahan</strong></div><div class="cost-list"><?php $max = $maxValue($purchaseRows, 'purchase_value'); foreach ($purchaseRows as $row): ?><div class="cost-list-row"><div class="cost-list-main"><span><?php echo html_escape((string)($row['material_name'] ?? '-')); ?></span><span><?php echo $money($row['purchase_value'] ?? 0); ?></span></div><div class="cost-list-meta"><span><?php echo number_format((int)($row['receipt_count'] ?? 0)); ?> receipt</span><span>Nilai masuk</span></div><div class="cost-list-bar"><span style="width: <?php echo $max > 0 ? min(100, (float)($row['purchase_value'] ?? 0) / $max * 100) : 0; ?>%"></span></div></div><?php endforeach; if (empty($purchaseRows)): ?><div class="cost-empty">Belum ada penerimaan bahan baku posted pada periode ini.</div><?php endif; ?></div></div>
      <div class="col-md-6"><div class="px-3 pt-3"><strong class="small text-uppercase text-muted">Top pemakaian produksi</strong></div><div class="cost-list"><?php $max = $maxValue($usageRows, 'usage_value'); foreach ($usageRows as $row): ?><div class="cost-list-row"><div class="cost-list-main"><span><?php echo html_escape((string)($row['material_name'] ?? '-')); ?></span><span><?php echo $money($row['usage_value'] ?? 0); ?></span></div><div class="cost-list-meta"><span><?php echo number_format((int)($row['batch_count'] ?? 0)); ?> batch</span><span>Masuk produksi</span></div><div class="cost-list-bar"><span style="width: <?php echo $max > 0 ? min(100, (float)($row['usage_value'] ?? 0) / $max * 100) : 0; ?>%"></span></div></div><?php endforeach; if (empty($usageRows)): ?><div class="cost-empty">Belum ada penggunaan bahan dari batch posted.</div><?php endif; ?></div></div></div>
    </section>
    <section class="cost-section"><div class="cost-section-head"><div><h2 class="cost-section-title">Waste, spoil &amp; selisih</h2><p class="cost-section-sub">Nilai berdasarkan cost pada penyesuaian stok posted.</p></div><span class="pos-report-badge danger"><?php echo $money($summary['waste_total'] ?? 0); ?></span></div><div class="cost-list"><?php $max = $maxValue($shrinkageRows, 'shrinkage_value'); foreach ($shrinkageRows as $row): ?><div class="cost-list-row"><div class="cost-list-main"><span><?php echo html_escape((string)($row['material_name'] ?? '-')); ?></span><span class="text-danger"><?php echo $money($row['shrinkage_value'] ?? 0); ?></span></div><div class="cost-list-meta"><span>Waste, spoil, process loss, dan variance</span></div><div class="cost-list-bar"><span style="width: <?php echo $max > 0 ? min(100, (float)($row['shrinkage_value'] ?? 0) / $max * 100) : 0; ?>%"></span></div></div><?php endforeach; if (empty($shrinkageRows)): ?><div class="cost-empty">Tidak ada shrinkage posted pada periode ini.</div><?php endif; ?></div></section>
  </div>

  <div class="cost-grid">
    <section class="cost-section"><div class="cost-section-head"><div><h2 class="cost-section-title">Batch produksi terbaru</h2><p class="cost-section-sub">Hanya batch yang sudah posted agar nilai input sudah final.</p></div><span class="pos-report-badge success"><?php echo number_format((int)($summary['production_batch_count'] ?? 0)); ?> batch</span></div><div class="cost-table-wrap"><table class="table table-sm cost-table"><thead><tr><th>Batch</th><th>Produk antara</th><th>Lokasi</th><th class="text-end">Biaya input</th></tr></thead><tbody><?php foreach ($batchRows as $row): ?><tr><td><strong><?php echo html_escape((string)($row['batch_no'] ?? '-')); ?></strong><div class="pos-report-meta"><?php echo html_escape($date($row['batch_date'] ?? '')); ?></div></td><td><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></td><td><?php echo html_escape((string)(($row['division_name'] ?? '') !== '' ? $row['division_name'] : ($row['location_type'] ?? '-'))); ?></td><td class="text-end"><strong><?php echo $money($row['total_input_cost'] ?? 0); ?></strong></td></tr><?php endforeach; if (empty($batchRows)): ?><tr><td colspan="4" class="cost-empty">Belum ada batch produksi posted pada periode ini.</td></tr><?php endif; ?></tbody></table></div></section>
    <section class="cost-section"><div class="cost-section-head"><div><h2 class="cost-section-title">Pengeluaran kas per sumber</h2><p class="cost-section-sub">Kas keluar efektif di luar transfer internal dan refund POS.</p></div><span class="pos-report-badge secondary"><?php echo $money($cashExpense); ?></span></div><div class="cost-table-wrap"><table class="table table-sm cost-table"><thead><tr><th>Sumber</th><th>Transaksi</th><th class="text-end">Kas keluar</th></tr></thead><tbody><?php foreach ($expenseRows as $row): ?><tr><td><strong><?php echo html_escape(str_replace('_', ' ', (string)($row['source_name'] ?? '-'))); ?></strong></td><td><?php echo number_format((int)($row['transaction_count'] ?? 0)); ?></td><td class="text-end"><strong><?php echo $money($row['expense_value'] ?? 0); ?></strong></td></tr><?php endforeach; if (empty($expenseRows)): ?><tr><td colspan="3" class="cost-empty">Belum ada pengeluaran kas yang tercatat pada periode ini.</td></tr><?php endif; ?></tbody></table></div></section>
  </div>

  <div class="cost-source-note"><strong>Cara membaca laporan:</strong> pembelian bahan adalah nilai stok yang masuk, pemakaian produksi adalah nilai bahan yang dikonsumsi batch, sedangkan HPP adalah biaya yang benar-benar melekat pada barang yang terjual. Karena waktunya bisa berbeda, ketiganya sengaja ditampilkan berdampingan dan tidak dijumlahkan. Pengeluaran kas dipisahkan untuk membantu membaca tekanan likuiditas, bukan sebagai laba-rugi akrual. Filter divisi/lokasi berlaku pada pembelian, produksi, dan shrinkage; kas ditampilkan pada tingkat perusahaan karena mutation log belum membawa dimensi divisi.</div>
</div>
