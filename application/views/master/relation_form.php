<?php
$current = function ($name, $default = '') use ($row) {
    if (set_value($name) !== '') {
        return set_value($name);
    }
    if (!empty($row) && array_key_exists($name, $row)) {
        return $row[$name];
    }
    return $default;
};

$relationType = $relation_type;
$isProductRecipe = $relationType === 'product-recipe';
$isComponentFormula = $relationType === 'component-formula';
$isProductExtra = $relationType === 'product-extra';

if ($isProductRecipe) {
    $backUrl = site_url('master/relation/product-recipe/' . (int)$parent['id']);
} elseif ($isComponentFormula) {
    $backUrl = site_url('master/relation/component-formula/' . (int)$parent['id']);
} else {
    $backUrl = site_url('master/relation/product-extra/' . (int)$parent['id']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title); ?></h4>
  <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?php echo site_url($form_action); ?>">
      <?php if ($isProductRecipe || $isComponentFormula): ?>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Line No</label>
            <input type="number" class="form-control" name="line_no" value="<?php echo html_escape((string)$current('line_no', 1)); ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Line Type</label>
            <select class="form-control" name="line_type" id="line_type">
              <option value="MATERIAL" <?php echo $current('line_type', 'MATERIAL') === 'MATERIAL' ? 'selected' : ''; ?>>BAHAN BAKU</option>
              <option value="COMPONENT" <?php echo $current('line_type', 'MATERIAL') === 'COMPONENT' ? 'selected' : ''; ?>>COMPONENT</option>
            </select>
          </div>
          <div class="col-md-3 mb-3 type-material">
            <label class="form-label mb-1">Item Bahan Baku</label>
            <select class="form-control" name="material_item_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['materials'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('material_item_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3 type-component">
            <label class="form-label mb-1"><?php echo $isProductRecipe ? 'Component' : 'Sub Component'; ?></label>
            <select class="form-control" name="<?php echo $isProductRecipe ? 'component_id' : 'sub_component_id'; ?>">
              <option value="">- pilih -</option>
              <?php foreach (($options['components'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current($isProductRecipe ? 'component_id' : 'sub_component_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if ($isProductRecipe): ?>
            <div class="col-md-3 mb-3">
              <label class="form-label mb-1">Source Divisi Operasional</label>
              <select class="form-control" name="source_division_id">
                <option value="">- default product -</option>
                <?php foreach (($options['source_divisions'] ?? []) as $opt): ?>
                  <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('source_division_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Gunakan divisi operasional agar alur stok bahan baku konsisten.</small>
            </div>
          <?php endif; ?>

          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Qty</label>
            <input type="number" step="0.0001" class="form-control" name="qty" value="<?php echo html_escape((string)$current('qty', '0')); ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">UOM</label>
            <select class="form-control" name="uom_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['uoms'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('uom_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label mb-1">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" value="<?php echo html_escape((string)$current('sort_order', '0')); ?>">
          </div>
          <div class="col-md-12 mb-3">
            <label class="form-label mb-1">Catatan</label>
            <textarea class="form-control" rows="3" name="notes"><?php echo html_escape((string)$current('notes', '')); ?></textarea>
          </div>
        </div>
      <?php else: ?>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label mb-1">Extra Group</label>
            <select class="form-control" name="extra_group_id">
              <option value="">- pilih -</option>
              <?php foreach (($options['extra_groups'] ?? []) as $opt): ?>
                <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$current('extra_group_id', '') === (string)$opt['value'] ? 'selected' : ''; ?>>
                  <?php echo html_escape((string)$opt['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label mb-1">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" value="<?php echo html_escape((string)$current('sort_order', '0')); ?>">
          </div>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Simpan</button>
      <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Batal</a>
    </form>
  </div>
</div>

<?php if ($isProductRecipe || $isComponentFormula): ?>
<script>
(function () {
  var lineType = document.getElementById('line_type');
  var materialFields = document.querySelectorAll('.type-material');
  var componentFields = document.querySelectorAll('.type-component');
  if (!lineType) return;

  function syncType() {
    var isMaterial = lineType.value === 'MATERIAL';
    materialFields.forEach(function (el) { el.style.display = isMaterial ? '' : 'none'; });
    componentFields.forEach(function (el) { el.style.display = isMaterial ? 'none' : ''; });
  }

  lineType.addEventListener('change', syncType);
  syncType();
})();
</script>
<?php endif; ?>
