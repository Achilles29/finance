<?php
$baseUrl = site_url((string)($base_url_adjustment ?? 'inventory/stock/adjustment'));
$searchUrl = site_url('inventory/stock/adjustment/item-search');
$storeUrl = site_url('inventory/stock/adjustment/store');
$postBaseUrl = site_url('inventory/stock/adjustment/post');
$voidBaseUrl = site_url('inventory/stock/adjustment/void');
$deleteBaseUrl = site_url('inventory/stock/adjustment/delete');
$rows = is_array($rows ?? null) ? $rows : [];
$lineRows = is_array($line_rows ?? null) ? $line_rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$stockScope = strtoupper(trim((string)($stock_scope ?? 'WAREHOUSE')));
$activeTab = strtolower(trim((string)($active_tab ?? 'input')));
if (!in_array($activeTab, ['input', 'rincian'], true)) {
  $activeTab = 'input';
}
$isDivisionScope = !empty($is_division_scope);
$isWarehouseScope = !$isDivisionScope;
$selectedDivisionId = (int)($division_id ?? 0);
$selectedDestination = strtoupper(trim((string)($destination ?? ($isDivisionScope ? 'OTHER' : 'ALL'))));
if ($selectedDestination === '') {
  $selectedDestination = $isDivisionScope ? 'OTHER' : 'ALL';
}
$tabBaseParams = [
  'month' => (string)($month ?? ''),
  'q' => (string)($q ?? ''),
  'limit' => (int)($limit ?? 200),
];
if ($isDivisionScope) {
  $tabBaseParams['division_id'] = $selectedDivisionId > 0 ? $selectedDivisionId : '';
  $tabBaseParams['destination'] = $selectedDestination;
}
$buildTabUrl = static function (string $tab) use ($baseUrl, $tabBaseParams): string {
  $params = $tabBaseParams;
  $params['tab'] = $tab;
  return $baseUrl . '?' . http_build_query($params);
};
$inputTabUrl = $buildTabUrl('input');
$detailTabUrl = $buildTabUrl('rincian');
$qtyUnitLabel = $isDivisionScope ? 'Isi' : 'Pack / Satuan Beli';
$costInputLabel = $isDivisionScope ? 'Unit Cost / Isi' : 'Harga Satuan / Beli';
$availColumnLabel = $isDivisionScope ? 'Avail (Isi)' : 'Avail (Pack)';
$wasteColumnLabel = $isDivisionScope ? 'Waste (Isi)' : 'Waste (Pack)';
$spoilColumnLabel = $isDivisionScope ? 'Spoil (Isi)' : 'Spoil (Pack)';
$processLossColumnLabel = $isDivisionScope ? 'P.Loss (Isi)' : 'P.Loss (Pack)';
$varianceColumnLabel = $isDivisionScope ? 'Variance (Isi)' : 'Variance (Pack)';
$adjustmentPlusColumnLabel = $isDivisionScope ? 'Adj + (Isi)' : 'Adj + (Pack)';
$costColumnLabel = $isDivisionScope ? 'Cost / Isi' : 'Harga / Beli';
$adjustmentReasonOptions = [
  'WASTE' => [
    'cancel_order' => 'Cancel Order',
    'kitchen_error' => 'Kitchen Error',
    'overproduction' => 'Overproduction',
    'spillage' => 'Spillage / Tumpah',
    'prep_trim_excess' => 'Prep Trim Excess',
    'expired_opened' => 'Expired Opened',
    'other' => 'Other',
  ],
  'SPOILAGE' => [
    'expired' => 'Expired',
    'temperature_abuse' => 'Temperature Abuse',
    'contamination' => 'Contamination',
    'overstock' => 'Overstock',
    'improper_storage' => 'Improper Storage',
    'other' => 'Other',
  ],
  'PROCESS_LOSS' => [
    'defrost_loss' => 'Defrost Loss',
    'trimming_standard' => 'Trimming Standard',
    'cooking_loss' => 'Cooking Loss',
    'evaporation' => 'Evaporation',
    'brew_loss' => 'Brew Loss',
    'absorption_loss' => 'Absorption Loss',
    'process_residue' => 'Process Residue',
    'variable_process_consumable' => 'Variable Process Consumable',
    'other' => 'Other',
  ],
  'VARIANCE' => [
    'over_usage' => 'Over Usage',
    'under_usage' => 'Under Usage',
    'unrecorded_usage' => 'Unrecorded Usage',
    'counting_error' => 'Counting Error',
    'system_mismatch' => 'System Mismatch',
    'theft_suspected' => 'Theft Suspected',
    'unknown_shrinkage' => 'Unknown Shrinkage',
    'other' => 'Other',
  ],
  'ADJUSTMENT_PLUS' => [
    'opening_correction' => 'Opening Correction',
    'stock_found' => 'Stock Found',
    'manual_reclass' => 'Manual Reclass',
    'other' => 'Other',
  ],
];
$resolveReasonLabel = static function (string $category, ?string $value) use ($adjustmentReasonOptions): string {
  $key = trim((string)$value);
  if ($key === '') {
    return '-';
  }
  return (string)($adjustmentReasonOptions[$category][$key] ?? $key);
};
$detailQtyByScope = static function (array $row, string $field) use ($isWarehouseScope): float {
  $qtyContent = (float)($row[$field] ?? 0);
  if (!$isWarehouseScope) {
    return $qtyContent;
  }
  $factor = (float)($row['profile_content_per_buy'] ?? 1);
  if ($factor <= 0) {
    $factor = 1;
  }
  return $qtyContent / $factor;
};
$detailCostByScope = static function (array $row) use ($isWarehouseScope): float {
  $unitCost = (float)($row['unit_cost'] ?? 0);
  if (!$isWarehouseScope) {
    return $unitCost;
  }
  $factor = (float)($row['profile_content_per_buy'] ?? 1);
  if ($factor <= 0) {
    $factor = 1;
  }
  return $unitCost * $factor;
};
$detailObjectLabel = static function (array $row): string {
  $code = !empty($row['material_id'])
    ? ((string)($row['material_code'] ?? ($row['item_code'] ?? '-')))
    : ((string)($row['item_code'] ?? ($row['material_code'] ?? '-')));
  $name = !empty($row['material_id'])
    ? ((string)($row['material_name'] ?? ($row['item_name'] ?? '-')))
    : ((string)($row['item_name'] ?? ($row['material_name'] ?? '-')));
  return trim($code . ' - ' . $name);
};
$detailProfileLabel = static function (array $row): string {
  $parts = array_filter([
    trim((string)($row['profile_name'] ?? '')),
    trim((string)($row['profile_brand'] ?? '')),
  ], static function ($value): bool {
    return $value !== '';
  });
  return !empty($parts) ? implode(' | ', $parts) : '-';
};
$detailReasonSummary = static function (array $row) use ($resolveReasonLabel): string {
  $parts = [];
  if ((float)($row['qty_waste_content'] ?? 0) > 0) {
    $parts[] = 'Waste: ' . $resolveReasonLabel('WASTE', $row['waste_reason_code'] ?? null);
  }
  if ((float)($row['qty_spoil_content'] ?? 0) > 0) {
    $parts[] = 'Spoil: ' . $resolveReasonLabel('SPOILAGE', $row['spoil_reason_code'] ?? null);
  }
  if ((float)($row['qty_process_loss_content'] ?? 0) > 0) {
    $parts[] = 'P.Loss: ' . $resolveReasonLabel('PROCESS_LOSS', $row['process_loss_reason_code'] ?? null);
  }
  if ((float)($row['qty_variance_content'] ?? 0) > 0) {
    $parts[] = 'Variance: ' . $resolveReasonLabel('VARIANCE', $row['variance_reason_code'] ?? null);
  }
  if ((float)($row['qty_adjustment_plus_content'] ?? 0) > 0) {
    $parts[] = 'Adj+: ' . $resolveReasonLabel('ADJUSTMENT_PLUS', $row['adjustment_plus_reason_code'] ?? null);
  }
  return !empty($parts) ? implode(' | ', $parts) : '-';
};
$summaryDraft = 0;
$summaryPosted = 0;
$summaryVoid = 0;
foreach ($rows as $row) {
  $status = strtoupper((string)($row['status'] ?? 'DRAFT'));
  if ($status === 'POSTED') {
    $summaryPosted++;
  } elseif ($status === 'VOID') {
    $summaryVoid++;
  } else {
    $summaryDraft++;
  }
}
?>

