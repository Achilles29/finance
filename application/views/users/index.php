<?php
/**
 * users/index.php — Daftar user
 */
$canCreate = !empty($current_user['is_superadmin']) || !empty($user_perms['auth.users.manage']['can_create']);
?>
<style>
  .users-index .users-header-title {
    font-size: 1.55rem;
    letter-spacing: 0.01em;
  }
  .users-index .filter-wrap .form-control,
  .users-index .filter-wrap .form-select {
    min-height: 40px;
  }
  .users-index .user-actions {
    display: flex;
    gap: 0.35rem;
    flex-wrap: nowrap;
    align-items: center;
  }
  .users-index .user-actions .btn {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
  }
  .users-index .user-actions .btn i {
    font-size: 1rem;
    margin: 0;
  }
  .users-index .table th,
  .users-index .table td {
    padding: 0.72rem 0.85rem;
    vertical-align: middle;
  }
  .users-index .table td {
    font-size: 0.92rem;
  }
  .users-index .username-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .users-index .username-label {
    font-weight: 700;
    color: #2f2f2f;
    letter-spacing: 0.01em;
  }
  .users-index .emp-badge {
    display: inline-block;
    font-size: 0.67rem;
    line-height: 1;
    padding: 0.28rem 0.48rem;
    border-radius: 999px;
    border: 1px solid #8ec7f8;
    background: #e8f4ff;
    color: #2169ad;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
  }
</style>

<div class="users-index">
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="fw-bold mb-0 users-header-title"><i class="ri ri-user-settings-line me-2 text-primary"></i>Manajemen User</h5>
  <?php if ($canCreate): ?>
  <a href="<?= base_url('users/create') ?>" class="btn btn-primary">
    <i class="ri ri-add-line me-1"></i> Tambah User
  </a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-3 filter-wrap">
    <?= form_open('users', ['method' => 'get', 'class' => 'd-flex flex-wrap gap-2 align-items-center']) ?>
      <input type="text" name="search" class="form-control" style="max-width:280px;"
             placeholder="Cari username / email..." value="<?= htmlspecialchars($filter['search'] ?? '') ?>">
      <select name="is_active" class="form-select" style="max-width:180px;">
        <option value="">Semua Status</option>
        <option value="1" <?= isset($filter['is_active']) && $filter['is_active'] == '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= isset($filter['is_active']) && $filter['is_active'] == '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
      <button type="submit" class="btn btn-outline-secondary"><i class="ri ri-search-line me-1"></i>Filter</button>
      <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">Reset</a>
    <?= form_close() ?>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">#</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Login Terakhir</th>
            <th>Dibuat</th>
            <th style="width:130px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data user.</td></tr>
          <?php else: ?>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="username-cell">
                <span class="username-label"><?= htmlspecialchars($u['username']) ?></span>
                <?php if (!empty($u['employee_id'])): ?>
                <span class="emp-badge">Pegawai</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td>
              <?php if ((int)$u['is_active'] === 1): ?>
              <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
              <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small">
              <?= !empty($u['last_login_at']) ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—' ?>
            </td>
            <td class="text-muted small"><?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?></td>
            <td>
              <div class="user-actions">
                <a href="<?= base_url('users/edit/' . (int)$u['id']) ?>" class="btn btn-outline-primary" title="Edit User">
                  <i class="ri ri-edit-line"></i>
                </a>
                <a href="<?= base_url('users/permissions/' . (int)$u['id']) ?>" class="btn btn-outline-warning" title="Override Izin">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <?php if ((int)$u['id'] !== (int)($current_user['id'] ?? 0)): ?>
                <a href="<?= base_url('users/toggle/' . (int)$u['id']) ?>"
                   class="btn <?= (int)$u['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                   title="<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                   onclick="return confirm('<?= (int)$u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> user ini?')">
                  <i class="ri <?= (int)$u['is_active'] === 1 ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' ?>"></i>
                </a>
                <?php endif; ?>
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
</div>
