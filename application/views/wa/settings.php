<?php
$session = (array)($session ?? []);
$canEdit = (bool)($can_edit ?? false);
?>

<div class="container-xxl py-3">
  <div class="mb-3">
    <h4 class="mb-1 fw-bold"><i class="ri ri-settings-3-line me-1"></i>Pengaturan WA Bot</h4>
    <p class="text-muted mb-0 small">Konfigurasi koneksi ke WhatsApp Bot yang berjalan di server.</p>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-7">
      <!-- Koneksi Bot -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Koneksi Bot</h5></div>
        <form method="post" action="<?= site_url('wa/settings') ?>">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">URL Internal Bot</label>
              <input type="url" name="bot_api_url" class="form-control font-monospace"
                value="<?= html_escape($session['bot_api_url'] ?? 'http://127.0.0.1:3070') ?>"
                <?= !$canEdit ? 'readonly' : '' ?>
                placeholder="http://127.0.0.1:3070">
              <div class="form-text">
                URL base internal bot (port 3070 secara default).
                Finance app akan menghubungi bot via URL ini.
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Token Auth Bot</label>
              <input type="text" name="bot_api_token" class="form-control font-monospace"
                value="<?= html_escape($session['bot_api_token'] ?? 'local-dev-token') ?>"
                <?= !$canEdit ? 'readonly' : '' ?>
                placeholder="local-dev-token">
              <div class="form-text">
                Token yang sama perlu dikonfigurasi di <code>wa-bot/config.json</code> → <code>internalToken</code>.
              </div>
            </div>
          </div>
          <?php if ($canEdit): ?>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>

      <!-- Test Koneksi -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Test Koneksi & Kirim Pesan</h5></div>
        <div class="card-body">
          <div class="d-flex gap-2 mb-3">
            <button class="btn btn-outline-primary btn-sm" id="btn-ping">
              <i class="ri ri-wifi-line me-1"></i>Ping Bot
            </button>
            <div id="ping-result" class="align-self-center small text-muted"></div>
          </div>
          <hr>
          <div class="mb-2">
            <label class="form-label fw-semibold">Kirim Pesan Test</label>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-md-5">
              <input type="text" id="test-phone" class="form-control form-control-sm font-monospace" placeholder="6281234567890">
            </div>
            <div class="col-md-7">
              <textarea id="test-message" class="form-control form-control-sm" rows="2" placeholder="Halo! Ini pesan test dari Finance App."></textarea>
            </div>
          </div>
          <button class="btn btn-success btn-sm" id="btn-send-test">
            <i class="ri ri-send-plane-line me-1"></i>Kirim
          </button>
          <div id="test-result" class="mt-2 small"></div>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <!-- Status Bot -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header"><h5 class="mb-0">Status Bot Saat Ini</h5></div>
        <div class="card-body">
          <?php
          $st = strtoupper($session['status'] ?? 'UNKNOWN');
          $badge = match($st) { 'CONNECTED' => 'bg-success', 'WAITING_QR' => 'bg-warning', 'DISCONNECTED' => 'bg-danger', default => 'bg-secondary' };
          $label = match($st) { 'CONNECTED' => 'Terhubung', 'WAITING_QR' => 'Menunggu QR', 'DISCONNECTED' => 'Terputus', default => 'Tidak Diketahui' };
          ?>
          <dl class="row mb-0 small">
            <dt class="col-5">Status</dt>
            <dd class="col-7"><span class="badge <?= $badge ?>"><?= $label ?></span></dd>
            <dt class="col-5">Nomor Terhubung</dt>
            <dd class="col-7"><?= html_escape($session['phone_number'] ?? '-') ?></dd>
            <dt class="col-5">Ping Terakhir</dt>
            <dd class="col-7"><?= html_escape($session['last_ping_at'] ?? '-') ?></dd>
          </dl>
        </div>
      </div>

      <!-- Panduan -->
      <div class="card border-0 shadow-sm">
        <div class="card-header"><h5 class="mb-0">Panduan Konfigurasi</h5></div>
        <div class="card-body small">
          <ol class="mb-0">
            <li class="mb-2">
              Pastikan <code>wa-bot</code> berjalan: jalankan <code>node index.js</code> di folder <code>wa-bot/</code>.
            </li>
            <li class="mb-2">
              Buka URL Settings Bot (<code>http://localhost:3001</code>) untuk melihat log dan scan QR code.
            </li>
            <li class="mb-2">
              Setelah terhubung, bot akan aktif di port internal <strong>3070</strong>. Finance app berkomunikasi lewat port ini.
            </li>
            <li class="mb-2">
              Pastikan <code>internalToken</code> di <code>wa-bot/config.json</code> sama dengan <strong>Token Auth Bot</strong> di atas.
            </li>
            <li>
              Klik <strong>Ping Bot</strong> untuk memverifikasi koneksi dari Finance App ke Bot.
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-ping')?.addEventListener('click', function () {
  const result = document.getElementById('ping-result');
  this.disabled = true;
  result.textContent = 'Menghubungi bot…';
  fetch('<?= site_url('wa/api/status') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(d => {
      if (d.ok !== false) {
        result.innerHTML = '<span class="text-success">✓ Bot aktif — Status: <strong>' + (d.status || '?') + '</strong>' + (d.phone ? ' | 📱 ' + d.phone : '') + '</span>';
      } else {
        result.innerHTML = '<span class="text-danger">✗ ' + (d.message || 'Gagal terhubung') + '</span>';
      }
    })
    .catch(e => { result.innerHTML = '<span class="text-danger">✗ ' + e + '</span>'; })
    .finally(() => { this.disabled = false; });
});

document.getElementById('btn-send-test')?.addEventListener('click', function () {
  const phone = document.getElementById('test-phone').value.trim();
  const msg   = document.getElementById('test-message').value.trim();
  const result = document.getElementById('test-result');
  if (!phone || !msg) { result.innerHTML = '<span class="text-danger">Isi nomor dan pesan.</span>'; return; }
  this.disabled = true;
  result.textContent = 'Mengirim…';
  fetch('<?= site_url('wa/api/send-test') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ to: phone, message: msg })
  })
    .then(r => r.json())
    .then(d => {
      result.innerHTML = d.ok
        ? '<span class="text-success">✓ Pesan terkirim!</span>'
        : '<span class="text-danger">✗ ' + (d.message || 'Gagal') + '</span>';
    })
    .catch(e => { result.innerHTML = '<span class="text-danger">✗ ' + e + '</span>'; })
    .finally(() => { this.disabled = false; });
});
</script>
