<?php
$filters            = is_array($filters ?? null) ? $filters : [];
$stampCampaignOpts  = is_array($stamp_campaign_opts ?? null) ? $stamp_campaign_opts : [];
$voucherCampaignOpts= is_array($voucher_campaign_opts ?? null) ? $voucher_campaign_opts : [];

$COST_LABELS   = ['POINT' => 'Poin', 'STAMP' => 'Stamp', 'BOTH' => 'Poin + Stamp'];
$REWARD_LABELS = [
    'VOUCHER'          => 'Voucher',
    'PRODUCT'          => 'Produk',
    'MERCHANDISE'      => 'Merchandise',
    'DISCOUNT_AMOUNT'  => 'Diskon (Rp)',
    'DISCOUNT_PERCENT' => 'Diskon (%)',
    'FREE_PRODUCT'     => 'Produk Gratis',
    'OTHER'            => 'Reward Lainnya',
];
?>
<style>
/* reuse loyalty promo styles */
.loyalty-shell { background:linear-gradient(180deg,#fffaf5 0%,#fff 100%);border:1px solid #f0dfd2;border-radius:26px;box-shadow:0 18px 48px rgba(126,73,35,.08); }
.loyalty-filter-strip,.loyalty-table-card { border:1px solid #f0dfd2;border-radius:22px;background:#fff;box-shadow:0 14px 36px rgba(126,73,35,.06); }
.loyalty-status-tab.active,.loyalty-status-tab:hover { background:#8f353a;color:#fff;border-color:#8f353a; }
.loyalty-status-tab { border-radius:999px;border:1px solid #dcb7ab;background:#fffaf6;color:#81584d;font-weight:700; }
.loyalty-table thead th { color:#7a6055;font-size:.8rem;text-transform:uppercase;letter-spacing:.03em;border-bottom-color:#eddcd0; }
.loyalty-table tbody td { padding-top:.85rem;padding-bottom:.85rem;border-bottom-color:#f4e8df; }
/* cost / reward badges */
.rr-cost-badge  { display:inline-flex;padding:.18rem .5rem;border-radius:999px;font-size:.65rem;font-weight:800; }
.rr-cost-badge.point   { background:#fff8e0;color:#7a5800; }
.rr-cost-badge.stamp   { background:#e8f0fb;color:#1a4a7a; }
.rr-cost-badge.both    { background:#f0e8fb;color:#5a1a7a; }
.rr-reward-badge { display:inline-flex;padding:.18rem .5rem;border-radius:999px;font-size:.65rem;font-weight:800;background:#e8f7ee;color:#1a5a3a; }
/* form section headings inside modal */
.rr-form-section { font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#7c1f2d;padding:.35rem 0 .15rem;border-bottom:1px solid #f0dfd2;margin-bottom:.5rem; }
/* conditional field rows */
.rr-field-row { display:none; }
.rr-field-row.is-visible { display:flex; }
/* ajax product lookup (reuse loyalty pattern) */
.loyalty-ajax-box { position:relative; }
.loyalty-ajax-result { position:absolute;z-index:20;inset:calc(100% + 6px) 0 auto 0;background:#fff;border:1px solid #ead7c8;border-radius:16px;box-shadow:0 18px 38px rgba(70,44,31,.14);max-height:240px;overflow:auto;display:none; }
.loyalty-ajax-result.is-open { display:block; }
.loyalty-ajax-item { padding:.8rem .9rem;border-bottom:1px solid #f4e7de;cursor:pointer;font-size:.82rem; }
.loyalty-ajax-item:hover { background:#fff7f0; }
.loyalty-ajax-selected { display:none;margin-top:.5rem;padding:.6rem .75rem;border:1px solid #ead7c8;border-radius:12px;background:#fffaf6;font-size:.8rem; }
.loyalty-ajax-selected.is-show { display:block; }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1"><i class="ri ri-settings-3-line me-1"></i>Pengaturan Redeem</h4>
      <p class="fin-page-subtitle mb-0">Tentukan apa yang bisa ditukar dengan poin atau stamp oleh member — katalog ini jadi referensi saat proses redeem di halaman Member.</p>
    </div>
  </div>

  <?php $this->load->view('loyalty/_tabs', ['promo_tab_active' => 'redeem-rule']); ?>

  <div class="loyalty-filter-strip p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h5 class="mb-0">Katalog Reward Redeem</h5>
      <button id="btn-new" type="button" class="btn btn-primary">+ Tambah Rule Redeem</button>
    </div>
    <div class="d-flex gap-2 flex-wrap mb-3" id="status-tabs">
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'active' : ''; ?>" data-status="ACTIVE">Aktif</button>
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? '') === 'INACTIVE' ? 'active' : ''; ?>" data-status="INACTIVE">Nonaktif</button>
      <button class="btn btn-sm loyalty-status-tab <?php echo ($filters['status'] ?? '') === 'ALL' ? 'active' : ''; ?>" data-status="ALL">Semua</button>
    </div>
    <div class="row g-2">
      <div class="col-lg-2">
        <select id="filter-cost-type" class="form-select form-select-sm">
          <option value="ALL">Semua Cara Bayar</option>
          <option value="POINT" <?php echo ($filters['cost_type'] ?? '') === 'POINT' ? 'selected' : ''; ?>>Poin</option>
          <option value="STAMP" <?php echo ($filters['cost_type'] ?? '') === 'STAMP' ? 'selected' : ''; ?>>Stamp</option>
          <option value="BOTH"  <?php echo ($filters['cost_type'] ?? '') === 'BOTH'  ? 'selected' : ''; ?>>Poin + Stamp</option>
        </select>
      </div>
      <div class="col-lg-3">
        <select id="filter-reward-type" class="form-select form-select-sm">
          <option value="ALL">Semua Jenis Reward</option>
          <?php foreach ($REWARD_LABELS as $v => $l): ?>
            <option value="<?php echo $v; ?>" <?php echo ($filters['reward_type'] ?? '') === $v ? 'selected' : ''; ?>><?php echo html_escape($l); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-5">
        <input id="filter-q" class="form-control form-control-sm" placeholder="Cari nama rule, kode, catatan reward…" value="<?php echo html_escape($filters['q'] ?? ''); ?>">
      </div>
      <div class="col-lg-2 d-flex gap-1">
        <button id="btn-search" type="button" class="btn btn-secondary btn-sm flex-fill"><i class="ri ri-search-2-line"></i> Cari</button>
        <button id="btn-reset" type="button" class="btn btn-outline-danger btn-sm">Reset</button>
      </div>
    </div>
  </div>

  <div class="loyalty-table-card p-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle loyalty-table mb-0">
        <thead>
          <tr>
            <th>Nama Rule</th>
            <th style="width:110px">Cara Bayar</th>
            <th style="width:120px">Biaya</th>
            <th style="width:120px">Jenis Reward</th>
            <th>Nilai Reward</th>
            <th style="width:80px" class="text-center">Stok</th>
            <th style="width:90px">Berlaku s/d</th>
            <th style="width:90px">Status</th>
            <th style="width:140px" class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody id="table-body"></tbody>
      </table>
    </div>
    <div id="empty-state" class="text-muted py-3 text-center d-none" style="font-size:.85rem">Belum ada rule redeem yang sesuai filter.</div>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <small id="pagination-info" class="text-muted"></small>
      <div class="d-flex gap-1" id="pagination"></div>
    </div>
  </div>
</div>

<!-- ── Modal Form Rule Redeem ─────────────────────────────── -->
<div class="modal fade finance-ui-modal" id="rrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="rrModalTitle">Tambah Rule Redeem</h5>
          <div class="small text-muted">Tentukan biaya (berapa poin/stamp) dan reward yang didapat member.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rr-id" value="">

        <!-- Identitas -->
        <div class="rr-form-section">Identitas Rule</div>
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Kode Internal</label>
            <input type="text" id="rr-code" class="form-control form-control-sm" placeholder="Otomatis jika kosong">
          </div>
          <div class="col-md-6">
            <label class="form-label form-label-sm mb-1">Nama Rule <span class="text-danger">*</span></label>
            <input type="text" id="rr-name" class="form-control form-control-sm" placeholder="Contoh: Tukar 500 poin dapat Voucher 50rb">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Status</label>
            <div class="form-check form-switch border rounded-3 px-3 py-2 h-100 d-flex align-items-center">
              <input class="form-check-input" type="checkbox" id="rr-active" checked>
              <label class="form-check-label ms-2" for="rr-active">Aktif</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label form-label-sm mb-1">Deskripsi</label>
            <textarea id="rr-desc" class="form-control form-control-sm" rows="2" placeholder="Penjelasan singkat untuk operator, misal: syarat, kondisi, catatan khusus…"></textarea>
          </div>
        </div>

        <!-- Cara Bayar -->
        <div class="rr-form-section">Cara Bayar (Cost)</div>
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Member Bayar Dengan <span class="text-danger">*</span></label>
            <select id="rr-cost-type" class="form-select form-select-sm">
              <option value="POINT">Poin saja</option>
              <option value="STAMP">Stamp saja</option>
              <option value="BOTH">Poin + Stamp</option>
            </select>
          </div>
          <!-- Point fields -->
          <div class="col-md-3 rr-field-row rr-cost-point" id="field-point-cost">
            <label class="form-label form-label-sm mb-1">Poin Dibutuhkan <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
              <input type="number" id="rr-point-cost" class="form-control" min="0" step="0.01" placeholder="Contoh: 100">
              <span class="input-group-text">poin</span>
            </div>
          </div>
          <!-- Stamp fields -->
          <div class="col-md-4 rr-field-row rr-cost-stamp" id="field-stamp-campaign">
            <label class="form-label form-label-sm mb-1">Campaign Stamp <span class="text-danger">*</span></label>
            <select id="rr-stamp-campaign" class="form-select form-select-sm">
              <option value="">— pilih campaign stamp —</option>
              <?php foreach ($stampCampaignOpts as $sc): ?>
                <option value="<?php echo (int)$sc['value']; ?>"><?php echo html_escape($sc['label']); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text" style="font-size:.68rem">Campaign stamp yang berlaku untuk rule ini</div>
          </div>
          <div class="col-md-2 rr-field-row rr-cost-stamp" id="field-stamp-cost">
            <label class="form-label form-label-sm mb-1">Stamp Dibutuhkan <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
              <input type="number" id="rr-stamp-cost" class="form-control" min="0" step="0.0001" placeholder="Contoh: 8">
              <span class="input-group-text">stamp</span>
            </div>
          </div>
        </div>

        <!-- Reward yang didapat -->
        <div class="rr-form-section">Reward yang Didapat Member</div>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label form-label-sm mb-1">Jenis Reward <span class="text-danger">*</span></label>
            <select id="rr-reward-type" class="form-select form-select-sm">
              <option value="DISCOUNT_AMOUNT">Diskon nominal (Rp)</option>
              <option value="DISCOUNT_PERCENT">Diskon persen (%)</option>
              <option value="VOUCHER">Voucher (dari campaign)</option>
              <option value="PRODUCT">Produk / item</option>
              <option value="FREE_PRODUCT">Produk Gratis</option>
              <option value="MERCHANDISE">Merchandise</option>
              <option value="OTHER">Reward lainnya</option>
            </select>
          </div>

          <!-- Diskon Nominal -->
          <div class="col-md-4 rr-field-row rr-reward-discount-amt" id="field-discount-amount">
            <label class="form-label form-label-sm mb-1">Nilai Diskon <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">Rp</span>
              <input type="number" id="rr-discount-amount" class="form-control" min="0" step="1000" placeholder="Contoh: 50000">
            </div>
          </div>

          <!-- Diskon Persen -->
          <div class="col-md-4 rr-field-row rr-reward-discount-pct" id="field-discount-percent">
            <label class="form-label form-label-sm mb-1">Persentase Diskon <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
              <input type="number" id="rr-discount-percent" class="form-control" min="0" max="100" step="0.5" placeholder="Contoh: 10">
              <span class="input-group-text">%</span>
            </div>
          </div>

          <!-- Voucher Campaign -->
          <div class="col-md-6 rr-field-row rr-reward-voucher" id="field-voucher-campaign">
            <label class="form-label form-label-sm mb-1">Campaign Voucher <span class="text-danger">*</span></label>
            <select id="rr-voucher-campaign" class="form-select form-select-sm">
              <option value="">— pilih campaign voucher —</option>
              <?php foreach ($voucherCampaignOpts as $vc): ?>
                <option value="<?php echo (int)$vc['value']; ?>"><?php echo html_escape($vc['label']); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text" style="font-size:.68rem">Sistem akan menerbitkan voucher dari campaign ini ke member</div>
          </div>

          <!-- Produk -->
          <div class="col-md-6 rr-field-row rr-reward-product" id="field-product">
            <label class="form-label form-label-sm mb-1">Produk Reward <span class="text-danger">*</span></label>
            <div class="loyalty-ajax-box" id="product-ajax-box">
              <input type="hidden" id="rr-product-id" value="">
              <input type="text" id="rr-product-q" class="form-control form-control-sm" placeholder="Ketik nama produk…" autocomplete="off">
              <div class="loyalty-ajax-result" id="product-ajax-result"></div>
              <div class="loyalty-ajax-selected" id="product-ajax-selected"></div>
            </div>
          </div>
          <div class="col-md-2 rr-field-row rr-reward-product" id="field-product-qty">
            <label class="form-label form-label-sm mb-1">Jumlah <span class="text-danger">*</span></label>
            <input type="number" id="rr-product-qty" class="form-control form-control-sm" min="0.0001" step="0.0001" placeholder="1">
          </div>

          <!-- Merchandise / Other notes -->
          <div class="col-md-8 rr-field-row rr-reward-notes" id="field-reward-notes">
            <label class="form-label form-label-sm mb-1">Deskripsi Reward <span class="text-danger">*</span></label>
            <input type="text" id="rr-reward-notes" class="form-control form-control-sm" placeholder="Contoh: Tote bag branded, 1 pcs T-shirt ukuran M, Gratis 1 bulan langganan">
          </div>
        </div>

        <!-- Kondisi & Masa Berlaku -->
        <div class="rr-form-section">Kondisi & Masa Berlaku</div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Min. Belanja (opsional)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">Rp</span>
              <input type="number" id="rr-min-spend" class="form-control" min="0" step="1000" placeholder="Kosong = tidak ada syarat">
            </div>
            <div class="form-text" style="font-size:.68rem">Min. nominal transaksi agar member bisa gunakan rule ini</div>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm mb-1">Stok Reward (opsional)</label>
            <input type="number" id="rr-stock-qty" class="form-control form-control-sm" min="0" step="1" placeholder="Kosong = tak terbatas">
            <div class="form-text" style="font-size:.68rem">Misal: 100 buah merchandise tersedia</div>
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Berlaku Mulai</label>
            <input type="date" id="rr-valid-from" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Berlaku Sampai</label>
            <input type="date" id="rr-valid-until" class="form-control form-control-sm">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-save" class="btn btn-primary btn-sm">
          <span class="btn-save-label">Simpan Rule</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const dataUrl      = <?php echo json_encode(site_url('loyalty/redeem-rules/data')); ?>;
  const saveUrl      = <?php echo json_encode(site_url('loyalty/redeem-rules/save')); ?>;
  const toggleBase   = <?php echo json_encode(site_url('loyalty/redeem-rules/toggle')); ?>;
  const deleteBase   = <?php echo json_encode(site_url('loyalty/redeem-rules/delete')); ?>;
  const productSearchUrl = <?php echo json_encode(site_url('loyalty/product-search')); ?>;

  const COST_LABELS   = {POINT:'Poin',STAMP:'Stamp',BOTH:'Poin + Stamp'};
  const REWARD_LABELS = {
    VOUCHER:'Voucher',PRODUCT:'Produk',MERCHANDISE:'Merchandise',
    DISCOUNT_AMOUNT:'Diskon (Rp)',DISCOUNT_PERCENT:'Diskon (%)',
    FREE_PRODUCT:'Produk Gratis',OTHER:'Reward Lainnya'
  };

  const fmtMoney = v => 'Rp ' + Number(v||0).toLocaleString('id-ID');
  const fmtNum   = v => Number(v||0).toLocaleString('id-ID',{maximumFractionDigits:4});
  const fmtDate  = v => v ? new Date(v+'T00:00:00').toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) : '—';
  const esc      = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

  let state = {
    q: <?php echo json_encode($filters['q'] ?? ''); ?>,
    status: <?php echo json_encode($filters['status'] ?? 'ACTIVE'); ?>,
    cost_type: <?php echo json_encode($filters['cost_type'] ?? 'ALL'); ?>,
    reward_type: <?php echo json_encode($filters['reward_type'] ?? 'ALL'); ?>,
    page: 1, limit: 25,
  };

  const tbody    = document.getElementById('table-body');
  const emptyEl  = document.getElementById('empty-state');
  const pgInfo   = document.getElementById('pagination-info');
  const pgEl     = document.getElementById('pagination');
  const modalEl  = document.getElementById('rrModal');
  const modal    = new bootstrap.Modal(modalEl);

  // ── Fetch helpers ──
  async function getJson(url) {
    const r = await fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }
  async function postJson(url, payload) {
    const r = await fetch(url, {method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.message || 'Error');
    return j;
  }

  // ── Reward value renderer ──
  function rewardValueCell(row) {
    const rt = row.reward_type || '';
    if (rt === 'DISCOUNT_AMOUNT') return fmtMoney(row.discount_amount);
    if (rt === 'DISCOUNT_PERCENT') return (row.discount_percent || 0) + '%';
    if (rt === 'VOUCHER') return esc(row.voucher_campaign_name || '—');
    if (rt === 'PRODUCT' || rt === 'FREE_PRODUCT') return fmtNum(row.product_qty||1) + ' unit produk';
    return esc(row.reward_notes || '—');
  }
  function costCell(row) {
    const ct = row.cost_type || 'POINT';
    const parts = [];
    if (ct === 'POINT' || ct === 'BOTH') parts.push(fmtNum(row.point_cost) + ' poin');
    if (ct === 'STAMP' || ct === 'BOTH') {
      const sc = row.stamp_campaign_name ? ' (' + esc(row.stamp_campaign_name) + ')' : '';
      parts.push(fmtNum(row.stamp_cost) + ' stamp' + sc);
    }
    return parts.join('<br>') || '—';
  }

  // ── Render table ──
  function renderRows(rows) {
    if (!rows.length) { tbody.innerHTML=''; emptyEl.classList.remove('d-none'); return; }
    emptyEl.classList.add('d-none');
    tbody.innerHTML = rows.map(r => {
      const ct  = (r.cost_type  || 'POINT').toLowerCase();
      const rt  = (r.reward_type|| '').toLowerCase().replace(/_/g,'-');
      const stock = r.stock_qty != null
        ? `${Number(r.stock_qty).toLocaleString('id-ID')} <small class="text-muted">(sisa)</small>`
        : '<span class="text-muted">∞</span>';
      const statusBadge = Number(r.is_active) === 1
        ? '<span class="badge bg-success-subtle text-success-emphasis">Aktif</span>'
        : '<span class="badge bg-danger-subtle text-danger-emphasis">Nonaktif</span>';
      return `<tr>
        <td>
          <div class="fw-semibold" style="font-size:.82rem">${esc(r.rule_name)}</div>
          <div style="font-size:.68rem;color:#888">${esc(r.rule_code)}</div>
          ${r.description ? `<div style="font-size:.68rem;color:#aaa;margin-top:.1rem">${esc(r.description)}</div>` : ''}
        </td>
        <td><span class="rr-cost-badge ${ct}">${esc(COST_LABELS[r.cost_type]||r.cost_type)}</span></td>
        <td style="font-size:.77rem">${costCell(r)}</td>
        <td><span class="rr-reward-badge">${esc(REWARD_LABELS[r.reward_type]||r.reward_type)}</span></td>
        <td style="font-size:.77rem">${rewardValueCell(r)}</td>
        <td class="text-center" style="font-size:.77rem">${stock}</td>
        <td style="font-size:.75rem">${fmtDate(r.valid_until)}</td>
        <td>${statusBadge}</td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-primary btn-edit" data-row="${esc(JSON.stringify(r))}">Edit</button>
            <button class="btn btn-outline-${Number(r.is_active)===1?'danger':'success'} btn-toggle" data-id="${r.id}">${Number(r.is_active)===1?'Nonaktifkan':'Aktifkan'}</button>
            <button class="btn btn-outline-dark btn-delete" data-id="${r.id}">Hapus</button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  // ── Load data ──
  async function loadData() {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3" style="font-size:.8rem">Memuat…</td></tr>';
    emptyEl.classList.add('d-none');
    try {
      const params = new URLSearchParams({
        q: state.q, status: state.status,
        cost_type: state.cost_type, reward_type: state.reward_type,
        page: state.page, limit: state.limit,
      });
      const data = await getJson(dataUrl + '?' + params);
      renderRows(data.rows || []);
      const meta = data.meta || {};
      const total = meta.total || 0;
      pgInfo.textContent = `${(data.rows||[]).length} dari ${total.toLocaleString('id-ID')} rule · Hal ${meta.page||1}/${meta.total_pages||1}`;
      pgEl.innerHTML = '';
      if ((meta.total_pages||1) > 1) {
        if (meta.page > 1) {
          const b=document.createElement('button'); b.className='btn btn-outline-secondary btn-sm'; b.textContent='‹';
          b.onclick=()=>{state.page--;loadData();};pgEl.appendChild(b);
        }
        if (meta.page < meta.total_pages) {
          const b=document.createElement('button'); b.className='btn btn-outline-secondary btn-sm'; b.textContent='›';
          b.onclick=()=>{state.page++;loadData();};pgEl.appendChild(b);
        }
      }
    } catch(e) {
      tbody.innerHTML=`<tr><td colspan="9" class="text-center text-danger py-3" style="font-size:.8rem">Gagal memuat: ${esc(e.message)}</td></tr>`;
    }
  }

  // ── Filter controls ──
  document.getElementById('btn-search').addEventListener('click', () => {
    state.q           = document.getElementById('filter-q').value.trim();
    state.cost_type   = document.getElementById('filter-cost-type').value;
    state.reward_type = document.getElementById('filter-reward-type').value;
    state.page = 1;
    loadData();
  });
  document.getElementById('btn-reset').addEventListener('click', () => {
    document.getElementById('filter-q').value = '';
    document.getElementById('filter-cost-type').value = 'ALL';
    document.getElementById('filter-reward-type').value = 'ALL';
    state.q=''; state.cost_type='ALL'; state.reward_type='ALL'; state.page=1;
    loadData();
  });
  document.querySelectorAll('.loyalty-status-tab').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.loyalty-status-tab').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      state.status = this.dataset.status; state.page=1; loadData();
    });
  });
  document.getElementById('filter-q').addEventListener('keydown', e => { if(e.key==='Enter'){state.page=1;loadData();} });

  // ── Table action delegation ──
  tbody.addEventListener('click', async function(e) {
    const editBtn   = e.target.closest('.btn-edit');
    const toggleBtn = e.target.closest('.btn-toggle');
    const deleteBtn = e.target.closest('.btn-delete');

    if (editBtn) {
      const row = JSON.parse(decodeURIComponent(editBtn.dataset.row));
      openForm(row);
      return;
    }
    if (toggleBtn) {
      const id = toggleBtn.dataset.id;
      if (!confirm('Ubah status rule ini?')) return;
      try { await postJson(toggleBase + '/' + id, {}); loadData(); }
      catch(e) { alert('Error: '+e.message); }
      return;
    }
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      if (!confirm('Hapus rule redeem ini? Tindakan ini tidak bisa dibatalkan.')) return;
      try { await postJson(deleteBase + '/' + id, {}); loadData(); }
      catch(e) { alert('Gagal menghapus: '+e.message); }
    }
  });

  // ── Conditional field visibility ──
  function updateCostVisibility() {
    const ct = document.getElementById('rr-cost-type').value;
    const showPoint = ct==='POINT'||ct==='BOTH';
    const showStamp = ct==='STAMP'||ct==='BOTH';
    document.querySelectorAll('.rr-cost-point').forEach(el=>el.classList.toggle('is-visible',showPoint));
    document.querySelectorAll('.rr-cost-stamp').forEach(el=>el.classList.toggle('is-visible',showStamp));
  }

  function updateRewardVisibility() {
    const rt = document.getElementById('rr-reward-type').value;
    const showDiscAmt  = rt==='DISCOUNT_AMOUNT';
    const showDiscPct  = rt==='DISCOUNT_PERCENT';
    const showVoucher  = rt==='VOUCHER';
    const showProduct  = rt==='PRODUCT'||rt==='FREE_PRODUCT';
    const showNotes    = rt==='MERCHANDISE'||rt==='OTHER';

    document.querySelectorAll('.rr-reward-discount-amt').forEach(el=>el.classList.toggle('is-visible',showDiscAmt));
    document.querySelectorAll('.rr-reward-discount-pct').forEach(el=>el.classList.toggle('is-visible',showDiscPct));
    document.querySelectorAll('.rr-reward-voucher').forEach(el=>el.classList.toggle('is-visible',showVoucher));
    document.querySelectorAll('.rr-reward-product').forEach(el=>el.classList.toggle('is-visible',showProduct));
    document.querySelectorAll('.rr-reward-notes').forEach(el=>el.classList.toggle('is-visible',showNotes));
  }

  document.getElementById('rr-cost-type').addEventListener('change', updateCostVisibility);
  document.getElementById('rr-reward-type').addEventListener('change', updateRewardVisibility);

  // ── Product AJAX search ──
  let productSearchTimer = null;
  document.getElementById('rr-product-q').addEventListener('input', function() {
    clearTimeout(productSearchTimer);
    const q = this.value.trim();
    const resultEl = document.getElementById('product-ajax-result');
    if (!q) { resultEl.classList.remove('is-open'); resultEl.innerHTML=''; return; }
    productSearchTimer = setTimeout(async () => {
      const data = await (await fetch(productSearchUrl+'?q='+encodeURIComponent(q))).json();
      const rows = (data.rows||[]);
      if (!rows.length) { resultEl.innerHTML='<div class="loyalty-ajax-item" style="color:#999">Produk tidak ditemukan</div>'; resultEl.classList.add('is-open'); return; }
      resultEl.innerHTML = rows.map(r=>`<div class="loyalty-ajax-item" data-id="${r.id}" data-name="${esc(r.product_name)}">
        <strong>${esc(r.product_name)}</strong> <span style="font-size:.75rem;color:#888">${esc(r.product_code||'')}</span>
      </div>`).join('');
      resultEl.classList.add('is-open');
    }, 280);
  });
  document.getElementById('product-ajax-result').addEventListener('click', function(e) {
    const item = e.target.closest('.loyalty-ajax-item[data-id]');
    if (!item) return;
    document.getElementById('rr-product-id').value = item.dataset.id;
    document.getElementById('rr-product-q').value  = item.dataset.name || '';
    document.getElementById('product-ajax-selected').textContent = item.dataset.name || '';
    document.getElementById('product-ajax-selected').classList.add('is-show');
    this.classList.remove('is-open'); this.innerHTML='';
  });
  document.addEventListener('click', e => {
    if (!document.getElementById('product-ajax-box').contains(e.target)) {
      document.getElementById('product-ajax-result').classList.remove('is-open');
    }
  });

  // ── Open form ──
  function resetForm() {
    document.getElementById('rr-id').value='';
    document.getElementById('rr-code').value='';
    document.getElementById('rr-name').value='';
    document.getElementById('rr-desc').value='';
    document.getElementById('rr-active').checked=true;
    document.getElementById('rr-cost-type').value='POINT';
    document.getElementById('rr-point-cost').value='';
    document.getElementById('rr-stamp-campaign').value='';
    document.getElementById('rr-stamp-cost').value='';
    document.getElementById('rr-reward-type').value='DISCOUNT_AMOUNT';
    document.getElementById('rr-discount-amount').value='';
    document.getElementById('rr-discount-percent').value='';
    document.getElementById('rr-voucher-campaign').value='';
    document.getElementById('rr-product-id').value='';
    document.getElementById('rr-product-q').value='';
    document.getElementById('product-ajax-selected').classList.remove('is-show');
    document.getElementById('product-ajax-selected').textContent='';
    document.getElementById('rr-product-qty').value='';
    document.getElementById('rr-reward-notes').value='';
    document.getElementById('rr-min-spend').value='';
    document.getElementById('rr-stock-qty').value='';
    document.getElementById('rr-valid-from').value='';
    document.getElementById('rr-valid-until').value='';
    updateCostVisibility();
    updateRewardVisibility();
  }

  function openForm(row) {
    resetForm();
    document.getElementById('rrModalTitle').textContent = row ? 'Edit Rule Redeem' : 'Tambah Rule Redeem';
    if (row) {
      document.getElementById('rr-id').value             = row.id || '';
      document.getElementById('rr-code').value           = row.rule_code || '';
      document.getElementById('rr-name').value           = row.rule_name || '';
      document.getElementById('rr-desc').value           = row.description || '';
      document.getElementById('rr-active').checked       = Number(row.is_active) === 1;
      document.getElementById('rr-cost-type').value      = row.cost_type || 'POINT';
      document.getElementById('rr-point-cost').value     = row.point_cost || '';
      document.getElementById('rr-stamp-campaign').value = row.stamp_campaign_id || '';
      document.getElementById('rr-stamp-cost').value     = row.stamp_cost || '';
      document.getElementById('rr-reward-type').value    = row.reward_type || 'DISCOUNT_AMOUNT';
      document.getElementById('rr-discount-amount').value= row.discount_amount || '';
      document.getElementById('rr-discount-percent').value= row.discount_percent || '';
      document.getElementById('rr-voucher-campaign').value= row.voucher_campaign_id || '';
      if (row.product_id) {
        document.getElementById('rr-product-id').value  = row.product_id;
        document.getElementById('rr-product-q').value   = '(produk ID ' + row.product_id + ')';
        document.getElementById('product-ajax-selected').textContent = 'Produk ID: ' + row.product_id;
        document.getElementById('product-ajax-selected').classList.add('is-show');
      }
      document.getElementById('rr-product-qty').value  = row.product_qty || '';
      document.getElementById('rr-reward-notes').value = row.reward_notes || '';
      document.getElementById('rr-min-spend').value    = row.min_spend_amount || '';
      document.getElementById('rr-stock-qty').value    = row.stock_qty || '';
      document.getElementById('rr-valid-from').value   = row.valid_from  || '';
      document.getElementById('rr-valid-until').value  = row.valid_until || '';
      updateCostVisibility();
      updateRewardVisibility();
    }
    modal.show();
  }

  document.getElementById('btn-new').addEventListener('click', () => openForm(null));

  // ── Save ──
  document.getElementById('btn-save').addEventListener('click', async function() {
    const name = document.getElementById('rr-name').value.trim();
    if (!name) { alert('Nama rule wajib diisi.'); return; }

    const payload = {
      id:                 parseInt(document.getElementById('rr-id').value) || 0,
      rule_code:          document.getElementById('rr-code').value.trim(),
      rule_name:          name,
      description:        document.getElementById('rr-desc').value.trim(),
      is_active:          document.getElementById('rr-active').checked ? 1 : 0,
      cost_type:          document.getElementById('rr-cost-type').value,
      point_cost:         parseFloat(document.getElementById('rr-point-cost').value) || null,
      stamp_campaign_id:  parseInt(document.getElementById('rr-stamp-campaign').value) || null,
      stamp_cost:         parseFloat(document.getElementById('rr-stamp-cost').value) || null,
      reward_type:        document.getElementById('rr-reward-type').value,
      discount_amount:    parseFloat(document.getElementById('rr-discount-amount').value) || null,
      discount_percent:   parseFloat(document.getElementById('rr-discount-percent').value) || null,
      voucher_campaign_id:parseInt(document.getElementById('rr-voucher-campaign').value) || null,
      product_id:         parseInt(document.getElementById('rr-product-id').value) || null,
      product_qty:        parseFloat(document.getElementById('rr-product-qty').value) || null,
      reward_notes:       document.getElementById('rr-reward-notes').value.trim(),
      min_spend_amount:   parseFloat(document.getElementById('rr-min-spend').value) || null,
      stock_qty:          parseInt(document.getElementById('rr-stock-qty').value) || null,
      valid_from:         document.getElementById('rr-valid-from').value || null,
      valid_until:        document.getElementById('rr-valid-until').value || null,
    };

    const btn = this; btn.disabled=true;
    const lbl = btn.querySelector('.btn-save-label');
    lbl.textContent='Menyimpan…';
    try {
      await postJson(saveUrl, payload);
      modal.hide();
      loadData();
    } catch(e) {
      alert('Gagal menyimpan: '+e.message);
    } finally {
      btn.disabled=false; lbl.textContent='Simpan Rule';
    }
  });

  // ── Init ──
  loadData();
});
</script>
