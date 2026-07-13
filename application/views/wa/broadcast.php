<?php
$broadcasts   = (array)($broadcasts ?? []);
$filterStatus = (string)($filter_status ?? '');
$filterQ      = (string)($filter_q ?? '');
$canCreate    = (bool)($can_create ?? false);
$canEdit      = (bool)($can_edit ?? false);
$canDelete    = (bool)($can_delete ?? false);

$statusOptions = ['DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED'];
$statusLabel   = ['DRAFT'=>'Draft','QUEUED'=>'Dijadwalkan','SENDING'=>'Berjalan','DONE'=>'Selesai','FAILED'=>'Gagal','CANCELLED'=>'Dibatalkan'];
$statusBadge   = ['DRAFT'=>'bg-secondary','QUEUED'=>'bg-info','SENDING'=>'bg-warning','DONE'=>'bg-success','FAILED'=>'bg-danger','CANCELLED'=>'bg-light text-dark'];
?>

<div class="container-xxl py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h4 class="mb-1 fw-bold"><i class="ri ri-broadcast-line me-1"></i>Broadcast WhatsApp</h4>
      <p class="text-muted mb-0 small">Kirim pesan massal ke pelanggan atau nomor pilihan.</p>
    </div>
    <?php if ($canCreate): ?>
    <a href="<?= site_url('wa/broadcast/create') ?>" class="btn btn-primary btn-sm">
      <i class="ri ri-add-line me-1"></i>Buat Broadcast
    </a>
    <?php endif; ?>
  </div>

  <?php if ($flash = $this->session->flashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php elseif ($flash = $this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= html_escape($flash) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endif; ?>

  <!-- Filter -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama broadcast…" value="<?= html_escape($filterQ) ?>">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <?php foreach ($statusOptions as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $statusLabel[$s] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="<?= site_url('wa/broadcast') ?>" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Nama Broadcast</th>
              <th>Target</th>
              <th class="text-center">Status</th>
              <th class="text-end">Terkirim</th>
              <th class="text-end">Gagal</th>
              <th>Dibuat</th>
              <th>Selesai</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($broadcasts as $bc): ?>
            <tr>
              <td>
                <a href="<?= site_url('wa/broadcast/detail/' . (int)$bc['id']) ?>" class="fw-semibold text-decoration-none">
                  <?= html_escape($bc['name']) ?>
                </a>
                <div class="text-muted small"><?= html_escape($bc['target_type']) ?></div>
              </td>
              <td class="text-center"><?= number_format((int)$bc['total_targets']) ?></td>
              <td class="text-center">
                <span class="badge <?= $statusBadge[$bc['status']] ?? 'bg-secondary' ?>">
                  <?= $statusLabel[$bc['status']] ?? $bc['status'] ?>
                </span>
              </td>
              <td class="text-end text-success"><?= number_format((int)$bc['total_sent']) ?></td>
              <td class="text-end text-danger"><?= number_format((int)$bc['total_failed']) ?></td>
              <td class="small text-muted"><?= html_escape(date('d/m/y H:i', strtotime($bc['created_at']))) ?></td>
              <td class="small text-muted"><?= $bc['finished_at'] ? html_escape(date('d/m/y H:i', strtotime($bc['finished_at']))) : '-' ?></td>
              <td class="text-center">
                <a href="<?= site_url('wa/broadcast/detail/' . (int)$bc['id']) ?>" class="btn btn-xs btn-outline-primary me-1" title="Detail">
                  <i class="ri ri-eye-line"></i>
                </a>
                <?php if ($canDelete && in_array($bc['status'], ['DRAFT','FAILED','CANCELLED'], true)): ?>
                <a href="<?= site_url('wa/broadcast/delete/' . (int)$bc['id']) ?>"
                   class="btn btn-xs btn-outline-danger"
                   onclick="return confirm('Hapus broadcast ini?')" title="Hapus">
                  <i class="ri ri-delete-bin-line"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($broadcasts)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada broadcast.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
