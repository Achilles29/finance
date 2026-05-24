<?php
$cardSummary = (array)($card_summary ?? []);
$filteredSummary = (array)($filtered_summary ?? []);
$lineSummary = (array)($line_summary ?? []);
$poRows = (array)($rows ?? []);
$poLineRows = (array)($line_rows ?? []);
$poPageCount = count($poRows);
$poPageValue = 0.0;
foreach ($poRows as $pageRow) {
    $poPageValue += (float)($pageRow['grand_total'] ?? 0);
}
$poLinePageCount = count($poLineRows);
$poLinePageQty = 0.0;
$poLinePageValue = 0.0;
foreach ($poLineRows as $lineRow) {
    $poLinePageQty += (float)($lineRow['qty_buy'] ?? 0);
    $poLinePageValue += (float)($lineRow['line_subtotal'] ?? 0);
}
$activeTab = in_array((string)($tab ?? 'nota'), ['nota', 'rincian'], true) ? (string)$tab : 'nota';
$dateStart = (string)($date_start ?? '');
$dateEnd = (string)($date_end ?? '');
$statusOptions = (array)($status_options ?? ['ALL']);
$activeStatus = strtoupper(trim((string)($status ?? 'ALL')));
if ($activeStatus === '') {
    $activeStatus = 'ALL';
}
$statusLabel = static function (string $value): string {
    $value = strtoupper(trim($value));
    if ($value === '' || $value === 'ALL') {
        return 'Semua';
    }
    return ucwords(strtolower(str_replace('_', ' ', $value)));
};
$buildStatusUrl = static function (string $statusValue) use ($q, $dateStart, $dateEnd, $activeTab): string {
    return site_url('purchase-orders') . '?' . http_build_query([
        'tab' => $activeTab,
        'q' => (string)$q,
        'status' => $statusValue,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
    ]);
};
$summaryStatusText = $statusLabel($activeStatus);
$summaryRangeText = trim(($dateStart !== '' ? $dateStart : '-') . ' s/d ' . ($dateEnd !== '' ? $dateEnd : '-'));

$statusBadgeClass = function (string $status): string {
    $status = strtoupper(trim($status));
    $map = [
        'DRAFT' => 'bg-secondary',
        'APPROVED' => 'bg-info',
        'ORDERED' => 'bg-primary',
        'REJECTED' => 'bg-danger',
        'PARTIAL_RECEIVED' => 'bg-warning text-dark',
        'RECEIVED' => 'bg-success',
        'PAID' => 'bg-info text-dark',
        'CLOSED' => 'bg-dark',
        'VOID' => 'bg-secondary',
    ];
    return $map[$status] ?? 'bg-secondary';
};

$canEditPo = !empty($current_user['is_superadmin']) || !empty($user_perms['purchase.order.index']['can_edit']);
?>

