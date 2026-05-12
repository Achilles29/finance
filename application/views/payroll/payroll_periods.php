<?php
$periodFilters = $period_filters ?? [];
$periodRows = $period_rows ?? [];
$periodPg = $period_pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 10, 'total' => 0];
$periodDetailRows = $period_detail_rows ?? [];
$periodBreakdownRows = $period_breakdown_rows ?? [];
$periodDetailId = (int)($period_detail_id ?? 0);

$buildQuery = static function ($overrides = []) use ($periodFilters, $periodPg) {
    $base = [
        'period_status' => $periodFilters['status'] ?? '',
        'period_q' => $periodFilters['q'] ?? '',
        'period_page' => $periodPg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Generate Payroll Period'); ?></h4>
    <small class="text-muted">Halaman ini khusus snapshot payroll period + review kecocokan sebelum pencairan batch.</small>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="<?php echo site_url('payroll/payroll-periods'); ?>">Generate Payroll Period</a></li>
  <li class="nav-item"><a class="nav-link" href="<?php echo site_url('payroll/salary-disbursements'); ?>">Generate Batch Pencairan Gaji</a></li>
</ul>

<div class="card mb-3">
  <div class="card-header"><strong>Generate Payroll Period</strong></div>
  <div class="card-body">
    <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-generate'); ?>" class="row g-2">
      <div class="col-md-3"><label class="form-label mb-1">Kode</label><input type="text" name="period_code" class="form-control" value="<?php echo date('Y-m'); ?>"></div>
      <div class="col-md-3"><label class="form-label mb-1">Mulai</label><input type="date" name="period_start" class="form-control" required value="<?php echo date('Y-m-01'); ?>"></div>
      <div class="col-md-3"><label class="form-label mb-1">Akhir</label><input type="date" name="period_end" class="form-control" required value="<?php echo date('Y-m-t'); ?>"></div>
      <div class="col-md-3">
        <label class="form-label mb-1">Pembulatan Net</label>
        <select name="rounding_mode" class="form-select">
          <option value="NONE">Tanpa Pembulatan</option>
          <option value="UP_1000">Naik ke Ribuan</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label mb-1">Catatan</label><textarea name="notes" class="form-control" rows="2" placeholder="Opsional"></textarea></div>
      <div class="col-12"><button type="submit" class="btn btn-primary" data-loading-label="Menghitung...">Generate / Refresh Period</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><strong>Daftar Payroll Period</strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Periode</th><th>Status</th><th class="text-end">Pegawai</th><th class="text-end">Net</th><th class="text-center">Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($periodRows)): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada payroll period.</td></tr><?php else: foreach($periodRows as $p): ?>
        <tr>
          <td><a href="<?php echo site_url('payroll/payroll-periods?' . $buildQuery(['period_detail_id' => (int)$p['id']])); ?>"><?php echo html_escape((string)$p['period_code']); ?></a><div class="small text-muted"><?php echo html_escape((string)$p['period_start']); ?> s/d <?php echo html_escape((string)$p['period_end']); ?></div></td>
          <td><?php echo html_escape((string)$p['status']); ?></td>
          <td class="text-end"><?php echo (int)($p['employee_count'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format((float)($p['net_pay_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="action-cell text-center">
            <a href="<?php echo site_url('payroll/payroll-periods?' . $buildQuery(['period_detail_id' => (int)$p['id']])); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Detail"><i class="ri ri-eye-line"></i></a>
            <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-void/' . (int)$p['id']); ?>" class="d-inline" data-confirm="Reset period ini ke DRAFT?">
              <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Reset/VOID"><i class="ri ri-restart-line"></i></button>
            </form>
            <form method="post" action="<?php echo site_url('payroll/salary-disbursements/period-delete/' . (int)$p['id']); ?>" class="d-inline" data-confirm="Hapus period ini?">
              <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus"><i class="ri ri-delete-bin-line"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($periodDetailId > 0): ?>
<div class="card mt-3">
  <div class="card-header"><strong>Summary Result Period #<?php echo $periodDetailId; ?></strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th class="text-end">Total Kotor (Riil)</th><th class="text-end">THP Riil (Snapshot)</th><th class="text-end">Pembulatan</th><th class="text-end">THP Final (Payroll)</th><th class="text-end">Validasi ke Absensi</th><th>Status</th></tr></thead>
      <tbody>
        <?php if(empty($periodDetailRows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada result.</td></tr>
        <?php else: foreach($periodDetailRows as $r):
          $payrollNet = (float)($r['net_pay'] ?? 0);
          $riilNet = (float)($r['net_pay_raw'] ?? $payrollNet);
          $roundingDiff = round((float)($r['rounding_adjustment'] ?? ($payrollNet - $riilNet)), 2);
          $attendanceNet = (float)($r['attendance_net_total'] ?? 0);
          $guardDiff = round($riilNet - $attendanceNet, 2);
        ?>
          <tr>
            <td><?php echo html_escape((string)($r['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($r['employee_code_snapshot'] ?? '')); ?></div></td>
            <td class="text-end"><?php echo number_format((float)($r['gross_pay'] ?? 0),2,',','.'); ?></td>
            <td class="text-end"><?php echo number_format($riilNet,2,',','.'); ?></td>
            <td class="text-end <?php echo abs($roundingDiff) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($roundingDiff,2,',','.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($payrollNet,2,',','.'); ?></td>
            <td class="text-end <?php echo abs($guardDiff) > 0.009 ? 'text-danger fw-semibold' : 'text-success'; ?>"><?php echo number_format($guardDiff,2,',','.'); ?></td>
            <td><?php echo html_escape((string)($r['status'] ?? '-')); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><strong>Breakdown Komponen Period #<?php echo $periodDetailId; ?></strong></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Pegawai</th><th class="text-end">Gaji Pokok</th><th class="text-end">Tunjangan</th><th class="text-end">U. Makan</th><th class="text-end">Lembur</th><th class="text-end">Adj (+)</th><th class="text-end">Kotor Riil</th><th class="text-end">Pot. Telat</th><th class="text-end">Pot. Alpha</th><th class="text-end">Adj (-) Lain</th><th class="text-end">Pot. Kasbon</th><th class="text-end">THP Riil</th><th class="text-end">Pembulatan</th><th class="text-end">THP Final</th></tr></thead>
      <tbody>
        <?php if(empty($periodBreakdownRows)): ?>
          <tr><td colspan="14" class="text-center text-muted py-3">Tidak ada breakdown.</td></tr>
        <?php else: foreach($periodBreakdownRows as $b): ?>
          <?php
            $basic = (float)($b['basic_total'] ?? 0);
            $allowance = (float)($b['allowance_total'] ?? 0);
            $meal = (float)($b['meal_total'] ?? 0);
            $overtime = (float)($b['overtime_total'] ?? 0);
            $manualAdd = (float)($b['manual_addition_total'] ?? 0);
            $lateDed = (float)($b['late_deduction_total'] ?? 0);
            $alphaDed = (float)($b['alpha_deduction_total'] ?? 0);
            $manualDedTotal = (float)($b['manual_deduction_total'] ?? 0);
            $cashCut = (float)($b['cash_advance_cut'] ?? 0);
            $manualDedOther = max(0, round($manualDedTotal - $cashCut, 2));
            $grossRiil = round($basic + $allowance + $meal + $overtime + $manualAdd, 2);
            $riilNet = round($grossRiil - $lateDed - $alphaDed - $manualDedTotal, 2);
            $finalNet = (float)($b['net_pay'] ?? 0);
            $roundingAdj = round($finalNet - $riilNet, 2);
          ?>
          <tr>
            <td><?php echo html_escape((string)($b['employee_name_snapshot'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($b['employee_code_snapshot'] ?? '')); ?></div></td>
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
            <td class="text-end <?php echo abs($roundingAdj) > 0.009 ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo number_format($roundingAdj,2,',','.'); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($finalNet,2,',','.'); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
