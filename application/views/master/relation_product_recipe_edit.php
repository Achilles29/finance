<?php
$parent = is_array($parent ?? null) ? $parent : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$options = is_array($options ?? null) ? $options : [];
$productVariableCost = is_array($product_variable_cost ?? null) ? $product_variable_cost : [];
$backUrl = site_url('master/relation/product-recipe/' . (int)($parent['id'] ?? 0));
$saveUrl = site_url('master/relation/product-recipe/edit-all/' . (int)($parent['id'] ?? 0) . '/save');

$initialLines = [];
foreach ($rows as $row) {
    $initialLines[] = [
        'line_type' => (string)($row['line_type'] ?? 'MATERIAL'),
    'ingredient_role' => (string)($row['ingredient_role'] ?? 'MAIN'),
        'material_item_id' => (int)($row['material_item_id'] ?? 0),
        'component_id' => (int)($row['component_id'] ?? 0),
    'source_division_id' => (int)($row['source_division_id'] ?? 0),
        'qty' => round((float)($row['qty'] ?? 0), 4),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'notes' => (string)($row['notes'] ?? ''),
    ];
}
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Edit Resep: <?php echo html_escape((string)($parent['product_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Divisi Produk <?php echo html_escape((string)($parent['product_division_name'] ?? '-')); ?> |
        Harga jual Rp <?php echo number_format((float)($summary['selling_price'] ?? 0), 2, ',', '.'); ?> |
        Total HPP live saat ini Rp <?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-info btn-sm" href="<?php echo $backUrl; ?>">Detail</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('master/relation/product-recipe'); ?>">Kembali</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
      <div class="col-md-2"><small class="text-muted d-block">Total Baris</small><strong id="sum-line"><?php echo (int)($summary['line_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Material</small><strong id="sum-material"><?php echo (int)($summary['material_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Line Component</small><strong id="sum-component"><?php echo (int)($summary['component_count'] ?? 0); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Direct Live</small><strong>Rp <?php echo number_format((float)($summary['direct_cost_live'] ?? 0), 2, ',', '.'); ?></strong></div>
      <div class="col-md-2"><small class="text-muted d-block">Variable Cost</small><strong><?php echo html_escape((string)($productVariableCost['mode'] ?? 'DEFAULT')); ?> / <?php echo number_format((float)($productVariableCost['effective_percent'] ?? 0), 2, ',', '.'); ?>%</strong></div>
      <div class="col-md-2"><small class="text-muted d-block">HPP Live</small><strong>Rp <?php echo number_format((float)($summary['total_hpp_live'] ?? 0), 2, ',', '.'); ?></strong></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="post" action="<?php echo $saveUrl; ?>" id="productRecipeBulkForm">
        <input type="hidden" name="lines_json" id="lines_json" value="">

        <div class="row g-2 mb-3">
          <div class="col-12">
            <div class="alert alert-light border py-2 mb-1">
              <strong>Catatan:</strong> source divisi default tetap mengikuti sumber, tetapi di layar ini boleh dioverride per baris jika bahan diambil dari divisi operasional lain. Semua perubahan disimpan sekaligus.
            </div>
          </div>
          <div class="col-md-4">
            <input id="line-search" class="form-control" placeholder="Cari sumber/catatan...">
          </div>
          <div class="col-md-3">
            <select id="line-type-filter" class="form-select">
              <option value="ALL">Semua Baris</option>
              <option value="MATERIAL">MATERIAL</option>
              <option value="COMPONENT">COMPONENT</option>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="button" id="btn-add-line" class="btn btn-outline-secondary">Tambah Baris</button>
          </div>
          <div class="col-md-3 d-grid">
            <button type="submit" id="btn-save" class="btn btn-primary">Simpan Resep</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle" id="recipe-table">
            <thead>
              <tr>
                <th style="width:120px;">Tipe</th>
                <th style="width:150px;">Role</th>
                <th style="width:180px;">Source Divisi</th>
                <th style="width:360px;">Sumber</th>
                <th style="width:110px;">Qty</th>
                <th style="width:90px;">UOM</th>
                <th style="width:100px;">Sort</th>
                <th>Catatan</th>
                <th style="width:90px;"></th>
              </tr>
            </thead>
            <tbody id="line-body"></tbody>
          </table>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var materials = <?php echo json_encode(array_values($options['materials'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var components = <?php echo json_encode(array_values($options['components'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var sourceDivisions = <?php echo json_encode(array_values($options['source_divisions'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var ingredientRoles = <?php echo json_encode(array_values($options['ingredient_roles'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var initialLines = <?php echo json_encode(array_values($initialLines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var defaultSourceDivisionName = <?php echo json_encode((string)($options['default_source_division_name'] ?? '-')); ?>;
  var defaultSourceDivisionId = <?php echo json_encode((int)($options['default_source_division_id'] ?? 0)); ?>;
  var lineBody = document.getElementById('line-body');
  var lineSearch = document.getElementById('line-search');
  var lineTypeFilter = document.getElementById('line-type-filter');
  var linesInput = document.getElementById('lines_json');
  var form = document.getElementById('productRecipeBulkForm');
  var btnAddLine = document.getElementById('btn-add-line');
  var sumLine = document.getElementById('sum-line');
  var sumMaterial = document.getElementById('sum-material');
  var sumComponent = document.getElementById('sum-component');

  var state = {
    lines: Array.isArray(initialLines) && initialLines.length ? initialLines : [{ line_type: 'MATERIAL', ingredient_role: 'MAIN', material_item_id: 0, component_id: 0, source_division_id: defaultSourceDivisionId || 0, qty: 1, sort_order: 10, notes: '' }]
  };

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function findOption(list, value) {
    var num = Number(value || 0);
    return list.find(function (item) { return Number(item.value || 0) === num; }) || null;
  }

  function ingredientRoleOptionsHtml(selected) {
    var current = String(selected || 'MAIN').toUpperCase();
    var html = '';
    ingredientRoles.forEach(function (opt) {
      var value = String(opt.value || 'MAIN').toUpperCase();
      var isSelected = value === current ? ' selected' : '';
      html += '<option value="' + esc(value) + '"' + isSelected + '>' + esc(opt.label || '-') + '</option>';
    });
    return html;
  }

  function sourceDivisionOptionsHtml(selected) {
    var current = Number(selected || 0);
    var html = '<option value="">- pilih divisi -</option>';
    sourceDivisions.forEach(function (opt) {
      var isSelected = Number(opt.value || 0) === current ? ' selected' : '';
      html += '<option value="' + esc(opt.value) + '"' + isSelected + '>' + esc(opt.label || '-') + '</option>';
    });
    return html;
  }

  function suggestedSourceDivisionId(line) {
    if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
      var component = findOption(components, line.component_id);
      if (component && Number(component.source_division_id || 0) > 0) {
        return Number(component.source_division_id || 0);
      }
      return Number(defaultSourceDivisionId || 0);
    }
    var material = findOption(materials, line.material_item_id);
    if (material && Number(material.source_division_id || 0) > 0) {
      return Number(material.source_division_id || 0);
    }
    return Number(defaultSourceDivisionId || 0);
  }

  function filteredSourceList(line) {
    if ((line.line_type || 'MATERIAL') !== 'COMPONENT') {
      return materials;
    }

    var selectedDivisionId = Number(line.source_division_id || suggestedSourceDivisionId(line) || 0);
    var list = components.filter(function (opt) {
      return selectedDivisionId <= 0 || Number(opt.source_division_id || 0) === selectedDivisionId;
    });

    if (Number(line.component_id || 0) > 0 && !list.some(function (opt) { return Number(opt.value || 0) === Number(line.component_id || 0); })) {
      var current = findOption(components, line.component_id);
      if (current) {
        list = list.concat([current]);
      }
    }

    return list;
  }

  function lineMeta(line) {
    var resolvedDivisionId = Number(line.source_division_id || 0);
    var sourceDivision = findOption(sourceDivisions, resolvedDivisionId);
    if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
      var component = findOption(components, line.component_id);
      return {
        sourceDivisionName: sourceDivision ? String(sourceDivision.label || '-') : (component ? String(component.source_division_name || '-') : '-'),
        uomLabel: component ? String(component.uom_label || '-') : '-',
        searchText: (component ? String(component.label || '') : '') + ' ' + String(line.notes || '')
      };
    }
    var material = findOption(materials, line.material_item_id);
    return {
      sourceDivisionName: sourceDivision ? String(sourceDivision.label || '-') : (material ? String(material.source_division_name || defaultSourceDivisionName || '-') : (defaultSourceDivisionName || '-')),
      uomLabel: material ? String(material.uom_label || '-') : '-',
      searchText: (material ? String(material.label || '') : '') + ' ' + String(line.notes || '')
    };
  }

  function sourceOptionsHtml(line) {
    var isComponent = (line.line_type || 'MATERIAL') === 'COMPONENT';
    var list = filteredSourceList(line);
    var field = isComponent ? 'component_id' : 'material_item_id';
    var selected = Number(line[field] || 0);
    var placeholder = isComponent
      ? (list.length ? '- pilih component -' : '- pilih source divisi dulu -')
      : '- pilih bahan baku -';
    var html = '<option value="">' + esc(placeholder) + '</option>';
    list.forEach(function (opt) {
      var isSelected = Number(opt.value || 0) === selected ? ' selected' : '';
      html += '<option value="' + esc(opt.value) + '"' + isSelected + '>' + esc(opt.label || '-') + '</option>';
    });
    return html;
  }

  function recalcSummary() {
    var materialCount = 0;
    var componentCount = 0;
    state.lines.forEach(function (line) {
      if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
        componentCount += 1;
      } else {
        materialCount += 1;
      }
    });
    sumLine.textContent = String(state.lines.length);
    sumMaterial.textContent = String(materialCount);
    sumComponent.textContent = String(componentCount);
  }

  function render() {
    var keyword = String(lineSearch ? lineSearch.value : '').trim().toLowerCase();
    var typeFilter = String(lineTypeFilter ? lineTypeFilter.value : 'ALL');
    lineBody.innerHTML = '';

    state.lines.forEach(function (line, index) {
      var meta = lineMeta(line);
      var haystack = meta.searchText.toLowerCase();
      var matchesKeyword = keyword === '' || haystack.indexOf(keyword) !== -1;
      var matchesType = typeFilter === 'ALL' || typeFilter === String(line.line_type || 'MATERIAL');
      if (!matchesKeyword || !matchesType) {
        return;
      }

      var row = document.createElement('tr');
      row.innerHTML = '' +
        '<td>' +
          '<select class="form-select form-select-sm" data-field="line_type" data-index="' + index + '">' +
            '<option value="MATERIAL"' + ((line.line_type || 'MATERIAL') === 'MATERIAL' ? ' selected' : '') + '>MATERIAL</option>' +
            '<option value="COMPONENT"' + ((line.line_type || 'MATERIAL') === 'COMPONENT' ? ' selected' : '') + '>COMPONENT</option>' +
          '</select>' +
        '</td>' +
        '<td><select class="form-select form-select-sm" data-field="ingredient_role" data-index="' + index + '">' + ingredientRoleOptionsHtml(line.ingredient_role || 'MAIN') + '</select></td>' +
        '<td><select class="form-select form-select-sm" data-field="source_division_id" data-index="' + index + '">' + sourceDivisionOptionsHtml(line.source_division_id || suggestedSourceDivisionId(line)) + '</select></td>' +
        '<td>' +
          '<select class="form-select form-select-sm" data-field="source_id" data-index="' + index + '">' + sourceOptionsHtml(line) + '</select>' +
        '</td>' +
        '<td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" data-field="qty" data-index="' + index + '" value="' + esc(line.qty || 0) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" value="' + esc(meta.uomLabel) + '" readonly></td>' +
        '<td><input type="number" class="form-control form-control-sm" data-field="sort_order" data-index="' + index + '" value="' + esc(line.sort_order || ((index + 1) * 10)) + '"></td>' +
        '<td><input type="text" class="form-control form-control-sm" data-field="notes" data-index="' + index + '" value="' + esc(line.notes || '') + '"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove" data-index="' + index + '">Hapus</button></td>';
      lineBody.appendChild(row);
    });

    recalcSummary();
  }

  lineBody.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !target.dataset) {
      return;
    }
    var index = Number(target.dataset.index || -1);
    if (index < 0 || !state.lines[index]) {
      return;
    }

    if (target.dataset.field === 'line_type') {
      state.lines[index].line_type = target.value === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL';
      state.lines[index].material_item_id = 0;
      state.lines[index].component_id = 0;
      state.lines[index].source_division_id = Number(state.lines[index].source_division_id || suggestedSourceDivisionId(state.lines[index]) || defaultSourceDivisionId || 0);
      render();
      return;
    }

    if (target.dataset.field === 'source_id') {
      if ((state.lines[index].line_type || 'MATERIAL') === 'COMPONENT') {
        state.lines[index].component_id = Number(target.value || 0);
      } else {
        state.lines[index].material_item_id = Number(target.value || 0);
      }
      if (Number(state.lines[index].source_division_id || 0) <= 0) {
        state.lines[index].source_division_id = suggestedSourceDivisionId(state.lines[index]);
      }
      render();
      return;
    }

    if (target.dataset.field === 'source_division_id') {
      state.lines[index].source_division_id = Number(target.value || 0);
      if ((state.lines[index].line_type || 'MATERIAL') === 'COMPONENT') {
        var filtered = filteredSourceList(state.lines[index]);
        if (!filtered.some(function (opt) { return Number(opt.value || 0) === Number(state.lines[index].component_id || 0); })) {
          state.lines[index].component_id = 0;
        }
        render();
      }
      return;
    }

    if (target.dataset.field === 'ingredient_role') {
      state.lines[index].ingredient_role = String(target.value || 'MAIN').toUpperCase();
      return;
    }

    if (target.dataset.field === 'qty') {
      state.lines[index].qty = Number(target.value || 0);
      return;
    }
    if (target.dataset.field === 'sort_order') {
      state.lines[index].sort_order = Number(target.value || 0);
      return;
    }
    if (target.dataset.field === 'notes') {
      state.lines[index].notes = target.value || '';
    }
  });

  lineBody.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-action="remove"]');
    if (!button) {
      return;
    }
    var index = Number(button.dataset.index || -1);
    if (index < 0) {
      return;
    }
    state.lines.splice(index, 1);
    if (!state.lines.length) {
      state.lines.push({ line_type: 'MATERIAL', ingredient_role: 'MAIN', material_item_id: 0, component_id: 0, source_division_id: defaultSourceDivisionId || 0, qty: 1, sort_order: 10, notes: '' });
    }
    render();
  });

  btnAddLine.addEventListener('click', function () {
    state.lines.push({ line_type: 'MATERIAL', ingredient_role: 'MAIN', material_item_id: 0, component_id: 0, source_division_id: defaultSourceDivisionId || 0, qty: 1, sort_order: (state.lines.length + 1) * 10, notes: '' });
    render();
  });

  if (lineSearch) {
    lineSearch.addEventListener('input', render);
  }
  if (lineTypeFilter) {
    lineTypeFilter.addEventListener('change', render);
  }

  form.addEventListener('submit', function (event) {
    var cleaned = state.lines.filter(function (line) {
      if ((line.line_type || 'MATERIAL') === 'COMPONENT') {
        return Number(line.component_id || 0) > 0 && Number(line.qty || 0) > 0;
      }
      return Number(line.material_item_id || 0) > 0 && Number(line.qty || 0) > 0;
    }).map(function (line) {
      return {
        line_type: (line.line_type || 'MATERIAL') === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
        ingredient_role: String(line.ingredient_role || 'MAIN').toUpperCase(),
        material_item_id: Number(line.material_item_id || 0),
        component_id: Number(line.component_id || 0),
        source_division_id: Number(line.source_division_id || suggestedSourceDivisionId(line) || 0),
        qty: Number(line.qty || 0),
        sort_order: Number(line.sort_order || 0),
        notes: String(line.notes || '')
      };
    });

    if (!cleaned.length) {
      event.preventDefault();
      window.alert('Minimal harus ada 1 line resep produk yang valid.');
      return;
    }

    linesInput.value = JSON.stringify(cleaned);
  });

  render();
})();
</script>
