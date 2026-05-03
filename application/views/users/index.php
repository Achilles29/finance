<?php
/**
 * users/index.php — Daftar user
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="fw-bold mb-0"><i class="fas fa-user-shield me-2 text-primary"></i>Manajemen User</h5>
  <?php if ($this->MY_Controller_can_create ?? false || (new MY_Controller_helper)->can_from_perms($user_perms, 'auth.users.manage', 'create')): ?>
  <?php /* Gunakan helper dari view */ ?>
  <?php endif; ?>
  <a href="<?= base_url('users/create') ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i> Tambah User
  </a>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <?= form_open('users', ['method' => 'get', 'class' => 'd-flex flex-wrap gap-2 align-items-center']) ?>
      <input type="text" name="search" class="form-control form-control-sm" style="max-width:220px;"
             placeholder="Cari username / email..." value="<?= htmlspecialchars($filter['search'] ?? '') ?>">
      <select name="is_active" class="form-select form-select-sm" style="max-width:140px;">
        <option value="">Semua Status</option>
        <option value="1" <?= isset($filter['is_active']) && $filter['is_active'] == '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= isset($filter['is_active']) && $filter['is_active'] == '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
      <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search me-1"></i>Filter</button>
      <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
    <?= form_close() ?>
  </div>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="tbl-users">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">#</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Login Terakhir</th>
            <th>Dibuat</th>
            <th style="width:160px;">Aksi</th>
          </tr>
            <td class="number-cell text-muted small"><?= $i + 1 ?></td>
            <td class="text-cell">
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data user.</td></tr>
          <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <span class="fw-semibold"><?= htmlspecialchars($u['username']) ?></span>
              <?php if (!empty($u['employee_id'])): ?>
              <span class="badge bg-info bg-opacity-20 text-info ms-1" style="font-size:0.65rem;">karyawan</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
              <td class="text-cell text-muted small"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small">
              <?= $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—' ?>
            </td>
              <td class="text-muted small"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
              <td class="action-cell">
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= base_url('users/edit/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="<?= base_url('users/permissions/' . $u['id']) ?>" class="btn btn-sm btn-outline-warning py-0 px-2" title="Override Izin">
                  <i class="fas fa-shield-alt"></i>
                </a>
                <a href="<?= base_url('users/toggle/' . $u['id']) ?>"
                   class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 px-2"
                   title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"
                   onclick="return confirm('<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> user ini?')">
                  <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
