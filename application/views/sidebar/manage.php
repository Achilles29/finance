<?php
$saveUrl = site_url('sidebar/manage/save');
$manageBase = site_url('sidebar/manage');
$storeUrl = site_url('sidebar/manage/menu/store');
$type = (string)($sidebar_type_selected ?? 'MAIN');
$summary = $favorite_summary ?? ['total_rows' => 0, 'active_users' => 0, 'top_menus' => [], 'top_users' => []];
$tree = $sidebar_tree_raw ?? [];
$treeEditor = $sidebar_tree_preview ?? [];
$flatMenus = $sidebar_flat_raw ?? [];
$editMenu = $edit_menu ?? null;
$parentCandidates = $parent_candidates ?? [];
$isEdit = !empty($editMenu);
$submitUrl = $isEdit ? site_url('sidebar/manage/menu/update/' . (int)$editMenu['id']) : $storeUrl;
$activeTab = (string)($this->input->get('tab', true) ?? '');
if ($activeTab !== 'menu-data' && $activeTab !== 'structure') {
  $activeTab = $isEdit ? 'menu-data' : 'structure';
}
$savedStructure = ((int)$this->input->get('saved', true) === 1);
?>

<?php if ($savedStructure): ?>
  <div class="alert alert-success py-2 mb-3" role="alert">
    Struktur sidebar berhasil disimpan.
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-node-tree page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Atur urutan menu, naik-turun, dan parent submenu dengan drag & drop. Simpan untuk menerapkan.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo $manageBase . '?type=MAIN'; ?>" class="btn <?php echo $type === 'MAIN' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">Sidebar MAIN</a>
    <a href="<?php echo $manageBase . '?type=MY'; ?>" class="btn <?php echo $type === 'MY' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">Sidebar MY</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3 col-6">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block">Total Pin</small>
        <div class="fs-4 fw-bold"><?php echo (int)$summary['total_rows']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block">User Aktif Pin</small>
        <div class="fs-4 fw-bold"><?php echo (int)$summary['active_users']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-12">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block mb-2">Top Menu Dipin</small>
        <?php if (empty($summary['top_menus'])): ?>
          <div class="text-muted small">Belum ada data.</div>
        <?php else: ?>
          <?php foreach ($summary['top_menus'] as $m): ?>
            <div class="small d-flex justify-content-between"><span><?php echo html_escape((string)$m['menu_label']); ?></span><strong><?php echo (int)$m['total_pin']; ?></strong></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-12">
    <div class="card h-100">
      <div class="card-body py-3">
        <small class="text-muted d-block mb-2">Top User Pin</small>
        <?php if (empty($summary['top_users'])): ?>
          <div class="text-muted small">Belum ada data.</div>
        <?php else: ?>
          <?php foreach ($summary['top_users'] as $u): ?>
            <div class="small d-flex justify-content-between"><span><?php echo html_escape((string)($u['username'] ?? 'Unknown')); ?></span><strong><?php echo (int)$u['total_pin']; ?></strong></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab === 'structure' ? 'active' : ''; ?>" id="tab-structure-btn" data-bs-toggle="tab" data-bs-target="#tab-structure" type="button" role="tab" aria-controls="tab-structure" aria-selected="<?php echo $activeTab === 'structure' ? 'true' : 'false'; ?>">Struktur Sidebar <?php echo html_escape($type); ?></button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab === 'menu-data' ? 'active' : ''; ?>" id="tab-menu-data-btn" data-bs-toggle="tab" data-bs-target="#tab-menu-data" type="button" role="tab" aria-controls="tab-menu-data" aria-selected="<?php echo $activeTab === 'menu-data' ? 'true' : 'false'; ?>">Data Menu (CRUD)</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade <?php echo $activeTab === 'structure' ? 'show active' : ''; ?>" id="tab-structure" role="tabpanel" aria-labelledby="tab-structure-btn" tabindex="0">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <strong>Struktur Aktual Sidebar <?php echo html_escape($type); ?></strong>
          <div class="text-muted small">Drag-drop langsung di sini untuk atur urutan/parent menu.</div>
        </div>
        <div class="d-flex gap-2">
          <button type="button" id="btn_expand_all_sidebar" class="btn btn-outline-secondary btn-sm">Expand All</button>
          <button type="button" id="btn_collapse_all_sidebar" class="btn btn-outline-secondary btn-sm">Collapse All</button>
          <button type="button" id="btn_save_sidebar_structure" class="btn btn-primary btn-sm" data-loading-label="Menyimpan...">Simpan Struktur</button>
        </div>
      </div>
      <div class="card-body">
        <div id="sidebar-tree-root"></div>
        <div id="sidebar-save-alert" class="mt-3"></div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade <?php echo $activeTab === 'menu-data' ? 'show active' : ''; ?>" id="tab-menu-data" role="tabpanel" aria-labelledby="tab-menu-data-btn" tabindex="0">
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header">
            <strong><?php echo $isEdit ? 'Edit Menu Sidebar' : 'Tambah Menu Sidebar'; ?></strong>
          </div>
          <div class="card-body">
            <form method="post" action="<?php echo $submitUrl; ?>" class="row g-2">
              <input type="hidden" name="sidebar_type" value="<?php echo html_escape($type); ?>">
              <div class="col-12">
                <label class="form-label mb-1">Kode Menu</label>
                <input type="text" class="form-control" name="menu_code" required value="<?php echo html_escape((string)($editMenu['menu_code'] ?? '')); ?>">
              </div>
              <div class="col-12">
                <label class="form-label mb-1">Nama Menu</label>
                <input type="text" class="form-control" name="menu_label" required value="<?php echo html_escape((string)($editMenu['menu_label'] ?? '')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">Icon (Remix class)</label>
                <input type="text" class="form-control" name="icon" placeholder="ri-settings-3-line" value="<?php echo html_escape((string)($editMenu['icon'] ?? 'ri-circle-line')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">URL</label>
                <input type="text" class="form-control" name="url" placeholder="/module/page" value="<?php echo html_escape((string)($editMenu['url'] ?? '')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">Parent</label>
                <select name="parent_id" class="form-select">
                  <option value="0">(Root)</option>
                  <?php foreach ($parentCandidates as $p): ?>
                    <option value="<?php echo (int)$p['id']; ?>" <?php echo ((int)($editMenu['parent_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>>
                      <?php echo html_escape((string)$p['menu_label'] . ' (' . $p['menu_code'] . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">Sort Order</label>
                <input type="number" min="1" class="form-control" name="sort_order" value="<?php echo (int)($editMenu['sort_order'] ?? 999); ?>">
              </div>
              <?php if ($isEdit): ?>
              <div class="col-12">
                <label class="form-label mb-1 d-block">Status</label>
                <label class="form-check form-switch">
                  <input type="checkbox" class="form-check-input" name="is_active" <?php echo !isset($editMenu['is_active']) || (int)$editMenu['is_active'] === 1 ? 'checked' : ''; ?>>
                  <span class="form-check-label">Aktif</span>
                </label>
              </div>
              <?php endif; ?>
              <div class="col-12 d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary btn-sm"><?php echo $isEdit ? 'Update Menu' : 'Simpan Menu'; ?></button>
                <?php if ($isEdit): ?>
                  <a href="<?php echo $manageBase . '?type=' . urlencode($type); ?>" class="btn btn-outline-secondary btn-sm">Batal Edit</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">
            <strong>Daftar Menu Sidebar <?php echo html_escape($type); ?></strong>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead>
                <tr>
                  <th width="70">ID</th>
                  <th>Nama</th>
                  <th>Kode</th>
                  <th>Parent</th>
                  <th>Sort</th>
                  <th>Status</th>
                  <th width="120">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($flatMenus)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">Belum ada menu.</td></tr>
                <?php else: ?>
                  <?php foreach ($flatMenus as $m): ?>
                    <tr>
                      <td class="number-cell"><?php echo (int)$m['id']; ?></td>
                      <td class="text-cell"><?php echo html_escape((string)$m['menu_label']); ?></td>
                      <td class="text-cell"><?php echo html_escape((string)$m['menu_code']); ?></td>
                      <td class="number-cell"><?php echo !empty($m['parent_id']) ? (int)$m['parent_id'] : 0; ?></td>
                      <td class="number-cell"><?php echo (int)$m['sort_order']; ?></td>
                      <td><?php echo (int)$m['is_active'] === 1 ? 'Aktif' : 'Nonaktif'; ?></td>
                      <td class="action-cell">
                        <a href="<?php echo $manageBase . '?type=' . urlencode($type) . '&tab=menu-data&edit_id=' . (int)$m['id']; ?>" class="btn btn-sm btn-outline-primary action-icon-btn" title="Edit"><i class="ri ri-pencil-line"></i></a>
                        <form method="post" action="<?php echo site_url('sidebar/manage/menu/delete/' . (int)$m['id']); ?>" class="d-inline" onsubmit="return confirm('Nonaktifkan menu ini?');">
                          <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" title="Delete (Soft)"><i class="ri ri-delete-bin-line"></i></button>
                        </form>
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
</div>

<script>
(function () {
  var rootEl = document.getElementById('sidebar-tree-root');
  var saveBtn = document.getElementById('btn_save_sidebar_structure');
  var expandAllBtn = document.getElementById('btn_expand_all_sidebar');
  var collapseAllBtn = document.getElementById('btn_collapse_all_sidebar');
  var alertBox = document.getElementById('sidebar-save-alert');
  var saveUrl = <?php echo json_encode($saveUrl); ?>;
  var manageBase = <?php echo json_encode($manageBase); ?>;
  var sidebarType = <?php echo json_encode($type); ?>;
  var treeData = <?php echo json_encode($treeEditor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  var dragState = {
    draggedLi: null,
    dropMode: null,
    dropTargetLi: null
  };

  function createNodeLabel(item, depth, hasChild) {
    var isVirtual = Number(item.is_virtual || 0) === 1;
    var isActive = isVirtual ? true : Number(item.is_active || 0) === 1;

    var wrap = document.createElement('div');
    wrap.className = 'sidebar-sort-item d-flex align-items-center justify-content-between gap-2';
    wrap.classList.toggle('is-inactive', !isActive);
    wrap.dataset.depth = String(depth || 0);

    var left = document.createElement('div');
    left.className = 'd-flex align-items-center gap-2';

    var expander = document.createElement('button');
    expander.type = 'button';
    expander.className = 'btn btn-xs btn-outline-secondary sidebar-node-expander';
    expander.innerHTML = '<i class="ri ri-arrow-down-s-line"></i>';
    expander.title = 'Expand / Collapse';
    expander.dataset.collapsed = '0';
    expander.style.visibility = hasChild ? 'visible' : 'hidden';
    left.appendChild(expander);

    var levelBadge = document.createElement('span');
    levelBadge.className = 'badge text-bg-light border';
    levelBadge.style.fontSize = '0.65rem';
    levelBadge.style.minWidth = '36px';
    levelBadge.style.textAlign = 'center';
    if ((depth || 0) <= 0) {
      levelBadge.textContent = 'PARENT';
    } else if ((depth || 0) === 1) {
      levelBadge.textContent = 'CHILD';
    } else {
      levelBadge.textContent = 'CH-2';
    }
    left.appendChild(levelBadge);

    if (!isVirtual) {
      var handle = document.createElement('i');
      handle.className = 'ri ri-drag-move-2-line text-muted sidebar-drag-handle';
      handle.style.cursor = 'grab';
      left.appendChild(handle);
    }

    var icon = document.createElement('i');
    icon.className = 'ri ' + (item.icon || 'ri-circle-line') + ' text-primary';

    var txt = document.createElement('span');
    txt.textContent = item.menu_label + ' (' + item.menu_code + ')';
    txt.style.fontSize = '0.9rem';

    left.appendChild(icon);
    left.appendChild(txt);

    var right = document.createElement('small');
    right.className = 'text-muted';
    right.textContent = (item.url && String(item.url).trim() !== '') ? item.url : '-';

    if (!isActive && !isVirtual) {
      var off = document.createElement('small');
      off.className = 'badge bg-danger text-white ms-2';
      off.textContent = 'Belum tampil';
      left.appendChild(off);
    }

    wrap.appendChild(left);
    wrap.appendChild(right);
    return wrap;
  }

  function buildList(items, depth) {
    var level = Number(depth || 0);
    var ul = document.createElement('ul');
    ul.className = 'list-unstyled sidebar-sortable-list';
    ul.classList.toggle('sidebar-list-root', level <= 0);
    ul.dataset.depth = String(level);

    (items || []).forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'mb-2 sidebar-tree-node';
      li.dataset.id = String(item.id || 0);
      li.dataset.virtual = String(Number(item.is_virtual || 0));
      li.dataset.depth = String(level);
      li.draggable = Number(item.is_virtual || 0) !== 1;
      if (Number(item.is_virtual || 0) === 1) {
        li.classList.add('sidebar-virtual-node');
      }
      var children = Array.isArray(item.children) ? item.children : [];
      li.appendChild(createNodeLabel(item, level, children.length > 0));

      var childList = buildList(children, level + 1);
      li.appendChild(childList);

      ul.appendChild(li);
    });
    return ul;
  }

  function isDescendant(parent, maybeChild) {
    if (!parent || !maybeChild) return false;
    return parent.contains(maybeChild);
  }

  function clearDropMarks() {
    rootEl.querySelectorAll('.drop-before, .drop-after, .drop-inside').forEach(function (el) {
      el.classList.remove('drop-before', 'drop-after', 'drop-inside');
    });
    dragState.dropMode = null;
    dragState.dropTargetLi = null;
  }

  function ensureChildList(li) {
    var ul = li.querySelector(':scope > ul.sidebar-sortable-list');
    if (ul) return ul;
    ul = document.createElement('ul');
    ul.className = 'list-unstyled sidebar-sortable-list';
    ul.dataset.depth = String(Number(li.dataset.depth || 0) + 1);
    li.appendChild(ul);
    return ul;
  }

  function refreshDepth(node, depth) {
    if (!(node instanceof HTMLElement)) return;
    node.dataset.depth = String(depth);
    var label = node.querySelector(':scope > .sidebar-sort-item');
    if (label) {
      label.dataset.depth = String(depth);
      var badge = label.querySelector('.badge.text-bg-light');
      if (badge) {
        if (depth <= 0) badge.textContent = 'PARENT';
        else if (depth === 1) badge.textContent = 'CHILD';
        else badge.textContent = 'CH-2';
      }
    }
    var childUl = node.querySelector(':scope > ul.sidebar-sortable-list');
    if (!childUl) return;
    childUl.dataset.depth = String(depth + 1);
    Array.prototype.slice.call(childUl.children).forEach(function (childLi) {
      refreshDepth(childLi, depth + 1);
    });
  }

  function updateExpanderForNode(li) {
    var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
    if (!btn) return;
    var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
    var hasChild = !!childUl && childUl.children.length > 0;
    btn.style.visibility = hasChild ? 'visible' : 'hidden';
    if (!hasChild) {
      btn.dataset.collapsed = '0';
      btn.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
      li.classList.remove('is-collapsed');
    }
  }

  function updateAllExpanders() {
    rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
      updateExpanderForNode(li);
    });
  }

  function applyDrop(draggedLi, targetLi, mode) {
    if (!draggedLi || !targetLi || !mode) return;
    var originParent = draggedLi.parentElement;
    var targetParent = targetLi.parentElement;
    if (!originParent || !targetParent) return;
    if (draggedLi === targetLi) return;
    if (isDescendant(draggedLi, targetLi)) return;

    if (mode === 'before') {
      targetParent.insertBefore(draggedLi, targetLi);
    } else if (mode === 'after') {
      targetParent.insertBefore(draggedLi, targetLi.nextSibling);
    } else {
      var targetChildUl = ensureChildList(targetLi);
      targetChildUl.appendChild(draggedLi);
      targetLi.classList.remove('is-collapsed');
      var expander = targetLi.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
      if (expander) {
        expander.dataset.collapsed = '0';
        expander.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
      }
    }

    var parentNode = draggedLi.parentElement ? draggedLi.parentElement.closest('li.sidebar-tree-node') : null;
    var parentDepth = parentNode ? Number(parentNode.getAttribute('data-depth') || 0) : -1;
    refreshDepth(draggedLi, parentDepth + 1);
    if (originParent.closest('li.sidebar-tree-node')) {
      updateExpanderForNode(originParent.closest('li.sidebar-tree-node'));
    }
    if (targetLi.closest('li.sidebar-tree-node')) {
      updateExpanderForNode(targetLi.closest('li.sidebar-tree-node'));
    }
    updateAllExpanders();
  }

  function serializeListForSave(ul) {
    var out = [];
    Array.prototype.slice.call(ul.children).forEach(function (li) {
      var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
      var children = childUl ? serializeListForSave(childUl) : [];
      var isVirtual = String(li.dataset.virtual || '0') === '1';

      if (isVirtual) {
        children.forEach(function (c) { out.push(c); });
      } else {
        out.push({
          id: Number(li.dataset.id || 0),
          children: children
        });
      }
    });
    return out;
  }

  function showAlert(type, text) {
    alertBox.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-0">' + text + '</div>';
  }

  if (!Array.isArray(treeData)) {
    showAlert('danger', 'Data sidebar tidak valid.');
    return;
  }

  function renderEditor() {
    rootEl.innerHTML = '';
    var rootList = buildList(treeData, 0);
    rootEl.appendChild(rootList);
    updateAllExpanders();

    rootEl.addEventListener('click', function (event) {
      var expanderBtn = event.target.closest('.sidebar-node-expander');
      if (expanderBtn) {
        var nodeLi = expanderBtn.closest('li.sidebar-tree-node');
        if (!nodeLi) return;
        var childUl = nodeLi.querySelector(':scope > ul.sidebar-sortable-list');
        if (!childUl || childUl.children.length === 0) return;
        var collapsed = expanderBtn.dataset.collapsed === '1';
        expanderBtn.dataset.collapsed = collapsed ? '0' : '1';
        expanderBtn.innerHTML = collapsed
          ? '<i class=\"ri ri-arrow-down-s-line\"></i>'
          : '<i class=\"ri ri-arrow-right-s-line\"></i>';
        nodeLi.classList.toggle('is-collapsed', !collapsed);
        return;
      }
    });

    rootEl.addEventListener('dragstart', function (event) {
      var li = event.target.closest('li.sidebar-tree-node');
      if (!li || li.classList.contains('sidebar-virtual-node')) {
        event.preventDefault();
        return;
      }
      dragState.draggedLi = li;
      li.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', li.dataset.id || '');
    });

    rootEl.addEventListener('dragend', function () {
      if (dragState.draggedLi) {
        dragState.draggedLi.classList.remove('is-dragging');
      }
      dragState.draggedLi = null;
      clearDropMarks();
    });

    rootEl.addEventListener('dragover', function (event) {
      if (!dragState.draggedLi) return;
      var targetLi = event.target.closest('li.sidebar-tree-node');
      if (!targetLi || targetLi.classList.contains('sidebar-virtual-node')) return;
      if (targetLi === dragState.draggedLi) return;
      if (isDescendant(dragState.draggedLi, targetLi)) return;

      event.preventDefault();
      var rect = targetLi.getBoundingClientRect();
      var y = event.clientY - rect.top;
      var ratio = rect.height > 0 ? (y / rect.height) : 0.5;
      var mode = ratio < 0.28 ? 'before' : (ratio > 0.72 ? 'after' : 'inside');

      clearDropMarks();
      targetLi.classList.add(mode === 'before' ? 'drop-before' : (mode === 'after' ? 'drop-after' : 'drop-inside'));
      dragState.dropMode = mode;
      dragState.dropTargetLi = targetLi;
    });

    rootEl.addEventListener('drop', function (event) {
      if (!dragState.draggedLi || !dragState.dropTargetLi || !dragState.dropMode) return;
      event.preventDefault();
      applyDrop(dragState.draggedLi, dragState.dropTargetLi, dragState.dropMode);
      clearDropMarks();
    });

    if (expandAllBtn) {
      expandAllBtn.addEventListener('click', function () {
        rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
          li.classList.remove('is-collapsed');
          var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
          if (btn) {
            btn.dataset.collapsed = '0';
            btn.innerHTML = '<i class=\"ri ri-arrow-down-s-line\"></i>';
          }
        });
      });
    }

    if (collapseAllBtn) {
      collapseAllBtn.addEventListener('click', function () {
        rootEl.querySelectorAll('li.sidebar-tree-node').forEach(function (li) {
          var childUl = li.querySelector(':scope > ul.sidebar-sortable-list');
          if (!childUl || childUl.children.length === 0) return;
          li.classList.add('is-collapsed');
          var btn = li.querySelector(':scope > .sidebar-sort-item .sidebar-node-expander');
          if (btn) {
            btn.dataset.collapsed = '1';
            btn.innerHTML = '<i class=\"ri ri-arrow-right-s-line\"></i>';
          }
        });
      });
    }

    saveBtn.addEventListener('click', function () {
      var payload = serializeListForSave(rootList);
      saveBtn.disabled = true;
      saveBtn.classList.add('is-loading');
      saveBtn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\" role=\"status\" aria-hidden=\"true\"></span>Menyimpan...';

      $.post(saveUrl, {
        sidebar_type: sidebarType,
        tree_json: JSON.stringify(payload)
      }).done(function (resp) {
        if (resp && resp.ok) {
          window.location.href = manageBase + '?type=' + encodeURIComponent(sidebarType) + '&tab=structure&saved=1';
          return;
        } else {
          showAlert('danger', (resp && resp.message) ? resp.message : 'Gagal menyimpan struktur sidebar.');
        }
      }).fail(function () {
        showAlert('danger', 'Gagal menyimpan struktur sidebar.');
      }).always(function () {
        saveBtn.disabled = false;
        saveBtn.classList.remove('is-loading');
        saveBtn.textContent = 'Simpan Struktur';
      });
    });
  }

  renderEditor();
})();
</script>

