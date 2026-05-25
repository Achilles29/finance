<?php
$rows = is_array($rows ?? null) ? $rows : [];
$components = is_array($components ?? null) ? $components : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
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
?>

<style>
  .component-adjustment-summary {
    background: #f8faf6;
    border: 1px solid #d8e4cc;
    border-radius: 16px;
    padding: 1rem 1.1rem;
  }
  .component-adjustment-summary strong {
    font-size: 1.12rem;
    color: #304125;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1">Adjustment Base/Prepare</h4>
  <small class="text-muted">Pecah per component untuk spoil, waste, plus, dan minus. Simpan DRAFT dulu sebelum POST.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'adjustment']); ?>

<div id="component-adjustment-alert" class="mb-3"></div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1">Form Adjustment</h5>
        <small class="text-muted">Set qty tersedia sebagai angka acuan lapangan, lalu isi spoil, waste, plus, atau minus sesuai kebutuhan.</small>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-adjustment-line">Tambah Baris</button>
    </div>

    <form id="frmAdjustment" autocomplete="off">
      <div class="row g-2 mb-3">
        <div class="col-md-3">
          <label class="form-label">Tanggal Adjustment</label>
          <input type="date" class="form-control" name="adjustment_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Lokasi</label>
          <select class="form-select" name="location_type" required>
            <option value="">Pilih lokasi...</option>
            <?php foreach ($locationOptions as $key => $label): if ($key === '') continue; ?>
              <option value="<?php echo html_escape($key); ?>"><?php echo html_escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Divisi</label>
          <select class="form-select" name="division_id">
            <option value="">Semua / Tidak spesifik</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?php echo (int)$division['id']; ?>"><?php echo html_escape((string)($division['code'] ?? '')); ?><?php echo !empty($division['code']) ? ' - ' : ''; ?><?php echo html_escape((string)($division['name'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Catatan Header</label>
          <input type="text" class="form-control" name="notes" placeholder="Contoh: penyesuaian akhir shift">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2" id="adjustment-table">
          <thead>
            <tr>
              <th style="width:34px;">#</th>
              <th style="width:310px;">Component</th>
              <th style="width:140px;">UOM</th>
              <th style="width:120px;" class="text-end">Available</th>
              <th style="width:110px;" class="text-end">Spoil</th>
              <th style="width:110px;" class="text-end">Waste</th>
              <th style="width:110px;" class="text-end">Plus</th>
              <th style="width:110px;" class="text-end">Minus</th>
              <th>Catatan</th>
              <th style="width:52px;"></th>
            </tr>
          </thead>
          <tbody id="adjustment-line-body"></tbody>
        </table>
      </div>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
        <div class="component-adjustment-summary d-flex flex-wrap gap-4">
          <div><div class="small text-muted">Baris</div><strong id="adj-line-count">0</strong></div>
          <div><div class="small text-muted">Total Spoil</div><strong id="adj-total-spoil">0,00</strong></div>
          <div><div class="small text-muted">Total Waste</div><strong id="adj-total-waste">0,00</strong></div>
          <div><div class="small text-muted">Total Plus</div><strong id="adj-total-plus">0,00</strong></div>
          <div><div class="small text-muted">Total Minus</div><strong id="adj-total-minus">0,00</strong></div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan DRAFT</button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Daftar Adjustment</h5>
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
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada dokumen adjustment.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['adjustment_no'] ?? '')); ?></td>
                <td><?php echo html_escape((string)($row['adjustment_date'] ?? '')); ?></td>
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

<?php $this->load->view('production/_ajax_picker_helper'); ?>

