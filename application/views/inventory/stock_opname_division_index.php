<?php
$opnameDate  = (string)($opname_date  ?? date('Y-m-d'));
$divisionId  = (int)($division_id    ?? 0);
$destination = strtoupper((string)($destination ?? 'ALL'));
$divisions   = is_array($divisions ?? null) ? $divisions : [];

$baseUrl        = site_url('inventory/stock/opname/division');
$dataUrl        = site_url('inventory/stock/opname/division/data');
$savePhysUrl    = site_url('inventory/stock/opname/division/save-physical');
$quickAdjUrl    = site_url('inventory/stock/opname/division/quick-adjust');
$adjBaseUrl     = site_url('inventory/stock/adjustment/division');

$destOptions = [
    'ALL'         => 'Semua Tujuan',
    'BAR'         => 'Bar Reguler',
    'KITCHEN'     => 'Kitchen Reguler',
    'BAR_EVENT'   => 'Bar Event',
    'KITCHEN_EVENT'=> 'Kitchen Event',
    'OFFICE'      => 'Office',
    'OTHER'       => 'Other',
];

$divNameCol = null;
if (!empty($divisions[0])) {
    $divNameCol = isset($divisions[0]['division_name']) ? 'division_name'
        : (isset($divisions[0]['name']) ? 'name' : null);
}
$divName = static function (array $div) use ($divNameCol): string {
    return htmlspecialchars((string)($divNameCol ? ($div[$divNameCol] ?? '') : ($div['name'] ?? $div['division_name'] ?? '')));
};
?>

