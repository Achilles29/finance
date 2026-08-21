<?php
$recentLogs = (array)($recent_logs ?? []);
$canCreate  = (bool)($can_create ?? false);
?>

<style>
.wa-manual-member-results {
  position: absolute;
  z-index: 50;
  left: 0;
  right: 0;
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #ead6d0;
  border-radius: .75rem;
  background: #fff;
  box-shadow: 0 .75rem 2rem rgba(80, 40, 40, .12);
}
.wa-manual-result-item {
  cursor: pointer;
  padding: .7rem .9rem;
  border-bottom: 1px solid #f3e8e4;
}
.wa-manual-result-item:hover { background: #fff5f2; }
.wa-target-chip {
  display: inline-flex;
  gap: .35rem;
  align-items: center;
  border: 1px solid #f0d2ca;
  border-radius: 999px;
  padding: .35rem .65rem;
  background: #fff7f4;
  color: #7f2722;
  font-size: .78rem;
  margin: .2rem;
}
.wa-target-chip button {
  border: 0;
  background: transparent;
  color: #b42318;
  padding: 0;
  line-height: 1;
}
</style>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-send-plane-line me-1"></i>Kirim Pesan Manual WA</h4>
      <p class="text-muted mb-0 small">Kirim pesan langsung ke nomor manual atau member yang terdaftar.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= site_url('wa/log?source=MANUAL') ?>" class="btn btn-outline-primary btn-sm">
        <i class="ri ri-history-line me-1"></i>Log Manual
      </a>
      <a href="<?= site_url('wa/settings') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ri ri-settings-3-line me-1"></i>Pengaturan
      </a>
    </div>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Form Pesan</h5>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('wa/manual') ?>" id="waManualForm">
          <input type="hidden" name="selected_member_ids" id="selectedMemberIds" value="">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-semibold">Pesan</label>
              <textarea name="message" class="form-control" rows="7" placeholder="Tulis pesan WhatsApp di sini..."><?= html_escape((string)$this->input->post('message', false)) ?></textarea>
              <div class="form-text">Jika mengirim gambar, pesan ini akan menjadi caption. Isi pesan atau gambar wajib ada.</div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Gambar (opsional)</label>
              <input type="file" name="media_image" id="mediaImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="form-text">Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.</div>
              <div id="mediaPreview" class="mt-2 d-none">
                <img src="" alt="Preview gambar" class="img-fluid rounded border" style="max-height:220px;">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Nomor Manual</label>
              <textarea name="manual_numbers" class="form-control font-monospace" rows="5" placeholder="6281234567890&#10;081234567890 | Nama Customer"><?= html_escape((string)$this->input->post('manual_numbers', false)) ?></textarea>
              <div class="form-text">Satu nomor per baris. Format opsional: <code>nomor | nama</code>. Nomor 08 otomatis diubah ke 628.</div>
            </div>

            <div class="mb-2 position-relative">
              <label class="form-label fw-semibold">Ambil Nomor Dari Member</label>
              <input type="text" id="memberSearch" class="form-control" autocomplete="off" placeholder="Cari no member / nama / nomor HP...">
              <div id="memberResults" class="wa-manual-member-results d-none"></div>
              <div class="form-text">Hanya member aktif dan punya nomor HP yang ditampilkan.</div>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label fw-semibold mb-0">Member Dipilih</label>
                <button type="button" class="btn btn-link btn-sm p-0 text-danger" id="clearMembers">Bersihkan</button>
              </div>
              <div id="selectedMembers" class="border rounded p-2 min-vh-25 text-muted small">Belum ada member dipilih.</div>
            </div>

            <div class="alert alert-info small mb-0">
              <i class="ri ri-information-line me-1"></i>
              Pesan manual langsung dikirim dan tercatat di Log Pengiriman dengan sumber <strong>MANUAL</strong>.
            </div>
          </div>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= site_url('wa/dashboard') ?>" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" <?= !$canCreate ? 'disabled' : '' ?> id="btnManualSubmit">
              <i class="ri ri-send-plane-line me-1"></i>Kirim Pesan
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Log Manual Terakhir</h5>
          <span class="badge bg-label-secondary"><?= number_format(count($recentLogs)) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:520px;overflow:auto;">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Waktu</th>
                  <th>Tujuan</th>
                  <th>Status</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                  <td class="small text-muted"><?= html_escape(date('d/m H:i', strtotime($log['sent_at']))) ?></td>
                  <td class="small">
                    <div class="fw-semibold"><?= html_escape($log['display_name'] ?: '-') ?></div>
                    <div class="text-muted font-monospace"><?= html_escape($log['phone_number'] ?: '-') ?></div>
                  </td>
                  <td>
                    <?php if (($log['status'] ?? '') === 'SENT'): ?>
                      <span class="badge bg-success">Terkirim</span>
                    <?php else: ?>
                      <span class="badge bg-danger" title="<?= html_escape($log['error_detail'] ?? '') ?>">Gagal</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if (($log['status'] ?? '') === 'FAILED'): ?>
                      <button type="button" class="btn btn-outline-danger btn-sm" data-retry-log="<?= (int)$log['id'] ?>">
                        Retry
                      </button>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentLogs)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pengiriman manual.</td></tr>
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
(() => {
  const memberSearch = document.getElementById('memberSearch');
  const memberResults = document.getElementById('memberResults');
  const selectedMembersEl = document.getElementById('selectedMembers');
  const selectedMemberIds = document.getElementById('selectedMemberIds');
  const clearMembers = document.getElementById('clearMembers');
  const form = document.getElementById('waManualForm');
  const submitBtn = document.getElementById('btnManualSubmit');
  const mediaImage = document.getElementById('mediaImage');
  const mediaPreview = document.getElementById('mediaPreview');
  const selected = new Map();
  let timer = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
  }

  function renderSelected() {
    selectedMemberIds.value = Array.from(selected.keys()).join(',');
    if (selected.size === 0) {
      selectedMembersEl.className = 'border rounded p-2 min-vh-25 text-muted small';
      selectedMembersEl.textContent = 'Belum ada member dipilih.';
      return;
    }
    selectedMembersEl.className = 'border rounded p-2';
    selectedMembersEl.innerHTML = Array.from(selected.values()).map(row => `
      <span class="wa-target-chip" data-id="${Number(row.id)}">
        <span>${escapeHtml(row.member_name || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '')}</span></span>
        <button type="button" title="Hapus" data-remove-member="${Number(row.id)}">×</button>
      </span>
    `).join('');
  }

  function hideResults() {
    memberResults.classList.add('d-none');
    memberResults.innerHTML = '';
  }

  function searchMembers(q) {
    if (q.length < 2) {
      hideResults();
      return;
    }
    memberResults.classList.remove('d-none');
    memberResults.innerHTML = '<div class="p-3 text-muted small">Mencari member...</div>';
    fetch(`<?= site_url('wa/api/member-search') ?>?q=${encodeURIComponent(q)}&limit=12`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.json())
      .then(data => {
        const rows = Array.isArray(data.rows) ? data.rows : [];
        if (!rows.length) {
          memberResults.innerHTML = '<div class="p-3 text-muted small">Member tidak ditemukan.</div>';
          return;
        }
        memberResults.innerHTML = rows.map(row => `
          <div class="wa-manual-result-item" data-member-id="${Number(row.id)}"
               data-member='${escapeHtml(JSON.stringify(row))}'>
            <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
            <div class="small text-muted">
              ${escapeHtml(row.member_no || '-')} · <span class="font-monospace">${escapeHtml(row.mobile_phone || '-')}</span>
            </div>
          </div>
        `).join('');
      })
      .catch(() => {
        memberResults.innerHTML = '<div class="p-3 text-danger small">Gagal mencari member.</div>';
      });
  }

  memberSearch?.addEventListener('input', function() {
    clearTimeout(timer);
    const q = this.value.trim();
    timer = setTimeout(() => searchMembers(q), 250);
  });

  memberResults?.addEventListener('click', function(e) {
    const item = e.target.closest('[data-member-id]');
    if (!item) return;
    try {
      const row = JSON.parse(item.dataset.member || '{}');
      if (row.id) {
        selected.set(String(row.id), row);
        renderSelected();
        memberSearch.value = '';
        hideResults();
      }
    } catch (err) {
      hideResults();
    }
  });

  selectedMembersEl?.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-remove-member]');
    if (!btn) return;
    selected.delete(String(btn.dataset.removeMember));
    renderSelected();
  });

  clearMembers?.addEventListener('click', function() {
    selected.clear();
    renderSelected();
  });

  document.addEventListener('click', function(e) {
    if (!e.target.closest('#memberSearch') && !e.target.closest('#memberResults')) {
      hideResults();
    }
  });

  form?.addEventListener('submit', function() {
    if (!submitBtn) return;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ri ri-loader-4-line me-1"></i>Mengirim...';
  });

  mediaImage?.addEventListener('change', function() {
    const img = mediaPreview?.querySelector('img');
    const file = this.files && this.files[0] ? this.files[0] : null;
    if (!mediaPreview || !img || !file) {
      mediaPreview?.classList.add('d-none');
      return;
    }
    img.src = URL.createObjectURL(file);
    mediaPreview.classList.remove('d-none');
  });

  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-retry-log]');
    if (!btn) return;
    if (!confirm('Kirim ulang pesan manual ini?')) return;

    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Mengirim...';

    fetch('<?= site_url('wa/api/log-retry/') ?>' + btn.getAttribute('data-retry-log'), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(data => {
        alert(data.ok ? 'Pesan berhasil dikirim ulang.' : ('Gagal kirim ulang: ' + (data.message || 'Error tidak diketahui')));
        window.location.reload();
      })
      .catch(err => {
        alert('Koneksi error: ' + err);
        btn.disabled = false;
        btn.textContent = oldText;
      });
  });
})();
</script>
