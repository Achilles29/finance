<?php
$this->load->view('assets/_nav', ['asset_nav_active' => 'items']);
$group = $group ?? [];
$filters = $filters ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$rows = $rows ?? [];
$statusLabels = $status_labels ?? [];
$fmtMoney = static function ($value): string {
  return 'Rp ' . number_format((float)$value, 0, ',', '.');
};
$statusBadge = static function ($status): string {
  $status = strtoupper((string)$status);
  $map = [
    'ACTIVE' => 'success',
    'BROKEN' => 'danger',
    'REPAIR' => 'warning',
    'LOST' => 'dark',
    'RETIRED' => 'secondary',
    'DISPOSED' => 'secondary',
  ];
  return $map[$status] ?? 'secondary';
};
$photo = trim((string)($group['photo_path'] ?? ''));
?>
<style>
.asset-group-hero{border:1px solid #e7d8ce;border-radius:8px;background:#fff}
.asset-group-photo{width:96px;height:96px;border-radius:8px;object-fit:cover;background:#f8fafc;border:1px solid #e5e7eb}
.asset-group-empty{width:96px;height:96px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-stat{border:1px solid #e7d8ce;border-radius:8px;background:#fff;min-height:88px}
.asset-stat .label{font-size:.76rem;text-transform:uppercase;color:#8b6f61;letter-spacing:.02em}
.asset-stat .value{font-size:1.25rem;font-weight:800;color:#2f1f1a}
.asset-thumb{width:50px;height:50px;border-radius:8px;object-fit:cover;background:#f3f4f6;border:1px solid #e5e7eb}
.asset-empty-thumb{width:50px;height:50px;border-radius:8px;display:grid;place-items:center;background:#f8fafc;color:#94a3b8;border:1px dashed #cbd5e1}
.asset-table{table-layout:fixed}
.asset-table th{font-size:.76rem;text-transform:uppercase;color:#77665c;letter-spacing:.02em;white-space:nowrap}
.asset-table td{vertical-align:middle}
.asset-progress{height:8px;border-radius:99px;background:#eef2f7;overflow:hidden}
.asset-progress span{display:block;height:100%;background:#18745c}
.asset-actions{display:inline-flex;justify-content:flex-end;min-width:78px}
@media (max-width: 991.98px){.asset-table{table-layout:auto}.asset-actions{width:100%;justify-content:flex-start!important}.asset-table td{white-space:normal}}
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <h4 class="mb-1">Detail Grup Aset</h4>
    <div class="text-muted">Daftar unit fisik untuk produk aset yang sama.</div>
  </div>
  <a href="<?= site_url('asset-management') ?>" class="btn btn-outline-secondary">
    <i class="ri ri-arrow-left-line me-1"></i>Kembali
  </a>
</div>

<div class="asset-group-hero p-3 mb-3">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <?php if ($photo !== ''): ?>
      <img class="asset-group-photo" src="<?= html_escape(base_url($photo)) ?>" alt="<?= html_escape($group['asset_name'] ?? '') ?>">
    <?php else: ?>
      <span class="asset-group-empty"><i class="ri ri-archive-2-line" style="font-size:2rem"></i></span>
    <?php endif; ?>
    <div class="flex-grow-1">
      <h5 class="mb-1"><?= html_escape($group['asset_name'] ?? '-') ?></h5>
      <div class="text-muted"><?= html_escape($group['category_name'] ?? '-') ?><?= !empty($group['sample_asset_code']) ? ' | contoh ' . html_escape($group['sample_asset_code']) : '' ?></div>
      <?php if (!empty($group['brand']) || !empty($group['model_name'])): ?>
        <div class="text-muted"><?= html_escape(trim((string)($group['brand'] ?? '') . ' ' . (string)($group['model_name'] ?? ''))) ?></div>
      <?php endif; ?>
    </div>
    <div class="text-end">
      <div class="h4 mb-0"><?= number_format((int)($group['unit_count'] ?? 0), 0, ',', '.') ?></div>
      <div class="text-muted small">total unit</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Aktif</div><div class="value text-success"><?= number_format((int)($group['active_count'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Unit siap pakai</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Bermasalah</div><div class="value text-danger"><?= number_format((int)($group['issue_count'] ?? 0), 0, ',', '.') ?></div><div class="small text-muted">Rusak, repair, hilang</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Nilai buku</div><div class="value"><?= $fmtMoney($group['book_value'] ?? 0) ?></div><div class="small text-muted">Semua unit grup</div></div></div>
  <div class="col-6 col-lg-3"><div class="asset-stat p-3"><div class="label">Kondisi rata-rata</div><div class="value"><?= number_format((float)($group['avg_condition'] ?? 0), 1, ',', '.') ?>%</div><div class="small text-muted">Skor fisik unit</div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get" action="<?= site_url('asset-management/group/' . rawurlencode((string)($group['group_key'] ?? ''))) ?>">
      <div class="col-12 col-lg-4">
        <label class="form-label">Cari unit</label>
        <input type="text" class="form-control" name="q" value="<?= html_escape($filters['q'] ?? '') ?>" placeholder="Kode aset, serial, lokasi, PIC">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach (['ALL' => 'Semua', 'ISSUE' => 'Bermasalah'] + $statusLabels as $key => $label): ?>
            <option value="<?= html_escape($key) ?>" <?= (($filters['status'] ?? '') === $key) ? 'selected' : '' ?>><?= html_escape($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-3">
        <label class="form-label">Divisi</label>
        <select class="form-select" name="division_id">
          <option value="0">Semua</option>
          <?php foreach (($divisions ?? []) as $div): ?>
            <option value="<?= (int)$div['id'] ?>" <?= (int)($filters['division_id'] ?? 0) === (int)$div['id'] ? 'selected' : '' ?>><?= html_escape($div['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-1">
        <label class="form-label">Baris</label>
        <select class="form-select" name="per_page">
          <?php foreach ([10,25,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)($pg['per_page'] ?? 25) === $pp ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-2 d-grid">
        <button class="btn btn-outline-primary" type="submit"><i class="ri ri-search-line me-1"></i>Terapkan</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover asset-table mb-0">
      <colgroup>
        <col style="width:31%">
        <col style="width:24%">
        <col style="width:16%">
        <col style="width:13%">
        <col style="width:8%">
        <col style="width:8%">
      </colgroup>
      <thead class="table-light">
        <tr>
          <th>Unit Aset</th>
          <th>Lokasi</th>
          <th>Nilai</th>
          <th>Kondisi</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted py-5">Belum ada unit pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php $unitPhoto = trim((string)($row['photo_path'] ?? '')); ?>
          <tr>
            <td style="min-width:280px">
              <div class="d-flex gap-2 align-items-center">
                <?php if ($unitPhoto !== ''): ?>
                  <img class="asset-thumb" src="<?= html_escape(base_url($unitPhoto)) ?>" alt="<?= html_escape($row['asset_name'] ?? '') ?>">
                <?php else: ?>
                  <span class="asset-empty-thumb"><i class="ri ri-file-text-line"></i></span>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= html_escape($row['asset_code'] ?? '-') ?></div>
                  <div class="small text-muted"><?= html_escape($row['asset_name'] ?? '-') ?><?= !empty($row['serial_no']) ? ' | SN ' . html_escape($row['serial_no']) : '' ?></div>
                  <?php if (!empty($row['batch_no'])): ?><div class="small text-muted">Batch <?= html_escape($row['batch_no']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <div><?= html_escape($row['division_name'] ?? '-') ?></div>
              <div class="small text-muted"><?= html_escape($row['current_location'] ?: ($row['outlet_name'] ?? '-')) ?></div>
              <?php if (!empty($row['custodian_name'])): ?><div class="small text-muted">PIC: <?= html_escape($row['custodian_name']) ?></div><?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?= $fmtMoney($row['book_value'] ?? 0) ?></div>
              <div class="small text-muted">Beli <?= $fmtMoney($row['acquisition_cost'] ?? 0) ?></div>
              <div class="small text-muted">Susut <?= number_format((float)($row['depreciation_percent'] ?? 0), 1, ',', '.') ?>%</div>
            </td>
            <td style="min-width:140px">
              <div class="d-flex justify-content-between small"><span>Skor</span><strong><?= (int)($row['condition_score'] ?? 0) ?>%</strong></div>
              <div class="asset-progress"><span style="width:<?= max(0, min(100, (int)($row['condition_score'] ?? 0))) ?>%"></span></div>
            </td>
            <td><span class="badge bg-<?= $statusBadge($row['status'] ?? '') ?>"><?= html_escape($row['status_label'] ?? '-') ?></span></td>
            <td class="text-end">
              <div class="dropdown asset-actions">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Aksi</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="<?= site_url('asset-management/detail/' . (int)$row['id']) ?>"><i class="ri ri-eye-line me-1"></i>Detail</a></li>
                  <?php if (!empty($can_edit)): ?><li><a class="dropdown-item" href="<?= site_url('asset-management/edit/' . (int)$row['id']) ?>"><i class="ri ri-edit-line me-1"></i>Edit</a></li><?php endif; ?>
                  <?php if (!empty($can_damage)): ?><li><a class="dropdown-item text-danger" href="<?= site_url('asset-management/damage/' . (int)$row['id']) ?>"><i class="ri ri-alert-line me-1"></i>Lapor Rusak</a></li><?php endif; ?>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="text-muted small">Total <?= number_format((int)($pg['total'] ?? 0), 0, ',', '.') ?> unit</span>
    <div class="btn-group">
      <?php
        $query = $_GET;
        $prev = max(1, (int)$pg['page'] - 1);
        $next = min((int)$pg['total_pages'], (int)$pg['page'] + 1);
        $query['page'] = $prev;
      ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] <= 1 ? 'disabled' : '' ?>" href="<?= site_url('asset-management/group/' . rawurlencode((string)($group['group_key'] ?? '')) . '?' . http_build_query($query)) ?>">Prev</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" disabled>Page <?= (int)$pg['page'] ?>/<?= (int)$pg['total_pages'] ?></button>
      <?php $query['page'] = $next; ?>
      <a class="btn btn-sm btn-outline-secondary <?= (int)$pg['page'] >= (int)$pg['total_pages'] ? 'disabled' : '' ?>" href="<?= site_url('asset-management/group/' . rawurlencode((string)($group['group_key'] ?? '')) . '?' . http_build_query($query)) ?>">Next</a>
    </div>
  </div>
</div>
