<?php
/**
 * roles/matrix.php — Matrix izin CRUD per halaman untuk satu role
 * $role: array
 * $pages_by_module: ['MODULE' => [['page_id','page_code','page_name','can_view',...], ...]]
 */
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Kembali
  </a>
  <div>
    <h5 class="fw-bold mb-0">Matrix Izin: <span class="text-primary"><?= htmlspecialchars($role['role_name']) ?></span></h5>
    <p class="text-muted small mb-0">Role code: <code><?= htmlspecialchars($role['role_code']) ?></code></p>
  </div>
</div>

<?php if (empty($pages_by_module)): ?>
<div class="alert alert-info">
  Belum ada halaman terdaftar di sys_page. Jalankan seeder SQL atau akses halaman sebagai superadmin terlebih dahulu.
</div>
<?php else: ?>

<!-- Toolbar cepat -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-check-all-view">
    <i class="fas fa-eye me-1"></i>Centang Semua View
  </button>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-check-all">
    <i class="fas fa-check-double me-1"></i>Centang Semua
  </button>
  <button type="button" class="btn btn-sm btn-outline-danger" id="btn-uncheck-all">
    <i class="fas fa-times me-1"></i>Kosongkan Semua
  </button>
</div>

<?= form_open('roles/save_matrix/' . $role['id'], ['id' => 'form-matrix']) ?>

<?php foreach ($pages_by_module as $module => $pages): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
    <span class="fw-semibold text-uppercase small"><?= htmlspecialchars($module) ?></span>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 module-check-view"
              data-module="<?= htmlspecialchars($module) ?>" style="font-size:0.7rem;">View</button>
      <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 module-check-all"
              data-module="<?= htmlspecialchars($module) ?>" style="font-size:0.7rem;">Semua</button>
      <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 module-uncheck"
              data-module="<?= htmlspecialchars($module) ?>" style="font-size:0.7rem;">Kosong</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light border-bottom">
          <tr>
            <th>Halaman</th>
            <th class="text-center" style="width:65px;">View</th>
            <th class="text-center" style="width:65px;">Create</th>
            <th class="text-center" style="width:65px;">Edit</th>
            <th class="text-center" style="width:65px;">Delete</th>
            <th class="text-center" style="width:65px;">Export</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pages as $pg): ?>
          <?php $pid = $pg['page_id']; ?>
          <tr data-module="<?= htmlspecialchars($module) ?>">
            <td>
              <div class="fw-semibold small"><?= htmlspecialchars($pg['page_name']) ?></div>
              <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($pg['page_code']) ?></div>
            </td>
            <?php foreach (['can_view','can_create','can_edit','can_delete','can_export'] as $flag): ?>
            <td class="text-center">
              <input type="checkbox" class="form-check-input matrix-cb"
                     name="perms[<?= $pid ?>][<?= $flag ?>]"
                     value="1"
                     data-module="<?= htmlspecialchars($module) ?>"
                     data-flag="<?= $flag ?>"
                     <?= $pg[$flag] ? 'checked' : '' ?>>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<div class="d-flex gap-2 mt-3 mb-5">
  <button type="submit" class="btn btn-primary px-4">
    <i class="fas fa-save me-1"></i>Simpan Matrix Izin
  </button>
  <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary">Batal</a>
</div>

<?= form_close() ?>

<?php endif; ?>

<script>
$(function(){
  // Jika view dicentang, enable kolom lain; jika view di-uncheck, uncheck semua
  $(document).on('change', '.matrix-cb[data-flag="can_view"]', function(){
    const pid = $(this).attr('name').match(/perms\[(\d+)\]/)[1];
    if (!$(this).is(':checked')) {
      $(`input[name^="perms[${pid}]"]`).prop('checked', false);
    }
  });

  // Centang semua → otomatis centang view dulu
  $('#btn-check-all').on('click', function(){
    $('.matrix-cb').prop('checked', true);
  });
  $('#btn-check-all-view').on('click', function(){
    $('.matrix-cb[data-flag="can_view"]').prop('checked', true);
  });
  $('#btn-uncheck-all').on('click', function(){
    $('.matrix-cb').prop('checked', false);
  });

  // Per module
  $('.module-check-view').on('click', function(){
    const mod = $(this).data('module');
    $(`.matrix-cb[data-module="${mod}"][data-flag="can_view"]`).prop('checked', true);
  });
  $('.module-check-all').on('click', function(){
    const mod = $(this).data('module');
    $(`.matrix-cb[data-module="${mod}"]`).prop('checked', true);
  });
  $('.module-uncheck').on('click', function(){
    const mod = $(this).data('module');
    $(`.matrix-cb[data-module="${mod}"]`).prop('checked', false);
  });
});
</script>
