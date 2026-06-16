<?php
$baseUrl      = site_url('inventory/stock/division/reconcile');
$auditUrl     = site_url('inventory/stock/division/reconcile/audit');
$repairUrl    = site_url('inventory/stock/division/reconcile/repair');
$lotRepairUrl        = site_url('inventory/stock/division/reconcile/lot-repair');
$lotProfileSyncUrl   = site_url('inventory/stock/division/reconcile/lot-profile-sync');
$lotRepairAllUrl     = site_url('inventory/stock/division/reconcile/lot-repair-all');
$gapRepairAllUrl     = site_url('inventory/stock/division/reconcile/gap-repair-all');
$repairMaterialIdUrl    = site_url('inventory/stock/division/reconcile/repair-material-id');
$profileRepairUrl       = site_url('inventory/stock/division/reconcile/profile-repair');

$allRows     = is_array($rows ?? null) ? $rows : [];
$divisions   = is_array($divisions ?? null) ? $divisions : [];
$summary     = is_array($summary ?? null) ? $summary : [];
$orphanStock = is_array($orphan_stock ?? null) ? $orphan_stock : ['fixable' => [], 'no_material' => [], 'total' => 0];
$orphanFixable   = (array)($orphanStock['fixable'] ?? []);
$orphanNoMat     = (array)($orphanStock['no_material'] ?? []);
$orphanTotal     = (int)($orphanStock['total'] ?? 0);
$destGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];

$asOfDate    = (string)($as_of_date ?? date('Y-m-d'));
$qSearch     = (string)($q ?? '');
$selDivId    = (int)($division_id ?? 0);
$selDest     = strtoupper((string)($destination ?? 'ALL'));
$perPage     = max(10, (int)($per_page ?? 25));
$page        = max(1, (int)($page ?? 1));

$mismatchOnly = !empty($_GET['mismatch_only']);

// ── Filter zero-balance rows ──────────────────────────────────────────────────
$rows = array_values(array_filter($allRows, static function (array $row) use ($mismatchOnly): bool {
    if (abs(round((float)($row['balance_qty_content'] ?? 0), 2)) < 0.01) return false;
    if ($mismatchOnly && !empty($row['is_match'])) return false;
    return true;
}));

// ── KPI ─────────────────────────────────────────────────────────────────────
$kpiTotal     = count($rows);
$kpiMatch     = 0;
$kpiMismatch  = 0;
$kpiMinus     = 0;
$kpiDivSet    = [];
$kpiDeltaSum  = 0.0;

// Per-division breakdown for heatmap
$divMatchMap  = [];  // div_label => [match, mismatch, minus]

foreach ($rows as $r) {
    $isMatch   = !empty($r['is_match']);
    $isMinus   = (float)($r['balance_qty_content'] ?? 0) < 0;
    $delta     = abs((float)($r['delta_balance_vs_movement'] ?? 0));
    if ($isMatch) $kpiMatch++; else $kpiMismatch++;
    if ($isMinus) $kpiMinus++;
    $kpiDeltaSum += $delta;
    $divId = (int)($r['division_id'] ?? 0);
    if ($divId > 0) $kpiDivSet[$divId] = true;
    $dc   = trim((string)($r['division_code'] ?? ''));
    $dn   = trim((string)($r['division_name'] ?? ''));
    $dkey = $dc !== '' ? $dc : ($dn ?: (string)$divId);
    if (!isset($divMatchMap[$dkey])) $divMatchMap[$dkey] = ['label' => $dkey, 'match' => 0, 'mismatch' => 0, 'minus' => 0];
    if ($isMatch) $divMatchMap[$dkey]['match']++;
    else          $divMatchMap[$dkey]['mismatch']++;
    if ($isMinus) $divMatchMap[$dkey]['minus']++;
}
$kpiDivCount = count($kpiDivSet);
$healthPct   = $kpiTotal > 0 ? round($kpiMatch / $kpiTotal * 100) : 100;

// Sort divisions by mismatch desc
uasort($divMatchMap, static fn($a,$b) => $b['mismatch'] - $a['mismatch']);

// ── Pagination ───────────────────────────────────────────────────────────────
$totalRows  = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$pagedRows  = array_slice($rows, ($page - 1) * $perPage, $perPage);
$pBase      = array_filter(['as_of_date' => $asOfDate, 'division_id' => $selDivId ?: null, 'destination' => $selDest !== 'ALL' ? $selDest : null, 'q' => $qSearch, 'per_page' => $perPage, 'mismatch_only' => $mismatchOnly ? '1' : null]);
$pQs        = http_build_query($pBase);
$mismatchToggleBase = $pBase;
if ($mismatchOnly) { unset($mismatchToggleBase['mismatch_only']); } else { $mismatchToggleBase['mismatch_only'] = '1'; }
$mismatchToggleUrl  = $baseUrl . '?' . http_build_query($mismatchToggleBase);

$fmtQty     = static fn($v): string => number_format((float)$v, 2, ',', '.');
$fmtText    = static fn($v, string $fb = '-'): string => trim((string)$v) !== '' ? trim((string)$v) : $fb;
?>

<style>
/* ── Filter ── */
.rec-filter-grid {
  display:grid;
  grid-template-columns: minmax(100px,1fr) minmax(130px,1.6fr) minmax(96px,1fr) minmax(150px,2fr) 84px auto;
  gap:.5rem; align-items:end;
}
@media(max-width:991px)  { .rec-filter-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .rec-filter-grid { grid-template-columns:1fr 1fr; } }

