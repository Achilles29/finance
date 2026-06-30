<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?php echo html_escape((string)($title ?? 'Print QR Meja')); ?></title>
<style>
*,*::before,*::after{
  box-sizing:border-box;
  margin:0;
  padding:0;
}

/* CONTROL BAR */
#ctrl{
  font-family:'Segoe UI',system-ui,sans-serif;
  position:sticky;
  top:0;
  z-index:200;
  background:#18181b;
  color:#fff;
  padding:8px 16px;
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:8px 14px;
  border-bottom:2px solid #b91c1c;
}

#ctrl .c-title{
  font-weight:700;
  font-size:13px;
  white-space:nowrap;
  letter-spacing:.02em;
}

#ctrl .c-sep{
  width:1px;
  background:#3f3f46;
  align-self:stretch;
}

#ctrl label{
  display:flex;
  align-items:center;
  gap:5px;
  font-size:12px;
  color:#a1a1aa;
  cursor:pointer;
  white-space:nowrap;
}

#ctrl label span{color:#e4e4e7}

#ctrl input[type=text]{
  background:#27272a;
  border:1px solid #52525b;
  color:#f4f4f5;
  padding:5px 10px;
  border-radius:6px;
  font-size:12px;
  outline:none;
  width:200px;
}

#ctrl input[type=text]::placeholder{color:#71717a}

#ctrl input[type=checkbox]{
  accent-color:#b91c1c;
  width:14px;
  height:14px;
}

#ctrl .c-count{
  margin-left:auto;
  font-size:11px;
  color:#71717a;
  white-space:nowrap;
}

#ctrl .btn-print{
  background:#b91c1c;
  color:#fff;
  border:none;
  padding:7px 18px;
  border-radius:7px;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  white-space:nowrap;
  letter-spacing:.02em;
}

#ctrl .btn-print:hover{
  background:#991b1b;
}

/* SCREEN */
@media screen{
  body{
    font-family:'Segoe UI',system-ui,sans-serif;
    background:#525659;
    min-height:100vh;
  }

  .page-wrap{
    width:297mm;
    height:210mm;
    background:#fff;
    margin:16px auto 32px;
    padding:10mm;
    overflow:hidden;
    box-shadow:0 4px 28px rgba(0,0,0,.45);
  }
}

/* PRINT */
@media print{
  @page{
    size:A4 landscape;
    margin:10mm;
  }

  body{
    background:#fff;
    font-family:'Segoe UI',system-ui,sans-serif;
  }

  #ctrl{
    display:none !important;
  }

  .page-wrap{
    width:297mm;
    min-height:210mm;
    background:#fff;
    margin:16px auto 32px;
    padding:10mm;
    box-shadow:0 4px 28px rgba(0,0,0,.45);
  }
}

/* GRID 4 x 2 */
.card-grid{
  width:100%;
  display:grid;
  grid-template-columns:repeat(4, 1fr);
  column-gap:5mm;
  row-gap:8mm;
  align-items:start;
}
/* CARD */
.qr-card{
  width:100%;
  height:82mm;
  background:#fff;
  border:.3mm solid #c4b5fd;
  border-radius:5mm;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  break-inside:avoid;
  page-break-inside:avoid;
}

