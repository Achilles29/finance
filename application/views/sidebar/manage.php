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
        <button type="button" id="btn_save_sidebar_structure" class="btn btn-primary btn-sm">Simpan Struktur</button>
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
  var alertBox = document.getElementById('sidebar-save-alert');
  var saveUrl = <?php echo json_encode($saveUrl); ?>;
  var manageBase = <?php echo json_encode($manageBase); ?>;
  var sidebarType = <?php echo json_encode($type); ?>;
  var treeData = <?php echo json_encode($treeEditor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  function createNodeLabel(item, depth) {
    var wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center justify-content-between gap-2 sidebar-sort-item';
    wrap.style.padding = '0.5rem 0.6rem';
    wrap.style.border = '1px solid rgba(0,0,0,0.08)';
    wrap.style.borderRadius = '8px';
    var isVirtual = Number(item.is_virtual || 0) === 1;
    var isActive = isVirtual ? true : Number(item.is_active || 0) === 1;
    wrap.style.background = isActive ? '#fff' : '#ffe9e9';
    wrap.style.borderColor = isActive ? 'rgba(0,0,0,0.08)' : 'rgba(192, 57, 43, 0.45)';
    wrap.style.borderLeft = isActive ? '4px solid transparent' : '4px solid #c0392b';
    wrap.style.cursor = 'grab';
    wrap.dataset.depth = String(depth || 0);

    var left = document.createElement('div');
    left.className = 'd-flex align-items-center gap-2';

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

    if (Number(item.is_virtual || 0) !== 1) {
      var handle = document.createElement('i');
      handle.className = 'ri ri-drag-move-2-line text-muted';
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
    ul.style.paddingLeft = level <= 0 ? '0.25rem' : '1.1rem';
    ul.style.borderLeft = level > 0 ? '2px dashed rgba(0,0,0,0.12)' : 'none';
    ul.dataset.depth = String(level);

    (items || []).forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'mb-2';
      li.dataset.id = String(item.id || 0);
      li.dataset.virtual = String(Number(item.is_virtual || 0));
      li.dataset.depth = String(level);
      if (Number(item.is_virtual || 0) === 1) {
        li.classList.add('sidebar-virtual-node');
      }
      li.appendChild(createNodeLabel(item, level));

      if (Array.isArray(item.children) && item.children.length) {
        li.appendChild(buildList(item.children, level + 1));
      } else {
        var emptyChild = document.createElement('ul');
        emptyChild.className = 'list-unstyled sidebar-sortable-list';
        emptyChild.style.paddingLeft = '1.1rem';
        emptyChild.style.borderLeft = '2px dashed rgba(0,0,0,0.12)';
        emptyChild.style.minHeight = '8px';
        emptyChild.dataset.depth = String(level + 1);
        li.appendChild(emptyChild);
        initSortable(emptyChild);
      }

      ul.appendChild(li);
    });

    initSortable(ul);
    return ul;
  }

  function initSortable(el) {
    if (typeof Sortable === 'undefined') {
      throw new Error('SortableJS belum tersedia.');
    }
    new Sortable(el, {
      group: 'sidebar-tree',
      animation: 150,
      draggable: 'li:not(.sidebar-virtual-node)',
      fallbackOnBody: true,
      swapThreshold: 0.65,
      invertSwap: true,
      dragoverBubble: true,
      emptyInsertThreshold: 12
    });
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

    saveBtn.addEventListener('click', function () {
      var payload = serializeListForSave(rootList);

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
      });
    });
  }

  function ensureSortable(ready) {
    if (typeof Sortable !== 'undefined') {
      ready();
      return;
    }

    var fallbackUrl = 'https://unpkg.com/sortablejs@1.15.3/Sortable.min.js';
    var s = document.createElement('script');
    s.src = fallbackUrl;
    s.async = true;
    s.onload = function () {
      if (typeof Sortable === 'undefined') {
        showAlert('danger', 'Library drag-drop gagal dimuat. Cek koneksi internet lalu refresh halaman.');
        return;
      }
      ready();
    };
    s.onerror = function () {
      showAlert('danger', 'Library drag-drop gagal dimuat. Cek koneksi internet lalu refresh halaman.');
    };
    document.head.appendChild(s);
  }

  ensureSortable(renderEditor);
})();
</script>

<style>
  #sidebar-tree-root .sidebar-sort-item[data-depth="0"] {
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
  }
  #sidebar-tree-root .sidebar-sort-item[data-depth="1"] {
    background: #fafcff;
  }
  #sidebar-tree-root .sidebar-sort-item[data-depth="2"],
  #sidebar-tree-root .sidebar-sort-item[data-depth="3"] {
    background: #fcfcff;
  }
</style>
