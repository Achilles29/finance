<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Portal Pegawai</h4>
    <small class="text-muted">Beranda personal untuk absensi, jadwal, kontrak, dan payroll.</small>
  </div>
  <?php if (!empty($employeeOptions)): ?>
  <form method="get" action="<?php echo site_url('my'); ?>" class="d-flex gap-2">
    <select name="employee_id" class="form-select form-select-sm" style="min-width:260px">
      <option value="">Pilih Pegawai (Preview Superadmin)</option>
      <?php foreach ($employeeOptions as $o): ?>
        <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)$o['value'] === $selectedEmployeeId) ? 'selected' : ''; ?>>
          <?php echo html_escape($o['label']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-primary">Buka</button>
  </form>
  <?php endif; ?>
</div>

<?php if (!$employee): ?>
<div class="alert alert-warning">
  Akun ini belum terhubung ke data pegawai. Hubungkan `auth_user.employee_id` ke `org_employee.id` untuk membuka fitur portal pegawai.
</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <small class="text-muted d-block">Pegawai</small>
        <h5 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h5>
        <div class="text-muted small"><?php echo html_escape((string)$employee['employee_code']); ?></div>
        <div class="mt-2">
          <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
          <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-2">Akses Cepat</h6>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?php echo site_url('my/attendance' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-primary btn-sm">Absensi Saya</a>
          <a href="<?php echo site_url('my/profile' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary btn-sm">Profil & Kontrak</a>
          <a href="<?php echo site_url('my/schedule' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary btn-sm">Jadwal Shift</a>
          <a href="<?php echo site_url('my/payroll' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : '')); ?>" class="btn btn-outline-secondary btn-sm">Slip Gaji</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
