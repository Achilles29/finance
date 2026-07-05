<?php
$mode = strtoupper(trim((string)($mode ?? 'OPEN_AND_CLOSE')));
$policy = strtoupper(trim((string)($policy ?? 'WARN_ONLY')));
$confirmMode = strtoupper(trim((string)($confirm_mode ?? 'BULK_ALLOWED')));
$requiredMaterials = (string)($required_materials ?? '');
$requiredComponents = (string)($required_components ?? '');
$canEdit = !empty($can_edit);

$modeLabels = [
    'OFF' => [
        'title' => 'Nonaktif',
        'desc' => 'Kasir tidak dicek terhadap konfirmasi daily recon saat buka atau tutup.',
    ],
    'OPEN_ONLY' => [
        'title' => 'Cek saat buka kasir',
        'desc' => 'Saat kasir dibuka, sistem memberi warning jika daily recon hari itu belum dikonfirmasi.',
    ],
    'CLOSE_ONLY' => [
        'title' => 'Cek saat tutup kasir',
        'desc' => 'Saat kasir ditutup, sistem memberi warning jika daily recon hari itu belum dikonfirmasi.',
    ],
    'OPEN_AND_CLOSE' => [
        'title' => 'Cek saat buka dan tutup kasir',
        'desc' => 'Mode paling lengkap: sistem mengecek konfirmasi daily recon saat awal dan akhir shift.',
    ],
];

$confirmModeLabels = [
    'BULK_ALLOWED' => [
        'title' => 'Boleh konfirmasi semua',
        'desc' => 'User bisa konfirmasi semua, kecuali ada item multi-lot atau item wajib recon yang belum dicek per baris.',
    ],
    'ROW_REQUIRED' => [
        'title' => 'Wajib satu per satu',
        'desc' => 'Setiap baris wajib dikonfirmasi dari halaman recon sebelum checkpoint divisi dianggap aman.',
    ],
];
?>

