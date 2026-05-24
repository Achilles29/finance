<?php
$detail = is_array($detail ?? null) ? $detail : [];
$component = is_array($detail['component'] ?? null) ? $detail['component'] : [];
$rows = is_array($detail['rows'] ?? null) ? $detail['rows'] : [];
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
?>
<div class="container-xxl py-3">
  <div class="fin-page-header mb-3">
    <div>
      <h4 class="fin-page-title mb-1">Dipakai Oleh: <?php echo html_escape((string)($component['component_name'] ?? '-')); ?></h4>
      <p class="fin-page-subtitle mb-0">
        Component <?php echo html_escape((string)($component['component_type'] ?? '-')); ?>
        <?php if (!empty($component['uom_name'])): ?> | UOM <?php echo html_escape((string)$component['uom_name']); ?><?php endif; ?>
        <?php if (!empty($component['component_code'])): ?> | Kode <?php echo html_escape((string)$component['component_code']); ?><?php endif; ?>
      </p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo site_url('production/component-masters'); ?>">Kembali</a>
      <a class="btn btn-outline-info btn-sm" href="<?php echo site_url('production/component-formulas/detail/' . (int)($component['id'] ?? 0)); ?>">Detail Formula</a>
    </div>
  </div>

  <?php $this->load->view('production/_component_ops_tabs', ['component_tab_active' => 'master']); ?>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted">Total Pemakai</div>
        <div class="fs-4 fw-semibold"><?php echo number_format((int)($summary['usage_count'] ?? 0), 0, ',', '.'); ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted">Base / Prepare</div>
        <div class="fs-4 fw-semibold"><?php echo number_format((int)($summary['component_usage_count'] ?? 0), 0, ',', '.'); ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted">Produk</div>
        <div class="fs-4 fw-semibold"><?php echo number_format((int)($summary['product_usage_count'] ?? 0), 0, ',', '.'); ?></div>
      </div></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:120px;">Tipe</th>
              <th style="width:140px;">Kode</th>
              <th>Nama</th>
              <th style="width:170px;">Divisi</th>
              <th class="text-end" style="width:100px;">Line</th>
              <th style="width:150px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Component ini belum dipakai pada formula base/prepare maupun recipe produk.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $isProduct = strtoupper((string)($row['usage_type'] ?? '')) === 'PRODUCT';
                  $targetUrl = $isProduct
                      ? site_url('master/relation/product-recipe/' . (int)($row['target_id'] ?? 0))
                      : site_url('production/component-formulas/detail/' . (int)($row['target_id'] ?? 0));
                ?>
                <tr>
                  <td>
                    <span class="badge <?php echo $isProduct ? 'bg-primary-subtle text-primary-emphasis' : 'bg-warning-subtle text-warning-emphasis'; ?>">
                      <?php echo $isProduct ? 'PRODUK' : html_escape((string)($row['target_kind'] ?? 'COMPONENT')); ?>
                    </span>
                  </td>
                  <td><?php echo html_escape((string)($row['target_code'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($row['target_name'] ?? '-')); ?></td>
                  <td><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></td>
                  <td class="text-end"><?php echo number_format((int)($row['usage_line_count'] ?? 0), 0, ',', '.'); ?></td>
                  <td><a class="btn btn-sm btn-outline-info" href="<?php echo $targetUrl; ?>">Lihat Resep</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>