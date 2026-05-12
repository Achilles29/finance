<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$detailHeader = $detail_header ?? null;
$detailLineBreakdown = $detail_line_breakdown ?? [];
$periodOptions = $payroll_period_options ?? [];
$accounts = $company_account_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'status' => $filters['status'] ?? '',
        'payroll_period_id' => $filters['payroll_period_id'] ?? '',
        'q' => $filters['q'] ?? '',
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Pencairan Gaji'); ?></h4>
    <small class="text-muted">Generate batch pencairan dari payroll period yang sudah difinalisasi.</small>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="<?php echo site_url('payroll/payroll-periods'); ?>">Generate Payroll Period</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?php echo site_url('payroll/salary-disbursements'); ?>">Generate Batch Pencairan Gaji</a></li>
</ul>

<div class="alert alert-info py-2 px-3 mb-3 small">
  Periksa detail payroll period lebih dulu di halaman terpisah, lalu generate batch pencairan di halaman ini.
</div>

<div class="card mb-3">
  <div class="card-header"><strong>Generate Batch Pencairan Gaji</strong></div>
  <div class="card-body">
    <form method="post" action="<?php echo site_url('payroll/salary-disbursements/generate'); ?>" class="row g-2">
      <div class="col-md-4"><label class="form-label mb-1">Payroll Period</label><select name="payroll_period_id" class="form-select" required><option value="">Pilih period</option><?php foreach($periodOptions as $p): ?><option value="<?php echo (int)$p['value']; ?>"><?php echo html_escape((string)$p['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label mb-1">Tgl Pencairan</label><input type="date" name="disbursement_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
      <div class="col-md-5"><label class="form-label mb-1">Rekening Sumber Mutasi (Wajib)</label><select name="company_account_id" class="form-select" required><option value="">Pilih rekening sumber</option><?php foreach($accounts as $a): ?><option value="<?php echo (int)$a['value']; ?>"><?php echo html_escape((string)$a['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><small class="text-muted">Rekening ini akan dipakai saat `Mark Paid` untuk mengurangi saldo perusahaan dan membuat mutation log otomatis.</small></div>
      <div class="col-12"><label class="form-label mb-1">Catatan</label><textarea name="notes" class="form-control" rows="2" placeholder="Opsional"></textarea></div>
      <div class="col-12"><button type="submit" class="btn btn-primary" data-loading-label="Generating...">Generate Batch Gaji</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>No Batch</th><th>Period</th><th>Tgl</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Baris</th><th class="text-center">Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada batch pencairan gaji.</td></tr><?php else: foreach($rows as $r): $status = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
          <tr>
            <td><?php echo html_escape((string)$r['disbursement_no']); ?></td>
            <td><?php echo html_escape((string)($r['period_code'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)$r['disbursement_date']); ?></td>
            <td><span class="badge bg-<?php echo $status === 'PAID' ? 'success' : ($status === 'VOID' ? 'danger' : 'warning'); ?>"><?php echo html_escape($status); ?></span></td>
            <td class="text-end fw-semibold"><?php echo number_format((float)($r['total_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($r['paid_amount'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo (int)($r['line_count'] ?? 0); ?></td>
            <td class="action-cell text-center">
              <a href="<?php echo site_url('payroll/salary-disbursements?' . $buildQuery(['detail_id' => (int)$r['id']])); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Detail"><i class="ri ri-eye-line"></i></a>
              <?php if ($status !== 'PAID' && $status !== 'VOID'): ?>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/mark-paid/' . (int)$r['id']); ?>" class="d-inline">
                  <button type="submit" class="btn btn-sm btn-outline-success action-icon-btn" data-bs-toggle="tooltip" title="Tandai Paid" data-loading-label="Posting..."><i class="ri ri-money-dollar-circle-line"></i></button>
                </form>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID batch gaji ini?">
                  <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Void" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
                </form>
                <form method="post" action="<?php echo site_url('payroll/salary-disbursements/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus batch gaji ini?">
                  <button type="submit" class="btn btn-sm btn-outline-secondary action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Hapus..."><i class="ri ri-delete-bin-line"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($detailHeader)): ?>
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Detail Batch <?php echo html_escape((string)$detailHeader['disbursement_no']); ?></strong>
    <span class="text-muted">Total: <?php echo number_format((float)($detailHeader['total_amount'] ?? 0),2,',','.'); ?></span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th>Rek Tujuan</th><th class="text-end">Pokok</th><th class="text-end">Tunjangan</th><th class="text-end">U. Makan</th><th class="text-end">Lembur</th><th class="text-end">Adj (+)</th><th class="text-end">Kotor Riil</th><th class="text-end">Pot. Telat</th><th class="text-end">Pot. Alpha</th><th class="text-end">Adj (-) Lain</th><th class="text-end">Pot. Kasbon</th><th class="text-end">THP Riil</th><th class="text-end">Pembulatan</th><th class="text-end">THP Final</th><th>Status</th><th>Ref</th><th>Paid At</th></tr></thead>
      <tbody>
      <?php if (empty($detailLineBreakdown)): ?><tr><td colspan="18" class="text-center text-muted py-3">Tidak ada baris detail.</td></tr><?php else: foreach($detailLineBreakdown as $l): ?>
      <?php
        $basic = (float)($l['basic_total'] ?? 0);
        $allowance = (float)($l['allowance_total'] ?? 0);
        $meal = (float)($l['meal_total'] ?? 0);
        $overtime = (float)($l['overtime_total'] ?? 0);
        $manualAdd = (float)($l['manual_addition_total'] ?? 0);
        $grossRiil = round($basic + $allowance + $meal + $overtime + $manualAdd, 2);
        $lateDed = (float)($l['late_deduction_total'] ?? 0);
        $alphaDed = (float)($l['alpha_deduction_total'] ?? 0);
        $manualDedTotal = (float)($l['manual_deduction_total'] ?? 0);
        $cashCut = (float)($l['cash_advance_cut'] ?? 0);
        $manualDedOther = max(0, round($manualDedTotal - $cashCut, 2));
        $riilNet = round($grossRiil - $lateDed - $alphaDed - $manualDedTotal, 2);
        $rounding = round((float)($l['rounding_adjustment'] ?? ((float)($l['net_pay'] ?? 0) - $riilNet)), 2);
        $finalNet = (float)($l['transfer_amount'] ?? ($l['net_pay'] ?? 0));
      ?>
      <tr>
        <td><?php echo html_escape((string)($l['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($l['employee_code_snapshot'] ?? '-')); ?></div></td>
        <td><?php echo html_escape((string)($l['bank_name'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($l['bank_account_no'] ?? '-')); ?> a/n <?php echo html_escape((string)($l['bank_account_name'] ?? '-')); ?></div></td>
        <td class="text-end"><?php echo number_format($basic,2,',','.'); ?></td>
        <td class="text-end"><?php echo number_format($allowance,2,',','.'); ?></td>
        <td class="text-end"><?php echo number_format($meal,2,',','.'); ?></td>
        <td class="text-end"><?php echo number_format($overtime,2,',','.'); ?></td>
        <td class="text-end text-success"><?php echo number_format($manualAdd,2,',','.'); ?></td>
        <td class="text-end"><?php echo number_format($grossRiil,2,',','.'); ?></td>
        <td class="text-end text-danger"><?php echo number_format($lateDed,2,',','.'); ?></td>
        <td class="text-end text-danger"><?php echo number_format($alphaDed,2,',','.'); ?></td>
        <td class="text-end text-danger"><?php echo number_format($manualDedOther,2,',','.'); ?></td>
        <td class="text-end text-danger"><?php echo number_format($cashCut,2,',','.'); ?></td>
        <td class="text-end"><?php echo number_format($riilNet,2,',','.'); ?></td>
        <td class="text-end <?php echo abs($rounding) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($rounding,2,',','.'); ?></td>
        <td class="text-end fw-semibold"><?php echo number_format($finalNet,2,',','.'); ?></td>
        <td><?php echo html_escape((string)($l['transfer_status'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($l['transfer_ref_no'] ?? '-')); ?></td>
        <td><?php echo html_escape((string)($l['paid_at'] ?? '-')); ?></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
