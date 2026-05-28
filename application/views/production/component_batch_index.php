<?php
$rows = is_array($rows ?? null) ? $rows : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];

$locationGroupLabel = static function ($locationType): string {
  $value = strtoupper(trim((string)$locationType));
  if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT') {
    return 'EVENT';
  }
  if ($value === 'BAR' || $value === 'KITCHEN') {
    return 'REGULER';
  }
  return $value !== '' ? $value : '-';
};
?>

<style>
  .component-batch-summary {
    background: #f8f4ee;
    border: 1px solid #e6d8c8;
    border-radius: 16px;
    padding: 1rem 1.1rem;
  }
  .component-batch-summary strong {
    font-size: 1.12rem;
    color: #4c3827;
  }
  .component-batch-stage {
    min-width: 138px;
  }
  .component-batch-status {
    font-size: 0.74rem;
    letter-spacing: 0.02em;
  }
  .component-batch-inline-meta {
    margin-top: 0.35rem;
  }
  .component-batch-inline-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #8a6a52;
  }
  .component-batch-inline-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0.15rem 0.3rem 0 0;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    border: 1px solid #e6d8c8;
    background: #f8f4ee;
    font-size: 0.76rem;
    color: #5d4636;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1">Batch Produksi Base/Prepare</h4>
  <small class="text-muted">Patokan produksi mengikuti hasil 1x resep di master component. Pilih mode sesuai resep atau sesuai bahan acuan, lalu sistem menghitung hasil jadi dan kebutuhan input otomatis.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'batch']); ?>

