<?php
$isProductRecipe = $relation_type === 'product-recipe';
$isComponentFormula = $relation_type === 'component-formula';
$isProductExtra = $relation_type === 'product-extra';

if ($isProductRecipe) {
  $baseUrl = site_url('master/relation/product-recipe');
  $backUrl = site_url('master/product');
  $openBase = 'master/relation/product-recipe/';
} elseif ($isComponentFormula) {
  $baseUrl = site_url('master/relation/component-formula');
  $backUrl = site_url('master/component');
  $openBase = 'master/relation/component-formula/';
} else {
  $baseUrl = site_url('master/relation/product-extra');
  $backUrl = site_url('master/product');
  $openBase = 'master/relation/product-extra/';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-1"><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Pilih data induk untuk kelola relasi.</small>
  </div>
  <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Kembali ke Master</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-8 mb-2">
        <label class="form-label mb-1">Pencarian</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Cari kode / nama...">
      </div>
      <div class="col-md-4 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary">Filter</button>
        <a href="<?php echo $baseUrl; ?>" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th width="60">No</th>
          <th>Kode</th>
          <th>Nama</th>
          <th>Divisi Produk</th>
          <th>Jumlah Line</th>
          <th width="160">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-4">Belum ada data.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($rows as $r): ?>
            <tr>
              <td><?php echo $no++; ?></td>
              <td><?php echo html_escape((string)(($isProductRecipe || $isProductExtra) ? $r['product_code'] : $r['component_code'])); ?></td>
              <td><?php echo html_escape((string)(($isProductRecipe || $isProductExtra) ? $r['product_name'] : $r['component_name'])); ?></td>
              <td><?php echo html_escape((string)($r['product_division_name'] ?? '-')); ?></td>
              <td><?php echo (int)($r['total_line'] ?? 0); ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo site_url($openBase . (int)$r['id']); ?>">
                  Buka
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
