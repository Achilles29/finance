<?php
$detail = $detail ?? [];
$header = (array)($detail['header'] ?? []);
$lines = (array)($detail['lines'] ?? []);
$links = (array)($detail['links'] ?? []);
$canVerify = !empty($can_verify);
$canEdit = !empty($can_edit);
$canReject = !empty($can_reject);
$canVoid = !empty($can_void);
$isPurchaseScope = !empty($is_purchase_scope);

if (!function_exists('finance_dreq_detail_badge')) {
    function finance_dreq_detail_badge($status)
    {
        switch (strtoupper((string)$status)) {
            case 'SUBMITTED':
                return 'bg-warning text-dark';
            case 'VERIFIED':
                return 'bg-success';
            case 'REJECTED':
                return 'bg-danger';
            case 'VOID':
                return 'bg-secondary';
            default:
                return 'bg-light text-dark';
        }
    }
}

  if (!function_exists('finance_dreq_detail_location_label')) {
    function finance_dreq_detail_location_label($destinationType)
    {
      $destinationType = strtoupper(trim((string)$destinationType));
      if (strpos($destinationType, 'EVENT') !== false) {
        return 'Event';
      }
      if (in_array($destinationType, ['BAR', 'KITCHEN', 'OFFICE'], true)) {
        return 'Reguler';
      }
      return $destinationType !== '' ? $destinationType : '-';
    }
  }
?>

