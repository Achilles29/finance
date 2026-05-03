<!doctype html>
<html lang="id" class="layout-wide">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
<title>Login — Finance App</title>
<link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Remix Icons -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/fonts/iconify-icons.css') ?>">
<!-- Materio Core CSS -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/libs/node-waves/node-waves.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/css/pages/page-auth.css') ?>">
<!-- Finance Theme -->
<link rel="stylesheet" href="<?= base_url('assets/css/theme-custom.css') ?>">
<!-- Materio Helpers -->
<script src="<?= base_url('assets/vendor/js/helpers.js') ?>"></script>
<script src="<?= base_url('assets/js/config.js') ?>"></script>
</head>
<body>

<div class="position-relative">
  <div class="authentication-wrapper authentication-basic container-p-y">
    <div class="authentication-inner py-6 mx-4">

      <div class="card p-sm-7 p-2">

        <!-- Logo -->
        <div class="app-brand justify-content-center mt-4 mb-4">
          <a href="javascript:void(0);" class="app-brand-link gap-3">
            <img src="<?= base_url('assets/img/logo.png') ?>"
                 onerror="this.src='<?= base_url('assets/img/logo.jpeg') ?>'"
                 alt="Finance App" height="40" style="object-fit:contain;">
            <span class="app-brand-text fw-bold" style="font-size:1.4rem; color:#c0392b;">Finance</span>
          </a>
        </div>

        <h4 class="mb-1 fw-semibold text-center" style="color:#34282c;">Selamat Datang!</h4>
        <p class="mb-5 text-center text-muted small">Masuk ke sistem manajemen kafe</p>

        <!-- Error alert -->
        <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
          <i class="ri ri-error-warning-line ri-20px me-2 flex-shrink-0"></i>
          <div><?= htmlspecialchars($error_msg) ?></div>
        </div>
        <?php endif; ?>

        <!-- Form login -->
        <?= form_open('auth/do_login', ['autocomplete' => 'off']) ?>

          <div class="mb-4">
            <label class="form-label" for="identifier">Username atau Email</label>
            <input type="text" class="form-control" id="identifier" name="identifier"
                   placeholder="Masukkan username atau email"
                   value="<?= set_value('identifier') ?>"
                   maxlength="150" required autofocus>
          </div>

          <div class="mb-5 form-password-toggle">
            <div class="d-flex justify-content-between mb-1">
              <label class="form-label" for="password">Password</label>
            </div>
            <div class="input-group input-group-merge">
              <input type="password" id="password" class="form-control form-control-merge"
                     name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                     maxlength="72" required>
              <span class="input-group-text cursor-pointer" id="toggle-pass">
                <i class="ri ri-eye-off-line ri-20px" id="eye-icon"></i>
              </span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary d-grid w-100 py-2 fw-semibold">
            <span>Masuk</span>
          </button>

        <?= form_close() ?>

      </div><!-- /card -->
    </div>
  </div>
</div>

<!-- JS -->
<script src="<?= base_url('assets/vendor/libs/jquery/jquery.js') ?>"></script>
<script src="<?= base_url('assets/vendor/js/bootstrap.js') ?>"></script>
<script src="<?= base_url('assets/vendor/libs/node-waves/node-waves.js') ?>"></script>
<script>
document.getElementById('toggle-pass').addEventListener('click', function(){
  var inp  = document.getElementById('password');
  var icon = document.getElementById('eye-icon');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.classList.replace('ri-eye-off-line', 'ri-eye-line');
  } else {
    inp.type = 'password';
    icon.classList.replace('ri-eye-line', 'ri-eye-off-line');
  }
});
</script>
</body>
</html>
