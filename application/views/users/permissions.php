<?php
/**
 * users/permissions.php — Override izin per user
 * $user: array
 * $user_roles: array (role yang dipunya)
 * $pages_by_module: ['MODULE' => [page_rows...]]
 * $overrides: [page_id => override_row]
 */
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Kembali
  </a>
  <h5 class="fw-bold mb-0">Override Izin: <span class="text-primary"><?= htmlspecialchars($user['username']) ?></span></h5>
</div>

<!-- Info role aktif user -->
<div class="alert alert-info py-2 small mb-3">
  <i class="fas fa-info-circle me-1"></i>
  Role aktif:
  <?php if (empty($user_roles)): ?>
    <em>tidak punya role</em>
  <?php else: ?>
    <?php foreach ($user_roles as $r): ?>
      <span class="badge bg-info text-dark me-1"><?= htmlspecialchars($r['role_name']) ?></span>
    <?php endforeach; ?>
  <?php endif; ?>
  <br>
  <span class="text-muted">Override GRANT menambah izin di luar role. Override REVOKE mencabut izin dari role.</span>
</div>

<!-- Tabel override per module -->
<?php if (empty($pages_by_module)): ?>
<div class="alert alert-warning">Belum ada halaman terdaftar di sys_page.</div>
<?php else: ?>

<?php foreach ($pages_by_module as $module => $pages): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-light fw-semibold text-uppercase small py-2">
    <?= htmlspecialchars($module) ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Halaman</th>
            <th class="text-center" style="width:80px;">Tipe</th>
            <th class="text-center" style="width:55px;">View</th>
            <th class="text-center" style="width:55px;">Create</th>
            <th class="text-center" style="width:55px;">Edit</th>
            <th class="text-center" style="width:55px;">Delete</th>
            <th class="text-center" style="width:55px;">Export</th>
            <th style="width:160px;">Alasan</th>
            <th style="width:80px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pages as $pg): ?>
          <?php $ov = $overrides[$pg['id']] ?? null; ?>
          <tr>
            <td>
              <div class="fw-semibold small"><?= htmlspecialchars($pg['page_name']) ?></div>
              <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($pg['page_code']) ?></div>
            </td>
            <td class="text-center">
              <?php if ($ov): ?>
                <span class="badge <?= $ov['override_type'] === 'GRANT' ? 'bg-success' : 'bg-danger' ?>">
                  <?= $ov['override_type'] ?>
                </span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <?php foreach (['can_view','can_create','can_edit','can_delete','can_export'] as $flag): ?>
            <td class="text-center">
              <?php if ($ov && $ov[$flag]): ?>
                <i class="fas fa-check text-success"></i>
              <?php elseif ($ov): ?>
                <i class="fas fa-times text-danger"></i>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="text-muted small"><?= htmlspecialchars($ov['reason'] ?? '') ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary py-0 px-1 btn-set-override"
                      data-page-id="<?= $pg['id'] ?>"
                      data-page-name="<?= htmlspecialchars($pg['page_name']) ?>"
                      data-ov='<?= $ov ? htmlspecialchars(json_encode($ov)) : 'null' ?>'
                      title="Set Override">
                <i class="fas fa-edit"></i>
              </button>
              <?php if ($ov): ?>
              <a href="#" class="btn btn-sm btn-outline-danger py-0 px-1 btn-del-override"
                 data-page-id="<?= $pg['id'] ?>"
                 data-page-name="<?= htmlspecialchars($pg['page_name']) ?>"
                 title="Hapus Override">
                <i class="fas fa-trash"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Modal Set Override -->
<div class="modal fade" id="modalOverride" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">Set Override Izin</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= form_open('users/save_override/' . $user['id'], ['id' => 'form-override']) ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="page_id" id="ov_page_id">
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="ov_page_name"></p>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Tipe Override</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="override_type" id="ov_grant" value="GRANT" checked>
              <label class="form-check-label" for="ov_grant"><span class="text-success fw-semibold">GRANT</span> — Tambah izin</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="override_type" id="ov_revoke" value="REVOKE">
              <label class="form-check-label" for="ov_revoke"><span class="text-danger fw-semibold">REVOKE</span> — Cabut izin</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Aksi yang di-override</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach (['can_view'=>'View','can_create'=>'Create','can_edit'=>'Edit','can_delete'=>'Delete','can_export'=>'Export'] as $k => $lbl): ?>
            <div class="form-check form-switch">
              <input class="form-check-input ov-flag" type="checkbox" name="<?= $k ?>" id="ov_<?= $k ?>" role="switch">
              <label class="form-check-label" for="ov_<?= $k ?>"><?= $lbl ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Alasan (opsional)</label>
          <input type="text" name="reason" id="ov_reason" class="form-control form-control-sm" maxlength="255" placeholder="Mis: jabatan sementara, akses proyek khusus">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Simpan</button>
      </div>
      <?= form_close() ?>
    </div>
  </div>
</div>

<!-- Form hapus override (hidden) -->
<?= form_open('users/save_override/' . $user['id'], ['id' => 'form-del-override']) ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="override_type" value="GRANT">
<input type="hidden" name="page_id" id="del_page_id">
<?= form_close() ?>

<script>
$(function(){
  // Buka modal override
  $('.btn-set-override').on('click', function(){
    const pageId   = $(this).data('page-id');
    const pageName = $(this).data('page-name');
    const ov       = $(this).data('ov');

    $('#ov_page_id').val(pageId);
    $('#ov_page_name').text(pageName);

    // Reset
    $('input[name=override_type][value=GRANT]').prop('checked', true);
    $('.ov-flag').prop('checked', false);
    $('#ov_reason').val('');

    if (ov && ov !== 'null') {
      $('input[name=override_type][value=' + ov.override_type + ']').prop('checked', true);
      $('#ov_can_view').prop('checked',   ov.can_view == 1);
      $('#ov_can_create').prop('checked', ov.can_create == 1);
      $('#ov_can_edit').prop('checked',   ov.can_edit == 1);
      $('#ov_can_delete').prop('checked', ov.can_delete == 1);
      $('#ov_can_export').prop('checked', ov.can_export == 1);
      $('#ov_reason').val(ov.reason || '');
    }

    new bootstrap.Modal(document.getElementById('modalOverride')).show();
  });

  // Hapus override
  $('.btn-del-override').on('click', function(e){
    e.preventDefault();
    const pageName = $(this).data('page-name');
    if (!confirm('Hapus override untuk "' + pageName + '"?')) return;
    $('#del_page_id').val($(this).data('page-id'));
    $('#form-del-override').submit();
  });
});
</script>
