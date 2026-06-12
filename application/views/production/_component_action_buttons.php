<?php
/**
 * _component_action_buttons.php
 * Quick-action bar: one Generate button (opname + stok awal sekaligus)
 * plus navigation links to view pages.
 *
 * Variables (all optional):
 *   $component_action_params : array of query params forwarded to view pages
 */
$fwdParams = is_array($component_action_params ?? null) ? $component_action_params : [];
unset($fwdParams['type'], $fwdParams['q']);

$month       = (string)($fwdParams['month'] ?? date('Y-m'));
$locationType = (string)($fwdParams['location_type'] ?? '');
$divisionId  = (string)($fwdParams['division_id'] ?? '');

$opnameUrl  = site_url('production/component-opname')   . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
$openingUrl = site_url('production/component-openings') . (!empty($fwdParams) ? '?' . http_build_query($fwdParams) : '');
$generateUrl = site_url('production/component-openings/generate-monthly');
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
  <span class="cab-label">Aksi Cepat</span>
  <button type="button" class="btn btn-sm btn-outline-danger fw-semibold" id="btnCabGenerate"
          data-month="<?php echo html_escape($month); ?>"
          data-location-type="<?php echo html_escape($locationType); ?>"
          data-division-id="<?php echo html_escape($divisionId); ?>">
    <i class="ri ri-refresh-line me-1"></i>Generate Opname &amp; Stok Awal
  </button>
  <a href="<?php echo html_escape($opnameUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-file-list-3-line me-1"></i>Lihat Opname
  </a>
  <a href="<?php echo html_escape($openingUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-archive-drawer-line me-1"></i>Lihat Stok Awal
  </a>
</div>

<div id="cabGenerateAlert" class="mt-2"></div>

<script>
(function () {
  const btn = document.getElementById('btnCabGenerate');
  const alertEl = document.getElementById('cabGenerateAlert');
  if (!btn) return;

  function showAlert(type, msg) {
    if (!alertEl) return;
    alertEl.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible mb-0 py-2"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' + msg + '</div>';
  }

  btn.addEventListener('click', async () => {
    const month       = btn.dataset.month || '';
    const locType     = btn.dataset.locationType || '';
    const divisionId  = btn.dataset.divisionId || '';
    if (!month) { showAlert('warning', 'Pilih bulan terlebih dahulu.'); return; }
    if (!confirm('Generate opname bulanan bulan ' + month + ' dan carry-forward stok awal bulan berikutnya?')) return;

    btn.disabled = true;
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';

    try {
      const res = await fetch(<?php echo json_encode($generateUrl); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ month: month, location_type: locType, division_id: divisionId })
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        showAlert('danger', 'Gagal: ' + (data.message || res.statusText));
      } else {
        showAlert('success', 'Generate opname &amp; stok awal bulan ' + month + ' berhasil.');
      }
    } catch (err) {
      showAlert('danger', 'Error: ' + err.message);
    }

    btn.disabled = false;
    btn.innerHTML = origHtml;
  });
})();
</script>