<div id="component-batch-alert" class="mb-3"></div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="mb-3">
      <h5 class="mb-1">Form Batch</h5>
      <small class="text-muted">Pilih output component, tentukan lokasi, lalu pilih mode produksi. Sistem akan menghitung hasil jadi berdasarkan resep, bukan dari qty output manual.</small>
    </div>

    <form id="frmBatch" autocomplete="off">
      <div class="row g-2 mb-3">
        <div class="col-md-2">
          <label class="form-label">Tanggal Batch</label>
          <input type="date" class="form-control" name="batch_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Output Component</label>
          <input type="hidden" name="component_id" id="batch-output-component-id" value="">
          <input type="text" class="form-control" id="batch-output-component-search" placeholder="Ketik nama component..." autocomplete="off" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Divisi</label>
          <input type="hidden" name="division_id" id="batch-division-id" value="">
          <input type="text" class="form-control" id="batch-division-name" value="Ikuti output component" readonly>
          <div class="form-text" id="batch-division-help">Divisi otomatis mengikuti output component.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Lokasi</label>
          <input type="hidden" name="location_type" id="batch-location-type" value="">
          <select class="form-select" id="batch-location-group" required>
            <option value="">Pilih lokasi...</option>
            <option value="REGULER">Reguler</option>
            <option value="EVENT">Event</option>
          </select>
          <div class="form-text" id="batch-location-help">Pilih output component dulu agar lokasi bisa diturunkan otomatis.</div>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-2">
          <label class="form-label">Mode Produksi</label>
          <select class="form-select" name="scaling_mode" id="batch-scaling-mode">
            <option value="BATCH">Sesuai Resep</option>
            <option value="REFERENCE">Sesuai Bahan Acuan</option>
          </select>
        </div>
        <div class="col-md-2" id="batch-count-wrap">
          <label class="form-label">Jumlah Batch</label>
          <input type="number" min="0.01" step="0.01" class="form-control text-end" name="batch_count" id="batch-batch-count" value="1.00">
          <div class="form-text">1 = 1 kali produksi sesuai resep dasar.</div>
        </div>
        <div class="col-md-3 d-none" id="batch-reference-line-wrap">
          <label class="form-label">Bahan Acuan</label>
          <select class="form-select" name="reference_line_no" id="batch-reference-line-no">
            <option value="">Pilih bahan acuan...</option>
          </select>
        </div>
        <div class="col-md-2 d-none" id="batch-reference-qty-wrap">
          <label class="form-label">Qty Aktual Acuan</label>
          <input type="number" min="0.01" step="0.01" class="form-control text-end" name="reference_actual_qty" id="batch-reference-actual-qty" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">UOM Output</label>
          <input type="hidden" name="output_uom_id" id="batch-output-uom" value="">
          <input type="text" class="form-control" id="batch-output-uom-label" value="Ikuti output component" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasil Produksi</label>
          <input type="hidden" name="output_qty" id="batch-output-qty" value="">
          <input type="text" class="form-control text-end" id="batch-output-qty-label" value="0,00" readonly>
          <div class="form-text" id="batch-output-help">Hasil jadi dihitung otomatis dari mode produksi yang dipilih.</div>
        </div>
        <div class="col-md-12">
          <label class="form-label">Catatan Header</label>
          <input type="text" class="form-control" name="notes" placeholder="Contoh: batch prep sore hari">
        </div>
      </div>

      <div class="card border-0 bg-light mb-3">
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Output Resep Dasar</div>
                <strong id="batch-base-output">-</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Mode / Skala</div>
                <strong id="batch-scaling-summary">-</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Total Input Cost</div>
                <strong id="batch-total-input-cost">Rp 0</strong>
              </div>
            </div>
            <div class="col-md-3">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Estimasi Cost / Output</div>
                <strong id="batch-estimated-unit-cost">Rp 0</strong>
              </div>
            </div>
          </div>

          <div id="batch-preview-issues" class="d-none mb-3"></div>
          <div id="batch-preview-empty" class="text-muted">Pilih output component, lokasi, dan mode produksi untuk melihat preview produksi.</div>

          <div class="row g-3 mb-3 d-none" id="batch-live-preview">
            <div class="col-md-4">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Preview Output</div>
                <strong id="batch-live-output">-</strong>
                <div class="small text-muted mt-2" id="batch-live-output-note">Hasil jadi akan tampil otomatis setelah parameter produksi diisi.</div>
              </div>
            </div>
            <div class="col-md-8">
              <div class="component-batch-summary h-100">
                <div class="small text-muted">Preview Pemakaian Bahan</div>
                <div id="batch-live-usage" class="small text-body-secondary">Belum ada pemakaian bahan yang bisa dihitung.</div>
              </div>
            </div>
          </div>

          <div class="table-responsive d-none" id="batch-preview-wrap">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:150px;">Tahap</th>
                  <th style="width:140px;">Role</th>
                  <th>Sumber</th>
                  <th style="width:120px;" class="text-end">Qty</th>
                  <th style="width:120px;" class="text-end">Tersedia</th>
                  <th style="width:140px;" class="text-end">Unit Cost</th>
                  <th style="width:140px;" class="text-end">Total</th>
                  <th style="width:120px;" class="text-center">Status</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody id="batch-preview-body"></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
        <div class="component-batch-summary d-flex flex-wrap gap-4">
          <div><div class="small text-muted">Component Langsung</div><strong id="batch-direct-component-count">0</strong></div>
          <div><div class="small text-muted">Bahan Langsung</div><strong id="batch-direct-material-count">0</strong></div>
          <div><div class="small text-muted">Variable Cost</div><strong id="batch-variable-cost">Rp 0</strong></div>
        </div>
        <button type="submit" class="btn btn-primary" id="batch-save-btn" disabled>Simpan DRAFT</button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Daftar Batch</h5>
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Lokasi</th>
            <th>Output</th>
            <th>Qty</th>
            <th>Status</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada batch component.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr id="component-batch-<?php echo (int)$row['id']; ?>">
                <td><?php echo html_escape((string)($row['batch_no'] ?? '')); ?></td>
                <td><?php echo html_escape((string)($row['batch_date'] ?? '')); ?></td>
                <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></td>
                <td>
                  <div><?php echo html_escape((string)($row['component_name'] ?? '')); ?></div>
                  <?php $inlineSummary = is_array($row['inline_summary'] ?? null) ? $row['inline_summary'] : []; ?>
                  <?php $inlineOutputs = is_array($inlineSummary['outputs'] ?? null) ? $inlineSummary['outputs'] : []; ?>
                  <?php $inlineUsages = is_array($inlineSummary['usages'] ?? null) ? $inlineSummary['usages'] : []; ?>
                  <?php if (!empty($inlineSummary['has_inline'])): ?>
                    <div class="component-batch-inline-meta small text-muted">
                      <?php if (!empty($inlineOutputs)): ?>
                        <div class="mt-1">
                          <span class="component-batch-inline-label">Inline Output</span>
                          <?php foreach ($inlineOutputs as $inlineRow): ?>
                            <span class="component-batch-inline-chip">
                              <?php echo html_escape((string)($inlineRow['component_name'] ?? '-')); ?>
                              <span class="text-muted"><?php echo number_format((float)($inlineRow['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($inlineRow['uom_code'] ?? '')); ?></span>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($inlineUsages)): ?>
                        <div class="mt-1">
                          <span class="component-batch-inline-label">Inline Pakai</span>
                          <?php foreach ($inlineUsages as $inlineRow): ?>
                            <span class="component-batch-inline-chip">
                              <?php echo html_escape((string)($inlineRow['component_name'] ?? '-')); ?>
                              <span class="text-muted"><?php echo number_format((float)($inlineRow['qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($inlineRow['uom_code'] ?? '')); ?></span>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format((float)($row['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                <td>
                  <div><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></div>
                  <?php if (strtoupper((string)($row['status'] ?? '')) === 'POSTED'): ?>
                    <?php if (!empty($row['can_void'])): ?>
                      <div class="mt-1"><span class="badge text-bg-success" title="Batch ini belum terdeteksi dipakai dokumen lain.">Siap Void</span></div>
                    <?php else: ?>
                      <div class="mt-1"><span class="badge text-bg-warning" title="<?php echo html_escape((string)($row['void_block_reason'] ?? 'Batch ini sudah dipakai dokumen lain.')); ?>">Tidak Bisa Void</span></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td class="component-action-cell">
                  <?php if (strtoupper((string)($row['status'] ?? '')) === 'DRAFT'): ?>
                    <div class="component-action-stack">
                      <button type="button" class="btn btn-outline-success action-icon-btn component-action-btn btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-checkbox-circle-line"></i></button>
                      <button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete"><i class="ri ri-delete-bin-line"></i></button>
                    </div>
                  <?php elseif (strtoupper((string)($row['status'] ?? '')) === 'POSTED'): ?>
                    <div class="component-action-stack">
                      <a href="<?php echo site_url('production/component-batches/detail/' . (int)$row['id']); ?>" class="btn btn-outline-info action-icon-btn component-action-btn" title="Buka Detail Batch" aria-label="Buka Detail Batch"><i class="ri ri-eye-line"></i></a>
                      <button type="button" class="btn btn-outline-secondary action-icon-btn component-action-btn btn-usage" data-id="<?php echo (int)$row['id']; ?>" title="Ringkasan Pemakaian dan Trace Inline" aria-label="Ringkasan Pemakaian dan Trace Inline"><i class="ri ri-information-line"></i></button>
                      <button type="button" class="btn btn-outline-warning action-icon-btn component-action-btn btn-void" data-id="<?php echo (int)$row['id']; ?>" title="<?php echo html_escape(!empty($row['can_void']) ? 'Void' : ((string)($row['void_block_reason'] ?? 'Tidak bisa di-void'))); ?>" aria-label="Void" <?php echo !empty($row['can_void']) ? '' : 'disabled'; ?>><i class="ri ri-close-circle-line"></i></button>
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
</div>

<div class="modal fade" id="componentBatchUsageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pemakaian Output Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="componentBatchUsageBody">
        <div class="text-muted">Memuat detail pemakaian batch...</div>
      </div>
    </div>
  </div>
</div>

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const previewUrl = '<?php echo site_url('production/component-batches/preview'); ?>';
  const saveUrl = '<?php echo site_url('production/component-batches/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-batches/post'); ?>';
  const usageBaseUrl = '<?php echo site_url('production/component-batches/usage'); ?>';
  const usageDetailBaseUrl = '<?php echo site_url('production/component-batches/detail'); ?>';
  const voidBaseUrl = '<?php echo site_url('production/component-batches/void'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-batches/delete'); ?>';

  const alertHost = document.getElementById('component-batch-alert');
  const previewWrap = document.getElementById('batch-preview-wrap');
  const previewBody = document.getElementById('batch-preview-body');
  const previewEmpty = document.getElementById('batch-preview-empty');
  const previewIssues = document.getElementById('batch-preview-issues');
  const livePreviewWrap = document.getElementById('batch-live-preview');
  const liveOutput = document.getElementById('batch-live-output');
  const liveOutputNote = document.getElementById('batch-live-output-note');
  const liveUsage = document.getElementById('batch-live-usage');
  const form = document.getElementById('frmBatch');
  const outputComponentId = document.getElementById('batch-output-component-id');
  const outputComponentSearch = document.getElementById('batch-output-component-search');
  const outputQty = document.getElementById('batch-output-qty');
  const outputQtyLabel = document.getElementById('batch-output-qty-label');
  const outputUom = document.getElementById('batch-output-uom');
  const outputUomLabel = document.getElementById('batch-output-uom-label');
  const scalingMode = document.getElementById('batch-scaling-mode');
  const batchCount = document.getElementById('batch-batch-count');
  const referenceLineNo = document.getElementById('batch-reference-line-no');
  const referenceActualQty = document.getElementById('batch-reference-actual-qty');
  const batchCountWrap = document.getElementById('batch-count-wrap');
  const referenceLineWrap = document.getElementById('batch-reference-line-wrap');
  const referenceQtyWrap = document.getElementById('batch-reference-qty-wrap');
  const divisionIdInput = document.getElementById('batch-division-id');
  const divisionNameInput = document.getElementById('batch-division-name');
  const divisionHelp = document.getElementById('batch-division-help');
  const locationGroupInput = document.getElementById('batch-location-group');
  const locationTypeInput = document.getElementById('batch-location-type');
  const locationHelp = document.getElementById('batch-location-help');
  const outputHelp = document.getElementById('batch-output-help');
  const saveButton = document.getElementById('batch-save-btn');
  const usageModalEl = document.getElementById('componentBatchUsageModal');
  const usageModalBody = document.getElementById('componentBatchUsageBody');
  const usageModal = usageModalEl && window.bootstrap ? new window.bootstrap.Modal(usageModalEl) : null;
  let outputDivisionCode = '';
  let outputDivisionName = '';
  let currentPreview = null;
  let previewTimer = null;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAlert(type, message) {
    if (alertHost) {
      alertHost.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>';
    }
  }

  function clearAlert() {
    if (alertHost) {
      alertHost.innerHTML = '';
    }
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
      throw new Error(json.message || 'Permintaan gagal diproses.');
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

  function pickerLabel(row) {
    return String(row.name || row.code || '');
  }

  function pickerSubLabel(row) {
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

  function syncBatchDivisionState() {
    const divisionId = String(divisionIdInput?.value || '');
    divisionNameInput.value = divisionId ? [outputDivisionCode, outputDivisionName].filter(Boolean).join(' - ') : 'Ikuti output component';
    divisionHelp.textContent = divisionId
      ? 'Input component akan dibatasi ke divisi yang sama dengan output.'
      : 'Divisi otomatis mengikuti output component.';
    locationTypeInput.value = resolveLocationType(outputDivisionCode, locationGroupInput?.value || '');
    locationHelp.textContent = divisionId
      ? (locationTypeInput.value ? 'Lokasi akan disimpan sebagai ' + locationTypeInput.value + '.' : 'Pilih Reguler atau Event untuk menentukan lokasi ledger.')
      : 'Pilih output component dulu agar lokasi bisa diturunkan otomatis.';
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 2}).format(value || 0);
  }

  function formatQty(value) {
    return new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(Number(value || 0));
  }

  function syncScalingMode() {
    const mode = String(scalingMode.value || 'BATCH').toUpperCase();
    batchCountWrap.classList.toggle('d-none', mode !== 'BATCH');
    referenceLineWrap.classList.toggle('d-none', mode !== 'REFERENCE');
    referenceQtyWrap.classList.toggle('d-none', mode !== 'REFERENCE');
    batchCount.disabled = mode !== 'BATCH';
    referenceLineNo.disabled = mode !== 'REFERENCE';
    referenceActualQty.disabled = mode !== 'REFERENCE';
    outputHelp.textContent = mode === 'REFERENCE'
      ? 'Hasil jadi dihitung otomatis dari bahan acuan yang dipilih.'
      : 'Hasil jadi dihitung otomatis dari jumlah batch resep dasar.';
  }

  function renderReferenceOptions(options, selectedLineNo) {
    const rows = Array.isArray(options) ? options : [];
    referenceLineNo.innerHTML = '<option value="">Pilih bahan acuan...</option>' + rows.map((row) =>
      '<option value="' + escapeHtml(row.line_no) + '"' + (String(selectedLineNo || '') === String(row.line_no) ? ' selected' : '') + '>' +
        escapeHtml((row.label || '-') + ' • ' + formatQty(row.base_qty || 0) + ' ' + (row.uom_code || '')) +
      '</option>'
    ).join('');
  }

  function renderLivePreviewShell(outputText, outputNoteText, usageHtml) {
    livePreviewWrap.classList.remove('d-none');
    liveOutput.textContent = outputText || '-';
    liveOutputNote.textContent = outputNoteText || 'Hasil jadi akan tampil otomatis setelah parameter produksi diisi.';
    liveUsage.innerHTML = usageHtml || 'Belum ada pemakaian bahan yang bisa dihitung.';
  }

  function renderLiveUsage(lines) {
    const usages = (Array.isArray(lines) ? lines : []).filter((line) => {
      const role = String(line.plan_role || '').toUpperCase();
      return role === 'MATERIAL_USAGE' || role === 'COMPONENT_USAGE';
    });
    if (!usages.length) {
      return 'Belum ada pemakaian bahan yang bisa dihitung.';
    }
    return usages.map((line) => {
      return '<span class="badge text-bg-light border me-1 mb-1">' +
        escapeHtml(line.source_label || '-') + ': ' +
        escapeHtml(formatQty(line.required_qty || 0)) + ' ' +
        escapeHtml(line.uom_code || '') +
      '</span>';
    }).join('');
  }

  function resetPreview(message) {
    currentPreview = null;
    previewBody.innerHTML = '';
    previewWrap.classList.add('d-none');
    previewEmpty.classList.remove('d-none');
    previewEmpty.textContent = message;
    previewIssues.classList.add('d-none');
    previewIssues.innerHTML = '';
    document.getElementById('batch-base-output').textContent = '-';
    document.getElementById('batch-scaling-summary').textContent = '-';
    document.getElementById('batch-total-input-cost').textContent = formatCurrency(0);
    document.getElementById('batch-estimated-unit-cost').textContent = formatCurrency(0);
    document.getElementById('batch-direct-component-count').textContent = '0';
    document.getElementById('batch-direct-material-count').textContent = '0';
    document.getElementById('batch-variable-cost').textContent = formatCurrency(0);
    outputQty.value = '';
    outputQtyLabel.value = '0,00';
    renderLivePreviewShell('-', message || 'Hasil jadi akan tampil otomatis setelah parameter produksi diisi.', 'Belum ada pemakaian bahan yang bisa dihitung.');
    saveButton.disabled = true;
  }

  function roleBadge(role) {
    const label = String(role || '').toUpperCase();
    if (label === 'INLINE_OUTPUT') {
      return '<span class="badge text-bg-warning component-batch-status">INLINE OUTPUT</span>';
    }
    if (label === 'INLINE_COMPONENT_USAGE') {
      return '<span class="badge text-bg-info component-batch-status">INLINE USE</span>';
    }
    if (label === 'COMPONENT_USAGE') {
      return '<span class="badge text-bg-primary component-batch-status">COMPONENT</span>';
    }
    return '<span class="badge text-bg-secondary component-batch-status">MATERIAL</span>';
  }

  function statusBadge(isShort, label) {
    return '<span class="badge ' + (isShort ? 'text-bg-danger' : 'text-bg-success') + ' component-batch-status">' + escapeHtml(label || (isShort ? 'KURANG' : 'READY')) + '</span>';
  }

  function renderPreview(preview) {
    currentPreview = preview;
    const summary = preview.summary || {};
    const component = preview.component || {};
    const lines = Array.isArray(preview.lines) ? preview.lines : [];
    document.getElementById('batch-base-output').textContent = formatQty(preview.base_output_qty || 0) + ' ' + escapeHtml(component.uom_code || '-');
    document.getElementById('batch-scaling-summary').textContent = String(preview.scaling_mode || 'BATCH').toUpperCase() === 'REFERENCE'
      ? ('Acuan ' + formatQty((preview.reference || {}).actual_qty || 0) + ' ' + escapeHtml((preview.reference || {}).uom_code || ''))
      : (formatQty(preview.batch_count || 0) + ' batch');
    document.getElementById('batch-total-input-cost').textContent = formatCurrency(summary.total_input_cost || 0);
    document.getElementById('batch-estimated-unit-cost').textContent = formatCurrency(summary.unit_cost || 0);
    document.getElementById('batch-direct-component-count').textContent = String(summary.direct_component_count || 0);
    document.getElementById('batch-direct-material-count').textContent = String(summary.direct_material_count || 0);
    document.getElementById('batch-variable-cost').textContent = formatCurrency(summary.variable_cost_total || 0);
    outputUom.value = String(component.uom_id || '');
    outputUomLabel.value = [component.uom_code || '', component.uom_name || ''].filter(Boolean).join(' - ') || 'Ikuti output component';
    outputQty.value = String(preview.output_qty || '');
    outputQtyLabel.value = formatQty(preview.output_qty || 0) + ' ' + (component.uom_code || '');
    renderLivePreviewShell(
      formatQty(preview.output_qty || 0) + ' ' + (component.uom_code || ''),
      String(preview.scaling_mode || 'BATCH').toUpperCase() === 'REFERENCE'
        ? 'Output dihitung dari qty bahan acuan aktual.'
        : 'Output dihitung dari kelipatan batch resep dasar.',
      renderLiveUsage(lines)
    );
    renderReferenceOptions(preview.reference_options || [], (preview.reference || {}).line_no || '');

    if (Array.isArray(preview.issues) && preview.issues.length) {
      previewIssues.classList.remove('d-none');
      previewIssues.innerHTML = '<div class="alert alert-danger mb-0"><strong>Batch tertolak jika diposting.</strong><ul class="mb-0 mt-2">' + preview.issues.map((issue) => '<li>' + escapeHtml(issue) + '</li>').join('') + '</ul></div>';
    } else {
      previewIssues.classList.add('d-none');
      previewIssues.innerHTML = '';
    }

    previewBody.innerHTML = lines.map((line) => {
      const stageName = String(line.stage_component_name || component.component_name || '-');
      const stagePrefix = Number(line.depth || 0) > 0 ? 'Inline' : 'Output';
      return '<tr>' +
        '<td><span class="badge text-bg-light border component-batch-stage">' + escapeHtml(stagePrefix + ' ' + stageName) + '</span></td>' +
        '<td>' + roleBadge(line.plan_role) + '</td>' +
        '<td>' + escapeHtml(line.source_label || '-') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatQty(line.required_qty || 0)) + ' ' + escapeHtml(line.uom_code || '') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatQty(line.available_qty || 0)) + ' ' + escapeHtml(line.uom_code || '') + '</td>' +
        '<td class="text-end">' + escapeHtml(formatCurrency(line.unit_cost || 0)) + '</td>' +
        '<td class="text-end fw-semibold">' + escapeHtml(formatCurrency(line.total_cost || 0)) + '</td>' +
        '<td class="text-center">' + statusBadge(Boolean(line.is_short), line.status_label) + '</td>' +
        '<td class="small text-muted">' + escapeHtml(line.notes || '') + '</td>' +
      '</tr>';
    }).join('');

    previewWrap.classList.toggle('d-none', !lines.length);
    previewEmpty.classList.toggle('d-none', !!lines.length);
    previewEmpty.textContent = lines.length ? '' : 'Belum ada plan produksi yang bisa ditampilkan.';
    saveButton.disabled = !lines.length || Boolean(summary.has_shortage);
  }

  async function loadPreview() {
    const componentId = String(outputComponentId.value || '');
    const locationType = String(locationTypeInput.value || '');
    const mode = String(scalingMode.value || 'BATCH').toUpperCase();
    const batchCountValue = parseFloat(batchCount.value || '0') || 0;
    const referenceActualQtyValue = parseFloat(referenceActualQty.value || '0') || 0;
    if (!componentId) {
      resetPreview('Pilih output component terlebih dahulu.');
      return;
    }
    if (!locationType) {
      resetPreview('Pilih lokasi Reguler atau Event agar plan produksi bisa dihitung.');
      return;
    }
    if (mode === 'REFERENCE') {
      if (!String(referenceLineNo.value || '')) {
        resetPreview('Pilih bahan acuan untuk melihat preview output dan pemakaian bahan.');
        return;
      }
      if (!(referenceActualQtyValue > 0)) {
        resetPreview('Isi qty aktual bahan acuan untuk melihat preview output dan pemakaian bahan.');
        return;
      }
    } else if (!(batchCountValue > 0)) {
      resetPreview('Isi jumlah batch untuk melihat preview output dan pemakaian bahan.');
      return;
    }

    try {
      const query = new URLSearchParams({
        component_id: componentId,
        location_type: locationType,
        scaling_mode: mode,
        batch_count: batchCountValue > 0 ? String(batchCountValue) : '',
        reference_line_no: String(referenceLineNo.value || ''),
        reference_actual_qty: referenceActualQtyValue > 0 ? String(referenceActualQtyValue) : ''
      });
      const response = await fetch(previewUrl + '?' + query.toString(), {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const text = await response.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (error) {
        throw new Error('Respons preview batch bukan JSON valid.');
      }
      if (!response.ok || !json.ok) {
        throw new Error(json.message || 'Preview batch gagal dimuat.');
      }
      renderPreview(json);
      clearAlert();
    } catch (error) {
      resetPreview(error.message || 'Gagal memuat preview batch.');
      renderAlert('danger', error.message || 'Gagal memuat preview batch.');
    }
  }

  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(() => {
      loadPreview();
    }, 180);
  }

  function bindOutputPicker() {
    window.ProductionAjaxPicker.bind(outputComponentSearch, {
      entity: 'COMPONENT',
      renderLabel: pickerLabel,
      renderSubLabel: pickerSubLabel,
      onType: () => {
        outputComponentId.value = '';
        outputUom.value = '';
        outputUomLabel.value = 'Ikuti output component';
        outputQty.value = '';
        outputQtyLabel.value = '0,00';
        divisionIdInput.value = '';
        outputDivisionCode = '';
        outputDivisionName = '';
        renderReferenceOptions([], '');
        syncBatchDivisionState();
        resetPreview('Pilih output component terlebih dahulu.');
      },
      onSelect: (result) => {
        outputComponentId.value = String(result.id || '');
        outputComponentSearch.value = pickerLabel(result);
        outputUom.value = String(result.uom_id || '');
        outputUomLabel.value = [result.uom_code || '', result.uom_name || ''].filter(Boolean).join(' - ') || 'Ikuti output component';
        divisionIdInput.value = String(result.operational_division_id || '');
        outputDivisionCode = String(result.division_code || '');
        outputDivisionName = String(result.division_name || '');
        syncBatchDivisionState();
        schedulePreview();
      }
    });
  }

  scalingMode?.addEventListener('change', () => {
    syncScalingMode();
    schedulePreview();
  });
  batchCount?.addEventListener('input', schedulePreview);
  batchCount?.addEventListener('change', schedulePreview);
  referenceLineNo?.addEventListener('change', schedulePreview);
  referenceActualQty?.addEventListener('input', schedulePreview);
  referenceActualQty?.addEventListener('change', schedulePreview);

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

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = event.submitter || form.querySelector('button[type="submit"]');
    const formData = new FormData(form);
    const payload = {
      batch_date: String(formData.get('batch_date') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      component_id: String(formData.get('component_id') || ''),
      output_qty: String(formData.get('output_qty') || ''),
      output_uom_id: String(formData.get('output_uom_id') || ''),
      scaling_mode: String(formData.get('scaling_mode') || 'BATCH'),
      batch_count: String(formData.get('batch_count') || ''),
      reference_line_no: String(formData.get('reference_line_no') || ''),
      reference_actual_qty: String(formData.get('reference_actual_qty') || ''),
      notes: String(formData.get('notes') || '')
    };
    if (!payload.component_id) {
      renderAlert('warning', 'Pilih output component melalui pencarian terlebih dahulu.');
      return;
    }
    if (!payload.division_id) {
      renderAlert('warning', 'Divisi batch belum terbentuk. Pilih output component yang valid.');
      return;
    }
    if (!payload.location_type) {
      renderAlert('warning', 'Pilih lokasi Reguler atau Event terlebih dahulu.');
      return;
    }
    if (String(payload.scaling_mode || 'BATCH').toUpperCase() === 'REFERENCE') {
      if (!payload.reference_line_no) {
        renderAlert('warning', 'Pilih bahan acuan terlebih dahulu.');
        return;
      }
      if (!(parseFloat(payload.reference_actual_qty || '0') > 0)) {
        renderAlert('warning', 'Qty aktual bahan acuan harus lebih dari 0.');
        return;
      }
    } else if (!(parseFloat(payload.batch_count || '0') > 0)) {
      renderAlert('warning', 'Jumlah batch harus lebih dari 0.');
      return;
    }
    if (!currentPreview) {
      renderAlert('warning', 'Preview batch belum siap. Lengkapi output component, lokasi, dan mode produksi terlebih dahulu.');
      return;
    }
    if (currentPreview.summary && currentPreview.summary.has_shortage) {
      renderAlert('warning', 'Batch masih memiliki shortage. Perbaiki ketersediaan bahan atau component terlebih dahulu.');
      return;
    }
    setButtonBusy(submitButton, 'Menyimpan batch...');
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal menyimpan batch.');
      clearButtonBusy(submitButton);
    }
  });

  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Posting batch akan mengurangi input dan menambah stok output component.', {
        title: 'Post Batch Produksi',
        okText: 'Post Batch',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Posting...');
      try {
        await postJson(postBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal post batch.');
        clearButtonBusy(button);
      }
    });
  });

  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Draft batch ini akan dihapus permanen.', {
        title: 'Hapus Draft Batch',
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
        renderAlert('danger', error.message || 'Gagal menghapus batch.');
        clearButtonBusy(button);
      }
    });
  });

  document.querySelectorAll('.btn-void').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('VOID hanya bisa dilakukan jika output batch belum dipakai. Lanjutkan?', {
        title: 'Void Batch Produksi',
        okText: 'Void Batch',
        cancelText: 'Batal'
      }))) {
        return;
      }
      setButtonBusy(button, 'Void...');
      try {
        await postJson(voidBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal void batch.');
        clearButtonBusy(button);
      }
    });
  });

  function renderUsageDetail(detail) {
    const traceRows = Array.isArray(detail.trace_rows) ? detail.trace_rows : [];
    const materialInputs = Array.isArray(detail.material_inputs) ? detail.material_inputs : [];
    const movementUsages = Array.isArray(detail.movement_usages) ? detail.movement_usages : [];
    const batchUsages = Array.isArray(detail.batch_usages) ? detail.batch_usages : [];
    const lotIssueUsages = Array.isArray(detail.lot_issue_usages) ? detail.lot_issue_usages : [];
    const header = detail.header || {};
    const blockReason = String(detail.block_reason || '');
    const detailUrl = usageDetailBaseUrl + '/' + String(header.id || '0');
    const summaryBadge = detail.can_void
      ? '<span class="badge text-bg-success">Batch masih bisa di-void</span>'
      : '<span class="badge text-bg-warning" title="' + escapeHtml(blockReason) + '">Tidak bisa di-void</span>';

    usageModalBody.innerHTML = '' +
      '<div class="mb-3">' +
        '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">' +
          '<div>' +
        '<div class="fw-semibold">' + escapeHtml(header.batch_no || '-') + ' • ' + escapeHtml(header.component_name || '-') + '</div>' +
        '<div class="small text-muted">Tanggal ' + escapeHtml(header.batch_date || '-') + ' • Qty ' + escapeHtml(formatQty(header.output_qty || 0)) + ' ' + escapeHtml(header.uom_code || '') + '</div>' +
        '<div class="mt-2">' + summaryBadge + '</div>' +
          '</div>' +
          '<div><a href="' + escapeHtml(detailUrl) + '" class="btn btn-sm btn-outline-info"><i class="ri ri-eye-line me-1"></i>Buka Detail</a></div>' +
        '</div>' +
        (blockReason ? '<div class="alert alert-warning mt-2 mb-0">' + escapeHtml(blockReason) + '</div>' : '') +
      '</div>' +
      '<div class="mb-3">' +
        '<h6 class="mb-2">Input Bahan Baku Batch Ini</h6>' +
        (materialInputs.length ?
          '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Line</th><th>Bahan</th><th class="text-end">Qty</th><th class="text-end">Total Cost</th><th>No FIFO</th></tr></thead><tbody>' +
          materialInputs.map((row) => '<tr><td>' + escapeHtml(String(row.line_no || 0)) + '</td><td>' + escapeHtml(row.material_label || '-') + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty || 0)) + ' ' + escapeHtml(row.uom_code || '') + '</td><td class="text-end">' + escapeHtml(formatCurrency(row.total_cost || 0)) + '</td><td>' + escapeHtml(row.fifo_issue_no || '-') + '</td></tr>').join('') +
          '</tbody></table></div>' :
          '<div class="text-muted small">Batch ini tidak memakai bahan baku langsung atau trace input bahan belum tersedia.</div>') +
      '</div>' +
      '<div class="mb-3">' +
        '<h6 class="mb-2">Trace Produksi Batch Ini</h6>' +
        (traceRows.length ?
          '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Tahap</th><th>Komponen</th><th>Jenis</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
          traceRows.map((row) => '<tr><td>' + escapeHtml(row.trace_label || '-') + '</td><td>' + escapeHtml(row.component_name || '-') + '</td><td>' + escapeHtml(row.movement_type_label || row.movement_type || '-') + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty_in || 0)) + ' ' + escapeHtml(row.uom_code || '') + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty_out || 0)) + ' ' + escapeHtml(row.uom_code || '') + '</td></tr>').join('') +
          '</tbody></table></div>' :
          '<div class="text-muted small">Belum ada trace posting batch yang tersimpan.</div>') +
      '</div>' +
      '<div class="mb-3">' +
        '<h6 class="mb-2">Dokumen yang memakai output batch</h6>' +
        (batchUsages.length ?
          '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Batch</th><th>Tanggal</th><th>Output</th><th class="text-end">Qty Pakai</th></tr></thead><tbody>' +
          batchUsages.map((row) => '<tr><td>' + escapeHtml(row.batch_no || '-') + '</td><td>' + escapeHtml(row.batch_date || '-') + '</td><td>' + escapeHtml(row.output_component_name || '-') + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty || 0)) + ' ' + escapeHtml(row.uom_code || '') + '</td></tr>').join('') +
          '</tbody></table></div>' :
          '<div class="text-muted small">Belum ada batch lain yang memakai output component ini sebagai input.</div>') +
      '</div>' +
      '<div>' +
        '<h6 class="mb-2">Movement keluar setelah batch ini</h6>' +
        (movementUsages.length ?
          '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>No Movement</th><th>Tanggal</th><th>Jenis</th><th>Sumber</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
          movementUsages.map((row) => '<tr><td>' + escapeHtml(row.movement_no || '-') + '</td><td>' + escapeHtml(row.movement_date || '-') + '</td><td>' + escapeHtml(row.movement_type_label || row.movement_type || '-') + '</td><td>' + escapeHtml((row.source_module || '-') + (row.source_id ? (' #' + row.source_id) : '')) + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty_out || 0)) + '</td></tr>').join('') +
          '</tbody></table></div>' :
          '<div class="text-muted small">Belum ada movement keluar yang memakai output batch ini.</div>') +
      '</div>' +
      '<div class="mt-3">' +
        '<h6 class="mb-2">Issue FIFO dari lot output batch ini</h6>' +
        (lotIssueUsages.length ?
          '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>No Issue</th><th>Tanggal</th><th>Sumber</th><th>Catatan</th><th class="text-end">Qty Out</th></tr></thead><tbody>' +
          lotIssueUsages.map((row) => '<tr><td>' + escapeHtml(row.issue_no || '-') + '</td><td>' + escapeHtml(row.issue_date || '-') + '</td><td>' + escapeHtml((row.source_module || '-') + (row.source_id ? (' #' + row.source_id) : '')) + '</td><td>' + escapeHtml(row.notes || '-') + '</td><td class="text-end">' + escapeHtml(formatQty(row.qty_out || 0)) + '</td></tr>').join('') +
          '</tbody></table></div>' :
          '<div class="text-muted small">Belum ada issue FIFO yang mengambil lot output batch ini.</div>') +
      '</div>';
  }

  document.querySelectorAll('.btn-usage').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!usageModal || !usageModalBody) {
        return;
      }
      usageModalBody.innerHTML = '<div class="text-muted">Memuat detail pemakaian batch...</div>';
      usageModal.show();
      try {
        const response = await fetch(usageBaseUrl + '/' + button.dataset.id, {
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const text = await response.text();
        let json;
        try {
          json = JSON.parse(text);
        } catch (error) {
          throw new Error('Respons detail usage bukan JSON valid.');
        }
        if (!response.ok || !json.ok) {
          throw new Error(json.message || 'Gagal memuat detail usage batch.');
        }
        renderUsageDetail(json);
      } catch (error) {
        usageModalBody.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message || 'Gagal memuat detail usage batch.') + '</div>';
      }
    });
  });

  const hash = String(window.location.hash || '').trim();
  if (hash.indexOf('#component-batch-') === 0) {
    const target = document.querySelector(hash);
    if (target) {
      target.classList.add('table-warning');
      target.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
  }

  locationGroupInput?.addEventListener('change', () => {
    syncBatchDivisionState();
    schedulePreview();
  });

  bindOutputPicker();
  syncBatchDivisionState();
  syncScalingMode();
  resetPreview('Pilih output component, lokasi, dan mode produksi untuk melihat preview produksi.');
})();
</script>
