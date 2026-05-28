<?php
$rows = is_array($rows ?? null) ? $rows : [];
$monthlyRows = is_array($monthly_rows ?? null) ? $monthly_rows : [];
$components = is_array($components ?? null) ? $components : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$editOpening = is_array($edit_opening ?? null) ? $edit_opening : [];
$editHeader = is_array($editOpening['header'] ?? null) ? $editOpening['header'] : [];
$editLines = is_array($editOpening['lines'] ?? null) ? $editOpening['lines'] : [];
$detailOpening = is_array($detail_opening ?? null) ? $detail_opening : [];
$detailHeader = is_array($detailOpening['header'] ?? null) ? $detailOpening['header'] : [];
$detailLines = is_array($detailOpening['lines'] ?? null) ? $detailOpening['lines'] : [];
$detailMovementRows = is_array($detailOpening['movement_rows'] ?? null) ? $detailOpening['movement_rows'] : [];
$detailEffectiveMovementRows = is_array($detailOpening['effective_movement_rows'] ?? null) ? $detailOpening['effective_movement_rows'] : [];
$detailLotRows = is_array($detailOpening['lot_rows'] ?? null) ? $detailOpening['lot_rows'] : [];
$detailActiveLotRows = is_array($detailOpening['active_lot_rows'] ?? null) ? $detailOpening['active_lot_rows'] : [];
$detailSummary = is_array($detailOpening['summary'] ?? null) ? $detailOpening['summary'] : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
$q = (string)($q ?? '');
$month = preg_match('/^\d{4}-\d{2}$/', (string)($month ?? '')) ? (string)$month : date('Y-m');
$selectedLocationType = (string)($selected_location_type ?? '');
$selectedDivisionId = (int)($selected_division_id ?? 0);
$openingTab = in_array((string)($opening_tab ?? ''), ['documents', 'detail', 'snapshot'], true) ? (string)$opening_tab : (!empty($detailHeader) ? 'detail' : 'documents');
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT') {
    return 'Event';
  }
  if ($value === 'BAR' || $value === 'KITCHEN') {
    return 'Reguler';
  }
  return $value !== '' ? $value : '-';
};
$componentMap = [];
foreach ($components as $component) {
  $componentId = (int)($component['id'] ?? 0);
  if ($componentId > 0) {
    $componentMap[$componentId] = $component;
  }
}
$divisionMap = [];
foreach ($divisions as $division) {
  $divisionId = (int)($division['id'] ?? 0);
  if ($divisionId > 0) {
    $divisionMap[$divisionId] = $division;
  }
}
$editPayload = null;
if (!empty($editHeader) && strtoupper((string)($editHeader['status'] ?? '')) === 'DRAFT') {
  $editPayload = [
    'id' => (int)($editHeader['id'] ?? 0),
    'opening_no' => (string)($editHeader['opening_no'] ?? ''),
    'opening_month' => substr((string)($editHeader['opening_date'] ?? date('Y-m-d')), 0, 7),
    'notes' => (string)($editHeader['notes'] ?? ''),
    'location_group' => $locationGroupLabel((string)($editHeader['location_type'] ?? '')) === 'Event' ? 'EVENT' : 'REGULER',
    'lines' => [],
  ];
  foreach ($editLines as $line) {
    $componentId = (int)($line['component_id'] ?? 0);
    $component = $componentMap[$componentId] ?? [];
    $division = $divisionMap[(int)($component['operational_division_id'] ?? ($editHeader['division_id'] ?? 0))] ?? [];
    $editPayload['lines'][] = [
      'component_id' => $componentId > 0 ? (string)$componentId : '',
      'component_label' => (string)($line['component_name'] ?? $component['component_name'] ?? ''),
      'component_division_id' => (string)($component['operational_division_id'] ?? ($editHeader['division_id'] ?? '')),
      'component_division_code' => (string)($division['code'] ?? $component['division_code'] ?? ''),
      'component_division_name' => (string)($division['name'] ?? $component['division_name'] ?? ($editHeader['division_name'] ?? '')),
      'uom_id' => (string)($line['uom_id'] ?? $component['uom_id'] ?? ''),
      'opening_qty' => (string)($line['opening_qty'] ?? ''),
      'unit_cost' => (string)($line['unit_cost'] ?? ''),
      'note' => (string)($line['note'] ?? ''),
    ];
  }
}
$editQuery = [
  'month' => $month,
  'location_type' => $selectedLocationType,
  'division_id' => $selectedDivisionId > 0 ? $selectedDivisionId : '',
  'q' => $q,
];
$editBaseUrl = site_url('production/component-openings');
$tabBaseQuery = $editQuery;
if (!empty($editPayload['id'])) {
  $tabBaseQuery['edit'] = (int)$editPayload['id'];
}
if (!empty($detailHeader['id'])) {
  $tabBaseQuery['detail'] = (int)$detailHeader['id'];
}
$tabUrl = static function (string $tab, array $extra = []) use ($editBaseUrl, $tabBaseQuery): string {
  return $editBaseUrl . '?' . http_build_query(array_merge($tabBaseQuery, ['tab' => $tab], $extra));
};
$detailAdjustmentUrl = '';
$detailReopenUrl = '';
if (!empty($detailHeader) && strtoupper((string)($detailHeader['status'] ?? '')) === 'POSTED') {
  $detailAdjustmentUrl = site_url('production/component-adjustments') . '?' . http_build_query([
    'adjustment_date' => (string)($detailHeader['opening_date'] ?? date('Y-m-d')),
    'location_type' => (string)($detailHeader['location_type'] ?? ''),
    'division_id' => (int)($detailHeader['division_id'] ?? 0),
    'notes' => 'Koreksi kekurangan opening ' . (string)($detailHeader['opening_no'] ?? ''),
    'source_opening_no' => (string)($detailHeader['opening_no'] ?? ''),
  ]);
  $detailReopenUrl = site_url('production/component-openings/reopen/' . (int)($detailHeader['id'] ?? 0));
}

