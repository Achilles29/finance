<?php
$tab = $tab ?? 'daily';
if (!in_array($tab, ['daily', 'recap'], true)) {
    $tab = 'daily';
}
$filters = $filters ?? [];
$rows = $rows ?? [];
$recapRows = $recap_rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$recapPg = $recap_pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$activePg = $active_pg ?? ($tab === 'recap' ? $recapPg : $pg);
$divisionOptions = $division_options ?? [];
$statusOptions = $status_options ?? [];

$buildQuery = static function ($overrides = []) use ($filters, $activePg, $tab) {
    $base = [
        'tab' => $tab,
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
        'per_page' => $activePg['per_page'] ?? 25,
        'page' => $activePg['page'] ?? 1,
    ];
    $params = array_merge($base, $overrides);
    return http_build_query($params);
};

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }
    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);
    if ($start > 2) {
        $items[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }
    if ($end < $totalPages - 1) {
        $items[] = '...';
    }
    $items[] = $totalPages;
    return $items;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title ?? 'Rekap Absensi'); ?></h4>
  <span class="text-muted small">Total: <?php echo (int)($activePg['total'] ?? 0); ?></span>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?php echo $tab === 'recap' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/daily?' . $buildQuery(['tab' => 'recap', 'page' => 1])); ?>">Rekap Bulanan Per Pegawai</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $tab === 'daily' ? 'active' : ''; ?>" href="<?php echo site_url('attendance/daily?' . $buildQuery(['tab' => 'daily', 'page' => 1])); ?>">Harian</a>
  </li>
</ul>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/daily'); ?>" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
      <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Shift"></div>
      <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
      <?php if ($tab === 'daily'): ?>
      <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $o): ?><option value="<?php echo html_escape($o); ?>" <?php echo (($filters['status'] ?? '')===$o)?'selected':''; ?>><?php echo html_escape($o); ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
      <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$activePg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><button type="submit" class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/daily?tab=' . $tab); ?>">Reset</a></div>
    </form>
  </div>
</div>

<?php if ($tab === 'recap'): ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Divisi / Jabatan</th>
          <th class="text-end">Jadwal</th>
          <th class="text-end">Hadir</th>
          <th class="text-end">Alpha</th>
          <th class="text-end">Telat</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Lembur (mnt)</th>
          <th class="text-end">Gaji</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">U. Makan</th>
          <th class="text-end">Lembur (Rp)</th>
          <th class="text-end">Potongan</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gross</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP</th>
          <th class="text-center">Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($recapRows)): ?>
        <tr><td colspan="20" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($recapRows as $r): ?>
        <?php
          $deductionTotal = (float)($r['late_deduction_total'] ?? 0) + (float)($r['alpha_deduction_total'] ?? 0);
          $detailUrl = site_url('attendance/estimate/detail/' . (int)$r['employee_id'] . '?' . $buildQuery());
        ?>
        <tr>
          <td><div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '')); ?></div><small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small></td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?> / <?php echo html_escape((string)($r['position_name'] ?? '-')); ?></td>
          <td class="text-end"><?php echo (int)($r['scheduled_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['present_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['alpha_days'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['late_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['overtime_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['basic_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['allowance_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['meal_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['overtime_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format($deductionTotal, 2, ',', '.'); ?></td>
          <td class="text-end text-success"><?php echo number_format((float)($r['manual_addition_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format((float)($r['manual_deduction_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end <?php echo ((float)($r['manual_adjustment_net_total'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format((float)($r['manual_adjustment_net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['gross_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format((float)($r['net_only_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-end fw-semibold text-success"><?php echo number_format((float)($r['net_total'] ?? 0), 2, ',', '.'); ?></td>
          <td class="text-center"><a class="btn btn-sm btn-outline-primary" href="<?php echo $detailUrl; ?>">Detail</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>NIP</th>
          <th>Nama</th>
          <th>Divisi</th>
          <th>Jabatan</th>
          <th>Shift</th>
          <th>Check-in</th>
          <th>Check-out</th>
          <th>Status</th>
          <th class="text-end">Telat</th>
          <th class="text-end">Pulang Cepat</th>
          <th class="text-end">Kerja (mnt)</th>
          <th class="text-end">Lembur (mnt)</th>
          <th class="text-end">Basic</th>
          <th class="text-end">Tunjangan</th>
          <th class="text-end">Uang Makan</th>
          <th class="text-end">Lembur</th>
          <th class="text-end">Pot. Telat</th>
          <th class="text-end">Pot. Alpha</th>
          <th class="text-end">Adj. (+)</th>
          <th class="text-end">Adj. (-)</th>
          <th class="text-end">Adj. Net</th>
          <th class="text-end">Gross</th>
          <th class="text-end">Net</th>
          <th class="text-end">THP Harian</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="25" class="text-center text-muted py-4">Tidak ada data.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <?php $isClosed = !empty($r['checkout_at']); ?>
        <tr>
          <td><?php echo html_escape((string)$r['attendance_date']); ?></td>
          <td><?php echo html_escape((string)$r['employee_code']); ?></td>
          <td><?php echo html_escape((string)$r['employee_name']); ?></td>
          <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['position_name'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['checkin_at'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)($r['checkout_at'] ?? '-')); ?></td>
          <td><?php echo html_escape((string)$r['attendance_status']); ?><?php echo (!empty($r['checkin_at']) && empty($r['checkout_at'])) ? ' (OPEN)' : ''; ?></td>
          <td class="text-end"><?php echo (int)($r['late_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['early_leave_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['work_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo (int)($r['overtime_minutes'] ?? 0); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['basic_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['allowance_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['meal_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['overtime_pay'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['late_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['alpha_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end text-success"><?php echo number_format($isClosed ? (float)($r['manual_addition_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end text-danger"><?php echo number_format($isClosed ? (float)($r['manual_deduction_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end <?php echo ((float)($r['manual_adjustment_net_amount'] ?? 0) >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($isClosed ? (float)($r['manual_adjustment_net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['gross_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['net_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
          <td class="text-end"><?php echo number_format($isClosed ? (float)($r['daily_salary_amount'] ?? 0) : 0, 2, ',', '.'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (($activePg['total_pages'] ?? 1) > 1): ?>
<div class="card mt-2">
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$activePg['page']; ?> dari <?php echo (int)$activePg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev=max(1,(int)$activePg['page']-1); $next=min((int)$activePg['total_pages'],(int)$activePg['page']+1); ?>
      <?php $pageItems = $buildPageItems((int)$activePg['page'], (int)$activePg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$activePg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$activePg['page']<=1)?'#':site_url('attendance/daily?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$activePg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/daily?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$activePg['page']>=(int)$activePg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$activePg['page']>=(int)$activePg['total_pages'])?'#':site_url('attendance/daily?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
    </div>
  </div>
</div>
<?php endif; ?>
