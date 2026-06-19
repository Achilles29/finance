<?php
$activeTab = strtolower(trim((string)($promo_tab_active ?? 'member')));
$links = [
    ['key' => 'member', 'label' => 'Member', 'url' => site_url('loyalty/members')],
    ['key' => 'point-rule', 'label' => 'Poin', 'url' => site_url('loyalty/point-rules')],
    ['key' => 'stamp-campaign', 'label' => 'Stamp', 'url' => site_url('loyalty/stamp-campaigns')],
    ['key' => 'voucher-issue', 'label' => 'Voucher', 'url' => site_url('loyalty/vouchers')],
    ['key' => 'voucher-campaign', 'label' => 'Promo Voucher', 'url' => site_url('loyalty/voucher-campaigns')],
    ['key' => 'redeem', 'label' => 'Redeem', 'url' => site_url('loyalty/redeem')],
];
?>
<style>
  .loyalty-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
    padding-top: .35rem;
  }
  .loyalty-pill {
    display:inline-flex; align-items:center; justify-content:center; min-height:38px; padding:.45rem .9rem;
    border-radius:10px; font-size:.92rem; font-weight:600; text-decoration:none; border:1px solid #cbb8aa;
    background:#fffaf6; color:#6a5c54;
  }
  .loyalty-pill.is-active {
    background:#7f2f33; border-color:#7f2f33; color:#fff;
  }
</style>
<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
  <div class="loyalty-label">Loyalty</div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($links as $link): ?>
      <a href="<?php echo $link['url']; ?>" class="loyalty-pill <?php echo $activeTab === $link['key'] ? 'is-active' : ''; ?>">
        <?php echo html_escape((string)$link['label']); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
