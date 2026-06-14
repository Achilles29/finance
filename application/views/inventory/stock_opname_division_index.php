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
        'improper_storage'  => 'Improper Storage',
        'overstock'         => 'Overstock',
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

<style>
/* ── Table layout ──────────────────────────────────────── */
#opnTableWrap .table-responsive { max-height:calc(100vh - 290px); overflow-y:auto; }
#opnTableWrap table { border-collapse:collapse; }
#opnTableWrap thead th {
  position:sticky; top:0; z-index:2;
  font-size:.73rem; font-weight:700; letter-spacing:.03em; white-space:nowrap;
  color:#334155; background:#e2e8f0;
  border-bottom:2px solid #94a3b8 !important; border-top:none !important;
}

/* Row borders + stripes */
#opnTbody tr            { border-bottom:1px solid #e2e8f0; }
#opnTbody tr > td       { border:none; padding-top:5px; padding-bottom:5px; }
#opnTbody tr:nth-child(even) > td { background:#f8fafc; }
#opnTbody tr:nth-child(odd)  > td { background:#ffffff; }
#opnTbody tr:hover > td      { background:#eff6ff !important; }

/* Arrow icon */
.opn-grp-arrow { transition:transform .18s, color .18s; }
tr.opn-grp-header.expanded .opn-grp-arrow { transform:rotate(90deg); color:#3b82f6 !important; }
.opn-arrow-wrap { display:inline-flex; width:16px; flex-shrink:0; align-items:center; }

/* Name cell */
.opn-name-cell { display:flex; align-items:flex-start; }
.opn-name-body { min-width:0; }

/* Negative stock rows */
.opn-row-minus > td { background:#fff1f2 !important; }
.opn-row-minus > td:first-child { border-left:3px solid #f87171; }
.opn-row-minus:hover > td { background:#fee2e2 !important; }
.opn-row-minus .fw-semibold,
.opn-row-minus .fw-bold { color:#991b1b !important; }
.opn-row-minus .text-muted { color:#b91c1c !important; opacity:.75; }

/* Tipe / Destinasi badges */
.opn-tipe-event   { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.opn-tipe-reguler { background:#f1f5f9; color:#64748b;  border:1px solid #cbd5e1; padding:1px 6px; border-radius:999px; font-size:.65rem; font-weight:700; white-space:nowrap; display:inline-block; }
.opn-kat-cell     { font-size:.72rem; color:#374151; white-space:nowrap; }

/* Divisi cell */
.opn-div-cell { font-size:.72rem; color:#1e40af; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:90px; }

/* Minus filter button — active state */
#btnFilterMinus.filter-on { background:#dc3545; border-color:#dc3545; color:#fff; }
</style>

<div class="fin-page-header">
    <div>
        <p class="fin-breadcrumb">
            <a href="<?= base_url('dashboard') ?>">Dashboard</a> / Bahan Baku Divisi / Daily Recon
        </p>
        <h4 class="fin-page-title">
            <i class="ri ri-check-double-line me-1 text-primary"></i>
            Daily Recon
        </h4>
        <p class="fin-page-subtitle">Input stok fisik harian · selisih vs sistem · posting adjustment langsung</p>
    </div>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
    <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'daily_recon']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
    'division_action_params' => ['month' => substr($adjustDate, 0, 7), 'division_id' => (string)$divisionId, 'destination_type' => $destination],
]); ?>

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
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Total Profil</div><div class="h5 mb-0 fw-bold" id="smTotal">0</div></div>
                    <i class="ri ri-stack-line ri-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Sudah Diisi</div><div class="h5 mb-0 fw-bold text-success" id="smFilled">0</div></div>
                    <i class="ri ri-checkbox-circle-line ri-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Ada Selisih</div><div class="h5 mb-0 fw-bold text-danger" id="smMiss">0</div></div>
                    <i class="ri ri-alert-line ri-2x text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Disesuaikan</div><div class="h5 mb-0 fw-bold text-info" id="smAdj">0</div></div>
                    <i class="ri ri-git-commit-line ri-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100" style="border-left:3px solid #f87171 !important;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Stok Minus</div><div class="h5 mb-0 fw-bold text-danger" id="smMinus">0</div></div>
                    <i class="ri ri-arrow-down-circle-line ri-2x text-danger opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md">
        <div class="card border-0 shadow-sm h-100" style="border-left:3px solid #3b82f6 !important;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div><div class="small text-muted">Nilai Stok Sistem</div><div class="h6 mb-0 fw-bold text-primary" id="smNilai" style="font-size:.88rem">—</div></div>
                    <i class="ri ri-currency-line ri-2x text-primary opacity-50"></i>
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
        <div class="d-flex justify-content-between align-items-center px-3 pt-2 pb-1 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted" id="opnInfo"></small>
                <span id="opnMinusBadge" class="badge bg-danger-subtle text-danger border border-danger-subtle" style="display:none;font-size:.7rem;cursor:pointer" title="Klik untuk filter stok minus"></span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-danger" id="btnFilterMinus" style="display:none">
                    <i class="ri ri-arrow-down-circle-line me-1"></i>Hanya Minus
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="opnRefresh">
                    <i class="ri ri-refresh-line me-1"></i>Refresh
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
                <thead>
                    <tr>
                        <th style="width:82px">Divisi</th>
                        <th style="width:66px">Tipe</th>
                        <th style="width:72px">Destinasi</th>
                        <th class="text-start" style="min-width:190px">Bahan Baku / Profil</th>
                        <th style="width:60px">UOM</th>
                        <th class="text-end" style="width:96px">Sistem</th>
                        <th class="text-end" style="width:100px">Fisik</th>
                        <th class="text-end" style="width:82px">Selisih</th>
                        <th style="min-width:220px">Jenis &amp; Alasan</th>
                        <th style="width:90px">Aksi</th>
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
    { val: 'WASTE',         lbl: 'Waste'          },
    { val: 'SPOIL',         lbl: 'Spoil'          },
    { val: 'PROCESS_LOSS',  lbl: 'Process Loss'   },
    { val: 'VARIANCE',      lbl: 'Variance / Minus' },
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
    let total = 0, filled = 0, miss = 0, adj = 0, minus = 0, nilaiTotal = 0;
    currentData.forEach(function (div) {
        div.materials.forEach(function (mat) {
            mat.profiles.forEach(function (p) {
                total++;
                const qty  = parseFloat(p.system_qty_content) || 0;
                const cost = parseFloat(p.avg_cost_per_content || p.total_value_per_content || 0);
                if (qty < -0.001) minus++;
                nilaiTotal += (p.total_value != null ? parseFloat(p.total_value) : qty * cost);
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
    const smMinus = el('smMinus');
    if (smMinus) smMinus.textContent = minus;
    const smNilai = el('smNilai');
    if (smNilai) smNilai.textContent = 'Rp ' + nilaiTotal.toLocaleString('id-ID', { maximumFractionDigits: 0 });
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

    // ── Destination helpers ───────────────────────────────
    function destInfo(destType) {
        const map = {
            'BAR':           { kat: 'Bar',     event: false },
            'KITCHEN':       { kat: 'Kitchen', event: false },
            'BAR_EVENT':     { kat: 'Bar',     event: true  },
            'KITCHEN_EVENT': { kat: 'Kitchen', event: true  },
            'OFFICE':        { kat: 'Office',  event: false },
            'OTHER':         { kat: 'Other',   event: false },
        };
        return map[destType] || { kat: destType || '—', event: false };
    }
    function tipeBadge(destType) {
        const d = destInfo(destType);
        return d.event
            ? `<span class="opn-tipe-event">Event</span>`
            : `<span class="opn-tipe-reguler">Reguler</span>`;
    }
    function katCell(destType) {
        return `<td class="opn-kat-cell">${esc(destInfo(destType).kat)}</td>`;
    }

    let html = '';
    let minusCount = 0;
    divisions.forEach(function (div) {

        div.materials.forEach(function (mat) {
            const multiProf = mat.profiles.length > 1;
            const grpIid = multiProf ? cssid(div.division_id + '_grp_' + mat.material_id) : null;

            if (multiProf) {
                const sysTotal  = mat.system_total;
                const physTotal = mat.physical_total;
                const selTotal  = physTotal !== null ? physTotal - sysTotal : null;
                const grpNeg    = sysTotal !== null && parseFloat(sysTotal) < -0.001;
                if (grpNeg) minusCount++;
                html += `<tr id="grp-row-${grpIid}" class="opn-grp-header${grpNeg ? ' opn-row-minus' : ''}"
                         data-system-val="${sysTotal}" data-div-id="${esc(String(div.division_id))}"
                         onclick="opnToggleGrp('${grpIid}')">
                    <td class="opn-div-cell">${esc(div.division_name)}</td>
                    <td></td>
                    <td></td>
                    <td class="text-start" style="font-size:.8rem;">
                        <div class="opn-name-cell">
                            <span class="opn-arrow-wrap"><i id="grp-icon-${grpIid}" class="ri ri-arrow-right-s-line opn-grp-arrow text-muted" style="font-size:1.05rem"></i></span>
                            <div class="opn-name-body">
                                <span class="fw-semibold">${esc(mat.material_name)}</span>
                                <span class="text-muted fw-normal ms-1" style="font-size:.74rem">(${mat.profiles.length} profil)</span>
                            </div>
                        </div>
                    </td>
                    <td></td>
                    <td class="text-end">${fmt4(sysTotal)}</td>
                    <td></td>
                    <td class="text-end">${selisihHtml(selTotal, grpIid)}</td>
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

                const uomCell = showBuy
                    ? `<td style="font-size:.8rem">${contentUom}<div class="text-muted" style="font-size:.72rem">${buyUom}</div></td>`
                    : `<td class="text-muted" style="font-size:.8rem">${contentUom}</td>`;

                const sysBuy = p.system_qty_buy > 0
                    ? p.system_qty_buy
                    : (cpb > 0 ? p.system_qty_content / cpb : null);
                const sistemCell = showBuy && sysBuy !== null
                    ? `<td class="text-end" style="font-size:.85rem">${fmt4(p.system_qty_content)}<div class="text-muted" style="font-size:.72rem">${fmt4(sysBuy)} ${buyUom}</div></td>`
                    : `<td class="text-end">${fmt4(p.system_qty_content)}</td>`;

                const physBuyInit = physVal !== '' && cpb > 0 && showBuy
                    ? fmt4(parseFloat(physVal) / cpb) + ' ' + buyUom : '';

                const matLabel = !multiProf
                    ? buildLabelSingle(p, contentUom)
                    : buildLabelSub(p);

                const profNeg   = parseFloat(p.system_qty_content) < -0.001;
                if (profNeg && !multiProf) minusCount++;
                const profClass = profNeg ? 'opn-row-minus' : '';
                const profAttrs = multiProf
                    ? `class="${profClass}" data-grp="${grpIid}" data-system-val="${p.system_qty_content}" data-div-id="${esc(String(div.division_id))}" style="display:none"`
                    : `class="${profClass}" data-system-val="${p.system_qty_content}" data-div-id="${esc(String(div.division_id))}"`;

                html += `<tr id="row-${iid}" ${profAttrs}>
                    <td class="opn-div-cell">${esc(div.division_name)}</td>
                    <td>${tipeBadge(p.destination_type)}</td>
                    ${katCell(p.destination_type)}
                    <td class="text-start">${matLabel}</td>
                    ${uomCell}
                    ${sistemCell}
                    <td class="text-end">
                        <input type="number" class="form-control form-control-sm text-end"
                               style="width:82px;display:inline-block"
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

    const minusBadge = el('opnMinusBadge');
    const filterBtn  = el('btnFilterMinus');
    if (minusBadge) {
        minusBadge.textContent = minusCount + ' stok minus';
        minusBadge.style.display = minusCount > 0 ? '' : 'none';
    }
    if (filterBtn) filterBtn.style.display = minusCount > 0 ? '' : 'none';

function buildLabelSingle(p, contentUom) {
    let inner = `<div class="fw-semibold">${esc(p.material_name || p.item_name)}</div>`;
    const subParts = [p.profile_name, p.profile_brand, p.profile_description].filter(Boolean);
    const expBadge = p.profile_expired_date
        ? ` <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ${esc(p.profile_expired_date)}</span>` : '';
    if (subParts.length) {
        inner += `<div class="text-muted" style="font-size:.76rem">${esc(subParts.join(' · '))}${expBadge}</div>`;
    } else if (expBadge) {
        inner += `<div>${expBadge}</div>`;
    }
    if (p.avg_cost_per_content > 0) {
        inner += `<div class="text-muted" style="font-size:.72rem">${fmtRp(p.avg_cost_per_content)}/${contentUom}</div>`;
    }
    return `<div class="opn-name-cell"><span class="opn-arrow-wrap"></span><div class="opn-name-body">${inner}</div></div>`;
}

function buildLabelSub(p) {
    const expBadge = p.profile_expired_date
        ? ` <span class="badge bg-danger-subtle text-danger" style="font-size:.62rem">exp ${esc(p.profile_expired_date)}</span>` : '';
    const subParts = [p.profile_brand, p.profile_description].filter(Boolean);
    let inner = `<span class="fw-semibold" style="font-size:.82rem">${esc(p.profile_name || '')}${expBadge}</span>`;
    if (subParts.length) inner += ` <span class="text-muted" style="font-size:.74rem">· ${esc(subParts.join(' · '))}</span>`;
    if (p.avg_cost_per_content > 0) inner += ` <span class="text-muted" style="font-size:.72rem">· ${fmtRp(p.avg_cost_per_content)}</span>`;
    return `<div class="opn-name-cell"><span class="opn-arrow-wrap"></span><div class="opn-name-body" style="padding-left:.6rem">${inner}</div></div>`;
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
    const hdrRow   = el('grp-row-' + grpIid);
    if (!children.length) return;

    const willExpand = children[0].style.display === 'none';
    children.forEach(function (row) {
        // When minus filter is on, only show children that are minus
        if (showOnlyMinus && willExpand) {
            const v = parseFloat(row.dataset.systemVal || 0);
            row.style.display = v < -0.001 ? '' : 'none';
        } else {
            row.style.display = willExpand ? '' : 'none';
        }
    });

    if (icon) {
        icon.className = 'ri opn-grp-arrow me-1 ' + (willExpand
            ? 'ri-arrow-down-s-line text-primary'
            : 'ri-arrow-right-s-line text-muted');
        icon.style.fontSize = '1.05rem';
    }
    if (hdrRow) hdrRow.classList.toggle('expanded', willExpand);
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

// ── Minus filter ─────────────────────────────────────────────
let showOnlyMinus = false;

function applyMinusFilter() {
    const tbody = el('opnTbody');
    if (!tbody) return;
    const rows    = Array.from(tbody.querySelectorAll('tr'));
    const btn     = el('btnFilterMinus');
    const badge   = el('opnMinusBadge');

    if (btn) btn.classList.toggle('filter-on', showOnlyMinus);
    if (badge) badge.style.fontWeight = showOnlyMinus ? '700' : '';

    if (!showOnlyMinus) {
        // Restore: show grp headers, collapse child rows as before
        rows.forEach(function (r) {
            if (r.classList.contains('opn-grp-header')) {
                r.style.display = '';
                return;
            }
            if (r.dataset.grp) {
                const icon = el('grp-icon-' + r.dataset.grp);
                const expanded = icon && icon.classList.contains('ri-arrow-down-s-line');
                r.style.display = expanded ? '' : 'none';
                return;
            }
            r.style.display = '';
        });
        return;
    }

    // Show only rows where system stock is negative
    const visibleGrps = new Set();

    // First pass: profile rows
    rows.forEach(function (r) {
        if (r.classList.contains('opn-grp-header')) return;
        const v = parseFloat(r.dataset.systemVal || 0);
        if (v < -0.001) {
            r.style.display = '';
            if (r.dataset.grp) visibleGrps.add(r.dataset.grp);
        } else {
            r.style.display = 'none';
        }
    });

    // Second pass: group header rows
    rows.forEach(function (r) {
        if (!r.classList.contains('opn-grp-header')) return;
        const grpIid = r.id.replace('grp-row-', '');
        if (visibleGrps.has(grpIid)) {
            r.style.display = '';
            const icon = el('grp-icon-' + grpIid);
            if (icon) {
                icon.className = 'ri opn-grp-arrow me-1 ri-arrow-down-s-line text-primary';
            }
            r.classList.add('expanded');
        } else {
            const v = parseFloat(r.dataset.systemVal || 0);
            r.style.display = v < -0.001 ? '' : 'none';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('#btnFilterMinus') || e.target.closest('#opnMinusBadge')) {
        showOnlyMinus = !showOnlyMinus;
        applyMinusFilter();
    }
});

function loadData(showSpinner) {
    const form  = el('opnForm');
    const date  = form.querySelector('[name=opname_date]').value;
    const divId = form.querySelector('[name=division_id]').value;
    const dest  = form.querySelector('[name=destination]').value;
    const q     = form.querySelector('[name=q]').value;

    el('opnEmpty').classList.add('d-none');
    el('opnNoResult').classList.add('d-none');
    if (showSpinner !== false) {
        showOnlyMinus = false;
        const fb = el('btnFilterMinus');
        if (fb) fb.classList.remove('filter-on');
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
