<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$lineRows = $line_rows ?? [];
$linksMap = $links_map ?? [];
$divisionOptions = $division_options ?? [];
$limit = (int)($limit ?? 50);
$activeTab = ($active_tab ?? 'notes') === 'lines' ? 'lines' : 'notes';
$notesCount = count((array)$rows);
$lineCount = count((array)$lineRows);
$canCreate = !empty($can_create);
$canVerify = !empty($can_verify);
$canManageOwn = !empty($can_manage_own);
$isPurchaseScope = !empty($is_purchase_scope);

if (!function_exists('finance_dreq_status_badge')) {
    function finance_dreq_status_badge($status)
    {
        switch (strtoupper((string)$status)) {
            case 'SUBMITTED':
                return 'bg-warning text-dark';
            case 'VERIFIED':
                return 'bg-success';
            case 'REJECTED':
                return 'bg-danger';
            case 'VOID':
                return 'bg-secondary';
            default:
                return 'bg-light text-dark';
        }
    }
}

  if (!function_exists('finance_dreq_location_badge')) {
    function finance_dreq_location_badge($destinationType)
    {
      $destinationType = strtoupper(trim((string)$destinationType));
      if (strpos($destinationType, 'EVENT') !== false) {
        return 'Event';
      }
      if (in_array($destinationType, ['BAR', 'KITCHEN', 'OFFICE'], true)) {
        return 'Reguler';
      }
      return $destinationType !== '' ? $destinationType : '-';
    }
  }

    if (!function_exists('finance_dreq_route_badge')) {
      function finance_dreq_route_badge($qtyToSr, $qtyToPo)
      {
        $qtyToSr = (float)$qtyToSr;
        $qtyToPo = (float)$qtyToPo;
        if ($qtyToSr > 0 && $qtyToPo > 0) {
          return ['label' => 'SR + PO', 'class' => 'bg-info-subtle text-dark border'];
        }
        if ($qtyToSr > 0) {
          return ['label' => 'SR', 'class' => 'bg-success-subtle text-success border'];
        }
        return ['label' => 'PO', 'class' => 'bg-warning-subtle text-dark border'];
      }
    }

    $tabQuery = [
      'q' => (string)($filters['q'] ?? ''),
      'status' => (string)($filters['status'] ?? ''),
      'division_id' => (int)($filters['division_id'] ?? 0),
      'date_start' => (string)($filters['date_start'] ?? ''),
      'date_end' => (string)($filters['date_end'] ?? ''),
      'limit' => $limit,
    ];
    $notesTabUrl = site_url('procurement/division-po-sr') . '?' . http_build_query(array_merge($tabQuery, ['tab' => 'notes']));
    $linesTabUrl = site_url('procurement/division-po-sr') . '?' . http_build_query(array_merge($tabQuery, ['tab' => 'lines']));
?>

