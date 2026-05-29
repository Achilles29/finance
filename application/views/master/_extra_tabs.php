<?php
$extraTabActive = $extraTabActive ?? '';
$extraTabs = [
    'workspace' => ['label' => 'Workspace', 'url' => site_url('master/relation/product-extra-workspace'), 'icon' => 'ri-dashboard-horizontal-line'],
    'master-extra' => ['label' => 'Master Extra', 'url' => site_url('master/extra'), 'icon' => 'ri-add-circle-line'],
    'extra-group' => ['label' => 'Group Extra', 'url' => site_url('master/extra-group'), 'icon' => 'ri-layout-grid-line'],
    'product-extra' => ['label' => 'Mapping Produk', 'url' => site_url('master/relation/product-extra'), 'icon' => 'ri-links-line'],
    'group-checklist' => ['label' => 'Checklist Group', 'url' => site_url('master/relation/extra-group'), 'icon' => 'ri-checkbox-multiple-line'],
];
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-2">
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($extraTabs as $key => $tab): ?>
        <a href="<?php echo $tab['url']; ?>" class="btn <?php echo $extraTabActive === $key ? 'btn-primary' : 'btn-outline-secondary'; ?> btn-sm">
          <i class="<?php echo html_escape($tab['icon']); ?> me-1"></i><?php echo html_escape($tab['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
