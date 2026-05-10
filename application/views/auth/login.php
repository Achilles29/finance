<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Finance</title>
<link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<style>
:root {
  --bg-a: #f6efe8;
  --bg-b: #efe3d5;
  --ink: #2f2325;
  --muted: #6e5a5f;
  --line: #e2d2c3;
  --brand: #a9232f;
  --brand-2: #7f1923;
  --ok: #fef2f2;
  --ok-line: #f9caca;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  color: var(--ink);
  font-family: "Segoe UI", "Noto Sans", "Helvetica Neue", Arial, sans-serif;
  background:
    radial-gradient(1200px 500px at -10% -20%, #ffffff 0%, rgba(255,255,255,0) 62%),
    radial-gradient(1000px 500px at 120% 120%, #f9d7ca 0%, rgba(249,215,202,0) 58%),
    linear-gradient(145deg, var(--bg-a), var(--bg-b));
}
.layout {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
}
.hero {
  padding: clamp(24px, 4vw, 52px);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  font-weight: 800;
  letter-spacing: .2px;
}
.brand img { height: 42px; width: auto; object-fit: contain; }
.brand-name {
  font-size: clamp(24px, 3vw, 34px);
  font-family: "Palatino Linotype", "Book Antiqua", Palatino, Georgia, serif;
}
.headline {
  max-width: 640px;
}
.headline h1 {
  margin: 0 0 16px;
  line-height: 1.08;
  font-size: clamp(32px, 5vw, 68px);
  font-family: "Palatino Linotype", "Book Antiqua", Palatino, Georgia, serif;
}
.headline p {
  margin: 0;
  color: var(--muted);
  font-size: clamp(14px, 1.5vw, 19px);
  line-height: 1.7;
  max-width: 560px;
}
.hero-foot {
  color: #7d6469;
  font-size: 13px;
}
.panel-wrap {
  display: grid;
  place-items: center;
  padding: clamp(18px, 3.6vw, 40px);
}
.panel {
  width: min(470px, 100%);
  background: rgba(255,255,255,.86);
  border: 1px solid rgba(255,255,255,.72);
  border-radius: 22px;
  box-shadow: 0 18px 50px rgba(40, 22, 20, 0.16);
  backdrop-filter: blur(6px);
  padding: clamp(20px, 2.3vw, 30px);
}
.panel h2 {
  margin: 0 0 6px;
  font-size: 31px;
  font-family: "Palatino Linotype", "Book Antiqua", Palatino, Georgia, serif;
}
.panel-sub {
  margin: 0 0 20px;
  color: var(--muted);
  font-size: 14px;
}
.alert {
  border: 1px solid var(--ok-line);
  background: var(--ok);
  color: #8f1f2a;
  border-radius: 14px;
  padding: 10px 12px;
  margin-bottom: 14px;
  font-size: 13px;
  line-height: 1.5;
}
.field { margin-bottom: 14px; }
label {
  display: inline-block;
  margin-bottom: 7px;
  font-size: 13px;
  font-weight: 700;
  color: #4a393d;
}
.control {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 13px;
  padding: 12px 13px;
  font-size: 15px;
  background: #fff;
  color: #342529;
  outline: none;
}
.control:focus {
  border-color: #d7a7a2;
  box-shadow: 0 0 0 4px rgba(186, 42, 56, 0.09);
}
.pw {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 9px;
}
.toggle {
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  min-width: 46px;
  cursor: pointer;
  color: #6f5b5f;
}
.toggle:hover { border-color: #d4b7aa; }
.btn {
  width: 100%;
  border: 0;
  border-radius: 13px;
  padding: 12px 14px;
  font-size: 15px;
  font-weight: 800;
  color: #fff;
  cursor: pointer;
  background: linear-gradient(135deg, var(--brand), var(--brand-2));
  box-shadow: 0 10px 22px rgba(164, 30, 43, 0.26);
}
.btn:hover { filter: brightness(1.02); }
@media (max-width: 960px) {
  .layout { grid-template-columns: 1fr; }
  .hero { padding-bottom: 8px; }
  .hero-foot { margin-top: 10px; }
}
</style>
</head>
<body>
<main class="layout">
  <section class="hero">
    <div class="brand">
      <img src="<?= base_url('assets/img/logo.png') ?>" onerror="this.src='<?= base_url('assets/img/logo.jpeg') ?>'" alt="Finance">
      <span class="brand-name">Finance</span>
    </div>
    <div class="headline">
      <h1>Satu Ruang Kerja Keuangan dan Operasional.</h1>
      <p>Kelola data pegawai, absensi, kontrak, penggajian, pembelian, dan inventori dalam alur kerja yang rapi, konsisten, dan terukur.</p>
    </div>
    <div class="hero-foot">Finance Dashboard</div>
  </section>

  <section class="panel-wrap">
    <div class="panel">
      <h2>Masuk</h2>
      <p class="panel-sub">Gunakan akun Anda untuk melanjutkan.</p>

      <?php if (!empty($error_msg)): ?>
      <div class="alert"><?= $error_msg ?></div>
      <?php endif; ?>

      <?= form_open('auth/do_login', ['autocomplete' => 'off']) ?>
        <div class="field">
          <label for="identifier">Username atau Email</label>
          <input class="control" id="identifier" type="text" name="identifier" maxlength="150"
                 value="<?= set_value('identifier') ?>" placeholder="contoh: superadmin" required autofocus>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="pw">
            <input class="control" id="password" type="password" name="password" maxlength="72" placeholder="••••••••" required>
            <button class="toggle" type="button" id="togglePass" aria-label="Tampilkan/Sembunyikan password">👁</button>
          </div>
        </div>

        <button class="btn" type="submit">Masuk ke Dashboard</button>
      <?= form_close() ?>
    </div>
  </section>
</main>

<script>
(function () {
  var btn = document.getElementById('togglePass');
  var input = document.getElementById('password');
  if (!btn || !input) return;
  btn.addEventListener('click', function () {
    input.type = (input.type === 'password') ? 'text' : 'password';
  });
})();
</script>
</body>
</html>
