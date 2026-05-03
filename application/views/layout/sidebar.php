<?php
/**
 * layout/sidebar.php — Materio sidebar, two-portal system
 *
 * Portal detection (auto dari active_menu):
 *   - active_menu starts with 'my.' → Employee Portal (MY menus)
 *   - otherwise                     → Company Portal  (MAIN menus)
 *
 * Variables diterima (dari MY_Controller::render via main.php):
 *   $sidebar_main, $sidebar_my, $sidebar_favorites, $active_menu, $current_user
 */

$active_menu  = $active_menu ?? '';
$current_user = $current_user ?? [];

// Deteksi portal mode
$is_employee_portal = (strpos($active_menu, 'my.') === 0);

// ---------------------------------------------------------------
// RI icon mapping — menu_code → Remix Icon class
// ---------------------------------------------------------------
if (!function_exists('_get_ri_icon')) {
    function _get_ri_icon(string $code, string $fallback = 'ri-circle-line'): string
    {
        static $map = [
            'dashboard'             => 'ri-home-smile-line',
            'grp.dashboard'         => 'ri-home-smile-line',
            'grp.system'            => 'ri-settings-3-line',
            'grp.sistema'           => 'ri-settings-3-line',
            'sys.users'             => 'ri-user-settings-line',
            'sys.roles'             => 'ri-shield-keyhole-line',
            'sys.access_control'    => 'ri-key-2-line',
            'sys.log'               => 'ri-file-shield-2-line',
            'grp.hr'                => 'ri-group-line',
            'hr.employees'          => 'ri-id-card-line',
            'hr.departments'        => 'ri-building-4-line',
            'hr.positions'          => 'ri-briefcase-4-line',
            'grp.attendance'        => 'ri-calendar-check-line',
            'att.daily'             => 'ri-calendar-todo-line',
            'att.report'            => 'ri-calendar-2-line',
            'att.overtime'          => 'ri-timer-flash-line',
            'grp.payroll'           => 'ri-money-dollar-circle-line',
            'pay.salary'            => 'ri-money-cny-circle-line',
            'pay.payslip'           => 'ri-file-list-3-line',
            'pay.bonus'             => 'ri-gift-line',
            'pay.deduction'         => 'ri-subtract-line',
            'grp.inventory'         => 'ri-archive-2-line',
            'inv.items'             => 'ri-box-3-line',
            'inv.stock'             => 'ri-stack-line',
            'inv.purchase'          => 'ri-shopping-cart-line',
            'inv.warehouse'         => 'ri-store-3-line',
            'grp.pos'               => 'ri-store-2-line',
            'pos.cashier'           => 'ri-shopping-bag-3-line',
            'pos.orders'            => 'ri-receipt-line',
            'pos.menu'              => 'ri-restaurant-line',
            'grp.material'          => 'ri-flask-line',
            'grp.production'        => 'ri-tools-line',
            'grp.purchase'          => 'ri-shopping-cart-2-line',
            'grp.finance'           => 'ri-bank-line',
            'fin.transactions'      => 'ri-exchange-line',
            'fin.income'            => 'ri-arrow-down-circle-line',
            'fin.expense'           => 'ri-arrow-up-circle-line',
            'fin.accounts'          => 'ri-bank-card-line',
            'grp.reports'           => 'ri-bar-chart-2-line',
            'rpt.daily'             => 'ri-file-chart-line',
            'rpt.monthly'           => 'ri-file-chart-2-line',
            'rpt.financial'         => 'ri-pie-chart-2-line',
            'grp.master'            => 'ri-database-2-line',
            'my.home'               => 'ri-home-3-line',
            'my.profile'            => 'ri-user-3-line',
            'my.schedule'           => 'ri-calendar-2-line',
            'my.attendance'         => 'ri-time-line',
            'my.payroll'            => 'ri-file-list-3-line',
            'my.leave'              => 'ri-hotel-bed-line',
            'my.meal'               => 'ri-restaurant-line',
            'my.cash_advance'       => 'ri-hand-coin-line',
        ];
        return $map[$code] ?? $fallback;
    }
}

