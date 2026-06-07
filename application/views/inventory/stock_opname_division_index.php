<?php
$adjustDate  = (string)($opname_date ?? date('Y-m-d'));
$divisionId  = (int)($division_id    ?? 0);
$destination = strtoupper((string)($destination ?? 'ALL'));
$q           = (string)($q ?? '');
$divisions   = is_array($divisions ?? null) ? $divisions : [];

$isSuperadmin = !empty($current_user['is_superadmin']);
$canCreate    = $isSuperadmin || !empty($can_create);

$baseUrl      = site_url('inventory/stock/daily-recon/division');
$dataUrl      = site_url('inventory/stock/daily-recon/division/data');
$savePhysUrl  = site_url('inventory/stock/daily-recon/division/save-physical');
$quickAdjUrl  = site_url('inventory/stock/daily-recon/division/quick-adjust');

$destOptions = [
    'ALL'           => 'Semua Tujuan',
    'BAR'           => 'Bar Reguler',
    'KITCHEN'       => 'Kitchen Reguler',
    'BAR_EVENT'     => 'Bar Event',
    'KITCHEN_EVENT' => 'Kitchen Event',
    'OFFICE'        => 'Office',
    'OTHER'         => 'Other',
];

$REASONS = [
    'WASTE' => [
        'cancel_order'     => 'Cancel Order',
        'kitchen_error'    => 'Kitchen Error',
        'overproduction'   => 'Overproduction',
        'spillage'         => 'Spillage / Tumpah',
        'prep_trim_excess' => 'Prep Trim Excess',
        'expired_opened'   => 'Expired Opened',
        'other'            => 'Other',
    ],
    'SPOIL' => [
        'expired'           => 'Expired',
        'temperature_abuse' => 'Temperature Abuse',
        'contamination'     => 'Contamination',
        'overstock'         => 'Overstock',
        'improper_storage'  => 'Improper Storage',
        'other'             => 'Other',
    ],
    'PROCESS_LOSS' => [
        'defrost_loss'                => 'Defrost Loss',
        'trimming_standard'           => 'Trimming Standard',
        'cooking_loss'                => 'Cooking Loss',
        'evaporation'                 => 'Evaporation',
        'brew_loss'                   => 'Brew Loss',
        'absorption_loss'             => 'Absorption Loss',
        'process_residue'             => 'Process Residue',
        'variable_process_consumable' => 'Variable Process Consumable',
        'other'                       => 'Other',
    ],
    'VARIANCE' => [
        'over_usage'        => 'Over Usage',
        'under_usage'       => 'Under Usage',
        'unrecorded_usage'  => 'Unrecorded Usage',
        'counting_error'    => 'Counting Error',
        'system_mismatch'   => 'System Mismatch',
        'theft_suspected'   => 'Theft Suspected',
        'unknown_shrinkage' => 'Unknown Shrinkage',
        'other'             => 'Other',
    ],
    'ADJUSTMENT_PLUS' => [
        'opening_correction' => 'Opening Correction',
        'stock_found'        => 'Stock Found',
        'manual_reclass'     => 'Manual Reclass',
        'other'              => 'Other',
    ],
];
?>

<div class="fin-page-header">
    <div>
        <p class="fin-breadcrumb">
            <a href="<?= base_url('dashboard') ?>">Dashboard</a> / Stok Divisi / Daily Recon
        </p>
        <h4 class="fin-page-title">
            <i class="ri ri-check-double-line me-1 text-primary"></i>
            Daily Recon — Stok Divisi
        </h4>
        <p class="fin-page-subtitle">Input stok fisik harian · selisih vs sistem otomatis · posting adjustment langsung</p>
    </div>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-3">
    <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'daily_recon']); ?>
</div>

