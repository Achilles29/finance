<?php
$detail = is_array($detail ?? null) ? $detail : [];
$component = is_array($detail['component'] ?? null) ? $detail['component'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$lines = is_array($detail['lines'] ?? null) ? $detail['lines'] : [];

$directStd = (float)($summary['direct_cost_standard'] ?? 0);
$directLive = (float)($summary['direct_cost_live'] ?? 0);
$variableStd = (float)($summary['variable_cost_std'] ?? 0);
$variableLive = (float)($summary['variable_cost_live'] ?? 0);
$totalStd = (float)($summary['total_cogs_std'] ?? 0);
$totalLive = (float)($summary['total_cogs_live'] ?? 0);
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1"><?php echo html_escape((string)($component['component_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Hasil 1x produksi: <?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?> |
        Baris <?php echo (int)($summary['line_count'] ?? 0); ?> | Formula default | Ini menjadi patokan 1 batch resep produksi
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali</a>
      <a class="btn btn-outline-primary action-icon-btn component-action-btn" href="<?php echo site_url('production/component-formulas/edit/' . (int)($component['id'] ?? 0)); ?>" title="Edit Formula" aria-label="Edit Formula"><i class="ri ri-edit-line"></i></a>
    </div>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'formula']); ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Standar</div>
        <div class="fw-semibold">Rp <?php echo number_format($directStd, 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel Rp <?php echo number_format($directStd, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Direct Cost Live</div>
        <div class="fw-semibold">Rp <?php echo number_format($directLive, 2, ',', '.'); ?></div>
        <div class="small text-muted">HPP tanpa variabel Rp <?php echo number_format($directLive, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Biaya Variabel</div>
        <div class="fw-semibold"><?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> | <?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%</div>
        <div class="small text-muted">Std Rp <?php echo number_format($variableStd, 2, ',', '.'); ?> | Live Rp <?php echo number_format($variableLive, 2, ',', '.'); ?></div>
      </div>
      <div class="col-md-3">
        <div class="small text-muted">Hasil 1x Produksi</div>
        <div class="fw-semibold"><?php echo number_format((float)($summary['output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="small text-muted mt-1">COGS per <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="fw-semibold">Std Rp <?php echo number_format($totalStd / max((float)($summary['output_qty'] ?? 1), 0.0001), 2, ',', '.'); ?></div>
        <div class="fw-semibold">Live Rp <?php echo number_format($totalLive / max((float)($summary['output_qty'] ?? 1), 0.0001), 2, ',', '.'); ?></div>
        <div class="small text-muted">Potensi produksi <?php echo number_format((float)($summary['potential_output_total'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></div>
        <div class="small text-muted">Bottleneck: <?php echo html_escape((string)($summary['bottleneck_source'] ?? '-')); ?></div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:70px;">No</th>
              <th>Sumber</th>
              <th style="width:130px;">Divisi Sumber</th>
              <th class="text-end" style="width:120px;">Qty</th>
              <th style="width:120px;">Satuan</th>
              <th class="text-end" style="width:140px;">Stok Tersedia</th>
              <th class="text-end" style="width:170px;">Potensi Output</th>
              <th class="text-end" style="width:130px;">Cost Std</th>
              <th class="text-end" style="width:180px;">Cost Live</th>
              <th class="text-end" style="width:150px;">Total Std</th>
              <th class="text-end" style="width:150px;">Total Live</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $i => $line): ?>
              <?php
                $sourceName = strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL'
                  ? (string)($line['material_name'] ?? '-')
                  : (string)($line['sub_component_name'] ?? '-');
              ?>
              <tr>
                <td><?php echo (int)($i + 1); ?></td>
                <td><?php echo html_escape($sourceName); ?></td>
                <td><?php echo html_escape((string)($line['source_division_name'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['qty'] ?? 0), 2, ',', '.'); ?></td>
                <td><?php echo html_escape((string)($line['uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['available_qty'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['potential_output_qty'] ?? 0), 2, ',', '.'); ?> <?php echo html_escape((string)($summary['output_uom_code'] ?? '-')); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['standard_unit_cost'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end">
                  <div class="d-inline-flex align-items-center gap-1">
                    <span><?php echo number_format((float)($line['live_unit_cost'] ?? 0), 2, ',', '.'); ?></span>
                    <span class="badge bg-light text-dark border" style="min-width:96px;" title="Sumber cost live"><?php echo html_escape((string)($line['live_cost_source_label'] ?? '-')); ?></span>
                  </div>
                </td>
                <td class="text-end"><?php echo number_format((float)($line['line_standard_total'] ?? 0), 2, ',', '.'); ?></td>
                <td class="text-end"><?php echo number_format((float)($line['line_live_total'] ?? 0), 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td colspan="9" class="text-end fw-semibold">Subtotal Direct Cost</td>
              <td class="text-end fw-semibold"><?php echo number_format($directStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format($directLive, 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="9" class="text-end fw-semibold">Biaya Variabel - <?php echo html_escape((string)($summary['variable_cost_mode'] ?? 'DEFAULT')); ?> (<?php echo number_format((float)($summary['variable_cost_percent'] ?? 0), 2, ',', '.'); ?>%)</td>
              <td class="text-end fw-semibold"><?php echo number_format($variableStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format($variableLive, 2, ',', '.'); ?></td>
            </tr>
            <tr>
              <td colspan="9" class="text-end fw-bold">Total COGS</td>
              <td class="text-end fw-bold"><?php echo number_format($totalStd, 2, ',', '.'); ?></td>
              <td class="text-end fw-bold"><?php echo number_format($totalLive, 2, ',', '.'); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-center gap-2 py-3 border-top">
        <a class="btn btn-outline-primary action-icon-btn component-action-btn" href="<?php echo site_url('production/component-formulas/edit/' . (int)($component['id'] ?? 0)); ?>" title="Edit Formula" aria-label="Edit Formula"><i class="ri ri-edit-line"></i></a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-formulas'); ?>">Kembali</a>
      </div>
    </div>
  </div>
</div>
