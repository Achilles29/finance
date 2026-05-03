<?php
$editMode = !empty($edit_mode);
$detailData = (array)($detail ?? []);
$detailOrder = (array)($detailData['order'] ?? []);
$detailLines = (array)($detailData['lines'] ?? []);

$storeUrl = $editMode
  ? site_url('purchase/order/update/' . (int)($detailOrder['id'] ?? 0))
  : site_url('purchase/order/store');
$catalogUrl = site_url('purchase/catalog/search');
$catalogUrlFallback = base_url('index.php/purchase/catalog/search');
$syncCatalogUrl = site_url('purchase/catalog/sync-core');
$syncSetupUrl = site_url('purchase/setup/sync-core');
$syncAllUrl = site_url('purchase/setup/sync-core-all');

$initialRequestDate = $editMode ? (string)($detailOrder['request_date'] ?? date('Y-m-d')) : date('Y-m-d');
$initialPurchaseTypeId = (int)($detailOrder['purchase_type_id'] ?? 0);
$initialDestinationType = strtoupper(trim((string)($detailOrder['destination_type'] ?? '')));
$initialDestinationDivisionId = (int)($detailOrder['destination_division_id'] ?? 0);
$initialVendorId = (int)($detailOrder['vendor_id'] ?? 0);
$initialPaymentAccountId = (int)($detailOrder['payment_account_id'] ?? 0);
$initialStatus = strtoupper(trim((string)($detailOrder['status'] ?? 'DRAFT')));
$initialNotes = (string)($detailOrder['notes'] ?? '');
$purchaseTypeRows = array_values((array)($purchase_types ?? []));
$initialPurchaseTypeExists = false;
foreach ($purchaseTypeRows as $ptRow) {
  if ((int)($ptRow['id'] ?? 0) === $initialPurchaseTypeId) {
    $initialPurchaseTypeExists = true;
    break;
  }
}

$initialLines = [];
foreach ($detailLines as $ln) {
  $lineKind = strtoupper(trim((string)($ln['line_kind'] ?? 'ITEM')));
  $itemName = trim((string)($ln['snapshot_item_name'] ?? ''));
  $materialName = trim((string)($ln['snapshot_material_name'] ?? ''));
  $catalogName = $itemName !== '' ? $itemName : $materialName;

  $lineBrand = trim((string)($ln['brand_name'] ?? ''));
  if ($lineBrand === '') {
    $lineBrand = trim((string)($ln['snapshot_brand_name'] ?? ''));
  }

  $lineDesc = trim((string)($ln['line_description'] ?? ''));
  if ($lineDesc === '') {
    $lineDesc = trim((string)($ln['snapshot_line_description'] ?? ''));
  }

  $contentUomId = (int)($ln['content_uom_id'] ?? 0);
  $initialLines[] = [
    'line_kind' => $lineKind,
    'item_id' => !empty($ln['item_id']) ? (int)$ln['item_id'] : null,
    'material_id' => !empty($ln['material_id']) ? (int)$ln['material_id'] : null,
    'item_name' => $itemName,
    'material_name' => $materialName,
    'catalog_name' => $catalogName,
    'brand_name' => $lineBrand,
    'line_description' => $lineDesc,
    'buy_uom_id' => !empty($ln['buy_uom_id']) ? (int)$ln['buy_uom_id'] : null,
    'content_uom_id' => $contentUomId > 0 ? $contentUomId : null,
    'buy_uom_code' => (string)($ln['snapshot_buy_uom_code'] ?? ''),
    'content_uom_code' => (string)($ln['snapshot_content_uom_code'] ?? ''),
    'material_content_uom_id' => $lineKind === 'MATERIAL' && $contentUomId > 0 ? $contentUomId : null,
    'material_content_uom_code' => $lineKind === 'MATERIAL' ? (string)($ln['snapshot_content_uom_code'] ?? '') : '',
    'content_per_buy' => (float)($ln['content_per_buy'] ?? 0),
    'conversion_factor_to_content' => (float)($ln['conversion_factor_to_content'] ?? ($ln['content_per_buy'] ?? 0)),
    'unit_price' => (float)($ln['unit_price'] ?? 0),
    'qty_buy' => (float)($ln['qty_buy'] ?? 0),
    'discount_percent' => (float)($ln['discount_percent'] ?? 0),
    'tax_percent' => (float)($ln['tax_percent'] ?? 0),
    'notes' => (string)($ln['notes'] ?? ''),
  ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-shopping-cart-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted"><?php echo $editMode ? 'Edit data Purchase Order (header dan line).' : 'Adopsi pola CORE: halaman create untuk membuat Purchase Order.'; ?></small>
    <?php if ($editMode): ?>
      <div class="small mt-1">PO: <strong><?php echo html_escape((string)($detailOrder['po_no'] ?? '-')); ?></strong> | Status saat ini: <strong><?php echo html_escape($initialStatus); ?></strong></div>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Kembali ke Purchase Orders</a>
    <button type="button" id="btn-sync-core-all" class="btn btn-success">Sync Master Purchase dari Core</button>
    <button type="button" id="btn-sync-core-setup" class="btn btn-outline-info">Sync Posting/Purchase Type Core</button>
    <button type="button" id="btn-sync-core-catalog" class="btn btn-outline-success">Sync Catalog dari Core</button>
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-outline-primary">Halaman Receipt (opsional)</a>
  </div>
</div>

<div id="alert-area"></div>

<style>
  .catalog-preview-anchor {
    position: relative;
    z-index: 2200;
  }
  .catalog-preview-dropdown {
    position: absolute;
    left: 0;
    top: 0;
    width: 280px;
    z-index: 5000;
    background: #fff;
    border: 1px solid #d7dce1;
    border-radius: 8px;
    max-height: 280px;
    overflow: auto;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
  }
  .catalog-preview-item {
    display: block;
    width: 100%;
    border: 0;
    border-bottom: 1px solid #ebeef2;
    background: #fff;
    text-align: left;
    padding: 10px 12px;
    font-family: inherit;
    cursor: pointer;
  }
  .catalog-preview-item:hover {
    background: #f7fafc;
  }
  .catalog-preview-item:last-child {
    border-bottom: 0;
  }
  .catalog-preview-name {
    font-size: 14px;
    line-height: 1.3;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 2px;
  }
  .catalog-preview-meta {
    font-size: 12px;
    line-height: 1.35;
    color: #6b7280;
  }
  .po-header-card,
  .po-catalog-card {
    overflow: visible !important;
  }
  #po-line-table {
    min-width: 1280px;
  }
  #po-line-table th,
  #po-line-table td {
    white-space: nowrap;
    vertical-align: middle;
  }
  #po-line-table .line-name { min-width: 220px; }
  #po-line-table .line-material-name { min-width: 180px; }
  #po-line-table .line-brand { min-width: 120px; }
  #po-line-table .line-desc { min-width: 220px; }
  #po-line-table .line-buy-uom,
  #po-line-table .line-content-uom { min-width: 120px; }
  #po-line-table .line-qty,
  #po-line-table .line-content { min-width: 140px; }
  #po-line-table .line-price { min-width: 150px; }
  #po-line-table.line-mode-noninventory .line-inventory-col {
    display: none;
  }
