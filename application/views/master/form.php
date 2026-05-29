<?php
$autoCodeSource = $cfg['auto_code_source'] ?? '';
$autoCodeInput = $cfg['code_input'] ?? '';
$vcProductDefault = (float)($variable_cost_defaults['product'] ?? 20);
$vcComponentDefault = (float)($variable_cost_defaults['component'] ?? 20);
$isPayrollMaster = in_array($entity, ['pay-component', 'pay-profile', 'pay-profile-line', 'pay-assignment'], true);
$isAttLocationMaster = ($entity === 'att-location');
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

$renderReadonlyValue = static function ($value, string $type): string {
    if ($value === null || $value === '') {
        return '';
    }
    if ($type === 'checkbox') {
        return (int)$value === 1 ? '1' : '0';
    }
    return (string)$value;
};
?>

<?php if ($isPayrollMaster): ?>
<style>
  .master-form--payroll .master-form-title {
    font-size: 1.45rem;
    letter-spacing: 0.01em;
  }
  .master-form--payroll .master-form-card {
    border: 0;
    box-shadow: 0 0.25rem 0.9rem rgba(67, 30, 30, 0.08);
  }
  .master-form--payroll .form-control,
  .master-form--payroll .form-select {
    min-height: 40px;
  }
</style>
<?php endif; ?>

<?php if ($isAttLocationMaster): ?>
<link
  rel="stylesheet"
  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
  crossorigin=""
>
<style>
  .master-ajax-box {
    position: relative;
  }
  .master-ajax-result {
    position: absolute;
    z-index: 25;
    inset: calc(100% + 6px) 0 auto 0;
    background: #fff;
    border: 1px solid #e5d2c6;
    border-radius: 14px;
    box-shadow: 0 18px 34px rgba(63, 35, 24, 0.12);
    max-height: 240px;
    overflow: auto;
    display: none;
  }
  .master-ajax-result.is-open {
    display: block;
  }
  .master-ajax-item {
    padding: .75rem .9rem;
    border-bottom: 1px solid #f1e4db;
    cursor: pointer;
  }
  .master-ajax-item:last-child {
    border-bottom: 0;
  }
  .master-ajax-item:hover {
    background: #fff7f1;
  }
  .master-ajax-item-title {
    font-weight: 600;
    color: #4d352c;
  }
</style>

<style>
  .att-location-map-wrap {
    border: 1px solid #dec7c7;
    border-radius: 14px;
    background: #fff;
    padding: 12px;
  }
  .att-location-map {
    width: 100%;
    min-height: 360px;
    border: 1px solid #d9c8c8;
    border-radius: 12px;
  }
  .att-location-map-toolbar {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }
  .att-location-map-status {
    font-size: 0.9rem;
    color: #4d5966;
  }
</style>
<?php endif; ?>

<div class="master-form <?php echo $isPayrollMaster ? 'master-form--payroll' : ''; ?>">
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0 master-form-title"><?php echo html_escape($title); ?></h4>
  <div class="d-flex gap-2 flex-wrap justify-content-end">
    <?php if ($entity === 'product' && !empty($is_edit) && !empty($row['id'])): ?>
      <a href="<?php echo site_url('master/product/detail/' . (int)$row['id']); ?>" class="btn btn-outline-info">Detail Product</a>
    <?php endif; ?>
    <a href="<?php echo site_url('master/' . $entity); ?>" class="btn btn-outline-secondary">Kembali</a>
  </div>
</div>

