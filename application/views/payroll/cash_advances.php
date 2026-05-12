<?php
$filters = $filters ?? [];
$rows = $rows ?? [];
$pg = $pg ?? ['page' => 1, 'total_pages' => 1, 'per_page' => 25, 'total' => 0];
$statusOptions = $status_options ?? ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'];
$employeeOptions = $employee_options ?? [];
$companyAccountOptions = $company_account_options ?? [];
$tab = $tab ?? 'transaction';
$recapRows = $recap_rows ?? [];
$recapEmployeeId = (int)($recap_employee_id ?? 0);
$recapEmployeeHistory = $recap_employee_history ?? [];
$editRow = $edit_row ?? null;
$isEdit = !empty($editRow);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0"><?php echo html_escape($title ?? 'Kasbon Pegawai'); ?></h4>
    <small class="text-muted">Tenor bersifat opsional. Isi `0` jika kasbon tanpa tenor tetap (fleksibel sesuai pembayaran aktual).</small>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><strong><?php echo $isEdit ? 'Edit Kasbon' : 'Tambah Kasbon'; ?></strong></div>
      <div class="card-body">
        <form method="post" action="<?php echo $isEdit ? site_url('payroll/cash-advances/update/' . (int)$editRow['id']) : site_url('payroll/cash-advances/store'); ?>" class="row g-2">
          <div class="col-12">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select" required>
              <option value="">Pilih pegawai</option>
              <?php foreach($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($editRow['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Request</label>
            <input type="date" name="request_date" class="form-control" required value="<?php echo html_escape((string)($editRow['request_date'] ?? date('Y-m-d'))); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Approved</label>
            <input type="date" name="approved_date" class="form-control" value="<?php echo html_escape((string)($editRow['approved_date'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Nominal</label>
            <input type="number" step="0.01" min="0" name="amount" class="form-control" required value="<?php echo html_escape((string)($editRow['amount'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Tenor (bulan)</label>
            <input type="number" min="0" max="60" name="tenor_month" class="form-control" value="<?php echo html_escape((string)($editRow['tenor_month'] ?? 0)); ?>">
            <small class="text-muted">`0` = fleksibel (tanpa jadwal cicilan tetap)</small>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Rekening Sumber Kasbon</label>
            <select name="company_account_id" class="form-select">
              <option value="">Pilih rekening (opsional)</option>
              <?php foreach($companyAccountOptions as $a): ?>
                <option value="<?php echo (int)$a['value']; ?>" <?php echo ((int)($editRow['company_account_id'] ?? 0) === (int)$a['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$a['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select" required>
              <?php foreach($statusOptions as $s): ?>
                <option value="<?php echo html_escape($s); ?>" <?php echo (strtoupper((string)($editRow['status'] ?? 'DRAFT')) === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?php echo html_escape((string)($editRow['notes'] ?? '')); ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-loading-label="Menyimpan..."><?php echo $isEdit ? 'Update Kasbon' : 'Simpan Kasbon'; ?></button>
            <?php if ($isEdit): ?>
              <a href="<?php echo site_url('payroll/cash-advances'); ?>" class="btn btn-outline-secondary">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link <?php echo $tab === 'transaction' ? 'active' : ''; ?>" href="<?php echo site_url('payroll/cash-advances?tab=transaction'); ?>">Transaksi Kasbon</a></li>
      <li class="nav-item"><a class="nav-link <?php echo $tab === 'recap' ? 'active' : ''; ?>" href="<?php echo site_url('payroll/cash-advances?tab=recap'); ?>">Rekap Pegawai</a></li>
    </ul>

    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="<?php echo site_url('payroll/cash-advances'); ?>" class="row g-2 align-items-end">
          <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
          <div class="col-md-3">
            <label class="form-label mb-1">Cari</label>
            <input type="text" name="q" class="form-control" value="<?php echo html_escape((string)($filters['q'] ?? '')); ?>" placeholder="No/Nama/NIP">
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1">Pegawai</label>
            <select name="employee_id" class="form-select">
              <option value="">Semua</option>
              <?php foreach($employeeOptions as $o): ?>
                <option value="<?php echo (int)$o['value']; ?>" <?php echo ((int)($filters['employee_id'] ?? 0) === (int)$o['value']) ? 'selected' : ''; ?>><?php echo html_escape((string)$o['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($tab === 'transaction'): ?>
          <div class="col-md-2">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select">
              <option value="">Semua</option>
              <?php foreach($statusOptions as $s): ?>
                <option value="<?php echo html_escape($s); ?>" <?php echo (($filters['status'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo html_escape($s); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-2">
            <label class="form-label mb-1">Dari</label>
            <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($filters['date_start'] ?? '')); ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label mb-1">Sampai</label>
            <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($filters['date_end'] ?? '')); ?>">
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a href="<?php echo site_url('payroll/cash-advances?tab=' . $tab); ?>" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>
    </div>

    <?php if ($tab === 'recap'): ?>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>Pegawai</th><th>Divisi</th><th class="text-end">Total Kasbon</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th class="text-end">Dokumen</th><th class="text-center">Detail</th></tr></thead>
            <tbody>
            <?php if (empty($recapRows)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada rekap kasbon.</td></tr>
            <?php else: foreach($recapRows as $r): ?>
              <tr>
                <td><?php echo html_escape((string)($r['employee_name'] ?? '-')); ?><div class="small text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></div></td>
                <td><?php echo html_escape((string)($r['division_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($r['total_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success"><?php echo number_format((float)($r['paid_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold text-danger"><?php echo number_format((float)($r['outstanding_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo (int)($r['doc_count'] ?? 0); ?></td>
                <td class="text-center"><a href="<?php echo site_url('payroll/cash-advances?tab=recap&recap_employee_id=' . (int)($r['employee_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Lihat Detail"><i class="ri ri-eye-line"></i></a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($recapEmployeeId > 0): ?>
      <div class="card mt-3">
        <div class="card-header"><strong>Detail Histori Kasbon Pegawai</strong></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>No Kasbon</th><th>Tanggal</th><th>Status</th><th class="text-end">Nominal</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th class="text-end">Tenor</th><th>Cicilan</th></tr></thead>
            <tbody>
            <?php if (empty($recapEmployeeHistory)): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada histori kasbon pegawai ini.</td></tr>
            <?php else: foreach($recapEmployeeHistory as $h): ?>
              <tr>
                <td><?php echo html_escape((string)($h['advance_no'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($h['request_date'] ?? '-')); ?></td>
                <td><?php echo html_escape((string)($h['status'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($h['amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success"><?php echo number_format((float)($h['installment_paid_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold text-danger"><?php echo number_format((float)($h['outstanding_amount'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo (int)($h['tenor_month'] ?? 0); ?></td>
                <td class="small text-muted">
                  <?php
                    $parts = [];
                    foreach ((array)($h['installments'] ?? []) as $it) {
                        $parts[] = (string)$it['due_period'] . ' [' . number_format((float)$it['paid_amount'], 0, ',', '.') . '/' . number_format((float)$it['plan_amount'], 0, ',', '.') . ']';
                    }
                    echo !empty($parts) ? html_escape(implode(' | ', $parts)) : '-';
                  ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>No</th>
                <th>Pegawai</th>
                <th>Status</th>
                <th class="text-end">Nominal</th>
                <th class="text-end">Outstanding</th>
                <th class="text-end">Rencana/Bln</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data kasbon.</td></tr>
              <?php else: ?>
                <?php foreach($rows as $r): ?>
                  <?php $status = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
                  <tr>
                    <td>
                      <?php echo html_escape((string)($r['advance_no'] ?? '-')); ?>
                      <div class="small text-muted"><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></div>
                    </td>
                    <td>
                      <?php echo html_escape((string)($r['employee_name'] ?? '-')); ?>
                      <div class="small text-muted"><?php echo html_escape((string)($r['employee_code'] ?? '')); ?></div>
                    </td>
                    <td><span class="badge bg-<?php echo $status === 'APPROVED' ? 'success' : ($status === 'SETTLED' ? 'primary' : ($status === 'REJECTED' ? 'danger' : 'secondary')); ?>"><?php echo html_escape((string)($r['status'] ?? 'DRAFT')); ?></span></td>
                    <td class="text-end"><?php echo number_format((float)($r['amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end fw-semibold"><?php echo number_format((float)($r['outstanding_amount'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="text-end"><?php echo number_format((float)($r['monthly_deduction_plan'] ?? 0), 2, ',', '.'); ?></td>
                    <td class="action-cell text-center">
                      <?php if ($status === 'SETTLED'): ?>
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Lunas</span>
                      <?php else: ?>
                        <a href="<?php echo site_url('payroll/cash-advances?tab=transaction&edit_id=' . (int)$r['id']); ?>" class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit"><i class="ri ri-pencil-line"></i></a>
                        <form method="post" action="<?php echo site_url('payroll/cash-advances/void/' . (int)$r['id']); ?>" class="d-inline" data-confirm="VOID kasbon ini?">
                          <button type="submit" class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Void" data-loading-label="Void..."><i class="ri ri-close-circle-line"></i></button>
                        </form>
                        <form method="post" action="<?php echo site_url('payroll/cash-advances/delete/' . (int)$r['id']); ?>" class="d-inline" data-confirm="Hapus kasbon ini?">
                          <button type="submit" class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" data-loading-label="Hapus..."><i class="ri ri-delete-bin-line"></i></button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="7" class="pt-0">
                      <div class="small text-muted mb-2">
                        Cicilan:
                        <?php
                          $inst = (array)($r['installments'] ?? []);
                          if (empty($inst)) {
                              echo '-';
                          } else {
                              $chunks = [];
                              foreach($inst as $it){
                                  $chunks[] = html_escape((string)$it['due_period']) . ' [' . number_format((float)$it['paid_amount'], 2, ',', '.') . '/' . number_format((float)$it['plan_amount'], 2, ',', '.') . ']';
                              }
                              echo implode(' | ', $chunks);
                          }
                        ?>
                      </div>
                      <?php if ($status === 'SETTLED'): ?>
                        <div class="small text-success fw-semibold py-2">Kasbon sudah SETTLED (lunas). Aksi pembayaran dinonaktifkan.</div>
                      <?php else: ?>
                      <form method="post" action="<?php echo site_url('payroll/cash-advances/pay-installment/' . (int)$r['id']); ?>" class="d-flex gap-2 align-items-center flex-wrap js-ca-pay-form">
                        <label class="small mb-0">Bayar:</label>
                        <select name="installment_no" class="form-select form-select-sm" style="max-width:190px;">
                          <option value="0">Auto / fleksibel</option>
                          <?php foreach((array)($r['installments'] ?? []) as $it): ?>
                            <option value="<?php echo (int)$it['installment_no']; ?>">#<?php echo (int)$it['installment_no']; ?> (<?php echo html_escape((string)$it['due_period']); ?>)</option>
                          <?php endforeach; ?>
                        </select>
                        <select name="payment_method" class="form-select form-select-sm js-ca-method" style="max-width:170px;">
                          <option value="CASH">Cair Tunai</option>
                          <option value="TRANSFER">Transfer</option>
                          <option value="SALARY_CUT">Potong Gaji</option>
                        </select>
                        <select name="company_account_id" class="form-select form-select-sm js-ca-account" style="max-width:240px;">
                          <option value="">Rek sumber (tunai/transfer)</option>
                          <?php foreach($companyAccountOptions as $a): ?>
                            <?php $optType = strtoupper((string)($a['account_type'] ?? '')); ?>
                            <option value="<?php echo (int)$a['value']; ?>" data-type="<?php echo html_escape($optType); ?>"><?php echo html_escape((string)$a['label']); ?><?php echo $optType !== '' ? ' [' . html_escape($optType) . ']' : ''; ?></option>
                          <?php endforeach; ?>
                        </select>
                        <input type="date" name="payment_date" class="form-control form-control-sm js-ca-paydate" style="max-width:170px;" value="<?php echo date('Y-m-d'); ?>" placeholder="Tanggal bayar">
                        <input type="date" name="salary_cut_period_start" class="form-control form-control-sm js-ca-period-start" style="max-width:170px;" value="<?php echo date('Y-m-01'); ?>" placeholder="Periode dari">
                        <input type="date" name="salary_cut_period_end" class="form-control form-control-sm js-ca-period-end" style="max-width:170px;" value="<?php echo date('Y-m-t'); ?>" placeholder="Periode sampai">
                        <input type="number" step="0.01" min="0" name="paid_amount" class="form-control form-control-sm" style="max-width:180px;" placeholder="Nominal" required>
                        <input type="text" name="transfer_ref_no" class="form-control form-control-sm js-ca-ref" style="max-width:170px;" placeholder="Ref transfer">
                        <input type="text" name="notes" class="form-control form-control-sm" style="max-width:220px;" placeholder="Catatan">
                        <button type="submit" class="btn btn-sm btn-outline-success" data-loading-label="Posting...">Posting</button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var forms = document.querySelectorAll('.js-ca-pay-form');
  forms.forEach(function (form) {
    var methodEl = form.querySelector('.js-ca-method');
    var accountEl = form.querySelector('.js-ca-account');
    var payDateEl = form.querySelector('.js-ca-paydate');
    var periodStartEl = form.querySelector('.js-ca-period-start');
    var periodEndEl = form.querySelector('.js-ca-period-end');
    var refEl = form.querySelector('.js-ca-ref');
    if (!methodEl || !accountEl || !payDateEl || !periodStartEl || !periodEndEl || !refEl) {
      return;
    }

    var resetAccountByType = function (expectedType) {
      if (!expectedType) {
        return;
      }
      var selected = accountEl.options[accountEl.selectedIndex];
      if (!selected || !selected.value) {
        return;
      }
      var selectedType = (selected.getAttribute('data-type') || '').toUpperCase();
      if (selectedType !== expectedType) {
        accountEl.value = '';
      }
    };

    var applyGuard = function () {
      var method = (methodEl.value || '').toUpperCase();
      var periodLabel = 'Periode gaji (potong gaji)';
      if (method === 'SALARY_CUT') {
        accountEl.value = '';
        accountEl.disabled = true;
        accountEl.required = false;
        payDateEl.disabled = true;
        payDateEl.required = false;
        periodStartEl.disabled = false;
        periodStartEl.required = true;
        periodStartEl.title = periodLabel;
        periodEndEl.disabled = false;
        periodEndEl.required = true;
        periodEndEl.title = periodLabel;
        refEl.disabled = true;
        refEl.required = false;
        refEl.value = '';
      } else if (method === 'TRANSFER') {
        accountEl.disabled = false;
        accountEl.required = true;
        payDateEl.disabled = false;
        payDateEl.required = true;
        periodStartEl.disabled = true;
        periodStartEl.required = false;
        periodEndEl.disabled = true;
        periodEndEl.required = false;
        refEl.disabled = false;
        refEl.required = true;
        resetAccountByType('BANK');
      } else {
        accountEl.disabled = false;
        accountEl.required = true;
        payDateEl.disabled = false;
        payDateEl.required = true;
        periodStartEl.disabled = true;
        periodStartEl.required = false;
        periodEndEl.disabled = true;
        periodEndEl.required = false;
        refEl.disabled = true;
        refEl.required = false;
        refEl.value = '';
        resetAccountByType('CASH');
      }
    };

    methodEl.addEventListener('change', applyGuard);
    periodStartEl.addEventListener('change', function () {
      if (!periodStartEl.value) {
        return;
      }
      var start = new Date(periodStartEl.value + 'T00:00:00');
      if (isNaN(start.getTime())) {
        return;
      }
      var end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
      var m = String(end.getMonth() + 1).padStart(2, '0');
      var d = String(end.getDate()).padStart(2, '0');
      periodEndEl.value = end.getFullYear() + '-' + m + '-' + d;
    });
    accountEl.addEventListener('change', function () {
      var method = (methodEl.value || '').toUpperCase();
      var selected = accountEl.options[accountEl.selectedIndex];
      var selectedType = (selected ? (selected.getAttribute('data-type') || '').toUpperCase() : '');
      if (method === 'CASH' && selected.value && selectedType !== 'CASH') {
        accountEl.value = '';
      }
      if (method === 'TRANSFER' && selected.value && selectedType !== 'BANK') {
        accountEl.value = '';
      }
    });
    applyGuard();
  });
});
</script>
