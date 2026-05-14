<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];

$buildQuery = static function (array $overrides = []) use ($filters, $pg): string {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'is_eligible' => $filters['is_eligible'] ?? '',
        'month' => $filters['month'] ?? date('Y-m'),
        'per_page' => $pg['per_page'] ?? 25,
        'page' => $pg['page'] ?? 1,
    ];
    return http_build_query(array_merge($base, $overrides));
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
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Rekap PH Pegawai'); ?></h4>
    <small class="text-muted">Ringkasan saldo PH semua pegawai + aktivitas bulan terpilih.</small>
  </div>
  <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?php echo site_url('attendance/ph-recap'); ?>" class="row g-2 align-items-end">
      <div class="col-md-4"><label class="form-label mb-1">Cari</label><input type="text" class="form-control" name="q" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Divisi/Jabatan"></div>
      <div class="col-md-3"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach ($divisionOptions as $opt): ?><option value="<?php echo (int)$opt['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0) === (int)$opt['value']) ? 'selected' : ''; ?>><?php echo html_escape($opt['label']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Eligible</label><select name="is_eligible" class="form-select"><option value="">Semua</option><option value="1" <?php echo ((string)($filters['is_eligible'] ?? '') === '1') ? 'selected' : ''; ?>>Eligible</option><option value="0" <?php echo ((string)($filters['is_eligible'] ?? '') === '0') ? 'selected' : ''; ?>>Non-Eligible</option></select></div>
      <div class="col-md-2"><label class="form-label mb-1">Bulan</label><input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($filters['month'] ?? date('Y-m'))); ?>"></div>
      <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $pp): ?><option value="<?php echo $pp; ?>" <?php echo ((int)$pg['per_page']===$pp)?'selected':''; ?>><?php echo $pp; ?></option><?php endforeach; ?></select></div>
      <div class="col-md-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/ph-recap'); ?>">Reset</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Divisi/Jabatan</th>
          <th>Status</th>
          <th class="text-end">Grant Total</th>
          <th class="text-end">Use Total</th>
          <th class="text-end">Expire Total</th>
          <th class="text-end">Adjust Total</th>
          <th class="text-end">Grant Bulan</th>
          <th class="text-end">Use Bulan</th>
          <th class="text-end">Expire Bulan</th>
          <th class="text-end">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Belum ada data rekap PH.</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <?php
            $grant = (float)($row['grant_total'] ?? 0);
            $use = (float)($row['use_total'] ?? 0);
            $expire = (float)($row['expire_total'] ?? 0);
            $adjust = (float)($row['adjust_total'] ?? 0);
            $balance = ($grant + $adjust) - ($use + $expire);
          ?>
          <tr>
            <td><div class="fw-semibold"><?php echo html_escape((string)($row['employee_name'] ?? '')); ?></div><small class="text-muted"><?php echo html_escape((string)($row['employee_code'] ?? '')); ?></small></td>
            <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?> / <?php echo html_escape((string)($row['position_name'] ?? '-')); ?></td>
            <td><?php if ((int)($row['is_eligible'] ?? 0) === 1): ?><span class="badge bg-success">Eligible</span><?php else: ?><span class="badge bg-secondary">Non-Eligible</span><?php endif; ?></td>
            <td class="text-end text-success"><?php echo number_format($grant, 2, ',', '.'); ?></td>
            <td class="text-end text-primary"><?php echo number_format($use, 2, ',', '.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format($expire, 2, ',', '.'); ?></td>
            <td class="text-end text-info"><?php echo number_format($adjust, 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($row['grant_month'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end"><?php echo number_format((float)($row['use_month'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end text-danger"><?php echo number_format((float)($row['expire_month'] ?? 0), 2, ',', '.'); ?></td>
            <td class="text-end fw-semibold <?php echo ($balance >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($balance, 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (($pg['total_pages'] ?? 1) > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
    <div class="btn-group">
      <?php $prev = max(1, (int)$pg['page'] - 1); $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1); ?>
      <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] <= 1) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] <= 1) ? '#' : site_url('attendance/ph-recap?' . $buildQuery(['page' => $prev])); ?>">&lt;</a>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '...'): ?>
          <span class="btn btn-sm btn-outline-secondary disabled">...</span>
        <?php else: ?>
          <a class="btn btn-sm <?php echo ((int)$pg['page'] === (int)$item) ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/ph-recap?' . $buildQuery(['page' => (int)$item])); ?>"><?php echo (int)$item; ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? 'disabled' : ''; ?>" href="<?php echo ((int)$pg['page'] >= (int)$pg['total_pages']) ? '#' : site_url('attendance/ph-recap?' . $buildQuery(['page' => $next])); ?>">&gt;</a>
    </div>
  </div>
  <?php endif; ?>
</div>