/* ── KPI ── */
.rec-kpi-row { display:grid; grid-template-columns:repeat(5,1fr) 180px; gap:.6rem; margin-bottom:1rem; align-items:stretch; }
@media(max-width:1199px) { .rec-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:575px)  { .rec-kpi-row { grid-template-columns:1fr 1fr; } }
.rec-kpi {
  border-radius:14px; padding:.95rem 1.1rem .9rem; color:#fff;
  position:relative; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.13);
}
.rec-kpi::before { content:''; position:absolute; right:-16px; bottom:-16px; width:76px; height:76px; border-radius:50%; background:rgba(255,255,255,.12); }
.rec-kpi::after  { content:''; position:absolute; right:12px; top:-20px; width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.08); }
.rec-kpi-icon { font-size:1.2rem; opacity:.82; margin-bottom:.3rem; display:block; }
.rec-kpi-val  { font-size:1.35rem; font-weight:800; line-height:1.1; }
.rec-kpi-sub  { font-size:.7rem; opacity:.75; margin-top:.1rem; }
.rec-kpi-lbl  { font-size:.67rem; opacity:.82; text-transform:uppercase; letter-spacing:.06em; margin-top:.2rem; }
.rec-kpi-1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.rec-kpi-2 { background:linear-gradient(135deg,#11998e,#38ef7d); color:#0b3b34; }
.rec-kpi-3-ok   { background:linear-gradient(135deg,#374151,#6b7280); }
.rec-kpi-3-warn { background:linear-gradient(135deg,#c92a2a,#fa5252); }
.rec-kpi-4 { background:linear-gradient(135deg,#e55d2b,#f7b733); }
.rec-kpi-5 { background:linear-gradient(135deg,#0c7cba,#0fcdba); }

/* ── SURPRISE: Health ring KPI ── */
.rec-health-card {
  border-radius:14px; padding:.9rem 1.1rem; color:#fff;
  display:flex; align-items:center; gap:.9rem;
  box-shadow:0 4px 18px rgba(0,0,0,.13);
  position:relative; overflow:hidden;
}
.rec-health-ring-wrap { flex-shrink:0; position:relative; width:68px; height:68px; }
.rec-health-ring {
  width:68px; height:68px; border-radius:50%;
  background: conic-gradient(
    var(--ring-fill) 0% var(--ring-pct),
    rgba(255,255,255,.18) var(--ring-pct) 100%
  );
  display:flex; align-items:center; justify-content:center;
}
.rec-health-ring-inner {
  width:48px; height:48px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-weight:900; font-size:.9rem; line-height:1;
}
.rec-health-ok   { background:linear-gradient(135deg,#2f9e44,#69db7c); color:#0b3b34; }
.rec-health-warn { background:linear-gradient(135deg,#d97706,#fbbf24); color:#1a0a00; }
.rec-health-bad  { background:linear-gradient(135deg,#c92a2a,#fa5252); }
.rec-health-ok   .rec-health-ring-inner  { background:rgba(0,0,0,.12); color:#f0fff4; }
.rec-health-warn .rec-health-ring-inner  { background:rgba(0,0,0,.10); color:#1a0a00; }
.rec-health-bad  .rec-health-ring-inner  { background:rgba(0,0,0,.12); color:#fff; }

/* ── Division mismatch bar ── */
.rec-div-bar-wrap { padding:.7rem 1rem; background:#faf6f3; border-bottom:1px solid #f0e0d4; }
.rec-div-bar { display:flex; flex-direction:column; gap:.3rem; }
.rec-div-bar-row { display:flex; align-items:center; gap:.6rem; }
.rec-div-bar-label { width:80px; font-size:.72rem; font-weight:700; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex-shrink:0; }
.rec-div-bar-track { flex:1; height:8px; border-radius:999px; background:#e9ecef; overflow:hidden; display:flex; }
.rec-div-bar-match    { height:100%; background:#2f9e44; }
.rec-div-bar-mismatch { height:100%; background:#c92a2a; }
.rec-div-bar-count { font-size:.69rem; color:#6b7280; white-space:nowrap; flex-shrink:0; width:60px; text-align:right; }

/* ── POS Jobs accordion ── */
.rec-pos-panel { border:1px solid #bfdbfe; border-radius:12px; background:#eff6ff; overflow:hidden; margin-bottom:1rem; }
.rec-pos-header {
  display:flex; align-items:center; justify-content:space-between; cursor:pointer;
  padding:.65rem 1rem; background:linear-gradient(135deg,#dbeafe,#bfdbfe);
  user-select:none;
}
.rec-pos-body { display:none; padding:1rem; }
.rec-pos-body.open { display:block; }

/* ── Table ── */
.rec-table-wrap { overflow-x:auto; overflow-y:auto; max-height:65vh; }
.rec-table-wrap table { min-width:1360px; border-collapse:separate; border-spacing:0; }
.rec-table-wrap thead th {
  position:sticky; top:0; z-index:4;
  background:linear-gradient(180deg,#312028 0%,#4a2f3a 100%);
  color:#ffe4dc; white-space:nowrap;
  border-bottom:1px solid #3d2430; font-size:.73rem; font-weight:700; letter-spacing:.03em;
}
.rec-row-negative td { background:#fff0f0 !important; }
.rec-row-negative td:first-child { border-left:3px solid #c92a2a; }
.rec-chip {
  display:inline-flex; align-items:center; padding:.22rem .6rem;
  border-radius:999px; font-size:.68rem; font-weight:800; white-space:nowrap;
}
.rec-chip-ok   { background:#d3f9d8; color:#2f9e44; border:1px solid #b2f2bb; }
.rec-chip-bad  { background:#fde9e8; color:#b42318; border:1px solid #fca5a5; }
.rec-chip-warn { background:#fff4e5; color:#e67700; border:1px solid #ffd8a8; }
.rec-delta-ok  { color:#2f9e44; font-weight:800; }
.rec-delta-bad { color:#b42318; font-weight:800; }

/* ── Material audit drawer ── */
.rec-audit-drawer {
  border:1px solid rgba(225,210,199,.82); border-radius:22px;
  background:#fff; box-shadow:0 16px 34px rgba(58,38,30,.08);
}
.rec-audit-summary {
  display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.8rem; margin-bottom:1rem;
}
@media(max-width:991px) { .rec-audit-summary { grid-template-columns:repeat(2,1fr); } }
.rec-audit-metric { border:1px solid rgba(225,210,199,.82); border-radius:14px; background:#fffaf7; padding:.8rem .9rem; }
.rec-audit-metric .label { display:block; font-size:.7rem; color:#8a776f; text-transform:uppercase; letter-spacing:.04em; font-weight:800; margin-bottom:.15rem; }
.rec-audit-metric .value { font-size:1.1rem; font-weight:900; color:#2f2628; }

/* ── Pagination ── */
.rec-pagination { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; }
.rec-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 .5rem;
  border:1px solid #ddd; border-radius:8px; background:#fff; color:#555;
  font-size:.8rem; font-weight:600; text-decoration:none; transition:all .15s;
}
.rec-page-btn:hover   { background:#f5f5f5; border-color:#bbb; color:#333; }
.rec-page-btn.active  { background:#312028; border-color:#312028; color:#fff; }
.rec-page-btn.disabled { opacity:.4; pointer-events:none; }
</style>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-start mb-2 gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-git-merge-line page-title-icon"></i>Rekonsiliasi Stok Divisi</h4>
    <small class="text-muted">Patokan stok akhir mengikuti snapshot monthly divisi. Material daily dan snapshot harian diperlakukan sebagai turunan tampilan — movement mentah dipakai untuk menandai mismatch yang belum sejalan dengan stok aktif.</small>
  </div>
  <div class="text-muted small align-self-center">Acuan: <strong><?php echo html_escape($asOfDate); ?></strong></div>
</div>

<div class="d-flex flex-wrap gap-1 align-items-center mb-2">
  <?php $this->load->view('purchase/_stock_group_tabs', ['tab_scope' => 'DIVISION', 'active_tab' => 'compare']); ?>
</div>
<?php $this->load->view('purchase/_division_stock_generate_btn', [
  'division_action_params' => ['month' => date('Y-m'), 'division_id' => (string)$selDivId, 'destination_type' => $selDest],
]); ?>

<!-- ── Filter 1-row ── -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="get" action="<?php echo $baseUrl; ?>" id="rec-filter-form">
      <div class="rec-filter-grid">
        <div>
          <label class="form-label mb-1">Tanggal</label>
          <input type="date" class="form-control form-control-sm" name="as_of_date" value="<?php echo html_escape($asOfDate); ?>">
        </div>
        <div>
          <label class="form-label mb-1">Divisi</label>
          <select class="form-select form-select-sm" name="division_id" id="recDivision">
            <option value="0">Semua Divisi</option>
            <?php foreach ($divisions as $d): ?>
              <?php $did=(int)($d['id']??0); $dc=trim((string)($d['code']??'')); $dn=trim((string)($d['name']??($d['division_name']??$d['division_code']??''))); $dl=$dc!==''?($dc.' - '.$dn):($dn?:(string)$did); ?>
              <option value="<?php echo $did; ?>" <?php echo $selDivId===$did?'selected':''; ?>><?php echo html_escape($dl); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Tujuan</label>
          <select class="form-select form-select-sm" name="destination" id="recDestination">
            <option value="ALL"     <?php echo $selDest==='ALL'    ?'selected':''; ?>>Semua</option>
            <option value="REGULER" <?php echo $selDest==='REGULER'?'selected':''; ?>>Reguler</option>
            <option value="EVENT"   <?php echo $selDest==='EVENT'  ?'selected':''; ?>>Event</option>
            <option value="BAR"     <?php echo $selDest==='BAR'    ?'selected':''; ?>>BAR</option>
            <option value="KITCHEN" <?php echo $selDest==='KITCHEN'?'selected':''; ?>>KITCHEN</option>
            <option value="OFFICE"  <?php echo $selDest==='OFFICE' ?'selected':''; ?>>OFFICE</option>
          </select>
        </div>
        <div>
          <label class="form-label mb-1">Cari Material</label>
          <input type="text" class="form-control form-control-sm" name="q" id="rec-q-input" value="<?php echo html_escape($qSearch); ?>" placeholder="Kode / nama material" autocomplete="off">
        </div>
        <div>
          <label class="form-label mb-1">/ Hal</label>
          <input type="number" class="form-control form-control-sm" name="per_page" min="10" max="200" value="<?php echo $perPage; ?>" style="max-width:84px">
        </div>
        <div style="display:flex;gap:.4rem;align-items:flex-end">
          <button type="submit" class="btn btn-sm btn-outline-primary">Terapkan</button>
          <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger">Clear</a>
        </div>
      </div>
    </form>
    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top" style="flex-wrap:wrap">
      <span class="text-muted" style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em">Filter Cepat:</span>
      <a href="<?php echo html_escape($mismatchToggleUrl); ?>"
         class="btn btn-xs <?php echo $mismatchOnly ? 'btn-warning' : 'btn-outline-secondary'; ?>"
         style="font-size:.75rem;padding:.22rem .65rem;border-radius:999px">
        <i class="ri ri-error-warning-line me-1"></i>Hanya Mismatch<?php echo $mismatchOnly ? ' &times;' : ''; ?>
      </a>
      <?php if ($mismatchOnly): ?>
      <span class="text-warning" style="font-size:.72rem;font-weight:600"><i class="ri ri-filter-3-line me-1"></i>Menampilkan <?php echo number_format($kpiMismatch); ?> baris mismatch</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Warning: Monthly Stock tanpa Material ID ─────────────────────────── -->
<?php if ($orphanTotal > 0): ?>
<div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-start gap-3" style="border-radius:8px;font-size:.82rem">
  <i class="ri ri-alert-line mt-1" style="font-size:1.1rem;color:#b45309;flex-shrink:0"></i>
  <div style="flex:1;min-width:0">
    <strong><?php echo $orphanTotal; ?> baris monthly stock tidak punya material_id</strong>
    — baris ini tersembunyi dari tabel rekonsiliasi dan tidak bisa di-Repair Lot.
    <?php if (count($orphanFixable) > 0): ?>
      <span class="text-success fw-bold"><?php echo count($orphanFixable); ?> bisa otomatis diperbaiki</span>
      (item sudah punya material di katalog):
      <span class="text-muted"><?php
        $names = array_slice(array_unique(array_column($orphanFixable, 'item_name')), 0, 5);
        echo implode(', ', array_map('htmlspecialchars', $names));
        if (count($orphanFixable) > 5) echo ' ...+' . (count($orphanFixable) - 5) . ' lainnya';
      ?></span>.
    <?php endif; ?>
    <?php if (count($orphanNoMat) > 0): ?>
      <span class="text-muted"><?php echo count($orphanNoMat); ?> item non-material</span>
      (misal kemasan, plastik) memang tidak punya kode bahan baku — tidak perlu diperbaiki.
    <?php endif; ?>
    <?php if (count($orphanFixable) > 0): ?>
    <div class="mt-2">
      <button type="button" id="src_repair_material_id_btn"
        class="btn btn-sm btn-warning" style="font-size:.77rem;padding:.25rem .7rem">
        <i class="ri ri-tools-line me-1"></i>Repair Material ID (<?php echo count($orphanFixable); ?> baris)
      </button>
      <span class="text-muted ms-2" style="font-size:.72rem">
        Mengisi material_id dari katalog item. Setelah repair, baris muncul di tabel rekonsiliasi dan Repair Lot bisa dijalankan.
      </span>
    </div>
    <?php endif; ?>
    <?php if (count($orphanNoMat) > 0): ?>
    <details class="mt-1" style="font-size:.75rem">
      <summary class="text-muted" style="cursor:pointer">Lihat item non-material (<?php echo count($orphanNoMat); ?>)</summary>
      <ul class="mb-0 mt-1" style="padding-left:1.2rem">
        <?php foreach ($orphanNoMat as $nm): ?>
        <li><?php echo html_escape((string)($nm['item_name'] ?? '-')); ?>
          @ <?php echo html_escape((string)($nm['division_name'] ?? '-')); ?>
          — <?php echo number_format((float)($nm['closing_qty_content'] ?? 0), 2); ?> sisa stok</li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── KPI Cards (6: 5 standard + 1 health ring) ── -->
<?php
$healthCls   = $healthPct >= 90 ? 'rec-health-ok' : ($healthPct >= 70 ? 'rec-health-warn' : 'rec-health-bad');
$ringFill    = $healthPct >= 90 ? '#69db7c' : ($healthPct >= 70 ? '#fbbf24' : '#fa5252');
?>
<div class="rec-kpi-row">
  <div class="rec-kpi rec-kpi-1">
    <span class="rec-kpi-icon"><i class="ri ri-list-check-3"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiTotal); ?></div>
    <div class="rec-kpi-sub">stok aktif (balance ≠ 0)</div>
    <div class="rec-kpi-lbl">Total Baris</div>
  </div>
  <div class="rec-kpi rec-kpi-2">
    <span class="rec-kpi-icon"><i class="ri ri-checkbox-circle-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMatch); ?></div>
    <div class="rec-kpi-sub">stok sejalan movement</div>
    <div class="rec-kpi-lbl">Match</div>
  </div>
  <div class="rec-kpi <?php echo $kpiMismatch > 0 ? 'rec-kpi-3-warn' : 'rec-kpi-3-ok'; ?>">
    <span class="rec-kpi-icon"><i class="ri ri-error-warning-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMismatch); ?></div>
    <div class="rec-kpi-sub">perlu investigasi</div>
    <div class="rec-kpi-lbl">Mismatch</div>
  </div>
  <div class="rec-kpi rec-kpi-4">
    <span class="rec-kpi-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiMinus); ?></div>
    <div class="rec-kpi-sub">stok di bawah nol</div>
    <div class="rec-kpi-lbl">Stok Minus</div>
  </div>
  <div class="rec-kpi rec-kpi-5">
    <span class="rec-kpi-icon"><i class="ri ri-building-2-line"></i></span>
    <div class="rec-kpi-val"><?php echo number_format($kpiDivCount); ?></div>
    <div class="rec-kpi-sub">divisi dengan stok aktif</div>
    <div class="rec-kpi-lbl">Divisi</div>
  </div>
  <!-- SURPRISE: Health Ring -->
  <div class="rec-health-card <?php echo $healthCls; ?>" style="--ring-pct:<?php echo $healthPct; ?>%;--ring-fill:<?php echo $ringFill; ?>">
    <div class="rec-health-ring-wrap">
      <div class="rec-health-ring">
        <div class="rec-health-ring-inner"><?php echo $healthPct; ?>%</div>
      </div>
    </div>
    <div>
      <div style="font-size:1rem;font-weight:900;line-height:1.1">
        <?php if ($healthPct >= 90): ?>Stok Sehat<?php elseif ($healthPct >= 70): ?>Perlu Perhatian<?php else: ?>Kritikal<?php endif; ?>
      </div>
      <div style="font-size:.7rem;opacity:.82;margin-top:.2rem"><?php echo $kpiMatch; ?> dari <?php echo $kpiTotal; ?> row match</div>
      <div style="font-size:.67rem;opacity:.7;text-transform:uppercase;letter-spacing:.06em;margin-top:.2rem">Health Score</div>
    </div>
  </div>
</div>

<!-- SURPRISE: Per-division mismatch bar -->
<?php if (count($divMatchMap) > 1): ?>
<div class="card mb-3">
  <div class="rec-div-bar-wrap">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <small class="fw-semibold text-muted text-uppercase" style="font-size:.68rem;letter-spacing:.05em">Distribusi Rekonsiliasi per Divisi</small>
      <small class="text-muted"><span style="color:#2f9e44">■</span> Match &nbsp; <span style="color:#c92a2a">■</span> Mismatch</small>
    </div>
    <div class="rec-div-bar">
      <?php foreach (array_slice($divMatchMap, 0, 8) as $dk => $dv):
        $tot = $dv['match'] + $dv['mismatch'];
        $mPct = $tot > 0 ? round($dv['match']    / $tot * 100) : 0;
        $xPct = $tot > 0 ? round($dv['mismatch'] / $tot * 100) : 0;
      ?>
      <div class="rec-div-bar-row">
        <span class="rec-div-bar-label" title="<?php echo html_escape($dv['label']); ?>"><?php echo html_escape($dv['label']); ?></span>
        <div class="rec-div-bar-track">
          <div class="rec-div-bar-match"    style="flex:<?php echo $mPct; ?>"></div>
          <div class="rec-div-bar-mismatch" style="flex:<?php echo $xPct; ?>"></div>
        </div>
        <span class="rec-div-bar-count"><?php echo $dv['mismatch']; ?> mismatch</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── POS Job Queue (collapsible) ── -->
<div class="rec-pos-panel mb-3">
  <div class="rec-pos-header" id="rec-pos-toggle">
    <span class="fw-semibold text-primary"><i class="ri ri-terminal-box-line me-2"></i>Audit POS Queue — Job Stock Commit Gagal</span>
    <span class="badge bg-primary" id="rec-pos-chevron"><i class="ri ri-arrow-down-s-line"></i></span>
  </div>
  <div class="rec-pos-body" id="rec-pos-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <input type="text" id="src_pos_job_q" class="form-control form-control-sm" placeholder="Cari order / commit / job / error" style="min-width:260px;max-width:380px;">
      <a href="<?php echo html_escape(site_url('pos/stock-live')); ?>" class="btn btn-sm btn-outline-secondary">Buka Stock Live POS</a>
      <button type="button" class="btn btn-sm btn-outline-primary" id="src_pos_job_reload">Refresh</button>
    </div>
    <div id="src_pos_job_list" style="display:grid;gap:.6rem"></div>
    <div id="src_pos_job_empty" class="text-muted small d-none">Tidak ada job stock commit POS yang gagal.</div>
  </div>
</div>

<!-- ── Reconcile Table ── -->
<div class="card mb-3">
  <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold small">
      Rekonsiliasi Stok vs Movement
      <?php if ($mismatchOnly): ?><span class="badge bg-warning text-dark ms-2" style="font-size:.65rem;font-weight:700">Hanya Mismatch</span><?php endif; ?>
    </span>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small"><?php echo number_format($totalRows); ?> baris<?php echo $mismatchOnly ? ' mismatch' : ' aktif'; ?></span>
      <button type="button" class="btn btn-xs btn-outline-danger" id="src_gap_repair_all_btn"
        style="font-size:.7rem;padding:.18rem .55rem"
        title="Repair semua gap opening monthly yang punya anchor aman">Repair Gap Opening</button>
      <button type="button" class="btn btn-xs btn-outline-warning" id="src_lot_repair_all_btn"
        style="font-size:.7rem;padding:.18rem .55rem"
        title="Repair semua lot yang mismatch sesuai filter aktif">Repair Lot Semua</button>
    </div>
  </div>
  <div class="rec-table-wrap">
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th style="min-width:210px">Material</th>
          <th class="text-end" style="min-width:100px">Stok Divisi</th>
          <th class="text-end" style="min-width:100px">Lot FIFO</th>
          <th class="text-end" style="min-width:100px">Material Daily</th>
          <th class="text-end" style="min-width:100px">Snapshot Harian</th>
          <th class="text-end" style="min-width:100px">Movement</th>
          <th style="min-width:180px">Selisih</th>
          <th style="min-width:100px">Status</th>
          <th style="min-width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pagedRows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4 small">Tidak ada baris aktif untuk filter ini. Row dengan stok akhir 0 disembunyikan agar fokus audit tetap ke stok aktif dan minus.</td></tr>
        <?php else: ?>
          <?php $shownBreakdownKeys = []; ?>
          <?php foreach ($pagedRows as $row): ?>
            <?php
              $isMatch      = !empty($row['is_match']);
              $isMinus      = (float)($row['balance_qty_content'] ?? 0) < 0;
              $dBVsM        = (float)($row['delta_balance_vs_movement'] ?? 0);
              $dMVsM        = (float)($row['delta_matrix_vs_movement']  ?? 0);
              $dDVsM        = (float)($row['delta_daily_vs_movement']   ?? 0);
              $lotQty       = $row['lot_qty_content'] ?? null;
              $hasLotData   = $lotQty !== null;
              $lotDelta     = (float)($row['lot_vs_balance_delta'] ?? 0);
              $hasLotMismatch = !empty($row['has_lot_mismatch']);
              $asOf      = html_escape($asOfDate);
              $dataDivId = (int)($row['division_id'] ?? 0);
              $dataItemId= (int)($row['item_id']     ?? 0);
              $dataMatId = (int)($row['material_id'] ?? 0);
              $dataDest  = html_escape((string)($row['destination_group'] ?? ($selDest !== 'ALL' ? $selDest : 'ALL')));
              $profileBreakdown = (array)($row['lot_profile_breakdown'] ?? []);
              $hasProfileMismatch = !empty($row['has_profile_lot_mismatch']);
              $bKey = $dataDivId . '-' . $dataMatId . '-' . $dataDest;
              $parentSelisihParts = [];
              $profileLotDeltaSum = 0.0;
              $profileStockDeltaSum = 0.0;
              if ($hasLotMismatch) {
                  $sign = $lotDelta > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($lotDelta);
              }
              if (abs($dBVsM) > 0.01) {
                  $sign = $dBVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($dBVsM);
              }
              if (abs($dMVsM) > 0.01) {
                  $sign = $dMVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Mat. Daily vs Mvt: ' . $sign . $fmtQty($dMVsM);
              }
              if (abs($dDVsM) > 0.01) {
                  $sign = $dDVsM > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Snapshot vs Mvt: ' . $sign . $fmtQty($dDVsM);
              }
              if (!empty($row['daily_log_has_gap']) && abs((float)($row['daily_log_gap_content'] ?? 0)) > 0.01) {
                  $sign = ((float)($row['daily_log_gap_content'] ?? 0)) > 0 ? '+' : '';
                  $parentSelisihParts[] = 'Gap Log: ' . $sign . $fmtQty($row['daily_log_gap_content'] ?? 0);
              }
              if (empty($parentSelisihParts) && $hasProfileMismatch && !empty($profileBreakdown)) {
                  foreach ($profileBreakdown as $pb) {
                      if (empty($pb['has_mismatch'])) {
                          continue;
                      }
                      $profileLotDeltaSum += (float)($pb['delta'] ?? 0);
                      $profileStockDeltaSum += (float)($pb['delta_stock_vs_mvt'] ?? 0);
                  }
                  $profileLotDeltaSum = round($profileLotDeltaSum, 4);
                  $profileStockDeltaSum = round($profileStockDeltaSum, 4);
                  if (abs($profileLotDeltaSum) > 0.01) {
                      $sign = $profileLotDeltaSum > 0 ? '+' : '';
                      $parentSelisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($profileLotDeltaSum) . ' (antar profil)';
                  }
                  if (abs($profileStockDeltaSum) > 0.01) {
                      $sign = $profileStockDeltaSum > 0 ? '+' : '';
                      $parentSelisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($profileStockDeltaSum) . ' (antar profil)';
                  }
                  if (empty($parentSelisihParts)) {
                      $parentSelisihParts[] = 'Mismatch antar profil';
                  }
              }
              $parentSelisihText = empty($parentSelisihParts) ? '-' : implode('; ', $parentSelisihParts);
              $parentMovementMismatch = abs((float)($row['delta_balance_vs_movement'] ?? 0)) > 0.01
                || abs((float)($row['delta_daily_vs_movement'] ?? 0)) > 0.01
                || abs((float)($row['delta_matrix_vs_movement'] ?? 0)) > 0.01
                || !empty($row['daily_log_has_gap'])
                || abs((float)$profileStockDeltaSum) > 0.01;
              $parentProfileLotMismatch = abs((float)$profileLotDeltaSum) > 0.01;
              $parentRepairLabel = 'Repair Bahan';
              $parentRepairTitle = 'Deteksi otomatis arah repair bahan ini.';
              if ($hasProfileMismatch && $parentMovementMismatch && !$parentProfileLotMismatch && !$hasLotMismatch) {
                  $parentRepairLabel = 'Repair Stok/Mvt';
                  $parentRepairTitle = 'Fokus ke selisih stok profil terhadap movement log.';
              } elseif ($hasProfileMismatch && !$parentMovementMismatch && $parentProfileLotMismatch) {
                  $parentRepairLabel = 'Repair Profil';
                  $parentRepairTitle = 'Samakan distribusi lot per profil ke stok divisi.';
              } elseif ($hasLotMismatch && $parentMovementMismatch) {
                  $parentRepairLabel = 'Pilih Repair';
                  $parentRepairTitle = 'Pilih apakah truth source-nya stok saat ini atau movement log.';
              } elseif ($parentMovementMismatch) {
                  $parentRepairLabel = 'Repair Stok/Mvt';
                  $parentRepairTitle = 'Samakan stok bahan dengan movement log.';
              }
            ?>
            <tr class="<?php echo $isMinus ? 'rec-row-negative' : ''; ?>">
              <td>
                <div class="fw-semibold small"><?php echo html_escape($fmtText($row['material_name'] ?? '')); ?></div>
                <div class="text-muted" style="font-size:.7rem">
                  <?php echo html_escape($fmtText($row['material_code'] ?? '')); ?>
                  · <?php echo html_escape($fmtText($row['division_name'] ?? '')); ?>
                  · <?php echo html_escape($fmtText($row['destination_name'] ?? 'Reguler')); ?>
                </div>
              </td>
              <td class="text-end small">
                <div class="fw-semibold <?php echo $isMinus ? 'text-danger' : ''; ?>"><?php echo $fmtQty($row['balance_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['balance_qty_pack'] ?? 0); ?> pack</div>
                <?php if ($isMinus): ?><span class="rec-chip rec-chip-warn" style="margin-top:.2rem">Minus</span><?php endif; ?>
              </td>
              <td class="text-end small <?php echo ($hasLotMismatch || $hasProfileMismatch) ? 'table-warning' : ''; ?>">
                <?php if ($hasLotData): ?>
                  <div class="fw-semibold <?php echo $hasLotMismatch ? 'text-danger' : ''; ?>"><?php echo $fmtQty($lotQty); ?></div>
                  <?php if ($hasLotMismatch): ?>
                    <div style="font-size:.66rem;color:#b45309;" title="Selisih lot FIFO vs stok ledger">Δ <?php echo $fmtQty(abs($lotDelta)); ?></div>
                    <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Lot Beda</span>
                    <div style="margin-top:.25rem">
                      <button type="button" class="btn btn-xs btn-warning src-lot-repair-btn"
                        data-division-id="<?php echo $dataDivId; ?>"
                        data-material-id="<?php echo $dataMatId; ?>"
                        data-destination="<?php echo $dataDest; ?>"
                        style="font-size:.6rem;padding:.1rem .35rem">Repair Lot</button>
                    </div>
                  <?php endif; ?>
                  <?php if ($hasProfileMismatch && !$hasLotMismatch): ?>
                    <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Profil Beda</span>
                  <?php endif; ?>
                  <?php if (!empty($profileBreakdown)): ?>
                    <div style="margin-top:.25rem">
                      <button type="button" class="btn btn-xs btn-outline-secondary src-profile-breakdown-toggle"
                        data-breakdown-key="<?php echo html_escape($bKey); ?>"
                        style="font-size:.6rem;padding:.1rem .35rem">Profil ▼</button>
                    </div>
                  <?php endif; ?>
                <?php elseif ($hasLotMismatch): ?>
                  <?php /* Stock > 0 but no FIFO lots at all — repair will insert a correction lot */ ?>
                  <div class="fw-semibold text-danger">0</div>
                  <div style="font-size:.66rem;color:#b45309;" title="Stok ada tapi tidak ada lot FIFO">Δ <?php echo $fmtQty(abs($lotDelta)); ?></div>
                  <span class="rec-chip rec-chip-bad" style="margin-top:.15rem;font-size:.6rem">Lot Kosong</span>
                  <div style="margin-top:.25rem">
                    <button type="button" class="btn btn-xs btn-warning src-lot-repair-btn"
                      data-division-id="<?php echo $dataDivId; ?>"
                      data-material-id="<?php echo $dataMatId; ?>"
                      data-destination="<?php echo $dataDest; ?>"
                      style="font-size:.6rem;padding:.1rem .35rem">Repair Lot</button>
                  </div>
                <?php else: ?>
                  <span class="text-muted">–</span>
                <?php endif; ?>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['matrix_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['matrix_qty_pack'] ?? 0); ?> pack</div>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['daily_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['daily_qty_pack'] ?? 0); ?> pack</div>
                <?php if (!empty($row['daily_audit_has_mismatch'])): ?><div class="text-danger" style="font-size:.66rem">Log liar ±<?php echo $fmtQty($row['daily_audit_mismatch_qty_content'] ?? 0); ?></div><?php endif; ?>
                <?php if (!empty($row['daily_log_has_gap'])): ?><div class="text-warning" style="font-size:.66rem">Gap log ±<?php echo $fmtQty($row['daily_log_gap_content'] ?? 0); ?></div><?php endif; ?>
              </td>
              <td class="text-end small">
                <div><?php echo $fmtQty($row['movement_qty_content'] ?? 0); ?></div>
                <div class="text-muted" style="font-size:.68rem"><?php echo $fmtQty($row['movement_qty_pack'] ?? 0); ?> pack</div>
              </td>
              <td class="small" style="font-size:.68rem">
                <?php if ($parentSelisihText === '-'): ?>
                  <span class="rec-delta-ok">-</span>
                <?php else: ?>
                  <span class="rec-delta-bad" style="font-weight:600"><?php echo html_escape($parentSelisihText); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="rec-chip <?php echo $isMatch ? 'rec-chip-ok' : 'rec-chip-bad'; ?>"><?php echo $isMatch ? 'Match' : 'Mismatch'; ?></span>
                <?php if (!empty($row['daily_audit_has_mismatch'])): ?><div class="text-muted" style="font-size:.66rem;margin-top:.2rem">Log liar: <?php echo html_escape((string)($row['daily_audit_mismatch_notes'] ?? 'fallback identity')); ?></div><?php endif; ?>
                <?php if (!empty($row['daily_log_has_gap'])): ?><div class="text-muted" style="font-size:.66rem;margin-top:.2rem">Gap movement log: opening + delta log tidak sama dengan closing monthly.</div><?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1 flex-wrap">
                  <button type="button" class="btn btn-xs btn-outline-primary src-material-audit-btn"
                    data-as-of-date="<?php echo $asOf; ?>"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-item-id="<?php echo $dataItemId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>">Audit</button>
                  <button type="button" class="btn btn-xs btn-outline-danger src-material-repair-btn"
                    data-as-of-date="<?php echo $asOf; ?>"
                    data-division-id="<?php echo $dataDivId; ?>"
                    data-item-id="<?php echo $dataItemId; ?>"
                    data-material-id="<?php echo $dataMatId; ?>"
                    data-destination="<?php echo $dataDest; ?>"
                    title="<?php echo html_escape($parentRepairTitle); ?>"><?php echo html_escape($parentRepairLabel); ?></button>
                </div>
              </td>
            </tr>
            <?php if ($hasLotData && !empty($profileBreakdown) && !isset($shownBreakdownKeys[$bKey])): ?>
              <?php $shownBreakdownKeys[$bKey] = true; ?>
              <tr class="rec-profile-breakdown-row" data-breakdown-key="<?php echo html_escape($bKey); ?>" style="display:<?php echo $hasProfileMismatch ? '' : 'none'; ?>">
                <td colspan="9" style="padding:.2rem .5rem .5rem 1.8rem;background:#f0f4f8;border-top:none;">
                  <div style="font-size:.62rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;padding:.3rem 0 .2rem">Breakdown per Profil</div>
                  <div style="overflow-x:auto">
                  <table class="table table-sm table-borderless mb-0" style="font-size:.68rem;min-width:720px;">
                    <thead>
                      <tr style="border-bottom:2px solid #cbd5e1;background:#e2e8f0;">
                        <th style="min-width:140px;font-weight:700;padding:.3rem .5rem">Profil</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Stok Divisi</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Lot FIFO</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Mat. Daily</th>
                        <th class="text-end" style="font-weight:700;padding:.3rem .4rem;min-width:82px">Movement</th>
                        <th style="font-weight:700;padding:.3rem .4rem;min-width:140px">Selisih</th>
                        <th class="text-center" style="font-weight:700;padding:.3rem .4rem;min-width:72px">Status</th>
                        <th class="text-center" style="font-weight:700;padding:.3rem .4rem;min-width:64px">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($profileBreakdown as $pb): ?>
                        <?php
                          $pbPk     = (string)($pb['profile_key'] ?? '');
                          $pbName   = trim((string)($pb['profile_name'] ?? ''));
                          $pbLabel  = $pbName !== '' ? $pbName : ($pbPk !== '' ? substr($pbPk, 0, 8).'…' : '(no profile)');
                          $pbStock  = (float)($pb['stock_balance'] ?? 0);
                          $pbLot    = (float)($pb['lot_balance'] ?? 0);
                          $pbDaily  = isset($pb['daily_content']) ? (float)$pb['daily_content'] : $pbStock;
                          $pbMvt    = isset($pb['movement_content']) ? (float)$pb['movement_content'] : 0.0;
                          $pbHasMvt = isset($pb['movement_content']);
                          $pbDStk   = round($pbStock - $pbMvt, 4);
                          $pbDLot   = round($pbStock - $pbLot, 4);
                          $dStkBad  = $pbHasMvt && abs($pbDStk) > 0.01;
                          $dLotBad  = abs($pbDLot) > 0.01;
                          $pbMis    = $dStkBad || $dLotBad;

                          // Build smart Selisih text
                          $selisihParts = [];
                          if ($dLotBad) {
                              $sign = $pbDLot > 0 ? '+' : '';
                              $selisihParts[] = 'Lot vs Stok: ' . $sign . $fmtQty($pbDLot);
                          }
                          if ($dStkBad) {
                              $sign = $pbDStk > 0 ? '+' : '';
                              $selisihParts[] = 'Stok vs Mvt: ' . $sign . $fmtQty($pbDStk);
                          }
                          $selisihText = empty($selisihParts) ? '–' : implode('; ', $selisihParts);

                          $pbStatusLbl = $pbMis ? 'Mismatch' : 'Match';
                          $pbStatusCls = $pbMis ? 'rec-chip-bad' : 'rec-chip-ok';
                          $profileRepairLabel = 'Repair';
                          $profileRepairTitle = 'Repair profil ini.';
                          if ($dLotBad && !$dStkBad) {
                              $profileRepairLabel = 'Repair Lot';
                              $profileRepairTitle = 'Samakan lot profil ini ke stok divisi.';
                          } elseif (!$dLotBad && $dStkBad) {
                              $profileRepairLabel = 'Repair Mvt';
                              $profileRepairTitle = 'Samakan stok profil ini ke movement log.';
                          } elseif ($dLotBad && $dStkBad) {
                              $profileRepairLabel = 'Pilih Repair';
                              $profileRepairTitle = 'Pilih apakah profil ini mengikuti stok saat ini atau movement log.';
                          }
                        ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                          <td style="padding:.3rem .5rem">
                            <div style="font-weight:600"><?php echo html_escape($pbLabel); ?></div>
                            <code title="<?php echo html_escape($pbPk); ?>" style="font-size:.59rem;color:#94a3b8"><?php echo html_escape(substr($pbPk,0,12)); ?><?php echo strlen($pbPk)>12?'…':''; ?></code>
                          </td>
                          <td class="text-end" style="padding:.3rem .4rem;font-weight:600;<?php echo $pbStock < 0 ? 'color:#b42318' : ''; ?>"><?php echo $fmtQty($pbStock); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem;<?php echo $dLotBad ? 'color:#b42318;font-weight:600' : ''; ?>"><?php echo $fmtQty($pbLot); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem"><?php echo $fmtQty($pbDaily); ?></td>
                          <td class="text-end" style="padding:.3rem .4rem<?php echo !$pbHasMvt ? ';color:#94a3b8' : ''; ?>"><?php echo $pbHasMvt ? $fmtQty($pbMvt) : '–'; ?></td>
                          <td style="padding:.3rem .4rem;font-size:.65rem">
                            <?php if (!$pbMis): ?>
                              <span style="color:#2f9e44">–</span>
                            <?php else: ?>
                              <span style="color:#b42318;font-weight:600"><?php echo html_escape($selisihText); ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center" style="padding:.3rem .4rem">
                            <span class="rec-chip <?php echo $pbStatusCls; ?>" style="font-size:.59rem"><?php echo $pbStatusLbl; ?></span>
                          </td>
                          <td class="text-center" style="padding:.3rem .4rem">
                            <?php if ($pbMis): ?>
                            <button type="button" class="btn btn-xs btn-outline-warning src-profile-repair-btn"
                              data-as-of-date="<?php echo html_escape($asOfDate); ?>"
                              data-division-id="<?php echo $dataDivId; ?>"
                              data-material-id="<?php echo $dataMatId; ?>"
                              data-destination="<?php echo html_escape($dataDest); ?>"
                              data-profile-key="<?php echo html_escape($pbPk); ?>"
                              data-profile-name="<?php echo html_escape($pbLabel); ?>"
                              data-stock="<?php echo $pbStock; ?>"
                              data-lot="<?php echo $pbLot; ?>"
                              data-daily="<?php echo $pbDaily; ?>"
                              data-movement="<?php echo $pbHasMvt ? $pbMvt : ''; ?>"
                              data-has-mvt="<?php echo $pbHasMvt ? '1' : '0'; ?>"
                              data-d-lot="<?php echo $pbDLot; ?>"
                              data-d-stk="<?php echo $pbDStk; ?>"
                              title="<?php echo html_escape($profileRepairTitle); ?>"
                              style="font-size:.59rem;padding:.1rem .35rem"><?php echo html_escape($profileRepairLabel); ?></button>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <?php if ($totalRows > 0): ?>
  <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <?php $fromR = ($page-1)*$perPage+1; $toR = min($page*$perPage,$totalRows); ?>
    <span class="text-muted small">Baris <?php echo "{$fromR}–{$toR}"; ?> dari <?php echo number_format($totalRows); ?></span>
    <?php if ($totalPages > 1): ?>
    <div class="rec-pagination">
      <?php
        $ws = max(1,$page-2); $we = min($totalPages,$page+2);
        if ($we-$ws < 4) { if ($ws===1) $we=min($totalPages,$ws+4); else $ws=max(1,$we-4); }
      ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page-1)); ?>" class="rec-page-btn<?php echo $page>1?'':' disabled'; ?>">&#8249;</a>
      <?php if ($ws>1): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page=1'); ?>" class="rec-page-btn">1</a><?php if ($ws>2): ?><span class="text-muted small px-1">…</span><?php endif; ?><?php endif; ?>
      <?php for ($pn=$ws;$pn<=$we;$pn++): ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$pn); ?>" class="rec-page-btn<?php echo $pn===$page?' active':''; ?>"><?php echo $pn; ?></a><?php endfor; ?>
      <?php if ($we<$totalPages): ?><?php if ($we<$totalPages-1): ?><span class="text-muted small px-1">…</span><?php endif; ?><a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.$totalPages); ?>" class="rec-page-btn"><?php echo $totalPages; ?></a><?php endif; ?>
      <a href="<?php echo html_escape($baseUrl.'?'.$pQs.'&page='.($page+1)); ?>" class="rec-page-btn<?php echo $page<$totalPages?'':' disabled'; ?>">&#8250;</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Material Audit Drawer ── -->
<div class="rec-audit-drawer p-3 p-lg-4" id="src_material_audit_card">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <div class="small text-uppercase fw-bold text-muted">Lacak Selisih per Bahan</div>
      <h5 class="mb-1 mt-1">Detail Audit Material</h5>
      <div class="text-muted small">Klik tombol <strong>Audit</strong> pada salah satu baris untuk melihat bucket OPENING, PO, SR, VOID, REFUND, ADJUSTMENT, POS, dan detail movement sumbernya.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="src_material_repair_current">Repair Bahan Ini</button>
  </div>
  <div id="src_material_audit_state" class="text-muted small">Belum ada bahan yang dipilih. Klik tombol Audit di tabel di atas.</div>
  <div id="src_material_audit_body" class="d-none">
    <div id="src_material_audit_summary" class="rec-audit-summary mb-3"></div>
    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Profil</th>
            <th class="text-end">Opening Monthly</th>
            <th class="text-end">Opening Snapshot</th>
            <th class="text-end">Prev Closing</th>
            <th class="text-end">Delta Log</th>
            <th class="text-end">Closing Monthly</th>
            <th class="text-end">Gap</th>
            <th>Saran Repair</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_gap_profiles"></tbody>
      </table>
    </div>
    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Bucket</th>
            <th class="text-end">Jumlah Log</th>
            <th class="text-end">Delta Content</th>
            <th class="text-end">Delta Buy</th>
            <th class="text-end">Nilai Mutasi</th>
            <th>Log Terakhir</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_buckets"></tbody>
      </table>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Tanggal</th><th>No</th><th>Sumber</th>
            <th class="text-end">Before</th><th class="text-end">Delta</th><th class="text-end">After</th>
            <th>Jenis</th><th>Catatan</th>
          </tr>
        </thead>
        <tbody id="src_material_audit_movements"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ── POS accordion ──────────────────────────────────────────────────────────
  var posToggle  = document.getElementById('rec-pos-toggle');
  var posBody    = document.getElementById('rec-pos-body');
  var posChevron = document.getElementById('rec-pos-chevron');
  if (posToggle && posBody) {
    posToggle.addEventListener('click', function () {
      var open = posBody.classList.toggle('open');
      if (posChevron) posChevron.innerHTML = open ? '<i class="ri ri-arrow-up-s-line"></i>' : '<i class="ri ri-arrow-down-s-line"></i>';
      if (open) loadFailedJobs().catch(function(e){ showAlert(e.message,'Audit POS Queue'); });
    });
  }

  // ── Search: submit only on Enter ─────────────────────────────────────────
  var qInput = document.getElementById('rec-q-input');
  if (qInput) {
    qInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('rec-filter-form')?.submit();
      }
    });
  }

  // ── Destination guard ────────────────────────────────────────────────────
  var divEl  = document.getElementById('recDivision');
  var destEl = document.getElementById('recDestination');
  var guardMap = <?php echo json_encode($destGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  if (divEl && destEl) {
    function applyDestGuard() {
      var did = String(divEl.value || '');
      var allowed = guardMap[did] || [];
      Array.prototype.forEach.call(destEl.options, function (opt) {
        var v = String(opt.value || '').toUpperCase();
        if (!did || v === 'ALL' || v === 'REGULER' || v === 'EVENT') { opt.hidden = false; opt.disabled = false; return; }
        var keep = allowed.indexOf(v) >= 0;
        opt.hidden = !keep; opt.disabled = !keep;
      });
      if (destEl.selectedOptions[0] && destEl.selectedOptions[0].disabled) destEl.value = 'ALL';
    }
    divEl.addEventListener('change', applyDestGuard);
    applyDestGuard();
  }

  // ── Shared helpers ────────────────────────────────────────────────────────
  var listEl      = document.getElementById('src_pos_job_list');
  var emptyEl     = document.getElementById('src_pos_job_empty');
  var searchEl    = document.getElementById('src_pos_job_q');
  var reloadBtn   = document.getElementById('src_pos_job_reload');
  var auditCard   = document.getElementById('src_material_audit_card');
  var auditState  = document.getElementById('src_material_audit_state');
  var auditBody   = document.getElementById('src_material_audit_body');
  var auditSumEl  = document.getElementById('src_material_audit_summary');
  var auditGapEl  = document.getElementById('src_material_audit_gap_profiles');
  var auditBktEl  = document.getElementById('src_material_audit_buckets');
  var auditMvtEl  = document.getElementById('src_material_audit_movements');
  var repairCurBtn = document.getElementById('src_material_repair_current');
  var searchTimer = null;
  var currentMaterialIdentity = null;
  var repairDecisionModal = document.getElementById('repairDecisionModal');
  var repairDecisionBs = repairDecisionModal ? (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(repairDecisionModal) : null) : null;

  function escHtml(v) { return String(v??'').replace(/[&<>"']/g,function(m){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);}); }
  function fmtQty(v) { return new Intl.NumberFormat('id-ID',{minimumFractionDigits:2,maximumFractionDigits:4}).format(Number(v||0)); }
  function fmtDT(v) { if(!v)return'-'; var d=new Date(String(v).replace(' ','T')); return isNaN(d.getTime())?escHtml(String(v)):new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(d); }
  function showAlert(msg,title) { if(window.FinanceUI&&typeof window.FinanceUI.alert==='function')return Promise.resolve(window.FinanceUI.alert(msg,{title:title||'Informasi'})); return Promise.resolve(); }
  function askConfirm(msg,opts) { if(window.FinanceUI&&typeof window.FinanceUI.confirm==='function')return Promise.resolve(window.FinanceUI.confirm(msg,opts||{})); return showAlert('Modal konfirmasi tidak tersedia.','UI Belum Siap').then(function(){return false;}); }
  async function getJson(url) {
    var r = await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||!j.ok) throw new Error(j.message||'Gagal memuat data.'); return j;
  }
  async function postJson(url,payload) {
    var r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload||{})});
    var t = await r.text(); var j=null; try{j=JSON.parse(t);}catch(e){throw new Error('Response tidak valid: '+String(t||'').slice(0,180));}
    if(!r.ok||(!j.ok && !j.needs_choice)) throw new Error(j.message||'Gagal memproses.'); return j;
  }
  function setButtonLoading(btn,lbl) {
    if(!btn)return; if(window.FinanceUI&&typeof window.FinanceUI.setButtonLoading==='function'){window.FinanceUI.setButtonLoading(btn,lbl);return;}
    btn.dataset.orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>'+escHtml(lbl||'...');
  }
  function clearButtonLoading(btn) {
    if(!btn)return; if(window.FinanceUI&&typeof window.FinanceUI.clearButtonLoading==='function'){window.FinanceUI.clearButtonLoading(btn);return;}
    btn.disabled=false; if(btn.dataset.orig){btn.innerHTML=btn.dataset.orig;delete btn.dataset.orig;}
  }
  function buttonIdentity(btn) {
    return {as_of_date:String(btn?.dataset.asOfDate||''),division_id:Number(btn?.dataset.divisionId||0),item_id:Number(btn?.dataset.itemId||0),material_id:Number(btn?.dataset.materialId||0),destination:String(btn?.dataset.destination||'ALL')};
  }
  function setAuditState(msg,loading) {
    auditState.textContent=msg; auditBody.classList.add('d-none'); auditState.classList.remove('d-none');
    if(repairCurBtn){repairCurBtn.classList.toggle('d-none',!currentMaterialIdentity||!!loading);repairCurBtn.disabled=!!loading||!currentMaterialIdentity;}
  }

  function hideRepairDecisionModal() {
    if (!repairDecisionModal) return;
    if (repairDecisionBs) { repairDecisionBs.hide(); return; }
    if (typeof $ !== 'undefined') { $(repairDecisionModal).modal('hide'); return; }
    repairDecisionModal.style.display = 'none';
    repairDecisionModal.classList.remove('show');
  }

  function openRepairDecisionModal(identity, response, sourceButton) {
    if (!repairDecisionModal) {
      showAlert('Pilihan repair membutuhkan modal, tetapi modal belum tersedia.', 'Repair Bahan');
      return;
    }
    var plan = response?.data?.repair_plan || {};
    var summary = response?.data?.summary || {};
    repairDecisionModal.dataset.identity = JSON.stringify(identity || {});
    repairDecisionModal.dataset.sourceButtonId = sourceButton?.id || '';

    var titleEl = document.getElementById('repairDecisionTitle');
    var bodyEl = document.getElementById('repairDecisionBody');
    var actionEl = document.getElementById('repairDecisionActions');
    if (titleEl) {
      titleEl.textContent = 'Pilih Arah Repair - ' + String(summary.material_name || 'Bahan');
    }
    var repairModeLabels = {
      noop: 'Tidak ada repair',
      lot_repair: 'Repair Lot',
      profile_sync: 'Repair Profil',
      profile_to_movement: 'Repair Stok/Mvt per Profil',
      repair_log_gap_opening: 'Repair Gap Opening',
      rebuild_from_movement: 'Repair Stok dari Movement',
      rebuild_then_lot_repair: 'Ikuti Movement lalu Repair Lot',
      rebuild_then_profile_sync: 'Ikuti Movement lalu Repair Profil'
    };
    if (bodyEl) {
      var verdict = String(summary.suspect_table || 'UNKNOWN');
      var reason = String(summary.suspect_reason || response.message || '');
      var recommended = String(plan.recommended_mode || '');
      bodyEl.innerHTML =
        '<div class="small text-muted mb-2">Sistem mendeteksi mismatch yang masih ambigu.</div>' +
        '<div class="border rounded p-2 mb-2" style="background:#fffaf5">' +
          '<div class="small"><strong>Verdict:</strong> ' + escHtml(verdict) + '</div>' +
          '<div class="small mt-1"><strong>Analisis:</strong> ' + escHtml(reason) + '</div>' +
          (recommended ? '<div class="small mt-1"><strong>Rekomendasi:</strong> ' + escHtml(repairModeLabels[recommended] || recommended) + '</div>' : '') +
        '</div>';
    }
    if (actionEl) {
      var choices = Array.isArray(plan.choices) ? plan.choices : [];
      actionEl.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" data-role="cancel">Batal</button>' +
        choices.map(function(choice){
          var recommendedBadge = choice.recommended ? ' <span class="badge bg-warning text-dark ms-1">Rekomendasi</span>' : '';
          var btnClass = choice.recommended ? 'btn-danger' : 'btn-outline-primary';
          return '<button type="button" class="btn btn-sm ' + btnClass + ' repair-choice-btn"'
            + ' data-mode="' + escHtml(String(choice.mode || '')) + '">'
            + escHtml(String(choice.label || choice.mode || 'Pilih')) + recommendedBadge + '</button>'
            + '<div class="w-100 small text-muted" style="margin-top:-.25rem;margin-bottom:.35rem">' + escHtml(String(choice.description || '')) + '</div>';
        }).join('');
    }

    if (repairDecisionBs) { repairDecisionBs.show(); }
    else if (typeof $ !== 'undefined') { $(repairDecisionModal).modal('show'); }
    else { repairDecisionModal.style.display = 'block'; repairDecisionModal.classList.add('show'); }
  }

  // ── Material audit render ────────────────────────────────────────────────
  function renderMaterialAudit(identity,json) {
    currentMaterialIdentity=identity;
    var summary=json.summary||{}; var buckets=Array.isArray(json.buckets)?json.buckets:[]; var movements=Array.isArray(json.movements)?json.movements:[]; var gapProfiles=Array.isArray(json.gap_profiles)?json.gap_profiles:[];
    auditState.classList.add('d-none'); auditBody.classList.remove('d-none');
    if(repairCurBtn){repairCurBtn.classList.remove('d-none');repairCurBtn.disabled=false;}
    auditSumEl.innerHTML=[
      ['Material', escHtml(summary.material_name||'-'), escHtml(summary.material_code||'-')],
      ['Verdict',  escHtml(summary.suspect_table||'MATCH'), escHtml(summary.suspect_reason||'Semua tabel masih sinkron.')],
      ['Stok vs Mvt', fmtQty(summary.delta_balance_vs_movement||0),'selisih content'],
      ['Daily vs Mvt', fmtQty(summary.delta_daily_vs_movement||0),'closing daily '+escHtml(summary.daily_date||'-')],
      ['Gap Log', fmtQty(summary.daily_log_gap_content||0), (summary.daily_log_has_gap ? 'opening + delta log tidak foot ke closing monthly' : 'tidak ada gap')],
      ['Identity Repair', escHtml(String(json.repair_identity_count||0)),'identity sumber'],
    ].map(function(it){return '<div class="rec-audit-metric"><span class="label">'+it[0]+'</span><div class="value">'+it[1]+'</div><div class="text-muted small">'+it[2]+'</div></div>';}).join('');
    var gapPathLabel = {
      NO_GAP: 'Sudah foot',
      RESTORE_OPENING_FROM_SNAPSHOT: 'Pulihkan opening dari snapshot',
      SEED_OPENING_FROM_PREV_MONTH_CLOSING: 'Isi opening dari closing bulan lalu',
      REVIEW_MOVEMENT_HISTORY: 'Review histori movement',
      NO_MONTH_MOVEMENT_REVIEW_MONTHLY: 'Review monthly tanpa movement bulan ini'
    };
    auditGapEl.innerHTML=gapProfiles.map(function(g){
      var gap = Number(g.gap_from_monthly_opening||0);
      var gapCls = Math.abs(gap) > 0.01 ? 'text-danger fw-bold' : 'text-success fw-bold';
      return '<tr>'
        + '<td>'+escHtml(g.profile_name||g.profile_key||'-')+'<div class="text-muted small">'+escHtml(String(g.profile_key||'').slice(0,12))+'</div></td>'
        + '<td class="text-end">'+fmtQty(g.monthly_opening_qty_content||0)+'</td>'
        + '<td class="text-end">'+(g.snapshot_opening_qty_content==null?'<span class="text-muted">-</span>':fmtQty(g.snapshot_opening_qty_content))+'</td>'
        + '<td class="text-end">'+(g.prev_closing_qty_content==null?'<span class="text-muted">-</span>':fmtQty(g.prev_closing_qty_content))+'</td>'
        + '<td class="text-end">'+fmtQty(g.net_non_opening_delta||0)+'</td>'
        + '<td class="text-end">'+fmtQty(g.monthly_closing_qty_content||0)+'</td>'
        + '<td class="text-end '+gapCls+'">'+(gap>0?'+':'')+fmtQty(gap)+'</td>'
        + '<td>'+escHtml(gapPathLabel[g.suggested_repair_path] || g.suggested_repair_path || '-')+'</td>'
        + '</tr>';
    }).join('')||'<tr><td colspan="8" class="text-center text-muted py-3">Tidak ada gap movement log per profil.</td></tr>';
    auditBktEl.innerHTML=buckets.map(function(b){return '<tr><td>'+escHtml(b.bucket_label||b.bucket_code||'-')+'</td><td class="text-end">'+Number(b.count||0)+'</td><td class="text-end">'+fmtQty(b.delta_content||0)+'</td><td class="text-end">'+fmtQty(b.delta_buy||0)+'</td><td class="text-end">'+fmtQty(b.mutation_value||0)+'</td><td>'+escHtml(b.last_movement_date||'-')+'<div class="text-muted small">'+escHtml(b.last_movement_no||'-')+'</div></td></tr>';}).join('')||'<tr><td colspan="6" class="text-center text-muted py-3">Belum ada bucket log.</td></tr>';
    auditMvtEl.innerHTML=movements.map(function(r){return '<tr><td>'+escHtml(r.movement_date||'-')+'</td><td>'+escHtml(r.movement_no||'-')+'</td><td>'+escHtml(r.source_label||'-')+'<div class="text-muted small">'+escHtml(r.source_bucket_label||'-')+'</div></td><td class="text-end">'+fmtQty(r.qty_content_before||0)+'</td><td class="text-end">'+fmtQty(r.qty_content_delta||0)+'</td><td class="text-end">'+fmtQty(r.qty_content_after||0)+'</td><td>'+escHtml(r.movement_type_label||r.movement_type||'-')+'</td><td>'+escHtml(r.notes||'-')+'</td></tr>';}).join('')||'<tr><td colspan="8" class="text-center text-muted py-3">Belum ada movement sumber.</td></tr>';
    auditCard.scrollIntoView({behavior:'smooth',block:'start'});
  }
  async function loadMaterialAudit(identity) {
    currentMaterialIdentity=identity;
    setAuditState('Memuat audit bahan...',true);
    var p=new URLSearchParams(); Object.keys(identity||{}).forEach(function(k){p.set(k,String(identity[k]??''));});
    var json=await getJson('<?php echo $auditUrl; ?>?'+p.toString());
    renderMaterialAudit(identity,json);
  }
  async function runMaterialRepair(identity,button,forceMode) {
    var ok=await askConfirm(
      forceMode
        ? 'Sistem akan memaksa repair mengikuti pilihan yang Anda tentukan. Lanjutkan?'
        : 'Sistem akan mendeteksi masalah lalu menjalankan repair yang paling cocok. Jika ambigu, Anda akan diminta memilih truth source. Lanjutkan?',
      {title:'Repair Reconcile Bahan',confirmText:'Repair',cancelText:'Batal'}
    );
    if(!ok)return;
    setButtonLoading(button,'Repair...');
    setAuditState('Menjalankan repair bahan...',true);
    try {
      var payload = Object.assign({}, identity || {});
      if (forceMode) payload.force_mode = forceMode;
      var json=await postJson('<?php echo $repairUrl; ?>', payload);
      if (json && json.needs_choice) {
        clearButtonLoading(button);
        setAuditState('Memerlukan keputusan user untuk menentukan truth source repair.', false);
        openRepairDecisionModal(identity, json, button);
        return;
      }
      await showAlert(json.message||'Repair selesai.','Repair Reconcile Bahan');
      window.location.reload();
    } catch(e) { clearButtonLoading(button); throw e; }
  }
  async function runLotRepair(identity,button) {
    var ok=await askConfirm('Repair lot FIFO akan menyesuaikan saldo lot agar cocok dengan saldo stok ledger. Lanjutkan?',{title:'Repair Lot FIFO',confirmText:'Repair Lot',cancelText:'Batal'});
    if(!ok)return;
    var orig=button.innerHTML; button.disabled=true; button.textContent='Repair...';
    try {
      var json=await postJson('<?php echo $lotRepairUrl; ?>',{division_id:identity.division_id,material_id:identity.material_id,destination:identity.destination});
      if(json.needs_profile_sync) {
        button.disabled=false; button.innerHTML=orig;
        var doSync=await askConfirm(
          (json.message||'Ada ketidaksesuaian profil lot.')+'\n\nSamakan lot per profil sekarang?',
          {title:'Profil Lot Silang Terdeteksi',confirmText:'Ya, Samakan Profil',cancelText:'Batal'}
        );
        if(!doSync)return;
        button.disabled=true; button.textContent='Sinkronisasi...';
        var syncJson=await postJson('<?php echo $lotProfileSyncUrl; ?>',{division_id:identity.division_id,material_id:identity.material_id,destination:identity.destination});
        await showAlert(syncJson.message||'Sinkronisasi profil selesai.','Samakan Lot per Profil');
        window.location.reload();
      } else {
        await showAlert(json.message||'Lot repair selesai.','Repair Lot FIFO');
        window.location.reload();
      }
    } catch(e) { button.disabled=false; button.innerHTML=orig; throw e; }
  }

  // ── Repair Lot Semua ──────────────────────────────────────────────────────
  var gapRepairAllBtn = document.getElementById('src_gap_repair_all_btn');
  if (gapRepairAllBtn) {
    gapRepairAllBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair semua gap movement log yang anchor-nya aman sesuai filter aktif?\n\nSaat ini sistem hanya akan memperbaiki kasus yang bisa dipulihkan dari opening snapshot atau closing bulan sebelumnya. Kasus lain tetap dibiarkan untuk review manual.',
        {title:'Repair Gap Opening', confirmText:'Repair Gap', cancelText:'Batal'}
      );
      if (!ok) return;
      var orig = gapRepairAllBtn.innerHTML;
      gapRepairAllBtn.disabled = true; gapRepairAllBtn.textContent = 'Memproses...';
      try {
        var json = await postJson('<?php echo $gapRepairAllUrl; ?>', {
          as_of_date:  '<?php echo $asOfDate; ?>',
          division_id: <?php echo (int)$selDivId; ?>,
          destination: '<?php echo addslashes($selDest ?? 'ALL'); ?>'
        });
        var data = json.data || {};
        var detail = json.message || 'Selesai.';
        if (Array.isArray(data.updated_profiles) && data.updated_profiles.length) {
          detail += '\n\nDirepair:\n' + data.updated_profiles.slice(0, 12).map(function(r) {
            return '• ' + (r.profile_name || r.profile_key || ('monthly#' + r.monthly_id)) + ' — ' + (r.repair_path || '-');
          }).join('\n');
        }
        if (Array.isArray(data.skipped_profiles) && data.skipped_profiles.length) {
          detail += '\n\nMasih perlu review:\n' + data.skipped_profiles.slice(0, 12).map(function(r) {
            return '• ' + (r.profile_name || r.profile_key || ('monthly#' + r.monthly_id)) + ' — ' + (r.repair_path || '-');
          }).join('\n');
        }
        await showAlert(detail, 'Repair Gap Opening');
        window.location.reload();
      } catch(e) {
        gapRepairAllBtn.disabled = false; gapRepairAllBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal menjalankan repair gap opening.', 'Repair Gap Opening');
      }
    });
  }

  // ── Repair Lot Semua ──────────────────────────────────────────────────────
  var lotRepairAllBtn = document.getElementById('src_lot_repair_all_btn');
  if (lotRepairAllBtn) {
    lotRepairAllBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair semua lot FIFO yang mismatch sesuai filter aktif?\n\nProses ini otomatis menjalankan profile sync jika ada profil silang.',
        {title:'Repair Lot Semua', confirmText:'Repair Semua', cancelText:'Batal'}
      );
      if (!ok) return;
      var orig = lotRepairAllBtn.innerHTML;
      lotRepairAllBtn.disabled = true; lotRepairAllBtn.textContent = 'Memproses...';
      try {
        var payload = {
          as_of_date:  '<?php echo $asOfDate; ?>',
          division_id: <?php echo (int)$selDivId; ?>,
          destination: '<?php echo addslashes($selDest ?? 'ALL'); ?>',
          q:           <?php echo json_encode($qSearch ?? ''); ?>
        };
        var json = await postJson('<?php echo $lotRepairAllUrl; ?>', payload);
        var d = json.data || {};
        var detail = json.message || 'Selesai.';
        if ((d.failed||0) > 0 && Array.isArray(d.results)) {
          var failLines = d.results.filter(function(r){return r.status==='failed';})
            .map(function(r){return '• '+r.label+(r.message?' — '+r.message:'');});
          if (failLines.length) detail += '\n\nGagal:\n' + failLines.join('\n');
        }
        await showAlert(detail, 'Repair Lot Semua');
        window.location.reload();
      } catch(e) {
        lotRepairAllBtn.disabled = false; lotRepairAllBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal menjalankan repair lot semua.', 'Repair Lot Semua');
      }
    });
  }

  // ── Repair Material ID ───────────────────────────────────────────────────
  var repairMatIdBtn = document.getElementById('src_repair_material_id_btn');
  if (repairMatIdBtn) {
    repairMatIdBtn.addEventListener('click', async function() {
      var ok = await askConfirm(
        'Repair Material ID akan mengisi kolom material_id di monthly stock untuk item yang sudah punya data bahan baku di katalog.\n\nProses ini aman dan tidak mengubah nilai stok.',
        {title: 'Repair Material ID', confirmText: 'Repair', cancelText: 'Batal'}
      );
      if (!ok) return;
      var orig = repairMatIdBtn.innerHTML;
      repairMatIdBtn.disabled = true; repairMatIdBtn.textContent = 'Memproses...';
      try {
        var json = await postJson('<?php echo $repairMaterialIdUrl; ?>', {
          division_id: <?php echo (int)($selDivId ?? 0); ?>
        });
        await showAlert(
          (json.message || 'Selesai.') + '\n\n' + (json.data && json.data.repaired ? json.data.repaired + ' baris diperbaiki.' : ''),
          'Repair Material ID'
        );
        window.location.reload();
      } catch(e) {
        repairMatIdBtn.disabled = false; repairMatIdBtn.innerHTML = orig;
        await showAlert(e.message || 'Gagal repair material ID.', 'Repair Material ID');
      }
    });
  }

  // ── POS Jobs ──────────────────────────────────────────────────────────────
  function jobBadge(status) {
    var v=String(status||'').toUpperCase(); var m={FAILED:['danger','FAILED'],QUEUED:['info','QUEUED'],PROCESSING:['warning','PROCESSING'],SUCCESS:['success','SUCCESS'],CANCELLED:['secondary','CANCELLED']};
    var r=m[v]||['secondary',v||'-']; return '<span class="badge bg-'+r[0]+'">'+escHtml(r[1])+'</span>';
  }
  async function loadFailedJobs() {
    var p=new URLSearchParams(); p.set('q',String(searchEl?(searchEl.value||''):'')); p.set('limit','8');
    var json=await getJson('<?php echo site_url('pos/orders/runtime-jobs/failed'); ?>?'+p.toString());
    var rows=Array.isArray(json.rows)?json.rows:[];
    if(!rows.length){listEl.innerHTML='';emptyEl.classList.remove('d-none');return;}
    emptyEl.classList.add('d-none');
    listEl.innerHTML=rows.map(function(row){return '<div class="card p-3 mb-0"><div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="fw-semibold small">'+escHtml(row.order_no||'-')+' | '+escHtml(row.commit_no||'-')+'</div><div class="text-muted small">Job '+escHtml(row.job_code||'-')+' · '+escHtml(row.outlet_name||'-')+' · '+escHtml(row.cashier_employee_name||'-')+'</div><div class="text-muted small">'+Number(row.attempts||0)+'/'+Number(row.max_attempts||0)+' percobaan · '+fmtDT(row.run_after||row.updated_at||row.created_at||'')+'</div></div><div class="d-flex flex-column align-items-end gap-1">'+jobBadge(row.status)+'<span class="badge bg-secondary">Stok '+escHtml(row.stock_commit_status||'-')+'</span><button type="button" class="btn btn-xs btn-outline-primary mt-1 src-pos-job-retry" data-job-id="'+Number(row.id||0)+'">Retry</button></div></div><div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">'+escHtml(row.last_error||'Job gagal tanpa detail.')+'</div></div>';}).join('');
  }

  if(searchEl){searchEl.addEventListener('input',function(){clearTimeout(searchTimer);searchTimer=setTimeout(function(){loadFailedJobs().catch(function(e){showAlert(e.message,'Audit POS Queue');});},260);});}
  if(reloadBtn){reloadBtn.addEventListener('click',function(){loadFailedJobs().catch(function(e){showAlert(e.message,'Audit POS Queue');});});}
  if(listEl){listEl.addEventListener('click',async function(ev){
    var retryBtn=ev.target.closest('.src-pos-job-retry'); if(!retryBtn)return;
    var ok=await askConfirm('Retry job stock commit POS ini?',{title:'Retry Stock Commit POS',confirmText:'Retry',cancelText:'Batal'}); if(!ok)return;
    var orig=retryBtn.innerHTML; retryBtn.disabled=true; retryBtn.textContent='Retry...';
    try{await postJson('<?php echo site_url('pos/orders/runtime-jobs/retry'); ?>/'+encodeURIComponent(retryBtn.dataset.jobId||'0'),{});await loadFailedJobs();}catch(e){await showAlert(e.message,'Retry Stock Commit POS');}finally{retryBtn.disabled=false;retryBtn.innerHTML=orig;}
  });}
  if(repairCurBtn){repairCurBtn.addEventListener('click',function(){if(!currentMaterialIdentity)return;runMaterialRepair(currentMaterialIdentity,repairCurBtn).catch(function(e){clearButtonLoading(repairCurBtn);setAuditState(e.message,false);});});}
  document.addEventListener('click',function(ev){
    var ab=ev.target.closest('.src-material-audit-btn');
    if(ab){loadMaterialAudit(buttonIdentity(ab)).catch(function(e){setAuditState(e.message,false);}); return;}
    var rb=ev.target.closest('.src-material-repair-btn');
    if(rb){runMaterialRepair(buttonIdentity(rb),rb).catch(function(e){clearButtonLoading(rb);setAuditState(e.message,false);}); return;}
    var lb=ev.target.closest('.src-lot-repair-btn');
    if(lb){runLotRepair(buttonIdentity(lb),lb).catch(function(e){showAlert(e.message,'Repair Lot FIFO');});}
    var pb=ev.target.closest('.src-profile-breakdown-toggle');
    if(pb){
      var key=pb.getAttribute('data-breakdown-key');
      var bdRows=document.querySelectorAll('.rec-profile-breakdown-row[data-breakdown-key="'+key+'"]');
      bdRows.forEach(function(r){r.style.display=r.style.display==='none'?'':'none';});
      pb.textContent=pb.textContent.includes('▼')?pb.textContent.replace('▼','▲'):pb.textContent.replace('▲','▼');
    }
    var prb=ev.target.closest('.src-profile-repair-btn');
    if(prb){ openProfileRepairModal(prb); }
  });

  // ── Profile Repair Modal ──────────────────────────────────────────────────
  var profileRepairUrl = <?php echo json_encode($profileRepairUrl); ?>;
  var prModal = document.getElementById('profileRepairModal');
  var prModalBs = prModal ? (typeof bootstrap !== 'undefined' ? new bootstrap.Modal(prModal) : null) : null;

  function openProfileRepairModal(btn) {
    if (!prModal) { showAlert('Modal tidak tersedia.', 'Error'); return; }
    var pk       = btn.dataset.profileKey  || '';
    var pname    = btn.dataset.profileName || (pk.slice(0,8)+'…');
    var stock    = Number(btn.dataset.stock  || 0);
    var lot      = Number(btn.dataset.lot    || 0);
    var daily    = Number(btn.dataset.daily  || 0);
    var hasMvt   = btn.dataset.hasMvt === '1';
    var mvt      = hasMvt ? Number(btn.dataset.movement || 0) : null;
    var dLot     = Number(btn.dataset.dLot || 0);  // stock - lot
    var dStk     = hasMvt ? Number(btn.dataset.dStk || 0) : null; // stock - mvt
    var lotBad   = Math.abs(dLot) > 0.01;
    var mvtBad   = hasMvt && Math.abs(dStk) > 0.01;

    // Store context on modal for use in repair
    prModal.dataset.divisionId  = btn.dataset.divisionId  || '0';
    prModal.dataset.materialId  = btn.dataset.materialId  || '0';
    prModal.dataset.destination = btn.dataset.destination || 'ALL';
    prModal.dataset.profileKey  = pk;
    prModal.dataset.asOfDate    = btn.dataset.asOfDate    || '';

    // Populate header
    var titleEl = document.getElementById('prModalTitle');
    if (titleEl) titleEl.textContent = 'Repair Profil — ' + pname;

    // Build analysis HTML
    var rows = [
      ['Stok Divisi', fmtQty(stock), '(monthly_stock closing)'],
      ['Lot FIFO',    fmtQty(lot),   lotBad ? '<span style="color:#b42318;font-weight:700">Selisih ' + (dLot > 0 ? '+' : '') + fmtQty(dLot) + '</span>' : 'OK'],
      ['Mat. Daily',  fmtQty(daily), '(= Stok Divisi)'],
      ['Movement',    hasMvt ? fmtQty(mvt) : '–', hasMvt && mvtBad ? '<span style="color:#b42318;font-weight:700">Selisih ' + (dStk > 0 ? '+' : '') + fmtQty(dStk) + '</span>' : (hasMvt ? 'OK' : 'Tidak ada data movement')],
    ];
    var analysisEl = document.getElementById('prModalAnalysis');
    if (analysisEl) {
      analysisEl.innerHTML = '<table style="width:100%;font-size:.8rem;border-collapse:collapse">'
        + rows.map(function(r){return '<tr><td style="padding:.3rem .5rem;font-weight:600;width:110px">'+escHtml(r[0])+'</td><td style="padding:.3rem .5rem;text-align:right;width:90px">'+escHtml(r[1])+'</td><td style="padding:.3rem .5rem;color:#64748b">'+r[2]+'</td></tr>';}).join('')
        + '</table>';
    }

    // Build action section
    var actionEl = document.getElementById('prModalActions');
    var toStockBtn   = document.getElementById('prRepairToStock');
    var toMvtBtn     = document.getElementById('prRepairToMovement');
    var explanEl     = document.getElementById('prModalExplanation');

    if (actionEl && toStockBtn && toMvtBtn && explanEl) {
      if (!lotBad && !mvtBad) {
        // Shouldn't happen (button only shows when mismatch)
        explanEl.innerHTML = '<div class="alert alert-success py-2 mb-0">Tidak ada selisih yang perlu direpair.</div>';
        toStockBtn.style.display = 'none';
        toMvtBtn.style.display   = 'none';
      } else if (lotBad && !mvtBad) {
        // Only lot mismatch — unambiguous: repair lot to match stock
        var dir = dLot > 0 ? 'dikurangi ' + fmtQty(dLot) : 'ditambah CORR lot ' + fmtQty(Math.abs(dLot));
        explanEl.innerHTML = '<div class="alert alert-warning py-2 mb-0 small">Lot FIFO tidak sesuai stok. Lot akan '+ escHtml(dir) +' agar cocok dengan stok <strong>' + escHtml(fmtQty(stock)) + '</strong>.</div>';
        toStockBtn.style.display = '';
        toStockBtn.textContent   = 'Repair Lot -> Ikuti Stok';
        toMvtBtn.style.display   = 'none';
      } else if (!lotBad && mvtBad) {
        // Only stock vs movement mismatch: only movement-based repair changes data
        explanEl.innerHTML = '<div class="alert alert-info py-2 mb-0 small">Lot sudah sama dengan stok. Yang perlu dibetulkan adalah <strong>stok profil</strong> agar mengikuti closing <strong>movement</strong> (<strong>' + escHtml(fmtQty(mvt)) + '</strong>).</div>';
        toStockBtn.style.display = 'none';
        toMvtBtn.style.display   = '';
        toMvtBtn.textContent     = 'Repair Stok -> Movement (' + fmtQty(mvt) + ')';
      } else {
        // Both mismatched
        explanEl.innerHTML = '<div class="alert alert-danger py-2 mb-0 small">Lot (<strong>' + escHtml(fmtQty(lot)) + '</strong>) tidak cocok stok (<strong>' + escHtml(fmtQty(stock)) + '</strong>), dan stok berbeda dari movement (<strong>' + escHtml(fmtQty(mvt)) + '</strong>). Pilih referensi:</div>';
        toStockBtn.style.display = '';
        toStockBtn.textContent   = 'Pertahankan Stok (' + fmtQty(stock) + ')';
        toMvtBtn.style.display   = '';
        toMvtBtn.textContent     = 'Ikuti Movement (' + fmtQty(mvt) + ')';
      }
    }

    if (prModalBs) { prModalBs.show(); }
    else if (typeof $ !== 'undefined') { $(prModal).modal('show'); }
    else { prModal.style.display = 'block'; prModal.classList.add('show'); }
  }

  async function runProfileRepair(repairMode) {
    var divisionId  = Number(prModal.dataset.divisionId  || 0);
    var materialId  = Number(prModal.dataset.materialId  || 0);
    var destination = prModal.dataset.destination || 'ALL';
    var profileKey  = prModal.dataset.profileKey  || '';
    var asOfDate    = prModal.dataset.asOfDate    || '';

    var actionBtn = repairMode === 'to_movement'
      ? document.getElementById('prRepairToMovement')
      : document.getElementById('prRepairToStock');

    if (prModalBs) { prModalBs.hide(); }
    else if (typeof $ !== 'undefined') { $(prModal).modal('hide'); }

    setButtonLoading(actionBtn, 'Repair...');
    try {
      var json = await postJson(profileRepairUrl, {
        division_id: divisionId,
        material_id: materialId,
        destination: destination,
        profile_key: profileKey,
        repair_mode: repairMode,
        as_of_date:  asOfDate,
      });
      await showAlert(json.message || 'Repair selesai.', 'Repair Profil');
      window.location.reload();
    } catch(e) {
      clearButtonLoading(actionBtn);
      await showAlert(e.message || 'Gagal repair profil.', 'Repair Profil');
    }
  }

  var prToStockBtn = document.getElementById('prRepairToStock');
  var prToMvtBtn   = document.getElementById('prRepairToMovement');
  if (prToStockBtn) { prToStockBtn.addEventListener('click', function() { runProfileRepair('lot_repair'); }); }
  if (prToMvtBtn)   { prToMvtBtn.addEventListener('click',   function() { runProfileRepair('to_movement'); }); }

  if (repairDecisionModal) {
    repairDecisionModal.addEventListener('click', function(ev) {
      var cancelBtn = ev.target.closest('[data-role="cancel"]');
      if (cancelBtn) {
        hideRepairDecisionModal();
        return;
      }
      var choiceBtn = ev.target.closest('.repair-choice-btn');
      if (!choiceBtn) return;
      var rawIdentity = repairDecisionModal.dataset.identity || '{}';
      var identity = {};
      try { identity = JSON.parse(rawIdentity); } catch (e) { identity = {}; }
      hideRepairDecisionModal();
      var fallbackBtn = repairCurBtn || document.querySelector('.src-material-repair-btn');
      runMaterialRepair(identity, fallbackBtn, choiceBtn.dataset.mode || '').catch(function(e){
        showAlert(e.message || 'Gagal menjalankan repair paksa.', 'Repair Bahan');
      });
    });
  }
});
</script>

<!-- Profile Repair Modal -->
<div class="modal fade" id="profileRepairModal" tabindex="-1" aria-labelledby="prModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#312028,#4a2f3a)">
        <h6 class="modal-title text-white mb-0" id="prModalTitle">Repair Profil</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body pb-2">
        <div class="small text-muted mb-2">Analisis selisih untuk profil ini:</div>
        <div id="prModalAnalysis" class="mb-3" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden"></div>
        <div id="prModalExplanation" class="mb-3"></div>
        <div id="prModalActions" class="d-flex gap-2 flex-wrap justify-content-end">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" id="prRepairToStock"    class="btn btn-sm btn-warning">Repair sesuai Stok</button>
          <button type="button" id="prRepairToMovement" class="btn btn-sm btn-info text-white">Repair sesuai Movement</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="repairDecisionModal" tabindex="-1" aria-labelledby="repairDecisionTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2" style="background:linear-gradient(135deg,#2b2430,#46354b)">
        <h6 class="modal-title text-white mb-0" id="repairDecisionTitle">Pilih Arah Repair</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="repairDecisionBody"></div>
        <div id="repairDecisionActions" class="d-flex flex-wrap gap-2 justify-content-end align-items-start"></div>
      </div>
    </div>
  </div>
</div>