<style>
    .po-table-wrap {
        overflow-x: visible;
        overflow-y: visible;
    }
    .po-table {
        table-layout: fixed;
        width: 100%;
        min-width: 0;
    }
    .po-table > :not(caption) > * > * {
        padding: 0.5rem 0.55rem;
    }
    .po-table th,
    .po-table td {
        white-space: normal;
        vertical-align: middle;
        word-break: break-word;
    }
    .po-col-po {
        width: 16%;
    }
    .po-col-vendor {
        width: 13%;
    }
    .po-col-purchase {
        width: 16%;
    }
    .po-col-value {
        width: 10%;
    }
    .po-col-status {
        width: 11%;
    }
    .po-col-action,
    .po-action-cell {
        width: 7%;
        min-width: 86px;
    }
    .po-status-next {
        min-width: 0;
        width: 5.75rem;
        max-width: 100%;
        font-size: 0.72rem;
        padding-top: 0.22rem;
        padding-bottom: 0.22rem;
        padding-left: 0.35rem;
        padding-right: 1.3rem;
        margin: 0 auto;
    }
    .po-cell-actions {
        display: flex;
        gap: 0.22rem;
        align-items: center;
        flex-wrap: nowrap;
        justify-content: center;
    }
    .po-action-btn {
        width: 1.85rem;
        height: 1.85rem;
        min-width: 1.85rem;
        padding: 0 !important;
        line-height: 1;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        overflow: visible;
        flex: 0 0 1.85rem;
        border-radius: 0.45rem;
    }
    .po-action-btn i {
        font-size: 0.9rem;
    }
    .po-tab-link {
        font-weight: 700;
    }
    .po-status-tab-link {
        font-weight: 600;
        white-space: nowrap;
    }
    .po-status-pill {
        font-size: 0.62rem;
        letter-spacing: 0.04em;
        padding: 0.28rem 0.42rem;
    }
    .po-cell-title {
        font-weight: 700;
        color: #243445;
        line-height: 1.25;
        font-size: 0.79rem;
    }
    .po-cell-subtext {
        display: block;
        margin-top: 0.08rem;
        color: #6c757d;
        font-size: 0.68rem;
        line-height: 1.18;
    }
    .po-money-main {
        font-weight: 700;
        text-align: right;
        line-height: 1.2;
        font-size: 0.79rem;
    }
    .po-table thead th {
        font-size: 0.74rem;
        vertical-align: top;
        white-space: nowrap;
    }
    .po-table tbody td {
        font-size: 0.77rem;
    }
    .po-status-cell {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        align-items: center;
    }
    .po-status-note {
        font-size: 0.66rem;
        line-height: 1.2;
        text-align: center;
    }
    .po-action-cell {
        text-align: center;
    }
    .po-summary-card {
        border: 0;
        box-shadow: 0 6px 22px rgba(67, 89, 113, 0.08);
    }
    .po-summary-card .card-body {
        padding: 0.9rem 1rem;
    }
    .po-summary-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.45rem;
    }
    .po-summary-kicker {
        display: block;
        color: #7b8190;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.2rem;
    }
    .po-summary-count {
        font-size: 1.05rem;
        font-weight: 800;
        color: #243445;
        line-height: 1.1;
    }
    .po-summary-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 0.7rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(168, 16, 39, 0.08);
        color: #a81027;
        flex: 0 0 2rem;
    }
    .po-summary-value {
        display: block;
        font-weight: 800;
        color: #243445;
        font-size: 0.92rem;
        line-height: 1.15;
        margin-bottom: 0.18rem;
    }
    .po-summary-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .po-summary-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.5rem;
        border-radius: 999px;
        background: rgba(67, 89, 113, 0.08);
        color: #566070;
        font-size: 0.68rem;
        line-height: 1.15;
    }
    .po-table-footer {
        border-top: 1px solid rgba(67, 89, 113, 0.12);
        background: rgba(67, 89, 113, 0.04);
        padding: 0.65rem 0.8rem;
        font-size: 0.78rem;
    }
    .po-footer-block {
        padding: 0.45rem 0.55rem;
        border-radius: 0.7rem;
        background: rgba(255, 255, 255, 0.76);
        height: 100%;
    }
    .po-table-footer strong {
        display: block;
        font-size: 0.78rem;
        color: #243445;
    }
    .po-table-footer span {
        color: #6c757d;
        font-size: 0.75rem;
    }
    .po-footer-value {
        display: block;
        font-size: 0.84rem;
        font-weight: 800;
        color: #243445;
        line-height: 1.2;
        margin-top: 0.12rem;
    }
    @media (max-width: 767.98px) {
        .po-summary-card .card-body {
            padding: 0.8rem 0.85rem;
        }
        .po-summary-count {
            font-size: 0.96rem;
        }
        .po-summary-value {
            font-size: 0.86rem;
        }
        .po-table-footer {
            padding: 0.55rem 0.65rem;
        }
    }
    .po-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid #eadcd2;
        border-radius: 24px;
        background: linear-gradient(135deg, #fffdfb 0%, #fff6f2 100%);
        box-shadow: 0 12px 30px rgba(67, 89, 113, 0.08);
    }
    .po-hero::before,
    .po-hero::after {
        content: '';
        position: absolute;
        border-radius: 999px;
        pointer-events: none;
    }
    .po-hero::before {
        width: 280px;
        height: 280px;
        top: -140px;
        right: -80px;
        background: radial-gradient(circle, rgba(159, 33, 65, .08) 0%, rgba(159, 33, 65, 0) 72%);
    }
    .po-hero::after {
        width: 240px;
        height: 240px;
        bottom: -140px;
        left: -70px;
        background: radial-gradient(circle, rgba(196, 95, 61, .08) 0%, rgba(196, 95, 61, 0) 72%);
    }
    .po-hero-body {
        position: relative;
        z-index: 1;
        padding: 1.2rem 1.3rem;
    }
    .po-hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .28rem .62rem;
        border-radius: 999px;
        background: rgba(255,255,255,.14);
        color: #8a1538;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        border: 1px solid rgba(159, 33, 65, .14);
    }
    .po-hero-title {
        margin: .8rem 0 .35rem;
        color: #2c2225;
        font-size: 1.9rem;
        font-weight: 800;
        line-height: 1.05;
    }
    .po-hero-subtitle {
        max-width: 720px;
        color: #655650;
        line-height: 1.5;
    }
    .po-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .95rem;
    }
    .po-hero-chip {
        display: inline-flex;
        align-items: center;
        padding: .34rem .68rem;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #eadcd2;
        color: #564942;
        font-size: .75rem;
        font-weight: 600;
    }
    .po-hero-actions {
        display: flex;
        align-items: center;
        gap: .6rem;
    }
    .po-hero-btn {
        border: 0;
        border-radius: 16px;
        padding: .8rem 1rem;
        font-weight: 800;
        color: #fff;
        background: #9f2141;
        box-shadow: 0 12px 24px rgba(159, 33, 65, .2);
    }
    .po-board-card {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 14px 30px rgba(67, 89, 113, .09);
    }
    .po-summary-card {
        --po-accent: #8a1538;
        --po-soft: rgba(138, 21, 56, .14);
        --po-surface: #fff5f4;
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        background: linear-gradient(145deg, var(--po-surface) 0%, #fff 68%);
        box-shadow: 0 16px 32px rgba(67, 89, 113, .1);
        border: 1px solid rgba(219, 208, 199, .65);
    }
    .po-summary-card::before {
        content: '';
        position: absolute;
        inset: auto -26px -34px auto;
        width: 132px;
        height: 132px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--po-soft) 0%, rgba(255,255,255,0) 72%);
        pointer-events: none;
    }
    .po-summary-card .card-body {
        padding: 1rem 1.05rem;
        position: relative;
        z-index: 1;
    }
    .po-summary-card--primary {
        --po-accent: #8a1538;
        --po-soft: rgba(138, 21, 56, .15);
        --po-surface: #fff3f5;
    }
    .po-summary-card--paid {
        --po-accent: #0f766e;
        --po-soft: rgba(15, 118, 110, .16);
        --po-surface: #eefaf7;
    }
    .po-summary-card--open {
        --po-accent: #b45309;
        --po-soft: rgba(180, 83, 9, .16);
        --po-surface: #fff6ee;
    }
    .po-summary-card--lines {
        --po-accent: #1d4ed8;
        --po-soft: rgba(29, 78, 216, .15);
        --po-surface: #eff4ff;
    }
    .po-summary-kicker {
        color: var(--po-accent);
        font-weight: 800;
        letter-spacing: .08em;
    }
    .po-summary-count,
    .po-summary-value {
        color: #1f2a39;
    }
    .po-summary-icon {
        background: var(--po-soft);
        color: var(--po-accent);
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 18px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
    }
    .po-summary-meta {
        gap: .42rem;
    }
    .po-summary-chip {
        background: rgba(255,255,255,.94);
        border: 1px solid rgba(31, 42, 57, .12);
        color: #46515f;
        font-weight: 600;
    }
    .po-tab-strip {
        gap: .55rem;
        border: 0;
    }
    .po-tab-strip .nav-item {
        margin: 0;
    }
    .po-tab-strip .nav-link {
        border: 0;
        border-radius: 15px;
        padding: .55rem .85rem;
        background: #f6efe9;
        color: #54473f;
        font-weight: 700;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        border: 1px solid #eadcd2;
    }
    .po-tab-strip .nav-link:hover {
        color: #3e332d;
        background: #f1e2d7;
    }
    .po-tab-strip .nav-link.active {
        background: #9f2141;
        color: #fff9f6;
        box-shadow: 0 12px 22px rgba(159, 33, 65, .16);
        border-color: #9f2141;
    }
    .po-board-card .card-body {
        padding: 1rem 1rem 0;
    }
    .po-toolbar input,
    .po-toolbar select {
        border-radius: 12px;
        border-color: rgba(103, 88, 74, .18);
        background: #fffdfb;
    }
    .po-toolbar .btn {
        border-radius: 12px;
        font-weight: 700;
    }
    @media (max-width: 767.98px) {
        .po-hero-title {
            font-size: 1.45rem;
        }
        .po-hero-body {
            padding: 1rem;
        }
        .po-hero-actions {
            width: 100%;
        }
        .po-hero-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="card po-hero mb-3">
    <div class="po-hero-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="po-hero-kicker"><i class="ri ri-command-line"></i> Purchase Overview</span>
            <h4 class="po-hero-title">Purchase Order</h4>
            <div class="po-hero-subtitle">Pusat monitoring PO, receipt, SR, dan utilitas sinkronisasi modul pembelian dalam satu permukaan yang lebih cepat dibaca.</div>
            <div class="po-hero-meta">
                <span class="po-hero-chip">Status: <?php echo html_escape($summaryStatusText); ?></span>
                <span class="po-hero-chip"><?php echo html_escape($summaryRangeText); ?></span>
                <span class="po-hero-chip">View: <?php echo $activeTab === 'rincian' ? 'Per Rincian' : 'Per Nota'; ?></span>
            </div>
        </div>
        <div class="po-hero-actions">
            <a class="btn d-inline-flex align-items-center gap-2 po-hero-btn" href="<?php echo site_url('purchase-orders/create'); ?>"><i class="ri ri-add-line"></i>Create Order</a>
        </div>
    </div>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'purchase-order']); ?>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--primary">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Total PO</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['total_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-file-list-3-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Status: <?php echo html_escape($summaryStatusText); ?></span>
                    <span class="po-summary-chip"><?php echo html_escape($summaryRangeText); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--paid">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">PO Paid</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['paid_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-money-dollar-circle-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['paid_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Sudah dibayar</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--open">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Belum Paid</span>
                        <div class="po-summary-count"><?php echo number_format((int)($cardSummary['unpaid_count'] ?? 0)); ?> nota</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-timer-line"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($cardSummary['unpaid_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Belum status Paid</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 po-summary-card po-summary-card--lines">
            <div class="card-body">
                <div class="po-summary-head">
                    <div>
                        <span class="po-summary-kicker">Rincian Filter</span>
                        <div class="po-summary-count"><?php echo number_format((int)($lineSummary['total_lines'] ?? 0)); ?> baris</div>
                    </div>
                    <span class="po-summary-icon"><i class="ri ri-list-check-2"></i></span>
                </div>
                <span class="po-summary-value">Rp <?php echo number_format((float)($lineSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                <div class="po-summary-meta">
                    <span class="po-summary-chip">Qty <?php echo number_format((float)($lineSummary['total_qty_buy'] ?? 0), 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3 po-board-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 po-toolbar">
            <h6 class="mb-0">Daftar Purchase Order</h6>
            <div class="d-flex gap-2">
                <input type="text" id="po-q" class="form-control form-control-sm" placeholder="Cari PO/vendor/tipe..." value="<?php echo html_escape((string)($q ?? '')); ?>">
                <select id="po-status" class="form-select form-select-sm">
                    <?php foreach (($status_options ?? ['ALL']) as $st): ?>
                        <option value="<?php echo html_escape((string)$st); ?>" <?php echo ((string)($status ?? 'ALL') === (string)$st) ? 'selected' : ''; ?>><?php echo html_escape((string)$st); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" id="po-date-start" class="form-control form-control-sm" value="<?php echo html_escape($dateStart); ?>">
                <input type="date" id="po-date-end" class="form-control form-control-sm" value="<?php echo html_escape($dateEnd); ?>">
                <button type="button" id="btn-filter-po" class="btn btn-sm btn-outline-secondary">Filter</button>
                <button type="button" id="btn-clear-po" class="btn btn-sm btn-outline-danger">Clear</button>
            </div>
        </div>

        <ul class="nav nav-pills po-tab-strip mb-3" role="tablist">
            <?php foreach ($statusOptions as $statusOption): ?>
                <?php $statusOption = strtoupper(trim((string)$statusOption)); ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link po-status-tab-link <?php echo $activeStatus === $statusOption ? 'active' : ''; ?>" href="<?php echo html_escape($buildStatusUrl($statusOption)); ?>"><?php echo html_escape($statusLabel($statusOption)); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <ul class="nav nav-pills po-tab-strip mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link <?php echo $activeTab === 'nota' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=nota&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd); ?>">Per Nota</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=rincian&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')) . '&date_start=' . urlencode($dateStart) . '&date_end=' . urlencode($dateEnd); ?>">Per Rincian</a>
            </li>
        </ul>

        <div class="tab-content p-0 border-0">
            <div class="tab-pane fade <?php echo $activeTab === 'nota' ? 'show active' : ''; ?>" id="po-tab-nota" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <colgroup>
                            <col class="po-col-po">
                            <col class="po-col-vendor">
                            <col class="po-col-purchase">
                            <col class="po-col-value">
                            <col class="po-col-status">
                            <col class="po-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="po-col-po">PO</th>
                                <th class="po-col-vendor">Vendor</th>
                                <th class="po-col-purchase">Pembelian</th>
                                <th class="text-end po-col-value">Nilai</th>
                                <th class="po-col-status">Status</th>
                                <th class="po-col-action text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows ?? [])): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($rows ?? []) as $r): ?>
                                    <?php $statusCurrent = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
                                    <?php $canEditData = in_array($statusCurrent, ['DRAFT', 'APPROVED'], true); ?>
                                    <?php $requiresEditReview = (int)($r['requires_edit_review'] ?? 0) === 1; ?>
                                    <tr>
                                        <td>
                                            <div class="po-cell-title"><?php echo html_escape((string)($r['po_no'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></span>
                                        </td>
                                        <td>
                                            <div class="po-cell-title"><?php echo html_escape((string)($r['vendor_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($r['destination_type'] ?? '-')); ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo html_escape((string)($r['purchase_type_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape(trim((string)($r['payment_account_name'] ?? '')) !== '' ? (string)($r['payment_account_name'] ?? '') : '-'); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="po-money-main"><?php echo number_format((float)($r['grand_total'] ?? 0), 2, ',', '.'); ?></div>
                                        </td>
                                        <td>
                                            <div class="po-status-cell">
                                                <div><span class="badge <?php echo $statusBadgeClass($statusCurrent); ?> po-status-pill"><?php echo html_escape($statusCurrent); ?></span></div>
                                            <?php
                                                $transitions = [
                                                    'DRAFT' => ['APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'APPROVED' => ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'ORDERED' => ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'PARTIAL_RECEIVED' => ['RECEIVED', 'PAID', 'VOID'],
                                                    'RECEIVED' => ['PAID', 'VOID'],
                                                    'PAID' => ['VOID'],
                                                    'CLOSED' => ['VOID'],
                                                    'REJECTED' => [],
                                                    'VOID' => [],
                                                ];
                                                $nextOptions = [];
                                                foreach (($transitions[$statusCurrent] ?? []) as $nextStatus) {
                                                    if (!in_array($nextStatus, $nextOptions, true)) {
                                                        $nextOptions[] = $nextStatus;
                                                    }
                                                }
                                                $statusUpdateDisabled = !$canEditPo || empty($nextOptions) || $requiresEditReview;
                                            ?>
                                                <select class="form-select form-select-sm po-status-next" data-id="<?php echo (int)($r['id'] ?? 0); ?>" <?php echo $statusUpdateDisabled ? 'disabled' : ''; ?>>
                                                    <option value="">Status...</option>
                                                    <?php foreach ($nextOptions as $st): ?>
                                                        <option value="<?php echo html_escape($st); ?>"><?php echo html_escape($st); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($requiresEditReview): ?>
                                                    <div class="text-warning po-status-note">Review buyer via edit dulu.</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="po-action-cell">
                                            <div class="po-cell-actions">
                                                <a href="<?php echo site_url('purchase-orders/detail/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary po-action-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail">
                                                    <i class="ri ri-eye-line"></i>
                                                </a>
                                                <?php if ($canEditPo && $canEditData): ?>
                                                    <a href="<?php echo site_url('purchase-orders/edit/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-warning po-action-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                                                        <i class="ri ri-pencil-line"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!$statusUpdateDisabled): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-po-status-update po-action-btn" data-id="<?php echo (int)($r['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Update Status" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php elseif ($requiresEditReview): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary po-action-btn" disabled title="Wajib review buyer via Edit Data dulu" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary po-action-btn" disabled title="Butuh izin edit" aria-label="Update Status">
                                                        <i class="ri ri-refresh-line"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="po-table-footer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Pagination Saat Ini</strong>
                                <span class="po-footer-value"><?php echo number_format($poPageCount); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format($poPageValue, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Total Range Filter</strong>
                                <span class="po-footer-value"><?php echo number_format((int)($filteredSummary['total_count'] ?? 0)); ?> nota</span>
                                <span>Nilai Rp <?php echo number_format((float)($filteredSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo $activeTab === 'rincian' ? 'show active' : ''; ?>" id="po-tab-rincian" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <colgroup>
                            <col class="po-col-po">
                            <col class="po-col-vendor">
                            <col class="po-col-purchase">
                            <col class="po-col-value">
                            <col class="po-col-status">
                            <col class="po-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="po-col-po">PO</th>
                                <th class="po-col-vendor">Vendor</th>
                                <th class="po-col-purchase">Rincian</th>
                                <th class="text-end po-col-value">Qty</th>
                                <th class="text-end po-col-status">Nilai</th>
                                <th class="po-col-action text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($line_rows ?? [])): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data rincian PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($line_rows ?? []) as $lr): ?>
                                    <?php
                                        $st = strtoupper((string)($lr['status'] ?? 'DRAFT'));
                                        $lineName = trim((string)($lr['snapshot_item_name'] ?? ''));
                                        if ($lineName === '') {
                                            $lineName = trim((string)($lr['snapshot_material_name'] ?? '-'));
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="po-cell-title"><?php echo html_escape((string)($lr['po_no'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($lr['request_date'] ?? '-')); ?></span>
                                            <span class="po-cell-subtext"><span class="badge <?php echo $statusBadgeClass($st); ?> po-status-pill"><?php echo html_escape($st); ?></span></span>
                                        </td>
                                        <td>
                                            <div class="po-cell-title"><?php echo html_escape((string)($lr['vendor_name'] ?? '-')); ?></div>
                                            <span class="po-cell-subtext">Tipe: <?php echo html_escape((string)($lr['purchase_type_name'] ?? '-')); ?></span>
                                        </td>
                                        <td>
                                            <div class="po-cell-title">#<?php echo (int)($lr['line_no'] ?? 0); ?> - <?php echo html_escape($lineName); ?></div>
                                            <span class="po-cell-subtext">Merk: <?php echo html_escape((string)($lr['snapshot_brand_name'] ?? '-')); ?></span>
                                            <span class="po-cell-subtext">Ket: <?php echo html_escape((string)($lr['snapshot_line_description'] ?? '-')); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="po-money-main"><?php echo number_format((float)($lr['qty_buy'] ?? 0), 2, ',', '.'); ?></div>
                                            <span class="po-cell-subtext"><?php echo html_escape((string)($lr['snapshot_buy_uom_code'] ?? '-')); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="po-money-main"><?php echo number_format((float)($lr['line_subtotal'] ?? 0), 2, ',', '.'); ?></div>
                                            <span class="po-cell-subtext">Harga: <?php echo number_format((float)($lr['unit_price'] ?? 0), 2, ',', '.'); ?></span>
                                        </td>
                                        <td class="po-action-cell">
                                            <a href="<?php echo site_url('purchase-orders/detail/' . (int)($lr['purchase_order_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary po-action-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail">
                                                <i class="ri ri-eye-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="po-table-footer">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Pagination Saat Ini</strong>
                                <span class="po-footer-value"><?php echo number_format($poLinePageCount); ?> baris</span>
                                <span>Qty <?php echo number_format($poLinePageQty, 2, ',', '.'); ?> | Nilai Rp <?php echo number_format($poLinePageValue, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="po-footer-block">
                                <strong>Total Range Filter</strong>
                                <span class="po-footer-value"><?php echo number_format((int)($lineSummary['total_lines'] ?? 0)); ?> baris</span>
                                <span>Qty <?php echo number_format((float)($lineSummary['total_qty_buy'] ?? 0), 2, ',', '.'); ?> | Nilai Rp <?php echo number_format((float)($lineSummary['total_value'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function parseJsonResponse(response) {
        return response.text().then(function (txt) {
            var parsed = null;
            try {
                parsed = JSON.parse(txt);
            } catch (e) {
                var snippet = (txt || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                throw new Error('Respons server bukan JSON valid. ' + snippet);
            }
            return { status: response.status, json: parsed };
        });
    }

    var currentTab = <?php echo json_encode($activeTab); ?>;
    var filterBtn = document.getElementById('btn-filter-po');
    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            var q = document.getElementById('po-q').value || '';
            var status = document.getElementById('po-status').value || 'ALL';
            var dateStart = document.getElementById('po-date-start').value || '';
            var dateEnd = document.getElementById('po-date-end').value || '';
            var url = <?php echo json_encode(site_url('purchase-orders')); ?>
                + '?tab=' + encodeURIComponent(currentTab)
                + '&q=' + encodeURIComponent(q)
                + '&status=' + encodeURIComponent(status)
                + '&date_start=' + encodeURIComponent(dateStart)
                + '&date_end=' + encodeURIComponent(dateEnd);
            window.location.href = url;
        });
    }

    var clearBtn = document.getElementById('btn-clear-po');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var url = <?php echo json_encode(site_url('purchase-orders')); ?>
                + '?tab=' + encodeURIComponent(currentTab)
                + '&status=ALL'
                + '&date_start=' + encodeURIComponent(<?php echo json_encode(date('Y-m-d')); ?>)
                + '&date_end=' + encodeURIComponent(<?php echo json_encode(date('Y-m-d')); ?>);
            window.location.href = url;
        });
    }

    var canEditPo = <?php echo $canEditPo ? 'true' : 'false'; ?>;
    if (!canEditPo) {
        document.querySelectorAll('.po-status-next').forEach(function (el) {
            el.disabled = true;
        });
    }

    var endpoint = <?php echo json_encode(site_url('purchase/order/status-update')); ?>;
    document.querySelectorAll('.btn-po-status-update').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!canEditPo) {
                window.alert('Akses edit status tidak tersedia untuk akun ini.');
                return;
            }

            var id = Number(btn.getAttribute('data-id') || 0);
            if (!id) {
                return;
            }

            var select = document.querySelector('.po-status-next[data-id="' + id + '"]');
            var status = select ? (select.value || '') : '';
            if (!status) {
                window.alert('Pilih status tujuan terlebih dahulu.');
                return;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ purchase_order_id: id, status: status })
            })
            .then(parseJsonResponse)
            .then(function (res) {
                if (res.status >= 400 || !res.json || !res.json.ok) {
                    throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal update status');
                }
                window.location.reload();
            })
            .catch(function (err) {
                window.alert(err.message || 'Gagal update status');
            });
        });
    });
})();
</script>