<style>
.pos-recon-setting-card {
  border: 0;
  border-radius: 18px;
  box-shadow: 0 14px 34px rgba(35, 24, 18, .08);
}
.pos-recon-mode-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: .8rem;
}
.pos-recon-mode {
  border: 1px solid #ead8cc;
  border-radius: 16px;
  background: linear-gradient(145deg, #fff, #fff7ef);
  padding: 1rem;
  min-height: 132px;
  cursor: pointer;
  transition: .18s ease;
}
.pos-recon-mode:hover {
  transform: translateY(-1px);
  border-color: #d8aa83;
  box-shadow: 0 8px 22px rgba(124, 57, 35, .09);
}
.pos-recon-mode.is-active {
  border-color: #a50f24;
  background: linear-gradient(145deg, #fff7ef, #fff0d6);
  box-shadow: inset 0 0 0 2px rgba(165, 15, 36, .09);
}
.pos-recon-mode input {
  margin-right: .45rem;
}
.pos-recon-mode-title {
  color: #861323;
  font-weight: 800;
  letter-spacing: .01em;
}
.pos-recon-mode-desc {
  color: #756357;
  font-size: .86rem;
  line-height: 1.35;
  margin-top: .55rem;
}
.pos-recon-info {
  border: 1px dashed #e7bd87;
  background: #fffaf0;
  border-radius: 16px;
  padding: .9rem 1rem;
  color: #6b4c37;
}
@media (max-width: 992px) {
  .pos-recon-mode-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 576px) {
  .pos-recon-mode-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header">
    <div>
      <h4 class="fin-page-title mb-1">Pengaturan Gate Daily Recon POS</h4>
      <p class="fin-page-subtitle mb-0">
        POS hanya mengecek apakah daily recon bahan baku dan component sudah dikonfirmasi. Rekon tetap dilakukan di halaman divisi masing-masing.
      </p>
    </div>
  </div>

  <?php $this->load->view('pos/_master_tabs', ['pos_master_tab_active' => 'daily-recon-settings']); ?>

  <?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
  <?php endif; ?>

  <div class="card pos-recon-setting-card">
    <div class="card-body p-4">
      <?php echo form_open('pos/daily-recon-settings/save'); ?>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
          <div>
            <h5 class="mb-1">Mode pengecekan saat buka/tutup kasir</h5>
            <div class="text-muted small">
              Jika recon belum lengkap, kasir mendapat warning. Proses tidak diblokir karena policy saat ini <strong><?php echo html_escape($policy); ?></strong>.
            </div>
          </div>
          <button type="submit" class="btn btn-danger" <?php echo !$canEdit ? 'disabled' : ''; ?>>
            Simpan Pengaturan
          </button>
        </div>

        <div class="pos-recon-mode-grid mb-4">
          <?php foreach ($modeLabels as $key => $item): ?>
            <label class="pos-recon-mode <?php echo $mode === $key ? 'is-active' : ''; ?>">
              <div class="d-flex align-items-center">
                <input type="radio" name="daily_recon_gate_mode" value="<?php echo html_escape($key); ?>" <?php echo $mode === $key ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                <span class="pos-recon-mode-title"><?php echo html_escape($item['title']); ?></span>
              </div>
              <div class="pos-recon-mode-desc"><?php echo html_escape($item['desc']); ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="pos-recon-info">
          <strong>Alur yang dipakai:</strong>
          <span>Divisi melakukan recon di <code>/inventory/stock/daily-recon/division</code> dan <code>/production/component-daily-recon</code>, lalu menekan konfirmasi buka/tutup. POS hanya membaca checkpoint tersebut.</span>
        </div>

        <hr class="my-4">

        <div class="mb-3">
          <h5 class="mb-1">Mode konfirmasi di halaman recon</h5>
          <div class="text-muted small">
            Item dengan lebih dari satu lot otomatis wajib dicek per baris. Daftar wajib di bawah ini menambah item yang tidak boleh dilewati oleh tombol konfirmasi semua.
          </div>
        </div>

        <div class="pos-recon-mode-grid mb-4" style="grid-template-columns:repeat(2,minmax(0,1fr))">
          <?php foreach ($confirmModeLabels as $key => $item): ?>
            <label class="pos-recon-mode <?php echo $confirmMode === $key ? 'is-active' : ''; ?>">
              <div class="d-flex align-items-center">
                <input type="radio" name="daily_recon_confirm_mode" value="<?php echo html_escape($key); ?>" <?php echo $confirmMode === $key ? 'checked' : ''; ?> <?php echo !$canEdit ? 'disabled' : ''; ?>>
                <span class="pos-recon-mode-title"><?php echo html_escape($item['title']); ?></span>
              </div>
              <div class="pos-recon-mode-desc"><?php echo html_escape($item['desc']); ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label fw-semibold">Bahan baku wajib recon per baris</label>
            <textarea class="form-control" name="daily_recon_required_materials" rows="5" <?php echo !$canEdit ? 'disabled' : ''; ?> placeholder="Contoh:&#10;ICE CREAM VANILLA&#10;BB-EXL-...&#10;123"><?php echo html_escape($requiredMaterials); ?></textarea>
            <div class="form-text">Isi material ID, kode, atau nama. Pisahkan dengan koma atau baris baru.</div>
          </div>
          <div class="col-lg-6">
            <label class="form-label fw-semibold">Component wajib recon per baris</label>
            <textarea class="form-control" name="daily_recon_required_components" rows="5" <?php echo !$canEdit ? 'disabled' : ''; ?> placeholder="Contoh:&#10;CHICKEN CUBE 40&#10;PREP-DASH-...&#10;77"><?php echo html_escape($requiredComponents); ?></textarea>
            <div class="form-text">Isi component ID, kode, atau nama. Pisahkan dengan koma atau baris baru.</div>
          </div>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>
</div>
