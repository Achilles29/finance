<?php
$baseUrl = site_url('inventory/fifo-audit');
$issues = is_array($issues ?? null) ? $issues : [];
$divisionOptions = is_array($divisions ?? null) ? $divisions : [];
$destinationGuardMap = is_array($destination_guard_map ?? null) ? $destination_guard_map : [];
$scopeValue = strtoupper(trim((string)($scope ?? 'ALL')));
$statusValue = strtoupper(trim((string)($status ?? 'POSTED')));
$destinationValue = strtoupper(trim((string)($destination ?? 'ALL')));
if ($destinationValue === '') {
    $destinationValue = 'ALL';
}

$formatDivisionLabel = static function (string $code, string $name, $fallbackId = '-'): string {
    $code = trim($code);
    $name = trim($name);
    if ($code !== '' && strcasecmp($code, $name) === 0) {
        return $code;
    }
    if ($code !== '' && $name !== '') {
        return $code . ' - ' . $name;
    }
    if ($code !== '') {
        return $code;
    }
    if ($name !== '') {
        return $name;
    }
    return (string)$fallbackId;
};

$destinationLabel = static function (?string $value): string {
    $map = [
        'BAR' => 'Bar Reguler',
        'KITCHEN' => 'Kitchen Reguler',
        'BAR_EVENT' => 'Bar Event',
        'KITCHEN_EVENT' => 'Kitchen Event',
        'OFFICE' => 'Office Reguler',
        'GUDANG' => 'Gudang',
        'OTHER' => 'Reguler',
        'REGULER' => 'Reguler',
        'EVENT' => 'Event',
        'ALL' => 'Semua',
    ];
    $key = strtoupper(trim((string)$value));
    return $map[$key] ?? ($key !== '' ? $key : '-');
};

$statusClass = static function (string $value): string {
    $key = strtoupper(trim($value));
    if ($key === 'VOID') {
        return 'fin-status-badge fin-status-void';
    }
    if ($key === 'POSTED') {
        return 'fin-status-badge fin-status-posted';
    }
    return 'fin-status-badge fin-status-neutral';
};