<div class="card master-form-card">
  <div class="card-body">
    <form method="post" action="<?php echo site_url($form_action); ?>" <?php echo $hasFileField ? 'enctype="multipart/form-data"' : ''; ?>>
      <div class="row">
        <?php foreach ($cfg['fields'] as $f): ?>
          <?php
            $name = $f['name'];
            $label = $f['label'];
            $type = $f['type'] ?? 'text';
            $val = $current($name, $type === 'checkbox' ? 1 : '');
            $isReadonly = !empty($f['readonly']);
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
              <textarea name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" rows="3" <?php echo $isReadonly ? 'readonly' : ''; ?>><?php echo html_escape((string)$val); ?></textarea>
            <?php elseif ($type === 'select'): ?>
              <?php $selectOptions = $options[$name] ?? ($f['options'] ?? []); ?>
              <select name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                <option value="">- pilih -</option>
                <?php foreach ($selectOptions as $opt): ?>
                  <option value="<?php echo html_escape((string)$opt['value']); ?>" <?php echo (string)$val === (string)$opt['value'] ? 'selected' : ''; ?>>
                    <?php echo html_escape((string)$opt['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($type === 'ajax_lookup'): ?>
              <div class="master-ajax-box" data-master-ajax-field="<?php echo html_escape($name); ?>" data-search-url="<?php echo html_escape(site_url('master/lookup-search/' . $entity . '/' . $name)); ?>">
                <input type="hidden" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" value="<?php echo html_escape((string)$val); ?>">
                <input type="text" class="form-control master-ajax-input" data-display-input="<?php echo html_escape($name); ?>" placeholder="<?php echo html_escape((string)($f['placeholder'] ?? 'Cari data...')); ?>" <?php echo $isReadonly ? 'readonly' : ''; ?>>
                <div class="master-ajax-result" data-result="<?php echo html_escape($name); ?>"></div>
              </div>
            <?php elseif ($type === 'number'): ?>
              <input
                type="number"
                name="<?php echo html_escape($name); ?>"
                id="id_<?php echo html_escape($name); ?>"
                class="form-control"
                value="<?php echo html_escape((string)$val); ?>"
                <?php echo !empty($f['step']) ? 'step="' . html_escape($f['step']) . '"' : ''; ?>
                <?php echo ($isAttLocationMaster && in_array($name, ['latitude', 'longitude'], true)) ? 'data-map-coordinate="1"' : ''; ?>
                <?php echo $isReadonly ? 'readonly' : ''; ?>
              >
            <?php elseif ($type === 'file'): ?>
              <?php if (!$isReadonly): ?>
              <input type="file" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" accept="image/*">
              <?php endif; ?>
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
                <input type="checkbox" name="<?php echo html_escape($name); ?>" class="form-check-input" id="id_<?php echo html_escape($name); ?>" value="1" <?php echo (int)$val === 1 ? 'checked' : ''; ?> <?php echo $isReadonly ? 'disabled' : ''; ?>>
                <label class="form-check-label" for="id_<?php echo html_escape($name); ?>"><?php echo html_escape($label); ?></label>
              </div>
            <?php else: ?>
              <input type="text" name="<?php echo html_escape($name); ?>" id="id_<?php echo html_escape($name); ?>" class="form-control" value="<?php echo html_escape((string)$val); ?>" <?php echo $isReadonly ? 'readonly' : ''; ?>>
            <?php endif; ?>

            <?php if ($isReadonly && $type === 'select'): ?>
              <input type="hidden" value="<?php echo html_escape($renderReadonlyValue($val, $type)); ?>">
            <?php endif; ?>

            <?php if ($name === $autoCodeInput && $autoCodeSource !== ''): ?>
              <small class="text-muted">Otomatis diisi dari inisial nama, tetap bisa diedit manual.</small>
            <?php endif; ?>

            <?php if ($isReadonly): ?>
              <small class="text-muted d-block mt-1">Field ini hanya tampil sebagai informasi dari database.</small>
            <?php endif; ?>

            <?php if ($entity === 'org-employee' && $name === 'employee_code'): ?>
              <small class="text-muted">Kode pegawai dibuat otomatis saat simpan (skema internal EMP-YYYYMM####).</small>
            <?php endif; ?>

            <?php if ($entity === 'org-employee' && $name === 'employee_nip'): ?>
              <small class="text-muted">NIP otomatis: tanggal gabung (YYYYMMDD) + tanggal lahir (YYYYMMDD) + urutan 3 digit.</small>
            <?php endif; ?>

            <?php if ($name === 'variable_cost_percent' && in_array($entity, ['product', 'component'], true)): ?>
              <small class="text-muted">Mode DEFAULT: nilai otomatis dari pengaturan, CUSTOM: isi manual, NONE: disembunyikan.</small>
            <?php endif; ?>

            <?php if ($isAttLocationMaster && in_array($name, ['latitude', 'longitude'], true)): ?>
              <small class="text-muted">Koordinat dipilih dari peta (drag pin / klik peta).</small>
            <?php endif; ?>
          </div>

          <?php if ($isAttLocationMaster && $name === 'longitude'): ?>
            <div class="col-12 mb-3">
              <label class="form-label mb-2">Pilih Titik Lokasi</label>
              <div class="att-location-map-wrap">
                <div class="att-location-map-toolbar">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="attLocationUseCurrent">Gunakan Lokasi Saat Ini</button>
                  <div id="attLocationMapStatus" class="att-location-map-status">Geser pin atau klik peta untuk mengisi koordinat.</div>
                </div>
                <div id="attLocationMap" class="att-location-map"></div>
              </div>
            </div>
          <?php endif; ?>
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

<script>
(function () {
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-master-ajax-field]'));
  if (!boxes.length) return;

  function escapeHtml(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m];
    });
  }

  function closeAll() {
    boxes.forEach(function (box) {
      var result = box.querySelector('.master-ajax-result');
      if (result) {
        result.classList.remove('is-open');
        result.innerHTML = '';
      }
    });
  }

  function setSelected(box, row) {
    var hidden = box.querySelector('input[type="hidden"]');
    var display = box.querySelector('.master-ajax-input');
    if (hidden) hidden.value = String(row.value || '');
    if (display) display.value = String(row.label || '');
    closeAll();
  }

  function bindBox(box) {
    var hidden = box.querySelector('input[type="hidden"]');
    var display = box.querySelector('.master-ajax-input');
    var result = box.querySelector('.master-ajax-result');
    var searchUrl = box.getAttribute('data-search-url') || '';
    var timer = null;
    if (!hidden || !display || !result || !searchUrl) return;

    if (hidden.value) {
      fetch(searchUrl + '?id=' + encodeURIComponent(hidden.value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (t) {
          var json = JSON.parse(t);
          var rows = Array.isArray(json.rows) ? json.rows : [];
          if (rows.length) {
            display.value = String(rows[0].label || '');
          }
        })
        .catch(function () {});
    }

    display.addEventListener('input', function () {
      hidden.value = '';
      var q = display.value.trim();
      if (timer) window.clearTimeout(timer);
      if (q.length < 2) {
        result.classList.remove('is-open');
        result.innerHTML = '';
        return;
      }
      timer = window.setTimeout(function () {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.text(); })
          .then(function (t) {
            var json = JSON.parse(t);
            var rows = Array.isArray(json.rows) ? json.rows : [];
            if (!rows.length) {
              result.innerHTML = '<div class="master-ajax-item"><div class="master-ajax-item-title">Tidak ada hasil.</div></div>';
              result.classList.add('is-open');
              return;
            }
            result.innerHTML = rows.map(function (row) {
              return '<div class="master-ajax-item" data-value="' + escapeHtml(row.value) + '" data-label="' + escapeHtml(row.label) + '"><div class="master-ajax-item-title">' + escapeHtml(row.label) + '</div></div>';
            }).join('');
            result.classList.add('is-open');
            Array.prototype.forEach.call(result.querySelectorAll('.master-ajax-item[data-value]'), function (item) {
              item.addEventListener('click', function () {
                setSelected(box, { value: item.getAttribute('data-value'), label: item.getAttribute('data-label') });
              });
            });
          })
          .catch(function () {
            result.innerHTML = '<div class="master-ajax-item"><div class="master-ajax-item-title">Gagal memuat hasil pencarian.</div></div>';
            result.classList.add('is-open');
          });
      }, 280);
    });

    display.addEventListener('focus', function () {
      if (result.innerHTML.trim() !== '') {
        result.classList.add('is-open');
      }
    });
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-master-ajax-field]')) {
      closeAll();
    }
  });

  boxes.forEach(bindBox);
})();
</script>

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

<?php if ($isAttLocationMaster): ?>
<script
  src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
  crossorigin=""
></script>
<script>
(function () {
  var latInput = document.getElementById('id_latitude');
  var lngInput = document.getElementById('id_longitude');
  var mapEl = document.getElementById('attLocationMap');
  var statusEl = document.getElementById('attLocationMapStatus');
  var useCurrentBtn = document.getElementById('attLocationUseCurrent');
  if (!latInput || !lngInput || !mapEl) return;

  function setStatus(message) {
    if (statusEl) statusEl.textContent = message;
  }

  function parseCoordinate(value) {
    var parsed = parseFloat(String(value || '').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : null;
  }

  var lat = parseCoordinate(latInput.value);
  var lng = parseCoordinate(lngInput.value);
  var hasInitial = lat !== null && lng !== null;
  var defaultPoint = hasInitial ? [lat, lng] : [-6.2000000, 106.8166667];

  if (typeof L === 'undefined') {
    setStatus('Peta gagal dimuat. Isi koordinat manual untuk sementara.');
    latInput.readOnly = false;
    lngInput.readOnly = false;
    return;
  }

  latInput.readOnly = true;
  lngInput.readOnly = true;

  var map = L.map(mapEl, {
    center: defaultPoint,
    zoom: hasInitial ? 16 : 12,
    zoomControl: true
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var marker = L.marker(defaultPoint, { draggable: true }).addTo(map);

  function syncInputs(latlng, message) {
    latInput.value = Number(latlng.lat).toFixed(7);
    lngInput.value = Number(latlng.lng).toFixed(7);
    if (message) setStatus(message);
  }

  function updatePoint(latlng, message) {
    marker.setLatLng(latlng);
    map.panTo(latlng);
    syncInputs(latlng, message);
  }

  marker.on('dragend', function (event) {
    updatePoint(event.target.getLatLng(), 'Koordinat diperbarui dari drag pin.');
  });

  map.on('click', function (event) {
    updatePoint(event.latlng, 'Koordinat diperbarui dari klik peta.');
  });

  if (useCurrentBtn) {
    useCurrentBtn.addEventListener('click', function () {
      if (!navigator.geolocation) {
        setStatus('Browser tidak mendukung geolokasi.');
        return;
      }
      useCurrentBtn.disabled = true;
      setStatus('Mengambil lokasi saat ini...');
      navigator.geolocation.getCurrentPosition(function (position) {
        var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
        map.setZoom(18);
        updatePoint(latlng, 'Lokasi saat ini berhasil dipakai.');
        useCurrentBtn.disabled = false;
      }, function () {
        setStatus('Lokasi saat ini gagal diambil. Cek izin GPS browser.');
        useCurrentBtn.disabled = false;
      }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
      });
    });
  }

  function tryUseCurrentLocationOnOpen() {
    if (!navigator.geolocation) return;
    if (hasInitial) return;
    setStatus('Mendeteksi lokasi saat ini...');
    navigator.geolocation.getCurrentPosition(function (position) {
      var latlng = L.latLng(position.coords.latitude, position.coords.longitude);
      map.setZoom(18);
      updatePoint(latlng, 'Lokasi saat ini dipakai otomatis.');
    }, function () {
      setStatus('Lokasi saat ini tidak tersedia, gunakan drag pin / klik peta.');
    }, {
      enableHighAccuracy: true,
      timeout: 8000,
      maximumAge: 0
    });
  }

  syncInputs(marker.getLatLng(), hasInitial ? 'Koordinat awal dimuat dari data existing.' : 'Set titik lokasi untuk mengisi koordinat.');
  setTimeout(function () {
    map.invalidateSize();
    tryUseCurrentLocationOnOpen();
  }, 180);
})();
</script>
<?php endif; ?>