<div id="alert-area" class="mb-3"></div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= $baseUrl ?>" class="row g-2 align-items-end" id="opnForm">
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 small">Tanggal</label>
                <input type="date" name="opname_date" class="form-control form-control-sm"
                       value="<?= html_escape($adjustDate) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 small">Divisi</label>
                <select name="division_id" class="form-select form-select-sm">
                    <option value="0">Semua</option>
                    <?php foreach ($divisions as $div): ?>
                    <option value="<?= (int)($div['id'] ?? 0) ?>" <?= (int)($div['id'] ?? 0) === $divisionId ? 'selected' : '' ?>>
                        <?= html_escape((string)($div['name'] ?? $div['division_name'] ?? '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 small">Tujuan</label>
                <select name="destination" class="form-select form-select-sm">
                    <?php foreach ($destOptions as $val => $lbl): ?>
                    <option value="<?= html_escape($val) ?>" <?= $val === $destination ? 'selected' : '' ?>>
                        <?= html_escape($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1 small">Cari Bahan</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="nama bahan, merek..."
                       value="<?= html_escape($q) ?>">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary flex-fill" id="opnLoad">
                    <i class="ri ri-search-line me-1"></i>Muat Data
                </button>
                <a href="<?= $baseUrl ?>" class="btn btn-sm btn-outline-secondary" title="Reset filter">
                    <i class="ri ri-close-line"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-2 mb-3" id="opnSummary" style="display:none">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Total Profil</div><div class="h5 mb-0 fw-bold" id="smTotal">0</div></div>
                    <i class="ri ri-stack-line ri-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Sudah Diisi</div><div class="h5 mb-0 fw-bold text-success" id="smFilled">0</div></div>
                    <i class="ri ri-checkbox-circle-line ri-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Ada Selisih</div><div class="h5 mb-0 fw-bold text-danger" id="smMiss">0</div></div>
                    <i class="ri ri-alert-line ri-2x text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Disesuaikan</div><div class="h5 mb-0 fw-bold text-info" id="smAdj">0</div></div>
                    <i class="ri ri-git-commit-line ri-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Data -->
<div class="card">
    <div id="opnLoading" class="text-center py-5 text-muted d-none">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memuat data stok divisi...
    </div>
    <div id="opnEmpty" class="text-center py-5 text-muted">
        <i class="ri ri-scales-3-line ri-3x d-block mb-2 opacity-25"></i>
        Klik <strong>Muat Data</strong> untuk memulai koreksi stok.
    </div>
    <div id="opnNoResult" class="text-center py-5 text-muted d-none">
        <i class="ri ri-inbox-line ri-3x d-block mb-2 opacity-25"></i>
        Tidak ada bahan baku ditemukan untuk filter ini.
    </div>

    <div id="opnTableWrap" style="display:none">
        <div class="d-flex justify-content-between align-items-center px-3 pt-2 pb-1">
            <small class="text-muted" id="opnInfo"></small>
            <button class="btn btn-sm btn-outline-secondary" id="opnRefresh">
                <i class="ri ri-refresh-line me-1"></i>Refresh
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
                <thead class="table-light">
                    <tr>
                        <th class="text-start" style="min-width:210px">Bahan Baku / Profil</th>
                        <th style="width:65px">UOM</th>
                        <th class="text-end" style="width:100px">Sistem</th>
                        <th class="text-end" style="width:105px">Fisik</th>
                        <th class="text-end" style="width:90px">Selisih</th>
                        <th style="min-width:240px">Jenis &amp; Alasan</th>
                        <th style="width:95px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="opnTbody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {

const DATA_URL   = '<?= $dataUrl ?>';
const SAVE_URL   = '<?= $savePhysUrl ?>';
const ADJ_URL    = '<?= $quickAdjUrl ?>';
const CAN_CREATE = <?= $canCreate ? 'true' : 'false' ?>;

const REASONS = <?= json_encode($REASONS) ?>;
const ADJ_TYPES_NEG = [
    { val: 'VARIANCE',     lbl: 'Variance' },
    { val: 'WASTE',        lbl: 'Waste' },
    { val: 'SPOIL',        lbl: 'Spoil' },
    { val: 'PROCESS_LOSS', lbl: 'Process Loss' },
];
const ADJ_TYPES_POS = [
    { val: 'ADJUSTMENT_PLUS', lbl: 'Adjustment Plus' },
];

let currentData = [];
let saveTimers  = {};
const profileMap = {};

const el   = id => document.getElementById(id);
const esc  = s  => { const d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; };
const fmt4 = v  => v == null ? '—' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
const fmtRp = v => 'Rp ' + Number(v || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
function cssid(s) { return String(s || '').replace(/[^a-zA-Z0-9_-]/g, '_'); }

function initTooltips(root) {
    (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        try { bootstrap.Tooltip.getOrCreateInstance(el); } catch(e) {}
    });
}

function updateSummary() {
    let total = 0, filled = 0, miss = 0, adj = 0;
    currentData.forEach(function (div) {
        div.materials.forEach(function (mat) {
            mat.profiles.forEach(function (p) {
                total++;
                if (p.physical_qty_content !== null) {
                    filled++;
                    if (p.selisih !== null && Math.abs(p.selisih) >= 0.001) miss++;
                    if (p.adjustment_id) adj++;
                }
            });
        });
    });
    el('smTotal').textContent  = total;
    el('smFilled').textContent = filled;
    el('smMiss').textContent   = miss;
    el('smAdj').textContent    = adj;
}

function selisihHtml(sel, iid) {
    if (sel === null) return `<span id="sel-${iid}" class="text-muted small">—</span>`;
    const v = Number(sel);
    if (Math.abs(v) < 0.001) return `<span id="sel-${iid}" class="text-success fw-bold">± 0</span>`;
    const cls = v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
    return `<span id="sel-${iid}" class="${cls}">${(v > 0 ? '+' : '')}${fmt4(v)}</span>`;
}

function adjColHtml(p, iid) {
    if (p.adjustment_id) {
        return `<td id="adjcol-${iid}" class="adj-col">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="ri ri-check-double-line me-1"></i>Adj #${p.adjustment_id}
            </span>
        </td>`;
    }

    const sel = p.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        const isNeg    = Number(sel) < 0;
        const types    = isNeg ? ADJ_TYPES_NEG : ADJ_TYPES_POS;
        const typeOpts = `<option value="">— pilih jenis —</option>` + types.map(function (t) {
            return `<option value="${t.val}">${t.lbl}</option>`;
        }).join('');

        return `<td id="adjcol-${iid}" class="adj-col py-2">
            <div class="d-flex flex-column gap-1">
                <select id="adjtype-${iid}" class="form-select form-select-sm" onchange="opnTypeChange('${iid}')">
                    ${typeOpts}
                </select>
                <select id="adjreason-${iid}" class="form-select form-select-sm d-none"></select>
                <input type="text" id="adjnotes-${iid}" class="form-control form-control-sm d-none"
                       placeholder="Catatan (opsional)">
            </div>
        </td>`;
    }

    return `<td id="adjcol-${iid}" class="adj-col"></td>`;
}

function actionCell(p, iid) {
    if (!CAN_CREATE) return '<td class="action-cell"></td>';

    if (p.adjustment_id) {
        return `<td class="action-cell"></td>`;
    }

    const sel = p.selisih;
    if (sel !== null && Math.abs(Number(sel)) >= 0.001) {
        return `<td class="action-cell">
            <button class="btn btn-sm btn-danger w-100" id="adjbtn-${iid}"
                    onclick="opnPostAdj('${iid}')">
                <i class="ri ri-upload-2-line me-1"></i>Posting
            </button>
        </td>`;
    }

    if (sel !== null) {
        return `<td class="action-cell text-center">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="ri ri-check-line me-1"></i>Match
            </span>
        </td>`;
    }

    return '<td class="action-cell"></td>';
}

function renderTable(divisions) {
    const tbody = el('opnTbody');
    if (!divisions.length) {
        el('opnTableWrap').style.display = 'none';
        el('opnNoResult').classList.remove('d-none');
        el('opnSummary').style.display = 'none';
        return;
    }
    el('opnNoResult').classList.add('d-none');
    el('opnTableWrap').style.display = '';
    el('opnSummary').style.display = '';

    let totalMat = 0, totalProf = 0;
    divisions.forEach(function (d) {
        totalMat += d.materials.length;
        d.materials.forEach(function (m) { totalProf += m.profiles.length; });
    });
    el('opnInfo').textContent = divisions.length + ' divisi · ' + totalMat + ' material · ' + totalProf + ' profil';

    let html = '';
    divisions.forEach(function (div) {
        html += `<tr class="table-secondary">
            <td colspan="7" class="py-1 text-start" style="font-size:.78rem;font-weight:700;letter-spacing:.04em">
                <i class="ri ri-building-2-line me-1 text-primary"></i>${esc(div.division_name)}
                <span class="text-muted fw-normal ms-1">(${div.materials.length} material)</span>
            </td>
        </tr>`;

        div.materials.forEach(function (mat) {
            const multiProf = mat.profiles.length > 1;

            const grpIid = multiProf ? cssid(div.division_id + '_grp_' + mat.material_id) : null;

            if (multiProf) {
                const sysTotal  = mat.system_total;
                const physTotal = mat.physical_total;
                const selTotal  = physTotal !== null ? physTotal - sysTotal : null;
                html += `<tr id="grp-row-${grpIid}" class="table-light opn-grp-header"
                         style="cursor:pointer" onclick="opnToggleGrp('${grpIid}')">
                    <td class="text-start" style="font-size:.8rem;padding-left:.75rem">
                        <i id="grp-icon-${grpIid}" class="ri ri-arrow-right-s-line text-muted me-1" style="font-size:1rem;vertical-align:middle"></i>
                        <span class="fw-semibold">${esc(mat.material_name)}</span>
                        <span class="text-muted fw-normal ms-1">(${mat.profiles.length} profil)</span>
                    </td>
                    <td></td>
                    <td class="text-end text-muted" style="font-size:.8rem">${fmt4(sysTotal)}</td>
                    <td></td>
                    <td class="text-end" style="font-size:.8rem">${selisihHtml(selTotal, grpIid)}</td>
                    <td></td>
                    <td></td>
                </tr>`;
            }

            mat.profiles.forEach(function (p) {
                const iid     = cssid(p.division_id + '_' + p.identity_key);
                profileMap[iid] = p;
                const physVal = p.physical_qty_content !== null ? p.physical_qty_content : '';
                const contentUom = esc(p.profile_content_uom_code || '—');
                const buyUom     = esc(p.profile_buy_uom_code || '');
                const cpb        = parseFloat(p.profile_content_per_buy) || 0;
                const showBuy    = buyUom && buyUom !== contentUom;

                // UOM cell: content UOM on top, buy UOM below
                const uomCell = showBuy
                    ? `<td class="text-start" style="font-size:.8rem">
                           ${contentUom}
                           <div class="text-muted" style="font-size:.72rem">${buyUom}</div>
                       </td>`
                    : `<td class="text-start text-muted" style="font-size:.8rem">${contentUom}</td>`;

                // Sistem cell: content qty on top, buy qty below
                const sysBuy = p.system_qty_buy > 0
                    ? p.system_qty_buy
                    : (cpb > 0 ? p.system_qty_content / cpb : null);
                const sistemCell = showBuy && sysBuy !== null
                    ? `<td class="text-end" style="font-size:.85rem">
                           ${fmt4(p.system_qty_content)}
                           <div class="text-muted" style="font-size:.72rem">${fmt4(sysBuy)} ${buyUom}</div>
                       </td>`
                    : `<td class="text-end">${fmt4(p.system_qty_content)}</td>`;

                // Fisik input + live buy equivalent
                const physBuyInit = physVal !== '' && cpb > 0 && showBuy
                    ? fmt4(parseFloat(physVal) / cpb) + ' ' + buyUom : '';

                const matLabel = !multiProf
                    ? buildLabelSingle(p, contentUom)
                    : buildLabelSub(p);

                const childAttr = multiProf
                    ? `data-grp="${grpIid}" style="display:none"`
                    : '';

                html += `<tr id="row-${iid}" ${childAttr}>
                    <td class="text-start">${matLabel}</td>
                    ${uomCell}
                    ${sistemCell}
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm text-end"
                               style="width:88px;display:inline-block"
                               step="0.01" min="0"
                               value="${esc(physVal)}"
                               placeholder="—"
                               data-iid="${iid}"
                               data-sys="${p.system_qty_content}"
                               data-cpb="${cpb}"
                               data-buyuom="${buyUom}"
                               oninput="opnLiveCalc(this,'${iid}')"
                               onchange="opnSavePhys('${iid}')"
                               ${!CAN_CREATE ? 'disabled' : ''}>
                        <div id="phys-buy-${iid}" class="text-muted text-end" style="font-size:.72rem;min-height:.9rem">${physBuyInit}</div>
                    </td>
                    <td class="text-end">${selisihHtml(p.selisih, iid)}</td>
                    ${adjColHtml(p, iid)}
                    ${actionCell(p, iid)}
                </tr>`;
            });
        });
    });
    tbody.innerHTML = html;
    initTooltips(tbody);

function buildLabelSingle(p, contentUom) {
    let h = `<div class="fw-semibold">${esc(p.material_name || p.item_name)}</div>`;
    const subParts = [p.profile_name, p.profile_brand, p.profile_description].filter(Boolean);
    const expBadge = p.profile_expired_date
        ? ` <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ${esc(p.profile_expired_date)}</span>` : '';
    if (subParts.length) {
        h += `<div class="text-muted" style="font-size:.76rem">${esc(subParts.join(' · '))}${expBadge}</div>`;
    } else if (expBadge) {
        h += `<div>${expBadge}</div>`;
    }
    if (p.avg_cost_per_content > 0) {
        h += `<div class="text-muted" style="font-size:.72rem">${fmtRp(p.avg_cost_per_content)}/${contentUom}</div>`;
    }
    return h;
}

function buildLabelSub(p) {
    const expBadge = p.profile_expired_date
        ? ` <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ${esc(p.profile_expired_date)}</span>` : '';
    const subParts = [p.profile_brand, p.profile_description].filter(Boolean);
    let h = `<div style="padding-left:1.25rem">`;
    h += `<span class="fw-semibold" style="font-size:.82rem">${esc(p.profile_name || '')}${expBadge}</span>`;
    if (subParts.length) h += ` <span class="text-muted" style="font-size:.74rem">· ${esc(subParts.join(' · '))}</span>`;
    if (p.avg_cost_per_content > 0) h += ` <span class="text-muted" style="font-size:.72rem">· ${fmtRp(p.avg_cost_per_content)}</span>`;
    h += `</div>`;
    return h;
}
}

window.opnLiveCalc = function (inp, iid) {
    const p    = profileMap[iid];
    const sys  = parseFloat(inp.dataset.sys) || 0;
    const phys = inp.value.trim() !== '' ? parseFloat(inp.value) : null;
    const selEl = el('sel-' + iid);
    if (!selEl) return;

    const buyEl  = el('phys-buy-' + iid);
    const cpb    = parseFloat(inp.dataset.cpb) || 0;
    const buyUom = inp.dataset.buyuom || '';

    if (phys === null) {
        selEl.textContent = '—'; selEl.className = 'text-muted small';
        if (buyEl) buyEl.textContent = '';
        if (p) { p.selisih = null; _liveUpdateAdjRow(iid, p); }
        return;
    }

    const v    = phys - sys;
    const sign = v > 0 ? '+' : '';
    selEl.className   = Math.abs(v) < 0.001 ? 'text-success fw-bold' : (v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold');
    selEl.textContent = Math.abs(v) < 0.001 ? '± 0' : sign + v.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 4 });

    if (buyEl && cpb > 0 && buyUom) {
        buyEl.textContent = fmt4(phys / cpb) + ' ' + buyUom;
    }

    if (p) {
        p.selisih = Math.abs(v) >= 0.001 ? v : null;
        _liveUpdateAdjRow(iid, p);
    }
};

function _liveUpdateAdjRow(iid, p) {
    if (!p || p.adjustment_id) return;
    const existingType = el('adjtype-' + iid);
    if (existingType && existingType.value) return;

    const adjColEl = el('adjcol-' + iid);
    if (adjColEl) adjColEl.outerHTML = adjColHtml(p, iid);

    const row = el('row-' + iid);
    if (row) {
        const tdAction = row.querySelector('td.action-cell');
        if (tdAction) tdAction.outerHTML = actionCell(p, iid);
    }
}

window.opnSavePhys = function (iid) {
    const p   = profileMap[iid];
    const row = el('row-' + iid);
    const inp = row ? row.querySelector('input[type="number"]') : null;
    if (!p || !inp) return;
    clearTimeout(saveTimers[iid]);
    saveTimers[iid] = setTimeout(function () { doSave(inp, p); }, 700);
};

function doSave(inp, p) {
    const form    = el('opnForm');
    const date    = form.querySelector('[name=opname_date]').value;
    const dest    = form.querySelector('[name=destination]').value;
    const physRaw = inp ? inp.value.trim() : null;
    const phys    = physRaw !== null && physRaw !== '' ? parseFloat(physRaw) : null;

    const payload = Object.assign({}, p, {
        opname_date:          date,
        physical_qty_content: phys,
        destination_type:     dest === 'ALL' ? (p.destination_type || 'OTHER') : dest,
        notes:                '',
    });

    fetch(SAVE_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
    .then(function (res) {
        if (!res.j.ok) { showAlert('danger', res.j.message || 'Gagal menyimpan.'); return; }
        p.physical_qty_content = res.j.data.physical_qty_content;
        p.selisih              = res.j.data.selisih;
        const iid = cssid(p.division_id + '_' + p.identity_key);

        const selEl = el('sel-' + iid);
        if (selEl) {
            const v = p.selisih;
            if (v === null) { selEl.textContent = '—'; selEl.className = 'text-muted small'; }
            else if (Math.abs(v) < 0.001) { selEl.textContent = '± 0'; selEl.className = 'text-success fw-bold'; }
            else { const s = v > 0 ? '+' : ''; selEl.className = v < 0 ? 'text-danger fw-bold' : 'text-warning fw-bold'; selEl.textContent = s + fmt4(v); }
        }

        const row = el('row-' + iid);
        if (row) {
            const tdAction = row.querySelector('td.action-cell');
            if (tdAction) { tdAction.outerHTML = actionCell(p, iid); }
            const tdAdj = el('adjcol-' + iid);
            if (tdAdj) { tdAdj.outerHTML = adjColHtml(p, iid); }
            initTooltips(row);
        }
        updateSummary();
    })
    .catch(function (e) { showAlert('danger', 'Error: ' + e.message); });
}

window.opnToggleGrp = function (grpIid) {
    const children = document.querySelectorAll('[data-grp="' + grpIid + '"]');
    const icon     = el('grp-icon-' + grpIid);
    if (!children.length) return;

    const willExpand = children[0].style.display === 'none';
    children.forEach(function (row) {
        row.style.display = willExpand ? '' : 'none';
    });

    if (icon) {
        icon.className = willExpand
            ? 'ri ri-arrow-down-s-line text-primary me-1'
            : 'ri ri-arrow-right-s-line text-muted me-1';
        icon.style.fontSize = '1rem';
        icon.style.verticalAlign = 'middle';
    }
};

window.opnTypeChange = function (iid) {
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
        .map(function ([k, v]) { return `<option value="${k}">${v}</option>`; })
        .join('');
    reasonSel.classList.remove('d-none');
    if (notesInp) notesInp.classList.remove('d-none');
};

window.opnPostAdj = function (iid) {
    const p = profileMap[iid];
    if (!p) return;

    const typeVal = el('adjtype-' + iid)?.value;
    if (!typeVal) {
        showAlert('warning', 'Pilih jenis adjustment terlebih dahulu.');
        el('adjtype-' + iid)?.focus();
        return;
    }

    const btn = el('adjbtn-' + iid);
    if (!btn) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    const form = el('opnForm');
    const date = form.querySelector('[name=opname_date]').value;
    const dest = form.querySelector('[name=destination]').value;

    const payload = Object.assign({}, p, {
        opname_date:      date,
        destination_type: dest === 'ALL' ? (p.destination_type || 'OTHER') : dest,
        adjustment_type:  typeVal,
        reason_code:      el('adjreason-' + iid)?.value || 'other',
        notes:            el('adjnotes-'  + iid)?.value?.trim() || '',
    });

    fetch(ADJ_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
    .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = orig;
        if (res.s >= 400 || !res.j.ok) {
            showAlert('danger', res.j.message || 'Terjadi kesalahan.');
            return;
        }
        const adjId = res.j.data?.adjustment_id;
        showAlert('success', 'Adjustment #' + adjId + ' berhasil diposting.');
        p.adjustment_id = adjId;

        const row = el('row-' + iid);
        if (row) {
            const tdAction = row.querySelector('td.action-cell');
            if (tdAction) { tdAction.outerHTML = actionCell(p, iid); }
            const tdAdj = el('adjcol-' + iid);
            if (tdAdj) { tdAdj.outerHTML = adjColHtml(p, iid); }
            initTooltips(row);
        }
        updateSummary();
    })
    .catch(function (e) {
        btn.disabled = false;
        btn.innerHTML = orig;
        showAlert('danger', 'Error: ' + e.message);
    });
};

function loadData(showSpinner) {
    const form  = el('opnForm');
    const date  = form.querySelector('[name=opname_date]').value;
    const divId = form.querySelector('[name=division_id]').value;
    const dest  = form.querySelector('[name=destination]').value;
    const q     = form.querySelector('[name=q]').value;

    el('opnEmpty').classList.add('d-none');
    el('opnNoResult').classList.add('d-none');
    if (showSpinner !== false) {
        el('opnLoading').classList.remove('d-none');
        el('opnTableWrap').style.display = 'none';
        el('opnSummary').style.display = 'none';
    }

    fetch(DATA_URL + '?opname_date=' + encodeURIComponent(date)
        + '&division_id=' + encodeURIComponent(divId)
        + '&destination=' + encodeURIComponent(dest)
        + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        el('opnLoading').classList.add('d-none');
        if (!data.ok) { showAlert('danger', data.message || 'Gagal memuat data.'); return; }
        currentData = data.rows || [];
        renderTable(currentData);
        updateSummary();
    })
    .catch(function (e) {
        el('opnLoading').classList.add('d-none');
        showAlert('danger', 'Error memuat data: ' + e.message);
    });
}

el('opnLoad').addEventListener('click', function () { loadData(true); });
el('opnRefresh').addEventListener('click', function () { loadData(false); });
el('opnForm').querySelector('[name=q]').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); loadData(true); }
});
initTooltips();
loadData(true);

})();
</script>