// ---------------------------------------------------------------
// Resolve RI icon dari item menu (abaikan FA icon dari DB)
// ---------------------------------------------------------------
if (!function_exists('_resolve_menu_icon')) {
    function _resolve_menu_icon(array $item): string
    {
        $db_icon = trim($item['icon'] ?? '');
        // Hanya pakai DB icon jika formatnya RI (mulai dengan 'ri-')
        if (strpos($db_icon, 'ri-') === 0) {
            return $db_icon;
        }
        return _get_ri_icon($item['menu_code'] ?? '');
    }
}

// ---------------------------------------------------------------
// Cek apakah ada child yang active (untuk membuka parent)
// ---------------------------------------------------------------
if (!function_exists('_has_active_child_sidebar')) {
  function _has_active_child_sidebar(array $children, string $active, string $current_uri = ''): bool
    {
        foreach ($children as $child) {
      if ((_is_sidebar_item_active($child, $active, $current_uri))) return true;
      if (!empty($child['children']) && _has_active_child_sidebar($child['children'], $active, $current_uri)) return true;
        }
        return false;
    }
}

if (!function_exists('_normalize_sidebar_uri')) {
  function _normalize_sidebar_uri(string $uri): string
  {
    if ($uri === '') {
      return '';
    }

    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? $uri);
    return trim($path, '/');
  }
}

if (!function_exists('_is_sidebar_item_active')) {
  function _is_sidebar_item_active(array $item, string $active_menu, string $current_uri = ''): bool
  {
    $menuCode = (string)($item['menu_code'] ?? '');
    if ($active_menu !== '') {
      if ($menuCode !== $active_menu) {
        return false;
      }
      return _menu_has_real_link($item);
    }

    $menu_url = _normalize_sidebar_uri((string)($item['url'] ?? ''));
    $current = _normalize_sidebar_uri($current_uri);

    if ($menu_url === '' || $current === '') {
      return false;
    }

    return $current === $menu_url;
  }
}

if (!function_exists('_find_first_matching_menu_code')) {
  function _find_first_matching_menu_code(array $items, string $current_uri): string
  {
    $current = _normalize_sidebar_uri($current_uri);
    if ($current === '') {
      return '';
    }

    foreach ($items as $item) {
      if (_menu_has_real_link($item)) {
        $menuUrl = _normalize_sidebar_uri((string)($item['url'] ?? ''));
        if ($menuUrl !== '' && $menuUrl === $current) {
          return (string)($item['menu_code'] ?? '');
        }
      }

      $children = (array)($item['children'] ?? []);
      if (!empty($children)) {
        $found = _find_first_matching_menu_code($children, $current_uri);
        if ($found !== '') {
          return $found;
        }
      }
    }

    return '';
  }
}

if (!function_exists('_menu_has_real_link')) {
  function _menu_has_real_link(array $item): bool
  {
    $url = trim((string)($item['url'] ?? ''));
    if ($url === '' || $url === '#' || strtolower($url) === 'javascript:void(0);' || strtolower($url) === 'javascript:void(0)') {
      return false;
    }
    return true;
  }
}