</style>

<div class="card mb-3 po-header-card">
  <div class="card-body">
    <h6 class="mb-3">Header Purchase Order</h6>
    <form id="po-form" class="row g-3" autocomplete="off">
      <div class="col-md-3">
        <label class="form-label">Tanggal PO</label>
        <input type="date" class="form-control" id="request_date" value="<?php echo html_escape($initialRequestDate); ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Purchase Type</label>
        <select id="purchase_type_id" class="form-select" required>
          <option value="">Pilih Purchase Type...</option>
          <?php if ($editMode && $initialPurchaseTypeId > 0 && !$initialPurchaseTypeExists): ?>
            <option value="<?php echo $initialPurchaseTypeId; ?>" selected>
              <?php echo html_escape((string)($detailOrder['purchase_type_name'] ?? ('TYPE #' . $initialPurchaseTypeId))); ?> (non-aktif)
            </option>
          <?php endif; ?>
          <?php foreach ($purchaseTypeRows as $t): ?>
            <option
              value="<?php echo (int)$t['id']; ?>"
              <?php echo ((int)$t['id'] === $initialPurchaseTypeId) ? 'selected' : ''; ?>
              data-default-destination="<?php echo html_escape((string)($t['default_destination'] ?? '')); ?>"
              data-destination-behavior="<?php echo html_escape((string)($t['destination_behavior'] ?? 'REQUIRED')); ?>"
              data-affects-inventory="<?php echo (int)($t['affects_inventory'] ?? 0); ?>"
            >
              <?php echo html_escape((string)$t['type_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tujuan</label>
        <select id="destination_type" class="form-select" disabled>
          <option value="">(opsional)</option>
          <option value="GUDANG" <?php echo $initialDestinationType === 'GUDANG' ? 'selected' : ''; ?>>GUDANG</option>
          <option value="BAR" <?php echo $initialDestinationType === 'BAR' ? 'selected' : ''; ?>>BAR</option>
          <option value="KITCHEN" <?php echo $initialDestinationType === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
          <option value="BAR_EVENT" <?php echo $initialDestinationType === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
          <option value="KITCHEN_EVENT" <?php echo $initialDestinationType === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
          <option value="OFFICE" <?php echo $initialDestinationType === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
          <option value="OTHER" <?php echo $initialDestinationType === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Divisi Tujuan</label>
        <input type="text" id="destination_division_label" class="form-control" value="-" readonly>
        <input type="hidden" id="destination_division_id" value="<?php echo $initialDestinationDivisionId > 0 ? (int)$initialDestinationDivisionId : ''; ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Vendor</label>
        <select id="vendor_id" class="form-select">
          <option value="">Pilih Vendor...</option>
          <?php foreach (($vendors ?? []) as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>" <?php echo ((int)$v['id'] === $initialVendorId) ? 'selected' : ''; ?>><?php echo html_escape((string)$v['vendor_code'] . ' - ' . (string)$v['vendor_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Metode Pembayaran (Rekening)</label>
        <select id="payment_account_id" class="form-select">
          <option value="">(opsional)</option>
          <?php foreach (($payment_accounts ?? []) as $a): ?>
            <?php
              $bank = trim((string)($a['bank_name'] ?? ''));
              $accNo = trim((string)($a['account_no'] ?? ''));
              $baseLabel = (string)($a['account_code'] ?? '') . ' - ' . (string)($a['account_name'] ?? '');
              if ($bank !== '') {
                $baseLabel .= ' | ' . $bank;
              }
              if ($accNo !== '') {
                $baseLabel .= ' (' . $accNo . ')';
              }
            ?>
            <option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)$a['id'] === $initialPaymentAccountId) ? 'selected' : ''; ?>><?php echo html_escape($baseLabel); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select id="status" class="form-select" <?php echo $editMode ? 'disabled' : ''; ?>>
          <?php foreach (($status_options ?? ['DRAFT']) as $st): ?>
            <?php $stValue = strtoupper((string)$st); ?>
            <option value="<?php echo html_escape($stValue); ?>" <?php echo $stValue === $initialStatus ? 'selected' : ''; ?>><?php echo html_escape($stValue); ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($editMode): ?>
          <small class="text-muted">Status diubah lewat aksi Update Status, bukan dari form edit data.</small>
        <?php endif; ?>
      </div>
      <div class="col-12">
        <label class="form-label">Notes</label>
        <input type="text" id="po_notes" class="form-control" value="<?php echo html_escape($initialNotes); ?>">
      </div>
    </form>
  </div>
</div>

<div class="card mb-3 po-catalog-card">
  <div class="card-body">
    <h6 class="mb-3">Tambah Line dari Catalog</h6>
    <div class="row g-2">
      <div class="col-md-8 catalog-preview-anchor">
        <label class="form-label">Cari Catalog (Ajax Preview)</label>
        <input type="text" id="catalog_keyword" class="form-control" placeholder="Ketik nama item/material/merk/keterangan, lalu pilih dari preview...">
        <div id="catalog-preview-wrap" class="catalog-preview-dropdown" style="display:none;">
          <div id="catalog-preview-list"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">Line Purchase Order</h6>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-line">Tambah Baris</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped" id="po-line-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>Merk</th>
            <th>Keterangan</th>
            <th class="line-inventory-col">UOM Beli</th>
            <th class="line-inventory-col">UOM Isi</th>
            <th class="text-end line-inventory-col">Qty Beli</th>
            <th class="text-end line-inventory-col">Isi/Beli</th>
            <th class="text-end">Harga</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="10" class="text-center text-muted py-3">Belum ada line.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end mt-3 gap-2">
      <button class="btn btn-primary" type="button" id="btn-save-po"><?php echo $editMode ? 'Update Purchase Order' : 'Simpan Purchase Order'; ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  var editMode = <?php echo $editMode ? 'true' : 'false'; ?>;
  var orderId = <?php echo (int)($detailOrder['id'] ?? 0); ?>;
  var detailUrl = <?php echo json_encode(site_url('purchase-orders/detail/' . (int)($detailOrder['id'] ?? 0))); ?>;
  var initialLines = <?php echo json_encode(array_values($initialLines)); ?>;
  var divisions = <?php echo json_encode(array_values((array)($divisions ?? []))); ?>;
  var uoms = <?php echo json_encode(array_values((array)($uoms ?? []))); ?>;
  var catalogUrl = <?php echo json_encode($catalogUrl); ?>;
  var catalogUrlFallback = <?php echo json_encode($catalogUrlFallback); ?>;
  var storeUrl = <?php echo json_encode($storeUrl); ?>;
  var syncCatalogUrl = <?php echo json_encode($syncCatalogUrl); ?>;
  var syncSetupUrl = <?php echo json_encode($syncSetupUrl); ?>;
  var syncAllUrl = <?php echo json_encode($syncAllUrl); ?>;
  var catalogPreviewWrap = document.getElementById('catalog-preview-wrap');
  var catalogPreviewList = document.getElementById('catalog-preview-list');
  var catalogKeywordEl = document.getElementById('catalog_keyword');
  var lineTbody = document.querySelector('#po-line-table tbody');
  var alertArea = document.getElementById('alert-area');
  var lines = [];
  var isInventoryType = true;
  var divisionCodeToId = {};
  var uomById = {};
  var previewTimer = null;
  var previewAbortController = null;
  var activeLineIdx = 0;

  if (catalogPreviewWrap && catalogPreviewWrap.parentNode !== document.body) {
    document.body.appendChild(catalogPreviewWrap);
  }

  divisions.forEach(function (d) {
    var id = Number(d && d.id ? d.id : 0);
    var code = String((d && d.code ? d.code : d && d.name ? d.name : '') || '').trim().toUpperCase();
    if (id > 0 && code) {
      divisionCodeToId[code] = id;
    }
  });

  uoms.forEach(function (u) {
    var id = Number(u && u.id ? u.id : 0);
    if (id > 0) {
      uomById[id] = {
        id: id,
        code: String(u && u.code ? u.code : ''),
        name: String(u && u.name ? u.name : '')
      };
    }
  });

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }

  function num(v) {
    var n = Number(v || 0);
    return Number.isFinite(n) ? n : 0;
  }

  function alertMsg(type, msg) {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function buildUomOptions(selectedId) {
    var sid = Number(selectedId || 0);
    var html = '<option value="">-</option>';
    Object.keys(uomById).forEach(function (k) {
      var id = Number(k);
      var row = uomById[id] || {};
      var code = String(row.code || '').trim();
      var name = String(row.name || '').trim();
      var label = code || name;
      html += '<option value="' + id + '"' + (id === sid ? ' selected' : '') + '>' + esc(label) + '</option>';
    });
    return html;
  }

  function firstUomId() {
    var ids = Object.keys(uomById)
      .map(function (k) { return Number(k); })
      .filter(function (id) { return id > 0; })
      .sort(function (a, b) { return a - b; });
    return ids.length ? ids[0] : null;
  }

  function applyLineTypeDefaults(line) {
    if (!line || isInventoryType) {
      return;
    }

    var defaultUom = firstUomId();
    if (!num(line.buy_uom_id) && defaultUom) {
      line.buy_uom_id = defaultUom;
      line.buy_uom_code = uomById[defaultUom] ? String(uomById[defaultUom].code || '') : '';
    }
    if (!num(line.content_uom_id) && defaultUom) {
      line.content_uom_id = defaultUom;
      line.content_uom_code = uomById[defaultUom] ? String(uomById[defaultUom].code || '') : '';
    }
    if (num(line.qty_buy) <= 0) {
      line.qty_buy = 1;
    }
    if (num(line.content_per_buy) <= 0) {
      line.content_per_buy = 1;
      line.conversion_factor_to_content = 1;
    }
  }

  function applyLineModeByPurchaseType() {
    var table = document.getElementById('po-line-table');
    if (!table) {
      return;
    }

    if (isInventoryType) {
      table.classList.remove('line-mode-noninventory');
    } else {
      table.classList.add('line-mode-noninventory');
    }

    lines.forEach(function (line) {
      applyLineTypeDefaults(line);
    });
    refreshLines();
  }

  function createEmptyLine() {
    return {
      line_kind: 'ITEM',
      item_id: null,
      material_id: null,
      selected_display_name: '',
      item_name: '',
      material_name: '',
      catalog_name: '',
      brand_name: '',
      line_description: '',
      buy_uom_id: null,
      content_uom_id: null,
      buy_uom_code: '',
      content_uom_code: '',
      material_content_uom_id: null,
      material_content_uom_code: '',
      content_per_buy: 0,
      conversion_factor_to_content: 0,
      unit_price: 0,
      qty_buy: 0,
      discount_percent: 0,
      tax_percent: 0,
      notes: ''
    };
  }

  function addEmptyLine(focusNewRow) {
    lines.push(createEmptyLine());
    applyLineTypeDefaults(lines[lines.length - 1]);
    refreshLines();
    activeLineIdx = lines.length - 1;
    if (focusNewRow) {
      window.setTimeout(function () {
        var tr = lineTbody.querySelector('tr[data-idx="' + activeLineIdx + '"]');
        var nameInput = tr ? tr.querySelector('.line-name') : null;
        if (nameInput) {
          nameInput.focus();
        }
      }, 0);
    }
  }

  function isLineEffectivelyEmpty(line) {
    return !num(line.item_id)
      && !num(line.material_id)
      && String(line.catalog_name || '').trim() === ''
      && String(line.brand_name || '').trim() === ''
      && String(line.line_description || '').trim() === ''
      && num(line.unit_price) <= 0;
  }

  function findFirstEmptyLineIndex() {
    for (var i = 0; i < lines.length; i++) {
      if (isLineEffectivelyEmpty(lines[i])) {
        return i;
      }
    }
    return -1;
  }

  function applyCatalogToLine(idx, it) {
    var lineKind = String(it.line_kind || 'ITEM').toUpperCase();
    var existing = lines[idx] || createEmptyLine();
    var selectedName = it.catalog_name || it.item_name || it.material_name || '';
    lines[idx] = {
      line_kind: lineKind,
      item_id: it.item_id || null,
      material_id: it.material_id || null,
      selected_display_name: selectedName,
      item_name: it.item_name || '',
      material_name: it.material_name || '',
      catalog_name: selectedName,
      brand_name: it.brand_name || '',
      line_description: it.line_description || '',
      buy_uom_id: it.buy_uom_id || null,
      content_uom_id: it.content_uom_id || null,
      buy_uom_code: it.buy_uom_code || '',
      content_uom_code: it.content_uom_code || '',
      material_content_uom_id: lineKind === 'MATERIAL' ? (it.content_uom_id || null) : null,
      material_content_uom_code: lineKind === 'MATERIAL' ? (it.content_uom_code || '') : '',
      content_per_buy: num(it.content_per_buy || 0),
      conversion_factor_to_content: num(it.conversion_factor_to_content || it.content_per_buy || 0),
      unit_price: num(it.last_unit_price || it.standard_price || 0),
      qty_buy: num(existing.qty_buy || 0),
      discount_percent: 0,
      tax_percent: 0,
      notes: existing.notes || ''
    };
    if (num(lines[idx].qty_buy) <= 0) {
      lines[idx].qty_buy = 1;
    }
    if (num(lines[idx].content_per_buy) <= 0) {
      lines[idx].content_per_buy = 1;
      lines[idx].conversion_factor_to_content = 1;
    }
    applyLineTypeDefaults(lines[idx]);
  }

  function parseJsonResponse(response) {
    return response.text().then(function (txt) {
      var parsed = null;
      try {
        parsed = JSON.parse(txt);
      } catch (e) {
        var firstBrace = (txt || '').indexOf('{');
        var lastBrace = (txt || '').lastIndexOf('}');
        if (firstBrace >= 0 && lastBrace > firstBrace) {
          try {
            parsed = JSON.parse((txt || '').slice(firstBrace, lastBrace + 1));
          } catch (ignored) {
            parsed = null;
          }
        }
      }

      if (parsed === null) {
        var snippet = (txt || '').replace(/\s+/g, ' ').trim().slice(0, 160);
        throw new Error('Respons server bukan JSON valid (HTTP ' + response.status + '). ' + snippet);
      }

      return { status: response.status, json: parsed };
    });
  }

  function refreshLines() {
    if (!lines.length) {
      lineTbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-3">Belum ada line.</td></tr>';
      return;
    }

    var html = [];
    lines.forEach(function (l, idx) {
      var name = l.catalog_name || l.item_name || l.material_name || '-';
      var kind = String(l.line_kind || '-').toUpperCase();
      var showItemMaterialDetail = (kind === 'ITEM' || kind === 'MATERIAL');
      var brand = showItemMaterialDetail ? (l.brand_name || '-') : '-';
      var desc = showItemMaterialDetail ? (l.line_description || '-') : '-';
      var buyUom = l.buy_uom_code || '-';
      var contentUom = l.content_uom_code || '-';
      var nameInput = (l.catalog_name || l.item_name || l.material_name || '');
      var buyUomId = Number(l.buy_uom_id || 0);
      var contentUomId = Number(l.content_uom_id || 0);
      var materialLocked = (kind === 'MATERIAL');
      var invDisabled = !isInventoryType;
      var materialNameInput = (l.material_name || '');
      var showMaterialForm = materialLocked || (Number(l.material_id || 0) > 0);
      if (materialLocked && Number(l.material_content_uom_id || 0) > 0) {
        contentUomId = Number(l.material_content_uom_id || 0);
      }
      html.push('<tr data-idx="' + idx + '">' +
        '<td>' + (idx + 1) + '</td>' +
        '<td><div class="d-flex gap-1 align-items-center"><input type="text" class="form-control form-control-sm line-name" value="' + esc(nameInput) + '">' +
        '<input type="text" class="form-control form-control-sm line-material-name" value="' + esc(materialNameInput) + '" placeholder="Form Bahan Baku" readonly' + (showMaterialForm ? '' : ' style="display:none;"') + '></div></td>' +
        '<td><input type="text" class="form-control form-control-sm line-brand" value="' + esc(brand === '-' ? '' : brand) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm line-desc" value="' + esc(desc === '-' ? '' : desc) + '"></td>' +
        '<td class="line-inventory-col"><select class="form-select form-select-sm line-buy-uom"' + (invDisabled ? ' disabled' : '') + '>' + buildUomOptions(buyUomId) + '</select></td>' +
        '<td class="line-inventory-col"><select class="form-select form-select-sm line-content-uom"' + ((materialLocked || invDisabled) ? ' disabled' : '') + '>' + buildUomOptions(contentUomId) + '</select></td>' +
        '<td class="line-inventory-col"><input type="number" class="form-control form-control-sm line-qty text-end" min="0" step="0.01" value="' + num(l.qty_buy || 0).toFixed(2) + '"' + (invDisabled ? ' disabled' : '') + '></td>' +
        '<td class="line-inventory-col"><input type="number" class="form-control form-control-sm line-content text-end" min="0" step="0.01" value="' + num(l.content_per_buy || 0).toFixed(2) + '"' + (invDisabled ? ' disabled' : '') + '></td>' +
        '<td><input type="number" class="form-control form-control-sm line-price text-end" min="0" step="0.01" value="' + num(l.unit_price || l.last_unit_price || 0).toFixed(2) + '"></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-line">Hapus</button></td>' +
      '</tr>');
    });

    lineTbody.innerHTML = html.join('');
  }

  function renderCatalogPreview(items) {
    window.requestAnimationFrame(positionCatalogPreview);

    if (!items.length) {
      catalogPreviewList.innerHTML = '<div class="p-2 text-muted">Data tidak ditemukan.</div>';
      catalogPreviewWrap.style.display = 'block';
      return;
    }

    var rows = [];
    items.forEach(function (it, idx) {
      var name = it.catalog_name || it.item_name || it.material_name || '-';
      var profile = 'Merk: ' + (it.brand_name || '-') + ' | Ket: ' + (it.line_description || '-');
      var buyUom = String(it.buy_uom_code || '-');
      var contentUom = String(it.content_uom_code || '-');
      var uom = buyUom === contentUom ? buyUom : (buyUom + ' -> ' + contentUom);
      rows.push('<button type="button" class="catalog-preview-item" data-idx="' + idx + '">' +
        '<div class="catalog-preview-name">' + esc(name) + '</div>' +
        '<div class="catalog-preview-meta">' + esc(profile + ' | ' + uom + ' | Harga: ' + num(it.last_unit_price || it.standard_price || 0).toFixed(2)) + '</div>' +
      '</button>');
    });
    catalogPreviewList.innerHTML = rows.join('');
    catalogPreviewWrap.style.display = 'block';

    catalogPreviewList.querySelectorAll('.catalog-preview-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = Number(btn.getAttribute('data-idx') || -1);
        if (idx >= 0 && items[idx]) {
          var targetIdx = (activeLineIdx >= 0 && lines[activeLineIdx]) ? activeLineIdx : -1;
          if (targetIdx < 0 || !isLineEffectivelyEmpty(lines[targetIdx])) {
            targetIdx = findFirstEmptyLineIndex();
          }
          if (targetIdx < 0) {
            addEmptyLine(false);
            targetIdx = lines.length - 1;
          }
          applyCatalogToLine(targetIdx, items[idx]);
          activeLineIdx = targetIdx;
          refreshLines();
          catalogKeywordEl.value = '';
          catalogPreviewWrap.style.display = 'none';
          catalogPreviewList.innerHTML = '';
        }
      });
    });
  }

  function positionCatalogPreview() {
    if (!catalogPreviewWrap || !catalogKeywordEl) {
      return;
    }

    var rect = catalogKeywordEl.getBoundingClientRect();
    if (!rect || rect.width <= 0 || rect.height <= 0) {
      return;
    }

    var left = Math.max(8, Math.round(window.pageXOffset + rect.left));
    var top = Math.round(window.pageYOffset + rect.bottom + 4);
    var width = Math.max(280, Math.round(rect.width));

    catalogPreviewWrap.style.left = left + 'px';
    catalogPreviewWrap.style.top = top + 'px';
    catalogPreviewWrap.style.width = width + 'px';
  }

  function fetchCatalogPreviewWithUrl(urlBase, retryOnFallback) {
    var q = catalogKeywordEl.value || '';
    var vendorId = document.getElementById('vendor_id').value || '';

    if (q.trim().length < 2) {
      catalogPreviewWrap.style.display = 'none';
      catalogPreviewList.innerHTML = '';
      return;
    }

    var url = urlBase + '?q=' + encodeURIComponent(q)
      + '&vendor_id=' + encodeURIComponent(vendorId)
      + '&limit=15';

    if (previewAbortController) {
      previewAbortController.abort();
    }
    previewAbortController = new AbortController();

    fetch(url, { headers: { 'Accept': 'application/json' }, signal: previewAbortController.signal })
      .then(parseJsonResponse)
      .then(function (res) {
        if (res.status >= 400 || !res.json || !res.json.ok) {
          throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal cari catalog');
        }
        var items = Array.isArray(res.json.items) ? res.json.items : [];
        renderCatalogPreview(items);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return;
        }
        if (retryOnFallback) {
          var pathname = String(window.location.pathname || '');
          var rootBase = pathname;
          if (rootBase.indexOf('/index.php/') >= 0) {
            rootBase = rootBase.split('/index.php/')[0];
          } else if (rootBase.indexOf('/purchase-orders') >= 0) {
            rootBase = rootBase.split('/purchase-orders')[0];
          } else if (rootBase.indexOf('/purchase') >= 0) {
            rootBase = rootBase.split('/purchase')[0];
          }
          if (rootBase.length > 1 && rootBase[rootBase.length - 1] === '/') {
            rootBase = rootBase.slice(0, -1);
          }

          var candidates = [catalogUrlFallback];
          if (rootBase) {
            candidates.push(rootBase + '/index.php/purchase/catalog/search');
            candidates.push(rootBase + '/purchase/catalog/search');
          }

          var tried = {};
          tried[urlBase] = true;
          for (var i = 0; i < candidates.length; i++) {
            var candidate = String(candidates[i] || '');
            if (candidate && !tried[candidate]) {
              fetchCatalogPreviewWithUrl(candidate, false);
              return;
            }
          }

          return;
        }
        catalogPreviewWrap.style.display = 'none';
        catalogPreviewList.innerHTML = '';
        alertMsg('danger', err.message || 'Gagal cari catalog.');
      });
  }

  function fetchCatalogPreview() {
    fetchCatalogPreviewWithUrl(catalogUrl, true);
  }

  catalogKeywordEl.addEventListener('input', function () {
    if (previewTimer) {
      window.clearTimeout(previewTimer);
    }
    previewTimer = window.setTimeout(fetchCatalogPreview, 250);
  });

  catalogKeywordEl.addEventListener('focus', function () {
    if ((catalogPreviewList.innerHTML || '').trim() === '') {
      return;
    }
    window.requestAnimationFrame(positionCatalogPreview);
    catalogPreviewWrap.style.display = 'block';
  });

  window.addEventListener('resize', positionCatalogPreview);
  window.addEventListener('scroll', function () {
    if (catalogPreviewWrap.style.display !== 'none') {
      positionCatalogPreview();
    }
  }, true);

  document.getElementById('btn-sync-core-catalog').addEventListener('click', function () {
    if (!window.confirm('Sinkronisasi catalog dari core sekarang?')) {
      return;
    }

    fetch(syncCatalogUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ limit: 2000 })
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal sinkron catalog.');
      }
      var d = res.json.data || {};
      alertMsg('success', 'Sinkron catalog selesai. Total catalog: ' + (d.catalog_total || 0));
    })
    .catch(function (err) {
      alertMsg('danger', err.message || 'Gagal sinkron catalog.');
    });
  });

  document.getElementById('btn-sync-core-all').addEventListener('click', function () {
    if (!window.confirm('Sinkron semua master purchase dari core (catalog + posting type + purchase type)?')) {
      return;
    }

    fetch(syncAllUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ limit: 3000 })
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal sinkron master purchase dari core.');
      }

      var setup = (res.json.data && res.json.data.setup) ? res.json.data.setup : {};
      var catalog = (res.json.data && res.json.data.catalog) ? res.json.data.catalog : {};
      alertMsg('success',
        'Sync selesai. Posting Type: ' + (setup.posting_type_total || 0)
        + ', Purchase Type: ' + (setup.purchase_type_total || 0)
        + ', Catalog: ' + (catalog.catalog_total || 0)
      );
    })
    .catch(function (err) {
      alertMsg('danger', err.message || 'Gagal sinkron master purchase dari core.');
    });
  });

  document.getElementById('btn-sync-core-setup').addEventListener('click', function () {
    if (!window.confirm('Sinkron posting type dan purchase type dari core sekarang?')) {
      return;
    }

    fetch(syncSetupUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ limit: 2000 })
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal sinkron posting/purchase type.');
      }
      var d = res.json.data || {};
      alertMsg('success', 'Sinkron setup selesai. Posting Type: ' + (d.posting_type_total || 0) + ', Purchase Type: ' + (d.purchase_type_total || 0));
      window.setTimeout(function () { window.location.reload(); }, 1000);
    })
    .catch(function (err) {
      alertMsg('danger', err.message || 'Gagal sinkron posting/purchase type.');
    });
  });

  lineTbody.addEventListener('click', function (e) {
    if (!e.target.classList.contains('btn-remove-line')) return;
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx >= 0) {
      lines.splice(idx, 1);
      if (!lines.length) {
        addEmptyLine(false);
      } else if (activeLineIdx >= lines.length) {
        activeLineIdx = lines.length - 1;
      }
      refreshLines();
    }
  });

  lineTbody.addEventListener('input', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx < 0 || !lines[idx]) return;
    activeLineIdx = idx;

    if (e.target.classList.contains('line-name')) lines[idx].catalog_name = e.target.value || '';
    if (e.target.classList.contains('line-brand')) lines[idx].brand_name = e.target.value || '';
    if (e.target.classList.contains('line-desc')) lines[idx].line_description = e.target.value || '';
    if (e.target.classList.contains('line-qty')) lines[idx].qty_buy = num(e.target.value);
    if (e.target.classList.contains('line-content')) {
      lines[idx].content_per_buy = num(e.target.value);
      lines[idx].conversion_factor_to_content = num(e.target.value);
    }
    if (e.target.classList.contains('line-price')) lines[idx].unit_price = num(e.target.value);
  });

  lineTbody.addEventListener('focusin', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx >= 0) {
      activeLineIdx = idx;
    }
  });

  lineTbody.addEventListener('change', function (e) {
    var tr = e.target.closest('tr');
    var idx = Number(tr ? tr.getAttribute('data-idx') : -1);
    if (idx < 0 || !lines[idx]) return;

    if (e.target.classList.contains('line-name')) {
      var typedName = String(e.target.value || '').trim();
      var linkedName = String(lines[idx].selected_display_name || '').trim();
      if (
        linkedName !== '' &&
        typedName.toUpperCase() !== linkedName.toUpperCase()
      ) {
        lines[idx].catalog_name = typedName;
        lines[idx].line_kind = 'ITEM';
        lines[idx].item_id = null;
        lines[idx].material_id = null;
        lines[idx].item_name = '';
        lines[idx].material_name = '';
        lines[idx].material_content_uom_id = null;
        lines[idx].material_content_uom_code = '';
        lines[idx].selected_display_name = '';

        if (num(lines[idx].qty_buy) <= 0) {
          lines[idx].qty_buy = 1;
        }
        if (num(lines[idx].content_per_buy) <= 0) {
          lines[idx].content_per_buy = 1;
          lines[idx].conversion_factor_to_content = 1;
        }

        refreshLines();
        var trUpdated = lineTbody.querySelector('tr[data-idx="' + idx + '"]');
        var nameInput = trUpdated ? trUpdated.querySelector('.line-name') : null;
        if (nameInput) {
          nameInput.focus();
          nameInput.value = typedName;
        }

        alertMsg('warning', 'Nama baris diubah manual. Link item/material sebelumnya dilepas agar tidak mismatch data.');
      }
      return;
    }

    if (e.target.classList.contains('line-buy-uom')) {
      if (!isInventoryType) {
        return;
      }
      var prevBuyId = num(lines[idx].buy_uom_id || 0);
      var buyId = num(e.target.value);
      if (prevBuyId > 0 && buyId > 0 && prevBuyId !== buyId) {
        var yes = window.confirm('Yakin merubah UOM beli? Perubahan ini dapat membuat profile baru.');
        if (!yes) {
          e.target.value = String(prevBuyId);
          return;
        }
      }
      lines[idx].buy_uom_id = buyId > 0 ? buyId : null;
      lines[idx].buy_uom_code = (buyId > 0 && uomById[buyId]) ? (uomById[buyId].code || '') : '';
    }
    if (e.target.classList.contains('line-content-uom')) {
      if (!isInventoryType) {
        return;
      }
      var kind = String(lines[idx].line_kind || '').toUpperCase();
      if (kind === 'MATERIAL') {
        var lockId = num(lines[idx].material_content_uom_id || lines[idx].content_uom_id || 0);
        if (lockId > 0) {
          e.target.value = String(lockId);
        }
        alertMsg('warning', 'UOM isi untuk MATERIAL harus mengikuti master material.');
        return;
      }
      var contentId = num(e.target.value);
      lines[idx].content_uom_id = contentId > 0 ? contentId : null;
      lines[idx].content_uom_code = (contentId > 0 && uomById[contentId]) ? (uomById[contentId].code || '') : '';
    }
  });

  function divisionCodeByDestination(dest) {
    var d = String(dest || '').toUpperCase();
    if (d === 'BAR' || d === 'BAR_EVENT') return 'BAR';
    if (d === 'KITCHEN' || d === 'KITCHEN_EVENT') return 'KITCHEN';
    if (d === 'OFFICE') return 'MANAJEMEN';
    return '';
  }

  function applyPurchaseTypeRules(preserveDestinationSelection) {
    var typeEl = document.getElementById('purchase_type_id');
    var destEl = document.getElementById('destination_type');
    var divLabelEl = document.getElementById('destination_division_label');
    var divIdEl = document.getElementById('destination_division_id');
    var destWrap = destEl ? destEl.closest('.col-md-3') : null;
    var divWrap = divLabelEl ? divLabelEl.closest('.col-md-3') : null;
    var opt = typeEl.options[typeEl.selectedIndex];

    var behavior = opt ? String(opt.getAttribute('data-destination-behavior') || 'REQUIRED').toUpperCase() : 'REQUIRED';
    var defaultDest = opt ? String(opt.getAttribute('data-default-destination') || '').toUpperCase() : '';
    var affectsInventory = opt ? Number(opt.getAttribute('data-affects-inventory') || 0) : 0;
    isInventoryType = affectsInventory === 1 && behavior !== 'NONE';

    if (affectsInventory !== 1 || behavior === 'NONE') {
      if (destWrap) destWrap.style.display = 'none';
      if (divWrap) divWrap.style.display = 'none';
      destEl.value = '';
      divLabelEl.value = '-';
      divIdEl.value = '';
      applyLineModeByPurchaseType();
      return;
    }

    if (destWrap) destWrap.style.display = '';
    if (divWrap) divWrap.style.display = '';

    var currentDest = String(destEl.value || '').toUpperCase();
    if (preserveDestinationSelection && currentDest !== '') {
      destEl.value = currentDest;
    } else {
      destEl.value = defaultDest || 'GUDANG';
    }

    var divisionCode = divisionCodeByDestination(destEl.value);
    var divisionId = divisionCode ? Number(divisionCodeToId[divisionCode] || 0) : 0;
    var currentDivisionId = Number(divIdEl.value || 0);
    if (preserveDestinationSelection && currentDivisionId > 0) {
      divisionId = currentDivisionId;
    }
    divLabelEl.value = divisionCode || '-';
    divIdEl.value = divisionId > 0 ? String(divisionId) : '';
    applyLineModeByPurchaseType();
  }

  document.getElementById('purchase_type_id').addEventListener('change', function () {
    applyPurchaseTypeRules(false);
  });

  document.getElementById('btn-save-po').addEventListener('click', function () {
    var submitLines = lines.filter(function (line) {
      return !isLineEffectivelyEmpty(line);
    });

    if (!submitLines.length) {
      alertMsg('warning', 'Tambahkan minimal 1 line PO.');
      return;
    }

    for (var i = 0; i < submitLines.length; i++) {
      var lk = String(submitLines[i].line_kind || '').toUpperCase();
      if (!num(submitLines[i].buy_uom_id)) {
        alertMsg('warning', 'Baris #' + (i + 1) + ': UOM beli wajib dipilih.');
        return;
      }
      if (lk === 'MATERIAL' && !num(submitLines[i].material_id)) {
        alertMsg('warning', 'Baris #' + (i + 1) + ': bahan baku baru harus dibuat dulu di master bahan baku, lalu pilih ulang dari catalog.');
        return;
      }
      if (lk === 'MATERIAL') {
        var matContentId = num(submitLines[i].material_content_uom_id || 0);
        if (matContentId > 0) {
          submitLines[i].content_uom_id = matContentId;
          submitLines[i].content_uom_code = (uomById[matContentId] && uomById[matContentId].code) ? uomById[matContentId].code : (submitLines[i].content_uom_code || '');
        }
      }
    }

    var header = {
      request_date: document.getElementById('request_date').value,
      purchase_type_id: num(document.getElementById('purchase_type_id').value),
      destination_type: document.getElementById('destination_type').value || null,
      destination_division_id: num(document.getElementById('destination_division_id').value) || null,
      vendor_id: num(document.getElementById('vendor_id').value),
      payment_account_id: num(document.getElementById('payment_account_id').value) || null,
      status: document.getElementById('status').value || 'DRAFT',
      notes: document.getElementById('po_notes').value || null
    };

    if (!header.purchase_type_id || !header.request_date) {
      alertMsg('warning', 'Header belum lengkap: request date dan purchase type wajib diisi.');
      return;
    }

    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ header: header, lines: submitLines })
    })
    .then(parseJsonResponse)
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan PO');
      }
      var d = res.json.data || {};
      if (editMode) {
        alertMsg('success', 'PO berhasil diperbarui. No: ' + esc(d.po_no || '-') + ', line: ' + esc(d.line_count || 0));
        window.setTimeout(function () {
          window.location.href = detailUrl;
        }, 700);
      } else {
        alertMsg('success', 'PO berhasil disimpan. No: ' + esc(d.po_no || '-') + ', line: ' + esc(d.line_count || 0));
        lines = [];
        addEmptyLine(false);
      }
    })
    .catch(function (err) {
      alertMsg('danger', err.message || 'Gagal simpan PO.');
    });
  });

  applyPurchaseTypeRules(editMode);

  document.getElementById('btn-add-line').addEventListener('click', function () {
    addEmptyLine(true);
  });

  if (editMode && Array.isArray(initialLines) && initialLines.length > 0) {
    lines = initialLines.map(function (row) {
      var mapped = Object.assign(createEmptyLine(), row || {});
      mapped.selected_display_name = String(mapped.catalog_name || mapped.item_name || mapped.material_name || '');
      mapped.qty_buy = num(mapped.qty_buy);
      mapped.content_per_buy = num(mapped.content_per_buy);
      mapped.conversion_factor_to_content = num(mapped.conversion_factor_to_content || mapped.content_per_buy);
      mapped.unit_price = num(mapped.unit_price);
      mapped.discount_percent = num(mapped.discount_percent);
      mapped.tax_percent = num(mapped.tax_percent);
      applyLineTypeDefaults(mapped);
      return mapped;
    });
    refreshLines();
    activeLineIdx = 0;
  } else {
    addEmptyLine(false);
  }

  document.addEventListener('click', function (e) {
    if (!catalogPreviewWrap.contains(e.target) && e.target !== catalogKeywordEl) {
      catalogPreviewWrap.style.display = 'none';
    }
  });
})();
</script>
