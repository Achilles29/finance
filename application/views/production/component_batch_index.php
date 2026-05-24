<?php
$rows = is_array($rows ?? null) ? $rows : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationOptions = is_array($location_options ?? null) ? $location_options : [];
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
</style>

<div class="mb-3">
  <h4 class="mb-1">Batch Produksi Base/Prepare</h4>
  <small class="text-muted">Pilih output component, tentukan qty jadi, lalu susun input material atau component per baris.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'batch']); ?>

<div id="component-batch-alert" class="mb-3"></div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h5 class="mb-1">Form Batch</h5>
        <small class="text-muted">Editor input mendukung sumber dari MATERIAL maupun COMPONENT tanpa JSON manual.</small>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-batch-line">Tambah Input</button>
    </div>

    <form id="frmBatch" autocomplete="off">
      <div class="row g-2 mb-3">
        <div class="col-md-2">
          <label class="form-label">Tanggal Batch</label>
          <input type="date" class="form-control" name="batch_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-2">
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
          <label class="form-label">Output Component</label>
          <input type="hidden" name="component_id" id="batch-output-component-id" value="">
          <input type="text" class="form-control" id="batch-output-component-search" placeholder="Ketik kode/nama component..." autocomplete="off" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Output Qty</label>
          <input type="number" min="0" step="0.0001" class="form-control text-end" name="output_qty" id="batch-output-qty" placeholder="0.0000" required>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-md-3">
          <label class="form-label">UOM Output</label>
          <select class="form-select" name="output_uom_id" id="batch-output-uom" required>
            <option value="">Pilih UOM output...</option>
            <?php foreach ($uoms as $uom): ?>
              <option value="<?php echo (int)$uom['id']; ?>"><?php echo html_escape((string)($uom['code'] ?? '')); ?> - <?php echo html_escape((string)($uom['name'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-9">
          <label class="form-label">Catatan Header</label>
          <input type="text" class="form-control" name="notes" placeholder="Contoh: batch prep sore hari">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle mb-2">
          <thead>
            <tr>
              <th style="width:34px;">#</th>
              <th style="width:120px;">Tipe</th>
              <th style="width:320px;">Sumber</th>
              <th style="width:140px;">UOM</th>
              <th style="width:120px;" class="text-end">Qty</th>
              <th style="width:140px;" class="text-end">Unit Cost</th>
              <th style="width:140px;" class="text-end">Total</th>
              <th>Catatan</th>
              <th style="width:52px;"></th>
            </tr>
          </thead>
          <tbody id="batch-line-body"></tbody>
        </table>
      </div>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
        <div class="component-batch-summary d-flex flex-wrap gap-4">
          <div><div class="small text-muted">Input Baris</div><strong id="batch-line-count">0</strong></div>
          <div><div class="small text-muted">Total Input Cost</div><strong id="batch-total-input-cost">Rp 0</strong></div>
          <div><div class="small text-muted">Estimasi Cost / Output</div><strong id="batch-estimated-unit-cost">Rp 0</strong></div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan DRAFT</button>
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
                <td><?php echo html_escape((string)($row['location_type'] ?? '')); ?></td>
                <td><?php echo html_escape((string)($row['component_code'] ?? '')); ?> - <?php echo html_escape((string)($row['component_name'] ?? '')); ?></td>
                <td><?php echo number_format((float)($row['output_qty'] ?? 0), 4, ',', '.'); ?> <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></td>
                <td><?php echo ui_status_badge((string)($row['status'] ?? 'DRAFT')); ?></td>
                <td class="component-action-cell">
                  <?php if (strtoupper((string)($row['status'] ?? '')) === 'DRAFT'): ?>
                    <div class="component-action-stack">
                      <button type="button" class="btn btn-success btn-sm action-icon-btn component-action-btn btn-post" data-id="<?php echo (int)$row['id']; ?>" title="Post" aria-label="Post"><i class="ri ri-upload-2-line"></i></button>
                      <button type="button" class="btn btn-outline-danger btn-sm action-icon-btn component-action-btn btn-del" data-id="<?php echo (int)$row['id']; ?>" title="Delete" aria-label="Delete"><i class="ri ri-delete-bin-line"></i></button>
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
  const saveUrl = '<?php echo site_url('production/component-batches/save'); ?>';
  const postBaseUrl = '<?php echo site_url('production/component-batches/post'); ?>';
  const deleteBaseUrl = '<?php echo site_url('production/component-batches/delete'); ?>';

  const alertHost = document.getElementById('component-batch-alert');
  const lineBody = document.getElementById('batch-line-body');
  const form = document.getElementById('frmBatch');
  const outputComponentId = document.getElementById('batch-output-component-id');
  const outputComponentSearch = document.getElementById('batch-output-component-search');
  const outputQty = document.getElementById('batch-output-qty');
  const outputUom = document.getElementById('batch-output-uom');
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

  function blankLine() {
    return {
      source_kind: 'MATERIAL',
      source_id: '',
      source_label: '',
      uom_id: '',
      qty: '',
      unit_cost: '',
      notes: ''
    };
  }

  function pickerLabel(row) {
    return [row.code || '', row.name || ''].filter(Boolean).join(' - ');
  }

  function pickerSubLabel(row) {
    return [row.entity_type || '', row.uom_code || '', row.category_name || ''].filter(Boolean).join(' | ');
  }

  function uomSelectOptions(selectedValue) {
    const options = ['<option value="">Pilih UOM...</option>'];
    uomOptions.forEach((uom) => {
      options.push('<option value="' + uom.id + '"' + (String(selectedValue) === String(uom.id) ? ' selected' : '') + '>' + escapeHtml(uom.label) + '</option>');
    });
    return options.join('');
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 2}).format(value || 0);
  }

  function renderSummary() {
    const validLines = lines.filter((line) => Number(line.source_id) > 0 && Number(line.uom_id) > 0 && (parseFloat(line.qty) || 0) > 0);
    const totalInputCost = validLines.reduce((sum, line) => sum + ((parseFloat(line.qty) || 0) * (parseFloat(line.unit_cost) || 0)), 0);
    const outputQtyValue = parseFloat(outputQty?.value || '0') || 0;
    document.getElementById('batch-line-count').textContent = String(validLines.length);
    document.getElementById('batch-total-input-cost').textContent = formatCurrency(totalInputCost);
    document.getElementById('batch-estimated-unit-cost').textContent = formatCurrency(outputQtyValue > 0 ? (totalInputCost / outputQtyValue) : 0);
  }

  function bindOutputPicker() {
    window.ProductionAjaxPicker.bind(outputComponentSearch, {
      entity: 'COMPONENT',
      renderLabel: pickerLabel,
      renderSubLabel: pickerSubLabel,
      onType: () => {
        outputComponentId.value = '';
        outputUom.value = '';
        renderSummary();
      },
      onSelect: (result) => {
        outputComponentId.value = String(result.id || '');
        outputComponentSearch.value = pickerLabel(result);
        outputUom.value = String(result.uom_id || '');
        renderSummary();
      }
    });
  }

  function bindSourcePickers() {
    lineBody.querySelectorAll('.batch-source-picker').forEach((input) => {
      window.ProductionAjaxPicker.bind(input, {
        entity: String(lines[Number(input.closest('tr')?.dataset.index || -1)]?.source_kind || 'MATERIAL') === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
        params: () => {
          const row = input.closest('tr');
          const index = Number(row?.dataset.index || -1);
          const line = lines[index] || {};
          if (String(line.source_kind || 'MATERIAL') === 'COMPONENT') {
            return {exclude_id: String(outputComponentId?.value || '')};
          }
          return {};
        },
        renderLabel: pickerLabel,
        renderSubLabel: pickerSubLabel,
        onType: (value, currentInput) => {
          const row = currentInput.closest('tr');
          const index = Number(row?.dataset.index || -1);
          if (index < 0) {
            return;
          }
          lines[index].source_label = value;
          lines[index].source_id = '';
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
          lines[index].source_id = String(result.id || '');
          lines[index].source_label = pickerLabel(result);
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
      const rowTotal = (parseFloat(line.qty) || 0) * (parseFloat(line.unit_cost) || 0);
      return '<tr data-index="' + index + '">' +
        '<td class="text-muted">' + (index + 1) + '</td>' +
        '<td><select class="form-select form-select-sm" data-field="source_kind"><option value="MATERIAL"' + (line.source_kind === 'MATERIAL' ? ' selected' : '') + '>MATERIAL</option><option value="COMPONENT"' + (line.source_kind === 'COMPONENT' ? ' selected' : '') + '>COMPONENT</option></select></td>' +
        '<td><input type="text" class="form-control form-control-sm batch-source-picker" value="' + escapeHtml(line.source_label || '') + '" placeholder="Ketik kode/nama sumber..."' + (Number(line.source_id) > 0 ? ' data-selected-label="' + escapeHtml(line.source_label || '') + '"' : '') + '></td>' +
        '<td><select class="form-select form-select-sm" data-field="uom_id">' + uomSelectOptions(line.uom_id) + '</select></td>' +
        '<td><input type="number" min="0" step="0.0001" class="form-control form-control-sm text-end" data-field="qty" value="' + escapeHtml(line.qty) + '"></td>' +
        '<td><input type="number" min="0" step="0.000001" class="form-control form-control-sm text-end" data-field="unit_cost" value="' + escapeHtml(line.unit_cost) + '"></td>' +
        '<td class="text-end fw-semibold">' + escapeHtml(formatCurrency(rowTotal)) + '</td>' +
        '<td><input type="text" class="form-control form-control-sm" data-field="notes" value="' + escapeHtml(line.notes) + '"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove">×</button></td>' +
      '</tr>';
    }).join('');
    bindSourcePickers();
    renderSummary();
  }

  function serializeLines() {
    return lines
      .filter((line) => Number(line.source_id) > 0 && Number(line.uom_id) > 0 && (parseFloat(line.qty) || 0) > 0)
      .map((line) => {
        const payload = {
          source_kind: String(line.source_kind || 'MATERIAL'),
          uom_id: Number(line.uom_id),
          qty: parseFloat(line.qty) || 0,
          unit_cost: parseFloat(line.unit_cost) || 0,
          notes: String(line.notes || '')
        };
        if (payload.source_kind === 'COMPONENT') {
          payload.component_id = Number(line.source_id);
        } else {
          payload.material_id = Number(line.source_id);
        }
        return payload;
      });
  }

  document.getElementById('btn-add-batch-line')?.addEventListener('click', () => {
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
    if (field === 'source_kind') {
      lines[index].source_id = '';
      lines[index].source_label = '';
      lines[index].uom_id = '';
      renderLines();
      return;
    }
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

  outputQty?.addEventListener('input', renderSummary);

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const payload = {
      batch_date: String(formData.get('batch_date') || ''),
      location_type: String(formData.get('location_type') || ''),
      division_id: String(formData.get('division_id') || ''),
      component_id: String(formData.get('component_id') || ''),
      output_qty: String(formData.get('output_qty') || ''),
      output_uom_id: String(formData.get('output_uom_id') || ''),
      notes: String(formData.get('notes') || ''),
      lines: serializeLines()
    };
    if (!payload.component_id) {
      renderAlert('warning', 'Pilih output component melalui pencarian terlebih dahulu.');
      return;
    }
    if (!payload.lines.length) {
      renderAlert('warning', 'Tambahkan minimal satu input batch yang valid.');
      return;
    }
    try {
      await postJson(saveUrl, payload);
      window.location.reload();
    } catch (error) {
      renderAlert('danger', error.message || 'Gagal menyimpan batch.');
    }
  });

  document.querySelectorAll('.btn-post').forEach((button) => {
    button.addEventListener('click', async () => {
      if (!window.confirm('Post batch ini?')) {
        return;
      }
      try {
        await postJson(postBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal post batch.');
      }
    });
  });

  document.querySelectorAll('.btn-del').forEach((button) => {
    button.addEventListener('click', async () => {
      if (!window.confirm('Hapus draft batch ini?')) {
        return;
      }
      try {
        await postJson(deleteBaseUrl + '/' + button.dataset.id, {});
        window.location.reload();
      } catch (error) {
        renderAlert('danger', error.message || 'Gagal menghapus batch.');
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

  bindOutputPicker();
  lines = [blankLine()];
  renderLines();
})();
</script>
