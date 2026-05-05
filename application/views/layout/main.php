<?php
/**
 * layout/main.php — Materio layout wrapper
 * Di-load dari MY_Controller::render()
 */
$activeMenuCode = (string)($active_menu ?? '');
$isPurchaseScope = strpos($activeMenuCode, 'purchase.') === 0;
$this->load->view('layout/header', ['title' => $title ?? 'Finance App']);
?>
<!-- Layout wrapper -->
<div
  class="layout-wrapper layout-content-navbar<?php echo $isPurchaseScope ? ' purchase-soft-ui' : ''; ?>"
  data-active-menu="<?= htmlspecialchars($active_menu ?? '', ENT_QUOTES, 'UTF-8') ?>"
  data-current-url="<?= htmlspecialchars(uri_string(), ENT_QUOTES, 'UTF-8') ?>"
>
  <?php if ($isPurchaseScope): ?>
  <style>
    .purchase-soft-ui .container-xxl,
    .purchase-soft-ui .container-xxl .card,
    .purchase-soft-ui .container-xxl .table,
    .purchase-soft-ui .container-xxl .form-control,
    .purchase-soft-ui .container-xxl .form-select,
    .purchase-soft-ui .container-xxl .btn,
    .purchase-soft-ui .container-xxl .badge {
      font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
      letter-spacing: 0.01em;
    }
    .purchase-soft-ui .container-xxl h1,
    .purchase-soft-ui .container-xxl h2,
    .purchase-soft-ui .container-xxl h3,
    .purchase-soft-ui .container-xxl h4,
    .purchase-soft-ui .container-xxl h5 {
      font-family: 'Merriweather', 'Plus Jakarta Sans', serif;
      letter-spacing: 0.01em;
    }
    .purchase-soft-ui .container-xxl .table td,
    .purchase-soft-ui .container-xxl .table th {
      line-height: 1.35;
    }
  </style>
  <?php endif; ?>
  <div class="layout-container">

    <!-- Sidebar / Menu -->
    <?php $this->load->view('layout/sidebar', get_defined_vars()); ?>

    <!-- Layout page -->
    <div class="layout-page">

      <!-- Navbar top -->
      <?php $this->load->view('layout/navbar', get_defined_vars()); ?>

      <!-- Content wrapper -->
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">

          <!-- Flash messages -->
          <?php if ($this->session->flashdata('success')): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri ri-checkbox-circle-line me-2"></i>
            <?= htmlspecialchars($this->session->flashdata('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <?php if ($this->session->flashdata('error')): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri ri-error-warning-line me-2"></i>
            <?= $this->session->flashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <?php if ($this->session->flashdata('warning')): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="ri ri-alert-line me-2"></i>
            <?= htmlspecialchars($this->session->flashdata('warning')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <!-- Page content -->
          <?php $this->load->view($content_view, $content_data ?? []); ?>

        </div><!-- /container-xxl -->

        <!-- Footer -->
        <footer class="content-footer footer bg-footer-theme">
          <div class="container-xxl d-flex flex-wrap justify-content-between py-2 px-4 gap-2">
            <small class="text-muted">
              &copy; <?= date('Y') ?> <span class="fw-semibold text-primary">Finance App</span>
            </small>
            <small class="text-muted">v1.0.0</small>
          </div>
        </footer>

      </div><!-- /content-wrapper -->
    </div><!-- /layout-page -->
  </div><!-- /layout-container -->

  <!-- Overlay (mobile) -->
  <div class="layout-overlay layout-menu-toggle"></div>
</div><!-- /layout-wrapper -->

<?php $this->load->view('layout/footer'); ?>