$componentSummary = count($monthlyRows);
$monthlyQty = 0.0;
$monthlyValue = 0.0;
foreach ($monthlyRows as $monthlyRow) {
    $monthlyQty += (float)($monthlyRow['opening_qty'] ?? 0);
    $monthlyValue += (float)($monthlyRow['total_value'] ?? 0);
}
?>

<style>
  .component-doc-table select,
  .component-doc-table input {
    min-width: 88px;
  }
  .component-doc-table .component-picker-input {
    min-width: 280px;
  }
  .component-doc-table .component-uom-select {
    min-width: 170px;
  }
  .component-doc-summary {
    background: #fbf8f3;
    border: 1px solid #eadfce;
    border-radius: 16px;
    padding: 1rem 1.1rem;
  }
  .component-doc-summary-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #3d342d;
  }
  .component-add-line-btn {
    background: #f0b429;
    border-color: #f0b429;
    color: #3b2a00;
    font-weight: 700;
    box-shadow: 0 0.35rem 0.9rem rgba(240, 180, 41, 0.28);
  }
  .component-add-line-btn:hover,
  .component-add-line-btn:focus {
    background: #d79a11;
    border-color: #d79a11;
    color: #2e2100;
  }
  .component-edit-banner {
    background: #fff5d9;
    border: 1px solid #f0d38a;
    border-radius: 14px;
    padding: 0.85rem 1rem;
  }
  .component-opening-tabs .nav-link {
    font-weight: 600;
  }
  .component-opening-detail-empty {
    border: 1px dashed #d7c7c2;
    border-radius: 14px;
    background: #fffaf7;
    padding: 1rem 1.1rem;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1">Opening Base/Prepare</h4>
  <small class="text-muted">Editor baris untuk stok awal component per bulan. Simpan sebagai DRAFT, lalu POST saat siap menulis ledger, balance, dan lot inbound awal di tanggal 1.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opening']); ?>

<div id="component-opening-alert" class="mb-3"></div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <div class="card border-0 shadow-sm h-100" id="component-opening-form-card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h5 class="mb-1"><?php echo !empty($editPayload) ? 'Edit Draft Opening' : 'Form Opening'; ?></h5>
            <small class="text-muted"><?php echo !empty($editPayload) ? 'Anda sedang melengkapi draft existing. Perubahan akan menyimpan ulang dokumen yang sama.' : 'Tidak perlu lagi tulis JSON mentah. Tambah baris, pilih component, isi qty dan biaya.'; ?></small>
          </div>
          <div class="d-flex gap-2">
            <?php if (!empty($editPayload)): ?>
              <a href="<?php echo $editBaseUrl; ?>" class="btn btn-outline-secondary btn-sm">Batal Edit</a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm component-add-line-btn" id="btn-add-opening-line">Tambah Baris</button>
          </div>
        </div>

        <?php if (!empty($editPayload)): ?>
          <div class="component-edit-banner mb-3">
            <div class="fw-semibold mb-1">Sedang edit dokumen <?php echo html_escape((string)($editHeader['opening_no'] ?? '-')); ?></div>
            <small class="text-muted">Jika opening sebelumnya kurang, tambahkan atau koreksi baris di sini lalu simpan draft kembali sebelum di-posting.</small>
          </div>
        <?php endif; ?>

        <form id="frmOpening" autocomplete="off">
          <input type="hidden" name="id" value="<?php echo (int)($editPayload['id'] ?? 0); ?>">
          <input type="hidden" name="opening_no" value="<?php echo html_escape((string)($editPayload['opening_no'] ?? '')); ?>">
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label">Bulan Opening</label>
              <input type="month" class="form-control" name="opening_month" value="<?php echo html_escape((string)($editPayload['opening_month'] ?? date('Y-m'))); ?>" required>
              <div class="form-text">Posting otomatis memakai tanggal 1 pada bulan yang dipilih.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Divisi</label>
              <input type="hidden" name="division_id" id="opening-division-id" value="">
              <input type="text" class="form-control" id="opening-division-name" value="Ikuti component" readonly>
              <div class="form-text" id="opening-division-help">Divisi otomatis mengikuti component yang dipilih di baris opening.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Lokasi</label>
              <input type="hidden" name="location_type" id="opening-location-type" value="">
              <select class="form-select" id="opening-location-group" required>
                <option value="">Pilih lokasi...</option>
                <option value="REGULER" <?php echo (($editPayload['location_group'] ?? '') === 'REGULER') ? 'selected' : ''; ?>>Reguler</option>
                <option value="EVENT" <?php echo (($editPayload['location_group'] ?? '') === 'EVENT') ? 'selected' : ''; ?>>Event</option>
              </select>
              <div class="form-text" id="opening-location-help">Pilih component dulu agar lokasi bisa diturunkan otomatis.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Catatan Header</label>
              <input type="text" class="form-control" name="notes" value="<?php echo html_escape((string)($editPayload['notes'] ?? '')); ?>" placeholder="Contoh: opening awal bulan">
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle component-doc-table mb-2">
              <thead>
                <tr>
                  <th style="width:34px;">#</th>
                  <th style="width:330px;">Component</th>
                  <th style="width:190px;">UOM</th>
                  <th style="width:130px;" class="text-end">Qty Opening</th>
                  <th style="width:150px;" class="text-end">Unit Cost</th>
                  <th>Catatan</th>
                  <th style="width:52px;"></th>
                </tr>
              </thead>
              <tbody id="opening-line-body"></tbody>
            </table>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div class="component-doc-summary d-flex flex-wrap gap-4">
              <div>
                <div class="small text-muted">Total Baris</div>
                <div class="component-doc-summary-value" id="opening-total-lines">0</div>
              </div>
              <div>
                <div class="small text-muted">Total Qty</div>
                <div class="component-doc-summary-value" id="opening-total-qty">0,00</div>
              </div>
              <div>
                <div class="small text-muted">Total Nilai</div>
                <div class="component-doc-summary-value" id="opening-total-value">Rp 0</div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo !empty($editPayload) ? 'Update DRAFT' : 'Simpan DRAFT'; ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h5 class="mb-1">Carry-Forward Bulanan</h5>
        <small class="text-muted d-block mb-3">Generate opname penutup bulan terpilih dari daily rollup, lalu otomatis buat opening bulan berikutnya.</small>

        <div class="row g-2 mb-3">
          <div class="col-12">
            <label class="form-label">Bulan Snapshot</label>
            <input type="month" class="form-control" id="monthly-month" value="<?php echo html_escape($month); ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Lokasi</label>
            <select class="form-select" id="monthly-location-type">
              <?php foreach ($locationFilterOptions as $key => $label): ?>
                <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLocationType === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Divisi</label>
            <select class="form-select" id="monthly-division-id">
              <option value="">Semua divisi</option>
              <?php foreach ($divisions as $division): ?>
                <option value="<?php echo (int)$division['id']; ?>" <?php echo $selectedDivisionId === (int)$division['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($division['code'] ?? '')); ?><?php echo !empty($division['code']) ? ' - ' : ''; ?><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="component-doc-summary mb-3 d-flex flex-wrap gap-4">
          <div>
            <div class="small text-muted">Baris Snapshot</div>
            <div class="component-doc-summary-value"><?php echo number_format($componentSummary, 0, ',', '.'); ?></div>
          </div>
          <div>
            <div class="small text-muted">Qty Opening</div>
            <div class="component-doc-summary-value"><?php echo number_format($monthlyQty, 2, ',', '.'); ?></div>
          </div>
          <div>
            <div class="small text-muted">Nilai</div>
            <div class="component-doc-summary-value">Rp <?php echo number_format($monthlyValue, 2, ',', '.'); ?></div>
          </div>
        </div>

        <button type="button" class="btn btn-outline-danger w-100" id="btn-generate-monthly-opening">Generate Opname + Opening</button>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm component-opening-tabs" id="component-opening-detail-tabs">
  <div class="card-header bg-transparent pb-0">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <h5 class="mb-1">Dokumen dan Rincian Opening</h5>
        <small class="text-muted">Filter halaman tetap berlaku untuk daftar dokumen dan snapshot bulanan.</small>
      </div>
      <form class="row g-2 align-items-end" method="get" action="<?php echo site_url('production/component-openings'); ?>">
        <input type="hidden" name="tab" value="<?php echo html_escape($openingTab); ?>">
        <?php if (!empty($editPayload['id'])): ?><input type="hidden" name="edit" value="<?php echo (int)$editPayload['id']; ?>"><?php endif; ?>
        <?php if (!empty($detailHeader['id'])): ?><input type="hidden" name="detail" value="<?php echo (int)$detailHeader['id']; ?>"><?php endif; ?>
        <div class="col-auto">
          <label class="form-label mb-1">Bulan</label>
          <input type="month" class="form-control" name="month" value="<?php echo html_escape($month); ?>">
        </div>
        <div class="col-auto">
          <label class="form-label mb-1">Lokasi</label>
          <select class="form-select" name="location_type">
            <?php foreach ($locationFilterOptions as $key => $label): ?>
              <option value="<?php echo html_escape($key); ?>" <?php echo $selectedLocationType === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select" name="division_id">
            <option value="">Semua divisi</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?php echo (int)$division['id']; ?>" <?php echo $selectedDivisionId === (int)$division['id'] ? 'selected' : ''; ?>><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label mb-1">Cari</label>
          <input type="text" class="form-control" name="q" value="<?php echo html_escape($q); ?>" placeholder="No dokumen / lokasi / divisi">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
      </form>
    </div>

    <ul class="nav nav-tabs card-header-tabs mt-3">
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'documents' ? 'active' : ''; ?>" href="<?php echo $tabUrl('documents'); ?>#component-opening-detail-tabs">Daftar Dokumen</a></li>
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'detail' ? 'active' : ''; ?>" href="<?php echo $tabUrl('detail'); ?>#component-opening-detail-tabs">Rincian</a></li>
      <li class="nav-item"><a class="nav-link <?php echo $openingTab === 'snapshot' ? 'active' : ''; ?>" href="<?php echo $tabUrl('snapshot'); ?>#component-opening-detail-tabs">Snapshot Bulanan</a></li>
    </ul>
  </div>
  <div class="card-body tab-content">
    <div class="tab-pane fade <?php echo $openingTab === 'documents' ? 'show active' : ''; ?>">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>No</th>
              <th>Bulan</th>
              <th>Lokasi</th>
              <th>Divisi</th>
              <th>Catatan</th>
              <th>Status</th>
              <th style="width:140px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada dokumen opening pada filter ini.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr id="component-opening-<?php echo (int)$row['id']; ?>">
                  <td><?php echo html_escape((string)($row['opening_no'] ?? '')); ?></td>
                  <td><?php echo html_escape(substr((string)($row['opening_date'] ?? ''), 0, 7)); ?></td>
                  <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></td>
                  <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                  <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                  <td class="component-action-cell">
                    <?php $rowDetailUrl = $tabUrl('detail', ['detail' => (int)($row['id'] ?? 0)]) . '#component-opening-detail-tabs'; ?>
                    <?php $rowEditUrl = $editBaseUrl . '?' . http_build_query(array_merge($editQuery, ['edit' => (int)($row['id'] ?? 0), 'detail' => (int)($row['id'] ?? 0), 'tab' => 'detail'])) . '#component-opening-form-card'; ?>
                    <?php if (strtoupper((string)($row['status'] ?? '')) === 'DRAFT'): ?>
                      <div class="component-action-stack">
                        <a href="<?php echo $rowEditUrl; ?>" class="btn btn-outline-primary action-icon-btn component-action-btn" title="Edit Draft" aria-label="Edit Draft"><i class="ri ri-edit-line"></i></a>
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info action-icon-btn component-action-btn" title="Buka Rincian" aria-label="Buka Rincian"><i class="ri ri-eye-line"></i></a>
                        <button type="button" class="btn btn-outline-success action-icon-btn component-action-btn btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-checkbox-circle-line"></i></button>
                        <button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete"><i class="ri ri-delete-bin-line"></i></button>
                      </div>
                    <?php elseif (strtoupper((string)($row['status'] ?? '')) === 'POSTED'): ?>
                      <div class="component-action-stack">
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info action-icon-btn component-action-btn" title="Buka Rincian" aria-label="Buka Rincian"><i class="ri ri-eye-line"></i></a>
                        <button type="button" class="btn btn-outline-primary action-icon-btn component-action-btn btn-reopen" data-reopen-url="<?php echo site_url('production/component-openings/reopen/' . (int)($row['id'] ?? 0)); ?>" data-edit-url="<?php echo $rowEditUrl; ?>" title="Draftkan & Edit" aria-label="Draftkan & Edit"><i class="ri ri-refresh-line"></i></button>
                        <button type="button" class="btn btn-outline-warning action-icon-btn component-action-btn btn-void" data-id="<?php echo (int)$row['id']; ?>" title="Void" aria-label="Void"><i class="ri ri-close-circle-line"></i></button>
                      </div>
                    <?php else: ?>
                      <div class="component-action-stack">
                        <a href="<?php echo $rowDetailUrl; ?>" class="btn btn-outline-info action-icon-btn component-action-btn" title="Buka Rincian" aria-label="Buka Rincian"><i class="ri ri-eye-line"></i></a>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade <?php echo $openingTab === 'detail' ? 'show active' : ''; ?>">
      <?php if (empty($detailHeader)): ?>
        <div class="component-opening-detail-empty">
          <div class="fw-semibold mb-1">Belum ada dokumen yang dipilih</div>
          <small class="text-muted">Pilih tombol rincian pada daftar dokumen untuk melihat isi opening, movement, dan lot inbound pada halaman ini.</small>
        </div>
      <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h5 class="mb-1">Rincian <?php echo html_escape((string)($detailHeader['opening_no'] ?? '-')); ?></h5>
            <small class="text-muted">Bulan <?php echo html_escape(substr((string)($detailHeader['opening_date'] ?? ''), 0, 7)); ?> | <?php echo html_escape($locationGroupLabel((string)($detailHeader['location_type'] ?? ''))); ?> | <?php echo html_escape((string)($detailHeader['division_name'] ?? '-')); ?></small>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <?php if (strtoupper((string)($detailHeader['status'] ?? '')) === 'DRAFT'): ?>
              <a href="<?php echo $editBaseUrl . '?' . http_build_query(array_merge($editQuery, ['edit' => (int)($detailHeader['id'] ?? 0), 'detail' => (int)($detailHeader['id'] ?? 0), 'tab' => 'detail'])) . '#component-opening-form-card'; ?>" class="btn btn-outline-primary btn-sm">Edit Draft</a>
            <?php endif; ?>
            <?php if ($detailReopenUrl !== ''): ?>
              <button type="button" class="btn btn-outline-primary btn-sm btn-reopen" data-reopen-url="<?php echo $detailReopenUrl; ?>" data-edit-url="<?php echo $editBaseUrl . '?' . http_build_query(array_merge($editQuery, ['edit' => (int)($detailHeader['id'] ?? 0), 'detail' => (int)($detailHeader['id'] ?? 0), 'tab' => 'detail'])) . '#component-opening-form-card'; ?>">Draftkan & Edit</button>
            <?php endif; ?>
            <?php if ($detailAdjustmentUrl !== ''): ?>
              <a href="<?php echo $detailAdjustmentUrl; ?>" class="btn btn-outline-warning btn-sm">Buat Adjustment Koreksi</a>
            <?php endif; ?>
            <a href="<?php echo site_url('production/component-openings/detail/' . (int)($detailHeader['id'] ?? 0)); ?>" class="btn btn-outline-secondary btn-sm">Detail Penuh</a>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="component-doc-summary"><div class="small text-muted">Status</div><div class="component-doc-summary-value"><?php echo html_escape((string)($detailHeader['status'] ?? 'DRAFT')); ?></div></div></div>
          <div class="col-md-3"><div class="component-doc-summary"><div class="small text-muted">Total Line</div><div class="component-doc-summary-value"><?php echo number_format((int)($detailSummary['line_count'] ?? 0), 0, ',', '.'); ?></div></div></div>
          <div class="col-md-3"><div class="component-doc-summary"><div class="small text-muted">Total Qty</div><div class="component-doc-summary-value"><?php echo number_format((float)($detailSummary['total_qty'] ?? 0), 2, ',', '.'); ?></div></div></div>
          <div class="col-md-3"><div class="component-doc-summary"><div class="small text-muted">Total Nilai</div><div class="component-doc-summary-value">Rp <?php echo number_format((float)($detailSummary['total_value'] ?? 0), 2, ',', '.'); ?></div></div></div>
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>Line</th>
                <th>Component</th>
                <th>UOM</th>
                <th class="text-end">Qty Opening</th>
                <th class="text-end">Unit Cost</th>
                <th class="text-end">Total Nilai</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($detailLines)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Dokumen ini belum memiliki baris opening.</td></tr>
              <?php else: ?>
                <?php foreach ($detailLines as $line): ?>
                  <tr>
                    <td><?php echo (int)($line['line_no'] ?? 0); ?></td>
                    <td>
                      <div><?php echo html_escape((string)($line['component_name'] ?? '-')); ?></div>
                      <small class="text-muted"><?php echo html_escape((string)($line['component_code'] ?? '')); ?></small>
                    </td>
                    <td><?php echo html_escape((string)($line['uom_code'] ?? '-')); ?></td>
                    <td class="text-end"><?php echo number_format((float)($line['opening_qty'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end"><?php echo number_format((float)($line['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end"><?php echo number_format(((float)($line['opening_qty'] ?? 0) * (float)($line['unit_cost'] ?? 0)), 2, ',', '.'); ?></td>
                    <td><?php echo html_escape((string)($line['note'] ?? '-')); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
              <div class="card-body">
                <ul class="nav nav-tabs nav-sm mb-2" role="tablist">
                  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#detail-opening-effective" type="button">Posisi Efektif</button></li>
                  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#detail-opening-audit" type="button">Histori Audit</button></li>
                </ul>
                <div class="tab-content">
                  <div class="tab-pane fade show active" id="detail-opening-effective">
                    <?php if (strtoupper((string)($detailHeader['status'] ?? '')) !== 'POSTED'): ?>
                      <div class="small text-muted">Dokumen masih <?php echo html_escape((string)($detailHeader['status'] ?? 'DRAFT')); ?>. Edit draft tidak membuat movement baru di ledger.</div>
                    <?php elseif (empty($detailEffectiveMovementRows)): ?>
                      <small class="text-muted">Belum ada posisi efektif yang aktif dari opening ini.</small>
                    <?php else: ?>
                      <div class="alert alert-light border small py-2 px-3 mb-2">Tab ini hanya menampilkan kontribusi stok yang masih efektif. Reversal sistem ditaruh di histori audit.</div>
                      <div class="table-responsive">
                        <table class="table table-sm mb-0">
                          <thead><tr><th>Component</th><th class="text-end">Qty Efektif</th><th class="text-end">Unit Cost</th><th class="text-end">Lot Aktif</th></tr></thead>
                          <tbody>
                            <?php foreach ($detailEffectiveMovementRows as $effectiveRow): ?>
                              <?php
                                $lotCount = 0;
                                foreach ($detailActiveLotRows as $lotRow) {
                                  if ((int)($lotRow['component_id'] ?? 0) === (int)($effectiveRow['component_id'] ?? 0) && (int)($lotRow['uom_id'] ?? 0) === (int)($effectiveRow['uom_id'] ?? 0)) {
                                    $lotCount++;
                                  }
                                }
                              ?>
                              <tr>
                                <td>
                                  <div><?php echo html_escape((string)($effectiveRow['component_name'] ?? '-')); ?></div>
                                  <small class="text-muted"><?php echo html_escape((string)($effectiveRow['uom_code'] ?? '-')); ?></small>
                                </td>
                                <td class="text-end"><?php echo number_format((float)($effectiveRow['effective_qty'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($effectiveRow['latest_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format($lotCount, 0, ',', '.'); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="tab-pane fade" id="detail-opening-audit">
                    <?php if (empty($detailMovementRows)): ?>
                      <small class="text-muted">Belum ada movement audit yang tercatat dari dokumen ini.</small>
                    <?php else: ?>
                      <div class="alert alert-light border small py-2 px-3 mb-2">
                        Edit <strong>DRAFT</strong> tidak membuat movement baru. Tabel ini adalah histori audit jika opening pernah diposting, dibuka ulang, di-void, lalu diposting lagi.
                      </div>
                      <div class="table-responsive">
                        <table class="table table-sm mb-0">
                          <thead><tr><th>No</th><th>Jenis</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th></tr></thead>
                          <tbody>
                            <?php foreach ($detailMovementRows as $movementRow): ?>
                              <tr>
                                <td><?php echo html_escape((string)($movementRow['movement_no'] ?? '-')); ?></td>
                                <td><?php echo html_escape((string)($movementRow['movement_type_label'] ?? $movementRow['movement_type'] ?? '-')); ?></td>
                                <td class="text-end"><?php echo number_format((float)($movementRow['qty_in'] ?? 0), 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((float)($movementRow['qty_out'] ?? 0), 2, ',', '.'); ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card border-0 bg-light h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Lot Inbound</h6>
                  <span class="badge text-bg-light border"><?php echo number_format(count($detailLotRows), 0, ',', '.'); ?></span>
                </div>
                <?php if (empty($detailLotRows)): ?>
                  <small class="text-muted">Belum ada lot inbound dari dokumen ini.</small>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>Lot</th><th>Component</th><th class="text-end">Qty In</th><th class="text-end">Saldo</th></tr></thead>
                      <tbody>
                        <?php foreach ($detailLotRows as $lotRow): ?>
                          <tr>
                            <td><?php echo html_escape((string)($lotRow['lot_no'] ?? '-')); ?></td>
                            <td><?php echo html_escape((string)($lotRow['component_name'] ?? '-')); ?></td>
                            <td class="text-end"><?php echo number_format((float)($lotRow['qty_in'] ?? 0), 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format((float)($lotRow['qty_balance'] ?? 0), 2, ',', '.'); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade <?php echo $openingTab === 'snapshot' ? 'show active' : ''; ?>">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h5 class="mb-1">Snapshot Opening Bulanan</h5>
          <small class="text-muted">Hasil carry-forward otomatis untuk bulan terpilih. Ini belum mem-posting dokumen operasional, tetapi menjadi basis opening awal bulan.</small>
        </div>
        <span class="badge text-bg-light border"><?php echo number_format($componentSummary, 0, ',', '.'); ?> baris</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Bulan</th>
              <th>Lokasi</th>
              <th>Divisi</th>
              <th>Component</th>
              <th>UOM</th>
              <th class="text-end">Qty Opening</th>
              <th class="text-end">HPP Live</th>
              <th class="text-end">Total Nilai</th>
              <th>Source</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($monthlyRows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Belum ada snapshot monthly opening untuk filter ini.</td></tr>
            <?php else: ?>
              <?php foreach ($monthlyRows as $monthlyRow): ?>
                <tr>
                  <td><?php echo html_escape((string)($monthlyRow['month_key'] ?? '')); ?></td>
                  <td><?php echo html_escape($locationGroupLabel((string)($monthlyRow['location_type'] ?? ''))); ?></td>
                  <td><?php echo html_escape((string)($monthlyRow['division_name'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($monthlyRow['component_name'] ?? '')); ?></td>
                  <td><?php echo html_escape((string)($monthlyRow['uom_name'] ?? $monthlyRow['uom_code'] ?? '')); ?></td>
                  <td class="text-end"><?php echo number_format((float)($monthlyRow['opening_qty'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)($monthlyRow['hpp_live'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end"><?php echo number_format((float)($monthlyRow['total_value'] ?? 0), 2, ',', '.'); ?></td>
                  <td><?php echo html_escape((string)($monthlyRow['source_month'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const uomOptions = <?php echo json_encode(array_values(array_map(static function ($uom) {
      return [
          'id' => (int)($uom['id'] ?? 0),
        'label' => trim((string)($uom['name'] ?? '')),
      ];
  }, $uoms)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const editingOpening = <?php echo json_encode($editPayload, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-openings/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-openings/post'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-openings/delete'); ?>';
  const voidBaseUrl = '<?php echo site_url('production/component-openings/void'); ?>';
  const generateUrl = '<?php echo site_url('production/component-openings/generate-monthly'); ?>';

  const alertHost = document.getElementById('component-opening-alert');
  const lineBody = document.getElementById('opening-line-body');
  const form = document.getElementById('frmOpening');
  const divisionIdInput = document.getElementById('opening-division-id');
  const divisionNameInput = document.getElementById('opening-division-name');
  const divisionHelp = document.getElementById('opening-division-help');
  const locationGroupInput = document.getElementById('opening-location-group');
  const locationTypeInput = document.getElementById('opening-location-type');
  const locationHelp = document.getElementById('opening-location-help');
  let lines = Array.isArray(editingOpening?.lines) ? editingOpening.lines.slice() : [];

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    if (!alertHost) {
      return;
    }
    alertHost.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>';
  }

  function renderConflictAlert(message, conflict) {
    if (!alertHost) {
      return;
    }
    const actions = [];
    if (conflict?.edit_url && !conflict?.reopen_url) {
      actions.push('<a href="' + escapeHtml(conflict.edit_url) + '" class="btn btn-sm btn-outline-primary">Buka Draft Existing</a>');
    }
    if (conflict?.reopen_url) {
      actions.push('<button type="button" class="btn btn-sm btn-outline-primary js-reopen-opening" data-reopen-url="' + escapeHtml(conflict.reopen_url) + '" data-edit-url="' + escapeHtml(conflict.edit_url || '') + '">Draftkan & Edit</button>');
    }
    if (conflict?.detail_url) {
      actions.push('<a href="' + escapeHtml(conflict.detail_url) + '" class="btn btn-sm btn-outline-secondary">Lihat Rincian</a>');
    }
    if (conflict?.adjustment_url) {
      actions.push('<a href="' + escapeHtml(conflict.adjustment_url) + '" class="btn btn-sm btn-outline-warning">Buat Adjustment Koreksi</a>');
    }
    alertHost.innerHTML = '<div class="alert alert-warning mb-0">'
      + '<div class="fw-semibold mb-1">' + escapeHtml(message) + '</div>'
      + (conflict?.opening_no ? '<div class="small text-muted mb-2">Dokumen terkait: ' + escapeHtml(conflict.opening_no) + ' (' + escapeHtml(conflict.status || '') + ')</div>' : '')
      + (actions.length ? '<div class="d-flex flex-wrap gap-2">' + actions.join('') + '</div>' : '')
      + '</div>';
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      throw new Error('Respons server bukan JSON valid.');
    }
    if (!response.ok || !json.ok) {
      const requestError = new Error(json.message || 'Permintaan gagal diproses.');
      requestError.payload = json;
      throw requestError;
    }
    return json;
  }

  function uiConfirm(message, options) {
    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      return window.FinanceUI.confirm(message, options || {});
    }
    if (window.FinanceUI && typeof window.FinanceUI.alert === 'function') {
      return window.FinanceUI.alert('Modal konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.', { title: 'UI Belum Siap' })
        .then(function () { return false; });
    }
    return Promise.resolve(false);
  }

  function blankLine() {
    return {
      component_id: '',
      component_label: '',
      component_division_id: '',
      component_division_code: '',
      component_division_name: '',
      uom_id: '',
      opening_qty: '',
      unit_cost: '',
      note: ''
    };
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 2}).format(value || 0);
  }

  function renderSummary() {
    const validLines = lines.filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0);
    const totalQty = validLines.reduce((sum, line) => sum + (parseFloat(line.opening_qty) || 0), 0);
    const totalValue = validLines.reduce((sum, line) => sum + ((parseFloat(line.opening_qty) || 0) * (parseFloat(line.unit_cost) || 0)), 0);
    document.getElementById('opening-total-lines').textContent = String(validLines.length);
    document.getElementById('opening-total-qty').textContent = totalQty.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('opening-total-value').textContent = formatCurrency(totalValue);
  }

  function uomSelectOptions(selectedValue) {
    const options = ['<option value="">Pilih UOM...</option>'];
    uomOptions.forEach((uom) => {
      options.push('<option value="' + uom.id + '"' + (String(selectedValue) === String(uom.id) ? ' selected' : '') + '>' + escapeHtml(uom.label) + '</option>');
    });
    return options.join('');
  }

  function componentPickerLabel(row) {
    return String(row.name || row.code || '');
  }

  function componentPickerSubLabel(row) {
    return [row.entity_type || '', row.division_name || row.division_code || '', row.uom_name || row.uom_code || ''].filter(Boolean).join(' | ');
  }

  function resolveLocationType(divisionCode, locationGroup) {
    const normalizedDivision = String(divisionCode || '').trim().toUpperCase();
    const normalizedGroup = String(locationGroup || '').trim().toUpperCase();
    if (!normalizedDivision || !normalizedGroup) {
      return '';
    }
    if (normalizedDivision === 'BAR') {
      return normalizedGroup === 'EVENT' ? 'BAR_EVENT' : 'BAR';
    }
    if (normalizedDivision === 'KITCHEN') {
      return normalizedGroup === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
    }
    return '';
  }

  function syncHeaderDivisionState() {
    const activeLine = lines.find((line) => Number(line.component_id) > 0 && Number(line.component_division_id) > 0);
    const divisionId = String(activeLine?.component_division_id || '');
    const divisionCode = String(activeLine?.component_division_code || '').trim();
    const divisionName = String(activeLine?.component_division_name || '').trim();
    divisionIdInput.value = divisionId;
    divisionNameInput.value = divisionId ? [divisionCode, divisionName].filter(Boolean).join(' - ') : 'Ikuti component';
    divisionHelp.textContent = divisionId
      ? 'Semua component di dokumen ini dibatasi ke divisi yang sama.'
      : 'Divisi otomatis mengikuti component yang dipilih di baris opening.';
    locationTypeInput.value = resolveLocationType(divisionCode, locationGroupInput?.value || '');
    locationHelp.textContent = divisionId
      ? (locationTypeInput.value ? 'Lokasi akan disimpan sebagai ' + locationTypeInput.value + '.' : 'Pilih Reguler atau Event untuk menentukan lokasi ledger.')
      : 'Pilih component dulu agar lokasi bisa diturunkan otomatis.';
  }

  function bindComponentPickers() {
    lineBody.querySelectorAll('.component-picker-input').forEach((input) => {
      window.ProductionAjaxPicker.bind(input, {
        entity: 'COMPONENT',
        params: () => {
          const currentDivisionId = String(divisionIdInput?.value || '');
          return currentDivisionId ? {division_id: currentDivisionId} : {};
        },
        renderLabel: componentPickerLabel,
        renderSubLabel: componentPickerSubLabel,
        onType: (value, currentInput) => {
          const row = currentInput.closest('tr');
          const index = Number(row?.dataset.index || -1);
          if (index < 0) {
            return;
          }
          lines[index].component_label = value;
          lines[index].component_id = '';
          lines[index].component_division_id = '';
          lines[index].component_division_code = '';
          lines[index].component_division_name = '';
          lines[index].uom_id = '';
          const uomSelect = row.querySelector('[data-field="uom_id"]');
          if (uomSelect) {
            uomSelect.value = '';
          }
          syncHeaderDivisionState();
          renderSummary();
        },
        onSelect: (result, currentInput) => {
          const row = currentInput.closest('tr');
          const index = Number(row?.dataset.index || -1);
          if (index < 0) {
            return;
          }
          lines[index].component_id = String(result.id || '');
          lines[index].component_label = componentPickerLabel(result);
          lines[index].component_division_id = String(result.operational_division_id || '');
          lines[index].component_division_code = String(result.division_code || '');
          lines[index].component_division_name = String(result.division_name || '');
          lines[index].uom_id = String(result.uom_id || '');
          syncHeaderDivisionState();
          renderLines();
        }
      });
    });
  }

  function renderLines() {
    if (!lineBody) {
      return;
    }
    if (!lines.length) {
      lines = [blankLine()];
    }
    lineBody.innerHTML = lines.map((line, index) => {
      return '<tr data-index="' + index + '">' +
        '<td class="text-muted">' + (index + 1) + '</td>' +
        '<td><input type="text" class="form-control form-control-sm component-picker-input" value="' + escapeHtml(line.component_label || '') + '" placeholder="Ketik nama component..."' + (Number(line.component_id) > 0 ? ' data-selected-label="' + escapeHtml(line.component_label || '') + '"' : '') + '></td>' +
        '<td><select class="form-select form-select-sm component-uom-select" data-field="uom_id">' + uomSelectOptions(line.uom_id) + '</select></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="opening_qty" value="' + escapeHtml(line.opening_qty) + '"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="unit_cost" value="' + escapeHtml(line.unit_cost) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" data-field="note" value="' + escapeHtml(line.note) + '" placeholder="Opsional"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove">×</button></td>' +
      '</tr>';
    }).join('');
    bindComponentPickers();
    renderSummary();
  }

  function serializeLines() {
    return lines
      .filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0 && (parseFloat(line.opening_qty) || 0) > 0)
      .map((line) => ({
        component_id: Number(line.component_id),
        uom_id: Number(line.uom_id),
        opening_qty: parseFloat(line.opening_qty) || 0,
        unit_cost: parseFloat(line.unit_cost) || 0,
        note: String(line.note || '')
      }));
  }

  document.getElementById('btn-add-opening-line')?.addEventListener('click', () => {
    lines.push(blankLine());
    renderLines();
  });

  lineBody?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action="remove"]');
    if (!button) {
      return;
    }
    const row = button.closest('tr');
    const index = Number(row?.dataset.index || -1);
    if (index < 0) {
      return;
    }
    lines.splice(index, 1);
    renderLines();
  });

  lineBody?.addEventListener('change', (event) => {
    const field = event.target.getAttribute('data-field');
    const row = event.target.closest('tr');
    const index = Number(row?.dataset.index || -1);
    if (index < 0 || !field) {
      return;
    }
    lines[index][field] = event.target.value;
    renderSummary();
  });

  lineBody?.addEventListener('input', (event) => {
    const field = event.target.getAttribute('data-field');
    const row = event.target.closest('tr');
    const index = Number(row?.dataset.index || -1);
    if (index < 0 || !field) {
      return;
    }
    lines[index][field] = event.target.value;
    renderSummary();
  });

  function setButtonBusy(button, label) {
    if (!button) {
      return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.setButtonLoading === 'function') {
      window.FinanceUI.setButtonLoading(button, label);
      return;
    }
    button.disabled = true;
  }

  function clearButtonBusy(button) {
    if (!button) {
      return;
    }
    if (window.FinanceUI && typeof window.FinanceUI.clearButtonLoading === 'function') {
      window.FinanceUI.clearButtonLoading(button);
      return;
    }
    button.disabled = false;
  }

  async function reopenOpening(button) {
    const reopenUrl = button?.dataset?.reopenUrl || '';
    if (!reopenUrl) {
      return;
    }
    button.blur();
    if (!(await uiConfirm('Opening posted ini akan dibalik dulu dari ledger dan lot, lalu statusnya kembali menjadi DRAFT agar bisa diedit.', {
      title: 'Draftkan Ulang Opening',
      okText: 'Draftkan & Edit',
      cancelText: 'Batal'
    }))) {
      return;
    }
    setButtonBusy(button, 'Drafting...');
    try {
      const result = await postJson(reopenUrl, {});
      window.location.href = result.edit_url || button.dataset.editUrl || window.location.href;
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal membuka kembali opening ke draft.');
      clearButtonBusy(button);
    }
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = event.submitter || form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const payload = {
      id: String(formData.get('id') || ''),
      opening_no: String(formData.get('opening_no') || ''),
      opening_month: String(formData.get('opening_month') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      notes: String(formData.get('notes') || ''),
      lines: serializeLines()
    };
    if (!payload.lines.length) {
    syncHeaderDivisionState();
      renderAlert('warning', 'Tambahkan minimal satu baris opening yang valid.');
      return;
    }
    if (!payload.division_id) {
      renderAlert('warning', 'Pilih minimal satu component agar divisi opening bisa ditentukan otomatis.');
      return;
    }
    if (!payload.location_type) {
      renderAlert('warning', 'Pilih lokasi Reguler atau Event terlebih dahulu.');
      return;
    }
    if (!payload.opening_month) {
      renderAlert('warning', 'Pilih bulan opening terlebih dahulu.');
      return;
    }
    setButtonBusy(submitButton, 'Menyimpan draft...');
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      if (error?.payload?.conflict) {
        renderConflictAlert(error.message || 'Gagal menyimpan opening.', error.payload.conflict);
      } else {
        renderAlert('danger', error.message || 'Gagal menyimpan opening.');
      }
      clearButtonBusy(submitButton);
    }
  });

  document.getElementById('btn-generate-monthly-opening')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const monthInput = document.getElementById('monthly-month');
    const locationInput = document.getElementById('monthly-location-type');
    const divisionInput = document.getElementById('monthly-division-id');
    const monthValue = String(monthInput?.value || '');
    if (!monthValue) {
      renderAlert('warning', 'Pilih bulan snapshot terlebih dahulu.');
      return;
    }
    if (!(await uiConfirm('Generate opname penutup bulan ' + monthValue + ' dan opening bulan berikutnya?', {
      title: 'Generate Carry-Forward Opening',
      okText: 'Generate Opening',
      cancelText: 'Batal'
    }))) {
      return;
    }
    setButtonBusy(button, 'Generating...');
    try {
      await postJson(generateUrl, {
        month: monthValue,
        location_type: String(locationInput?.value || ''),
        division_id: String(divisionInput?.value || '')
      });
      window.location.search = new URLSearchParams({
        month: monthValue,
        location_type: String(locationInput?.value || ''),
        division_id: String(divisionInput?.value || ''),
        q: '<?php echo html_escape($q); ?>'
      }).toString();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal generate carry-forward bulanan component.');
      clearButtonBusy(button);
    }
  });

  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Posting opening akan menulis ledger dan saldo component untuk dokumen ini.', {
        title: 'Post Dokumen Opening',
        okText: 'Post Opening',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Posting...');
      try {
        await postJson(postBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal post opening.');
        clearButtonBusy(button);
      }
    });
  });

  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Draft opening ini akan dihapus permanen.', {
        title: 'Hapus Draft Opening',
        okText: 'Hapus Draft',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Menghapus...');
      try {
        await postJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal menghapus opening.');
        clearButtonBusy(button);
      }
    });
  });

  document.querySelectorAll('.btn-reopen').forEach((button) => {
    button.addEventListener('click', async () => {
      await reopenOpening(button);
    });
  });

  alertHost?.addEventListener('click', async (event) => {
    const button = event.target.closest('.js-reopen-opening');
    if (!button) {
      return;
    }
    await reopenOpening(button);
  });

  document.querySelectorAll('.btn-void').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('VOID opening akan membatalkan saldo opening yang sudah diposting. Lanjutkan?', {
        title: 'Void Dokumen Opening',
        okText: 'Void Opening',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Void...');
      try {
        await postJson(voidBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal void opening.');
        clearButtonBusy(button);
      }
    });
  });

  locationGroupInput?.addEventListener('change', () => {
    syncHeaderDivisionState();
  });

  if (!Array.isArray(lines) || !lines.length) {
    lines = [blankLine()];
  }
  syncHeaderDivisionState();
  renderLines();
})();
</script>
