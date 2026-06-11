<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$reconDate    = $opname_date   ?? date('Y-m-d');
$selLocType   = $location_type ?? '';
$selDivId     = (int)($division_id ?? 0);
$selType      = $type          ?? '';
$selQ         = $q             ?? '';
$divisionList = $divisions     ?? [];
$canCreate    = !empty($can_create);

// Sama persis dengan /production/component-adjustments
$REASONS = [
    'WASTE' => [
        'cancel_order'   => 'Cancel Order',
        'kitchen_error'  => 'Kitchen Error',
        'overproduction' => 'Overproduction',
        'spillage'       => 'Spillage / Tumpah',
        'expired_opened' => 'Expired Opened',
        'other'          => 'Other',
    ],
    'SPOILAGE' => [
        'expired'           => 'Expired',
        'temperature_abuse' => 'Temperature Abuse',
        'contamination'     => 'Contamination',
        'improper_storage'  => 'Improper Storage',
        'overstock'         => 'Overstock',
        'other'             => 'Other',
    ],
    'ADJUSTMENT_PLUS' => [
        'opening_correction' => 'Opening Correction',
        'stock_found'        => 'Stock Found',
        'manual_reclass'     => 'Manual Reclass',
        'other'              => 'Other',
    ],
    'ADJUSTMENT_MINUS' => [
        'counting_error'    => 'Counting Error',
        'system_mismatch'   => 'System Mismatch',
        'unrecorded_usage'  => 'Unrecorded Usage',
        'process_loss'      => 'Process Loss',
        'theft_suspected'   => 'Theft Suspected',
        'other'             => 'Other',
    ],
];
?>
<?php $this->load->view('layout/header', ['title' => $page_title ?? 'Daily Recon Stok Component']); ?>

<div class="mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <h4 class="mb-1"><i class="ri ri-check-double-line page-title-icon"></i><?php echo html_escape($page_title ?? 'Daily Recon Stok Component'); ?></h4>
      <small class="text-muted">Input stok fisik harian component · selisih vs sistem · posting adjustment langsung.</small>
    </div>
  </div>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'daily_recon']); ?>
<?php $this->load->view('production/_component_type_tabs', [
    'component_type_base_url' => site_url('production/component-daily-recon'),
    'component_type_filters'  => [
        'opname_date'   => $reconDate,
        'location_type' => $selLocType,
        'division_id'   => $selDivId > 0 ? $selDivId : null,
        'q'             => $selQ,
    ],
    'component_type_active' => $selType,
]); ?>

