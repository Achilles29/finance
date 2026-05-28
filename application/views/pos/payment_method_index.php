<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$accounts = is_array($filterOptions['accounts'] ?? null) ? $filterOptions['accounts'] : [];
?>
<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Payment Method POS</h4>
      <p class="fin-page-subtitle mb-0">Atur metode pembayaran kasir, mapping rekening perusahaan, dan visibilitas tombol kasir.</p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'payment-method']); ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Metode Pembayaran</h5>
        <button id="btn-new" type="button" class="btn btn-primary btn-sm">Tambah Metode</button>
      </div>

      <div class="d-flex gap-2 flex-wrap mb-2">
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ACTIVE">Aktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="INACTIVE">Nonaktif</button>
        <button class="btn btn-sm btn-outline-primary pos-status-tab" data-status="ALL">Semua</button>
      </div>
      <div class="d-flex gap-2 flex-wrap mb-3">
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="ALL">Semua Tipe</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="CASH">Cash</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="BANK">Bank</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="EWALLET">E-Wallet</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="QRIS">QRIS</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="COMPLIMENT">Compliment</button>
        <button class="btn btn-sm btn-outline-secondary pos-type-tab" data-type="DEPOSIT">Deposit</button>
      </div>

      <form class="row g-2 mb-3">
        <div class="col-md-10">
          <input id="q" class="form-control" placeholder="Cari kode / nama metode / rekening">
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
              <th>Rekening</th>
              <th class="text-center">Kembalian</th>
              <th class="text-center">Ref No</th>
              <th class="text-center">Kasir</th>
              <th class="text-end">Urutan</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:132px;">Aksi</th>
            </tr>
          </thead>
          <tbody id="table-body"></tbody>
        </table>
      </div>
      <div id="empty-state" class="text-muted py-3 d-none">Metode pembayaran tidak ditemukan.</div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <small id="pagination-info" class="text-muted"></small>
        <div class="d-flex gap-1" id="pagination"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="paymentMethodModalLabel">Tambah Payment Method</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-save" class="row g-2">
          <input type="hidden" name="id" value="">
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Kode</label>
            <input class="form-control" name="method_code" placeholder="Otomatis saat simpan" readonly>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Nama Metode</label>
            <input class="form-control" name="method_name" required>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1 small text-muted">Tipe</label>
            <select class="form-select" name="method_type">
              <option value="CASH">CASH</option>
              <option value="BANK">BANK</option>
              <option value="EWALLET">EWALLET</option>
              <option value="QRIS">QRIS</option>
              <option value="COMPLIMENT">COMPLIMENT</option>
              <option value="DEPOSIT">DEPOSIT</option>
              <option value="OTHER">OTHER</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label mb-1 small text-muted">Company Account</label>
            <select class="form-select" name="company_account_id">
              <option value="">Tanpa Rekening</option>
              <?php foreach ($accounts as $account): ?>
                <option value="<?php echo (int)$account['id']; ?>">
                  <?php echo html_escape((string)($account['account_name'] ?? '-')); ?><?php echo !empty($account['account_code']) ? ' | ' . html_escape((string)$account['account_code']) : ''; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1 small text-muted">Urutan</label>
            <input type="number" class="form-control" name="sort_order" value="0">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="allows_change" name="allows_change" value="1">
              <label class="form-check-label" for="allows_change">Boleh kembalian</label>
            </div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="requires_reference_no" name="requires_reference_no" value="1">
              <label class="form-check-label" for="requires_reference_no">Butuh ref no</label>
            </div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="show_in_cashier" name="show_in_cashier" value="1" checked>
              <label class="form-check-label" for="show_in_cashier">Tampil di kasir</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small text-muted">Catatan</label>
            <textarea class="form-control" rows="2" name="notes"></textarea>
          </div>
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
    method_type: initialFilters.method_type || 'ALL',
    page: parseInt(initialFilters.page || 1, 10),
    limit: parseInt(initialFilters.limit || 50, 10) || 50
  };

  const tableBody = document.getElementById('table-body');
  const emptyState = document.getElementById('empty-state');
  const paginationInfo = document.getElementById('pagination-info');
  const pagination = document.getElementById('pagination');
  const modalEl = document.getElementById('paymentMethodModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('form-save');
  const modalTitle = document.getElementById('paymentMethodModalLabel');

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
  function badge(flag, okLabel, noLabel) {
    return Number(flag || 0) === 1
      ? `<span class="badge bg-success-subtle text-success-emphasis">${okLabel}</span>`
      : `<span class="badge bg-secondary-subtle text-secondary-emphasis">${noLabel}</span>`;
  }
  function statusBadge(flag) {
    return Number(flag || 0) === 1
      ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
      : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
  }
  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q);
    p.set('status', state.status);
    p.set('method_type', state.method_type);
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }
  function syncControls() {
    document.getElementById('q').value = state.q;
    document.getElementById('limit').value = String(state.limit || 50);
    document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.status === state.status));
    document.querySelectorAll('.pos-type-tab').forEach((btn) => btn.classList.toggle('active', btn.dataset.type === state.method_type));
  }
  async function getJson(url) {
    const r = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const t = await r.text();
    const j = JSON.parse(t);
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }
  async function postJson(url, payload) {
    const r = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify(payload)});
    const t = await r.text();
    const j = JSON.parse(t);
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data');
    return j;
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
        <td class="text-nowrap">${escapeHtml(r.method_code || '-')}</td>
        <td>${escapeHtml(r.method_name || '-')}</td>
        <td>${escapeHtml(r.method_type || '-')}</td>
        <td>${escapeHtml(r.account_name || '-')}</td>
        <td class="text-center">${badge(r.allows_change, 'Ya', 'Tidak')}</td>
        <td class="text-center">${badge(r.requires_reference_no, 'Ya', 'Tidak')}</td>
        <td class="text-center">${badge(r.show_in_cashier, 'Tampil', 'Sembunyi')}</td>
        <td class="text-end">${Number(r.sort_order || 0)}</td>
        <td class="text-center">${statusBadge(r.is_active)}</td>
        <td class="text-center">
          <div class="d-inline-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-row='${JSON.stringify(r).replace(/'/g, '&#039;')}'>Edit</button>
            <button type="button" class="btn btn-sm ${Number(r.is_active || 0) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btn-toggle" data-id="${Number(r.id || 0)}">${Number(r.is_active || 0) === 1 ? 'Nonaktifkan' : 'Aktifkan'}</button>
          </div>
        </td>
      </tr>
    `).join('');
  }
  function renderPagination(meta) {
    const total = Number(meta.total || 0);
    const page = Number(meta.page || 1);
    const totalPages = Number(meta.total_pages || 1);
    const limit = Number(meta.limit || state.limit || 50);
    const start = total === 0 ? 0 : ((page - 1) * limit) + 1;
    const end = Math.min(total, page * limit);
    paginationInfo.textContent = total ? `Menampilkan ${start}-${end} dari ${total} metode pembayaran` : 'Belum ada data';
    pagination.innerHTML = Array.from({length: totalPages}, (_, idx) => {
      const p = idx + 1;
      return `<button type="button" class="btn btn-sm ${p === page ? 'btn-dark' : 'btn-outline-secondary'} btn-page" data-page="${p}">${p}</button>`;
    }).join('');
  }
  async function loadRows() {
    syncControls();
    const json = await getJson('<?php echo site_url('pos/payment-methods/data'); ?>?' + qsFromState());
    renderRows(json.rows || []);
    renderPagination(json.meta || {});
    history.replaceState(null, '', '<?php echo site_url('pos/payment-methods'); ?>?' + qsFromState());
  }
  function openNew() {
    form.reset();
    form.elements.id.value = '';
    form.elements.method_code.value = '';
    form.elements.show_in_cashier.checked = true;
    modalTitle.textContent = 'Tambah Payment Method';
    modal && modal.show();
  }
  function openEdit(row) {
    form.reset();
    Object.keys(row || {}).forEach((key) => {
      if (!form.elements[key]) return;
      if (form.elements[key].type === 'checkbox') {
        form.elements[key].checked = Number(row[key] || 0) === 1;
      } else {
        form.elements[key].value = row[key] == null ? '' : row[key];
      }
    });
    modalTitle.textContent = `Edit Payment Method: ${row.method_name || ''}`;
    modal && modal.show();
  }
  let timer = null;
  document.getElementById('q').addEventListener('input', (e) => {
    state.q = e.target.value || '';
    state.page = 1;
    clearTimeout(timer);
    timer = setTimeout(() => loadRows().catch((err) => alert(err.message)), 250);
  });
  document.getElementById('limit').addEventListener('change', (e) => { state.limit = parseInt(e.target.value || '50', 10) || 50; state.page = 1; loadRows().catch((err) => alert(err.message)); });
  document.querySelectorAll('.pos-status-tab').forEach((btn) => btn.addEventListener('click', () => { state.status = btn.dataset.status; state.page = 1; loadRows().catch((err) => alert(err.message)); }));
  document.querySelectorAll('.pos-type-tab').forEach((btn) => btn.addEventListener('click', () => { state.method_type = btn.dataset.type; state.page = 1; loadRows().catch((err) => alert(err.message)); }));
  document.getElementById('btn-clear-filter').addEventListener('click', () => { state.q=''; state.status='ACTIVE'; state.method_type='ALL'; state.page=1; state.limit=50; loadRows().catch((err)=>alert(err.message)); });
  document.getElementById('btn-new').addEventListener('click', openNew);
  pagination.addEventListener('click', (e) => { const btn = e.target.closest('.btn-page'); if (!btn) return; state.page = parseInt(btn.dataset.page || '1', 10) || 1; loadRows().catch((err)=>alert(err.message)); });
  tableBody.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.btn-edit');
    if (editBtn) { openEdit(JSON.parse(editBtn.dataset.row)); return; }
    const toggleBtn = e.target.closest('.btn-toggle');
    if (!toggleBtn) return;
    if (!confirm('Ubah status payment method ini?')) return;
    try { await postJson(`<?php echo site_url('pos/payment-methods/toggle'); ?>/${toggleBtn.dataset.id}`, {}); await loadRows(); } catch (err) { alert(err.message); }
  });
  document.getElementById('btn-save').addEventListener('click', async () => {
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    payload.allows_change = form.elements.allows_change.checked ? 1 : 0;
    payload.requires_reference_no = form.elements.requires_reference_no.checked ? 1 : 0;
    payload.show_in_cashier = form.elements.show_in_cashier.checked ? 1 : 0;
    try { await postJson('<?php echo site_url('pos/payment-methods/save'); ?>', payload); modal && modal.hide(); await loadRows(); } catch (err) { alert(err.message); }
  });
  loadRows().catch((err) => alert(err.message));
});
</script>
