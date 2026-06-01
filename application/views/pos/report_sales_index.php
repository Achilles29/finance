<?php
$money = static function ($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$paymentMethods = is_array($payment_methods ?? null) ? $payment_methods : [];
$overview = is_array($overview ?? null) ? $overview : [];
$totalDiscount = (float)($overview['discount_amount'] ?? 0)
  + (float)($overview['promo_amount'] ?? 0)
  + (float)($overview['voucher_amount'] ?? 0)
  + (float)($overview['point_redeem_amount'] ?? 0)
  + (float)($overview['compliment_amount'] ?? 0);
$depositAppliedAmount = (float)($overview['deposit_applied_amount'] ?? 0);
$finalPaymentAmount = (float)($overview['paid_total'] ?? 0);
$settlementAmount = $finalPaymentAmount + $depositAppliedAmount;
$qty = static function ($value, int $decimals = 0): string {
    return number_format((float)$value, $decimals, ',', '.');
};
$dt = static function ($value): string {
    if (empty($value)) {
        return '-';
    }

    $time = strtotime((string)$value);
    return $time ? date('d M Y H:i', $time) : (string)$value;
};
$statusBadge = static function (string $status, ?string $label = null): string {
  $status = strtoupper(trim($status));
    $class = 'secondary';
    if (in_array($status, ['PAID', 'READY', 'SERVED'], true)) {
        $class = 'success';
    } elseif (in_array($status, ['PAID_PARTIAL', 'CONFIRMED', 'IN_KITCHEN'], true)) {
        $class = 'warning';
    } elseif (in_array($status, ['REFUND_PARTIAL', 'REFUND_FULL'], true)) {
        $class = 'info';
    }

    return '<span class="pos-report-badge ' . $class . '">' . html_escape($label !== null && $label !== '' ? $label : ($status !== '' ? $status : '-')) . '</span>';
};
$statusLabel = static function ($status): string {
  $value = strtoupper(trim((string)$status));
  $map = [
    'PAID' => 'Lunas',
    'READY' => 'Siap saji',
    'SERVED' => 'Sudah disajikan',
    'PAID_PARTIAL' => 'Bayar sebagian',
    'CONFIRMED' => 'Terkonfirmasi',
    'IN_KITCHEN' => 'Sedang diproses',
    'REFUND_PARTIAL' => 'Refund sebagian',
    'REFUND_FULL' => 'Refund penuh',
  ];

  return $map[$value] ?? ($value !== '' ? str_replace('_', ' ', $value) : '-');
};
?>
<?php $this->load->view('pos/_report_styles'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="pos-report-shell">
    <div class="pos-report-hero mb-3">
      <div class="pos-report-title d-flex align-items-center gap-2"><i class="ri-receipt-line"></i><span>Laporan Penjualan POS</span></div>
    </div>

    <?php $this->load->view('pos/_report_nav', ['report_nav_active' => 'sales']); ?>
    <?php $this->load->view('pos/_report_filter_summary', ['filters' => $filters, 'outlets' => $outlets]); ?>

    <div class="row g-3 mb-3">
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Subtotal Item</div>
          <div class="pos-report-card-value"><?php echo $money($overview['gross_sales'] ?? 0); ?></div>
          <div class="pos-report-card-note">Akumulasi subtotal item sebelum pajak dan service.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Transaksi</div>
          <div class="pos-report-card-value"><?php echo number_format((int)($overview['order_count'] ?? 0)); ?></div>
          <div class="pos-report-card-note">Jumlah order pada filter aktif.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Pembayaran Final</div>
          <div class="pos-report-card-value text-success"><?php echo $money($finalPaymentAmount); ?></div>
          <div class="pos-report-card-note">Dana masuk saat checkout final pada order.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">DP Dipakai</div>
          <div class="pos-report-card-value text-info"><?php echo $money($depositAppliedAmount); ?></div>
          <div class="pos-report-card-note">Saldo DP customer yang dipakai ke order.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Terbayar</div>
          <div class="pos-report-card-value text-success"><?php echo $money($settlementAmount); ?></div>
          <div class="pos-report-card-note">Nilai yang tercatat sudah dibayar, refund tetap ditampilkan terpisah.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Diskon</div>
          <div class="pos-report-card-value text-danger"><?php echo $money($totalDiscount); ?></div>
          <div class="pos-report-card-note">Diskon, promo, voucher, poin, dan compliment.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Refund</div>
          <div class="pos-report-card-value text-warning"><?php echo $money($overview['refund_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Nilai refund dari order terbayar.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Void</div>
          <div class="pos-report-card-value text-danger"><?php echo $money($overview['void_amount'] ?? 0); ?></div>
          <div class="pos-report-card-note">Nilai pembatalan order yang masuk void.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Total Piutang</div>
          <div class="pos-report-card-value"><?php echo $money($overview['balance_due'] ?? 0); ?></div>
          <div class="pos-report-card-note">Sisa tagihan order yang belum lunas.</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="pos-report-card">
          <div class="pos-report-card-label">Grand Total Order</div>
          <div class="pos-report-card-value text-primary"><?php echo $money($overview['grand_total'] ?? 0); ?></div>
          <div class="pos-report-card-note">Tagihan final order pada filter aktif sebelum melihat refund terpisah.</div>
        </div>
      </div>
    </div>

    <div class="pos-report-section p-3 mb-3">
      <form method="get" class="pos-report-filter-box row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1">Cari</label>
          <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Order / customer / outlet / meja">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Outlet</label>
          <select name="outlet_id" class="form-select">
            <option value="0">Semua outlet</option>
            <?php foreach ((array)$outlets as $outlet): ?>
              <?php $outletId = (int)($outlet['id'] ?? 0); ?>
              <option value="<?php echo $outletId; ?>"<?php echo $outletId === (int)($filters['outlet_id'] ?? 0) ? ' selected' : ''; ?>>
                <?php echo html_escape((string)($outlet['outlet_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Status</label>
          <select name="status" class="form-select">
            <?php $statusOptions = ['ALL' => 'Semua', 'CONFIRMED' => 'Confirmed', 'PAID_PARTIAL' => 'Paid Partial', 'PAID' => 'Paid', 'IN_KITCHEN' => 'In Kitchen', 'READY' => 'Ready', 'SERVED' => 'Served', 'REFUND_PARTIAL' => 'Refund Partial', 'REFUND_FULL' => 'Refund Full']; ?>
            <?php foreach ($statusOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['status'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Scope</label>
          <select name="order_scope" class="form-select">
            <?php $scopeOptions = ['ALL' => 'Semua', 'REGULAR' => 'Reguler', 'EVENT' => 'Event']; ?>
            <?php foreach ($scopeOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['order_scope'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1">Service</label>
          <select name="service_type" class="form-select">
            <?php $serviceOptions = ['ALL' => 'Semua', 'DINE_IN' => 'Dine In', 'TAKE_AWAY' => 'Take Away', 'DELIVERY' => 'Delivery', 'PICKUP' => 'Pickup']; ?>
            <?php foreach ($serviceOptions as $value => $label): ?>
              <option value="<?php echo $value; ?>"<?php echo $value === (string)($filters['service_type'] ?? 'ALL') ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label small text-muted mb-1">Metode Pembayaran</label>
          <select name="payment_method_id" class="form-select">
            <option value="0">Semua metode</option>
            <?php foreach ($paymentMethods as $method): ?>
              <?php $methodId = (int)($method['id'] ?? 0); ?>
              <option value="<?php echo $methodId; ?>"<?php echo $methodId === (int)($filters['payment_method_id'] ?? 0) ? ' selected' : ''; ?>>
                <?php echo html_escape((string)($method['method_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Dari</label>
          <input type="date" name="date_from" class="form-control" value="<?php echo html_escape((string)($filters['date_from'] ?? '')); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Sampai</label>
          <input type="date" name="date_to" class="form-control" value="<?php echo html_escape((string)($filters['date_to'] ?? '')); ?>">
        </div>
        <div class="col-lg-2 col-md-6">
          <label class="form-label small text-muted mb-1">Baris</label>
          <select name="limit" class="form-select">
            <?php foreach ([25, 50, 100, 200] as $limit): ?>
              <option value="<?php echo $limit; ?>"<?php echo $limit === (int)($filters['limit'] ?? 25) ? ' selected' : ''; ?>><?php echo $limit; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-4 col-md-12">
          <div class="d-flex gap-2 justify-content-lg-end">
            <a href="<?php echo site_url('pos/reports/sales'); ?>" class="btn btn-outline-secondary w-100">Reset</a>
            <button type="submit" class="btn btn-dark w-100">Terapkan</button>
          </div>
        </div>
      </form>
    </div>

    <div class="pos-report-section p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h5 class="mb-1">Daftar Penjualan</h5>
          <div class="pos-report-meta">Susunan tabel dibuat ringkas: identitas order, waktu, tipe layanan, nilai terbayar, metode bayar, dan akses detail.</div>
        </div>
      </div>

      <div class="pos-report-table-wrap">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 pos-report-table">
            <thead>
              <tr>
                <th>Order</th>
                <th>Ordered At</th>
                <th>Paid At</th>
                <th>Tipe</th>
                <th>Pembayaran</th>
                <th class="text-end">Terbayar</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="7" class="text-center pos-report-empty">Tidak ada data penjualan untuk filter yang dipilih.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $scope = strtoupper((string)($row['order_scope'] ?? ''));
                  $service = strtoupper((string)($row['service_type'] ?? ''));
                    $customerName = trim((string)($row['customer_display_name'] ?? ''));
                  $metaLine = [];
                  if (!empty($row['outlet_name'])) {
                      $metaLine[] = $row['outlet_name'];
                  }
                    if ($customerName !== '') {
                      $metaLine[] = $customerName;
                  }
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?php echo html_escape((string)($row['order_no'] ?? '-')); ?></div>
                      <div class="pos-report-meta"><?php echo html_escape(implode(' | ', $metaLine)); ?></div>
                    </td>
                    <td><?php echo html_escape($dt($row['ordered_at'] ?? null)); ?></td>
                    <td><?php echo html_escape($dt($row['paid_at'] ?? null)); ?></td>
                    <td>
                      <div class="pos-report-inline-list">
                        <div><?php echo $statusBadge((string)($row['status'] ?? ''), $statusLabel((string)($row['status'] ?? ''))); ?></div>
                        <div class="muted"><?php echo html_escape(($scope !== '' ? $scope : '-') . ' / ' . ($service !== '' ? $service : '-')); ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="pos-report-inline-list">
                        <div><?php echo html_escape((string)($row['payment_method_names'] ?? '-')); ?></div>
                        <div class="muted">Refund <?php echo $money($row['refund_amount'] ?? 0); ?> · Void <?php echo $money($row['void_amount'] ?? 0); ?></div>
                      </div>
                    </td>
                    <td class="text-end">
                      <div class="fw-semibold"><?php echo $money($row['net_sales'] ?? 0); ?></div>
                      <div class="pos-report-meta">Tagihan <?php echo $money($row['grand_total'] ?? 0); ?> · Refund <?php echo $money($row['refund_amount'] ?? 0); ?></div>
                    </td>
                    <td>
                      <div class="pos-report-actions">
                        <a href="<?php echo site_url('pos/reports/sales-detail/' . (int)($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php $this->load->view('pos/_report_pager', ['meta' => $meta, 'filters' => $filters, 'pager_path' => 'pos/reports/sales']); ?>
    </div>
  </div>
</div>
