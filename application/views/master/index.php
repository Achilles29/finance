<?php
$baseUrl = site_url('master/' . $entity);
$queryBase = [
    'q' => $q,
    'status' => $status,
    'per_page' => $per_page,
];
$isPayrollMaster = in_array($entity, ['pay-component', 'pay-profile', 'pay-profile-line', 'pay-assignment'], true);

$buildPageItems = static function (int $page, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $items = [1];
    $start = max(2, $page - 1);
    $end = min($totalPages - 1, $page + 1);

    if ($start > 2) {
        $items[] = '...';
    }

    for ($i = $start; $i <= $end; $i++) {
        $items[] = $i;
    }

    if ($end < $totalPages - 1) {
        $items[] = '...';
    }

    $items[] = $totalPages;

    return $items;
};
?>

<?php if ($isPayrollMaster): ?>
<style>
  .master-index--payroll .master-title {
    font-size: 1.52rem;
    letter-spacing: 0.01em;
  }
  .master-index--payroll .master-filter .form-control,
  .master-index--payroll .master-filter .form-select,
  .master-index--payroll .master-filter .btn {
    min-height: 40px;
  }
  .master-index--payroll .master-card {
    border: 0;
    box-shadow: 0 0.25rem 0.9rem rgba(67, 30, 30, 0.08);
  }
  .master-index--payroll .master-table thead th {
    background: #f5f6f8 !important;
    color: #4a4458 !important;
    border-color: #ebe7ef !important;
    text-align: left !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.73rem;
  }
  .master-index--payroll .master-table th,
  .master-index--payroll .master-table td {
    padding: 0.72rem 0.88rem;
    vertical-align: middle;
  }
  .master-index--payroll .master-table td {
    font-size: 0.92rem;
  }
  .master-index--payroll .master-table td.number-cell {
    text-align: right !important;
  }
  .master-index--payroll .master-table td.action-cell {
    text-align: center !important;
  }
  .master-index--payroll .payroll-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: nowrap;
  }
  .master-index--payroll .payroll-actions .action-icon-btn {
    width: 34px;
    min-width: 34px;
    height: 34px;
    border-radius: 9px;
  }
  .master-index--payroll .master-footer {
    border-top: 1px solid #efe8ee;
    padding-top: 0.72rem;
    padding-bottom: 0.72rem;
  }
</style>
<?php endif; ?>

