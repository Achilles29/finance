<?php
$mode = (string)($mode ?? 'create');
$header = $header ?? [];
$lines = $lines ?? [];
$divisionOptions = $division_options ?? [];
$destinationOptions = $destination_options ?? [];
$destinationGuardMap = $destination_guard_map ?? [];
$uomOptions = $uom_options ?? [];
$vendorOptions = $vendor_options ?? [];
$isPurchaseScope = !empty($is_purchase_scope);
$canVerify = !empty($can_verify);
$requestId = (int)($request_id ?? 0);
$showVendorColumn = $canVerify;
$lineColumnCount = $showVendorColumn ? 14 : 13;

$formAction = $mode === 'create'
    ? site_url('procurement/division-po-sr/create')
    : site_url('procurement/division-po-sr/edit/' . $requestId);
$submitLabel = 'Simpan Pengajuan';
if ($mode === 'edit') {
    $submitLabel = 'Perbarui Pengajuan';
} elseif ($mode === 'verify') {
    $submitLabel = 'Verifikasi & Bentuk Dokumen';
}
$divisionLocked = $mode !== 'create' || count($divisionOptions) <= 1 || !$isPurchaseScope;
$selectedDivisionId = (int)($header['division_id'] ?? 0);
$allowedDestinationValues = (array)($destinationGuardMap[$selectedDivisionId] ?? []);
$initialLines = [];
foreach ((array)$lines as $line) {
  $contentPerBuy = (float)($line['profile_content_per_buy'] ?? 1);
  if ($contentPerBuy <= 0) {
    $contentPerBuy = 1;
  }
  $qtyContentBalance = (float)($line['qty_content_available_snapshot'] ?? 0);
  $qtyContentToPo = (float)($line['qty_content_to_po'] ?? 0);
  $buyUomCode = (string)($line['profile_buy_uom_code'] ?? '');
  $contentUomCode = (string)($line['profile_content_uom_code'] ?? '');
  $storedRequestMode = strtoupper(trim((string)($line['request_uom_mode'] ?? '')));
    $initialLines[] = [
        'line_kind' => (string)($line['line_kind'] ?? 'ITEM'),
        'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
        'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
        'profile_key' => (string)($line['profile_key'] ?? ''),
        'profile_name' => (string)($line['profile_name'] ?? ''),
        'profile_brand' => (string)($line['profile_brand'] ?? ''),
        'profile_description' => (string)($line['profile_description'] ?? ''),
        'profile_expired_date' => (string)($line['profile_expired_date'] ?? ''),
        'buy_uom_id' => (int)($line['buy_uom_id'] ?? 0),
        'content_uom_id' => (int)($line['content_uom_id'] ?? 0),
        'profile_content_per_buy' => $contentPerBuy,
        'profile_buy_uom_code' => $buyUomCode,
        'profile_content_uom_code' => $contentUomCode,
        'request_uom_mode' => in_array($storedRequestMode, ['BUY', 'CONTENT'], true) ? $storedRequestMode : '',
        'vendor_id' => !empty($line['vendor_id']) ? (int)$line['vendor_id'] : null,
        'vendor_name' => (string)($line['vendor_name'] ?? ''),
        'qty_buy_requested' => (float)($line['qty_buy_requested'] ?? 0),
        'qty_content_requested' => (float)($line['qty_content_requested'] ?? 0),
        'qty_buy_po_requested' => $qtyContentToPo > 0 ? ($qtyContentToPo / $contentPerBuy) : 0,
        'qty_content_po_requested' => $qtyContentToPo,
        'qty_buy_balance' => $qtyContentBalance / $contentPerBuy,
        'qty_content_balance' => $qtyContentBalance,
        'estimated_unit_price' => (float)($line['estimated_unit_price'] ?? 0),
        'source_type' => $qtyContentBalance > 0 ? 'WAREHOUSE' : 'CATALOG',
        'notes' => (string)($line['notes'] ?? ''),
    ];
}
?>

<?php
if (!function_exists('finance_dreq_location_label')) {
  function finance_dreq_location_label($destinationType)
  {
    $destinationType = strtoupper(trim((string)$destinationType));
    if (strpos($destinationType, 'EVENT') !== false) {
      return 'Event';
    }
    if (in_array($destinationType, ['BAR', 'KITCHEN', 'OFFICE'], true)) {
      return 'Reguler';
    }
    return $destinationType !== '' ? $destinationType : '-';
  }
}
?>