<style>
  .dreq-action-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .dreq-action-btn {
    min-height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 12px !important;
    font-weight: 600;
  }
  .dreq-action-btn.btn-outline-secondary { color: #6c757d; border-color: rgba(108,117,125,.55); }
  .dreq-action-btn.btn-outline-success { color: #198754; border-color: rgba(25,135,84,.55); }
  .dreq-action-btn.btn-outline-warning { color: #d39e00; border-color: rgba(211,158,0,.55); }
  .dreq-action-btn.btn-outline-danger { color: #dc3545; border-color: rgba(220,53,69,.55); }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h4 class="mb-0"><i class="ri-file-search-line page-title-icon me-1"></i><?php echo html_escape($title ?? 'Detail Pengajuan Divisi'); ?></h4>
    <small class="text-muted">
      <?php echo $isPurchaseScope
        ? 'Purchase meninjau detail pengajuan ini sebelum membentuk SR/PO final.'
        : 'Detail pengajuan untuk divisi Anda. Edit atau void masih bisa dilakukan sebelum diverifikasi purchase.'; ?>
    </small>
  </div>
  <div class="dreq-action-wrap">
    <a href="<?php echo site_url('procurement/division-po-sr'); ?>" class="btn btn-outline-secondary dreq-action-btn"><i class="ri ri-arrow-left-line"></i><span>Kembali</span></a>
    <?php if ($canEdit || $canVerify): ?>
      <a href="<?php echo site_url('procurement/division-po-sr/edit/' . (int)($header['id'] ?? 0)); ?>" class="btn <?php echo $canVerify ? 'btn-outline-success' : 'btn-outline-warning'; ?> dreq-action-btn">
        <i class="ri <?php echo $canVerify ? 'ri-check-line' : 'ri-pencil-line'; ?>"></i>
        <span><?php echo $canVerify ? 'Verifikasi' : 'Edit'; ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<?php $this->load->view('purchase/_po_sr_tabs', ['po_sr_active' => 'division-po-sr']); ?>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?php echo html_escape((string)$this->session->flashdata('success')); ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?php echo html_escape((string)$this->session->flashdata('error')); ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="text-muted small">No Request</div>
            <div class="fw-semibold"><?php echo html_escape((string)($header['request_no'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Tanggal</div>
            <div><?php echo html_escape((string)($header['request_date'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Tgl Butuh</div>
            <div><?php echo html_escape((string)($header['needed_date'] ?? '-')); ?></div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Status</div>
            <div><span class="badge <?php echo finance_dreq_detail_badge((string)($header['status'] ?? '')); ?>"><?php echo html_escape((string)($header['status'] ?? '-')); ?></span></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Divisi</div>
            <div><?php echo html_escape((string)($header['division_name'] ?? '-')); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Lokasi Stok</div>
            <div><?php echo html_escape(finance_dreq_detail_location_label((string)($header['destination_type'] ?? ''))); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Pengaju</div>
            <div><?php echo html_escape((string)($header['created_by_username'] ?? '-')); ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Update Terakhir</div>
            <div><?php echo html_escape((string)($header['updated_at'] ?? $header['created_at'] ?? '-')); ?></div>
          </div>
          <div class="col-12">
            <div class="text-muted small">Catatan</div>
            <div class="border rounded p-2 bg-light-subtle"><?php echo nl2br(html_escape((string)($header['notes'] ?? '-'))); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Dokumen Hasil</h6>
        <?php if (empty($links)): ?>
          <div class="text-muted small">Belum ada dokumen hasil. Request masih menunggu proses berikutnya.</div>
        <?php else: ?>
          <?php foreach ($links as $link): ?>
            <div class="border rounded p-2 mb-2">
              <div class="fw-semibold"><?php echo html_escape((string)($link['doc_type'] ?? '-')); ?>: <?php echo html_escape((string)($link['doc_no'] ?? '-')); ?></div>
              <div class="small text-muted">Status: <?php echo html_escape((string)($link['doc_status'] ?? '-')); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canReject || $canVoid): ?>
          <div class="border-top pt-3 mt-3">
            <h6 class="mb-2">Aksi</h6>
            <?php if ($canReject): ?>
              <form method="post" action="<?php echo site_url('procurement/division-po-sr/action/' . (int)($header['id'] ?? 0)); ?>" class="mb-2 dreq-confirm-form" data-confirm-message="Reject pengajuan ini?">
                <input type="hidden" name="action" value="REJECT">
                <button type="submit" class="btn btn-outline-danger w-100 dreq-action-btn"><i class="ri ri-close-line"></i><span>Reject</span></button>
              </form>
            <?php endif; ?>
            <?php if ($canVoid): ?>
              <form method="post" action="<?php echo site_url('procurement/division-po-sr/action/' . (int)($header['id'] ?? 0)); ?>" class="dreq-confirm-form" data-confirm-message="Void pengajuan ini?">
                <input type="hidden" name="action" value="VOID">
                <button type="submit" class="btn btn-outline-danger w-100 dreq-action-btn"><i class="ri ri-close-circle-line"></i><span>Void</span></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body border-bottom">
    <h6 class="mb-0">Line Pengajuan</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Profile</th>
          <th>Jenis</th>
          <th>Vendor PO</th>
          <th>UOM</th>
          <th class="text-end">Qty Beli</th>
          <th class="text-end">Qty Isi</th>
          <th class="text-end">Snapshot Stok</th>
          <th class="text-end">Route SR</th>
          <th class="text-end">Route PO</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lines)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Belum ada line pengajuan.</td></tr>
        <?php else: ?>
          <?php foreach ($lines as $line): ?>
            <tr>
              <td><?php echo (int)($line['line_no'] ?? 0); ?></td>
              <td>
                <div class="fw-semibold"><?php echo html_escape((string)($line['profile_name'] ?? '-')); ?></div>
              </td>
              <td><?php echo html_escape((string)($line['line_kind'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($line['vendor_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($line['profile_buy_uom_code'] ?? '-')); ?> -> <?php echo html_escape((string)($line['profile_content_uom_code'] ?? '-')); ?></td>
              <td class="text-end"><?php echo ui_num((float)($line['qty_buy_requested'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($line['qty_content_requested'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($line['qty_content_available_snapshot'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($line['qty_content_to_sr'] ?? 0)); ?></td>
              <td class="text-end"><?php echo ui_num((float)($line['qty_content_to_po'] ?? 0)); ?></td>
              <td><?php echo html_escape((string)($line['notes'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('.dreq-confirm-form');
    if (!form || form.dataset.confirmed === '1') {
      return;
    }

    event.preventDefault();
    var message = form.getAttribute('data-confirm-message') || 'Lanjutkan aksi ini?';

    function doSubmit() {
      form.dataset.confirmed = '1';
      form.submit();
    }

    if (window.FinanceUI && typeof window.FinanceUI.confirm === 'function') {
      Promise.resolve(window.FinanceUI.confirm({
        title: 'Konfirmasi',
        message: message,
        confirmText: 'Ya',
        cancelText: 'Batal'
      })).then(function (confirmed) {
        if (confirmed) {
          doSubmit();
        }
      });
      return;
    }

    if (window.confirm(message)) {
      doSubmit();
    }
  });
})();
</script>