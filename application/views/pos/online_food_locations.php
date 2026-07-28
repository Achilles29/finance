<?php
$filters = is_array($filters ?? null) ? $filters : [];
?>

<style>
  .online-location-shell { display:grid; gap:1rem; }
  .online-location-card { border:0; border-radius:18px; box-shadow:0 18px 40px rgba(58,38,30,.08); }
  .online-location-filter {
    border:1px solid rgba(224,209,198,.72);
    border-radius:16px;
    padding:1rem;
    background:#fffdfb;
  }
  .online-location-pill-row { display:flex; flex-wrap:wrap; gap:.55rem; }
  .online-location-pill {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:.45rem .92rem;
    border-radius:999px;
    border:1px solid #d6e0dc;
    background:#eef3f1;
    color:#34534f;
    font-size:.86rem;
    font-weight:700;
    cursor:pointer;
  }
  .online-location-pill.is-active {
    background:#1f5d54;
    border-color:#1f5d54;
    color:#fff;
    box-shadow:0 12px 24px rgba(31,93,84,.16);
  }
  .online-location-section-label {
    font-size:.72rem;
    font-weight:800;
    letter-spacing:.04em;
    text-transform:uppercase;
    color:#8a776d;
    margin-bottom:.5rem;
  }
  .online-location-summary {
    display:grid;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:.75rem;
  }
  .online-location-summary-item {
    border:1px solid rgba(224,209,198,.72);
    border-radius:14px;
    padding:.8rem .9rem;
    background:#fffaf6;
  }
  .online-location-summary-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#8a776d; margin-bottom:.12rem; }
  .online-location-summary-value { font-size:1.25rem; font-weight:900; color:#36292a; }
  .online-location-empty {
    border:1px dashed rgba(189,170,154,.6);
    border-radius:16px;
    padding:1.4rem;
    text-align:center;
    color:#8b7a70;
    background:#fffaf6;
  }
  .online-location-member { font-weight:800; color:#33272a; }
  .online-location-muted { font-size:.78rem; color:#8b7a70; }
  .online-location-address { max-width:380px; white-space:normal; }
  .online-location-coord { font-size:.78rem; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color:#5d514b; }
  .online-location-badge {
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.25rem .58rem;
    border-radius:999px;
    font-size:.72rem;
    font-weight:800;
    white-space:nowrap;
  }
  .online-location-badge.free { background:#dcfce7; color:#166534; }
  .online-location-badge.normal { background:#f1f5f9; color:#475569; }
  .online-location-modal-note {
    border:1px solid rgba(224,209,198,.7);
    border-radius:14px;
    padding:.75rem .85rem;
    background:#fff7f2;
    color:#755f56;
  }
  .online-location-toast-wrap {
    position:fixed;
    top:88px;
    right:24px;
    z-index:2000;
    display:grid;
    gap:.55rem;
    width:min(340px, calc(100vw - 32px));
  }
  .online-location-toast {
    border-radius:16px;
    padding:.8rem .95rem;
    box-shadow:0 16px 30px rgba(61,38,27,.16);
    color:#fff;
    font-size:.86rem;
    font-weight:600;
  }
  .online-location-toast.success { background:#176b3a; }
  .online-location-toast.warning { background:#9a4e0f; }
  @media (max-width: 767.98px) {
    .online-location-summary { grid-template-columns:1fr; }
    .online-location-address { max-width:none; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'online-food']); ?>
  <?php $this->load->view('pos/_online_food_tabs', ['online_food_tab_active' => 'locations']); ?>

  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Alamat Online Food</h4>
      <p class="fin-page-subtitle mb-0">Kelola alamat tersimpan customer dan tandai lokasi yang mendapat gratis ongkir khusus.</p>
    </div>
  </div>

  <div class="online-location-shell">
    <div class="card online-location-card">
      <div class="card-body p-4">
        <div class="online-location-filter mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-7">
              <label class="form-label small text-muted mb-1">Cari alamat</label>
              <input type="text" class="form-control" id="online_location_q" placeholder="Nama member, nomor HP, label alamat, penerima, atau alamat">
            </div>
            <div class="col-md-3 col-lg-2">
              <label class="form-label small text-muted mb-1">Baris</label>
              <select class="form-select" id="online_location_limit">
                <?php foreach ([10, 25, 50, 100, 200] as $rowLimit): ?>
                  <option value="<?php echo $rowLimit; ?>" <?php echo (int)($filters['limit'] ?? 50) === $rowLimit ? 'selected' : ''; ?>><?php echo $rowLimit; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 col-lg-2 d-grid">
              <button type="button" class="btn btn-outline-danger" id="online_location_reset_filter">Reset</button>
            </div>
          </div>

          <div class="mt-3">
            <div class="online-location-section-label">Status gratis ongkir</div>
            <div class="online-location-pill-row">
              <button type="button" class="online-location-pill" data-free-status="ALL">Semua</button>
              <button type="button" class="online-location-pill" data-free-status="FREE">Gratis Ongkir</button>
              <button type="button" class="online-location-pill" data-free-status="NORMAL">Normal</button>
            </div>
          </div>
        </div>

        <div class="online-location-summary mb-3" id="online_location_summary"></div>

        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead>
              <tr>
                <th>Member</th>
                <th>Label</th>
                <th>Penerima</th>
                <th>Alamat</th>
                <th>Koordinat</th>
                <th class="text-center">Gratis Ongkir</th>
                <th>Terakhir Dipakai</th>
                <th class="text-center" style="width:132px;">Aksi</th>
              </tr>
            </thead>
            <tbody id="online_location_body"></tbody>
          </table>
        </div>
        <div id="online_location_empty_state" class="online-location-empty d-none">Belum ada alamat customer pada filter ini.</div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <small id="online_location_pagination_info" class="text-muted"></small>
          <div class="d-flex gap-1" id="online_location_pagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="online-location-toast-wrap" id="online_location_toast_wrap"></div>

<div class="modal fade" id="onlineLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:18px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Pengaturan Gratis Ongkir</h5>
          <div class="small text-muted" id="online_location_modal_meta">Alamat belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="online_location_form" class="row g-3">
          <input type="hidden" name="id" value="">
          <div class="col-12">
            <div class="online-location-modal-note" id="online_location_modal_address">-</div>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="free_delivery_enabled" name="free_delivery_enabled" value="1">
              <label class="form-check-label fw-semibold" for="free_delivery_enabled">Aktifkan gratis ongkir khusus untuk alamat ini</label>
            </div>
            <div class="small text-muted mt-1">Dipakai untuk pelanggan langganan, area dekat yang diset manual, atau kompensasi khusus. Nilai ongkir tetap tercatat sebagai delivery terpisah dari sales POS.</div>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Alasan / catatan internal</label>
            <input type="text" class="form-control" name="free_delivery_reason" maxlength="120" placeholder="Contoh: Langganan, area dekat, kompensasi">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="online_location_save">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const state = {
    q: initialFilters.q || '',
    free_status: initialFilters.free_status || 'ALL',
    page: parseInt(initialFilters.page || 1, 10) || 1,
    limit: parseInt(initialFilters.limit || 50, 10) || 50
  };

  const dataUrl = '<?php echo site_url('pos/online-food/locations/data'); ?>';
  const saveUrl = '<?php echo site_url('pos/online-food/locations/save'); ?>';
  const body = document.getElementById('online_location_body');
  const emptyState = document.getElementById('online_location_empty_state');
  const paginationInfo = document.getElementById('online_location_pagination_info');
  const pagination = document.getElementById('online_location_pagination');
  const summary = document.getElementById('online_location_summary');
  const toastWrap = document.getElementById('online_location_toast_wrap');
  const modalEl = document.getElementById('onlineLocationModal');
  const modal = (window.bootstrap && window.bootstrap.Modal) ? new window.bootstrap.Modal(modalEl) : null;
  const form = document.getElementById('online_location_form');
  const modalMeta = document.getElementById('online_location_modal_meta');
  const modalAddress = document.getElementById('online_location_modal_address');
  const rowMap = {};

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
    });
  }

  function debounce(fn, delay) {
    let timer = null;
    return function () {
      const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, delay);
    };
  }

  function showToast(message, type) {
    const el = document.createElement('div');
    el.className = 'online-location-toast ' + (type || 'success');
    el.textContent = message;
    toastWrap.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(function () { el.remove(); }, 250);
    }, 2600);
  }

  function qsFromState() {
    const p = new URLSearchParams();
    p.set('q', state.q || '');
    p.set('free_status', state.free_status || 'ALL');
    p.set('page', String(state.page || 1));
    p.set('limit', String(state.limit || 50));
    return p.toString();
  }

  async function getJson(url) {
    const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response bukan JSON. Cek session atau error PHP.'); }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal memuat data alamat.');
    }
    return json;
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(payload)
    });
    const text = await response.text();
    let json = null;
    try { json = JSON.parse(text); } catch (e) { throw new Error('Response simpan bukan JSON. Cek session atau error PHP.'); }
    if (!response.ok || !json.ok) {
      throw new Error(json.message || 'Gagal menyimpan data alamat.');
    }
    return json;
  }

  function formatDate(value) {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('id-ID', {dateStyle:'medium', timeStyle:'short'});
  }

  function syncControls() {
    document.getElementById('online_location_q').value = state.q || '';
    document.getElementById('online_location_limit').value = String(state.limit || 50);
    document.querySelectorAll('.online-location-pill').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.freeStatus === state.free_status);
    });
  }

  function badge(row) {
    if (Number(row.free_delivery_enabled || 0) === 1) {
      const reason = row.free_delivery_reason ? '<div class="online-location-muted mt-1">' + escapeHtml(row.free_delivery_reason) + '</div>' : '';
      return '<span class="online-location-badge free">Gratis</span>' + reason;
    }
    return '<span class="online-location-badge normal">Normal</span>';
  }

  function renderSummary(rows, paginationData) {
    let freeCount = 0;
    rows.forEach(function (row) {
      if (Number(row.free_delivery_enabled || 0) === 1) freeCount += 1;
    });
    const totalRows = paginationData ? Number(paginationData.total_rows || 0) : rows.length;
    summary.innerHTML = [
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Total Filter</div><div class="online-location-summary-value">' + totalRows + '</div></div>',
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Gratis di Halaman Ini</div><div class="online-location-summary-value">' + freeCount + '</div></div>',
      '<div class="online-location-summary-item"><div class="online-location-summary-label">Mode Ongkir</div><div class="online-location-summary-value" style="font-size:1rem;">Terpisah POS</div></div>'
    ].join('');
  }

  function renderRows(rows) {
    Object.keys(rowMap).forEach(function (key) { delete rowMap[key]; });
    if (!rows.length) {
      body.innerHTML = '';
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    body.innerHTML = rows.map(function (row) {
      rowMap[String(row.id)] = row;
      const memberName = row.member_name || '-';
      const memberMeta = [row.member_no || '', row.mobile_phone || ''].filter(Boolean).join(' | ');
      const recipient = [row.recipient_name || '', row.recipient_phone || ''].filter(Boolean).join(' | ') || '-';
      const addressNote = row.address_note ? '<div class="online-location-muted mt-1">' + escapeHtml(row.address_note) + '</div>' : '';
      const defaultBadge = Number(row.is_default || 0) === 1 ? '<span class="badge bg-primary-subtle text-primary-emphasis ms-1">Default</span>' : '';
      return '' +
        '<tr>' +
          '<td><div class="online-location-member">' + escapeHtml(memberName) + '</div><div class="online-location-muted">' + escapeHtml(memberMeta || '-') + '</div></td>' +
          '<td><strong>' + escapeHtml(row.label || '-') + '</strong>' + defaultBadge + '</td>' +
          '<td>' + escapeHtml(recipient) + '</td>' +
          '<td class="online-location-address">' + escapeHtml(row.address || '-') + addressNote + '</td>' +
          '<td class="online-location-coord">' + escapeHtml(row.latitude || '-') + '<br>' + escapeHtml(row.longitude || '-') + '</td>' +
          '<td class="text-center">' + badge(row) + '</td>' +
          '<td class="text-nowrap">' + escapeHtml(formatDate(row.last_used_at)) + '</td>' +
          '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary online-location-edit" data-id="' + escapeHtml(row.id) + '">Atur</button></td>' +
        '</tr>';
    }).join('');
  }

  function renderPagination(meta) {
    const page = Number(meta && meta.page ? meta.page : 1);
    const totalPages = Number(meta && meta.total_pages ? meta.total_pages : 1);
    const totalRows = Number(meta && meta.total_rows ? meta.total_rows : 0);
    const limit = Number(meta && meta.limit ? meta.limit : state.limit);
    const from = totalRows > 0 ? ((page - 1) * limit) + 1 : 0;
    const to = Math.min(page * limit, totalRows);
    paginationInfo.textContent = totalRows ? ('Menampilkan ' + from + '-' + to + ' dari ' + totalRows + ' alamat') : 'Tidak ada data';
    pagination.innerHTML = '';
    const pages = [];
    for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p += 1) {
      pages.push(p);
    }
    if (page > 1) pages.unshift(page - 1);
    if (page < totalPages) pages.push(page + 1);
    Array.from(new Set(pages)).sort(function (a, b) { return a - b; }).forEach(function (p) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary');
      btn.textContent = String(p);
      btn.addEventListener('click', function () {
        state.page = p;
        loadRows();
      });
      pagination.appendChild(btn);
    });
  }

  async function loadRows() {
    syncControls();
    try {
      const json = await getJson(dataUrl + '?' + qsFromState());
      const data = json.data || {};
      const rows = data.rows || [];
      const meta = data.pagination || {};
      state.page = Number(meta.page || state.page || 1);
      renderSummary(rows, meta);
      renderRows(rows);
      renderPagination(meta);
    } catch (error) {
      showToast(error.message || 'Gagal memuat data alamat.', 'warning');
      renderSummary([], {total_rows: 0});
      renderRows([]);
      renderPagination({page:1, total_pages:1, total_rows:0, limit:state.limit});
    }
  }

  function openModal(row) {
    form.reset();
    form.elements.id.value = row.id || '';
    form.elements.free_delivery_reason.value = row.free_delivery_reason || '';
    form.elements.free_delivery_enabled.checked = Number(row.free_delivery_enabled || 0) === 1;
    modalMeta.textContent = (row.member_name || '-') + ' | ' + (row.mobile_phone || row.member_no || '-');
    modalAddress.innerHTML = '<strong>' + escapeHtml(row.label || '-') + '</strong><br>' +
      escapeHtml(row.address || '-') +
      (row.address_note ? '<br><span class="small">' + escapeHtml(row.address_note) + '</span>' : '');
    if (modal) modal.show();
  }

  document.getElementById('online_location_q').addEventListener('input', debounce(function (event) {
    state.q = event.target.value;
    state.page = 1;
    loadRows();
  }, 320));

  document.getElementById('online_location_limit').addEventListener('change', function (event) {
    state.limit = parseInt(event.target.value || '50', 10) || 50;
    state.page = 1;
    loadRows();
  });

  document.querySelectorAll('.online-location-pill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      state.free_status = btn.dataset.freeStatus || 'ALL';
      state.page = 1;
      loadRows();
    });
  });

  document.getElementById('online_location_reset_filter').addEventListener('click', function () {
    state.q = '';
    state.free_status = 'ALL';
    state.page = 1;
    state.limit = 50;
    loadRows();
  });

  body.addEventListener('click', function (event) {
    const btn = event.target.closest('.online-location-edit');
    if (!btn) return;
    const row = rowMap[String(btn.dataset.id || '')];
    if (row) openModal(row);
  });

  document.getElementById('online_location_save').addEventListener('click', async function () {
    const payload = {
      id: parseInt(form.elements.id.value || '0', 10) || 0,
      free_delivery_enabled: form.elements.free_delivery_enabled.checked ? 1 : 0,
      free_delivery_reason: form.elements.free_delivery_reason.value || ''
    };
    try {
      await postJson(saveUrl, payload);
      if (modal) modal.hide();
      showToast('Pengaturan gratis ongkir alamat berhasil disimpan.', 'success');
      loadRows();
    } catch (error) {
      showToast(error.message || 'Gagal menyimpan data alamat.', 'warning');
    }
  });

  loadRows();
});
</script>
