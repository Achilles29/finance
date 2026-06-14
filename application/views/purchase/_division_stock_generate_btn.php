<?php
/**
 * _division_stock_generate_btn.php
 * Tombol Generate Opname + Stok Awal untuk semua halaman rumpun Bahan Baku Divisi.
 *
 * Variables (all optional):
 *   $division_action_params : array of filter params (month, division_id, destination_type)
 */
$params = is_array($division_action_params ?? null) ? $division_action_params : [];

$month       = (string)($params['month']       ?? date('Y-m'));
$divisionId  = (string)($params['division_id'] ?? '');
$destType    = (string)($params['destination_type'] ?? $params['destination'] ?? '');

$opnameUrl  = site_url('inventory/stock/opname/division/monthly')
    . '?' . http_build_query(array_filter(['month' => $month, 'division_id' => $divisionId]));
$openingUrl = site_url('inventory/stock/opening/division')
    . '?' . http_build_query(array_filter(['month' => $month, 'division_id' => $divisionId]));
$generateUrl = site_url('inventory/stock/opname/generate');
?>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2 px-1" id="div-stock-action-bar">
  <span class="text-muted fw-semibold" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;min-width:70px">Aksi Cepat</span>
  <button type="button" class="btn btn-sm btn-outline-danger" id="btnDivStockGenerate"
          data-month="<?php echo html_escape($month); ?>"
          data-division-id="<?php echo html_escape($divisionId); ?>"
          data-destination="<?php echo html_escape($destType); ?>">
    <i class="ri ri-refresh-line me-1"></i>Generate Opname &amp; Stok Awal
  </button>
  <a href="<?php echo html_escape($opnameUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-file-list-3-line me-1"></i>Lihat Opname
  </a>
  <a href="<?php echo html_escape($openingUrl); ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-archive-drawer-line me-1"></i>Lihat Opening
  </a>
  <div id="divStockGenerateAlert" class="w-100" style="display:none"></div>
</div>

<script>
(function () {
  const btn     = document.getElementById('btnDivStockGenerate');
  const alertEl = document.getElementById('divStockGenerateAlert');
  if (!btn) return;

  function showAlert(type, msg) {
    if (!alertEl) return;
    alertEl.style.display = '';
    alertEl.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible mb-1 py-2 small"><button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>' + msg + '</div>';
  }

  btn.addEventListener('click', async () => {
    const month      = btn.dataset.month || '';
    const divisionId = btn.dataset.divisionId || '';
    const destination = btn.dataset.destination || '';
    if (!month) { showAlert('warning', 'Bulan tidak ditemukan. Pilih bulan pada filter terlebih dahulu.'); return; }
    if (!confirm('Generate opname divisi bulan ' + month + ' dan carry-forward stok awal ke bulan berikutnya?\n\nProses ini akan menimpa data opname bulan tersebut jika sudah ada.')) return;

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
    if (alertEl) alertEl.style.display = 'none';

    try {
      const body = { stock_scope: 'DIVISION', month };
      if (divisionId && divisionId !== '0') body.division_id = divisionId;
      if (destination && destination !== 'ALL') body.destination_type = destination;

      const res  = await fetch(<?php echo json_encode($generateUrl); ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        showAlert('danger', 'Gagal: ' + (data.message || res.statusText));
      } else {
        showAlert('success', 'Generate selesai untuk bulan <strong>' + month + '</strong>. '
          + (data.message || '') + ' <a href="' + <?php echo json_encode($opnameUrl); ?> + '" class="alert-link ms-1">Lihat Opname</a>'
          + ' <a href="' + <?php echo json_encode($openingUrl); ?> + '" class="alert-link ms-1">Lihat Opening</a>');
      }
    } catch (err) {
      showAlert('danger', 'Error: ' + err.message);
    }

    btn.disabled = false;
    btn.innerHTML = origHtml;
  });
})();
</script>
