<?php
$filters = is_array($filters ?? null) ? $filters : [];
$overview = is_array($overview ?? null) ? $overview : [];
$payMethods = is_array($pay_methods ?? null) ? $pay_methods : [];
$payAccounts = is_array($pay_accounts ?? null) ? $pay_accounts : [];
$shifts = is_array($shifts ?? null) ? $shifts : [];
$byDivision = is_array($by_division ?? null) ? $by_division : [];
$outlets = is_array($outlets ?? null) ? $outlets : [];
$money = static function ($value): string { return 'Rp ' . number_format((float)$value, 0, ',', '.'); };
$printUrl = site_url('pos/reports/daily-sales/print?date=' . rawurlencode((string)($filters['date'] ?? date('Y-m-d'))) . ((int)($filters['outlet_id'] ?? 0) > 0 ? '&outlet_id=' . (int)$filters['outlet_id'] : ''));
$this->load->view('pos/_report_styles');
?>

<div class="container-xxl py-3">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="pos-report-title">Daily Sales POS</div>
          <p class="pos-report-copy mb-0">Ringkasan penjualan harian POS dengan breakdown divisi produk, metode pembayaran, rekening penerimaan, dan riwayat shift pada tanggal terpilih.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?php echo html_escape($printUrl); ?>" target="_blank" class="btn btn-outline-dark">
            <i class="ri-printer-line me-1"></i>Cetak / PDF
          </a>
          <a href="<?php echo site_url('pos/reports/payment-methods?date_from=' . rawurlencode((string)($filters['date'] ?? '')) . '&date_to=' . rawurlencode((string)($filters['date'] ?? '')) . ((int)($filters['outlet_id'] ?? 0) > 0 ? '&outlet_id=' . (int)$filters['outlet_id'] : '')); ?>" class="btn btn-outline-secondary">
            <i class="ri-bank-card-2-line me-1"></i>Metode Bayar
          </a>
          <a href="<?php echo site_url('pos/reports/payment-accounts?date_from=' . rawurlencode((string)($filters['date'] ?? '')) . '&date_to=' . rawurlencode((string)($filters['date'] ?? '')) . ((int)($filters['outlet_id'] ?? 0) > 0 ? '&outlet_id=' . (int)$filters['outlet_id'] : '')); ?>" class="btn btn-outline-secondary">
            <i class="ri-wallet-3-line me-1"></i>Rekening Bayar
          </a>
        </div>
      </div>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'daily_sales']); ?>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box row g-2 align-items-end">
        <div class="col-auto"><a href="?date=<?php echo html_escape((string)($prev_date ?? '')); ?><?php echo !empty($filters['outlet_id']) ? '&outlet_id=' . (int)$filters['outlet_id'] : ''; ?>" class="btn btn-outline-secondary">&lsaquo; Kemarin</a></div>
        <div class="col-md-2"><label class="form-label small text-muted mb-1">Tanggal</label><input type="date" name="date" class="form-control" value="<?php echo html_escape((string)($filters['date'] ?? date('Y-m-d'))); ?>"></div>
        <div class="col-md-3"><label class="form-label small text-muted mb-1">Outlet</label><select name="outlet_id" class="form-select"><option value="0">Semua Outlet</option><?php foreach ($outlets as $outlet): ?><option value="<?php echo (int)($outlet['id'] ?? 0); ?>"<?php echo (int)($filters['outlet_id'] ?? 0) === (int)($outlet['id'] ?? 0) ? ' selected' : ''; ?>><?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?></option><?php endforeach; ?></select></div>
        <div class="col-auto d-flex gap-2"><button class="btn btn-dark">Tampilkan</button><a href="?date=<?php echo html_escape((string)($next_date ?? '')); ?><?php echo !empty($filters['outlet_id']) ? '&outlet_id=' . (int)$filters['outlet_id'] : ''; ?>" class="btn btn-outline-secondary">Besok &rsaquo;</a></div>
      </form>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Gross Sales</div><div class="pos-report-card-value"><?php echo $money($overview['gross_sales'] ?? 0); ?></div><div class="pos-report-card-note">Penjualan kotor sebelum refund.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Net Sales</div><div class="pos-report-card-value"><?php echo $money($overview['net_sales'] ?? 0); ?></div><div class="pos-report-card-note">Penjualan bersih setelah refund.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Net Received</div><div class="pos-report-card-value"><?php echo $money($overview['net_received'] ?? 0); ?></div><div class="pos-report-card-note">Penerimaan bersih yang benar-benar tercatat.</div></div></div>
      <div class="col-md-3 col-sm-6"><div class="pos-report-card"><div class="pos-report-card-label">Net Daily Sales</div><div class="pos-report-card-value"><?php echo $money($net_daily_sales ?? 0); ?></div><div class="pos-report-card-note">Net sales dikurangi total purchase hari itu.</div></div></div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-6">
        <div class="pos-report-section p-3 h-100">
          <div class="fw-semibold mb-2">Revenue per Divisi Produk</div>
          <div class="table-responsive"><table class="table table-sm align-middle mb-0 pos-report-table"><thead><tr><th>Divisi</th><th class="text-end">Revenue</th></tr></thead><tbody><?php if (empty($byDivision)): ?><tr><td colspan="2" class="text-center pos-report-empty">Tidak ada data.</td></tr><?php else: foreach ($byDivision as $row): ?><tr><td><?php echo html_escape((string)($row['division_name'] ?? 'Lainnya')); ?></td><td class="text-end fw-semibold"><?php echo $money($row['revenue'] ?? 0); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="pos-report-section p-3 h-100">
          <div class="fw-semibold mb-2">Ringkasan Transaksi</div>
          <div class="table-responsive"><table class="table table-sm align-middle mb-0 pos-report-table"><tbody><tr><td>Transaksi</td><td class="text-end fw-semibold"><?php echo number_format((int)($overview['order_count'] ?? 0)); ?></td></tr><tr><td>Pending</td><td class="text-end"><?php echo number_format((int)($overview['pending_count'] ?? 0)); ?></td></tr><tr><td>Nominal Pending</td><td class="text-end text-warning"><?php echo $money($overview['pending_amount'] ?? 0); ?></td></tr><tr><td>Total Refund</td><td class="text-end text-danger"><?php echo $money($overview['refund_amount'] ?? 0); ?></td></tr><tr><td>Total Purchase</td><td class="text-end"><?php echo $money($total_purchase ?? 0); ?></td></tr><tr><td>Selisih Net Sales vs Penerimaan</td><td class="text-end <?php echo abs((float)($overview['selisih'] ?? 0)) > 0.009 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($overview['selisih'] ?? 0); ?></td></tr></tbody></table></div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-6"><div class="pos-report-section p-3 h-100"><div class="fw-semibold mb-2">Metode Pembayaran</div><div class="table-responsive"><table class="table table-sm align-middle mb-0 pos-report-table"><thead><tr><th>Metode</th><th class="text-end">Gross</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead><tbody><?php if (empty($payMethods)): ?><tr><td colspan="4" class="text-center pos-report-empty">Tidak ada data.</td></tr><?php else: foreach ($payMethods as $row): ?><tr><td><?php echo html_escape((string)($row['method_name'] ?? '-')); ?></td><td class="text-end"><?php echo $money($row['gross_amount'] ?? 0); ?></td><td class="text-end text-danger"><?php echo $money($row['refund_amount'] ?? 0); ?></td><td class="text-end fw-semibold"><?php echo $money($row['net_amount'] ?? 0); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
      <div class="col-lg-6"><div class="pos-report-section p-3 h-100"><div class="fw-semibold mb-2">Rekening Pembayaran</div><div class="table-responsive"><table class="table table-sm align-middle mb-0 pos-report-table"><thead><tr><th>Rekening</th><th class="text-end">Gross</th><th class="text-end">Refund</th><th class="text-end">Net</th></tr></thead><tbody><?php if (empty($payAccounts)): ?><tr><td colspan="4" class="text-center pos-report-empty">Tidak ada data.</td></tr><?php else: foreach ($payAccounts as $row): ?><tr><td><div class="fw-semibold"><?php echo html_escape((string)($row['account_name'] ?? '-')); ?></div><div class="pos-report-meta"><?php echo html_escape((string)($row['bank_name'] ?? '-')); ?> | <?php echo html_escape((string)($row['account_no'] ?? '-')); ?></div></td><td class="text-end"><?php echo $money($row['gross_amount'] ?? 0); ?></td><td class="text-end text-danger"><?php echo $money($row['refund_amount'] ?? 0); ?></td><td class="text-end fw-semibold"><?php echo $money($row['net_amount'] ?? 0); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
    </div>

    <div class="pos-report-section p-3">
      <div class="fw-semibold mb-2">Riwayat Shift</div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0 pos-report-table"><thead><tr><th>No Shift</th><th>Kasir</th><th>Mulai</th><th>Selesai</th><th class="text-center">Status</th><th class="text-center">Trx</th><th class="text-end">Revenue</th></tr></thead><tbody><?php if (empty($shifts)): ?><tr><td colspan="7" class="text-center pos-report-empty">Tidak ada shift pada tanggal ini.</td></tr><?php else: foreach ($shifts as $shift): ?><tr><td class="fw-semibold"><?php echo html_escape((string)($shift['shift_no'] ?? '-')); ?></td><td><?php echo html_escape((string)($shift['cashier_name'] ?? '-')); ?></td><td><?php echo !empty($shift['opened_at']) ? date('H:i', strtotime((string)$shift['opened_at'])) : '-'; ?></td><td><?php echo !empty($shift['closed_at']) ? date('H:i', strtotime((string)$shift['closed_at'])) : '-'; ?></td><td class="text-center"><?php echo html_escape((string)($shift['shift_status'] ?? '-')); ?></td><td class="text-center"><?php echo number_format((int)($shift['trx_count'] ?? 0)); ?></td><td class="text-end fw-semibold"><?php echo $money($shift['revenue'] ?? 0); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
    </div>
  </div>
</div>

