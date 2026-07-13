<?php
$mode      = (string)($mode ?? 'create');
$broadcast = (array)($broadcast ?? []);
$templates = (array)($templates ?? []);
$groups    = (array)($groups ?? []);
?>

<div class="container-xxl py-3">
  <div class="mb-3">
    <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
  </div>

  <div class="d-flex align-items-center gap-2 mb-3">
    <h4 class="mb-0 fw-bold"><i class="ri ri-broadcast-line me-1"></i>
      <?= $mode === 'create' ? 'Buat Broadcast Baru' : 'Edit Broadcast' ?>
    </h4>
  </div>

  <?php if ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <form method="post" action="<?= site_url($mode === 'create' ? 'wa/broadcast/create' : 'wa/broadcast/create') ?>">
    <div class="row g-3">
      <div class="col-md-8">
        <!-- Identitas -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header"><h5 class="mb-0">Informasi Broadcast</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Nama Broadcast <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required
                value="<?= html_escape($broadcast['name'] ?? '') ?>"
                placeholder="Contoh: Promo Ramadan Juli 2026">
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Tipe Target</label>
                <select name="target_type" id="target_type" class="form-select">
                  <option value="MANUAL" <?= ($broadcast['target_type'] ?? 'MANUAL') === 'MANUAL' ? 'selected' : '' ?>>Manual (input nomor)</option>
                  <option value="ALL_MEMBERS" <?= ($broadcast['target_type'] ?? '') === 'ALL_MEMBERS' ? 'selected' : '' ?>>Semua Member</option>
                  <option value="MEMBER_ACTIVE" <?= ($broadcast['target_type'] ?? '') === 'MEMBER_ACTIVE' ? 'selected' : '' ?>>Member Aktif</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Jadwal Kirim (opsional)</label>
                <input type="datetime-local" name="scheduled_at" class="form-control"
                  value="<?= html_escape($broadcast['scheduled_at'] ?? '') ?>">
                <div class="form-text">Kosongkan untuk kirim segera setelah mulai.</div>
              </div>
            </div>
            <div class="mt-3" id="manual-lines-wrapper">
              <label class="form-label fw-semibold">Daftar Nomor (manual)</label>
              <textarea name="manual_lines" class="form-control font-monospace" rows="8"
                placeholder="Satu nomor per baris. Format: 081234567890 atau 081234567890|Nama Pelanggan"><?= html_escape($broadcast['manual_lines'] ?? '') ?></textarea>
              <div class="form-text">Format: <code>08xxx</code> atau <code>08xxx|Nama</code>. Nomor 08 akan dikonversi ke 62.</div>
            </div>
            <div class="mt-3">
              <label class="form-label fw-semibold">Catatan Internal</label>
              <textarea name="notes" class="form-control" rows="2"><?= html_escape($broadcast['notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <!-- Pesan -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header"><h5 class="mb-0">Pesan</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Template Pesan</label>
              <select name="template_id" id="template_select" class="form-select">
                <option value="">— Tanpa template —</option>
                <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int)$tpl['id'] ?>"
                  data-body="<?= html_escape($tpl['body']) ?>"
                  <?= (int)($broadcast['template_id'] ?? 0) === (int)$tpl['id'] ? 'selected' : '' ?>>
                  <?= html_escape($tpl['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label fw-semibold">Pesan Custom</label>
              <textarea name="custom_message" id="custom_message" class="form-control font-monospace" rows="10"
                placeholder="Kosongkan jika menggunakan template. Isi jika ingin override."><?= html_escape($broadcast['custom_message'] ?? '') ?></textarea>
              <div class="form-text">Gunakan <code>&#123;&#123;nama&#125;&#125;</code> untuk variabel dinamis.</div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview">
              <i class="ri ri-eye-line me-1"></i>Preview Pesan
            </button>
            <div id="preview-box" class="mt-2 p-2 bg-light rounded small font-monospace d-none" style="white-space:pre-wrap;"></div>
          </div>
        </div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ri ri-save-line me-1"></i>Simpan Broadcast
          </button>
          <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
// Auto-fill template ke custom_message
document.getElementById('template_select')?.addEventListener('change', function () {
  const opt = this.options[this.selectedIndex];
  const body = opt.getAttribute('data-body') || '';
  document.getElementById('custom_message').value = body;
});

// Toggle manual lines
document.getElementById('target_type')?.addEventListener('change', function () {
  document.getElementById('manual-lines-wrapper').style.display = this.value === 'MANUAL' ? '' : 'none';
});
document.getElementById('target_type')?.dispatchEvent(new Event('change'));

// Preview
document.getElementById('btn-preview')?.addEventListener('click', function () {
  const msg = document.getElementById('custom_message').value;
  const box = document.getElementById('preview-box');
  if (!msg) { box.classList.add('d-none'); return; }
  fetch('<?= site_url('wa/api/template-preview') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ body: msg, variables: { nama: 'Pelanggan', judul_promo: '[Judul]', tanggal_promo: '[Tanggal]', deskripsi: '[Deskripsi]' } })
  }).then(r => r.json()).then(d => {
    box.textContent = d.preview || '';
    box.classList.remove('d-none');
  });
});
</script>
