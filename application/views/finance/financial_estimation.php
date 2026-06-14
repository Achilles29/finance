<?php
$reportData = (array)($report ?? []);
$overview = (array)($reportData['overview'] ?? []);
$rows = (array)($reportData['rows'] ?? []);
$selectedMonth = (int)($month ?? (int)date('n'));
$selectedYear = (int)($year ?? (int)date('Y'));
?>

<style>
  .fin-est-shell { display: grid; gap: 1rem; }
  .fin-est-card,
  .fin-est-table {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(96, 60, 39, .07);
    overflow: hidden;
    background: #fff;
  }
  .fin-est-filter {
    border: 1px solid rgba(143, 53, 58, .10);
    border-radius: 24px;
    background: linear-gradient(180deg, #fff, #fff8f5);
    padding: 1.25rem;
  }
  .fin-est-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
  }
  .fin-est-kpi {
    border-radius: 20px;
    border: 1px solid rgba(143, 53, 58, .08);
    background: #fff;
    padding: 1rem 1.1rem;
  }
  .fin-est-kpi .label {
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8b7a6f;
    margin-bottom: .35rem;
  }
  .fin-est-kpi .value {
    font-size: 1.25rem;
    font-weight: 800;
    color: #4f1f1f;
  }
  .fin-est-kpi .value.positive { color: #16a34a; }
  .fin-est-kpi .value.negative { color: #dc2626; }
  .fin-est-note {
    border: 1px solid rgba(58, 132, 255, .18);
    background: #f8fbff;
    color: #42526e;
    border-radius: 999px;
    padding: .7rem 1rem;
    font-size: .92rem;
  }
  .fin-est-table thead th {
    background: linear-gradient(135deg, #8f353a, #6f222a);
    color: #fff;
    border: 0;
    white-space: nowrap;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .fin-est-table tbody td {
    vertical-align: middle;
    border-color: rgba(143, 53, 58, .08);
    font-size: .86rem;
  }
  .fin-est-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: #fff7f5;
    font-size: .86rem;
    border-top: 2px solid rgba(143, 53, 58, .16);
  }
  .fin-est-scroll {
    max-height: 70vh;
    overflow: auto;
  }
  .fin-est-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 3;
  }
  .fin-est-table .table {
    min-width: 980px;
  }
  .fin-est-sub {
    display: block;
    margin-top: .2rem;
    font-size: .8rem;
    color: #7f6d62;
  }
  @media (max-width: 991.98px) {
    .fin-est-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 575.98px) {
    .fin-est-kpis { grid-template-columns: 1fr; }
  }
</style>

<div class="container-xxl py-3">
  <?php $this->load->view('finance/_tabs', ['finance_tab_active' => 'financial-estimation']); ?>

  <div class="fin-est-shell">
    <div class="fin-est-filter">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-4">
          <label class="form-label mb-1">Bulan</label>
          <select name="month" class="form-select">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?php echo $m; ?>" <?php echo $m === $selectedMonth ? 'selected' : ''; ?>><?php echo html_escape(date('F', mktime(0, 0, 0, $m, 1, 2026))); ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-3">
          <label class="form-label mb-1">Tahun</label>
          <input type="number" name="year" class="form-control" min="2020" max="2100" value="<?php echo $selectedYear; ?>">
        </div>
        <div class="col-lg-2 col-md-3">
          <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
        <div class="col-lg-5 col-md-12">
          <div class="small text-muted pt-md-4">
            Data absen tersedia: <?php echo (int)($overview['attendance_days_with_data'] ?? 0); ?> hari dari <?php echo (int)($overview['days_in_month'] ?? 0); ?> hari bulan <?php echo html_escape((string)($reportData['month_label'] ?? '')); ?>
          </div>
        </div>
      </form>
    </div>

    <div class="fin-est-note">
      Estimasi gaji mengikuti ritme halaman <a href="<?php echo site_url('attendance/estimate'); ?>" class="text-decoration-none">/attendance/estimate</a>, dengan basis harian dari <code>att_daily.daily_salary_amount</code> dan lembur harian yang sudah tercatat.
    </div>

    <div class="fin-est-kpis">
      <div class="fin-est-kpi">
        <div class="label">Total Penjualan</div>
        <div class="value positive">Rp <?php echo number_format((float)($overview['total_sales'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi">
        <div class="label">Total Pengeluaran</div>
        <div class="value negative">Rp <?php echo number_format((float)($overview['total_expense'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi">
        <div class="label">Est. Total Gaji</div>
        <div class="value">Rp <?php echo number_format((float)($overview['total_salary'] ?? 0), 2, ',', '.'); ?></div>
      </div>
      <div class="fin-est-kpi">
        <div class="label">Est. Profit Bersih</div>
        <div class="value <?php echo ((float)($overview['total_final_profit'] ?? 0) < 0 ? 'negative' : 'positive'); ?>">Rp <?php echo number_format((float)($overview['total_final_profit'] ?? 0), 2, ',', '.'); ?></div>
      </div>
    </div>

    <div class="fin-est-table">
      <div class="table-responsive fin-est-scroll">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th class="text-end">Penjualan</th>
              <th class="text-end">Refund</th>
              <th class="text-end">Pengeluaran</th>
              <th class="text-end">Pendapatan Kotor</th>
              <th class="text-end">Estimasi Gaji</th>
              <th class="text-end">Estimasi Pendapatan Final</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">Belum ada data untuk periode ini.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?php echo html_escape((string)($row['date'] ?? '-')); ?></td>
                  <td class="text-end text-primary fw-semibold">Rp <?php echo number_format((float)($row['sales_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">Rp <?php echo number_format((float)($row['refund_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">Rp <?php echo number_format((float)($row['expense_total'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end fw-semibold <?php echo ((float)($row['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['gross_profit'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end text-danger">
                    Rp <?php echo number_format((float)($row['salary_total'] ?? 0), 2, ',', '.'); ?>
                    <?php if ((float)($row['salary_total'] ?? 0) > 0): ?>
                      <span class="fin-est-sub">
                        Absen Rp <?php echo number_format((float)($row['attendance_base_total'] ?? 0), 2, ',', '.'); ?>
                        <?php if ((float)($row['overtime_total'] ?? 0) > 0): ?> + Lembur Rp <?php echo number_format((float)($row['overtime_total'] ?? 0), 2, ',', '.'); ?><?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-bold <?php echo ((float)($row['final_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($row['final_profit'] ?? 0), 2, ',', '.'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <td class="fw-bold">Total</td>
              <td class="text-end fw-bold text-primary">Rp <?php echo number_format((float)($overview['total_sales'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_refund'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_expense'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold <?php echo ((float)($overview['total_gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['total_gross_profit'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold text-danger">Rp <?php echo number_format((float)($overview['total_salary'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end fw-bold <?php echo ((float)($overview['total_final_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">Rp <?php echo number_format((float)($overview['total_final_profit'] ?? 0), 2, ',', '.'); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
