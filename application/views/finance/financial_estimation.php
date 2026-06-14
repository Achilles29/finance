<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$waterfallRows = (array)($reportData['waterfall_rows'] ?? []);
$metricGroups = (array)($reportData['global_metric_groups'] ?? []);
$divisionRows = (array)($reportData['division_scoreboard'] ?? []);
$accountRows = (array)($reportData['account_snapshots'] ?? []);
?>

<style>
  .finest-card,
  .finest-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 26px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
  }
  .finest-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .finest-summary {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1rem 1.1rem;
  }
  .finest-summary .label {
    font-size: .78rem;
    text-transform: uppercase;
    color: #8b7a6f;
    letter-spacing: .04em;
  }
  .finest-summary .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #4f1f1f;
  }
  .finest-summary .sub {
    font-size: .86rem;
    color: #7f6d62;
  }
  .finest-waterfall-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: .8rem 0;
    border-bottom: 1px dashed rgba(143, 53, 58, .12);
  }
  .finest-waterfall-row:last-child {
    border-bottom: 0;
  }
  .finest-amount-positive { color: #067647; font-weight: 700; }
  .finest-amount-negative { color: #b42318; font-weight: 700; }
  .finest-amount-neutral { color: #4f1f1f; font-weight: 700; }
  .finest-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    font-size: .79rem;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  @media (max-width: 991.98px) {
    .finest-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .finest-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($page_title ?? 'Estimasi Keuangan')); ?></h4>
      <p class="fin-page-subtitle mb-0">Halaman ini membaca engine metric yang sama dengan target dan period close, jadi angka profit estimasi, saldo riil, dan beban berjalan tetap satu sumber.</p>
    </div>
  </div>

  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'financial-estimation']); ?>

  <div class="card finest-card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label mb-1">Tanggal Mulai</label>
          <input type="date" name="date_start" class="form-control" value="<?php echo html_escape((string)($date_start ?? date('Y-m-01'))); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label mb-1">Tanggal Akhir</label>
          <input type="date" name="date_end" class="form-control" value="<?php echo html_escape((string)($date_end ?? date('Y-m-t'))); ?>">
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
          <a href="<?php echo site_url('finance-reports/financial-estimation'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="finest-grid mb-3">
    <div class="finest-summary">
      <div class="label">Omzet Bersih</div>
      <div class="value">Rp <?php echo number_format((float)($overview['net_revenue'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Omzet POS dikurangi refund</div>
    </div>
    <div class="finest-summary">
      <div class="label">Profit Estimasi</div>
      <div class="value <?php echo ((float)($overview['estimated_profit_value'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['estimated_profit_value'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Margin <?php echo number_format((float)($overview['estimated_profit_percent'] ?? 0), 2, ',', '.'); ?>%</div>
    </div>
    <div class="finest-summary">
      <div class="label">Total Beban Estimasi</div>
      <div class="value">Rp <?php echo number_format((float)($overview['estimated_cost_total'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">HPP live + beban + payroll + adjustment</div>
    </div>
    <div class="finest-summary">
      <div class="label">Saldo Riil Kafe</div>
      <div class="value">Rp <?php echo number_format((float)($overview['real_balance_value'] ?? 0), 2, ',', '.'); ?></div>
      <div class="sub">Pembanding terhadap saldo fisik Rp <?php echo number_format((float)($overview['physical_balance_value'] ?? 0), 2, ',', '.'); ?></div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-5">
      <div class="card finest-card h-100">
        <div class="card-body">
          <h5 class="mb-3">Alur Profit Estimasi</h5>
          <?php foreach ($waterfallRows as $row): ?>
            <?php $tone = (string)($row['tone'] ?? 'neutral'); ?>
            <div class="finest-waterfall-row">
              <div><?php echo html_escape((string)($row['label'] ?? '-')); ?></div>
              <div class="finest-amount-<?php echo html_escape($tone); ?>">
                Rp <?php echo number_format((float)($row['amount'] ?? 0), 2, ',', '.'); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-xl-7">
      <div class="card finest-card h-100">
        <div class="card-body">
          <h5 class="mb-3">Metric Global</h5>
          <?php if (empty($metricGroups)): ?>
            <div class="text-muted">Belum ada metric yang bisa dihitung untuk periode ini.</div>
          <?php else: ?>
            <?php foreach ($metricGroups as $groupLabel => $rows): ?>
              <div class="mb-3">
                <div class="small text-uppercase text-muted fw-bold mb-2"><?php echo html_escape((string)$groupLabel); ?></div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <tbody>
                      <?php foreach ((array)$rows as $row): ?>
                        <tr>
                          <td><?php echo html_escape((string)($row['metric_label'] ?? $row['metric_code'] ?? '-')); ?></td>
                          <td class="text-end fw-semibold">
                            <?php
                              $metricCode = strtoupper(trim((string)($row['metric_code'] ?? '')));
                              $amount = (float)($row['metric_amount'] ?? 0);
                              if ($metricCode === 'ESTIMATED_PROFIT_PERCENT') {
                                  echo number_format($amount, 2, ',', '.') . '%';
                              } else {
                                  echo 'Rp ' . number_format($amount, 2, ',', '.');
                              }
                            ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card finest-table mb-3">
    <div class="card-body border-bottom">
      <h5 class="mb-1">Scoreboard Divisi</h5>
      <div class="small text-muted">Ringkasan ini membantu kita membaca divisi mana yang sedang berat di pemakaian bahan baku, adjustment, atau SR pending.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Divisi</th>
            <th class="text-end">SR Pending</th>
            <th class="text-end">Estimasi Gaji</th>
            <th class="text-end">Bahan Masuk</th>
            <th class="text-end">Bahan Terpakai</th>
            <th class="text-end">Adjustment</th>
            <th class="text-end">Stok Akhir</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($divisionRows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada metric divisi untuk periode ini.</td></tr>
          <?php else: ?>
            <?php foreach ($divisionRows as $row): ?>
              <tr>
                <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['SR_PENDING_VALUE'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['PAYROLL_ESTIMATE_RUNNING'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['RAW_MATERIAL_IN_VALUE'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['RAW_MATERIAL_USAGE_VALUE'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end <?php echo ((float)($row['DIVISION_ADJUSTMENT_VALUE'] ?? 0) > 0 ? 'text-danger' : ''); ?>">Rp <?php echo number_format((float)($row['DIVISION_ADJUSTMENT_VALUE'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">Rp <?php echo number_format((float)($row['DIVISION_ENDING_STOCK_VALUE'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card finest-table">
    <div class="card-body border-bottom">
      <h5 class="mb-1">Snapshot Rekening Akhir Periode</h5>
      <div class="small text-muted">Saldo fisik dan saldo riil per rekening pada akhir periode analisa.</div>
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
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada rekening aktif untuk disajikan.</td></tr>
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