if (!function_exists('_regroup_master_children')) {
  function _regroup_master_children(array $children): array
  {
    $buckets = [
      'product' => [
        'label' => 'Produk & Extra',
        'icon' => 'ri-store-2-line',
        'match' => [
          'master.product.division', 'master.product.classification', 'master.product.category',
          'master.product_division', 'master.product_classification', 'master.product_category',
          'master.product', 'master.extra', 'master.extra_group', 'master.product.extra',
          'master.product_extra_map', 'master.extra.group', 'master.extra-group',
        ],
      ],
      'inventory' => [
        'label' => 'Item & Bahan',
        'icon' => 'ri-flask-line',
        'match' => [
          'master.uom', 'master.operational_division', 'master.item_category',
          'master.material', 'master.item', 'master.component_category', 'master.component', 'master.vendor',
        ],
      ],
      'relation' => [
        'label' => 'Relasi & Formula',
        'icon' => 'ri-links-line',
        'match' => [
          'master.product.recipe', 'master.component.formula', 'master.product_recipe',
          'master.component_formula', 'master.relation',
        ],
      ],
      'config' => [
        'label' => 'Konfigurasi',
        'icon' => 'ri-settings-3-line',
        'match' => [
          'master.variable.cost.default', 'master.variable_cost_default',
        ],
      ],
    ];

    $grouped = [
      'product' => [],
      'inventory' => [],
      'relation' => [],
      'config' => [],
      'other' => [],
    ];

    foreach ($children as $child) {
      $code = (string)($child['menu_code'] ?? '');
      $placed = false;
      foreach ($buckets as $bucketKey => $bucket) {
        foreach ($bucket['match'] as $needle) {
          if (strpos($code, $needle) === 0) {
            $grouped[$bucketKey][] = $child;
            $placed = true;
            break 2;
          }
        }
      }
      if (!$placed) {
        $grouped['other'][] = $child;
      }
    }

    $result = [];
    $order = 1;
    foreach (['product', 'inventory', 'relation', 'config'] as $bucketKey) {
      if (empty($grouped[$bucketKey])) {
        continue;
      }
      $result[] = [
        'id' => -1000 - $order,
        'parent_id' => null,
        'menu_code' => 'master.group.' . $bucketKey,
        'menu_label' => $buckets[$bucketKey]['label'],
        'icon' => $buckets[$bucketKey]['icon'],
        'url' => null,
        'page_id' => null,
        'sort_order' => $order,
        'children' => $grouped[$bucketKey],
      ];
      $order++;
    }

    foreach ($grouped['other'] as $other) {
      $result[] = $other;
    }

    return $result;
  }
}

if (!function_exists('_regroup_master_sidebar_tree')) {
  function _regroup_master_sidebar_tree(array $tree): array
  {
    foreach ($tree as &$item) {
      if (($item['menu_code'] ?? '') === 'grp.master' && !empty($item['children'])) {
        $item['children'] = _regroup_master_children($item['children']);
      }
    }
    unset($item);
    return $tree;
  }
}

// ---------------------------------------------------------------
// Render rekursif tree menu (Materio style)
// ---------------------------------------------------------------
if (!function_exists('render_menu_tree')) {
  function render_menu_tree(array $items, string $active_menu, string $current_uri = '', int $depth = 0, array $fav_map = []): void
    {
        foreach ($items as $item) {
            $has_children = !empty($item['children']);
      $is_active    = _is_sidebar_item_active($item, $active_menu, $current_uri);
      $child_active = $has_children && _has_active_child_sidebar($item['children'], $active_menu, $current_uri);
            $icon_class   = _resolve_menu_icon($item);
            $label        = htmlspecialchars($item['menu_label'] ?? '');
            $menu_id      = (int)($item['id'] ?? 0);
            $is_fav       = ($menu_id > 0) && !empty($fav_map[$menu_id]);

            if ($has_children) {
                // Menu group dengan sub-items
                $open_class = $child_active ? 'open active current-path' : (($is_active) ? 'open active' : '');
                ?>
                <li class="menu-item <?= $open_class ?>">
                  <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ri <?= $icon_class ?>"></i>
                    <div class="flex-grow-1"><?= $label ?></div>
                    <?php if (_menu_has_real_link($item) && $menu_id > 0): ?>
                    <button type="button"
                            class="btn p-0 border-0 bg-transparent sidebar-pin-toggle <?= $is_fav ? 'is-pinned' : '' ?>"
                            data-menu-id="<?= $menu_id ?>"
                            data-pinned="<?= $is_fav ? '1' : '0' ?>"
                            title="<?= $is_fav ? 'Hapus dari favorit' : 'Tambah ke favorit' ?>"
                            aria-label="<?= $is_fav ? 'Hapus dari favorit' : 'Tambah ke favorit' ?>">
                      <i class="ri <?= $is_fav ? 'ri-star-fill' : 'ri-star-line' ?>"></i>
                    </button>
                    <?php endif; ?>
                  </a>
                  <ul class="menu-sub">
                    <?php render_menu_tree($item['children'], $active_menu, $current_uri, $depth + 1, $fav_map); ?>
                  </ul>
                </li>
                <?php
            } elseif (_menu_has_real_link($item)) {
                // Link langsung
                $active_class = $is_active ? 'active' : '';
                ?>
                <li class="menu-item <?= $active_class ?>">
                  <a href="<?= base_url(ltrim($item['url'], '/')) ?>" class="menu-link">
                    <i class="menu-icon tf-icons ri <?= $icon_class ?>"></i>
                    <div class="flex-grow-1"><?= $label ?></div>
                    <?php if ($menu_id > 0): ?>
                    <button type="button"
                            class="btn p-0 border-0 bg-transparent sidebar-pin-toggle <?= $is_fav ? 'is-pinned' : '' ?>"
                            data-menu-id="<?= $menu_id ?>"
                            data-pinned="<?= $is_fav ? '1' : '0' ?>"
                            title="<?= $is_fav ? 'Hapus dari favorit' : 'Tambah ke favorit' ?>"
                            aria-label="<?= $is_fav ? 'Hapus dari favorit' : 'Tambah ke favorit' ?>">
                      <i class="ri <?= $is_fav ? 'ri-star-fill' : 'ri-star-line' ?>"></i>
                    </button>
                    <?php endif; ?>
                  </a>
                </li>
                <?php
            }
            // Orphan group headers (url=NULL, no children) → skip / jangan tampilkan
        }
    }
}
?>

