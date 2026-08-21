<?php
$active = (string)($asset_nav_active ?? '');
$items = [
  'items' => ['label' => 'Daftar Aset', 'url' => site_url('asset-management'), 'icon' => 'ri-list-check-2'],
  'damage' => ['label' => 'Lapor Rusak', 'url' => site_url('asset-management/damage'), 'icon' => 'ri-alert-line'],
  'recon' => ['label' => 'Rekon Bulanan', 'url' => site_url('asset-management/recon'), 'icon' => 'ri-calendar-check-line'],
  'labels' => ['label' => 'QR Label', 'url' => site_url('asset-management/labels'), 'icon' => 'ri-fingerprint-line'],
  'transfer' => ['label' => 'Mutasi', 'url' => site_url('asset-management/transfer'), 'icon' => 'ri-arrow-left-right-line'],
  'handover' => ['label' => 'Serah Terima', 'url' => site_url('asset-management/handover'), 'icon' => 'ri-user-follow-line'],
  'maintenance' => ['label' => 'Maintenance', 'url' => site_url('asset-management/maintenance'), 'icon' => 'ri-tools-line'],
  'disposal' => ['label' => 'Disposal', 'url' => site_url('asset-management/disposal'), 'icon' => 'ri-delete-bin-line'],
  'depreciation' => ['label' => 'Penyusutan', 'url' => site_url('asset-management/depreciation'), 'icon' => 'ri-line-chart-line'],
];
?>
<style>
.asset-module-nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:.5rem;margin-bottom:1rem}
.asset-module-nav-link{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:40px;padding:.58rem .72rem;border:1px solid #d8c9bd;border-radius:8px;background:#fff;color:#5a4a40;font-weight:700;font-size:.86rem;line-height:1.15;text-align:center;text-decoration:none;box-shadow:0 6px 18px rgba(35,24,18,.035)}
.asset-module-nav-link:hover{border-color:#18745c;color:#18745c;background:#f7fbf9;text-decoration:none}
.asset-module-nav-link.is-active{border-color:#18745c;background:#18745c;color:#fff;box-shadow:0 10px 22px rgba(24,116,92,.18)}
.asset-module-nav-link i{font-size:1.05rem;line-height:1;display:inline-flex;align-items:center;justify-content:center}
.asset-section-tabs{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.5rem}
.asset-section-tabs .nav-item{width:100%}
.asset-section-tabs .nav-link{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:40px;border:1px solid #d8c9bd;border-radius:8px;background:#fff;color:#5a4a40;font-weight:700;font-size:.88rem;text-align:center;box-shadow:0 6px 18px rgba(35,24,18,.035)}
.asset-section-tabs .nav-link:hover{border-color:#18745c;color:#18745c;background:#f7fbf9}
.asset-section-tabs .nav-link.active{background:#18745c;border-color:#18745c;color:#fff;box-shadow:0 10px 22px rgba(24,116,92,.18)}
.asset-section-tabs .nav-link i{margin-right:0!important;font-size:1.05rem;line-height:1}
@media (max-width:575.98px){.asset-module-nav{grid-template-columns:repeat(2,minmax(0,1fr));gap:.42rem}.asset-module-nav-link{font-size:.8rem;padding:.5rem .48rem}.asset-section-tabs{grid-template-columns:1fr}}
</style>
<div class="asset-module-nav">
  <?php foreach ($items as $key => $item): ?>
    <a class="asset-module-nav-link <?= $active === $key ? 'is-active' : '' ?>" href="<?= $item['url'] ?>">
      <i class="ri <?= html_escape($item['icon']) ?>"></i><?= html_escape($item['label']) ?>
    </a>
  <?php endforeach; ?>
</div>
