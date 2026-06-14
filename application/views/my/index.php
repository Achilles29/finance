<?php
$employee = $employee ?? null;
$employeeOptions = $employee_options ?? [];
$selectedEmployeeId = (int)($selected_employee_id ?? 0);
$attendanceUrl = site_url('my/attendance' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$profileUrl = site_url('my/profile' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$scheduleUrl = site_url('my/schedule' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$payrollUrl = site_url('my/payroll' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
$bonusUrl = site_url('my/bonus' . (!empty($selectedEmployeeId) ? ('?employee_id=' . $selectedEmployeeId) : ''));
?>

<div class="my-mobile-shell d-block d-md-none">
  <div class="my-mobile-header">
    <div>
      <h4 class="my-mobile-title"><i class="ri ri-contacts-book-3-line me-1 text-primary"></i>Portal Pegawai</h4>
      <p class="my-mobile-sub">Akses cepat absensi, bonus, jadwal, profil, dan payroll.</p>
    </div>
  </div>
  <div class="my-mobile-grid">
    <a href="<?php echo $attendanceUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-fingerprint-line"></i></span>
      <span class="my-mobile-tile-text">Absensi Saya</span>
    </a>
    <a href="<?php echo $scheduleUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-calendar-check-line"></i></span>
      <span class="my-mobile-tile-text">Jadwal Shift</span>
    </a>
    <a href="<?php echo $profileUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-user-3-line"></i></span>
      <span class="my-mobile-tile-text">Profil & Kontrak</span>
    </a>
    <a href="<?php echo $payrollUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-file-list-3-line"></i></span>
      <span class="my-mobile-tile-text">Slip Gaji</span>
    </a>
    <a href="<?php echo $bonusUrl; ?>" class="my-mobile-tile">
      <span class="my-mobile-tile-icon"><i class="ri ri-medal-line"></i></span>
      <span class="my-mobile-tile-text">Bonus Saya</span>
    </a>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-none d-md-flex">
  <div>
    <h4 class="mb-0">Portal Pegawai</h4>
    <small class="text-muted">Beranda personal untuk absensi, bonus, jadwal, kontrak, dan payroll.</small>
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
<div class="alert alert-warning d-none d-md-block">
  Akun ini belum terhubung ke data pegawai. Hubungkan `auth_user.employee_id` ke `org_employee.id` untuk membuka fitur portal pegawai.
</div>
<div class="card border-0 shadow-sm d-block d-md-none">
  <div class="card-body p-3">
    <div class="d-flex align-items-start gap-2">
      <span class="my-mobile-tile-icon"><i class="ri ri-alert-line"></i></span>
      <div>
        <div class="fw-semibold text-dark mb-1">Akun belum terhubung ke pegawai</div>
        <div class="small text-muted">Hubungkan <code>auth_user.employee_id</code> ke <code>org_employee.id</code> untuk membuka fitur portal pegawai.</div>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="row g-3 d-none d-md-flex">
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
          <a href="<?php echo $attendanceUrl; ?>" class="btn btn-outline-primary btn-sm">Absensi Saya</a>
          <a href="<?php echo $bonusUrl; ?>" class="btn btn-outline-secondary btn-sm">Bonus Saya</a>
          <a href="<?php echo $profileUrl; ?>" class="btn btn-outline-secondary btn-sm">Profil & Kontrak</a>
          <a href="<?php echo $scheduleUrl; ?>" class="btn btn-outline-secondary btn-sm">Jadwal Shift</a>
          <a href="<?php echo $payrollUrl; ?>" class="btn btn-outline-secondary btn-sm">Slip Gaji</a>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="d-block d-md-none">
  <div class="card border-0 shadow-sm mb-2">
    <div class="card-body p-3">
      <small class="text-muted d-block">Pegawai</small>
      <h6 class="mb-1"><?php echo html_escape((string)$employee['employee_name']); ?></h6>
      <div class="text-muted small mb-2"><?php echo html_escape((string)$employee['employee_code']); ?></div>
      <div class="d-flex flex-wrap gap-1">
        <span class="badge bg-label-primary"><?php echo html_escape((string)($employee['division_name'] ?? '-')); ?></span>
        <span class="badge bg-label-info"><?php echo html_escape((string)($employee['position_name'] ?? '-')); ?></span>
      </div>
    </div>
  </div>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-3">
      <div class="fw-semibold mb-2">Akses Cepat</div>
      <div class="my-mobile-grid">
        <a href="<?php echo $attendanceUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-fingerprint-line"></i></span>
          <span class="my-mobile-tile-text">Absensi Saya</span>
        </a>
        <a href="<?php echo $scheduleUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-calendar-check-line"></i></span>
          <span class="my-mobile-tile-text">Jadwal Shift</span>
        </a>
        <a href="<?php echo $profileUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-user-3-line"></i></span>
          <span class="my-mobile-tile-text">Profil & Kontrak</span>
        </a>
        <a href="<?php echo $payrollUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-file-list-3-line"></i></span>
          <span class="my-mobile-tile-text">Slip Gaji</span>
        </a>
        <a href="<?php echo $bonusUrl; ?>" class="my-mobile-tile">
          <span class="my-mobile-tile-icon"><i class="ri ri-medal-line"></i></span>
          <span class="my-mobile-tile-text">Bonus Saya</span>
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
