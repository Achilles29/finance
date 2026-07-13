<?php
$session  = (array)($session ?? []);
$stats    = (array)($stats ?? []);
$recentLogs = (array)($stats['recentLogs'] ?? []);
$waStatus   = strtoupper(trim((string)($session['status'] ?? 'UNKNOWN')));
$badgeClass = match($waStatus) {
    'CONNECTED'    => 'bg-success',
    'WAITING_QR'   => 'bg-warning',
    'DISCONNECTED' => 'bg-danger',
    default        => 'bg-secondary',
};
$badgeLabel = match($waStatus) {
    'CONNECTED'    => 'Terhubung',
    'WAITING_QR'   => 'Menunggu QR',
    'DISCONNECTED' => 'Terputus',
    default        => 'Tidak Diketahui',
};
?>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-whatsapp-line me-1"></i>WhatsApp Dashboard</h4>
      <p class="text-muted mb-0 small">Status bot, statistik broadcast, dan pengiriman pesan WhatsApp.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" id="btn-refresh-status">
        <i class="ri ri-refresh-line me-1"></i>Refresh Status
      </button>
      <a href="<?= site_url('wa/broadcast/create') ?>" class="btn btn-success btn-sm">
        <i class="ri ri-broadcast-line me-1"></i>Broadcast Baru
      </a>
    </div>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Status Bot -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-muted mb-1 small fw-semibold text-uppercase">Status Bot</p>
              <span id="bot-status-badge" class="badge <?= $badgeClass ?> fs-6"><?= $badgeLabel ?></span>
              <div class="mt-1 small text-muted" id="bot-phone">
                <?= $waStatus === 'CONNECTED' && $session['phone_number'] ? ('📱 ' . html_escape($session['phone_number'])) : '' ?>
              </div>
            </div>
            <div class="avatar avatar-lg">
              <span class="avatar-initial rounded-circle bg-label-success" style="font-size:1.5rem;">
                <i class="ri ri-whatsapp-line"></i>
              </span>
            </div>
          </div>
          <?php if ($session['last_ping_at']): ?>
            <p class="text-muted mb-0 mt-2 small">Ping terakhir: <?= html_escape($session['last_ping_at']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <p class="text-muted mb-1 small fw-semibold text-uppercase">Total Broadcast</p>
          <h2 class="mb-0 fw-bold"><?= number_format((int)($stats['totalBroadcast'] ?? 0)) ?></h2>
          <small class="text-success"><?= number_format((int)($stats['doneBroadcast'] ?? 0)) ?> selesai</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <p class="text-muted mb-1 small fw-semibold text-uppercase">Total Terkirim</p>
          <h2 class="mb-0 fw-bold"><?= number_format((int)($stats['totalSent'] ?? 0)) ?></h2>
          <small class="text-muted">dari semua broadcast</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <p class="text-muted mb-1 small fw-semibold text-uppercase">Kirim Hari Ini</p>
          <h2 class="mb-0 fw-bold text-primary"><?= number_format((int)($stats['todaySent'] ?? 0)) ?></h2>
          <small class="text-muted">pesan</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <p class="text-muted mb-1 small fw-semibold text-uppercase">Template Aktif</p>
          <h2 class="mb-0 fw-bold"><?= number_format((int)($stats['totalTemplates'] ?? 0)) ?></h2>
          <small class="text-muted"><?= number_format((int)($stats['totalGroups'] ?? 0)) ?> grup</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Log terbaru -->
  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Log Pengiriman Terbaru</h5>
      <a href="<?= site_url('wa/log') ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Waktu</th>
              <th>Sumber</th>
              <th>Tujuan</th>
              <th>Pesan</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentLogs as $log): ?>
            <tr>
              <td class="text-muted small"><?= html_escape(date('d/m H:i', strtotime($log['sent_at']))) ?></td>
              <td><span class="badge bg-label-secondary"><?= html_escape($log['source']) ?></span></td>
              <td class="small">
                <?= html_escape($log['display_name'] ?: ($log['phone_number'] ?: $log['group_jid'] ?: '-')) ?>
              </td>
              <td class="small text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= html_escape(mb_substr($log['message_preview'] ?? '', 0, 60)) ?>
              </td>
              <td class="text-center">
                <?php if ($log['status'] === 'SENT'): ?>
                  <span class="badge bg-success">Terkirim</span>
                <?php elseif ($log['status'] === 'FAILED'): ?>
                  <span class="badge bg-danger" title="<?= html_escape($log['error_detail'] ?? '') ?>">Gagal</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentLogs)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada log pengiriman.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-refresh-status')?.addEventListener('click', function () {
  this.disabled = true;
  this.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Memuat...';
  fetch('<?= site_url('wa/api/status') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
      const badge = document.getElementById('bot-status-badge');
      const phone = document.getElementById('bot-phone');
      const labelMap  = { CONNECTED: 'Terhubung', WAITING_QR: 'Menunggu QR', DISCONNECTED: 'Terputus', UNKNOWN: 'Tidak Diketahui' };
      const classMap  = { CONNECTED: 'bg-success', WAITING_QR: 'bg-warning', DISCONNECTED: 'bg-danger', UNKNOWN: 'bg-secondary' };
      const s = (data.status || 'UNKNOWN').toUpperCase();
      badge.className = 'badge ' + (classMap[s] || 'bg-secondary') + ' fs-6';
      badge.textContent = labelMap[s] || s;
      phone.textContent = s === 'CONNECTED' && data.phone ? '📱 ' + data.phone : '';
    })
    .catch(() => alert('Tidak dapat menghubungi WA Bot.'))
    .finally(() => {
      this.disabled = false;
      this.innerHTML = '<i class="ri ri-refresh-line me-1"></i>Refresh Status';
    });
});
</script>