<?php
// Persiapan data user untuk sidebar
$sb_username = $current_user['username'] ?? 'User';
$sb_initials = mb_strtoupper(mb_substr($sb_username, 0, 2));
$sb_role     = !empty($current_user['is_superadmin']) ? 'Super Admin' : 'Staff';
$sb_current_uri = (string)uri_string();

$sb_fav_map = [];
if (!empty($sidebar_favorites) && is_array($sidebar_favorites)) {
  foreach ($sidebar_favorites as $fav_row) {
    $fav_menu_id = (int)($fav_row['menu_id'] ?? 0);
    if ($fav_menu_id > 0) {
      $sb_fav_map[$fav_menu_id] = true;
    }
  }
}

if (!$is_employee_portal && !empty($sidebar_main)) {
  $sidebar_main = _regroup_master_sidebar_tree((array)$sidebar_main);
}

$active_source_tree = $is_employee_portal ? (array)($sidebar_my ?? []) : (array)($sidebar_main ?? []);
$resolved_active_code = _find_first_matching_menu_code($active_source_tree, $sb_current_uri);
if ($resolved_active_code !== '' && ($active_menu === '' || strpos($active_menu, 'grp.') === 0 || strpos($active_menu, 'master.group.') === 0)) {
  $active_menu = $resolved_active_code;
}
?>
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

  <!-- App Brand / Logo -->
  <div class="app-brand demo">
    <a href="<?= $is_employee_portal ? base_url('my') : base_url('dashboard') ?>" class="app-brand-link">
      <span class="app-brand-logo-wrap">
        <img src="<?= base_url('assets/img/logo.png') ?>"
             alt="Finance" width="34" height="34"
             style="object-fit:contain;display:block;"
             onerror="this.style.display='none';this.nextSibling.style.display='flex';">
        <span style="display:none;font-size:18px;font-weight:800;color:#c0392b;">F</span>
      </span>
      <span>
        <span class="sb-brand-title"><?= $is_employee_portal ? 'PEGAWAI' : 'FINANCE' ?></span>
        <span class="sb-brand-sub"><?= $is_employee_portal ? 'Portal Karyawan' : 'Finance Workspace' ?></span>
      </span>
    </a>
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-flex">
      <i class="ri ri-close-line d-xl-none"></i>
      <i class="ri ri-radio-button-line d-none d-xl-inline-flex"></i>
    </a>
  </div>

  <!-- User mini-card -->
  <div class="sb-user-card">
    <div class="sb-user-avatar"><?= htmlspecialchars($sb_initials) ?></div>
    <div style="min-width:0;">
      <div class="sb-user-name"><?= htmlspecialchars(mb_strtoupper($sb_username)) ?></div>
      <div class="sb-user-role"><?= htmlspecialchars($sb_role) ?></div>
    </div>
  </div>

  <div class="menu-inner-shadow"></div>

  <!-- ============================================================ -->
  <!-- Scroll area                                                   -->
  <!-- ============================================================ -->
  <ul class="menu-inner py-1">

    <!-- ======================================================== -->
    <!-- BRIDGE PORTAL — Selalu di atas, sebelum menu items        -->
    <!-- ======================================================== -->
    <li style="padding: 0.25rem 0.75rem 0.5rem;">
      <?php if (!$is_employee_portal): ?>
      <a href="<?= base_url('my') ?>"
         class="sb-portal-bridge"
         title="Buka Portal Pegawai">
        <span class="sb-portal-bridge-icon">
          <i class="ri ri-user-heart-line"></i>
        </span>
        <span class="sb-portal-bridge-body">
          <span class="sb-portal-bridge-title">Portal Pegawai</span>
          <span class="sb-portal-bridge-sub">Absensi &amp; data diri</span>
        </span>
        <i class="ri ri-arrow-right-s-line sb-portal-bridge-arrow"></i>
      </a>
      <?php else: ?>
      <a href="<?= base_url('dashboard') ?>"
         class="sb-portal-bridge"
         title="Kembali ke Manajemen">
        <span class="sb-portal-bridge-icon">
          <i class="ri ri-building-2-line"></i>
        </span>
        <span class="sb-portal-bridge-body">
          <span class="sb-portal-bridge-title">Manajemen Finance</span>
          <span class="sb-portal-bridge-sub">Kembali ke dashboard</span>
        </span>
        <i class="ri ri-arrow-right-s-line sb-portal-bridge-arrow"></i>
      </a>
      <?php endif; ?>
    </li>

    <!-- Divider after bridge -->
    <li><hr style="border-color:rgba(255,255,255,0.1);margin:0 0.75rem 0.5rem;"></li>

    <?php if (!$is_employee_portal): ?>
    <!-- ========================================================== -->
    <!-- COMPANY PORTAL — Menu utama manajemen                       -->
    <!-- ========================================================== -->

      <?php if (!empty($sidebar_favorites)): ?>
      <li class="menu-header small text-uppercase">
        <span class="menu-header-text">
          <i class="ri ri-star-line me-1" style="color:#e0aa45;font-size:.75rem;"></i> Favorit
        </span>
      </li>
      <?php foreach ($sidebar_favorites as $fav):
        if (!_menu_has_real_link($fav)) continue;
        $fav_icon = _resolve_menu_icon($fav); ?>
      <li class="menu-item" data-fav-id="<?= $fav['menu_id'] ?>">
        <a href="<?= base_url(ltrim($fav['url'] ?? '#', '/')) ?>" class="menu-link d-flex align-items-center">
          <i class="menu-icon tf-icons ri <?= $fav_icon ?>"></i>
          <div class="flex-grow-1"><?= htmlspecialchars($fav['menu_label'] ?? '') ?></div>
          <button type="button"
                  class="btn p-0 border-0 bg-transparent sidebar-pin-toggle is-pinned"
                  data-menu-id="<?= (int)$fav['menu_id'] ?>"
                  data-pinned="1"
                  title="Hapus dari favorit"
                  aria-label="Hapus dari favorit">
            <i class="ri ri-star-fill"></i>
          </button>
        </a>
      </li>
      <?php endforeach; ?>
      <li><hr class="menu-divider my-1 opacity-25"></li>
      <?php endif; ?>

      <!-- Main company menus -->
      <?php render_menu_tree($sidebar_main ?? [], $active_menu, $sb_current_uri, 0, $sb_fav_map); ?>

    <?php else: ?>
    <!-- ========================================================== -->
    <!-- EMPLOYEE PORTAL — Data pribadi pegawai                      -->
    <!-- ========================================================== -->

      <li class="menu-header small text-uppercase">
        <span class="menu-header-text">
          <i class="ri ri-user-line me-1" style="font-size:.75rem;"></i> Data Saya
        </span>
      </li>

      <?php render_menu_tree($sidebar_my ?? [], $active_menu, $sb_current_uri, 0, $sb_fav_map); ?>

    <?php endif; ?>

  </ul>
</aside>

<style>
  #layout-menu .menu-link > div.flex-grow-1 {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.25;
    word-break: break-word;
  }
  #layout-menu .menu-link {
    align-items: flex-start;
  }
</style>
