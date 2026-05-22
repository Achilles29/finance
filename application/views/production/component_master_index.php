<?php
$filters = is_array($filters ?? null) ? $filters : [];
$categories = is_array($categories ?? null) ? $categories : [];
$uoms = is_array($uoms ?? null) ? $uoms : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$productDivisions = is_array($product_divisions ?? null) ? $product_divisions : [];
?>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Master Base/Prepare</h4>
      <p class="fin-page-subtitle mb-0">Kelola komponen base/prepare, validasi kategori, dan akses cepat ke formula.</p>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Data Component</h5>
        <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Component</button>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-2" id="status-tabs">
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary tab-status" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3" id="type-tabs">
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="ALL">Semua Tipe</button>
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="BASE">Base</button>
        <button class="btn btn-sm btn-outline-secondary tab-type" data-type="PREPARE">Prepare</button>
      </div>

      <form id="filter-form" class="row g-2 mb-3">
        <div class="col-md-3">
          <select class="form-select" id="division_id">
            <option value="0">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)$d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select class="form-select" id="category_id">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>"><?php echo html_escape((string)$c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <input id="q" class="form-control" placeholder="Cari kode/nama (ajax search)">
        </div>
        <div class="col-md-1">
          <select class="form-select" id="limit">
            <option value="25">25</option>
            <option value="50" selected>50</option>
            <option value="100">100</option>
            <option value="200">200</option>
          </select>
        </div>
        <div class="col-md-1 d-grid">
          <button type="button" id="btn-clear-filter" class="btn btn-outline-danger">Clear Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama</th>
              <th>Tipe</th>
              <th>Divisi</th>
              <th>Kategori</th>
              <th>UOM</th>
              <th class="text-end">HPP Standar</th>
              <th class="text-end">HPP Live</th>
              <th>Resep</th>
              <th>Status</th>
              <th style="width:260px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Data tidak ditemukan.</div>

      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="componentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="componentModalLabel">Tambah Component</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-2">
          <input type="hidden" name="id" value="">
          <div class="col-md-3"><input class="form-control" name="component_code" placeholder="Kode"></div>
          <div class="col-md-5"><input class="form-control" name="component_name" placeholder="Nama" required></div>
          <div class="col-md-4">
            <select class="form-select" name="component_type" id="component_type">
              <option value="BASE">BASE</option>
              <option value="PREPARE">PREPARE</option>
            </select>
          </div>
          <div class="col-md-6">
            <select class="form-select" name="component_category_id" id="component_category_id" required>
              <option value="">Kategori</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" data-scope="<?php echo html_escape((string)($c['scope_type'] ?? 'ALL')); ?>">
                  <?php echo html_escape((string)$c['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <select class="form-select" name="uom_id" required>
              <option value="">UOM</option>
              <?php foreach ($uoms as $u): ?>
                <option value="<?php echo (int)$u['id']; ?>"><?php echo html_escape((string)$u['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <select class="form-select" name="operational_division_id">
              <option value="">Divisi Operasional</option>
              <?php foreach ($divisions as $d): ?>
                <option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)$d['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <select class="form-select" name="product_division_id">
              <option value="">Divisi Produk</option>
              <?php foreach ($productDivisions as $d): ?>
                <option value="<?php echo (int)$d['id']; ?>"><?php echo html_escape((string)$d['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3"><label class="form-label mb-1 small text-muted">HPP Standar</label><input class="form-control" name="hpp_standard" value="0" placeholder="0.000000"></div>
          <div class="col-md-3"><label class="form-label mb-1 small text-muted">Yield %</label><input class="form-control" name="yield_percent" value="100" placeholder="100"></div>
          <div class="col-md-3"><label class="form-label mb-1 small text-muted">Std Batch Qty</label><input class="form-control" name="std_batch_qty" value="1" placeholder="1"></div>
          <div class="col-md-3"><label class="form-label mb-1 small text-muted">Process Loss %</label><input class="form-control" name="process_loss_percent" value="0" placeholder="0"></div>
          <div class="col-md-3"><label class="form-label mb-1 small text-muted">Shelf Life (hari)</label><input class="form-control" name="shelf_life_days" value="0" placeholder="0"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btn-save">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const state = {
  q: initialFilters.q || '',
  status: initialFilters.status || 'ACTIVE',
  type: initialFilters.type || 'ALL',
  division_id: parseInt(initialFilters.division_id || 0, 10),
  category_id: parseInt(initialFilters.category_id || 0, 10),
  page: parseInt(initialFilters.page || 1, 10),
  limit: parseInt(initialFilters.limit || 50, 10) || 50
};

const tableBody = document.getElementById('table-body');
const emptyState = document.getElementById('empty-state');
const paginationInfo = document.getElementById('pagination-info');
const pagination = document.getElementById('pagination');
const modalEl = document.getElementById('componentModal');
const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
const form = document.getElementById('form-save');
const modalTitle = document.getElementById('componentModalLabel');
const limitEl = document.getElementById('limit');

function escapeHtml(v) {
  return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function qsFromState() {
  const p = new URLSearchParams();
  p.set('q', state.q);
  p.set('status', state.status);
  p.set('type', state.type);
  p.set('division_id', String(state.division_id || 0));
  p.set('category_id', String(state.category_id || 0));
  p.set('page', String(state.page || 1));
  p.set('limit', String(state.limit || 50));
  return p.toString();
}

function syncControls() {
  document.getElementById('q').value = state.q;
  document.getElementById('division_id').value = String(state.division_id || 0);
  document.getElementById('category_id').value = String(state.category_id || 0);
  limitEl.value = String(state.limit || 50);
  document.querySelectorAll('.tab-status').forEach((b) => b.classList.toggle('active', b.dataset.status === state.status));
  document.querySelectorAll('.tab-type').forEach((b) => b.classList.toggle('active', b.dataset.type === state.type));
}

async function getJson(url) {
  const r = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
  const t = await r.text();
  let j = null;
  try { j = JSON.parse(t); } catch (e) { throw new Error('Response bukan JSON. Cek session/permission/error backend.'); }
  if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
  return j;
}

async function postJson(url, payload) {
  const r = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    body: JSON.stringify(payload)
  });
  const t = await r.text();
  let j = null;
  try { j = JSON.parse(t); } catch (e) {
    throw new Error('Response save bukan JSON. Kemungkinan ada warning/error PHP di backend.');
  }
  if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal');
  return j;
}

function applyCategoryTypeFilter(keepCurrentSelection = true) {
  const selectedType = form.querySelector('[name="component_type"]').value || 'BASE';
  const catSelect = form.querySelector('[name="component_category_id"]');
  const selectedValue = catSelect.value;
  Array.from(catSelect.options).forEach((opt) => {
    if (!opt.value) return;
    const scope = (opt.dataset.scope || 'ALL').toUpperCase();
    // legacy-safe: keep currently selected option visible on edit, even if scope mismatch.
    const visible = (scope === 'ALL' || scope === selectedType || (keepCurrentSelection && opt.value === selectedValue));
    opt.hidden = !visible;
  });
  if (!keepCurrentSelection && catSelect.selectedOptions.length && catSelect.selectedOptions[0].hidden) {
    catSelect.value = '';
  }
}

function renderRows(rows) {
  if (!rows.length) {
    tableBody.innerHTML = '';
    emptyState.classList.remove('d-none');
    return;
  }
  emptyState.classList.add('d-none');
  tableBody.innerHTML = rows.map((r) => `
    <tr>
      <td>${escapeHtml(r.component_code)}</td>
      <td>${escapeHtml(r.component_name)}</td>
      <td>${escapeHtml(r.component_type)}</td>
      <td>${escapeHtml(r.division_name || '-')}</td>
      <td>${escapeHtml(r.category_name || '-')}</td>
      <td>${escapeHtml(r.uom_code || '-')}</td>
      <td class="text-end">${Number(r.hpp_standard || 0).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 6})}</td>
      <td class="text-end">${Number(r.hpp_live || 0).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 6})}</td>
      <td>
        ${parseInt(r.formula_count || 0, 10) > 0
          ? `<a href="<?php echo site_url('production/component-formulas/edit'); ?>/${r.id}" class="badge bg-success-subtle text-success-emphasis text-decoration-none">${parseInt(r.formula_count || 0, 10)} line</a>`
          : `<a href="<?php echo site_url('production/component-formulas/edit'); ?>/${r.id}" class="badge bg-secondary-subtle text-secondary-emphasis text-decoration-none">Belum ada</a>`
        }
      </td>
      <td>${parseInt(r.is_active || 0, 10) === 1 ? '<span class="fin-status-badge fin-status-active">AKTIF</span>' : '<span class="fin-status-badge fin-status-inactive">NONAKTIF</span>'}</td>
      <td>
        <button class="btn btn-sm btn-outline-primary js-edit" data-id="${r.id}">Edit</button>
        <a class="btn btn-sm btn-outline-info" href="<?php echo site_url('production/component-formulas/edit'); ?>/${r.id}">Formula</a>
        <button class="btn btn-sm btn-outline-secondary js-toggle" data-id="${r.id}">Toggle</button>
      </td>
    </tr>
  `).join('');
}

function renderPagination(meta) {
  const start = meta.total === 0 ? 0 : ((meta.page - 1) * meta.limit) + 1;
  const end = Math.min(meta.total, meta.page * meta.limit);
  paginationInfo.textContent = `Menampilkan ${start}-${end} dari ${meta.total} data`;

  const btn = (label, page, disabled = false, active = false) => `<button class="btn btn-sm ${active ? 'btn-primary' : 'btn-outline-secondary'}" ${disabled ? 'disabled' : ''} data-page="${page}">${label}</button>`;
  let html = '';
  html += btn('Prev', Math.max(1, meta.page - 1), meta.page <= 1);
  const from = Math.max(1, meta.page - 2);
  const to = Math.min(meta.total_pages, meta.page + 2);
  for (let p = from; p <= to; p++) html += btn(String(p), p, false, p === meta.page);
  html += btn('Next', Math.min(meta.total_pages, meta.page + 1), meta.page >= meta.total_pages);
  pagination.innerHTML = html;
}

async function loadData(pushHistory = true) {
  syncControls();
  if (pushHistory) {
    const newUrl = `${location.pathname}?${qsFromState()}`;
    history.replaceState(null, '', newUrl);
  }
  const json = await getJson(`<?php echo site_url('production/component-masters/data'); ?>?${qsFromState()}`);
  renderRows(json.rows || []);
  renderPagination(json.meta || {total: 0, page: 1, limit: state.limit, total_pages: 1});
}

function openForCreate() {
  form.reset();
  form.querySelector('[name="id"]').value = '';
  form.querySelector('[name="yield_percent"]').value = '100';
  form.querySelector('[name="std_batch_qty"]').value = '1';
  form.querySelector('[name="process_loss_percent"]').value = '0';
  form.querySelector('[name="hpp_standard"]').value = '0';
  form.querySelector('[name="shelf_life_days"]').value = '0';
  modalTitle.textContent = 'Tambah Component';
  applyCategoryTypeFilter(false);
  if (modal) modal.show();
}

async function openForEdit(id) {
  const json = await getJson(`<?php echo site_url('production/component-masters/data'); ?>?${qsFromState()}`);
  const row = (json.rows || []).find((x) => parseInt(x.id, 10) === parseInt(id, 10));
  if (!row) throw new Error('Data tidak ditemukan di halaman saat ini.');
  form.querySelector('[name="id"]').value = row.id || '';
  form.querySelector('[name="component_code"]').value = row.component_code || '';
  form.querySelector('[name="component_name"]').value = row.component_name || '';
  form.querySelector('[name="component_type"]').value = row.component_type || 'BASE';
  applyCategoryTypeFilter(true);
  form.querySelector('[name="component_category_id"]').value = row.component_category_id || '';
  form.querySelector('[name="uom_id"]').value = row.uom_id || '';
  form.querySelector('[name="operational_division_id"]').value = row.operational_division_id || '';
  form.querySelector('[name="product_division_id"]').value = row.product_division_id || '';
  form.querySelector('[name="yield_percent"]').value = row.yield_percent || '100';
  form.querySelector('[name="std_batch_qty"]').value = row.std_batch_qty || '1';
  form.querySelector('[name="process_loss_percent"]').value = row.process_loss_percent || '0';
  form.querySelector('[name="hpp_standard"]').value = row.hpp_standard || '0';
  form.querySelector('[name="shelf_life_days"]').value = row.shelf_life_days || '0';
  modalTitle.textContent = `Edit Component: ${row.component_name || row.component_code || row.id}`;
  if (modal) modal.show();
}

document.getElementById('btn-new').addEventListener('click', openForCreate);
form.querySelector('[name="component_type"]').addEventListener('change', applyCategoryTypeFilter);
document.getElementById('btn-save').addEventListener('click', async () => {
  const payload = Object.fromEntries(new FormData(form).entries());
  try {
    await postJson('<?php echo site_url('production/component-masters/save'); ?>', payload);
    if (modal) modal.hide();
    await loadData(false);
  } catch (e) {
    alert(e.message);
  }
});

document.querySelectorAll('.tab-status').forEach((b) => {
  b.addEventListener('click', async () => {
    state.status = b.dataset.status;
    state.page = 1;
    await loadData();
  });
});
document.querySelectorAll('.tab-type').forEach((b) => {
  b.addEventListener('click', async () => {
    state.type = b.dataset.type;
    state.page = 1;
    await loadData();
  });
});

document.getElementById('division_id').addEventListener('change', async (e) => {
  state.division_id = parseInt(e.target.value || '0', 10);
  state.page = 1;
  await loadData();
});
document.getElementById('category_id').addEventListener('change', async (e) => {
  state.category_id = parseInt(e.target.value || '0', 10);
  state.page = 1;
  await loadData();
});
limitEl.addEventListener('change', async (e) => {
  state.limit = parseInt(e.target.value || '50', 10);
  state.page = 1;
  await loadData();
});

let searchTimer = null;
document.getElementById('q').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    state.q = document.getElementById('q').value.trim();
    state.page = 1;
    await loadData();
  }, 350);
});

document.getElementById('btn-clear-filter').addEventListener('click', async () => {
  state.q = '';
  state.status = 'ACTIVE';
  state.type = 'ALL';
  state.division_id = 0;
  state.category_id = 0;
  state.page = 1;
  state.limit = 50;
  await loadData();
});

pagination.addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-page]');
  if (!btn) return;
  state.page = parseInt(btn.dataset.page || '1', 10);
  await loadData();
});

tableBody.addEventListener('click', async (e) => {
  const editBtn = e.target.closest('.js-edit');
  if (editBtn) {
    try {
      await openForEdit(editBtn.dataset.id);
    } catch (err) {
      alert(err.message);
    }
    return;
  }
  const toggleBtn = e.target.closest('.js-toggle');
  if (toggleBtn) {
    try {
      await postJson(`<?php echo site_url('production/component-masters/toggle'); ?>/${toggleBtn.dataset.id}`, {});
      await loadData(false);
    } catch (err) {
      alert(err.message);
    }
  }
});

loadData(false).catch((e) => alert(e.message));
});
</script>
