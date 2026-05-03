<?php
$relationType = $relation_type;
$isProductRecipe = $relationType === 'product-recipe';
$isComponentFormula = $relationType === 'component-formula';
$isProductExtra = $relationType === 'product-extra';

if ($isProductRecipe) {
    $base = site_url('master/relation/product-recipe/' . (int)$parent['id']);
    $createUrl = site_url('master/relation/product-recipe/' . (int)$parent['id'] . '/create');
    $backUrl = site_url('master/product');
} elseif ($isComponentFormula) {
    $base = site_url('master/relation/component-formula/' . (int)$parent['id']);
    $createUrl = site_url('master/relation/component-formula/' . (int)$parent['id'] . '/create');
    $backUrl = site_url('master/component');
} else {
    $base = site_url('master/relation/product-extra/' . (int)$parent['id']);
    $createUrl = site_url('master/relation/product-extra/' . (int)$parent['id'] . '/create');
    $backUrl = site_url('master/product');
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
  <div>
    <h4 class="mb-1"><?php echo html_escape($title); ?></h4>
    <small class="text-muted">
      Parent: <?php echo html_escape($parent['product_name'] ?? $parent['component_name'] ?? ('#' . $parent['id'])); ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary mr-2" href="<?php echo $backUrl; ?>">Kembali Master</a>
    <a class="btn btn-primary" href="<?php echo $createUrl; ?>">Tambah Relasi</a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th width="60">No</th>
          <?php if ($isProductRecipe || $isComponentFormula): ?>
            <th>Line</th>
            <th>Type</th>
            <th>Referensi</th>
            <?php if ($isProductRecipe): ?><th>Source Divisi Operasional</th><?php endif; ?>
            <th>Qty</th>
            <th>UOM</th>
            <th>Sort</th>
            <th>Catatan</th>
          <?php else: ?>
            <th>Extra Group</th>
            <th>Sort</th>
          <?php endif; ?>
          <th width="190">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="10" class="text-center text-muted py-4">Belum ada data relasi.</td>
          </tr>
        <?php else: ?>
          <?php $no = 1; foreach ($rows as $r): ?>
            <tr>
              <td class="number-cell"><?php echo $no++; ?></td>
              <?php if ($isProductRecipe || $isComponentFormula): ?>
                <td class="number-cell"><?php echo (int)$r['line_no']; ?></td>
                <td><?php echo ($r['line_type'] ?? '') === 'MATERIAL' ? 'BAHAN BAKU' : html_escape((string)$r['line_type']); ?></td>
                <td>
                  <?php if (($r['line_type'] ?? '') === 'MATERIAL'): ?>
                    <?php echo html_escape((string)($r['item_name'] ?? '-')); ?>
                  <?php else: ?>
                    <?php echo html_escape((string)($r['component_name'] ?? '-')); ?>
                  <?php endif; ?>
                </td>
                <?php if ($isProductRecipe): ?><td><?php echo html_escape((string)($r['source_division_name'] ?? '-')); ?></td><?php endif; ?>
                <td class="number-cell"><span data-decimal="1" data-value="<?php echo html_escape((string)$r['qty']); ?>"><?php echo html_escape((string)$r['qty']); ?></span></td>
                <td><?php echo html_escape((string)($r['uom_name'] ?? '-')); ?></td>
                <td class="number-cell"><?php echo (int)$r['sort_order']; ?></td>
                <td><?php echo html_escape((string)($r['notes'] ?? '')); ?></td>
              <?php else: ?>
                <td><?php echo html_escape((string)($r['group_name'] ?? '-')); ?></td>
                <td class="number-cell"><?php echo (int)$r['sort_order']; ?></td>
              <?php endif; ?>
              <td class="action-cell">
                <?php if ($isProductRecipe): ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/relation/product-recipe/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                  <a class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus" href="<?php echo site_url('master/relation/product-recipe/delete/' . (int)$r['id']); ?>" onclick="return confirm('Hapus relasi ini?')"><i class="ri ri-delete-bin-line"></i></a>
                <?php elseif ($isComponentFormula): ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/relation/component-formula/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                  <a class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus" href="<?php echo site_url('master/relation/component-formula/delete/' . (int)$r['id']); ?>" onclick="return confirm('Hapus relasi ini?')"><i class="ri ri-delete-bin-line"></i></a>
                <?php else: ?>
                  <a class="btn btn-sm btn-outline-danger action-icon-btn" data-bs-toggle="tooltip" title="Hapus" aria-label="Hapus" href="<?php echo site_url('master/relation/product-extra/delete/' . (int)$r['id']); ?>" onclick="return confirm('Hapus mapping ini?')"><i class="ri ri-delete-bin-line"></i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
