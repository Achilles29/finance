<?php
$storeUrl = site_url('finance/mutations/store');
$baseUrl  = site_url('finance/mutations');
$pg       = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$summary  = (array)($summary ?? []);
$accountBreakdown = (array)($account_breakdown ?? []);
$scope    = (string)($scope ?? 'all');

$buildQuery = static function ($overrides = []) use ($filter_account_id, $date_from, $date_to, $pg, $scope): string {
    $base = [
        'scope'      => $scope,
        'account_id' => $filter_account_id ?? '',
        'date_from'  => $date_from ?? '',
        'date_to'    => $date_to ?? '',
        'per_page'   => $pg['per_page'] ?? 25,
        'page'       => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) return range(1, $totalPages);
    $items = [1];
    $start = max(2, $page - 1);
    $end   = min($totalPages - 1, $page + 1);
    if ($start > 2) $items[] = '...';
    for ($i = $start; $i <= $end; $i++) $items[] = $i;
    if ($end < $totalPages - 1) $items[] = '...';
    $items[] = $totalPages;
    return $items;
};

/* Resolve a clickable URL from ref_module + ref_table + ref_id */
$resolveRefUrl = static function (string $module, string $table, int $refId, int $posLineParentId = 0): string {
    if ($refId <= 0) return '';
    switch (strtoupper($module)) {
        case 'PURCHASE':
            return site_url('purchase-orders/detail/' . $refId);
        case 'POS':
            if ($table === 'pos_payment')      return site_url('pos/reports/payments/' . $refId);
            if ($table === 'pos_payment_line') return $posLineParentId > 0 ? site_url('pos/reports/payments/' . $posLineParentId) : '';
            if ($table === 'pos_refund')       return site_url('pos/reports/refunds/' . $refId);
            return '';
        case 'PAYROLL':
            if ($table === 'pay_cash_advance')        return site_url('payroll/cash-advances');
            if ($table === 'pay_salary_disbursement') return site_url('payroll/salary-disbursements');
            return '';
        default:
            return '';
    }
};

$moduleLabel = static function (string $module, string $table): string {
    switch (strtoupper($module)) {
        case 'PURCHASE':          return 'Purchase';
        case 'POS':               return 'POS';
        case 'FINANCE_TRANSFER':  return 'Transfer';
        case 'FINANCE_PAYABLE':   return 'Utang';
        case 'FINANCE_RECEIVABLE':return 'Piutang';
        case 'FINANCE':           return 'Manual';
        case 'PAYROLL':
            if ($table === 'pay_cash_advance')        return 'Kasbon';
            if ($table === 'pay_salary_disbursement') return 'Gaji';
            return 'Payroll';
        default: return $module ?: '-';
    }
};

$moduleBadgeClass = static function (string $module): string {
    switch (strtoupper($module)) {
        case 'PURCHASE':           return 'bg-label-danger';
        case 'POS':                return 'bg-label-success';
        case 'FINANCE_TRANSFER':   return 'bg-label-info';
        case 'FINANCE_PAYABLE':    return 'bg-label-warning';
        case 'FINANCE_RECEIVABLE': return 'bg-label-primary';
        case 'PAYROLL':            return 'bg-label-secondary';
        default:                   return 'bg-label-secondary';
    }
};

$acctTypeMeta = static function (string $type): array {
    switch (strtoupper($type)) {
        case 'BANK':    return ['accent' => '#1d4ed8', 'soft' => 'rgba(29,78,216,.13)',  'surface' => '#eff4ff', 'icon' => 'ri-bank-line'];
        case 'CASH':    return ['accent' => '#0f766e', 'soft' => 'rgba(15,118,110,.13)', 'surface' => '#eefaf8', 'icon' => 'ri-money-dollar-circle-line'];
        case 'EWALLET': return ['accent' => '#7c3aed', 'soft' => 'rgba(124,58,237,.13)','surface' => '#f5f0ff', 'icon' => 'ri-wallet-3-line'];
        default:        return ['accent' => '#475569', 'soft' => 'rgba(71,85,105,.13)',  'surface' => '#f8fafc', 'icon' => 'ri-safe-line'];
    }
};