<style>
#cmpTableWrap table  { border-collapse:collapse; width:100%; }
#cmpTableWrap thead th {
    position:sticky; top:0; z-index:2;
    font-size:.73rem; font-weight:700; color:#334155;
    background:#e2e8f0; border-bottom:2px solid #94a3b8 !important;
    padding:6px 8px; white-space:nowrap;
}
#cmpTbody tr           { border-bottom:1px solid #dee2e6; }
#cmpTbody tr > td      { border:none; padding:5px 8px; vertical-align:middle; }
#cmpTbody tr:nth-child(even) > td { background:#f8fafc; }
#cmpTbody tr:nth-child(odd)  > td { background:#ffffff; }
#cmpTbody tr:hover > td      { background:#eff6ff !important; }
.cmp-row-minus > td          { background:#fff1f2 !important; }
.cmp-row-minus > td:first-child { border-left:3px solid #f87171; }

.cmp-div-cell  { font-size:.72rem; color:#1e40af; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:90px; }
.cmp-tipe-reguler { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-tipe-event   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-jenis-base { background:#dbeafe; color:#1d4ed8; border:1px solid #93c5fd; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-jenis-prep { background:#ede9fe; color:#6d28d9; border:1px solid #c4b5fd; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.cmp-name-cell  { display:flex; align-items:flex-start; }
.cmp-name-body  { min-width:0; }
.cmp-adj-col    { min-width:200px; }
.filter-on      { background:#2563eb !important; color:#fff !important; border-color:#2563eb !important; }
</style>

<div class="d-flex align-items-center gap-2 mb-2">
  <span class="badge bg-secondary" id="cmpBadgeTotal">—</span>
</div>

<!-- Filter -->
  <form id="cmpForm" method="get" action="<?php echo site_url('production/component-daily-recon'); ?>"
        class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Tanggal</label>
      <input type="date" name="opname_date" class="form-control form-control-sm"
             value="<?php echo html_escape($reconDate); ?>">
    </div>
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Divisi</label>
      <select name="division_id" class="form-select form-select-sm" style="min-width:130px">
        <option value="0">Semua Divisi</option>
        <?php foreach ($divisionList as $dv): ?>
          <option value="<?php echo (int)$dv['id']; ?>"
            <?php echo $selDivId === (int)$dv['id'] ? 'selected' : ''; ?>>
            <?php echo html_escape($dv['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label form-label-sm mb-1">Lokasi</label>
      <select name="location_type" class="form-select form-select-sm">
        <option value="" <?php echo $selLocType === '' ? 'selected' : ''; ?>>Semua</option>
        <option value="REGULER" <?php echo $selLocType === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
        <option value="EVENT"   <?php echo $selLocType === 'EVENT'   ? 'selected' : ''; ?>>Event</option>
      </select>
    </div>
    <input type="hidden" name="type" value="<?php echo html_escape($selType); ?>">
    <div class="col">
      <label class="form-label form-label-sm mb-1">Cari</label>
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="Nama / kode component…" value="<?php echo html_escape($selQ); ?>">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="ri ri-search-line me-1"></i>Tampilkan
      </button>
    </div>
  </form>

  <!-- Summary cards -->
  <div class="row g-2 mb-3">
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Total</div><div class="fw-bold fs-5" id="smTotal">—</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">BASE</div><div class="fw-bold fs-5 text-primary" id="smBase">—</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">PREPARE</div><div class="fw-bold fs-5" style="color:#7c3aed" id="smPrep">—</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Dihitung</div><div class="fw-bold fs-5 text-success" id="smCounted">—</div>
    </div></div></div>
    <div class="col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 px-3">
      <div class="text-muted small">Stok Minus</div><div class="fw-bold fs-5 text-danger" id="smMinus">—</div>
    </div></div></div>
  </div>

  <!-- Toolbar -->
  <div class="d-flex gap-2 mb-2 align-items-center">
    <button id="btnFilterMinus" class="btn btn-sm btn-outline-danger" style="display:none">
      <i class="ri ri-arrow-down-line me-1"></i>Tampilkan Minus
      <span id="cmpMinusBadge" class="badge bg-danger ms-1" style="display:none">0</span>
    </button>
    <div id="cmpSpinner" class="spinner-border spinner-border-sm text-secondary" style="display:none"></div>
    <span id="cmpErrMsg" class="text-danger small" style="display:none"></span>
  </div>

  <!-- Table -->
  <div id="cmpTableWrap" class="table-responsive rounded border shadow-sm" style="max-height:68vh;overflow-y:auto">
    <table class="table table-sm mb-0">
      <thead>
        <tr>
          <th style="width:80px">Divisi</th>
          <th style="width:64px">Tipe</th>
          <th style="width:68px">Jenis</th>
          <th class="text-start" style="min-width:190px">Nama Component</th>
          <th style="width:54px">UOM</th>
          <th class="text-end" style="width:94px">Sistem</th>
          <th class="text-end" style="width:100px">Fisik</th>
          <th class="text-end" style="width:80px">Selisih</th>
          <th class="cmp-adj-col">Jenis &amp; Alasan</th>
          <th style="width:88px">Aksi</th>
        </tr>
      </thead>
      <tbody id="cmpTbody">
        <tr><td colspan="10" class="text-center text-muted py-4">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>

<script>
const DATA_URL     = '<?php echo site_url('production/component-daily-recon/data'); ?>';
const SAVE_URL     = '<?php echo site_url('production/component-daily-recon/save-physical'); ?>';
const ADJ_URL      = '<?php echo site_url('production/component-daily-recon/quick-adjust'); ?>';
const ADJ_PAGE_URL = '<?php echo site_url('production/component-adjustments'); ?>';
const CAN_CREATE   = <?php echo $canCreate ? 'true' : 'false'; ?>;
const RECON_DATE   = '<?php echo html_escape($reconDate); ?>';
const LOC_TYPE     = '<?php echo html_escape($selLocType); ?>';
const DIV_ID       = <?php echo (int)$selDivId; ?>;
const COMP_TYPE    = '<?php echo html_escape($selType); ?>';
const COMP_Q       = <?php echo json_encode($selQ); ?>;

// Reason codes — sama persis dengan /production/component-adjustments
const REASONS = <?php echo json_encode($REASONS); ?>;

const ADJ_TYPES_NEG = [
    { val: 'ADJUSTMENT_MINUS', lbl: 'Adjustment Minus' },
    { val: 'WASTE',            lbl: 'Waste'            },
    { val: 'SPOILAGE',         lbl: 'Spoil'            },
];
const ADJ_TYPES_POS = [
    { val: 'ADJUSTMENT_PLUS', lbl: 'Adjustment Plus' },
];

// ── Helpers ───────────────────────────────────────────────────
const el  = id => document.getElementById(id);
const esc = s  => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
const fmt4 = v => isNaN(parseFloat(v)) ? '—' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
function cssid(s) { return String(s).replace(/[^a-zA-Z0-9_-]/g, '_'); }

const profileMap = {};

function selisihHtml(sel, iid) {
    if (sel === null || sel === undefined) return `<span id="sel-${iid}" class="text-muted small">—</span>`;
    const v = Number(sel);
    if (Math.abs(v) < 0.001) return `<span id="sel-${iid}" class="text-success fw-bold">± 0</span>`;
    const cls = v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    return `<span id="sel-${iid}" class="${cls}">${v > 0 ? '+' : ''}${fmt4(v)}</span>`;
}

function tipeBadge(lt) {
    return lt === 'EVENT'
        ? `<span class="cmp-tipe-event">Event</span>`
        : `<span class="cmp-tipe-reguler">Reguler</span>`;
}
function jenisBadge(ct) {
    return ct === 'BASE'
        ? `<span class="cmp-jenis-base">BASE</span>`
        : `<span class="cmp-jenis-prep">PREP</span>`;
}

function buildLabel(row) {
    let inner = `<div class="fw-semibold" style="font-size:.82rem">${esc(row.component_name)}</div>`;
    const sub = [row.component_code, row.category_name].filter(Boolean);
    if (sub.length) inner += `<div class="text-muted" style="font-size:.74rem">${esc(sub.join(' · '))}</div>`;
    return `<div class="cmp-name-cell"><div class="cmp-name-body">${inner}</div></div>`;
}

// ── Adjustment col ─────────────────────────────────────────────
function adjColHtml(row, iid) {
    if (row.adjustment_id) {
        return `<td id="adjcol-${iid}" class="cmp-adj-col">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="ri ri-check-double-line me-1"></i>Adj #${row.adjustment_id}
            </span>
        </td>`;
    }
    const sel = row.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        const isNeg  = Number(sel) < 0;
        const types  = isNeg ? ADJ_TYPES_NEG : ADJ_TYPES_POS;
        const typeOpts = `<option value="">— pilih jenis —</option>` +
            types.map(t => `<option value="${t.val}">${t.lbl}</option>`).join('');
        return `<td id="adjcol-${iid}" class="cmp-adj-col py-1">
            <div class="d-flex flex-column gap-1">
                <select id="adjtype-${iid}" class="form-select form-select-sm" style="font-size:.72rem"
                        onchange="cmpTypeChange('${iid}')">
                    ${typeOpts}
                </select>
                <select id="adjreason-${iid}" class="form-select form-select-sm d-none" style="font-size:.72rem"></select>
                <input type="text" id="adjnotes-${iid}" class="form-control form-control-sm d-none"
                       placeholder="Catatan (opsional)" style="font-size:.72rem">
            </div>
        </td>`;
    }
    return `<td id="adjcol-${iid}" class="cmp-adj-col"></td>`;
}

function actionCell(row, iid) {
    if (!CAN_CREATE || row.adjustment_id) return `<td id="acell-${iid}"></td>`;
    const sel = row.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        return `<td id="acell-${iid}">
            <button class="btn btn-sm btn-danger w-100" id="adjbtn-${iid}"
                    onclick="cmpPostAdj('${iid}')">
                <i class="ri ri-upload-2-line me-1"></i>Posting
            </button>
        </td>`;
    }
    return `<td id="acell-${iid}"></td>`;
}

// ── Render table ──────────────────────────────────────────────
function renderTable(groups) {
    const tbody = el('cmpTbody');
    if (!tbody) return;

    let html = '';
    let total = 0, base = 0, prep = 0, counted = 0, minus = 0;

    groups.forEach(function (grp) {
        grp.rows.forEach(function (row) {
            const iid = cssid(row.identity_key);
            profileMap[iid] = row;
            total++;
            if (row.component_type === 'BASE') base++; else prep++;
            if (row.physical_qty !== null) counted++;
            if (parseFloat(row.system_qty) < -0.001) minus++;

            const rowCls  = parseFloat(row.system_qty) < -0.001 ? 'cmp-row-minus' : '';
            const physVal = row.physical_qty !== null ? row.physical_qty : '';

            html += `<tr id="row-${iid}" class="${rowCls}"
                         data-system-val="${row.system_qty}" data-div-id="${row.division_id}">
                <td class="cmp-div-cell">${esc(row.division_name)}</td>
                <td>${tipeBadge(row.location_type)}</td>
                <td>${jenisBadge(row.component_type)}</td>
                <td class="text-start">${buildLabel(row)}</td>
                <td class="text-muted" style="font-size:.8rem">${esc(row.uom_code || '—')}</td>
                <td class="text-end" style="font-size:.85rem">${fmt4(row.system_qty)}</td>
                <td class="text-end">
                    <input type="number" class="form-control form-control-sm text-end"
                           style="width:88px;display:inline-block"
                           step="0.01" value="${esc(physVal)}" placeholder="—"
                           data-iid="${iid}" data-sys="${row.system_qty}"
                           oninput="cmpLiveCalc(this,'${iid}')"
                           onchange="cmpSavePhys('${iid}')"
                           ${!CAN_CREATE ? 'disabled' : ''}>
                </td>
                <td class="text-end">${selisihHtml(row.selisih, iid)}</td>
                ${adjColHtml(row, iid)}
                ${actionCell(row, iid)}
            </tr>`;
        });
    });

    tbody.innerHTML = html || `<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data.</td></tr>`;

    const s = (id, v) => { const e = el(id); if (e) e.textContent = v; };
    s('smTotal',   total);
    s('smBase',    base);
    s('smPrep',    prep);
    s('smCounted', counted + ' / ' + total);
    s('smMinus',   minus);
    s('cmpBadgeTotal', total + ' item');

    const minusBadge = el('cmpMinusBadge');
    const filterBtn  = el('btnFilterMinus');
    if (minusBadge) { minusBadge.textContent = minus; minusBadge.style.display = minus > 0 ? '' : 'none'; }
    if (filterBtn)  filterBtn.style.display = minus > 0 ? '' : 'none';
}

// ── Type / reason change ──────────────────────────────────────
window.cmpTypeChange = function (iid) {
    const typeSel   = el('adjtype-'   + iid);
    const reasonSel = el('adjreason-' + iid);
    const notesInp  = el('adjnotes-'  + iid);
    if (!typeSel || !reasonSel) return;
    if (!typeSel.value) {
        reasonSel.classList.add('d-none');
        if (notesInp) notesInp.classList.add('d-none');
        return;
    }
    const opts = REASONS[typeSel.value] || { other: 'Other' };
    reasonSel.innerHTML = Object.entries(opts)
        .map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
    reasonSel.classList.remove('d-none');
    if (notesInp) notesInp.classList.remove('d-none');
};

// ── Live selisih ──────────────────────────────────────────────
window.cmpLiveCalc = function (inp, iid) {
    const row   = profileMap[iid];
    const sys   = parseFloat(inp.dataset.sys) || 0;
    const phys  = inp.value.trim() !== '' ? parseFloat(inp.value) : null;
    const selEl = el('sel-' + iid);
    if (!selEl) return;

    row.selisih      = phys !== null ? phys - sys : null;
    row.physical_qty = phys;

    if (phys === null) {
        selEl.textContent = '—'; selEl.className = 'text-muted small';
    } else {
        const diff = phys - sys;
        selEl.textContent = (diff > 0 ? '+' : '') + fmt4(diff);
        selEl.className   = Math.abs(diff) < 0.001 ? 'text-success fw-bold'
            : diff < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    }

    // Refresh adj col and action cell
    const adjCell = el('adjcol-' + iid);
    if (adjCell) adjCell.outerHTML = adjColHtml(row, iid);
    const actCell = el('acell-' + iid);
    if (actCell) actCell.outerHTML = actionCell(row, iid);

    // Row highlight
    const tr = el('row-' + iid);
    if (tr) tr.classList.toggle('cmp-row-minus', sys < -0.001);
};

// ── Save physical ─────────────────────────────────────────────
window.cmpSavePhys = function (iid) {
    if (!CAN_CREATE) return;
    const row = profileMap[iid];
    if (!row) return;
    const inp  = document.querySelector(`input[data-iid="${iid}"]`);
    if (!inp) return;
    const physVal = inp.value.trim() !== '' ? parseFloat(inp.value) : null;

    fetch(SAVE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            opname_date:   RECON_DATE,
            location_type: row.location_type,
            division_id:   row.division_id || null,
            component_id:  row.component_id,
            uom_id:        row.uom_id,
            system_qty:    row.system_qty,
            physical_qty:  physVal,
        }),
    })
    .then(r => r.json())
    .catch(err => console.error('Save error:', err));
};

// ── Post adjustment ───────────────────────────────────────────
window.cmpPostAdj = function (iid) {
    const row = profileMap[iid];
    if (!row) return;

    const typeVal = el('adjtype-' + iid)?.value;
    if (!typeVal) {
        alert('Pilih jenis adjustment terlebih dahulu.');
        el('adjtype-' + iid)?.focus();
        return;
    }

    const btn = el('adjbtn-' + iid);
    const orig = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

    fetch(ADJ_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            opname_date:     RECON_DATE,
            location_type:   row.location_type,
            division_id:     row.division_id || null,
            division_code:   row.division_code,
            component_id:    row.component_id,
            uom_id:          row.uom_id,
            system_qty:      row.system_qty,
            physical_qty:    row.physical_qty,
            avg_cost:        row.avg_cost,
            adjustment_type: typeVal,
            reason_code:     el('adjreason-' + iid)?.value || 'other',
            notes:           el('adjnotes-'  + iid)?.value?.trim() || '',
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        if (!data.ok) { alert('Gagal: ' + (data.message || 'Error.')); return; }

        row.adjustment_id = data.adjustment_id;
        const adjCell = el('adjcol-' + iid);
        if (adjCell) adjCell.outerHTML = adjColHtml(row, iid);
        const actCell = el('acell-' + iid);
        if (actCell) actCell.outerHTML = actionCell(row, iid);
    })
    .catch(err => {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        alert('Request gagal: ' + err.message);
    });
};

// ── Minus filter ──────────────────────────────────────────────
let showOnlyMinus = false;
function applyMinusFilter() {
    const btn  = el('btnFilterMinus');
    if (btn) btn.classList.toggle('filter-on', showOnlyMinus);
    document.querySelectorAll('#cmpTbody tr').forEach(function (r) {
        r.style.display = !showOnlyMinus || parseFloat(r.dataset.systemVal || 0) < -0.001 ? '' : 'none';
    });
}
document.addEventListener('click', function (e) {
    if (e.target.closest('#btnFilterMinus') || e.target.closest('#cmpMinusBadge')) {
        showOnlyMinus = !showOnlyMinus;
        applyMinusFilter();
    }
});

// ── Load data ─────────────────────────────────────────────────
function loadData() {
    const spinner = el('cmpSpinner');
    const errMsg  = el('cmpErrMsg');
    const tbody   = el('cmpTbody');
    if (spinner) spinner.style.display = '';
    if (errMsg)  errMsg.style.display  = 'none';

    const params = new URLSearchParams({
        opname_date:   RECON_DATE,
        location_type: LOC_TYPE,
        division_id:   DIV_ID,
        type:          COMP_TYPE,
        q:             COMP_Q,
    });

    fetch(DATA_URL + '?' + params)
        .then(r => r.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            if (!data.ok) {
                if (errMsg)  { errMsg.textContent = data.message || 'Gagal.'; errMsg.style.display = ''; }
                if (tbody)   tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Gagal memuat data.</td></tr>`;
                return;
            }
            renderTable(data.rows || []);
            if (showOnlyMinus) applyMinusFilter();
        })
        .catch(err => {
            if (spinner) spinner.style.display = 'none';
            if (errMsg)  { errMsg.textContent = 'Request gagal: ' + err.message; errMsg.style.display = ''; }
            if (tbody)   tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Request gagal.</td></tr>`;
        });
}

loadData();
</script>

<?php $this->load->view('layout/footer'); ?>