<style>
  .dreq-view-tabs-wrap {
    padding: 0 1rem 1rem;
  }
  .dreq-view-tabs {
    gap: .5rem;
    padding: .5rem;
    border: 1px solid #eaded7;
    border-radius: 16px;
    background: linear-gradient(180deg, #fffaf7 0%, #f7f1ee 100%);
  }
  .dreq-view-tab {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 260px;
    padding: .9rem 1rem;
    border: 0;
    border-radius: 12px;
    color: #7a2e2b;
    background: transparent;
    transition: all .18s ease;
  }
  .dreq-view-tab:hover {
    color: #5f161b;
    background: rgba(157, 27, 45, .06);
  }
  .dreq-view-tab.active {
    color: #fff;
    background: linear-gradient(135deg, #8f1023 0%, #b51d35 100%);
    box-shadow: 0 10px 24px rgba(143, 16, 35, .18);
  }
  .dreq-view-tab-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(157, 27, 45, .10);
    font-size: 1.1rem;
    flex: 0 0 auto;
  }
  .dreq-view-tab.active .dreq-view-tab-icon {
    background: rgba(255,255,255,.18);
  }
  .dreq-view-tab-body {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
    min-width: 0;
  }
  .dreq-view-tab-title {
    font-weight: 700;
    font-size: .98rem;
  }
  .dreq-view-tab-subtitle {
    font-size: .76rem;
    opacity: .82;
  }
  .dreq-view-tab-count {
    margin-left: auto;
    border-radius: 999px;
    padding: .3rem .6rem;
    font-weight: 700;
    background: rgba(157, 27, 45, .10);
    color: inherit;
  }
  .dreq-view-tab.active .dreq-view-tab-count {
    background: rgba(255,255,255,.18);
  }
  @media (max-width: 767.98px) {
    .dreq-view-tab {
      min-width: 0;
      width: 100%;
    }
    .dreq-view-tabs-wrap {
      padding: 0 .75rem .75rem;
    }
  }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-inbox-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'PO / SR Divisi'); ?></h4>
    <small class="text-muted">
      <?php echo $isPurchaseScope
        ? 'Purchase meninjau pengajuan divisi, menyesuaikan item bila perlu, lalu membentuk SR/PO final.'
        : 'Pegawai divisi membuat dan mengelola pengajuan untuk divisinya sendiri sebelum diverifikasi purchase.'; ?>
    </small>
  </div>
  <?php if ($canCreate): ?>
    <a href="<?php echo site_url('procurement/division-po-sr/create'); ?>" class="btn btn-primary">Buat Pengajuan</a>
  <?php endif; ?>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'division-po-sr']); ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?php echo site_url('procurement/division-po-sr'); ?>">
      <input type="hidden" name="tab" value="<?php echo html_escape($activeTab); ?>">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No request / catatan">
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="">Semua</option>
          <?php foreach ($divisionOptions as $d): ?>
            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
              <?php echo html_escape((string)($d['name'] ?? $d['division_name'] ?? ('DIV#' . $d['id']))); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select">
          <option value="">Semua</option>
          <?php foreach (['SUBMITTED', 'VERIFIED', 'REJECTED', 'VOID'] as $statusOption): ?>
            <option value="<?php echo $statusOption; ?>" <?php echo ((string)($filters['status'] ?? '') === $statusOption) ? 'selected' : ''; ?>><?php echo $statusOption; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari</label>
        <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai</label>
        <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <select name="limit" class="form-select">
          <?php foreach ([25, 50, 100, 200] as $rowLimit): ?>
            <option value="<?php echo $rowLimit; ?>" <?php echo $limit === $rowLimit ? 'selected' : ''; ?>><?php echo $rowLimit; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-10 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?php echo site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="dreq-view-tabs-wrap">
    <ul class="nav dreq-view-tabs">
      <li class="nav-item">
        <a class="nav-link dreq-view-tab <?php echo $activeTab === 'notes' ? 'active' : ''; ?>" href="<?php echo html_escape($notesTabUrl); ?>">
          <span class="dreq-view-tab-icon"><i class="ri-file-list-3-line"></i></span>
          <span class="dreq-view-tab-body">
            <span class="dreq-view-tab-title">Per Nota</span>
            <span class="dreq-view-tab-subtitle">Ringkasan per request</span>
          </span>
          <span class="dreq-view-tab-count"><?php echo $notesCount; ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link dreq-view-tab <?php echo $activeTab === 'lines' ? 'active' : ''; ?>" href="<?php echo html_escape($linesTabUrl); ?>">
          <span class="dreq-view-tab-icon"><i class="ri-list-check-3"></i></span>
          <span class="dreq-view-tab-body">
            <span class="dreq-view-tab-title">Detail Rincian</span>
            <span class="dreq-view-tab-subtitle">Audit line per barang</span>
          </span>
          <span class="dreq-view-tab-count"><?php echo $lineCount; ?></span>
        </a>
      </li>
    </ul>
  </div>

  <?php if ($activeTab === 'notes'): ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>No Request</th>
            <th>Divisi</th>
            <th>Lokasi</th>
            <th>Pengaju</th>
            <th>Status</th>
            <th class="text-end">Line</th>
            <th class="text-end">Qty</th>
            <th>Dokumen</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">Belum ada pengajuan divisi.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $requestId = (int)($row['id'] ?? 0);
                $status = strtoupper((string)($row['status'] ?? 'SUBMITTED'));
                $links = (array)($linksMap[$requestId] ?? []);
                $hasDocs = !empty($links);
                $canEditRow = $canManageOwn && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs;
                $canVerifyRow = $canVerify && $status === 'SUBMITTED';
              ?>
              <tr>
                <td class="text-nowrap">
                  <?php echo html_escape((string)($row['request_date'] ?? '-')); ?>
                  <div class="small text-muted">Need: <?php echo html_escape((string)($row['needed_date'] ?? '-')); ?></div>
                </td>
                <td>
                  <a href="<?php echo site_url('procurement/division-po-sr/detail/' . $requestId); ?>" class="fw-semibold text-decoration-none">
                    <?php echo html_escape((string)($row['request_no'] ?? '-')); ?>
                  </a>
                </td>
                <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo html_escape(finance_dreq_location_badge((string)($row['destination_type'] ?? ''))); ?></span></td>
                <td><?php echo html_escape((string)($row['created_by_username'] ?? '-')); ?></td>
                <td><span class="badge <?php echo finance_dreq_status_badge($status); ?>"><?php echo html_escape($status); ?></span></td>
                <td class="text-end"><?php echo (int)($row['line_total'] ?? 0); ?></td>
                <td class="text-end"><?php echo ui_num((float)($row['qty_total'] ?? 0)); ?></td>
                <td>
                  <?php if (empty($links)): ?>
                    <span class="text-muted small">Belum ada dokumen hasil</span>
                  <?php else: ?>
                    <?php foreach ($links as $link): ?>
                      <div class="mb-1">
                        <span class="badge bg-light text-dark border">
                          <?php echo html_escape((string)($link['doc_type'] ?? '-')); ?>:
                          <?php echo html_escape((string)($link['doc_no'] ?? '-')); ?>
                          (<?php echo html_escape((string)($link['doc_status'] ?? '-')); ?>)
                        </span>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <a href="<?php echo site_url('procurement/division-po-sr/detail/' . $requestId); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                  <?php if ($canEditRow || $canVerifyRow): ?>
                    <a href="<?php echo site_url('procurement/division-po-sr/edit/' . $requestId); ?>" class="btn btn-sm <?php echo $canVerifyRow ? 'btn-primary' : 'btn-outline-warning'; ?>">
                      <?php echo $canVerifyRow ? 'Verifikasi' : 'Edit'; ?>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>No Request</th>
            <th>Divisi</th>
            <th>Lokasi</th>
            <th>Profile</th>
            <th>Jenis</th>
            <th>Route</th>
            <th>UOM</th>
            <th class="text-end">Qty Beli</th>
            <th class="text-end">Qty Isi</th>
            <th class="text-end">Snapshot Stok</th>
            <th>Pengaju</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lineRows)): ?>
            <tr>
              <td colspan="13" class="text-center text-muted py-4">Belum ada rincian pengajuan divisi.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($lineRows as $line): ?>
              <?php
                $requestId = (int)($line['request_id'] ?? 0);
                $route = finance_dreq_route_badge((float)($line['qty_content_to_sr'] ?? 0), (float)($line['qty_content_to_po'] ?? 0));
              ?>
              <tr>
                <td class="text-nowrap">
                  <?php echo html_escape((string)($line['request_date'] ?? '-')); ?>
                  <div class="small text-muted">Line #<?php echo (int)($line['line_no'] ?? 0); ?></div>
                </td>
                <td>
                  <a href="<?php echo site_url('procurement/division-po-sr/detail/' . $requestId); ?>" class="fw-semibold text-decoration-none">
                    <?php echo html_escape((string)($line['request_no'] ?? '-')); ?>
                  </a>
                </td>
                <td><?php echo html_escape((string)($line['division_name'] ?? '-')); ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo html_escape(finance_dreq_location_badge((string)($line['destination_type'] ?? ''))); ?></span></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($line['profile_name'] ?? '-')); ?></div>
                  <?php if (trim((string)($line['line_notes'] ?? '')) !== ''): ?>
                    <div class="small text-muted"><?php echo html_escape((string)($line['line_notes'] ?? '')); ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo html_escape((string)($line['line_kind'] ?? '-')); ?></td>
                <td><span class="badge <?php echo html_escape((string)($route['class'] ?? 'bg-light text-dark border')); ?>"><?php echo html_escape((string)($route['label'] ?? '-')); ?></span></td>
                <td><?php echo html_escape((string)($line['profile_buy_uom_code'] ?? '-')); ?> -> <?php echo html_escape((string)($line['profile_content_uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo ui_num((float)($line['qty_buy_requested'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num((float)($line['qty_content_requested'] ?? 0)); ?></td>
                <td class="text-end"><?php echo ui_num((float)($line['qty_content_available_snapshot'] ?? 0)); ?></td>
                <td><?php echo html_escape((string)($line['created_by_username'] ?? '-')); ?></td>
                <td class="text-end text-nowrap">
                  <a href="<?php echo site_url('procurement/division-po-sr/detail/' . $requestId); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>