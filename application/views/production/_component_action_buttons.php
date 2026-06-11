<?php
/**
 * _component_action_buttons.php
 * Reusable quick-action buttons for component pages.
 * Renders right-aligned shortcut links to Opname and Opening modules.
 *
 * Variables (all optional):
 *   $component_action_params : array of query params to forward to the target pages
 */
$fwdParams = is_array($component_action_params ?? null) ? $component_action_params : [];
unset($fwdParams['type'], $fwdParams['q']); // don't forward text/type filters to other modules

$opnameUrl  = site_url('production/component-opname')  . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
$openingUrl = site_url('production/component-openings') . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
?>

<style>
  .component-action-btn-bar {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    align-items: center;
    margin-top: .4rem;
    padding-top: .4rem;
    border-top: 1px solid #e9e3dc;
  }
  .component-action-btn-bar .cab-label {
    min-width: 88px;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #7a6d62;
  }
</style>

<div class="component-action-btn-bar">
  <span class="cab-label">Modul</span>
  <a href="<?php echo html_escape($opnameUrl); ?>"
     class="btn btn-sm btn-warning fw-semibold">
    <i class="ri-clipboard-check-line me-1"></i>Stok Opname
  </a>
  <a href="<?php echo html_escape($openingUrl); ?>"
     class="btn btn-sm btn-success fw-semibold">
    <i class="ri-archive-drawer-line me-1"></i>Stok Awal
  </a>
</div>