<style>
  .opn-filter-bar  { display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; }
  .opn-filter-item { display:flex; flex-direction:column; gap:.25rem; }
  .opn-filter-item label { font-size:.78rem; font-weight:700; color:#6f2119; text-transform:uppercase; }
  .opn-badge-miss { background:#fff0f0; color:#c62828; border:1px solid rgba(198,40,40,.2); border-radius:999px; padding:.2rem .6rem; font-size:.72rem; font-weight:800; }
  .opn-badge-ok   { background:#f0fff4; color:#2e7d32; border:1px solid rgba(46,125,50,.2); border-radius:999px; padding:.2rem .6rem; font-size:.72rem; font-weight:800; }
  .opn-badge-plus { background:#fff8e1; color:#e65100; border:1px solid rgba(230,81,0,.2); border-radius:999px; padding:.2rem .6rem; font-size:.72rem; font-weight:800; }
  .opn-tbl        { width:100%; border-collapse:collapse; font-size:.87rem; }
  .opn-tbl th     { position:sticky; top:0; background:#fff; z-index:2; padding:.65rem .75rem; text-align:left; font-size:.75rem; font-weight:700; color:#6f2119; text-transform:uppercase; border-bottom:2px solid #f3e3db; white-space:nowrap; }
  .opn-tbl td     { padding:.55rem .75rem; vertical-align:middle; border-bottom:1px solid #f9f0ec; }
  .opn-tbl tr.mat-group { background:#fdf7f4; }
  .opn-tbl tr.mat-group td { font-weight:800; color:#6f2119; padding:.55rem .75rem; border-bottom:1px solid #f3e3db; }
  .opn-tbl tr.mat-group td:first-child { padding-left:.75rem; }
  .opn-tbl tr.profile-row td:first-child { padding-left:2rem; color:#6b4e49; }
  .opn-tbl tr.profile-row:hover { background:#fffaf7; }
  .opn-input-qty  { width:90px; border:1px solid #e0c8bf; border-radius:8px; padding:.35rem .5rem; font-size:.9rem; text-align:right; background:#fff; }
  .opn-input-qty:focus { border-color:#8f2d23; outline:none; box-shadow:0 0 0 3px rgba(143,45,35,.12); }
  .opn-input-qty.changed { border-color:#2e7d32; }
  .opn-selisih    { font-weight:800; text-align:right; }
  .opn-selisih.neg { color:#c62828; }
  .opn-selisih.pos { color:#e65100; }
  .opn-selisih.zero { color:#2e7d32; }
  .opn-selisih.empty { color:#bbb; font-weight:400; font-style:italic; }
  .opn-btn-adj    { padding:.3rem .7rem; border-radius:8px; border:1px solid rgba(143,45,35,.2); background:#fff0ea; color:#8f2d23; font-size:.78rem; font-weight:800; cursor:pointer; white-space:nowrap; }
  .opn-btn-adj:hover { background:#8f2d23; color:#fff; }
  .opn-btn-adj:disabled { opacity:.45; cursor:not-allowed; }
  .opn-btn-save   { padding:.28rem .65rem; border-radius:8px; border:1px solid rgba(46,125,50,.25); background:#f0fff4; color:#2e7d32; font-size:.78rem; font-weight:700; cursor:pointer; }
  .opn-btn-save:hover { background:#2e7d32; color:#fff; }
  .opn-status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:.3rem; }
  .opn-status-dot.adj { background:#2e7d32; }
  .opn-status-dot.pending { background:#e65100; }
  .opn-scroll-wrap { max-height:calc(100vh - 260px); overflow-y:auto; }
  .opn-notes-input { width:130px; font-size:.8rem; border:1px solid #e0c8bf; border-radius:8px; padding:.3rem .45rem; background:#fff; }
  .opn-notes-input:focus { border-color:#8f2d23; outline:none; }
  .opn-summ-bar   { display:flex; gap:1rem; flex-wrap:wrap; align-items:center; font-size:.82rem; color:#8b7772; padding:.6rem 0; border-bottom:1px solid #f3e3db; margin-bottom:.5rem; }
  .opn-summ-val   { font-weight:800; color:#6f2119; }
  @media (max-width:768px) { .opn-tbl th, .opn-tbl td { padding:.4rem .4rem; font-size:.8rem; } }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri-clipboard-check-line page-title-icon"></i><?= html_escape($title) ?></h4>
    <small class="text-muted">Kroscek stok bahan baku divisi · input stok fisik · selisih otomatis · penyesuaian langsung</small>
  </div>
  <a href="<?= $adjBaseUrl ?>" class="btn btn-outline-secondary btn-sm">Ke Halaman Adjustment</a>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <div class="opn-filter-bar">
      <div class="opn-filter-item">
        <label>Tanggal Opname</label>
        <input type="date" id="opnDate" class="form-control form-control-sm" value="<?= htmlspecialchars($opnameDate) ?>" style="width:160px">
      </div>
      <div class="opn-filter-item">
        <label>Divisi</label>
        <select id="opnDivision" class="form-select form-select-sm" style="min-width:180px">
          <option value="0">-- Pilih Divisi --</option>
          <?php foreach ($divisions as $div): ?>
            <option value="<?= (int)($div['id'] ?? 0) ?>" <?= (int)($div['id'] ?? 0) === $divisionId ? 'selected' : '' ?>>
              <?= $divName($div) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="opn-filter-item">
        <label>Tujuan</label>
        <select id="opnDestination" class="form-select form-select-sm" style="min-width:160px">
          <?php foreach ($destOptions as $val => $lbl): ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $val === $destination ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="opn-filter-item">
        <label>Cari Bahan</label>
        <input type="text" id="opnQ" class="form-control form-control-sm" placeholder="nama / kode..." style="width:180px">
      </div>
      <div class="opn-filter-item">
        <label>&nbsp;</label>
        <button class="btn btn-primary btn-sm" id="opnLoad">Muat Data</button>
      </div>
    </div>
  </div>
</div>

<!-- Summary bar -->
<div id="opnSummBar" class="opn-summ-bar d-none">
  <span>Material: <span class="opn-summ-val" id="smTotal">0</span></span>
  <span>Sudah diisi: <span class="opn-summ-val" id="smFilled">0</span></span>
  <span>Mismatch: <span class="opn-summ-val" id="smMiss">0</span></span>
  <span>Sudah disesuaikan: <span class="opn-summ-val" id="smAdj">0</span></span>
  <button class="btn btn-outline-secondary btn-sm ms-auto" id="opnRefresh" title="Refresh data"><i class="ri-refresh-line"></i></button>
</div>

<!-- Table container -->
<div class="card">
  <div class="card-body p-0">
    <div id="opnLoading" class="text-center py-5 text-muted d-none"><i class="ri-loader-4-line ri-spin"></i> Memuat data...</div>
    <div id="opnEmpty" class="text-center py-5 text-muted d-none">Pilih divisi dan klik "Muat Data" untuk memulai.</div>
    <div id="opnNoResult" class="text-center py-5 text-muted d-none">Tidak ada bahan baku ditemukan untuk filter ini.</div>
    <div id="opnEmpty2" class="text-center py-5 text-muted">Pilih divisi dan klik "Muat Data" untuk memulai.</div>
    <div class="opn-scroll-wrap" id="opnScrollWrap" style="display:none">
      <table class="opn-tbl">
        <thead>
          <tr>
            <th style="min-width:200px">Bahan Baku</th>
            <th style="min-width:160px">Profil / Lot</th>
            <th>UOM</th>
            <th style="text-align:right">Stok Sistem</th>
            <th style="text-align:right;min-width:110px">Stok Fisik</th>
            <th style="text-align:right">Selisih</th>
            <th style="min-width:130px">Catatan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="opnTbody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Quick Adjust -->
<div class="modal fade" id="opnAdjModal" tabindex="-1" aria-labelledby="opnAdjModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="opnAdjModalLabel">Sesuaikan Stok</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="opnAdjInfo" class="mb-3"></div>
        <div class="mb-3">
          <label class="form-label fw-bold">Jenis Penyesuaian</label>
          <select class="form-select" id="opnAdjType">
            <option value="VARIANCE">Variance (selisih stok)</option>
            <option value="WASTE">Waste (terbuang)</option>
            <option value="SPOIL">Spoil (busuk/expired)</option>
            <option value="PROCESS_LOSS">Process Loss</option>
            <option value="ADJUSTMENT_MINUS">Adjustment Minus</option>
            <option value="ADJUSTMENT_PLUS">Adjustment Plus (tambah stok)</option>
          </select>
          <small class="text-muted" id="opnAdjTypeHint"></small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">Catatan</label>
          <input type="text" class="form-control" id="opnAdjNotes" placeholder="Alasan penyesuaian...">
        </div>
        <div class="alert alert-warning py-2 small mb-0">
          Adjustment akan langsung diposting. Tidak bisa di-undo melalui halaman ini.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="opnAdjSubmit">Posting Penyesuaian</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const DATA_URL    = '<?= $dataUrl ?>';
  const SAVE_URL    = '<?= $savePhysUrl ?>';
  const ADJ_URL     = '<?= $quickAdjUrl ?>';
  const BASE_URL    = '<?= base_url() ?>';

  let currentRows   = [];
  let currentMeta   = {};
  let pendingRow    = null;
  let saveTimers    = {};

  const el = id => document.getElementById(id);

  function fmt(v, dec) {
    if (v === null || v === undefined) return '-';
    return Number(v).toLocaleString('id-ID', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  }

  function setLoading(on) {
    el('opnLoading').classList.toggle('d-none', !on);
    el('opnScrollWrap').style.display = on ? 'none' : '';
    if (on) el('opnEmpty2').classList.add('d-none');
  }

  function updateSummary() {
    let total = 0, filled = 0, miss = 0, adj = 0;
    currentRows.forEach(function (g) {
      g.profiles.forEach(function (r) {
        total++;
        if (r.physical_qty_content !== null) {
          filled++;
          if (r.selisih !== null && Math.abs(r.selisih) > 0.001) miss++;
          if (r.adjustment_id) adj++;
        }
      });
    });
    el('smTotal').textContent   = total;
    el('smFilled').textContent  = filled;
    el('smMiss').textContent    = miss;
    el('smAdj').textContent     = adj;
    el('opnSummBar').classList.remove('d-none');
  }

  function buildSelisihCell(selisih) {
    if (selisih === null) return '<span class="opn-selisih empty">—</span>';
    const v = Number(selisih);
    if (Math.abs(v) < 0.0001) return '<span class="opn-selisih zero">± 0</span>';
    const cls = v < 0 ? 'neg' : 'pos';
    const sign = v > 0 ? '+' : '';
    return `<span class="opn-selisih ${cls}">${sign}${fmt(v, 4)}</span>`;
  }

  function buildAdjButton(r) {
    const selisih = r.selisih;
    if (selisih === null) return '<button class="opn-btn-adj" disabled>Sesuaikan</button>';
    if (Math.abs(Number(selisih)) < 0.0001) return '<span class="opn-badge-ok">✓ Match</span>';
    if (r.adjustment_id) {
      return `<span class="opn-badge-miss"><span class="opn-status-dot adj"></span>Adj #${r.adjustment_id}</span>`;
    }
    return `<button class="opn-btn-adj" onclick="opnOpenAdj(${JSON.stringify(r)})">Sesuaikan</button>`;
  }

  function renderTable(groups) {
    const tbody = el('opnTbody');
    if (!groups.length) {
      el('opnScrollWrap').style.display = 'none';
      el('opnNoResult').classList.remove('d-none');
      return;
    }
    el('opnNoResult').classList.add('d-none');
    el('opnScrollWrap').style.display = '';

    let html = '';
    groups.forEach(function (g) {
      const multiProfile = g.profiles.length > 1;
      if (multiProfile) {
        const sysTotal = g.system_total;
        const physTotal = g.physical_total;
        const selisihTotal = physTotal !== null ? (physTotal - sysTotal) : null;
        html += `<tr class="mat-group">
          <td colspan="3"><i class="ri-arrow-down-s-line" style="color:#8b7772"></i> ${htmlEsc(g.material_name || g.item_name)} <span style="color:#8b7772;font-weight:400;font-size:.82rem;">(${g.profiles.length} profil)</span></td>
          <td style="text-align:right">${fmt(sysTotal, 4)}</td>
          <td style="text-align:right">${physTotal !== null ? fmt(physTotal, 4) : '<span style="color:#bbb">—</span>'}</td>
          <td style="text-align:right">${buildSelisihCell(selisihTotal)}</td>
          <td colspan="2"></td>
        </tr>`;
      }

      g.profiles.forEach(function (r) {
        const rowClass = multiProfile ? 'profile-row' : '';
        const matLabel = !multiProfile ? (htmlEsc(r.material_name || r.item_name)) : '';
        const profLabel = htmlEsc(r.profile_name || r.profile_key || '-');
        const physVal   = r.physical_qty_content !== null ? r.physical_qty_content : '';
        const uomCode   = htmlEsc(r.profile_content_uom_code || '');

        html += `<tr class="${rowClass}" data-ikey="${htmlEsc(r.identity_key)}">
          <td>${matLabel}</td>
          <td>${profLabel}${r.profile_expired_date ? ' <small class="text-muted">exp:'+htmlEsc(r.profile_expired_date)+'</small>' : ''}</td>
          <td>${uomCode}</td>
          <td style="text-align:right">${fmt(r.system_qty_content, 4)}</td>
          <td style="text-align:right">
            <input type="number" class="opn-input-qty"
              step="0.01" min="0"
              value="${physVal !== '' ? physVal : ''}"
              placeholder="—"
              data-ikey="${htmlEsc(r.identity_key)}"
              data-sys="${r.system_qty_content}"
              onchange="opnPhysChanged(this, ${JSON.stringify(r)})"
              oninput="opnPhysInput(this)">
          </td>
          <td id="sel-${cssEsc(r.identity_key)}" style="text-align:right">${buildSelisihCell(r.selisih)}</td>
          <td>
            <input type="text" class="opn-notes-input"
              placeholder="catatan..."
              value="${htmlEsc(r.opname_notes || '')}"
              data-ikey="${htmlEsc(r.identity_key)}"
              onchange="opnNotesChanged(this, ${JSON.stringify(r)})">
          </td>
          <td id="adj-${cssEsc(r.identity_key)}">${buildAdjButton(r)}</td>
        </tr>`;
      });
    });
    tbody.innerHTML = html;
  }

  function htmlEsc(s) {
    const d = document.createElement('div');
    d.textContent = String(s || '');
    return d.innerHTML;
  }
  function cssEsc(s) { return String(s || '').replace(/[^a-zA-Z0-9_-]/g, '_'); }

  function loadData(showLoading) {
    const date  = el('opnDate').value;
    const divId = el('opnDivision').value;
    const dest  = el('opnDestination').value;
    const q     = el('opnQ').value;

    if (!divId || divId === '0') {
      el('opnEmpty2').classList.remove('d-none');
      el('opnEmpty2').textContent = 'Pilih divisi terlebih dahulu.';
      el('opnScrollWrap').style.display = 'none';
      el('opnSummBar').classList.add('d-none');
      return;
    }
    el('opnEmpty2').classList.add('d-none');

    if (showLoading !== false) setLoading(true);

    const url = DATA_URL + '?opname_date=' + encodeURIComponent(date)
      + '&division_id=' + encodeURIComponent(divId)
      + '&destination=' + encodeURIComponent(dest)
      + '&q=' + encodeURIComponent(q);

    fetch(url, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(function (data) {
        setLoading(false);
        if (!data.ok) { alert('Gagal memuat data: ' + (data.message || '')); return; }
        currentRows = data.rows || [];
        currentMeta = data.meta || {};
        renderTable(currentRows);
        updateSummary();
      })
      .catch(function (e) {
        setLoading(false);
        alert('Error memuat data: ' + e.message);
      });
  }

  window.opnPhysInput = function (inp) {
    const sys = parseFloat(inp.dataset.sys) || 0;
    const val = inp.value.trim();
    const phys = val !== '' ? parseFloat(val) : null;
    const ikey = inp.dataset.ikey;
    const selEl = el('sel-' + cssEsc(ikey));
    if (selEl) {
      const sel = phys !== null ? phys - sys : null;
      selEl.innerHTML = buildSelisihCell(sel);
    }
    inp.classList.add('changed');
  };

  window.opnPhysChanged = function (inp, rowData) {
    const ikey = inp.dataset.ikey;
    clearTimeout(saveTimers[ikey]);
    saveTimers[ikey] = setTimeout(function () { doSavePhysical(inp, rowData); }, 600);
  };

  window.opnNotesChanged = function (inp, rowData) {
    const ikey = rowData.identity_key;
    clearTimeout(saveTimers['notes_' + ikey]);
    saveTimers['notes_' + ikey] = setTimeout(function () { doSavePhysical(null, rowData, inp.value); }, 800);
  };

  function doSavePhysical(inp, rowData, notesOverride) {
    const date  = el('opnDate').value;
    const divId = el('opnDivision').value;
    const dest  = el('opnDestination').value;
    const physVal = inp ? inp.value.trim() : null;
    const phys  = physVal !== null && physVal !== '' ? parseFloat(physVal) : null;

    const notesEl = document.querySelector('input.opn-notes-input[data-ikey="' + rowData.identity_key + '"]');
    const notes = notesOverride !== undefined ? notesOverride : (notesEl ? notesEl.value : '');

    const payload = Object.assign({}, rowData, {
      opname_date:         date,
      division_id:         parseInt(divId),
      destination_type:    dest === 'ALL' ? (rowData.destination_type || 'OTHER') : dest,
      physical_qty_content: phys,
      notes:               notes,
    });

    fetch(SAVE_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(function (data) {
        if (inp) inp.classList.remove('changed');
        if (!data.ok) { console.error('Save failed', data.message); return; }
        const ikey = rowData.identity_key;
        rowData.physical_qty_content = data.physical_qty_content;
        rowData.selisih = data.selisih;
        const selEl = el('sel-' + cssEsc(ikey));
        if (selEl) selEl.innerHTML = buildSelisihCell(data.selisih);
        const adjEl = el('adj-' + cssEsc(ikey));
        if (adjEl && !rowData.adjustment_id) adjEl.innerHTML = buildAdjButton(rowData);
        updateSummary();
      })
      .catch(function (e) { console.error('Save error', e); });
  }

  window.opnOpenAdj = function (rowData) {
    pendingRow = rowData;
    const selisih = Number(rowData.selisih || 0);
    const selisihFmt = (selisih > 0 ? '+' : '') + fmt(selisih, 4);
    const uom = rowData.profile_content_uom_code || '';
    const mat = rowData.material_name || rowData.item_name || '';

    el('opnAdjInfo').innerHTML = `
      <div class="fw-bold mb-1">${htmlEsc(mat)}</div>
      <div class="small text-muted">Profil: ${htmlEsc(rowData.profile_name || '-')}</div>
      <div class="mt-2">Selisih: <strong class="${selisih < 0 ? 'text-danger' : 'text-warning'}">${selisihFmt} ${uom}</strong></div>
      <div class="small text-muted">Sistem: ${fmt(rowData.system_qty_content, 4)} · Fisik: ${fmt(rowData.physical_qty_content, 4)}</div>
    `;

    const adjSel = el('opnAdjType');
    if (selisih < 0) {
      adjSel.innerHTML = `
        <option value="VARIANCE">Variance (selisih stok)</option>
        <option value="WASTE">Waste (terbuang)</option>
        <option value="SPOIL">Spoil (busuk/expired)</option>
        <option value="PROCESS_LOSS">Process Loss</option>
        <option value="ADJUSTMENT_MINUS">Adjustment Minus</option>
      `;
    } else {
      adjSel.innerHTML = `<option value="ADJUSTMENT_PLUS">Adjustment Plus (tambah stok)</option>`;
    }
    el('opnAdjNotes').value = rowData.opname_notes || '';

    const modal = new bootstrap.Modal(document.getElementById('opnAdjModal'));
    modal.show();
  };

  el('opnAdjSubmit').addEventListener('click', function () {
    if (!pendingRow) return;
    const btn = el('opnAdjSubmit');
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    const date  = el('opnDate').value;
    const divId = el('opnDivision').value;
    const dest  = el('opnDestination').value;

    const payload = Object.assign({}, pendingRow, {
      opname_date:        date,
      division_id:        parseInt(divId),
      destination_type:   dest === 'ALL' ? (pendingRow.destination_type || 'OTHER') : dest,
      adjustment_type:    el('opnAdjType').value,
      notes:              el('opnAdjNotes').value,
    });

    fetch(ADJ_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(function (data) {
        btn.disabled = false;
        btn.textContent = 'Posting Penyesuaian';
        if (!data.ok) { alert('Gagal: ' + (data.message || '')); return; }

        bootstrap.Modal.getInstance(document.getElementById('opnAdjModal')).hide();
        const ikey = pendingRow.identity_key;
        pendingRow.adjustment_id = data.adjustment_id;
        const adjEl = el('adj-' + cssEsc(ikey));
        if (adjEl) adjEl.innerHTML = `<span class="opn-badge-miss"><span class="opn-status-dot adj"></span>Adj #${data.adjustment_id}</span>`;
        updateSummary();
        pendingRow = null;
      })
      .catch(function (e) {
        btn.disabled = false;
        btn.textContent = 'Posting Penyesuaian';
        alert('Error: ' + e.message);
      });
  });

  el('opnLoad').addEventListener('click', function () { loadData(true); });
  el('opnRefresh').addEventListener('click', function () { loadData(false); });
  el('opnQ').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadData(true); });

  // Auto-load jika divisi sudah dipilih
  if (el('opnDivision').value && el('opnDivision').value !== '0') {
    loadData(true);
  }
})();
</script>
