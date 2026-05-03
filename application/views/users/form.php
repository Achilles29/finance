<?php
/**
 * users/form.php — Form buat / edit user
 * $edit_mode: bool
 * $user: array|null (untuk edit)
 * $all_roles: array
 * $user_roles: array  (list role_id yang sudah dipunya user, untuk edit)
 * $form_action: string
 */
$user       = $user ?? null;
$user_roles = $user_roles ?? [];
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Kembali
  </a>
  <h5 class="fw-bold mb-0"><?= htmlspecialchars($title ?? '') ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px;">
  <div class="card-body">
    <?= form_open($form_action) ?>

    <!-- Username (hanya tampil saat buat, tidak bisa diubah) -->
    <?php if (!$edit_mode): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
      <input type="text" name="username" class="form-control" maxlength="60" required
             pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf, angka, _ dan -"
             placeholder="contoh: kasir_01"
             value="<?= set_value('username') ?>">
      <div class="form-text">Tidak bisa diubah setelah dibuat.</div>
    </div>
    <?php else: ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Username</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" disabled>
    </div>
    <?php endif; ?>

    <!-- Email -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Email</label>
      <input type="email" name="email" class="form-control" maxlength="150"
             placeholder="opsional"
             value="<?= set_value('email', htmlspecialchars($user['email'] ?? '')) ?>">
    </div>

    <!-- Password -->
    <div class="mb-3">
      <label class="form-label fw-semibold">
        Password <?= !$edit_mode ? '<span class="text-danger">*</span>' : '' ?>
      </label>
      <div class="input-group">
        <input type="password" name="password" id="password" class="form-control" maxlength="72"
               <?= !$edit_mode ? 'required' : '' ?>
               placeholder="<?= $edit_mode ? 'Kosongkan jika tidak ingin diubah' : 'Minimal 8 karakter' ?>"
               minlength="8">
        <button type="button" class="btn btn-outline-secondary" id="toggle-pass" tabindex="-1">
          <i class="fas fa-eye" id="eye-icon"></i>
        </button>
      </div>
    </div>

    <!-- Role -->
    <div class="mb-4">
      <label class="form-label fw-semibold">Role</label>
      <?php if (empty($all_roles)): ?>
        <p class="text-muted small">Belum ada role aktif.</p>
      <?php else: ?>
      <div class="row g-2">
        <?php foreach ($all_roles as $role): ?>
        <div class="col-12 col-sm-6">
          <div class="form-check border rounded p-2 <?= in_array($role['id'], $user_roles) ? 'border-primary bg-primary bg-opacity-5' : '' ?>">
            <input class="form-check-input" type="checkbox" name="role_ids[]"
                   id="role_<?= $role['id'] ?>" value="<?= $role['id'] ?>"
                   <?= in_array($role['id'], $user_roles) ? 'checked' : '' ?>>
            <label class="form-check-label w-100" for="role_<?= $role['id'] ?>">
              <span class="fw-semibold"><?= htmlspecialchars($role['role_name']) ?></span>
              <span class="badge bg-secondary ms-1 opacity-75" style="font-size:0.65rem;"><?= htmlspecialchars($role['role_code']) ?></span>
            </label>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4">
        <i class="fas fa-save me-1"></i> <?= $edit_mode ? 'Simpan Perubahan' : 'Buat User' ?>
      </button>
      <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary">Batal</a>
    </div>

    <?= form_close() ?>
  </div>
</div>

<script>
$('#toggle-pass').on('click', function(){
  const inp = $('#password');
  const icon = $('#eye-icon');
  if (inp.attr('type') === 'password') {
    inp.attr('type', 'text');
    icon.removeClass('fa-eye').addClass('fa-eye-slash');
  } else {
    inp.attr('type', 'password');
    icon.removeClass('fa-eye-slash').addClass('fa-eye');
  }
});
</script>
