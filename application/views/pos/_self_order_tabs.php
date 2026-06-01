<?php
$activeTab = strtolower(trim((string)($self_order_tab_active ?? 'settings')));
$tabs = [
    ['key' => 'settings', 'label' => 'Settings', 'url' => site_url('pos/self-order/settings')],
    ['key' => 'tables', 'label' => 'QR Meja', 'url' => site_url('pos/self-order/tables')],
];
?>

<style>
  .self-order-tab-label{min-width:110px;font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#7a6d62;padding-top:.35rem}
  .self-order-tab-pill{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:.48rem .95rem;border-radius:12px;font-size:.92rem;font-weight:700;text-decoration:none;border:1px solid #d9c7bc;background:#fffaf6;color:#6a5c54}
  .self-order-tab-pill.is-active{background:#b4233c;border-color:#b4233c;color:#fff;box-shadow:0 10px 24px rgba(180,35,60,.16)}
</style>

<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="self-order-tab-label">Self Order</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($tabs as $tab): ?>
      <a href="<?= $tab['url'] ?>" class="self-order-tab-pill <?= $activeTab === $tab['key'] ? 'is-active' : '' ?>"><?= html_escape($tab['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
