<?php
$preselected   = is_array($preselected ?? null) ? $preselected : null;
$preselectedId = (int)($preselected_id ?? 0);
?>
<script src="<?php echo base_url('assets/libs/apex-charts/apexcharts.js'); ?>"></script>

<style>
  /* ── Layout ─────────────────────────── */
  .iph-card { border:0; border-radius:18px; box-shadow:0 6px 20px rgba(58,38,30,.07); }
  /* ── Item search ─────────────────────── */
  .iph-search-wrap { position:relative; }
  .iph-search-results {
    display:none; position:absolute; top:calc(100% + .3rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201,183,168,.72); border-radius:14px;
    box-shadow:0 16px 36px rgba(61,38,27,.14); overflow:hidden; max-height:280px; overflow-y:auto;
  }
  .iph-search-item { padding:.7rem .9rem; cursor:pointer; border-bottom:1px solid rgba(232,220,210,.9); }
  .iph-search-item:last-child { border-bottom:0; }
  .iph-search-item:hover, .iph-search-item.is-active { background:#fff7f2; }
  .iph-search-name { font-weight:700; color:#382a2b; font-size:.9rem; }
  .iph-search-meta { font-size:.75rem; color:#8a776d; }
  /* ── Preview card ────────────────────── */
  .iph-preview { border:1px solid rgba(224,209,198,.6); border-radius:14px; background:#fffdfb; padding:.8rem 1rem; display:none; }
  .iph-preview-name { font-size:1rem; font-weight:800; color:#2d1f1c; }
  .iph-preview-meta { font-size:.8rem; color:#7a6a60; }
  .iph-preview-badge { background:#e8f3ef; color:#1a6450; border-radius:999px; font-size:.72rem; font-weight:700; padding:.1rem .5rem; }
  /* ── Spinner / empty ─────────────────── */
  .iph-spinner { display:none; text-align:center; padding:2rem; color:#7a6a60; }
  .iph-empty   { display:none; text-align:center; padding:2rem; border:1px dashed #d9c9bc; border-radius:16px; color:#7a6a60; }
  /* ── Table ───────────────────────────── */
  .iph-table th { font-size:.76rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
  .iph-table td { font-size:.86rem; vertical-align:middle; }
  .iph-div-badge { background:#e8f3ef; color:#1a6450; border-radius:999px; font-size:.72rem; font-weight:700; padding:.1rem .4rem; }
</style>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'price-history']); ?>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri ri-line-chart-line me-1 text-primary"></i>Riwayat Harga Item</h4>
    <p class="fin-page-subtitle mb-0">Tren harga beli &amp; HPP/satuan isi per item dari data purchase receipt</p>
  </div>
</div>

<!-- Filter Card -->
<div class="card iph-card mb-3">
  <div class="card-body py-3">
    <div class="row g-3 align-items-start">

      <!-- Item search -->
      <div class="col-md-5">
        <label class="form-label small mb-1 fw-semibold">Cari Item <span class="text-danger">*</span></label>
        <div class="iph-search-wrap">
          <input type="text" id="iph-item-input" class="form-control"
                 placeholder="Ketik nama item atau bahan baku..." autocomplete="off">
          <div class="iph-search-results" id="iph-search-results"></div>
          <input type="hidden" id="iph-item-id" value="<?php echo $preselectedId; ?>">
        </div>
        <!-- Preview item terpilih -->
        <div class="iph-preview mt-2" id="iph-preview"></div>
      </div>

      <!-- N transaksi -->
      <div class="col-md-2">
        <label class="form-label small mb-1">N Terakhir</label>
        <select id="iph-limit" class="form-select">
          <?php foreach ([10, 20, 30, 50, 100] as $l): ?>
            <option value="<?php echo $l; ?>" <?php echo $l === 20 ? 'selected' : ''; ?>><?php echo $l; ?> order</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Mode -->
      <div class="col-md-3">
        <label class="form-label small mb-1">Tampilkan</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="iph_mode" id="iph_hpp" value="hpp" checked>
          <label class="btn btn-outline-primary btn-sm" for="iph_hpp">HPP / Isi</label>
          <input type="radio" class="btn-check" name="iph_mode" id="iph_buy" value="buy">
          <label class="btn btn-outline-primary btn-sm" for="iph_buy">Harga / Pack</label>
        </div>
      </div>

      <!-- Load button -->
      <div class="col-md-2 d-flex align-items-end">
        <button type="button" id="iph-load-btn" class="btn btn-primary w-100" disabled>
          <i class="ri ri-refresh-line me-1"></i>Tampilkan
        </button>
      </div>
    </div>
  </div>
</div>

<div class="iph-spinner" id="iph-spinner">
  <span class="spinner-border text-secondary"></span>
  <div class="mt-2 small">Memuat data...</div>
</div>
<div class="iph-empty" id="iph-empty">
  <i class="ri ri-inbox-line ri-2x d-block mb-2"></i>Belum ada data purchase untuk item ini.
</div>

<!-- Chart -->
<div class="card iph-card mb-3" id="iph-chart-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold" id="iph-chart-title">Tren Harga</span>
    <small class="text-muted" id="iph-chart-meta"></small>
  </div>
  <div class="card-body py-3">
    <div id="iph-chart-container"></div>
  </div>
</div>

<!-- Table -->
<div class="card iph-card" id="iph-table-card" style="display:none">
  <div class="card-header py-2 d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Detail Transaksi</span>
    <small class="text-muted" id="iph-table-meta"></small>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0 iph-table">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Tanggal</th><th>Nama / Brand</th><th>Divisi</th>
          <th class="text-end">Qty Isi</th><th class="text-end">Qty Pack</th>
          <th class="text-end">HPP / Isi</th><th class="text-end">Harga / Pack</th>
        </tr>
      </thead>
      <tbody id="iph-table-body"></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';
  let chart = null;
  let searchTimer = null;
  let selectedItem = <?php echo $preselected ? json_encode($preselected) : 'null'; ?>;

  /* ── helpers ──────────────────────────────────────────────── */
  function money(v) { return 'Rp ' + Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function num(v, d) { return Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:d??2,maximumFractionDigits:d??2}); }
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  function getMode() { return document.querySelector('input[name="iph_mode"]:checked')?.value || 'hpp'; }

  /* ── DOM refs ─────────────────────────────────────────────── */
  const itemInput    = document.getElementById('iph-item-input');
  const itemIdInput  = document.getElementById('iph-item-id');
  const searchResults = document.getElementById('iph-search-results');
  const preview      = document.getElementById('iph-preview');
  const loadBtn      = document.getElementById('iph-load-btn');
  const spinner      = document.getElementById('iph-spinner');
  const emptyBox     = document.getElementById('iph-empty');
  const chartCard    = document.getElementById('iph-chart-card');
  const tableCard    = document.getElementById('iph-table-card');
  const tableBody    = document.getElementById('iph-table-body');
  const chartMeta    = document.getElementById('iph-chart-meta');
  const tableMeta    = document.getElementById('iph-table-meta');
  const chartTitle   = document.getElementById('iph-chart-title');

  /* ── Item preview ─────────────────────────────────────────── */
  function showItemPreview(item) {
    if (!item) { preview.style.display = 'none'; return; }
    const matText = item.material_name ? `<span class="iph-preview-badge me-1">${esc(item.material_name)}</span>` : '';
    const catText = item.category_name ? esc(item.category_name) : '';
    const uomText = [item.buy_uom, item.content_uom].filter(Boolean).join(' / ');
    preview.innerHTML = `
      <div class="iph-preview-name">${esc(item.item_name)}</div>
      <div class="iph-preview-meta mt-1">
        ${matText}${catText ? `<span class="text-muted">${catText}</span>` : ''}
        ${uomText ? `<span class="text-muted ms-2">${esc(uomText)}</span>` : ''}
      </div>`;
    preview.style.display = 'block';
  }

  /* ── Item search ──────────────────────────────────────────── */
  function selectItem(item) {
    selectedItem = item;
    itemInput.value  = item.item_name;
    itemIdInput.value = item.id;
    searchResults.style.display = 'none';
    searchResults.innerHTML = '';
    showItemPreview(item);
    loadBtn.disabled = false;
    history.replaceState({}, '', BASE + 'purchase/item-price-history/' + item.id);
    loadData();
  }

  function clearSelection() {
    selectedItem = null;
    itemIdInput.value = '0';
    loadBtn.disabled = true;
    preview.style.display = 'none';
    chartCard.style.display = 'none';
    tableCard.style.display = 'none';
    emptyBox.style.display  = 'none';
  }

  async function runSearch(q) {
    if (q.length < 2) { searchResults.style.display = 'none'; return; }
    searchResults.innerHTML = '<div class="p-3 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Mencari...</div>';
    searchResults.style.display = 'block';
    try {
      const res  = await fetch(BASE + 'purchase/item-price-history/item-search?q=' + encodeURIComponent(q), {
        headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch(pe) {
        searchResults.innerHTML = '<div class="p-3 text-danger small">Response error. Cek permission.</div>';
        return;
      }
      const rows = Array.isArray(json.rows) ? json.rows : [];
      if (!rows.length) {
        searchResults.innerHTML = '<div class="p-3 text-muted small">Tidak ada item cocok untuk "<em>' + esc(q) + '</em>".</div>';
        return;
      }
      searchResults.innerHTML = rows.map(function(r, idx) {
        const meta = [r.material_name, r.category_name, r.content_uom].filter(Boolean).join(' · ');
        return `<div class="iph-search-item" data-idx="${idx}">
          <div class="iph-search-name">${esc(r.item_name)}</div>
          ${meta ? `<div class="iph-search-meta">${esc(meta)}</div>` : ''}
        </div>`;
      }).join('');
      searchResults.querySelectorAll('.iph-search-item').forEach(function(el) {
        el.addEventListener('mousedown', function(ev) {
          ev.preventDefault(); // prevent blur before click registers
          selectItem(rows[Number(el.dataset.idx)]);
        });
      });
    } catch(e) {
      searchResults.innerHTML = '<div class="p-3 text-danger small">' + esc(e.message || 'Gagal mencari') + '</div>';
    }
  }

  itemInput.addEventListener('input', function() {
    if (!this.value.trim()) { clearSelection(); searchResults.style.display = 'none'; return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { runSearch(itemInput.value.trim()); }, 260);
  });

  document.addEventListener('click', function(e) {
    if (!searchResults.contains(e.target) && e.target !== itemInput) {
      searchResults.style.display = 'none';
    }
  });

  /* ── Chart render ─────────────────────────────────────────── */
  function renderChart(rows, mode) {
    const reversed = [...rows].reverse();
    const dates  = reversed.map(r => r.movement_date);
    const values = reversed.map(r => parseFloat(mode === 'hpp' ? r.unit_cost : r.price_per_buy) || 0);
    const label  = mode === 'hpp' ? 'HPP / Satuan Isi' : 'Harga Beli / Pack';

    const container = document.getElementById('iph-chart-container');
    container.innerHTML = '';
    if (chart) { try { chart.destroy(); } catch(e){} chart = null; }
    if (!window.ApexCharts || !values.length) return;

    chart = new ApexCharts(container, {
      chart: { type: 'area', height: 280, toolbar: {show:false}, zoom: {enabled:false} },
      series: [{ name: label, data: values }],
      xaxis: { categories: dates, tickAmount: Math.min(dates.length, 10), labels: { rotate: -35, style:{fontSize:'11px'} } },
      yaxis: { labels: { formatter: v => money(v) } },
      dataLabels: { enabled: values.length <= 15 },
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom:.35, opacityTo:.05 } },
      colors: ['#1a6450'],
      tooltip: { y: { formatter: v => money(v) } },
      markers: { size: values.length <= 40 ? 4 : 0, strokeWidth: 0 },
      grid: { borderColor: '#f0ebe6', strokeDashArray: 3 },
    });
    chart.render();
  }

  /* ── Table render ─────────────────────────────────────────── */
  function renderTable(rows, total) {
    tableMeta.textContent = rows.length + ' dari ' + total + ' total';
    if (!rows.length) { tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada data.</td></tr>'; return; }
    tableBody.innerHTML = rows.map(function(r, i) {
      const brand = r.brand && r.brand !== r.item_name ? r.brand : '';
      return `<tr>
        <td class="text-muted small">${i+1}</td>
        <td class="small">${esc(r.movement_date||'-')}</td>
        <td>
          <div class="fw-semibold">${esc(r.item_name||'-')}</div>
          ${brand ? `<div class="small text-muted">${esc(brand)}</div>` : ''}
        </td>
        <td>${r.division_name ? `<span class="iph-div-badge">${esc(r.division_name)}</span>` : '<span class="text-muted">-</span>'}</td>
        <td class="text-end">${num(r.qty_content_delta)} <span class="text-muted small">${esc(r.content_uom||'')}</span></td>
        <td class="text-end">${parseFloat(r.qty_buy_delta||0)>0 ? num(r.qty_buy_delta)+' <span class="text-muted small">pack</span>' : '<span class="text-muted">-</span>'}</td>
        <td class="text-end fw-semibold">${money(r.unit_cost)}</td>
        <td class="text-end">${parseFloat(r.price_per_buy||0)>0 ? money(r.price_per_buy) : '<span class="text-muted">-</span>'}</td>
      </tr>`;
    }).join('');
  }

  /* ── Load data ────────────────────────────────────────────── */
  async function loadData() {
    const itemId = Number(itemIdInput.value || 0);
    if (itemId <= 0) return;

    spinner.style.display   = 'block';
    chartCard.style.display = 'none';
    tableCard.style.display = 'none';
    emptyBox.style.display  = 'none';

    try {
      const limit = Number(document.getElementById('iph-limit').value || 20);
      const mode  = getMode();
      const res  = await fetch(BASE + 'purchase/item-price-history/data?item_id=' + itemId + '&limit=' + limit + '&mode=' + mode, {
        headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
      });
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch(pe) { throw new Error('Response bukan JSON. Cek permission/route.'); }
      if (!json.ok) throw new Error(json.message || 'Gagal memuat data');

      const rows  = json.rows  || [];
      const total = json.meta?.total || 0;
      const modeLabel = mode === 'hpp' ? 'HPP / Satuan Isi' : 'Harga Beli / Pack';
      chartTitle.textContent = (selectedItem?.item_name || 'Item') + ' — ' + modeLabel;
      chartMeta.textContent  = rows.length + ' dari ' + total + ' total transaksi';

      if (!rows.length) { emptyBox.style.display = 'block'; return; }

      chartCard.style.display = 'block';
      tableCard.style.display = 'block';
      renderChart(rows, mode);
      renderTable(rows, total);
    } catch(e) {
      emptyBox.style.display = 'block';
      emptyBox.innerHTML = `<i class="ri ri-error-warning-line ri-2x d-block mb-2"></i>${esc(e.message||String(e))}`;
    } finally {
      spinner.style.display = 'none';
    }
  }

  loadBtn.addEventListener('click', loadData);
  document.querySelectorAll('input[name="iph_mode"]').forEach(el => el.addEventListener('change', () => { if (Number(itemIdInput.value) > 0) loadData(); }));
  document.getElementById('iph-limit').addEventListener('change', () => { if (Number(itemIdInput.value) > 0) loadData(); });

  /* ── Pre-populate if item already selected ─────────────────── */
  <?php if ($preselected): ?>
  itemInput.value = '<?php echo html_escape($preselected['item_name']); ?>';
  loadBtn.disabled = false;
  showItemPreview(selectedItem);
  loadData();
  <?php endif; ?>
})();
</script>
