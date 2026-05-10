<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page'=>1,'total_pages'=>1,'per_page'=>25,'total'=>0];
$divisionOptions = $division_options ?? [];
$shiftOptions = $shift_options ?? [];
$employeeOptions = $employee_options ?? [];
$currentUser = $current_user ?? [];
$userPerms = $user_perms ?? [];

$isSuperadmin = !empty($currentUser['is_superadmin']);
$canEdit = $isSuperadmin || !empty($userPerms['attendance.schedules.index']['can_edit']);
$canDelete = $isSuperadmin || !empty($userPerms['attendance.schedules.index']['can_delete']);

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'shift_code' => $filters['shift_code'] ?? '',
        'date_start' => $filters['date_start'] ?? '',
        'date_end' => $filters['date_end'] ?? '',
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

<style>
  .att-schedules .action-wrap {
    display: flex;
    gap: .35rem;
    align-items: center;
  }
  .att-schedules .action-wrap .btn {
    min-height: 34px;
    padding: 0 .55rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
  }
  .att-schedules .table th,
  .att-schedules .table td {
    padding: 0.72rem 0.85rem;
    vertical-align: middle;
  }
</style>

<div class="att-schedules">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="ri-calendar-check-line me-2 text-primary"></i><?php echo html_escape($title ?? 'Jadwal Shift Pegawai'); ?></h5>
    <div class="d-flex gap-2">
      <a href="<?php echo site_url('attendance/schedules-v2'); ?>" class="btn btn-outline-primary"><i class="ri-table-line me-1"></i>Jadwal V2</a>
      <?php if ($canEdit): ?>
      <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#createScheduleBox" aria-expanded="false" aria-controls="createScheduleBox">
        <i class="ri-add-line me-1"></i>Tambah Jadwal
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($canEdit): ?>
  <div class="collapse mb-3" id="createScheduleBox">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post" action="<?php echo site_url('attendance/schedules/store'); ?>" class="row g-2 align-items-end">
          <div class="col-md-4"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select" required><option value="">Pilih Pegawai</option><?php foreach($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label mb-1">Shift</label><select name="shift_id" class="form-select" required><option value="">Pilih Shift</option><?php foreach($shiftOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>"><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Tanggal</label><input type="date" name="schedule_date" class="form-control" required></div>
          <div class="col-md-2"><label class="form-label mb-1">Catatan</label><input type="text" name="notes" class="form-control" maxlength="255" placeholder="Opsional"></div>
          <div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit"><i class="ri-check-line"></i></button></div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <form method="get" action="<?php echo site_url('attendance/schedules'); ?>" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Shift"></div>
        <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label mb-1">Shift Code</label><input type="text" name="shift_code" class="form-control" value="<?php echo html_escape((string)($filters['shift_code'] ?? '')); ?>"></div>
        <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
        <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
        <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line me-1"></i>Filter</button> <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/schedules'); ?>">Reset</a></div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Tanggal</th><th>NIP</th><th>Nama</th><th>Divisi</th><th>Shift</th><th>Catatan</th><?php if($canEdit||$canDelete): ?><th style="width:130px;">Aksi</th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="<?php echo ($canEdit||$canDelete)?'7':'6'; ?>" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?php echo html_escape((string)$r['schedule_date']); ?></td>
            <td><?php echo html_escape((string)$r['employee_code']); ?></td>
            <td><?php echo html_escape((string)$r['employee_name']); ?></td>
            <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
            <td><?php echo html_escape((string)($r['shift_code'] ?? '-')); ?> - <?php echo html_escape((string)($r['shift_name'] ?? '')); ?></td>
            <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
            <?php if($canEdit||$canDelete): ?>
            <td>
              <div class="action-wrap">
                <?php if($canEdit): ?>
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editScheduleModal<?php echo (int)$r['id']; ?>" title="Edit">
                    <i class="ri-edit-line me-1"></i><span>Edit</span>
                  </button>
                <?php endif; ?>
                <?php if($canDelete): ?>
                  <form method="post" action="<?php echo site_url('attendance/schedules/delete/'.(int)$r['id'].'?'.$buildQuery()); ?>" onsubmit="return confirm('Hapus jadwal ini?');">
                    <button class="btn btn-outline-danger" type="submit" title="Hapus">
                      <i class="ri-delete-bin-line me-1"></i><span>Hapus</span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
            <?php endif; ?>
          </tr>

        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pg['total_pages'] ?? 1) > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small>Halaman <?php echo (int)$pg['page']; ?> dari <?php echo (int)$pg['total_pages']; ?></small>
      <div class="btn-group">
        <?php $prev=max(1,(int)$pg['page']-1); $next=min((int)$pg['total_pages'],(int)$pg['page']+1); ?>
        <?php $pageItems = $buildPageItems((int)$pg['page'], (int)$pg['total_pages']); ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/schedules?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
        <?php foreach ($pageItems as $item): ?>
          <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">...</span>
          <?php else: ?>
            <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/schedules?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/schedules?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($rows) && $canEdit): ?>
  <?php foreach ($rows as $r): ?>
  <div class="modal fade" id="editScheduleModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="<?php echo site_url('attendance/schedules/update/'.(int)$r['id'].'?'.$buildQuery()); ?>">
          <div class="modal-header">
            <h6 class="modal-title">Edit Jadwal: <?php echo html_escape((string)$r['employee_name']); ?></h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="schedule_date" class="form-control" value="<?php echo html_escape((string)$r['schedule_date']); ?>" required></div>
            <div class="mb-2"><label class="form-label">Shift</label><select name="shift_id" class="form-select" required><?php foreach($shiftOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value']===(int)($r['shift_id'] ?? 0))?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label">Catatan</label><input type="text" name="notes" class="form-control" value="<?php echo html_escape((string)($r['notes'] ?? '')); ?>" maxlength="255"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
