<?php
/**
 * roles/index.php — Daftar role
 */
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="fw-bold mb-0"><i class="fas fa-id-badge me-2 text-primary"></i>Manajemen Role</h5>
  <a href="<?= base_url('roles/create') ?>" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i> Buat Role Baru
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:40px;">#</th>
            <th>Kode</th>
            <th>Nama Role</th>
            <th>Deskripsi</th>
            <th>Status</th>
            <th style="width:180px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($roles)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada role.</td></tr>
          <?php else: ?>
          <?php foreach ($roles as $i => $r): ?>
          <tr>
            <td class="number-cell text-muted small"><?= $i + 1 ?></td>
            <td class="text-cell">
              <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($r['role_code']) ?></code>
            </td>
            <td class="text-cell fw-semibold"><?= htmlspecialchars($r['role_name']) ?></td>
            <td class="text-muted small"><?= htmlspecialchars($r['description'] ?? '—') ?></td>
            <td>
              <?php if ($r['is_active']): ?>
                <span class="badge bg-success-subtle text-success">Aktif</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td class="action-cell">
              <div class="d-flex gap-1 flex-wrap">
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Matrix Izin" aria-label="Matrix Izin">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <a href="<?= base_url('roles/edit/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit">
                  <i class="ri ri-edit-line"></i>
                </a>
                <?php if ($r['role_code'] !== 'SUPERADMIN'): ?>
                <a href="<?= base_url('roles/delete/' . $r['id']) ?>" class="btn btn-sm btn-outline-danger action-icon-btn"
                   data-bs-toggle="tooltip"
                   title="Hapus"
                   onclick="return confirm('Hapus role <?= htmlspecialchars(addslashes($r['role_name'])) ?>? Tidak bisa dilakukan jika masih ada user dengan role ini.')">
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
