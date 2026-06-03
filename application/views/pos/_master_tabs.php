<?php
$activeTab = strtolower(trim((string)($pos_master_tab_active ?? 'payment-method')));
$links = [
    ['key' => 'sales-channel', 'label' => 'Sales Channel', 'url' => site_url('pos/sales-channels'), 'enabled' => true],
    ['key' => 'payment-method', 'label' => 'Payment Method', 'url' => site_url('pos/payment-methods'), 'enabled' => true],
    ['key' => 'deposit', 'label' => 'Deposit / DP', 'url' => site_url('pos/deposits'), 'enabled' => true],
    ['key' => 'self-order', 'label' => 'Self Order', 'url' => site_url('pos/self-order/orders'), 'enabled' => true],
    ['key' => 'stock-commit-audit', 'label' => 'Audit Commit', 'url' => site_url('pos/stock-commit-audit'), 'enabled' => true],
    ['key' => 'outlet-terminal', 'label' => 'Outlet + Terminal', 'url' => site_url('pos/outlets-terminals'), 'enabled' => true],
    ['key' => 'printer', 'label' => 'Printer', 'url' => site_url('pos/printers'), 'enabled' => true],
];
?>

<style>
  .pos-master-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .35rem;
  }
  .pos-master-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: .45rem .9rem;
    border-radius: 10px;
    font-size: .92rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #b7ab9e;
    background: #fffaf6;
    color: #6a5c54;
  }
  .pos-master-pill.is-active {
    background: #34325e;
    border-color: #34325e;
    color: #fff;
  }
  .pos-master-pill.is-disabled {
    opacity: .55;
    cursor: default;
  }
</style>

<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="pos-master-label">Master POS</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($links as $link): ?>
      <?php if (!empty($link['enabled'])): ?>
        <a href="<?php echo $link['url']; ?>" class="pos-master-pill <?php echo $activeTab === $link['key'] ? 'is-active' : ''; ?>">
          <?php echo html_escape((string)$link['label']); ?>
        </a>
      <?php else: ?>
        <span class="pos-master-pill is-disabled"><?php echo html_escape((string)$link['label']); ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>

