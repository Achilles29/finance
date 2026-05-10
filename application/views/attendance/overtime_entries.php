<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$divisionOptions = $division_options ?? [];
$employeeOptions = $employee_options ?? [];
$overtimeStandardOptions = $overtime_standard_options ?? [];
$statusOptions = $status_options ?? ['PENDING', 'APPROVED', 'REJECTED'];
$editRow = $edit_row ?? null;
$isEdit = !empty($editRow);

$editStartTime = '';
$editEndTime = '';
if ($isEdit) {
    $startTs = !empty($editRow['start_at']) ? strtotime((string)$editRow['start_at']) : 0;
    $endTs = !empty($editRow['end_at']) ? strtotime((string)$editRow['end_at']) : 0;
    $editStartTime = $startTs ? date('H:i', $startTs) : '';
    $editEndTime = $endTs ? date('H:i', $endTs) : '';
}

$buildQuery = static function ($overrides = []) use ($filters, $pg) {
    $base = [
        'q' => $filters['q'] ?? '',
        'division_id' => $filters['division_id'] ?? '',
        'employee_id' => $filters['employee_id'] ?? '',
        'status' => $filters['status'] ?? '',
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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Input Lembur Manual'); ?></h4>
    <small class="text-muted">Pilih standar lembur/jam, input jam mulai-selesai, nilai dihitung otomatis dari durasi x tarif standar.</small>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="<?php echo site_url('master/att-overtime-standard'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri-settings-3-line me-1"></i>Master Standar Lembur
    </a>
    <span class="text-muted small">Total: <?php echo (int)$pg['total']; ?></span>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><strong><?php echo $isEdit ? 'Edit Input Lembur' : 'Tambah Input Lembur'; ?></strong></div>
      <div class="card-body">
        <form method="post" action="<?php echo $isEdit ? site_url('attendance/overtime-entries/update/' . (int)$editRow['id']) : site_url('attendance/overtime-entries/store'); ?>" id="overtimeForm" class="row g-2">
          <div class="col-12">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select" required>
              <option value="">Pilih pegawai</option>
              <?php foreach ($employeeOptions as $o): ?>
              <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($editRow['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tanggal Lembur</label>
            <input type="date" name="overtime_date" class="form-control" required value="<?php echo html_escape((string)($editRow['overtime_date'] ?? date('Y-m-d'))); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select">
              <?php foreach ($statusOptions as $s): ?>
              <option value="<?php echo html_escape((string)$s); ?>" <?php echo (strtoupper((string)($editRow['status'] ?? 'APPROVED')) === (string)$s) ? 'selected' : ''; ?>><?php echo html_escape((string)$s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Jam Mulai</label>
            <input type="time" name="start_time" class="form-control" required value="<?php echo html_escape($editStartTime); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Jam Selesai</label>
            <input type="time" name="end_time" class="form-control" required value="<?php echo html_escape($editEndTime); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Standar Lembur / Jam</label>
            <select name="overtime_standard_id" class="form-select" required>
              <option value="">Pilih standar</option>
              <?php foreach ($overtimeStandardOptions as $st): ?>
              <option
                value="<?php echo (int)$st['value']; ?>"
                data-rate="<?php echo html_escape((string)$st['hourly_rate']); ?>"
                <?php echo ((int)($editRow['overtime_standard_id'] ?? 0) === (int)$st['value']) ? 'selected' : ''; ?>
              >
                <?php echo html_escape((string)$st['standard_code'] . ' - ' . (string)$st['standard_name'] . ' (Rp ' . number_format((float)$st['hourly_rate'], 2, ',', '.') . '/jam)'); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tarif Lembur / Jam</label>
            <input type="number" step="0.01" min="0" name="overtime_rate" class="form-control" value="<?php echo html_escape((string)($editRow['overtime_rate'] ?? '0')); ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Estimasi Durasi</label>
            <input type="text" class="form-control" id="otHoursPreview" readonly value="<?php echo number_format((float)($editRow['overtime_hours'] ?? 0), 2, ',', '.'); ?> jam">
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Opsional"><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-primary" data-loading-label="Menyimpan...">
              <?php echo $isEdit ? 'Update Lembur' : 'Simpan Lembur'; ?>
            </button>
            <?php if ($isEdit): ?>
            <a href="<?php echo site_url('attendance/overtime-entries'); ?>" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="<?php echo site_url('attendance/overtime-entries'); ?>" class="row g-2 align-items-end">
          <div class="col-md-3"><label class="form-label mb-1">Cari</label><input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="Nama/NIP/Catatan"></div>
          <div class="col-md-2"><label class="form-label mb-1">Divisi</label><select name="division_id" class="form-select"><option value="">Semua</option><?php foreach($divisionOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['division_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label mb-1">Pegawai</label><select name="employee_id" class="form-select"><option value="">Semua</option><?php foreach($employeeOptions as $o): ?><option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0)===(int)$o['value'])?'selected':''; ?>><?php echo html_escape($o['label']); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Status</label><select name="status" class="form-select"><option value="">Semua</option><?php foreach($statusOptions as $s): ?><option value="<?php echo html_escape((string)$s); ?>" <?php echo (($filters['status'] ?? '')===$s)?'selected':''; ?>><?php echo html_escape((string)$s); ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label mb-1">Dari</label><input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>"></div>
          <div class="col-md-2"><label class="form-label mb-1">Sampai</label><input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>"></div>
          <div class="col-md-1"><label class="form-label mb-1">Per</label><select name="per_page" class="form-select"><?php foreach([10,25,50,100] as $p): ?><option value="<?php echo $p; ?>" <?php echo ((int)$pg['per_page']===$p)?'selected':''; ?>><?php echo $p; ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a href="<?php echo site_url('attendance/overtime-entries'); ?>" class="btn btn-outline-secondary">Reset</a></div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Pegawai</th>
              <th>Waktu Lembur</th>
              <th class="text-end">Jam</th>
              <th>Standar</th>
              <th class="text-end">Tarif</th>
              <th class="text-end">Total</th>
              <th>Status</th>
              <th>Catatan</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data lembur.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <?php
                $status = strtoupper((string)($r['status'] ?? 'PENDING'));
                $statusClass = $status === 'APPROVED' ? 'success' : ($status === 'REJECTED' ? 'danger' : 'warning');
              ?>
              <tr>
                <td><?php echo html_escape((string)($r['overtime_date'] ?? '-')); ?></td>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?></div>
                  <small class="text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></small>
                </td>
                <td>
                  <?php echo html_escape((string)date('d/m/Y H:i', strtotime((string)$r['start_at']))); ?>
                  <div class="text-muted small">s/d <?php echo html_escape((string)date('d/m/Y H:i', strtotime((string)$r['end_at']))); ?></div>
                </td>
                <td class="text-end"><?php echo number_format((float)($r['overtime_hours'] ?? 0), 2, ',', '.'); ?></td>
                <td>
                  <?php
                    $standardName = trim((string)($r['overtime_standard_name'] ?? ''));
                    echo html_escape($standardName !== '' ? $standardName : '-');
                  ?>
                </td>
                <td class="text-end"><?php echo number_format((float)($r['overtime_rate'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold"><?php echo number_format((float)($r['total_overtime_pay'] ?? 0), 2, ',', '.'); ?></td>
                <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo html_escape($status); ?></span></td>
                <td><?php echo html_escape((string)($r['notes'] ?? '-')); ?></td>
                <td class="action-cell text-center">
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" href="<?php echo site_url('attendance/overtime-entries?' . $buildQuery(['edit_id' => (int)$r['id']])); ?>"><i class="ri ri-pencil-line"></i></a>
                  <form method="post" action="<?php echo site_url('attendance/overtime-entries/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus data lembur ini?">
                    <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Menghapus..."><i class="ri ri-delete-bin-line"></i></button>
                  </form>
                </td>
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
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']<=1)?'disabled':''; ?>" href="<?php echo ((int)$pg['page']<=1)?'#':site_url('attendance/overtime-entries?'.$buildQuery(['page'=>$prev])); ?>">&lt;</a>
          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '...'): ?>
              <span class="btn btn-sm btn-outline-secondary disabled">...</span>
            <?php else: ?>
              <a class="btn btn-sm <?php echo ((int)$pg['page']===(int)$item)?'btn-primary':'btn-outline-secondary'; ?>" href="<?php echo site_url('attendance/overtime-entries?'.$buildQuery(['page'=>(int)$item])); ?>"><?php echo (int)$item; ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="btn btn-sm btn-outline-secondary <?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'disabled':''; ?>" href="<?php echo ((int)$pg['page']>=(int)$pg['total_pages'])?'#':site_url('attendance/overtime-entries?'.$buildQuery(['page'=>$next])); ?>">&gt;</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('overtimeForm');
  if (!form) return;
  var dateEl = form.querySelector('[name="overtime_date"]');
  var startEl = form.querySelector('[name="start_time"]');
  var endEl = form.querySelector('[name="end_time"]');
  var standardEl = form.querySelector('[name="overtime_standard_id"]');
  var rateEl = form.querySelector('[name="overtime_rate"]');
  var previewEl = document.getElementById('otHoursPreview');

  function recalcHours() {
    if (!dateEl || !startEl || !endEl || !previewEl) return;
    var date = dateEl.value || '';
    var start = startEl.value || '';
    var end = endEl.value || '';
    if (!date || !start || !end) {
      previewEl.value = '0,00 jam';
      return;
    }

    var startTs = Date.parse(date + 'T' + start + ':00');
    var endTs = Date.parse(date + 'T' + end + ':00');
    if (!Number.isFinite(startTs) || !Number.isFinite(endTs)) {
      previewEl.value = '0,00 jam';
      return;
    }
    if (endTs <= startTs) {
      endTs += 24 * 60 * 60 * 1000;
    }
    var hours = Math.max(0, (endTs - startTs) / 3600000);
    previewEl.value = hours.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' jam';
  }

  ['change', 'keyup'].forEach(function (evt) {
    if (dateEl) dateEl.addEventListener(evt, recalcHours);
    if (startEl) startEl.addEventListener(evt, recalcHours);
    if (endEl) endEl.addEventListener(evt, recalcHours);
  });

  function syncRateFromStandard() {
    if (!standardEl || !rateEl) return;
    var option = standardEl.options[standardEl.selectedIndex];
    var rate = option ? (option.getAttribute('data-rate') || '0') : '0';
    rateEl.value = rate;
  }
  if (standardEl) {
    standardEl.addEventListener('change', syncRateFromStandard);
    syncRateFromStandard();
  }
  recalcHours();
})();
</script>
