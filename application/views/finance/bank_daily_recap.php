<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$dailyRows = (array)($reportData['daily_rows'] ?? []);
$accountRows = (array)($reportData['account_rows'] ?? []);
$selectedAccountId = (int)($selected_account_id ?? 0);
$selectedAccountLabel = 'Semua rekening';
foreach ((array)($accounts ?? []) as $accountRow) {
    if ((int)($accountRow['id'] ?? 0) === $selectedAccountId) {
        $selectedAccountLabel = trim(
            (string)($accountRow['account_code'] ?? '-') . ' - ' .
            (string)($accountRow['account_name'] ?? '-') . ' - ' .
            (string)($accountRow['bank_name'] ?? '-')
        );
        break;
    }
}
?>

<style>
  .finbank-card,
  .finbank-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .finbank-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 1rem;
  }
  .finbank-summary {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .finbank-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
  }
  .finbank-summary .value {
    font-size: 1.18rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .finbank-summary .sub {
    font-size: .84rem;
    color: #7f6d62;
  }
  .finbank-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  @media (max-width: 1199.98px) {
    .finbank-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .finbank-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($page_title ?? 'Rekap Rekening Harian')); ?></h4>
      <p class="fin-page-subtitle mb-0">Halaman ini memadukan saldo rekening harian dengan saldo riil kafe, supaya kita bisa lihat apakah kas terlihat aman secara fisik tetapi sebenarnya sedang berat oleh utang, payroll, atau kasbon.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'bank-daily-recap']); ?>

  <div class="card finbank-card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label mb-1">Bulan</label>
          <input type="month" name="month" class="form-control" value="<?php echo html_escape((string)($month ?? date('Y-m'))); ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label mb-1">Fokus Rekening</label>
          <select name="account_id" class="form-select">
            <option value="0">Semua rekening</option>
            <?php foreach ((array)($accounts ?? []) as $accountRow): ?>
              <?php $accountId = (int)($accountRow['id'] ?? 0); ?>
              <option value="<?php echo $accountId; ?>" <?php echo $accountId === $selectedAccountId ? 'selected' : ''; ?>>
                <?php echo html_escape((string)($accountRow['account_code'] ?? '-') . ' - ' . (string)($accountRow['account_name'] ?? '-') . ' - ' . (string)($accountRow['bank_name'] ?? '-')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
          <a href="<?php echo site_url('finance-reports/rekap-rekening-harian'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="col-12 small text-muted">
          Fokus saat ini: <strong><?php echo html_escape($selectedAccountLabel); ?></strong>.
          Kolom "rekening" membaca akun terpilih, sedangkan kolom "kafe" tetap membaca agregat seluruh rekening aktif.
        </div>
      </form>
    </div>
  </div>

  <div class="finbank-grid mb-3">
    <div class="finbank-summary">
      <div class="label">Saldo Rekening Fisik</div>
      <div class="value">Rp <?php echo number_format((float)($overview['rekening_physical_balance'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Akhir bulan untuk fokus rekening</div>
    </div>
    <div class="finbank-summary">
      <div class="label">Saldo Rekening Riil</div>
      <div class="value">Rp <?php echo number_format((float)($overview['rekening_real_balance'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Setelah exposure aktif ikut dibaca</div>
    </div>
    <div class="finbank-summary">
      <div class="label">Saldo Fisik Kafe</div>
      <div class="value">Rp <?php echo number_format((float)($overview['kafe_physical_balance'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Akumulasi seluruh rekening aktif</div>
    </div>
    <div class="finbank-summary">
      <div class="label">Saldo Riil Kafe</div>
      <div class="value">Rp <?php echo number_format((float)($overview['kafe_real_balance'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Kekuatan kas setelah utang/piutang/payroll</div>
    </div>
    <div class="finbank-summary">
      <div class="label">Gap Fisik vs Riil</div>
      <div class="value <?php echo ((float)($overview['kafe_gap'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['kafe_gap'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Selisih yang perlu kita jaga</div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="finbank-summary">
        <div class="label">Utang Aktif</div>
        <div class="value">Rp <?php echo number_format((float)($overview['payable_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="finbank-summary">
        <div class="label">Piutang Aktif</div>
        <div class="value">Rp <?php echo number_format((float)($overview['receivable_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="finbank-summary">
        <div class="label">Kasbon Aktif</div>
        <div class="value">Rp <?php echo number_format((float)($overview['cash_advance_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="finbank-summary">
        <div class="label">Payroll Pending</div>
        <div class="value">Rp <?php echo number_format((float)($overview['payroll_pending_total'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>
  </div>

  <div class="card finbank-table mb-3">
    <div class="card-body border-bottom">
      <h5 class="mb-1">Rekap Harian</h5>
      <div class="small text-muted">Baris per hari untuk membaca ritme mutasi rekening fokus sekaligus posisi fisik dan riil kafe.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th class="text-end">Mutasi Rekening Masuk</th>
            <th class="text-end">Mutasi Rekening Keluar</th>
            <th class="text-end">Saldo Rekening Fisik</th>
            <th class="text-end">Saldo Rekening Riil</th>
            <th class="text-end">Saldo Kafe Fisik</th>
            <th class="text-end">Saldo Kafe Riil</th>
            <th class="text-end">Gap Kafe</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dailyRows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data harian untuk periode ini.</td></tr>
          <?php else: ?>
            <?php foreach ($dailyRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['date'] ?? '-')); ?></td>
                <td class="text-end text-success">Rp <?php echo number_format((float)($row['rekening_mutation_in'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['rekening_mutation_out'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['rekening_physical_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold <?php echo ((float)($row['rekening_real_balance'] ?? 0) < 0 ? 'text-danger' : ''); ?>">Rp <?php echo number_format((float)($row['rekening_real_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['kafe_physical_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold <?php echo ((float)($row['kafe_real_balance'] ?? 0) < 0 ? 'text-danger' : ''); ?>">Rp <?php echo number_format((float)($row['kafe_real_balance'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end <?php echo ((float)($row['kafe_gap'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['kafe_gap'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card finbank-table">
    <div class="card-body border-bottom">
      <h5 class="mb-1">Posisi Akhir Per Rekening</h5>
      <div class="small text-muted">Snapshot rekening di akhir bulan untuk membantu audit cepat.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Rekening</th>
            <th class="text-end">Saldo Fisik</th>
            <th class="text-end">Piutang</th>
            <th class="text-end">Kasbon</th>
            <th class="text-end">Utang</th>
            <th class="text-end">Payroll Pending</th>
            <th class="text-end">Saldo Riil</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($accountRows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada rekening aktif.</td></tr>
          <?php else: ?>
            <?php foreach ($accountRows as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo html_escape((string)($row['account_name_snapshot'] ?? '-')); ?></div>
                  <div class="small text-muted"><?php echo html_escape((string)($row['account_code_snapshot'] ?? '-')); ?> - <?php echo html_escape((string)($row['bank_name_snapshot'] ?? '-')); ?></div>
                </td>
                <td class="text-end">Rp <?php echo number_format((float)($row['closing_balance_physical'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success">Rp <?php echo number_format((float)($row['receivable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-success">Rp <?php echo number_format((float)($row['cash_advance_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['payable_outstanding'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end text-danger">Rp <?php echo number_format((float)($row['payroll_pending'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end fw-semibold <?php echo ((float)($row['closing_balance_real'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['closing_balance_real'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