/* HEADER */
.qr-card__hd{
  height:18mm;
  flex:0 0 18mm;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  text-align:center;
  padding:0 2mm;
  background:linear-gradient(135deg,#9f1d26 0%,#c81e2d 55%,#dc2635 100%);
  color:#fff;
}

.qr-card__brand{
  width:100%;
  font-size:4.5pt;
  font-weight:700;
  line-height:1;
  letter-spacing:.12em;
  text-transform:uppercase;
  opacity:.75;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  margin-bottom:1mm;
}

.qr-card__name{
  width:100%;
  font-size:14pt;
  font-weight:900;
  line-height:1;
  color:#fff;
  text-align:center;
  text-transform:uppercase;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  text-shadow:0 .3mm .8mm rgba(0,0,0,.25);
}

.qr-card__sublabel{
  width:100%;
  font-size:4.5pt;
  font-weight:700;
  line-height:1;
  opacity:.85;
  margin-top:.8mm;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* BODY */
.qr-card__bd{
  flex:1 1 auto;
  min-height:0;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  padding:2mm 2mm 1.5mm;
  gap:1.2mm;
}

.qr-card__qr{
  flex:0 0 auto;
  background:#fff;
  border:.45mm solid #ede9fe;
  border-radius:2.5mm;
  padding:1mm;
  line-height:0;
}

.qr-card__qr img{
  width:32mm;
  height:32mm;
  display:block;
}

/* INSTRUCTION */
.qr-card__instr{
  flex:0 0 auto;
  width:100%;
  text-align:center;
  line-height:1.15;
}

.qr-card__instr-main{
  font-size:6pt;
  font-weight:800;
  color:#1e1b4b;
  letter-spacing:.01em;
  white-space:nowrap;
}

.qr-card__instr-sub{
  font-size:4.3pt;
  color:#6b7280;
  margin-top:.4mm;
  white-space:nowrap;
}

/* CAPACITY */
.qr-card__cap{
  flex:0 0 auto;
  display:inline-flex;
  align-items:center;
  gap:1mm;
  background:#f0fdf4;
  border:.3mm solid #86efac;
  color:#166534;
  border-radius:99mm;
  padding:.45mm 1.8mm;
  font-size:4.5pt;
  font-weight:700;
  white-space:nowrap;
}

/* FOOTER */
.qr-card__ft{
  flex:0 0 5mm;
  height:5mm;
  background:#fff5f5;
  border-top:.25mm solid #fee2e2;
  padding:.8mm 1.5mm;
  text-align:center;
  overflow:hidden;
}

.qr-card__url{
  font-size:3.5pt;
  color:#b91c1c;
  line-height:1;
  font-family:'Courier New',monospace;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* Jika URL disembunyikan via JS, body tetap rapi */
.ctrl-url-el[style*="display: none"]{
  display:none !important;
}
</style>
</head>
<body>

<!-- ── Control bar ────────────────────────────────────────────── -->
<div id="ctrl">
  <span class="c-title">&#128438; Print QR Meja &mdash; A4 Landscape · 4×2</span>
  <div class="c-sep"></div>

  <label>
    <span>Nama Tempat</span>
    <input type="text" id="ctrl-brand" placeholder="Nama restoran / café" maxlength="50">
  </label>

  <div class="c-sep"></div>

  <label>
    <input type="checkbox" id="ctrl-url" checked>
    <span>Tampilkan URL</span>
  </label>
  <label>
    <input type="checkbox" id="ctrl-cap">
    <span>Kapasitas</span>
  </label>

  <span class="c-count" id="ctrl-count"></span>

  <button class="btn-print" onclick="window.print()">Cetak</button>
</div>

<!-- ── A4 Landscape page wrapper ─────────────────────────────── -->
<div class="page-wrap">
  <div class="card-grid" id="card-grid">

  <?php
  $rows     = is_array($rows ?? null) ? $rows : [];
  $settings = is_array($settings ?? null) ? $settings : [];
  foreach ($rows as $row):
    $nama     = (string)($row['nama_meja'] ?? 'Meja');
    $label    = (string)($row['qr_label']  ?? '');
    $url      = (string)($row['qr_url']    ?? '');
    $capacity = (int)($row['capacity']     ?? 0);
    /* Request 300×300 — high quality, CSS scales to 33mm */
    $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&ecc=H&data=' . rawurlencode($url);
  ?>

  <div class="qr-card"
       data-capacity="<?php echo $capacity; ?>"
       data-url="<?php echo html_escape($url); ?>">

    <div class="qr-card__hd">
      <div class="qr-card__brand ctrl-brand-txt">Scan &amp; Order</div>
      <div class="qr-card__name"><?php echo html_escape($nama); ?></div>
      <?php if ($label !== ''): ?>
      <div class="qr-card__sublabel"><?php echo html_escape($label); ?></div>
      <?php endif; ?>
    </div>

    <div class="qr-card__bd">
      <div class="qr-card__qr">
        <img src="<?php echo $qrSrc; ?>"
             alt="QR <?php echo html_escape($nama); ?>"
             loading="lazy">
      </div>

      <div class="qr-card__instr">
        <div class="qr-card__instr-main">📷 Scan untuk memesan</div>
        <div class="qr-card__instr-sub">Arahkan kamera HP ke kode di atas</div>
      </div>

      <?php if ($capacity > 0): ?>
      <div class="qr-card__cap ctrl-cap-el">
        👥 <?php echo $capacity; ?> orang
      </div>
      <?php endif; ?>
    </div>

    <div class="qr-card__ft ctrl-url-el">
      <div class="qr-card__url"><?php echo html_escape($url); ?></div>
    </div>

  </div>

  <?php endforeach; ?>

  <?php if (empty($rows)): ?>
  <div style="grid-column:1/-1;padding:30mm 0;text-align:center;color:#6b7280;font-size:12pt">
    Belum ada meja aktif untuk dicetak.
  </div>
  <?php endif; ?>

  </div><!-- /card-grid -->
</div><!-- /page-wrap -->

<script>
(function(){
  const LS = 'pos_qr_print_';

  const ctrlBrand = document.getElementById('ctrl-brand');
  const ctrlUrl   = document.getElementById('ctrl-url');
  const ctrlCap   = document.getElementById('ctrl-cap');
  const countEl   = document.getElementById('ctrl-count');

  // ── Card count ────────────────────────────────────────────────────────────
  const total = document.querySelectorAll('.qr-card').length;
  countEl.textContent = total + ' meja aktif';

  // ── Restore saved preferences ─────────────────────────────────────────────
  ctrlBrand.value  = localStorage.getItem(LS + 'brand') || '';
  ctrlUrl.checked  = localStorage.getItem(LS + 'url')   !== 'false';
  ctrlCap.checked  = localStorage.getItem(LS + 'cap')   === 'true';

  // ── Apply: brand name ─────────────────────────────────────────────────────
  function applyBrand(){
    const v = ctrlBrand.value.trim();
    localStorage.setItem(LS + 'brand', v);
    document.querySelectorAll('.ctrl-brand-txt').forEach(el =>
      el.textContent = v || 'Scan & Order'
    );
  }

  // ── Apply: show/hide URL footer ───────────────────────────────────────────
  function applyUrl(){
    const show = ctrlUrl.checked;
    localStorage.setItem(LS + 'url', show);
    document.querySelectorAll('.ctrl-url-el').forEach(el =>
      el.style.display = show ? '' : 'none'
    );
  }

  // ── Apply: show/hide capacity ─────────────────────────────────────────────
  function applyCap(){
    const show = ctrlCap.checked;
    localStorage.setItem(LS + 'cap', show);
    document.querySelectorAll('.ctrl-cap-el').forEach(el =>
      el.style.display = show ? '' : 'none'
    );
  }

  // ── Initial render ────────────────────────────────────────────────────────
  applyBrand();
  applyUrl();
  applyCap();

  // ── Listeners ─────────────────────────────────────────────────────────────
  ctrlBrand.addEventListener('input',  applyBrand);
  ctrlUrl.addEventListener('change',   applyUrl);
  ctrlCap.addEventListener('change',   applyCap);
})();
</script>
</body>
</html>
