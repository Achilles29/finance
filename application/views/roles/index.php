<?php
/**
 * roles/index.php — Daftar role dengan user count, page count, division scope
 */
$_is_super  = !empty($current_user['is_superadmin']);
$canCreate  = $_is_super || !empty($user_perms['auth.roles.manage']['can_create']);
$canEdit    = $_is_super || !empty($user_perms['auth.roles.manage']['can_edit']);
$canDelete  = $_is_super || !empty($user_perms['auth.roles.manage']['can_delete']);
$registryAudit = is_array($registry_audit ?? null) ? $registry_audit : [];
$activePageCount = (int)($registryAudit['active_page_count'] ?? 0);
$activeMenuCount = (int)($registryAudit['active_menu_count'] ?? 0);
$menusWithoutPageCount = (int)($registryAudit['active_menus_without_page_count'] ?? 0);
$pagesWithoutMenuCount = (int)($registryAudit['active_pages_without_menu_count'] ?? 0);
$menusWithoutPage = is_array($registryAudit['menus_without_page'] ?? null) ? $registryAudit['menus_without_page'] : [];
$pagesWithoutMenu = is_array($registryAudit['pages_without_menu'] ?? null) ? $registryAudit['pages_without_menu'] : [];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0"><i class="ri ri-shield-keyhole-line me-2 text-primary"></i>Role & Hak Akses</h5>
    <p class="text-muted small mb-0">Pusat pengaturan akses Finance. Hak akses idealnya mengikuti nama menu yang dikenal user, bukan kode teknis.</p>
  </div>
  <?php if ($canCreate): ?>
  <a href="<?= base_url('roles/create') ?>" class="btn btn-primary btn-sm">
    <i class="ri ri-add-line me-1"></i> Buat Role Baru
  </a>
  <?php endif; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Halaman Terdaftar</div>
        <div class="fs-4 fw-bold"><?= $activePageCount ?></div>
        <div class="small text-muted">Page aktif di registry hak akses</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small mb-1">Menu Aktif</div>
        <div class="fs-4 fw-bold"><?= $activeMenuCount ?></div>
        <div class="small text-muted">Menu/sidebar aktif di database</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100 border-warning-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Menu Belum Masuk Hak Akses</div>
        <div class="fs-4 fw-bold text-warning"><?= $menusWithoutPageCount ?></div>
        <div class="small text-muted">Menu aktif punya URL tapi belum terhubung ke `sys_page`</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100 border-info-subtle">
      <div class="card-body">
        <div class="text-muted small mb-1">Halaman Teknis / Review</div>
        <div class="fs-4 fw-bold text-info"><?= $pagesWithoutMenuCount ?></div>
        <div class="small text-muted">Page aktif yang belum terhubung ke menu aktif</div>
      </div>
    </div>
  </div>
</div>

<?php if ($menusWithoutPageCount > 0 || $pagesWithoutMenuCount > 0): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3 align-items-start">
      <div>
        <div class="fw-semibold">Audit cepat registry hak akses</div>
        <div class="small text-muted">Dipakai untuk mengecek apakah ada halaman/menu yang belum masuk pengelolaan role, atau page yang perlu direview karena tidak lagi terhubung ke menu aktif.</div>
      </div>
      <span class="badge bg-light text-dark border">Sumber: `sys_page` + `sys_menu`</span>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-2 text-warning">Menu aktif belum punya page registry</div>
          <?php if (empty($menusWithoutPage)): ?>
            <div class="small text-muted">Tidak ada temuan.</div>
          <?php else: ?>
            <div class="small text-muted mb-2">Contoh temuan yang belum akan muncul rapi di matrix role:</div>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($menusWithoutPage as $row): ?>
                <div class="border rounded-2 px-2 py-2 small">
                  <div class="fw-semibold"><?= htmlspecialchars((string)($row['menu_label'] ?? '-')) ?></div>
                  <div class="text-muted"><?= htmlspecialchars((string)($row['url'] ?? '-')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-2 text-info">Page aktif tanpa menu aktif</div>
          <?php if (empty($pagesWithoutMenu)): ?>
            <div class="small text-muted">Tidak ada temuan.</div>
          <?php else: ?>
            <div class="small text-muted mb-2">Sebagian bisa memang halaman teknis/internal, sebagian lain perlu dicek apakah masih dipakai:</div>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($pagesWithoutMenu as $row): ?>
                <div class="border rounded-2 px-2 py-2 small">
                  <div class="fw-semibold"><?= htmlspecialchars((string)($row['page_name'] ?? '-')) ?></div>
                  <div class="text-muted"><?= htmlspecialchars((string)($row['page_code'] ?? '-')) ?> • <?= htmlspecialchars((string)($row['module'] ?? '-')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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
            <th class="text-center" style="width:80px;">Akses</th>
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
                <a href="<?= base_url('roles/matrix/' . $r['id']) ?>" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Atur Hak Akses" aria-label="Atur Hak Akses">
                  <i class="ri ri-shield-keyhole-line"></i>
                </a>
                <a href="<?= base_url('roles/users/' . $r['id']) ?>" class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Atur Pengguna Role" aria-label="Atur Pengguna Role">
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
