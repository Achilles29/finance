<?php
/**
 * roles/form.php — Buat / edit role
 * $role: array|null
 * $edit_mode: bool
 * $form_action: string
 */
$role = $role ?? null;
?>
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= base_url('roles') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>Kembali
  </a>
  <h5 class="fw-bold mb-0"><?= htmlspecialchars($title ?? '') ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:520px;">
  <div class="card-body">
    <?= form_open($form_action) ?>

    <!-- Kode role (hanya saat buat) -->
    <?php if (!$edit_mode): ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Kode Role <span class="text-danger">*</span></label>
      <input type="text" name="role_code" class="form-control text-uppercase" maxlength="50" required
             pattern="[a-zA-Z0-9_\-]+" title="Hanya huruf besar, angka, _ dan -"
             placeholder="contoh: KASIR"
             value="<?= set_value('role_code') ?>"
             oninput="this.value = this.value.toUpperCase()">
      <div class="form-text">Tidak bisa diubah setelah dibuat.</div>
    </div>
    <?php else: ?>
    <div class="mb-3">
      <label class="form-label fw-semibold">Kode Role</label>
      <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($role['role_code']) ?>" disabled>
    </div>
    <?php endif; ?>

    <!-- Nama -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Nama Role <span class="text-danger">*</span></label>
      <input type="text" name="role_name" class="form-control" maxlength="100" required
             placeholder="contoh: Kasir"
             value="<?= set_value('role_name', htmlspecialchars($role['role_name'] ?? '')) ?>">
    </div>

    <!-- Deskripsi -->
    <div class="mb-3">
      <label class="form-label fw-semibold">Deskripsi</label>
      <input type="text" name="description" class="form-control" maxlength="255"
             placeholder="Penjelasan singkat role ini"
             value="<?= set_value('description', htmlspecialchars($role['description'] ?? '')) ?>">
    </div>

    <!-- Status (hanya saat edit) -->
    <?php if ($edit_mode): ?>
    <div class="mb-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" role="switch"
               value="1" <?= ($role['is_active'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_active">Role aktif</label>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4">
        <i class="fas fa-save me-1"></i> <?= $edit_mode ? 'Simpan' : 'Buat Role' ?>
      </button>
      <a href="<?= base_url('roles') ?>" class="btn btn-outline-secondary">Batal</a>
    </div>

    <?= form_close() ?>
  </div>
</div>
