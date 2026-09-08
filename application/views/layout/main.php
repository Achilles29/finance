<?php
/**
 * layout/main.php — Materio layout wrapper
 * Di-load dari MY_Controller::render()
 */
$activeMenuCode = (string)($active_menu ?? '');
$isMyScope = strpos($activeMenuCode, 'my.') === 0;
$activeMenuSegments = explode('.', strtolower($activeMenuCode));
$moduleSegment = (string)($activeMenuSegments[0] ?? '');
if ($moduleSegment === 'grp' && !empty($activeMenuSegments[1])) {
  $moduleSegment = (string)$activeMenuSegments[1];
}
$moduleSegment = trim((string)preg_replace('/[^a-z0-9_-]+/', '-', $moduleSegment), '-');
$moduleSegment = $moduleSegment !== '' ? $moduleSegment : 'general';
$flashMessages = [
  'success' => $this->session->flashdata('success'),
  'error' => $this->session->flashdata('error'),
  'warning' => $this->session->flashdata('warning'),
];
$hasFlashMessages = array_filter($flashMessages, static function ($message) {
  return $message !== null && $message !== false && $message !== '';
}) !== [];
$isSuperadmin = !empty($current_user['is_superadmin']);
$canGlobalSelfOrderNotify = $isSuperadmin || !empty($user_perms['pos.self_order.index']['can_view']);
$canGlobalOnlineFoodNotify = $isSuperadmin || !empty($user_perms['pos.online_food.index']['can_view']);
$globalNotifierConfig = [
  'sound_url' => base_url('assets/sounds/notifikasi.mp3'),
  'notifiers' => [
    [
      'enabled' => $canGlobalSelfOrderNotify,
      'channel' => 'self_order',
      'poll_ms' => 12000,
      'current_path' => trim((string)uri_string(), '/'),
      'skip_paths' => ['pos/self-order/orders'],
      'endpoint' => site_url('pos/self-order/orders/data'),
      'title' => 'Self Order',
    ],
    [
      'enabled' => $canGlobalOnlineFoodNotify,
      'channel' => 'online_food',
      'poll_ms' => 12000,
      'current_path' => trim((string)uri_string(), '/'),
      'skip_paths' => ['pos/online-food/orders'],
      'endpoint' => site_url('pos/online-food/orders/data'),
      'title' => 'Online Food',
    ],
  ],
];
$this->load->view('layout/header', [
  'title' => $title ?? 'Finance',
  'business_profile' => $business_profile ?? [],
]);
?>
<!-- Layout wrapper -->
<div
  class="layout-wrapper layout-content-navbar<?php echo $isMyScope ? ' my-portal-scope' : ''; ?>"
  data-active-menu="<?= htmlspecialchars($active_menu ?? '', ENT_QUOTES, 'UTF-8') ?>"
  data-current-url="<?= htmlspecialchars(uri_string(), ENT_QUOTES, 'UTF-8') ?>"
>
  <div class="layout-container">

    <!-- Sidebar / Menu -->
    <?php $this->load->view('layout/sidebar', get_defined_vars()); ?>

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Navbar top -->
      <?php $this->load->view('layout/navbar', get_defined_vars()); ?>

      <!-- Content wrapper -->
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y finance-page-shell" data-module="<?= htmlspecialchars($moduleSegment, ENT_QUOTES, 'UTF-8') ?>" role="main">

          <!-- Flash messages -->
          <?php if ($hasFlashMessages): ?>
          <div class="finance-feedback-region" role="status" aria-live="polite" aria-atomic="false">
            <?php if ($flashMessages['success'] !== null && $flashMessages['success'] !== false && $flashMessages['success'] !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="ri ri-checkbox-circle-line me-2" aria-hidden="true"></i>
              <?= htmlspecialchars((string)$flashMessages['success'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan sukses"></button>
            </div>
            <?php endif; ?>

            <?php if ($flashMessages['error'] !== null && $flashMessages['error'] !== false && $flashMessages['error'] !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="ri ri-error-warning-line me-2" aria-hidden="true"></i>
              <?= htmlspecialchars((string)$flashMessages['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan error"></button>
            </div>
            <?php endif; ?>

            <?php if ($flashMessages['warning'] !== null && $flashMessages['warning'] !== false && $flashMessages['warning'] !== ''): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
              <i class="ri ri-alert-line me-2" aria-hidden="true"></i>
              <?= htmlspecialchars((string)$flashMessages['warning'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan peringatan"></button>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Page content -->
          <?php $this->load->view($content_view, $content_data ?? []); ?>

        </div><!-- /container-xxl -->

        <!-- Footer -->
        <footer class="content-footer footer bg-footer-theme">
          <div class="container-xxl d-flex flex-wrap justify-content-between py-2 px-4 gap-2">
            <small class="text-muted">
              &copy; <?= date('Y') ?> <span class="fw-semibold text-primary"><?= htmlspecialchars((string)($business_profile['display_name'] ?? 'Finance'), ENT_QUOTES, 'UTF-8') ?></span>
            </small>
            <small class="text-muted">v1.0.0</small>
          </div>
        </footer>

      </div><!-- /content-wrapper -->
    </div><!-- /layout-page -->
  </div><!-- /layout-container -->

  <?php if ($isMyScope): ?>
  <nav class="my-bottom-nav d-md-none" aria-label="Navigasi Portal Pegawai">
    <?php
      $myNavItems = [
        ['code' => 'my.home', 'url' => site_url('my'), 'icon' => 'ri-home-5-line', 'label' => 'Beranda'],
        ['code' => 'my.attendance', 'url' => site_url('my/attendance'), 'icon' => 'ri-fingerprint-line', 'label' => 'Absensi'],
        ['code' => 'my.leave', 'url' => site_url('my/leave-requests'), 'icon' => 'ri-hotel-bed-line', 'label' => 'Izin'],
        ['code' => 'my.bonus', 'url' => site_url('my/bonus'), 'icon' => 'ri-medal-line', 'label' => 'Bonus'],
        ['code' => 'my.profile', 'url' => site_url('my/profile'), 'icon' => 'ri-user-3-line', 'label' => 'Kontrak'],
        ['code' => 'my.payroll', 'url' => site_url('my/payroll'), 'icon' => 'ri-file-list-3-line', 'label' => 'Payroll'],
      ];
      $activeCode = (string)($active_menu ?? '');
      $currentUri = trim((string)uri_string(), '/');
      foreach ($myNavItems as $item):
        $isActive = ($activeCode === $item['code']) || (trim(parse_url($item['url'], PHP_URL_PATH), '/') === $currentUri);
    ?>
      <a href="<?= $item['url'] ?>" class="my-bottom-nav-link<?= $isActive ? ' is-active' : '' ?>">
        <i class="ri <?= $item['icon'] ?>"></i>
        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <!-- Overlay (mobile) -->
  <div class="layout-overlay layout-menu-toggle"></div>
</div><!-- /layout-wrapper -->

<?php
$this->load->view('layout/footer', [
  'global_notifier_config' => $globalNotifierConfig,
  'sidebar_favorite_csrf_token' => (string)($sidebar_favorite_csrf_token ?? ''),
]);
?>
