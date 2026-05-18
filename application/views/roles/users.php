<?php
/**
 * roles/users.php — Daftar user yang memiliki role ini
 * $role: array
 * $users: array
 */
$users = $users ?? [];
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="ri ri-arrow-left-line me-1"></i>Kembali
  </a>
  <div>
    <h5 class="fw-bold mb-0">User dalam Role: <span class="text-primary"><?= htmlspecialchars($role['role_name']) ?></span></h5>
    <p class="text-muted small mb-0">
      <code><?= htmlspecialchars($role['role_code']) ?></code>
      <?php if (!empty($role['division_scope_id'])): ?>
        &nbsp;·&nbsp;<span class="badge bg-info-subtle text-info"><i class="ri ri-building-line me-1"></i><?= htmlspecialchars($role['division_scope_name'] ?? '') ?></span>
      <?php endif; ?>
      &nbsp;·&nbsp;
      <a href="<?= base_url('roles/matrix/' . $role['id']) ?>">Lihat Matrix Izin</a>
    </p>
  </div>
</div>

<?php if (empty($users)): ?>
<div class="alert alert-info">
  Belum ada user yang memiliki role ini.
  <a href="<?= base_url('users/create') ?>" class="alert-link">Buat user baru</a> dan assign role ini, atau edit user yang sudah ada.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
    <span class="text-muted small"><?= count($users) ?> user memiliki role ini</span>
    <a href="<?= base_url('users/create') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ri ri-user-add-line me-1"></i>Tambah User
    </a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px;">#</th>
            <th>Username</th>
            <th>Pegawai</th>
            <th>Jabatan</th>
            <th>Divisi</th>
            <th>Status</th>
            <th>Login Terakhir</th>
            <th style="width:80px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($u['username']) ?></div>
              <?php if (!empty($u['email'])): ?>
              <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($u['email']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= htmlspecialchars($u['employee_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= htmlspecialchars($u['position_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= htmlspecialchars($u['division_name'] ?? '—') ?></td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted">
              <?= $u['last_login_at'] ? date('d/m/y H:i', strtotime($u['last_login_at'])) : '—' ?>
            </td>
            <td>
              <a href="<?= base_url('users/edit/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit User">
                <i class="ri ri-edit-line"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