$summaryIssueCount = count($issues);
$summaryQty = 0.0;
$summaryCost = 0.0;
$summaryLineCount = 0;
foreach ($issues as $issueRow) {
    $summaryQty += (float)($issueRow['issue_qty'] ?? 0);
    $summaryCost += (float)($issueRow['total_cost'] ?? 0);
    $summaryLineCount += (int)($issueRow['line_count'] ?? count((array)($issueRow['line_rows'] ?? [])));
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-git-branch-line page-title-icon"></i><?php echo html_escape($title ?? 'Audit FIFO Material'); ?></h4>
    <small class="text-muted">Audit khusus lot FIFO material untuk transfer gudang ke divisi dan pemakaian FIFO di divisi.</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo site_url('inventory-warehouse-daily'); ?>" class="btn btn-outline-secondary">Snapshot Harian Gudang</a>
    <a href="<?php echo site_url('inventory-material-daily'); ?>" class="btn btn-outline-secondary">Daily Material Matrix</a>
    <a href="<?php echo site_url('inventory/stock/warehouse/movement'); ?>" class="btn btn-outline-secondary">Mutasi Gudang</a>
    <a href="<?php echo site_url('inventory/stock/division/movement'); ?>" class="btn btn-outline-secondary">Mutasi Divisi</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label mb-1">Scope</label>
        <select class="form-select" name="scope">
          <option value="ALL" <?php echo $scopeValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="WAREHOUSE" <?php echo $scopeValue === 'WAREHOUSE' ? 'selected' : ''; ?>>Warehouse</option>
          <option value="DIVISION" <?php echo $scopeValue === 'DIVISION' ? 'selected' : ''; ?>>Division</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="POSTED" <?php echo $statusValue === 'POSTED' ? 'selected' : ''; ?>>Posted</option>
          <option value="ALL" <?php echo $statusValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="VOID" <?php echo $statusValue === 'VOID' ? 'selected' : ''; ?>>Void</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select class="form-select" name="division_id" id="fifoDivision">
          <option value="">Semua Divisi</option>
          <?php foreach ($divisionOptions as $divisionRow): ?>
            <?php
              $id = (int)($divisionRow['id'] ?? 0);
              $label = $formatDivisionLabel((string)($divisionRow['code'] ?? ''), (string)($divisionRow['name'] ?? ''), $id);
            ?>
            <option value="<?php echo $id; ?>" <?php echo ((int)($division_id ?? 0) === $id) ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Tujuan</label>
        <select class="form-select" name="destination" id="fifoDestination">
          <option value="ALL" <?php echo $destinationValue === 'ALL' ? 'selected' : ''; ?>>Semua</option>
          <option value="REGULER" <?php echo $destinationValue === 'REGULER' ? 'selected' : ''; ?>>Reguler</option>
          <option value="EVENT" <?php echo $destinationValue === 'EVENT' ? 'selected' : ''; ?>>Event</option>
          <option value="BAR" <?php echo $destinationValue === 'BAR' ? 'selected' : ''; ?>>Bar Reguler</option>
          <option value="KITCHEN" <?php echo $destinationValue === 'KITCHEN' ? 'selected' : ''; ?>>Kitchen Reguler</option>
          <option value="BAR_EVENT" <?php echo $destinationValue === 'BAR_EVENT' ? 'selected' : ''; ?>>Bar Event</option>
          <option value="KITCHEN_EVENT" <?php echo $destinationValue === 'KITCHEN_EVENT' ? 'selected' : ''; ?>>Kitchen Event</option>
          <option value="OFFICE" <?php echo $destinationValue === 'OFFICE' ? 'selected' : ''; ?>>Office Reguler</option>
          <option value="OTHER" <?php echo $destinationValue === 'OTHER' ? 'selected' : ''; ?>>Lainnya</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($q ?? '')); ?>" placeholder="Issue no / item / material / ref / catatan">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Dari Tanggal</label>
        <input type="date" class="form-control" name="date_from" value="<?php echo html_escape((string)($date_from ?? '')); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sampai Tanggal</label>
        <input type="date" class="form-control" name="date_to" value="<?php echo html_escape((string)($date_to ?? '')); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label mb-1">Limit</label>
        <input type="number" class="form-control" min="1" max="500" name="limit" value="<?php echo (int)($limit ?? 100); ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Dokumen FIFO</div><div class="h5 mb-0"><?php echo number_format($summaryIssueCount); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Line Alokasi</div><div class="h5 mb-0"><?php echo number_format($summaryLineCount); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Qty</div><div class="h5 mb-0"><?php echo number_format($summaryQty, 2, ',', '.'); ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Total Cost</div><div class="h5 mb-0">Rp <?php echo number_format($summaryCost, 2, ',', '.'); ?></div></div></div></div>
</div>