<script>
(() => {
  const uomOptions = <?php echo json_encode(array_values(array_map(static function ($uom) {
      return [
          'id' => (int)($uom['id'] ?? 0),
          'label' => trim((string)($uom['code'] ?? '') . ' - ' . (string)($uom['name'] ?? '')),
      ];
  }, $uoms)), JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const saveUrl = '<?php echo site_url('production/component-adjustments/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-adjustments/post'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-adjustments/delete'); ?>';

  const alertHost = document.getElementById('component-adjustment-alert');
  const lineBody = document.getElementById('adjustment-line-body');
  const form = document.getElementById('frmAdjustment');
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
    if (alertHost) {
      alertHost.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(message) + '</div>';
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
    return Promise.resolve(window.confirm(String(message || 'Lanjutkan aksi?')));
  }

  function blankLine() {
    return {
      component_id: '',
      component_label: '',
      uom_id: '',
      available_qty: '',
      qty_spoil: '',
      qty_waste: '',
      qty_adjust_pos: '',
      qty_adjust_neg: '',
      note: ''
    };
  }

  function componentPickerLabel(row) {
    return [row.code || '', row.name || ''].filter(Boolean).join(' - ');
  }

  function componentPickerSubLabel(row) {
    return [row.entity_type || '', row.uom_code || '', row.category_name || ''].filter(Boolean).join(' | ');
  }

  function uomSelectOptions(selectedValue) {
    const options = ['<option value="">Pilih UOM...</option>'];
    uomOptions.forEach((uom) => {
      options.push('<option value="' + uom.id + '"' + (String(selectedValue) === String(uom.id) ? ' selected' : '') + '>' + escapeHtml(uom.label) + '</option>');
    });
    return options.join('');
  }

  function renderSummary() {
    const validLines = lines.filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0);
    const totalOf = (field) => validLines.reduce((sum, line) => sum + (parseFloat(line[field]) || 0), 0);
    const formatter = (value) => value.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('adj-line-count').textContent = String(validLines.length);
    document.getElementById('adj-total-spoil').textContent = formatter(totalOf('qty_spoil'));
    document.getElementById('adj-total-waste').textContent = formatter(totalOf('qty_waste'));
    document.getElementById('adj-total-plus').textContent = formatter(totalOf('qty_adjust_pos'));
    document.getElementById('adj-total-minus').textContent = formatter(totalOf('qty_adjust_neg'));
  }

  function bindComponentPickers() {
    lineBody.querySelectorAll('.component-picker-input').forEach((input) => {
      window.ProductionAjaxPicker.bind(input, {
        entity: 'COMPONENT',
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
          lines[index].uom_id = '';
          const uomSelect = row.querySelector('[data-field="uom_id"]');
          if (uomSelect) {
            uomSelect.value = '';
          }
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
          lines[index].uom_id = String(result.uom_id || '');
          renderLines();
        }
      });
    });
  }

  function renderLines() {
    if (!lines.length) {
      lines = [blankLine()];
    }
    lineBody.innerHTML = lines.map((line, index) => {
      return '<tr data-index="' + index + '">' +
        '<td class="text-muted">' + (index + 1) + '</td>' +
        '<td><input type="text" class="form-control form-control-sm component-picker-input" value="' + escapeHtml(line.component_label || '') + '" placeholder="Ketik kode/nama component..."' + (Number(line.component_id) > 0 ? ' data-selected-label="' + escapeHtml(line.component_label || '') + '"' : '') + '></td>' +
        '<td><select class="form-select form-select-sm" data-field="uom_id">' + uomSelectOptions(line.uom_id) + '</select></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="available_qty" value="' + escapeHtml(line.available_qty) + '"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="qty_spoil" value="' + escapeHtml(line.qty_spoil) + '"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="qty_waste" value="' + escapeHtml(line.qty_waste) + '"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="qty_adjust_pos" value="' + escapeHtml(line.qty_adjust_pos) + '"></td>' +
        '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-field="qty_adjust_neg" value="' + escapeHtml(line.qty_adjust_neg) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" data-field="note" value="' + escapeHtml(line.note) + '"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove">×</button></td>' +
      '</tr>';
    }).join('');
    bindComponentPickers();
    renderSummary();
  }

  function serializeLines() {
    return lines
      .filter((line) => Number(line.component_id) > 0 && Number(line.uom_id) > 0)
      .map((line) => ({
        component_id: Number(line.component_id),
        uom_id: Number(line.uom_id),
        available_qty: parseFloat(line.available_qty) || 0,
        qty_spoil: parseFloat(line.qty_spoil) || 0,
        qty_waste: parseFloat(line.qty_waste) || 0,
        qty_adjust_pos: parseFloat(line.qty_adjust_pos) || 0,
        qty_adjust_neg: parseFloat(line.qty_adjust_neg) || 0,
        note: String(line.note || '')
      }))
      .filter((line) => line.available_qty > 0 || line.qty_spoil > 0 || line.qty_waste > 0 || line.qty_adjust_pos > 0 || line.qty_adjust_neg > 0);
  }

  document.getElementById('btn-add-adjustment-line')?.addEventListener('click', () => {
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
    if (index >= 0) {
      lines.splice(index, 1);
      renderLines();
    }
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
      adjustment_date: String(formData.get('adjustment_date') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      notes: String(formData.get('notes') || ''),
      lines: serializeLines()
    };
    if (!payload.lines.length) {
      renderAlert('warning', 'Tambahkan minimal satu baris adjustment yang berisi angka perubahan.');
      return;
    }
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal menyimpan adjustment.');
    }
  });

  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Posting adjustment akan menulis mutasi spoil, waste, plus, dan minus ke ledger component.', {
        title: 'Post Dokumen Adjustment',
        okText: 'Post Adjustment',
        cancelText: 'Batal'
      }))) {
        return;
      }
      try {
        await postJson(postBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal post adjustment.');
      }
    });
  });

  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      button.blur();
      if (!(await uiConfirm('Draft adjustment ini akan dihapus permanen.', {
        title: 'Hapus Draft Adjustment',
        okText: 'Hapus Draft',
        cancelText: 'Batal'
      }))) {
        return;
      }
      try {
        await postJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal menghapus adjustment.');
      }
    });
  });

  lines = [blankLine()];
  renderLines();
})();
</script>