<style>
  #sidebar-tree-root .sidebar-sortable-list {
    padding-left: 1rem;
    border-left: 1px dashed rgba(84, 59, 59, 0.23);
    min-height: 8px;
  }
  #sidebar-tree-root .sidebar-list-root {
    border-left: none;
    padding-left: 0;
  }
  #sidebar-tree-root .sidebar-sort-item {
    padding: 0.5rem 0.6rem;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 10px;
    background: #fff;
    border-left: 4px solid transparent;
    cursor: grab;
    transition: box-shadow 0.15s ease, transform 0.12s ease;
  }
  #sidebar-tree-root .sidebar-sort-item.is-inactive,
  #sidebar-tree-root .sidebar-sort-item[data-depth].is-inactive {
    background: #fff2f2;
    border-color: rgba(192, 57, 43, 0.45);
    border-left-color: #c0392b;
  }
  #sidebar-tree-root li.is-dragging > .sidebar-sort-item {
    opacity: 0.72;
    transform: scale(0.992);
    box-shadow: 0 10px 20px rgba(29, 12, 12, 0.16);
  }
  #sidebar-tree-root li.drop-before > .sidebar-sort-item {
    box-shadow: inset 0 3px 0 #198754;
  }
  #sidebar-tree-root li.drop-after > .sidebar-sort-item {
    box-shadow: inset 0 -3px 0 #198754;
  }
  #sidebar-tree-root li.drop-inside > .sidebar-sort-item {
    box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.36);
  }
  #sidebar-tree-root .sidebar-drag-handle {
    font-size: 1rem;
    opacity: 0.72;
  }
  #sidebar-tree-root .sidebar-node-expander {
    min-width: 24px;
    width: 24px;
    height: 24px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  #sidebar-tree-root li.is-collapsed > ul.sidebar-sortable-list {
    display: none;
  }
  #sidebar-tree-root .sidebar-sort-item[data-depth=\"0\"] {
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
  }
  #sidebar-tree-root .sidebar-sort-item[data-depth=\"1\"] {
    background: #fafcff;
  }
  #sidebar-tree-root .sidebar-sort-item[data-depth=\"2\"],
  #sidebar-tree-root .sidebar-sort-item[data-depth=\"3\"] {
    background: #fcfcff;
  }
</style>