<div class="card">
  <div class="card-body pb-0">
    <small class="fin-audit-note">Halaman ini menjadi audit FIFO terpisah. Before / Delta / After di level lot memakai snapshot yang tersimpan saat issue diposting; histori lama yang belum punya snapshot tetap tampil tetapi kolom snapshot bisa kosong.</small>
  </div>
  <div class="card-body">
    <?php if (empty($issues)): ?>
      <div class="text-center text-muted py-4">Belum ada issue FIFO yang sesuai filter.</div>
    <?php else: ?>
      <div class="accordion" id="fifoAuditAccordion">
        <?php foreach ($issues as $index => $issue): ?>
          <?php
            $issueId = (int)($issue['id'] ?? 0);
            $collapseId = 'fifo-issue-' . $issueId;
            $isDivisionTarget = strtoupper((string)($issue['target_scope'] ?? '')) === 'DIVISION';
            $sourceDivisionLabel = $formatDivisionLabel((string)($issue['source_division_code'] ?? ''), (string)($issue['source_division_name'] ?? ''), (string)($issue['division_id'] ?? '-'));
            $targetDivisionLabel = $formatDivisionLabel((string)($issue['target_division_code'] ?? ''), (string)($issue['target_division_name'] ?? ''), (string)($issue['target_division_id'] ?? '-'));
            $objectText = trim((string)($issue['material_code'] ?? '') . ' - ' . (string)($issue['material_name'] ?? ''));
            if ($objectText === '-' || $objectText === '') {
                $objectText = trim((string)($issue['item_code'] ?? '') . ' - ' . (string)($issue['item_name'] ?? ''));
            }
            if ($objectText === '-' || $objectText === '') {
                $objectText = (string)($issue['profile_key'] ?? '-');
            }
          ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="<?php echo $collapseId; ?>-header">
              <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                <span class="me-3"><strong><?php echo html_escape((string)($issue['issue_no'] ?? '-')); ?></strong></span>
                <span class="me-3 small text-muted"><?php echo html_escape((string)($issue['issue_date'] ?? '-')); ?></span>
                <span class="me-3"><span class="<?php echo $statusClass((string)($issue['status'] ?? '')); ?>"><?php echo html_escape((string)($issue['status'] ?? '-')); ?></span></span>
                <span class="me-3 small text-muted"><?php echo html_escape($objectText); ?></span>
                <span class="small text-muted">Qty <?php echo number_format((float)($issue['issue_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($issue['content_uom_code'] ?? '')); ?></span>
              </button>
            </h2>
            <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $collapseId; ?>-header" data-bs-parent="#fifoAuditAccordion">
              <div class="accordion-body">
                <div class="row g-2 mb-3">
                  <div class="col-md-3"><small class="text-muted d-block">Flow</small><strong><?php echo html_escape((string)($issue['location_scope'] ?? '-')); ?></strong> <?php echo html_escape($destinationLabel((string)($issue['destination_type'] ?? ''))); ?><?php echo !empty($issue['target_scope']) ? ' -> ' . html_escape((string)$issue['target_scope']) . ' ' . html_escape($destinationLabel((string)($issue['target_destination_type'] ?? ''))) : ''; ?></div>
                  <div class="col-md-3"><small class="text-muted d-block">Divisi Efektif</small><strong><?php echo html_escape($isDivisionTarget ? $targetDivisionLabel : $sourceDivisionLabel); ?></strong></div>
                  <div class="col-md-2"><small class="text-muted d-block">Issue Qty</small><strong><?php echo number_format((float)($issue['issue_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($issue['content_uom_code'] ?? '')); ?></strong></div>
                  <div class="col-md-2"><small class="text-muted d-block">Total Cost</small><strong>Rp <?php echo number_format((float)($issue['total_cost'] ?? 0), 2, ',', '.'); ?></strong></div>
                  <div class="col-md-2"><small class="text-muted d-block">Line Count</small><strong><?php echo (int)($issue['line_count'] ?? count((array)($issue['line_rows'] ?? []))); ?></strong></div>
                  <div class="col-md-6"><small class="text-muted d-block">Ref Sumber</small><strong><?php echo html_escape((string)($issue['source_module'] ?? '-')); ?></strong> | <?php echo html_escape((string)($issue['source_table'] ?? '-')); ?><?php echo !empty($issue['source_id']) ? ' #' . (int)$issue['source_id'] : ''; ?><?php echo !empty($issue['source_line_id']) ? ' / Line #' . (int)$issue['source_line_id'] : ''; ?></div>
                  <div class="col-md-6"><small class="text-muted d-block">Catatan</small><strong><?php echo html_escape((string)($issue['notes'] ?? '-')); ?></strong></div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0 fin-audit-table">
                    <thead>
                      <tr>
                        <th>Lot Sumber</th>
                        <th class="text-end col-balance">Before</th>
                        <th class="text-end col-delta">Delta</th>
                        <th class="text-end col-balance">After</th>
                        <th>Lot Target</th>
                        <th class="text-end col-balance">Before</th>
                        <th class="text-end col-delta">Delta</th>
                        <th class="text-end col-balance">After</th>
                        <th class="text-end col-amount">Unit Cost</th>
                        <th class="text-end col-amount">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $lineRows = (array)($issue['line_rows'] ?? []); ?>
                      <?php if (empty($lineRows)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-3">Belum ada line alokasi FIFO.</td></tr>
                      <?php else: ?>
                        <?php foreach ($lineRows as $lineRow): ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?php echo html_escape((string)($lineRow['source_lot_no'] ?? '-')); ?></div>
                              <div class="small text-muted">Receipt <?php echo html_escape((string)($lineRow['source_receipt_date'] ?? '-')); ?><?php echo !empty($lineRow['source_expiry_date']) ? ' | Exp ' . html_escape((string)$lineRow['source_expiry_date']) : ''; ?></div>
                            </td>
                            <td class="text-end col-balance"><?php echo $lineRow['source_balance_before'] !== null ? number_format((float)$lineRow['source_balance_before'], 2, ',', '.') : '-'; ?></td>
                            <td class="text-end col-delta fin-audit-delta-negative"><?php echo number_format((float)($lineRow['qty_out'] ?? 0) * -1, 2, ',', '.'); ?></td>
                            <td class="text-end col-balance"><?php echo $lineRow['source_balance_after'] !== null ? number_format((float)$lineRow['source_balance_after'], 2, ',', '.') : '-'; ?></td>
                            <td>
                              <div class="fw-semibold"><?php echo html_escape((string)($lineRow['target_lot_no'] ?? '-')); ?></div>
                              <div class="small text-muted"><?php echo !empty($lineRow['target_receipt_date']) ? 'Receipt ' . html_escape((string)$lineRow['target_receipt_date']) : 'Tidak ada lot target'; ?><?php echo !empty($lineRow['target_expiry_date']) ? ' | Exp ' . html_escape((string)$lineRow['target_expiry_date']) : ''; ?></div>
                            </td>
                            <td class="text-end col-balance"><?php echo $lineRow['target_balance_before'] !== null ? number_format((float)$lineRow['target_balance_before'], 2, ',', '.') : '-'; ?></td>
                            <td class="text-end col-delta fin-audit-delta-positive"><?php echo $lineRow['target_lot_id'] ? number_format((float)($lineRow['qty_out'] ?? 0), 2, ',', '.') : '-'; ?></td>
                            <td class="text-end col-balance"><?php echo $lineRow['target_balance_after'] !== null ? number_format((float)$lineRow['target_balance_after'], 2, ',', '.') : '-'; ?></td>
                            <td class="text-end col-amount">Rp <?php echo number_format((float)($lineRow['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                            <td class="text-end col-amount">Rp <?php echo number_format((float)($lineRow['total_cost'] ?? 0), 2, ',', '.'); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var divisionEl = document.getElementById('fifoDivision');
  var destinationEl = document.getElementById('fifoDestination');
  var guardMap = <?php echo json_encode($destinationGuardMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  if (!divisionEl || !destinationEl) {
    return;
  }

  function applyDestinationGuard() {
    var divisionId = String(divisionEl.value || '');
    var allowed = guardMap[divisionId] || [];
    Array.prototype.forEach.call(destinationEl.options, function (opt) {
      var value = String(opt.value || '').toUpperCase();
      if (!divisionId || value === 'ALL' || value === 'REGULER' || value === 'EVENT') {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      var keep = allowed.indexOf(value) >= 0;
      opt.hidden = !keep;
      opt.disabled = !keep;
    });
    if (destinationEl.selectedOptions.length === 0 || destinationEl.selectedOptions[0].disabled) {
      destinationEl.value = 'ALL';
    }
  }

  divisionEl.addEventListener('change', applyDestinationGuard);
  applyDestinationGuard();
})();
</script>
