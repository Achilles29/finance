<?php
$autoCodeSource = $cfg['auto_code_source'] ?? '';
$autoCodeInput = $cfg['code_input'] ?? '';
$vcProductDefault = (float)($variable_cost_defaults['product'] ?? 20);
$vcComponentDefault = (float)($variable_cost_defaults['component'] ?? 20);
$current = function ($name, $default = '') use ($row) {
    if (set_value($name) !== '') {
        return set_value($name);
    }
    if (!empty($row) && array_key_exists($name, $row)) {
        return $row[$name];
    }
    return $default;
};

$hasFileField = false;
foreach (($cfg['fields'] ?? []) as $fieldCfg) {
  if (($fieldCfg['type'] ?? '') === 'file' && empty($fieldCfg['readonly'])) {
    $hasFileField = true;
    break;
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><?php echo html_escape($title); ?></h4>
  <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?php echo site_url($form_action); ?>" <?php echo $hasFileField ? 'enctype="multipart/form-data"' : ''; ?>>
      <div class="row">
        <?php foreach ($cfg['fields'] as $f): ?>
          <?php if (!empty($f['readonly'])) continue; ?>
          <?php
            $name = $f['name'];
            $label = $f['label'];
            $type = $f['type'] ?? 'text';
            $val = $current($name, $type === 'checkbox' ? 1 : '');
            if ($name === 'variable_cost_mode' && in_array($entity, ['product', 'component'], true) && (string)$val === '') {
                $val = 'DEFAULT';
            }
            $inputId = '';
            if ($name === $autoCodeSource || $name === $autoCodeInput) {
                $inputId = 'id_' . $name;
            }
          ?>

          <div class="col-md-6 mb-3 <?php echo in_array($type, ['textarea'], true) ? 'col-12' : ''; ?>">
            <?php if ($type !== 'checkbox'): ?>
              <label class="form-label mb-1"><?php echo html_escape($label); ?></label>
            <?php endif; ?>

            <?php if ($type === 'textarea'): ?>
              <textarea name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" rows="3"><?php echo html_escape((string)$val); ?></textarea>
            <?php elseif ($type === 'select'): ?>
              <?php $selectOptions = $options[$name] ?? ($f['options'] ?? []); ?>
              <select name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control">
                <option value="">- pilih -</option>
                <?php foreach ($selectOptions as $opt): ?>
                  <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$val === (string)$opt['value'] ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($type === 'number'): ?>
              <input
                type="number"
                name="<?php echo html_escape($name); ?>"
                id="id_<?php echo html_escape($name); ?>"
                class="form-control"
                value="<?php echo html_escape((string)$val); ?>"
                <?php echo !empty($f['step']) ? 'step="' . html_escape($f['step']) . '"' : ''; ?>
              >
            <?php elseif ($type === 'file'): ?>
              <input type="file" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" accept="image/*">
              <?php if ($name === 'photo_file'): ?>
                <?php
                  $currentPhotoPath = trim((string)($row['photo_path'] ?? ''));
                  $currentPhotoUrl = $currentPhotoPath !== '' ? base_url(ltrim($currentPhotoPath, '/')) : '';
                ?>
                <div class="mt-2" id="productPhotoPreviewWrap" style="<?php echo $currentPhotoUrl !== '' ? '' : 'display:none;'; ?>">
                  <img
                    id="productPhotoPreviewImg"
                    src="<?php echo html_escape($currentPhotoUrl); ?>"
                    alt="Preview foto produk"
                    style="max-height:120px; max-width:100%; border:1px solid #dee2e6; border-radius:8px; padding:2px;"
                  >
                </div>
                <small class="text-muted d-block mt-1">Pilih file untuk mengganti foto produk.</small>
              <?php elseif (!empty($row['photo_path'])): ?>
                <small class="text-muted d-block mt-1">File saat ini: <?php echo html_escape((string)$row['photo_path']); ?></small>
              <?php endif; ?>
            <?php elseif ($type === 'checkbox'): ?>
              <div class="form-check mt-3">
                <input type="checkbox" name="<?php echo html_escape($name); ?>" class="form-check-input" id="id_<?php echo html_escape($name); ?>" value="1" <?php echo (int)$val === 1 ? 'checked' : ''; ?>>
                <label class="form-check-label" for="id_<?php echo html_escape($name); ?>"><?php echo html_escape($label); ?></label>
              </div>
            <?php else: ?>
              <input type="text" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" value="<?php echo html_escape((string)$val); ?>">
            <?php endif; ?>

            <?php if ($name === $autoCodeInput && $autoCodeSource !== ''): ?>
              <small class="text-muted">Otomatis diisi dari inisial nama, tetap bisa diedit manual.</small>
            <?php endif; ?>

            <?php if ($name === 'variable_cost_percent' && in_array($entity, ['product', 'component'], true)): ?>
              <small class="text-muted">Mode DEFAULT: nilai otomatis dari pengaturan, CUSTOM: isi manual, NONE: disembunyikan.</small>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($entity === 'item'): ?>
        <div class="border rounded p-3 mb-3" id="newMaterialBox">
          <h6 class="mb-2">Auto-create Bahan Baku (opsional jika Item ini Bahan Baku)</h6>
          <div class="row">
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">Kode Bahan Baku Baru</label>
              <input type="text" name="new_material_code" class="form-control" value="<?php echo html_escape((string)set_value('new_material_code')); ?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">Nama Bahan Baku Baru</label>
              <input type="text" name="new_material_name" class="form-control" value="<?php echo html_escape((string)set_value('new_material_name')); ?>">
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label mb-1">HPP Bahan Baku Baru</label>
              <input type="number" step="0.0001" name="new_material_hpp" class="form-control" value="<?php echo html_escape((string)set_value('new_material_hpp')); ?>">
            </div>
          </div>
          <small class="text-muted">Isi bagian ini hanya jika Bahan Baku Existing belum dipilih.</small>
        </div>
      <?php endif; ?>

      <div class="mt-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php if ($entity === 'item'): ?>
<script>
(function () {
  var check = document.getElementById('id_is_material');
  var box = document.getElementById('newMaterialBox');
  if (!check || !box) return;
  var sync = function () {
    box.style.display = check.checked ? 'block' : 'none';
  };
  check.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>

<?php if ($autoCodeSource !== '' && $autoCodeInput !== ''): ?>
<script>
(function () {
  var nameInput = document.getElementById('id_<?php echo html_escape($autoCodeSource); ?>');
  var codeInput = document.getElementById('id_<?php echo html_escape($autoCodeInput); ?>');
  if (!nameInput || !codeInput) return;

  var userTouchedCode = codeInput.value.trim() !== '';

  function buildCode(rawName) {
    var clean = rawName
      .toUpperCase()
      .replace(/[^A-Z0-9 ]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    if (!clean) return '';

    var parts = clean.split(' ');
    var initials = '';
    for (var i = 0; i < parts.length; i++) {
      if (!parts[i]) continue;
      initials += parts[i].charAt(0);
    }

    if (initials.length < 3) {
      initials = clean.replace(/\s+/g, '').slice(0, 3);
    }

    return initials;
  }

  function syncCode() {
    if (userTouchedCode && codeInput.value.trim() !== '') return;
    codeInput.value = buildCode(nameInput.value);
  }

  nameInput.addEventListener('input', syncCode);
  codeInput.addEventListener('input', function () {
    userTouchedCode = codeInput.value.trim() !== '';
  });

  syncCode();
})();
</script>
<?php endif; ?>

<?php if (in_array($entity, ['product', 'component'], true)): ?>
<script>
(function () {
  var mode = document.getElementById('id_variable_cost_mode');
  var percent = document.getElementById('id_variable_cost_percent');
  if (!mode || !percent) return;

  var defaultPercent = <?php echo $entity === 'product' ? json_encode($vcProductDefault) : json_encode($vcComponentDefault); ?>;
  var fieldWrap = percent.closest('.col-md-6') || percent.closest('.col-12') || percent.parentElement;

  function sync() {
    var modeVal = (mode.value || '').trim().toUpperCase();
    if (modeVal === '') {
      modeVal = 'DEFAULT';
      mode.value = 'DEFAULT';
    }

    var isDefault = modeVal === 'DEFAULT';
    var isNone = modeVal === 'NONE';

    if (fieldWrap) {
      if (isNone) {
        fieldWrap.style.setProperty('display', 'none', 'important');
      } else {
        fieldWrap.style.removeProperty('display');
      }
    }

    if (isDefault) {
      percent.value = defaultPercent;
      percent.readOnly = true;
    } else if (isNone) {
      percent.value = '';
      percent.readOnly = true;
    } else {
      percent.readOnly = false;
    }
  }

  mode.addEventListener('change', sync);
  mode.addEventListener('input', sync);
  sync();
})();
</script>
<?php endif; ?>

<?php if ($entity === 'product'): ?>
<script>
(function () {
  var fileInput = document.getElementById('id_photo_file');
  var previewWrap = document.getElementById('productPhotoPreviewWrap');
  var previewImg = document.getElementById('productPhotoPreviewImg');
  if (!fileInput || !previewWrap || !previewImg) return;

  fileInput.addEventListener('change', function () {
    var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) {
      if (!previewImg.getAttribute('src')) {
        previewWrap.style.display = 'none';
      }
      return;
    }
    if (!file.type || file.type.indexOf('image/') !== 0) {
      return;
    }

    var objectUrl = URL.createObjectURL(file);
    previewImg.src = objectUrl;
    previewWrap.style.display = '';
    previewImg.onload = function () {
      URL.revokeObjectURL(objectUrl);
    };
  });
})();
</script>
<?php endif; ?>
