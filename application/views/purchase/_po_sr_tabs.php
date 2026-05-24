<?php
$poSrActive = (string)($po_sr_active ?? '');
$poSrTabs = [
  ['key' => 'purchase-order', 'label' => 'Purchase Order', 'hint' => 'Monitoring', 'icon' => 'ri-shopping-bag-3-line', 'url' => site_url('purchase-orders')],
  ['key' => 'log-purchase', 'label' => 'Log Purchase', 'hint' => 'Histori', 'icon' => 'ri-history-line', 'url' => site_url('purchase-orders/logs')],
  ['key' => 'rebuild-impact', 'label' => 'Rebuild Impact', 'hint' => 'Audit', 'icon' => 'ri-radar-line', 'url' => site_url('purchase/rebuild-impact')],
  ['key' => 'receipt-purchase', 'label' => 'Receipt Purchase', 'hint' => 'Inbound', 'icon' => 'ri-inbox-unarchive-line', 'url' => site_url('purchase-orders/receipt')],
  ['key' => 'division-po-sr', 'label' => 'PO / SR Divisi', 'hint' => 'Review', 'icon' => 'ri-git-merge-line', 'url' => site_url('procurement/division-po-sr')],
  ['key' => 'store-request', 'label' => 'Store Request', 'hint' => 'Fulfillment', 'icon' => 'ri-inbox-archive-line', 'url' => site_url('store-requests')],
  ['key' => 'reclassify-profile', 'label' => 'Reclassify Profile', 'hint' => 'Cleanup', 'icon' => 'ri-shape-line', 'url' => site_url('purchase/reclassify-profile-domain')],
];
?>

<style>
  .po-sr-nav {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-bottom: 1rem;
  }
  .po-sr-nav .btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border-radius: 12px;
    font-weight: 700;
    padding: .6rem .85rem;
    border-width: 1px;
  }
  .po-sr-nav .btn-outline-secondary {
    color: #51453d;
    border-color: #dfd5cb;
    background: #fff;
  }
  .po-sr-nav .btn-outline-secondary:hover {
    color: #3f342d;
    border-color: #cdb8ab;
    background: #fff8f3;
  }
  .po-sr-nav .btn-primary {
    background: #9f2141;
    border-color: #9f2141;
    box-shadow: 0 8px 18px rgba(159, 33, 65, .18);
  }
</style>

<div class="nav nav-pills gap-2 po-sr-nav">
  <?php foreach ($poSrTabs as $tab): ?>
    <a href="<?php echo $tab['url']; ?>" class="btn <?php echo $poSrActive === $tab['key'] ? 'btn-primary' : 'btn-outline-secondary'; ?>">
      <i class="ri <?php echo html_escape((string)$tab['icon']); ?>"></i>
      <span><?php echo html_escape($tab['label']); ?></span>
    </a>
  <?php endforeach; ?>
</div>