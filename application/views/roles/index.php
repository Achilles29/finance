<?php
/**
 * roles/index.php — Daftar role dengan user count, page count, division scope
 */
$_is_super  = !empty($current_user['is_superadmin']);
$canCreate  = $_is_super || !empty($user_perms['auth.roles.manage']['can_create']);
$canEdit    = $_is_super || !empty($user_perms['auth.roles.manage']['can_edit']);
$canDelete  = $_is_super || !empty($user_perms['auth.roles.manage']['can_delete']);
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri ri-shield-keyhole-line me-2 text-primary"></i>Manajemen Role & Hak Akses</h5>
    <p class="text-muted small mb-0">Role menentukan halaman apa yang bisa diakses. User bisa punya beberapa role sekaligus.</p>
  </div>
  <?php if ($canCreate): ?>
  <a href="<?= base_url('roles/create') ?>" class="btn btn-primary btn-sm">
    <i class="ri ri-add-line me-1"></i> Buat Role Baru
  </a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px;">#</th>
            <th>Kode</th>
            <th>Nama Role</th>
            <th>Scope Divisi</th>
            <th class="text-center" style="width:80px;">User</th>
            <th class="text-center" style="width:80px;">Halaman</th>
            <th>Status</th>
            <th style="width:150px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($roles)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada role.</td></tr>
          <?php else: ?>
          <?php foreach ($roles as $i => $r): ?>
          <tr>
            <td class="number-cell text-muted small"><?= $i + 1 ?></td>
            <td>
              <code class="bg-light px-2 py-1 rounded small"><?= htmlspecialchars($r['role_code']) ?></code>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($r['role_name']) ?></div>
              <?php if (!empty($r['description'])): ?>
              <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($r['description']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($r['division_scope_id'])): ?>
                <span class="badge bg-info-subtle text-info">
                  <i class="ri ri-building-line me-1"></i><?= htmlspecialchars($r['division_scope_name']) ?>
                </span>
              <?php else: ?>
                <span class="text-muted">Lintas divisi</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php $uc = (int)($r['user_count'] ?? 0); ?>
              <?php if ($uc > 0): ?>
                <a href="<?= base_url('roles/users/' . $r['id']) ?>" class="badge bg-primary-subtle text-primary text-decoration-none">
                  <?= $uc ?>
                </a>
              <?php else: ?>
                <span class="text-muted small">0</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php $pc = (int)($r['page_count'] ?? 0); ?>
              <?php if ($pc > 0): ?>
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="badge bg-success-subtle text-success text-decoration-none">
                  <?= $pc ?>
                </a>
              <?php else: ?>
                <span class="text-muted small">0</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['is_active']): ?>
                <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <div class="d-flex gap-1 flex-nowrap justify-content-end">
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Matrix Izin" aria-label="Matrix Izin">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <a href="<?= base_url('roles/users/' . $r['id']) ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Lihat User" aria-label="Lihat User">
                  <i class="ri ri-group-line"></i>
                </a>
                <?php if ($canEdit): ?>
                <a href="<?= base_url('roles/edit/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <?php endif; ?>
                <?php if ($canDelete && $r['role_code'] !== 'SUPERADMIN'): ?>
                <a href="<?= base_url('roles/delete/' . $r['id']) ?>" class="btn btn-sm btn-outline-danger action-icon-btn"
                   data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus"
                   onclick="return confirm('Hapus role <?= htmlspecialchars(addslashes($r['role_name'])) ?>? Tidak bisa jika masih ada user.')">
                  <i class="ri ri-delete-bin-line"></i>
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
