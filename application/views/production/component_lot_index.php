<?php
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$divisions = is_array($divisions ?? null) ? $divisions : [];
$locationFilterOptions = ['' => 'Semua Lokasi', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
$divisionLabel = static function (array $row): string {
    $code = trim((string)($row['division_code'] ?? $row['code'] ?? ''));
    $name = trim((string)($row['division_name'] ?? $row['name'] ?? ''));
    if ($code !== '' && $name !== '') {
        return $code . ' - ' . $name;
    }
    return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$locationGroupLabel = static function ($locationType): string {
    $value = strtoupper(trim((string)$locationType));
    if ($value === 'BAR' || $value === 'KITCHEN') {
        return 'Reguler';
    }
    if ($value === 'BAR_EVENT' || $value === 'KITCHEN_EVENT') {
        return 'Event';
    }
    return $value !== '' ? $value : '-';
};
$summaryOpen = 0;
$summaryBalance = 0.0;
$summaryValue = 0.0;
foreach ($rows as $row) {
    if (strtoupper((string)($row['status'] ?? '')) === 'OPEN') {
        $summaryOpen++;
    }
    $balance = (float)($row['qty_balance'] ?? 0);
    $summaryBalance += $balance;
    $summaryValue += $balance * (float)($row['unit_cost'] ?? 0);
}
?>

<style>
  .component-lot-summary-card,
  .component-lot-filter-card,
  .component-lot-table-card {
    border:1px solid rgba(226,212,200,.88);
    border-radius:22px;
    box-shadow:0 14px 30px rgba(58,38,30,.06);
  }
  .component-lot-summary-card .card-body {
    padding:.9rem 1rem;
  }
  .component-lot-summary-label {
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a7a72;
    margin-bottom:.16rem;
  }
  .component-lot-summary-value {
    font-size:1.2rem;
    font-weight:900;
    color:#312729;
  }
  .component-lot-source-links {
    display:flex;
    gap:.35rem;
    flex-wrap:wrap;
    margin-top:.28rem;
  }
  .component-lot-source-links .btn {
    --bs-btn-padding-y:.16rem;
    --bs-btn-padding-x:.48rem;
    --bs-btn-font-size:.68rem;
    border-radius:999px;
  }
</style>

<div class="mb-3">
  <h4 class="mb-1"><i class="ri ri-stack-line page-title-icon"></i><?php echo html_escape((string)($page_title ?? 'Lot FIFO Base/Prepare')); ?></h4>
  <small class="text-muted">Ledger lot output component yang siap dipakai FIFO saat konsumsi ke produk atau dokumen lanjutan.</small>
</div>

<?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'lot']); ?>
<?php $this->load->view('production/_component_type_tabs', [
    'component_type_base_url' => site_url('production/component-lots'),
    'component_type_filters' => $filters,
    'component_type_active' => (string)($filters['type'] ?? ''),
]); ?>
<?php $this->load->view('production/_component_action_buttons', [
    'component_action_params' => array_filter([
        'month'         => (string)($filters['month'] ?? ''),
        'division_id'   => !empty($filters['division_id']) ? (int)$filters['division_id'] : '',
        'location_type' => (string)($filters['location_type'] ?? ''),
    ], static fn($v) => $v !== '' && $v !== 0 && $v !== '0'),
]); ?>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card h-100 component-lot-summary-card"><div class="card-body"><div class="component-lot-summary-label">Lot Open</div><div class="component-lot-summary-value"><?php echo (int)$summaryOpen; ?></div></div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100 component-lot-summary-card"><div class="card-body"><div class="component-lot-summary-label">Saldo Qty</div><div class="component-lot-summary-value"><?php echo number_format($summaryBalance, 2, ',', '.'); ?></div></div></div>
  </div>
  <div class="col-md-4">
    <div class="card h-100 component-lot-summary-card"><div class="card-body"><div class="component-lot-summary-label">Nilai Lot Aktif</div><div class="component-lot-summary-value"><?php echo number_format($summaryValue, 0, ',', '.'); ?></div></div></div>
  </div>
</div>

<div class="card mb-3 component-lot-filter-card">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('production/component-lots'); ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Cari</label>
        <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Lot / batch / nama component">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['OPEN' => 'Open', 'CLOSED' => 'Closed', 'VOID' => 'Void', 'ALL' => 'Semua'] as $key => $label): ?>
            <option value="<?php echo $key; ?>" <?php echo ((string)($filters['status'] ?? 'OPEN') === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Divisi</label>
        <select name="division_id" class="form-select">
          <option value="0">Semua Divisi</option>
          <?php foreach ($divisions as $division): ?>
            <?php $optionId = (int)($division['id'] ?? 0); ?>
            <option value="<?php echo $optionId; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === $optionId) ? 'selected' : ''; ?>><?php echo html_escape($divisionLabel((array)$division)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Lokasi</label>
        <select name="location_type" class="form-select">
          <?php foreach ($locationFilterOptions as $key => $label): ?>
            <option value="<?php echo html_escape((string)$key); ?>" <?php echo ((string)($filters['location_type'] ?? '') === (string)$key) ? 'selected' : ''; ?>><?php echo html_escape((string)$label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo site_url('production/component-lots'); ?>" class="btn btn-outline-danger">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card component-lot-table-card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
        <thead>
          <tr>
            <th>Lot No</th>
            <th>Tanggal Masuk</th>
            <th>Component</th>
            <th>Divisi/Lokasi</th>
            <th class="text-end">Qty In</th>
            <th class="text-end">Qty Out</th>
            <th class="text-end">Saldo</th>
            <th class="text-end">Unit Cost</th>
            <th>Sumber</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada lot component pada filter ini.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['lot_no'] ?? '-')); ?></div>
                  <small class="text-muted">Batch <?php echo html_escape((string)($row['batch_no'] ?? '-')); ?></small>
                </td>
                <td><?php echo html_escape((string)($row['receipt_date'] ?? '-')); ?></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['component_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($row['component_type'] ?? '-')); ?> • <?php echo html_escape((string)($row['uom_code'] ?? '')); ?></small>
                </td>
                <td>
                  <div><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape($locationGroupLabel((string)($row['location_type'] ?? ''))); ?></small>
                </td>
                <td class="text-end"><?php echo number_format((float)($row['qty_in_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['qty_out_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($row['qty_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($row['unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td>
                  <div><?php echo html_escape((string)($row['source_module'] ?? '-')); ?></div>
                  <div class="component-lot-source-links">
                    <a href="<?php echo html_escape(site_url('production/component-lots/usage/' . (int)($row['id'] ?? 0))); ?>" class="btn btn-outline-primary btn-sm">Pemakaian</a>
                    <?php if ((string)($row['source_table'] ?? '') === 'inv_component_batch' && !empty($row['source_id'])): ?>
                      <a href="<?php echo site_url('production/component-batches/detail/' . (int)$row['source_id']); ?>" class="btn btn-outline-secondary btn-sm"><?php echo html_escape((string)($row['batch_no'] ?? ('Batch #' . (int)$row['source_id']))); ?></a>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?php echo ui_status_badge((string)($row['status'] ?? 'OPEN')); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
