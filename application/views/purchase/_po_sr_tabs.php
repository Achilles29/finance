<?php
$poSrActive = (string)($po_sr_active ?? '');
$poSrTabs = [
  ['key' => 'purchase-order', 'label' => 'Purchase Order', 'url' => site_url('purchase-orders')],
  ['key' => 'log-purchase', 'label' => 'Log Purchase', 'url' => site_url('purchase-orders/logs')],
  ['key' => 'rebuild-impact', 'label' => 'Rebuild Impact', 'url' => site_url('purchase/rebuild-impact')],
  ['key' => 'receipt-purchase', 'label' => 'Receipt Purchase', 'url' => site_url('purchase-orders/receipt')],
  ['key' => 'division-po-sr', 'label' => 'PO / SR Divisi', 'url' => site_url('procurement/division-po-sr')],
  ['key' => 'store-request', 'label' => 'Store Request', 'url' => site_url('store-requests')],
  ['key' => 'reclassify-profile', 'label' => 'Reclassify Profile', 'url' => site_url('purchase/reclassify-profile-domain')],
];
?>

<div class="d-flex flex-wrap gap-1 align-items-center mb-3">
  <?php foreach ($poSrTabs as $tab): ?>
    <a href="<?php echo $tab['url']; ?>" class="btn btn-sm <?php echo $poSrActive === $tab['key'] ? 'btn-dark' : 'btn-outline-secondary'; ?>"><?php echo html_escape($tab['label']); ?></a>
  <?php endforeach; ?>
</div>