$netFlow     = (float)($summary['in_total'] ?? 0) - (float)($summary['out_total'] ?? 0);
$totalBalance = array_sum(array_column($accountBreakdown, 'current_balance'));
$scopeTabs = [
    'all' => [
        'label' => 'Semua',
        'desc' => 'Semua mutasi rekening, manual dan hasil sistem.',
    ],
    'manual' => [
        'label' => 'Mutasi Manual',
        'desc' => 'Hanya mutasi yang diinput dari halaman ini: antar rekening, mutasi IN, dan mutasi OUT.',
    ],
];
?>

<style>
  /* ─── Hero ─────────────────────────────────────────────────── */
  .mut-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid #e8ddd7;
    border-radius: 22px;
    background: linear-gradient(135deg, #fffdfb 0%, #fff5f2 100%);
    box-shadow: 0 10px 28px rgba(67,89,113,.08);
  }
  .mut-hero::before {
    content:'';
    position:absolute;
    width:260px;height:260px;
    top:-130px;right:-60px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(159,33,65,.07) 0%,transparent 72%);
    pointer-events:none;
  }
  .mut-hero-body { position:relative;z-index:1;padding:1rem 1.2rem; }
  .mut-hero-title {
    font-size:1.5rem;font-weight:800;color:#2c2225;
    display:flex;align-items:center;gap:.55rem;margin-bottom:.5rem;
  }
  .mut-hero-sub { color:#76605a;font-size:.8rem; }
  .mut-hero-actions { display:flex;gap:.5rem;flex-shrink:0; }

  /* ─── Summary Cards ─────────────────────────────────────────── */
  .mut-card {
    --mc-accent:#8a1538;--mc-soft:rgba(138,21,56,.13);--mc-surface:#fff5f4;
    position:relative;overflow:hidden;
    border-radius:20px;
    background:linear-gradient(145deg,var(--mc-surface) 0%,#fff 68%);
    box-shadow:0 12px 26px rgba(67,89,113,.09);
    border:1px solid rgba(210,200,195,.6);
  }
  .mut-card::before {
    content:'';position:absolute;
    inset:auto -22px -28px auto;
    width:110px;height:110px;border-radius:50%;
    background:radial-gradient(circle,var(--mc-soft) 0%,transparent 72%);
    pointer-events:none;
  }
  .mut-card .card-body { position:relative;z-index:1;padding:.9rem 1rem; }
  .mut-card--in      { --mc-accent:#0f766e;--mc-soft:rgba(15,118,110,.15);--mc-surface:#eefaf8; }
  .mut-card--out     { --mc-accent:#be123c;--mc-soft:rgba(190,18,60,.14); --mc-surface:#fff0f3; }
  .mut-card--net-pos { --mc-accent:#1d4ed8;--mc-soft:rgba(29,78,216,.14); --mc-surface:#eff4ff; }
  .mut-card--net-neg { --mc-accent:#b45309;--mc-soft:rgba(180,83,9,.14);  --mc-surface:#fff6ee; }
  .mut-card--total   { --mc-accent:#475569;--mc-soft:rgba(71,85,105,.13); --mc-surface:#f8fafc; }
  .mut-card--balance { --mc-accent:#1d4ed8;--mc-soft:rgba(29,78,216,.12); --mc-surface:#eff4ff; }
  .mut-card-kicker {
    display:block;font-size:.69rem;font-weight:800;letter-spacing:.08em;
    text-transform:uppercase;color:var(--mc-accent);margin-bottom:.25rem;
  }
  .mut-card-value {
    font-size:1rem;font-weight:800;color:#1f2a39;line-height:1.15;
    margin-bottom:.2rem;
  }
  .mut-card-sub { font-size:.71rem;color:#6b7280; }
  .mut-card-icon {
    width:2.2rem;height:2.2rem;border-radius:14px;
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--mc-soft);color:var(--mc-accent);font-size:1.05rem;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.7);
    flex-shrink:0;
  }

  /* ─── Account Breakdown Cards ───────────────────────────────── */
  .mut-acct-card {
    border-radius:16px;border:1px solid rgba(210,200,195,.55);
    background:linear-gradient(145deg,var(--mc-surface) 0%,#fff 70%);
    box-shadow:0 8px 18px rgba(67,89,113,.07);
    padding:.75rem .85rem;
    position:relative;overflow:hidden;
  }
  .mut-acct-card::after {
    content:'';position:absolute;
    bottom:-18px;right:-18px;
    width:72px;height:72px;border-radius:50%;
    background:var(--mc-soft);
    pointer-events:none;
  }
  .mut-acct-name  { font-size:.78rem;font-weight:700;color:#1f2a39;line-height:1.2; }
  .mut-acct-code  { font-size:.67rem;color:#6b7280; }
  .mut-acct-bal   { font-size:.9rem;font-weight:800;color:var(--mc-accent);margin:.3rem 0 .18rem; }
  .mut-acct-chips { display:flex;gap:.3rem;flex-wrap:wrap; }
  .mut-acct-chip  {
    font-size:.63rem;font-weight:600;padding:.12rem .38rem;
    border-radius:999px;background:var(--mc-soft);color:var(--mc-accent);
  }
  .mut-acct-icon  {
    width:2rem;height:2rem;border-radius:10px;
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--mc-soft);color:var(--mc-accent);font-size:.95rem;flex-shrink:0;
  }

  /* ─── Filter card ────────────────────────────────────────────── */
  .mut-filter-card.card {
    border:1px solid rgba(210,200,195,.5) !important;
    border-radius:14px !important;
    box-shadow:0 6px 16px rgba(67,89,113,.06);
    background:#fffdfb;
  }
  .mut-filter-card .form-control,
  .mut-filter-card .form-select { border-radius:8px;border-color:rgba(103,88,74,.18);background:#fffdfb;font-size:.81rem; }
  .mut-filter-card .btn { border-radius:8px;font-weight:700; }

  /* ─── Table card ─────────────────────────────────────────────── */
  .mut-board-card {
    border:0;border-radius:22px;
    box-shadow:0 14px 32px rgba(67,89,113,.1);
    /* NO overflow:hidden — it would trap position:sticky inside */
  }
  .mut-board-card > .card-body { padding:1rem 1rem 0; }

  /* ─── Scrollable table (sticky header) ──────────────────────── */
  /*
   * Single container: overflow-y auto triggers vertical scroll,
   * overflow-x auto handles wide tables.
   * position:sticky top:0 on thead works correctly because
   * we have NO horizontal sticky columns — Chrome's dual-axis
   * sticky bug only applies when sticky is needed on BOTH axes.
   */
  .mut-table-scroll {
    max-height: 68vh;
    overflow-y: auto;
    overflow-x: auto;
  }
  .mut-table {
    table-layout: fixed;
    min-width: 1024px;
    width: 100%;
    font-size: .77rem;
  }
  .mut-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    font-size: .73rem;
    font-weight: 700;
    white-space: nowrap;
    border-bottom: 2px solid #e9e3dc;
    padding: .5rem .55rem;
    vertical-align: middle;
  }
  .mut-table tbody td {
    padding: .42rem .55rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(233,227,220,.6);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .mut-table tbody tr:hover td { background: rgba(255,245,242,.7); }
  .mut-table tbody tr:last-child td { border-bottom: 0; }

  /* column widths — total = 1024px, keep min-width in sync */
  .mut-col-date  { width: 98px; }
  .mut-col-no    { width: 130px; }
  .mut-col-acct  { width: 126px; }
  .mut-col-type  { width: 48px;  text-align: center; }
  .mut-col-mod   { width: 74px;  text-align: center; }
  .mut-col-amt   { width: 118px; text-align: right; }
  .mut-col-bal   { width: 108px; text-align: right; }
  .mut-col-ref   { width: 120px; }
  .mut-col-notes { width: 94px; white-space: normal; word-break: break-word; overflow: hidden; }

  /* amount colors */
  .mut-amt-in  { color: #0f766e; font-weight: 700; }
  .mut-amt-out { color: #be123c; font-weight: 700; }

  /* ─── Table footer ───────────────────────────────────────────── */
  .mut-table-footer {
    border-top: 1px solid rgba(67,89,113,.1);
    padding: .65rem 1rem;
    background: rgba(67,89,113,.03);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .5rem;
    font-size: .78rem;
  }
  .mut-table-footer .pagination { margin-bottom:0; }
  .mut-table-footer .page-link { border-radius: 8px !important; font-size:.74rem; }

  /* ─── Notes hyperlink ────────────────────────────────────────── */
  .mut-ref-link {
    display: inline-flex;
    align-items: center;
    gap: .22rem;
    color: inherit;
    text-decoration: none;
    border-bottom: 1px dashed rgba(67,89,113,.3);
    transition: border-color .15s, color .15s;
  }
  .mut-ref-link:hover { color: #8a1538; border-bottom-color: #8a1538; }
  .mut-ref-link i { font-size: .78rem; opacity: .7; }

  /* ─── Modal ──────────────────────────────────────────────────── */
  .mut-modal .modal-content {
    border-radius: 20px;
    border: 0;
    box-shadow: 0 24px 60px rgba(0,0,0,.18);
  }
  .mut-modal .modal-header {
    border-bottom: 1px solid #f0e8e3;
    padding: 1rem 1.2rem .8rem;
    border-radius: 20px 20px 0 0;
    background: linear-gradient(135deg, #fffdfb 0%, #fff5f2 100%);
  }
  .mut-modal .modal-title { font-weight: 800; font-size: 1rem; }
  .mut-modal .modal-body  { padding: 1.1rem 1.2rem; }
  .mut-modal .modal-footer { border-top: 1px solid #f0e8e3; padding: .8rem 1.2rem; }
  .mut-modal .form-label { font-size: .8rem; font-weight: 600; margin-bottom: .25rem; }
  .mut-modal .form-control,
  .mut-modal .form-select { border-radius: 10px; border-color: rgba(103,88,74,.22); font-size: .84rem; }
</style>

<!-- ───────────────── Hero ─────────────────────────────────────── -->
<div class="card mut-hero mb-3">
  <div class="mut-hero-body d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <div class="mut-hero-title">
        <i class="ri ri-exchange-funds-line text-danger"></i>
        <?php echo html_escape($title); ?>
      </div>
      <div class="mut-hero-sub">
        Log mutasi saldo rekening. Periode: <strong><?php echo html_escape((string)$date_from); ?></strong>
        &ndash; <strong><?php echo html_escape((string)$date_to); ?></strong>
        <?php if ((int)($filter_account_id ?? 0) > 0):
            $acctName = '';
            foreach (($accounts ?? []) as $a) {
                if ((int)$a['id'] === (int)$filter_account_id) { $acctName = (string)$a['account_name']; break; }
            }
        ?>
        &bull; Rekening: <strong><?php echo html_escape($acctName); ?></strong>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <?php foreach ($scopeTabs as $tabKey => $tabMeta): ?>
          <?php $isActiveScope = $scope === $tabKey; ?>
          <a
            href="<?php echo site_url('finance/mutations?' . $buildQuery(['scope' => $tabKey, 'page' => 1])); ?>"
            class="btn btn-sm <?php echo $isActiveScope ? 'btn-danger' : 'btn-outline-danger'; ?> fw-bold"
          >
            <?php echo html_escape($tabMeta['label']); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="mut-hero-sub mt-2">
        <?php echo html_escape((string)($scopeTabs[$scope]['desc'] ?? '')); ?>
      </div>
    </div>
    <div class="mut-hero-actions">
      <a href="<?php echo site_url('finance/accounts'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri ri-bank-line me-1"></i>Master Rekening
      </a>
      <button type="button" class="btn btn-sm btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#mutInputModal">
        <i class="ri ri-add-circle-line me-1"></i>Input Mutasi
      </button>
    </div>
  </div>
</div>

<div id="mut-alert-area" class="mb-2"></div>

<!-- ───────────────── Summary Cards ──────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--in">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total IN</span>
            <div class="mut-card-value">Rp <?php echo number_format((float)($summary['in_total'] ?? 0), 0, ',', '.'); ?></div>
            <div class="mut-card-sub">Dana masuk periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-arrow-down-circle-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--out">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total OUT</span>
            <div class="mut-card-value">Rp <?php echo number_format((float)($summary['out_total'] ?? 0), 0, ',', '.'); ?></div>
            <div class="mut-card-sub">Dana keluar periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-arrow-up-circle-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card <?php echo $netFlow >= 0 ? 'mut-card--net-pos' : 'mut-card--net-neg'; ?>">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Net Cashflow</span>
            <div class="mut-card-value"><?php echo ($netFlow >= 0 ? '+' : ''); ?>Rp <?php echo number_format($netFlow, 0, ',', '.'); ?></div>
            <div class="mut-card-sub"><?php echo $netFlow >= 0 ? 'Surplus' : 'Defisit'; ?> periode ini</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-bar-chart-grouped-line"></i></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card h-100 mut-card mut-card--total">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="mut-card-kicker">Total Transaksi</span>
            <div class="mut-card-value"><?php echo number_format((int)($summary['rows_total'] ?? 0)); ?> log</div>
            <div class="mut-card-sub">Rp <?php echo number_format($totalBalance, 0, ',', '.'); ?> saldo total</div>
          </div>
          <span class="mut-card-icon"><i class="ri ri-list-check-2"></i></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ───────────────── Per-Account Breakdown ───────────────────────── -->
<?php if (!empty($accountBreakdown)): ?>
<div class="row g-2 mb-3">
  <?php foreach ($accountBreakdown as $ab):
    $meta = $acctTypeMeta((string)($ab['account_type'] ?? ''));
    $acIn  = (float)($ab['in_total'] ?? 0);
    $acOut = (float)($ab['out_total'] ?? 0);
    $acTx  = (int)($ab['tx_count'] ?? 0);
  ?>
  <div class="col-sm-6 col-md-4 col-lg-3">
    <div class="mut-acct-card" style="--mc-accent:<?php echo $meta['accent']; ?>;--mc-soft:<?php echo $meta['soft']; ?>;--mc-surface:<?php echo $meta['surface']; ?>;">
      <div class="d-flex align-items-start gap-2">
        <span class="mut-acct-icon"><i class="ri <?php echo $meta['icon']; ?>"></i></span>
        <div style="min-width:0;">
          <div class="mut-acct-name"><?php echo html_escape((string)($ab['account_name'] ?? '')); ?></div>
          <div class="mut-acct-code"><?php echo html_escape((string)($ab['account_code'] ?? '')); ?> &bull; <?php echo html_escape((string)($ab['account_type'] ?? '')); ?></div>
          <div class="mut-acct-bal">Rp <?php echo number_format((float)($ab['current_balance'] ?? 0), 0, ',', '.'); ?></div>
          <div class="mut-acct-chips">
            <?php if ($acTx > 0): ?>
            <span class="mut-acct-chip">+<?php echo number_format($acIn, 0, ',', '.'); ?></span>
            <span class="mut-acct-chip" style="background:rgba(190,18,60,.1);color:#be123c;">&minus;<?php echo number_format($acOut, 0, ',', '.'); ?></span>
            <span class="mut-acct-chip" style="background:rgba(71,85,105,.1);color:#475569;"><?php echo $acTx; ?> tx</span>
            <?php else: ?>
            <span class="mut-acct-chip" style="background:rgba(71,85,105,.1);color:#475569;">Tidak ada transaksi</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ───────────────── Main Table Card ────────────────────────────── -->
<div class="card mut-board-card">
  <div class="card-body">
    <!-- Filter bar -->
    <div class="mut-filter-card card mb-3">
      <div class="card-body py-2 px-3">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
          <input type="hidden" name="scope" value="<?php echo html_escape($scope); ?>">
          <div class="col-md-4 col-lg-3">
            <label class="form-label mb-1">Rekening</label>
            <select name="account_id" class="form-select form-select-sm">
              <option value="">Semua rekening</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)($filter_account_id ?? 0) === (int)$a['id']) ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Dari</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label mb-1">Sampai</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
          </div>
          <div class="col-4 col-md-1">
            <label class="form-label mb-1">Per</label>
            <select name="per_page" class="form-select form-select-sm">
              <?php foreach ([10, 25, 50, 100, 200] as $pp): ?>
                <option value="<?php echo $pp; ?>" <?php echo ((int)($pg['per_page'] ?? 25) === $pp) ? 'selected' : ''; ?>><?php echo $pp; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-8 col-md-auto d-flex gap-2 align-items-end">
            <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            <a href="<?php echo $baseUrl; ?>" class="btn btn-sm btn-outline-danger">Clear</a>
            <small class="text-muted ms-1 align-self-center">
              Hal <?php echo (int)($pg['page'] ?? 1); ?>/<?php echo (int)($pg['total_pages'] ?? 1); ?>
              &bull; <?php echo number_format((int)($pg['total'] ?? 0)); ?> baris
            </small>
          </div>
        </form>
      </div>
    </div>

    <!-- Table with sticky header -->
    <div class="mut-table-scroll" id="mutTableScroll">
        <table class="table table-hover mb-0 mut-table">
          <thead>
            <tr>
              <th class="mut-col-date">Tanggal</th>
              <th class="mut-col-no">No Mutasi</th>
              <th class="mut-col-acct">Rekening</th>
              <th class="mut-col-type">Tipe</th>
              <th class="mut-col-mod">Modul</th>
              <th class="mut-col-amt">Nominal</th>
              <th class="mut-col-bal">Before</th>
              <th class="mut-col-bal">After</th>
              <th class="mut-col-ref">Ref No</th>
              <th class="mut-col-notes">Catatan</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="10" class="text-center text-muted py-5">
                <i class="ri ri-inbox-line" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                Belum ada data mutasi pada periode ini.
              </td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r):
                $isIn   = strtoupper((string)($r['mutation_type'] ?? '')) === 'IN';
                $mod              = strtoupper(trim((string)($r['ref_module'] ?? '')));
                $tbl              = (string)($r['ref_table'] ?? '');
                $refId            = (int)($r['ref_id'] ?? 0);
                $refNo            = (string)($r['ref_no'] ?? '');
                $notes            = (string)($r['notes'] ?? '');
                $posLineParentId  = (int)($r['pos_payment_line_parent_id'] ?? 0);
                $refUrl           = $resolveRefUrl($mod, $tbl, $refId, $posLineParentId);
              ?>
              <tr>
                <?php $dt = (string)($r['mutation_date'] ?? ''); ?>
                <td class="mut-col-date" title="<?php echo html_escape($dt); ?>">
                  <div style="font-size:.73rem;"><?php echo html_escape(strlen($dt) >= 10 ? substr($dt, 0, 10) : $dt); ?></div>
                  <?php if (strlen($dt) >= 16): ?>
                    <div style="font-size:.63rem;color:#9ca3af;"><?php echo html_escape(substr($dt, 11, 5)); ?></div>
                  <?php endif; ?>
                </td>
                <td class="mut-col-no" style="font-family:monospace;font-size:.71rem;"><?php echo html_escape((string)($r['mutation_no'] ?? '')); ?></td>
                <td class="mut-col-acct">
                  <div style="font-weight:600;font-size:.76rem;color:#1f2a39;"><?php echo html_escape((string)($r['account_name'] ?? '')); ?></div>
                  <div style="font-size:.67rem;color:#6b7280;"><?php echo html_escape((string)($r['account_code'] ?? '')); ?></div>
                </td>
                <td class="mut-col-type">
                  <?php if ($isIn): ?>
                    <span class="badge bg-label-success" style="font-size:.64rem;">IN</span>
                  <?php else: ?>
                    <span class="badge bg-label-danger"  style="font-size:.64rem;">OUT</span>
                  <?php endif; ?>
                </td>
                <td class="mut-col-mod">
                  <?php if ($mod !== ''): ?>
                    <span class="badge <?php echo $moduleBadgeClass($mod); ?>" style="font-size:.62rem;">
                      <?php echo html_escape($moduleLabel($mod, $tbl)); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                </td>
                <td class="mut-col-amt <?php echo $isIn ? 'mut-amt-in' : 'mut-amt-out'; ?>">
                  <?php echo ($isIn ? '+' : '-'); ?>Rp&nbsp;<?php echo number_format((float)($r['amount'] ?? 0), 0, ',', '.'); ?>
                </td>
                <td class="mut-col-bal" style="color:#6b7280;font-size:.73rem;"><?php echo number_format((float)($r['balance_before'] ?? 0), 0, ',', '.'); ?></td>
                <td class="mut-col-bal" style="font-weight:600;font-size:.73rem;"><?php echo number_format((float)($r['balance_after'] ?? 0), 0, ',', '.'); ?></td>
                <td class="mut-col-ref" style="font-size:.71rem;color:#6b7280;" title="<?php echo html_escape($refNo); ?>">
                  <?php if ($refNo !== '' && $refUrl !== ''): ?>
                    <a href="<?php echo html_escape($refUrl); ?>" class="mut-ref-link" target="_blank" style="font-size:.71rem;"><?php echo html_escape($refNo); ?></a>
                  <?php else: ?>
                    <?php echo html_escape($refNo !== '' ? $refNo : '–'); ?>
                  <?php endif; ?>
                </td>
                <td class="mut-col-notes">
                  <?php if ($notes !== ''): ?>
                    <?php if ($refUrl !== ''): ?>
                      <a href="<?php echo html_escape($refUrl); ?>" class="mut-ref-link" target="_blank">
                        <?php echo html_escape($notes); ?>
                        <i class="ri ri-external-link-line"></i>
                      </a>
                    <?php else: ?>
                      <?php echo html_escape($notes); ?>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">–</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
    </div>
  </div>

  <!-- Pagination footer -->
  <div class="mut-table-footer">
    <small class="text-muted">
      Halaman <strong><?php echo (int)($pg['page'] ?? 1); ?></strong>
      dari <strong><?php echo (int)($pg['total_pages'] ?? 1); ?></strong>
      &bull; Total <strong><?php echo number_format((int)($pg['total'] ?? 0)); ?></strong> baris
    </small>
    <?php if ((int)($pg['total_pages'] ?? 1) > 1):
      $curPage   = (int)($pg['page'] ?? 1);
      $totalPgs  = (int)($pg['total_pages'] ?? 1);
      $prev = max(1, $curPage - 1);
      $next = min($totalPgs, $curPage + 1);
      $pageItems = $buildPageItems($curPage, $totalPgs);
    ?>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?php echo $curPage <= 1 ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo $curPage <= 1 ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $prev])); ?>">&lsaquo;</a>
      </li>
      <?php foreach ($pageItems as $pi): ?>
        <?php if ($pi === '...'): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php else: ?>
          <li class="page-item <?php echo $curPage === (int)$pi ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo site_url('finance/mutations?' . $buildQuery(['page' => (int)$pi])); ?>"><?php echo (int)$pi; ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <li class="page-item <?php echo $curPage >= $totalPgs ? 'disabled' : ''; ?>">
        <a class="page-link" href="<?php echo $curPage >= $totalPgs ? '#' : site_url('finance/mutations?' . $buildQuery(['page' => $next])); ?>">&rsaquo;</a>
      </li>
    </ul>
    <?php endif; ?>
  </div>
</div>

<!-- ───────────────── Input Mutasi Modal ─────────────────────────── -->
<div class="modal fade mut-modal" id="mutInputModal" tabindex="-1" aria-labelledby="mutInputModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mutInputModalLabel">
          <i class="ri ri-add-circle-line text-danger me-2"></i>Input Mutasi Rekening
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="mut-modal-alert"></div>
        <form id="mutation-form" class="row g-3" autocomplete="off">
          <div class="col-12">
            <label class="form-label">Jenis Mutasi <span class="text-danger">*</span></label>
            <select class="form-select" id="mutation_type" required>
              <option value="IN">IN — Dana Masuk</option>
              <option value="OUT">OUT — Dana Keluar</option>
              <option value="TRANSFER">TRANSFER — Antar Rekening</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" id="account_id_label">Rekening <span class="text-danger">*</span></label>
            <select class="form-select" id="account_id" required>
              <option value="">Pilih rekening...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 0, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-none" id="to_account_wrap">
            <label class="form-label">Rekening Tujuan <span class="text-danger">*</span></label>
            <select class="form-select" id="to_account_id">
              <option value="">Pilih rekening tujuan...</option>
              <?php foreach (($accounts ?? []) as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>">
                  <?php echo html_escape((string)$a['account_code'] . ' – ' . (string)$a['account_name']); ?>
                  (Rp <?php echo number_format((float)$a['current_balance'], 0, ',', '.'); ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="mutation_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label">Jumlah <span class="text-danger">*</span></label>
            <input type="number" min="0.01" step="0.01" class="form-control" id="amount" placeholder="0" required>
          </div>
          <div class="col-12">
            <label class="form-label">Reference No</label>
            <input type="text" class="form-control" id="reference_no" placeholder="Opsional">
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea class="form-control" id="notes" rows="2" placeholder="Opsional"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger fw-bold px-4" id="btn-save-mutation">
          <i class="ri ri-save-line me-1"></i>Simpan
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var storeUrl      = <?php echo json_encode($storeUrl); ?>;
  var alertArea     = document.getElementById('mut-alert-area');
  var modalAlert    = document.getElementById('mut-modal-alert');
  var mutTypeEl     = document.getElementById('mutation_type');
  var toWrapEl      = document.getElementById('to_account_wrap');
  var acctLabelEl   = document.getElementById('account_id_label');
  var toAcctEl      = document.getElementById('to_account_id');
  var submitBtn     = document.getElementById('btn-save-mutation');

  function showAlert(el, type, msg) {
    el.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible py-2 mb-2"><button type="button" class="btn-close" data-bs-dismiss="alert"></button>' + msg + '</div>';
  }

  function syncForm() {
    var isT = (mutTypeEl.value || 'IN') === 'TRANSFER';
    toWrapEl.classList.toggle('d-none', !isT);
    toAcctEl.required = isT;
    var lbl = isT ? 'Rekening Sumber <span class="text-danger">*</span>' : 'Rekening <span class="text-danger">*</span>';
    acctLabelEl.innerHTML = lbl;
  }
  mutTypeEl.addEventListener('change', syncForm);
  syncForm();

  /* Reset modal on close */
  document.getElementById('mutInputModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('mutation-form').reset();
    mutTypeEl.value = 'IN';
    syncForm();
    modalAlert.innerHTML = '';
  });

  submitBtn.addEventListener('click', function () {
    var type = mutTypeEl.value || 'IN';
    var payload = {
      account_id:    Number(document.getElementById('account_id').value || 0),
      to_account_id: Number(toAcctEl.value || 0),
      mutation_type: type,
      mutation_date: document.getElementById('mutation_date').value,
      amount:        Number(document.getElementById('amount').value || 0),
      reference_no:  document.getElementById('reference_no').value || null,
      notes:         document.getElementById('notes').value || null
    };

    if (!payload.account_id || !payload.mutation_type || !payload.mutation_date || payload.amount <= 0) {
      showAlert(modalAlert, 'warning', 'Field wajib belum lengkap.');
      return;
    }
    if (type === 'TRANSFER' && (!payload.to_account_id || payload.to_account_id === payload.account_id)) {
      showAlert(modalAlert, 'warning', 'Rekening tujuan wajib dipilih dan harus berbeda dari rekening sumber.');
      return;
    }

    var origHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

    fetch(storeUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
    .then(function (res) {
      if (res.status >= 400 || !res.json || !res.json.ok) {
        throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal simpan mutasi.');
      }
      showAlert(alertArea, 'success', 'Mutasi berhasil disimpan. Halaman akan dimuat ulang&hellip;');
      var modal = bootstrap.Modal.getInstance(document.getElementById('mutInputModal'));
      if (modal) modal.hide();
      window.setTimeout(function () { window.location.reload(); }, 600);
    })
    .catch(function (err) {
      showAlert(modalAlert, 'danger', err.message || 'Gagal simpan mutasi.');
      submitBtn.disabled = false;
      submitBtn.innerHTML = origHtml;
    });
  });
})();
</script>