<style>
  .dreq-line-table th, .dreq-line-table td { vertical-align: middle; }
  .dreq-line-table { min-width: 1760px; }
  .dreq-search-scroll { max-height: 260px; overflow: auto; }
  .dreq-manual-card { display: none; }
  .dreq-search-table th, .dreq-search-table td { vertical-align: middle; }
  .dreq-profile-cell { min-width: 260px; }
  .dreq-uom-cell { min-width: 140px; }
  .dreq-vendor-cell { min-width: 240px; }
  .dreq-stock-cell { min-width: 95px; }
  .dreq-request-cell { min-width: 180px; }
  .dreq-qty-input { min-width: 110px; }
  .dreq-po-input { min-width: 120px; }
  .dreq-qty-readonly { min-width: 110px; background: #f8f9fa; }
  .dreq-notes-input { min-width: 220px; }
  .dreq-action-btn { min-width: 72px; }
  .dreq-route-note { font-size: .74rem; line-height: 1.3; color: #6c757d; margin-top: .3rem; display: block; }
  .dreq-qty-input.is-overstock { border-color: #f0ad4e; background: #fff8ea; }
  .dreq-po-input.is-active-po { border-color: #7f5af0; background: #f7f2ff; }
  .dreq-stock-split { color: #9a6700; font-weight: 600; }
  .dreq-guidance {
    border: 1px solid #f0dfcf;
    background: linear-gradient(180deg, #fffaf4 0%, #fff6ed 100%);
    border-radius: 14px;
    padding: .8rem 1rem;
  }
  .dreq-vendor-wrap { display: flex; align-items: center; gap: .45rem; }
  .dreq-vendor-select { min-width: 0; flex: 1 1 auto; }
  .dreq-vendor-add { min-width: 42px; flex: 0 0 auto; border-radius: 10px; }
  .dreq-vendor-meta { font-size: .72rem; line-height: 1.35; color: #8c7771; }
  .dreq-request-mode { min-width: 130px; }
  .dreq-mode-caption { font-size: .71rem; font-weight: 700; color: #8a1538; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .2rem; }
  .dreq-buyer-caption { font-size: .68rem; font-weight: 700; color: #6b4f3f; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
  .dreq-profile-editor { border-top: 1px dashed #dfd2c8; padding-top: .55rem; }
  .dreq-suggestion-list { display: flex; flex-direction: column; gap: .35rem; }
  .dreq-suggestion-chip { text-align: left; white-space: normal; border-radius: 12px; padding: .5rem .65rem; }
  .dreq-suggestion-chip strong,
  .dreq-suggestion-chip small { display: block; }
  .dreq-suggestion-chip small { font-size: .72rem; line-height: 1.35; color: #6c757d; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-file-list-3-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'Form Pengajuan Divisi'); ?></h4>
    <small class="text-muted">
      <?php echo $mode === 'verify'
        ? 'Purchase dapat menyesuaikan item, merk, keterangan, dan harga estimasi sebelum sistem membentuk SR/PO final. Split stok final dihitung ulang saat verifikasi disimpan.'
        : 'Pencarian berjalan via AJAX: stok gudang lebih dulu, lalu katalog, lalu input manual bila tidak ditemukan.'; ?>
    </small>
  </div>
  <a href="<?php echo $requestId > 0 ? site_url('procurement/division-po-sr/detail/' . $requestId) : site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'division-po-sr']); ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo $formAction; ?>" id="divisionRequestForm">
  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label mb-1">No Request</label>
          <input type="text" class="form-control" value="<?php echo html_escape((string)($header['request_no'] ?? 'AUTO')); ?>" readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Tgl Request</label>
          <input type="date" name="request_date" class="form-control" value="<?php echo html_escape((string)($header['request_date'] ?? date('Y-m-d'))); ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Tgl Butuh</label>
          <input type="date" name="needed_date" class="form-control" value="<?php echo html_escape((string)($header['needed_date'] ?? date('Y-m-d'))); ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Divisi</label>
          <select name="division_id" id="fieldDivisionId" class="form-select" <?php echo $divisionLocked ? 'disabled' : ''; ?>>
            <?php foreach ($divisionOptions as $option): ?>
              <option value="<?php echo (int)$option['id']; ?>" <?php echo ((int)($header['division_id'] ?? 0) === (int)$option['id']) ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($option['name'] ?? $option['division_name'] ?? ('DIV#' . $option['id']))); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($divisionLocked): ?>
            <input type="hidden" name="division_id" value="<?php echo (int)($header['division_id'] ?? 0); ?>">
          <?php endif; ?>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Lokasi Stok</label>
          <select name="destination_type" id="fieldDestinationType" class="form-select">
            <?php foreach ($destinationOptions as $option): ?>
              <?php if (!empty($allowedDestinationValues) && !in_array((string)($option['value'] ?? ''), $allowedDestinationValues, true)) { continue; } ?>
              <option value="<?php echo html_escape((string)($option['value'] ?? '')); ?>" <?php echo ((string)($header['destination_type'] ?? '') === (string)($option['value'] ?? '')) ? 'selected' : ''; ?>>
                <?php echo html_escape(finance_dreq_location_label((string)($option['value'] ?? ''))); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Catatan</label>
          <input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($header['notes'] ?? '')); ?>" placeholder="Opsional">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Cari Barang</h6>
        <small class="text-muted">Ketik nama barang. Sistem cek stok gudang dulu, lalu fallback ke katalog, lalu input manual bila kosong.</small>
      </div>
      <div class="dreq-guidance small text-muted mb-3">
        Jika stok gudang ada, daftar pencarian hanya menampilkan stok gudang agar pilihan utama tetap jelas. Setelah barang dipilih, tiap line bisa diinput memakai <strong>UOM Beli</strong> atau <strong>UOM Isi</strong>. Bila kebutuhan melebihi stok snapshot, isi saja <strong>Qty Request</strong> sesuai kebutuhan total; sistem akan pecah otomatis: stok yang ada masuk <strong>SR</strong>, sisanya diarahkan ke <strong>PO</strong> saat simpan/verifikasi.
      </div>
      <div id="dreqFormAlert"></div>
      <div id="dreqSearchMeta" class="small text-muted mb-2"></div>
      <div class="row g-2 mb-2">
        <div class="col-md-10">
          <input type="text" id="searchProfileInput" class="form-control" placeholder="Nama profile / item / material">
        </div>
        <div class="col-md-2 d-grid">
          <button type="button" class="btn btn-outline-primary" id="btnSearchProfile">Cari</button>
        </div>
      </div>
      <div class="dreq-search-scroll border rounded">
        <table class="table table-sm mb-0 dreq-search-table">
          <thead>
            <tr>
              <th>Profile</th>
              <th>UOM</th>
              <th class="text-end">Stok Beli</th>
              <th class="text-end">Stok Isi</th>
              <th style="width:90px">Aksi</th>
            </tr>
          </thead>
          <tbody id="searchResultRows">
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada pencarian.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="border rounded p-3 mt-3 bg-light dreq-manual-card" id="manualEntryCard">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <strong>Input Manual</strong>
            <div class="small text-muted">Dipakai hanya saat stok gudang dan katalog sama-sama tidak menemukan barang. Line ini akan diarahkan ke PO, lalu mode inputnya tetap bisa diubah ke UOM beli atau isi di tabel bawah.</div>
          </div>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label mb-1">Nama Barang</label>
            <input type="text" class="form-control" id="manualNameInput" placeholder="Nama barang manual">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Deskripsi</label>
            <input type="text" class="form-control" id="manualDescriptionInput" placeholder="Spesifikasi opsional untuk PO">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">UOM Beli</label>
            <select class="form-select" id="manualBuyUomId">
              <option value="">Pilih</option>
              <?php foreach ($uomOptions as $uom): ?>
                <option value="<?php echo (int)($uom['id'] ?? 0); ?>" data-code="<?php echo html_escape((string)($uom['code'] ?? '')); ?>"><?php echo html_escape((string)($uom['name'] ?? $uom['code'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">UOM Isi</label>
            <select class="form-select" id="manualContentUomId">
              <option value="">Pilih</option>
              <?php foreach ($uomOptions as $uom): ?>
                <option value="<?php echo (int)($uom['id'] ?? 0); ?>" data-code="<?php echo html_escape((string)($uom['code'] ?? '')); ?>"><?php echo html_escape((string)($uom['name'] ?? $uom['code'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Isi per Beli</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="manualContentPerBuy" value="1.00">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Qty Beli</label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="manualQtyBuy" value="1.00">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Qty Isi</label>
            <input type="number" step="0.01" class="form-control" id="manualQtyContent" value="1.00" readonly>
          </div>
          <div class="col-md-2 d-grid align-items-end">
            <button type="button" class="btn btn-outline-primary" id="btnAddManualLine">Tambah Manual</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Line Pengajuan</h6>
        <small class="text-muted">Pilih mode input per line. `Qty Request`, `Ke SR`, `Qty Tambahan PO`, dan `Ke PO` akan mengikuti UOM beli atau isi yang dipilih, sementara sistem tetap menyimpan konversi buy-content yang konsisten.</small>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-sm dreq-line-table mb-0">
          <thead>
            <tr>
              <th>Profile</th>
              <th>Jenis</th>
              <th>Route</th>
              <?php if ($showVendorColumn): ?><th>Vendor PO</th><?php endif; ?>
              <th>UOM</th>
              <th class="text-end">Stok Beli</th>
              <th class="text-end">Stok Isi</th>
              <th>Input Request</th>
              <th>Ke SR</th>
              <th>Qty Tambahan PO</th>
              <th>Ke PO</th>
              <th>Qty Isi</th>
              <th>Catatan</th>
              <th style="width:90px">Aksi</th>
            </tr>
          </thead>
          <tbody id="requestLineRows">
            <tr><td colspan="<?php echo (int)$lineColumnCount; ?>" class="text-center text-muted py-3">Belum ada line pengajuan.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <input type="hidden" name="lines_json" id="fieldLinesJson" value="">

  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="<?php echo $requestId > 0 ? site_url('procurement/division-po-sr/detail/' . $requestId) : site_url('procurement/division-po-sr'); ?>" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary" id="btnSubmitDivisionRequest"><?php echo html_escape($submitLabel); ?></button>
  </div>
</form>

<?php $this->load->view('purchase/_vendor_quick_create_modal'); ?>

<script>
(function () {
  'use strict';

  var searchUrl = <?php echo json_encode(site_url('procurement/division-po-sr/profile-search')); ?>;
  var catalogSearchUrl = <?php echo json_encode(site_url('purchase/catalog/search')); ?>;
  var destinationGuardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
  var destinationOptionMeta = <?php echo json_encode($destinationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var vendorOptions = <?php echo json_encode($vendorOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
  var canAssignVendor = <?php echo $showVendorColumn ? 'true' : 'false'; ?>;
  var isVerifyMode = <?php echo $canVerify ? 'true' : 'false'; ?>;
  var lineColumnCount = <?php echo (int)$lineColumnCount; ?>;
  var requestLines = <?php echo json_encode($initialLines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];

  function esc(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
    });
  }

  function num(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function round2(value) {
    return Math.round(num(value) * 100) / 100;
  }

  function selectedRequestMode(value) {
    var mode = String(value || '').toUpperCase().trim();
    return mode === 'BUY' || mode === 'CONTENT' ? mode : '';
  }

  function requestMode(value) {
    return selectedRequestMode(value) === 'CONTENT' ? 'CONTENT' : 'BUY';
  }

  function defaultRequestModeForRow(row) {
    var plan = routePlan(row || {});
    return (plan.label === 'SR' || plan.label === 'SR + PO') ? 'BUY' : '';
  }

  function hasRequestModeSelection(row) {
    return selectedRequestMode(row && row.request_uom_mode) !== '';
  }

  function fixed2(value) {
    return round2(value).toFixed(2);
  }

  function qtyWithCode(value, code) {
    return fixed2(value) + ' ' + String(code || '-').trim();
  }

  function normalizeToken(value) {
    return String(value || '').trim().toUpperCase();
  }

  function flash(type, message) {
    var el = document.getElementById('dreqFormAlert');
    if (!el) {
      return;
    }
    el.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-3">' + message + '</div>';
  }

  function setSearchMeta(message) {
    var el = document.getElementById('dreqSearchMeta');
    if (el) {
      el.textContent = message || '';
    }
  }

  function toggleManualCard(show, defaultName) {
    var card = document.getElementById('manualEntryCard');
    if (!card) {
      return;
    }
    card.style.display = show ? 'block' : 'none';
    if (show) {
      var nameInput = document.getElementById('manualNameInput');
      if (nameInput && !String(nameInput.value || '').trim()) {
        nameInput.value = defaultName || '';
      }
    }
  }

  function lineKey(row) {
    return [row.profile_key || '', row.profile_name || '', row.item_id || 0, row.material_id || 0, row.buy_uom_id || 0, row.content_uom_id || 0].join('|');
  }

  function normalizeRow(row) {
    var contentPerBuy = num(row.profile_content_per_buy);
    if (contentPerBuy <= 0) {
      contentPerBuy = 1;
    }
    var qtyContentBalance = num(row.qty_content_balance || row.qty_content_available_snapshot);
    var qtyBuyBalance = num(row.qty_buy_balance);
    if (qtyBuyBalance <= 0 && qtyContentBalance > 0) {
      qtyBuyBalance = qtyContentBalance / contentPerBuy;
    }
    var normalized = {
      line_kind: row.line_kind || (num(row.material_id) > 0 ? 'MATERIAL' : 'ITEM'),
      item_id: num(row.item_id) > 0 ? num(row.item_id) : null,
      material_id: num(row.material_id) > 0 ? num(row.material_id) : null,
      profile_key: row.profile_key || '',
      profile_name: row.profile_name || '',
      profile_brand: row.profile_brand || '',
      profile_description: row.profile_description || '',
      profile_expired_date: row.profile_expired_date || '',
      buy_uom_id: num(row.buy_uom_id),
      content_uom_id: num(row.content_uom_id),
      profile_content_per_buy: contentPerBuy,
      profile_buy_uom_code: row.profile_buy_uom_code || '',
      profile_content_uom_code: row.profile_content_uom_code || '',
      request_uom_mode: selectedRequestMode(row.request_uom_mode || defaultRequestModeForRow(row)),
      vendor_id: num(row.vendor_id) > 0 ? num(row.vendor_id) : null,
      vendor_name: row.vendor_name || '',
      qty_buy_requested: round2(num(row.qty_buy_requested) > 0 ? num(row.qty_buy_requested) : 0),
      qty_content_requested: round2(num(row.qty_content_requested) > 0 ? num(row.qty_content_requested) : 0),
      qty_buy_po_requested: round2(num(row.qty_buy_po_requested)),
      qty_content_po_requested: round2(num(row.qty_content_po_requested)),
      qty_buy_balance: round2(qtyBuyBalance),
      qty_content_balance: round2(qtyContentBalance),
      estimated_unit_price: round2(num(row.estimated_unit_price || row.last_unit_price || row.standard_price || 0)),
      catalog_suggestions: Array.isArray(row.catalog_suggestions) ? row.catalog_suggestions : [],
      suggestion_loading: !!row.suggestion_loading,
      suggestion_query: row.suggestion_query || '',
      source_type: row.source_type || row.search_source || (num(row.qty_content_balance || row.qty_content_available_snapshot) > 0 ? 'WAREHOUSE' : 'CATALOG'),
      notes: row.notes || ''
    };

    if (!hasRequestModeSelection(normalized)) {
      normalized.qty_buy_po_requested = 0;
      normalized.qty_content_po_requested = 0;
    } else if (String(normalized.source_type || '').toUpperCase() !== 'WAREHOUSE') {
      normalized.qty_buy_po_requested = round2(normalized.qty_buy_requested);
      normalized.qty_content_po_requested = round2(normalized.qty_content_requested);
    } else if (normalized.qty_buy_po_requested <= 0 && normalized.qty_content_po_requested <= 0) {
      normalized.qty_buy_po_requested = round2(Math.max(0, normalized.qty_buy_requested - Math.min(normalized.qty_buy_requested, normalized.qty_buy_balance)));
      normalized.qty_content_po_requested = round2(normalized.qty_buy_po_requested * contentPerBuy);
    } else if (normalized.qty_content_po_requested <= 0 && normalized.qty_buy_po_requested > 0) {
      normalized.qty_content_po_requested = round2(normalized.qty_buy_po_requested * contentPerBuy);
    }

    return normalized;
  }

  function requestUnitCode(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? (row.profile_content_uom_code || row.profile_buy_uom_code || '-')
      : (row.profile_buy_uom_code || row.profile_content_uom_code || '-');
  }

  function requestQtyValue(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? round2(num(row.qty_content_requested))
      : round2(num(row.qty_buy_requested));
  }

  function requestPoQtyValue(row) {
    return requestMode(row && row.request_uom_mode) === 'CONTENT'
      ? round2(num(row.qty_content_po_requested))
      : round2(num(row.qty_buy_po_requested));
  }

  function requestPlanQty(plan, row, target) {
    var mode = requestMode(row && row.request_uom_mode);
    if (target === 'SR') {
      return mode === 'CONTENT' ? round2(num(plan.toSrContent)) : round2(num(plan.toSrBuy));
    }
    return mode === 'CONTENT' ? round2(num(plan.toPoContent)) : round2(num(plan.toPoBuy));
  }

  function requestSecondarySummary(row) {
    var mode = requestMode(row && row.request_uom_mode);
    var primaryCode = requestUnitCode(row);
    var secondaryCode = mode === 'CONTENT'
      ? String(row.profile_buy_uom_code || '').trim()
      : String(row.profile_content_uom_code || '').trim();
    if (!secondaryCode || secondaryCode === primaryCode) {
      return '';
    }
    var secondaryValue = mode === 'CONTENT'
      ? round2(num(row.qty_buy_requested))
      : round2(num(row.qty_content_requested));
    return qtyWithCode(secondaryValue, secondaryCode);
  }

  function poSecondarySummary(row) {
    var mode = requestMode(row && row.request_uom_mode);
    var primaryCode = requestUnitCode(row);
    var secondaryCode = mode === 'CONTENT'
      ? String(row.profile_buy_uom_code || '').trim()
      : String(row.profile_content_uom_code || '').trim();
    if (!secondaryCode || secondaryCode === primaryCode) {
      return '';
    }
    var secondaryValue = mode === 'CONTENT'
      ? round2(num(row.qty_buy_po_requested))
      : round2(num(row.qty_content_po_requested));
    return qtyWithCode(secondaryValue, secondaryCode);
  }

  function requestInputCaption(row, plan) {
    if (!hasRequestModeSelection(row)) {
      return 'Pilih dulu mode input: per beli atau per isi.';
    }
    var unitCode = requestUnitCode(row);
    if (num(plan && plan.toSrContent) <= 0.00001 && num(plan && plan.toPoContent) > 0.00001) {
      return 'Input kebutuhan PO langsung dalam ' + unitCode + '.';
    }
    return 'Input kebutuhan total dalam ' + unitCode + '.';
  }

  function vendorNameById(vendorId) {
    var id = num(vendorId);
    if (id <= 0) {
      return '';
    }
    for (var i = 0; i < vendorOptions.length; i++) {
      if (num(vendorOptions[i] && vendorOptions[i].id) === id) {
        return String(vendorOptions[i].vendor_name || vendorOptions[i].vendor_code || '');
      }
    }
    return '';
  }

  function vendorLabel(vendor) {
    var code = String(vendor && vendor.vendor_code ? vendor.vendor_code : '').trim();
    var name = String(vendor && vendor.vendor_name ? vendor.vendor_name : '').trim();
    if (code && name) {
      return code + ' - ' + name;
    }
    return name || code || ('Vendor #' + String(vendor && vendor.id ? vendor.id : ''));
  }

  function upsertVendorOption(vendor) {
    var vendorId = num(vendor && vendor.id);
    if (vendorId <= 0) {
      return;
    }

    var normalized = {
      id: vendorId,
      vendor_code: String(vendor && vendor.vendor_code ? vendor.vendor_code : ''),
      vendor_name: String(vendor && vendor.vendor_name ? vendor.vendor_name : '')
    };
    var replaced = false;
    for (var i = 0; i < vendorOptions.length; i++) {
      if (num(vendorOptions[i] && vendorOptions[i].id) === vendorId) {
        vendorOptions[i] = normalized;
        replaced = true;
        break;
      }
    }
    if (!replaced) {
      vendorOptions.push(normalized);
    }
    vendorOptions.sort(function (left, right) {
      return vendorLabel(left).localeCompare(vendorLabel(right));
    });
  }

  function requiresVendor(row, plan) {
    return canAssignVendor && num(plan && plan.toPoContent) > 0.00001;
  }

  function vendorInputCaption(row, plan) {
    if (!canAssignVendor) {
      return '';
    }
    if (!requiresVendor(row, plan)) {
      return 'Tidak diperlukan bila line hanya ke SR.';
    }
    return 'Wajib dipilih Purchase untuk line yang masuk PO.';
  }

  function renderVendorOptions(selectedId) {
    var html = ['<option value="">Pilih vendor</option>'];
    vendorOptions.forEach(function (vendor) {
      var vendorId = num(vendor && vendor.id);
      var selected = vendorId > 0 && vendorId === num(selectedId) ? ' selected' : '';
      var label = vendorLabel(vendor);
      html.push('<option value="' + vendorId + '"' + selected + '>' + esc(label) + '</option>');
    });
    return html.join('');
  }

  function poInputCaption(row, plan) {
    if (!hasRequestModeSelection(row)) {
      return 'Mode input belum dipilih.';
    }
    var unitCode = requestUnitCode(row);
    if (num(plan && plan.toSrContent) <= 0.00001 && num(plan && plan.toPoContent) > 0.00001) {
      return 'Seluruh qty akan dibuat PO dalam ' + unitCode + '.';
    }
    return 'Porsi shortage yang masuk PO dalam ' + unitCode + '.';
  }

  function sourceLabel(sourceType) {
    if (sourceType === 'WAREHOUSE') {
      return 'Gudang';
    }
    if (sourceType === 'MANUAL') {
      return 'Manual';
    }
    return 'Katalog';
  }

  function locationLabel(destinationType) {
    var value = String(destinationType || '').toUpperCase();
    if (value.indexOf('EVENT') !== -1) {
      return 'Event';
    }
    if (value === 'BAR' || value === 'KITCHEN' || value === 'OFFICE') {
      return 'Reguler';
    }
    return value || '-';
  }

  function applyDivisionDestinationGuard() {
    var divisionEl = document.getElementById('fieldDivisionId');
    var destinationEl = document.getElementById('fieldDestinationType');
    if (!divisionEl || !destinationEl) {
      return;
    }

    var divisionId = String(divisionEl.value || '');
    var allowed = destinationGuardMap[divisionId] || [];
    if (!Array.isArray(allowed) || !allowed.length) {
      return;
    }

    var current = String(destinationEl.value || '');
    var html = [];
    destinationOptionMeta.forEach(function (option) {
      if (allowed.indexOf(option.value) !== -1) {
        html.push('<option value="' + esc(option.value) + '">' + esc(locationLabel(option.value)) + '</option>');
      }
    });
    destinationEl.innerHTML = html.join('');
    if (allowed.indexOf(current) !== -1) {
      destinationEl.value = current;
    } else {
      destinationEl.value = String(allowed[0] || '');
    }
  }

  function routeLabel(row) {
    return routePlan(row).label;
  }

  function routeBadgeClass(row) {
    var label = routeLabel(row);
    if (label === 'SR') {
      return 'success';
    }
    if (label === 'SR + PO') {
      return 'info text-dark';
    }
    return 'warning text-dark';
  }

  function syncRowTotalsFromRequest(row, requestQty) {
    var factor = num(row.profile_content_per_buy) || 1;
    var mode = requestMode(row && row.request_uom_mode);
    var safeQty = round2(Math.max(0, num(requestQty)));
    if (mode === 'CONTENT') {
      row.qty_content_requested = safeQty;
      row.qty_buy_requested = round2(safeQty / factor);
    } else {
      row.qty_buy_requested = safeQty;
      row.qty_content_requested = round2(safeQty * factor);
    }
    if (String(row.source_type || '').toUpperCase() !== 'WAREHOUSE') {
      row.qty_buy_po_requested = round2(row.qty_buy_requested);
      row.qty_content_po_requested = round2(row.qty_content_requested);
      return;
    }
    row.qty_content_po_requested = round2(Math.max(0, row.qty_content_requested - Math.min(row.qty_content_requested, num(row.qty_content_balance))));
    row.qty_buy_po_requested = round2(row.qty_content_po_requested / factor);
  }

  function syncRowTotalsFromPo(row, poQty, previousPlan) {
    var factor = num(row.profile_content_per_buy) || 1;
    var sourceType = String(row.source_type || '').toUpperCase();
    var mode = requestMode(row && row.request_uom_mode);
    var safeQty = round2(Math.max(0, num(poQty)));
    if (mode === 'CONTENT') {
      row.qty_content_po_requested = safeQty;
      row.qty_buy_po_requested = round2(safeQty / factor);
    } else {
      row.qty_buy_po_requested = safeQty;
      row.qty_content_po_requested = round2(safeQty * factor);
    }
    if (sourceType !== 'WAREHOUSE') {
      row.qty_buy_requested = round2(row.qty_buy_po_requested);
      row.qty_content_requested = round2(row.qty_content_po_requested);
      row.qty_content_po_requested = round2(row.qty_content_requested);
      row.qty_buy_po_requested = round2(row.qty_buy_requested);
      return;
    }

    var currentPoContent = round2(num(row.qty_content_po_requested));
    var srBaseContent = round2(Math.max(0, num(previousPlan && previousPlan.toSrContent)));
    if (srBaseContent <= 0) {
      var currentTotalContent = round2(num(row.qty_content_requested));
      srBaseContent = round2(Math.max(0, currentTotalContent - currentPoContent));
    }
    srBaseContent = round2(Math.min(srBaseContent, num(row.qty_content_balance)));
    row.qty_content_requested = round2(srBaseContent + currentPoContent);
    row.qty_buy_requested = round2(row.qty_content_requested / factor);
    row.qty_buy_po_requested = round2(currentPoContent / factor);
  }

  function routePlan(row) {
    var sourceType = String(row.source_type || 'WAREHOUSE').toUpperCase();
    var requestedContent = round2(num(row.qty_content_requested));
    var availableContent = round2(num(row.qty_content_balance));
    var factor = num(row.profile_content_per_buy) || 1;
    var explicitPoContent = round2(num(row.qty_content_po_requested) || (num(row.qty_buy_po_requested) * factor));

    if (requestedContent <= 0) {
      requestedContent = round2(num(row.qty_buy_requested) * factor);
    }

    if (sourceType !== 'WAREHOUSE' || availableContent <= 0) {
      return {
        label: 'PO',
        toSrContent: 0,
        toPoContent: requestedContent,
        toSrBuy: 0,
        toPoBuy: round2(requestedContent / factor)
      };
    }

    if (explicitPoContent > 0) {
      var poContentExplicit = Math.min(requestedContent, explicitPoContent);
      var srContentExplicit = Math.min(Math.max(0, requestedContent - poContentExplicit), availableContent);
      var remainingContent = Math.max(0, requestedContent - srContentExplicit - poContentExplicit);
      if (remainingContent > 0) {
        poContentExplicit = round2(poContentExplicit + remainingContent);
      }
      return {
        label: poContentExplicit > 0 && srContentExplicit > 0 ? 'SR + PO' : (srContentExplicit > 0 ? 'SR' : 'PO'),
        toSrContent: srContentExplicit,
        toPoContent: poContentExplicit,
        toSrBuy: round2(srContentExplicit / factor),
        toPoBuy: round2(poContentExplicit / factor)
      };
    }

    if (requestedContent <= availableContent + 0.00001) {
      return {
        label: 'SR',
        toSrContent: requestedContent,
        toPoContent: 0,
        toSrBuy: round2(requestedContent / factor),
        toPoBuy: 0
      };
    }

    return {
      label: 'SR + PO',
      toSrContent: availableContent,
      toPoContent: round2(requestedContent - availableContent),
      toSrBuy: round2(availableContent / factor),
      toPoBuy: round2((requestedContent - availableContent) / factor)
    };
  }

  function routeNote(row) {
    var plan = routePlan(row);
    var sourceType = String(row.source_type || 'WAREHOUSE').toUpperCase();
    var unitCode = requestUnitCode(row);
    var srQty = requestPlanQty(plan, row, 'SR');
    var poQty = requestPlanQty(plan, row, 'PO');
    if (sourceType !== 'WAREHOUSE') {
      return 'Fallback katalog/manual: seluruh kebutuhan diarahkan ke PO (' + qtyWithCode(poQty, unitCode) + ').';
    }
    if (plan.label === 'SR + PO') {
      return 'Stok kurang. SR ' + qtyWithCode(srQty, unitCode) + ', sisa PO ' + qtyWithCode(poQty, unitCode) + '.';
    }
    return 'Semua kebutuhan masih tertutup stok gudang, jadi masuk SR ' + qtyWithCode(srQty, unitCode) + '.';
  }

  function lineSuggestionKeyword(row) {
    var name = String(row && row.profile_name || '').trim();
    if (name !== '') {
      return name;
    }
    return String(row && row.profile_description || '').trim();
  }

  function suggestionScore(row, suggestion) {
    var score = 0;
    var lineName = normalizeToken(row && row.profile_name);
    var lineBrand = normalizeToken(row && row.profile_brand);
    var lineDesc = normalizeToken(row && row.profile_description);
    var suggestionName = normalizeToken(suggestion && (suggestion.catalog_name || suggestion.item_name || suggestion.material_name));
    var suggestionBrand = normalizeToken(suggestion && suggestion.brand_name);
    var suggestionDesc = normalizeToken(suggestion && suggestion.line_description);

    if (lineBrand !== '') {
      if (suggestionBrand === lineBrand) {
        score += 1200;
      } else if ((suggestionBrand !== '' && suggestionBrand.indexOf(lineBrand) >= 0) || (suggestionBrand !== '' && lineBrand.indexOf(suggestionBrand) >= 0)) {
        score += 700;
      }
    } else if (suggestionBrand !== '') {
      score += 160;
    }

    if (lineName !== '') {
      if (suggestionName === lineName) {
        score += 500;
      } else if ((suggestionName !== '' && suggestionName.indexOf(lineName) >= 0) || (suggestionName !== '' && lineName.indexOf(suggestionName) >= 0)) {
        score += 260;
      }
    }

    if (lineDesc !== '') {
      if (suggestionDesc === lineDesc) {
        score += 180;
      } else if ((suggestionDesc !== '' && suggestionDesc.indexOf(lineDesc) >= 0) || (suggestionDesc !== '' && lineDesc.indexOf(suggestionDesc) >= 0)) {
        score += 90;
      }
    }

    if (num(row && row.item_id) > 0 && num(suggestion && suggestion.item_id) === num(row.item_id)) {
      score += 800;
    }
    if (num(row && row.material_id) > 0 && num(suggestion && suggestion.material_id) === num(row.material_id)) {
      score += 800;
    }

    return score;
  }

  function renderProfileSuggestionButtons(idx, suggestions) {
    return suggestions.map(function (it, suggestionIdx) {
      var name = String(it.catalog_name || it.item_name || it.material_name || '-');
      var brand = String(it.brand_name || '-');
      var desc = String(it.line_description || '-');
      var price = fixed2(num(it.last_unit_price || it.standard_price || 0));
      var meta = [brand, desc].filter(function (part) {
        return String(part || '').trim() !== '' && String(part || '-') !== '-';
      }).join(' | ');
      if (meta === '') {
        meta = 'Tanpa merk/keterangan';
      }
      meta += ' | Est ' + price;
      return '<button type="button" class="btn btn-sm btn-outline-secondary dreq-suggestion-chip btn-apply-profile-suggestion" data-idx="' + idx + '" data-suggestion-idx="' + suggestionIdx + '">'
        + '<strong>' + esc(name) + '</strong>'
        + '<small>' + esc(meta) + '</small>'
        + '</button>';
    }).join('');
  }

  function fetchLineProfileSuggestions(idx) {
    if (!isVerifyMode || !requestLines[idx]) {
      return;
    }

    var row = requestLines[idx];
    var keyword = lineSuggestionKeyword(row);
    if (keyword.length < 2) {
      row.catalog_suggestions = [];
      row.suggestion_loading = false;
      row.suggestion_query = '';
      renderLines();
      return;
    }

    var queryKey = [
      keyword.toUpperCase(),
      String(num(row.vendor_id) || ''),
      String(row.line_kind || '').toUpperCase(),
      normalizeToken(row.profile_brand),
      normalizeToken(row.profile_description)
    ].join('|');

    row.suggestion_loading = true;
    row.suggestion_query = queryKey;
    renderLines();

    var url = catalogSearchUrl + '?q=' + encodeURIComponent(keyword)
      + '&line_kind=' + encodeURIComponent(String(row.line_kind || '').toUpperCase())
      + '&limit=8';
    if (num(row.vendor_id) > 0) {
      url += '&vendor_id=' + encodeURIComponent(String(num(row.vendor_id)));
    }

    fetchJson(url)
      .then(function (response) {
        if (!requestLines[idx] || requestLines[idx].suggestion_query !== queryKey) {
          return;
        }
        if (!response || response.ok === false) {
          throw new Error((response && response.message) ? response.message : 'Gagal memuat saran profile.');
        }

        var kind = String(row.line_kind || '').toUpperCase();
        var items = Array.isArray(response.items) ? response.items : [];
        var filtered = items.filter(function (it) {
          var itemKind = String(it.line_kind || 'ITEM').toUpperCase();
          return (kind === '' || itemKind === kind) && String(it.profile_key || '').trim() !== '';
        }).map(function (it, listIdx) {
          return {
            row: it,
            score: suggestionScore(row, it),
            idx: listIdx
          };
        }).sort(function (a, b) {
          if (b.score !== a.score) {
            return b.score - a.score;
          }
          return a.idx - b.idx;
        }).slice(0, 3).map(function (entry) {
          return entry.row;
        });

        requestLines[idx].catalog_suggestions = filtered;
        requestLines[idx].suggestion_loading = false;
        renderLines();
      })
      .catch(function (error) {
        if (!requestLines[idx] || requestLines[idx].suggestion_query !== queryKey) {
          return;
        }
        requestLines[idx].catalog_suggestions = [];
        requestLines[idx].suggestion_loading = false;
        renderLines();
        flash('warning', error && error.message ? error.message : 'Gagal memuat saran profile.');
      });
  }

  function applyProfileSuggestion(idx, suggestion) {
    if (!requestLines[idx] || !suggestion) {
      return;
    }

    var row = requestLines[idx];
    var requestPrimaryQty = hasRequestModeSelection(row) ? requestQtyValue(row) : 0;
    var poPrimaryQty = hasRequestModeSelection(row) ? requestPoQtyValue(row) : 0;
    var currentMode = requestMode(row && row.request_uom_mode);

    row.line_kind = String(suggestion.line_kind || row.line_kind || 'ITEM').toUpperCase();
    row.item_id = num(suggestion.item_id) > 0 ? num(suggestion.item_id) : null;
    row.material_id = num(suggestion.material_id) > 0 ? num(suggestion.material_id) : null;
    row.profile_key = String(suggestion.profile_key || row.profile_key || '');
    row.profile_name = String(suggestion.catalog_name || suggestion.item_name || suggestion.material_name || row.profile_name || '');
    row.profile_brand = String(suggestion.brand_name || '');
    row.profile_description = String(suggestion.line_description || '');
    row.profile_expired_date = String(suggestion.expired_date || row.profile_expired_date || '');
    row.buy_uom_id = num(suggestion.buy_uom_id) > 0 ? num(suggestion.buy_uom_id) : row.buy_uom_id;
    row.content_uom_id = num(suggestion.content_uom_id) > 0 ? num(suggestion.content_uom_id) : row.content_uom_id;
    row.profile_content_per_buy = num(suggestion.content_per_buy || suggestion.conversion_factor_to_content || row.profile_content_per_buy || 1);
    if (row.profile_content_per_buy <= 0) {
      row.profile_content_per_buy = 1;
    }
    row.profile_buy_uom_code = String(suggestion.buy_uom_code || row.profile_buy_uom_code || '');
    row.profile_content_uom_code = String(suggestion.content_uom_code || row.profile_content_uom_code || '');
    row.estimated_unit_price = round2(num(suggestion.last_unit_price || suggestion.standard_price || row.estimated_unit_price));
    row.catalog_suggestions = [];
    row.suggestion_loading = false;
    row.suggestion_query = '';

    if (num(row.vendor_id) <= 0 && num(suggestion.vendor_id) > 0) {
      row.vendor_id = num(suggestion.vendor_id);
      row.vendor_name = vendorNameById(row.vendor_id);
    }

    if (hasRequestModeSelection(row)) {
      row.request_uom_mode = currentMode;
      syncRowTotalsFromRequest(row, requestPrimaryQty);
      if (poPrimaryQty > 0) {
        syncRowTotalsFromPo(row, poPrimaryQty, routePlan(row));
      }
    }

    renderLines();
    flash('success', 'Profile buyer dipakai. Split SR/PO final akan dihitung ulang saat verifikasi disimpan.');
  }

  function setLinesJson() {
    var el = document.getElementById('fieldLinesJson');
    if (el) {
      el.value = JSON.stringify(requestLines.map(function (row) {
        var payload = Object.assign({}, row || {});
        delete payload.catalog_suggestions;
        delete payload.suggestion_loading;
        delete payload.suggestion_query;
        return payload;
      }));
    }
  }

  function renderLines() {
    var tbody = document.getElementById('requestLineRows');
    if (!tbody) {
      return;
    }
    if (!requestLines.length) {
      tbody.innerHTML = '<tr><td colspan="' + lineColumnCount + '" class="text-center text-muted py-3">Belum ada line pengajuan.</td></tr>';
      setLinesJson();
      return;
    }

    var html = '';
    requestLines.forEach(function (row, idx) {
      var sourceType = String(row.source_type || 'WAREHOUSE').toUpperCase();
      var badgeClass = sourceType === 'WAREHOUSE' ? 'success' : (sourceType === 'MANUAL' ? 'warning text-dark' : 'info text-dark');
      var routeClass = routeBadgeClass(row);
      var plan = routePlan(row);
      var selectedMode = selectedRequestMode(row.request_uom_mode);
      if (!selectedMode) {
        var autoMode = defaultRequestModeForRow(row);
        if (autoMode !== '') {
          row.request_uom_mode = autoMode;
          selectedMode = autoMode;
        }
      }
      var mode = selectedMode || 'BUY';
      var hasMode = selectedMode !== '';
      var overStock = sourceType === 'WAREHOUSE' && plan.toPoContent > 0.00001;
      var canSplitWarehouse = sourceType === 'WAREHOUSE' && num(row.qty_content_balance) > 0.00001;
      var isDirectPo = plan.toSrContent <= 0.00001 && plan.toPoContent > 0.00001;
      var requestSummary = requestSecondarySummary(row);
      var poSummary = poSecondarySummary(row);
      var requestCaption = requestInputCaption(row, plan);
      var poCaption = poInputCaption(row, plan);
      var vendorRequired = requiresVendor(row, plan);
      var vendorValue = num(row.vendor_id) > 0 ? num(row.vendor_id) : '';
      var vendorCellHtml = '';
      var profileCellHtml = '<td class="dreq-profile-cell"><strong>' + esc(row.profile_name || '-') + '</strong><div class="small mt-1"><span class="badge bg-' + badgeClass + '">' + esc(sourceLabel(sourceType)) + '</span></div>';
      if (isVerifyMode) {
        var suggestionHtml = '<div class="small text-muted mt-2">Buyer bisa koreksi merk, keterangan, dan harga estimasi lalu klik Cari Saran Profil.</div>';
        if (row.suggestion_loading) {
          suggestionHtml = '<div class="small text-muted mt-2">Memuat saran profile...</div>';
        } else if (Array.isArray(row.catalog_suggestions) && row.catalog_suggestions.length) {
          suggestionHtml = '<div class="dreq-suggestion-list mt-2">' + renderProfileSuggestionButtons(idx, row.catalog_suggestions) + '</div>';
        }
        profileCellHtml += '<div class="dreq-profile-editor mt-2">'
          + '<div class="dreq-buyer-caption">Buyer Correction</div>'
          + '<input type="text" class="form-control form-control-sm line-profile-brand mb-1" data-idx="' + idx + '" value="' + esc(row.profile_brand || '') + '" placeholder="Merk final">'
          + '<input type="text" class="form-control form-control-sm line-profile-description mb-1" data-idx="' + idx + '" value="' + esc(row.profile_description || '') + '" placeholder="Keterangan/spec final">'
          + '<div class="input-group input-group-sm"><span class="input-group-text">Est</span><input type="number" min="0" step="0.01" class="form-control line-estimated-price" data-idx="' + idx + '" value="' + fixed2(row.estimated_unit_price || 0) + '" placeholder="Harga estimasi"><button type="button" class="btn btn-outline-primary line-profile-suggest" data-idx="' + idx + '">Cari Saran</button></div>'
          + suggestionHtml
          + '</div>';
      }
      profileCellHtml += '</td>';
      if (canAssignVendor) {
        vendorCellHtml = '<td class="dreq-vendor-cell"><div class="dreq-vendor-wrap"><select class="form-select form-select-sm line-vendor dreq-vendor-select" data-idx="' + idx + '"' + (vendorRequired ? '' : ' disabled') + '>'
          + renderVendorOptions(vendorValue)
          + '</select><button type="button" class="btn btn-sm btn-outline-primary line-vendor-add dreq-vendor-add" data-idx="' + idx + '" title="Tambah vendor baru">+</button></div><div class="dreq-vendor-meta mt-1">' + esc(vendorInputCaption(row, plan)) + '</div></td>';
      }
      var srCellHtml = isDirectPo
        ? '<div class="form-control form-control-sm dreq-qty-readonly text-center">-</div><div class="small text-muted mt-1">Langsung PO</div>'
        : '<input type="number" step="0.01" class="form-control form-control-sm dreq-qty-readonly" value="' + (hasMode ? fixed2(requestPlanQty(plan, row, 'SR')) : '') + '" readonly><div class="small text-muted mt-1">' + esc(hasMode ? requestUnitCode(row) : '-') + '</div>';
      var poInputClass = 'form-control form-control-sm line-po dreq-po-input' + ((canSplitWarehouse && requestPoQtyValue(row) > 0) ? ' is-active-po' : '');
      var poInputHtml = canSplitWarehouse
        ? '<input type="number" min="0.00" step="0.01" class="' + poInputClass + '" data-idx="' + idx + '" value="' + (hasMode ? fixed2(requestPoQtyValue(row)) : '') + '"' + (hasMode ? '' : ' disabled') + '><div class="small text-muted mt-1">' + esc(poCaption) + '</div>' + (hasMode && poSummary ? '<div class="small text-muted">~ ' + esc(poSummary) + '</div>' : '')
        : '<input type="number" step="0.01" class="form-control form-control-sm dreq-qty-readonly" value="' + (hasMode ? fixed2(requestPlanQty(plan, row, 'PO')) : '') + '" readonly><div class="small text-muted mt-1">' + esc(poCaption) + '</div>';
      html += '<tr>'
        + profileCellHtml
        + '<td>' + esc(row.line_kind || '-') + '</td>'
        + '<td><span class="badge bg-' + routeClass + '">' + esc(routeLabel(row)) + '</span><span class="dreq-route-note ' + (overStock ? 'dreq-stock-split' : '') + '">' + esc(routeNote(row)) + '</span></td>'
        + vendorCellHtml
        + '<td class="dreq-uom-cell">' + esc(row.profile_buy_uom_code || '-') + ' -> ' + esc(row.profile_content_uom_code || '-') + '</td>'
        + '<td class="text-end dreq-stock-cell">' + fixed2(row.qty_buy_balance) + (overStock ? '<div class="small dreq-stock-split">butuh lebih</div>' : '') + '</td>'
        + '<td class="text-end dreq-stock-cell">' + fixed2(row.qty_content_balance) + '</td>'
        + '<td class="dreq-request-cell"><div class="dreq-mode-caption">Mode Input</div><select class="form-select form-select-sm line-request-mode dreq-request-mode mb-1" data-idx="' + idx + '"><option value=""' + (!hasMode ? ' selected' : '') + '>Pilih mode input</option><option value="BUY"' + (mode === 'BUY' && hasMode ? ' selected' : '') + '>PACK / UOM Beli (' + esc(row.profile_buy_uom_code || '-') + ')</option><option value="CONTENT"' + (mode === 'CONTENT' && hasMode ? ' selected' : '') + '>ISI (' + esc(row.profile_content_uom_code || '-') + ')</option></select><input type="number" min="0.01" step="0.01" class="form-control form-control-sm line-request dreq-qty-input ' + (overStock ? 'is-overstock' : '') + '" data-idx="' + idx + '" value="' + (hasMode ? fixed2(requestQtyValue(row)) : '') + '"' + (hasMode ? '' : ' disabled') + '><div class="small text-muted mt-1">' + esc(requestCaption) + '</div>' + (hasMode && requestSummary ? '<div class="small text-muted">~ ' + esc(requestSummary) + '</div>' : '') + '</td>'
        + '<td>' + srCellHtml + '</td>'
        + '<td>' + poInputHtml + '</td>'
        + '<td><input type="number" step="0.01" class="form-control form-control-sm dreq-qty-readonly" value="' + fixed2(requestPlanQty(plan, row, 'PO')) + '" readonly><div class="small text-muted mt-1">' + esc(requestUnitCode(row)) + '</div></td>'
        + '<td><input type="number" step="0.01" class="form-control form-control-sm dreq-qty-readonly" value="' + fixed2(row.qty_content_requested) + '" readonly><div class="small text-muted mt-1">' + esc(row.profile_content_uom_code || '-') + '</div></td>'
        + '<td><input type="text" class="form-control form-control-sm line-notes dreq-notes-input" data-idx="' + idx + '" value="' + esc(row.notes || '') + '" placeholder="Opsional"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger line-del dreq-action-btn" data-idx="' + idx + '">Hapus</button></td>'
        + '</tr>';
    });
    tbody.innerHTML = html;
    setLinesJson();
  }

  function renderSearchResults(rows, source, allowManual, query) {
    var tbody = document.getElementById('searchResultRows');
    if (!tbody) {
      return;
    }
    toggleManualCard(allowManual, query);
    if (!rows || !rows.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data.</td></tr>';
      if (allowManual) {
        setSearchMeta('Stok gudang dan katalog tidak menemukan barang. Lanjut input manual untuk diarahkan ke PO.');
      } else {
        setSearchMeta('Tidak ada data untuk pencarian ini.');
      }
      return;
    }

    if (source === 'WAREHOUSE') {
      setSearchMeta('Menampilkan stok gudang saja. Jika kebutuhan total melebihi stok snapshot, isi Qty Request lebih besar dari stok dan sistem akan split otomatis: SR sesuai stok, sisanya ke PO.');
    } else {
      setSearchMeta('Stok gudang kosong untuk kata kunci ini, jadi sistem menampilkan fallback katalog/master. Line ini akan diarahkan ke PO bila tidak ada stok gudang saat proses.');
    }

    var html = '';
    rows.forEach(function (row) {
      var normalized = normalizeRow(row);
      var sourceType = String(row.source_type || source || 'CATALOG').toUpperCase();
      var badgeClass = sourceType === 'WAREHOUSE' ? 'success' : 'info text-dark';
      html += '<tr>'
        + '<td class="dreq-profile-cell"><strong>' + esc(normalized.profile_name || '-') + '</strong><div class="small mt-1"><span class="badge bg-' + badgeClass + '">' + esc(sourceLabel(sourceType)) + '</span></div></td>'
        + '<td class="dreq-uom-cell">' + esc(normalized.profile_buy_uom_code || '-') + ' -> ' + esc(normalized.profile_content_uom_code || '-') + '</td>'
        + '<td class="text-end dreq-stock-cell">' + fixed2(normalized.qty_buy_balance) + '</td>'
        + '<td class="text-end dreq-stock-cell">' + fixed2(normalized.qty_content_balance) + '</td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-primary dreq-action-btn search-pick" data-row="' + esc(JSON.stringify(normalized)) + '">Pilih</button></td>'
        + '</tr>';
    });
    tbody.innerHTML = html;
  }

  function addLine(row) {
    var normalized = normalizeRow(row);
    var key = lineKey(normalized);
    for (var i = 0; i < requestLines.length; i++) {
      if (lineKey(requestLines[i]) === key) {
        flash('warning', 'Profile ini sudah ada di line pengajuan.');
        return;
      }
    }
    requestLines.push(normalized);
    renderLines();
  }

  function selectCode(selectId) {
    var select = document.getElementById(selectId);
    if (!select || !select.options || select.selectedIndex < 0) {
      return '';
    }
    return select.options[select.selectedIndex].getAttribute('data-code') || '';
  }

  function addManualLine() {
    var name = String((document.getElementById('manualNameInput') || {}).value || '').trim();
    var description = String((document.getElementById('manualDescriptionInput') || {}).value || '').trim();
    var buyUomId = num((document.getElementById('manualBuyUomId') || {}).value);
    var contentUomId = num((document.getElementById('manualContentUomId') || {}).value);
    var contentPerBuy = num((document.getElementById('manualContentPerBuy') || {}).value);
    var qtyBuy = num((document.getElementById('manualQtyBuy') || {}).value);

    if (!name) {
      flash('warning', 'Nama barang manual wajib diisi.');
      return;
    }
    if (buyUomId <= 0 || contentUomId <= 0) {
      flash('warning', 'UOM beli dan UOM isi wajib dipilih untuk input manual.');
      return;
    }
    if (contentPerBuy <= 0 || qtyBuy <= 0) {
      flash('warning', 'Isi per beli dan qty beli manual harus lebih dari 0.');
      return;
    }

    addLine({
      source_type: 'MANUAL',
      line_kind: 'ITEM',
      item_id: null,
      material_id: null,
      profile_key: '',
      profile_name: name,
      profile_brand: '',
      profile_description: description,
      profile_expired_date: '',
      buy_uom_id: buyUomId,
      content_uom_id: contentUomId,
      profile_content_per_buy: contentPerBuy,
      profile_buy_uom_code: selectCode('manualBuyUomId'),
      profile_content_uom_code: selectCode('manualContentUomId'),
      qty_buy_requested: round2(qtyBuy),
      qty_content_requested: round2(qtyBuy * contentPerBuy),
      qty_buy_po_requested: round2(qtyBuy),
      qty_content_po_requested: round2(qtyBuy * contentPerBuy),
      qty_buy_balance: 0,
      qty_content_balance: 0,
      notes: ''
    });
  }

  function syncManualQtyContent() {
    var qtyBuy = num((document.getElementById('manualQtyBuy') || {}).value);
    var contentPerBuy = num((document.getElementById('manualContentPerBuy') || {}).value);
    var qtyContent = document.getElementById('manualQtyContent');
    if (!qtyContent) {
      return;
    }
    if (contentPerBuy <= 0) {
      contentPerBuy = 1;
    }
    qtyContent.value = fixed2(qtyBuy * contentPerBuy);
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (response) {
      return response.text().then(function (text) {
        var data = {};
        try {
          data = text ? JSON.parse(text) : {};
        } catch (error) {
          data = {};
        }
        if (!response.ok) {
          data.ok = false;
          data.message = data.message || ('Request gagal (' + response.status + ')');
        }
        return data;
      });
    });
  }

  var searchTimer = null;
  function runSearch() {
    var q = (document.getElementById('searchProfileInput') || {}).value || '';
    q = String(q).trim();
    if (!q) {
      renderSearchResults([], 'EMPTY', false, '');
      setSearchMeta('Masukkan kata kunci untuk mulai mencari barang.');
      return;
    }
    setSearchMeta('Memuat kandidat barang...');
    fetchJson(searchUrl + '?q=' + encodeURIComponent(q) + '&limit=20')
      .then(function (response) {
        if (!response || response.ok === false) {
          flash('danger', (response && response.message) ? response.message : 'Gagal memuat kandidat barang.');
          return;
        }
        renderSearchResults(response.rows || [], String(response.source || 'EMPTY').toUpperCase(), !!response.allow_manual, q);
      })
      .catch(function () {
        flash('danger', 'Gagal memuat kandidat barang.');
      });
  }

  var btnSearch = document.getElementById('btnSearchProfile');
  if (btnSearch) {
    btnSearch.addEventListener('click', function () {
      runSearch();
    });
  }

  var searchInput = document.getElementById('searchProfileInput');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      if (searchTimer) {
        clearTimeout(searchTimer);
      }
      searchTimer = setTimeout(runSearch, 250);
    });
    searchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        runSearch();
      }
    });
  }

  var divisionSelect = document.getElementById('fieldDivisionId');
  if (divisionSelect) {
    divisionSelect.addEventListener('change', applyDivisionDestinationGuard);
  }

  var btnManual = document.getElementById('btnAddManualLine');
  if (btnManual) {
    btnManual.addEventListener('click', function () {
      addManualLine();
    });
  }

  ['manualQtyBuy', 'manualContentPerBuy'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', syncManualQtyContent);
    }
  });

  document.addEventListener('click', function (event) {
    var pick = event.target.closest('.search-pick');
    if (pick) {
      event.preventDefault();
      try {
        addLine(JSON.parse(pick.getAttribute('data-row') || '{}'));
      } catch (error) {
        flash('danger', 'Data barang tidak valid.');
      }
      return;
    }

    var del = event.target.closest('.line-del');
    if (del) {
      event.preventDefault();
      var idx = parseInt(del.getAttribute('data-idx') || '-1', 10);
      if (idx >= 0) {
        requestLines.splice(idx, 1);
        renderLines();
      }
      return;
    }

    var addVendor = event.target.closest('.line-vendor-add');
    if (addVendor) {
      event.preventDefault();
      var vendorLineIdx = parseInt(addVendor.getAttribute('data-idx') || '-1', 10);
      if (vendorLineIdx >= 0 && requestLines[vendorLineIdx] && window.FinanceQuickVendor) {
        window.FinanceQuickVendor.open({
          title: 'Tambah Vendor PO',
          onCreated: function (vendor) {
            upsertVendorOption(vendor);
            requestLines[vendorLineIdx].vendor_id = num(vendor.id);
            requestLines[vendorLineIdx].vendor_name = String(vendor.vendor_name || vendor.vendor_code || '');
            renderLines();
            flash('success', 'Vendor baru siap dipakai untuk line PO ini.');
          }
        });
        return;
      }
    }

    var suggestProfile = event.target.closest('.line-profile-suggest');
    if (suggestProfile) {
      event.preventDefault();
      var suggestIdx = parseInt(suggestProfile.getAttribute('data-idx') || '-1', 10);
      if (suggestIdx >= 0) {
        fetchLineProfileSuggestions(suggestIdx);
      }
      return;
    }

    var applyProfile = event.target.closest('.btn-apply-profile-suggestion');
    if (applyProfile) {
      event.preventDefault();
      var applyIdx = parseInt(applyProfile.getAttribute('data-idx') || '-1', 10);
      var suggestionIdx = parseInt(applyProfile.getAttribute('data-suggestion-idx') || '-1', 10);
      if (applyIdx >= 0 && requestLines[applyIdx] && Array.isArray(requestLines[applyIdx].catalog_suggestions) && requestLines[applyIdx].catalog_suggestions[suggestionIdx]) {
        applyProfileSuggestion(applyIdx, requestLines[applyIdx].catalog_suggestions[suggestionIdx]);
      }
      return;
    }
  });

  document.addEventListener('input', function (event) {
    var requestInput = event.target.closest('.line-request');
    if (requestInput) {
      var requestIdx = parseInt(requestInput.getAttribute('data-idx') || '-1', 10);
      if (requestIdx >= 0 && requestLines[requestIdx] && hasRequestModeSelection(requestLines[requestIdx])) {
        syncRowTotalsFromRequest(requestLines[requestIdx], requestInput.value);
        setLinesJson();
      }
      return;
    }

    var po = event.target.closest('.line-po');
    if (po) {
      var poIdx = parseInt(po.getAttribute('data-idx') || '-1', 10);
      if (poIdx >= 0 && requestLines[poIdx] && hasRequestModeSelection(requestLines[poIdx])) {
        var previousPlan = routePlan(requestLines[poIdx]);
        syncRowTotalsFromPo(requestLines[poIdx], po.value, previousPlan);
        setLinesJson();
      }
      return;
    }

    var vendor = event.target.closest('.line-vendor');
    if (vendor) {
      var vendorIdx = parseInt(vendor.getAttribute('data-idx') || '-1', 10);
      if (vendorIdx >= 0 && requestLines[vendorIdx]) {
        requestLines[vendorIdx].vendor_id = num(vendor.value) > 0 ? num(vendor.value) : null;
        requestLines[vendorIdx].vendor_name = vendorNameById(vendor.value);
        setLinesJson();
      }
      return;
    }

    var notes = event.target.closest('.line-notes');
    if (notes) {
      var notesIdx = parseInt(notes.getAttribute('data-idx') || '-1', 10);
      if (notesIdx >= 0 && requestLines[notesIdx]) {
        requestLines[notesIdx].notes = notes.value || '';
        setLinesJson();
      }
      return;
    }

    var profileBrand = event.target.closest('.line-profile-brand');
    if (profileBrand) {
      var brandIdx = parseInt(profileBrand.getAttribute('data-idx') || '-1', 10);
      if (brandIdx >= 0 && requestLines[brandIdx]) {
        requestLines[brandIdx].profile_brand = profileBrand.value || '';
        setLinesJson();
      }
      return;
    }

    var profileDescription = event.target.closest('.line-profile-description');
    if (profileDescription) {
      var descIdx = parseInt(profileDescription.getAttribute('data-idx') || '-1', 10);
      if (descIdx >= 0 && requestLines[descIdx]) {
        requestLines[descIdx].profile_description = profileDescription.value || '';
        setLinesJson();
      }
      return;
    }

    var estimatedPrice = event.target.closest('.line-estimated-price');
    if (estimatedPrice) {
      var priceIdx = parseInt(estimatedPrice.getAttribute('data-idx') || '-1', 10);
      if (priceIdx >= 0 && requestLines[priceIdx]) {
        requestLines[priceIdx].estimated_unit_price = round2(Math.max(0, num(estimatedPrice.value)));
        setLinesJson();
      }
    }
  });

  document.addEventListener('change', function (event) {
    var mode = event.target.closest('.line-request-mode');
    if (mode) {
      var idx = parseInt(mode.getAttribute('data-idx') || '-1', 10);
      if (idx >= 0 && requestLines[idx]) {
        requestLines[idx].request_uom_mode = selectedRequestMode(mode.value);
        renderLines();
      }
      return;
    }

    var requestInput = event.target.closest('.line-request');
    if (requestInput) {
      var requestIdx = parseInt(requestInput.getAttribute('data-idx') || '-1', 10);
      if (requestIdx >= 0 && requestLines[requestIdx]) {
        if (!hasRequestModeSelection(requestLines[requestIdx])) {
          renderLines();
          return;
        }
        syncRowTotalsFromRequest(requestLines[requestIdx], requestInput.value);
        renderLines();
      }
      return;
    }

    var po = event.target.closest('.line-po');
    if (po) {
      var poIdx = parseInt(po.getAttribute('data-idx') || '-1', 10);
      if (poIdx >= 0 && requestLines[poIdx]) {
        if (!hasRequestModeSelection(requestLines[poIdx])) {
          renderLines();
          return;
        }
        var previousPlan = routePlan(requestLines[poIdx]);
        syncRowTotalsFromPo(requestLines[poIdx], po.value, previousPlan);
        renderLines();
      }
      return;
    }

    var vendor = event.target.closest('.line-vendor');
    if (vendor) {
      var vendorIdx = parseInt(vendor.getAttribute('data-idx') || '-1', 10);
      if (vendorIdx >= 0 && requestLines[vendorIdx]) {
        requestLines[vendorIdx].vendor_id = num(vendor.value) > 0 ? num(vendor.value) : null;
        requestLines[vendorIdx].vendor_name = vendorNameById(vendor.value);
        setLinesJson();
      }
    }
  });

  var form = document.getElementById('divisionRequestForm');
  if (form) {
    form.addEventListener('submit', function (event) {
      if (!requestLines.length) {
        event.preventDefault();
        flash('warning', 'Minimal 1 line pengajuan wajib diisi.');
        return;
      }
      for (var i = 0; i < requestLines.length; i++) {
        if (!hasRequestModeSelection(requestLines[i])) {
          event.preventDefault();
          flash('warning', 'Pilih mode input dulu untuk semua line pengajuan.');
          return;
        }
        if (canAssignVendor) {
          var currentPlan = routePlan(requestLines[i]);
          if (requiresVendor(requestLines[i], currentPlan) && num(requestLines[i].vendor_id) <= 0) {
            event.preventDefault();
            flash('warning', 'Pilih vendor untuk semua line yang masuk PO.');
            return;
          }
        }
      }
      setLinesJson();
      var submitButton = document.getElementById('btnSubmitDivisionRequest');
      if (submitButton) {
        submitButton.disabled = true;
      }
    });
  }

  requestLines = requestLines.map(function (row) {
    return normalizeRow(row || {});
  });
  setSearchMeta('Masukkan kata kunci untuk mulai mencari barang.');
  toggleManualCard(false, '');
  applyDivisionDestinationGuard();
  syncManualQtyContent();
  renderLines();
})();
</script>