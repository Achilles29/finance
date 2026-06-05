<?php
$poSrActive = (string)($po_sr_active ?? '');
$poSrTabs = [
  ['key' => 'purchase-order', 'label' => 'Purchase Order', 'hint' => 'Monitoring', 'url' => site_url('purchase-orders')],
  ['key' => 'store-request', 'label' => 'Store Request', 'hint' => 'Fulfillment', 'url' => site_url('store-requests')],
  ['key' => 'division-po-sr', 'label' => 'PO / SR Divisi', 'hint' => 'Review', 'url' => site_url('procurement/division-po-sr')],
  ['key' => 'log-purchase', 'label' => 'Log Purchase', 'hint' => 'Histori', 'url' => site_url('purchase-orders/logs')],
  ['key' => 'report-purchase', 'label' => 'Laporan Purchase', 'hint' => 'Ringkasan', 'url' => site_url('purchase-orders/report')],
  ['key' => 'rebuild-impact', 'label' => 'Rebuild Impact', 'hint' => 'Audit', 'url' => site_url('purchase/rebuild-impact')],
  ['key' => 'receipt-purchase', 'label' => 'Receipt Purchase', 'hint' => 'Inbound', 'url' => site_url('purchase/receipt')],
  ['key' => 'reclassify-profile', 'label' => 'Reclassify Profile', 'hint' => 'Cleanup', 'url' => site_url('purchase/reclassify-profile-domain')],
  ['key' => 'price-history', 'label' => 'Riwayat Harga', 'hint' => 'Tren Bahan', 'url' => site_url('master/material/price-history')],
];
?>

<style>
  .po-sr-nav {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .po-sr-nav .po-sr-nav-link {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border-radius: 10px;
    font-weight: 700;
    padding: .55rem .85rem;
    border: 1px solid #dfd5cb;
    background: #fff;
    color: #51453d;
    text-decoration: none;
  }
  .po-sr-nav .po-sr-nav-link:hover {
    color: #3f342d;
    border-color: #d9c8bc;
    background: #fff8f3;
  }
  .po-sr-nav .po-sr-nav-link.is-active {
    background: #9f2141;
    border-color: #9f2141;
    color: #fff;
    box-shadow: 0 8px 18px rgba(159, 33, 65, .18);
  }
  .po-sr-nav .po-sr-dot {
    width: .58rem;
    height: .58rem;
    border-radius: .18rem;
    background: #6a5c54;
    display: inline-block;
    flex: 0 0 .58rem;
  }
  .po-sr-nav .po-sr-nav-link.is-active .po-sr-dot {
    background: #fff;
    opacity: .95;
  }
</style>

<div class="nav nav-pills gap-2 po-sr-nav">
  <?php foreach ($poSrTabs as $tab): ?>
    <a href="<?php echo $tab['url']; ?>" class="po-sr-nav-link <?php echo $poSrActive === $tab['key'] ? 'is-active' : ''; ?>">
      <span class="po-sr-dot" aria-hidden="true"></span>
      <span><?php echo html_escape($tab['label']); ?></span>
    </a>
  <?php endforeach; ?>
</div>
