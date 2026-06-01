<?php
$reportNavActive = trim((string)($report_nav_active ?? ''));
if ($reportNavActive === '') {
    $activeMenuValue = trim((string)($active_menu ?? ''));
    $map = [
        'pos.report.daily_sales' => 'daily_sales',
        'pos.report.sales' => 'sales',
        'pos.report.sales.detail' => 'sales_detail',
        'pos.report.payment' => 'payment',
        'pos.report.payment.method' => 'payment_methods',
        'pos.report.payment.account' => 'payment_accounts',
        'pos.report.cashier.close' => 'cashier_close',
        'pos.report.refund' => 'refund',
        'pos.report.void' => 'void',
    ];
    $reportNavActive = $map[$activeMenuValue] ?? '';
}

$navItems = [
    'daily_sales' => ['label' => 'Daily Sales', 'url' => 'pos/reports/daily-sales', 'icon' => 'ri-calendar-check-line'],
    'sales' => ['label' => 'Penjualan', 'url' => 'pos/reports/sales', 'icon' => 'ri-receipt-line'],
    'sales_detail' => ['label' => 'Penjualan Produk', 'url' => 'pos/reports/sales-detail', 'icon' => 'ri-file-list-3-line'],
    'payment' => ['label' => 'Pembayaran', 'url' => 'pos/reports/payments', 'icon' => 'ri-bank-card-line'],
    'payment_methods' => ['label' => 'Metode Bayar', 'url' => 'pos/reports/payment-methods', 'icon' => 'ri-bank-card-2-line'],
    'payment_accounts' => ['label' => 'Rekening Bayar', 'url' => 'pos/reports/payment-accounts', 'icon' => 'ri-wallet-3-line'],
    'cashier_close' => ['label' => 'Tutup Kasir', 'url' => 'pos/reports/cashier-close', 'icon' => 'ri-safe-2-line'],
    'refund' => ['label' => 'Refund', 'url' => 'pos/reports/refunds', 'icon' => 'ri-arrow-go-back-line'],
    'void' => ['label' => 'Void', 'url' => 'pos/reports/voids', 'icon' => 'ri-close-circle-line'],
];
?>
<div class="pos-report-nav mb-3">
  <?php foreach ($navItems as $navKey => $navItem): ?>
    <a href="<?php echo site_url($navItem['url']); ?>" class="btn btn-sm btn-outline-dark<?php echo $reportNavActive === $navKey ? ' active' : ''; ?>">
      <i class="<?php echo html_escape((string)($navItem['icon'] ?? 'ri-circle-line')); ?> me-1"></i>
      <?php echo html_escape($navItem['label']); ?>
    </a>
  <?php endforeach; ?>
</div>