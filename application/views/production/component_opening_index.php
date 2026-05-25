<?php
$rows = is_array($rows ?? null) ? $rows : [];
$monthlyRows = is_array($monthly_rows ?? null) ? $monthly_rows : [];
$components = is_array($components ?? null) ? $components : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
$q = (string)($q ?? '');
$month = preg_match('/^\d{4}-\d{2}$/', (string)($month ?? '')) ? (string)$month : date('Y-m');
$selectedLocationType = (string)($selected_location_type ?? '');
$selectedDivisionId = (int)($selected_division_id ?? 0);
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
</style>

<div class="mb-3">
  <h4 class="mb-1">Opening Base/Prepare</h4>
  <small class="text-muted">Editor baris untuk stok awal component. Simpan sebagai DRAFT, lalu POST saat siap menulis ledger dan balance.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'opening']); ?>

<div id="component-opening-alert" class="mb-3"></div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h5 class="mb-1">Form Opening</h5>
            <small class="text-muted">Tidak perlu lagi tulis JSON mentah. Tambah baris, pilih component, isi qty dan biaya.</small>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-opening-line">Tambah Baris</button>
        </div>

        <form id="frmOpening" autocomplete="off">
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label">Tanggal Opening</label>
              <input type="date" class="form-control" name="opening_date" value="<?php echo date('Y-m-d'); ?>" required>
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
                <option value="REGULER">Reguler</option>
                <option value="EVENT">Event</option>
              </select>
              <div class="form-text" id="opening-location-help">Pilih component dulu agar lokasi bisa diturunkan otomatis.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Catatan Header</label>
              <input type="text" class="form-control" name="notes" placeholder="Contoh: opening awal bulan">
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
            <button type="submit" class="btn btn-primary">Simpan DRAFT</button>
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

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h5 class="mb-1">Daftar Dokumen Opening</h5>
        <small class="text-muted">Filter halaman ini juga dipakai untuk daftar snapshot monthly di bawah.</small>
      </div>
      <form class="row g-2 align-items-end" method="get" action="<?php echo site_url('production/component-openings'); ?>">
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

    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
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
              <tr>
                <td><?php echo html_escape((string)($row['opening_no'] ?? '')); ?></td>
                <td><?php echo html_escape((string)($row['opening_date'] ?? '')); ?></td>
                <td><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></td>
                <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($row['notes'] ?? '-')); ?></td>
                <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                <td class="component-action-cell">
                  <?php if (strtoupper((string)($row['status'] ?? '')) === 'DRAFT'): ?>
                    <div class="component-action-stack">
                      <button type="button" class="btn btn-outline-success action-icon-btn component-action-btn btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-upload-2-line"></i></button>
                      <button type="button" class="btn btn-outline-danger action-icon-btn component-action-btn btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete"><i class="ri ri-delete-bin-line"></i></button>
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

<div class="card border-0 shadow-sm">
  <div class="card-body">
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

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const uomOptions = <?php echo json_encode(array_values(array_map(static function ($uom) {
      return [
          'id' => (int)($uom['id'] ?? 0),
        'label' => trim((string)($uom['name'] ?? '')),
      ];
  }, $uoms)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-openings/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-openings/post'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-openings/delete'); ?>';
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
  let lines = [];

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
    return Promise.resolve(window.confirm(String(message || 'Lanjutkan aksi?')));
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

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const payload = {
      opening_date: String(formData.get('opening_date') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      notes: String(formData.get('notes') || ''),
      lines: serializeLines()
    };
    if (!payload.lines.length) {
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
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal menyimpan opening.');
    }
  });

  document.getElementById('btn-generate-monthly-opening')?.addEventListener('click', async () => {
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
      try {
        await postJson(postBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal post opening.');
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
      try {
        await postJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal menghapus opening.');
      }
    });
  });

  locationGroupInput?.addEventListener('change', () => {
    syncHeaderDivisionState();
  });

  lines = [blankLine()];
  syncHeaderDivisionState();
  renderLines();
})();
</script>