<div data-master-root class="master-index <?php echo $isPayrollMaster ? 'master-index--payroll' : ''; ?>">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-1 master-title"><?php echo html_escape($cfg['title']); ?></h4>
    <small class="text-muted" id="masterTotalText">Total data: <?php echo (int)$total; ?></small>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="<?php echo site_url('master/' . $entity . '/create'); ?>">
      <i class="fas fa-plus mr-1"></i>Tambah
    </a>
    <?php if ($entity === 'product'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-recipe'); ?>">Halaman Resep Produk</a>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/product-extra'); ?>">Halaman Mapping Extra</a>
    <?php endif; ?>
    <?php if ($entity === 'component'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/component-formula'); ?>">Halaman Formula Component</a>
    <?php endif; ?>
    <?php if ($entity === 'product' || $entity === 'component'): ?>
      <a class="btn btn-outline-secondary" href="<?php echo site_url('master/variable-cost-default'); ?>">Pengaturan Variable Cost</a>
    <?php endif; ?>
    <?php if ($entity === 'extra-group'): ?>
      <a class="btn btn-outline-info" href="<?php echo site_url('master/relation/extra-group'); ?>">Checklist Produk per Group</a>
    <?php endif; ?>
    <?php if ($entity === 'att-overtime-standard'): ?>
      <a class="btn btn-outline-secondary" href="<?php echo site_url('attendance/overtime-entries'); ?>">
        <i class="ri-arrow-left-line me-1"></i>Kembali ke Input Lembur
      </a>
    <?php endif; ?>
    <?php if ($entity === 'att-holiday'): ?>
      <form method="post" action="<?php echo site_url('master/att-holiday/generate-year'); ?>" class="d-flex align-items-center gap-2">
        <input
          type="number"
          name="year"
          class="form-control form-control-sm"
          min="2000"
          max="2100"
          value="<?php echo (int)date('Y'); ?>"
          style="width:96px;"
        >
        <button type="submit" class="btn btn-outline-primary btn-sm">
          <i class="ri-calendar-check-line me-1"></i>Generate 1 Tahun
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($has_active_flag)): ?>
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="btn-group" role="group" aria-label="Status filter">
      <?php
        $statusTabs = [
          'active' => 'Aktif',
          'inactive' => 'Nonaktif',
          'all' => 'Semua',
        ];
      ?>
      <?php foreach ($statusTabs as $statusKey => $statusLabel): ?>
        <?php $statusQuery = http_build_query(array_merge($queryBase, ['status' => $statusKey, 'page' => 1])); ?>
        <a
          href="<?php echo $baseUrl . '?' . $statusQuery; ?>"
          class="btn btn-sm <?php echo $status === $statusKey ? 'btn-primary' : 'btn-outline-secondary'; ?>"
          data-master-ajax="1"
        >
          <?php echo html_escape($statusLabel); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card mb-3 master-card">
  <div class="card-body py-3 master-filter">
    <form id="filterForm" method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-6 mb-2">
        <label class="form-label mb-1">Pencarian</label>
        <input type="text" name="q" class="form-control" placeholder="Cari data..." value="<?php echo html_escape($q); ?>">
      </div>
      <div class="col-md-2 mb-2">
        <label class="form-label mb-1">Per Halaman</label>
        <select class="form-control" name="per_page" id="perPageSelect">
          <?php foreach ([10,25,50,100,500] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php echo $pp == $per_page ? 'selected' : ''; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary mr-2">Filter</button>
        <a href="<?php echo $baseUrl . '?status=' . (!empty($has_active_flag) ? 'active' : 'all'); ?>" class="btn btn-outline-secondary" data-master-ajax="1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card master-card" id="masterListCard">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0 master-table">
      <thead>
        <tr>
          <th width="60">No</th>
          <?php foreach ($cfg['columns'] as $col): ?>
            <th><?php echo html_escape($col['label']); ?></th>
          <?php endforeach; ?>
          <th width="190">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="<?php echo count($cfg['columns']) + 2; ?>" class="text-center text-muted py-4">Belum ada data.</td>
          </tr>
        <?php else: ?>
          <?php $no = ($page - 1) * $per_page + 1; ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="number-cell"><?php echo $no++; ?></td>
              <?php foreach ($cfg['columns'] as $col): ?>
                <?php
                  $val = $r[$col['key']] ?? '';
                  $type = $col['type'] ?? 'text';
                  $key = strtolower((string)($col['key'] ?? ''));
                  $isNumeric = is_numeric($val);
                  $isDecimal = $isNumeric && (strpos((string)$val, '.') !== false || strpos((string)$val, ',') !== false);
                  $isTextLeft = (strpos($key, 'code') !== false) || (strpos($key, 'name') !== false);
                  $cellClasses = [];
                  if ($isNumeric) {
                    $cellClasses[] = 'number-cell';
                  }
                  if ($isTextLeft) {
                    $cellClasses[] = 'text-cell';
                  }
                ?>
                <td class="<?php echo html_escape(implode(' ', $cellClasses)); ?>">
                  <?php if ($type === 'status'): ?>
                    <?php if ((int)$val === 1): ?>
                      <span class="badge badge-success">Aktif</span>
                    <?php else: ?>
                      <span class="badge badge-secondary">Nonaktif</span>
                    <?php endif; ?>
                  <?php elseif ($type === 'bool'): ?>
                    <?php echo (int)$val === 1 ? 'Ya' : 'Tidak'; ?>
                  <?php elseif ($type === 'image'): ?>
                    <?php
                      $imgPath = trim((string)$val);
                      $imgSrc = '';
                      if ($imgPath !== '') {
                        if (preg_match('#^https?://#i', $imgPath)) {
                          $imgSrc = $imgPath;
                        } else {
                          $imgSrc = base_url(ltrim($imgPath, '/'));
                        }
                      }
                    ?>
                    <?php if ($imgSrc !== ''): ?>
                      <img src="<?php echo html_escape($imgSrc); ?>" alt="Foto" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php if ($isDecimal): ?>
                      <span data-decimal="1" data-value="<?php echo html_escape((string)$val); ?>"><?php echo html_escape((string)$val); ?></span>
                    <?php else: ?>
                      <?php echo html_escape((string)$val); ?>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td class="action-cell">
                <div class="<?php echo $isPayrollMaster ? 'payroll-actions' : ''; ?>">
                  <?php if ($entity === 'org-employee'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Detail" aria-label="Detail" href="<?php echo site_url('master/' . $entity . '/detail/' . (int)$r['id']); ?>"><i class="ri ri-eye-line"></i></a>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary action-icon-btn" data-bs-toggle="tooltip" title="Edit" aria-label="Edit" href="<?php echo site_url('master/' . $entity . '/edit/' . (int)$r['id']); ?>"><i class="ri ri-edit-line"></i></a>
                  <?php if ($entity === 'product'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Recipe" aria-label="Recipe" href="<?php echo site_url('master/relation/product-recipe/' . (int)$r['id']); ?>"><i class="ri ri-book-open-line"></i></a>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Extra" aria-label="Extra" href="<?php echo site_url('master/relation/product-extra/' . (int)$r['id']); ?>"><i class="ri ri-links-line"></i></a>
                  <?php endif; ?>
                  <?php if ($entity === 'component'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Formula" aria-label="Formula" href="<?php echo site_url('master/relation/component-formula/' . (int)$r['id']); ?>"><i class="ri ri-function-line"></i></a>
                  <?php endif; ?>
                  <?php if ($entity === 'extra-group'): ?>
                    <a class="btn btn-sm btn-outline-info action-icon-btn" data-bs-toggle="tooltip" title="Checklist Produk" aria-label="Checklist Produk" href="<?php echo site_url('master/relation/extra-group/' . (int)$r['id']); ?>"><i class="ri ri-checkbox-multiple-line"></i></a>
                  <?php endif; ?>
                  <?php if (!empty($cfg['toggle'])): ?>
                    <a class="btn btn-sm btn-outline-warning action-icon-btn" data-bs-toggle="tooltip" title="Toggle Status" aria-label="Toggle Status" href="<?php echo site_url('master/' . $entity . '/toggle/' . (int)$r['id']); ?>" onclick="return confirm('Ubah status data ini?')"><i class="ri ri-refresh-line"></i></a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 master-footer">
      <small>Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></small>
      <div class="btn-group">
        <?php
          $prev = max(1, $page - 1);
          $next = min($total_pages, $page + 1);
          $prevQuery = http_build_query(array_merge($queryBase, ['page' => $prev]));
          $nextQuery = http_build_query(array_merge($queryBase, ['page' => $next]));
          $items = $buildPageItems((int)$page, (int)$total_pages);
        ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" data-master-ajax="1" href="<?php echo $page <= 1 ? '#' : ($baseUrl . '?' . $prevQuery); ?>">&lt;</a>
        <?php foreach ($items as $item): ?>
          <?php if ($item === '...'): ?>
            <span class="btn btn-sm btn-outline-secondary disabled">...</span>
          <?php else: ?>
            <?php $itemQuery = http_build_query(array_merge($queryBase, ['page' => (int)$item])); ?>
            <a class="btn btn-sm <?php echo (int)$item === (int)$page ? 'btn-primary' : 'btn-outline-secondary'; ?>" data-master-ajax="1" href="<?php echo $baseUrl . '?' . $itemQuery; ?>"><?php echo (int)$item; ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" data-master-ajax="1" href="<?php echo $page >= $total_pages ? '#' : ($baseUrl . '?' . $nextQuery); ?>">&gt;</a>
      </div>
    </div>
  <?php endif; ?>
</div>
 </div>

<script>
(function () {
  var rootSelector = '[data-master-root]';

  function applyUiEnhancements(scope) {
    var host = scope || document;
    host.querySelectorAll('[data-decimal="1"]').forEach(function (el) {
      var raw = (el.getAttribute('data-value') || el.textContent || '').trim();
      if (!raw) return;
      var num = Number(raw.replace(/,/g, ''));
      if (!Number.isFinite(num)) return;
      el.textContent = num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      var td = el.closest('td');
      if (td) td.classList.add('number-cell');
    });

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      host.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
      });
    }
  }

  function initMasterAjax() {
    var root = document.querySelector(rootSelector);
    if (!root) return;
    applyUiEnhancements(root);

    var form = document.getElementById('filterForm');
    var input = form ? form.querySelector('input[name="q"]') : null;
    var perPage = document.getElementById('perPageSelect');

    function fetchAndSwap(url) {
      if (!url || url === '#') return;
      fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var nextRoot = doc.querySelector(rootSelector);
        if (!nextRoot) return;
        root.replaceWith(nextRoot);
        history.replaceState({}, '', url);
        initMasterAjax();
        applyUiEnhancements(nextRoot);
      });
    }

    root.querySelectorAll('a[data-master-ajax="1"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (a.classList.contains('disabled')) {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        fetchAndSwap(a.href);
      });
    });

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.set('page', '1');
        var qs = new URLSearchParams(fd).toString();
        fetchAndSwap(form.getAttribute('action') + '?' + qs);
      });
    }

    if (perPage && form) {
      perPage.addEventListener('change', function () {
        var fd = new FormData(form);
        fd.set('page', '1');
        var qs = new URLSearchParams(fd).toString();
        fetchAndSwap(form.getAttribute('action') + '?' + qs);
      });
    }

    if (input && form) {
      var t;
      input.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
          var fd = new FormData(form);
          fd.set('page', '1');
          var qs = new URLSearchParams(fd).toString();
          fetchAndSwap(form.getAttribute('action') + '?' + qs);
        }, 450);
      });
    }
  }

  initMasterAjax();
})();
</script>
