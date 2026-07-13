<?php
$broadcast  = (array)($broadcast ?? []);
$lines      = (array)($lines ?? []);
$canEdit    = (bool)($can_edit ?? false);
$canDelete  = (bool)($can_delete ?? false);

$statusLabel = ['DRAFT'=>'Draft','QUEUED'=>'Dijadwalkan','SENDING'=>'Berjalan','DONE'=>'Selesai','FAILED'=>'Gagal','CANCELLED'=>'Dibatalkan'];
$statusBadge = ['DRAFT'=>'bg-secondary','QUEUED'=>'bg-info','SENDING'=>'bg-warning','DONE'=>'bg-success','FAILED'=>'bg-danger','CANCELLED'=>'bg-light text-dark'];
$lineBadge   = ['PENDING'=>'bg-secondary','SENT'=>'bg-success','FAILED'=>'bg-danger','SKIPPED'=>'bg-light text-dark'];

$currentStatus = $broadcast['status'] ?? 'DRAFT';
$canStart = $canEdit && in_array($currentStatus, ['DRAFT','FAILED'], true);
$bcId = (int)($broadcast['id'] ?? 0);
?>

<div class="container-xxl py-3">
  <div class="mb-3">
    <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ri ri-arrow-left-line me-1"></i>Kembali
    </a>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Info Broadcast</h5>
          <span class="badge <?= $statusBadge[$currentStatus] ?? 'bg-secondary' ?>">
            <?= $statusLabel[$currentStatus] ?? $currentStatus ?>
          </span>
        </div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Nama</dt>
            <dd class="col-7"><?= html_escape($broadcast['name'] ?? '-') ?></dd>
            <dt class="col-5">Tipe Target</dt>
            <dd class="col-7"><?= html_escape($broadcast['target_type'] ?? '-') ?></dd>
            <dt class="col-5">Total Target</dt>
            <dd class="col-7"><?= number_format((int)($broadcast['total_targets'] ?? 0)) ?></dd>
            <dt class="col-5">Terkirim</dt>
            <dd class="col-7 text-success fw-semibold"><?= number_format((int)($broadcast['total_sent'] ?? 0)) ?></dd>
            <dt class="col-5">Gagal</dt>
            <dd class="col-7 text-danger fw-semibold"><?= number_format((int)($broadcast['total_failed'] ?? 0)) ?></dd>
            <dt class="col-5">Dibuat</dt>
            <dd class="col-7"><?= html_escape($broadcast['created_at'] ?? '-') ?></dd>
            <dt class="col-5">Mulai Kirim</dt>
            <dd class="col-7"><?= html_escape($broadcast['started_at'] ?? '-') ?></dd>
            <dt class="col-5">Selesai</dt>
            <dd class="col-7"><?= html_escape($broadcast['finished_at'] ?? '-') ?></dd>
            <?php if ($broadcast['notes']): ?>
            <dt class="col-5">Catatan</dt>
            <dd class="col-7"><?= nl2br(html_escape($broadcast['notes'])) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
        <?php if ($canStart || ($canDelete && in_array($currentStatus, ['DRAFT','FAILED','CANCELLED'], true))): ?>
        <div class="card-footer d-flex gap-2">
          <?php if ($canStart): ?>
          <button class="btn btn-success btn-sm flex-fill" id="btn-start">
            <i class="ri ri-send-plane-line me-1"></i>Mulai Kirim
          </button>
          <?php endif; ?>
          <?php if ($canDelete && in_array($currentStatus, ['DRAFT','FAILED','CANCELLED'], true)): ?>
          <a href="<?= site_url('wa/broadcast/delete/' . $bcId) ?>"
             class="btn btn-outline-danger btn-sm"
             onclick="return confirm('Hapus broadcast ini?')">
            <i class="ri ri-delete-bin-line"></i>
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Pesan -->
      <div class="card border-0 shadow-sm">
        <div class="card-header"><h5 class="mb-0">Pesan</h5></div>
        <div class="card-body">
          <?php $msg = $broadcast['custom_message'] ?: ($broadcast['template_body'] ?? ''); ?>
          <pre class="small mb-0" style="white-space:pre-wrap;"><?= html_escape($msg ?: '(menggunakan template: ' . ($broadcast['template_name'] ?? '-') . ')') ?></pre>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Daftar Penerima (<?= count($lines) ?>)</h5>
          <div id="progress-bar-wrapper" class="d-none w-50">
            <div class="progress" style="height:6px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progress-bar" style="width:0%"></div>
            </div>
            <div class="text-muted small mt-1" id="progress-text">Mengirim…</div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light sticky-top">
                <tr>
                  <th>#</th>
                  <th>Nama</th>
                  <th>Nomor</th>
                  <th class="text-center">Status</th>
                  <th>Waktu Kirim</th>
                  <th>Error</th>
                </tr>
              </thead>
              <tbody id="line-tbody">
                <?php foreach ($lines as $i => $line): ?>
                <tr id="line-<?= (int)$line['id'] ?>">
                  <td class="text-muted"><?= $i + 1 ?></td>
                  <td><?= html_escape($line['display_name'] ?? '-') ?></td>
                  <td class="font-monospace small"><?= html_escape($line['phone_number']) ?></td>
                  <td class="text-center">
                    <span class="badge <?= $lineBadge[$line['status']] ?? 'bg-secondary' ?>">
                      <?= html_escape($line['status']) ?>
                    </span>
                  </td>
                  <td class="small text-muted"><?= $line['sent_at'] ? html_escape(date('H:i:s', strtotime($line['sent_at']))) : '-' ?></td>
                  <td class="small text-danger"><?= html_escape($line['error_msg'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada penerima.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-start')?.addEventListener('click', function () {
  if (!confirm('Mulai kirim broadcast ke semua penerima sekarang?')) return;
  this.disabled = true;
  this.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim…';
  document.getElementById('progress-bar-wrapper')?.classList.remove('d-none');

  fetch('<?= site_url('wa/api/broadcast-start/' . $bcId) ?>', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        alert('Broadcast selesai. Terkirim: ' + data.sent + ', Gagal: ' + data.failed);
        location.reload();
      } else {
        alert('Gagal: ' + (data.message || 'Error tidak diketahui'));
        location.reload();
      }
    })
    .catch(e => { alert('Koneksi error: ' + e); location.reload(); });
});
</script>