<style>
  .adjustment-search-result { cursor: pointer; }
  .adjustment-search-result:hover { background: rgba(0,0,0,.03); }
  .adjustment-summary-chip {
    border: 1px solid rgba(0,0,0,.08);
    border-radius: .75rem;
    padding: .65rem .8rem;
    background: #fff;
  }
  .adjustment-line-table td,
  .adjustment-line-table th { vertical-align: middle; }
  .adjustment-selected-card {
    border: 1px dashed rgba(0,0,0,.18);
    border-radius: .9rem;
    background: linear-gradient(180deg, #ffffff 0%, #faf8f4 100%);
  }
  .adjustment-confirm-modal .modal-content {
    border: 0;
    border-radius: 1rem;
    box-shadow: 0 1.5rem 3rem rgba(17, 24, 39, .18);
  }
  .adjustment-confirm-modal .modal-header,
  .adjustment-confirm-modal .modal-footer {
    border: 0;
  }
  .adjustment-confirm-hero {
    width: 3rem;
    height: 3rem;
    border-radius: .9rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
    color: #c2410c;
    font-size: 1.35rem;
    flex: 0 0 auto;
  }
  .adjustment-profile-choice-list {
    display: grid;
    gap: .8rem;
  }
  .adjustment-profile-choice-card {
    width: 100%;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 1rem;
    background: linear-gradient(180deg, #ffffff 0%, #faf8f4 100%);
    padding: .9rem 1rem;
    text-align: left;
    box-shadow: 0 .75rem 1.6rem rgba(15, 23, 42, .05);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
  }
  .adjustment-profile-choice-card:hover {
    transform: translateY(-1px);
    border-color: rgba(147, 51, 234, .18);
    box-shadow: 0 1rem 2rem rgba(15, 23, 42, .08);
  }
  .adjustment-profile-choice-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .45rem;
  }
  .adjustment-profile-choice-title {
    font-weight: 700;
    color: #111827;
  }
  .adjustment-profile-choice-key {
    display: inline-flex;
    align-items: center;
    padding: .18rem .55rem;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, .08);
    color: #475569;
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
  }
  .adjustment-profile-choice-desc {
    color: #6b7280;
    font-size: .84rem;
    margin-bottom: .55rem;
  }
  .adjustment-profile-choice-meta {
    color: #6b7280;
    font-size: .78rem;
    margin-bottom: .55rem;
  }
  .adjustment-profile-choice-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .55rem;
  }
  .adjustment-profile-choice-metric {
    border: 1px solid rgba(15, 23, 42, .07);
    border-radius: .85rem;
    background: rgba(255,255,255,.82);
    padding: .55rem .65rem;
  }
  .adjustment-profile-choice-metric .label {
    display: block;
    font-size: .72rem;
    color: #6b7280;
    margin-bottom: .15rem;
  }
  .adjustment-profile-choice-metric strong {
    display: block;
    font-size: .92rem;
    color: #111827;
    line-height: 1.2;
  }
  @media (max-width: 767.98px) {
    .adjustment-profile-choice-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="mb-2">
  <h4 class="mb-1"><i class="ri ri-scales-3-line page-title-icon"></i><?php echo html_escape((string)$title); ?></h4>
  <small class="text-muted">Draft disimpan dulu. Saat diposting, stok live, stok harian, dan lot FIFO ikut berubah.</small>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-3">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => $isDivisionScope ? 'DIVISION' : 'WAREHOUSE', 'active_tab' => 'adjustment']); ?>
</div>

<div id="adjustment-alert"></div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $activeTab === 'input' ? 'active' : ''; ?>" href="<?php echo html_escape($inputTabUrl); ?>">Form Input Adjustment</a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo html_escape($detailTabUrl); ?>">Rincian Adjustment</a>
  </li>
</ul>

<div class="tab-content p-0 border-0">
  <div class="tab-pane fade <?php echo $activeTab === 'input' ? 'show active' : ''; ?>" id="adjustment-tab-input" role="tabpanel">
<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Form Draft Adjustment</h6>
        <form id="adjustment-form" class="row g-2" autocomplete="off">
          <input type="hidden" id="stock_scope" value="<?php echo html_escape($stockScope); ?>">
          <?php if ($isDivisionScope): ?>
          <div class="col-md-6">
            <label class="form-label">Divisi</label>
            <select class="form-select" id="division_id" required>
              <option value="">Pilih divisi...</option>
              <?php foreach ($divisions as $divisionRow): ?>
                <?php
                  $id = (int)($divisionRow['id'] ?? 0);
                  $code = trim((string)($divisionRow['code'] ?? ''));
                  $name = trim((string)($divisionRow['name'] ?? ''));
                  $label = $code !== '' ? ($code . ' - ' . $name) : ($name !== '' ? $name : (string)$id);
                ?>
                <option value="<?php echo $id; ?>" <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tujuan</label>
            <select class="form-select" id="destination_type" required>
              <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR (Reguler)</option>
              <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN (Reguler)</option>
              <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
              <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
              <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
              <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-6">
            <label class="form-label">Tanggal Adjustment</label>
            <input type="date" class="form-control" id="adjustment_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-<?php echo $isDivisionScope ? '12' : '6'; ?>">
            <label class="form-label">Catatan Header</label>
            <input type="text" class="form-control" id="header_notes" placeholder="Opsional, misalnya waste closing shift">
          </div>

          <div class="col-12">
            <label class="form-label">Cari Item / Profile</label>
            <input type="text" class="form-control" id="item_search" placeholder="Ketik minimal 2 huruf...">
            <div id="item_search_results" class="list-group mt-1" style="max-height:220px; overflow:auto;"></div>
          </div>

          <div class="col-12">
            <div class="adjustment-selected-card p-3" id="selected-card">
              <div class="fw-semibold mb-1">Belum ada profile dipilih.</div>
              <div class="text-muted small">Pilih item/profile dari hasil pencarian untuk menambahkan line adjustment.</div>
            </div>
          </div>

          <div class="col-12">
            <div class="alert alert-light border small mb-0">
              <div class="fw-semibold mb-1">Panduan kategori adjustment</div>
              <div class="mb-1"><strong>Mode input <?php echo $isWarehouseScope ? 'Gudang' : 'Divisi'; ?></strong>: <?php echo $isWarehouseScope ? 'qty diisi dalam pack atau satuan beli, harga diisi per pack atau per satuan beli.' : 'qty diisi dalam satuan isi, karena stok divisi dipakai dalam bentuk bahan baku terbuka atau isi.'; ?></div>
              <div class="mb-1"><strong>Waste</strong>: barang terbuang atau rusak karena operasional.</div>
              <div class="mb-1"><strong>Spoil</strong>: barang busuk, expired, atau rusak karena penyimpanan.</div>
              <div class="mb-1"><strong>Process Loss</strong>: susut yang terjadi saat proses kerja atau proses produksi.</div>
              <div class="mb-1"><strong>Variance</strong>: selisih antara stok fisik dan stok sistem.</div>
              <div><strong>Adjustment +</strong>: penambahan stok manual karena koreksi atau stok ditemukan.</div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Waste (<?php echo html_escape($qtyUnitLabel); ?>)</label>
            <input type="number" class="form-control" id="qty_waste_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Reason Waste</label>
            <select class="form-select" id="waste_reason_code">
              <option value="other">Other</option>
              <?php foreach (($adjustmentReasonOptions['WASTE'] ?? []) as $value => $label): ?>
                <?php if ($value === 'other') { continue; } ?>
                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Spoil (<?php echo html_escape($qtyUnitLabel); ?>)</label>
            <input type="number" class="form-control" id="qty_spoil_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Reason Spoil</label>
            <select class="form-select" id="spoil_reason_code">
              <option value="other">Other</option>
              <?php foreach (($adjustmentReasonOptions['SPOILAGE'] ?? []) as $value => $label): ?>
                <?php if ($value === 'other') { continue; } ?>
                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Process Loss (<?php echo html_escape($qtyUnitLabel); ?>)</label>
            <input type="number" class="form-control" id="qty_process_loss_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Reason Process Loss</label>
            <select class="form-select" id="process_loss_reason_code">
              <option value="other">Other</option>
              <?php foreach (($adjustmentReasonOptions['PROCESS_LOSS'] ?? []) as $value => $label): ?>
                <?php if ($value === 'other') { continue; } ?>
                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Variance (<?php echo html_escape($qtyUnitLabel); ?>)</label>
            <input type="number" class="form-control" id="qty_variance_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Reason Variance</label>
            <select class="form-select" id="variance_reason_code">
              <option value="other">Other</option>
              <?php foreach (($adjustmentReasonOptions['VARIANCE'] ?? []) as $value => $label): ?>
                <?php if ($value === 'other') { continue; } ?>
                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Adjustment + (<?php echo html_escape($qtyUnitLabel); ?>)</label>
            <input type="number" class="form-control" id="qty_adjustment_plus_content" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-8">
            <label class="form-label">Reason Adjustment +</label>
            <select class="form-select" id="adjustment_plus_reason_code">
              <option value="other">Other</option>
              <?php foreach (($adjustmentReasonOptions['ADJUSTMENT_PLUS'] ?? []) as $value => $label): ?>
                <?php if ($value === 'other') { continue; } ?>
                <option value="<?php echo html_escape($value); ?>"><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label"><?php echo html_escape($costInputLabel); ?></label>
            <input type="number" class="form-control" id="unit_cost" min="0" step="0.01" value="0">
            <div class="form-text"><?php echo $isWarehouseScope ? 'Untuk gudang, isi harga per pack atau per satuan beli.' : 'Untuk divisi, isi biaya per satuan isi.'; ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Lot Masuk Manual</label>
            <input type="text" class="form-control" id="inbound_lot_no" placeholder="Opsional untuk adjustment +">
            <div class="form-text">Dipakai jika Adjustment + membuat stok masuk baru dan Anda ingin memberi nomor lot sendiri. Jika dikosongkan, sistem akan membuat lot otomatis.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Exp Date Lot Masuk</label>
            <input type="date" class="form-control" id="inbound_expiry_date">
            <div class="form-text">Tanggal kedaluwarsa lot inbound untuk stok tambahan dari Adjustment +.</div>
          </div>
          <div class="col-12">
            <label class="form-label">Catatan Line</label>
            <input type="text" class="form-control" id="line_note" placeholder="Opsional">
          </div>
          <div class="col-12 d-grid">
            <button type="button" class="btn btn-outline-primary" id="btn-add-line">Tambah Line</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="adjustment-summary-chip"><div class="small text-muted">Dokumen</div><div class="h5 mb-0"><?php echo number_format(count($rows)); ?></div></div></div>
      <div class="col-6 col-md-3"><div class="adjustment-summary-chip"><div class="small text-muted">Draft</div><div class="h5 mb-0 text-warning"><?php echo number_format($summaryDraft); ?></div></div></div>
      <div class="col-6 col-md-3"><div class="adjustment-summary-chip"><div class="small text-muted">Posted</div><div class="h5 mb-0 text-success"><?php echo number_format($summaryPosted); ?></div></div></div>
      <div class="col-6 col-md-3"><div class="adjustment-summary-chip"><div class="small text-muted">Void</div><div class="h5 mb-0 text-danger"><?php echo number_format($summaryVoid); ?></div></div></div>
    </div>

    <div class="card mb-3">
      <div class="card-body pb-2">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h6 class="mb-0">Line Draft Saat Ini</h6>
          <button type="button" class="btn btn-primary btn-sm" id="btn-save-draft">Simpan Draft</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-striped mb-0 adjustment-line-table" id="draft-lines-table">
          <thead>
            <tr>
              <th>Objek</th>
              <th class="text-end"><?php echo html_escape($availColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($wasteColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($spoilColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($processLossColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($varianceColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($adjustmentPlusColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($costColumnLabel); ?></th>
              <th>Lot In</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada line draft.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-body py-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end" id="adjustment-doc-filter-form" data-adjustment-filter="1">
          <input type="hidden" name="tab" value="input">
          <?php if ($isDivisionScope): ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Divisi</label>
            <select class="form-select" name="division_id">
              <option value="">Semua Divisi</option>
              <?php foreach ($divisions as $divisionRow): ?>
                <?php
                  $id = (int)($divisionRow['id'] ?? 0);
                  $code = trim((string)($divisionRow['code'] ?? ''));
                  $name = trim((string)($divisionRow['name'] ?? ''));
                  $label = $code !== '' ? ($code . ' - ' . $name) : ($name !== '' ? $name : (string)$id);
                ?>
                <option value="<?php echo $id; ?>" <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Tujuan</label>
            <select class="form-select" name="destination">
              <option value="ALL" <?php echo $selectedDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
              <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR</option>
              <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
              <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
              <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
              <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
              <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Bulan</label>
            <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          </div>
          <div class="col-md-<?php echo $isDivisionScope ? '2' : '5'; ?>">
            <label class="form-label mb-1">Cari</label>
            <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="No adjustment / catatan">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" class="form-control" name="limit" min="1" max="500" value="<?php echo (int)$limit; ?>">
          </div>
          <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
          <div class="col-md-2 d-grid"><a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a></div>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <?php if ($isDivisionScope): ?><th>Divisi/Tujuan</th><?php endif; ?>
              <th>Status</th>
              <th class="text-end">Lines</th>
              <th class="text-end"><?php echo html_escape($wasteColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($spoilColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($processLossColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($varianceColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($adjustmentPlusColumnLabel); ?></th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="<?php echo $isDivisionScope ? '11' : '10'; ?>" class="text-center text-muted py-4">Belum ada dokumen adjustment.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr id="stock-adjustment-<?php echo (int)($row['id'] ?? 0); ?>">
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['adjustment_no'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['notes'] ?? '')); ?></small>
                </td>
                <td><?php echo html_escape((string)($row['adjustment_date'] ?? '-')); ?></td>
                <?php if ($isDivisionScope): ?>
                <td>
                  <div><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['destination_type'] ?? '-')); ?></small>
                </td>
                <?php endif; ?>
                <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                <td class="text-end"><?php echo number_format((int)($row['line_count'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num((float)($isWarehouseScope ? ($row['total_waste_buy'] ?? 0) : ($row['total_waste_content'] ?? 0))); ?></td>
                <td class="text-end"><?php echo ui_num((float)($isWarehouseScope ? ($row['total_spoil_buy'] ?? 0) : ($row['total_spoil_content'] ?? 0))); ?></td>
                <td class="text-end"><?php echo ui_num((float)($isWarehouseScope ? ($row['total_process_loss_buy'] ?? 0) : ($row['total_process_loss_content'] ?? 0))); ?></td>
                <td class="text-end"><?php echo ui_num((float)($isWarehouseScope ? ($row['total_variance_buy'] ?? 0) : ($row['total_variance_content'] ?? 0))); ?></td>
                <td class="text-end"><?php echo ui_num((float)($isWarehouseScope ? ($row['total_adjustment_plus_buy'] ?? 0) : ($row['total_adjustment_plus_content'] ?? 0))); ?></td>
                <td class="action-cell">
                  <?php $rowStatus = strtoupper((string)($row['status'] ?? 'DRAFT')); ?>
                  <?php if ($rowStatus === 'DRAFT'): ?>
                    <div class="d-flex gap-1 flex-nowrap justify-content-end">
                      <button type="button" class="btn btn-sm btn-outline-success action-icon-btn btn-post-doc" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-upload-2-line"></i></button>
                      <button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-delete-doc" data-id="<?php echo (int)$row['id']; ?>" title="Hapus" aria-label="Hapus"><i class="ri ri-delete-bin-line"></i></button>
                    </div>
                  <?php elseif ($rowStatus === 'POSTED'): ?>
                    <div class="d-flex gap-1 flex-nowrap justify-content-end">
                      <button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-void-doc" data-id="<?php echo (int)$row['id']; ?>" title="Void" aria-label="Void"><i class="ri ri-close-circle-line"></i></button>
                    </div>
                  <?php else: ?>
                    <span class="text-muted small">Sudah dibatalkan</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
  </div>

  <div class="tab-pane fade <?php echo $activeTab === 'rincian' ? 'show active' : ''; ?>" id="adjustment-tab-rincian" role="tabpanel">
    <div class="card mb-3">
      <div class="card-body py-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end" id="adjustment-line-filter-form" data-adjustment-filter="1">
          <input type="hidden" name="tab" value="rincian">
          <?php if ($isDivisionScope): ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Divisi</label>
            <select class="form-select" name="division_id">
              <option value="">Semua Divisi</option>
              <?php foreach ($divisions as $divisionRow): ?>
                <?php
                  $id = (int)($divisionRow['id'] ?? 0);
                  $code = trim((string)($divisionRow['code'] ?? ''));
                  $name = trim((string)($divisionRow['name'] ?? ''));
                  $label = $code !== '' ? ($code . ' - ' . $name) : ($name !== '' ? $name : (string)$id);
                ?>
                <option value="<?php echo $id; ?>" <?php echo $selectedDivisionId === $id ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Tujuan</label>
            <select class="form-select" name="destination">
              <option value="ALL" <?php echo $selectedDestination === 'ALL' ? 'selected' : ''; ?>>Semua</option>
              <option value="BAR" <?php echo $selectedDestination === 'BAR' ? 'selected' : ''; ?>>BAR</option>
              <option value="KITCHEN" <?php echo $selectedDestination === 'KITCHEN' ? 'selected' : ''; ?>>KITCHEN</option>
              <option value="BAR_EVENT" <?php echo $selectedDestination === 'BAR_EVENT' ? 'selected' : ''; ?>>BAR_EVENT</option>
              <option value="KITCHEN_EVENT" <?php echo $selectedDestination === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>KITCHEN_EVENT</option>
              <option value="OFFICE" <?php echo $selectedDestination === 'OFFICE' ? 'selected' : ''; ?>>OFFICE</option>
              <option value="OTHER" <?php echo $selectedDestination === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-3">
            <label class="form-label mb-1">Bulan</label>
            <input type="month" class="form-control" name="month" value="<?php echo html_escape($month !== '' ? substr((string)$month, 0, 7) : date('Y-m')); ?>">
          </div>
          <div class="col-md-<?php echo $isDivisionScope ? '2' : '5'; ?>">
            <label class="form-label mb-1">Cari</label>
            <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="No adjustment, profile, item, lot, catatan">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" class="form-control" name="limit" min="1" max="500" value="<?php echo (int)$limit; ?>">
          </div>
          <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
          <div class="col-md-2 d-grid"><a href="<?php echo html_escape($detailTabUrl); ?>" class="btn btn-outline-danger">Clear</a></div>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 adjustment-line-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <?php if ($isDivisionScope): ?><th>Divisi/Tujuan</th><?php endif; ?>
              <th>Status</th>
              <th>Line</th>
              <th>Objek</th>
              <th>Profile</th>
              <th>Reason</th>
              <th class="text-end"><?php echo html_escape($wasteColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($spoilColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($processLossColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($varianceColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($adjustmentPlusColumnLabel); ?></th>
              <th class="text-end"><?php echo html_escape($costColumnLabel); ?></th>
              <th>Lot In</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($lineRows)): ?>
            <tr><td colspan="<?php echo $isDivisionScope ? '16' : '15'; ?>" class="text-center text-muted py-4">Belum ada rincian adjustment.</td></tr>
          <?php else: ?>
            <?php foreach ($lineRows as $lineRow): ?>
              <?php
                $lineNoLabel = trim((string)($lineRow['adjustment_no'] ?? '-'));
                $lineNoValue = (int)($lineRow['line_no'] ?? 0);
                $lineObject = $detailObjectLabel($lineRow);
                $lineProfile = $detailProfileLabel($lineRow);
                $lineReason = $detailReasonSummary($lineRow);
                $lotParts = array_filter([
                  trim((string)($lineRow['inbound_lot_no'] ?? '')),
                  trim((string)($lineRow['inbound_expiry_date'] ?? '')),
                ], static function ($value): bool {
                  return $value !== '';
                });
              ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo html_escape($lineNoLabel); ?></div>
                  <small class="text-muted">#<?php echo $lineNoValue > 0 ? $lineNoValue : '-'; ?></small>
                </td>
                <td><?php echo html_escape((string)($lineRow['adjustment_date'] ?? '-')); ?></td>
                <?php if ($isDivisionScope): ?>
                <td>
                  <div><?php echo html_escape((string)($lineRow['division_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($lineRow['destination_type'] ?? '-')); ?></small>
                </td>
                <?php endif; ?>
                <td><?php echo ui_status_badge((string)($lineRow['status'] ?? 'DRAFT')); ?></td>
                <td class="text-nowrap">Line <?php echo $lineNoValue > 0 ? $lineNoValue : '-'; ?></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape($lineObject); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($lineRow['profile_key'] ?? '-')); ?></small>
                </td>
                <td>
                  <div><?php echo html_escape($lineProfile); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($lineRow['profile_description'] ?? '-')); ?></small>
                </td>
                <td><small><?php echo html_escape($lineReason); ?></small></td>
                <td class="text-end"><?php echo ui_num($detailQtyByScope($lineRow, 'qty_waste_content')); ?></td>
                <td class="text-end"><?php echo ui_num($detailQtyByScope($lineRow, 'qty_spoil_content')); ?></td>
                <td class="text-end"><?php echo ui_num($detailQtyByScope($lineRow, 'qty_process_loss_content')); ?></td>
                <td class="text-end"><?php echo ui_num($detailQtyByScope($lineRow, 'qty_variance_content')); ?></td>
                <td class="text-end"><?php echo ui_num($detailQtyByScope($lineRow, 'qty_adjustment_plus_content')); ?></td>
                <td class="text-end"><?php echo ui_num($detailCostByScope($lineRow)); ?></td>
                <td><?php echo !empty($lotParts) ? html_escape(implode(' | ', $lotParts)) : '-'; ?></td>
                <td>
                  <div><?php echo html_escape((string)($lineRow['line_note'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($lineRow['header_notes'] ?? '')); ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="adjustmentProfilePickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-uppercase text-muted fw-semibold">Pilih Profil Adjustment</div>
          <h5 class="modal-title mb-0" id="adjustment-profile-picker-title">Pilih profil item</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3" id="adjustment-profile-picker-meta">Pilih profil yang benar sebelum line adjustment ditambahkan.</div>
        <div class="adjustment-profile-choice-list" id="adjustment-profile-picker-options"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade adjustment-confirm-modal" id="adjustmentConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header pb-0">
        <div>
          <div class="small text-uppercase text-muted fw-semibold" id="adjustment-confirm-kicker">Konfirmasi</div>
          <h5 class="modal-title mb-0" id="adjustment-confirm-title">Konfirmasi aksi</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="d-flex gap-3 align-items-start">
          <div class="adjustment-confirm-hero">
            <i class="ri-alert-line"></i>
          </div>
          <div>
            <div class="fw-semibold mb-1" id="adjustment-confirm-message">Pastikan aksi ini memang ingin dilanjutkan.</div>
            <div class="text-muted small" id="adjustment-confirm-note">Perubahan akan langsung diproses setelah Anda menekan tombol lanjut.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary px-4" id="adjustment-confirm-submit">Lanjutkan</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const destinationGuardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const stockScope = document.getElementById('stock_scope')?.value || 'WAREHOUSE';
  const isDivisionScope = stockScope === 'DIVISION';
  const isWarehouseScope = stockScope === 'WAREHOUSE';
  const alertArea = document.getElementById('adjustment-alert');
  const profileModalEl = document.getElementById('adjustmentProfilePickerModal');
  const profileModalTitleEl = document.getElementById('adjustment-profile-picker-title');
  const profileModalMetaEl = document.getElementById('adjustment-profile-picker-meta');
  const profileModalOptionsEl = document.getElementById('adjustment-profile-picker-options');
  const confirmModalEl = document.getElementById('adjustmentConfirmModal');
  const confirmKickerEl = document.getElementById('adjustment-confirm-kicker');
  const confirmTitleEl = document.getElementById('adjustment-confirm-title');
  const confirmMessageEl = document.getElementById('adjustment-confirm-message');
  const confirmNoteEl = document.getElementById('adjustment-confirm-note');
  const confirmSubmitBtn = document.getElementById('adjustment-confirm-submit');
  const confirmModal = (confirmModalEl && window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(confirmModalEl) : null;
  const searchInput = document.getElementById('item_search');
  const searchResults = document.getElementById('item_search_results');
  const selectedCard = document.getElementById('selected-card');
  const draftTableBody = document.querySelector('#draft-lines-table tbody');
  const lines = [];
  let selectedItem = null;
  let selectedProfileOptions = [];
  let currentSearchItems = [];
  let profilePickerBaseItem = null;
  let profilePickerOptions = [];
  let profileLookupToken = 0;
  let searchTimer = null;
  let confirmResolver = null;
  let confirmBackdropEl = null;
  const reasonLabelMap = <?php echo json_encode($adjustmentReasonOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const formDivisionEl = document.getElementById('division_id');
  const formDestinationEl = document.getElementById('destination_type');
  const docFilterFormEl = document.getElementById('adjustment-doc-filter-form');
  const docFilterDivisionEl = docFilterFormEl ? docFilterFormEl.querySelector('select[name="division_id"]') : null;
  const docFilterDestinationEl = docFilterFormEl ? docFilterFormEl.querySelector('select[name="destination"]') : null;
  const lineFilterFormEl = document.getElementById('adjustment-line-filter-form');
  const lineFilterDivisionEl = lineFilterFormEl ? lineFilterFormEl.querySelector('select[name="division_id"]') : null;
  const lineFilterDestinationEl = lineFilterFormEl ? lineFilterFormEl.querySelector('select[name="destination"]') : null;
  const formDestinationOptions = [
    { value: 'BAR', label: 'BAR (Reguler)' },
    { value: 'KITCHEN', label: 'KITCHEN (Reguler)' },
    { value: 'BAR_EVENT', label: 'BAR_EVENT' },
    { value: 'KITCHEN_EVENT', label: 'KITCHEN_EVENT' },
    { value: 'OFFICE', label: 'OFFICE' },
    { value: 'OTHER', label: 'OTHER' }
  ];
  const filterDestinationOptions = [
    { value: 'ALL', label: 'Semua' },
    { value: 'REGULER', label: 'Reguler' },
    { value: 'EVENT', label: 'Event' },
    { value: 'BAR', label: 'BAR' },
    { value: 'KITCHEN', label: 'KITCHEN' },
    { value: 'BAR_EVENT', label: 'BAR_EVENT' },
    { value: 'KITCHEN_EVENT', label: 'KITCHEN_EVENT' },
    { value: 'OFFICE', label: 'OFFICE' },
    { value: 'OTHER', label: 'OTHER' }
  ];

  const fmt = (num) => Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  const fmt6 = (num) => Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 6 });
  const reasonLabel = (category, value) => ((reasonLabelMap?.[category] || {})[value] || value || '-');
  const contentPerBuyValue = (row) => {
    const value = Number(row?.profile_content_per_buy || row?.default_content_per_buy || 1);
    return value > 0 ? value : 1;
  };
  const qtyByScope = (row, contentQty) => isWarehouseScope ? (Number(contentQty || 0) / contentPerBuyValue(row)) : Number(contentQty || 0);
  const qtyUnitByScope = (row) => isWarehouseScope ? (row?.profile_buy_uom_code || row?.default_buy_uom_code || '') : (row?.profile_content_uom_code || row?.default_content_uom_code || '');
  const costByScope = (row, costPerContent) => isWarehouseScope ? (Number(costPerContent || 0) * contentPerBuyValue(row)) : Number(costPerContent || 0);
  const costLabelByScope = () => isWarehouseScope ? 'Harga Satuan / Beli' : 'Avg Cost/Isi';
  const formatUpdatedAt = (value) => {
    const raw = String(value || '').trim();
    if (!raw) {
      return 'Belum ada update saldo';
    }
    const normalized = raw.replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
      return raw;
    }
    return new Intl.DateTimeFormat('id-ID', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date);
  };
  const escHtml = (value) => String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
  const objectIdentityKey = (row) => [
    Number(row?.id || 0),
    Number(row?.material_id || 0),
    String(row?.stock_domain || (Number(row?.material_id || 0) > 0 ? 'MATERIAL' : 'ITEM')).toUpperCase()
  ].join('|');
  const profileOptionKey = (row) => [
    String(row?.profile_key || ''),
    Number(row?.default_buy_uom_id || 0),
    Number(row?.default_content_uom_id || 0),
    Number(contentPerBuyValue(row) || 1).toFixed(6),
    String(row?.profile_name || '').trim().toUpperCase(),
    String(row?.profile_brand || '').trim().toUpperCase(),
    String(row?.profile_description || '').trim().toUpperCase()
  ].join('|');
  const sameAdjustmentObject = (left, right) => objectIdentityKey(left) === objectIdentityKey(right);
  const hasPositiveProfileStock = (row) => Number(row?.available_qty_content || 0) > 0 || Number(row?.available_qty_buy || 0) > 0;
  const buildProfileOptions = (rows, baseItem) => {
    const filtered = Array.isArray(rows) ? rows.filter((row) => sameAdjustmentObject(row, baseItem)) : [];
    const seen = new Set();
    const options = [];
    filtered.forEach((row) => {
      const key = profileOptionKey(row);
      if (seen.has(key)) {
        return;
      }
      seen.add(key);
      options.push(row);
    });
    const stockedOptions = options.filter((row) => hasPositiveProfileStock(row));
    if (stockedOptions.length > 0) {
      return stockedOptions;
    }
    if (!options.length && baseItem) {
      options.push(baseItem);
    }
    return options;
  };
  const objectLabel = (row) => {
    const objectCode = row?.material_id ? (row?.material_code || row?.item_code || '-') : (row?.item_code || row?.material_code || '-');
    const objectName = row?.material_id ? (row?.material_name || row?.item_name || '-') : (row?.item_name || row?.material_name || '-');
    return (objectCode + ' - ' + objectName).trim();
  };
  const profileLabel = (row) => [row?.profile_name || 'Tanpa profile', row?.profile_brand || ''].filter(Boolean).join(' | ');
  const syncSelectedProfile = (preferredKey = '') => {
    if (!selectedProfileOptions.length) {
      selectedProfileOptions = selectedItem ? [selectedItem] : [];
    }
    if (!selectedProfileOptions.length) {
      selectedItem = null;
      return;
    }
    const targetKey = preferredKey || profileOptionKey(selectedItem || selectedProfileOptions[0]);
    selectedItem = selectedProfileOptions.find((option) => profileOptionKey(option) === targetKey) || selectedProfileOptions[0];
  };

  const resolveProfileOptions = async (baseItem) => {
    const baseOptions = buildProfileOptions(currentSearchItems, baseItem);
    if (!isDivisionScope || !baseItem) {
      return baseOptions;
    }
    const searchToken = String(baseItem.item_code || baseItem.material_code || baseItem.item_name || baseItem.material_name || '').trim();
    if (searchToken.length < 1) {
      return baseOptions;
    }
    const params = new URLSearchParams({ q: searchToken, limit: '50', stock_scope: stockScope });
    params.set('division_id', String(document.getElementById('division_id')?.value || ''));
    params.set('destination', String(document.getElementById('destination_type')?.value || ''));
    try {
      const res = await fetchJson('<?php echo $searchUrl; ?>?' + params.toString());
      if (!res.ok) {
        return baseOptions;
      }
      return buildProfileOptions([].concat(baseOptions, Array.isArray(res.items) ? res.items : []), baseItem);
    } catch (error) {
      return baseOptions;
    }
  };

  const getProfileModalInstance = () => {
    if (!profileModalEl || !(window.bootstrap && window.bootstrap.Modal)) {
      return null;
    }
    return window.bootstrap.Modal.getOrCreateInstance(profileModalEl);
  };

  const closeProfilePicker = () => {
    const modal = getProfileModalInstance();
    if (modal) {
      modal.hide();
    }
  };

  const applySelectedProfileOption = (index) => {
    if (index < 0 || index >= profilePickerOptions.length) {
      return;
    }
    selectedProfileOptions = profilePickerOptions.slice();
    selectedItem = selectedProfileOptions[index];
    syncSelectedProfile(profileOptionKey(selectedItem));
    renderSelectedItem();
    clearLineInputs();
    closeProfilePicker();
  };

  const renderProfilePicker = () => {
    if (!profileModalOptionsEl) {
      return;
    }
    if (!profilePickerBaseItem || !profilePickerOptions.length) {
      profileModalOptionsEl.innerHTML = '<div class="text-muted small">Belum ada profil yang bisa dipilih untuk item ini.</div>';
      return;
    }
    profileModalTitleEl.textContent = objectLabel(profilePickerBaseItem);
    profileModalMetaEl.textContent = 'Pilih profil stok divisi yang benar untuk item ini sebelum line adjustment dibuat.';
    profileModalOptionsEl.innerHTML = profilePickerOptions.map((option, index) => {
      const availPrimary = fmt(isWarehouseScope ? option.available_qty_buy : option.available_qty_content) + ' ' + (qtyUnitByScope(option) || '');
      const secondaryQty = isWarehouseScope
        ? (fmt(option.available_qty_content) + ' ' + (option.default_content_uom_code || ''))
        : (fmt(option.available_qty_buy) + ' ' + (option.default_buy_uom_code || ''));
      const hasActiveStock = hasPositiveProfileStock(option);
      const description = [
        option.profile_description || '',
        option.profile_expired_date ? ('Exp ' + option.profile_expired_date) : '',
        !hasActiveStock ? 'Belum ada saldo aktif pada divisi/tujuan ini.' : ''
      ].filter(Boolean).join(' | ');
      const updatedLabel = formatUpdatedAt(option.updated_at || '');
      return ''
        + '<button type="button" class="adjustment-profile-choice-card" data-profile-choice-index="' + index + '">'
          + '<div class="adjustment-profile-choice-head">'
            + '<div class="adjustment-profile-choice-title">' + escHtml(profileLabel(option)) + '</div>'
            + '<span class="adjustment-profile-choice-key">' + escHtml(option.profile_key || 'Tanpa profile_key') + '</span>'
          + '</div>'
          + '<div class="adjustment-profile-choice-desc">' + escHtml(description || 'Tidak ada deskripsi tambahan.') + '</div>'
          + '<div class="adjustment-profile-choice-meta">Update terakhir: ' + escHtml(updatedLabel) + '</div>'
          + '<div class="adjustment-profile-choice-grid">'
            + '<div class="adjustment-profile-choice-metric"><span class="label">Avail</span><strong>' + escHtml(availPrimary) + '</strong></div>'
            + '<div class="adjustment-profile-choice-metric"><span class="label">Setara</span><strong>' + escHtml(secondaryQty) + '</strong></div>'
            + '<div class="adjustment-profile-choice-metric"><span class="label">' + escHtml(costLabelByScope()) + '</span><strong>' + escHtml(fmt6(costByScope(option, Number(option.avg_cost_per_content || 0)))) + '</strong></div>'
          + '</div>'
        + '</button>';
    }).join('');
    profileModalOptionsEl.querySelectorAll('[data-profile-choice-index]').forEach((button) => {
      button.addEventListener('click', () => {
        applySelectedProfileOption(Number(button.getAttribute('data-profile-choice-index') || -1));
      });
    });
  };

  const openProfilePicker = (baseItem, options) => {
    profilePickerBaseItem = baseItem || null;
    profilePickerOptions = Array.isArray(options) ? options.slice() : [];
    if (!profilePickerBaseItem || !profilePickerOptions.length) {
      showAlert('warning', 'Profil item tidak ditemukan untuk adjustment ini.');
      return;
    }
    const modal = getProfileModalInstance();
    if (!modal) {
      applySelectedProfileOption(0);
      return;
    }
    renderProfilePicker();
    modal.show();
  };

  const syncDivisionDestinationOptions = (divisionEl, destinationEl, options, preserveGroups) => {
    if (!divisionEl || !destinationEl) {
      return;
    }
    const divisionId = parseInt(divisionEl.value || '0', 10);
    const current = String(destinationEl.value || '').toUpperCase();
    let filtered = options.slice();
    if (Number.isFinite(divisionId) && divisionId > 0 && destinationGuardMap[String(divisionId)]) {
      const allowed = (destinationGuardMap[String(divisionId)] || []).map((value) => String(value || '').toUpperCase());
      filtered = options.filter((option) => {
        if (preserveGroups && (option.value === 'ALL' || option.value === 'REGULER' || option.value === 'EVENT')) {
          return true;
        }
        return allowed.indexOf(option.value) !== -1;
      });
    }
    destinationEl.innerHTML = filtered.map((option) => '<option value="' + escHtml(option.value) + '">' + escHtml(option.label) + '</option>').join('');
    if (!filtered.length) {
      destinationEl.value = '';
      return;
    }
    const selected = filtered.some((option) => option.value === current)
      ? current
      : (preserveGroups ? 'ALL' : filtered[0].value);
    destinationEl.value = selected;
  };

  const showAlert = (type, message) => {
    alertArea.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">'
      + message
      + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
  };

  const askConfirmation = ({ kicker = 'Konfirmasi', title = 'Konfirmasi aksi', message = '', note = '', confirmLabel = 'Lanjutkan', confirmClass = 'btn-primary' }) => {
    if (!confirmModalEl || !confirmKickerEl || !confirmTitleEl || !confirmMessageEl || !confirmNoteEl || !confirmSubmitBtn) {
      showAlert('danger', 'Komponen konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.');
      return Promise.resolve(false);
    }
    if (confirmResolver) {
      confirmResolver(false);
      confirmResolver = null;
    }
    confirmKickerEl.textContent = kicker;
    confirmTitleEl.textContent = title;
    confirmMessageEl.textContent = message;
    confirmNoteEl.textContent = note;
    confirmSubmitBtn.textContent = confirmLabel;
    confirmSubmitBtn.className = 'btn px-4 ' + confirmClass;
    if (confirmModal) {
      confirmModal.show();
    } else {
      confirmModalEl.style.display = 'block';
      confirmModalEl.removeAttribute('aria-hidden');
      confirmModalEl.setAttribute('aria-modal', 'true');
      confirmModalEl.classList.add('show');
      document.body.classList.add('modal-open');
      if (!confirmBackdropEl) {
        confirmBackdropEl = document.createElement('div');
        confirmBackdropEl.className = 'modal-backdrop fade show';
        document.body.appendChild(confirmBackdropEl);
      }
    }
    return new Promise((resolve) => {
      confirmResolver = resolve;
    });
  };

  const closeConfirmation = (accepted) => {
    if (confirmResolver) {
      const resolve = confirmResolver;
      confirmResolver = null;
      resolve(accepted);
    }
    if (confirmModal) {
      confirmModal.hide();
      return;
    }
    if (confirmModalEl) {
      confirmModalEl.classList.remove('show');
      confirmModalEl.setAttribute('aria-hidden', 'true');
      confirmModalEl.removeAttribute('aria-modal');
      confirmModalEl.style.display = 'none';
    }
    document.body.classList.remove('modal-open');
    if (confirmBackdropEl) {
      confirmBackdropEl.remove();
      confirmBackdropEl = null;
    }
  };

  confirmSubmitBtn?.addEventListener('click', () => {
    closeConfirmation(true);
  });

  confirmModalEl?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
    button.addEventListener('click', () => {
      closeConfirmation(false);
    });
  });

  confirmModalEl?.addEventListener('hidden.bs.modal', () => {
    if (confirmResolver) {
      const resolve = confirmResolver;
      confirmResolver = null;
      resolve(false);
    }
  });

  confirmModalEl?.addEventListener('click', (event) => {
    if (!confirmModal && event.target === confirmModalEl) {
      closeConfirmation(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (!confirmModal && confirmResolver && event.key === 'Escape') {
      closeConfirmation(false);
    }
  });

  if (isDivisionScope) {
    formDivisionEl?.addEventListener('change', () => {
      syncDivisionDestinationOptions(formDivisionEl, formDestinationEl, formDestinationOptions, false);
      selectedItem = null;
      selectedProfileOptions = [];
      currentSearchItems = [];
      renderSelectedItem();
      searchResults.innerHTML = '';
      if (searchInput) {
        searchInput.value = '';
      }
    });
    syncDivisionDestinationOptions(formDivisionEl, formDestinationEl, formDestinationOptions, false);
    [
      [docFilterDivisionEl, docFilterDestinationEl],
      [lineFilterDivisionEl, lineFilterDestinationEl],
    ].forEach(([divisionEl, destinationEl]) => {
      if (!divisionEl || !destinationEl) {
        return;
      }
      divisionEl.addEventListener('change', () => {
        syncDivisionDestinationOptions(divisionEl, destinationEl, filterDestinationOptions, true);
      });
      syncDivisionDestinationOptions(divisionEl, destinationEl, filterDestinationOptions, true);
    });
  }

  const clearLineInputs = () => {
    ['qty_waste_content', 'qty_spoil_content', 'qty_process_loss_content', 'qty_variance_content', 'qty_adjustment_plus_content', 'unit_cost'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.value = id === 'unit_cost'
          ? (selectedItem ? String(costByScope(selectedItem, Number(selectedItem.avg_cost_per_content || 0))) : '0')
          : '0';
      }
    });
    ['waste_reason_code', 'spoil_reason_code', 'process_loss_reason_code', 'variance_reason_code', 'adjustment_plus_reason_code'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.value = 'other';
      }
    });
    const noteEl = document.getElementById('line_note');
    if (noteEl) noteEl.value = '';
    const lotEl = document.getElementById('inbound_lot_no');
    if (lotEl) lotEl.value = '';
    const expEl = document.getElementById('inbound_expiry_date');
    if (expEl) expEl.value = '';
  };

  const renderSelectedItem = () => {
    if (!selectedItem) {
      selectedCard.innerHTML = '<div class="fw-semibold mb-1">Belum ada profile dipilih.</div><div class="text-muted small">Pilih item/profile dari hasil pencarian untuk menambahkan line adjustment.</div>';
      return;
    }
    syncSelectedProfile();
    selectedCard.innerHTML = ''
      + '<div class="fw-semibold">' + escHtml(objectLabel(selectedItem)) + '</div>'
      + '<div class="small text-muted mb-2">'
      + escHtml(profileLabel(selectedItem))
      + '</div>'
      + (isDivisionScope
          ? ('<div class="mb-3">'
              + '<label class="form-label mb-1">Profile Yang Di-adjust</label>'
              + '<div class="small text-muted">' + escHtml(selectedItem.profile_key || 'Tanpa profile_key') + '</div>'
              + (selectedProfileOptions.length > 1
                  ? ('<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-action="change-profile">Pilih Ulang Profil</button></div>'
                    + '<div class="form-text">Gunakan modal picker untuk mengganti profil item ini.</div>')
                  : '')
            + '</div>')
          : '')
      + '<div class="row g-2 small">'
        + '<div class="col-md-4"><div class="text-muted">Avail ' + (isWarehouseScope ? 'Pack' : 'Isi') + '</div><div class="fw-semibold">' + fmt(isWarehouseScope ? selectedItem.available_qty_buy : selectedItem.available_qty_content) + ' ' + qtyUnitByScope(selectedItem) + '</div></div>'
        + '<div class="col-md-4"><div class="text-muted">' + (isWarehouseScope ? 'Setara Isi' : 'Setara Pack') + '</div><div class="fw-semibold">' + fmt(isWarehouseScope ? selectedItem.available_qty_content : selectedItem.available_qty_buy) + ' ' + (isWarehouseScope ? (selectedItem.default_content_uom_code || '') : (selectedItem.default_buy_uom_code || '')) + '</div></div>'
        + '<div class="col-md-4"><div class="text-muted">' + costLabelByScope() + '</div><div class="fw-semibold">' + fmt6(costByScope(selectedItem, Number(selectedItem.avg_cost_per_content || 0))) + '</div></div>'
      + '</div>';
  };

  const renderDraftLines = () => {
    if (!lines.length) {
      draftTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Belum ada line draft.</td></tr>';
      return;
    }
    draftTableBody.innerHTML = lines.map((line, index) => {
      const objectText = line.material_id
        ? ((line.material_code || line.item_code || '-') + ' - ' + (line.material_name || line.item_name || '-'))
        : ((line.item_code || line.material_code || '-') + ' - ' + (line.item_name || line.material_name || '-'));
      const profileText = [line.profile_name, line.profile_brand].filter(Boolean).join(' | ');
      const reasonParts = [];
      if (Number(line.qty_waste_content || 0) > 0) reasonParts.push('Waste: ' + reasonLabel('WASTE', line.waste_reason_code));
      if (Number(line.qty_spoil_content || 0) > 0) reasonParts.push('Spoil: ' + reasonLabel('SPOILAGE', line.spoil_reason_code));
      if (Number(line.qty_process_loss_content || 0) > 0) reasonParts.push('P.Loss: ' + reasonLabel('PROCESS_LOSS', line.process_loss_reason_code));
      if (Number(line.qty_variance_content || 0) > 0) reasonParts.push('Variance: ' + reasonLabel('VARIANCE', line.variance_reason_code));
      if (Number(line.qty_adjustment_plus_content || 0) > 0) reasonParts.push('Adj+: ' + reasonLabel('ADJUSTMENT_PLUS', line.adjustment_plus_reason_code));
      return ''
        + '<tr>'
        + '<td><div class="fw-semibold">' + objectText + '</div><small class="text-muted d-block">' + (profileText || '-') + '</small><small class="text-muted">' + (reasonParts.join(' | ') || '-') + '</small></td>'
        + '<td class="text-end">' + fmt(isWarehouseScope ? line.available_qty_buy : line.available_qty_content) + ' ' + qtyUnitByScope(line) + '</td>'
        + '<td class="text-end">' + fmt(qtyByScope(line, line.qty_waste_content)) + '</td>'
        + '<td class="text-end">' + fmt(qtyByScope(line, line.qty_spoil_content)) + '</td>'
        + '<td class="text-end">' + fmt(qtyByScope(line, line.qty_process_loss_content)) + '</td>'
        + '<td class="text-end">' + fmt(qtyByScope(line, line.qty_variance_content)) + '</td>'
        + '<td class="text-end">' + fmt(qtyByScope(line, line.qty_adjustment_plus_content)) + '</td>'
        + '<td class="text-end">' + fmt6(costByScope(line, line.unit_cost)) + '</td>'
        + '<td><div>' + (line.inbound_lot_no || '-') + '</div><small class="text-muted">' + (line.inbound_expiry_date || '') + '</small></td>'
        + '<td class="action-cell"><div class="d-flex gap-1 flex-nowrap justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger action-icon-btn btn-remove-line" data-index="' + index + '" title="Hapus" aria-label="Hapus"><i class="ri ri-delete-bin-line"></i></button></div></td>'
        + '</tr>';
    }).join('');
    draftTableBody.querySelectorAll('.btn-remove-line').forEach((button) => {
      button.addEventListener('click', () => {
        const idx = Number(button.dataset.index || -1);
        if (idx >= 0) {
          lines.splice(idx, 1);
          renderDraftLines();
        }
      });
    });
  };

  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, options));
    return response.json();
  };

  const performSearch = async () => {
    const q = String(searchInput?.value || '').trim();
    if (q.length < 2) {
      currentSearchItems = [];
      searchResults.innerHTML = '';
      return;
    }
    const params = new URLSearchParams({ q, limit: '12', stock_scope: stockScope });
    if (isDivisionScope) {
      params.set('division_id', String(document.getElementById('division_id')?.value || ''));
      params.set('destination', String(document.getElementById('destination_type')?.value || ''));
    }
    const res = await fetchJson('<?php echo $searchUrl; ?>?' + params.toString());
    if (!res.ok) {
      currentSearchItems = [];
      searchResults.innerHTML = '<div class="list-group-item text-danger">Pencarian gagal.</div>';
      return;
    }
    const items = Array.isArray(res.items) ? res.items : [];
    currentSearchItems = items;
    if (!items.length) {
      searchResults.innerHTML = '<div class="list-group-item text-muted">Tidak ada hasil.</div>';
      return;
    }
    searchResults.innerHTML = items.map((item, index) => {
      const objectCode = item.material_id ? (item.material_code || item.item_code || '-') : (item.item_code || item.material_code || '-');
      const objectName = item.material_id ? (item.material_name || item.item_name || '-') : (item.item_name || item.material_name || '-');
      const profileText = [item.profile_name, item.profile_brand, item.profile_description].filter(Boolean).join(' | ');
      return ''
        + '<button type="button" class="list-group-item list-group-item-action adjustment-search-result" data-index="' + index + '">'
        + '<div class="fw-semibold">' + objectCode + ' - ' + objectName + '</div>'
        + '<div class="small text-muted">' + (profileText || 'Tanpa profile') + '</div>'
        + '<div class="small">Avail: ' + fmt(isWarehouseScope ? item.available_qty_buy : item.available_qty_content) + ' ' + qtyUnitByScope(item) + (isWarehouseScope ? (' | Setara: ' + fmt(item.available_qty_content) + ' ' + (item.default_content_uom_code || '')) : '') + ' | ' + (isWarehouseScope ? 'Harga satuan' : 'Avg cost') + ': ' + fmt6(costByScope(item, Number(item.avg_cost_per_content || 0))) + '</div>'
        + '</button>';
    }).join('');
    searchResults.querySelectorAll('.adjustment-search-result').forEach((button) => {
      button.addEventListener('click', async () => {
        const idx = Number(button.dataset.index || -1);
        const clickedItem = items[idx] || null;
        searchResults.innerHTML = '';
        if (!clickedItem) {
          return;
        }
        if (!isDivisionScope) {
          selectedItem = clickedItem;
          selectedProfileOptions = [clickedItem];
          renderSelectedItem();
          clearLineInputs();
          return;
        }
        const lookupId = ++profileLookupToken;
        const options = await resolveProfileOptions(clickedItem);
        if (lookupId !== profileLookupToken) {
          return;
        }
        openProfilePicker(clickedItem, options);
      });
    });
  };

  selectedCard?.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-action="change-profile"]');
    if (!trigger || !selectedItem || !selectedProfileOptions.length) {
      return;
    }
    openProfilePicker(selectedItem, selectedProfileOptions);
  });

  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(performSearch, 250);
  });

  document.getElementById('btn-add-line')?.addEventListener('click', () => {
    if (!selectedItem) {
      showAlert('warning', 'Pilih item/profile lebih dulu.');
      return;
    }
    if (isDivisionScope && selectedProfileOptions.length > 1 && !String(selectedItem.profile_key || '').trim()) {
      showAlert('warning', 'Pilih profil item yang akan di-adjust lebih dulu.');
      return;
    }
    if (isDivisionScope) {
      const divisionId = Number(document.getElementById('division_id')?.value || 0);
      const destinationType = String(document.getElementById('destination_type')?.value || '');
      if (divisionId <= 0 || destinationType === '') {
        showAlert('warning', 'Divisi dan tujuan wajib dipilih untuk adjustment divisi.');
        return;
      }
    }

    const line = {
      stock_domain: selectedItem.stock_domain || (selectedItem.material_id ? 'MATERIAL' : 'ITEM'),
      item_id: Number(selectedItem.id || 0) || null,
      material_id: Number(selectedItem.material_id || 0) || null,
      item_code: selectedItem.item_code || '',
      item_name: selectedItem.item_name || '',
      material_code: selectedItem.material_code || '',
      material_name: selectedItem.material_name || '',
      buy_uom_id: Number(selectedItem.default_buy_uom_id || 0) || null,
      content_uom_id: Number(selectedItem.default_content_uom_id || 0) || null,
      profile_key: selectedItem.profile_key || '',
      profile_name: selectedItem.profile_name || '',
      profile_brand: selectedItem.profile_brand || '',
      profile_description: selectedItem.profile_description || '',
      profile_content_per_buy: Number(selectedItem.default_content_per_buy || 1) || 1,
      profile_buy_uom_code: selectedItem.default_buy_uom_code || '',
      profile_content_uom_code: selectedItem.default_content_uom_code || '',
      available_qty_buy: Number(selectedItem.available_qty_buy || 0),
      available_qty_content: Number(selectedItem.available_qty_content || 0),
      qty_waste_content: 0,
      waste_reason_code: String(document.getElementById('waste_reason_code')?.value || 'other').trim(),
      qty_spoil_content: 0,
      spoil_reason_code: String(document.getElementById('spoil_reason_code')?.value || 'other').trim(),
      qty_process_loss_content: 0,
      process_loss_reason_code: String(document.getElementById('process_loss_reason_code')?.value || 'other').trim(),
      qty_variance_content: 0,
      variance_reason_code: String(document.getElementById('variance_reason_code')?.value || 'other').trim(),
      qty_adjustment_plus_content: 0,
      adjustment_plus_reason_code: String(document.getElementById('adjustment_plus_reason_code')?.value || 'other').trim(),
      unit_cost: 0,
      inbound_lot_no: String(document.getElementById('inbound_lot_no')?.value || '').trim(),
      inbound_expiry_date: String(document.getElementById('inbound_expiry_date')?.value || '').trim(),
      note: String(document.getElementById('line_note')?.value || '').trim()
    };

    const contentPerBuy = contentPerBuyValue(line);
    const rawWaste = Number(document.getElementById('qty_waste_content')?.value || 0);
    const rawSpoil = Number(document.getElementById('qty_spoil_content')?.value || 0);
    const rawProcessLoss = Number(document.getElementById('qty_process_loss_content')?.value || 0);
    const rawVariance = Number(document.getElementById('qty_variance_content')?.value || 0);
    const rawAdjustmentPlus = Number(document.getElementById('qty_adjustment_plus_content')?.value || 0);
    const rawUnitCost = Number(document.getElementById('unit_cost')?.value || 0);

    if (isWarehouseScope) {
      line.qty_waste_content = rawWaste * contentPerBuy;
      line.qty_spoil_content = rawSpoil * contentPerBuy;
      line.qty_process_loss_content = rawProcessLoss * contentPerBuy;
      line.qty_variance_content = rawVariance * contentPerBuy;
      line.qty_adjustment_plus_content = rawAdjustmentPlus * contentPerBuy;
      line.unit_cost = rawUnitCost > 0 ? (rawUnitCost / contentPerBuy) : 0;
    } else {
      line.qty_waste_content = rawWaste;
      line.qty_spoil_content = rawSpoil;
      line.qty_process_loss_content = rawProcessLoss;
      line.qty_variance_content = rawVariance;
      line.qty_adjustment_plus_content = rawAdjustmentPlus;
      line.unit_cost = rawUnitCost;
    }

    const totalQty = line.qty_waste_content + line.qty_spoil_content + line.qty_process_loss_content + line.qty_variance_content + line.qty_adjustment_plus_content;
    if (totalQty <= 0) {
      showAlert('warning', 'Isi minimal satu qty adjustment lebih dari nol.');
      return;
    }
    if (line.qty_adjustment_plus_content > 0 && line.unit_cost <= 0) {
      showAlert('warning', 'Adjustment plus wajib punya unit cost lebih dari nol.');
      return;
    }

    lines.push(line);
    renderDraftLines();
    clearLineInputs();
    showAlert('success', 'Line ditambahkan ke draft.');
  });

  document.getElementById('btn-save-draft')?.addEventListener('click', async () => {
    const saveDraftBtn = document.getElementById('btn-save-draft');
    if (!lines.length) {
      showAlert('warning', 'Belum ada line draft untuk disimpan.');
      return;
    }
    const payload = {
      stock_scope: stockScope,
      adjustment_date: String(document.getElementById('adjustment_date')?.value || ''),
      notes: String(document.getElementById('header_notes')?.value || '').trim(),
      lines
    };
    if (isDivisionScope) {
      payload.division_id = Number(document.getElementById('division_id')?.value || 0) || null;
      payload.destination_type = String(document.getElementById('destination_type')?.value || '').trim();
    }
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(saveDraftBtn, 'Menyimpan draft...');
    }
    try {
      const res = await fetchJson('<?php echo $storeUrl; ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
          window.FinanceUI.clearButtonLoading(saveDraftBtn);
        }
        showAlert('danger', res.message || 'Gagal menyimpan draft adjustment.');
        return;
      }
      lines.splice(0, lines.length);
      renderDraftLines();
      showAlert('success', 'Draft berhasil disimpan. Halaman akan dimuat ulang.');
      window.location.reload();
    } catch (error) {
      if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
        window.FinanceUI.clearButtonLoading(saveDraftBtn);
      }
      showAlert('danger', error.message || 'Gagal menyimpan draft adjustment.');
    }
  });

  document.querySelectorAll('.btn-post-doc').forEach((button) => {
    button.addEventListener('click', async () => {
      const confirmed = await askConfirmation({
        kicker: 'Post Adjustment',
        title: 'Post dokumen ini?',
        message: 'Stok live, stok harian, dan lot FIFO akan langsung diperbarui.',
        note: 'Pastikan seluruh line adjustment sudah final sebelum diposting.',
        confirmLabel: 'Ya, post sekarang',
        confirmClass: 'btn-success'
      });
      if (!confirmed) {
        return;
      }
      if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
        window.FinanceUI.setButtonLoading(button, 'Posting...');
      }
      try {
        const res = await fetchJson('<?php echo $postBaseUrl; ?>/' + button.dataset.id, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: '{}'
        });
        if (!res.ok) {
          if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
            window.FinanceUI.clearButtonLoading(button);
          }
          showAlert('danger', res.message || 'Gagal post adjustment.');
          return;
        }
        window.location.reload();
      } catch (error) {
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
          window.FinanceUI.clearButtonLoading(button);
        }
        showAlert('danger', error.message || 'Gagal post adjustment.');
      }
    });
  });

  document.querySelectorAll('.btn-delete-doc').forEach((button) => {
    button.addEventListener('click', async () => {
      const confirmed = await askConfirmation({
        kicker: 'Hapus Draft',
        title: 'Hapus draft adjustment ini?',
        message: 'Dokumen draft dan line yang belum diposting akan dihapus dari daftar.',
        note: 'Aksi ini dipakai untuk membatalkan draft sebelum stok diproses.',
        confirmLabel: 'Ya, hapus draft',
        confirmClass: 'btn-danger'
      });
      if (!confirmed) {
        return;
      }
      if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
        window.FinanceUI.setButtonLoading(button, 'Menghapus...');
      }
      try {
        const res = await fetchJson('<?php echo $deleteBaseUrl; ?>/' + button.dataset.id, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: '{}'
        });
        if (!res.ok) {
          if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
            window.FinanceUI.clearButtonLoading(button);
          }
          showAlert('danger', res.message || 'Gagal menghapus draft adjustment.');
          return;
        }
        window.location.reload();
      } catch (error) {
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
          window.FinanceUI.clearButtonLoading(button);
        }
        showAlert('danger', error.message || 'Gagal menghapus draft adjustment.');
      }
    });
  });

  document.querySelectorAll('.btn-void-doc').forEach((button) => {
    button.addEventListener('click', async () => {
      const confirmed = await askConfirmation({
        kicker: 'Void Adjustment',
        title: 'Batalkan dokumen adjustment ini?',
        message: 'Posting adjustment yang sudah masuk ke stok, daily, dan lot akan dibatalkan.',
        note: 'VOID berarti membatalkan dokumen posted dan mengembalikan histori stok seperti sebelum adjustment.',
        confirmLabel: 'Ya, void sekarang',
        confirmClass: 'btn-danger'
      });
      if (!confirmed) {
        return;
      }
      if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
        window.FinanceUI.setButtonLoading(button, 'Void...');
      }
      try {
        const res = await fetchJson('<?php echo $voidBaseUrl; ?>/' + button.dataset.id, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: '{}'
        });
        if (!res.ok) {
          if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
            window.FinanceUI.clearButtonLoading(button);
          }
          showAlert('danger', res.message || 'Gagal VOID adjustment.');
          return;
        }
        window.location.reload();
      } catch (error) {
        if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
          window.FinanceUI.clearButtonLoading(button);
        }
        showAlert('danger', error.message || 'Gagal VOID adjustment.');
      }
    });
  });

  renderSelectedItem();
  renderDraftLines();
})();
</script>